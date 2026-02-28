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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    // Validate inputs
    if ($feedback_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid feedback ID']);
        exit();
    }
    
    if (!in_array($status, ['pending', 'approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Update feedback status
    $query = "UPDATE feedback SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE feedback_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("si", $status, $feedback_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Feedback status updated successfully',
            'status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update feedback status: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
