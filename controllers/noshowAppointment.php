<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PhpMailer/src/Exception.php';
require '../PhpMailer/src/PHPMailer.php';
require '../PhpMailer/src/SMTP.php';

include_once("config.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {

    $appointment_id = trim($_POST['appointment_id']);

    if (empty($appointment_id)) {
        echo "<script>alert('Error: Appointment ID is required.'); window.location='admin.php?noshow=invalid_id';</script>";
        exit();
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
        echo "<script>alert('Appointment not found.'); window.location='admin.php?noshow=not_found';</script>";
        exit();
    }

    /** UPDATE STATUS TO NO-SHOW **/
    $stmtUpdate = $con->prepare("UPDATE appointments SET status = 'No-Show' WHERE appointment_id = ?");
    $stmtUpdate->bind_param("s", $appointment_id);

    if (!$stmtUpdate->execute()) {
        echo "<script>alert('Failed to update appointment status: " . $stmtUpdate->error . "'); window.location='admin.php?noshow=failed';</script>";
        $stmtUpdate->close();
        exit();
    }

    $stmtUpdate->close();

    /** EMAIL VARIABLES **/
    $patient_name = trim($appointment['first_name'] . " " . $appointment['last_name']);
    $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
    $dentist = trim($appointment['dentist_first'] . " " . $appointment['dentist_last']);
    $email = $appointment['email'];

    /** SEND EMAIL **/
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'padillavincehenrick@gmail.com';
        $mail->Password = 'glxd csoa ispj bvjg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('padillavincehenrick@gmail.com', 'Landero Dental Clinic');
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

        echo "<script>
                alert('Status updated to No-Show and email sent.');
                window.location='admin.php?noshow=success';
             </script>";
        exit();

    } catch (Exception $e) {
        echo "<script>
                alert('Status updated, but email failed to send. Error: {$mail->ErrorInfo}');
                window.location='admin.php?noshow=email_failed';
             </script>";
        exit();
    }

} else {
    header("Location: admin.php");
    exit();
}
?>
