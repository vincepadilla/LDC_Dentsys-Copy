<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once('../layouts/header.php');

require_once __DIR__ . '/../libraries/PhpMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/SMTP.php';

require_once __DIR__ . '/../database/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ Check if user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: /views/login.php");
    exit();
}

$user_id = $_SESSION['userID'];

$rescheduleTimeMap = [
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
$rescheduleToday = date('Y-m-d');
$rescheduleMaxDate = date('Y-m-d', strtotime('+1 month'));

// ✅ Fetch user and patient information
$user_query = $con->prepare("
    SELECT ua.user_id, ua.username, ua.first_name, ua.last_name, ua.email, ua.phone,
           p.patient_id, p.birthdate, p.gender, p.address
    FROM user_account ua
    LEFT JOIN patient_information p ON ua.user_id = p.user_id
    WHERE ua.user_id = ?
");
$user_query->bind_param("s", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

// ✅ Check if user exists, if not show friendly message and redirect
if (empty($user)) {
    // User ID doesn't exist in database - destroy session and redirect with message
    session_destroy();
    header("Location: login.php?error=account_not_found");
    exit();
}

// ✅ Fetch most recent appointment (if patient exists)
$recent_appointments = [];
$hasFeedback = false;
if (!empty($user['patient_id'])) {
    // Query for the most recent appointment with payment information
    $appt_query = $con->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, 
               COALESCE(s.sub_service, s.service_category) as service_display, 
               s.sub_service, s.service_category, a.status, a.created_at,
               a.request_note,
               a.reschedule_reason,
               p.payment_id, p.method as payment_method, p.status as payment_status
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.service_id
        LEFT JOIN payment p ON a.appointment_id = p.appointment_id
        WHERE a.patient_id = ?
        ORDER BY 
            CASE 
                WHEN a.status IN ('Cancelled', 'Complete', 'Completed', 'No-show') THEN 1
                ELSE 0
            END ASC,
            a.created_at DESC,
            a.appointment_date DESC, 
            a.appointment_time DESC
        LIMIT 1
    ");
    $appt_query->bind_param("s", $user['patient_id']);
    $appt_query->execute();
    $appt_result = $appt_query->get_result();
    while ($row = $appt_result->fetch_assoc()) {
        $recent_appointments[] = $row;
    }
    
    // Check if user already has feedback
    $feedback_check = $con->prepare("SELECT feedback_id FROM feedback WHERE user_id = ?");
    $feedback_check->bind_param("s", $user_id);
    $feedback_check->execute();
    $feedback_result = $feedback_check->get_result();
    $hasFeedback = $feedback_result->num_rows > 0;
    $feedback_check->close();
}

// ✅ Fetch most recent walk-in appointment from database
$walkInSessionAppt = $_SESSION['walkin_appointment'] ?? null;
$walkin_id_to_fetch = null;

// If we have a walk-in ID in session (just created), fetch that specific one
if (!empty($walkInSessionAppt['walkin_id'])) {
    $walkin_id_to_fetch = $walkInSessionAppt['walkin_id']; // VARCHAR, not int
}

// Fetch walk-in appointment from database
$walkin_appointment = null;
if ($walkin_id_to_fetch) {
    // Fetch specific walk-in by ID (VARCHAR)
    $walkin_query = $con->prepare("
        SELECT walkin_id, patient_id, service, sub_service, 
               dentist_name, branch, status, created_at
        FROM walkin_appointments
        WHERE walkin_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $walkin_query->bind_param("s", $walkin_id_to_fetch);
    $walkin_query->execute();
    $walkin_result = $walkin_query->get_result();
    $walkin_appointment = $walkin_result->fetch_assoc();
    $walkin_query->close();
} else {
    // Fetch most recent walk-in by patient_id
    if (!empty($user['patient_id'])) {
        $walkin_query = $con->prepare("
            SELECT walkin_id, patient_id, service, sub_service, 
                   dentist_name, branch, status, created_at
            FROM walkin_appointments
            WHERE patient_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $walkin_query->bind_param("s", $user['patient_id']);
        $walkin_query->execute();
        $walkin_result = $walkin_query->get_result();
        $walkin_appointment = $walkin_result->fetch_assoc();
        $walkin_query->close();
    }
}

// If we have a walk-in appointment from database, show it as the "recent appointment"
if (!empty($walkin_appointment)) {
    // Always display sub_service as the service (as requested)
    // sub_service should always be present from the database
    $service_display = !empty($walkin_appointment['sub_service']) 
        ? $walkin_appointment['sub_service'] 
        : ($walkin_appointment['service'] ?? 'N/A');
    
    $recent_appointments = [[
        'appointment_id' => 'WALK-IN-' . $walkin_appointment['walkin_id'],
        'appointment_date' => 'Walk-In',
        'appointment_time' => 'To be arranged at clinic',
        'service_display' => $service_display,
        'sub_service' => $walkin_appointment['sub_service'] ?? null,
        'service_category' => $walkin_appointment['service'] ?? null,
        // Use the actual walk-in status from the database so that
        // any updates (e.g., Completed) are reflected in account.php
        'status' => $walkin_appointment['status'] ?? 'Walk-in',
        'created_at' => $walkin_appointment['created_at'] ?? null,
        'payment_method' => 'Cash',
        'payment_status' => 'paid',
        'reschedule_reason' => null,
        '_is_walkin' => true,
        'walkin_id' => $walkin_appointment['walkin_id'],
    ]];
}

// ✅ Debug output to browser console
echo "<script>
console.log('DEBUG: User ID => " . addslashes($user_id) . "');
console.log('DEBUG: Patient ID => " . addslashes($user['patient_id'] ?? 'NULL') . "');
console.log('DEBUG: User data exists => " . (!empty($user) ? 'YES' : 'NO') . "');
console.log('DEBUG: Found appointments => " . count($recent_appointments) . "');
</script>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/accountstyle.css">
    <style>
        /* Notification System Styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 400px;
        }

        .notification {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 320px;
            animation: slideInRight 0.4s ease-out;
            position: relative;
            overflow: hidden;
        }

        .notification.success {
            border-left: 4px solid #10B981;
        }

        .notification.warning {
            border-left: 4px solid #F59E0B;
        }

        .notification.error {
            border-left: 4px solid #EF4444;
        }

        .notification.info {
            border-left: 4px solid #3B82F6;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .notification.hide {
            animation: slideOutRight 0.3s ease-out forwards;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .notification.success .notification-icon {
            background: #D1FAE5;
            color: #10B981;
        }

        .notification.warning .notification-icon {
            background: #FEF3C7;
            color: #F59E0B;
        }

        .notification.error .notification-icon {
            background: #FEE2E2;
            color: #EF4444;
        }

        .notification.info .notification-icon {
            background: #DBEAFE;
            color: #3B82F6;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 16px;
            margin: 0 0 4px 0;
            color: #111827;
        }

        .notification-message {
            font-size: 14px;
            color: #6B7280;
            margin: 0;
        }

        .notification-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: transparent;
            border: none;
            font-size: 20px;
            color: #9CA3AF;
            cursor: pointer;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .notification-close:hover {
            background: #F3F4F6;
            color: #374151;
        }

        /* Check Animation */
        @keyframes checkmark {
            0% {
                stroke-dashoffset: 100;
            }
            100% {
                stroke-dashoffset: 0;
            }
        }

        .check-animation {
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: checkmark 0.6s ease-out forwards;
        }

        /* Success Scale Animation */
        @keyframes successScale {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        .success-scale-animation {
            animation: successScale 0.5s ease-out;
        }

        /* Confirmation Modal */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10001;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .confirmation-modal.show {
            display: flex;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .confirmation-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalPopIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPopIn {
            0% {
                transform: scale(0.8) translateY(-20px);
                opacity: 0;
            }
            100% {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .confirmation-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            animation: successScale 0.5s ease-out;
        }

        .confirmation-title {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin: 0 0 15px 0;
            color: #111827;
        }

        .confirmation-message {
            font-size: 16px;
            text-align: center;
            color: #6B7280;
            margin: 0 0 30px 0;
            line-height: 1.6;
        }

        .confirmation-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .confirmation-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .confirmation-btn-cancel {
            background: #F3F4F6;
            color: #374151;
        }

        .confirmation-btn-cancel:hover {
            background: #E5E7EB;
        }

        .confirmation-btn-confirm {
            background: #EF4444;
            color: white;
        }

        .confirmation-btn-confirm:hover {
            background: #DC2626;
        }

        /* Feedback Button Styles */
        .btn-feedback {
            background: #10B981;
            color: white;
        }

        .btn-feedback:hover:not(.disabled) {
            background: #059669;
        }

        .btn-feedback.disabled {
            background: #6B7280;
        }

        /* Password Requirements Styling */
        .password-requirements {
            margin-top: 15px;
            padding: 15px;
            background: #F9FAFB;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
        }

        .password-requirements p {
            margin: 0 0 10px 0;
            font-weight: 600;
            color: #374151;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            padding: 5px 0;
            color: #6B7280;
            transition: all 0.3s ease;
        }

        .password-requirements li.requirement-met {
            color: #10B981;
            font-weight: 500;
        }

        .password-requirements li.requirement-met::before {
            content: "✓ ";
            color: #10B981;
            font-weight: bold;
            margin-right: 5px;
        }

        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 4px;
            background: #E5E7EB;
            border-radius: 2px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .strength-bar.weak {
            background: #EF4444;
            width: 33%;
        }

        .strength-bar.medium {
            background: #F59E0B;
            width: 66%;
        }

        .strength-bar.strong {
            background: #10B981;
            width: 100%;
        }

        .strength-text {
            font-size: 12px;
            color: #6B7280;
        }

        .validation-message {
            display: block;
            margin-top: 5px;
            font-size: 12px;
        }

        .validation-message.valid {
            color: #10B981;
        }

        .validation-message.invalid {
            color: #EF4444;
        }

        /* Reschedule modal (matching Add Services modal look and spacing) */
        .reschedule-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.52);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10002;
            padding: 20px;
        }

        .reschedule-modal-overlay.show {
            display: flex;
        }

        .reschedule-modal-panel {
            position: relative;
            width: min(760px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.28);
            animation: modalPopIn 0.28s ease-out;
        }

        .reschedule-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 10px;
            background: #f3f4f6;
            color: #374151;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s ease;
        }

        .reschedule-modal-close:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .reschedule-modal-heading {
            margin-bottom: 18px;
        }

        .reschedule-modal-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .reschedule-modal-heading h3 {
            margin: 0 0 6px 0;
            font-size: 1.35rem;
            color: #111827;
            font-weight: 700;
        }

        .reschedule-modal-heading p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .reschedule-current-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #f8fafc;
            padding: 14px;
            margin-bottom: 16px;
        }

        .reschedule-current-card-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 12px 0;
        }

        .reschedule-current-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 16px;
        }

        .reschedule-current-item label {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 2px;
        }

        .reschedule-current-item span {
            color: #0f172a;
            font-size: 14px;
            font-weight: 600;
        }

        .reschedule-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .reschedule-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .reschedule-form-group label {
            color: #111827;
            font-size: 13px;
            font-weight: 600;
        }

        .reschedule-form-control {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #111827;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .reschedule-form-control:focus {
            outline: none;
            border-color: #48A6A7;
            box-shadow: 0 0 0 3px rgba(72, 166, 167, 0.18);
        }

        .time-slot-disabled {
            color: #9ca3af;
            cursor: not-allowed;
        }

        .reschedule-modal-actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .reschedule-submit-btn,
        .reschedule-cancel-btn {
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .reschedule-submit-btn {
            background: #48A6A7;
            color: #fff;
        }

        .reschedule-submit-btn:hover {
            background: #2a9d8f;
        }

        .reschedule-cancel-btn {
            background: #f3f4f6;
            color: #374151;
        }

        @media (max-width: 720px) {
            .reschedule-modal-panel {
                padding: 20px;
            }

            .reschedule-form-grid,
            .reschedule-current-grid {
                grid-template-columns: 1fr;
            }

            .reschedule-modal-actions button {
                width: 100%;
            }
        }

        /* Edit account modal refresh (simple, clean, aligned with reschedule modal) */
        #editModal .edit-modal-content {
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.28);
            border: 1px solid #e5e7eb;
        }

        #editModal .close {
            top: 14px;
            right: 14px;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #f3f4f6;
            color: #374151;
            font-size: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #editModal .close:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .edit-account-heading {
            margin-bottom: 18px;
        }

        .edit-account-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        #editModal .edit-account-heading h3 {
            margin: 0 0 6px 0;
            font-size: 1.35rem;
            color: #111827;
            font-weight: 700;
            text-align: left;
        }

        .edit-account-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .edit-account-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        #editModal .form-row {
            display: contents;
            margin: 0;
        }

        #editModal .form-group {
            margin: 0;
        }

        #editModal .form-group.full-width {
            grid-column: 1 / -1;
        }

        #editModal label {
            margin-bottom: 8px;
            color: #111827;
            font-size: 13px;
            font-weight: 600;
        }

        #editModal input,
        #editModal select,
        #editModal textarea {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 11px 12px;
            background: #fff;
            font-size: 14px;
        }

        #editModal input:focus,
        #editModal select:focus,
        #editModal textarea:focus {
            outline: none;
            border-color: #48A6A7;
            box-shadow: 0 0 0 3px rgba(72, 166, 167, 0.18);
        }

        #editModal textarea {
            min-height: 90px;
        }

        #editModal .modal-actions {
            margin-top: 18px;
            gap: 10px;
        }

        #editModal .btn-submit,
        #editModal .btn-cancel {
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 14px;
            min-width: 150px;
        }

        #editModal .btn-submit {
            background: #48A6A7;
            color: #fff;
        }

        #editModal .btn-submit:hover {
            background: #2a9d8f;
        }

        @media (max-width: 720px) {
            #editModal .edit-modal-content {
                padding: 20px;
            }

            .edit-account-grid {
                grid-template-columns: 1fr;
            }

            #editModal .modal-actions {
                flex-direction: column;
            }

            #editModal .btn-submit,
            #editModal .btn-cancel {
                width: 100%;
            }
        }

        /* Feedback modal refresh (mirrors reschedule modal layout) */
        #feedbackModal .edit-modal-content {
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.28);
            border: 1px solid #e5e7eb;
        }

        #feedbackModal .close {
            top: 14px;
            right: 14px;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #f3f4f6;
            color: #374151;
            font-size: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #feedbackModal .close:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .feedback-modal-heading {
            margin-bottom: 18px;
        }

        .feedback-modal-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        #feedbackModal .feedback-modal-heading h3 {
            margin: 0 0 6px 0;
            font-size: 1.35rem;
            color: #111827;
            font-weight: 700;
            text-align: left;
        }

        .feedback-modal-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .feedback-modal-counter {
            text-align: right;
            margin-top: 6px;
            font-size: 12px;
            color: #6B7280;
        }

        #feedbackModal .modal-actions {
            margin-top: 18px;
            gap: 10px;
        }

        #feedbackModal .btn-submit,
        #feedbackModal .btn-cancel {
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 14px;
            min-width: 150px;
        }

        #feedbackModal .btn-submit {
            background: #48A6A7;
            color: #fff;
        }

        #feedbackModal .btn-submit:hover {
            background: #2a9d8f;
        }

        @media (max-width: 720px) {
            #feedbackModal .edit-modal-content {
                padding: 20px;
            }

            #feedbackModal .modal-actions {
                flex-direction: column;
            }

            #feedbackModal .btn-submit,
            #feedbackModal .btn-cancel {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<?php
