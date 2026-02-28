<?php
include_once('../database/config.php');

$dentist = $_POST['dentist'] ?? 'Dr. Allen';
$duration = 60; // minutes per appointment
$restTime = 15; // minutes of rest required
date_default_timezone_set('Asia/Manila');

$today = new DateTime();
$suggestions = [];

// Loop through next 7 days
for ($i = 0; $i < 7; $i++) {
    $date = clone $today;
    $date->modify("+$i day");
    $formattedDate = $date->format('Y-m-d');

    // Dentist schedule (sample fixed time: 9AM–5PM)
    $dentistStart = new DateTime('09:00 AM');
    $dentistEnd = new DateTime('05:00 PM');

    // Get booked appointments for this dentist and date
    $query = "SELECT appointment_time FROM tbl_appointments 
              WHERE dentist = '$dentist' AND appointment_date = '$formattedDate' 
              AND status NOT IN ('Cancelled')";
    $result = mysqli_query($con, $query);

    $booked = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $range = explode('-', str_replace(' ', '', $row['appointment_time']));
        if (count($range) == 2) {
            $start = DateTime::createFromFormat('h:ia', $range[0]);
            $end = DateTime::createFromFormat('h:ia', $range[1]);
            if ($start && $end) $booked[] = [$start, $end];
        }
    }

    // Generate possible time slots
    $slot = clone $dentistStart;
    while ($slot < $dentistEnd) {
        $slotEnd = clone $slot;
        $slotEnd->modify("+{$duration} minutes");

        $isAvailable = true;
        foreach ($booked as [$bStart, $bEnd]) {
            // Check overlap or insufficient rest gap
            $gapBefore = ($slot->getTimestamp() - $bEnd->getTimestamp()) / 60;
            if (($slot < $bEnd && $slotEnd > $bStart) || ($gapBefore >= 0 && $gapBefore < $restTime)) {
                $isAvailable = false;
                break;
            }
        }

        if ($isAvailable && $slotEnd <= $dentistEnd) {
            $suggestions[] = [
                'date' => $formattedDate,
                'time' => $slot->format('g:iA') . '-' . $slotEnd->format('g:iA'),
                'dentist' => $dentist
            ];
            break 2; // first best suggestion found
        }

        $slot->modify('+15 minutes'); // try next 15-min increment
    }
}

// Output result
header('Content-Type: application/json');
echo json_encode($suggestions ? $suggestions[0] : ['message' => 'No suitable slot found']);
