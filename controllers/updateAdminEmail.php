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
    $new_email = mysqli_real_escape_string($con, trim($_POST['new_email']));
    
    if (empty($new_email)) {
        $_SESSION['admin_account_error'] = "Email address is required.";
        header("Location: ../views/admin_account.php");
        exit();
    }
    
    // Validate email format
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_account_error'] = "Please enter a valid email address.";
        header("Location: ../views/admin_account.php");
        exit();
    }
    
    // Check if email already exists in user_account (excluding current user)
    $checkEmail = $con->prepare("SELECT user_id FROM user_account WHERE email = ? AND user_id != ?");
    $checkEmail->bind_param("ss", $new_email, $user_id);
    $checkEmail->execute();
    $emailResult = $checkEmail->get_result();
    
    if ($emailResult->num_rows > 0) {
        $_SESSION['admin_account_error'] = "This email address is already in use by another account.";
        header("Location: ../views/admin_account.php");
        exit();
    }
    $checkEmail->close();
    
    // Check if email already exists in multidisciplinary_dental_team (excluding current user)
    $checkTeamEmail = $con->prepare("SELECT user_id FROM multidisciplinary_dental_team WHERE email = ? AND user_id != ?");
    $checkTeamEmail->bind_param("ss", $new_email, $user_id);
    $checkTeamEmail->execute();
    $teamEmailResult = $checkTeamEmail->get_result();
    
    if ($teamEmailResult->num_rows > 0) {
        $_SESSION['admin_account_error'] = "This email address is already in use by another team member.";
        header("Location: ../views/admin_account.php");
        exit();
    }
    $checkTeamEmail->close();
    
    try {
        // Update email in user_account table
        $updateUserAccount = $con->prepare("UPDATE user_account SET email = ? WHERE user_id = ?");
        $updateUserAccount->bind_param("ss", $new_email, $user_id);
        
        if (!$updateUserAccount->execute()) {
            throw new Exception("Failed to update email in user account.");
        }
        $updateUserAccount->close();
        
        // Update email in multidisciplinary_dental_team table
        $updateTeam = $con->prepare("UPDATE multidisciplinary_dental_team SET email = ? WHERE user_id = ?");
        $updateTeam->bind_param("ss", $new_email, $user_id);
        
        if (!$updateTeam->execute()) {
            throw new Exception("Failed to update email in team record.");
        }
        $updateTeam->close();
        
        // Update session email
        $_SESSION['email'] = $new_email;
        
        $_SESSION['admin_account_success'] = "Email updated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['admin_account_error'] = "Error updating email: " . $e->getMessage();
    }
    
    header("Location: ../views/admin_account.php");
    exit();
} else {
    header("Location: ../views/admin_account.php");
    exit();
}
?>
