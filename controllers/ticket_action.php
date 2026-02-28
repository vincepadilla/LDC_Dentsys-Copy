<?php
// Allow patients to confirm or cancel appointments via email link
include_once('../database/config.php');

$action = $_GET['action'] ?? '';
$appointment_id = $_GET['appointment_id'] ?? '';
$ticket = $_GET['ticket'] ?? '';

if (empty($action) || empty($appointment_id)) {
    echo "Invalid request.";
    exit();
}

// Check if ticket_code column exists
$colCheck = mysqli_query($con, "SHOW COLUMNS FROM appointments LIKE 'ticket_code'");
$hasTicketCols = ($colCheck && mysqli_num_rows($colCheck) > 0);

// Fetch appointment
$stmt = $con->prepare("SELECT appointment_id, status" . ($hasTicketCols ? ", ticket_code" : "") . " FROM appointments WHERE appointment_id = ? LIMIT 1");
$stmt->bind_param('s', $appointment_id);
$stmt->execute();
$res = $stmt->get_result();
$appt = $res->fetch_assoc();
$stmt->close();

if (!$appt) {
    echo "Appointment not found.";
    exit();
}

// If ticket column exists, validate provided ticket
if ($hasTicketCols && !empty($appt['ticket_code'])) {
    if (empty($ticket) || $ticket !== $appt['ticket_code']) {
        echo "Invalid or missing ticket code.";
        exit();
    }
}

if ($action === 'confirm') {
    // Mark as Confirmed (patient intends to come). Do not mark paid here.
    $u = $con->prepare("UPDATE appointments SET status = 'Confirmed' WHERE appointment_id = ?");
    $u->bind_param('s', $appointment_id);
    $u->execute();
    $u->close();

    echo "<h2>Appointment Confirmed</h2><p>Your appointment (ID: " . htmlspecialchars($appointment_id) . ") has been confirmed. Please present your ticket at reception on the appointment day.</p>";
    exit();
} elseif ($action === 'cancel') {
    $u = $con->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
    $u->bind_param('s', $appointment_id);
    $u->execute();
    $u->close();

    echo "<h2>Appointment Cancelled</h2><p>Your appointment (ID: " . htmlspecialchars($appointment_id) . ") has been cancelled. If this was a mistake, please contact the clinic.</p>";
    exit();
} else {
    echo "Unknown action.";
    exit();
}

?>