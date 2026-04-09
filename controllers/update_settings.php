<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

function isAjaxRequest() {
    return (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
}

function respond($success, $message, $statusCode = 200, $errors = []) {
    if (isAjaxRequest()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => (bool)$success,
            'message' => (string)$message,
            'errors' => $errors
        ]);
        exit();
    }
}

if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super-admin') {
    respond(false, 'Unauthorized', 401);
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
    // Current, supported setting keys in the system (table now contains only these)
    $knownKeys = [
        'appointment_slot_duration',
        'max_appointments_per_day',
        'advance_booking_limit',
        'walk_ins_enabled',
        'gcash_enabled',
        'maya_enabled',
        'reservation_fee_amount',
        'maintenance_mode',
        'gcash_account_name',
        'gcash_account_number',
        'gcash_qr_code',
        'maya_account_name',
        'maya_account_number',
        'maya_qr_code'
    ];

    // Boolean toggle keys (checkboxes) - save '0' when missing from POST
    $booleanKeys = ['walk_ins_enabled', 'gcash_enabled', 'maya_enabled', 'maintenance_mode'];

    // Build list of keys to process:
    // - Always include boolean keys to persist their 0/1 state
    // - Include text/number keys only if present in POST (avoid overwriting with empty)
    $settingsKeys = [];
    foreach ($knownKeys as $k) {
        if (in_array($k, $booleanKeys, true)) {
            $settingsKeys[] = $k;
        } else {
            if (isset($_POST[$k])) {
                $settingsKeys[] = $k;
            }
        }
    }

    $success = true;
    $errors = [];

    foreach ($settingsKeys as $key) {
        $value = '';
        
        // Handle checkbox values (toggle switches)
        if (in_array($key, $booleanKeys, true)) {
            $value = isset($_POST[$key]) ? '1' : '0';
        } else {
            $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        }

        // Determine section
        $section = 'appointment';
        if (strpos($key, 'gcash') !== false || 
            strpos($key, 'maya') !== false || strpos($key, 'reservation_fee') !== false) {
            $section = 'payment';
        } elseif (strpos($key, 'maintenance') !== false) {
            $section = 'maintenance';
        }

        // Validate numeric fields
        if (in_array($key, ['advance_booking_limit', 'reservation_fee_amount', 'appointment_slot_duration', 'max_appointments_per_day'], true)) {
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

    // Handle QR code uploads (optional)
    $uploadDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    $allowedExts = ['jpg','jpeg','png'];
    $allowedMime = ['image/jpeg','image/png'];
    $maxBytes = 5 * 1024 * 1024; // 5MB

    $qrFields = [
        'gcash_qr_code' => 'gcash_qr_code',
        'maya_qr_code' => 'maya_qr_code'
    ];

    foreach ($qrFields as $fileKey => $settingKey) {
        if (isset($_FILES[$fileKey]) && is_array($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES[$fileKey]['tmp_name'] ?? '';
            $origName = $_FILES[$fileKey]['name'] ?? '';
            $size = (int)($_FILES[$fileKey]['size'] ?? 0);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $mime = mime_content_type($tmpPath);

            if ($size <= 0 || $size > $maxBytes) {
                $errors[] = "File too large for {$fileKey}";
                $success = false;
                continue;
            }
            if (!in_array($ext, $allowedExts, true) || !in_array($mime, $allowedMime, true)) {
                $errors[] = "Invalid file type for {$fileKey}";
                $success = false;
                continue;
            }

            $unique = $fileKey . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destFsPath = $uploadDir . $unique;

            if (!@move_uploaded_file($tmpPath, $destFsPath)) {
                $errors[] = "Failed to store uploaded file for {$fileKey}";
                $success = false;
                continue;
            }

            // Build web path (relative from web root to uploads/qr/)
            $relativeWebPath = 'uploads/qr/' . $unique;

            // Upsert into system_settings
            $checkQuery = "SELECT setting_id FROM system_settings WHERE setting_key = ?";
            $checkStmt = $con->prepare($checkQuery);
            $checkStmt->bind_param("s", $settingKey);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkStmt->close();

            if ($checkResult->num_rows > 0) {
                $updateQuery = "UPDATE system_settings SET setting_value = ?, section = 'payment', updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?";
                $updateStmt = $con->prepare($updateQuery);
                $updateStmt->bind_param("ss", $relativeWebPath, $settingKey);
                if (!$updateStmt->execute()) {
                    $errors[] = "Failed to update {$settingKey}";
                    $success = false;
                }
                $updateStmt->close();
            } else {
                $insertQuery = "INSERT INTO system_settings (setting_key, setting_value, setting_type, section) VALUES (?, ?, 'text', 'payment')";
                $insertStmt = $con->prepare($insertQuery);
                $insertStmt->bind_param("ss", $settingKey, $relativeWebPath);
                if (!$insertStmt->execute()) {
                    $errors[] = "Failed to insert {$settingKey}";
                    $success = false;
                }
                $insertStmt->close();
            }
        }
    }

    if ($success) {
        $_SESSION['settings_success'] = 'Settings updated successfully!';
        respond(true, 'Settings updated successfully!');
    } else {
        $_SESSION['settings_error'] = 'Some settings could not be updated: ' . implode(', ', $errors);
        respond(false, $_SESSION['settings_error'], 400, $errors);
    }
} else {
    $_SESSION['settings_error'] = 'Invalid request method';
    respond(false, 'Invalid request method', 405);
}

header("Location: ../views/settings.php");
exit();
