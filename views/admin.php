<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    header("Location: admin_verify.php");
    exit();
}

// Dashboard data queries only
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dental Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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

        /* Calendar Animation */
        @keyframes calendarPop {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .calendar-animation {
            animation: calendarPop 0.5s ease-out;
        }

        /* Warning Pulse Animation */
        @keyframes warningPulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        .warning-animation {
            animation: warningPulse 0.8s ease-out 2;
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

        /* Progress Bar */
        .notification-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: #E5E7EB;
            width: 100%;
        }

        .notification-progress-bar {
            height: 100%;
            background: currentColor;
            animation: progressBar 5s linear forwards;
        }

        @keyframes progressBar {
            from {
                width: 100%;
            }
            to {
                width: 0%;
            }
        }

        /* Appointment Card Styles */
        .appointment-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 16px;
            transition: all 0.3s ease;
            border: 1px solid #E5E7EB;
            position: relative;
        }

        .appointment-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .appointment-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .appointment-patient-info {
            flex: 1;
        }

        .appointment-patient-name {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin: 0 0 6px 0;
        }

        .appointment-service {
            font-size: 14px;
            color: #6B7280;
            margin: 0;
        }

        .appointment-status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .appointment-status-badge.Scheduled {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .appointment-status-badge.Confirmed {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .appointment-status-badge.Pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .appointment-status-badge.Completed {
            background: #D1FAE5;
            color: #065F46;
        }

        .appointment-status-badge.Reschedule {
            background: #FEE2E2;
            color: #991B1B;
        }

        .appointment-status-badge.Cancelled {
            background: #F3F4F6;
            color: #6B7280;
        }

        .appointment-status-badge.No-show {
            background: #FEE2E2;
            color: #991B1B;
        }

        .appointment-card-body {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .appointment-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .appointment-info-value {
            font-size: 14px;
            color: #4B5563;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .appointment-info-value i {
            font-size: 16px;
            color: #9CA3AF;
            width: 20px;
        }

        .appointments-section {
            background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .appointments-section h2 {
            margin-top: 0;
            margin-bottom: 16px;
            color: #1F2937;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .appointments-section h2 i {
            color: #3B82F6;
            font-size: 20px;
        }

        .appointments-cards-grid {
            display: flex;
            flex-direction: column;
            gap: 0;
            max-width: 100%;
        }

        /* For today's appointments - vertical list */
        .today-appointments .appointments-cards-grid {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6B7280;
            background: white;
            border-radius: 16px;
            border: 2px dashed #E5E7EB;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.4;
            color: #9CA3AF;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }

        /* Responsive Sidebar Styles */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1002;
            background: white;
            color: var(--secondary-color);
            border: none;
            padding: 0;
            border-radius: 10px;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            width: 44px;
            height: 44px;
        }

        .menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .menu-toggle.active {
            opacity: 1;
        }

        .menu-toggle i {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-text {
            transition: opacity 0.3s ease;
        }

        /* Sidebar Overlay Backdrop */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                left: 20px;
                top: 20px;
            }

            .sidebar {
                width: 250px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-header {
                opacity: 1;
                max-height: 100px;
                padding: 0 18px 12px;
                margin-bottom: 50px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }

            .sidebar-divider {
                opacity: 1;
                max-height: 2px;
                margin: 8px 18px;
            }

            .sidebar-nav a,
            .sidebar-nav button {
                justify-content: flex-start;
                padding: 10px 18px;
            }

            .sidebar-nav a i,
            .sidebar-nav button i {
                margin-right: 10px;
            }

            .sidebar-text {
                display: inline;
                opacity: 1;
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            /* Show overlay when sidebar is active */
            .sidebar-overlay.active {
                display: block;
            }

            /* Adjust dashboard for mobile */
            .dashboard-stats {
                grid-template-columns: 1fr !important;
            }

            .appointments-container {
                flex-direction: column !important;
            }

            /* Hide table view, show card view on mobile */
            .appointments-table {
                display: none !important;
            }

            .appointments-section {
                display: block !important;
                
            }

            .appointment-card-body {
                grid-template-columns: 1fr;
            }

            .appointments-section {
                padding: 15px;
            }

            .notification-container {
                right: 10px;
                top: 60px;
                max-width: calc(100% - 20px);
            }
        }

        /* Desktop: Show cards, hide table */
        @media (min-width: 769px) {
            .appointments-table {
                display: none !important;
            }

            .appointments-section {
                display: block !important;
            }

            .appointment-card-body {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .appointments-section {
                padding: 25px;
            }

            /* Responsive grid for different desktop sizes */
            @media (min-width: 1200px) {
                .appointment-card-body {
                    grid-template-columns: repeat(3, 1fr);
                }
            }

            @media (min-width: 769px) and (max-width: 1199px) {
                .appointment-card-body {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        }

        @media (min-width: 769px) {
            .sidebar {
                transform: translateX(0) !important;
            }
        }

        /* Two-card Dashboard Stats (KPI + Chart) */
        .dashboard-two-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .dashboard-two-cards {
                grid-template-columns: 1fr;
            }
        }

        .modern-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .modern-card h2 {
            margin: 0 0 12px 0;
            font-size: 18px;
            color: #1F2937;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modern-card h2 i {
            color: #3B82F6;
            font-size: 20px;
        }

        /* Premium Health Analytics Card (Booked vs Slots) */
        .premium-analytics-card {
            padding: 26px;
            display: flex;
            flex-direction: column;
        }

        .premium-analytics-card .premium-analytics-title {
            padding-bottom: 16px;
            margin-bottom: 18px;
            position: relative;
        }

        .premium-analytics-card .premium-analytics-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 3px;
            border-radius: 999px;
            background: linear-gradient(90deg, #F59E0B 0%, #FBBF24 100%);
        }

        /* Donut chart centered area */
        .today-donut-only {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            min-height: 220px; /* leaves room for the header above */
        }

        .today-donut-wrap {
            position: relative;
            width: 210px;
            height: 210px;
        }

        .today-donut-wrap canvas {
            width: 210px !important;
            height: 210px !important;
        }

        .today-donut-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 85%;
            pointer-events: none;
        }

        .today-donut-center-booked {
            font-size: 34px;
            font-weight: 900;
            color: #111827;
            line-height: 1;
            margin-bottom: 2px;
        }

        .today-donut-center-label {
            font-size: 13px;
            font-weight: 800;
            color: #6B7280;
            margin-bottom: 6px;
        }

        .today-donut-center-range {
            font-size: 13px;
            font-weight: 900;
            color: #1E40AF;
            background: rgba(59, 130, 246, 0.08);
            padding: 6px 10px;
            border-radius: 999px;
            display: inline-block;
        }

        @media (max-width: 520px) {
            .today-donut-only {
                min-height: 200px;
            }

            .today-donut-wrap {
                width: 190px;
                height: 190px;
            }

            .today-donut-wrap canvas {
                width: 190px !important;
                height: 190px !important;
            }
        }

        .kpi-number {
            font-size: 46px;
            font-weight: 800;
            color: #1E40AF;
            line-height: 1.05;
            margin: 8px 0 8px 0;
        }

        .kpi-subtitle {
            color: #6B7280;
            font-size: 14px;
            font-weight: 600;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 260px;
            margin-top: 6px;
        }

        @media (max-width: 480px) {
            .chart-wrapper {
                height: 220px;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../assets/images/landerologo.png">
    </div>
    <nav class="sidebar-nav">
        <a href="#" class="active" onclick="showSection('dashboard', this)"><i class="fa fa-tachometer"></i> <span class="sidebar-text">Dashboard</span></a>
        <a href="../admin/appointment.php" onclick="return navigateToAppointments(event, this)"><i class="fas fa-calendar-check"></i> <span class="sidebar-text">Appointments</span></a>
        <a href="../admin/timeslot.php" onclick="return navigateToTimeSlot(event, this)"><i class="fas fa-calendar-days"></i> <span class="sidebar-text">Time Slots</span></a>
        <a href="../admin/services.php" onclick="return navigateToServices(event, this)"><i class="fa-solid fa-teeth"></i> <span class="sidebar-text">Services</span></a>
        <a href="../admin/patients.php" onclick="return navigateToPatients(event, this)"><i class="fa-solid fa-hospital-user"></i> <span class="sidebar-text">Patients</span></a>
        <a href="../admin/treatmenthistory.php" onclick="return navigateToTreatmentHistory(event, this)"><i class="fa-solid fa-notes-medical"></i> <span class="sidebar-text">History</span></a>
        <a href="../admin/staffs.php" onclick="return navigateToStaffs(event, this)"><i class="fa-solid fa-user-doctor"></i> <span class="sidebar-text">Dentists & Staff</span></a>
        <a href="../admin/transactions.php" onclick="return navigateToTransactions(event, this)"><i class="fa-solid fa-money-bill"></i> <span class="sidebar-text">Transactions</span></a> 
        <a href="../admin/reports.php" onclick="return navigateToReports(event, this)"><i class="fa-solid fa-square-poll-vertical"></i> <span class="sidebar-text">Reports</span></a> 
        <a href="../controllers/logout.php"><i class="fa-solid fa-right-from-bracket"></i> <span class="sidebar-text">Logout</span></a>
        <div class="sidebar-divider"></div>
        <button class="sidebar-btn-clinic-control" onclick="showControlsPopup()" title="Controls">
            <i class="fa-regular fa-circle-right"></i> <span class="sidebar-text">Others</span>
        </button>
    </nav>
</div>

<!-- Controls Popup Modal -->
<div id="controlsPopupModal" class="modal" style="display:none; z-index: 10001;">
    <div class="modal-content" style="max-width: 400px;">
        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-sliders-h"></i> Others
        </h3>
        <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
            <button class="control-option-btn" onclick="navigateToClinicControl()">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="control-icon" style="background: #f59e0b20; color: #f59e0b;">
                        <i class="fas fa-building"></i>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-weight: 600; font-size: 16px;">Clinic Control</div>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 3px;">Manage closures & holidays</div>
                    </div>
                </div>
            </button>

            <button class="control-option-btn" onclick="navigateToArchives()">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="control-icon" style="background: #3b82f620; color: #3b82f6;">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-weight: 600; font-size: 16px;">Archive</div>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 3px;">View archived appointments</div>
                    </div>
                </div>
            </button>
            
            <button class="control-option-btn" onclick="navigateToWalkinRecords()">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="control-icon" style="background: #10b98120; color: #10b981;">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-weight: 600; font-size: 16px;">Walk-in Records</div>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 3px;">View and manage walk-in records</div>
                    </div>
                </div>
            </button>
        </div>
    </div>
</div>

<?php
    // Get total number of appointments
    $appointmentCountQuery = "SELECT COUNT(*) AS total_appointments FROM appointments";
    $appointmentCountResult = mysqli_query($con, $appointmentCountQuery);
    $appointmentCount = mysqli_fetch_assoc($appointmentCountResult)['total_appointments'];

    // Get total number of services
    $servicesCountQuery = "SELECT COUNT(*) AS total_services FROM services";
    $servicesCountResult = mysqli_query($con, $servicesCountQuery);
    $servicesCount = mysqli_fetch_assoc($servicesCountResult)['total_services'];

    // Get number of active dentists
    $activeDentistQuery = "SELECT COUNT(*) AS active_dentists FROM multidisciplinary_dental_team WHERE status = 'active'";
    $activeDentistResult = mysqli_query($con, $activeDentistQuery);
    $activeDentists = mysqli_fetch_assoc($activeDentistResult)['active_dentists'];


    // Get today's appointments
    $todaysAppointmentsQuery = "SELECT a.appointment_id, p.first_name, p.last_name, s.sub_service,
                                       d.first_name as dentist_first, d.last_name as dentist_last,
                                       a.appointment_date, a.appointment_time, a.status
                                FROM appointments a
                                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                                LEFT JOIN services s ON a.service_id = s.service_id
                                LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                                WHERE a.appointment_date = CURDATE() AND a.status != 'Cancelled' 
                                ORDER BY a.appointment_time ASC";
    $todaysAppointmentsResult = mysqli_query($con, $todaysAppointmentsQuery);
    $todaysAppointmentsCount = mysqli_num_rows($todaysAppointmentsResult);

    // Premium analytics: Donut-only card (total time slots per day is fixed at 11)
    $todayBookedAppointmentsCount = (int)$todaysAppointmentsCount; // X (booked appointments today)
    $todayTotalSlotsCount = 11; // Y (fixed)
    $todayFillPercent = 0; // (X / 11) * 100
    if ($todayTotalSlotsCount > 0) {
        $todayFillPercent = (int)round(($todayBookedAppointmentsCount / $todayTotalSlotsCount) * 100);
        $todayFillPercent = (int)max(0, min(100, $todayFillPercent));
    }
    
    // Store results in array for reuse
    $todaysAppointmentsData = [];
    while ($row = mysqli_fetch_assoc($todaysAppointmentsResult)) {
        $todaysAppointmentsData[] = $row;
    }

    // Upcoming Appointments
    $upcomingAppointmentsQuery = "SELECT a.appointment_id, p.first_name, p.last_name, 
                                         a.appointment_date, a.appointment_time
                                  FROM appointments a
                                  LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                                  WHERE a.appointment_date > CURDATE() AND a.status != 'Cancelled' 
                                  ORDER BY a.appointment_date ASC, a.appointment_time ASC 
                                  LIMIT 5";
    $upcomingAppointmentsResult = mysqli_query($con, $upcomingAppointmentsQuery);
    $upcomingAppointmentsCount = mysqli_num_rows($upcomingAppointmentsResult);
    
    // Store results in array for reuse
    $upcomingAppointmentsData = [];
    while ($row = mysqli_fetch_assoc($upcomingAppointmentsResult)) {
        $upcomingAppointmentsData[] = $row;
    }

    // Get unread system alerts for popup notifications
    $admin_user_id = $_SESSION['userID'];
    $systemAlertsQuery = "SELECT alert_id, title, message, related_appointment_id, is_read, created_at 
                          FROM system_alerts 
                          WHERE user_id = ? AND role = 'admin' AND is_read = 0 
                          ORDER BY created_at DESC 
                          LIMIT 5";
    $stmt = $con->prepare($systemAlertsQuery);
    $stmt->bind_param("s", $admin_user_id);
    $stmt->execute();
    $systemAlertsResult = $stmt->get_result();
    
    $systemAlerts = [];
    while ($row = mysqli_fetch_assoc($systemAlertsResult)) {
        // Filter out "Dentist Logged Out - Appointment Alert" alerts
        if (stripos($row['title'], 'Dentist Logged Out') === false) {
            $systemAlerts[] = $row;
        }
    }
    $stmt->close();

    // Appointments per month for the current year (Jan-Dec)
    $currentYear = (int)date('Y');
    $monthlyTotals = array_fill(0, 12, 0);
    $startDate = $currentYear . '-01-01';
    $endDate = ($currentYear + 1) . '-01-01';

    $monthlyAppointmentsQuery = "SELECT MONTH(a.appointment_date) AS month_num, COUNT(*) AS total
                                  FROM appointments a
                                  WHERE a.appointment_date >= ? AND a.appointment_date < ?
                                  AND a.status != 'Cancelled'
                                  GROUP BY MONTH(a.appointment_date)";
    $stmt = $con->prepare($monthlyAppointmentsQuery);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $monthlyAppointmentsResult = $stmt->get_result();
    while ($row = mysqli_fetch_assoc($monthlyAppointmentsResult)) {
        $m = (int)$row['month_num'];
        if ($m >= 1 && $m <= 12) {
            $monthlyTotals[$m - 1] = (int)$row['total'];
        }
    }
    $stmt->close();
?>

<div class="main-content" id="dashboard">
    <h1>Dashboard Overview</h1>
    <p>Welcome Admin!</p>

    <!-- Stats Section -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <i class="fas fa-calendar-check fa-2x"></i>
            <div class="stat-info">
                <h3><?php echo $appointmentCount; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>

        <div class="stat-card">
            <i class="fas fa-user-md fa-2x"></i>
            <div class="stat-info">
                <h3><?php echo $activeDentists; ?></h3>
                <p>Active Dentists</p>
            </div>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-teeth"></i>
            <div class="stat-info">
                <h3><?php echo $servicesCount; ?></h3>
                <p>Total Services</p>
            </div>
        </div>
    </div>

    <!-- Appointments Side-by-Side Layout -->
    <div class="appointments-container" style="display: flex; flex-wrap: wrap; gap: 20px;">
        <!-- Today's Appointments Section -->
        <div class="today-appointments">
            <div class="appointments-section">
                <h2><i class="fas fa-calendar-day"></i> Today's Appointments</h2>
                <?php 
                    $todaysLimited = array_slice($todaysAppointmentsData, 0, 3);
                    if (count($todaysLimited) > 0) { 
                ?>
                    <div class="appointments-cards-grid">
                        <?php foreach ($todaysLimited as $row) { 
                            $patientName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                            $service = htmlspecialchars($row['sub_service'] ?? 'General Service');
                            $time = htmlspecialchars($row['appointment_time']);
                            $dentistName = htmlspecialchars(($row['dentist_first'] ?? '') . ' ' . ($row['dentist_last'] ?? ''));
                            $status = htmlspecialchars($row['status'] ?? 'Pending');
                        ?>
                            <div class="appointment-card">
                                <div class="appointment-card-header">
                                    <div class="appointment-patient-info">
                                        <h3 class="appointment-patient-name"><?php echo $patientName; ?></h3>
                                        <p class="appointment-service"><?php echo $service; ?></p>
                                    </div>
                                    <span class="appointment-status-badge <?php echo $status; ?>"><?php echo $status; ?></span>
                                </div>
                                <div class="appointment-card-body">
                                    <div class="appointment-info-item">
                                        <span class="appointment-info-value">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $time; ?>
                                        </span>
                                    </div>
                                    <div class="appointment-info-item">
                                        <span class="appointment-info-value">
                                            <i class="fas fa-user-doctor"></i>
                                            <?php echo !empty($dentistName) ? 'Dr. ' . trim($dentistName) : 'Not Assigned'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No appointments scheduled for today.</p>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="upcoming-appointments">
            <div class="appointments-section">
                <h2><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h2>
                <?php 
                    $upcomingLimited = array_slice($upcomingAppointmentsData, 0, 3);
                    if (count($upcomingLimited) > 0) { 
                ?>
                    <div class="appointments-cards-grid">
                        <?php foreach ($upcomingLimited as $row) { ?>
                            <div class="appointment-card">
                                <div class="appointment-card-header">
                                    <div class="appointment-time">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y', strtotime($row['appointment_date'])); ?>
                                    </div>
                                </div>
                                <div class="appointment-card-body">
                                    <div class="appointment-info-item">
                                        <span class="appointment-info-value">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($row['appointment_time']); ?>
                                        </span>
                                    </div>
                                    <div class="appointment-info-item">
                                        <span class="appointment-info-value">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No upcoming appointments.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Dashboard Two-Card Stats -->
    <div class="dashboard-two-cards">
        <div class="modern-card premium-analytics-card">
            <h2 class="premium-analytics-title">
                <i class="fas fa-calendar-day" aria-hidden="true"></i>
                Today’s Appointments
            </h2>
            <div class="today-donut-only">
                <div class="today-donut-wrap">
                    <canvas id="todaySlotsDonutChart"></canvas>
                    <div class="today-donut-center">
                        <div class="today-donut-center-booked"><?php echo (int)$todayBookedAppointmentsCount; ?></div>
                        <div class="today-donut-center-label">Booked</div>
                        <div class="today-donut-center-range">
                            <?php echo (int)$todayBookedAppointmentsCount; ?> / <?php echo (int)$todayTotalSlotsCount; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modern-card">
            <h2><i class="fas fa-chart-line"></i> Appointments Per Month</h2>
            <div class="chart-wrapper">
                <canvas id="appointmentsPerMonthChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // ==================== RESPONSIVE SIDEBAR ====================
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const menuToggle = document.querySelector(".menu-toggle");
        const overlay = document.getElementById("sidebarOverlay");
        
        sidebar.classList.toggle("active");
        menuToggle.classList.toggle("active");
        
        if (window.innerWidth <= 768) {
            if (overlay) {
                overlay.classList.toggle("active");
            }
        }
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById("sidebar");
        const menuToggle = document.querySelector(".menu-toggle");
        const overlay = document.getElementById("sidebarOverlay");
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);
        
        if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !isClickInsideSidebar && !isClickOnToggle) {
            sidebar.classList.remove('active');
            menuToggle.classList.remove('active');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }
    });

    // ==================== SECTION NAVIGATION ====================
    function showSection(sectionId, clickedElement) {
        const sections = document.querySelectorAll('.main-content');
        sections.forEach(sec => sec.style.display = 'none');

        const sectionToShow = document.getElementById(sectionId);
        if (sectionToShow) sectionToShow.style.display = 'block';

        const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
        sidebarLinks.forEach(link => link.classList.remove('active'));

        if (clickedElement) {
            clickedElement.classList.add('active');
        }

        // Close sidebar on mobile after selection
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById("sidebar");
            const menuToggle = document.querySelector(".menu-toggle");
            sidebar.classList.remove('active');
            menuToggle.classList.remove('active');
        }
    }

    // ==================== CONTROLS POPUP ====================
    function showControlsPopup() {
        document.getElementById('controlsPopupModal').style.display = 'block';
    }
    
    function closeControlsPopup() {
        document.getElementById('controlsPopupModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('controlsPopupModal');
        if (event.target === modal) {
            closeControlsPopup();
        }
    });
    
    // ==================== NAVIGATION FUNCTIONS ====================
    function navigateToClinicControl() {
        closeControlsPopup();
        const mainContent = document.querySelector('.main-content');
        const clinicControlBtn = document.querySelector('.sidebar-btn-clinic-control');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (clinicControlBtn) {
            clinicControlBtn.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = 'clinicControl.php';
        }, 300);
    }
    
    function navigateToUserControl() {
        closeControlsPopup();
        const mainContent = document.querySelector('.main-content');
        const clinicControlBtn = document.querySelector('.sidebar-btn-clinic-control');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (clinicControlBtn) {
            clinicControlBtn.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../views/userControl.php';
        }, 300);
    }
    
    function navigateToWalkinRecords() {
        closeControlsPopup();
        const mainContent = document.querySelector('.main-content');
        const clinicControlBtn = document.querySelector('.sidebar-btn-clinic-control');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (clinicControlBtn) {
            clinicControlBtn.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/walkinRecords.php';
        }, 300);
    }

    function navigateToArchives() {
        closeControlsPopup();
        const mainContent = document.querySelector('.main-content');
        const clinicControlBtn = document.querySelector('.sidebar-btn-clinic-control');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (clinicControlBtn) {
            clinicControlBtn.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../views/archives.php';
        }, 300);
    }

    // ==================== NAVIGATION FUNCTIONS FOR ADMIN PAGES ====================
    function navigateToAppointments(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/appointment.php';
        }, 300);
        
        return false;
    }

    function navigateToTimeSlot(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/timeslot.php';
        }, 300);
        
        return false;
    }

    function navigateToServices(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/services.php';
        }, 300);
        
        return false;
    }

    function navigateToPatients(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/patients.php';
        }, 300);
        
        return false;
    }

    function navigateToTreatmentHistory(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/treatmenthistory.php';
        }, 300);
        
        return false;
    }

    function navigateToStaffs(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/staffs.php';
        }, 300);
        
        return false;
    }

    function navigateToTransactions(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/transactions.php';
        }, 300);
        
        return false;
    }

    function navigateToReports(event, element) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        
        if (element) {
            element.style.transform = 'scale(0.95)';
        }
        
        setTimeout(() => {
            window.location.href = '../admin/reports.php';
        }, 300);
        
        return false;
    }

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
                    iconHTML = '<i class="fas fa-check"></i>';
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
            <div class="notification-icon ${type === 'success' ? 'success-scale-animation' : ''} ${type === 'warning' ? 'warning-animation' : ''} ${type === 'info' ? 'calendar-animation' : ''}">
                ${iconHTML}
            </div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">&times;</button>
            <div class="notification-progress">
                <div class="notification-progress-bar" style="color: ${getNotificationColor(type)}"></div>
            </div>
        `;
        
        container.appendChild(notification);
        
        // Auto remove after duration
        setTimeout(() => {
            closeNotification(notification.querySelector('.notification-close'));
        }, duration);
    }
    
    function getNotificationColor(type) {
        const colors = {
            'success': '#10B981',
            'warning': '#F59E0B',
            'error': '#EF4444',
            'info': '#3B82F6'
        };
        return colors[type] || colors.info;
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

    // ==================== SYSTEM ALERTS - POPUP NOTIFICATIONS ====================
    // Show system alerts as popup notifications on page load
    <?php if (!empty($systemAlerts)) { ?>
        $(document).ready(function() {
            // Show each alert as a popup notification with delay
            const alerts = <?php echo json_encode($systemAlerts); ?>;
            alerts.forEach(function(alert, index) {
                setTimeout(function() {
                    showNotification(
                        'warning',
                        alert.title,
                        alert.message,
                        '<i class="fas fa-exclamation-triangle"></i>',
                        8000 // Show for 8 seconds
                    );
                }, index * 1000); // Stagger notifications by 1 second
            });
        });
    <?php } ?>

    // ==================== DASHBOARD CHARTS ====================
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;

        // Appointments Per Month (Jan-Dec)
        const monthCanvas = document.getElementById('appointmentsPerMonthChart');
        if (monthCanvas) {
            const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const monthlyAppointments = <?php echo json_encode(array_values($monthlyTotals)); ?>;

            const ctx = monthCanvas.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Appointments',
                        data: monthlyAppointments,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.12)',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#ffffff',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return ` ${context.parsed.y} appointments`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#6B7280',
                                maxRotation: 0,
                                autoSkip: true
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#6B7280',
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(107, 114, 128, 0.15)'
                            }
                        }
                    }
                }
            });
        }

        // Today's Slots Donut (Booked vs Total Available slots for the day)
        const donutCanvas = document.getElementById('todaySlotsDonutChart');
        if (donutCanvas) {
            const booked = <?php echo (int)$todayBookedAppointmentsCount; ?>;
            const totalSlots = <?php echo (int)$todayTotalSlotsCount; ?>;

            const denom = totalSlots > 0 ? totalSlots : 1; // keep chart stable when there are no slots
            const bookedCapped = Math.max(0, Math.min(booked, denom));
            const remaining = Math.max(0, denom - bookedCapped);
            const fillPercent = denom > 0 ? Math.round((bookedCapped / denom) * 100) : 0;

            const donutCtx = donutCanvas.getContext('2d');
            new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [bookedCapped, remaining],
                        backgroundColor: [
                            '#3B82F6',
                            '#E5E7EB'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    rotation: -90,
                    circumference: 360,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const value = context.parsed;
                                    if (context.dataIndex === 0) {
                                        return ` Booked: ${value} / ${denom} (${fillPercent}%)`;
                                    }
                                    return ` Available: ${value} slots`;
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    // ==================== AUTOMATIC INACTIVE DENTIST CHECK ====================
    // Automatically check for inactive dentists with today's appointments when admin dashboard loads
    // This will send email notifications instead of creating alerts
    function autoCheckInactiveDentists() {
        // Run check silently in the background to send email notifications
        $.ajax({
            url: '../controllers/checkDentistInactivity.php',
            method: 'GET',
            success: function(response) {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success && result.emails_sent > 0) {
                    // Emails have been sent, no need to reload page
                    console.log('Email notifications sent for inactive dentists');
                }
            },
            error: function(xhr, status, error) {
                // Silently fail - don't show error to user
                console.error('Auto-check inactive dentists failed:', error);
            }
        });
    }

    // Run automatic check when page loads
    $(document).ready(function() {
        // Wait a moment for page to fully load, then check for inactive dentists
        setTimeout(function() {
            autoCheckInactiveDentists();
        }, 1000);
    });
</script>
</body>
</html>
