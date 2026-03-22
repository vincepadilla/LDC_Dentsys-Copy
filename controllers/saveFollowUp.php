<?php
// Start output buffering to catch any errors/warnings
ob_start();

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

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

// Include config file with error handling
try {
    require_once(__DIR__ . "/../database/config.php");
    
    // Check if connection exists
    if (!isset($con) || !$con) {
        throw new Exception('Database connection not established');
    }
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// Clear any output that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');

try {
// Check if user is admin
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Get form data
$original_appointment_id = trim($_POST['original_appointment_id'] ?? '');
$patient_id = trim($_POST['patient_id'] ?? '');
$service_id = trim($_POST['service_id'] ?? '');
$team_id = trim($_POST['team_id'] ?? '');
$branch = trim($_POST['branch'] ?? '');
$appointment_date = trim($_POST['appointment_date'] ?? '');
$time_slot = trim($_POST['time_slot'] ?? '');
$followup_reason = trim($_POST['followup_reason'] ?? '');

// Validate required fields
if (empty($original_appointment_id) || empty($patient_id) || empty($service_id) || empty($team_id) || empty($branch) || empty($appointment_date) || empty($time_slot)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

// Validate reason
if (empty($followup_reason)) {
    ob_clean();
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
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid time slot selected.']);
    exit();
}

// Function to generate new appointment ID
function generateAppointmentID($con) {
    $query = "SELECT appointment_id FROM appointments ORDER BY appointment_id DESC LIMIT 1";
    $result = mysqli_query($con, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastID = $row['appointment_id'];
        $number = intval(substr($lastID, 1));
        $newNumber = $number + 1;
        return 'A' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
    return 'A001';
}

// Check for double booking
$checkBooking = $con->prepare("
    SELECT appointment_id FROM appointments 
    WHERE appointment_date = ? 
    AND time_slot = ? 
    AND team_id = ? 
    AND status NOT IN ('Cancelled', 'No-show')
    LIMIT 1
");
$checkBooking->bind_param("sss", $appointment_date, $time_slot, $team_id);
$checkBooking->execute();
$bookingResult = $checkBooking->get_result();

if ($bookingResult->num_rows > 0) {
    $checkBooking->close();
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please select another time.']);
    exit();
}
$checkBooking->close();

// Generate new appointment ID
$new_appointment_id = generateAppointmentID($con);

// Get patient, service, and dentist details for email
// Get patient info
$stmtPatient = $con->prepare("
    SELECT first_name, last_name, email
    FROM patient_information
    WHERE patient_id = ?
");
$stmtPatient->bind_param("s", $patient_id);
$stmtPatient->execute();
$patientResult = $stmtPatient->get_result();
$patientData = $patientResult->fetch_assoc();
$stmtPatient->close();

if (!$patientData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Patient information not found.']);
    exit();
}

// Get service info
$stmtService = $con->prepare("
    SELECT service_category, sub_service
    FROM services
    WHERE service_id = ?
");
$stmtService->bind_param("s", $service_id);
$stmtService->execute();
$serviceResult = $stmtService->get_result();
$serviceData = $serviceResult->fetch_assoc();
$stmtService->close();

if (!$serviceData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Service information not found.']);
    exit();
}

// Get dentist info
$stmtDentist = $con->prepare("
    SELECT first_name, last_name
    FROM multidisciplinary_dental_team
    WHERE team_id = ?
");
$stmtDentist->bind_param("s", $team_id);
$stmtDentist->execute();
$dentistResult = $stmtDentist->get_result();
$dentistData = $dentistResult->fetch_assoc();
$stmtDentist->close();

if (!$dentistData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Dentist information not found.']);
    exit();
}

// Combine all data
$appointmentData = array_merge($patientData, $serviceData, [
    'dentist_first' => $dentistData['first_name'],
    'dentist_last' => $dentistData['last_name']
]);

if (!$appointmentData) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Patient, service, or dentist information not found.']);
    exit();
}

// Check if 'Follow-up' status exists in enum, if not use 'Pending' and update later
// First, try to insert with 'Follow-up' status
$followUpStatus = 'Follow-up';

// Create new follow-up appointment
$stmtInsert = $con->prepare("
    INSERT INTO appointments 
    (appointment_id, patient_id, team_id, service_id, branch, appointment_date, appointment_time, time_slot, status, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmtInsert->bind_param("sssssssss", $new_appointment_id, $patient_id, $team_id, $service_id, $branch, $appointment_date, $appointment_time, $time_slot, $followUpStatus);

if (!$stmtInsert->execute()) {
    // If 'Follow-up' status doesn't exist in enum, try with 'Pending' and update enum
    $error = $stmtInsert->error;
    $stmtInsert->close();
    
    // Check if error is related to enum value
    if (strpos($error, 'enum') !== false || strpos($error, 'status') !== false) {
        // Try to alter the enum to include 'Follow-up'
        $alterQuery = "ALTER TABLE appointments MODIFY status ENUM('Pending','Confirmed','Reschedule','Complete','Cancelled','No-show','Follow-up') DEFAULT NULL";
        @mysqli_query($con, $alterQuery);
        
        // Try again with 'Follow-up'
        $stmtInsert2 = $con->prepare("
            INSERT INTO appointments 
            (appointment_id, patient_id, team_id, service_id, branch, appointment_date, appointment_time, time_slot, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtInsert2->bind_param("sssssssss", $new_appointment_id, $patient_id, $team_id, $service_id, $branch, $appointment_date, $appointment_time, $time_slot, $followUpStatus);
        
        if (!$stmtInsert2->execute()) {
            // If still fails, use 'Pending' as fallback
            $followUpStatus = 'Pending';
            $stmtInsert2->close();
            $stmtInsert3 = $con->prepare("
                INSERT INTO appointments 
                (appointment_id, patient_id, team_id, service_id, branch, appointment_date, appointment_time, time_slot, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert3->bind_param("sssssssss", $new_appointment_id, $patient_id, $team_id, $service_id, $branch, $appointment_date, $appointment_time, $time_slot, $followUpStatus);
            
            if (!$stmtInsert3->execute()) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Error creating follow-up appointment: ' . $stmtInsert3->error]);
                $stmtInsert3->close();
                exit();
            }
            $stmtInsert3->close();
        } else {
            $stmtInsert2->close();
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error creating follow-up appointment: ' . $error]);
        exit();
    }
} else {
    $stmtInsert->close();
}

// Prepare email variables
$patient_name = trim($appointmentData['first_name'] . ' ' . $appointmentData['last_name']);
$service = !empty($appointmentData['sub_service']) ? $appointmentData['sub_service'] : $appointmentData['service_category'];
$dentist = trim($appointmentData['dentist_first'] . ' ' . $appointmentData['dentist_last']);
$email = $appointmentData['email'];

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
            <strong>Appointment ID:</strong> {$new_appointment_id}<br>
            <strong>Service:</strong> {$service}<br>
            <strong>Dentist:</strong> {$dentist}<br>
            <strong>Follow-Up Date:</strong> " . date('F j, Y', strtotime($appointment_date)) . "<br>
            <strong>Follow-Up Time:</strong> {$appointment_time}<br>
            <strong>Branch:</strong> {$branch}
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

// Return JSON response - ensure clean output
ob_clean();

if ($emailSent) {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Follow-up appointment #' . $new_appointment_id . ' created and email sent successfully.',
        'appointment_id' => $new_appointment_id
    ]);
} else if (!empty($email)) {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Follow-up appointment #' . $new_appointment_id . ' created, but email failed to send. Error: ' . $emailError,
        'appointment_id' => $new_appointment_id
    ]);
} else {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Follow-up appointment #' . $new_appointment_id . ' created successfully. (No email address found for patient)',
        'appointment_id' => $new_appointment_id
    ]);
}
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit();
}
exit();

