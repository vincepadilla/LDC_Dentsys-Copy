<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    
    if (empty($referenceNo)) {
        echo json_encode([
            'exists' => false,
            'message' => 'Reference number is required'
        ]);
        exit();
    }
    
    // Check if reference number exists in payment table
    $checkQuery = "SELECT payment_id, appointment_id, method, status 
                   FROM payment 
                   WHERE reference_no = ? AND method = ? 
                   LIMIT 1";
    
    $stmt = $con->prepare($checkQuery);
    if ($stmt) {
        $stmt->bind_param("ss", $referenceNo, $paymentMethod);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $payment = $result->fetch_assoc();
            echo json_encode([
                'exists' => true,
                'message' => 'This reference number has already been used.',
                'payment_id' => $payment['payment_id'],
                'appointment_id' => $payment['appointment_id'],
                'status' => $payment['status']
            ]);
        } else {
            echo json_encode([
                'exists' => false,
                'message' => 'Reference number is available'
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Database error: ' . $con->error
        ]);
    }
} else {
    echo json_encode([
        'exists' => false,
        'message' => 'Invalid request method'
    ]);
}

$con->close();
?>
