<?php
session_start();

// Always return JSON from this endpoint (avoid HTML warnings breaking fetch().json())
header('Content-Type: application/json; charset=utf-8');

// Ensure warnings/notices don't get printed as HTML into the JSON response
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once(__DIR__ . '/../database/config.php');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Ensure DB connection exists
if (!isset($con) || !$con) {
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized']);
    exit();
}

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

function blockDay($con, $data, $silent = false) {
    $date = $data['date'] ?? '';
    $closureType = $data['closure_type'] ?? 'full_day';
    $reason = $data['reason'] ?? '';
    $customReason = $data['custom_reason'] ?? '';
    $notifyPatients = $data['notify_patients'] ?? false;
    
    if (empty($date)) {
        $resp = ['success' => false, 'message' => 'Date is required'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    }
    
    // Use custom reason if reason is "Other"
    if ($reason === 'Other' && !empty($customReason)) {
        $reason = $customReason;
    }
    
    if (empty($reason)) {
        $resp = ['success' => false, 'message' => 'Reason is required'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    }
    
    // Check if clinic_closures table exists, if not create it
    checkClinicClosuresTable($con);
    
    // Check if closure already exists for this date
    $checkQuery = "SELECT id FROM clinic_closures WHERE closure_date = ? AND status = 'active'";
    $checkStmt = $con->prepare($checkQuery);
    if (!$checkStmt) {
        $resp = ['success' => false, 'message' => 'Failed to prepare closure check query'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    }
    $checkStmt->bind_param("s", $date);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $resp = ['success' => false, 'message' => 'Closure already exists for this date'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    }
    
    // Insert clinic closure
    $insertQuery = "INSERT INTO clinic_closures (closure_date, closure_type, reason, status, created_at) VALUES (?, ?, ?, 'active', NOW())";
    $insertStmt = $con->prepare($insertQuery);
    if (!$insertStmt) {
        $resp = ['success' => false, 'message' => 'Failed to prepare closure insert query'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    }
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
        
        $resp = ['success' => true, 'message' => 'Day blocked successfully'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    } else {
        $resp = ['success' => false, 'message' => 'Failed to block day'];
        if (!$silent) echo json_encode($resp);
        return $resp;
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
    if (!$insertStmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare holiday insert query']);
        return;
    }
    $insertStmt->bind_param("sss", $holidayName, $holidayDate, $recurrence);
    
    if ($insertStmt->execute()) {
        // Automatically create closure for this holiday (silent to avoid double JSON output)
        $blockResp = blockDay($con, [
            'date' => $holidayDate,
            'closure_type' => 'full_day',
            'reason' => "Holiday: $holidayName",
            'custom_reason' => '',
            'notify_patients' => true
        ], true);
        
        if (!empty($blockResp['success'])) {
            echo json_encode(['success' => true, 'message' => 'Holiday added and day blocked successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Holiday added (closure not created)', 'closure_error' => $blockResp['message'] ?? 'Unknown error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add holiday']);
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
    if (!$getStmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare holiday lookup query']);
        return;
    }
    $getStmt->bind_param("i", $holidayId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $holiday = $result->fetch_assoc();
    
    // Delete holiday
    $deleteQuery = "DELETE FROM holidays WHERE id = ?";
    $deleteStmt = $con->prepare($deleteQuery);
    if (!$deleteStmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare holiday delete query']);
        return;
    }
    $deleteStmt->bind_param("i", $holidayId);
    
    if ($deleteStmt->execute()) {
        // Optionally remove closure for this date
        if ($holiday) {
            removeClosure($con, ['date' => $holiday['holiday_date']], true);
        }
        
        echo json_encode(['success' => true, 'message' => 'Holiday deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete holiday']);
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

function removeClosure($con, $data, $silent = false) {
    $date = $data['date'] ?? '';
    
    if (empty($date)) {
        $resp = ['success' => false, 'message' => 'Date is required'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    }
    
    // Mark closure as inactive
    $updateQuery = "UPDATE clinic_closures SET status = 'inactive' WHERE closure_date = ? AND status = 'active'";
    $updateStmt = $con->prepare($updateQuery);
    if (!$updateStmt) {
        $resp = ['success' => false, 'message' => 'Failed to prepare closure update query'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    }
    $updateStmt->bind_param("s", $date);
    
    if ($updateStmt->execute()) {
        // Unblock all time slots for this date
        $unblockQuery = "DELETE FROM blocked_time_slots WHERE date = ? AND reason LIKE 'Clinic Closure:%'";
        $unblockStmt = $con->prepare($unblockQuery);
        if ($unblockStmt) {
            $unblockStmt->bind_param("s", $date);
            $unblockStmt->execute();
        }
        $resp = ['success' => true, 'message' => 'Closure removed successfully'];
        if (!$silent) echo json_encode($resp);
        return $resp;
    } else {
        $resp = ['success' => false, 'message' => 'Failed to remove closure'];
        if (!$silent) echo json_encode($resp);
        return $resp;
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
    if (!$result) return;
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

