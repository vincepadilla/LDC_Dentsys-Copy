<?php
session_start();
include_once("../database/config.php");

header('Content-Type: application/json');

// Check if admin is logged in and verified
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    echo json_encode(['success' => false, 'message' => 'Admin not verified']);
    exit();
}

// Get all feedbacks (pending, approved, rejected) for management
$statusFilter = $_GET['status'] ?? 'all';

if ($statusFilter === 'all') {
    $query = "SELECT feedback_id, user_id, patient_name, feedback_text, appointment_id, status, created_at, updated_at 
              FROM feedback 
              ORDER BY 
                  CASE status 
                      WHEN 'pending' THEN 1 
                      WHEN 'rejected' THEN 2 
                      WHEN 'approved' THEN 3 
                  END,
                  created_at DESC";
    $stmt = $con->prepare($query);
} else {
    $query = "SELECT feedback_id, user_id, patient_name, feedback_text, appointment_id, status, created_at, updated_at 
              FROM feedback 
              WHERE status = ?
              ORDER BY created_at DESC";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $statusFilter);
}

$stmt->execute();
$result = $stmt->get_result();

$feedbacks = [];
while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
}

// Get pending count
$countQuery = "SELECT COUNT(*) as pending_count FROM feedback WHERE status = 'pending'";
$countResult = mysqli_query($con, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$pendingCount = $countRow['pending_count'] ?? 0;

$stmt->close();

echo json_encode([
    'success' => true,
    'feedbacks' => $feedbacks,
    'pending_count' => (int)$pendingCount
]);
?>
