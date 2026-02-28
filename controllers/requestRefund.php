<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

session_start();
require_once __DIR__ . '/../database/config.php';

// Detect AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function sendJsonResponse(bool $success, string $message, array $extra = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit();
}

// Ensure user is logged in
if (!isset($_SESSION['userID'])) {
    if ($isAjax) {
        sendJsonResponse(false, 'You are not logged in.');
    }
    header("Location: ../views/login.php");
    exit();
}

$user_id = $_SESSION['userID'];
$appointment_id = $_POST['appointment_id'] ?? null;
$payment_id = $_POST['payment_id'] ?? null;

if (!$appointment_id || !$payment_id) {
    $msg = 'Missing appointment or payment information.';
    if ($isAjax) {
        sendJsonResponse(false, $msg);
    }
    echo "<script>alert('{$msg}'); window.location.href='../views/account.php';</script>";
    exit();
}

// Verify appointment belongs to user and is cancelled, and fetch details
$appointmentQuery = $con->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.branch, a.status,
           p.payment_id, p.method, p.amount, p.status AS payment_status,
           pi.first_name, pi.last_name, pi.email,
           s.service_category, s.sub_service,
           d.first_name AS dentist_first, d.last_name AS dentist_last, d.email AS dentist_email
    FROM appointments a
    INNER JOIN patient_information pi ON a.patient_id = pi.patient_id
    LEFT JOIN payment p ON a.appointment_id = p.appointment_id
    LEFT JOIN services s ON a.service_id = s.service_id
    LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
    WHERE a.appointment_id = ? 
      AND p.payment_id = ?
      AND pi.user_id = ?
");

if (!$appointmentQuery) {
    $msg = 'Failed to prepare appointment lookup.';
    if ($isAjax) {
        sendJsonResponse(false, $msg);
    }
    echo "<script>alert('{$msg}'); window.location.href='../views/account.php';</script>";
    exit();
}

$appointmentQuery->bind_param("sss", $appointment_id, $payment_id, $user_id);
$appointmentQuery->execute();
$result = $appointmentQuery->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    $msg = 'Appointment or payment not found, or you do not have permission to request a refund for this appointment.';
    if ($isAjax) {
        sendJsonResponse(false, $msg);
    }
    echo "<script>alert('{$msg}'); window.location.href='../views/account.php';</script>";
    exit();
}

if ($appointment['status'] !== 'Cancelled') {
    $msg = 'Refunds can only be requested for cancelled appointments.';
    if ($isAjax) {
        sendJsonResponse(false, $msg);
    }
    echo "<script>alert('{$msg}'); window.location.href='../views/account.php';</script>";
    exit();
}

// Only allow refund requests for paid digital payments
$paymentStatus = strtolower($appointment['payment_status'] ?? '');
if ($paymentStatus !== 'paid') {
    $msg = 'Refunds can only be requested for successfully paid transactions.';
    if ($isAjax) {
        sendJsonResponse(false, $msg);
    }
    echo "<script>alert('{$msg}'); window.location.href='../views/account.php';</script>";
    exit();
}

// Check that appointment date is at least 2 days away
try {
    $today = new DateTime('today');
    $appointmentDate = new DateTime($appointment['appointment_date']);
    $interval = $today->diff($appointmentDate);
    $daysUntilAppointment = (int)$interval->format('%r%a'); // positive if in future
} catch (Exception $e) {
    $msg = 'Invalid appointment date. Please contact the clinic.';
    if ($isAjax) {
        sendJsonResponse(false, $msg);
    }
    echo "<script>alert('{$msg}'); window.location.href='../views/account.php';</script>";
    exit();
}

if ($daysUntilAppointment < 2) {
    $msg = 'Refunds can only be requested at least 2 days before the appointment date.';
    if ($isAjax) {
        sendJsonResponse(false, $msg);
    }
    echo "<script>alert('{$msg}'); window.location.href='../views/account.php';</script>";
    exit();
}

