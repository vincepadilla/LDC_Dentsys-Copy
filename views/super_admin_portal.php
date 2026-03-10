<?php
session_start();
include_once("../database/config.php");

// Only allow logged in super admins
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super-admin') {
    header("Location: login.php");
    exit();
}

// Get admin info (optional, used only for greeting)
$user_id = $_SESSION['userID'];
$adminInfo = null;

$query = "SELECT * FROM multidisciplinary_dental_team WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $adminInfo = $result->fetch_assoc();
}
$stmt->close();

// Get dashboard statistics
$totalUsers = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM user_account WHERE role != 'super-admin'"))['total'];
$totalPatients = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM user_account WHERE role = 'patient'"))['total'];
$totalAdmins = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM user_account WHERE role = 'admin'"))['total'];
$totalDentists = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM user_account WHERE role = 'dentist'"))['total'];
$totalAppointments = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM appointments"))['total'];
$activeUsers = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM user_account WHERE (status IS NULL OR status = 'active') AND role != 'super-admin'"))['total'];
$blockedUsers = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM user_account WHERE status = 'blocked'"))['total'];
$todayAppointments = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE()"))['total'];

// System Performance Metrics
// Patient Feedback Satisfaction (based on approved feedbacks)
$totalFeedbacks = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM feedback"))['total'];
$approvedFeedbacks = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM feedback WHERE status = 'approved'"))['total'];
$feedbackSatisfaction = $totalFeedbacks > 0 ? round(($approvedFeedbacks / $totalFeedbacks) * 100, 1) : 0;

// Appointment Completion Rate
$completedAppointments = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM appointments WHERE status IN ('Complete', 'Completed')"))['total'];
$completionRate = $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 1) : 0;

// Cancellation Rate
$cancelledAppointments = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM appointments WHERE status = 'Cancelled'"))['total'];
$cancellationRate = $totalAppointments > 0 ? round(($cancelledAppointments / $totalAppointments) * 100, 1) : 0;

// Top Active Users (by appointment count)
$topUsersQuery = "
    SELECT 
        ua.user_id,
        ua.first_name,
        ua.last_name,
        ua.role,
        COUNT(DISTINCT a.appointment_id) as appointment_count
    FROM user_account ua
    LEFT JOIN patient_information p ON ua.user_id = p.user_id
    LEFT JOIN appointments a ON p.patient_id = a.patient_id
    WHERE ua.role != 'super-admin'
    GROUP BY ua.user_id, ua.first_name, ua.last_name, ua.role
    ORDER BY appointment_count DESC
    LIMIT 5
