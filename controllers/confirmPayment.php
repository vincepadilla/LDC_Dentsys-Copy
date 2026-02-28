<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

include_once("../database/config.php");

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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['payment_id'])) {
    $payment_id = $_POST['payment_id'];

    // First, get payment details to get appointment_id and check method
    $checkPayment = $con->prepare("SELECT appointment_id, method, status FROM payment WHERE payment_id = ?");
    $checkPayment->bind_param("s", $payment_id);
    $checkPayment->execute();
    $paymentResult = $checkPayment->get_result();
    $paymentData = $paymentResult->fetch_assoc();
    $checkPayment->close();

    if (!$paymentData) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Payment not found!'
        ]);
        exit();
    }

    // Check if payment is already paid
    if ($paymentData['status'] === 'paid') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Payment is already confirmed!'
        ]);
        exit();
    }

    $appointment_id = $paymentData['appointment_id'];
    $paymentMethod = $paymentData['method'] ?? '';

    // Update payment status
    $stmt = $con->prepare("UPDATE payment SET status = 'paid' WHERE payment_id = ? AND status = 'pending'");
    $stmt->bind_param("s", $payment_id);

    if ($stmt->execute()) {
        // If payment method is Cash, send notification and email
        if ($paymentMethod === 'Cash') {
            // Get appointment details with patient information
            $appointmentQuery = $con->prepare("SELECT a.*, 
                                                      p.first_name, p.last_name, p.email, p.user_id,
                                                      s.service_category, s.sub_service,
                                                      d.first_name as dentist_first, d.last_name as dentist_last
                                              FROM appointments a 
                                              LEFT JOIN patient_information p ON a.patient_id = p.patient_id 
                                              LEFT JOIN services s ON a.service_id = s.service_id
                                              LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                                              WHERE a.appointment_id = ?");
            $appointmentQuery->bind_param("s", $appointment_id);
            $appointmentQuery->execute();
            $appointmentResult = $appointmentQuery->get_result();
            $appointment = $appointmentResult->fetch_assoc();
            $appointmentQuery->close();

            if ($appointment && !empty($appointment['user_id'])) {
                $user_id = $appointment['user_id'];
                $email = $appointment['email'];
                $patient_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);

                // Create notification
                $notification_id = generateID('N', 'notifications', 'notification_id', $con);
                $dentistName = 'Dr. ' . trim(($appointment['dentist_first'] ?? '') . ' ' . ($appointment['dentist_last'] ?? ''));
                $dentistName = mysqli_real_escape_string($con, $dentistName);
                $appointment_date = mysqli_real_escape_string($con, $appointment['appointment_date']);
                $appointment_time = mysqli_real_escape_string($con, $appointment['appointment_time']);
                $user_id_escaped = mysqli_real_escape_string($con, $user_id);
                
                $insertNotification = "INSERT INTO notifications 
                    (notification_id, user_id, type, appointment_date, appointment_time, dentist_name, is_read, created_at)
                    VALUES 
                    ('$notification_id', '$user_id_escaped', 'payment_confirmed', '$appointment_date', '$appointment_time', '$dentistName', 0, NOW())";
                
                mysqli_query($con, $insertNotification);

                // Send email using PHPMailer
                if (!empty($email)) {
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
                        $mail->Subject = 'Payment Confirmed';

                        $mail->Body = "
                            <h3>Hi {$patient_name},</h3>
                            <p>The staff confirmed your payment please check your account for more details.</p>
                            <p>Thank you for choosing our clinic!</p>
                        ";

                        $mail->send();
                    } catch (Exception $e) {
                        // Email sending failed, but payment is still confirmed
                        error_log("Email sending failed: " . $mail->ErrorInfo);
                    }
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Payment confirmed successfully!'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error updating payment: ' . $stmt->error
        ]);
    }

    $stmt->close();
    $con->close();
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Payment ID is required.'
    ]);
    exit();
}
