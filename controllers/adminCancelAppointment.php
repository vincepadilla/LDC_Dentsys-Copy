<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

session_start();
include_once('../database/config.php');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin' || empty($_SESSION['admin_verified'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {
    $appointment_id = trim($_POST['appointment_id']);

    if (empty($appointment_id)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Appointment ID is required']);
        exit();
    }

    // Fetch appointment details with patient information
    $appointmentQuery = $con->prepare("
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
    $appointmentQuery->bind_param("s", $appointment_id);
    $appointmentQuery->execute();
    $appointmentResult = $appointmentQuery->get_result();
    $appointment = $appointmentResult->fetch_assoc();
    $appointmentQuery->close();

    if (!$appointment) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        exit();
    }

    // Check if appointment is already cancelled
    if ($appointment['status'] === 'Cancelled') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'This appointment is already cancelled']);
        exit();
    }

    // Cancel appointment
    $updateAppointment = $con->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
    $updateAppointment->bind_param("s", $appointment_id);

    if ($updateAppointment->execute()) {
        $paymentRefunded = false;
        
        // Find and update payment status if already paid
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
        $paymentQuery->close();

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
        $branch = $appointment['branch'] ?? 'Main Branch';

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
                    
                    <p>We are writing to inform you that your appointment has been <strong style='color: #e63946;'>cancelled</strong> by our administration.</p>
                    
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
                    
                    <p>If you would like to reschedule your appointment, please log in to your account and select a new date and time that works for you.</p>
                    
                    <p>We apologize for any inconvenience this may cause. If you have any questions or concerns, please don't hesitate to reach out to us.</p>
                    
                    <p>Best regards,<br>
                    <strong>Landero Dental Clinic</strong><br>
                    Phone: 0922 861 1987<br>
                    Email: landerodentalclinic@gmail.com</p>
                </div>
            ";

            $mail->send();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Appointment cancelled and email notification sent successfully.',
                'payment_refunded' => $paymentRefunded
            ]);

        } catch (Exception $e) {
            // Appointment was cancelled but email failed
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Appointment cancelled, but email notification failed to send.',
                'email_error' => $mail->ErrorInfo
            ]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to cancel appointment: ' . $updateAppointment->error]);
    }
    
    $updateAppointment->close();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>

