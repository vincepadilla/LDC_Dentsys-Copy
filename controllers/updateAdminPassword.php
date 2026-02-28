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

$user_id = $_SESSION['userID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['admin_account_error'] = "All password fields are required.";
        header("Location: ../views/admin_account.php");
        exit();
    }
    
    // Check if passwords match
    if ($new_password !== $confirm_password) {
        $_SESSION['admin_account_error'] = "New passwords do not match!";
        header("Location: ../views/admin_account.php");
        exit();
    }
    
    // Validate password requirements
    $errors = [];
    
    if (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $new_password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    if (!empty($errors)) {
        $_SESSION['admin_account_error'] = "Password requirements not met: " . implode(", ", $errors);
        header("Location: ../views/admin_account.php");
        exit();
    }
    
    try {
        // Get current password hash from user_account
        $check_query = $con->prepare("SELECT password_hash FROM user_account WHERE user_id = ?");
        $check_query->bind_param("s", $user_id);
        $check_query->execute();
        $result = $check_query->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['admin_account_error'] = "User account not found!";
            header("Location: ../views/admin_account.php");
            exit();
        }
        
        $user = $result->fetch_assoc();
        $current_password_hash = $user['password_hash'];
        
        // Verify current password
        if (!password_verify($current_password, $current_password_hash)) {
            $_SESSION['admin_account_error'] = "Current password is incorrect. Please try again.";
            header("Location: ../views/admin_account.php");
            exit();
        }
        
        // Hash the new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password
        $update_query = $con->prepare("UPDATE user_account SET password_hash = ? WHERE user_id = ?");
        $update_query->bind_param("ss", $new_password_hash, $user_id);
        
        if ($update_query->execute()) {
            $_SESSION['admin_account_success'] = "Password updated successfully!";
        } else {
            $_SESSION['admin_account_error'] = "Error updating password. Please try again.";
        }
        
        $update_query->close();
        
    } catch (Exception $e) {
        $_SESSION['admin_account_error'] = "An error occurred: " . $e->getMessage();
    }
    
    header("Location: ../views/admin_account.php");
    exit();
} else {
    header("Location: ../views/admin_account.php");
    exit();
}
?>
