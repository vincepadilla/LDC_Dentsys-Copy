<?php 
session_start();
include_once("../database/config.php");

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

/**
 * Send email notification to dentist about logout and upcoming appointments
 */
function sendLogoutEmailToDentist($dentistEmail, $dentistName, $appointmentDetails) {
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'padillavincehenrick@gmail.com';
        $mail->Password = 'glxd csoa ispj bvjg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Email content
        $mail->setFrom('padillavincehenrick@gmail.com', 'Landero Dental Clinic');
        $mail->addAddress($dentistEmail, $dentistName);
        $mail->isHTML(true);
        $mail->Subject = 'URGENT: Pending Appointments Today - Inactive Status Alert';
        
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #F59E0B; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { padding: 20px; background-color: #f8f9fa; border: 1px solid #ddd; }
                    .appointment-list { margin: 20px 0; }
                    .appointment-item { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #F59E0B; border-radius: 4px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                    .warning { background-color: #FEF3C7; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #F59E0B; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>⚠️ URGENT: Pending Appointments Alert</h2>
                    </div>
                    <div class='content'>
                        <p>Dear Dr. {$dentistName},</p>
                        <p>You have successfully logged out of the system. However, <strong>your account status has been set to inactive</strong> and you have <strong>PENDING appointments scheduled for TODAY</strong> that require your attention.</p>
                        <div class='warning'>
                            <strong>⚠️ Action Required:</strong> These appointments are still in &quot;Pending&quot; status and need to be confirmed. Please log in to your account to review and confirm these appointments.
                        </div>
                        <div class='appointment-list'>
                            <h3>Today's Pending Appointments:</h3>
                            {$appointmentDetails}
                        </div>
                        <p><strong>Important:</strong> Please log in again to activate your account and confirm these pending appointments. Failure to do so may result in appointment cancellations.</p>
                        <p>Best regards,<br>Landero Dental Clinic</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Dear Dr. {$dentistName},\n\nYou have successfully logged out of the system. However, your account status has been set to inactive and you have PENDING appointments scheduled for TODAY that require your attention.\n\n⚠️ Action Required: These appointments are still in 'Pending' status and need to be confirmed. Please log in to your account to review and confirm these appointments.\n\nImportant: Please log in again to activate your account and confirm these pending appointments. Failure to do so may result in appointment cancellations.\n\nBest regards,\nLandero Dental Clinic";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send email notification to admin about dentist logout
 */
function sendLogoutEmailToAdmin($adminEmail, $adminName, $dentistName, $appointmentDetails) {
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mlanderodentalclinic@gmail.com';
        $mail->Password = 'xrfp cpvv ckdv jmht';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Email content
        $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
        $mail->addAddress($adminEmail, $adminName);
        $mail->isHTML(true);
        $mail->Subject = 'URGENT: Inactive Dentist with Pending Appointments Today';
        
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #F59E0B; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { padding: 20px; background-color: #f8f9fa; border: 1px solid #ddd; }
                    .appointment-list { margin: 20px 0; }
                    .appointment-item { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #F59E0B; border-radius: 4px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                    .warning { background-color: #FEF3C7; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #F59E0B; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>⚠️ URGENT: Inactive Dentist Alert</h2>
                    </div>
                    <div class='content'>
                        <p>Dear {$adminName},</p>
                        <p>This is an <strong>URGENT</strong> notification to inform you that <strong>Dr. {$dentistName}</strong> has logged out of the system and their account status has been set to <strong>inactive</strong>.</p>
                        <div class='warning'>
                            <strong>⚠️ Action Required:</strong> The dentist has <strong>PENDING appointments scheduled for TODAY</strong> that need to be confirmed. These appointments are still in &quot;Pending&quot; status and require immediate attention.
                        </div>
                        <div class='appointment-list'>
                            <h3>Today's Pending Appointments:</h3>
                            {$appointmentDetails}
                        </div>
                        <p><strong>URGENT ACTION REQUIRED:</strong> Please monitor these appointments and ensure the dentist logs in to confirm these pending appointments. You may need to reassign these appointments to another active dentist if the dentist does not log in soon.</p>
                        <p>Best regards,<br>Landero Dental Clinic System</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Dear {$adminName},\n\nThis is an URGENT notification to inform you that Dr. {$dentistName} has logged out of the system and their account status has been set to inactive.\n\n⚠️ Action Required: The dentist has PENDING appointments scheduled for TODAY that need to be confirmed. These appointments are still in 'Pending' status and require immediate attention.\n\nURGENT ACTION REQUIRED: Please monitor these appointments and ensure the dentist logs in to confirm these pending appointments. You may need to reassign these appointments to another active dentist if the dentist does not log in soon.\n\nBest regards,\nLandero Dental Clinic System";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Check if user is logged in and is a dentist/admin
if (isset($_SESSION['userID'])) {
    $user_id = $_SESSION['userID'];
    
    // Check if user is in multidisciplinary_dental_team
    $checkDentist = $con->prepare("SELECT team_id, first_name, last_name, email FROM multidisciplinary_dental_team WHERE user_id = ?");
    $checkDentist->bind_param("s", $user_id);
    $checkDentist->execute();
    $dentistResult = $checkDentist->get_result();
    
    if ($dentistResult && $dentistResult->num_rows > 0) {
        $dentist = $dentistResult->fetch_assoc();
        $team_id = $dentist['team_id'];
        $dentist_name = $dentist['first_name'] . ' ' . $dentist['last_name'];
        $dentist_email = $dentist['email'] ?? '';
        
        // Update status to inactive
        $updateStatus = $con->prepare("UPDATE multidisciplinary_dental_team SET status = 'inactive', last_active = NOW() WHERE team_id = ?");
        $updateStatus->bind_param("s", $team_id);
        $updateStatus->execute();
        $updateStatus->close();
        
        // Check for TODAY's appointments with "Pending" status only
        $today = date('Y-m-d');
        $checkPendingAppointments = $con->prepare("
            SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
                   p.first_name, p.last_name, s.sub_service, s.service_category
            FROM appointments a
            LEFT JOIN patient_information p ON a.patient_id = p.patient_id
            LEFT JOIN services s ON a.service_id = s.service_id
            WHERE a.team_id = ? 
            AND a.appointment_date = ?
            AND a.status = 'Pending'
            ORDER BY a.appointment_time ASC
        ");
        $checkPendingAppointments->bind_param("ss", $team_id, $today);
        $checkPendingAppointments->execute();
        $pendingAppointmentsResult = $checkPendingAppointments->get_result();
        
        // Get admin user info
        $adminQuery = "SELECT user_id, email, first_name, last_name FROM user_account WHERE role = 'admin' LIMIT 1";
        $adminResult = mysqli_query($con, $adminQuery);
        $admin = mysqli_fetch_assoc($adminResult);
        $admin_email = $admin['email'] ?? '';
        $admin_name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? 'Admin'));
        
        // Build appointment details for emails (only today's pending appointments)
        $appointmentDetails = "";
        $appointmentCount = 0;
        
        while ($appointment = $pendingAppointmentsResult->fetch_assoc()) {
            $patient_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
            $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
            $appointment_time = $appointment['appointment_time'];
            
            $appointmentDetails .= "
                <div class='appointment-item'>
                    <strong>Patient:</strong> {$patient_name}<br>
                    <strong>Date:</strong> Today (" . date('F j, Y') . ")<br>
                    <strong>Time:</strong> {$appointment_time}<br>
                    <strong>Service:</strong> {$service}<br>
                    <strong>Status:</strong> Pending
                </div>
            ";
            $appointmentCount++;
        }
        
        // Send email alerts ONLY if dentist has TODAY's Pending appointments and is inactive
        if ($appointmentCount > 0) {
            if (!empty($dentist_email) && filter_var($dentist_email, FILTER_VALIDATE_EMAIL)) {
                sendLogoutEmailToDentist($dentist_email, $dentist_name, $appointmentDetails);
            }
            
            // Send email to admin if dentist has today's pending appointments
            if (!empty($admin_email) && filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                sendLogoutEmailToAdmin($admin_email, $admin_name, $dentist_name, $appointmentDetails);
            }
        }
        
        $checkPendingAppointments->close();
    }
    
    $checkDentist->close();
}

session_destroy(); 
header("Location: ../views/login.php"); 
exit();
?>