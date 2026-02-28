<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Fallback to POST if JSON is empty
    if (empty($data)) {
        $data = $_POST;
    }
    
    $action = $data['action'] ?? '';
    
    // Handle blocking/unblocking time slots
    if ($action === 'update_slot' || $action === 'block_slot' || $action === 'unblock_slot') {
        $dentist_id = $data['dentist_id'] ?? '';
        $date = $data['date'] ?? '';
        $time_slot = $data['time_slot'] ?? '';
        $status = $data['status'] ?? '';
        $reason = $data['reason'] ?? 'Blocked by admin';
        $block_id = $data['block_id'] ?? '';
        
        // Get admin user ID from session
        $created_by = $_SESSION['userID'] ?? '';
        
        if (empty($created_by)) {
            echo json_encode(["success" => false, "message" => "User session not found."]);
            exit;
        }
        
        // Handle unblocking by block_id (from table) - no validation needed
        if ($action === 'unblock_slot' && !empty($block_id)) {
            // Delete from blocked_time_slots
            $deleteQuery = "DELETE FROM blocked_time_slots WHERE block_id = ?";
            $deleteStmt = $con->prepare($deleteQuery);
            $deleteStmt->bind_param("s", $block_id);
            
            if ($deleteStmt->execute()) {
                echo json_encode(["success" => true, "message" => "Time slot unblocked successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error unblocking slot: " . $deleteStmt->error]);
            }
            $deleteStmt->close();
            $con->close();
            exit;
        }
        
        // Validate ENUM values (only needed for blocking/unblocking by date/slot)
        $validSlots = [
            'firstBatch','secondBatch','thirdBatch','fourthBatch',
            'fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch'
        ];
        $validStatus = ['available', 'booked', 'blocked'];
        
        // Only validate time_slot if it's provided and we're not unblocking by block_id
        if (!empty($time_slot) && !in_array($time_slot, $validSlots)) {
            echo json_encode(["success" => false, "message" => "Invalid time slot."]);
            exit;
        }
        
        // Handle unblocking by date/slot (from schedule view toggle)
        if ($status === 'available' && !empty($dentist_id) && !empty($date) && !empty($time_slot)) {
            // Delete from blocked_time_slots
            $deleteQuery = "DELETE FROM blocked_time_slots WHERE dentist_id = ? AND date = ? AND time_slot = ?";
            $deleteStmt = $con->prepare($deleteQuery);
            $deleteStmt->bind_param("sss", $dentist_id, $date, $time_slot);
            
            if ($deleteStmt->execute()) {
                echo json_encode(["success" => true, "message" => "Time slot unblocked successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error unblocking slot: " . $deleteStmt->error]);
            }
            $deleteStmt->close();
            $con->close();
            exit;
        }
        
        // Handle blocking
        if ($status === 'blocked') {
            // Check if already blocked
            $checkQuery = "SELECT block_id FROM blocked_time_slots WHERE dentist_id = ? AND date = ? AND time_slot = ?";
            $checkStmt = $con->prepare($checkQuery);
            $checkStmt->bind_param("sss", $dentist_id, $date, $time_slot);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                // Already blocked, just update reason if provided
                $existing = $result->fetch_assoc();
                $updateQuery = "UPDATE blocked_time_slots SET reason = ? WHERE block_id = ?";
                $updateStmt = $con->prepare($updateQuery);
                $updateStmt->bind_param("ss", $reason, $existing['block_id']);
                $updateStmt->execute();
                $updateStmt->close();
                echo json_encode(["success" => true, "message" => "Time slot already blocked, reason updated."]);
            } else {
                // Generate block_id
                $prefix = "BLK";
                $getLast = $con->query("SELECT block_id FROM blocked_time_slots ORDER BY block_id DESC LIMIT 1");
                if ($getLast && $getLast->num_rows > 0) {
                    $lastId = $getLast->fetch_assoc()['block_id'];
                    $num = (int)substr($lastId, 3) + 1;
                    $new_block_id = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
                } else {
                    $new_block_id = "BLK001";
                }
                
                // Insert into blocked_time_slots
                $insertQuery = "INSERT INTO blocked_time_slots (block_id, dentist_id, date, time_slot, reason, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                $insertStmt = $con->prepare($insertQuery);
                $insertStmt->bind_param("ssssss", $new_block_id, $dentist_id, $date, $time_slot, $reason, $created_by);
                
                if ($insertStmt->execute()) {
                    echo json_encode(["success" => true, "message" => "Time slot blocked successfully.", "block_id" => $new_block_id]);
                } else {
                    echo json_encode(["success" => false, "message" => "Error blocking slot: " . $insertStmt->error]);
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        } else {
            // If status is available, remove from blocked_time_slots if exists
            $deleteQuery = "DELETE FROM blocked_time_slots WHERE dentist_id = ? AND date = ? AND time_slot = ?";
            $deleteStmt = $con->prepare($deleteQuery);
            $deleteStmt->bind_param("sss", $dentist_id, $date, $time_slot);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            echo json_encode(["success" => true, "message" => "Schedule updated successfully."]);
        }
        
        $con->close();
        exit;
    }
    
    
    // Legacy code for dentist_schedule updates (keeping for backward compatibility)
    $dentist_id = $data['dentist_id'] ?? '';
    $date = $data['date'] ?? '';
    $time_slot = $data['time_slot'] ?? '';
    $status = $data['status'] ?? '';
    
    if (empty($dentist_id) || empty($date) || empty($time_slot) || empty($status)) {
        echo json_encode(["success" => false, "message" => "Missing required fields."]);
        exit;
    }
    
    $validSlots = [
        'firstBatch','secondBatch','thirdBatch','fourthBatch',
        'fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch'
    ];
    $validStatus = ['available', 'booked', 'blocked'];
    
    if (!in_array($time_slot, $validSlots) || !in_array($status, $validStatus)) {
        echo json_encode(["success" => false, "message" => "Invalid slot or status."]);
        exit;
    }
    
    // Generate schedule_id (e.g. SCH001)
    $prefix = "SCH";
    $getLast = $con->query("SELECT schedule_id FROM dentist_schedule ORDER BY schedule_id DESC LIMIT 1");
    if ($getLast && $getLast->num_rows > 0) {
        $lastId = $getLast->fetch_assoc()['schedule_id'];
        $num = (int)substr($lastId, 3) + 1;
        $schedule_id = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    } else {
        $schedule_id = "SCH001";
    }
    
    // Insert or update schedule
    $query = "
        INSERT INTO dentist_schedule (schedule_id, dentist_id, date, time_slot, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP
    ";
    $stmt = $con->prepare($query);
    $stmt->bind_param("sssss", $schedule_id, $dentist_id, $date, $time_slot, $status);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Schedule updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating schedule: " . $stmt->error]);
    }
    
    $stmt->close();
    $con->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}
?>
