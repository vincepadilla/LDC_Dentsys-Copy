<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check for PhpMailer path
if (file_exists('../libraries/PhpMailer/src/Exception.php')) {
    require '../libraries/PhpMailer/src/Exception.php';
    require '../libraries/PhpMailer/src/PHPMailer.php';
    require '../libraries/PhpMailer/src/SMTP.php';
} else if (file_exists('../PhpMailer/src/Exception.php')) {
    require '../PhpMailer/src/Exception.php';
    require '../PhpMailer/src/PHPMailer.php';
    require '../PhpMailer/src/SMTP.php';
} else {
    require '../PhpMailer/src/Exception.php';
    require '../PhpMailer/src/PHPMailer.php';
    require '../PhpMailer/src/SMTP.php';
}

session_start();
include_once("config.php");

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Get form data
$appointment_id = trim($_POST['original_appointment_id'] ?? '');
$appointment_date = trim($_POST['appointment_date'] ?? '');
$time_slot = trim($_POST['time_slot'] ?? '');
$followup_reason = trim($_POST['followup_reason'] ?? '');

// Validate required fields
if (empty($appointment_id) || empty($appointment_date) || empty($time_slot)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

// Validate reason
if (empty($followup_reason)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for the follow-up.']);
    exit();
}

// Map time slot to actual time
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

$appointment_time = $timeMap[$time_slot] ?? '';

if (empty($appointment_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time slot selected.']);
    exit();
}

// Get appointment details for email
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
    echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
    exit();
}

// Update the existing appointment status to 'Follow-Up' and update date/time
$stmtUpdate = $con->prepare("
    UPDATE appointments 
    SET appointment_date = ?, 
        appointment_time = ?, 
        time_slot = ?, 
        status = 'Follow-Up' 
    WHERE appointment_id = ?
");
$stmtUpdate->bind_param("ssss", $appointment_date, $appointment_time, $time_slot, $appointment_id);

if (!$stmtUpdate->execute()) {
    echo json_encode(['success' => false, 'message' => 'Error updating appointment: ' . $stmtUpdate->error]);
    $stmtUpdate->close();
    exit();
}

$stmtUpdate->close();

// Prepare email variables
$patient_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
$service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
$dentist = trim($appointment['dentist_first'] . ' ' . $appointment['dentist_last']);
$email = $appointment['email'];

// Send email notification
$emailSent = false;
$emailError = '';

if (!empty($email)) {
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
        $mail->Subject = 'Follow-Up Appointment Scheduled';

        $reasonText = htmlspecialchars($followup_reason);
        
        $mail->Body = "
            <h3>Hi {$patient_name},</h3>
            <p>A <strong>follow-up appointment</strong> has been scheduled for you.</p>

            <p>
            <strong>Service:</strong> {$service}<br>
            <strong>Dentist:</strong> {$dentist}<br>
            <strong>Follow-Up Date:</strong> " . date('F j, Y', strtotime($appointment_date)) . "<br>
            <strong>Follow-Up Time:</strong> {$appointment_time}<br>
            <strong>Branch:</strong> {$appointment['branch']}
            </p>
            
            <p><strong>Reason for Follow-Up:</strong><br>{$reasonText}</p>

            <p>Please check your account for more details.</p>
            <p>Thank you for choosing our clinic!</p>
        ";

        $mail->send();
        $emailSent = true;
    } catch (Exception $e) {
        $emailSent = false;
        $emailError = $mail->ErrorInfo;
    }
}

// Return JSON response
if ($emailSent) {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Follow-up appointment scheduled and email sent successfully.'
    ]);
} else if (!empty($email)) {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Follow-up appointment scheduled, but email failed to send. Error: ' . $emailError
    ]);
} else {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Follow-up appointment scheduled successfully. (No email address found for patient)'
    ]);
}
exit();

