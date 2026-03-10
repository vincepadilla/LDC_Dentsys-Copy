<?php
session_start();
include_once("../database/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super-admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['restore_file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['restore_file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit();
}

// Validate file type
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($file_ext !== 'sql') {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a .sql file']);
    exit();
}

// Read SQL file
$sql_content = file_get_contents($file['tmp_name']);
if ($sql_content === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to read file']);
    exit();
}

// Disable foreign key checks temporarily
mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 0");

// Split SQL file into individual queries
$queries = array_filter(
    array_map('trim', explode(';', $sql_content)),
    function($query) {
        return !empty($query) && 
               !preg_match('/^(--|#|\/\*)/', $query) && 
               !preg_match('/^(SET|START|COMMIT)/i', $query);
    }
);

$success = true;
$error_message = '';

// Execute each query
foreach ($queries as $query) {
    if (!empty(trim($query))) {
        if (!mysqli_query($con, $query)) {
            $success = false;
            $error_message = mysqli_error($con);
            break;
        }
    }
}

// Re-enable foreign key checks
mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Restore failed: ' . $error_message]);
}

exit();
