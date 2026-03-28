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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Control - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Notification System Styles - Same as admin.php */
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
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .notification.success { border-left: 4px solid #10B981; }
        .notification.warning { border-left: 4px solid #F59E0B; }
        .notification.error { border-left: 4px solid #EF4444; }
        .notification.info { border-left: 4px solid #3B82F6; }
        
        /* Hide sidebar in clinic control page */
        .sidebar {
            display: none !important;
        }
        
        .menu-toggle {
            display: none !important;
        }
        
        /* Full width content without sidebar */
        .main-content {
            margin-left: 0 !important;
            animation: pageFadeIn 0.3s ease-in-out;
        }
        @keyframes pageFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Back button styling */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            text-decoration: none;
        }
        
        .back-button:hover {
            background: #3d8e90;
            transform: translateX(-3px);
        }
        
        .back-button i {
            font-size: 14px;
        }

        /* Clinic Control layout refinement */
        .main-content .container {
            max-width: 1040px;
            margin: 0 auto;
            padding: 24px 20px 40px;
        }

        .main-content h2 {
            font-weight: 600;
            letter-spacing: 0.08em;
            color: #111827;
            margin-bottom: 6px;
        }

        .main-content h2 i {
            margin-right: 8px;
            color: var(--primary-color);
        }

        /* Inputs, helpers, and modern field styling */
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .field-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #0f172a;
            font-size: 0.95rem;
        }
        .field-hint {
            color: #64748b;
            font-size: 12px;
            margin-top: 2px;
        }
        
        

        /* Option cards (for closure type) */
        .option-group {
            display: grid;
            gap: 8px;
        }
        .option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: transparent;
            transition: border-color 0.12s ease;
        }
        .option:hover {
            border-color: #cbd5e1;
        }
        .option.selected {
            border-color: #6366f1;
            box-shadow: none;
        }
        .option .option-body {
            display: grid;
            gap: 2px;
        }
        .option .option-title {
            font-weight: 600;
            color: #0f172a;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            line-height: 1.2;
            font-size: 0.92rem;
        }
        .option .option-desc {
            color: #64748b;
            font-size: 12px;
            line-height: 1.15;
        }
        .option input[type="radio"] {
            margin: 0 6px 0 0;
            align-self: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

        /* Toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            vertical-align: middle;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e5e7eb;
            transition: .2s;
            border-radius: 999px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .2s;
            border-radius: 50%;
            box-shadow: 0 1px 2px rgba(0,0,0,.2);
        }
        input:checked + .slider {
            background-color: #34d399;
        }
        input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* Info banner */
        .info-banner {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #075985;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            display: grid;
            grid-template-columns: 20px 1fr;
            gap: 10px;
        }
        .info-banner i {
            color: #0284c7;
            margin-top: 1px;
        }

        /* Modern card look for the Active Closures section */
        #clinicClosureList {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px 18px 20px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(148, 163, 184, 0.4);
        }

        #clinicClosureList h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #clinicClosureList h3 i {
            color: #6366f1;
        }

        /* Improved button look (local override) */
        .main-content .btn {
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 9px 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.14);
            transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
        }

        .main-content .btn i {
            font-size: 0.95rem;
        }

        .main-content .btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
        }

        .main-content .btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.14);
        }

        /* Shared modal styling (overlay + content) */
        .clinic-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.45);
            z-index: 9999;
            padding: 16px;
            backdrop-filter: blur(2px);
        }

        .clinic-modal .clinic-modal-content {
            width: 100%;
            max-height: 88vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.55);
            box-shadow: 0 22px 45px rgba(15, 23, 42, 0.35);
            background: #ffffff;
        }

        .clinic-modal .clinic-modal-body {
            padding: 14px 16px 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Two-column layout for Block Day modal */
        .blockday-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: stretch;
            grid-auto-rows: 1fr;
        }
        .blockday-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .blockday-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #0f172a;
            margin: 0 0 10px 0;
            font-size: 0.95rem;
        }
        .blockday-scroll {
            flex: 1 1 auto;
            overflow: auto;
            padding: 4px 4px 0;
            margin: 0 4px;
        }
        .blockday-actions {
            position: sticky;
            bottom: 0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            background: #ffffff;
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            padding: 12px 4px 12px;
            margin-top: 10px;
        }

        @media (max-width: 720px) {
            .blockday-grid {
                grid-template-columns: 1fr;
            }
        }

        .clinic-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px 10px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            
        }

        .clinic-modal-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clinic-modal-title i {
            color: #4f46e5;
        }

        .clinic-modal-close {
            cursor: pointer;
            border: none;
            background: transparent;
            color: #6b7280;
            font-size: 1.1rem;
            width: 32px;
            height: 32px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.12s ease, color 0.12s ease, transform 0.05s ease;
        }

        .clinic-modal-close:hover {
            background: rgba(15, 23, 42, 0.04);
            color: #111827;
        }

        .clinic-modal-close:active {
            transform: translateY(1px);
        }

        /* Form fields inside modals */
        .clinic-modal-body label strong {
            font-size: 0.9rem;
            color: #111827;
        }

        .clinic-modal-body input[type="date"],
        .clinic-modal-body select,
        .clinic-modal-body textarea {
            border-radius: 10px !important;
            border: 1px solid #d1d5db !important;
            font-size: 0.9rem !important;
        }

        .clinic-modal-body textarea {
            resize: vertical;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .main-content .container {
                padding: 18px 14px 28px;
            }

            .clinic-modal .clinic-modal-content {
                max-width: 100%;
                border-radius: 14px;
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
        <a href="admin.php#dashboard" onclick="return navigateToPage('admin.php#dashboard', this, event)"><i class="fa fa-tachometer"></i> Dashboard</a>
        <a href="admin.php#appointment" onclick="return navigateToPage('admin.php#appointment', this, event)"><i class="fas fa-calendar-check"></i> Appointments</a>
        <a href="admin.php#schedules" onclick="return navigateToPage('admin.php#schedules', this, event)"><i class="fas fa-calendar-days"></i> Time Slots</a>
        <a href="#" class="active"><i class="fas fa-building"></i> Clinic Control</a>
        <a href="admin.php#services" onclick="return navigateToPage('admin.php#services', this, event)"><i class="fa-solid fa-teeth"></i> Services</a>
        <a href="admin.php#patients" onclick="return navigateToPage('admin.php#patients', this, event)"><i class="fa-solid fa-hospital-user"></i> Patients</a>
        <a href="admin.php#treatment" onclick="return navigateToPage('admin.php#treatment', this, event)"><i class="fa-solid fa-notes-medical"></i> History</a>
        <a href="admin.php#dentists" onclick="return navigateToPage('admin.php#dentists', this, event)"><i class="fa-solid fa-user-doctor"></i> Dentists & Staff</a>
        <a href="admin.php#payment" onclick="return navigateToPage('admin.php#payment', this, event)"><i class="fa-solid fa-money-bill"></i> Transactions</a> 
        <a href="admin.php#reports" onclick="return navigateToPage('admin.php#reports', this, event)"><i class="fa-solid fa-square-poll-vertical"></i> Reports</a> 
        <a href="login.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <div class="container">
        <a href="admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fas fa-building"></i> CLINIC CONTROL</h2>
        <p style="color: #6b7280; margin-bottom: 30px;">Manage clinic-wide closures, holidays, and emergency closures.</p>
        
        <!-- Control Buttons -->
        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 30px;">
            <button class="btn btn-warning" onclick="openBlockDayModal()">
                <i class="fa-solid fa-calendar-day"></i> Block Entire Day
            </button>
            <button class="btn btn-danger" onclick="openEmergencyClosureModal()">
                <i class="fas fa-calendar-times"></i> Date Range Closure
            </button>
        </div>
        
        <!-- Active Closures List -->
        <div id="clinicClosureList" style="margin-top: 20px;">
            <h3>
                <i class="fas fa-list"></i> Active Closures
                <button class="btn btn-secondary" style="margin-left: auto;" onclick="removeAllClosures()" title="Remove all active closures">
                    <i class="fas fa-trash-alt"></i> Remove All
                </button>
            </h3>
            <div id="closuresContent" style="margin-top: 15px;">
                <!-- Closures will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Block Entire Day Modal -->
<div id="blockDayModal" class="modal clinic-modal" style="display:none;">
    <div class="modal-content clinic-modal-content" style="max-width: 820px;">
        <div class="clinic-modal-header">
            <div class="clinic-modal-title">
            <i class="fa-solid fa-calendar-day"></i>
                Block Entire Day
            </div>
            <button type="button" class="clinic-modal-close" onclick="closeBlockDayModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="clinic-modal-body">
        <form id="blockDayForm" onsubmit="handleBlockDaySubmit(event)">

            <div class="blockday-scroll">
                <div class="blockday-grid">
                    <div class="blockday-card">
                        <div class="blockday-section-title">
                            <i class="fas fa-calendar-day"></i> Date & Type
                        </div>
                        <div class="field" style="margin-bottom: 12px;">
                            <label for="blockDayDate" class="field-label">
                                <i class="fa-solid fa-calendar"></i>
                                Select Date
                                <span class="badge"><i class="fa-regular fa-clock"></i> Clinic timezone</span>
                            </label>
                            <div class="input-affix">
                                <input type="date" id="blockDayDate" name="closure_date" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="field-hint">Patients with appointments on this date may be notified.</div>
                        </div>
                        <div class="field" style="margin-bottom: 4px;">
                            <div class="field-label">
                                <i class="fa-solid fa-toggle-off"></i>
                                Closure Type
                            </div>
                        <div class="option-group" id="closureTypeOptions" style="margin-top: 6px;">
                            <label class="option" data-value="full_day">
                                <input type="radio" name="closure_type" value="full_day" checked>
                                <div class="option-body">
                                    <div class="option-title">Full Day Closure</div>
                                    <div class="option-desc">Blocks all appointment activity for the day.</div>
                                </div>
                            </label>
                            <label class="option" data-value="no_new_appointments">
                                <input type="radio" name="closure_type" value="no_new_appointments">
                                <div class="option-body">
                                    <div class="option-title">No New Appointments</div>
                                    <div class="option-desc">Existing appointments remain; only new bookings are blocked.</div>
                                </div>
                            </label>
                        </div>
                            
                        </div>
                    </div>
                    <div class="blockday-card">
                        <div class="blockday-section-title">
                            <i class="fas fa-align-left"></i> Reason & Notifications
                        </div>
                        <div class="field" style="margin-bottom: 12px;">
                            <label for="blockDayReason" class="field-label">
                                <i class="fa-solid fa-list-check"></i>
                                Reason
                            </label>
                            <div class="input-affix">
                                <select id="blockDayReason" name="reason" required>
                                    <option value="">Select Reason</option>
                                    <option value="Holiday">Holiday</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Staff Training">Staff Training</option>
                                    <option value="Emergency">Emergency</option>
                                    <option value="Weather">Weather Conditions</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div id="blockDayCustomReasonContainer" style="margin-bottom: 12px; display: none;">
                            <label for="blockDayCustomReason" class="field-label">
                                <i class="fa-regular fa-pen-to-square"></i>
                                Custom Reason (if Other)
                            </label>
                            <textarea id="blockDayCustomReason" name="custom_reason" rows="4" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; margin-top: 5px; background:#fff;" placeholder="Enter custom reason..."></textarea>
                        </div>
                        <div class="info-banner" style="margin-bottom: 10px;">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>
                                Blocking the day prevents new bookings for the selected date. Existing appointments are not cancelled automatically.
                            </div>
                        </div>
                        <div class="field" style="margin-bottom: 0;">
                            <div class="field-label" style="justify-content: space-between;">
                                <span style="display:inline-flex; align-items:center; gap:8px;">
                                    <i class="fa-regular fa-bell"></i>
                                    Notify patients with appointments
                                </span>
                                <label class="switch">
                                    <input type="checkbox" id="notifyPatients" name="notify_patients" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="field-hint">A message will be queued for all affected patients.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="blockday-actions">
                <button type="button" class="btn btn-secondary" onclick="closeBlockDayModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Block Day</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- Emergency Closure Modal -->
<div id="emergencyClosureModal" class="modal clinic-modal" style="display:none;">
    <div class="modal-content clinic-modal-content" style="max-width: 820px;">
        <div class="clinic-modal-header">
            <div class="clinic-modal-title">
            <i class="fas fa-calendar-times"></i>
                Date Range Closure
            </div>
            <button type="button" class="clinic-modal-close" onclick="closeEmergencyClosureModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="clinic-modal-body">
        <form id="emergencyClosureForm" onsubmit="handleEmergencyClosureSubmit(event)">
            <div class="blockday-scroll">
                <div class="blockday-grid">
                    <div class="blockday-card">
                        <div class="blockday-section-title">
                            <i class="fas fa-calendar-range"></i> Dates
                        </div>
                        <div class="field" style="margin-bottom: 12px;">
                            <label for="emergencyStartDate" class="field-label">
                                <i class="fa-solid fa-calendar"></i>
                                Start Date
                            </label>
                            <div class="input-affix">
                                <input type="date" id="emergencyStartDate" name="start_date" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="field-hint">Select the first day of the closure.</div>
                        </div>
                        <div class="field" id="emergencyEndDateContainer" style="margin-bottom: 0;">
                            <label for="emergencyEndDate" class="field-label">
                                <i class="fa-solid fa-calendar"></i>
                                End Date
                            </label>
                            <div class="input-affix">
                                <input type="date" id="emergencyEndDate" name="end_date" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="field-hint">Must be after the start date.</div>
                        </div>
                    </div>
                    <div class="blockday-card">
                        <div class="blockday-section-title">
                            <i class="fas fa-align-left"></i> Reason & Notifications
                        </div>
                        <div class="field" style="margin-bottom: 12px;">
                            <label for="emergencyReason" class="field-label">
                                <i class="fa-solid fa-list-check"></i>
                                Reason
                            </label>
                            <textarea id="emergencyReason" name="reason" rows="6" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; margin-top: 5px; background:#fff;" placeholder="Describe the emergency situation..."></textarea>
                        </div>
                        <div class="info-banner" style="margin-bottom: 10px;">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>
                                This will mark the selected date range as clinic closed and notify affected patients. Appointments are not cancelled automatically.
                            </div>
                        </div>
                        <div class="field" style="margin-bottom: 0;">
                            <div class="field-label" style="justify-content: space-between;">
                                <span style="display:inline-flex; align-items:center; gap:8px;">
                                    <i class="fa-regular fa-bell"></i>
                                    Notify all affected patients
                                </span>
                                <label class="switch">
                                    <input type="checkbox" id="emergencyNotifyPatients" name="notify_patients" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="field-hint">Immediate notification will be sent to all affected patients.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="blockday-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEmergencyClosureModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Apply Date Range Closure</button>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
    // Include all JavaScript functions from admin.php for clinic control
    // Copy the relevant functions here...

    // Robust JSON helper: if PHP returns HTML (warnings/redirect), show the raw text
    function fetchJson(url, options = {}) {
        return fetch(url, options).then(async (res) => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                const preview = String(text).slice(0, 220).replace(/\s+/g, ' ').trim();
                throw new Error(`Invalid JSON from ${url}. HTTP ${res.status}. Response starts with: ${preview}`);
            }
        });
    }
    
    // Notification System - Same as admin.php
    function showNotification(type, title, message, iconHtml = '', duration = 5000) {
        const container = document.getElementById('notificationContainer');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icon = iconHtml || (type === 'success' ? '<i class="fas fa-check-circle"></i>' :
                      type === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' :
                      type === 'error' ? '<i class="fas fa-times-circle"></i>' :
                      '<i class="fas fa-info-circle"></i>');
        
        notification.innerHTML = `
            <div style="flex-shrink: 0; font-size: 24px; color: ${type === 'success' ? '#10B981' : type === 'warning' ? '#F59E0B' : type === 'error' ? '#EF4444' : '#3B82F6'};">
                ${icon}
            </div>
            <div style="flex-grow: 1;">
                <div style="font-weight: 600; color: #111827; margin-bottom: 5px;">${title}</div>
                <div style="font-size: 14px; color: #6B7280;">${message}</div>
            </div>
            <button onclick="this.parentElement.remove()" style="background: transparent; border: none; color: #9CA3AF; cursor: pointer; font-size: 18px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideInRight 0.4s ease-out reverse';
            setTimeout(() => notification.remove(), 400);
        }, duration);
    }
    
    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('active');
    }
    
    // Navigate back to admin page with animation
    function navigateBack(event) {
        if (event) event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        setTimeout(() => {
            window.location.href = 'admin.php';
        }, 300);
        return false;
    }
    
    // Load closures on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadClinicClosures();
        
        // Handle reason select change
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

        // Enhance closure type option cards (selected state)
        const optionContainer = document.getElementById('closureTypeOptions');
        if (optionContainer) {
            const optionLabels = Array.from(optionContainer.querySelectorAll('label.option'));
            const syncSelected = () => {
                optionLabels.forEach(l => {
                    const input = l.querySelector('input[type="radio"]');
                    if (input && input.checked) {
                        l.classList.add('selected');
                    } else {
                        l.classList.remove('selected');
                    }
                });
            };
            optionLabels.forEach(l => {
                const input = l.querySelector('input[type="radio"]');
                if (input) {
                    input.addEventListener('change', syncSelected);
                }
                // Also allow clicking anywhere on the label to select
                l.addEventListener('click', function(e) {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });
            // Initialize visual state
            syncSelected();
        }

        // Ensure end date is visible and required for date range closure
        const endDateContainer = document.getElementById('emergencyEndDateContainer');
        const endDateInput = document.getElementById('emergencyEndDate');
        const startDateInput = document.getElementById('emergencyStartDate');
        if (endDateContainer && endDateInput) {
            endDateContainer.style.display = 'block';
            endDateInput.setAttribute('required', 'required');
        }
        // Ensure end date cannot be before or equal to start date (must be next day or later)
        if (startDateInput && endDateInput) {
            const enforceEndMin = () => {
                const startVal = startDateInput.value;
                if (!startVal) return;
                const d = new Date(startVal);
                // Add 1 day
                d.setDate(d.getDate() + 1);
                const yyyy = d.getFullYear();
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                const nextDay = `${yyyy}-${mm}-${dd}`;
                endDateInput.min = nextDay;
                if (endDateInput.value && endDateInput.value < nextDay) {
                    endDateInput.value = nextDay;
                }
            };
            // On load (in case start date already selected)
            enforceEndMin();
            // On change
            startDateInput.addEventListener('change', enforceEndMin);
        }
    });
    
    // Include all clinic closure JavaScript functions from admin.php
    // Block Day Modal Functions
    function openBlockDayModal() { document.getElementById('blockDayModal').style.display = 'flex'; }
    function closeBlockDayModal() {
        document.getElementById('blockDayModal').style.display = 'none';
        document.getElementById('blockDayForm').reset();
        const customReasonContainer = document.getElementById('blockDayCustomReasonContainer');
        if (customReasonContainer) customReasonContainer.style.display = 'none';
    }
    
    // Holiday Modal Functions
    function openHolidayModal() {
        document.getElementById('holidayModal').style.display = 'flex';
        loadHolidays();
    }
    function closeHolidayModal() {
        document.getElementById('holidayModal').style.display = 'none';
        hideAddHolidayForm();
    }
    function showAddHolidayForm() { document.getElementById('addHolidayForm').style.display = 'block'; }
    function hideAddHolidayForm() {
        document.getElementById('addHolidayForm').style.display = 'none';
        document.getElementById('holidayForm').reset();
    }
    
    // Emergency Closure Modal Functions
    function openEmergencyClosureModal() { document.getElementById('emergencyClosureModal').style.display = 'flex'; }
    function closeEmergencyClosureModal() {
        document.getElementById('emergencyClosureModal').style.display = 'none';
        document.getElementById('emergencyClosureForm').reset();
        const endDateContainer = document.getElementById('emergencyEndDateContainer');
        if (endDateContainer) endDateContainer.style.display = 'block';
    }
    
    // Handle block day form submission
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
        
        fetchJson('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(data => {
            if (data.success) {
                showNotification('success', 'Day Blocked Successfully', `Date ${closureDate} has been blocked. ${notifyPatients ? 'Patients have been notified.' : ''}`);
                closeBlockDayModal();
                loadClinicClosures();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to block day. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', error?.message || 'An error occurred while blocking the day. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // Handle holiday form submission
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
        
        fetchJson('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(data => {
            if (data.success) {
                showNotification('success', 'Holiday Added', `Holiday "${requestData.holiday_name}" has been added.`);
                hideAddHolidayForm();
                loadHolidays();
                loadClinicClosures();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to add holiday. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', error?.message || 'An error occurred while adding holiday. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // Load holidays list
    function loadHolidays() {
        fetchJson('../controllers/get_holidays.php')
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
            showNotification('error', 'Error', error?.message || 'Failed to load holidays.');
        });
    }
    
    // Delete holiday
    function deleteHoliday(holidayId) {
        if (!confirm('Are you sure you want to delete this holiday?')) return;
        
        fetchJson('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_holiday', holiday_id: holidayId })
        })
        .then(data => {
            if (data.success) {
                showNotification('success', 'Holiday Deleted', 'Holiday has been deleted successfully.');
                loadHolidays();
                loadClinicClosures();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to delete holiday.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', error?.message || 'An error occurred while deleting holiday.');
        });
    }
    
    // Handle emergency closure form submission
    function handleEmergencyClosureSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        const startDate = formData.get('start_date');
        const endDate = formData.get('end_date');
        const reason = formData.get('reason');
        const notifyPatients = formData.get('notify_patients') === 'on';

        // Basic validation: end date must be present and >= start date
        if (!endDate) {
            showNotification('error', 'Error', 'Please select an end date.');
            return;
        }
        // Must be strictly after start date
        if (startDate && endDate && endDate <= startDate) {
            showNotification('error', 'Error', 'End date must be after start date.');
            return;
        }
        
        if (!confirm('This will mark the selected dates as clinic closed and notify affected patients. Appointments will NOT be cancelled automatically. Proceed?')) {
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Emergency Closure...';
        
        const requestData = {
            action: 'emergency_closure',
            start_date: startDate,
            end_date: endDate,
            reason: reason,
            notify_patients: notifyPatients
        };
        
        fetchJson('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(data => {
            if (data.success) {
                showNotification('warning', 'Date Range Closure Applied', `Clinic closed from ${startDate} to ${requestData.end_date}. ${notifyPatients ? 'Patients have been notified.' : ''}`);
                closeEmergencyClosureModal();
                loadClinicClosures();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to process emergency closure. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', error?.message || 'An error occurred while processing emergency closure. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // Load clinic closures list
    function loadClinicClosures() {
        fetchJson('../controllers/get_clinic_closures.php')
        .then(data => {
            const container = document.getElementById('closuresContent');
            if (!container) return;
            
            if (data.success && data.closures && data.closures.length > 0) {
                let html = '<div style="display: grid; gap: 10px;">';
                data.closures.forEach(closure => {
                    // Map "Emergency:" prefix to "Date Range Closure:" for display
                    const displayReason = String(closure.reason || '').replace(/^\s*Emergency\s*:/i, 'Date Range Closure:');
                    const closureTypeBadge = closure.closure_type === 'full_day' ? 
                        '<span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Full Day</span>' :
                        '<span style="background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px;">No New Appointments</span>';
                    
                    html += `
                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>${closure.date}</strong> - ${displayReason}
                                ${closureTypeBadge}
                            </div>
                            <button class="btn btn-sm btn-secondary" onclick="removeClosure('${closure.date}')" title="Remove Closure">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p style="color: #6c757d; margin: 0; padding: 20px; background: white; border-radius: 8px; text-align: center;">No active closures.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading clinic closures:', error);
            showNotification('error', 'Error', error?.message || 'Failed to load clinic closures.');
        });
    }
    
    // Remove closure
    function removeClosure(date) {
        if (!confirm(`Are you sure you want to remove the closure for ${date}?`)) return;
        
        fetchJson('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_closure', date: date })
        })
        .then(data => {
            if (data.success) {
                showNotification('success', 'Closure Removed', `Closure for ${date} has been removed.`);
                loadClinicClosures();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to remove closure.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', error?.message || 'An error occurred while removing closure.');
        });
    }

    // Remove all active closures
    function removeAllClosures() {
        if (!confirm('Are you sure you want to remove ALL active closures?')) return;

        fetchJson('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_all_closures' })
        })
        .then(data => {
            if (data.success) {
                const count = typeof data.removed_count === 'number' ? data.removed_count : 0;
                showNotification('success', 'All Closures Removed', `Removed ${count} active ${count === 1 ? 'closure' : 'closures'}.`);
                loadClinicClosures();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to remove all closures.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', error?.message || 'An error occurred while removing all closures.');
        });
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('blockDayModal')) closeBlockDayModal();
        if (event.target === document.getElementById('holidayModal')) closeHolidayModal();
        if (event.target === document.getElementById('emergencyClosureModal')) closeEmergencyClosureModal();
    });
</script>

</body>
</html>

