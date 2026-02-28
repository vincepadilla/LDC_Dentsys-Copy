<?php
include_once('../database/config.php');

header('Content-Type: application/json');

// Check if clinic_closures table exists
$checkTable = "SHOW TABLES LIKE 'clinic_closures'";
$tableExists = mysqli_query($con, $checkTable);

$closedDates = [];

if ($tableExists && mysqli_num_rows($tableExists) > 0) {
    // Get all active clinic closures
    $today = date('Y-m-d');
    $query = "SELECT closure_date, closure_type, reason FROM clinic_closures WHERE status = 'active' AND closure_date >= ? ORDER BY closure_date ASC";
    $stmt = $con->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $closedDates[] = [
                'date' => $row['closure_date'],
                'closure_type' => $row['closure_type'],
                'reason' => $row['reason']
            ];
        }
        $stmt->close();
    }
}

// Also check holidays table for recurring holidays
$checkHolidaysTable = "SHOW TABLES LIKE 'holidays'";
$holidaysTableExists = mysqli_query($con, $checkHolidaysTable);

$holidayDates = [];

if ($holidaysTableExists && mysqli_num_rows($holidaysTableExists) > 0) {
    // Get all holidays (including recurring ones)
    $query = "SELECT holiday_date, recurrence FROM holidays WHERE holiday_date >= ? ORDER BY holiday_date ASC";
    $stmt = $con->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // For yearly recurring holidays, check if this year's date hasn't passed
            if ($row['recurrence'] === 'yearly') {
                $holidayDate = new DateTime($row['holiday_date']);
                $currentYear = date('Y');
                $holidayYear = $holidayDate->format('Y');
                
                // Check up to 2 years ahead
                for ($yearOffset = 0; $yearOffset <= 2; $yearOffset++) {
                    $checkYear = $currentYear + $yearOffset;
                    $checkDate = new DateTime($row['holiday_date']);
                    $checkDate->setDate($checkYear, (int)$holidayDate->format('m'), (int)$holidayDate->format('d'));
                    $checkDateStr = $checkDate->format('Y-m-d');
                    
                    if ($checkDateStr >= $today) {
                        // Check if not already in closed dates
                        $alreadyClosed = false;
                        foreach ($closedDates as $closed) {
                            if ($closed['date'] === $checkDateStr) {
                                $alreadyClosed = true;
                                break;
                            }
                        }
                        
                        if (!$alreadyClosed) {
                            $holidayDates[] = [
                                'date' => $checkDateStr,
                                'closure_type' => 'full_day',
                                'reason' => 'Holiday'
                            ];
                        }
                    }
                }
            } else {
                // One-time holiday
                $holidayDates[] = [
                    'date' => $row['holiday_date'],
                    'closure_type' => 'full_day',
                    'reason' => 'Holiday'
                ];
            }
        }
        $stmt->close();
    }
}

// Merge closed dates and holiday dates, removing duplicates
$allClosedDates = [];
$datesMap = [];

foreach ($closedDates as $closed) {
    $datesMap[$closed['date']] = $closed;
}

foreach ($holidayDates as $holiday) {
    if (!isset($datesMap[$holiday['date']])) {
        $datesMap[$holiday['date']] = $holiday;
    }
}

$allClosedDates = array_values($datesMap);

echo json_encode([
    'success' => true,
    'closed_dates' => $allClosedDates
]);
?>

