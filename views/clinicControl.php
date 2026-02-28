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
                <i class="fas fa-calendar-times"></i> Block Entire Day
            </button>
            <button class="btn btn-info" onclick="openHolidayModal()">
                <i class="fas fa-calendar-star"></i> Manage Holidays
            </button>
            <button class="btn btn-danger" onclick="openEmergencyClosureModal()">
                <i class="fas fa-exclamation-triangle"></i> Emergency Closure
            </button>
        </div>
        
        <!-- Active Closures List -->
        <div id="clinicClosureList" style="margin-top: 20px;">
            <h3><i class="fas fa-list"></i> Active Closures</h3>
            <div id="closuresContent" style="margin-top: 15px;">
                <!-- Closures will be loaded here -->
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

<script>
    // Include all JavaScript functions from admin.php for clinic control
    // Copy the relevant functions here...
    
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
        
        // Handle closure duration radio buttons
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
    
    // Include all clinic closure JavaScript functions from admin.php
    // Block Day Modal Functions
    function openBlockDayModal() { document.getElementById('blockDayModal').style.display = 'block'; }
    function closeBlockDayModal() {
        document.getElementById('blockDayModal').style.display = 'none';
        document.getElementById('blockDayForm').reset();
        const customReasonContainer = document.getElementById('blockDayCustomReasonContainer');
        if (customReasonContainer) customReasonContainer.style.display = 'none';
    }
    
    // Holiday Modal Functions
    function openHolidayModal() {
        document.getElementById('holidayModal').style.display = 'block';
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
    function openEmergencyClosureModal() { document.getElementById('emergencyClosureModal').style.display = 'block'; }
    function closeEmergencyClosureModal() {
        document.getElementById('emergencyClosureModal').style.display = 'none';
        document.getElementById('emergencyClosureForm').reset();
        document.getElementById('emergencyEndDateContainer').style.display = 'none';
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
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
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
            showNotification('error', 'Error', 'An error occurred while blocking the day. Please try again.');
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
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
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
            showNotification('error', 'Error', 'An error occurred while adding holiday. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // Load holidays list
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
        .catch(error => console.error('Error loading holidays:', error));
    }
    
    // Delete holiday
    function deleteHoliday(holidayId) {
        if (!confirm('Are you sure you want to delete this holiday?')) return;
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_holiday', holiday_id: holidayId })
        })
        .then(response => response.json())
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
            showNotification('error', 'Error', 'An error occurred while deleting holiday.');
        });
    }
    
    // Handle emergency closure form submission
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
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('warning', 'Emergency Closure Activated', `Clinic closed from ${startDate} to ${requestData.end_date}. ${data.cancelled_count || 0} appointments cancelled. ${notifyPatients ? 'Patients have been notified.' : ''}`);
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
            showNotification('error', 'Error', 'An error occurred while processing emergency closure. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // Load clinic closures list
    function loadClinicClosures() {
        fetch('../controllers/get_clinic_closures.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('closuresContent');
            if (!container) return;
            
            if (data.success && data.closures && data.closures.length > 0) {
                let html = '<div style="display: grid; gap: 10px;">';
                data.closures.forEach(closure => {
                    const closureTypeBadge = closure.closure_type === 'full_day' ? 
                        '<span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Full Day</span>' :
                        '<span style="background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px;">No New Appointments</span>';
                    
                    html += `
                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>${closure.date}</strong> - ${closure.reason}
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
        .catch(error => console.error('Error loading clinic closures:', error));
    }
    
    // Remove closure
    function removeClosure(date) {
        if (!confirm(`Are you sure you want to remove the closure for ${date}?`)) return;
        
        fetch('../controllers/manage_clinic_closure.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_closure', date: date })
        })
        .then(response => response.json())
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
            showNotification('error', 'Error', 'An error occurred while removing closure.');
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

