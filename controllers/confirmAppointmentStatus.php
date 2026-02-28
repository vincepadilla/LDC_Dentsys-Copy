<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$appointment_id = $_POST['appointment_id'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($appointment_id) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

// Verify that the appointment belongs to the logged-in user
$user_id = $_SESSION['userID'];

// Get patient_id from user_id
$patient_query = $con->prepare("SELECT patient_id FROM patient_information WHERE user_id = ?");
$patient_query->bind_param("s", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient = $patient_result->fetch_assoc();
$patient_query->close();

if (empty($patient['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Patient information not found.']);
    exit();
}

$patient_id = $patient['patient_id'];

// Verify appointment belongs to this patient
$verify_query = $con->prepare("SELECT appointment_id, status FROM appointments WHERE appointment_id = ? AND patient_id = ?");
$verify_query->bind_param("ss", $appointment_id, $patient_id);
$verify_query->execute();
$verify_result = $verify_query->get_result();
$appointment = $verify_result->fetch_assoc();
$verify_query->close();

if (!$appointment) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found or you do not have permission to modify it.']);
    exit();
}

// Check if appointment is in a valid state for confirmation/cancellation
if ($appointment['status'] == 'Cancelled' || $appointment['status'] == 'Complete' || $appointment['status'] == 'Completed') {
    echo json_encode(['success' => false, 'message' => 'Cannot modify appointment with status: ' . $appointment['status']]);
    exit();
}

// Perform the action
if ($action === 'confirm') {
    // Update status to Confirmed
    $update_query = $con->prepare("UPDATE appointments SET status = 'Confirmed' WHERE appointment_id = ? AND patient_id = ?");
    $update_query->bind_param("ss", $appointment_id, $patient_id);
    
    if ($update_query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appointment confirmed successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to confirm appointment.']);
    }
    $update_query->close();
    
} elseif ($action === 'cancel') {
    // Update status to Cancelled
    $update_query = $con->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ? AND patient_id = ?");
    $update_query->bind_param("ss", $appointment_id, $patient_id);
    
    if ($update_query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment.']);
    }
    $update_query->close();
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

$con->close();
?>