// Display feedback success/error messages
if (isset($_SESSION['feedback_success'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('success', 'Feedback Posted!', '" . addslashes($_SESSION['feedback_success']) . "');
        });
    </script>";
    unset($_SESSION['feedback_success']);
}
if (isset($_SESSION['feedback_error'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('error', 'Error', '" . addslashes($_SESSION['feedback_error']) . "');
        });
    </script>";
    unset($_SESSION['feedback_error']);
}

// Display password change success/error messages
if (isset($_SESSION['password_success'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('success', 'Password Changed!', '" . addslashes($_SESSION['password_success']) . "');
        });
    </script>";
    unset($_SESSION['password_success']);
}
if (isset($_SESSION['password_error'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('error', 'Password Change Failed', '" . addslashes($_SESSION['password_error']) . "');
        });
    </script>";
    unset($_SESSION['password_error']);
}
?>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="confirmation-modal">
    <div class="confirmation-content">
        <div class="confirmation-icon">⚠️</div>
        <h3 class="confirmation-title" id="confirmationTitle">Confirm Action</h3>
        <p class="confirmation-message" id="confirmationMessage">Are you sure you want to proceed?</p>
        <div class="confirmation-actions">
            <button class="confirmation-btn confirmation-btn-cancel" onclick="closeConfirmationModal()">Cancel</button>
            <button class="confirmation-btn confirmation-btn-confirm" id="confirmActionBtn">Confirm</button>
        </div>
    </div>
