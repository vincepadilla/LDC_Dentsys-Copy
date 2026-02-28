<?php
include_once('../database/config.php');

header('Content-Type: application/json');

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_id = trim($_POST['payment_id'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Validate required fields
    if (empty($payment_id) || empty($status)) {
        echo json_encode([
            'success' => false,
            'message' => 'Payment ID and status are required.'
        ]);
        exit;
    }

    // Validate status value
    $allowedStatuses = ['pending', 'paid', 'failed', 'refunded'];
    if (!in_array(strtolower($status), $allowedStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status value.'
        ]);
        exit;
    }

    // Update the payment table
    $updatePaymentSql = "UPDATE payment SET status = ? WHERE payment_id = ?";

    if ($stmt = $con->prepare($updatePaymentSql)) {
        $stmt->bind_param("ss", $status, $payment_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Payment status updated successfully!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error updating payment: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error preparing update statement: ' . $con->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

$con->close();
?>
