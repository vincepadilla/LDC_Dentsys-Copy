<?php
session_start();
include_once('config.php');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'block_day':
        blockDay($con, $input);
        break;
    
    case 'add_holiday':
        addHoliday($con, $input);
        break;
    
    case 'delete_holiday':
        deleteHoliday($con, $input);
        break;
    
    case 'emergency_closure':
        emergencyClosure($con, $input);
        break;
    
    case 'remove_closure':
        removeClosure($con, $input);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function blockDay($con, $data) {
    $date = $data['date'] ?? '';
    $closureType = $data['closure_type'] ?? 'full_day';
    $reason = $data['reason'] ?? '';
    $customReason = $data['custom_reason'] ?? '';
    $notifyPatients = $data['notify_patients'] ?? false;
    
    if (empty($date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required']);
        return;
    }
    
    // Use custom reason if reason is "Other"
    if ($reason === 'Other' && !empty($customReason)) {
        $reason = $customReason;
    }
    
    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Reason is required']);
        return;
    }
    
    // Check if clinic_closures table exists, if not create it
    checkClinicClosuresTable($con);
    
    // Check if closure already exists for this date
    $checkQuery = "SELECT id FROM clinic_closures WHERE closure_date = ? AND status = 'active'";
    $checkStmt = $con->prepare($checkQuery);
    $checkStmt->bind_param("s", $date);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Closure already exists for this date']);
        return;
    }
    
    // Insert clinic closure
    $insertQuery = "INSERT INTO clinic_closures (closure_date, closure_type, reason, status, created_at) VALUES (?, ?, ?, 'active', NOW())";
    $insertStmt = $con->prepare($insertQuery);
    $insertStmt->bind_param("sss", $date, $closureType, $reason);
    
    if ($insertStmt->execute()) {
        $closureId = $con->insert_id;
        
        // If full_day closure, block all time slots for all dentists on that date
        if ($closureType === 'full_day') {
            blockAllTimeSlotsForDate($con, $date, $reason, $closureId);
        }
        
        // If notify patients is enabled, notify affected patients
        if ($notifyPatients) {
            notifyAffectedPatients($con, $date, $reason, $closureType);
        }
        
        echo json_encode(['success' => true, 'message' => 'Day blocked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to block day: ' . mysqli_error($con)]);
    }
}