";
$topUsersResult = mysqli_query($con, $topUsersQuery);
$topUsers = [];
while ($row = mysqli_fetch_assoc($topUsersResult)) {
    $topUsers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Portal | Landero Dental Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">

    <style>
        /* Keep layout consistent with admin dashboard, but simplified for super admin tools */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            background: #f3f4f6;
        }

        .super-admin-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .super-admin-title {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
        }

        .super-admin-subtitle {
            color: #6b7280;
            margin-top: 4px;
            font-size: 14px;
        }

        .super-admin-actions {
            display: flex;
            gap: 10px;
        }

        .sa-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #111827;
            color: white;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .tool-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .tool-card {
            background: white;
            border-radius: 16px;
            padding: 22px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .tool-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tool-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .tool-card-title {
            font-weight: 600;
            color: #111827;
        }

        .tool-card-desc {
            font-size: 13px;
            color: #6b7280;
        }

        .tool-card-footer {
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tool-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            border: none;
            background: #48A6A7;
            color: white;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .tool-link-btn:hover {
            background: #3d8e90;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(72, 166, 167, 0.35);
        }

        .tool-meta {
            font-size: 11px;
            color: #9ca3af;
        }

        /* Dashboard Styles - Matching admin.php layout */
        .dashboard-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            flex: 1 1 30%;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            margin-right: 15px;
            color: #3c8dbc;
            font-size: 24px;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .stat-label {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        .stat-change {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-change.positive {
            color: #10b981;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        /* Two Column Layout for Metrics and Top Users */
        .metrics-users-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
            margin-bottom: 20px;
        }

        .metrics-section,
        .top-users-section {
            display: flex;
            flex-direction: column;
        }

        .metrics-section h2,
        .top-users-section h2 {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
            margin-top: 0;
        }

        /* System Performance Metrics - Single Card with List */
        .metrics-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            padding: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            flex: 1;
        }

        .metrics-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .metric-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .metric-item:last-child {
            border-bottom: none;
        }

        .metric-item:hover {
            background: #f8fafc;
            margin: 0 -24px;
            padding: 16px 24px;
            border-radius: 8px;
        }

        .metric-item-content {
            flex: 1;
        }

        .metric-item-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .metric-item-value {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
        }

        .metric-item-details {
            font-size: 13px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .metric-item-details.positive {
            color: #059669;
        }

        .metric-item-details.negative {
            color: #dc2626;
        }

        /* Top Active Users Card Styles */
        .top-user-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            padding: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .top-user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .top-user-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .top-user-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }

        .top-user-card-rank {
            font-size: 14px;
            font-weight: 700;
            color: #6b7280;
        }

        .top-user-card-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .top-user-card-item:last-child {
            border-bottom: none;
        }

        .top-user-card-info {
            flex: 1;
            min-width: 0;
        }

        .top-user-card-name {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .top-user-card-role {
            font-size: 13px;
            color: #6b7280;
            text-transform: capitalize;
        }

        .top-user-card-count {
            font-size: 18px;
            font-weight: 700;
            color: #48A6A7;
        }

        .no-data-message {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
        }

        .no-data-message i {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .no-data-message p {
            color: #6b7280;
            font-size: 16px;
            margin: 0;
        }

        /* Responsive: reuse admin sidebar responsiveness */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 16px;
            }

            .super-admin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .dashboard-grid {
                flex-direction: column;
                align-items: stretch;
            }

            .stat-card {
                width: 100%;
            }

            .metrics-users-container {
                grid-template-columns: 1fr;
            }

            .metrics-container {
                padding: 20px;
            }

            .metric-item-value {
                font-size: 20px;
            }

            .top-user-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar Toggle (mobile) -->
    <div class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../assets/images/landerologo.png" alt="Clinic Logo">
        </div>
        <nav class="sidebar-nav">
            <a href="super_admin_portal.php" class="active">
                <i class="fas fa-tachometer-alt"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="userControl.php">
                <i class="fas fa-users-cog"></i>
                <span class="sidebar-text">User Control</span>
            </a>
            <a href="edit_content.php">
                <i class="fas fa-edit"></i>
                <span class="sidebar-text">Edit Content</span>
            </a>
            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span class="sidebar-text">Settings</span>
            </a>
            <a href="../controllers/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="sidebar-text">Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="super-admin-header">
            <div>
                <div class="super-admin-title">
                    Super Admin Dashboard
                </div>
                <div class="super-admin-subtitle">
                    Welcome, <?php echo htmlspecialchars($adminInfo['first_name'] ?? $_SESSION['first_name'] ?? 'Admin'); ?>.
                    Overview of system statistics and management tools.
                </div>
            </div>
        </div>

        <!-- Dashboard Statistics -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <i class="fas fa-users fa-2x"></i>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>

            <div class="stat-card">
                <i class="fas fa-user-injured fa-2x"></i>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($totalPatients); ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>

            <div class="stat-card">
                <i class="fas fa-user-shield fa-2x"></i>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($totalAdmins); ?></div>
                    <div class="stat-label">Total Admins</div>
                </div>
            </div>

            <div class="stat-card">
                <i class="fas fa-user-md fa-2x"></i>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($totalDentists); ?></div>
                    <div class="stat-label">Total Dentists</div>
                </div>
            </div>

            <div class="stat-card">
                <i class="fas fa-calendar-check fa-2x"></i>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($totalAppointments); ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>

            <div class="stat-card">
                <i class="fas fa-ban fa-2x"></i>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($blockedUsers); ?></div>
                    <div class="stat-label">Blocked Users</div>
                </div>
            </div>

            <div class="stat-card">
                <i class="fas fa-calendar-day fa-2x"></i>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($todayAppointments); ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
            </div>
        </div>

        <!-- System Performance Metrics and Top Active Users - Side by Side -->
        <div class="metrics-users-container">
            <!-- System Performance Metrics Section -->
            <div class="metrics-section">
                <h2>System Performance Metrics</h2>
                <div class="metrics-container">
                    <div class="metric-item">
                        <div class="metric-item-content">
                            <div class="metric-item-label">Feedback Satisfaction</div>
                            <div class="metric-item-value"><?php echo $feedbackSatisfaction; ?>%</div>
                            <div class="metric-item-details positive">
                                <?php echo number_format($approvedFeedbacks); ?> approved of <?php echo number_format($totalFeedbacks); ?> total
                            </div>
                        </div>
                    </div>

                    <div class="metric-item">
                        <div class="metric-item-content">
                            <div class="metric-item-label">Appointment Completion Rate</div>
                            <div class="metric-item-value"><?php echo $completionRate; ?>%</div>
                            <div class="metric-item-details positive">
                                <?php echo number_format($completedAppointments); ?> completed of <?php echo number_format($totalAppointments); ?> total
                            </div>
                        </div>
                    </div>

                    <div class="metric-item">
                        <div class="metric-item-content">
                            <div class="metric-item-label">Cancellation Rate</div>
                            <div class="metric-item-value"><?php echo $cancellationRate; ?>%</div>
                            <div class="metric-item-details negative">
                                <?php echo number_format($cancelledAppointments); ?> cancelled of <?php echo number_format($totalAppointments); ?> total
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Active Users Section -->
            <div class="top-users-section">
                <h2>Top Active Users</h2>
                <div class="top-user-card">
                    <?php if (count($topUsers) > 0): ?>
                        <?php foreach ($topUsers as $index => $user): ?>
                            <div class="top-user-card-item">
                                <div class="top-user-card-info">
                                    <div class="top-user-card-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                    <div class="top-user-card-role"><?php echo ucfirst($user['role']); ?></div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="top-user-card-count"><?php echo number_format($user['appointment_count']); ?> appts</div>
                                    <button class="tool-link-btn" style="padding: 4px 10px; font-size: 11px;" onclick="window.location.href='userControl.php'">
                                        Change Role
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: #9ca3af;">No data available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple sidebar toggle copied from admin.php behavior (simplified)
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

            if (!sidebar || !menuToggle) return;

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
    </script>
</body>
</html>
