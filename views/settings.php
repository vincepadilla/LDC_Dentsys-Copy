<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

// Only allow logged in super admins
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
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

// Prepare QR preview paths (resolve relative URLs for views/)
$gcash_qr_stored = isset($settingsData['gcash_qr_code']) ? trim((string)$settingsData['gcash_qr_code']) : '';
$maya_qr_stored = isset($settingsData['maya_qr_code']) ? trim((string)$settingsData['maya_qr_code']) : '';
$gcash_qr_src = '';
$maya_qr_src = '';
if (!empty($gcash_qr_stored)) {
    $gcash_qr_src = (strpos($gcash_qr_stored, 'uploads/') === 0) ? ('../' . $gcash_qr_stored) : $gcash_qr_stored;
}
if (!empty($maya_qr_stored)) {
    $maya_qr_src = (strpos($maya_qr_stored, 'uploads/') === 0) ? ('../' . $maya_qr_stored) : $maya_qr_stored;
}

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

        .maintenance-session-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 30000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .maintenance-session-modal.is-open {
            display: flex;
        }

        .maintenance-session-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
        }

        .maintenance-session-modal__dialog {
            position: relative;
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            border: 1px solid #e5e7eb;
        }

        .maintenance-session-modal__dialog h3 {
            margin: 0 0 12px;
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .maintenance-session-modal__dialog p {
            margin: 0 0 24px;
            font-size: 15px;
            line-height: 1.5;
            color: #4b5563;
        }

        .maintenance-session-modal__actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .maintenance-session-modal__actions button {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .maintenance-session-modal__btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }

        .maintenance-session-modal__btn-cancel:hover {
            background: #e5e7eb;
        }

        .maintenance-session-modal__btn-confirm {
            background: #48A6A7;
            color: #fff;
        }

        .maintenance-session-modal__btn-confirm:hover {
            background: #3d8e90;
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

        .coming-soon-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 20000;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #f59e0b;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
            max-width: 420px;
            animation: toastIn 0.28s ease-out;
        }

        .coming-soon-toast.hide {
            animation: toastOut 0.25s ease-in forwards;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateY(-12px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes toastOut {
            from {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            to {
                opacity: 0;
                transform: translateY(-8px) scale(0.98);
            }
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
            <h5 style="text-align: center; font-size: 18px; font-weight: 600; color: #fff; margin-bottom: 10px;">Admin Control Center</h5>
        </div>
        <nav class="sidebar-nav">
            <a href="userControl.php">
                <i class="fas fa-users-cog"></i>
                <span class="sidebar-text">User Control</span>
            </a>
            <a href="clinicControl.php">
                <i class="fas fa-building"></i>
                <span class="sidebar-text">Clinic Control</span>
            </a> 
            <a href="edit_content.php">
                <i class="fas fa-edit"></i>
                <span class="sidebar-text">Edit Content</span>
            </a>
            <a href="settings.php" class="active">
                <i class="fas fa-cog"></i>
                <span class="sidebar-text">Settings</span>
            </a>
            <a href="admin.php">
                <i class="fa-solid fa-arrow-left"></i>
                <span class="sidebar-text">Back</span>
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
            
        </div>

        <form id="settingsForm" action="../controllers/update_settings.php" method="POST" enctype="multipart/form-data" novalidate
              data-maintenance-was-on="<?php echo ($settingsData['maintenance_mode'] == '1') ? '1' : '0'; ?>">
            <div class="content-area">
                <div id="settingsAlertContainer">
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
                </div>

                <!-- Section Tabs -->
                <div class="section-tabs">
                    <button type="button" class="tab-button active" onclick="showSection('appointment', this)">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointment</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('payment', this)">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Payment</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('maintenance', this)">
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

                    <!-- GCash Settings Card -->
                    <div class="section-header" style="margin-top: 10px;">
                        <div class="section-title">
                            <i class="fas fa-wallet"></i>
                            GCash Settings
                        </div>
                        <div class="section-description">
                            Configure GCash account details and QR code for payments
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">GCash Account Name</label>
                            <input type="text" name="gcash_account_name" class="form-input"
                                   value="<?php echo isset($settingsData['gcash_account_name']) ? htmlspecialchars($settingsData['gcash_account_name']) : ''; ?>"
                                   placeholder="e.g., Juan Dela Cruz">
                            <div class="form-help">Name that appears on your GCash account</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">GCash Account Number</label>
                            <input type="text" name="gcash_account_number" class="form-input"
                                   value="<?php echo isset($settingsData['gcash_account_number']) ? htmlspecialchars($settingsData['gcash_account_number']) : ''; ?>"
                                   placeholder="e.g., 09XXXXXXXXX" maxlength="11" pattern="\d{11}" inputmode="numeric" title="GCash number must be exactly 11 digits">
                            <div class="form-help">Mobile number linked to your GCash</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Upload GCash QR Code</label>
                            <input type="file" name="gcash_qr_code" accept=".jpg,.jpeg,.png" class="form-input">
                            <div class="form-help">Accepts JPG or PNG. Max 5 MB.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Current GCash QR Preview</label>
                            <?php if (!empty($gcash_qr_src)): ?>
                                <img id="gcashQrPreview" src="<?php echo htmlspecialchars($gcash_qr_src); ?>?v=<?php echo time(); ?>" alt="GCash QR Code" style="max-width: 220px; border: 1px solid #e5e7eb; border-radius: 10px; padding: 6px; background: #fff;">
                            <?php else: ?>
                                <div class="form-help" id="gcashQrPreviewHelp">No QR uploaded yet</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Maya Settings Card -->
                    <div class="section-header" style="margin-top: 10px;">
                        <div class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Maya Settings
                        </div>
                        <div class="section-description">
                            Configure Maya account details and QR code for payments
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Maya Account Name</label>
                            <input type="text" name="maya_account_name" class="form-input"
                                   value="<?php echo isset($settingsData['maya_account_name']) ? htmlspecialchars($settingsData['maya_account_name']) : ''; ?>"
                                   placeholder="e.g., Juan Dela Cruz">
                            <div class="form-help">Name that appears on your Maya account</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Maya Account Number</label>
                            <input type="text" name="maya_account_number" class="form-input"
                                   value="<?php echo isset($settingsData['maya_account_number']) ? htmlspecialchars($settingsData['maya_account_number']) : ''; ?>"
                                   placeholder="e.g., 09XXXXXXXXX" maxlength="11" pattern="\d{11}" inputmode="numeric" title="Maya number must be exactly 11 digits">
                            <div class="form-help">Mobile number linked to your Maya</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Upload Maya QR Code</label>
                            <input type="file" name="maya_qr_code" accept=".jpg,.jpeg,.png" class="form-input">
                            <div class="form-help">Accepts JPG or PNG. Max 5 MB.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Current Maya QR Preview</label>
                            <?php if (!empty($maya_qr_src)): ?>
                                <img id="mayaQrPreview" src="<?php echo htmlspecialchars($maya_qr_src); ?>?v=<?php echo time(); ?>" alt="Maya QR Code" style="max-width: 220px; border: 1px solid #e5e7eb; border-radius: 10px; padding: 6px; background: #fff;">
                            <?php else: ?>
                                <div class="form-help" id="mayaQrPreviewHelp">No QR uploaded yet</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Email & Notification Settings -->
                
                <!-- User & Security Settings -->
            
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
                                <button type="button" class="btn-action btn-secondary" onclick="showComingSoonRestoreAlert()">
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

    <div id="maintenanceSessionModal" class="maintenance-session-modal" aria-hidden="true">
        <div class="maintenance-session-modal__backdrop" data-maint-modal-dismiss="1"></div>
        <div class="maintenance-session-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="maintenanceSessionModalTitle">
            <h3 id="maintenanceSessionModalTitle">Maintenance mode</h3>
            <p>There are users currently in session. Are you sure you want to enable Maintenance Mode?</p>
            <div class="maintenance-session-modal__actions">
                <button type="button" class="maintenance-session-modal__btn-cancel" id="maintenanceSessionModalCancel">Cancel</button>
                <button type="button" class="maintenance-session-modal__btn-confirm" id="maintenanceSessionModalConfirm">Confirm</button>
            </div>
        </div>
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

        function showSection(sectionName, buttonEl = null) {
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
            if (buttonEl) {
                buttonEl.classList.add('active');
            }

            // Persist current tab to avoid jumping back to first tab
            try {
                localStorage.setItem('settings_active_tab', sectionName);
            } catch (e) {}
        }

        function renderSettingsAlert(type, message) {
            const container = document.getElementById('settingsAlertContainer');
            if (!container) return;

            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            container.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="fas ${iconClass}"></i>
                    <span>${message}</span>
                </div>
            `;
        }

        function openMaintenanceSessionConfirmModal() {
            return new Promise((resolve) => {
                const modal = document.getElementById('maintenanceSessionModal');
                const btnConfirm = document.getElementById('maintenanceSessionModalConfirm');
                const btnCancel = document.getElementById('maintenanceSessionModalCancel');
                const backdrop = modal ? modal.querySelector('[data-maint-modal-dismiss="1"]') : null;
                if (!modal || !btnConfirm || !btnCancel) {
                    resolve(false);
                    return;
                }

                const finish = (value) => {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    btnConfirm.removeEventListener('click', onConfirm);
                    btnCancel.removeEventListener('click', onCancel);
                    if (backdrop) {
                        backdrop.removeEventListener('click', onCancel);
                    }
                    resolve(value);
                };

                function onConfirm() {
                    finish(true);
                }
                function onCancel() {
                    finish(false);
                }

                btnConfirm.addEventListener('click', onConfirm);
                btnCancel.addEventListener('click', onCancel);
                if (backdrop) {
                    backdrop.addEventListener('click', onCancel);
                }
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Restore previously active tab if available
            try {
                const activeTab = localStorage.getItem('settings_active_tab');
                if (activeTab) {
                    const button = document.querySelector(`.tab-button[onclick*="'${activeTab}'"]`);
                    if (button && document.getElementById(activeTab)) {
                        showSection(activeTab, button);
                    }
                }
            } catch (e) {}

            // AJAX save to avoid full page refresh and keep tab state
            const settingsForm = document.getElementById('settingsForm');
            if (!settingsForm) return;

            // Local instant preview for QR uploads
            const gcashFileInput = settingsForm.querySelector('input[name="gcash_qr_code"]');
            const mayaFileInput = settingsForm.querySelector('input[name="maya_qr_code"]');
            const gcashNumberInput = settingsForm.querySelector('input[name="gcash_account_number"]');
            const mayaNumberInput = settingsForm.querySelector('input[name="maya_account_number"]');
            function setPreview(fileInput, imgId, helpId) {
                const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                const img = document.getElementById(imgId);
                const help = document.getElementById(helpId);
                if (!file) return;
                const validTypes = ['image/jpeg', 'image/png'];
                if (!validTypes.includes(file.type)) return;
                const url = URL.createObjectURL(file);
                if (img) {
                    img.src = url;
                    img.style.display = '';
                }
                if (help) {
                    help.style.display = 'none';
                }
            }
            if (gcashFileInput) {
                gcashFileInput.addEventListener('change', function() {
                    setPreview(this, 'gcashQrPreview', 'gcashQrPreviewHelp');
                });
            }
            if (mayaFileInput) {
                mayaFileInput.addEventListener('change', function() {
                    setPreview(this, 'mayaQrPreview', 'mayaQrPreviewHelp');
                });
            }
            // Enforce 11-digit numeric only for account numbers
            function enforceElevenDigits(el) {
                if (!el) return;
                el.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
                });
                el.addEventListener('blur', function() {
                    if (this.value.length > 0 && this.value.length !== 11) {
                        this.setCustomValidity('Number must be exactly 11 digits');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
            enforceElevenDigits(gcashNumberInput);
            enforceElevenDigits(mayaNumberInput);

            settingsForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = settingsForm.querySelector('.btn-save');
                const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

                const wasMaintenanceOn = settingsForm.dataset.maintenanceWasOn === '1';
                const maintenanceCb = settingsForm.querySelector('[name="maintenance_mode"]');
                const nowMaintenanceOn = !!(maintenanceCb && maintenanceCb.checked);

                if (nowMaintenanceOn && !wasMaintenanceOn && settingsForm.dataset.maintenanceSessionBypass !== '1') {
                    try {
                        const sessRes = await fetch('userControl.php?ajax_in_session_count=1', {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' }
                        });
                        const sessData = await sessRes.json().catch(() => ({}));
                        if (!sessRes.ok || !sessData.success) {
                            throw new Error('session check failed');
                        }
                        const inSession = parseInt(sessData.in_session_count, 10) || 0;
                        if (inSession > 0) {
                            const confirmed = await openMaintenanceSessionConfirmModal();
                            if (!confirmed) {
                                return;
                            }
                            settingsForm.dataset.maintenanceSessionBypass = '1';
                        }
                    } catch (err) {
                        renderSettingsAlert('error', 'Could not verify active user sessions. Please try again.');
                        return;
                    }
                }

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                }

                try {
                    const formData = new FormData(settingsForm);
                    const response = await fetch(settingsForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json().catch(() => ({}));
                    if (response.ok && data.success) {
                        renderSettingsAlert('success', data.message || 'Settings updated successfully!');
                        const cb = settingsForm.querySelector('[name="maintenance_mode"]');
                        settingsForm.dataset.maintenanceWasOn = (cb && cb.checked) ? '1' : '0';
                        delete settingsForm.dataset.maintenanceSessionBypass;
                    } else {
                        renderSettingsAlert('error', data.message || 'Some settings could not be updated.');
                        delete settingsForm.dataset.maintenanceSessionBypass;
                    }
                } catch (error) {
                    renderSettingsAlert('error', 'Failed to save settings. Please try again.');
                    delete settingsForm.dataset.maintenanceSessionBypass;
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                }
            });
        });

        function backupDatabase() {
            if (confirm('Are you sure you want to create a database backup? This may take a few moments.')) {
                window.location.href = '../controllers/backup_database.php';
            }
        }

        function handleRestoreFile(input) {
            if (input.files && input.files[0]) {
                showComingSoonRestoreAlert();
                // Reset file input to avoid accidental form state issues
                input.value = '';
            }
        }

        function showComingSoonRestoreAlert() {
            const oldToast = document.querySelector('.coming-soon-toast');
            if (oldToast) {
                oldToast.remove();
            }

            const toast = document.createElement('div');
            toast.className = 'coming-soon-toast';
            toast.innerHTML = `
                <i class="fas fa-tools"></i>
                <div>
                    <strong>Restore Database - Coming Soon</strong><br>
                    <span>This feature is temporarily disabled to avoid system errors.</span>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) toast.remove();
                }, 260);
            }, 2600);
        }
    </script>
</body>
</html>
