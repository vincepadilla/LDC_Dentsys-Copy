<?php
include_once('../database/config.php');
if (isset($_GET['appointment_date'])) {
    $selectedDate = $_GET['appointment_date'];
    $dentistId = $_GET['dentist_id'] ?? '';

    $bookedSlots = array();
    
    // Build query with optional dentist filter
    if (!empty($dentistId)) {
        $query = "SELECT time_slot FROM appointments WHERE appointment_date = ? AND team_id = ? AND status != 'Cancelled'";
        $stmt = $con->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ss", $selectedDate, $dentistId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $bookedSlots[] = $row['time_slot'];
            }
            $stmt->close(); 
        }
    } else {
        $query = "SELECT time_slot FROM appointments WHERE appointment_date = ? AND status != 'Cancelled'";
        $stmt = $con->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $selectedDate);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $bookedSlots[] = $row['time_slot'];
            }
            $stmt->close(); 
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($bookedSlots);
}
?>