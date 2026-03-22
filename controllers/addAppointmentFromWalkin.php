<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin' || empty($_SESSION['admin_verified'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get form data
    $walkin_id = trim($_POST['walkin_id'] ?? '');
    $patient_id = trim($_POST['patient_id'] ?? '');
    $dentist_name = trim($_POST['dentist_name'] ?? '');
    $service_name = trim($_POST['service_name'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $time_slot = trim($_POST['time_slot'] ?? '');

    // Validate required fields
    if (empty($walkin_id) || empty($patient_id) || empty($dentist_name) || empty($service_name) || empty($branch) || empty($appointment_date) || empty($time_slot)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    // Get team_id from dentist_name
    // Split dentist name into first and last name
    $nameParts = explode(' ', trim($dentist_name));
    $dentist_first_name = $nameParts[0] ?? '';
    $dentist_last_name = '';
    if (count($nameParts) > 1) {
        $dentist_last_name = implode(' ', array_slice($nameParts, 1));
    }

    // Query to get team_id from dentist name
    $getTeamIdQuery = $con->prepare("
        SELECT team_id 
        FROM multidisciplinary_dental_team 
        WHERE first_name = ? AND last_name = ?
        LIMIT 1
    ");
    $getTeamIdQuery->bind_param("ss", $dentist_first_name, $dentist_last_name);
    $getTeamIdQuery->execute();
    $teamIdResult = $getTeamIdQuery->get_result();
    
    if ($teamIdResult->num_rows === 0) {
        $getTeamIdQuery->close();
        echo json_encode(['success' => false, 'message' => 'Dentist not found in system. Please verify the dentist name.']);
        exit();
    }
    
    $teamRow = $teamIdResult->fetch_assoc();
    $team_id = $teamRow['team_id'];
    $getTeamIdQuery->close();

    // Get service_id from service_name (sub_service or service_category)
    // First try to match by sub_service, then by service_category
    $getServiceIdQuery = $con->prepare("
        SELECT service_id 
        FROM services 
        WHERE sub_service = ? OR service_category = ?
        LIMIT 1
    ");
    $getServiceIdQuery->bind_param("ss", $service_name, $service_name);
    $getServiceIdQuery->execute();
    $serviceIdResult = $getServiceIdQuery->get_result();
    
    if ($serviceIdResult->num_rows === 0) {
        $getServiceIdQuery->close();
        echo json_encode(['success' => false, 'message' => 'Service not found in system. Please verify the service name.']);
        exit();
    }
    
    $serviceRow = $serviceIdResult->fetch_assoc();
    $service_id = $serviceRow['service_id'];
    $getServiceIdQuery->close();

    // Time slot mapping
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

    if (!isset($timeMap[$time_slot])) {
        echo json_encode(['success' => false, 'message' => 'Invalid time slot selected.']);
        exit();
    }

    $appointment_time = $timeMap[$time_slot];

    // Validate date is not in the past
    $today = date('Y-m-d');
    if ($appointment_date < $today) {
        echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past.']);
        exit();
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
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please select another time.']);
        exit();
    }
    $checkBooking->close();

    // Generate appointment ID
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

    $appointment_id = generateAppointmentID($con);

    // Insert appointment
    $insert = $con->prepare("
        INSERT INTO appointments 
        (appointment_id, patient_id, team_id, service_id, branch, appointment_date, appointment_time, time_slot, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
    ");
    $insert->bind_param("ssssssss", $appointment_id, $patient_id, $team_id, $service_id, $branch, $appointment_date, $appointment_time, $time_slot);

    if ($insert->execute()) {
        $insert->close();
        echo json_encode([
            'success' => true, 
            'message' => 'Walk-in record has been successfully added to appointment schedule.',
            'appointment_id' => $appointment_id
        ]);
    } else {
        $error = $insert->error;
        $insert->close();
        echo json_encode(['success' => false, 'message' => 'Failed to add appointment: ' . $error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
