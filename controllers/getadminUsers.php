<?php
session_start();
include_once('../database/config.php'); 

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Prevent any output before JSON
ob_start();

try {
    $sql = "SELECT user_id, first_name, last_name, email, phone FROM user_account WHERE role = 'admin'";
    $result = mysqli_query($con, $sql);

    $adminUsers = [];

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $adminUsers[] = $row;
            }
        }
    } else {
        // Query failed
        $error = mysqli_error($con);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database query failed: ' . $error]);
        exit;
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($adminUsers);
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
}
ob_end_flush();
?>