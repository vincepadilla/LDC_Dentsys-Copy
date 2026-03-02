<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once(__DIR__ . '/../database/config.php');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if holidays table exists
$checkTable = "SHOW TABLES LIKE 'holidays'";
$result = mysqli_query($con, $checkTable);

if (mysqli_num_rows($result) == 0) {
    // Table doesn't exist, return empty array
    echo json_encode(['success' => true, 'holidays' => []]);
    exit();
}

// Get all holidays
$query = "SELECT id, holiday_name, holiday_date, recurrence, created_at 
          FROM holidays 
          ORDER BY holiday_date ASC";
$result = mysqli_query($con, $query);

$holidays = [];
while ($row = mysqli_fetch_assoc($result)) {
    $holidays[] = [
        'id' => $row['id'],
        'holiday_name' => $row['holiday_name'],
        'holiday_date' => $row['holiday_date'],
        'recurrence' => $row['recurrence'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode(['success' => true, 'holidays' => $holidays]);
?>

