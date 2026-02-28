<?php
include_once('../database/config.php');

$date = $_GET['date'] ?? '';

if (!$date) {
    echo json_encode([]);
    exit;
}

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
        $closureStmt->bind_param("s", $date);
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
    
    header('Content-Type: application/json');
    echo json_encode([
        'unavailable_slots' => $allSlots,
        'clinic_closed' => true,
        'closure_reason' => $closureReason,
        'closure_type' => $closureType
    ]);
    exit;
}

// Query appointments for that date, fetch booked time slots
$query = "SELECT time_slot FROM appointments WHERE appointment_date = ? AND status != 'Cancelled'";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$bookedSlots = [];
while ($row = $result->fetch_assoc()) {
    $bookedSlots[] = $row['time_slot'];
}
$stmt->close();

// Also get blocked time slots for all dentists (blocked slots apply to all dentists)
$blockedQuery = "SELECT DISTINCT time_slot FROM blocked_time_slots WHERE date = ?";
$blockedStmt = $con->prepare($blockedQuery);
$blockedStmt->bind_param("s", $date);
$blockedStmt->execute();
$blockedResult = $blockedStmt->get_result();

$blockedSlots = [];
while ($row = $blockedResult->fetch_assoc()) {
    $blockedSlots[] = $row['time_slot'];
}
$blockedStmt->close();

// Merge booked and blocked slots (remove duplicates)
$unavailableSlots = array_unique(array_merge($bookedSlots, $blockedSlots));

header('Content-Type: application/json');
echo json_encode([
    'unavailable_slots' => array_values($unavailableSlots),
    'clinic_closed' => $clinicClosed,
    'closure_reason' => $closureReason,
    'closure_type' => $closureType
]);
