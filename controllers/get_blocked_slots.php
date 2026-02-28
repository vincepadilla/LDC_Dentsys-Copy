<?php
include_once('../database/config.php');

// Get all blocked slots (for admin display)
$query = "
    SELECT 
        b.block_id,
        b.dentist_id,
        b.date,
        b.time_slot,
        b.reason,
        b.created_at,
        CONCAT(d.first_name, ' ', d.last_name) as dentist_name,
        CASE b.time_slot
            WHEN 'firstBatch' THEN '8:00-9:00 AM'
            WHEN 'secondBatch' THEN '9:00-10:00 AM'
            WHEN 'thirdBatch' THEN '10:00-11:00 AM'
            WHEN 'fourthBatch' THEN '11:00-12:00 PM'
            WHEN 'fifthBatch' THEN '1:00-2:00 PM'
            WHEN 'sixthBatch' THEN '2:00-3:00 PM'
            WHEN 'sevenBatch' THEN '3:00-4:00 PM'
            WHEN 'eightBatch' THEN '4:00-5:00 PM'
            WHEN 'nineBatch' THEN '5:00-6:00 PM'
            WHEN 'tenBatch' THEN '6:00-7:00 PM'
            WHEN 'lastBatch' THEN '7:00-8:00 PM'
            ELSE b.time_slot
        END as time_slot_display
    FROM blocked_time_slots b
    LEFT JOIN multidisciplinary_dental_team d ON b.dentist_id = d.team_id
    ORDER BY b.date DESC, b.time_slot ASC
";

$result = mysqli_query($con, $query);
$blockedSlots = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $blockedSlots[] = [
            'id' => $row['block_id'],
            'dentist_id' => $row['dentist_id'],
            'dentist_name' => $row['dentist_name'] ?? 'Unknown',
            'date' => $row['date'],
            'time_slot' => $row['time_slot'],
            'time_slot_display' => $row['time_slot_display'],
            'reason' => $row['reason'],
            'created_at' => $row['created_at']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($blockedSlots);
?>
