<?php
session_start();
include_once("../database/config.php");

// Set JSON header
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit();
}

// Check if admin is verified
if (empty($_SESSION['admin_verified'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Admin verification required.'
    ]);
    exit();
}

// Check if request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
}

// Check if walkin_id is provided
if (!isset($_POST['walkin_id']) || empty($_POST['walkin_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Walk-in ID is required.'
    ]);
    exit();
}

$walkin_id = mysqli_real_escape_string($con, $_POST['walkin_id']);

// Check if walk-in record exists
$checkQuery = "SELECT walkin_id, status FROM walkin_appointments WHERE walkin_id = '$walkin_id'";
$checkResult = mysqli_query($con, $checkQuery);

if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Walk-in record not found.'
    ]);
    exit();
}

$walkinData = mysqli_fetch_assoc($checkResult);

// Check if already completed
if (strtolower($walkinData['status']) === 'completed') {
    echo json_encode([
        'success' => false,
        'message' => 'This walk-in record is already marked as completed.'
    ]);
    exit();
}

// Update status to Completed
$updateQuery = "UPDATE walkin_appointments SET status = 'Completed' WHERE walkin_id = '$walkin_id'";

if (mysqli_query($con, $updateQuery)) {
    echo json_encode([
        'success' => true,
        'message' => 'Walk-in record marked as completed successfully!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update walk-in record: ' . mysqli_error($con)
    ]);
}

mysqli_close($con);
?>
