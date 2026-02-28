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
 * Check if alert already exists for this appointment and admin
 */
function alertExistsForAdmin($con, $admin_user_id, $related_appointment_id) {
    $query = "SELECT alert_id FROM system_alerts 
              WHERE user_id = ? 
              AND related_appointment_id = ? 
              AND role = 'admin'
              AND is_read = 0
              LIMIT 1";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ss", $admin_user_id, $related_appointment_id);
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
 * Send email notification to admin
 */
function sendEmailToAdmin($adminEmail, $adminName, $appointmentDetails, $totalCount) {
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
        $mail->addAddress($adminEmail, $adminName);
        $mail->isHTML(true);
        $mail->Subject = 'System Alert: Pending Appointments Require Attention';
        
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
                    .count-badge { background: #F59E0B; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>⚠️ Pending Appointments Alert</h2>
                        <p>You have <span class='count-badge'>{$totalCount}</span> pending appointment(s) that require your attention.</p>
                    </div>
                    <div class='content'>
                        <p>Dear {$adminName},</p>
                        <p>This is an automated alert to inform you that there are pending appointments in the system that require your review and action.</p>
                        <div class='appointment-list'>
                            <h3>Pending Appointments:</h3>
                            {$appointmentDetails}
                        </div>
                        <p><strong>Action Required:</strong> Please log in to the admin dashboard to review and confirm or cancel these appointments.</p>
                        <p>Best regards,<br>Landero Dental Clinic System</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Dear {$adminName},\n\nThis is an automated alert to inform you that there are {$totalCount} pending appointment(s) in the system that require your review and action.\n\nPlease log in to the admin dashboard to review and confirm or cancel these appointments.\n\nBest regards,\nLandero Dental Clinic System";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Main function to check pending appointments and create alerts for admin
 */
function checkPendingAppointments($con) {
    // Get admin user info
    $adminInfo = getAdminUserInfo($con);
    
    if (!$adminInfo || !isset($adminInfo['user_id'])) {
        return [
            'success' => false,
            'message' => 'Admin user not found'
        ];
    }
    
    $admin_user_id = $adminInfo['user_id'];
    $adminEmail = $adminInfo['email'] ?? '';
    $adminName = trim(($adminInfo['first_name'] ?? '') . ' ' . ($adminInfo['last_name'] ?? '')) ?: 'Admin';
    
    // Find pending appointments
    $pendingAppointmentsQuery = "
        SELECT a.appointment_id, a.patient_id, a.appointment_date, a.appointment_time, a.status,
               p.first_name, p.last_name, s.sub_service, s.service_category,
               d.first_name as dentist_first, d.last_name as dentist_last
        FROM appointments a
        LEFT JOIN patient_information p ON a.patient_id = p.patient_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
        WHERE a.status = 'Pending'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ";
    
    $result = mysqli_query($con, $pendingAppointmentsQuery);
    
    if (!$result) {
        error_log("Error querying pending appointments: " . mysqli_error($con));
        return [
            'success' => false,
            'message' => 'Error querying pending appointments'
        ];
    }
    
    $alertsCreated = 0;
    $appointmentItems = "";
    $appointmentCount = 0;
    $newAppointments = [];
    
    // Process each pending appointment
    while ($appointment = mysqli_fetch_assoc($result)) {
        $appointment_id = $appointment['appointment_id'];
        $patientName = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
        $appointmentDate = $appointment['appointment_date'];
        $appointmentTime = $appointment['appointment_time'];
        $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
        $dentistName = trim($appointment['dentist_first'] . ' ' . $appointment['dentist_last']);
        
        // Check if alert already exists for this appointment
        if (!alertExistsForAdmin($con, $admin_user_id, $appointment_id)) {
            // Generate alert ID
            $alert_id = generateAlertID($con);
            
            // Create alert message for admin
            $title = "Pending Appointment - Action Required";
            $message = "There is a pending appointment that requires your attention:\n\n";
            $message .= "Patient: {$patientName}\n";
            $message .= "Service: {$service}\n";
            $message .= "Dentist: " . (!empty($dentistName) ? "Dr. {$dentistName}" : "Not assigned") . "\n";
            $message .= "Date: {$appointmentDate}\n";
            $message .= "Time: {$appointmentTime}\n\n";
            $message .= "Please review and confirm or cancel this appointment.";
            
            // Insert alert for admin
            if (insertAlert($con, $alert_id, $admin_user_id, 'admin', $title, $message, $appointment_id)) {
                $alertsCreated++;
                $newAppointments[] = [
                    'patient' => $patientName,
                    'service' => $service,
                    'dentist' => $dentistName,
                    'date' => $appointmentDate,
                    'time' => $appointmentTime
                ];
            }
        }
        
        // Build appointment details for email (all pending appointments)
        $appointmentItems .= "
            <div class='appointment-item'>
                <strong>Patient:</strong> {$patientName}<br>
                <strong>Service:</strong> {$service}<br>
                <strong>Dentist:</strong> " . (!empty($dentistName) ? "Dr. {$dentistName}" : "Not assigned") . "<br>
                <strong>Date:</strong> {$appointmentDate}<br>
                <strong>Time:</strong> {$appointmentTime}
            </div>
        ";
        $appointmentCount++;
    }
    
    // Send email to admin if there are pending appointments
    $emailSent = false;
    if ($appointmentCount > 0 && !empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        if (sendEmailToAdmin($adminEmail, $adminName, $appointmentItems, $appointmentCount)) {
            $emailSent = true;
        }
    }
    
    return [
        'success' => true,
        'alerts_created' => $alertsCreated,
        'email_sent' => $emailSent,
        'total_pending' => $appointmentCount,
        'message' => "Pending appointments check completed. {$alertsCreated} new alert(s) created. " . ($emailSent ? "Email notification sent." : "")
    ];
}

// Execute the check if run directly (not included)
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'checkPendingAppointments.php') {
    $result = checkPendingAppointments($con);
    
    header('Content-Type: application/json');
    echo json_encode($result);
}

?>
