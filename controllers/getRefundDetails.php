<?php
session_start();
include_once('../database/config.php');

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$refund_id = $_GET['id'] ?? null;

if (!$refund_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing refund ID']);
    exit();
}

$query = $con->prepare("
    SELECT rr.id, rr.payment_id, rr.appointment_id, rr.user_id, rr.status, rr.created_at,
           p.amount, p.method, p.account_name, p.account_number, p.reference_no, p.proof_image, p.status AS payment_status,
           pi.first_name, pi.last_name, pi.email,
           a.appointment_date, a.appointment_time,
           s.service_category, s.sub_service
    FROM refund_requests rr
    LEFT JOIN payment p ON rr.payment_id = p.payment_id
    LEFT JOIN patient_information pi ON rr.user_id = pi.user_id
    LEFT JOIN appointments a ON rr.appointment_id = a.appointment_id
    LEFT JOIN services s ON a.service_id = s.service_id
    WHERE rr.id = ?
");

$query->bind_param("s", $refund_id);
$query->execute();
$result = $query->get_result();
$refund = $result->fetch_assoc();
$query->close();

if (!$refund) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Refund request not found']);
    exit();
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'refund' => [
        'id' => $refund['id'],
        'payment_id' => $refund['payment_id'],
        'appointment_id' => $refund['appointment_id'],
        'patient_name' => trim(($refund['first_name'] ?? '') . ' ' . ($refund['last_name'] ?? '')),
        'patient_email' => $refund['email'] ?? '',
        'amount' => $refund['amount'],
        'status' => $refund['status'],
        'payment_status' => $refund['payment_status'] ?? '',
        'method' => $refund['method'] ?? '',
        'account_name' => $refund['account_name'] ?? '',
        'account_number' => $refund['account_number'] ?? '',
        'reference_no' => $refund['reference_no'] ?? '',
        'has_proof' => !empty($refund['proof_image']),
        'service' => !empty($refund['sub_service']) ? $refund['sub_service'] : ($refund['service_category'] ?? 'N/A'),
        'appointment_schedule' => (!empty($refund['appointment_date']) ? date('M j, Y', strtotime($refund['appointment_date'])) : 'N/A') . (!empty($refund['appointment_time']) ? (' ' . $refund['appointment_time']) : ''),
        'created_at' => date('M j, Y g:i A', strtotime($refund['created_at']))
    ]
]);
?>