</div>

<div class="account-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-header">
            <div>
                <h1>Welcome back, <?= htmlspecialchars($user['first_name'] ?? 'User'); ?></h1>
                <p>Manage your appointments and account settings</p>
            </div>
            <div class="header-actions">
                <a href="../controllers/patientLogout.php" class="btn btn-secondary logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="account-layout">
        <!-- Left Column - Account Info & Quick Actions -->
        <div class="left-column">
            <!-- Account Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Account Information</h2>
                    <button class="btn-edit" onclick="openEditModal()">Edit</button>
                </div>
                <p class="card-subtitle">Your personal details</p>
                
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?= htmlspecialchars($user['username'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">User ID</span>
                        <span class="info-value"><?= htmlspecialchars($user['user_id'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?= htmlspecialchars($user['email'] ?? ''); ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <h2 class="card-title">Quick Actions</h2>
                
                <div class="action-list">
                    <a href="allAppointments.php" class="action-item">
                        <span class="action-icon">📋</span>
                        <span class="action-text">View All Appointments</span>
                    </a>
                    <a href="#edit-credentials" class="action-item" onclick="openCredentialsModal()">
                        <span class="action-icon">🔐</span>
                        <span class="action-text">Change Password</span>
                    </a>
                    <a href="location.php" class="action-item">
                        <span class="action-icon">📍</span>
                        <span class="action-text">Find Locations</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column - Recent Appointments -->
        <div class="right-column">
            <div class="card">
                <h2 class="card-title">Your Recent Appointment</h2>
                <p class="card-subtitle">View and manage your most recent appointment</p>

                <?php if (!empty($recent_appointments)): ?>
                    <?php 
                    // Check if any appointment is completed and user hasn't posted feedback
                    $showFeedbackButton = false;
                    $completedAppointmentId = null;
                    foreach ($recent_appointments as $appt) {
                        if (($appt['status'] == 'Complete' || $appt['status'] == 'Completed') && !$hasFeedback) {
                            $showFeedbackButton = true;
                            $completedAppointmentId = $appt['appointment_id'];
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($showFeedbackButton): ?>
                        <div style="margin-bottom: 20px; padding: 15px; background: #f0fdf4; border: 2px solid #10B981; border-radius: 8px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                                <div>
                                    <p style="margin: 0; font-weight: 600; color: #065f46;">Share Your Experience</p>
                                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #047857;">Help us improve by posting your feedback</p>
                                </div>
                                <button type="button" 
                                   class="btn btn-feedback"
                                   data-appointment-id="<?= htmlspecialchars($completedAppointmentId); ?>"
                                   onclick="openFeedbackModal(this)">
                                    Post Feedback
                                </button>
                            </div>
                        </div>
                    <?php elseif ($hasFeedback): ?>
                        <div style="margin-bottom: 20px; padding: 15px; background: #f0f9ff; border: 2px solid #3b82f6; border-radius: 8px;">
                            <p style="margin: 0; color: #1e40af; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you! You have already posted your feedback.</span>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach ($recent_appointments as $recent_appointment): ?>
                        <?php
                        $status = $recent_appointment['status'];
                        $payment_method = $recent_appointment['payment_method'] ?? null;
                        $payment_status = $recent_appointment['payment_status'] ?? null;
                        $payment_id = $recent_appointment['payment_id'] ?? null;
                        $isWalkInAppointment = !empty($recent_appointment['_is_walkin']) || ($status === 'Walk-in');

                        // make sure refund_requests table exists so our later lookup doesn't error
                        $createReqTable = "CREATE TABLE IF NOT EXISTS refund_requests (
                                          id varchar(10) NOT NULL,
                                          payment_id varchar(10) NOT NULL,
                                          appointment_id varchar(10) NOT NULL,
                                          user_id varchar(10) NOT NULL,
                                          status enum('pending','processed','refunded') NOT NULL DEFAULT 'pending',
                                          created_at timestamp NOT NULL DEFAULT current_timestamp(),
                                          PRIMARY KEY (id),
                                          UNIQUE KEY payment_id (payment_id)
                                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                        mysqli_query($con, $createReqTable);
                        @mysqli_query($con, "ALTER TABLE refund_requests MODIFY status ENUM('pending','processed','refunded') NOT NULL DEFAULT 'pending'");

                        // check if a refund request has already been submitted for this payment
                        $alreadyRequested = false;
                        $requestStatus = '';
                        $requestDate = null;
                        if (!empty($payment_id)) {
                            $stmt = $con->prepare("SELECT status, created_at FROM refund_requests WHERE payment_id = ? LIMIT 1");
                            if ($stmt) {
                                $stmt->bind_param("s", $payment_id);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res && ($row = $res->fetch_assoc())) {
                                    $alreadyRequested = true;
                                    $requestStatus = $row['status'];
                                    $requestDate = $row['created_at'];
                                }
                                $stmt->close();
                            }
                        }
                        
                        // Check if cash payment and not paid yet (check both lowercase and uppercase for safety)
                        $isCashUnpaid = ($payment_method == 'Cash' && (strtolower($payment_status) == 'pending' || $payment_status == null));
                        
                        // Calculate date-related helpers
                        $appointment_date = $recent_appointment['appointment_date'];
                        $deadline_date = null;
                        $deadline_formatted = '';
                        $isTomorrow = false;
                        $today = new DateTime('today');
                        $appointmentDateObj = null;
                        $daysBeforeAppointment = null;

                        if ($appointment_date && $appointment_date !== 'Walk-In' && $appointment_date !== 'Walk-in') {
                            $appointmentDateObj = new DateTime($appointment_date);
                            $interval = $today->diff($appointmentDateObj);
                            $daysBeforeAppointment = (int)$interval->format('%r%a'); // positive if appointment is in the future
                        }

                        // Calculate deadline - check if appointment is tomorrow (for unpaid cash)
                        if ($isCashUnpaid && $appointmentDateObj) {
                            $tomorrow = clone $today;
                            $tomorrow->modify('+1 day');
                            $isTomorrow = ($appointmentDateObj->format('Y-m-d') === $tomorrow->format('Y-m-d'));

                            if ($isTomorrow) {
                                // For tomorrow appointments, deadline is today
                                $deadline_formatted = $today->format('F j, Y');
                            } else {
                                // For other appointments, deadline is 2 days before
                                $deadlineDateObj = clone $appointmentDateObj;
                                $deadlineDateObj->modify('-2 days');
                                $deadline_formatted = $deadlineDateObj->format('F j, Y');
                            }
                        }

                        // Determine if this cancelled appointment is eligible for a refund request
                        $paymentStatusLower = strtolower($payment_status ?? '');
                        $eligibleForRefundRequest = (
                            $status === 'Cancelled' &&
                            !$isWalkInAppointment &&
                            !empty($payment_id) &&
                            $paymentStatusLower === 'paid' &&
                            $daysBeforeAppointment !== null &&
                            $daysBeforeAppointment >= 2 &&
                            !$alreadyRequested
                        );
                        
                        $statusClass = match($status) {
                            'Pending' => 'status-pending',
                            'Confirmed' => 'status-confirmed',
                            'Walk-in' => 'status-confirmed',
                            'Follow-up' => 'status-follow-up',
                            'Cancelled' => 'status-cancelled',
                            'Complete' => 'status-completed',
                            'Completed' => 'status-completed',
                            'Reschedule' => 'status-reschedule',
                            default => 'status-default'
                        };
                        
                        // Determine if buttons should be disabled
                        $buttonsDisabled = ($status == 'Cancelled' || $status == 'Complete' || $status == 'Completed' || $isCashUnpaid);
                        
                        // Print Receipt enabled only for digital Confirmed appointments.
                        // Walk-in appointments should NOT allow printing.
                        $printReceiptDisabled = ($isWalkInAppointment || $status !== 'Confirmed');

                        // Reschedule is disabled for walk-in appointments
                        $rescheduleDisabled = ($buttonsDisabled || $isWalkInAppointment);
                        ?>
                        <div class="appointment-card" style="margin-bottom: 20px;" data-appointment-id="<?= htmlspecialchars($recent_appointment['appointment_id']); ?>" data-is-walkin="<?= $isWalkInAppointment ? '1' : '0'; ?>">
                            <div class="appointment-header">
                                <h3>Appointment #<?= htmlspecialchars($recent_appointment['appointment_id']); ?></h3>
                                <span class="status-badge js-status-badge <?= $statusClass; ?>"><?= htmlspecialchars($status); ?></span>
                            </div>
                            
                            <div class="appointment-details">
                                <div class="detail-item">
                                    <span class="detail-label">Date</span>
                                    <span class="detail-value js-appointment-date"><?= htmlspecialchars($recent_appointment['appointment_date']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Time</span>
                                    <span class="detail-value js-appointment-time"><?= htmlspecialchars($recent_appointment['appointment_time']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Service</span>
                                    <span class="detail-value js-appointment-service"><?= htmlspecialchars($recent_appointment['service_display'] ?? $recent_appointment['sub_service'] ?? $recent_appointment['service_category'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Dentist</span>
                                    <span class="detail-value">Dr. Michelle Landero</span>
                                </div>
                            </div>
                            
                            <div class="appointment-message js-appointment-message">
                                <?php
                                if ($isCashUnpaid) {
                                    // Show cash payment notice
                                    echo "<div style='background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 15px 0;'>";
                                    echo "<p style='color: #856404; margin: 0; line-height: 1.6;'><strong>⚠️ Appointment Slot Reserved!</strong></p>";
                                    
                                    echo "<p style='color: #856404; margin: 10px 0 0 0; line-height: 1.6;'><strong>📱 On your appointment date (" . date('F j, Y', strtotime($appointment_date)) . "):</strong></p>";
                                    echo "<p style='color: #856404; margin: 5px 0 0 0; line-height: 1.6;'>Please visit the clinic and present your Appointment ID (in the confirmation email) to the cashier for payment.</p>";
                                    echo "<p style='color: #856404; margin: 5px 0 0 0; line-height: 1.6;'>Appointment ID: " . htmlspecialchars($recent_appointment['appointment_id']) . " (Status: Pending - Cash Payment Required)</p>";
                                    echo "</div>";
                                } elseif ($status == "Pending") {
                                    // Show the "scheduled / cancel until ..." note only for the original booking.
                                    // If admin/clinic rescheduled and left a note (`reschedule_reason`), hide that note here
                                    // and let the "Note from clinic" section below be the main message.
                                    if (empty($recent_appointment['reschedule_reason'])) {
                                        echo "<p>Your appointment has been scheduled. Please wait for confirmation.</p>";
                                        
                                        if ($appointmentDateObj) {
                                            $cancelDeadline = clone $appointmentDateObj;
                                            $cancelDeadline->modify('-1 day');
                                            echo "<p><strong>Note:</strong> You can cancel this appointment until " . $cancelDeadline->format('F j, Y') . " (1 day before your appointment date).</p>";
                                        } else {
                                            echo "<p><strong>Note:</strong> Cancellations must be made at least 1 day before your appointment date.</p>";
                                        }
                                    } else {
                                        echo "<p>Your appointment has been rescheduled. Please wait for confirmation.</p>";
                                    }
                                } elseif ($status == "Confirmed") {
                                    echo "<p>Your appointment has been confirmed.</p>";
                                } elseif ($status == "Follow-up") {
                                    echo "<p>Your follow-up appointment has been scheduled. Please check the details below.</p>";
                                } elseif ($status == "Walk-In") {
                                    echo "<p>Your walk-in appointment is confirmed. Please proceed to the clinic for final scheduling.</p>";
                                } elseif ($status == "Complete" || $status == "Completed") {
                                    echo "<p>Your appointment has been completed.</p>";
                                } elseif ($status == "Cancelled") {
                                    echo "<p>Your appointment has been cancelled.</p>";

                                    if ($alreadyRequested) {
                                        echo "<p class=\"text-success\"><strong>Refund request has already been submitted" .
                                             ($requestStatus ? " (" . htmlspecialchars(ucfirst($requestStatus)) . ")" : "") .
                                             ".</strong></p>";
                                        if ($requestDate) {
                                            echo "<p>Requested on " . date('F j, Y g:i A', strtotime($requestDate)) . "</p>";
                                        }
                                    } elseif ($eligibleForRefundRequest) {
                                        echo "<p><strong>Note:</strong> This appointment was cancelled at least 2 days before the scheduled date. You may request a refund for your payment below.</p>";
                                    }
                                } elseif ($status == "Reschedule") {
                                    echo "<p>Your appointment has been rescheduled. Please wait for confirmation.</p>";
                                }

                                // Show clinic reschedule notes only for non-cancelled appointments.
                                if (!empty($recent_appointment['reschedule_reason']) && strtolower((string)$status) !== 'cancelled') {
                                    echo "<p><strong>Note from clinic:</strong> " . htmlspecialchars($recent_appointment['reschedule_reason']) . "</p>";
                                    echo "<p>If you are not available for this new schedule, you can reschedule again from this page.</p>";
                                }
                                ?>
                            </div>
                            
                            <div class="appointment-actions js-appointment-actions">
                                <?php if ($status == 'Cancelled'): ?>
                                    <!-- For Cancelled appointments, allow reschedule and optional refund request -->
                                    <?php if (!$isWalkInAppointment): ?>
                                        <button type="button"
                                           class="btn btn-primary"
                                           data-appointment-id="<?= htmlspecialchars($recent_appointment['appointment_id']); ?>"
                                           data-service="<?= htmlspecialchars($recent_appointment['service_display'] ?? $recent_appointment['sub_service'] ?? $recent_appointment['service_category'] ?? 'N/A'); ?>"
                                           data-current-date="<?= htmlspecialchars($recent_appointment['appointment_date']); ?>"
                                           data-current-time="<?= htmlspecialchars($recent_appointment['appointment_time']); ?>"
                                           data-has-request-note="<?= !empty($recent_appointment['request_note']) ? '1' : '0'; ?>"
                                           data-status="<?= htmlspecialchars($status); ?>"
                                           onclick="openRescheduleModal(this)">
                                            Reschedule
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($eligibleForRefundRequest): ?>
                                        <button type="button"
                                            class="btn btn-secondary refund-btn"
                                            data-appointment-id="<?= htmlspecialchars($recent_appointment['appointment_id']); ?>"
                                            data-payment-id="<?= htmlspecialchars($payment_id); ?>"
                                            onclick="requestRefund(this)">
                                            Request Refund
                                        </button>
                                    <?php elseif ($alreadyRequested): ?>
                                        <button type="button" class="btn btn-secondary" disabled>
                                            Refund Requested
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Show all buttons for non-cancelled appointments -->

                                    <?php if (!$isWalkInAppointment): ?>
                                        <button type="button" 
                                           class="btn btn-danger <?= $buttonsDisabled ? 'disabled' : ''; ?>"
                                           data-appointment-id="<?= htmlspecialchars($recent_appointment['appointment_id']); ?>"
                                           <?= $buttonsDisabled ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : 'onclick="showCancelConfirmation(this)"'; ?>>
                                            Cancel
                                        </button>

                                        <button type="button"
                                           class="btn btn-primary <?= $rescheduleDisabled ? 'disabled' : ''; ?>"
                                           data-appointment-id="<?= htmlspecialchars($recent_appointment['appointment_id']); ?>"
                                           data-service="<?= htmlspecialchars($recent_appointment['service_display'] ?? $recent_appointment['sub_service'] ?? $recent_appointment['service_category'] ?? 'N/A'); ?>"
                                           data-current-date="<?= htmlspecialchars($recent_appointment['appointment_date']); ?>"
                                           data-current-time="<?= htmlspecialchars($recent_appointment['appointment_time']); ?>"
                                           data-has-request-note="<?= !empty($recent_appointment['request_note']) ? '1' : '0'; ?>"
                                           data-status="<?= htmlspecialchars($status); ?>"
                                           <?= $rescheduleDisabled ? 'disabled style="opacity: 0.5; cursor: not-allowed; pointer-events: none;"' : 'onclick="openRescheduleModal(this)"'; ?>>
                                            Reschedule
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-appointment">
                        <div class="no-appointment-icon">📅</div>
                        <h3>No Recent Appointment</h3>
                        <p>You don't have any appointments scheduled yet. Book your next dental visit to maintain your oral health.</p>
                        <a href="index.php" class="btn btn-primary">Book an Appointment</a>
                        <a href="allAppointments.php" class="btn btn-secondary" style="margin-top: 10px; display: inline-block;">View All Appointments</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="reschedule-modal-overlay">
    <div class="reschedule-modal-panel">
        <button type="button" class="reschedule-modal-close" onclick="closeRescheduleModal()" aria-label="Close reschedule dialog">&times;</button>
        <div class="reschedule-modal-heading">
            <span class="reschedule-modal-badge">Reschedule</span>
            <h3>Update your appointment schedule</h3>
            <p>Select a new date and available time slot.</p>
        </div>

        <div class="reschedule-current-card">
            <p class="reschedule-current-card-title">Current Appointment Details</p>
            <div class="reschedule-current-grid">
                <div class="reschedule-current-item">
                    <label>Service</label>
                    <span id="rescheduleCurrentService">-</span>
                </div>
                <div class="reschedule-current-item">
                    <label>Current Date</label>
                    <span id="rescheduleCurrentDate">-</span>
                </div>
                <div class="reschedule-current-item">
                    <label>Current Time</label>
                    <span id="rescheduleCurrentTime">-</span>
                </div>
                <div class="reschedule-current-item">
                    <label>Status</label>
                    <span id="rescheduleCurrentStatus">-</span>
                </div>
            </div>
        </div>

        <form id="rescheduleFormModal">
            <input type="hidden" name="appointment_id" id="rescheduleAppointmentId">
            <input type="hidden" name="has_request_note" id="rescheduleHasRequestNote" value="0">
            <div class="reschedule-form-grid">
                <div class="reschedule-form-group">
                    <label for="new_date_resched_modal">New Date *</label>
                    <input type="date" id="new_date_resched_modal" name="new_date_resched" class="reschedule-form-control" min="<?= htmlspecialchars($rescheduleToday); ?>" max="<?= htmlspecialchars($rescheduleMaxDate); ?>" required>
                </div>
                <div class="reschedule-form-group">
                    <label for="new_time_slot_modal">New Time Slot *</label>
                    <select id="new_time_slot_modal" name="new_time_slot" class="reschedule-form-control" required>
                        <option value="">Select a time slot</option>
                        <?php foreach ($rescheduleTimeMap as $slot => $time): ?>
                            <option value="<?= htmlspecialchars($slot); ?>"><?= htmlspecialchars($time); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p id="rescheduleSlotHint" style="margin: 10px 0 0 0; font-size: 13px; color: #6b7280; display: none;">
                This appointment includes a request note and requires 2 consecutive time slots.
            </p>

            <div class="reschedule-modal-actions">
                <button type="button" class="reschedule-cancel-btn" onclick="closeRescheduleModal()">Cancel</button>
                <button type="submit" class="reschedule-submit-btn" id="rescheduleSubmitBtn">Reschedule Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Account Modal -->
<div id="editModal" class="edit-modal">
    <div class="edit-modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <div class="edit-account-heading">
            <span class="edit-account-badge">Profile</span>
            <h3>Edit account information</h3>
            <p class="edit-account-subtitle">Update your personal details and keep your profile up to date.</p>
        </div>
        <form id="editAccountForm" action="../controllers/updateAccount.php" method="POST">
            <div class="edit-account-grid">
            <div class="form-row">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone:</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Birthdate:</label>
                    <input type="date" name="birthdate" value="<?= htmlspecialchars($user['birthdate'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Gender:</label>
                    <select name="gender" required>
                        <option value="Male" <?= (($user['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?= (($user['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <!-- Kept for existing form structure -->
                </div>
            </div>
            
            <div class="form-group full-width">
                <label>Address:</label>
                <textarea name="address" required><?= htmlspecialchars($user['address'] ?? ''); ?></textarea>
            </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-submit">UPDATE ACCOUNT</button>
            </div>
        </form>
    </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="edit-modal">
    <div class="edit-modal-content">
        <span class="close" onclick="closeFeedbackModal()">&times;</span>
        <div class="feedback-modal-heading">
            <span class="feedback-modal-badge">Feedback</span>
            <h3>Post your feedback</h3>
            <p class="feedback-modal-subtitle">Share your experience with us. Your feedback will be displayed on our homepage.</p>
        </div>
        <form action="../controllers/submitFeedback.php" method="POST" id="feedbackForm">
            <input type="hidden" name="appointment_id" id="feedback_appointment_id" value="">
            <div class="form-group full-width">
                <label>Your Feedback:</label>
                <textarea name="feedback_text" id="feedback_text" rows="6" placeholder="Tell us about your experience..." required maxlength="500"></textarea>
                <div class="feedback-modal-counter">
                    <span id="charCount">0</span>/500 characters
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeFeedbackModal()">Cancel</button>
                <button type="submit" class="btn-submit">POST FEEDBACK</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Credentials Modal -->
<div id="credentialsModal" class="edit-modal">
    <div class="edit-modal-content">
        <span class="close" onclick="closeCredentialsModal()">&times;</span>
        <h3>CHANGE PASSWORD</h3>
        <form action="../controllers/updateCredentials.php" method="POST" id="credentialsForm">
            <div class="form-group full-width">
                <label>Current Password:</label>
                <input type="password" name="current_password" id="current_password" required>
                <span class="password-toggle" onclick="togglePassword('current_password', this)">👁️</span>
            </div>
            
            <div class="form-group full-width">
                <label>New Password:</label>
                <input type="password" name="new_password" id="new_password" required minlength="8">
                <span class="password-toggle" onclick="togglePassword('new_password', this)">👁️</span>
                <div class="password-strength">
                    <div class="strength-bar"></div>
                    <span class="strength-text">Password strength</span>
                </div>
            </div>
            
            <div class="form-group full-width">
                <label>Confirm New Password:</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
                <span class="password-toggle" onclick="togglePassword('confirm_password', this)">👁️</span>
                <span id="password-match" class="validation-message"></span>
            </div>
            
            <div class="password-requirements">
                <p><strong>Password Requirements:</strong></p>
                <ul>
                    <li id="req-length">At least 8 characters</li>
                    <li id="req-uppercase">One uppercase letter</li>
                    <li id="req-lowercase">One lowercase letter</li>
                    <li id="req-number">One number</li>
                    <li id="req-special">One special character</li>
                </ul>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeCredentialsModal()">Cancel</button>
                <button type="submit" class="btn-submit" id="updatePasswordBtn">UPDATE PASSWORD</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function openEditModal() {
    const modal = document.getElementById("editModal");
    modal.style.display = "flex";
    setTimeout(() => modal.classList.add("show"), 10);
}

function closeEditModal() {
    const modal = document.getElementById("editModal");
    modal.classList.remove("show");
    setTimeout(() => modal.style.display = "none", 300);
}

// Handle edit account form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const editAccountForm = document.getElementById('editAccountForm');
    if (editAccountForm) {
        editAccountForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = editAccountForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Disable submit button and show loading
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            // Create FormData object
            const formData = new FormData(editAccountForm);
            
            // Submit via AJAX
            fetch('../controllers/updateAccount.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success notification with animation
                    showNotification('success', 'Account Updated', data.message || 'Your account information has been updated successfully!');
                    
                    // Close modal after short delay
                    setTimeout(() => {
                        closeEditModal();
                        // Reload page to show updated information
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error notification
                    showNotification('error', 'Update Failed', data.message || 'Failed to update account. Please try again.');
                    
                    // Re-enable submit button
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Update Failed', 'An error occurred while updating your account. Please try again.');
                
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    }
});

function openCredentialsModal() {
    const modal = document.getElementById("credentialsModal");
    modal.style.display = "flex";
    setTimeout(() => modal.classList.add("show"), 10);
    resetCredentialsForm();
}

function closeCredentialsModal() {
    const modal = document.getElementById("credentialsModal");
    modal.classList.remove("show");
    setTimeout(() => modal.style.display = "none", 300);
}

// Feedback Modal functions
function openFeedbackModal(button) {
    const modal = document.getElementById("feedbackModal");
    const appointmentId = button.getAttribute('data-appointment-id');
    document.getElementById('feedback_appointment_id').value = appointmentId;
    document.getElementById('feedback_text').value = '';
    document.getElementById('charCount').textContent = '0';
    modal.style.display = "flex";
    setTimeout(() => modal.classList.add("show"), 10);
}

function closeFeedbackModal() {
    const modal = document.getElementById("feedbackModal");
    modal.classList.remove("show");
    setTimeout(() => modal.style.display = "none", 300);
}

function openRescheduleModal(button) {
    const modal = document.getElementById('rescheduleModal');
    const appointmentId = button.getAttribute('data-appointment-id') || '';
    const service = button.getAttribute('data-service') || 'N/A';
    const currentDate = button.getAttribute('data-current-date') || '';
    const currentTime = button.getAttribute('data-current-time') || 'N/A';
    const status = button.getAttribute('data-status') || 'N/A';
    const hasRequestNote = button.getAttribute('data-has-request-note') === '1';

    document.getElementById('rescheduleAppointmentId').value = appointmentId;
    document.getElementById('rescheduleHasRequestNote').value = hasRequestNote ? '1' : '0';
    document.getElementById('rescheduleCurrentService').textContent = service;
    document.getElementById('rescheduleCurrentDate').textContent = currentDate || 'N/A';
    document.getElementById('rescheduleCurrentTime').textContent = currentTime;
    document.getElementById('rescheduleCurrentStatus').textContent = status;
    document.getElementById('new_date_resched_modal').value = '';
    const slotHint = document.getElementById('rescheduleSlotHint');
    if (slotHint) {
        slotHint.style.display = hasRequestNote ? 'block' : 'none';
    }
    resetTimeSlotOptions();
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeRescheduleModal() {
    const modal = document.getElementById('rescheduleModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 200);
}

function resetTimeSlotOptions() {
    const timeSelect = document.getElementById('new_time_slot_modal');
    if (!timeSelect) return;
    Array.from(timeSelect.options).forEach(option => {
        if (!option.value) return;
        if (!option.getAttribute('data-original-text')) {
            option.setAttribute('data-original-text', option.textContent);
        }
        option.disabled = false;
        option.classList.remove('time-slot-disabled');
        option.textContent = option.getAttribute('data-original-text');
    });
    timeSelect.value = '';
}

// Character counter for feedback
document.addEventListener('DOMContentLoaded', function() {
    const feedbackText = document.getElementById('feedback_text');
    const charCount = document.getElementById('charCount');
    
    if (feedbackText && charCount) {
        feedbackText.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            
            if (length > 500) {
                charCount.style.color = '#EF4444';
            } else if (length > 400) {
                charCount.style.color = '#F59E0B';
            } else {
                charCount.style.color = '#6B7280';
            }
        });
    }
    
    // Form submission handler
    const feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function(e) {
            const feedbackText = document.getElementById('feedback_text').value.trim();
            if (feedbackText.length < 10) {
                e.preventDefault();
                showNotification('warning', 'Feedback Too Short', 'Please provide at least 10 characters of feedback.');
                return false;
            }
            if (feedbackText.length > 500) {
                e.preventDefault();
                showNotification('error', 'Feedback Too Long', 'Please keep your feedback under 500 characters.');
                return false;
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('new_date_resched_modal');
    const timeSelect = document.getElementById('new_time_slot_modal');
    const form = document.getElementById('rescheduleFormModal');
    const submitBtn = document.getElementById('rescheduleSubmitBtn');
    const hasRequestNoteInput = document.getElementById('rescheduleHasRequestNote');
    const slotOrder = [
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
    let latestUnavailableSlots = new Set();

    function getNextSlot(slotKey) {
        const idx = slotOrder.indexOf(slotKey);
        if (idx === -1 || idx + 1 >= slotOrder.length) return null;
        return slotOrder[idx + 1];
    }

    function getCombinedRangeLabel(firstSlotKey, secondSlotKey) {
        const firstOption = Array.from(timeSelect.options).find(opt => opt.value === firstSlotKey);
        const secondOption = Array.from(timeSelect.options).find(opt => opt.value === secondSlotKey);
        const firstText = firstOption
            ? (firstOption.getAttribute('data-original-text') || firstOption.textContent || '').trim()
            : '';
        const secondText = secondOption
            ? (secondOption.getAttribute('data-original-text') || secondOption.textContent || '').trim()
            : '';
        if (!firstText || !secondText || !firstText.includes('-') || !secondText.includes('-')) {
            return firstText || secondText || '';
        }
        const start = firstText.split('-')[0];
        const end = secondText.split('-')[1];
        return `${start}-${end}`;
    }

    if (!dateInput || !timeSelect || !form || !submitBtn) return;

    dateInput.addEventListener('change', function() {
        const selectedDate = this.value;
        resetTimeSlotOptions();
        if (!selectedDate) return;

        fetch(`../controllers/getAppointments.php?date=${encodeURIComponent(selectedDate)}`)
            .then(response => {
                if (!response.ok) throw new Error('Unable to fetch appointment slots.');
                return response.json();
            })
            .then(data => {
                const unavailableSlots = data.unavailable_slots || [];
                latestUnavailableSlots = new Set(unavailableSlots);

                if (data.clinic_closed && data.closure_type === 'full_day') {
                    showNotification('warning', 'Clinic Closed', data.closure_reason || 'No appointments available on this date.');
                    dateInput.value = '';
                    return;
                }

                Array.from(timeSelect.options).forEach(option => {
                    if (!option.value) return;
                    if (unavailableSlots.includes(option.value)) {
                        option.disabled = true;
                        option.classList.add('time-slot-disabled');
                        option.textContent = `${option.getAttribute('data-original-text')} (Booked)`;
                    }
                });

                const requiresTwoSlots = hasRequestNoteInput && hasRequestNoteInput.value === '1';
                if (requiresTwoSlots) {
                    Array.from(timeSelect.options).forEach(option => {
                        if (!option.value || option.disabled) return;
                        const nextSlot = getNextSlot(option.value);
                        if (!nextSlot) {
                            option.disabled = true;
                            option.classList.add('time-slot-disabled');
                            option.textContent = `${option.getAttribute('data-original-text')} (Needs next slot)`;
                            return;
                        }
                        if (latestUnavailableSlots.has(nextSlot)) {
                            option.disabled = true;
                            option.classList.add('time-slot-disabled');
                            option.textContent = `${option.getAttribute('data-original-text')} (Next slot unavailable)`;
                        }
                    });
                }
            })
            .catch(error => {
                console.error(error);
                showNotification('error', 'Error', 'Error loading available time slots. Please try again.');
            });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const appointmentId = document.getElementById('rescheduleAppointmentId').value.trim();
        const selectedDate = dateInput.value;
        const selectedTime = timeSelect.value;
        const requiresTwoSlots = hasRequestNoteInput && hasRequestNoteInput.value === '1';

        if (!appointmentId || !selectedDate || !selectedTime) {
            showNotification('warning', 'Validation Error', 'Please select both date and time.');
            return;
        }

        if (timeSelect.options[timeSelect.selectedIndex] && timeSelect.options[timeSelect.selectedIndex].disabled) {
            showNotification('error', 'Time Slot Unavailable', 'The selected time slot is not available. Please choose another time.');
            return;
        }

        if (requiresTwoSlots) {
            const nextSlot = getNextSlot(selectedTime);
            if (!nextSlot) {
                showNotification('error', 'Invalid Time Slot', 'This appointment requires 2 consecutive slots. Please select an earlier time.');
                return;
            }
            if (latestUnavailableSlots.has(nextSlot)) {
                showNotification('error', 'Second Slot Unavailable', 'The next consecutive slot is unavailable. Please choose another start time.');
                return;
            }
        }

        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        const formData = new FormData(form);

        fetch('../controllers/rescheduleAppointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (parseError) {
                    return { success: false, message: 'Invalid response from server.' };
                }
            });
        })
        .then(data => {
            if (data.success || data.status === 'success') {
                showNotification('success', 'Appointment Rescheduled', data.message || 'Appointment rescheduled successfully!');
                closeRescheduleModal();
                let selectedTimeLabel = timeSelect.options[timeSelect.selectedIndex]
                    ? timeSelect.options[timeSelect.selectedIndex].textContent.replace(' (Booked)', '')
                    : '';
                if (data.appointment_time) {
                    selectedTimeLabel = data.appointment_time;
                } else if (requiresTwoSlots) {
                    const nextSlot = getNextSlot(selectedTime);
                    if (nextSlot) {
                        selectedTimeLabel = getCombinedRangeLabel(selectedTime, nextSlot) || selectedTimeLabel;
                    }
                }
                const card = getAppointmentCard(appointmentId);
                if (card) {
                    setCardSchedule(card, selectedDate, selectedTimeLabel);
                    setCardStatus(card, 'Pending');
                    setCardMessage(card, '<p>Your appointment has been rescheduled. Please wait for confirmation.</p>');
                    setCardActionsForPending(card, appointmentId);
                }
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            } else {
                showNotification('error', 'Reschedule Failed', data.message || 'Failed to reschedule appointment. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while rescheduling. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
});

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById("editModal");
    const credentialsModal = document.getElementById("credentialsModal");
    const feedbackModal = document.getElementById("feedbackModal");
    const rescheduleModal = document.getElementById("rescheduleModal");
    
    if (event.target === editModal) closeEditModal();
    if (event.target === credentialsModal) closeCredentialsModal();
    if (event.target === feedbackModal) closeFeedbackModal();
    if (event.target === rescheduleModal) closeRescheduleModal();
};

// Password visibility toggle
function togglePassword(inputId, toggleElement) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        toggleElement.textContent = '🙈';
    } else {
        input.type = 'password';
        toggleElement.textContent = '👁️';
    }
}

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    const requirements = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
    };

    // Update requirement indicators
    Object.keys(requirements).forEach(req => {
        const element = document.getElementById(`req-${req}`);
        if (element) {
            element.className = requirements[req] ? 'requirement-met' : '';
        }
    });

    // Calculate strength
    strength += requirements.length ? 1 : 0;
    strength += requirements.uppercase ? 1 : 0;
    strength += requirements.lowercase ? 1 : 0;
    strength += requirements.number ? 1 : 0;
    strength += requirements.special ? 1 : 0;

    // Update strength bar
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    
    if (strengthBar && strengthText) {
        const width = (strength / 5) * 100;
        strengthBar.style.width = `${width}%`;
        
        if (strength <= 2) {
            strengthBar.className = 'strength-bar weak';
            strengthText.textContent = 'Weak password';
        } else if (strength <= 4) {
            strengthBar.className = 'strength-bar medium';
            strengthText.textContent = 'Medium strength';
        } else {
            strengthBar.className = 'strength-bar strong';
            strengthText.textContent = 'Strong password';
        }
    }

    return strength;
}

// Password confirmation check
function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchElement = document.getElementById('password-match');
    
    if (confirmPassword === '') {
        matchElement.textContent = '';
        matchElement.className = 'validation-message';
    } else if (newPassword === confirmPassword) {
        matchElement.textContent = '✓ Passwords match';
        matchElement.className = 'validation-message valid';
    } else {
        matchElement.textContent = '✗ Passwords do not match';
        matchElement.className = 'validation-message invalid';
    }
}

// Reset credentials form
function resetCredentialsForm() {
    document.getElementById('credentialsForm').reset();
    document.querySelector('.strength-bar').style.width = '0%';
    document.querySelector('.strength-text').textContent = 'Password strength';
    document.getElementById('password-match').textContent = '';
    
    // Reset requirement indicators
    const requirements = ['length', 'uppercase', 'lowercase', 'number', 'special'];
    requirements.forEach(req => {
        const element = document.getElementById(`req-${req}`);
        if (element) element.className = '';
    });
}

// Event listeners for password fields
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
    
    // Form validation and AJAX submission for credentials
    const credentialsForm = document.getElementById('credentialsForm');
    if (credentialsForm) {
        credentialsForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default to use AJAX
            
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitButton = document.getElementById('updatePasswordBtn');
            const originalButtonText = submitButton.innerHTML;
            
            // Check if current password is provided
            if (!currentPassword) {
                showNotification('error', 'Validation Error', 'Please enter your current password.');
                return false;
            }
            
            // Check if passwords match
            if (newPassword !== confirmPassword) {
                showNotification('error', 'Validation Error', 'New passwords do not match!');
                return false;
            }
            
            // Check password strength (must meet all requirements)
            const strength = checkPasswordStrength(newPassword);
            const requirements = {
                length: newPassword.length >= 8,
                uppercase: /[A-Z]/.test(newPassword),
                lowercase: /[a-z]/.test(newPassword),
                number: /[0-9]/.test(newPassword),
                special: /[^A-Za-z0-9]/.test(newPassword)
            };
            
            // Check if all requirements are met
            const allRequirementsMet = Object.values(requirements).every(req => req === true);
            
            if (!allRequirementsMet) {
                const missingRequirements = [];
                if (!requirements.length) missingRequirements.push('at least 8 characters');
                if (!requirements.uppercase) missingRequirements.push('one uppercase letter');
                if (!requirements.lowercase) missingRequirements.push('one lowercase letter');
                if (!requirements.number) missingRequirements.push('one number');
                if (!requirements.special) missingRequirements.push('one special character');
                
                showNotification('error', 'Password Requirements Not Met', 
                    'Your password must contain: ' + missingRequirements.join(', '));
                return false;
            }
            
            if (strength < 5) {
                showNotification('error', 'Weak Password', 'Please choose a stronger password that meets all requirements.');
                return false;
            }
            
            // Disable submit button and show loading
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            // Create FormData object
            const formData = new FormData(credentialsForm);
            
            // Submit via AJAX
            fetch('../controllers/updateCredentials.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                // Check content type to determine if JSON or HTML
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, might be redirect or HTML
                    return response.text().then(text => {
                        // Try to parse as JSON if possible
                        try {
                            return JSON.parse(text);
                        } catch {
                            // If redirect happened, assume success
                            if (response.redirected || response.ok) {
                                return { success: true, message: 'Password updated successfully!' };
                            }
                            return { success: false, message: text || 'An error occurred' };
                        }
                    });
                }
            })
            .then(data => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                
                if (data.success || data.redirected) {
                    // Show success notification
                    showNotification('success', 'Password Changed!', 'Your password has been updated successfully.');
                    
                    // Close modal after short delay
                    setTimeout(() => {
                        closeCredentialsModal();
                        // Reload page to show session messages and clear form
                        location.reload();
                    }, 1500);
                } else {
                    // Show error notification
                    showNotification('error', 'Password Change Failed', data.message || 'An error occurred. Please try again.');
                }
            })
            .catch(error => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while updating your password. Please try again.');
            });
            
            return false;
        });
    }
});

// ==================== NOTIFICATION SYSTEM ====================
function showNotification(type, title, message, icon = null, duration = 5000) {
    const container = document.getElementById('notificationContainer');
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Default icons based on type
    let iconHTML = '';
    if (icon) {
        iconHTML = icon;
    } else {
        switch(type) {
            case 'success':
                iconHTML = '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" class="check-animation" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                break;
            case 'warning':
                iconHTML = '<i class="fas fa-exclamation-triangle"></i>';
                break;
            case 'error':
                iconHTML = '<i class="fas fa-times-circle"></i>';
                break;
            case 'info':
                iconHTML = '<i class="fas fa-info-circle"></i>';
                break;
        }
    }
    
    notification.innerHTML = `
        <div class="notification-icon ${type === 'success' ? 'success-scale-animation' : ''}">
            ${iconHTML}
        </div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close" onclick="closeNotification(this)">&times;</button>
    `;
    
    container.appendChild(notification);
    
    // Auto remove after duration
    setTimeout(() => {
        closeNotification(notification.querySelector('.notification-close'));
    }, duration);
}

function closeNotification(btn) {
    const notification = btn.closest('.notification');
    if (notification) {
        notification.classList.add('hide');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
}

// Special notification for appointment cancellation
function showCancelledNotification(appointmentId) {
    const container = document.getElementById('notificationContainer');
    const notification = document.createElement('div');
    notification.className = 'notification success';
    
    notification.innerHTML = `
        <div class="notification-icon success-scale-animation">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <path d="M5 13l4 4L19 7" class="check-animation" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="notification-content">
            <div class="notification-title">Appointment Cancelled!</div>
            <div class="notification-message">Appointment #${appointmentId} has been successfully cancelled.</div>
        </div>
        <button class="notification-close" onclick="closeNotification(this)">&times;</button>
    `;
    
    container.appendChild(notification);
    setTimeout(() => {
        closeNotification(notification.querySelector('.notification-close'));
    }, 5000);
}
// ==================== END NOTIFICATION SYSTEM ====================

// ==================== CONFIRMATION MODAL ====================
let pendingAction = null;

function showCancelConfirmation(button) {
    const appointmentId = button.getAttribute('data-appointment-id');
    const modal = document.getElementById('confirmationModal');
    const title = document.getElementById('confirmationTitle');
    const message = document.getElementById('confirmationMessage');
    const confirmBtn = document.getElementById('confirmActionBtn');
    
    title.textContent = 'Cancel Appointment';
    message.textContent = `Are you sure you want to cancel Appointment #${appointmentId}? This action cannot be undone.`;
    confirmBtn.textContent = 'Yes, Cancel';
    confirmBtn.onclick = function() {
        cancelAppointment(appointmentId);
        closeConfirmationModal();
    };
    
    modal.classList.add('show');
}

function closeConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    modal.classList.remove('show');
    pendingAction = null;
}

function getStatusClass(status) {
    switch (status) {
        case 'Pending': return 'status-pending';
        case 'Confirmed': return 'status-confirmed';
        case 'Walk-in': return 'status-confirmed';
        case 'Follow-up': return 'status-follow-up';
        case 'Cancelled': return 'status-cancelled';
        case 'Complete':
        case 'Completed': return 'status-completed';
        case 'Reschedule': return 'status-reschedule';
        default: return 'status-default';
    }
}

function getAppointmentCard(appointmentId) {
    const escapedId = (window.CSS && CSS.escape) ? CSS.escape(appointmentId) : appointmentId.replace(/"/g, '\\"');
    return document.querySelector(`.appointment-card[data-appointment-id="${escapedId}"]`);
}

function setCardStatus(card, statusText) {
    const badge = card ? card.querySelector('.js-status-badge') : null;
    if (!badge) return;
    badge.textContent = statusText;
    badge.className = `status-badge js-status-badge ${getStatusClass(statusText)}`;
}

function setCardSchedule(card, dateValue, timeValue) {
    const dateEl = card ? card.querySelector('.js-appointment-date') : null;
    const timeEl = card ? card.querySelector('.js-appointment-time') : null;
    if (dateEl) dateEl.textContent = dateValue;
    if (timeEl) timeEl.textContent = timeValue;
}

function setCardMessage(card, html) {
    const msg = card ? card.querySelector('.js-appointment-message') : null;
    if (msg) msg.innerHTML = html;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function setCardActionsForCancelled(card, appointmentId) {
    const actions = card ? card.querySelector('.js-appointment-actions') : null;
    if (!actions) return;

    const service = (card.querySelector('.js-appointment-service')?.textContent || 'N/A').trim();
    const date = (card.querySelector('.js-appointment-date')?.textContent || '').trim();
    const time = (card.querySelector('.js-appointment-time')?.textContent || '').trim();

    actions.innerHTML = `
        <button type="button"
            class="btn btn-primary"
            data-appointment-id="${escapeHtml(appointmentId)}"
            data-service="${escapeHtml(service)}"
            data-current-date="${escapeHtml(date)}"
            data-current-time="${escapeHtml(time)}"
            data-status="Cancelled"
            onclick="openRescheduleModal(this)">
            Reschedule
        </button>
    `;
}

function setCardActionsForPending(card, appointmentId) {
    const actions = card ? card.querySelector('.js-appointment-actions') : null;
    if (!actions) return;

    const isWalkIn = card.getAttribute('data-is-walkin') === '1';
    if (isWalkIn) return;

    const service = (card.querySelector('.js-appointment-service')?.textContent || 'N/A').trim();
    const date = (card.querySelector('.js-appointment-date')?.textContent || '').trim();
    const time = (card.querySelector('.js-appointment-time')?.textContent || '').trim();

    actions.innerHTML = `
        <button type="button"
            class="btn btn-danger"
            data-appointment-id="${escapeHtml(appointmentId)}"
            onclick="showCancelConfirmation(this)">
            Cancel
        </button>
        <button type="button"
            class="btn btn-primary"
            data-appointment-id="${escapeHtml(appointmentId)}"
            data-service="${escapeHtml(service)}"
            data-current-date="${escapeHtml(date)}"
            data-current-time="${escapeHtml(time)}"
            data-status="Pending"
            onclick="openRescheduleModal(this)">
            Reschedule
        </button>
    `;
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('confirmationModal');
    if (event.target === modal) {
        closeConfirmationModal();
    }
});
// ==================== END CONFIRMATION MODAL ====================

// ==================== CANCEL APPOINTMENT AJAX ====================
function cancelAppointment(appointmentId) {
    // Show loading state
    showNotification('info', 'Processing...', 'Please wait while we cancel your appointment.', '<i class="fas fa-spinner fa-spin"></i>', 2000);
    
    fetch(`../controllers/cancelAppointment.php?id=${encodeURIComponent(appointmentId)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(response => {
        // Check if response is HTML (redirect) or JSON
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
            return response.json();
        } else {
            // If it's HTML or redirect, assume success
            return response.text().then(() => ({ success: true }));
        }
    })
    .then(data => {
        if (data.success || !data.error) {
            showCancelledNotification(appointmentId);
            const card = getAppointmentCard(appointmentId);
            if (card) {
                setCardStatus(card, 'Cancelled');
                setCardMessage(card, '<p>Your appointment has been cancelled.</p>');
                setCardActionsForCancelled(card, appointmentId);
            }
        } else {
            showNotification('error', 'Error', data.message || data.error || 'Failed to cancel appointment. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Error', 'An error occurred while cancelling the appointment. Please try again.');
    });
}
// ==================== END CANCEL APPOINTMENT ====================

// ==================== REFUND REQUEST AJAX ====================
function requestRefund(button) {
    const appointmentId = button.getAttribute('data-appointment-id');
    const paymentId = button.getAttribute('data-payment-id');

    if (!appointmentId || !paymentId) {
        showNotification('error', 'Refund Error', 'Missing appointment or payment information for refund request.');
        return;
    }

    const formData = new FormData();
    formData.append('appointment_id', appointmentId);
    formData.append('payment_id', paymentId);

    showNotification('info', 'Submitting Refund Request', 'Please wait while we send your refund request to the clinic.', '<i class="fas fa-spinner fa-spin"></i>', 3000);

    fetch('../controllers/requestRefund.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch {
                return { success: false, message: text || 'Unexpected response from server.' };
            }
        });
    })
    .then(data => {
        if (data.success) {
            showNotification('success', 'Refund Request Sent', data.message || 'Your refund request has been sent to the clinic.');
            // disable button and give feedback
            button.disabled = true;
            button.textContent = 'Refund Requested';
            // reload page after short delay so eligibility and UI update
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('error', 'Refund Request Failed', data.message || 'Unable to submit your refund request. Please try again later.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Error', 'An error occurred while submitting your refund request. Please try again.');
    });
}
// ==================== END REFUND REQUEST AJAX ====================

</script>

<?php include_once('../layouts/footer.php'); ?>
</body>
</html>