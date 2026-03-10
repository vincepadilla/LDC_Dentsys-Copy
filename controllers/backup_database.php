<?php
session_start();
include_once("../database/config.php");

if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super-admin') {
    header("Location: ../views/login.php");
    exit();
}

// Get database name from connection
$db_name = 'dental_clinic_db'; // Default
if (isset($con)) {
    $db_result = mysqli_query($con, "SELECT DATABASE()");
    if ($db_result) {
        $db_row = mysqli_fetch_row($db_result);
        if ($db_row && isset($db_row[0])) {
            $db_name = $db_row[0];
        }
    }
}

// Create backup directory if it doesn't exist
$backup_dir = '../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Generate backup filename
$backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$backup_path = $backup_dir . $backup_filename;

// Get all tables
$tables = [];
$result = mysqli_query($con, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}

// Start backup file
$output = "-- Database Backup\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Database: {$db_name}\n\n";
$output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$output .= "START TRANSACTION;\n";
$output .= "SET time_zone = \"+00:00\";\n\n";

// Backup each table
foreach ($tables as $table) {
    // Get table structure
    $output .= "--\n";
    $output .= "-- Table structure for table `{$table}`\n";
    $output .= "--\n\n";
    
    $create_table = mysqli_query($con, "SHOW CREATE TABLE `{$table}`");
    $create_table_row = mysqli_fetch_row($create_table);
    $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $output .= $create_table_row[1] . ";\n\n";
    
    // Get table data
    $output .= "--\n";
    $output .= "-- Dumping data for table `{$table}`\n";
    $output .= "--\n\n";
    
    $data_result = mysqli_query($con, "SELECT * FROM `{$table}`");
    if (mysqli_num_rows($data_result) > 0) {
        $columns = [];
        $column_result = mysqli_query($con, "SHOW COLUMNS FROM `{$table}`");
        while ($col = mysqli_fetch_assoc($column_result)) {
            $columns[] = $col['Field'];
        }
        
        while ($row = mysqli_fetch_assoc($data_result)) {
            $output .= "INSERT INTO `{$table}` (`" . implode("`, `", $columns) . "`) VALUES (";
            $values = [];
            foreach ($columns as $col) {
                $value = $row[$col];
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $value = mysqli_real_escape_string($con, $value);
                    $values[] = "'{$value}'";
                }
            }
            $output .= implode(", ", $values) . ");\n";
        }
        $output .= "\n";
    }
}

$output .= "COMMIT;\n";

// Write to file
file_put_contents($backup_path, $output);

// Send file to browser for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $backup_filename . '"');
header('Content-Length: ' . filesize($backup_path));
readfile($backup_path);

exit();
