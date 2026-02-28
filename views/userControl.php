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

// Check if status column exists and add it if not
$checkColumn = "SHOW COLUMNS FROM user_account LIKE 'status'";
$columnCheck = mysqli_query($con, $checkColumn);
if (mysqli_num_rows($columnCheck) == 0) {
    mysqli_query($con, "ALTER TABLE user_account ADD COLUMN status ENUM('active', 'blocked') NOT NULL DEFAULT 'active' AFTER role");
}

// Check if last_login column exists and add it if not
$checkLastLogin = "SHOW COLUMNS FROM user_account LIKE 'last_login'";
$lastLoginCheck = mysqli_query($con, $checkLastLogin);
if (mysqli_num_rows($lastLoginCheck) == 0) {
    mysqli_query($con, "ALTER TABLE user_account ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER status");
}

// Get all users with appointment count
// Use COALESCE to handle missing status column gracefully
$usersQuery = "
    SELECT 
        ua.user_id,
        ua.username,
        ua.first_name,
        ua.last_name,
        ua.email,
        ua.phone,
        ua.role,
        ua.created_at,
        COALESCE(ua.status, 'active') as account_status,
        COALESCE(p.patient_id, 'N/A') as patient_id,
        COUNT(DISTINCT a.appointment_id) as appointment_count,
        MAX(a.appointment_date) as last_appointment_date
    FROM user_account ua
    LEFT JOIN patient_information p ON ua.user_id = p.user_id
    LEFT JOIN appointments a ON p.patient_id = a.patient_id
    WHERE ua.role != 'admin'
    GROUP BY ua.user_id, ua.username, ua.first_name, ua.last_name, ua.email, ua.phone, ua.role, ua.created_at, ua.status, p.patient_id
    ORDER BY ua.created_at DESC
