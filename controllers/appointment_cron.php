<?php
// Intended to be run by scheduler (cron / Windows Task Scheduler) every X minutes
include_once('../database/config.php');

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// 1) Mark no-shows: appointments for today where ticket_expires_at < now and still Pending -> No-show
$query = "SELECT appointment_id FROM appointments WHERE status = 'Pending' AND ticket_status = 'issued' AND ticket_expires_at IS NOT NULL AND ticket_expires_at < ?";
$stmt = $con->prepare($query);
$stmt->bind_param('s', $now);
$stmt->execute();
$res = $stmt->get_result();
$ids = [];
while ($r = $res->fetch_assoc()) {
    $ids[] = $r['appointment_id'];
}
$stmt->close();

if (!empty($ids)) {
    $in = implode("','", $ids);
    $sql = "UPDATE appointments SET status = 'No-show', ticket_status = 'expired' WHERE appointment_id IN ('" . $in . "')";
    mysqli_query($con, $sql);
}

// 2) Optionally auto-cancel old unconfirmed reservations (e.g., created long before appointment)
// Mark any Pending appointment where appointment_date < today and still Pending -> No-show
$sql2 = "UPDATE appointments SET status = 'No-show', ticket_status = 'expired' WHERE status = 'Pending' AND appointment_date < ?";
$stmt2 = $con->prepare($sql2);
$stmt2->bind_param('s', $today);
$stmt2->execute();
$stmt2->close();

// Close DB
$con->close();
?>
