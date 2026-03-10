<?php
session_start();
include_once("../database/config.php");

if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super-admin') {
    header("Location: ../views/login.php");
    exit();
}

// Create system_settings table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    section VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

mysqli_query($con, $createTableQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // List of all settings keys
    $settingsKeys = [
        'advance_booking_limit',
        'walk_ins_enabled',
        'gcash_enabled',
        'maya_enabled',
        'reservation_fee_amount',
        'appointment_confirmation_email',
        'appointment_reminder_notifications',
        'promotional_campaign_emails',
        'default_user_role',
        'account_verification',
        'max_login_attempts',
        'session_timeout',
        'maintenance_mode'
    ];

    $success = true;
    $errors = [];

    foreach ($settingsKeys as $key) {
        $value = '';
        
        // Handle checkbox values (toggle switches)
        if (in_array($key, ['walk_ins_enabled', 'gcash_enabled', 'maya_enabled', 
                           'appointment_confirmation_email', 'appointment_reminder_notifications', 
                           'promotional_campaign_emails', 'maintenance_mode'])) {
            $value = isset($_POST[$key]) ? '1' : '0';
        } else {
            $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        }

        // Determine section
        $section = 'appointment';
        if (strpos($key, 'payment') !== false || strpos($key, 'gcash') !== false || 
            strpos($key, 'maya') !== false || strpos($key, 'reservation_fee') !== false) {
            $section = 'payment';
        } elseif (strpos($key, 'email') !== false || strpos($key, 'notification') !== false || 
                  strpos($key, 'promotional') !== false) {
            $section = 'email';
        } elseif (strpos($key, 'user') !== false || strpos($key, 'security') !== false || 
                  strpos($key, 'login') !== false || strpos($key, 'session') !== false || 
                  strpos($key, 'verification') !== false || strpos($key, 'role') !== false) {
            $section = 'security';
        } elseif (strpos($key, 'maintenance') !== false) {
            $section = 'maintenance';
        }

        // Validate numeric fields
        if (in_array($key, ['advance_booking_limit', 'reservation_fee_amount', 
                           'max_login_attempts', 'session_timeout'])) {
            if (!is_numeric($value) || $value < 0) {
                $errors[] = "Invalid value for {$key}";
                $success = false;
                continue;
            }
        }

        // Update or insert setting
        $checkQuery = "SELECT setting_id FROM system_settings WHERE setting_key = ?";
        $checkStmt = $con->prepare($checkQuery);
        $checkStmt->bind_param("s", $key);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();

        if ($checkResult->num_rows > 0) {
            // Update existing setting
            $updateQuery = "UPDATE system_settings SET setting_value = ?, section = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?";
            $updateStmt = $con->prepare($updateQuery);
            $updateStmt->bind_param("sss", $value, $section, $key);
            
            if (!$updateStmt->execute()) {
                $errors[] = "Failed to update {$key}";
                $success = false;
            }
            $updateStmt->close();
        } else {
            // Insert new setting
            $insertQuery = "INSERT INTO system_settings (setting_key, setting_value, setting_type, section) VALUES (?, ?, 'text', ?)";
            $insertStmt = $con->prepare($insertQuery);
            $insertStmt->bind_param("sss", $key, $value, $section);
            
            if (!$insertStmt->execute()) {
                $errors[] = "Failed to insert {$key}";
                $success = false;
            }
            $insertStmt->close();
        }
    }

    if ($success) {
        $_SESSION['settings_success'] = 'Settings updated successfully!';
    } else {
        $_SESSION['settings_error'] = 'Some settings could not be updated: ' . implode(', ', $errors);
    }
} else {
    $_SESSION['settings_error'] = 'Invalid request method';
}

header("Location: ../views/settings.php");
exit();