";
$usersResult = mysqli_query($con, $usersQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Control - Admin</title>

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
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .notification.success { border-left: 4px solid #10B981; }
        .notification.warning { border-left: 4px solid #F59E0B; }
        .notification.error { border-left: 4px solid #EF4444; }
        .notification.info { border-left: 4px solid #3B82F6; }
        
        /* Hide sidebar in user control page */
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
        
        /* User status badges */
        .status-active {
            background: rgba(42, 157, 143, 0.2);
            color: var(--success);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-blocked {
            background: rgba(231, 111, 81, 0.2);
            color: var(--danger);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .user-row.has-appointments {
            border-left: 3px solid #3b82f6;
        }
        
        .promo-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .promo-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-info {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-success {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-danger {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .btn-secondary {
            background-color: #f1f3f5;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(241, 243, 245, 0.4);
        }
        
        .btn-warning {                  
            background-color: #f59e0b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<div class="main-content">
    <div class="container">
        <a href="admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fas fa-users"></i> USER CONTROL</h2>
        <p style="color: #6b7280; margin-bottom: 30px;">Manage user accounts, view appointment history, and send promotional communications.</p>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 30px;">
            <button class="promo-btn" onclick="openPromotionalEmailModal()">
                <i class="fas fa-paper-plane"></i> Send Promotional Campaign
            </button>
            <button class="btn btn-info" onclick="exportUsersList()">
                <i class="fas fa-download"></i> Export Users List
            </button>
        </div>
        
        <!-- Filter and Search -->
        <div class="filter-container" style="margin-bottom: 20px;">
            <div class="filter-group">
                <label for="filter-user-status"><i class="fas fa-filter"></i> Status:</label>
                <select id="filter-user-status" onchange="filterUsers()">
                    <option value="">All Users</option>
                    <option value="active">Active</option>
                    <option value="blocked">Blocked</option>
                    <option value="has_appointments">Has Appointments</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search-users"><i class="fas fa-search"></i> Search:</label>
                <input type="text" id="search-users" placeholder="Search by name, email..." onkeyup="filterUsers()" style="width: 250px; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 20px;">
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Appointments</th>
                        <th>Last Appointment</th>
                        <th>Account Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php
                    if (mysqli_num_rows($usersResult) > 0) {
                        while ($user = mysqli_fetch_assoc($usersResult)) {
                            $hasAppointments = $user['appointment_count'] > 0;
                            $statusClass = ($user['account_status'] === 'blocked' || $user['account_status'] === 'Blocked') ? 'status-blocked' : 'status-active';
                            $statusText = ($user['account_status'] === 'blocked' || $user['account_status'] === 'Blocked') ? 'Blocked' : 'Active';
                            $rowClass = $hasAppointments ? 'user-row has-appointments' : 'user-row';
                            $lastAppt = $user['last_appointment_date'] ? date('M j, Y', strtotime($user['last_appointment_date'])) : 'N/A';
                            
                            echo "<tr class='{$rowClass}' data-status='{$statusText}' data-search='" . strtolower($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['email']) . "' data-has-appointments='" . ($hasAppointments ? 'yes' : 'no') . "'>";
                            echo "<td>{$user['user_id']}</td>";
                            echo "<td><strong>{$user['first_name']} {$user['last_name']}</strong><br><small style='color: #999;'>@{$user['username']}</small></td>";
                            echo "<td>{$user['email']}</td>";
                            echo "<td>" . ($user['phone'] ? $user['phone'] : 'N/A') . "</td>";
                            echo "<td><span class='badge'>" . ($user['appointment_count'] > 0 ? $user['appointment_count'] : '0') . "</span></td>";
                            echo "<td>{$lastAppt}</td>";
                            echo "<td><span class='{$statusClass}'>{$statusText}</span></td>";
                            echo "<td>";
                            echo "<div style='display: flex; gap: 5px;'>";
                            if ($user['account_status'] !== 'blocked' && $user['account_status'] !== 'Blocked') {
                                echo "<button class='action-btn btn-danger' onclick='blockUser(\"{$user['user_id']}\", \"{$user['first_name']} {$user['last_name']}\")' title='Block User'>";
                                echo "<i class='fas fa-ban'></i>";
                                echo "</button>";
                            } else {
                                echo "<button class='action-btn btn-success' onclick='unblockUser(\"{$user['user_id']}\", \"{$user['first_name']} {$user['last_name']}\")' title='Unblock User'>";
                                echo "<i class='fas fa-check-circle'></i>";
                                echo "</button>";
                            }
                            if ($hasAppointments) {
                                echo "<button class='action-btn btn-info' onclick='viewUserAppointments(\"{$user['user_id']}\")' title='View Appointments'>";
                                echo "<i class='fas fa-calendar'></i>";
                                echo "</button>";
                            }
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align: center; padding: 30px;'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Promotional Email Modal -->
<div id="promotionalEmailModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 900px; width: 95%; max-height: 95vh; display: flex; flex-direction: column;">
        <span class="close" onclick="closePromotionalEmailModal()">&times;</span>
        <h3 style="margin-top: 0; margin-bottom: 15px;"><i class="fas fa-paper-plane"></i> Send Promotional Campaign</h3>
        <form id="promotionalEmailForm" onsubmit="handlePromotionalEmailSubmit(event)" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px; flex-shrink: 0;">
                <div>
                    <label><strong>Recipients:</strong></label>
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <input type="radio" name="recipients" value="all_users" checked>
                            <span><i class="fas fa-users" style="color: #3b82f6;"></i> All Users</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <input type="radio" name="recipients" value="with_appointments">
                            <span><i class="fas fa-calendar-check" style="color: #10b981;"></i> With Appointments</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <input type="radio" name="recipients" value="no_appointments">
                            <span><i class="fas fa-calendar-times" style="color: #f59e0b;"></i> Without Appointments</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label for="promoSubject"><strong>Email Subject:</strong></label>
                    <input type="text" id="promoSubject" name="subject" required placeholder="Enter email subject..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; margin-top: 8px;">
                    
                    <div style="background: #e0f2fe; border: 1px solid #0ea5e9; border-radius: 6px; padding: 10px; margin-top: 12px;">
                        <strong style="color: #0369a1; font-size: 12px;">ℹ️ Info:</strong>
                        <p style="color: #075985; margin: 5px 0 0 0; font-size: 12px; line-height: 1.4;">Promotional emails will be sent to all selected recipients.</p>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 12px; flex: 1; display: flex; flex-direction: column; min-height: 0;">
                <label for="promoMessage"><strong>Email Message:</strong></label>
                <textarea id="promoMessage" name="message" required placeholder="Write your promotional message here..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; margin-top: 8px; font-family: inherit; resize: none; flex: 1; min-height: 150px; max-height: 300px;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; flex-shrink: 0;">
                <button type="button" class="btn btn-secondary" onclick="closePromotionalEmailModal()">Cancel</button>
                <button type="submit" class="promo-btn">
                    <i class="fas fa-paper-plane"></i> Send Campaign
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Notification System
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
    
    // Navigate back to admin
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
    
    // Filter users
    function filterUsers() {
        const selectedStatus = document.getElementById('filter-user-status').value.toLowerCase();
        const searchText = document.getElementById('search-users').value.toLowerCase().trim();
        const rows = document.querySelectorAll('.user-row');
        
        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status').toLowerCase();
            const hasAppointments = row.getAttribute('data-has-appointments');
            const searchData = row.getAttribute('data-search');
            
            let matchesStatus = true;
            if (selectedStatus === 'active') {
                matchesStatus = rowStatus === 'active';
            } else if (selectedStatus === 'blocked') {
                matchesStatus = rowStatus === 'blocked';
            } else if (selectedStatus === 'has_appointments') {
                matchesStatus = hasAppointments === 'yes';
            }
            
            const matchesSearch = !searchText || searchData.includes(searchText);
            
            if (matchesStatus && matchesSearch) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Block user
    function blockUser(userId, userName) {
        if (!confirm(`Are you sure you want to block ${userName}? They will not be able to login.`)) {
            return;
        }
        
        fetch('../controllers/manage_user_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'block_user', user_id: userId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'User Blocked', `${userName} has been blocked successfully.`);
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to block user.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while blocking user.');
        });
    }
    
    // Unblock user
    function unblockUser(userId, userName) {
        if (!confirm(`Are you sure you want to unblock ${userName}?`)) {
            return;
        }
        
        fetch('../controllers/manage_user_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'unblock_user', user_id: userId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'User Unblocked', `${userName} has been unblocked successfully.`);
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to unblock user.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while unblocking user.');
        });
    }
    
    // View user appointments
    function viewUserAppointments(userId) {
        // Open modal or redirect to appointments page filtered by user
        window.location.href = `admin.php#appointment`;
        // Note: User filtering can be added later if needed
    }
    
    // Promotional Email Modal
    function openPromotionalEmailModal() {
        document.getElementById('promotionalEmailModal').style.display = 'block';
    }
    
    function closePromotionalEmailModal() {
        document.getElementById('promotionalEmailModal').style.display = 'none';
        document.getElementById('promotionalEmailForm').reset();
    }
    
    // Handle promotional email form submission
    function handlePromotionalEmailSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        
        const requestData = {
            action: 'send_promotional_email',
            recipients: formData.get('recipients'),
            subject: formData.get('subject'),
            message: formData.get('message')
        };
        
        fetch('../controllers/manage_user_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(response => {
            // Check if response is valid HTTP response
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            try {
                // Reset button immediately
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                console.log('Email response:', data); // Debug log
                
                // Check response data for success
                if (data && data.success === true) {
                    const sentCount = data.sent_count || 0;
                    console.log('Success! Sent to', sentCount, 'recipients'); // Debug log
                    showNotification('success', '✓ Campaign Sent!', `Promotional email sent to ${sentCount} recipient${sentCount !== 1 ? 's' : ''}.`, '', 4000);
                    setTimeout(() => {
                        closePromotionalEmailModal();
                    }, 500);
                } else {
                    // API returned success: false
                    console.log('API returned error:', data.message); // Debug log
                    showNotification('error', '✕ Error', data.message || 'Failed to send promotional email. Please try again.');
                }
            } catch (e) {
                console.error('Error in success handler:', e);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            // ONLY network/fetch errors reach here, NOT API errors
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            console.error('Network/Fetch error:', error); // Debug log
            showNotification('error', '✕ Connection Error', 'A network error occurred. Please check your connection and try again.');
        });
    }
    
    // Export users list
    function exportUsersList() {
        window.location.href = '../controllers/export_users.php';
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('promotionalEmailModal');
        if (event.target === modal) {
            closePromotionalEmailModal();
        }
    });
</script>

</body>
</html>

