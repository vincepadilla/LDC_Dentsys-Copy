<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to access this feature.']);
    exit();
}

$user_role = strtolower($_SESSION['role'] ?? '');
$allowed_roles = ['admin', 'cashier', 'dentist', 'receptionist'];
if (!in_array($user_role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to access this feature.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$scanned_id = trim($_POST['scanned_id'] ?? '');

if (empty($scanned_id)) {
    echo json_encode(['success' => false, 'message' => 'Scanned ID is required.']);
    exit();
}

// Check if ticket_code column exists
$colCheck = mysqli_query($con, "SHOW COLUMNS FROM appointments LIKE 'ticket_code'");
$hasTicketCols = ($colCheck && mysqli_num_rows($colCheck) > 0);

// Try to find appointment by appointment_id first (primary lookup), then by ticket_code (fallback)
// QR codes now embed appointment_id directly for faster lookup
// NOTE: Date restriction removed for testing purposes - allows scanning appointments for any date

if ($hasTicketCols) {
    // Prioritize appointment_id lookup (what QR codes now contain), fallback to ticket_code
    $query = $con->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.ticket_code,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               s.sub_service, s.service_category
        FROM appointments a
        LEFT JOIN patient_information p ON a.patient_id = p.patient_id
        LEFT JOIN services s ON a.service_id = s.service_id
        WHERE (a.appointment_id = ? OR a.ticket_code = ?)
        LIMIT 1
    ");
    $query->bind_param("ss", $scanned_id, $scanned_id);
} else {
    // Fallback: search by appointment_id only (QR codes now contain appointment_id)
    $query = $con->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               s.sub_service, s.service_category
        FROM appointments a
        LEFT JOIN patient_information p ON a.patient_id = p.patient_id
        LEFT JOIN services s ON a.service_id = s.service_id
        WHERE a.appointment_id = ?
        LIMIT 1
    ");
    $query->bind_param("s", $scanned_id);
}

$query->execute();
$result = $query->get_result();
$appointment = $result->fetch_assoc();
$query->close();

if (!$appointment) {
    echo json_encode([
        'success' => false, 
        'message' => 'Appointment not found. Please verify the QR code.'
    ]);
    exit();
}

// Return appointment details
$response = [
    'success' => true,
    'appointment_id' => $appointment['appointment_id'],
    'appointment_date' => $appointment['appointment_date'],
    'appointment_time' => $appointment['appointment_time'],
    'status' => $appointment['status'],
    'patient_name' => $appointment['patient_name'] ?? 'N/A',
    'service' => $appointment['sub_service'] ?? $appointment['service_category'] ?? 'N/A'
];

if ($hasTicketCols && isset($appointment['ticket_code'])) {
    $response['ticket_code'] = $appointment['ticket_code'];
} else {
    $response['ticket_code'] = $appointment['appointment_id']; // Fallback to appointment_id
}

echo json_encode($response);
$con->close();
?>
