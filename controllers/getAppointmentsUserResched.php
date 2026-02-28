<?php
include_once("config.php");

    if (!isset($_GET['new_date']) || !isset($_GET['appointment_id'])) {
        echo json_encode([]);
        exit();
    }

    $newDate = $_GET['new_date'];
    $appointmentID = $_GET['appointment_id'];

    // Get the current appointment's time
    $currentTime = '';
    $stmt = $con->prepare("SELECT appointment_time FROM tbl_appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointmentID);
    $stmt->execute();
    $stmt->bind_result($currentTime);
    $stmt->fetch();
    $stmt->close();

    // Get other booked times for that date
    $stmt = $con->prepare("
        SELECT appointment_time FROM tbl_appointments 
        WHERE appointment_date = ? AND appointment_id != ? AND status != 'Cancelled'
    ");
    $stmt->bind_param("si", $newDate, $appointmentID);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookedSlots = [];
    while ($row = $result->fetch_assoc()) {
        $bookedSlots[] = $row['appointment_time'];
    }

    // Exclude current time from booked slots so user can still select it
    $bookedSlots = array_filter($bookedSlots, function($slot) use ($currentTime) {
        return $slot !== $currentTime;
    });

echo json_encode(array_values($bookedSlots));
?>
