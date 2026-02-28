<?php
session_start();
include_once('config.php');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

// Check if clinic_closures table exists
$checkTable = "SHOW TABLES LIKE 'clinic_closures'";
$result = mysqli_query($con, $checkTable);

if (mysqli_num_rows($result) == 0) {
    // Table doesn't exist, return empty array
    echo json_encode(['success' => true, 'closures' => []]);
    exit();
}

// Get active clinic closures
$today = date('Y-m-d');
$query = "SELECT id, closure_date, closure_type, reason, created_at 
          FROM clinic_closures 
          WHERE status = 'active' AND closure_date >= ?
          ORDER BY closure_date ASC";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$closures = [];
while ($row = mysqli_fetch_assoc($result)) {
    $closures[] = [
        'id' => $row['id'],
        'date' => $row['closure_date'],
        'closure_type' => $row['closure_type'],
        'reason' => $row['reason'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode(['success' => true, 'closures' => $closures]);
?>

