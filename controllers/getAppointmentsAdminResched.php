<?php
include_once('../database/config.php');
header('Content-Type: application/json');

if (isset($_GET['new_date_resched'])) {
    $selectedDate = $_GET['new_date_resched'];
    $currentAppointmentId = $_GET['appointment_id'] ?? null;

    // Check if clinic is closed on this date
    $clinicClosed = false;
    $closureReason = '';
    $closureType = '';
    
    // Check if clinic_closures table exists
    $checkTable = "SHOW TABLES LIKE 'clinic_closures'";
    $tableExists = mysqli_query($con, $checkTable);
    
    if ($tableExists && mysqli_num_rows($tableExists) > 0) {
        $closureQuery = "SELECT closure_type, reason FROM clinic_closures WHERE closure_date = ? AND status = 'active' LIMIT 1";
        $closureStmt = $con->prepare($closureQuery);
        if ($closureStmt) {
            $closureStmt->bind_param("s", $selectedDate);
            $closureStmt->execute();
            $closureResult = $closureStmt->get_result();
            
            if ($closureRow = $closureResult->fetch_assoc()) {
                $clinicClosed = true;
                $closureType = $closureRow['closure_type'];
                $closureReason = $closureRow['reason'];
            }
            $closureStmt->close();
        }
    }
    
    // If clinic is fully closed, return all slots as unavailable
    if ($clinicClosed && $closureType === 'full_day') {
        $allSlots = ['firstBatch', 'secondBatch', 'thirdBatch', 'fourthBatch', 'fifthBatch', 
                      'sixthBatch', 'sevenBatch', 'eightBatch', 'nineBatch', 'tenBatch', 'lastBatch'];
        
        echo json_encode([
            'unavailable_slots' => $allSlots,
            'clinic_closed' => true,
            'closure_reason' => $closureReason,
            'closure_type' => $closureType
        ]);
        exit;
    }

    $bookedSlots = array();
    
    // Get booked slots for the selected date, excluding cancelled, no-show, and the current appointment being rescheduled
    $query = "SELECT time_slot FROM appointments 
              WHERE appointment_date = ? 
              AND status NOT IN ('Cancelled', 'No-show', 'no-show', 'cancelled', 'No-Show')
              AND time_slot IS NOT NULL 
              AND time_slot != ''";
    
    // If rescheduling, exclude the current appointment from booked slots
    if ($currentAppointmentId) {
        $query .= " AND appointment_id != ?";
    }
    
    $stmt = $con->prepare($query);
    if ($stmt) {
        if ($currentAppointmentId) {
            $stmt->bind_param("ss", $selectedDate, $currentAppointmentId);
        } else {
            $stmt->bind_param("s", $selectedDate);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['time_slot'])) {
                $bookedSlots[] = $row['time_slot'];
            }
        }
        $stmt->close(); 
    }
    
    // Also get blocked time slots for all dentists (blocked slots apply to all dentists)
    $blockedSlots = [];
    $blockedQuery = "SELECT DISTINCT time_slot FROM blocked_time_slots WHERE date = ?";
    $blockedStmt = $con->prepare($blockedQuery);
    if ($blockedStmt) {
        $blockedStmt->bind_param("s", $selectedDate);
        $blockedStmt->execute();
        $blockedResult = $blockedStmt->get_result();
        while ($row = $blockedResult->fetch_assoc()) {
            if (!empty($row['time_slot'])) {
                $blockedSlots[] = $row['time_slot'];
            }
        }
        $blockedStmt->close();
    }
    
    // Merge booked and blocked slots (remove duplicates)
    $unavailableSlots = array_unique(array_merge($bookedSlots, $blockedSlots));
    
    echo json_encode([
        'unavailable_slots' => array_values($unavailableSlots),
        'clinic_closed' => $clinicClosed,
        'closure_reason' => $closureReason,
        'closure_type' => $closureType
    ]);
} else {
    echo json_encode([
        'unavailable_slots' => [],
        'clinic_closed' => false,
        'closure_reason' => '',
        'closure_type' => ''
    ]);
}
?>