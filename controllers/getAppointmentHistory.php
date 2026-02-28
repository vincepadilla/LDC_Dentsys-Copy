<?php
include '../database/config.php'; 

header('Content-Type: application/json');

if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    $sql = "SELECT 
                a.appointment_id,
                CONCAT(t.first_name, ' ', t.last_name) as dentist_name,
                s.sub_service as service_name,
                a.branch,
                a.appointment_date,
                a.appointment_time
            FROM appointments a
            LEFT JOIN multidisciplinary_dental_team t ON a.team_id = t.team_id
            LEFT JOIN services s ON a.service_id = s.service_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC";

    $stmt = $con->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $appointments = [];

        while ($row = $result->fetch_assoc()) {
            // Format dates if needed
            $row['appointment_date'] = date('M j, Y', strtotime($row['appointment_date']));
            $appointments[] = $row;
        }

        if (count($appointments) > 0) {
            echo json_encode([
                "status" => "success",
                "data" => $appointments
            ]);
        } else {
            echo json_encode([
                "status" => "empty",
                "message" => "No appointment history found for this patient."
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