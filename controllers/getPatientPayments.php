<?php
include '../database/config.php'; 

header('Content-Type: application/json');

if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    $sql = "SELECT 
                p.payment_id,
                p.appointment_id,
                p.method,
                p.amount,
                p.status,
                p.created_at,
                a.appointment_date
            FROM payment p
            INNER JOIN appointments a ON p.appointment_id = a.appointment_id
            WHERE a.patient_id = ?
            ORDER BY p.created_at DESC";

    $stmt = $con->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $payments = [];

        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }

        if (count($payments) > 0) {
            echo json_encode([
                "status" => "success",
                "data" => $payments
            ]);
        } else {
            echo json_encode([
                "status" => "empty",
                "message" => "No payment history found for this patient.",
                "data" => []
            ]);
        }

        $stmt->close();
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Database query failed: " . $con->error,
            "data" => []
        ]);
    }

    $con->close();
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Missing patient_id parameter.",
        "data" => []
    ]);
}
?>
