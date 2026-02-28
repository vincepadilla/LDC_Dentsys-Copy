<?php
session_start();
include_once('../database/config.php');

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refund_id = $_POST['refund_id'] ?? null;
    $payment_id = $_POST['payment_id'] ?? null;

    if (!$refund_id || !$payment_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing refund or payment ID']);
        exit();
    }

    // Ensure schema supports "refunded" status (safe to run; ignore if it fails)
    @mysqli_query($con, "ALTER TABLE refund_requests MODIFY status ENUM('pending','processed','refunded') NOT NULL DEFAULT 'pending'");

    // Update refund request status to processed
    $updateRefund = $con->prepare("UPDATE refund_requests SET status = 'refunded' WHERE id = ?");
    $updateRefund->bind_param("s", $refund_id);
    
    if (!$updateRefund->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update refund status to refunded']);
        exit();
    }
    $updateRefund->close();

    // Update payment status to refunded
    $updatePayment = $con->prepare("UPDATE payment SET status = 'refunded' WHERE payment_id = ?");
    $updatePayment->bind_param("s", $payment_id);
    
    if (!$updatePayment->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update payment status']);
        exit();
    }
    $updatePayment->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Refund marked as refunded successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
