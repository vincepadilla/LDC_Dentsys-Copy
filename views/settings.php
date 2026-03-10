<?php
session_start();
include_once("../database/config.php");

// Only allow logged in super admins
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super-admin') {
    header("Location: login.php");
    exit();
}

// Get admin info
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

// Create system_settings table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    section VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

mysqli_query($con, $createTableQuery);

// Get all settings
$settingsQuery = "SELECT setting_key, setting_value, setting_type, section FROM system_settings";
$settingsResult = mysqli_query($con, $settingsQuery);
$settingsData = [];

while ($row = mysqli_fetch_assoc($settingsResult)) {
    $settingsData[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    // Appointment Settings
    'advance_booking_limit' => '30',
    'walk_ins_enabled' => '1',
    
    // Payment Settings
    'gcash_enabled' => '1',
    'maya_enabled' => '1',
    'reservation_fee_amount' => '500',
    
    // Email & Notification Settings
    'appointment_confirmation_email' => '1',
    'appointment_reminder_notifications' => '1',
    'promotional_campaign_emails' => '1',
    
    // User & Security Settings
    'default_user_role' => 'patient',
    'account_verification' => 'email',
    'max_login_attempts' => '5',
    'session_timeout' => '3600',
    
    // System Maintenance
    'maintenance_mode' => '0'
];

foreach ($defaults as $key => $value) {
    if (!isset($settingsData[$key])) {
        $settingsData[$key] = $value;
        // Insert default value
        $insertQuery = "INSERT INTO system_settings (setting_key, setting_value, setting_type, section) VALUES (?, ?, 'text', ?)";
        $insertStmt = $con->prepare($insertQuery);
        $section = 'appointment';
        if (strpos($key, 'payment') !== false || strpos($key, 'gcash') !== false || strpos($key, 'maya') !== false || strpos($key, 'reservation_fee') !== false) {
            $section = 'payment';
        } elseif (strpos($key, 'email') !== false || strpos($key, 'notification') !== false || strpos($key, 'promotional') !== false) {
            $section = 'email';
        } elseif (strpos($key, 'user') !== false || strpos($key, 'security') !== false || strpos($key, 'login') !== false || strpos($key, 'session') !== false || strpos($key, 'verification') !== false || strpos($key, 'role') !== false) {
            $section = 'security';
        } elseif (strpos($key, 'maintenance') !== false || strpos($key, 'backup') !== false || strpos($key, 'restore') !== false) {
            $section = 'maintenance';
        }
        $insertStmt->bind_param("sss", $key, $value, $section);
        $insertStmt->execute();
        $insertStmt->close();
    }
}

// Display success/error messages
$success_msg = '';
$error_msg = '';

if (isset($_SESSION['settings_success'])) {
    $success_msg = $_SESSION['settings_success'];
    unset($_SESSION['settings_success']);
}

if (isset($_SESSION['settings_error'])) {
    $error_msg = $_SESSION['settings_error'];
    unset($_SESSION['settings_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Landero Dental Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">

    <style>
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            background: #f3f4f6;
        }

        .settings-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .settings-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-subtitle {
            color: #6b7280;
            margin-top: 6px;
            font-size: 14px;
        }

        .back-to-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #48A6A7;
            border: 2px solid #48A6A7;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .back-to-dashboard:hover {
            background: #48A6A7;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(72, 166, 167, 0.3);
        }

        .content-area {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .section-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
            border-bottom: 2px solid #e5e7eb;
            flex-wrap: wrap;
            overflow-x: auto;
            padding-bottom: 0;
        }

        .tab-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #6b7280;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            top: 2px;
            white-space: nowrap;
        }

        .tab-button:hover {
            color: #48A6A7;
            background: #f9fafb;
        }

        .tab-button.active {
            color: #48A6A7;
            border-bottom-color: #48A6A7;
            font-weight: 600;
        }

        .tab-button i {
            font-size: 16px;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .section-description {
            color: #6b7280;
            font-size: 14px;
            margin-top: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-label-required::after {
            content: " *";
            color: #ef4444;
        }

        .form-input,
        .form-select {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            color: #111827;
            background: white;
            transition: all 0.2s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #48A6A7;
            box-shadow: 0 0 0 3px rgba(72, 166, 167, 0.1);
        }

        .form-help {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 26px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .toggle-slider {
            background-color: #48A6A7;
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        .toggle-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: #48A6A7;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 32px;
        }

        .btn-save:hover {
            background: #3d8e90;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 166, 167, 0.4);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .maintenance-warning {
            padding: 16px;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 10px;
            color: #92400e;
            font-size: 14px;
            display: flex;
            align-items: start;
            gap: 12px;
            margin-bottom: 24px;
        }

        .maintenance-warning i {
            margin-top: 2px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 16px;
            }

            .settings-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .section-tabs {
                overflow-x: auto;
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
            <a href="super_admin_portal.php">
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
            <a href="settings.php" class="active">
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
        <div class="settings-header">
            <div>
                <div class="settings-title">
                    <i class="fas fa-cog"></i>
                    System Settings
                </div>
                <div class="settings-subtitle">
                    Manage system configuration and preferences
                </div>
            </div>
            <a href="super_admin_portal.php" class="back-to-dashboard">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <form action="../controllers/update_settings.php" method="POST">
            <div class="content-area">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Section Tabs -->
                <div class="section-tabs">
                    <button type="button" class="tab-button active" onclick="showSection('appointment')">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointment</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('payment')">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Payment</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('email')">
                        <i class="fas fa-envelope"></i>
                        <span>Email & Notifications</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('security')">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('maintenance')">
                        <i class="fas fa-tools"></i>
                        <span>Maintenance</span>
                    </button>
                </div>

                <!-- Appointment Settings -->
                <div id="appointment" class="tab-content active">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Appointment Settings
                        </div>
                        <div class="section-description">
                            Configure appointment booking preferences and limitations
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label form-label-required">Advance Booking Limit (days)</label>
                            <input type="number" name="advance_booking_limit" class="form-input" 
                                   value="<?php echo htmlspecialchars($settingsData['advance_booking_limit']); ?>" 
                                   min="1" max="365" required>
                            <div class="form-help">How many days in advance patients can book</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Walk-ins</label>
                            <div class="toggle-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="walk_ins_enabled" value="1" 
                                           <?php echo ($settingsData['walk_ins_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Enable walk-in appointments</span>
                            </div>
                            <div class="form-help">Allow patients to book same-day appointments</div>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings -->
                <div id="payment" class="tab-content">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-money-bill-wave"></i>
                            Payment Settings
                        </div>
                        <div class="section-description">
                            Manage payment methods and reservation fees
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">GCash Payment</label>
                            <div class="toggle-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="gcash_enabled" value="1" 
                                           <?php echo ($settingsData['gcash_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Enable GCash payment method</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Maya Payment</label>
                            <div class="toggle-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maya_enabled" value="1" 
                                           <?php echo ($settingsData['maya_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Enable Maya payment method</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label-required">Reservation Fee Amount (PHP)</label>
                            <input type="number" name="reservation_fee_amount" class="form-input" 
                                   value="<?php echo htmlspecialchars($settingsData['reservation_fee_amount']); ?>" 
                                   min="0" step="0.01" required>
                            <div class="form-help">Amount required to reserve an appointment</div>
                        </div>
                    </div>
                </div>

                <!-- Email & Notification Settings -->
                <div id="email" class="tab-content">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-envelope"></i>
                            Email & Notification Settings
                        </div>
                        <div class="section-description">
                            Configure email notifications and communication preferences
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Appointment Confirmation Email</label>
                            <div class="toggle-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="appointment_confirmation_email" value="1" 
                                           <?php echo ($settingsData['appointment_confirmation_email'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Send confirmation emails when appointments are booked</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Appointment Reminder Notifications</label>
                            <div class="toggle-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="appointment_reminder_notifications" value="1" 
                                           <?php echo ($settingsData['appointment_reminder_notifications'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Send reminder notifications before appointments</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Promotional Campaign Emails</label>
                            <div class="toggle-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="promotional_campaign_emails" value="1" 
                                           <?php echo ($settingsData['promotional_campaign_emails'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Allow sending promotional emails to users</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User & Security Settings -->
                <div id="security" class="tab-content">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            User & Security Settings
                        </div>
                        <div class="section-description">
                            Configure user account defaults and security preferences
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label form-label-required">Default User Role</label>
                            <select name="default_user_role" class="form-select" required>
                                <option value="patient" <?php echo ($settingsData['default_user_role'] == 'patient') ? 'selected' : ''; ?>>Patient</option>
                                <option value="dentist" <?php echo ($settingsData['default_user_role'] == 'dentist') ? 'selected' : ''; ?>>Dentist</option>
                                <option value="admin" <?php echo ($settingsData['default_user_role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <div class="form-help">Default role assigned to new users</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label-required">Account Verification</label>
                            <select name="account_verification" class="form-select" required>
                                <option value="email" <?php echo ($settingsData['account_verification'] == 'email') ? 'selected' : ''; ?>>Email</option>
                                <option value="otp" <?php echo ($settingsData['account_verification'] == 'otp') ? 'selected' : ''; ?>>OTP (SMS)</option>
                                <option value="both" <?php echo ($settingsData['account_verification'] == 'both') ? 'selected' : ''; ?>>Both (Email & OTP)</option>
                            </select>
                            <div class="form-help">Method used for account verification</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label-required">Maximum Login Attempts</label>
                            <input type="number" name="max_login_attempts" class="form-input" 
                                   value="<?php echo htmlspecialchars($settingsData['max_login_attempts']); ?>" 
                                   min="3" max="10" required>
                            <div class="form-help">Number of failed login attempts before account lockout</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label-required">Session Timeout (seconds)</label>
                            <input type="number" name="session_timeout" class="form-input" 
                                   value="<?php echo htmlspecialchars($settingsData['session_timeout']); ?>" 
                                   min="300" max="86400" step="300" required>
                            <div class="form-help">Time before user session expires (300-86400 seconds)</div>
                        </div>
                    </div>
                </div>

                <!-- System Maintenance -->
                <div id="maintenance" class="tab-content">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-tools"></i>
                            System Maintenance
                        </div>
                        <div class="section-description">
                            Database management and system maintenance tools
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Maintenance Mode</label>
                            <div class="toggle-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" value="1" 
                                           <?php echo ($settingsData['maintenance_mode'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Enable maintenance mode (disables booking temporarily)</span>
                            </div>
                            <div class="form-help">When enabled, appointment booking will be disabled for all users</div>
                        </div>
                    </div>
                    
                    <div class="maintenance-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Warning:</strong> Database operations should be performed with caution. 
                            Always backup your database before performing restore operations.
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Database Backup</label>
                            <button type="button" class="btn-action btn-success" onclick="backupDatabase()">
                                <i class="fas fa-download"></i>
                                Create Backup
                            </button>
                            <div class="form-help">Create a backup of the current database</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Restore Database</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="restoreFile" accept=".sql" style="display: none;" onchange="handleRestoreFile(this)">
                                <button type="button" class="btn-action btn-secondary" onclick="document.getElementById('restoreFile').click()">
                                    <i class="fas fa-upload"></i>
                                    Select Backup File
                                </button>
                            </div>
                            <div class="form-help">Restore database from a backup file (.sql)</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i>
                    Save All Settings
                </button>
            </div>
        </form>
    </div>

    <script>
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

        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.tab-content').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected section
            document.getElementById(sectionName).classList.add('active');

            // Add active class to clicked tab button
            event.target.closest('.tab-button').classList.add('active');
        }

        function backupDatabase() {
            if (confirm('Are you sure you want to create a database backup? This may take a few moments.')) {
                window.location.href = '../controllers/backup_database.php';
            }
        }

        function handleRestoreFile(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (confirm('WARNING: Restoring the database will overwrite all current data. Are you absolutely sure you want to proceed?')) {
                    const formData = new FormData();
                    formData.append('restore_file', file);
                    
                    fetch('../controllers/restore_database.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Database restored successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to restore database'));
                        }
                    })
                    .catch(error => {
                        alert('Error: ' + error.message);
                    });
                } else {
                    input.value = '';
                }
            }
        }
    </script>
</body>
</html>
