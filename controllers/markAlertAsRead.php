<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if alert_id is provided
if (!isset($_POST['alert_id']) || empty($_POST['alert_id'])) {
    echo json_encode(['success' => false, 'message' => 'Alert ID is required']);
    exit();
}

$alert_id = mysqli_real_escape_string($con, $_POST['alert_id']);
$user_id = $_SESSION['userID'];

// Update alert as read
$query = "UPDATE system_alerts 
          SET is_read = 1, read_at = NOW() 
          WHERE alert_id = ? AND user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("ss", $alert_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Alert marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Alert not found or already read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating alert: ' . mysqli_error($con)]);
}

$stmt->close();
mysqli_close($con);
?>
