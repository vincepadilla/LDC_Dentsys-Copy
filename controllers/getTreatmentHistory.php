<?php
include '../database/config.php'; 

header('Content-Type: application/json'); // Return data in JSON format

if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    // ✅ Query to get treatment history for the specific patient
    $sql = "SELECT treatment_id, treatment, prescription_given, notes, treatment_cost, created_at
            FROM treatment_history
            WHERE patient_id = ?
            ORDER BY created_at DESC";

    $stmt = $con->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $treatments = [];

        while ($row = $result->fetch_assoc()) {
            // Format created_at for display
            $row['created_at'] = date('M j, Y g:i A', strtotime($row['created_at']));
            $treatments[] = $row;
        }

        if (count($treatments) > 0) {
            echo json_encode([
                "status" => "success",
                "data" => $treatments
            ]);
        } else {
            echo json_encode([
                "status" => "empty",
                "message" => "No treatment history found for this patient."
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
