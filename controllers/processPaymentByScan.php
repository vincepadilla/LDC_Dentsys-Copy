<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action.']);
    exit();
}

$user_role = strtolower($_SESSION['role'] ?? '');
$allowed_roles = ['admin', 'cashier', 'dentist', 'receptionist'];
if (!in_array($user_role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$appointment_id = trim($_POST['appointment_id'] ?? '');

if (empty($appointment_id)) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required.']);
    exit();
}

// Verify appointment exists
// NOTE: Date restriction removed for testing purposes - allows processing payments for any date
$verify_query = $con->prepare("
    SELECT appointment_id, status, appointment_date 
    FROM appointments 
    WHERE appointment_id = ?
    LIMIT 1
");
$verify_query->bind_param("s", $appointment_id);
$verify_query->execute();
$verify_result = $verify_query->get_result();
$appointment = $verify_result->fetch_assoc();
$verify_query->close();

if (!$appointment) {
    echo json_encode([
        'success' => false, 
        'message' => 'Appointment not found.'
    ]);
    exit();
}

// Check if already paid
if ($appointment['status'] === 'Paid' || $appointment['status'] === 'Complete' || $appointment['status'] === 'Completed') {
    echo json_encode([
        'success' => false, 
        'message' => 'Payment has already been processed for this appointment.'
    ]);
    exit();
}

// Start transaction
$con->begin_transaction();

try {
    // Get appointment details including patient_id and service info for payment record
    $appt_query = $con->prepare("
        SELECT a.appointment_id, a.patient_id, a.appointment_date, s.service_category, s.price
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        WHERE a.appointment_id = ?
        LIMIT 1
    ");
    $appt_query->bind_param("s", $appointment_id);
    $appt_query->execute();
    $appt_result = $appt_query->get_result();
    $appt_data = $appt_result->fetch_assoc();
    $appt_query->close();
    
    if (!$appt_data) {
        throw new Exception("Appointment details not found.");
    }
    
    // Update appointment status to 'Paid'
    $update_appointment = $con->prepare("UPDATE appointments SET status = 'Paid' WHERE appointment_id = ?");
    $update_appointment->bind_param("s", $appointment_id);
    $update_appointment->execute();
    $update_appointment->close();
    
    // Check if payment record exists
    $check_payment = $con->prepare("SELECT payment_id, status FROM payment WHERE appointment_id = ? LIMIT 1");
    $check_payment->bind_param("s", $appointment_id);
    $check_payment->execute();
    $payment_result = $check_payment->get_result();
    $payment_data = $payment_result->fetch_assoc();
    $check_payment->close();
    
    if ($payment_data) {
        // Update existing payment record status to 'paid'
        $update_payment = $con->prepare("UPDATE payment SET status = 'paid' WHERE appointment_id = ?");
        $update_payment->bind_param("s", $appointment_id);
        $update_payment->execute();
        $update_payment->close();
    } else {
        // Create payment record if it doesn't exist (for cash payments scanned on appointment day)
        $amount = floatval($appt_data['price'] ?? 0);
        $payment_id = 'PAY' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $create_payment = $con->prepare("
            INSERT INTO payment (payment_id, appointment_id, method, amount, status, created_at)
            VALUES (?, ?, 'Cash', ?, 'paid', NOW())
        ");
        $create_payment->bind_param("ssd", $payment_id, $appointment_id, $amount);
        $create_payment->execute();
        $create_payment->close();
    }
    
    // Check if ticket_code column exists and update ticket_status
    $colCheck = mysqli_query($con, "SHOW COLUMNS FROM appointments LIKE 'ticket_status'");
    $hasTicketStatus = ($colCheck && mysqli_num_rows($colCheck) > 0);
    
    if ($hasTicketStatus) {
        $update_ticket = $con->prepare("UPDATE appointments SET ticket_status = 'used', arrival_verified = 1 WHERE appointment_id = ?");
        $update_ticket->bind_param("s", $appointment_id);
        $update_ticket->execute();
        $update_ticket->close();
    }
    
    // Commit transaction
    $con->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully! Appointment status updated to Paid. Payment transaction record updated.'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $con->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process payment: ' . $e->getMessage()
    ]);
}

$con->close();
?>
