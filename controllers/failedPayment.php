<?php
include_once("../database/config.php");

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['payment_id'])) {
    $payment_id = $_POST['payment_id'];

    // Check if payment exists
    $checkPayment = $con->prepare("SELECT payment_id, status FROM payment WHERE payment_id = ?");
    $checkPayment->bind_param("s", $payment_id);
    $checkPayment->execute();
    $paymentResult = $checkPayment->get_result();
    $paymentData = $paymentResult->fetch_assoc();
    $checkPayment->close();

    if (!$paymentData) {
        echo json_encode([
            'success' => false,
            'message' => 'Payment not found!'
        ]);
        exit();
    }

    // Check if payment is already failed
    if ($paymentData['status'] === 'failed') {
        echo json_encode([
            'success' => false,
            'message' => 'Payment is already marked as failed!'
        ]);
        exit();
    }

    // Update payment status to 'failed'
    $stmt = $con->prepare("UPDATE payment SET status = 'failed' WHERE payment_id = ?");
    $stmt->bind_param("s", $payment_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Payment marked as failed successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating payment: ' . $stmt->error
        ]);
    }

    $stmt->close();
    $con->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Payment ID is required.'
    ]);
    exit();
}
