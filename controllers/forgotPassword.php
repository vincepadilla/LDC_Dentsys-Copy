<?php
session_start();
include_once('../database/config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($con, trim($_POST['email']));
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit();
    }
    
    // Check if email exists in user_account table
    $query = "SELECT user_id, username, first_name, last_name, email FROM user_account WHERE email = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Generate a new temporary password that meets requirements:
        // - At least 8 characters
        // - At least one uppercase letter
        // - At least one lowercase letter
        // - At least one number
        // - At least one special character
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';
        
        // Ensure we have at least one of each required character type
        $newPassword = '';
        $newPassword .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $newPassword .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $newPassword .= $numbers[random_int(0, strlen($numbers) - 1)];
        $newPassword .= $special[random_int(0, strlen($special) - 1)];
        
        // Fill the rest randomly (total 12 characters)
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = strlen($newPassword); $i < 12; $i++) {
            $newPassword .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password to randomize character positions
        $newPassword = str_shuffle($newPassword);
        
        // Hash the new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in database
        $updateQuery = "UPDATE user_account SET password_hash = ? WHERE user_id = ?";
        $updateStmt = $con->prepare($updateQuery);
        $updateStmt->bind_param("ss", $passwordHash, $user['user_id']);
        
        if ($updateStmt->execute()) {
            // Send email with new password
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mlanderodentalclinic@gmail.com';
                $mail->Password = 'xrfp cpvv ckdv jmht';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $userName = trim($user['first_name'] . ' ' . $user['last_name']);
                
                $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
                $mail->addAddress($user['email'], $userName);
                
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset - Landero Dental Clinic';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #166088;'>Password Reset Request</h2>
                        <p>Dear {$userName},</p>
                        <p>You have requested to reset your password for your Landero Dental Clinic account.</p>
                        <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #166088;'>
                            <p style='margin: 0;'><strong>Your new password is:</strong></p>
                            <p style='margin: 10px 0; font-size: 18px; font-weight: bold; color: #166088; letter-spacing: 2px;'>{$newPassword}</p>
                        </div>
                        <p><strong>Username:</strong> {$user['username']}</p>
                        <p style='color: #dc3545;'><strong>Important:</strong> Please change this password after logging in for security purposes.</p>
                        <p>If you did not request this password reset, please contact us immediately.</p>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='color: #666; font-size: 12px;'>This is an automated message from Landero Dental Clinic. Please do not reply to this email.</p>
                    </div>
                ";
                
                if ($mail->send()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Your new password has been sent to your email address. Please check your inbox.'
                    ]);
                } else {
                    // Rollback password update if email fails
                    // Note: In production, you might want to keep the password reset but log the email failure
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Password was reset but failed to send email. Please contact support.'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error sending email: ' . $mail->ErrorInfo
                ]);
            }
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error updating password. Please try again later.'
            ]);
        }
        
        $updateStmt->close();
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No account found with that email address. Please check your email and try again.'
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
