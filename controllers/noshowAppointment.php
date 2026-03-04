<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libraries/PhpMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/SMTP.php';

include_once __DIR__ . '/../database/config.php';

header('Content-Type: application/json');

function sendNoShowJson($data) {
    echo json_encode($data);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {

    $appointment_id = trim($_POST['appointment_id']);

    if (empty($appointment_id)) {
        sendNoShowJson([
            'success' => false,
            'message' => 'Error: Appointment ID is required.'
        ]);
    }

    /** CHECK IF APPOINTMENT EXISTS **/
    $stmtCheck = $con->prepare("
        SELECT a.*, 
               p.first_name, p.last_name, p.email,
               s.service_category, s.sub_service,
               d.first_name AS dentist_first, d.last_name AS dentist_last
        FROM appointments a
        LEFT JOIN patient_information p ON a.patient_id = p.patient_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
        WHERE a.appointment_id = ?
    ");
    $stmtCheck->bind_param("s", $appointment_id);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    $appointment = $result->fetch_assoc();
    $stmtCheck->close();

    if (!$appointment) {
        sendNoShowJson([
            'success' => false,
            'message' => 'Appointment not found.'
        ]);
    }

    /** UPDATE STATUS TO NO-SHOW **/
    $stmtUpdate = $con->prepare("UPDATE appointments SET status = 'No-Show' WHERE appointment_id = ?");
    $stmtUpdate->bind_param("s", $appointment_id);

    if (!$stmtUpdate->execute()) {
        $error = $stmtUpdate->error;
        $stmtUpdate->close();
        sendNoShowJson([
            'success' => false,
            'message' => 'Failed to update appointment status: ' . $error
        ]);
    }

    $stmtUpdate->close();

    /** EMAIL VARIABLES **/
    $patient_name = trim(($appointment['first_name'] ?? '') . " " . ($appointment['last_name'] ?? ''));
    $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : ($appointment['service_category'] ?? '');
    $dentist = trim(($appointment['dentist_first'] ?? '') . " " . ($appointment['dentist_last'] ?? ''));
    $email = $appointment['email'] ?? '';

    $emailSent = false;

    if (!empty($email)) {
        /** SEND EMAIL **/
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
            $mail->addAddress($email, $patient_name);

            $mail->isHTML(true);
            $mail->Subject = 'Missed Appointment Notice';

            $mail->Body = "
                <h3>Hi {$patient_name},</h3>
                <p>It appears that you missed your scheduled dental appointment.</p>

                <p>
                <strong>Service:</strong> {$service}<br>
                <strong>Dentist:</strong> {$dentist}<br>
                <strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appointment_date'])) . "<br>
                <strong>Time:</strong> {$appointment['appointment_time']}
                </p>

                <p>If you wish to reschedule, please log in to your account or contact our clinic.</p>
                <p>Thank you for your understanding.</p>
            ";

            $mail->send();
            $emailSent = true;
        } catch (Exception $e) {
            // Log error but still consider status update successful
            error_log('No-Show email failed: ' . $mail->ErrorInfo);
        }
    }

    $message = $emailSent
        ? 'Status updated to No-Show and email sent.'
        : 'Status updated to No-Show. Email not sent (no email on file or sending failed).';

    sendNoShowJson([
        'success' => true,
        'status'  => 'success',
        'message' => $message
    ]);

} else {
    sendNoShowJson([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>