// Ensure refund_requests table exists
$createTable = "CREATE TABLE IF NOT EXISTS refund_requests (
  id varchar(10) NOT NULL,
  payment_id varchar(10) NOT NULL,
  appointment_id varchar(10) NOT NULL,
  user_id varchar(10) NOT NULL,
                                          status enum('pending','processed','refunded') NOT NULL DEFAULT 'pending',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY payment_id (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($con, $createTable);
@mysqli_query($con, "ALTER TABLE refund_requests MODIFY status ENUM('pending','processed','refunded') NOT NULL DEFAULT 'pending'");

// Generate next refund request id (RF001, RF002, ...)
$idResult = mysqli_query($con, "SELECT id FROM refund_requests WHERE id LIKE 'RF%' ORDER BY id DESC LIMIT 1");
$nextNum = 1;
if ($idResult && $row = mysqli_fetch_assoc($idResult)) {
    $num = (int) substr($row['id'], 2);
    $nextNum = $num + 1;
}
$refund_request_id = 'RF' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

// Insert refund request (one per payment; duplicate = already requested)
$insertStmt = $con->prepare("INSERT INTO refund_requests (id, payment_id, appointment_id, user_id, status) VALUES (?, ?, ?, ?, 'pending')");
if ($insertStmt) {
    $insertStmt->bind_param("ssss", $refund_request_id, $payment_id, $appointment_id, $user_id);
    if (!$insertStmt->execute()) {
        $err = $insertStmt->error;
        $insertStmt->close();
        if (strpos($err, 'Duplicate') !== false) {
            $msg = 'A refund request for this payment has already been submitted.';
        } else {
            $msg = 'Unable to save refund request. Please try again.';
        }
        if ($isAjax) {
            sendJsonResponse(false, $msg);
        }
        echo "<script>alert('" . addslashes($msg) . "'); window.location.href='../views/account.php';</script>";
        exit();
    }
    $insertStmt->close();
}

// Prepare email data
$patient_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
$service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
$dentist = trim($appointment['dentist_first'] . ' ' . $appointment['dentist_last']);
if (empty($dentist)) {
    $dentist = 'Assigned Dentist';
}
$appointment_date_formatted = $appointmentDate->format('F j, Y');
$appointment_time = $appointment['appointment_time'];
$branch = $appointment['branch'];
$payment_method = $appointment['method'];
$amount = number_format((float)$appointment['amount'], 2);

// Get an admin email (fallback to clinic email if none)
$adminEmail = 'landerodentalclinic@gmail.com';
$adminName = 'Clinic Admin';
$adminQuery = $con->query("SELECT first_name, last_name, email FROM user_account WHERE role = 'admin' LIMIT 1");
if ($adminQuery && $adminRow = $adminQuery->fetch_assoc()) {
    $adminEmail = $adminRow['email'] ?: $adminEmail;
    $adminName = trim(($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? '')) ?: $adminName;
}

// Link to transactions page with highlighted payment ID
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$transactionsUrl = $scheme . '://' . $host . '/admin/transactions.php?refund_payment_id=' . urlencode($payment_id);

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mlanderodentalclinic@gmail.com';
    $mail->Password = 'xrfp cpvv ckdv jmht';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
    $mail->addAddress($adminEmail, $adminName);

    // Email the dentist using their registered email (SMTP)
    if (!empty($appointment['dentist_email'])) {
        $mail->addAddress($appointment['dentist_email'], $dentist);
    } else {
        // If dentist email not found, try to get it from team table
        $dentistQuery = $con->prepare("
            SELECT email FROM multidisciplinary_dental_team 
            WHERE first_name = ? AND last_name = ?
            LIMIT 1
        ");
        if ($dentistQuery) {
            $dentistQuery->bind_param("ss", $appointment['dentist_first'], $appointment['dentist_last']);
            $dentistQuery->execute();
            $dentistResult = $dentistQuery->get_result();
            if ($dentistRow = $dentistResult->fetch_assoc()) {
                if (!empty($dentistRow['email'])) {
                    $mail->addAddress($dentistRow['email'], $dentist);
                }
            }
            $dentistQuery->close();
        }
    }

    $mail->isHTML(true);
    $mail->Subject = "Refund Request - Payment {$payment_id} (Appointment {$appointment_id})";

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #166088;'>Refund Request Submitted</h2>

            <p>The following patient has requested a refund for a paid appointment that was cancelled at least 2 days before the appointment date.</p>

            <h3 style='color: #166088;'>Patient Details</h3>
            <p><strong>Name:</strong> {$patient_name}<br>
               <strong>Email:</strong> " . htmlspecialchars($appointment['email']) . "</p>

            <h3 style='color: #166088;'>Appointment Details</h3>
            <p><strong>Appointment ID:</strong> {$appointment_id}<br>
               <strong>Service:</strong> {$service}<br>
               <strong>Dentist:</strong> {$dentist}<br>
               <strong>Date:</strong> {$appointment_date_formatted}<br>
               <strong>Time:</strong> {$appointment_time}<br>
               <strong>Branch:</strong> {$branch}</p>

            <h3 style='color: #166088;'>Payment Details</h3>
            <p><strong>Payment ID:</strong> {$payment_id}<br>
               <strong>Method:</strong> {$payment_method}<br>
               <strong>Amount:</strong> ₱{$amount}</p>

            <p>You can review this transaction in the admin panel here:<br>
               <a href='{$transactionsUrl}' style='color: #1d4ed8;' target='_blank'>Open Payment Transactions (highlight refund request)</a>
            </p>

            <p>Please process the refund as appropriate and update the payment status to <strong>Refunded</strong> in the admin transactions page once completed.</p>

            <p>Best regards,<br>
            <strong>Landero Dental Clinic System</strong></p>
        </div>
    ";

    $mail->send();

    $successMessage = 'Your refund request has been sent to the clinic. They will review your payment and process the refund if eligible.';

    if ($isAjax) {
        sendJsonResponse(true, $successMessage, [
            'payment_id' => $payment_id,
            'appointment_id' => $appointment_id,
        ]);
    }

    echo "<script>alert('" . addslashes($successMessage) . "'); window.location.href='../views/account.php';</script>";
} catch (Exception $e) {
    $msg = 'We were unable to send your refund request email. Please try again later or contact the clinic directly.';

    if ($isAjax) {
        sendJsonResponse(false, $msg, [
            'emailError' => $mail->ErrorInfo,
        ]);
    }

    echo "<script>alert('" . addslashes($msg . ' Error: ' . $mail->ErrorInfo) . "'); window.location.href='../views/account.php';</script>";
}

?>

