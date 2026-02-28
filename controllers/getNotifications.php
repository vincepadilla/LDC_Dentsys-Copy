<?php
session_start();
include_once('../database/config.php');
header('Content-Type: application/json');

if (!isset($_SESSION['valid']) || !isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['userID'];

try {
    // Fetch notifications from database
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $stmt = $con->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: ");
    }
    
    $stmt->bind_param("s", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $notifications = [];

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'count' => count($notifications) // Optional: include count
    ]);

} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to fetch notifications'
    ]);
}
?>