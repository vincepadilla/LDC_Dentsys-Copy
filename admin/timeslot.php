<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../views/login.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Slot Scheduling Control - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="timeslotDesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        /* Page Fade-In Animation */
        body {
            animation: pageFadeIn 0.5s ease-in-out;
        }

        @keyframes pageFadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
    </style>
    <style>
        /* Premium styling for Blocked Time Slots section (UI only) */
        .blocked-slots-section {
            margin-top: 12px;
            padding: 18px;
            background: #ffffff;
            border: 1px solid #e7eef6;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(16, 24, 40, 0.05);
        }

        .blocked-slots-section > h3 {
            margin: 0 0 14px 0;
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #blockedSlotsTable {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            border: none;
        }

        #blockedSlotsTable,
        #blockedSlotsTable th,
        #blockedSlotsTable td {
            border: none !important; /* remove all visible table borders/lines */
        }

        #blockedSlotsTable thead th {
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            color: #ffffff;
            text-align: left;
            font-weight: 900;
            padding: 10px 14px;
            font-size: 12.5px;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        #blockedSlotsTable tbody td {
            padding: 10px 14px;
            font-size: 14px;
            color: #111827;
            vertical-align: middle;
        }

        #blockedSlotsTable tbody tr {
            transition: background 0.15s ease, transform 0.15s ease;
            line-height: 1.2;
        }

        /* Zebra rows for a cleaner, softer look */
        #blockedSlotsTable tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        #blockedSlotsTable tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        #blockedSlotsTable tbody tr:hover {
            background: rgba(13, 148, 136, 0.08) !important;
        }

        /* Compact no-data row */
        #blockedSlotsTable td.no-data {
            padding: 14px 14px;
            color: #6b7280;
        }

        /* Premium Actions button style (table only) */
        #blockedSlotsTable .action-btn.btn-danger {
            width: 38px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: #ffffff;
            border: none;
            box-shadow: 0 10px 24px rgba(239, 68, 68, 0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }

        #blockedSlotsTable .action-btn.btn-danger i {
            font-size: 13px;
        }

        #blockedSlotsTable .action-btn.btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 32px rgba(239, 68, 68, 0.32);
            filter: brightness(1.05);
        }

        #blockedSlotsTable .action-btn.btn-danger:active {
            transform: translateY(0);
            box-shadow: 0 10px 24px rgba(239, 68, 68, 0.25);
        }

        #blockedSlotsTable .action-btn.btn-danger:focus-visible {
            outline: 3px solid rgba(13, 148, 136, 0.35);
            outline-offset: 2px;
        }

        /* Pagination UI for blocked slots table */
        .blocked-slots-pagination {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
        }

        .blocked-slots-pagination .page-btn {
            border: 1px solid #e7eef6;
            background: #ffffff;
            color: #0f172a;
            border-radius: 12px;
            padding: 8px 12px;
            font-weight: 900;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .blocked-slots-pagination .page-btn:hover:not(:disabled) {
            background: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(16, 24, 40, 0.06);
        }

        .blocked-slots-pagination .page-btn:disabled {
            cursor: not-allowed;
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #e5e7eb;
            transform: none;
            box-shadow: none;
        }

        .blocked-slots-pagination .page-btn.active {
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            color: #ffffff;
            border-color: transparent;
        }

        @media (max-width: 768px) {
            .blocked-slots-pagination {
                display: none !important; /* desktop table is hidden on mobile */
            }
        }

        /* Premium Actions button style (card view) */
        .blocked-slot-card-actions .control-card-button.btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%) !important;
            border: none !important;
            box-shadow: 0 14px 32px rgba(239, 68, 68, 0.25) !important;
            border-radius: 14px !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }

        .blocked-slot-card-actions .control-card-button.btn-danger:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 18px 40px rgba(239, 68, 68, 0.32) !important;
            filter: brightness(1.05) !important;
        }

        /* Custom animated modal for blocking a time slot (replaces prompt()) */
        .ts-block-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.60);
            display: none;
            opacity: 0;
            pointer-events: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 10050;
            transition: opacity 180ms ease;
        }

        .ts-block-modal-overlay.show {
            display: flex;
            opacity: 1;
            pointer-events: auto;
        }

        .ts-block-modal-overlay.hide {
            display: flex;
            opacity: 0;
            pointer-events: none;
        }

        .ts-block-modal {
            width: min(560px, 100%);
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e7eef6;
            box-shadow: 0 24px 80px rgba(2, 6, 23, 0.35);
            transform: scale(0.96);
            opacity: 0;
            transition: transform 180ms ease, opacity 180ms ease;
        }

        .ts-block-modal-overlay.show .ts-block-modal {
            transform: scale(1);
            opacity: 1;
        }

        .ts-block-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 16px 10px;
        }

        .ts-block-modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ts-block-modal-close {
            background: transparent;
            border: none;
            color: #6b7280;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            line-height: 1;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .ts-block-modal-close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .ts-block-modal-body {
            padding: 0 16px 8px;
        }

        .ts-block-modal-body label {
            display: block;
            font-weight: 900;
            color: #111827;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .ts-block-modal-textarea {
            width: 100%;
            min-height: 110px;
            resize: vertical;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            outline: none;
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .ts-block-modal-textarea:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
        }

        .ts-block-modal-error {
            display: none;
            margin-top: 10px;
            font-size: 13px;
            color: #dc2626;
            font-weight: 900;
        }

        .ts-block-modal-error.show {
            display: block;
        }

        .ts-block-modal-footer {
            padding: 12px 16px 16px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .ts-block-modal-cancel {
            border-radius: 14px;
            padding: 10px 16px;
            font-weight: 900;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #111827;
            transition: transform 0.15s ease, background 0.15s ease;
        }

        .ts-block-modal-cancel:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }

        .ts-block-modal-confirm {
            border-radius: 14px;
            padding: 10px 16px;
            font-weight: 900;
            border: none;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: #ffffff;
            box-shadow: 0 14px 32px rgba(239, 68, 68, 0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }

        .ts-block-modal-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 40px rgba(239, 68, 68, 0.32);
            filter: brightness(1.05);
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fa-solid fa-calendar-days"></i> TIME SLOT SCHEDULING CONTROL</h2>
        
        <!-- Desktop Controls (hidden: dentist + view type are auto-selected) -->
        <div class="schedule-controls" style="display:none;">
            <div class="control-group">
                <label for="dentistSelectSchedule">Select Dentist:</label>
                <select id="dentistSelectSchedule">
                    <option value="">Select Dentist</option>
                    <?php
                    $dentistsQuery = "SELECT team_id, first_name, last_name FROM multidisciplinary_dental_team WHERE status = 'active'";
                    $dentistsResult = mysqli_query($con, $dentistsQuery);
                    while ($dentist = mysqli_fetch_assoc($dentistsResult)) {
                        echo "<option value='{$dentist['team_id']}'>Dr. {$dentist['first_name']} {$dentist['last_name']}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="control-group">
                <label for="viewType">View Type:</label>
                <select id="viewType" onchange="changeScheduleView()">
                    <option value="weekly">Weekly View</option>
                    <option value="monthly">Monthly View</option>
                </select>
            </div>
            
        </div>

        <!-- Mobile/Tablet Card View Controls -->
        <div class="schedule-controls-card">
            <!-- Dentist Selection Card -->
            <div class="control-card" style="display:none;">
                <div class="control-card-header">
                    <i class="fas fa-user-doctor"></i>
                    <h4>Select Dentist</h4>
                </div>
                <div class="control-card-content">
                    <select id="dentistSelectScheduleMobile" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                        <option value="">Select Dentist</option>
                        <?php
                        $dentistsQuery = "SELECT team_id, first_name, last_name FROM multidisciplinary_dental_team WHERE status = 'active'";
                        $dentistsResult = mysqli_query($con, $dentistsQuery);
                        while ($dentist = mysqli_fetch_assoc($dentistsResult)) {
                            echo "<option value='{$dentist['team_id']}'>Dr. {$dentist['first_name']} {$dentist['last_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- View Type Card -->
            <div class="control-card" style="display:none;">
                <div class="control-card-header">
                    <i class="fas fa-calendar-alt"></i>
                    <h4>View Type</h4>
                </div>
                <div class="control-card-content">
                    <select id="viewTypeMobile" onchange="changeScheduleView()" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                        <option value="weekly">Weekly View</option>
                        <option value="monthly">Monthly View</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Weekly Schedule View -->
        <div id="weeklyView" class="schedule-view">
            <div class="week-navigation" style="justify-content:space-between; gap:12px; flex-wrap:nowrap; width:100%;">
                <button id="prevWeekBtn" class="btn btn-accent" style="display:flex; justify-content:center; flex:0 0 auto;" onclick="changeWeek(-1)">
                    <i class="fas fa-chevron-left"></i> Previous Week
                </button>
                <h3 id="currentWeekRange" style="flex:1; text-align:center; margin:0;">Week of ...</h3>
                <button id="nextWeekBtn" class="btn btn-accent" style="display:flex; justify-content:center; flex:0 0 auto;" onclick="changeWeek(1)">
                    Next Week <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="weekly-schedule" data-active-day="mon">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
                $currentDate = new DateTime();
                $currentDate->modify('monday this week');
                ?>

                <!-- Day Tabs (Filters) -->
                <div class="day-tabs" role="tablist" aria-label="Filter schedule by day">
                    <?php
                    for ($i = 0; $i < 6; $i++) {
                        $dayDate = clone $currentDate;
                        $dayDate->modify("+$i days");
                        $isActive = $i === 0;
                        $dayKey = $dayKeys[$i];
                        $dayName = $days[$i];
                        $dayDateDisplay = $dayDate->format('M j');

                        echo "<button type='button' class='day-tab" . ($isActive ? ' active' : '') . "' data-day='{$dayKey}' data-day-name='{$dayName}' data-day-date='{$dayDateDisplay}'>{$dayName}</button>";
                    }
                    ?>
                </div>

                <h3 class="selected-day-title">
                    <?= $days[0] ?> – <?= $currentDate->format('M j') ?>
                </h3>

                <!-- Selected-Day Slot Grid -->
                <div class="time-slots-container">
                    <?php
                    $timeSlots = [
                        'firstBatch' => '8:00-9:00 AM',
                        'secondBatch' => '9:00-10:00 AM',
                        'thirdBatch' => '10:00-11:00 AM',
                        'fourthBatch' => '11:00-12:00 PM',
                        'fifthBatch' => '1:00-2:00 PM',
                        'sixthBatch' => '2:00-3:00 PM',
                        'sevenBatch' => '3:00-4:00 PM',
                        'eightBatch' => '4:00-5:00 PM',
                        'nineBatch' => '5:00-6:00 PM',
                        'tenBatch' => '6:00-7:00 PM',
                        'lastBatch' => '7:00-8:00 PM'
                    ];

                    foreach ($timeSlots as $slotKey => $slotTime) {
                        $slotStartLabel = $slotTime;
                        if (preg_match('/^(\d{1,2}:\d{2})-\d{1,2}:\d{2}\s*([AP]M)$/', $slotTime, $m)) {
                            $slotStartLabel = trim($m[1]) . ' ' . trim($m[2]);
                        }
                        $slotStartLabelEscaped = htmlspecialchars($slotStartLabel, ENT_QUOTES, 'UTF-8');

                        echo "<div class='time-slot-row'>";
                        for ($i = 0; $i < 6; $i++) {
                            $dayDate = clone $currentDate;
                            $dayDate->modify("+$i days");
                            $dateString = $dayDate->format('Y-m-d');
                            $dayKey = $dayKeys[$i];

                            echo "<div class='time-slot-cell' data-date='{$dateString}' data-slot='{$slotKey}' data-day='{$dayKey}'>";
                            echo "<div class='slot-card'>";
                            echo "<span class='slot-time'>{$slotStartLabelEscaped}</span>";
                            echo "<div class='slot-status available' onclick=\"toggleTimeSlot(this, '{$dateString}', '{$slotKey}')\">";
                            echo "<i class='fas fa-check-circle'></i>";
                            echo "<span>Available</span>";
                            echo "</div>";
                            echo "</div>";
                            echo "</div>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>

                <!-- Legend -->
                <div class="schedule-legend" aria-label="Schedule legend">
                    <div class="legend-item">
                        <span class="legend-badge available" aria-hidden="true"></span>
                        Patients can book
                    </div>
                    <div class="legend-item">
                        <span class="legend-badge booked" aria-hidden="true"></span>
                        Already booked
                    </div>
                    <div class="legend-item">
                        <span class="legend-badge blocked" aria-hidden="true"></span>
                        Closed / Not working
                    </div>
                </div>
            </div>

            <!-- Mobile/Tablet Card View for Time Slots -->
            <div class="time-slots-card-view" style="display: none;">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $currentDate = new DateTime();
                $currentDate->modify('monday this week');
                
                foreach ($timeSlots as $slotKey => $slotTime) {
                    echo "<div class='time-slot-card' data-slot='{$slotKey}'>";
                    echo "<div class='time-slot-card-header'>";
                    echo "<span class='time-label'>{$slotTime}</span>";
                    echo "</div>";
                    echo "<div class='time-slot-card-slots'>";
                    
                    for ($i = 0; $i < 6; $i++) {
                        $dayDate = clone $currentDate;
                        $dayDate->modify("+$i days");
                        $dateString = $dayDate->format('Y-m-d');
                        $dayName = $days[$i];
                        $dayShort = $dayDate->format('M j');
                        
                        echo "<div class='time-slot-card-slot available' data-date='{$dateString}' data-slot='{$slotKey}' onclick=\"toggleTimeSlotCard(this, '{$dateString}', '{$slotKey}')\">";
                        echo "<div style='font-weight: 600; margin-bottom: 4px; font-size: 13px;'>{$dayName}</div>";
                        echo "<div style='font-size: 11px; color: #666; margin-bottom: 6px;'>{$dayShort}</div>";
                        echo "<div class='slot-status-text' style='margin-top: 6px; font-size: 12px;'><i class='fas fa-check-circle'></i> Available</div>";
                        echo "</div>";
                    }
                    
                    echo "</div>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>

        <!-- Monthly View -->
        <div id="monthlyView" class="schedule-view" style="display:none;">
            <div class="month-navigation">
                <button class="btn btn-accent" onclick="changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i> Previous Month
                </button>
                <h3 id="currentMonth">Month Year</h3>
                <button class="btn btn-accent" onclick="changeMonth(1)">
                    Next Month <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="monthly-calendar" id="monthlyCalendar">
                <!-- Monthly calendar will be generated by JavaScript -->
            </div>
        </div>

        <!-- Blocked Time Slots List -->
        <div class="blocked-slots-section">
            <h3><i class="fa-solid fa-clock"></i> Blocked Time Slots</h3>
            
            <!-- Desktop Table View -->
            <div class="table-responsive">
                <table id="blockedSlotsTable">
                    <thead>
                        <tr>
                            <th>Dentist</th>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="blockedSlotsBody">
                        <!-- Blocked slots will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination (desktop/table UI only) -->
            <div class="blocked-slots-pagination" id="blockedSlotsPagination" aria-label="Blocked slots pagination"></div>

            <!-- Mobile/Tablet Card View -->
            <div class="blocked-slots-card-view" id="blockedSlotsCardView" style="display: none;">
                <!-- Blocked slots cards will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Block Entire Day Modal -->
<div id="blockDayModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeBlockDayModal()">&times;</span>
        <h3><i class="fas fa-calendar-times"></i> Block Entire Day</h3>
        <form id="blockDayForm" onsubmit="handleBlockDaySubmit(event)">
            <div style="margin-bottom: 15px;">
                <label for="blockDayDate"><strong>Select Date:</strong></label>
                <input type="date" id="blockDayDate" name="closure_date" required min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label><strong>Closure Type:</strong></label>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <input type="radio" name="closure_type" value="full_day" checked>
                        <span><i class="fas fa-ban" style="color: #dc3545;"></i> Full Day Closure (All appointments blocked)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <input type="radio" name="closure_type" value="no_new_appointments">
                        <span><i class="fas fa-exclamation-circle" style="color: #ffc107;"></i> No New Appointments (Existing appointments remain)</span>
                    </label>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="blockDayReason"><strong>Reason:</strong></label>
                <select id="blockDayReason" name="reason" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; margin-top: 5px;">
                    <option value="">Select Reason</option>
                    <option value="Holiday">Holiday</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Staff Training">Staff Training</option>
                    <option value="Emergency">Emergency</option>
                    <option value="Weather">Weather Conditions</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div id="blockDayCustomReasonContainer" style="margin-bottom: 15px; display: none;">
                <label for="blockDayCustomReason"><strong>Custom Reason (if Other):</strong></label>
                <textarea id="blockDayCustomReason" name="custom_reason" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; margin-top: 5px;" placeholder="Enter custom reason..."></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="notifyPatients" name="notify_patients" checked>
                    <span>Notify patients with appointments on this date</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeBlockDayModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Block Day</button>
            </div>
        </form>
    </div>
</div>

<!-- Holiday Management Modal -->
<div id="holidayModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 700px;">
        <span class="close" onclick="closeHolidayModal()">&times;</span>
        <h3><i class="fas fa-calendar-star"></i> Manage Holidays</h3>
        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
            <button class="btn btn-primary" onclick="showAddHolidayForm()">
                <i class="fas fa-plus"></i> Add Holiday
            </button>
        </div>
        
        <!-- Add Holiday Form -->
        <div id="addHolidayForm" style="display:none; background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h4>Add New Holiday</h4>
            <form id="holidayForm" onsubmit="handleHolidaySubmit(event)">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="holidayName"><strong>Holiday Name:</strong></label>
                        <input type="text" id="holidayName" name="holiday_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div>
                        <label for="holidayDate"><strong>Date:</strong></label>
                        <input type="date" id="holidayDate" name="holiday_date" required min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label><strong>Recurrence:</strong></label>
                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="recurrence" value="once" checked>
                            <span>One Time</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="recurrence" value="yearly">
                            <span>Yearly (Recurring)</span>
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="hideAddHolidayForm()">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Holiday</button>
                </div>
            </form>
        </div>
        
        <!-- Holidays List -->
        <div id="holidaysList">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px; text-align: left;">Holiday Name</th>
                        <th style="padding: 12px; text-align: left;">Date</th>
                        <th style="padding: 12px; text-align: left;">Recurrence</th>
                        <th style="padding: 12px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="holidaysTableBody">
                    <!-- Holidays will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Emergency Closure Modal -->
<div id="emergencyClosureModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeEmergencyClosureModal()">&times;</span>
        <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Emergency Closure</h3>
        <form id="emergencyClosureForm" onsubmit="handleEmergencyClosureSubmit(event)">
            <div style="margin-bottom: 15px;">
                <label><strong>Closure Duration:</strong></label>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <input type="radio" name="closure_duration" value="single_day" checked>
                        <span>Single Day</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <input type="radio" name="closure_duration" value="date_range">
                        <span>Date Range</span>
                    </label>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="emergencyStartDate"><strong>Start Date:</strong></label>
                <input type="date" id="emergencyStartDate" name="start_date" required min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 15px;" id="emergencyEndDateContainer" style="display:none;">
                <label for="emergencyEndDate"><strong>End Date:</strong></label>
                <input type="date" id="emergencyEndDate" name="end_date" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="emergencyReason"><strong>Emergency Reason:</strong></label>
                <textarea id="emergencyReason" name="reason" rows="4" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; margin-top: 5px;" placeholder="Describe the emergency situation..."></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="emergencyNotifyPatients" name="notify_patients" checked>
                    <span>Notify all affected patients immediately</span>
                </label>
            </div>
            
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 15px; margin-bottom: 15px;">
                <strong style="color: #856404;">⚠️ Warning:</strong>
                <p style="color: #856404; margin: 5px 0 0 0;">This will automatically cancel all appointments during the closure period. Affected patients will be notified.</p>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeEmergencyClosureModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Emergency Closure</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Block Time Slot Modal (replaces prompt()) -->
<div id="blockTimeSlotModalOverlay" class="ts-block-modal-overlay" aria-hidden="true">
    <div id="blockTimeSlotModal" class="ts-block-modal" role="dialog" aria-modal="true" aria-labelledby="blockTimeSlotModalTitle">
        <div class="ts-block-modal-header">
            <h3 id="blockTimeSlotModalTitle" class="ts-block-modal-title">
                <i class="fas fa-ban" style="color:#dc2626;"></i>
                Block Time Slot
            </h3>
            <button type="button" id="blockTimeSlotModalCloseBtn" class="ts-block-modal-close" aria-label="Close">&times;</button>
        </div>

        <div class="ts-block-modal-body">
            <label for="blockTimeSlotReasonTextarea">Please provide a reason for blocking this time slot</label>
            <textarea
                id="blockTimeSlotReasonTextarea"
                class="ts-block-modal-textarea"
                rows="4"
                placeholder="e.g., Blocked by admin"
            ></textarea>
            <div id="blockTimeSlotReasonError" class="ts-block-modal-error">Reason is required to block a time slot.</div>
        </div>

        <div class="ts-block-modal-footer">
            <button type="button" id="blockTimeSlotCancelBtn" class="btn ts-block-modal-cancel">Cancel</button>
            <button type="button" id="blockTimeSlotConfirmBtn" class="btn ts-block-modal-confirm">Confirm Block</button>
        </div>
    </div>
</div>

<script>
    // Notification Functions
    function showNotification(type, title, message, icon = null, duration = 5000) {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
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
            <div class="notification-icon">
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

    // Navigate back to admin with fade animation
    function navigateBack(event) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        setTimeout(() => {
            window.location.href = '../views/admin.php';
        }, 300);
    }

    // Dentist Schedules
    let currentWeekStart = getMondayOf(new Date());
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();

    // Ensure currentWeekStart is the Monday of the current week
    function getMondayOf(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diffToMonday = (day === 0) ? -6 : 1 - day;
        d.setDate(d.getDate() + diffToMonday);
        d.setHours(0,0,0,0);
        return d;
    }

    // Initialize schedule
    document.addEventListener('DOMContentLoaded', function() {
        currentWeekStart = getMondayOf(new Date());
        updateWeekDisplay();
        loadBlockedSlots();
        generateMonthlyCalendar();
        
        // Sync mobile and desktop dentist selects
        const dentistSelect = document.getElementById('dentistSelectSchedule');
        const dentistSelectMobile = document.getElementById('dentistSelectScheduleMobile');

        // Auto-select defaults so the schedule loads immediately (UI dropdowns are hidden).
        const setDefaultDentist = (selectEl) => {
            if (!selectEl) return;
            if (selectEl.value && selectEl.value !== '') return;
            const firstRealOption = selectEl.querySelector('option[value]:not([value=""])');
            if (firstRealOption) selectEl.value = firstRealOption.value;
        };

        const setDefaultViewType = (selectEl) => {
            if (!selectEl) return;
            selectEl.value = 'weekly';
        };

        setDefaultDentist(dentistSelect);
        setDefaultDentist(dentistSelectMobile);
        
        if (dentistSelect && dentistSelectMobile) {
            dentistSelect.addEventListener('change', function() {
                dentistSelectMobile.value = this.value;
                loadScheduleData();
                generateMonthlyCalendar();
                
                // Update card view if on mobile
                const isMobile = window.innerWidth <= 768;
                if (isMobile) {
                    setTimeout(() => {
                        loadTimeSlotsCardView();
                    }, 100);
                }
            });
            
            dentistSelectMobile.addEventListener('change', function() {
                dentistSelect.value = this.value;
                loadScheduleData();
                generateMonthlyCalendar();
                
                // Update card view on mobile
                setTimeout(() => {
                    loadTimeSlotsCardView();
                }, 100);
            });
        } else if (dentistSelect) {
            dentistSelect.addEventListener('change', function() {
                loadScheduleData();
                generateMonthlyCalendar();
                
                // Update card view if on mobile
                const isMobile = window.innerWidth <= 768;
                if (isMobile) {
                    setTimeout(() => {
                        loadTimeSlotsCardView();
                    }, 100);
                }
            });
        }

        // Sync view type selects
        const viewTypeSelect = document.getElementById('viewType');
        const viewTypeMobile = document.getElementById('viewTypeMobile');

        setDefaultViewType(viewTypeSelect);
        setDefaultViewType(viewTypeMobile);

        if (viewTypeSelect && viewTypeMobile) {
            viewTypeSelect.addEventListener('change', function() {
                viewTypeMobile.value = this.value;
                changeScheduleView();
            });
            
            viewTypeMobile.addEventListener('change', function() {
                viewTypeSelect.value = this.value;
                changeScheduleView();
            });
        }

        // Initialize view mode
        checkViewMode();

        // Auto-load the weekly schedule immediately (blocked + booked states).
        loadScheduleData();
    });

    function changeScheduleView() {
        const viewTypeSelect = document.getElementById('viewType');
        const viewTypeMobile = document.getElementById('viewTypeMobile');
        const viewType = viewTypeSelect?.value || viewTypeMobile?.value || 'weekly';
        
        // Sync both selects
        if (viewTypeSelect) viewTypeSelect.value = viewType;
        if (viewTypeMobile) viewTypeMobile.value = viewType;
        
        const weeklyView = document.getElementById('weeklyView');
        const monthlyView = document.getElementById('monthlyView');
        const cardView = document.querySelector('.time-slots-card-view');
        
        if (viewType === 'monthly') {
            if (weeklyView) weeklyView.style.display = 'none';
            if (monthlyView) monthlyView.style.display = 'block';
            if (cardView) cardView.style.display = 'none';
            generateMonthlyCalendar();
        } else {
            if (weeklyView) weeklyView.style.display = 'block';
            if (monthlyView) monthlyView.style.display = 'none';
            
            // Show card view on mobile/tablet, desktop view on desktop
            const isMobile = window.innerWidth <= 768;
            const weeklySchedule = document.querySelector('.weekly-schedule');
            
            if (isMobile) {
                if (cardView) cardView.style.display = 'block';
                if (weeklySchedule) weeklySchedule.style.display = 'none';
            } else {
                if (cardView) cardView.style.display = 'none';
                if (weeklySchedule) weeklySchedule.style.display = 'block';
            }
            if (weeklyView) weeklyView.style.display = 'block';
            
            loadScheduleData();
            if (isMobile) {
                updateCardViewDates();
                setTimeout(() => {
                    loadTimeSlotsCardView();
                }, 100);
            }
        }
    }

    function changeWeek(direction) {
        const newWeekStart = new Date(currentWeekStart);
        newWeekStart.setDate(newWeekStart.getDate() + (direction * 7));
        
        const thisWeekMonday = getMondayOf(new Date());
        
        if (newWeekStart < thisWeekMonday) {
            return;
        }
        
        currentWeekStart = newWeekStart;
        updateWeekDisplay();
        updateWeekNavigationButtons();
        
        setTimeout(() => {
            loadScheduleData();
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                updateCardViewDates();
                loadTimeSlotsCardView();
            }
        }, 100);
    }
    
    function updateWeekNavigationButtons() {
        const prevBtn = document.getElementById('prevWeekBtn');
        const nextBtn = document.getElementById('nextWeekBtn');
        
        if (!prevBtn || !nextBtn) return;
        
        const thisWeekMonday = getMondayOf(new Date());
        
        if (currentWeekStart.getTime() === thisWeekMonday.getTime()) {
            prevBtn.disabled = true;
            prevBtn.style.opacity = '0.5';
            prevBtn.style.cursor = 'not-allowed';
        } else {
            prevBtn.disabled = false;
            prevBtn.style.opacity = '1';
            prevBtn.style.cursor = 'pointer';
        }
        
        nextBtn.disabled = false;
        nextBtn.style.opacity = '1';
        nextBtn.style.cursor = 'pointer';
    }

    function updateWeekDisplay() {
        const weekEnd = new Date(currentWeekStart);
        weekEnd.setDate(weekEnd.getDate() + 5);
        const options = { month: 'short', day: 'numeric' };
        const startStr = currentWeekStart.toLocaleDateString('en-US', options);
        const endStr = weekEnd.toLocaleDateString('en-US', options);
        document.getElementById('currentWeekRange').textContent = `Week of ${startStr} - ${endStr}`;

        updateDayHeadersAndCells();
        refreshDayTabDatesFromCells();
        updateSelectedDayTitleFromActiveTab();
        updateWeekNavigationButtons();
        updateCardViewDates();
        // Auto-select today's tab and disable past days when showing current week
        selectTodayTabIfCurrentWeek();
        disablePastDaysIfCurrentWeek();
    }

    function updateCardViewDates() {
        const cardView = document.querySelector('.time-slots-card-view');
        if (!cardView) return;
        
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const cards = cardView.querySelectorAll('.time-slot-card');
        
        cards.forEach(card => {
            const slots = card.querySelectorAll('.time-slot-card-slot');
            
            slots.forEach((slot, index) => {
                if (index < 6) {
                    const dateForSlot = new Date(currentWeekStart);
                    dateForSlot.setDate(currentWeekStart.getDate() + index);
                    
                    // Use the same date formatting as desktop view to avoid timezone issues
                    const yyyy = dateForSlot.getFullYear();
                    const mm = String(dateForSlot.getMonth() + 1).padStart(2, '0');
                    const dd = String(dateForSlot.getDate()).padStart(2, '0');
                    const dateString = `${yyyy}-${mm}-${dd}`;
                    
                    const dayName = days[index];
                    const dayShort = dateForSlot.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    
                    // Update data-date attribute
                    slot.setAttribute('data-date', dateString);
                    
                    // Get the current status HTML and class (preserve them)
                    const statusMatch = slot.innerHTML.match(/(<div class=['"]slot-status-text[^>]*>.*?<\/div>)/);
                    const currentClass = slot.className; // Preserve current status class (available/blocked/booked)
                    const statusHTML = statusMatch ? statusMatch[1] : '<div class="slot-status-text" style="margin-top: 6px; font-size: 12px;"><i class="fas fa-check-circle"></i> Available</div>';
                    
                    // Rebuild the HTML with updated dates, preserving the status
                    slot.innerHTML = `<div style="font-weight: 600; margin-bottom: 4px; font-size: 13px;">${dayName}</div><div style="font-size: 11px; color: #666; margin-bottom: 6px;">${dayShort}</div>${statusHTML}`;
                    
                    // Restore the class
                    slot.className = currentClass;
                    
                    // Update onclick attribute with new date (only if not booked)
                    const slotKey = slot.getAttribute('data-slot');
                    if (!currentClass.includes('booked')) {
                        slot.setAttribute('onclick', `toggleTimeSlotCard(this, '${dateString}', '${slotKey}')`);
                        slot.style.cursor = 'pointer';
                    } else {
                        slot.removeAttribute('onclick');
                        slot.style.cursor = 'not-allowed';
                    }
                }
            });
        });
    }

    // Check and update view mode on resize
    function checkViewMode() {
        const isMobile = window.innerWidth <= 768;
        const cardView = document.querySelector('.time-slots-card-view');
        const weeklySchedule = document.querySelector('.weekly-schedule');
        const viewType = document.getElementById('viewType')?.value || document.getElementById('viewTypeMobile')?.value || 'weekly';
        
        if (viewType === 'weekly') {
            if (isMobile) {
                if (cardView) cardView.style.display = 'block';
                if (weeklySchedule) weeklySchedule.style.display = 'none';
                setTimeout(() => {
                    loadTimeSlotsCardView();
                }, 100);
            } else {
                if (cardView) cardView.style.display = 'none';
                if (weeklySchedule) weeklySchedule.style.display = 'block';
            }
        }
        
        // Show/hide blocked slots card view
        const blockedTable = document.getElementById('blockedSlotsTable');
        const blockedCardView = document.getElementById('blockedSlotsCardView');
        if (isMobile) {
            if (blockedTable && blockedTable.closest('.table-responsive')) {
                blockedTable.closest('.table-responsive').style.display = 'none';
            }
            if (blockedCardView) blockedCardView.style.display = 'block';
        } else {
            if (blockedTable && blockedTable.closest('.table-responsive')) {
                blockedTable.closest('.table-responsive').style.display = 'block';
            }
            if (blockedCardView) blockedCardView.style.display = 'none';
        }
    }
    
    window.addEventListener('resize', checkViewMode);

    function updateDayHeadersAndCells() {
        const dayDateEls = document.querySelectorAll('.time-slots-header .day-header .day-date');
        for (let i = 0; i < dayDateEls.length; i++) {
            const d = new Date(currentWeekStart);
            d.setDate(currentWeekStart.getDate() + i);
            dayDateEls[i].textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        const cells = document.querySelectorAll('.time-slot-cell');
        cells.forEach(cell => {
            const parentRow = cell.parentElement;
            if (!parentRow) return;
            const rowCells = Array.from(parentRow.querySelectorAll('.time-slot-cell'));
            const colIndex = rowCells.indexOf(cell);
            if (colIndex >= 0) {
                const dateForCell = new Date(currentWeekStart);
                dateForCell.setDate(currentWeekStart.getDate() + colIndex);
                const yyyy = dateForCell.getFullYear();
                const mm = String(dateForCell.getMonth() + 1).padStart(2, '0');
                const dd = String(dateForCell.getDate()).padStart(2, '0');
                const isoDate = `${yyyy}-${mm}-${dd}`;
                cell.setAttribute('data-date', isoDate);
            }
        });
    }

    const dayTabKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    function formatMonthDayFromISO(isoDate) {
        if (!isoDate) return '';
        // Parse as UTC to avoid timezone shifting when formatting
        const parts = isoDate.split('-');
        if (parts.length !== 3) return isoDate;
        const yyyy = Number(parts[0]);
        const mm = Number(parts[1]);
        const dd = Number(parts[2]);
        const dt = new Date(Date.UTC(yyyy, mm - 1, dd));
        return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
    }

    function refreshDayTabDatesFromCells() {
        dayTabKeys.forEach(dayKey => {
            const cell = document.querySelector(`.time-slot-cell[data-day="${dayKey}"]`);
            const tab = document.querySelector(`.day-tab[data-day="${dayKey}"]`);
            if (!cell || !tab) return;

            const isoDate = cell.getAttribute('data-date');
            const display = formatMonthDayFromISO(isoDate);
            if (display) {
                tab.dataset.dayDate = display;
            }
        });
    }

    function updateSelectedDayTitleFromActiveTab() {
        const weeklySchedule = document.querySelector('.weekly-schedule');
        const activeDay = weeklySchedule?.dataset.activeDay || 'mon';
        const activeTab = document.querySelector(`.day-tab[data-day="${activeDay}"]`);
        const titleEl = document.querySelector('.selected-day-title');
        if (!activeTab || !titleEl) return;

        const dayName = activeTab.dataset.dayName || activeTab.textContent.trim();
        const dayDateDisplay = activeTab.dataset.dayDate || '';
        if (dayDateDisplay) {
            titleEl.textContent = `${dayName} – ${dayDateDisplay}`;
        }
    }

    // Select today's day tab if viewing the current week
    function selectTodayTabIfCurrentWeek() {
        const thisWeekMonday = getMondayOf(new Date());
        if (currentWeekStart.getTime() !== thisWeekMonday.getTime()) return;
        const dow = new Date().getDay(); // 0=Sun..6=Sat
        const map = {1:'mon',2:'tue',3:'wed',4:'thu',5:'fri',6:'sat',0:'mon'}; // default Sun->Mon
        const dayKey = map[dow] || 'mon';
        setActiveDayTab(dayKey);
    }

    // Disable selection for past days (current week only)
    function disablePastDaysIfCurrentWeek() {
        const thisWeekMonday = getMondayOf(new Date());
        if (currentWeekStart.getTime() !== thisWeekMonday.getTime()) return;

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const isoToday = `${yyyy}-${mm}-${dd}`;

        // Disable desktop grid cells (past dates)
        document.querySelectorAll('.time-slot-cell').forEach(cell => {
            const date = cell.getAttribute('data-date');
            if (!date) return;
            const statusEl = cell.querySelector('.slot-status');
            if (statusEl) {
                if (date < isoToday) {
                    statusEl.removeAttribute('onclick');
                    statusEl.style.cursor = 'not-allowed';
                    statusEl.style.opacity = '0.6';
                }
            }
        });

        // Disable past day tabs
        dayTabKeys.forEach(dayKey => {
            const cell = document.querySelector(`.time-slot-cell[data-day="${dayKey}"]`);
            const tab = document.querySelector(`.day-tab[data-day="${dayKey}"]`);
            if (!cell || !tab) return;
            const date = cell.getAttribute('data-date');
            if (date && date < isoToday) {
                tab.setAttribute('disabled', 'true');
                tab.style.opacity = '0.6';
                tab.style.cursor = 'not-allowed';
                tab.style.pointerEvents = 'none';
            } else {
                tab.removeAttribute('disabled');
                tab.style.opacity = '';
                tab.style.cursor = '';
                tab.style.pointerEvents = '';
            }
        });

        // Disable mobile card slots (past dates)
        document.querySelectorAll('.time-slot-card-slot').forEach(slot => {
            const date = slot.getAttribute('data-date');
            if (!date) return;
            if (date < isoToday) {
                slot.removeAttribute('onclick');
                slot.style.cursor = 'not-allowed';
                slot.style.opacity = '0.6';
            }
        });
    }

    function setActiveDayTab(dayKey) {
        const weeklySchedule = document.querySelector('.weekly-schedule');
        if (weeklySchedule) {
            weeklySchedule.dataset.activeDay = dayKey;
        }

        document.querySelectorAll('.day-tab').forEach(tab => {
            tab.classList.remove('active');
        });

        const activeTab = document.querySelector(`.day-tab[data-day="${dayKey}"]`);
        if (activeTab) activeTab.classList.add('active');

        updateSelectedDayTitleFromActiveTab();
    }

    function initDayTabs() {
        const tabs = document.querySelectorAll('.day-tab');
        if (!tabs || tabs.length === 0) return;

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const dayKey = this.getAttribute('data-day');
                if (dayKey) setActiveDayTab(dayKey);
            });
        });

        // Initialize selected tab + filter state
        const activeTab = document.querySelector('.day-tab.active') || tabs[0];
        if (activeTab) {
            setActiveDayTab(activeTab.getAttribute('data-day'));
        }
    }

    function changeMonth(direction) {
        currentMonth += direction;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        } else if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        generateMonthlyCalendar();
        setTimeout(() => {
            loadScheduleData();
        }, 100);
    }

    function generateMonthlyCalendar() {
        const calendar = document.getElementById('monthlyCalendar');
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const dentistSelect = document.getElementById('dentistSelectSchedule');
        const dentistSelectMobile = document.getElementById('dentistSelectScheduleMobile');
        const dentistId = dentistSelect?.value || dentistSelectMobile?.value;
        
        document.getElementById('currentMonth').textContent = `${monthNames[currentMonth]} ${currentYear}`;
        
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const startingDay = firstDay.getDay();
        
        loadMonthlyScheduleData(dentistId, firstDay, lastDay).then(scheduleData => {
            let calendarHTML = '';
            
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayNames.forEach(day => {
                calendarHTML += `<div class="calendar-day-header">${day}</div>`;
            });
            
            for (let i = 0; i < startingDay; i++) {
                const prevDate = new Date(currentYear, currentMonth, -i);
                calendarHTML += `<div class="calendar-day other-month">${prevDate.getDate()}</div>`;
            }
            
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const date = new Date(currentYear, currentMonth, day);
                const dateStr = date.toISOString().split('T')[0];
                const isToday = new Date().toDateString() === date.toDateString();
                const dayClass = isToday ? 'calendar-day today' : 'calendar-day';
                
                const dayData = scheduleData[dateStr] || { blocked: 0, booked: 0, available: 0 };
                const totalSlots = 11;
                const available = totalSlots - dayData.blocked - dayData.booked;
                
                calendarHTML += `
                    <div class="${dayClass}" data-date="${dateStr}">
                        <div class="calendar-day-header">${day}</div>
                        <div class="day-slots">
                            <div><span class="slot-indicator available"></span> ${available} available</div>
                            <div><span class="slot-indicator blocked"></span> ${dayData.blocked} blocked</div>
                            <div><span class="slot-indicator booked"></span> ${dayData.booked} booked</div>
                        </div>
                    </div>
                `;
            }
            
            calendar.innerHTML = calendarHTML;
        });
    }
    
    async function loadMonthlyScheduleData(dentistId, firstDay, lastDay) {
        if (!dentistId) {
            return {};
        }
        
        const scheduleData = {};
        
        try {
            const blockedResponse = await fetch('../controllers/get_blocked_slots.php');
            const blockedSlots = await blockedResponse.json();
            
            const startDate = firstDay.toISOString().split('T')[0];
            const endDate = lastDay.toISOString().split('T')[0];
            
            for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                const dateStr = d.toISOString().split('T')[0];
                scheduleData[dateStr] = { blocked: 0, booked: 0, available: 0 };
            }
            
            blockedSlots.forEach(slot => {
                if (slot.dentist_id === dentistId && slot.date >= startDate && slot.date <= endDate) {
                    if (!scheduleData[slot.date]) {
                        scheduleData[slot.date] = { blocked: 0, booked: 0, available: 0 };
                    }
                    scheduleData[slot.date].blocked++;
                }
            });
            
            const appointmentPromises = [];
            for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                const dateStr = d.toISOString().split('T')[0];
                appointmentPromises.push(
                    fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${dateStr}&dentist_id=${dentistId}`)
                        .then(res => res.json())
                        .then(slots => {
                            if (!scheduleData[dateStr]) {
                                scheduleData[dateStr] = { blocked: 0, booked: 0, available: 0 };
                            }
                            scheduleData[dateStr].booked = slots.length;
                        })
                        .catch(() => {})
                );
            }
            
            await Promise.all(appointmentPromises);
            
        } catch (error) {
            console.error('Error loading monthly schedule data:', error);
        }
        
        return scheduleData;
    }

    // Custom modal state for blocking a single time slot (replaces browser prompt()).
    let pendingBlockTimeSlot = null; // { type: 'desktop'|'card', element, date, slot, dayInfoHTML? }

    function openBlockTimeSlotModalDesktop(targetEl, date, slot) {
        pendingBlockTimeSlot = { type: 'desktop', element: targetEl, date: date, slot: slot };

        const overlay = document.getElementById('blockTimeSlotModalOverlay');
        const textarea = document.getElementById('blockTimeSlotReasonTextarea');
        const errorEl = document.getElementById('blockTimeSlotReasonError');

        if (!overlay || !textarea || !errorEl) return;

        textarea.value = 'Blocked by admin';
        errorEl.classList.remove('show');
        overlay.classList.remove('hide');
        overlay.classList.add('show');
        setTimeout(() => textarea.focus(), 0);
    }

    function openBlockTimeSlotModalCard(targetEl, date, slot, dayInfoHTML) {
        pendingBlockTimeSlot = { type: 'card', element: targetEl, date: date, slot: slot, dayInfoHTML: dayInfoHTML };

        const overlay = document.getElementById('blockTimeSlotModalOverlay');
        const textarea = document.getElementById('blockTimeSlotReasonTextarea');
        const errorEl = document.getElementById('blockTimeSlotReasonError');

        if (!overlay || !textarea || !errorEl) return;

        textarea.value = 'Blocked by admin';
        errorEl.classList.remove('show');
        overlay.classList.remove('hide');
        overlay.classList.add('show');
        setTimeout(() => textarea.focus(), 0);
    }

    function closeBlockTimeSlotModal() {
        pendingBlockTimeSlot = null;

        const overlay = document.getElementById('blockTimeSlotModalOverlay');
        if (!overlay) return;

        overlay.classList.remove('show');
        overlay.classList.add('hide');

        setTimeout(() => {
            overlay.classList.remove('hide');
        }, 180);
    }

    function confirmBlockTimeSlotModal() {
        if (!pendingBlockTimeSlot) return;

        const textarea = document.getElementById('blockTimeSlotReasonTextarea');
        const errorEl = document.getElementById('blockTimeSlotReasonError');
        if (!textarea || !errorEl) return;

        const reason = (textarea.value || '').trim();
        if (!reason) {
            errorEl.textContent = 'Reason is required to block a time slot.';
            errorEl.classList.add('show');
            textarea.focus();
            return;
        }

        errorEl.classList.remove('show');

        const { type, element, date, slot, dayInfoHTML } = pendingBlockTimeSlot;
        const newStatus = 'blocked';

        // Apply UI immediately (matches existing prompt() behavior).
        if (type === 'desktop') {
            element.className = `slot-status ${newStatus}`;
            element.innerHTML = '<i class="fas fa-times-circle"></i><span>Blocked</span>';
        } else {
            element.className = 'time-slot-card-slot blocked';
            element.innerHTML =
                (dayInfoHTML || '') +
                '<div class="slot-status-text" style="margin-top: 6px; font-size: 12px;"><i class="fas fa-times-circle"></i> Blocked</div>';
        }

        closeBlockTimeSlotModal();
        updateTimeSlotStatus(date, slot, newStatus, reason);
    }

    (function initBlockTimeSlotModal() {
        const overlay = document.getElementById('blockTimeSlotModalOverlay');
        const textarea = document.getElementById('blockTimeSlotReasonTextarea');
        const errorEl = document.getElementById('blockTimeSlotReasonError');
        const cancelBtn = document.getElementById('blockTimeSlotCancelBtn');
        const confirmBtn = document.getElementById('blockTimeSlotConfirmBtn');
        const closeBtn = document.getElementById('blockTimeSlotModalCloseBtn');

        // Modal might not exist in some environments; safely no-op.
        if (!overlay || !textarea || !errorEl || !cancelBtn || !confirmBtn) return;

        cancelBtn.addEventListener('click', closeBlockTimeSlotModal);
        confirmBtn.addEventListener('click', confirmBlockTimeSlotModal);
        if (closeBtn) {
            closeBtn.addEventListener('click', closeBlockTimeSlotModal);
        }

        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) closeBlockTimeSlotModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('show')) {
                closeBlockTimeSlotModal();
            }
        });
    })();

    function toggleTimeSlot(element, date, slot) {
        if (element.classList.contains('booked') || element.style.cursor === 'not-allowed') {
            alert('This slot is already booked and cannot be modified.');
            return;
        }
        
        const currentStatus = element.classList.contains('available') ? 'available' : 
                            element.classList.contains('blocked') ? 'blocked' : 'booked';
        
        if (currentStatus === 'booked') {
            alert('This slot is already booked and cannot be modified.');
            return;
        }
        
        const newStatus = currentStatus === 'available' ? 'blocked' : 'available';
        
        if (newStatus === 'blocked') {
            openBlockTimeSlotModalDesktop(element, date, slot);
            return; // UI waits for modal confirmation before updating status
        } else {
            if (!confirm('Are you sure you want to unblock this time slot?')) {
                return;
            }
            
            element.className = `slot-status ${newStatus}`;
            element.innerHTML = '<i class="fas fa-check-circle"></i><span>Available</span>';
            
            updateTimeSlotStatus(date, slot, newStatus);
        }
    }

    function updateTimeSlotStatus(date, slot, status, reason = '') {
        const dentistSelect = document.getElementById('dentistSelectSchedule');
        const dentistSelectMobile = document.getElementById('dentistSelectScheduleMobile');
        const dentistId = dentistSelect?.value || dentistSelectMobile?.value;
        
        if (!dentistId) {
            alert('Please select a dentist first.');
            const element = document.querySelector(`[data-date="${date}"][data-slot="${slot}"] .slot-status`);
            if (element) {
                const currentStatus = status === 'blocked' ? 'available' : 'blocked';
                element.className = `slot-status ${currentStatus}`;
                element.innerHTML = currentStatus === 'available' ? 
                    '<i class="fas fa-check-circle"></i><span>Available</span>' :
                    '<i class="fas fa-times-circle"></i><span>Blocked</span>';
            }
            return;
        }
        
        const requestData = {
            dentist_id: dentistId,
            date: date,
            time_slot: slot,
            status: status,
            action: 'update_slot'
        };
        
        if (reason) {
            requestData.reason = reason;
        }
        
        fetch('../controllers/update_schedule.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Success', data.message || 'Time slot updated successfully.');
                loadBlockedSlots();
                loadScheduleData();
                
                // Force reload card view after a short delay to ensure data is updated
                setTimeout(() => {
                    loadTimeSlotsCardView();
                }, 300);
                
                const monthlyView = document.getElementById('monthlyView');
                if (monthlyView && monthlyView.style.display !== 'none') {
                    generateMonthlyCalendar();
                }
            } else {
                showNotification('error', 'Error', data.message || 'Failed to update time slot.');
                const element = document.querySelector(`[data-date="${date}"][data-slot="${slot}"] .slot-status`);
                if (element) {
                    const revertStatus = status === 'blocked' ? 'available' : 'blocked';
                    element.className = `slot-status ${revertStatus}`;
                    element.innerHTML = revertStatus === 'available' ? 
                        '<i class="fas fa-check-circle"></i><span>Available</span>' :
                        '<i class="fas fa-times-circle"></i><span>Blocked</span>';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while updating the time slot. Please try again.');
            const element = document.querySelector(`[data-date="${date}"][data-slot="${slot}"] .slot-status`);
            if (element) {
                const revertStatus = status === 'blocked' ? 'available' : 'blocked';
                element.className = `slot-status ${revertStatus}`;
                element.innerHTML = revertStatus === 'available' ? 
                    '<i class="fas fa-check-circle"></i><span>Available</span>' :
                    '<i class="fas fa-times-circle"></i><span>Blocked</span>';
            }
        });
    }

    function loadBlockedSlots() {
        fetch('../controllers/get_blocked_slots.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('blockedSlotsBody');
            const cardView = document.getElementById('blockedSlotsCardView');
            const paginationEl = document.getElementById('blockedSlotsPagination');
            const rowsPerPage = 5;
            const allSlots = Array.isArray(data) ? data : [];
            let currentPage = 1;
            
            const renderBlockedSlotsTablePage = (page) => {
                if (!tbody) return;

                const totalPages = Math.ceil(allSlots.length / rowsPerPage) || 1;
                currentPage = Math.min(Math.max(page, 1), totalPages);

                tbody.innerHTML = '';

                if (allSlots.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="no-data">No blocked time slots found</td></tr>';
                    if (paginationEl) paginationEl.innerHTML = '';
                    return;
                }

                const startIdx = (currentPage - 1) * rowsPerPage;
                const pageSlots = allSlots.slice(startIdx, startIdx + rowsPerPage);

                pageSlots.forEach(slot => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${slot.dentist_name}</td>
                        <td>${slot.date}</td>
                        <td>${slot.time_slot_display}</td>
                        <td>${slot.reason}</td>
                        <td>
                            <button class="action-btn btn-danger" onclick="unblockSlot('${slot.id}')" title="Unblock">
                                <i class="fas fa-unlock"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // Pagination UI
                if (paginationEl) {
                    paginationEl.innerHTML = '';
                    if (totalPages <= 1) return;

                    const prevBtn = document.createElement('button');
                    prevBtn.type = 'button';
                    prevBtn.className = 'page-btn';
                    prevBtn.textContent = 'Previous';
                    prevBtn.disabled = currentPage === 1;
                    prevBtn.addEventListener('click', () => renderBlockedSlotsTablePage(currentPage - 1));

                    paginationEl.appendChild(prevBtn);

                    for (let i = 1; i <= totalPages; i++) {
                        const pageBtn = document.createElement('button');
                        pageBtn.type = 'button';
                        pageBtn.className = 'page-btn' + (i === currentPage ? ' active' : '');
                        pageBtn.textContent = i;
                        pageBtn.disabled = false;
                        pageBtn.addEventListener('click', () => renderBlockedSlotsTablePage(i));
                        paginationEl.appendChild(pageBtn);
                    }

                    const nextBtn = document.createElement('button');
                    nextBtn.type = 'button';
                    nextBtn.className = 'page-btn';
                    nextBtn.textContent = 'Next';
                    nextBtn.disabled = currentPage === totalPages;
                    nextBtn.addEventListener('click', () => renderBlockedSlotsTablePage(currentPage + 1));

                    paginationEl.appendChild(nextBtn);
                }
            };

            renderBlockedSlotsTablePage(1);
            
            // Load card view for mobile/tablet
            if (cardView) {
                cardView.innerHTML = '';
                
                if (allSlots.length === 0) {
                    cardView.innerHTML = '<div class="blocked-slot-card"><p style="text-align: center; color: #666; padding: 20px;">No blocked time slots found</p></div>';
                } else {
                    allSlots.forEach(slot => {
                        const card = document.createElement('div');
                        card.className = 'blocked-slot-card';
                        card.innerHTML = `
                            <div class="blocked-slot-card-header">
                                <h5>${slot.time_slot_display}</h5>
                            </div>
                            <div class="blocked-slot-card-info">
                                <div class="blocked-slot-card-info-item">
                                    <i class="fas fa-user-md"></i>
                                    <span>${slot.dentist_name}</span>
                                </div>
                                <div class="blocked-slot-card-info-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>${slot.date}</span>
                                </div>
                                <div class="blocked-slot-card-info-item">
                                    <i class="fas fa-info-circle"></i>
                                    <span>${slot.reason}</span>
                                </div>
                            </div>
                            <div class="blocked-slot-card-actions">
                                <button class="control-card-button btn-danger" onclick="unblockSlot('${slot.id}')" style="width: 100%;">
                                    <i class="fas fa-unlock"></i>
                                    <span>Unblock Slot</span>
                                </button>
                            </div>
                        `;
                        cardView.appendChild(card);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading blocked slots:', error);
        });
    }

    function unblockSlot(blockId) {
        if (!confirm('Are you sure you want to unblock this time slot?')) {
            return;
        }
        
        fetch('../controllers/update_schedule.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                block_id: blockId,
                action: 'unblock_slot'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Success', 'Time slot unblocked successfully.');
                loadBlockedSlots();
                loadScheduleData();
                
                // Force reload card view after a short delay to ensure data is updated
                setTimeout(() => {
                    loadTimeSlotsCardView();
                }, 300);
                
                const monthlyView = document.getElementById('monthlyView');
                if (monthlyView && monthlyView.style.display !== 'none') {
                    generateMonthlyCalendar();
                }
            } else {
                showNotification('error', 'Error', data.message || 'Failed to unblock time slot.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while unblocking the time slot. Please try again.');
        });
    }

    function loadTimeSlotsCardView() {
        const dentistSelect = document.getElementById('dentistSelectSchedule');
        const dentistSelectMobile = document.getElementById('dentistSelectScheduleMobile');
        const dentistId = dentistSelect?.value || dentistSelectMobile?.value;
        if (!dentistId) return;
        
        const cardView = document.querySelector('.time-slots-card-view');
        if (!cardView || cardView.style.display === 'none') return;
        
        const cards = cardView.querySelectorAll('.time-slot-card');
        if (cards.length === 0) return;
        
        const dates = new Set();
        
        cards.forEach(card => {
            const slots = card.querySelectorAll('.time-slot-card-slot');
            slots.forEach(slot => {
                const date = slot.getAttribute('data-date');
                if (date) dates.add(date);
            });
        });
        
        if (dates.size === 0) return;
        
        Promise.all([
            fetch('../controllers/get_blocked_slots.php').then(res => res.json()),
            Promise.all(Array.from(dates).map(date => 
                fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${date}&dentist_id=${dentistId}`)
                    .then(res => res.json())
                    .then(slots => ({ date, slots }))
                    .catch(() => ({ date, slots: [] }))
            ))
        ])
        .then(([blockedSlots, appointmentData]) => {
            const appointmentsByDate = {};
            appointmentData.forEach(({ date, slots }) => {
                appointmentsByDate[date] = new Set(slots);
            });
            
            cards.forEach(card => {
                const slotKey = card.getAttribute('data-slot');
                const slots = card.querySelectorAll('.time-slot-card-slot');
                
                // Convert dentistId to string for comparison
                const dentistIdStr = String(dentistId);
                
                slots.forEach(slot => {
                    const date = slot.getAttribute('data-date');
                    const slotKeyAttr = slot.getAttribute('data-slot');
                    
                    // Get the original day info HTML (day name and date)
                    const dayMatch = slot.innerHTML.match(/(<div style=['"]font-weight: 600[^>]*>.*?<\/div><div style=['"]font-size: 11px[^>]*>.*?<\/div>)/);
                    const dayInfoHTML = dayMatch ? dayMatch[1] : '';
                    
                    // Check if blocked first (before checking booked)
                    let isBlocked = false;
                    blockedSlots.forEach(blockedSlot => {
                        const blockedDentistIdStr = String(blockedSlot.dentist_id);
                        const blockedDate = blockedSlot.date.trim();
                        const slotDate = date.trim();
                        
                        if (blockedDentistIdStr === dentistIdStr && 
                            blockedSlot.time_slot === slotKeyAttr && 
                            blockedDate === slotDate) {
                            isBlocked = true;
                        }
                    });
                    
                    // Check if booked
                    const isBooked = appointmentsByDate[date] && appointmentsByDate[date].has(slotKeyAttr);
                    
                    if (isBooked) {
                        slot.className = 'time-slot-card-slot booked';
                        slot.innerHTML = dayInfoHTML + '<div class="slot-status-text" style="margin-top: 6px; font-size: 12px;"><i class="fas fa-calendar-check"></i> Booked</div>';
                        slot.removeAttribute('onclick');
                        slot.style.cursor = 'not-allowed';
                    } else if (isBlocked) {
                        slot.className = 'time-slot-card-slot blocked';
                        slot.innerHTML = dayInfoHTML + '<div class="slot-status-text" style="margin-top: 6px; font-size: 12px;"><i class="fas fa-times-circle"></i> Blocked</div>';
                        slot.setAttribute('onclick', `toggleTimeSlotCard(this, '${date}', '${slotKeyAttr}')`);
                        slot.style.cursor = 'pointer';
                    } else {
                        slot.className = 'time-slot-card-slot available';
                        slot.innerHTML = dayInfoHTML + '<div class="slot-status-text" style="margin-top: 6px; font-size: 12px;"><i class="fas fa-check-circle"></i> Available</div>';
                        slot.setAttribute('onclick', `toggleTimeSlotCard(this, '${date}', '${slotKeyAttr}')`);
                        slot.style.cursor = 'pointer';
                    }
                });
            });
        })
        .catch(error => {
            console.error('Error loading card view data:', error);
        });
    }

    function toggleTimeSlotCard(element, date, slot) {
        if (element.classList.contains('booked') || element.style.cursor === 'not-allowed') {
            alert('This slot is already booked and cannot be modified.');
            return;
        }
        
        const currentStatus = element.classList.contains('available') ? 'available' : 
                            element.classList.contains('blocked') ? 'blocked' : 'booked';
        
        if (currentStatus === 'booked') {
            alert('This slot is already booked and cannot be modified.');
            return;
        }
        
        const newStatus = currentStatus === 'available' ? 'blocked' : 'available';
        
        // Get day info from element - try both single and double quotes
        const dayMatch = element.innerHTML.match(/(<div style=['"]font-weight: 600[^>]*>.*?<\/div><div style=['"]font-size: 11px[^>]*>.*?<\/div>)/);
        const dayInfoHTML = dayMatch ? dayMatch[1] : '';
        
        if (newStatus === 'blocked') {
            openBlockTimeSlotModalCard(element, date, slot, dayInfoHTML);
            return; // UI waits for modal confirmation before updating status
        } else {
            if (!confirm('Are you sure you want to unblock this time slot?')) {
                return;
            }
            
            element.className = 'time-slot-card-slot available';
            element.innerHTML = dayInfoHTML + '<div class="slot-status-text" style="margin-top: 6px; font-size: 12px;"><i class="fas fa-check-circle"></i> Available</div>';
            
            updateTimeSlotStatus(date, slot, newStatus);
        }
    }

    function loadScheduleData() {
        const dentistSelect = document.getElementById('dentistSelectSchedule');
        const dentistSelectMobile = document.getElementById('dentistSelectScheduleMobile');
        const dentistId = dentistSelect?.value || dentistSelectMobile?.value;
        if (!dentistId) return;
        
        const dateCells = document.querySelectorAll('.time-slot-cell');
        const dates = new Set();
        dateCells.forEach(cell => {
            const date = cell.getAttribute('data-date');
            if (date) dates.add(date);
        });
        
        Promise.all([
            fetch('../controllers/get_blocked_slots.php').then(res => res.json()),
            Promise.all(Array.from(dates).map(date => 
                fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${date}&dentist_id=${dentistId}`)
                    .then(res => res.json())
                    .then(slots => ({ date, slots }))
                    .catch(() => ({ date, slots: [] }))
            ))
        ])
        .then(([blockedSlots, appointmentData]) => {
            const appointmentsByDate = {};
            appointmentData.forEach(({ date, slots }) => {
                appointmentsByDate[date] = new Set(slots);
            });
            
            document.querySelectorAll('.slot-status').forEach(slot => {
                slot.className = 'slot-status available';
                slot.innerHTML = '<i class="fas fa-check-circle"></i><span>Available</span>';
                const cell = slot.closest('.time-slot-cell');
                if (cell) {
                    const date = cell.getAttribute('data-date');
                    const slotKey = cell.getAttribute('data-slot');
                    slot.setAttribute('onclick', `toggleTimeSlot(this, '${date}', '${slotKey}')`);
                    slot.style.cursor = 'pointer';
                    slot.style.opacity = '1';
                }
            });
            
            Object.keys(appointmentsByDate).forEach(date => {
                const bookedSlots = appointmentsByDate[date];
                bookedSlots.forEach(timeSlot => {
                    const cell = document.querySelector(`[data-date="${date}"][data-slot="${timeSlot}"]`);
                    if (cell) {
                        const statusElement = cell.querySelector('.slot-status');
                        if (statusElement) {
                            statusElement.className = 'slot-status booked';
                            statusElement.innerHTML = '<i class="fas fa-calendar-check"></i><span>Booked</span>';
                            statusElement.removeAttribute('onclick');
                            statusElement.style.cursor = 'not-allowed';
                            statusElement.style.opacity = '0.7';
                        }
                    }
                });
            });
            
            blockedSlots.forEach(slot => {
                if (slot.dentist_id === dentistId) {
                    const cell = document.querySelector(`[data-date="${slot.date}"][data-slot="${slot.time_slot}"]`);
                    if (cell) {
                        const statusElement = cell.querySelector('.slot-status');
                        if (statusElement && !statusElement.classList.contains('booked')) {
                            statusElement.className = 'slot-status blocked';
                            statusElement.innerHTML = '<i class="fas fa-times-circle"></i><span>Blocked</span>';
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading schedule data:', error);
        });
    }

    // Block Entire Day Modal Functions
    function openBlockDayModal() {
        document.getElementById('blockDayModal').style.display = 'block';
    }
    
    function closeBlockDayModal() {
        document.getElementById('blockDayModal').style.display = 'none';
        document.getElementById('blockDayForm').reset();
        const customReasonContainer = document.getElementById('blockDayCustomReasonContainer');
        if (customReasonContainer) {
            customReasonContainer.style.display = 'none';
        }
    }
    
    function handleBlockDaySubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const closureDate = formData.get('closure_date');
        const closureType = formData.get('closure_type');
        let reason = formData.get('reason');
        const customReason = formData.get('custom_reason');
        const notifyPatients = formData.get('notify_patients') === 'on';
        
        if (reason === 'Other' && customReason) {
            reason = customReason;
        }
        
        if (!reason || reason.trim() === '') {
            showNotification('error', 'Error', 'Please provide a reason for the closure.');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        const requestData = {
            action: 'block_day',
            date: closureDate,
            closure_type: closureType,
            reason: reason,
            custom_reason: customReason || '',
            notify_patients: notifyPatients
        };
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Day Blocked Successfully', `Date ${closureDate} has been blocked. ${notifyPatients ? 'Patients have been notified.' : ''}`);
                closeBlockDayModal();
                loadScheduleData();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to block day. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while blocking the day. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const reasonSelect = document.getElementById('blockDayReason');
        const customReasonContainer = document.getElementById('blockDayCustomReasonContainer');
        const customReasonTextarea = document.getElementById('blockDayCustomReason');
        if (reasonSelect && customReasonContainer && customReasonTextarea) {
            reasonSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    customReasonContainer.style.display = 'block';
                    customReasonTextarea.setAttribute('required', 'required');
                } else {
                    customReasonContainer.style.display = 'none';
                    customReasonTextarea.removeAttribute('required');
                    customReasonTextarea.value = '';
                }
            });
        }
        
        const closureDurationRadios = document.querySelectorAll('input[name="closure_duration"]');
        const endDateContainer = document.getElementById('emergencyEndDateContainer');
        if (closureDurationRadios.length > 0 && endDateContainer) {
            closureDurationRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'date_range') {
                        endDateContainer.style.display = 'block';
                        document.getElementById('emergencyEndDate').setAttribute('required', 'required');
                    } else {
                        endDateContainer.style.display = 'none';
                        document.getElementById('emergencyEndDate').removeAttribute('required');
                        document.getElementById('emergencyEndDate').value = '';
                    }
                });
            });
        }
    });
    
    // Holiday Management Modal Functions
    function openHolidayModal() {
        document.getElementById('holidayModal').style.display = 'block';
        loadHolidays();
    }
    
    function closeHolidayModal() {
        document.getElementById('holidayModal').style.display = 'none';
        hideAddHolidayForm();
    }
    
    function showAddHolidayForm() {
        document.getElementById('addHolidayForm').style.display = 'block';
    }
    
    function hideAddHolidayForm() {
        document.getElementById('addHolidayForm').style.display = 'none';
        document.getElementById('holidayForm').reset();
    }
    
    function handleHolidaySubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        
        const requestData = {
            action: 'add_holiday',
            holiday_name: formData.get('holiday_name'),
            holiday_date: formData.get('holiday_date'),
            recurrence: formData.get('recurrence')
        };
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Holiday Added', `Holiday "${requestData.holiday_name}" has been added.`);
                hideAddHolidayForm();
                loadHolidays();
                loadScheduleData();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to add holiday. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while adding holiday. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    function loadHolidays() {
        fetch('../controllers/get_holidays.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('holidaysTableBody');
            if (!tbody) return;
            
            if (data.success && data.holidays && data.holidays.length > 0) {
                tbody.innerHTML = '';
                data.holidays.forEach(holiday => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td style="padding: 12px;">${holiday.holiday_name}</td>
                        <td style="padding: 12px;">${holiday.holiday_date}</td>
                        <td style="padding: 12px;">${holiday.recurrence === 'yearly' ? 'Yearly (Recurring)' : 'One Time'}</td>
                        <td style="padding: 12px; text-align: center;">
                            <button class="action-btn btn-danger" onclick="deleteHoliday(${holiday.id})" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No holidays found. Add one to get started.</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading holidays:', error);
        });
    }
    
    function deleteHoliday(holidayId) {
        if (!confirm('Are you sure you want to delete this holiday?')) {
            return;
        }
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_holiday',
                holiday_id: holidayId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Holiday Deleted', 'Holiday has been deleted successfully.');
                loadHolidays();
                loadScheduleData();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to delete holiday.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while deleting holiday.');
        });
    }
    
    // Emergency Closure Modal Functions
    function openEmergencyClosureModal() {
        document.getElementById('emergencyClosureModal').style.display = 'block';
    }
    
    function closeEmergencyClosureModal() {
        document.getElementById('emergencyClosureModal').style.display = 'none';
        document.getElementById('emergencyClosureForm').reset();
        document.getElementById('emergencyEndDateContainer').style.display = 'none';
    }
    
    function handleEmergencyClosureSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        const startDate = formData.get('start_date');
        const endDate = formData.get('end_date');
        const closureDuration = formData.get('closure_duration');
        const reason = formData.get('reason');
        const notifyPatients = formData.get('notify_patients') === 'on';
        
        if (!confirm('⚠️ WARNING: This will cancel all appointments during the closure period. Are you absolutely sure you want to proceed?')) {
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Emergency Closure...';
        
        const requestData = {
            action: 'emergency_closure',
            start_date: startDate,
            end_date: closureDuration === 'date_range' ? endDate : startDate,
            reason: reason,
            notify_patients: notifyPatients
        };
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('warning', 'Emergency Closure Activated', `Clinic closed from ${startDate} to ${requestData.end_date}. ${data.cancelled_count || 0} appointments cancelled. ${notifyPatients ? 'Patients have been notified.' : ''}`);
                closeEmergencyClosureModal();
                loadScheduleData();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to process emergency closure. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while processing emergency closure. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('blockDayModal')) {
            closeBlockDayModal();
        }
        if (event.target === document.getElementById('holidayModal')) {
            closeHolidayModal();
        }
        if (event.target === document.getElementById('emergencyClosureModal')) {
            closeEmergencyClosureModal();
        }
    });

    // Day tab initialization (time-slot grid filtering)
    document.addEventListener('DOMContentLoaded', function() {
        initDayTabs();
        refreshDayTabDatesFromCells();
        updateSelectedDayTitleFromActiveTab();
    });
</script>
</body>
</html>
