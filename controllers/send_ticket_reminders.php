<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';
include_once('../database/config.php');

$now = date('Y-m-d H:i:s');
$windowEnd = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Find cash appointments in next 24 hours that are still pending
$query = "SELECT a.*, p.method, u.email, u.first_name, u.last_name
          FROM appointments a
          JOIN payment p ON p.appointment_id = a.appointment_id
          LEFT JOIN user_account u ON u.user_id = (SELECT user_id FROM patient_information WHERE patient_id = a.patient_id LIMIT 1)
          WHERE p.method = 'Cash' AND p.status = 'pending' AND a.status = 'Pending'
          AND CONCAT(a.appointment_date, ' ', SUBSTRING_INDEX(a.appointment_time, '-', 1)) BETWEEN ? AND ?";

$stmt = $con->prepare($query);
$stmt->bind_param('ss', $now, $windowEnd);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $email = $row['email'];
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Patient';
    $ticket = $row['ticket_code'] ?? '';
    $apptDate = $row['appointment_date'];
    $apptTime = $row['appointment_time'];

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'Dental Clinic');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Reminder & Ticket Code';

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $host ? $protocol . '://' . $host : '';
        $confirmLink = $baseUrl . '/controllers/ticket_action.php?action=confirm&appointment_id=' . urlencode($row['appointment_id']) . '&ticket=' . urlencode($ticket);
        $cancelLink = $baseUrl . '/controllers/ticket_action.php?action=cancel&appointment_id=' . urlencode($row['appointment_id']) . '&ticket=' . urlencode($ticket);

        $mail->Body = "<h3>Hi $name,</h3>
                <p>This is a reminder for your upcoming appointment on <strong>$apptDate</strong> at <strong>$apptTime</strong>.</p>
                <p>Your Ticket Code: <strong>$ticket</strong></p>
                <p>Please present this ticket at reception and pay the consultation fee upon arrival.</p>
                <p>If you will attend, confirm now: <a href='$confirmLink'>Confirm Appointment</a></p>
                <p>If you need to cancel: <a href='$cancelLink'>Cancel Appointment</a></p>
            ";

        $mail->send();
        // Optionally mark a flag in DB if you track reminders
    } catch (Exception $e) {
        // log error
    }
}

$stmt->close();
$con->close();
?>
