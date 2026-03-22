<?php
session_start();
require_once(__DIR__ . "/../database/config.php");
header("Content-Type: application/json");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['admin_verified'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$refundId = trim($_POST['refund_id'] ?? '');
if ($refundId === '') {
    echo json_encode(["success" => false, "message" => "Refund request ID is required."]);
    exit();
}

$stmt = $con->prepare("DELETE FROM refund_requests WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Failed to prepare delete query."]);
    exit();
}

$stmt->bind_param("s", $refundId);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if (!$ok || $affected <= 0) {
    echo json_encode(["success" => false, "message" => "Refund request not found or already deleted."]);
    exit();
}

echo json_encode(["success" => true, "message" => "Refund request deleted successfully."]);
?>
