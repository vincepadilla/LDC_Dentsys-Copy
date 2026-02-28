<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required.']);
    exit();
}

$appointment_id = trim($_GET['id']);

if (empty($appointment_id)) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID cannot be empty.']);
    exit();
}

// Fetch appointment details with all related information
$stmt = $con->prepare("
    SELECT a.*, 
           p.patient_id, p.first_name, p.last_name, p.email, p.phone, p.birthdate, p.gender, p.address,
           s.service_category, s.sub_service, s.description as service_description,
           d.first_name AS dentist_first, d.last_name AS dentist_last, d.specialization,
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           CONCAT(d.first_name, ' ', d.last_name) as dentist_name
    FROM appointments a
    LEFT JOIN patient_information p ON a.patient_id = p.patient_id
    LEFT JOIN services s ON a.service_id = s.service_id
    LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
    WHERE a.appointment_id = ?
");

$stmt->bind_param("s", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
    exit();
}

// Format the response
echo json_encode([
    'success' => true,
    'appointment_id' => $appointment['appointment_id'],
    'patient_id' => $appointment['patient_id'],
    'patient_name' => $appointment['patient_name'],
    'first_name' => $appointment['first_name'],
    'last_name' => $appointment['last_name'],
    'email' => $appointment['email'],
    'phone' => $appointment['phone'],
    'birthdate' => $appointment['birthdate'],
    'gender' => $appointment['gender'],
    'address' => $appointment['address'],
    'service_id' => $appointment['service_id'],
    'service_category' => $appointment['service_category'],
    'sub_service' => $appointment['sub_service'],
    'service_description' => $appointment['service_description'],
    'team_id' => $appointment['team_id'],
    'dentist_name' => $appointment['dentist_name'],
    'dentist_first' => $appointment['dentist_first'],
    'dentist_last' => $appointment['dentist_last'],
    'specialization' => $appointment['specialization'],
    'branch' => $appointment['branch'],
    'appointment_date' => $appointment['appointment_date'],
    'appointment_time' => $appointment['appointment_time'],
    'time_slot' => $appointment['time_slot'],
    'status' => $appointment['status'],
    'created_at' => $appointment['created_at']
]);
?>
