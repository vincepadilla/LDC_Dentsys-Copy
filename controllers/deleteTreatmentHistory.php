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
if ($treatment_id === '') {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'treatment_id is required.'
    ]);
    exit();
}

$stmt = $con->prepare("DELETE FROM treatment_history WHERE treatment_id = ?");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database prepare error: ' . $con->error
    ]);
    exit();
}

$stmt->bind_param('s', $treatment_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Treatment record deleted successfully!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Treatment record not found.'
    ]);
}

$stmt->close();
?>

