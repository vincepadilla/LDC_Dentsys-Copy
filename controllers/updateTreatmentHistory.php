<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Unauthorized access.'
    ]);
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin verification required.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit();
}

$treatment_id = trim($_POST['treatment_id'] ?? '');
$treatment = trim($_POST['treatment'] ?? '');
$prescription_given = trim($_POST['prescription_given'] ?? '');
$treatment_notes = trim($_POST['treatment_notes'] ?? '');
$treatment_cost = trim($_POST['treatment_cost'] ?? '');

$errors = [];
if ($treatment_id === '') $errors[] = 'treatment_id is required.';
if ($treatment === '') $errors[] = 'Treatment is required.';
if ($prescription_given === '') $errors[] = 'Prescription given is required.';
if ($treatment_notes === '') $errors[] = 'Notes are required.';
if ($treatment_cost === '' || !is_numeric($treatment_cost) || (float)$treatment_cost < 0) {
    $errors[] = 'Please enter a valid treatment cost.';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => implode(' ', $errors)
    ]);
    exit();
}

$updated_at = date('Y-m-d H:i:s');

$stmt = $con->prepare("
    UPDATE treatment_history
    SET treatment = ?, prescription_given = ?, notes = ?, treatment_cost = ?, updated_at = ?
    WHERE treatment_id = ?
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database prepare error: ' . $con->error
    ]);
    exit();
}

$stmt->bind_param(
    'sssdss',
    $treatment,
    $prescription_given,
    $treatment_notes,
    $treatment_cost,
    $updated_at,
    $treatment_id
);

if (!$stmt->execute()) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to update treatment record: ' . $stmt->error
    ]);
    $stmt->close();
    exit();
}

$stmt->close();

// Return updated row data so the UI can refresh in real-time
$selectStmt = $con->prepare("
    SELECT
        th.treatment_id,
        th.patient_id,
        th.treatment,
        th.prescription_given,
        th.notes,
        th.treatment_cost,
        th.created_at,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name
    FROM treatment_history th
    LEFT JOIN patient_information p ON th.patient_id = p.patient_id
    WHERE th.treatment_id = ?
    LIMIT 1
");

if (!$selectStmt) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database prepare error: ' . $con->error
    ]);
    exit();
}

$selectStmt->bind_param('s', $treatment_id);
$selectStmt->execute();
$result = $selectStmt->get_result();

if (!$row = $result->fetch_assoc()) {
    $selectStmt->close();
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Updated record not found.'
    ]);
    exit();
}

$selectStmt->close();

$row['treatment_cost'] = (float)$row['treatment_cost'];
$row['created_at_formatted'] = date('M j, Y', strtotime($row['created_at']));

echo json_encode([
    'success' => true,
    'status' => 'success',
    'message' => 'Treatment record updated successfully!',
    'record' => $row
]);
?>

