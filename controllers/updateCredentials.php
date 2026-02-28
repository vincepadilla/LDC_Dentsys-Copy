<?php
session_start();
include_once("../database/config.php");

if (!isset($_SESSION['userID'])) {
    // Check if AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit();
    }
    header("Location: ../views/login.php");
    exit();
}

$user_id = $_SESSION['userID'];
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Enhanced debugging
    error_log("=== PASSWORD UPDATE ATTEMPT ===");
    error_log("User ID: " . $user_id);
    error_log("Current password provided: " . (empty($current_password) ? 'EMPTY' : 'PROVIDED'));
    error_log("New password length: " . strlen($new_password));

    // Basic validation
    if ($new_password !== $confirm_password) {
        $errorMsg = "New passwords do not match!";
        $_SESSION['password_error'] = $errorMsg;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit();
        }
        header("Location: ../views/account.php");
        exit();
    }

    // Validate all password requirements
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
        $errorMsg = "Password requirements not met: " . implode(", ", $errors);
        $_SESSION['password_error'] = $errorMsg;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit();
        }
        header("Location: ../views/account.php");
        exit();
    }

    try {
        // Check user and password field - USING CORRECT COLUMN NAME password_hash
        $check_query = $con->prepare("SELECT username, password_hash FROM user_account WHERE user_id = ?");
        $check_query->bind_param("s", $user_id);
        $check_query->execute();
        $result = $check_query->get_result();
        
        if ($result->num_rows === 0) {
            $errorMsg = "User not found!";
            $_SESSION['password_error'] = $errorMsg;
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                exit();
            }
            header("Location: ../views/account.php");
            exit();
        }

        $user = $result->fetch_assoc();
        $current_password_hash = $user['password_hash'];
        $username = $user['username'];

        // Enhanced debugging
        error_log("Username: " . $username);
        error_log("Current password hash in DB: " . ($current_password_hash ?? 'NULL'));
        error_log("Hash length: " . strlen($current_password_hash));

        // Verify current password
        if (password_verify($current_password, $current_password_hash)) {
            error_log("Current password verification: SUCCESS");
            
            // Hash the new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            error_log("New password hash created: " . substr($new_password_hash, 0, 20) . "...");
            
            // Update the password - USING CORRECT COLUMN NAME password_hash
            $update_query = $con->prepare("UPDATE user_account SET password_hash = ? WHERE user_id = ?");
            $update_query->bind_param("ss", $new_password_hash, $user_id);
            
            if ($update_query->execute()) {
                error_log("Password update: SUCCESS");
                $successMsg = "Password updated successfully!";
                $_SESSION['password_success'] = $successMsg;
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $successMsg]);
                    exit();
                }
            } else {
                error_log("Password update: FAILED - " . $update_query->error);
                $errorMsg = "Error updating password. Please try again.";
                $_SESSION['password_error'] = $errorMsg;
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $errorMsg]);
                    exit();
                }
            }
        } else {
            error_log("Current password verification: FAILED");
            $errorMsg = "Current password is incorrect. Please try again.";
            $_SESSION['password_error'] = $errorMsg;
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                exit();
            }
        }

    } catch (Exception $e) {
        error_log("Password update exception: " . $e->getMessage());
        $errorMsg = "An error occurred. Please try again.";
        $_SESSION['password_error'] = $errorMsg;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit();
        }
    }

    // If not AJAX, redirect
    if (!$isAjax) {
        header("Location: ../views/account.php");
        exit();
    }
}
?>