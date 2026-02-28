<?php
session_start();
include_once("../database/config.php");
define("TITLE", "All Appointments");
include_once('../layouts/header.php');

// ✅ Check if user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userID'];

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

// ✅ Fetch all appointments (if patient exists)
$all_appointments = [];
if (!empty($user['patient_id'])) {
    // Check if ticket_code column exists
    $colCheck = mysqli_query($con, "SHOW COLUMNS FROM appointments LIKE 'ticket_code'");
    $hasTicketCols = ($colCheck && mysqli_num_rows($colCheck) > 0);
    
    // Query for all appointments with payment information and ticket_code
    $ticketField = $hasTicketCols ? ", a.ticket_code" : "";
    $appt_query = $con->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, 
                s.sub_service ,s.service_category, a.status, a.created_at,
               p.method as payment_method, p.status as payment_status" . $ticketField . ",
               'appointment' as appointment_type
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.service_id
        LEFT JOIN payment p ON a.appointment_id = p.appointment_id
        WHERE a.patient_id = ?
    ");
    $appt_query->bind_param("s", $user['patient_id']);
    $appt_query->execute();
    $appt_result = $appt_query->get_result();
    while ($row = $appt_result->fetch_assoc()) {
        $all_appointments[] = $row;
    }
    
    // ✅ Fetch walk-in appointments for the same patient
    $walkin_query = $con->prepare("
        SELECT walkin_id as appointment_id, 
               NULL as appointment_date, 
               NULL as appointment_time,
               sub_service,
               service as service_category,
               status,
               created_at,
               'Cash' as payment_method,
               NULL as payment_status,
               'walkin' as appointment_type,
               dentist_name,
               branch
        FROM walkin_appointments
        WHERE patient_id = ?
    ");
    $walkin_query->bind_param("s", $user['patient_id']);
    $walkin_query->execute();
    $walkin_result = $walkin_query->get_result();
    while ($row = $walkin_result->fetch_assoc()) {
        // Add walk-in specific fields
        $row['walkin_id'] = $row['appointment_id'];
        $row['dentist_name'] = $row['dentist_name'] ?? 'N/A';
        $row['branch'] = $row['branch'] ?? 'N/A';
        $all_appointments[] = $row;
    }
    $walkin_query->close();
    
    // Sort all appointments together
    usort($all_appointments, function($a, $b) {
        // First, sort by status (active appointments first)
        $aStatusPriority = in_array($a['status'], ['Cancelled', 'Complete', 'Completed', 'No-show']) ? 1 : 0;
        $bStatusPriority = in_array($b['status'], ['Cancelled', 'Complete', 'Completed', 'No-show']) ? 1 : 0;
        
        if ($aStatusPriority !== $bStatusPriority) {
            return $aStatusPriority <=> $bStatusPriority;
        }
        
        // Then by created_at (newest first)
        $aCreated = strtotime($a['created_at']);
        $bCreated = strtotime($b['created_at']);
        if ($aCreated !== $bCreated) {
            return $bCreated <=> $aCreated;
        }
        
        // Then by appointment_date if available
        if (!empty($a['appointment_date']) && !empty($b['appointment_date'])) {
            return strtotime($b['appointment_date']) <=> strtotime($a['appointment_date']);
        }
        
        return 0;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Appointments</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/accountstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary-color, #48A6A7);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .back-button:hover {
            background: var(--secondary-color, #264653);
            transform: translateX(-3px);
        }
        .appointments-full-width {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
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
        
        /* Walk-in Status Badge */
        .status-walkin {
            background-color: #E9D5FF;
            color: #6B21A8;
            border: 1px solid #8B5CF6;
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

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
                <a href="account.php" class="back-button">
                    <span>←</span> Back to Account
                </a>
                <h1>All Your Appointments</h1>
                <p>View and manage all your appointment history</p>
            </div>
            <div class="header-actions">
                <a href="../controllers/logout.php" class="btn btn-secondary logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="appointments-full-width">
        <div class="card">
            <h2 class="card-title">Appointment History</h2>
            <p class="card-subtitle">Total Appointments: <?= count($all_appointments); ?></p>

            <?php if (!empty($all_appointments)): ?>
                <?php foreach ($all_appointments as $appointment): ?>
                    <?php
                    $status = $appointment['status'];
                    $payment_method = $appointment['payment_method'] ?? null;
                    $payment_status = $appointment['payment_status'] ?? null;
                    $appointment_type = $appointment['appointment_type'] ?? 'appointment';
                    $isWalkin = ($appointment_type === 'walkin');
                    
                    // Check if cash payment and not paid yet
                    $isCashUnpaid = ($payment_method == 'Cash' && (strtolower($payment_status) == 'pending' || $payment_status == null));
                    
                    // Calculate deadline - check if appointment is tomorrow
                    $appointment_date = $appointment['appointment_date'];
                    $deadline_date = null;
                    $deadline_formatted = '';
                    $isTomorrow = false;
                    $today = new DateTime('today');
                    
                    if ($isCashUnpaid && $appointment_date) {
                        $appointmentDateObj = new DateTime($appointment_date);
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
                    
                    $statusClass = match($status) {
                        'Pending' => 'status-pending',
                        'Confirmed' => 'status-confirmed',
                        'Cancelled' => 'status-cancelled',
                        'Complete' => 'status-completed',
                        'Completed' => 'status-completed',
                        'Reschedule' => 'status-reschedule',
                        'Walk-in' => 'status-walkin',
                        default => 'status-default'
                    };
                    
                    // Determine if buttons should be disabled
                    // Walk-ins have different rules - they can't be rescheduled/cancelled like regular appointments
                    $buttonsDisabled = ($isWalkin || $status == 'Cancelled' || $status == 'Complete' || $status == 'Completed' || $isCashUnpaid);
                    
                    // Print Receipt button is only enabled when status is "Confirmed" and not walk-in
                    $printReceiptDisabled = ($status != 'Confirmed' || $isWalkin);
                    
                    // Feature 1: "Day Before" Logic - Calculate if appointment is 1 day before
                    // Only applies to regular appointments with dates
                    $ticket_code = $appointment['ticket_code'] ?? null;
                    $isDayBefore = false;
                    if (!$isWalkin && $appointment_date) {
                        $appointmentDateObj = new DateTime($appointment_date);
                        $today = new DateTime('today');
                        $tomorrow = clone $today;
                        $tomorrow->modify('+1 day');
                        $isDayBefore = ($appointmentDateObj->format('Y-m-d') === $tomorrow->format('Y-m-d'));
                    }
                    
                    // Show confirmation buttons ONLY if:
                    // 1. Status is Pending or Confirmed
                    // 2. It's exactly 1 day before the appointment
                    // 3. Appointment is not cancelled, completed, etc.
                    // 4. Not a walk-in
                    $showConfirmButtons = (!$isWalkin && $isDayBefore && 
                                         ($status == 'Pending' || $status == 'Confirmed') && 
                                         $status != 'Cancelled' && 
                                         $status != 'Complete' && 
                                         $status != 'Completed');
                    ?>
                    <div class="appointment-card" style="margin-bottom: 20px; <?= $isWalkin ? 'border-left: 4px solid #8B5CF6;' : ''; ?>">
                        <div class="appointment-header">
                            <h3>
                                <?= $isWalkin ? 'Walk-in #' : 'Appointment #'; ?><?= htmlspecialchars($appointment['appointment_id']); ?>
                                <?php if ($isWalkin): ?>
                                    <span style="background: #8B5CF6; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-left: 10px;">Walk-in</span>
                                <?php endif; ?>
                            </h3>
                            <span class="status-badge <?= $statusClass; ?>"><?= htmlspecialchars($status); ?></span>
                        </div>
                        
                        <div class="appointment-details">
                            <?php if (!$isWalkin): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Date</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment['appointment_date'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Time</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment['appointment_time'] ?? 'N/A'); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="detail-item">
                                    <span class="detail-label">Created</span>
                                    <span class="detail-value"><?= date('F j, Y', strtotime($appointment['created_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <span class="detail-label">Service</span>
                                <span class="detail-value"><?= htmlspecialchars($appointment['sub_service'] ?? $appointment['service_category'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Dentist</span>
                                <span class="detail-value"><?= $isWalkin ? htmlspecialchars($appointment['dentist_name'] ?? 'N/A') : 'Dr. Michelle Landero'; ?></span>
                            </div>
                            <?php if ($isWalkin && !empty($appointment['branch'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Branch</span>
                                    <span class="detail-value"><?= htmlspecialchars($appointment['branch']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="appointment-message">
                            <?php
                            if ($isWalkin) {
                                if ($status == "Walk-in") {
                                    echo "<p>This is a walk-in appointment. Please visit the clinic during operating hours.</p>";
                                } elseif ($status == "Complete" || $status == "Completed") {
                                    echo "<p>Your walk-in appointment has been completed.</p>";
                                } else {
                                    echo "<p>Walk-in appointment status: " . htmlspecialchars($status) . "</p>";
                                }
                            } elseif ($isCashUnpaid) {
                                // Show cash payment notice
                                echo "<div style='background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 15px 0;'>";
                                echo "<p style='color: #856404; margin: 0; line-height: 1.6;'><strong>⚠️ Appointment Slot Reserved!</strong></p>";
                                
                                if ($isTomorrow) {
                                    // For tomorrow appointments, require immediate payment
                                    echo "<p style='color: #856404; margin: 10px 0 0 0; line-height: 1.6;'><strong>IMPORTANT: Your appointment is tomorrow (" . date('F j, Y', strtotime($appointment_date)) . ")!</strong></p>";
                                    echo "<p style='color: #856404; margin: 5px 0 0 0; line-height: 1.6;'><strong>You must pay TODAY (" . $deadline_formatted . ") at the branch, otherwise your reservation will be cancelled.</strong></p>";
                                } else {
                                    // For other appointments, maintain 2-day deadline
                                    echo "<p style='color: #856404; margin: 10px 0 0 0; line-height: 1.6;'>Please pay at least 2 days before your appointment date (" . date('F j, Y', strtotime($appointment_date)) . ") at the branch.</p>";
                                    echo "<p style='color: #856404; margin: 5px 0 0 0; line-height: 1.6;'><strong>Payment deadline: " . $deadline_formatted . "</strong></p>";
                                }
                                
                                echo "<p style='color: #856404; margin: 5px 0 0 0; line-height: 1.6;'>Your slot will be cancelled if payment is not received on time.</p>";
                                echo "<p style='color: #856404; margin: 5px 0 0 0; line-height: 1.6;'>Appointment ID: " . htmlspecialchars($appointment['appointment_id']) . " (Status: Pending - Cash Payment Required)</p>";
                                echo "</div>";
                            } elseif ($status == "Pending") {
                                echo "<p>Your appointment has been scheduled. Please wait for confirmation.</p>";
                            } elseif ($status == "Confirmed") {
                                echo "<p>Your appointment has been confirmed.</p>";
                            } elseif ($status == "Complete" || $status == "Completed") {
                                echo "<p>Your appointment has been completed.</p>";
                            } elseif ($status == "Cancelled") {
                                echo "<p>Your appointment has been cancelled.</p>";
                            } elseif ($status == "Reschedule") {
                                echo "<p>Your appointment has been rescheduled. Please wait for confirmation.</p>";
                            }
                            ?>
                        </div>
                        
                        <!-- Feature 1: "Day Before" Confirmation Buttons -->
                        <?php if ($showConfirmButtons): ?>
                            <div class="day-before-actions" style="margin: 20px 0; padding: 20px; background: #e3f2fd; border-radius: 8px; border: 2px solid #2196f3;">
                                <p style="margin: 0 0 15px 0; font-weight: 600; color: #1976d2; text-align: center;">
                                    ⏰ Your appointment is tomorrow! Please confirm your attendance.
                                </p>
                                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                                    <button type="button" 
                                       class="btn btn-success confirm-appointment-btn"
                                       data-appointment-id="<?= htmlspecialchars($appointment['appointment_id']); ?>"
                                       onclick="confirmAppointment('<?= htmlspecialchars($appointment['appointment_id']); ?>')"
                                       style="background: #4caf50; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                        ✓ Confirm Appointment
                                    </button>
                                    <button type="button" 
                                       class="btn btn-danger not-coming-btn"
                                       data-appointment-id="<?= htmlspecialchars($appointment['appointment_id']); ?>"
                                       onclick="notComingAppointment('<?= htmlspecialchars($appointment['appointment_id']); ?>')"
                                       style="background: #f44336; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                        ✗ Not Coming
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="appointment-actions">
                            <?php if ($isWalkin): ?>
                                <!-- Walk-in specific actions - only allow printing receipt when status is Completed/Complete -->
                                <?php 
                                $walkinCanPrint = ($status == 'Complete' || $status == 'Completed');
                                ?>
                                <?php if (!empty($appointment['walkin_id']) && $walkinCanPrint): ?>
                                    <a href="../controllers/printWalkInReceipt.php?id=<?= htmlspecialchars($appointment['walkin_id']); ?>" 
                                       class="btn btn-secondary"
                                       target="_blank">
                                        Print Receipt
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($status == 'Cancelled'): ?>
                                <!-- Only show Reschedule button for Cancelled appointments -->
                                <a href="reschedule.php?id=<?= $appointment['appointment_id']; ?>" 
                                   class="btn btn-primary">
                                    Reschedule
                                </a>
                            <?php else: ?>
                                <!-- Show all buttons for non-cancelled appointments -->
                                <a href="../controllers/printAppointmentReceipt.php?id=<?= $appointment['appointment_id']; ?>" 
                                   class="btn btn-secondary <?= $printReceiptDisabled ? 'disabled' : ''; ?>"
                                   target="_blank"
                                   <?= $printReceiptDisabled ? 'onclick="return false;" style="opacity: 0.5; cursor: not-allowed; pointer-events: none;"' : ''; ?>>
                                    Print Receipt
                                </a>

                                <button type="button" 
                                   class="btn btn-danger <?= $buttonsDisabled ? 'disabled' : ''; ?>"
                                   data-appointment-id="<?= htmlspecialchars($appointment['appointment_id']); ?>"
                                   <?= $buttonsDisabled ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : 'onclick="showCancelConfirmation(this)"'; ?>>
                                    Cancel
                                </button>

                                <a href="reschedule.php?id=<?= $appointment['appointment_id']; ?>" 
                                   class="btn btn-primary <?= $buttonsDisabled ? 'disabled' : ''; ?>"
                                   <?= $buttonsDisabled ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                                    Reschedule
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-appointment">
                    <div class="no-appointment-icon">📅</div>
                    <h3>No Appointments Found</h3>
                    <p>You don't have any appointments scheduled yet. Book your next dental visit to maintain your oral health.</p>
                    <a href="index.php" class="btn btn-primary">Book an Appointment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
    
    fetch(`../controllers/cancelAppointment.php?id=${appointmentId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
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
            // Reload page after 2 seconds to show updated appointment
            setTimeout(() => {
                location.reload();
            }, 2000);
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

// ==================== FEATURE 1: DAY BEFORE CONFIRMATION ====================
function confirmAppointment(appointmentId) {
    if (!confirm('Are you sure you want to confirm this appointment? You will be expected to attend.')) {
        return;
    }
    
    showNotification('info', 'Processing...', 'Please wait while we confirm your appointment.', '<i class="fas fa-spinner fa-spin"></i>', 2000);
    
    fetch('../controllers/confirmAppointmentStatus.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'appointment_id=' + encodeURIComponent(appointmentId) + '&action=confirm'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Appointment Confirmed!', 'Your appointment has been confirmed. We look forward to seeing you.', null, 5000);
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('error', 'Error', data.message || 'Failed to confirm appointment. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Error', 'An error occurred while confirming the appointment. Please try again.');
    });
}

function notComingAppointment(appointmentId) {
    if (!confirm('Are you sure you will not be coming to this appointment? This will cancel your appointment.')) {
        return;
    }
    
    showNotification('info', 'Processing...', 'Please wait while we update your appointment.', '<i class="fas fa-spinner fa-spin"></i>', 2000);
    
    fetch('../controllers/confirmAppointmentStatus.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'appointment_id=' + encodeURIComponent(appointmentId) + '&action=cancel'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Appointment Cancelled', 'Your appointment has been cancelled as you indicated you will not be coming.', null, 5000);
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('error', 'Error', data.message || 'Failed to update appointment. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Error', 'An error occurred while updating the appointment. Please try again.');
    });
}
// ==================== END FEATURE 1 ====================
</script>

<?php include_once('../layouts/footer.php'); ?>
</body>
</html>

