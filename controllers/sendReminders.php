<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PhpMailer/src/Exception.php';
require '../PhpMailer/src/PHPMailer.php';
require '../PhpMailer/src/SMTP.php';

include_once("config.php");

// Time window: now up to 24 hours ahead
$now = date('Y-m-d H:i:s');
$reminderWindow = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Get appointments in window, JOIN user table for email
$query = "SELECT a.*, u.email 
          FROM tbl_appointments a
          JOIN users u ON a.userID = u.user_id
          WHERE 
            CONCAT(a.appointment_date, ' ', 
              STR_TO_DATE(SUBSTRING_INDEX(a.appointment_time, '-', 1), '%l:%i%p')
            ) BETWEEN ? AND ?
          AND a.status IN ('Confirmed', 'Reschedule')
          AND a.reminder_sent = 0";

$stmt = $con->prepare($query);
$stmt->bind_param("ss", $now, $reminderWindow);
$stmt->execute();
$result = $stmt->get_result();

while ($appointment = $result->fetch_assoc()) {

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'Dental Buddies Clinic');
        $mail->addAddress($appointment['email'], $appointment['patient_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Reminder';

        $mail->Body = "
            <h3>Hi {$appointment['patient_name']},</h3>
            <p>This is a friendly reminder for your upcoming dental appointment:</p>
            <p><strong>Service:</strong> {$appointment['service']}<br>
            <strong>Dentist:</strong> {$appointment['dentist']}<br>
            <strong>Date:</strong> {$appointment['appointment_date']}<br>
            <strong>Time:</strong> {$appointment['appointment_time']}</p>
            <p>Please arrive 10–15 minutes early.</p>
            <p>Thank you for trusting Dental Buddies Clinic!</p>
        ";

        $mail->send();

        // Mark reminder as sent
        $update = $con->prepare("UPDATE tbl_appointments SET reminder_sent = 1 WHERE appointment_id = ?");
        $update->bind_param("i", $appointment['appointment_id']);
        $update->execute();
        $update->close();

        echo "Reminder sent to {$appointment['email']}<br>";

    } catch (Exception $e) {
        echo "Failed for {$appointment['email']}: {$mail->ErrorInfo}<br>";
    }
}

$stmt->close();
$con->close();
