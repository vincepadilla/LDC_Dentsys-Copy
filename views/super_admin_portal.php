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
                <i class="fa fa-shield-alt"></i>
                <span class="sidebar-text">Super Admin Home</span>
            </a>
            <a href="#settings">
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
                    Super Admin Portal
                </div>
                <div class="super-admin-subtitle">
                    Welcome, <?php echo htmlspecialchars($adminInfo['first_name'] ?? $_SESSION['first_name'] ?? 'Admin'); ?>.
                    Manage global website content and user accounts from here.
                </div>
            </div>
            <div class="super-admin-actions">
                <span class="sa-badge">
                    <i class="fas fa-crown"></i>
                    SUPER ADMIN
                </span>
            </div>
        </div>

        <div class="tool-cards">
            <!-- Edit Content Tool -->
            <div class="tool-card">
                <div class="tool-card-header">
                    <div class="tool-icon" style="background: #e0f2fe; color: #0284c7;">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div>
                        <div class="tool-card-title">Edit Website Content</div>
                        <div class="tool-card-desc">
                            Manage homepage text, services, contact details, clinic locations, and other public-facing content.
                        </div>
                    </div>
                </div>
                <div class="tool-card-footer">
                    <a href="edit_content.php" class="tool-link-btn">
                        Go to Content Manager
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <span class="tool-meta"></span>
                </div>
            </div>

            <!-- User Management Tool -->
            <div class="tool-card">
                <div class="tool-card-header">
                    <div class="tool-icon" style="background: #ecfdf5; color: #16a34a;">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div>
                        <div class="tool-card-title">User Management</div>
                        <div class="tool-card-desc">
                            View and manage user accounts, review activity, and block or unblock accounts when needed.
                        </div>
                    </div>
                </div>
                <div class="tool-card-footer">
                    <a href="userControl.php" class="tool-link-btn">
                        Go to User Control
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <span class="tool-meta"></span>
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
