<?php
include_once('../database/config.php');

header('Content-Type: application/json');

if (isset($_GET['payment_id'])) {
    $payment_id = $_GET['payment_id'];
    
    // Query to get payment details
    $query = "SELECT 
                p.payment_id,
                p.appointment_id,
                p.method,
                p.account_name,
                p.account_number,
                p.amount,
                p.reference_no,
                p.proof_image,
                p.status
              FROM payment p
              WHERE p.payment_id = ?";
    
    if ($stmt = $con->prepare($query)) {
        $stmt->bind_param("s", $payment_id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $payment = $result->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'data' => $payment
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Payment not found'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Query execution error: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database preparation error: ' . $con->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Payment ID is required'
    ]);
}

$con->close();
?>
