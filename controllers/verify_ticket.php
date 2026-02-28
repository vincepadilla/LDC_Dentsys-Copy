<?php
session_start();
include_once('../database/config.php');

// Receptionist verifies ticket code and marks appointment as paid/confirmed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket = isset($_POST['ticket_code']) ? mysqli_real_escape_string($con, trim($_POST['ticket_code'])) : '';
    $action = $_POST['action'] ?? 'verify'; // verify or mark_paid

    if (empty($ticket)) {
        echo json_encode(['success' => false, 'message' => 'Ticket code required']);
        exit();
    }

    // Find appointment for today with that ticket
    $today = date('Y-m-d');
    $stmt = $con->prepare("SELECT appointment_id, status, ticket_status, ticket_expires_at FROM appointments WHERE ticket_code = ? LIMIT 1");
    $stmt->bind_param('s', $ticket);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Invalid ticket code']);
        exit();
    }

    // If verifying
    if ($action === 'verify') {
        // Check expiry
        $now = date('Y-m-d H:i:s');
        if (!empty($row['ticket_expires_at']) && $row['ticket_expires_at'] < $now) {
            // mark expired
            $u = $con->prepare("UPDATE appointments SET ticket_status = 'expired', status = 'No-show' WHERE appointment_id = ?");
            $u->bind_param('s', $row['appointment_id']);
            $u->execute();
            $u->close();

            echo json_encode(['success' => false, 'message' => 'Ticket expired — appointment marked as No-show']);
            exit();
        }

        // Ticket valid
        echo json_encode(['success' => true, 'message' => 'Ticket valid. Proceed to mark paid.', 'appointment_id' => $row['appointment_id']]);
        exit();
    }

    if ($action === 'mark_paid') {
        // Update payment and appointment
        $appointment_id = $row['appointment_id'];

        // Mark payment as paid if exists
        $pay = $con->prepare("UPDATE payment SET status = 'paid' WHERE appointment_id = ?");
        $pay->bind_param('s', $appointment_id);
        $pay->execute();
        $pay->close();

        // Update appointment
        $u = $con->prepare("UPDATE appointments SET status = 'Confirmed', ticket_status = 'used', arrival_verified = 1 WHERE appointment_id = ?");
        $u->bind_param('s', $appointment_id);
        $u->execute();
        $u->close();

        echo json_encode(['success' => true, 'message' => 'Appointment marked as Paid and Confirmed', 'appointment_id' => $appointment_id]);
        exit();
    }
}

header('HTTP/1.1 400 Bad Request');
echo json_encode(['success' => false, 'message' => 'Bad request']);
?>
