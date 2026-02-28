<?php
include '../database/config.php'; 

header('Content-Type: application/json');

if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    $sql = "SELECT 
                p.payment_id,
                p.method,
                p.account_name,
                p.amount,
                p.reference_no,
                p.status
            FROM payment p
            INNER JOIN appointments a ON p.appointment_id = a.appointment_id
            WHERE a.patient_id = ?
            ORDER BY p.payment_id DESC
            LIMIT 1";

    $stmt = $con->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
            
            echo json_encode([
                "status" => "success",
                "data" => $transaction
            ]);
        } else {
            echo json_encode([
                "status" => "empty",
                "message" => "No transaction history found for this patient."
            ]);
        }

        $stmt->close();
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . $con->error
        ]);
    }

    $con->close();
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Missing patient_id parameter."
    ]);
}
?>