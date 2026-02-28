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

// Get dentists/staff data
$dentistSql = "SELECT team_id, first_name, last_name, specialization, email, phone, status 
              FROM multidisciplinary_dental_team
              ORDER BY team_id ASC";
$dentistResult = mysqli_query($con, $dentistSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentists & Staff - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="staffsDesign.css">
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
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- Staff Section -->
<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fa-solid fa-user-doctor"></i> DENTISTS AND STAFF</h2>

        <div class="filter-container">
            <div class="filter-group">
                <label for="filter-staff-status"><i class="fas fa-filter"></i> Status:</label>
                <select id="filter-staff-status" onchange="filterStaff()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="filter-group search-group">
                <label for="filter-staff-search"><i class="fas fa-search"></i> Search:</label>
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="filter-staff-search" class="search-input" 
                           placeholder="Search by name, ID, email..." onkeyup="filterStaff()">
                    <button type="button" class="search-clear-btn" id="clear-search-btn" onclick="clearStaffSearch()" style="display:none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <button class="btn btn-primary" id="openAddDentistBtn">
                <i class="fas fa-plus"></i> ADD NEW DENTIST/STAFF
            </button>
            
            <button class="btn btn-accent" onclick="printStaff()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <div class="table-responsive">
            <table id="staffs-table">
                <thead>
                    <tr>
                        <th>Team ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Specialization</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($dentistResult) > 0) {
                        while ($row = mysqli_fetch_assoc($dentistResult)) { 
                            // Full name for search
                            $fullName = strtolower($row['first_name'] . ' ' . $row['last_name']);
                            $searchText = strtolower($row['team_id'] . ' ' . $fullName . ' ' . $row['email'] . ' ' . $row['specialization']);
                    ?>
                        <tr class="staff-row" 
                            data-status="<?php echo htmlspecialchars(strtolower($row['status'])); ?>"
                            data-search="<?php echo htmlspecialchars($searchText); ?>">
                            <td><?php echo htmlspecialchars($row['team_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['specialization']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td>
                                <span class="status status-<?php echo htmlspecialchars(strtolower($row['status'])); ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn btn-primary" title="Edit" onclick="editDentist('<?php echo $row['team_id']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button class="action-btn btn-danger" title="Delete" onclick="deleteStaff(<?php echo $row['team_id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                        <tr>
                            <td colspan="8" class="no-data">
                                <i class="fas fa-exclamation-circle fa-2x"></i>
                                <p>No Dentists/Staff found</p>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="mobile-card-view">
            <?php 
            // Reset result pointer
            mysqli_data_seek($dentistResult, 0);
            if(mysqli_num_rows($dentistResult) > 0) {
                while ($row = mysqli_fetch_assoc($dentistResult)) { 
                    // Full name for search
                    $fullName = strtolower($row['first_name'] . ' ' . $row['last_name']);
                    $searchText = strtolower($row['team_id'] . ' ' . $fullName . ' ' . $row['email'] . ' ' . $row['specialization']);
            ?>
                <div class="staff-card staff-row" 
                     data-status="<?php echo htmlspecialchars(strtolower($row['status'])); ?>"
                     data-search="<?php echo htmlspecialchars($searchText); ?>">
                    <div class="staff-card-header">
                        <div>
                            <div class="staff-card-id">Team #<?php echo htmlspecialchars($row['team_id']); ?></div>
                            <div class="staff-card-name"><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></div>
                        </div>
                        <span class="status status-<?php echo htmlspecialchars(strtolower($row['status'])); ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </div>
                    <div class="staff-card-body">
                        <div class="staff-card-field">
                            <div class="staff-card-label">Specialization</div>
                            <div class="staff-card-value"><?php echo htmlspecialchars($row['specialization']); ?></div>
                        </div>
                        <div class="staff-card-field">
                            <div class="staff-card-label">Email</div>
                            <div class="staff-card-value"><?php echo htmlspecialchars($row['email']); ?></div>
                        </div>
                        <div class="staff-card-field">
                            <div class="staff-card-label">Phone</div>
                            <div class="staff-card-value"><?php echo htmlspecialchars($row['phone']); ?></div>
                        </div>
                    </div>
                    <div class="staff-card-actions">
                        <button class="action-btn btn-primary" title="Edit" onclick="editDentist('<?php echo $row['team_id']; ?>')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="action-btn btn-danger" title="Delete" onclick="deleteStaff(<?php echo $row['team_id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                    <p>No Dentists/Staff found</p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Pagination Controls for Staff -->
        <div class="pagination-container" id="staffs-pagination-container">
            <div class="pagination-info" id="staffs-pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="staffs-prev-page-btn" onclick="changeStaffsPage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-numbers" id="staffs-pagination-numbers"></div>
                <button class="pagination-btn" id="staffs-next-page-btn" onclick="changeStaffsPage(1)" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div id="addDentistModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3><i class="fa-solid fa-user-doctor"></i> ADD DENTIST/STAFF</h3>
        <form action="../controllers/addStaff.php" method="POST" id="addStaffForm">
            <!-- User ID Section -->
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="userid">User ID:</label>
                    <select name="userid" id="userid" required>
                        <option value="">Select User ID</option>
                        <!-- Admin users will be populated here by JavaScript -->
                    </select>
                </div>
            </div>

            <!-- Name Section -->
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="addFirstName">First Name:</label>
                    <input type="text" name="first_name" id="addFirstName" required readonly>
                </div>
                <div style="flex: 1;">
                    <label for="addLastName">Last Name:</label>
                    <input type="text" name="last_name" id="addLastName" required readonly>
                </div>
            </div>

            <!-- Specialization & Email Section -->
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="addSpecialization">Specialization:</label>
                    <input type="text" name="specialization" id="addSpecialization" required>
                </div>
                <div style="flex: 1;">
                    <label for="addEmail">Email:</label>
                    <input type="email" name="email" id="addEmail" required readonly>
                </div>
            </div>

            <!-- Phone & Status Section -->
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="addPhone">Phone:</label>
                    <input type="text" name="phone" id="addPhone" required readonly>
                </div>
                <div style="flex: 1;">
                    <label for="addStatus">Status:</label>
                    <select name="status" id="addStatus" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Buttons -->
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Add Staff
                </button>
                <button type="button" onclick="closeDentistModal()" class="modal-close-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Dentist Modal -->
<div id="editDentistModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3><i class="fa-solid fa-user-doctor"></i> EDIT DENTIST/STAFF</h3>
        <form id="editDentistForm" method="POST" action="../controllers/updateStaff.php">
            <input type="hidden" name="team_id" id="editDentistId">
            <input type="hidden" name="user_id" id="editDentistUserId">

            <!-- Name Section -->
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="editDentistFirstName">First Name:</label>
                    <input type="text" name="first_name" id="editDentistFirstName" required>
                </div>
                <div style="flex: 1;">
                    <label for="editDentistLastName">Last Name:</label>
                    <input type="text" name="last_name" id="editDentistLastName" required>
                </div>
            </div>

            <!-- Specialization & Status Section -->
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="editDentistSpecialization">Specialization:</label>
                    <input type="text" name="specialization" id="editDentistSpecialization" required>
                </div>
                <div style="flex: 1;">
                    <label for="editDentistStatus">Status:</label>
                    <select name="status" id="editDentistStatus" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Email & Phone Section -->
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="editDentistEmail">Email:</label>
                    <input type="email" name="email" id="editDentistEmail" required>
                </div>
                <div style="flex: 1;">
                    <label for="editDentistPhone">Phone:</label>
                    <input type="text" name="phone" id="editDentistPhone" required>
                </div>
            </div>

            <!-- Buttons -->
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Update Details
                </button>
                <button type="button" onclick="closeEditDentistModal()" class="modal-close-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Pagination state for Staff
    let staffsCurrentPage = 1;
    let staffsRowsPerPage = 5;
    
    // Detect mobile/tablet and adjust rows per page
    function updateRowsPerPage() {
        if (window.innerWidth <= 1024) {
            // Mobile and tablet: 2 cards per page
            staffsRowsPerPage = 2;
        } else {
            // Desktop: 5 rows per page
            staffsRowsPerPage = 5;
        }
    }
    
    // Update on load and resize
    updateRowsPerPage();
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const oldRowsPerPage = staffsRowsPerPage;
            updateRowsPerPage();
            if (oldRowsPerPage !== staffsRowsPerPage && typeof getVisibleStaffsRows === 'function') {
                // Recalculate pagination if rows per page changed
                staffsCurrentPage = 1;
                const visibleRows = getVisibleStaffsRows();
                if (typeof updateStaffsPagination === 'function' && typeof showStaffsPage === 'function') {
                    updateStaffsPagination(visibleRows);
                    showStaffsPage(visibleRows, staffsCurrentPage);
                }
            }
        }, 250);
    });

    // Notification function
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
                    iconHTML = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    iconHTML = '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'warning':
                    iconHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'info':
                    iconHTML = '<i class="fas fa-info-circle"></i>';
                    break;
            }
        }
        
        notification.innerHTML = `
            <div style="flex-shrink: 0; font-size: 24px; color: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : type === 'warning' ? '#F59E0B' : '#3B82F6'};">
                ${iconHTML}
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; margin-bottom: 4px; color: #1F2937;">${title}</div>
                <div style="font-size: 14px; color: #6B7280;">${message}</div>
            </div>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #9CA3AF; font-size: 18px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">&times;</button>
        `;
        
        container.appendChild(notification);
        
        // Auto remove after duration
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease-out';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 400);
        }, duration);
    }

    // Add Staffs Modal
    async function populateAdminUsers() {
        try {
            console.log('Fetching admin users...');
            const response = await fetch('../controllers/getadminUsers.php');
            
            // Get response text first to check if it's valid JSON
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Try to parse as JSON
            let adminUsers;
            try {
                adminUsers = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Server returned invalid JSON. Response: ' + responseText.substring(0, 200));
            }
            
            console.log('Admin users received:', adminUsers);
            
            // Check if response contains an error
            if (adminUsers && adminUsers.error) {
                throw new Error(adminUsers.error);
            }
            
            // Ensure adminUsers is an array
            if (!Array.isArray(adminUsers)) {
                throw new Error('Invalid response format: expected array');
            }
            
            const userSelect = document.getElementById('userid');
            
            if (!userSelect) {
                console.error('userid select element not found');
                return;
            }
            
            // Clear existing options
            userSelect.innerHTML = '<option value="">Select User ID</option>';
            
            // Check if we have admin users
            if (!adminUsers || adminUsers.length === 0) {
                const option = document.createElement('option');
                option.value = "";
                option.textContent = "No admin users found";
                option.disabled = true;
                userSelect.appendChild(option);
                console.warn('No admin users found in database');
                showNotification('info', 'No Users', 'No admin users found in the database.');
                return;
            }
            
            // Populate dropdown with admin users
            adminUsers.forEach(user => {
                // Get user_id - check all possible field names and ensure it's not null/undefined
                const userId = user.user_id || user.userID || user.id || null;
                
                // Skip if user_id is still null/undefined
                if (!userId) {
                    console.warn('Skipping user with no user_id:', user);
                    return;
                }
                
                const option = document.createElement('option');
                option.value = String(userId); // Ensure it's a string
                option.textContent = String(userId); // Display user_id
                option.setAttribute('data-firstname', user.first_name || '');
                option.setAttribute('data-lastname', user.last_name || '');
                option.setAttribute('data-email', user.email || '');
                option.setAttribute('data-phone', user.phone || '');
                userSelect.appendChild(option);
            });
            
            console.log(`Successfully loaded ${adminUsers.length} admin user(s)`);
            
        } catch (error) {
            console.error('Error fetching admin users:', error);
            console.error('Error stack:', error.stack);
            showNotification('error', 'Error Loading Data', error.message || 'Failed to load user data. Please check the console for details.');
            
            // Show error in dropdown
            const userSelect = document.getElementById('userid');
            if (userSelect) {
                userSelect.innerHTML = '<option value="">Error loading users - Check console</option>';
            }
        }
    }

    // Function to handle user selection change
    function handleUserSelection() {
        const userSelect = document.getElementById('userid');
        const selectedOption = userSelect.options[userSelect.selectedIndex];
        
        if (selectedOption.value && selectedOption.value !== "") {
            document.getElementById('addFirstName').value = selectedOption.getAttribute('data-firstname') || '';
            document.getElementById('addLastName').value = selectedOption.getAttribute('data-lastname') || '';
            document.getElementById('addEmail').value = selectedOption.getAttribute('data-email') || '';
            document.getElementById('addPhone').value = selectedOption.getAttribute('data-phone') || '';
        } else {
            // Clear fields if no user selected
            document.getElementById('addFirstName').value = '';
            document.getElementById('addLastName').value = '';
            document.getElementById('addEmail').value = '';
            document.getElementById('addPhone').value = '';
        }
    }

    // Modal open/close functionality
    const openDentistBtn = document.getElementById('openAddDentistBtn');
    const dentistModal = document.getElementById('addDentistModal');

    if (openDentistBtn && dentistModal) {
        openDentistBtn.addEventListener('click', function() {
            dentistModal.style.display = 'flex';
            populateAdminUsers(); // Populate when modal opens
        });
    }

    function closeDentistModal() {
        if (dentistModal) {
            dentistModal.style.display = 'none';
        }
        
        // Reset form when closing
        const userSelect = document.getElementById('userid');
        if (userSelect) userSelect.selectedIndex = 0;
        
        const addFirstName = document.getElementById('addFirstName');
        const addLastName = document.getElementById('addLastName');
        const addEmail = document.getElementById('addEmail');
        const addPhone = document.getElementById('addPhone');
        const addSpecialization = document.getElementById('addSpecialization');
        const addStatus = document.getElementById('addStatus');
        
        if (addFirstName) addFirstName.value = '';
        if (addLastName) addLastName.value = '';
        if (addEmail) addEmail.value = '';
        if (addPhone) addPhone.value = '';
        if (addSpecialization) addSpecialization.value = '';
        if (addStatus) addStatus.selectedIndex = 0;
    }

    // Initialize event listeners when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        const userSelect = document.getElementById('userid');
        if (userSelect) {
            // Remove any existing event listeners and add new one
            userSelect.removeEventListener('change', handleUserSelection);
            userSelect.addEventListener('change', handleUserSelection);
        }
        
        // Close modal when clicking outside
        if (dentistModal) {
            dentistModal.addEventListener('click', function(event) {
                if (event.target === dentistModal) {
                    closeDentistModal();
                }
            });
        }
    });
    
    //For edit Staffs
    function editDentist(teamId) {
        console.log('Edit dentist:', teamId);
        
        // Fetch staff details via AJAX
        fetch('../controllers/getStaff.php?team_id=' + encodeURIComponent(teamId))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const staff = data.data;
                    
                    // Populate form fields
                    document.getElementById('editDentistId').value = staff.team_id;
                    document.getElementById('editDentistUserId').value = staff.user_id || '';
                    document.getElementById('editDentistFirstName').value = staff.first_name || '';
                    document.getElementById('editDentistLastName').value = staff.last_name || '';
                    document.getElementById('editDentistSpecialization').value = staff.specialization || '';
                    document.getElementById('editDentistEmail').value = staff.email || '';
                    document.getElementById('editDentistPhone').value = staff.phone || '';
                    document.getElementById('editDentistStatus').value = staff.status || 'active';
                    
                    // Show the modal
                    document.getElementById('editDentistModal').style.display = 'flex';
                } else {
                    showNotification('error', 'Error Loading Staff', data.message || 'Unknown error occurred.');
                }
            })
            .catch(error => {
                console.error('Error fetching staff:', error);
                showNotification('error', 'Error Loading Staff', error.message || 'Failed to load staff details.');
            });
    }

    function closeEditDentistModal() {
        document.getElementById('editDentistModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener("click", function(event) {
        const editModal = document.getElementById("editDentistModal");
        if (event.target === editModal) {
            closeEditDentistModal();
        }
    });

    // Delete Staff function
    function deleteStaff(teamId, staffName) {
        if (!teamId || teamId <= 0) {
            showNotification('error', 'Invalid Input', 'Invalid staff ID. Please try again.');
            return;
        }

        if (confirm(`Are you sure you want to delete ${staffName}? This action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../controllers/deleteStaff.php';

            const teamIdInput = document.createElement('input');
            teamIdInput.type = 'hidden';
            teamIdInput.name = 'team_id';
            teamIdInput.value = teamId;

            form.appendChild(teamIdInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Filter Staff
    function filterStaff() {
        const searchInput = document.getElementById("filter-staff-search");
        const searchText = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const clearBtn = document.getElementById("clear-search-btn");
        
        // Show/hide clear button based on input
        if (clearBtn) {
            if (searchText !== "") {
                clearBtn.style.display = "flex";
            } else {
                clearBtn.style.display = "none";
            }
        }
        
        // Update rows per page based on current screen size
        updateRowsPerPage();
        
        // Reset to first page after filtering
        staffsCurrentPage = 1;
        
        // Get visible rows and update pagination
        const visibleRows = getVisibleStaffsRows();
        
        // Check if we're on mobile/tablet
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Ensure we have rows before updating
        if (visibleRows.length > 0) {
            if (isMobileOrTablet) {
                // On mobile/tablet: Show all items, hide pagination
                updateStaffsPagination(visibleRows);
                showStaffsPage(visibleRows, 1);
            } else {
                // On desktop: Use pagination
                updateStaffsPagination(visibleRows);
                showStaffsPage(visibleRows, staffsCurrentPage);
            }
        } else {
            // Hide all rows if no matches
            const allRows = document.querySelectorAll(".staff-row");
            allRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            updateStaffsPagination([]);
        }
    }
    
    // Clear Staff Search
    function clearStaffSearch() {
        const searchInput = document.getElementById("filter-staff-search");
        const clearBtn = document.getElementById("clear-search-btn");
        
        searchInput.value = "";
        clearBtn.style.display = "none";
        filterStaff(); // Re-filter to show all staff
        searchInput.focus(); // Focus back on the search input
    }
    
    // Print Staff
    function printStaff() {
        window.print();
    }
    
    // Update Staffs Pagination
    function updateStaffsPagination(visibleRows) {
        const totalRows = visibleRows.length;
        const totalPages = Math.ceil(totalRows / staffsRowsPerPage);
        const paginationContainer = document.getElementById("staffs-pagination-container");
        const paginationInfo = document.getElementById("staffs-pagination-info");
        const paginationNumbers = document.getElementById("staffs-pagination-numbers");
        const prevBtn = document.getElementById("staffs-prev-page-btn");
        const nextBtn = document.getElementById("staffs-next-page-btn");

        // Check if we're on mobile/tablet (hide pagination)
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        if (isMobileOrTablet) {
            // Hide pagination on mobile/tablet
            if (paginationContainer) paginationContainer.style.display = "none";
            return;
        }

        // Hide pagination if no rows
        if (totalRows === 0) {
            if (paginationContainer) paginationContainer.style.display = "none";
            return;
        }

        if (paginationContainer) paginationContainer.style.display = "flex";
        
        // Ensure current page is valid
        if (staffsCurrentPage > totalPages && totalPages > 0) {
            staffsCurrentPage = totalPages;
        }
        if (staffsCurrentPage < 1) {
            staffsCurrentPage = 1;
        }

        // Update info
        const startRow = (staffsCurrentPage - 1) * staffsRowsPerPage + 1;
        const endRow = Math.min(staffsCurrentPage * staffsRowsPerPage, totalRows);
        if (paginationInfo) paginationInfo.textContent = `Showing ${startRow}-${endRow} of ${totalRows} staff`;

        // Update buttons
        if (prevBtn) prevBtn.disabled = staffsCurrentPage === 1;
        if (nextBtn) nextBtn.disabled = staffsCurrentPage >= totalPages;

        // Generate page numbers
        if (paginationNumbers) paginationNumbers.innerHTML = "";
        const maxPagesToShow = 5;
        let startPage = Math.max(1, staffsCurrentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        // First page and ellipsis
        if (startPage > 1 && paginationNumbers) {
            createStaffsPageNumber(1, paginationNumbers);
            if (startPage > 2) {
                createStaffsEllipsis(paginationNumbers);
            }
        }

        // Page numbers
        if (paginationNumbers) {
            for (let i = startPage; i <= endPage; i++) {
                createStaffsPageNumber(i, paginationNumbers);
            }
        }

        // Last page and ellipsis
        if (endPage < totalPages && paginationNumbers) {
            if (endPage < totalPages - 1) {
                createStaffsEllipsis(paginationNumbers);
            }
            createStaffsPageNumber(totalPages, paginationNumbers);
        }
    }

    // Create Staffs page number button
    function createStaffsPageNumber(pageNum, container) {
        const pageBtn = document.createElement("button");
        pageBtn.className = "pagination-number" + (pageNum === staffsCurrentPage ? " active" : "");
        pageBtn.textContent = pageNum;
        pageBtn.onclick = () => goToStaffsPage(pageNum);
        container.appendChild(pageBtn);
    }

    // Create Staffs ellipsis
    function createStaffsEllipsis(container) {
        const ellipsis = document.createElement("span");
        ellipsis.className = "pagination-number ellipsis";
        ellipsis.textContent = "...";
        container.appendChild(ellipsis);
    }

    // Show Staffs specific page
    function showStaffsPage(visibleRows, page) {
        // Check if we're on mobile/tablet (no pagination)
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Hide all staff rows first (both table rows and cards)
        const allStaffRows = document.querySelectorAll(".staff-row");
        
        if (isMobileOrTablet) {
            // On mobile/tablet: Show all visible rows/cards (no pagination)
            allStaffRows.forEach(row => {
                // Check if it's a table row or card
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    // It's a card - check if it's in visibleRows
                    row.style.display = "none";
                }
            });
            
            // Show all visible rows/cards
            visibleRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "block";
                }
            });
        } else {
            // On desktop: Use pagination
            allStaffRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            
            const startIndex = (page - 1) * staffsRowsPerPage;
            const endIndex = startIndex + staffsRowsPerPage;
            const rowsToShow = visibleRows.slice(startIndex, endIndex);
            
            // Show only rows/cards for current page
            rowsToShow.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "block";
                }
            });
        }
    }

    // Get visible Staffs rows based on current filters
    function getVisibleStaffsRows() {
        const selectedStatus = document.getElementById("filter-staff-status").value.toLowerCase();
        const searchInput = document.getElementById("filter-staff-search");
        const searchText = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const rows = document.querySelectorAll(".staff-row");
        const visibleRows = [];
        
        rows.forEach(row => {
            const rowStatus = row.getAttribute("data-status").toLowerCase();
            const rowSearch = row.getAttribute("data-search").toLowerCase();
            
            const matchesStatus = selectedStatus === "" || rowStatus === selectedStatus;
            const matchesSearch = searchText === "" || rowSearch.includes(searchText);
            
            if (matchesStatus && matchesSearch) {
                visibleRows.push(row);
            }
        });
        
        return visibleRows;
    }

    // Go to Staffs specific page
    function goToStaffsPage(page) {
        const visibleRows = getVisibleStaffsRows();
        if (visibleRows.length === 0) return;

        staffsCurrentPage = page;
        updateStaffsPagination(visibleRows);
        showStaffsPage(visibleRows, staffsCurrentPage);
    }

    // Change Staffs page (previous/next)
    function changeStaffsPage(direction) {
        const visibleRows = getVisibleStaffsRows();
        if (visibleRows.length === 0) return;

        const totalPages = Math.ceil(visibleRows.length / staffsRowsPerPage);
        const newPage = staffsCurrentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            goToStaffsPage(newPage);
        }
    }

    // Initialize pagination on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Update rows per page based on screen size
        updateRowsPerPage();
        
        // Ensure all rows are visible initially before filtering
        const allRows = document.querySelectorAll(".staff-row");
        allRows.forEach(row => {
            // Check if it's a table row or card
            if (row.tagName === 'TR') {
                row.style.display = "table-row";
            } else {
                // It's a card
                row.style.display = "block";
            }
        });
        
        // Then apply filters and pagination
        setTimeout(() => {
            filterStaff();
        }, 100);
    });

    // Navigate back function
    function navigateBack(event) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
            setTimeout(() => {
                window.location.href = '../views/admin.php';
            }, 300);
        } else {
            window.location.href = '../views/admin.php';
        }
    }
</script>

</body>
</html>
