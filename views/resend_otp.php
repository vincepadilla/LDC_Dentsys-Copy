<?php
session_start();
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if session exists
if (!isset($_SESSION['temp_user']) || !isset($_SESSION['otp_expiry'])) {
    echo json_encode(['success' => false, 'message' => 'No registration session found. Please register again.']);
    exit;
}

// Check if OTP expired
if (time() > $_SESSION['otp_expiry']) {
    unset($_SESSION['temp_user'], $_SESSION['otp'], $_SESSION['otp_expiry']);
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please register again.']);
    exit;
}

// Rate limiting: Check if resend was attempted recently (within last 60 seconds)
if (isset($_SESSION['last_otp_resend']) && (time() - $_SESSION['last_otp_resend']) < 60) {
    $remaining = 60 - (time() - $_SESSION['last_otp_resend']);
    echo json_encode([
        'success' => false, 
        'message' => 'Please wait ' . $remaining . ' seconds before requesting a new OTP.'
    ]);
    exit;
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

// Generate new OTP
$new_otp = rand(100000, 999999);
$email = $_SESSION['temp_user']['email'];
$fname = $_SESSION['temp_user']['fname'];
$lname = $_SESSION['temp_user']['lname'];

// Update session with new OTP and expiry
$_SESSION['otp'] = $new_otp;
$_SESSION['otp_expiry'] = time() + 600; // 10 minutes
$_SESSION['last_otp_resend'] = time();

// Send OTP via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mlanderodentalclinic@gmail.com';
    $mail->Password = 'xrfp cpvv ckdv jmht';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
    $mail->addAddress($email, $fname . ' ' . $lname);
    $mail->Subject = 'OTP Verification - New Code';
    $mail->Body = "Hello $fname,\n\nYour new OTP for account verification is: $new_otp\n\nThis code will expire in 10 minutes.\n\nIf you did not request this code, please ignore this email.\n\nThank you!";

    $mail->send();
    
    echo json_encode([
        'success' => true, 
        'message' => 'New OTP has been sent to your email.',
        'expiry' => $_SESSION['otp_expiry']
    ]);
} catch (Exception $e) {
    error_log('Resend OTP Mailer Error: ' . $mail->ErrorInfo);
    echo json_encode([
        'success' => false, 
        'message' => 'Unable to send OTP at this time. Please try again later.'
    ]);
}
?>
