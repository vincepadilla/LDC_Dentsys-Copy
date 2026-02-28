<?php


// Include database configuration
include_once('../database/config.php');

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

/**
 * Generate next alert ID (AL001, AL002, etc.)
 */
function generateAlertID($con) {
    $query = "SELECT alert_id FROM system_alerts ORDER BY alert_id DESC LIMIT 1";
    $result = mysqli_query($con, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastID = $row['alert_id'];
        // Extract number from AL001 format
        if (preg_match('/AL(\d+)/', $lastID, $matches)) {
            $number = intval($matches[1]);
            $newNumber = $number + 1;
        } else {
            $newNumber = 1;
        }
    } else {
        $newNumber = 1;
    }
    
    return 'AL' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

/**
 * Check if alert already exists for this appointment and user
 */
function alertExists($con, $user_id, $related_appointment_id) {
    $query = "SELECT alert_id FROM system_alerts 
              WHERE user_id = ? 
              AND related_appointment_id = ? 
              AND is_read = 0
              LIMIT 1";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ss", $user_id, $related_appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Insert alert into system_alerts table
 */
function insertAlert($con, $alert_id, $user_id, $role, $title, $message, $related_appointment_id = null) {
    $query = "INSERT INTO system_alerts 
              (alert_id, user_id, role, title, message, related_appointment_id, is_read, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ssssss", $alert_id, $user_id, $role, $title, $message, $related_appointment_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        error_log("Error inserting alert: " . mysqli_error($con));
        $stmt->close();
        return false;
    }
}

/**
 * Get admin user ID and email
 */
function getAdminUserInfo($con) {
    $query = "SELECT user_id, email, first_name, last_name FROM user_account WHERE role = 'admin' LIMIT 1";
    $result = mysqli_query($con, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Send email notification to admin about inactive dentists
 */
function sendEmailToAdmin($adminEmail, $adminName, $inactiveDentistDetails) {
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
        $mail->Subject = 'System Alert: Inactive Dentists with Confirmed Appointments';
        
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #F59E0B; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { padding: 20px; background-color: #f8f9fa; border: 1px solid #ddd; }
                    .dentist-list { margin: 20px 0; }
                    .dentist-item { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #F59E0B; border-radius: 4px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>⚠️ Inactive Dentist Alert</h2>
                        <p>Inactive dentists have confirmed appointments scheduled.</p>
                    </div>
                    <div class='content'>
                        <p>Dear {$adminName},</p>
                        <p>This is an <strong>URGENT</strong> automated alert to inform you that there are inactive dentists in the system who have confirmed appointments scheduled for <strong>TODAY</strong>.</p>
                        <div class='dentist-list'>
                            <h3>Inactive Dentists & Their Today's Appointments:</h3>
                            {$inactiveDentistDetails}
                        </div>
                        <p><strong>URGENT ACTION REQUIRED:</strong> Please review these appointments immediately and either activate the dentists or reassign the appointments to active dentists.</p>
                        <p>Best regards,<br>Landero Dental Clinic System</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Dear {$adminName},\n\nThis is an URGENT automated alert to inform you that there are inactive dentists in the system who have confirmed appointments scheduled for TODAY.\n\nURGENT ACTION REQUIRED: Please review these appointments immediately and either activate the dentists or reassign the appointments to active dentists.\n\nBest regards,\nLandero Dental Clinic System";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send email notification to dentist
 */
function sendEmailToDentist($dentistEmail, $dentistName, $appointmentDetails) {
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
        $mail->Subject = 'Inactivity Alert - Upcoming Appointments';
        
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f8f9fa; }
                    .appointment-list { margin: 20px 0; }
                    .appointment-item { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>⚠️ Inactivity Alert</h2>
                    </div>
                    <div class='content'>
                        <p>Dear Dr. {$dentistName},</p>
                        <p>This is an automated alert to inform you that your account has been marked as inactive, but you have confirmed appointments scheduled for <strong>today</strong>.</p>
                        <div class='appointment-list'>
                            <h3>Today's Appointments:</h3>
                            {$appointmentDetails}
                        </div>
                        <p>Please update your account status to active immediately or contact the administrator if you need assistance.</p>
                        <p>Best regards,<br>Landero Dental Clinic</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Dear Dr. {$dentistName},\n\nThis is an automated alert to inform you that your account has been marked as inactive, but you have confirmed appointments scheduled for TODAY.\n\nPlease update your account status to active immediately or contact the administrator if you need assistance.\n\nBest regards,\nLandero Dental Clinic";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Main function to check dentist inactivity and create alerts
 */
function checkDentistInactivity($con) {
    // Find inactive dentists from multidisciplinary_dental_team table
    // Inactive if: status = 'inactive' OR last_active < NOW() - INTERVAL 24 HOUR
    // Note: The email field is retrieved from multidisciplinary_dental_team table and will be used for notifications
    $inactiveDentistsQuery = "
        SELECT team_id, user_id, first_name, last_name, email, status, last_active
        FROM multidisciplinary_dental_team
        WHERE status = 'inactive' 
           OR last_active IS NULL 
           OR last_active < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";
    
    $result = mysqli_query($con, $inactiveDentistsQuery);
    
    if (!$result) {
        error_log("Error querying inactive dentists: " . mysqli_error($con));
        return false;
    }
    
    $emailsSent = 0;
    
    // Get admin user info for email notifications
    $adminInfo = getAdminUserInfo($con);
    $adminEmail = $adminInfo ? ($adminInfo['email'] ?? '') : '';
    $adminName = $adminInfo ? trim(($adminInfo['first_name'] ?? '') . ' ' . ($adminInfo['last_name'] ?? '')) : 'Admin';
    
    $adminDentistDetails = "";
    $totalInactiveDentists = 0;
    
    // Process each inactive dentist
    while ($dentist = mysqli_fetch_assoc($result)) {
        $team_id = $dentist['team_id'];
        $dentist_user_id = $dentist['user_id'];
        $dentistName = trim($dentist['first_name'] . ' ' . $dentist['last_name']);
        // Get email from multidisciplinary_dental_team table
        $dentistEmail = trim($dentist['email']);
        
        // Find confirmed appointments for TODAY only (current date, not yesterday or future dates)
        $appointmentsQuery = "
            SELECT a.appointment_id, a.patient_id, a.appointment_date, a.appointment_time, a.status,
                   p.first_name, p.last_name, p.user_id as patient_user_id
            FROM appointments a
            INNER JOIN patient_information p ON a.patient_id = p.patient_id
            WHERE a.team_id = ?
              AND a.status = 'Confirmed'
              AND a.appointment_date = CURDATE()
            ORDER BY a.appointment_time ASC
        ";
        
        $stmt = $con->prepare($appointmentsQuery);
        $stmt->bind_param("s", $team_id);
        $stmt->execute();
        $appointmentsResult = $stmt->get_result();
        
        if ($appointmentsResult->num_rows > 0) {
            $appointmentItems = "";
            $appointmentCount = 0;
            $dentistAppointmentDetails = "";
            
            // Process each appointment
            while ($appointment = $appointmentsResult->fetch_assoc()) {
                $appointment_id = $appointment['appointment_id'];
                $patient_user_id = $appointment['patient_user_id'];
                $patientName = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
                $appointmentDate = $appointment['appointment_date'];
                $appointmentTime = $appointment['appointment_time'];
                
                // Build appointment details for email to dentist
                $appointmentItems .= "
                    <div class='appointment-item'>
                        <strong>Patient:</strong> {$patientName}<br>
                        <strong>Date:</strong> {$appointmentDate}<br>
                        <strong>Time:</strong> {$appointmentTime}
                    </div>
                ";
                
                // Build appointment details for email to admin
                $dentistAppointmentDetails .= "
                    <div class='appointment-item'>
                        <strong>Patient:</strong> {$patientName}<br>
                        <strong>Date:</strong> {$appointmentDate}<br>
                        <strong>Time:</strong> {$appointmentTime}
                    </div>
                ";
                
                $appointmentCount++;
            }
            
            // Send email notification to dentist using email from multidisciplinary_dental_team table
            if ($appointmentCount > 0) {
                if (empty($dentistEmail)) {
                    error_log("Warning: No email found for dentist {$dentistName} (Team ID: {$team_id}) in multidisciplinary_dental_team table");
                } elseif (!filter_var($dentistEmail, FILTER_VALIDATE_EMAIL)) {
                    error_log("Warning: Invalid email format for dentist {$dentistName} (Team ID: {$team_id}): {$dentistEmail}");
                } else {
                    // Send email to the dentist's email address from multidisciplinary_dental_team table
                    if (sendEmailToDentist($dentistEmail, $dentistName, $appointmentItems)) {
                        $emailsSent++;
                        error_log("Email notification sent successfully to dentist {$dentistName} at {$dentistEmail} (from multidisciplinary_dental_team table)");
                    } else {
                        error_log("Failed to send email to dentist {$dentistName} at {$dentistEmail}");
                    }
                }
            }
            
            // Build admin email details
            if ($appointmentCount > 0) {
                $adminDentistDetails .= "
                    <div class='dentist-item'>
                        <h4>Dr. {$dentistName}</h4>
                        <p><strong>Status:</strong> Inactive</p>
                        <p><strong>Confirmed Appointments:</strong> {$appointmentCount}</p>
                        {$dentistAppointmentDetails}
                    </div>
                ";
                $totalInactiveDentists++;
            }
        }
        
        $stmt->close();
    }
    
    // Send email to admin if there are inactive dentists with appointments
    $adminEmailSent = false;
    if ($totalInactiveDentists > 0 && !empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        if (sendEmailToAdmin($adminEmail, $adminName, $adminDentistDetails)) {
            $adminEmailSent = true;
            $emailsSent++;
        }
    }
    
    return [
        'success' => true,
        'emails_sent' => $emailsSent,
        'admin_email_sent' => $adminEmailSent,
        'total_inactive_dentists' => $totalInactiveDentists
    ];
}

// Execute the check if run directly (not included)
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'checkDentistInactivity.php') {
    $result = checkDentistInactivity($con);
    
    if ($result && is_array($result)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Inactivity check completed successfully. " .
                        ($result['admin_email_sent'] ? "Email notification sent to admin." : ""),
            'emails_sent' => $result['emails_sent'],
            'admin_email_sent' => $result['admin_email_sent'] ?? false,
            'total_inactive_dentists' => $result['total_inactive_dentists'] ?? 0
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Error checking dentist inactivity."
        ]);
    }
}

?>
