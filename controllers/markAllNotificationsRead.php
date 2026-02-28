<?php
session_start();
include_once('../database/config.php');
header('Content-Type: application/json');

if (!isset($_SESSION['valid']) || !isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['userID'];

$sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$stmt = $con->prepare($sql);
$stmt->bind_param("s", $user_id); // Changed from "i" to "s" for VARCHAR

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to mark all notifications as read']);
}
?>