function addHoliday($con, $data) {
    $holidayName = $data['holiday_name'] ?? '';
    $holidayDate = $data['holiday_date'] ?? '';
    $recurrence = $data['recurrence'] ?? 'once';
    
    if (empty($holidayName) || empty($holidayDate)) {
        echo json_encode(['success' => false, 'message' => 'Holiday name and date are required']);
        return;
    }
    
    // Check if holidays table exists, if not create it
    checkHolidaysTable($con);
    
    // Insert holiday
    $insertQuery = "INSERT INTO holidays (holiday_name, holiday_date, recurrence, created_at) VALUES (?, ?, ?, NOW())";
    $insertStmt = $con->prepare($insertQuery);
    $insertStmt->bind_param("sss", $holidayName, $holidayDate, $recurrence);
    
    if ($insertStmt->execute()) {
        // Automatically create closure for this holiday
        blockDay($con, [
            'date' => $holidayDate,
            'closure_type' => 'full_day',
            'reason' => "Holiday: $holidayName",
            'custom_reason' => '',
            'notify_patients' => true
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Holiday added and day blocked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add holiday: ' . mysqli_error($con)]);
    }
}

function deleteHoliday($con, $data) {
    $holidayId = $data['holiday_id'] ?? 0;
    
    if (empty($holidayId)) {
        echo json_encode(['success' => false, 'message' => 'Holiday ID is required']);
        return;
    }
    
    // Get holiday date before deleting
    $getQuery = "SELECT holiday_date FROM holidays WHERE id = ?";
    $getStmt = $con->prepare($getQuery);
    $getStmt->bind_param("i", $holidayId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $holiday = $result->fetch_assoc();
    
    // Delete holiday
    $deleteQuery = "DELETE FROM holidays WHERE id = ?";
    $deleteStmt = $con->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $holidayId);
    
    if ($deleteStmt->execute()) {
        // Optionally remove closure for this date
        if ($holiday) {
            removeClosure($con, ['date' => $holiday['holiday_date']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Holiday deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete holiday: ' . mysqli_error($con)]);
    }
}

function emergencyClosure($con, $data) {
    $startDate = $data['start_date'] ?? '';
    $endDate = $data['end_date'] ?? $startDate;
    $reason = $data['reason'] ?? 'Emergency closure';
    $notifyPatients = $data['notify_patients'] ?? false;
    
    if (empty($startDate)) {
        echo json_encode(['success' => false, 'message' => 'Start date is required']);
        return;
    }
    
    checkClinicClosuresTable($con);
    
    // Create date range
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day'); // Include end date
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end);
    
    $cancelledCount = 0;
    
    // Block each date in the range
    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        
        // Check if closure already exists
        $checkQuery = "SELECT id FROM clinic_closures WHERE closure_date = ? AND status = 'active'";
        $checkStmt = $con->prepare($checkQuery);
        $checkStmt->bind_param("s", $dateStr);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 0) {
            // Insert clinic closure directly
            $insertQuery = "INSERT INTO clinic_closures (closure_date, closure_type, reason, status, created_at) VALUES (?, 'full_day', ?, 'active', NOW())";
            $insertStmt = $con->prepare($insertQuery);
            $emergencyReason = "Emergency: $reason";
            $insertStmt->bind_param("ss", $dateStr, $emergencyReason);
            $insertStmt->execute();
            $closureId = $con->insert_id;
            
            // Block all time slots
            blockAllTimeSlotsForDate($con, $dateStr, $emergencyReason, $closureId);
        }
        
        // Cancel all appointments for this date
        $cancelQuery = "UPDATE appointments SET status = 'Cancelled' WHERE appointment_date = ? AND status NOT IN ('Cancelled', 'Completed', 'No-show')";
        $cancelStmt = $con->prepare($cancelQuery);
        $cancelStmt->bind_param("s", $dateStr);
        $cancelStmt->execute();
        $cancelledCount += $cancelStmt->affected_rows;
        
        // Notify affected patients
        if ($notifyPatients) {
            notifyAffectedPatients($con, $dateStr, $emergencyReason, 'full_day');
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Emergency closure activated from $startDate to $endDate",
        'cancelled_count' => $cancelledCount
    ]);
}

function removeClosure($con, $data) {
    $date = $data['date'] ?? '';
    
    if (empty($date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required']);
        return;
    }
    
    // Mark closure as inactive
    $updateQuery = "UPDATE clinic_closures SET status = 'inactive' WHERE closure_date = ? AND status = 'active'";
    $updateStmt = $con->prepare($updateQuery);
    $updateStmt->bind_param("s", $date);
    
    if ($updateStmt->execute()) {
        // Unblock all time slots for this date
        $unblockQuery = "DELETE FROM blocked_time_slots WHERE date = ? AND reason LIKE 'Clinic Closure:%'";
        $unblockStmt = $con->prepare($unblockQuery);
        $unblockStmt->bind_param("s", $date);
        $unblockStmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Closure removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove closure: ' . mysqli_error($con)]);
    }
}

function blockAllTimeSlotsForDate($con, $date, $reason, $closureId = null) {
    // Get all active dentists
    $dentistsQuery = "SELECT team_id FROM multidisciplinary_dental_team WHERE status = 'active'";
    $dentistsResult = mysqli_query($con, $dentistsQuery);
    
    if (!$dentistsResult || mysqli_num_rows($dentistsResult) == 0) {
        return; // No active dentists
    }
    
    $timeSlots = ['firstBatch', 'secondBatch', 'thirdBatch', 'fourthBatch', 'fifthBatch', 
                  'sixthBatch', 'sevenBatch', 'eightBatch', 'nineBatch', 'tenBatch', 'lastBatch'];
    
    $closureReason = "Clinic Closure: $reason";
    
    while ($dentist = mysqli_fetch_assoc($dentistsResult)) {
        $dentistId = $dentist['team_id'];
        
        foreach ($timeSlots as $timeSlot) {
            // Check if already blocked
            $checkQuery = "SELECT block_id FROM blocked_time_slots WHERE dentist_id = ? AND date = ? AND time_slot = ?";
            $checkStmt = $con->prepare($checkQuery);
            $checkStmt->bind_param("sss", $dentistId, $date, $timeSlot);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows == 0) {
                // Insert blocked slot
                $insertQuery = "INSERT INTO blocked_time_slots (dentist_id, date, time_slot, reason, created_at) VALUES (?, ?, ?, ?, NOW())";
                $insertStmt = $con->prepare($insertQuery);
                $insertStmt->bind_param("ssss", $dentistId, $date, $timeSlot, $closureReason);
                $insertStmt->execute();
            }
        }
    }
}

function notifyAffectedPatients($con, $date, $reason, $closureType) {
    // Get affected appointments
    $appointmentsQuery = "SELECT appointment_id, patient_id, appointment_date, appointment_time 
                          FROM appointments 
                          WHERE appointment_date = ? AND status NOT IN ('Cancelled', 'Completed', 'No-show')";
    $apptStmt = $con->prepare($appointmentsQuery);
    $apptStmt->bind_param("s", $date);
    $apptStmt->execute();
    $appointments = $apptStmt->get_result();
    
    // Create notifications for affected patients
    while ($appointment = $appointments->fetch_assoc()) {
        if (!empty($appointment['patient_id'])) {
            // Get user_id from patient_id
            $userQuery = "SELECT user_id FROM patient_information WHERE patient_id = ?";
            $userStmt = $con->prepare($userQuery);
            $userStmt->bind_param("s", $appointment['patient_id']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userRow = $userResult->fetch_assoc()) {
                $userId = $userRow['user_id'];
                
                // Create notification
                $notificationId = generateNotificationID($con);
                $notificationQuery = "INSERT INTO notifications (notification_id, user_id, type, appointment_date, appointment_time, message, is_read, created_at) 
                                      VALUES (?, ?, 'closure', ?, ?, ?, 0, NOW())";
                $notifStmt = $con->prepare($notificationQuery);
                $message = "Clinic Closure: $reason - Your appointment on $date has been affected.";
                $notifStmt->bind_param("sssss", $notificationId, $userId, $appointment['appointment_date'], $appointment['appointment_time'], $message);
                $notifStmt->execute();
            }
        }
    }
}

function checkClinicClosuresTable($con) {
    // Check if table exists, create if not
    $checkTable = "SHOW TABLES LIKE 'clinic_closures'";
    $result = mysqli_query($con, $checkTable);
    
    if (mysqli_num_rows($result) == 0) {
        $createTable = "CREATE TABLE clinic_closures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            closure_date DATE NOT NULL,
            closure_type ENUM('full_day', 'no_new_appointments') NOT NULL DEFAULT 'full_day',
            reason VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_active_closure (closure_date, status),
            INDEX idx_closure_date (closure_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($con, $createTable);
    }
}

function checkHolidaysTable($con) {
    // Check if table exists, create if not
    $checkTable = "SHOW TABLES LIKE 'holidays'";
    $result = mysqli_query($con, $checkTable);
    
    if (mysqli_num_rows($result) == 0) {
        $createTable = "CREATE TABLE holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_name VARCHAR(255) NOT NULL,
            holiday_date DATE NOT NULL,
            recurrence ENUM('once', 'yearly') NOT NULL DEFAULT 'once',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_holiday_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($con, $createTable);
    }
}

function generateNotificationID($con) {
    $query = "SELECT notification_id FROM notifications ORDER BY notification_id DESC LIMIT 1";
    $result = mysqli_query($con, $query);
    $row = mysqli_fetch_assoc($result);
    if ($row) {
        $lastNum = intval(substr($row['notification_id'], 1)) + 1;
    } else {
        $lastNum = 1;
    }
    return 'N' . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
}
?>

