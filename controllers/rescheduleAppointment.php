<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../libraries/PhpMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/SMTP.php';

require_once __DIR__ . '/../database/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


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
    // Source of reschedule: 'admin' from admin panel, otherwise default to 'user'
    $reschedule_source = $_POST['reschedule_source'] ?? 'user';
    
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
    
    // Validate reason ONLY when rescheduled by admin (patient self-reschedule keeps old behavior)
    if ($reschedule_source === 'admin' && empty($reschedule_reason)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a reason for rescheduling.']);
        exit();
    }

    // Prevent past dates
    $today = date("Y-m-d");
    if ($new_date < $today) {
        echo json_encode(['success' => false, 'message' => 'New date cannot be in the past.']);
        exit();
    }

    $slotOrder = [
        'firstBatch',
        'secondBatch',
        'thirdBatch',
        'fourthBatch',
        'fifthBatch',
        'sixthBatch',
        'sevenBatch',
        'eightBatch',
        'nineBatch',
        'tenBatch',
        'lastBatch'
    ];

    $new_time = $timeMap[$new_time_slot];

    // First, verify the appointment exists and read request_note for 2-slot enforcement.
    $stmtCheck = $con->prepare("SELECT appointment_id, request_note FROM appointments WHERE appointment_id = ?");
    $stmtCheck->bind_param("s", $appointment_id);
    $stmtCheck->execute();
    $checkResult = $stmtCheck->get_result();
    
    if ($checkResult->num_rows === 0) {
        $stmtCheck->close();
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit();
    }
    $currentAppointment = $checkResult->fetch_assoc();
    $hasRequestNote = !empty($currentAppointment['request_note']);
    $stmtCheck->close();

    if ($hasRequestNote) {
        $slotIndex = array_search($new_time_slot, $slotOrder, true);
        if ($slotIndex === false || !isset($slotOrder[$slotIndex + 1])) {
            echo json_encode([
                'success' => false,
                'message' => 'This appointment requires 2 consecutive time slots. Please select an earlier start time.'
            ]);
            exit();
        }

        $nextSlot = $slotOrder[$slotIndex + 1];

        // Validate second slot is available (not blocked and not already booked).
        $stmtBlocked = $con->prepare("SELECT 1 FROM blocked_time_slots WHERE date = ? AND time_slot = ? LIMIT 1");
        $stmtBlocked->bind_param("ss", $new_date, $nextSlot);
        $stmtBlocked->execute();
        $blockedResult = $stmtBlocked->get_result();
        $isBlocked = $blockedResult && $blockedResult->num_rows > 0;
        $stmtBlocked->close();

        $stmtBooked = $con->prepare("
            SELECT appointment_id, time_slot, request_note
            FROM appointments
            WHERE appointment_date = ?
              AND status != 'Cancelled'
              AND appointment_id != ?
        ");
        $stmtBooked->bind_param("ss", $new_date, $appointment_id);
        $stmtBooked->execute();
        $bookedResult = $stmtBooked->get_result();

        $unavailableSlots = [];
        while ($bookedRow = $bookedResult->fetch_assoc()) {
            if (empty($bookedRow['time_slot'])) {
                continue;
            }
            $unavailableSlots[] = $bookedRow['time_slot'];
            if (!empty($bookedRow['request_note'])) {
                $bookedIndex = array_search($bookedRow['time_slot'], $slotOrder, true);
                if ($bookedIndex !== false && isset($slotOrder[$bookedIndex + 1])) {
                    $unavailableSlots[] = $slotOrder[$bookedIndex + 1];
                }
            }
        }
        $stmtBooked->close();

        if ($isBlocked || in_array($nextSlot, $unavailableSlots, true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Unable to reschedule: the next consecutive slot is already booked or unavailable.'
            ]);
            exit();
        }

        $firstStart = explode('-', $timeMap[$new_time_slot])[0] ?? '';
        $secondEnd = explode('-', $timeMap[$nextSlot])[1] ?? '';
        if ($firstStart && $secondEnd) {
            $new_time = $firstStart . '-' . $secondEnd;
        }
    }

    // UPDATE appointment record - using "s" for appointment_id (VARCHAR, not integer)
    // Also store the latest reschedule reason so it can be shown in the patient's account.
    $stmtUpdate = $con->prepare("
        UPDATE appointments
        SET appointment_date = ?, 
            appointment_time = ?, 
            time_slot = ?,
            status = 'Pending',
            reschedule_reason = ?
        WHERE appointment_id = ?
    ");
    // Changed from "sssi" to "sssss" - appointment_id and reschedule_reason are VARCHAR
    $stmtUpdate->bind_param("sssss", $new_date, $new_time, $new_time_slot, $reschedule_reason, $appointment_id);

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

        // Only include a detailed reason section when the reschedule was done by the admin
        $reasonText = ($reschedule_source === 'admin' && !empty($reschedule_reason))
            ? "<p><strong>Reason for Rescheduling:</strong> " . htmlspecialchars($reschedule_reason) . "</p>"
            : "";
        
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
            'appointment_time' => $new_time,
            'status' => 'success'
        ]);
        exit();

    } catch (Exception $e) {

        echo json_encode([
            'success' => true, 
            'message' => 'Appointment rescheduled, but email failed to send.',
            'appointment_time' => $new_time,
            'status' => 'success'
        ]);
        exit();
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
?>

