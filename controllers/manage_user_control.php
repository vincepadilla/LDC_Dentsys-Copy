<?php
session_start();

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include_once('../database/config.php');

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
    case 'block_user':
        blockUser($con, $input);
        break;
    
    case 'unblock_user':
        unblockUser($con, $input);
        break;
    
    case 'send_promotional_email':
        sendPromotionalEmail($con, $input);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function blockUser($con, $data) {
    $userId = $data['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Check if user_account table has status column, if not create it
    checkUserAccountStatusColumn($con);
    
    // Block user by setting status to 'blocked'
    $updateQuery = "UPDATE user_account SET status = 'blocked' WHERE user_id = ? AND role != 'admin'";
    $updateStmt = $con->prepare($updateQuery);
    $updateStmt->bind_param("s", $userId);
    
    if ($updateStmt->execute()) {
        if ($updateStmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User blocked successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found or cannot be blocked']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to block user: ' . mysqli_error($con)]);
    }
    
    $updateStmt->close();
}

function unblockUser($con, $data) {
    $userId = $data['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    checkUserAccountStatusColumn($con);
    
    // Unblock user by setting status to 'active'
    $updateQuery = "UPDATE user_account SET status = 'active' WHERE user_id = ?";
    $updateStmt = $con->prepare($updateQuery);
    $updateStmt->bind_param("s", $userId);
    
    if ($updateStmt->execute()) {
        if ($updateStmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User unblocked successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unblock user: ' . mysqli_error($con)]);
    }
    
    $updateStmt->close();
}

function sendPromotionalEmail($con, $data) {
    $recipients = $data['recipients'] ?? 'all_users';
    $subject = $data['subject'] ?? '';
    $message = $data['message'] ?? '';
    
    if (empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        return;
    }
    
    // Validate subject and message length
    if (strlen($subject) > 255) {
        echo json_encode(['success' => false, 'message' => 'Subject is too long (max 255 characters)']);
        return;
    }
    
    if (strlen($message) > 500) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (max 500 characters)']);
        return;
    }
    
    // Get recipient emails based on selection
    $whereClause = "";
    if ($recipients === 'with_appointments') {
        $whereClause = "AND EXISTS (SELECT 1 FROM patient_information p 
                        INNER JOIN appointments a ON p.patient_id = a.patient_id 
                        WHERE p.user_id = ua.user_id AND a.status NOT IN ('Cancelled', 'No-show'))";
    } else if ($recipients === 'no_appointments') {
        $whereClause = "AND NOT EXISTS (SELECT 1 FROM patient_information p 
                        INNER JOIN appointments a ON p.patient_id = a.patient_id 
                        WHERE p.user_id = ua.user_id)";
    }
    
    $query = "SELECT DISTINCT ua.user_id, ua.email, ua.first_name, ua.last_name 
              FROM user_account ua 
              WHERE ua.role = 'patient' 
              AND (ua.status IS NULL OR ua.status != 'blocked')
              AND ua.email IS NOT NULL AND ua.email != '' 
              {$whereClause}
              LIMIT 500";
    
    $result = mysqli_query($con, $query);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch recipients: ' . mysqli_error($con)]);
        return;
    }
    
    $recipientCount = mysqli_num_rows($result);
    if ($recipientCount === 0) {
        echo json_encode(['success' => false, 'message' => 'No eligible recipients found for this campaign']);
        return;
    }
    
    $sentCount = 0;
    $failedCount = 0;
    
    // Initialize promotional_emails table if needed
    checkPromotionalEmailsTable($con);
    
    // Send emails using PHPMailer
    while ($user = mysqli_fetch_assoc($result)) {
        $userEmail = trim($user['email']);
        $userName = trim($user['first_name'] . ' ' . $user['last_name']);
        $userId = $user['user_id'];
        
        // Validate email format
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $failedCount++;
            continue;
        }
        
        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            // SMTP Configuration (same as appointmentProcess.php)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'padillavincehenrick@gmail.com';
            $mail->Password = 'glxd csoa ispj bvjg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('padillavincehenrick@gmail.com', 'Landero Dental Clinic');
            $mail->addAddress($userEmail, $userName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            
            // Create formatted HTML email body
            $mail->Body = "
                <div style='font-family: Poppins, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9fafb; border-radius: 8px;'>
                    <div style='background: white; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                        <h2 style='color: #2a9d8f; margin: 0 0 16px 0; font-size: 22px;'>Hello " . htmlspecialchars($userName) . ",</h2>
                        <div style='line-height: 1.6; color: #333333; font-size: 15px; margin: 16px 0;'>
                            " . nl2br(htmlspecialchars($message)) . "
                        </div>
                        <hr style='border: none; border-top: 2px solid #e5e7eb; margin: 24px 0;'>
                        <p style='color: #666666; font-size: 13px; margin: 0;'>
                            Warm regards,<br>
                            <strong style='color: #2a9d8f;'>Landero Dental Clinic</strong><br>
                            <em>Your Smile, Our Priority</em>
                        </p>
                    </div>
                    <p style='color: #999999; font-size: 11px; text-align: center; margin: 16px 0 0 0;'>
                        This is an automated message. Please do not reply to this email.
                    </p>
                </div>
            ";

            // Send the email
            if ($mail->send()) {
                // Log successful email
                $insertQuery = "INSERT INTO promotional_emails (user_id, email, subject, message, sent_at, status) 
                               VALUES (?, ?, ?, ?, NOW(), 'sent')";
                $stmt = $con->prepare($insertQuery);
                if ($stmt) {
                    $stmt->bind_param("ssss", $userId, $userEmail, $subject, $message);
                    $stmt->execute();
                    $stmt->close();
                }
                $sentCount++;
            } else {
                // Log failed email
                $failedCount++;
                error_log("Promotional email failed for {$userEmail}: " . $mail->ErrorInfo);
            }
        } catch (Exception $e) {
            // Log failed email
            $failedCount++;
            error_log("Promotional email exception for {$userEmail}: " . $e->getMessage());
        }
    }
    
    // Return success response with details
    if ($sentCount > 0) {
        echo json_encode([
            'success' => true, 
            'message' => "Campaign sent successfully to {$sentCount} recipient" . ($sentCount !== 1 ? 's' : '') . ".",
            'sent_count' => $sentCount,
            'failed_count' => $failedCount
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send emails. Please check your email settings.',
            'sent_count' => 0,
            'failed_count' => $failedCount
        ]);
    }
}

function checkUserAccountStatusColumn($con) {
    // Check if status column exists in user_account table
    $checkColumn = "SHOW COLUMNS FROM user_account LIKE 'status'";
    $result = mysqli_query($con, $checkColumn);
    
    if (mysqli_num_rows($result) == 0) {
        // Add status column if it doesn't exist
        $addColumn = "ALTER TABLE user_account ADD COLUMN status ENUM('active', 'blocked') NOT NULL DEFAULT 'active' AFTER role";
        mysqli_query($con, $addColumn);
    }
}

// Create promotional_emails table if it doesn't exist
function checkPromotionalEmailsTable($con) {
    $checkTable = "SHOW TABLES LIKE 'promotional_emails'";
    $result = mysqli_query($con, $checkTable);
    
    if (mysqli_num_rows($result) == 0) {
        $createTable = "CREATE TABLE promotional_emails (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(20),
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('sent', 'failed') DEFAULT 'sent',
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_email (email),
            INDEX idx_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($con, $createTable);
    }
}

// Initialize tables on first access
checkPromotionalEmailsTable($con);
?>

