<?php
session_start();
include_once("../database/config.php");

if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../views/login.php");
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    header("Location: ../views/admin_verify.php");
    exit();
}

// Create site_content table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS site_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(100) UNIQUE NOT NULL,
    content_value TEXT,
    content_type VARCHAR(50) DEFAULT 'text',
    section VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

mysqli_query($con, $createTableQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // List of content keys to update
    $contentKeys = [
        'hero_title', 'hero_subtitle',
        'services_title', 'services_subtitle',
        'contact_title', 'contact_subtitle', 'contact_help_title', 'contact_help_text',
        'contact_hours', 'contact_phone', 'contact_email',
        'location_title', 'location_subtitle', 'location_comembo', 'location_taytay',
        'dentist_title', 'dentist_subtitle', 'dentist_name', 'dentist_specialty', 'dentist_experience'
    ];
    
    // Get current values from database to compare
    $currentContent = [];
    $placeholders = str_repeat('?,', count($contentKeys) - 1) . '?';
    $currentQuery = "SELECT content_key, content_value FROM site_content WHERE content_key IN ($placeholders)";
    $currentStmt = $con->prepare($currentQuery);
    if ($currentStmt) {
        $types = str_repeat('s', count($contentKeys));
        $currentStmt->bind_param($types, ...$contentKeys);
        $currentStmt->execute();
        $currentResult = $currentStmt->get_result();
        while ($row = $currentResult->fetch_assoc()) {
            $currentContent[$row['content_key']] = stripslashes($row['content_value']);
        }
        $currentStmt->close();
    }
    
    $updated = 0;
    $errors = [];
    
    foreach ($contentKeys as $key) {
        if (isset($_POST[$key])) {
            // Use trim only - prepared statements handle escaping automatically
            $value = trim($_POST[$key]);
            
            // Check if value actually changed
            $currentValue = isset($currentContent[$key]) ? trim($currentContent[$key]) : '';
            if ($value === $currentValue) {
                continue; // Skip if no change
            }
            
            // Determine section based on key
            $section = 'general';
            if (strpos($key, 'hero') !== false) $section = 'hero';
            elseif (strpos($key, 'service') !== false) $section = 'services';
            elseif (strpos($key, 'contact') !== false) $section = 'contact';
            elseif (strpos($key, 'location') !== false) $section = 'location';
            elseif (strpos($key, 'dentist') !== false) $section = 'dentist';
            
            // Use INSERT ... ON DUPLICATE KEY UPDATE
            $query = "INSERT INTO site_content (content_key, content_value, content_type, section) 
                     VALUES (?, ?, 'text', ?)
                     ON DUPLICATE KEY UPDATE 
                     content_value = VALUES(content_value),
                     section = VALUES(section),
                     updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $con->prepare($query);
            if ($stmt) {
                $stmt->bind_param("sss", $key, $value, $section);
                if ($stmt->execute()) {
                    $updated++;
                } else {
                    $errors[] = "Failed to update $key: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Failed to prepare statement for $key: " . $con->error;
            }
        }
    }
    
    if ($updated > 0 && empty($errors)) {
        $itemText = $updated === 1 ? 'item' : 'items';
        $_SESSION['content_success'] = "Content updated successfully! ($updated $itemText updated)";
    } elseif (!empty($errors)) {
        $_SESSION['content_error'] = "Some content could not be updated: " . implode(", ", $errors);
    } elseif ($updated === 0) {
        $_SESSION['content_success'] = "No changes detected. All content is up to date.";
    } else {
        $_SESSION['content_error'] = "No content was updated.";
    }
    
    header("Location: ../views/edit_content.php");
    exit();
} else {
    header("Location: ../views/edit_content.php");
    exit();
}
?>
