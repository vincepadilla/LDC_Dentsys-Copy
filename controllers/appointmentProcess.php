<?php
session_start();
include_once('../database/config.php');

// Function to generate new prefixed ID
function generateID($prefix, $table, $column, $con) {
    $query = "SELECT $column FROM $table ORDER BY $column DESC LIMIT 1";
    $result = mysqli_query($con, $query);
    $row = mysqli_fetch_assoc($result);
    if ($row) {
        $lastNum = intval(substr($row[$column], strlen($prefix))) + 1;
    } else {
        $lastNum = 1;
    }
    return $prefix . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
}

// Function to show success notification with alert animation
function showSuccessNotificationPage($title, $message, $appointmentId = '', $redirectUrl = '../views/account.php', $delay = 3000) {
    $appointmentIdHtml = $appointmentId ? "<span class='appointment-id'>$appointmentId</span>" : '';
    $redirectJson = json_encode($redirectUrl);
    $titleJson = json_encode($title);
    $messageJson = json_encode($message);
    $appointmentIdJson = json_encode($appointmentId);
    $delayInt = intval($delay);

    echo <<<HTML
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Appointment Booked</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' rel='stylesheet'>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background: #f5f5f5; }
        
        /* Success Alert Animation Styles */
        .success-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 320px;
            max-width: 450px;
            transform: translateX(500px);
            opacity: 0;
            animation: slideInRight 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        @keyframes slideInRight {
            0% {
                transform: translateX(500px);
                opacity: 0;
            }
            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            0% {
                transform: translateX(0);
                opacity: 1;
            }
            100% {
                transform: translateX(500px);
                opacity: 0;
            }
        }

        .success-alert.slide-out {
            animation: slideOutRight 0.4s ease-in forwards;
        }

        .success-alert-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
        }

        .success-alert-icon::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.5;
            }
        }

        .success-alert-icon i {
            font-size: 24px;
            position: relative;
            z-index: 1;
        }

        .success-alert-content {
            flex: 1;
        }

        .success-alert-content h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 700;
        }

        .success-alert-content p {
            margin: 0;
            font-size: 14px;
            opacity: 0.95;
            line-height: 1.4;
        }

        .appointment-id {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 10px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .success-alert {
                top: 10px;
                right: 10px;
                left: 10px;
                min-width: auto;
                max-width: none;
                padding: 16px 20px;
            }

            .success-alert-icon {
                width: 40px;
                height: 40px;
            }

            .success-alert-icon i {
                font-size: 20px;
            }

            .success-alert-content h3 {
                font-size: 16px;
            }

            .success-alert-content p {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <script>
        const redirectUrl = $redirectJson;
        const notifyDelay = $delayInt;

        function showSuccessNotification(title, message, appointmentId) {
            const alert = document.createElement('div');
            alert.className = 'success-alert';
            alert.id = 'successAlert';

            const appointmentIdHtml = appointmentId ? '<div style="margin-top:8px;">Appointment ID: <span class="appointment-id">' + appointmentId + '</span></div>' : '';

            alert.innerHTML = '<div class="success-alert-icon">'
                + '<i class="fas fa-check-circle"></i>'
                + '</div>'
                + '<div class="success-alert-content">'
                + '<h3>' + title + '</h3>'
                + '<p>' + message + '</p>'
                + appointmentIdHtml
                + '</div>';

            document.body.appendChild(alert);

            // Redirect after delay
            setTimeout(() => {
                alert.classList.add('slide-out');
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 400);
            }, notifyDelay);
        }

        window.addEventListener('DOMContentLoaded', function() {
            showSuccessNotification($titleJson, $messageJson, $appointmentIdJson);
        });
    </script>
</body>
</html>
HTML;
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_SESSION['userID'])) {
        echo "<script>alert('Please login to book an appointment');
        window.location.href='../views/login.php';</script>";
        exit();
    }

    $userID = $_SESSION['userID']; // e.g., U001

    // Personal Info
    $fname = mysqli_real_escape_string($con, trim($_POST['fname']));
    $lname = mysqli_real_escape_string($con, trim($_POST['lname']));
    $age = (int)$_POST['age'];
    $birthdate = mysqli_real_escape_string($con, trim($_POST['birthdate']));
    $gender = mysqli_real_escape_string($con, trim($_POST['gender']));
    $email = mysqli_real_escape_string($con, trim($_POST['email']));
    $phone = mysqli_real_escape_string($con, trim($_POST['phone']));

    // Address
    $address = mysqli_real_escape_string($con, trim($_POST['address']));
    // Appointment Details
    $service_id = mysqli_real_escape_string($con, trim($_POST['service_id']));
    $subService = mysqli_real_escape_string($con, trim($_POST['subService']));
    $subService_id = mysqli_real_escape_string($con, trim($_POST['subservice_id']));

    $team_id = mysqli_real_escape_string($con, trim($_POST['team_id'] ?? 'T001')); 
    $date = mysqli_real_escape_string($con, trim($_POST['date']));
    $time_slot = mysqli_real_escape_string($con, trim($_POST['time']));
    $branch = mysqli_real_escape_string($con, trim($_POST['branch']));

    $timeMap = [
        'firstBatch' => '8:00AM-9:00AM',
        'secondBatch' => '9:00AM-10:00AM',
        'thirdBatch' => '10:00AM-11:00AM',
        'fourthBatch' => '11:00AM-12:00PM',
        'fifthBatch' => '1:00PM-2:00PM',
        'sixthBatch' => '2:00PM-3:00PM',
        'sevenBatch' => '3:00PM-4:00PM',
        'eightBatch' => '4:00PM-5:00PM',
        'nineBatch' => '5:00PM-6:00PM',
        'tenBatch' => '6:00PM-7:00PM',
        'lastBatch' => '7:00PM-8:00PM'
    ];
    $time = $timeMap[$time_slot] ?? '';

    // Payment Details
    $paymentMethod = mysqli_real_escape_string($con, trim($_POST['paymentMethod']));
    $paymentNumber = '';
    $paymentAmount = 0;
    $paymentRefNum = '';
    $paymentAccName = '';

    if ($paymentMethod == 'GCash') {
        $paymentAccName = mysqli_real_escape_string($con, trim($_POST['gcashaccName']));
        $paymentNumber = mysqli_real_escape_string($con, trim($_POST['gcashNum']));
        $paymentAmount = (float)$_POST['gcashAmount'];
        $paymentRefNum = mysqli_real_escape_string($con, trim($_POST['gcashrefNum']));
    } elseif ($paymentMethod == 'PayMaya') {
        $paymentAccName = mysqli_real_escape_string($con, trim($_POST['mayaaccName']));
        $paymentNumber = mysqli_real_escape_string($con, trim($_POST['mayaNum']));
        $paymentAmount = (float)$_POST['mayaAmount'];
        $paymentRefNum = mysqli_real_escape_string($con, trim($_POST['mayarefNum']));
    } elseif ($paymentMethod == 'Cash') {
        // For cash payments, amount is the consultation fee
        $paymentAmount = 500;
    }

    // Handle Proof Image (not required for Cash payments)
    $proofImagePath = '';
    $isCashPayment = ($paymentMethod == 'Cash');
    
    if (!$isCashPayment) {
        $proofField = $paymentMethod == 'GCash' ? 'proofImage' : 'proofImageMaya';

        if (isset($_FILES[$proofField]) && $_FILES[$proofField]['error'] == UPLOAD_ERR_OK) {
            $img = $_FILES[$proofField];
            $imgName = basename($img['name']);
            $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($imgExt, $allowed)) {
                $safeName = uniqid() . "_" . preg_replace("/[^A-Za-z0-9_\-\.]/", '_', $imgName);
                $uploadDir = "../uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $proofImagePath = $uploadDir . $safeName;
                move_uploaded_file($img['tmp_name'], $proofImagePath);
            } else {
                echo "<script>alert('Invalid file type for proof image.');
                window.location.href='../views/index.php#appointment';</script>";
                exit();
            }
        }
    }

    // Validation (proof image not required for Cash payments)
    if (empty($fname) || empty($lname) || empty($gender) || empty($email) || empty($phone) ||
        empty($address) || empty($date) || empty($time) || empty($service_id) || empty($subService) 
        || empty($paymentMethod)) {
        echo "<script>alert('All required fields must be filled');</script>";
        exit();
    }
    
    // Validate service_id is not empty and is a valid format
    if (empty($service_id) || !preg_match('/^[A-Z0-9]+$/', $service_id)) {
        echo "<script>
            alert('Invalid service selected. Please select a valid service and try again.');
            window.location.href='../views/index.php#appointment';
        </script>";
        exit();
    }
    
    // For non-cash payments, proof image is required
    if (!$isCashPayment && empty($proofImagePath)) {
        echo "<script>alert('Please upload payment proof image.');</script>";
        exit();
    }
    
    // Validate reference number for non-cash payments (check if it already exists)
    if (!$isCashPayment && !empty($paymentRefNum)) {
        $checkRefQuery = "SELECT payment_id, appointment_id, method, status 
                         FROM payment 
                         WHERE reference_no = ? AND method = ? 
                         LIMIT 1";
        $checkRefStmt = $con->prepare($checkRefQuery);
        if ($checkRefStmt) {
            $checkRefStmt->bind_param("ss", $paymentRefNum, $paymentMethod);
            $checkRefStmt->execute();
            $checkRefResult = $checkRefStmt->get_result();
            
            if ($checkRefResult->num_rows > 0) {
                $existingPayment = $checkRefResult->fetch_assoc();
                $checkRefStmt->close();
                error_log("Duplicate reference number detected: $paymentRefNum for method $paymentMethod");
                echo "<script>
                    alert('This reference number has already been used. Please verify your reference number and try again.\\n\\nPayment ID: " . htmlspecialchars($existingPayment['payment_id']) . "\\nStatus: " . htmlspecialchars($existingPayment['status']) . "');
                    window.location.href='../views/index.php#appointment';
                </script>";
                exit();
            }
            $checkRefStmt->close();
        } else {
            error_log("Reference number check prepare failed: " . $con->error);
        }
    }
    
    // Final safety check: Verify clinic closure status (validation should have been done in payment.php, but this is a security measure)
    $clinicClosed = false;
    $checkTable = "SHOW TABLES LIKE 'clinic_closures'";
    $tableExists = mysqli_query($con, $checkTable);
    
    if ($tableExists && mysqli_num_rows($tableExists) > 0) {
        $closureQuery = "SELECT closure_type, reason FROM clinic_closures WHERE closure_date = ? AND status = 'active' LIMIT 1";
        $closureStmt = $con->prepare($closureQuery);
        if ($closureStmt) {
            $closureStmt->bind_param("s", $date);
            $closureStmt->execute();
            $closureResult = $closureStmt->get_result();
            
            if ($closureRow = $closureResult->fetch_assoc()) {
                if ($closureRow['closure_type'] === 'full_day') {
                    $clinicClosed = true;
                }
            }
            $closureStmt->close();
        }
    }
    
    // Final safety check: Verify time slot is not blocked
    $slotBlocked = false;
    $blockedSlotQuery = "SELECT block_id FROM blocked_time_slots WHERE date = ? AND time_slot = ? LIMIT 1";
    $blockedStmt = $con->prepare($blockedSlotQuery);
    if ($blockedStmt) {
        $blockedStmt->bind_param("ss", $date, $time_slot);
        $blockedStmt->execute();
        $blockedResult = $blockedStmt->get_result();
        $slotBlocked = ($blockedResult->num_rows > 0);
        $blockedStmt->close();
    }
    
    // If validation fails (should not happen if payment.php validation worked, but safety check)
    if ($clinicClosed || $slotBlocked) {
        echo "<script>
            alert('Appointment booking failed: The selected date or time slot is no longer available. Please select another appointment.');
            window.location.href='../views/index.php';
        </script>";
        exit();
    }

    // === CHECK IF PATIENT EXISTS ===
    $userID_escaped_check = mysqli_real_escape_string($con, $userID);
    $checkPatientQuery = "SELECT patient_id FROM patient_information WHERE user_id = '$userID_escaped_check' LIMIT 1";
    $checkPatientResult = mysqli_query($con, $checkPatientQuery);
    $existingPatient = mysqli_fetch_assoc($checkPatientResult);
    $isExistingPatient = !empty($existingPatient);

    if ($isExistingPatient) {
        // Patient already exists, use existing patient_id
        $patient_id = $existingPatient['patient_id'];
        
        // Update patient information in case details changed
        $updatePatient = "UPDATE patient_information 
            SET first_name = '$fname', 
                last_name = '$lname', 
                birthdate = '$birthdate', 
                gender = '$gender', 
                phone = '$phone', 
                email = '$email', 
                address = '$address' 
            WHERE patient_id = '$patient_id'";
        
        $patientInsertSuccess = mysqli_query($con, $updatePatient);
    } else {
        // Patient doesn't exist, create new patient record
        $patient_id = generateID('P', 'patient_information', 'patient_id', $con);
        $insertPatient = "INSERT INTO patient_information 
            (patient_id, user_id, first_name, last_name, birthdate, gender, phone, email, address) 
            VALUES 
            ('$patient_id', '$userID', '$fname', '$lname', '$birthdate', '$gender', '$phone', '$email', '$address')";
        
        $patientInsertSuccess = mysqli_query($con, $insertPatient);
    }

    if ($patientInsertSuccess) {
        // === DOUBLE BOOKING CHECK ===
        // Check if there's already an appointment with the same date, time_slot, and dentist
        // Only allow booking if the existing appointment is Cancelled or No-Show
        // All other statuses (Pending, Confirmed, Reschedule, Completed) will block the slot
        $checkDoubleBooking = "SELECT appointment_id FROM appointments 
                               WHERE appointment_date = ? 
                               AND time_slot = ? 
                               AND team_id = ? 
                               AND status NOT IN ('Cancelled', 'No-Show') 
                               LIMIT 1";
        $checkStmt = $con->prepare($checkDoubleBooking);
        if ($checkStmt) {
            $checkStmt->bind_param("sss", $date, $time_slot, $team_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $existingAppointment = $checkResult->fetch_assoc();
                $checkStmt->close();
                error_log("Double booking prevented: Appointment ID " . $existingAppointment['appointment_id'] . " already exists for date=$date, time_slot=$time_slot, team_id=$team_id");
                echo "<script>
                    alert('This time slot is already booked by another patient. Please select another appointment time.');
                    window.location.href='../views/index.php#appointment';
                </script>";
                exit();
            }
            $checkStmt->close();
        } else {
            // If prepare failed, log error but don't block (fallback to database constraint if exists)
            error_log("Double booking check prepare failed: " . $con->error);
        }
        
        // === VALIDATE SERVICE_ID EXISTS ===
        // Check if the service_id exists in the services table to prevent foreign key constraint failure
        $checkServiceQuery = "SELECT service_id FROM services WHERE service_id = ? LIMIT 1";
        $checkServiceStmt = $con->prepare($checkServiceQuery);
        if ($checkServiceStmt) {
            $checkServiceStmt->bind_param("s", $service_id);
            $checkServiceStmt->execute();
            $checkServiceResult = $checkServiceStmt->get_result();
            
            if ($checkServiceResult->num_rows == 0) {
                $checkServiceStmt->close();
                error_log("Invalid service_id: $service_id does not exist in services table");
                echo "<script>
                    alert('Invalid service selected. Please select a valid service and try again.');
                    window.location.href='../views/index.php#appointment';
                </script>";
                exit();
            }
            $checkServiceStmt->close();
        } else {
            // If prepare failed, log error but continue (database will catch it if constraint exists)
            error_log("Service validation check prepare failed: " . $con->error);
        }
        
        // === APPOINTMENT INSERT ===
        // For Cash payments, create appointment with "Pending" status but marked as cash reservation
        // The appointment will remain "Pending" until cash payment is confirmed at branch
        // For GCash/PayMaya, create appointment with "Pending" status (normal flow)
        $appointment_id = generateID('A', 'appointments', 'appointment_id', $con);
        $appointmentStatus = 'Pending'; // Use Pending for both, cash payment will be verified on arrival

        // If Cash payment: generate alphanumeric ticket code and compute expiry (appointment end + grace)
        $ticketCode = null;
        $ticketExpiresAt = null;
        if ($isCashPayment) {
            $ticketCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            // Parse end time from appointment time like '9:00AM-10:00AM'
            $endTime = '11:59PM';
            if (strpos($time, '-') !== false) {
                $parts = explode('-', $time);
                $endTime = trim($parts[1]);
            }
            $endDateTime = DateTime::createFromFormat('Y-m-d g:iA', "$date $endTime");
            if (!$endDateTime) {
                $endDateTime = new DateTime($date . ' 23:59:00');
            }
            // Grace period after end time (e.g., 30 minutes)
            $endDateTime->modify('+30 minutes');
            $ticketExpiresAt = $endDateTime->format('Y-m-d H:i:s');
        }

        // Check if the DB has ticket columns (migration may not have been run)
        $colCheck = mysqli_query($con, "SHOW COLUMNS FROM appointments LIKE 'ticket_code'");
        $hasTicketCols = ($colCheck && mysqli_num_rows($colCheck) > 0);

        if ($hasTicketCols) {
            $insertAppointment = "INSERT INTO appointments 
                (appointment_id, patient_id, team_id, service_id, branch, appointment_date, appointment_time, time_slot, status, ticket_code, ticket_expires_at)
                VALUES 
                ('$appointment_id', '$patient_id', '$team_id', '$service_id', '$branch', '$date', '$time', '$time_slot', '$appointmentStatus', " . ($ticketCode ? "'" . $ticketCode . "'" : "NULL") . ", " . ($ticketExpiresAt ? "'" . $ticketExpiresAt . "'" : "NULL") . ")";
        } else {
            // Fallback for older DB without ticket columns
            $insertAppointment = "INSERT INTO appointments 
                (appointment_id, patient_id, team_id, service_id, branch, appointment_date, appointment_time, time_slot, status)
                VALUES 
                ('$appointment_id', '$patient_id', '$team_id', '$service_id', '$branch', '$date', '$time', '$time_slot', '$appointmentStatus')";
        }

        $appointmentInserted = mysqli_query($con, $insertAppointment);
        
        if (!$appointmentInserted) {
            $errorMsg = mysqli_error($con);
            $errorCode = mysqli_errno($con);
            error_log('Appointment insert error: ' . $errorMsg . ' (Error Code: ' . $errorCode . ')');
            error_log('Failed query: ' . $insertAppointment);
            error_log('Service ID used: ' . $service_id);
            
            // Check if it's a foreign key constraint error
            if ($errorCode == 1452) {
                echo "<script>
                    alert('Invalid service selected. The service you selected is no longer available. Please select a different service and try again.');
                    window.location.href='../views/index.php#appointment';
                </script>";
            } else {
                echo "<script>
                    alert('Error booking appointment: " . addslashes($errorMsg) . ". Please try again.');
                    window.location.href='../views/index.php#appointment';
                </script>";
            }
            exit();
        }

        // === PAYMENT INSERT ===
        if ($isCashPayment) {
            // For Cash: Create payment record linked to reserved appointment
            // Appointment status is "Reserved" - will be changed to "Pending" when payment is confirmed
            $payment_id = generateID('PY', 'payment', 'payment_id', $con);
            $insertPayment = "INSERT INTO payment 
                (payment_id, appointment_id, method, account_name, account_number, amount, reference_no, proof_image, status)
                VALUES 
                ('$payment_id', '$appointment_id', '$paymentMethod', '', '', '$paymentAmount', '', '', 'pending')";

            if (mysqli_query($con, $insertPayment)) {
                // Send ticket email to patient with confirm/cancel links
                try {
                    require_once '../libraries/PhpMailer/src/Exception.php';
                    require_once '../libraries/PhpMailer/src/PHPMailer.php';
                    require_once '../libraries/PhpMailer/src/SMTP.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? '';
                    $baseUrl = $host ? $protocol . '://' . $host : '';
                    $confirmLink = $baseUrl . '/controllers/ticket_action.php?action=confirm&appointment_id=' . urlencode($appointment_id) . '&ticket=' . urlencode($ticketCode);
                    $cancelLink = $baseUrl . '/controllers/ticket_action.php?action=cancel&appointment_id=' . urlencode($appointment_id) . '&ticket=' . urlencode($ticketCode);

                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'padillavincehenrick@gmail.com';
                    $mail->Password = 'glxd csoa ispj bvjg';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;

                    $mail->setFrom('padillavincehenrick@gmail.com', 'Dental Clinic');
                    $mail->addAddress($email, trim($fname . ' ' . $lname));
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Appointment Ticket Code';
                    // Generate QR image for email (QR contains the appointment_id for direct scanner lookup)
                    // Using higher resolution (300x300) for better scanner readability
                    $qrImgUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($appointment_id);

                    $mail->Body =
                        "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #111827;'>" .
                            "<h2 style='margin: 0 0 10px 0;'>Hello " . htmlspecialchars($fname) . "</h2>" .
                            "<p style='margin: 0 0 10px 0;'>Your appointment has been reserved. Your ticket code is: <strong>" . htmlspecialchars($ticketCode) . "</strong></p>" .
                            "<p style='margin: 0 0 14px 0;'><strong>Appointment:</strong> " . htmlspecialchars($date) . " at " . htmlspecialchars($time) . "</p>" .

                            "<div style='margin: 16px 0; padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; text-align: center;'>" .
                                "<div style='font-weight: 700; margin-bottom: 8px;'>Your QR Code</div>" .
                                "<img src='" . htmlspecialchars($qrImgUrl) . "' alt='Appointment QR Code' style='width: 300px; height: 300px; border: 8px solid #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); display: block; margin: 0 auto;' />" .
                                "<div style='font-size: 12px; color: #6b7280; margin-top: 10px;'>Show this QR code on your appointment day for scanning and payment.</div>" .
                            "</div>" .

                            "<p style='margin: 0 0 10px 0;'>On your appointment day, the cashier/dentist will scan this QR code in the clinic to verify your ticket and then process payment.</p>" .
                            "<p style='margin: 0 0 10px 0;'>If you will attend, you can confirm now: <a href='" . $confirmLink . "'>Confirm Appointment</a></p>" .
                            "<p style='margin: 0 0 10px 0;'>If you wish to cancel, click: <a href='" . $cancelLink . "'>Cancel Appointment</a></p>" .
                        "</div>";

                    $mail->send();
                } catch (Exception $ex) {
                    error_log('Ticket email send failed: ' . ($mail->ErrorInfo ?? $ex->getMessage()));
                }

                // Check if appointment is tomorrow
                $appointmentDate = new DateTime($date);
                $today = new DateTime('today');
                $tomorrow = clone $today;
                $tomorrow->modify('+1 day');
                $isTomorrow = ($appointmentDate->format('Y-m-d') === $tomorrow->format('Y-m-d'));
                
                // For tomorrow appointments with cash: require immediate payment
                // For other appointments with cash: maintain 2-day deadline
                if ($isTomorrow) {
                    $todayFormatted = $today->format('F j, Y');
                    showSuccessNotificationPage(
                        'Appointment Slot Reserved for Tomorrow!',
                        "IMPORTANT: You must pay TODAY ($todayFormatted) at the branch, otherwise your reservation will be cancelled.<br><br>Your Ticket Code: $ticketCode<br>Present this code at reception on your appointment day.",
                        $appointment_id,
                        '../views/account.php',
                        4000
                    );
                } else {
                    // Calculate deadline date (2 days before appointment)
                    $deadlineDate = clone $appointmentDate;
                    $deadlineDate->modify('-2 days');
                    $deadlineFormatted = $deadlineDate->format('F j, Y');
                    
                    showSuccessNotificationPage(
                        'Appointment Slot Reserved!',
                        "Please pay at least 2 days before your appointment date ($date) at the branch. Deadline: $deadlineFormatted<br><br>Your Ticket Code: $ticketCode<br>Present this code at reception on your appointment day.",
                        $appointment_id,
                        '../views/account.php',
                        4000
                    );
                }
            } else {
                error_log('Payment error: ' . mysqli_error($con));
                echo "<script>alert('Error saving reservation. Try again.');
                window.location.href='../views/index.php#appointment';</script>";
            }
        } else {
            // For GCash and PayMaya: Normal flow with appointment
            $payment_id = generateID('PY', 'payment', 'payment_id', $con);
            $insertPayment = "INSERT INTO payment 
                (payment_id, appointment_id, method, account_name, account_number, amount, reference_no, proof_image, status)
                VALUES 
                ('$payment_id', '$appointment_id', '$paymentMethod', '$paymentAccName', '$paymentNumber', '$paymentAmount', '$paymentRefNum', '$proofImagePath', 'pending')";

            if (mysqli_query($con, $insertPayment)) {
                // Show success notification with check animation
                showSuccessNotificationPage(
                    'Appointment Successfully Booked!',
                    'Your appointment has been confirmed and is pending payment verification.',
                    $appointment_id,
                    '../views/account.php',
                    3000
                );
            } else {
                error_log('Payment error: ' . mysqli_error($con));
                echo "<script>alert('Error saving payment. Try again.');
                window.location.href='../views/index.php#appointment';</script>";
            }
        }
    } else {
        error_log('Patient error: ' . mysqli_error($con));
        $errorMsg = $isExistingPatient ? 'Error updating patient info. Try again.' : 'Error saving patient info. Try again.';
        echo "<script>alert('$errorMsg');
        window.location.href='../views/index.php#appointment';</script>";
    }
} else {
    header("Location: ../views/index.php");
    exit();
}

mysqli_close($con);
?>
