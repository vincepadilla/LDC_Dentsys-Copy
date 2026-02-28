<?php
include_once('../database/config.php');

// Fetch approved feedbacks for homepage
$query = "
    SELECT f.feedback_id, f.patient_name, f.feedback_text, f.created_at, f.appointment_id
    FROM feedback f
    WHERE f.status = 'approved'
    ORDER BY f.created_at DESC
    LIMIT 10
";

$result = $con->query($query);
$feedbacks = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = [
            'feedback_id' => $row['feedback_id'],
            'patient_name' => $row['patient_name'],
            'feedback_text' => $row['feedback_text'],
            'created_at' => $row['created_at'],
            'appointment_id' => $row['appointment_id']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);

