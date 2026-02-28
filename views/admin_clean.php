<?php
session_start();
include_once("../database/config.php");

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

        /* Responsive Sidebar Styles */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: #1e3a47;
            transform: scale(1.05);
        }

        .menu-toggle.active {
            background: var(--accent-color);
        }

        .sidebar-text {
            transition: opacity 0.3s ease;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }

            .sidebar-text {
                opacity: 1;
            }

            .sidebar.active .sidebar-text {
                opacity: 1;
            }

            /* Adjust dashboard for mobile */
            .dashboard-stats {
                grid-template-columns: 1fr !important;
            }

            .appointments-container {
                flex-direction: column !important;
            }

            .notification-container {
                right: 10px;
                top: 60px;
                max-width: calc(100% - 20px);
            }
        }

        @media (min-width: 769px) {
            .sidebar {
                transform: translateX(0) !important;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<div class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../assets/images/landerologo.png">
    </div>
    <nav class="sidebar-nav">
        <a href="#" class="active" onclick="showSection('dashboard', this)"><i class="fa fa-tachometer"></i> <span class="sidebar-text">Dashboard</span></a>
        <div class="sidebar-divider"></div>
        <button class="sidebar-btn-clinic-control" onclick="showControlsPopup()" title="Controls">
            <i class="fas fa-cog"></i> <span class="sidebar-text">Controls</span>
        </button>
        <a href="login.php"><i class="fa-solid fa-right-from-bracket"></i> <span class="sidebar-text">Logout</span></a>
    </nav>
</div>

<!-- Controls Popup Modal -->
<div id="controlsPopupModal" class="modal" style="display:none; z-index: 10001;">
    <div class="modal-content" style="max-width: 400px;">
        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-sliders-h"></i> Select Control
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
            
            <button class="control-option-btn" onclick="navigateToUserControl()">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="control-icon" style="background: #3b82f620; color: #3b82f6;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-weight: 600; font-size: 16px;">User Control</div>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 3px;">Manage users & accounts</div>
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
    $todaysAppointmentsQuery = "SELECT a.appointment_id, p.first_name, p.last_name, s.service_category,
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

    // Get today's appointment summary by hour
    $summaryQuery = "SELECT HOUR(appointment_time) AS hour, COUNT(*) AS total 
                     FROM appointments 
                     WHERE appointment_date = CURDATE() 
                     GROUP BY HOUR(appointment_time) 
                     ORDER BY hour";
    $summaryResult = mysqli_query($con, $summaryQuery);

    $appointmentHours = [];
    $appointmentCounts = [];

    while ($row = mysqli_fetch_assoc($summaryResult)) {
        $appointmentHours[] = $row['hour'] . ':00';
        $appointmentCounts[] = $row['total'];
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
            <h2>Today's Appointments (<?php echo $todaysAppointmentsCount; ?>)</h2>

            <?php if ($todaysAppointmentsCount > 0) { ?>
                <div class="appointments-table">
                    <div class="appointments-table-header">
                        <div class="appointments-table-column"><strong>Time</strong></div>
                        <div class="appointments-table-column"><strong>Patient Name</strong></div>
                        <div class="appointments-table-column"><strong>Service</strong></div>
                        <div class="appointments-table-column"><strong>Dentist</strong></div>
                        <div class="appointments-table-column"><strong>Status</strong></div>
                    </div>

                    <?php while ($row = mysqli_fetch_assoc($todaysAppointmentsResult)) { ?>
                        <div class="appointments-table-row">
                            <div class="appointments-table-column"><?php echo htmlspecialchars($row['appointment_time']); ?></div>
                            <div class="appointments-table-column">
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            </div>
                            <div class="appointments-table-column">
                                <?php echo htmlspecialchars($row['service_category']); ?>
                            </div>
                            <div class="appointments-table-column">
                                <?php echo htmlspecialchars($row['dentist_first'] . ' ' . $row['dentist_last']); ?>
                            </div>
                            <div class="appointments-table-column"><?php echo htmlspecialchars($row['status']); ?></div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p>No appointments scheduled for today.</p>
            <?php } ?>
        </div>

        <div class="upcoming-appointments">
            <h2>Upcoming Appointments (<?php echo $upcomingAppointmentsCount; ?>)</h2>

            <?php if ($upcomingAppointmentsCount > 0) { ?>
                <div class="appointments-table">
                    <div class="appointments-table-header">
                        <div class="appointments-table-column"><strong>Date</strong></div>
                        <div class="appointments-table-column"><strong>Time</strong></div>
                        <div class="appointments-table-column"><strong>Patient</strong></div>
                    </div>

                    <?php while ($row = mysqli_fetch_assoc($upcomingAppointmentsResult)) { ?>
                        <div class="appointments-table-row">
                            <div class="appointments-table-column"><?php echo date('M j', strtotime($row['appointment_date'])); ?></div>
                            <div class="appointments-table-column"><?php echo htmlspecialchars($row['appointment_time']); ?></div>
                            <div class="appointments-table-column">
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p>No upcoming appointments.</p>
            <?php } ?>
        </div>
    </div>

    <div class="graph-container" style="margin-top: 30px;">
        <h3>Appointment Time Summary</h3>
        <canvas id="appointmentSummaryChart" width="500" height="200"></canvas>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const timeLabels = <?php echo json_encode($appointmentHours); ?>;
        const appointmentData = <?php echo json_encode($appointmentCounts); ?>;

        const ctx = document.getElementById('appointmentSummaryChart').getContext('2d');

        // Predefined set of 5 colors
        const barColors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
        ];

        // Repeat the color set if there are more than 5 bars
        const colorsForBars = appointmentData.map((_, index) => barColors[index % barColors.length]);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: timeLabels,
                datasets: [{
                    label: 'Appointments per Hour',
                    data: appointmentData,
                    backgroundColor: colorsForBars,
                    borderColor: '#ffffff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Today\'s Appointment Distribution by Time'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true, 
                        stepSize: 1,         
                        title: {
                            display: true,
                            text: 'Number of Patients'
                        },
                        ticks: {
                            callback: function(value) {
                                return Number.isInteger(value) ? value : '';
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Time (Hourly)'
                        }
                    }
                }
            }
        });
    </script>
</div>

<script>
    // ==================== RESPONSIVE SIDEBAR ====================
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const menuToggle = document.querySelector(".menu-toggle");
        sidebar.classList.toggle("active");
        menuToggle.classList.toggle("active");
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById("sidebar");
        const menuToggle = document.querySelector(".menu-toggle");
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);
        
        if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !isClickInsideSidebar && !isClickOnToggle) {
            sidebar.classList.remove('active');
            menuToggle.classList.remove('active');
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
</script>
</body>
</html>
