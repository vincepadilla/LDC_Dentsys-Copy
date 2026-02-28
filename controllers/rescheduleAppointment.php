<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

include_once("../database/config.php");
session_start();

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {

    // Sanitize and validate appointment_id
    $appointment_id = trim($_POST['appointment_id']);
    
    // Validate appointment_id is not empty
    if (empty($appointment_id)) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID is required.']);
        exit();
    }
    
    $new_date = $_POST['new_date_resched'] ?? null;
    $new_time_slot = $_POST['new_time_slot'] ?? null;
    $reschedule_reason = trim($_POST['reschedule_reason'] ?? '');
    
    // Time Slot Mapping (unchanged)
    $timeMap = [
        'firstBatch'   => '8:00AM-9:00AM',
        'secondBatch'  => '9:00AM-10:00AM',
        'thirdBatch'   => '10:00AM-11:00AM',
        'fourthBatch'  => '11:00AM-12:00PM',
        'fifthBatch'   => '1:00PM-2:00PM',
        'sixthBatch'   => '2:00PM-3:00PM',
        'sevenBatch'   => '3:00PM-4:00PM',
        'eightBatch'   => '4:00PM-5:00PM',
        'nineBatch'    => '5:00PM-6:00PM',
        'tenBatch'     => '6:00PM-7:00PM',
        'lastBatch'    => '7:00PM-8:00PM'
    ];

    // Validate inputs
    if (!$new_date || !$new_time_slot || !isset($timeMap[$new_time_slot])) {
        echo json_encode(['success' => false, 'message' => 'Please complete all required fields.']);
        exit();
    }
    
    // Validate reason
    if (empty($reschedule_reason)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a reason for rescheduling.']);
        exit();
    }

    // Prevent past dates
    $today = date("Y-m-d");
    if ($new_date < $today) {
        echo json_encode(['success' => false, 'message' => 'New date cannot be in the past.']);
        exit();
    }

    $new_time = $timeMap[$new_time_slot];

    // First, verify the appointment exists before updating
    $stmtCheck = $con->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ?");
    $stmtCheck->bind_param("s", $appointment_id);
    $stmtCheck->execute();
    $checkResult = $stmtCheck->get_result();
    
    if ($checkResult->num_rows === 0) {
        $stmtCheck->close();
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit();
    }
    $stmtCheck->close();

    // UPDATE appointment record - using "s" for appointment_id (VARCHAR, not integer)
    $stmtUpdate = $con->prepare("
        UPDATE appointments
        SET appointment_date = ?, 
            appointment_time = ?, 
            time_slot = ?,
            status = 'Pending'
        WHERE appointment_id = ?
    ");
    // Changed from "sssi" to "ssss" - appointment_id is VARCHAR, not integer
    $stmtUpdate->bind_param("ssss", $new_date, $new_time, $new_time_slot, $appointment_id);

    if (!$stmtUpdate->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment: ' . $stmtUpdate->error]);
        $stmtUpdate->close();
        exit();
    }
    
    // Verify only one row was affected
    $affectedRows = $stmtUpdate->affected_rows;
    $stmtUpdate->close();
    
    if ($affectedRows === 0) {
        echo json_encode(['success' => false, 'message' => 'No appointment was updated. Please check the appointment ID.']);
        exit();
    }
    
    if ($affectedRows > 1) {
        echo json_encode(['success' => false, 'message' => 'Multiple appointments were updated. This should not happen. Please contact administrator.']);
        exit();
    }

    // FETCH updated appointment (MATCHING confirmAppointment.php)
    $stmt = $con->prepare("SELECT a.*, 
                                   p.first_name, p.last_name, p.email,
                                   s.service_category, s.sub_service,
                                   d.first_name AS dentist_first, d.last_name AS dentist_last
                           FROM appointments a
                           LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                           LEFT JOIN services s ON a.service_id = s.service_id
                           LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                           WHERE a.appointment_id = ?");
    $stmt->bind_param("s", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found after update.']);
        exit();
    }

    // Prepare email variables (same format as confirmAppointment.php)
    $patient_name = trim($appointment['first_name'] . ' ' . $appointment['last_name']);
    $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : $appointment['service_category'];
    $dentist = trim($appointment['dentist_first'] . ' ' . $appointment['dentist_last']);
    $email = $appointment['email'];

    // SEND EMAIL
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
        $mail->addAddress($email, $patient_name);

        $mail->isHTML(true);
        $mail->Subject = 'Appointment Rescheduled';

        $reasonText = !empty($reschedule_reason) ? "<p><strong>Reason for Rescheduling:</strong> " . htmlspecialchars($reschedule_reason) . "</p>" : "";
        
        $mail->Body = "
            <h3>Hi {$patient_name},</h3>
            <p>Your appointment has been <strong>rescheduled</strong>.</p>

            <p>
            <strong>Service:</strong> {$service}<br>
            <strong>Dentist:</strong> {$dentist}<br>
            <strong>New Date:</strong> " . date('F j, Y', strtotime($new_date)) . "<br>
            <strong>New Time:</strong> {$new_time}<br>
            <strong>Branch:</strong> {$appointment['branch']}
            </p>
            
            {$reasonText}

            <p>Please check your account for more details.</p>
            <p>Thank you for choosing our clinic!</p>
        ";

        $mail->send();

        echo json_encode([
            'success' => true, 
            'message' => 'Appointment rescheduled successfully and email sent.',
            'status' => 'success'
        ]);
        exit();

    } catch (Exception $e) {

        echo json_encode([
            'success' => true, 
            'message' => 'Appointment rescheduled, but email failed to send.',
            'status' => 'success'
        ]);
        exit();
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
?>

