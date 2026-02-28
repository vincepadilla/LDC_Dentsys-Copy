<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PhpMailer/src/Exception.php';
require '../PhpMailer/src/PHPMailer.php';
require '../PhpMailer/src/SMTP.php';

session_start();
include_once('config.php');

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    echo "<script>alert('Invalid appointment ID.'); window.location.href='account.php';</script>";
    exit();
}

// Verify the appointment belongs to the logged-in user and fetch appointment details
$user_id = $_SESSION['userID'];
$appointmentQuery = $con->prepare("
    SELECT a.*, 
           p.first_name, p.last_name, p.email,
           s.service_category, s.sub_service,
           d.first_name AS dentist_first, d.last_name AS dentist_last
    FROM appointments a
    INNER JOIN patient_information p ON a.patient_id = p.patient_id
    LEFT JOIN services s ON a.service_id = s.service_id
    LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
    WHERE a.appointment_id = ? AND p.user_id = ?
");
$appointmentQuery->bind_param("ss", $appointment_id, $user_id);
$appointmentQuery->execute();
$appointmentResult = $appointmentQuery->get_result();
$appointment = $appointmentResult->fetch_assoc();

if (!$appointment) {
    echo "<script>alert('Appointment not found or you do not have permission to cancel this appointment.'); window.location.href='account.php';</script>";
    exit();
}

// Check if appointment is already cancelled
if ($appointment['status'] === 'Cancelled') {
    echo "<script>alert('This appointment is already cancelled.'); window.location.href='account.php';</script>";
    exit();
}

// Cancel appointment - using appointments table with VARCHAR appointment_id
$updateAppointment = $con->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
$updateAppointment->bind_param("s", $appointment_id);

if ($updateAppointment->execute()) {
    $paymentRefunded = false;
    
    // Find and update payment status if already paid (using payment table, not tbl_payment)
    $paymentQuery = $con->prepare("SELECT status FROM payment WHERE appointment_id = ?");
    $paymentQuery->bind_param("s", $appointment_id);
    $paymentQuery->execute();
    $paymentResult = $paymentQuery->get_result();

    if ($paymentResult->num_rows > 0) {
        $payment = $paymentResult->fetch_assoc();
        
        if ($payment['status'] === 'paid' || $payment['status'] === 'pending') {
            $refund = $con->prepare("UPDATE payment SET status = 'refund' WHERE appointment_id = ?");
            $refund->bind_param("s", $appointment_id);
            $refund->execute();
            $paymentRefunded = true;
        }
    }

    // Prepare email variables
    $patient_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
    $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
    $dentist = trim($appointment['dentist_first'] . ' ' . $appointment['dentist_last']);
    if (empty($dentist)) {
        $dentist = 'Dr. Michelle Landero';
    }
    $email = $appointment['email'];
    $appointment_date = date('F j, Y', strtotime($appointment['appointment_date']));
    $appointment_time = $appointment['appointment_time'];
    $branch = $appointment['branch'];

    // Send email notification
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
        $mail->Subject = 'Appointment Cancelled - Landero Dental Clinic';

        $refundMessage = $paymentRefunded 
            ? '<p style="color: #2a9d8f;"><strong>Note:</strong> Your payment has been processed for refund. Please allow 3-5 business days for the refund to be processed.</p>'
            : '';

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #166088;'>Appointment Cancelled</h2>
                
                <p>Dear {$patient_name},</p>
                
                <p>We are writing to confirm that your appointment has been <strong style='color: #e63946;'>cancelled</strong> as requested.</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='color: #166088; margin-top: 0;'>Cancelled Appointment Details:</h3>
                    <p><strong>Appointment ID:</strong> {$appointment_id}</p>
                    <p><strong>Service:</strong> {$service}</p>
                    <p><strong>Dentist:</strong> {$dentist}</p>
                    <p><strong>Date:</strong> {$appointment_date}</p>
                    <p><strong>Time:</strong> {$appointment_time}</p>
                    <p><strong>Branch:</strong> {$branch}</p>
                </div>
                
                {$refundMessage}
                
                <p>If you would like to reschedule your appointment, please log in to your account or contact us directly.</p>
                
                <p>We hope to serve you in the future. If you have any questions or concerns, please don't hesitate to reach out to us.</p>
                
                <p>Best regards,<br>
                <strong>Landero Dental Clinic</strong><br>
                Phone: 0922 861 1987<br>
                Email: landerodentalclinic@gmail.com</p>
            </div>
        ";

        $mail->send();

        // Show appropriate success message
        if ($paymentRefunded) {
            echo "<script>alert('Appointment cancelled, payment refunded, and confirmation email sent.'); window.location.href='account.php';</script>";
        } else {
            echo "<script>alert('Appointment cancelled and confirmation email sent.'); window.location.href='account.php';</script>";
        }

    } catch (Exception $e) {
        // Appointment was cancelled but email failed
        if ($paymentRefunded) {
            echo "<script>alert('Appointment cancelled and payment refunded, but email notification failed to send. Error: " . addslashes($mail->ErrorInfo) . "'); window.location.href='account.php';</script>";
        } else {
            echo "<script>alert('Appointment cancelled, but email notification failed to send. Error: " . addslashes($mail->ErrorInfo) . "'); window.location.href='account.php';</script>";
        }
    }
} else {
    echo "<script>alert('Error cancelling appointment.'); window.history.back();</script>";
}
?>
