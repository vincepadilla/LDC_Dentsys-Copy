<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

include_once("../database/config.php");

header('Content-Type: application/json');

// Function to generate new prefixed ID
if (!function_exists('generateID')) {
    function generateID($prefix, $table, $column, $con) {
        $query = "SELECT $column FROM $table ORDER BY $column DESC LIMIT 1";
        $result = mysqli_query($con, $query);
        $row = mysqli_fetch_assoc($result);
        if ($row && !empty($row[$column])) {
            $lastNum = intval(substr($row[$column], strlen($prefix))) + 1;
        } else {
            $lastNum = 1;
        }
        return $prefix . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {
    $appointment_id = $_POST['appointment_id'];

    // Get appointment details with joins to patient_information, services, and multidisciplinary_dental_team
    $stmt = $con->prepare("SELECT a.*, 
                                   p.first_name, p.last_name, p.email, p.user_id,
                                   s.service_category, s.sub_service,
                                   d.first_name as dentist_first, d.last_name as dentist_last
                           FROM appointments a 
                           LEFT JOIN patient_information p ON a.patient_id = p.patient_id 
                           LEFT JOIN services s ON a.service_id = s.service_id
                           LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                           WHERE a.appointment_id = ?");
    $stmt->bind_param("s", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if ($appointment) {
        // Update status to Confirmed
        $stmtUpdate = $con->prepare("UPDATE appointments SET status = 'Confirmed' WHERE appointment_id = ?");
        $stmtUpdate->bind_param("s", $appointment_id);

        if ($stmtUpdate->execute()) {
            // Prepare patient name and service details
            $patient_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
            $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
            $dentist = trim($appointment['dentist_first'] . ' ' . $appointment['dentist_last']);
            $email = $appointment['email'];
            $user_id = $appointment['user_id'];
            
            // === NOTIFICATION INSERT ===
            if (!empty($user_id)) {
                $notification_id = generateID('N', 'notifications', 'notification_id', $con);
                $dentistName = 'Dr. ' . $dentist;
                $dentistName = mysqli_real_escape_string($con, $dentistName);
                $appointment_date = mysqli_real_escape_string($con, $appointment['appointment_date']);
                $appointment_time = mysqli_real_escape_string($con, $appointment['appointment_time']);
                $user_id = mysqli_real_escape_string($con, $user_id);
                
                $insertNotification = "INSERT INTO notifications 
                    (notification_id, user_id, type, appointment_date, appointment_time, dentist_name, is_read, created_at)
                    VALUES 
                    ('$notification_id', '$user_id', 'confirmed', '$appointment_date', '$appointment_time', '$dentistName', 0, NOW())";
                
                mysqli_query($con, $insertNotification);
            }
            
            // Send email
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
                $mail->Subject = 'Appointment Confirmed';

                $mail->Body = "
                    <h3>Hi {$patient_name},</h3>
                    <p>Your appointment has been <strong>confirmed</strong>.</p>
                    <p><strong>Service:</strong> {$service}<br>
                    <strong>Dentist:</strong> {$dentist}<br>
                    <strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appointment_date'])) . "<br>
                    <strong>Time:</strong> {$appointment['appointment_time']}<br>
                    <strong>Branch:</strong> {$appointment['branch']}</p>
                    <p>Thank you for choosing our clinic!</p>
                ";

                $mail->send();

                // Return JSON success response
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Appointment confirmed and email sent successfully.'
                ]);
                exit();
            } catch (Exception $e) {
                // Appointment was confirmed but email failed - still return success
                error_log("Email sending failed: " . $mail->ErrorInfo);
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Appointment confirmed, but email failed to send.'
                ]);
                exit();
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error updating appointment: ' . $stmtUpdate->error
            ]);
            exit();
        }

        $stmtUpdate->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found.'
        ]);
        exit();
    }

    $con->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Appointment ID is required.'
    ]);
    exit();
}
