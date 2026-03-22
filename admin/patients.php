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

// Get patients data
$patientSql = "SELECT patient_id, first_name, last_name, birthdate, gender, email, phone, address 
              FROM patient_information
              ORDER BY patient_id ASC";
$patientResult = mysqli_query($con, $patientSql);

// Get patients map for other uses
$patientsQuery = "
    SELECT patient_id, CONCAT(first_name, ' ', last_name) as full_name
    FROM patient_information
    ORDER BY patient_id ASC
";
$patientsResult = mysqli_query($con, $patientsQuery);
$patientsMap = [];
while ($row = mysqli_fetch_assoc($patientsResult)) {
    $fullName = $row['full_name'];
    $patientsMap[$row['patient_id']] = $fullName;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="patientsDesign.css">
    <link rel="stylesheet" href="serviceDesign.css">
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

        /* Patients table styling (scoped to this page) */
        #patients-table {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            table-layout: auto;
        }

        #patients-table thead th {
            background: linear-gradient(135deg, #48A6A7, #2a9d8f);
            color: #fff;
            border: none !important;
            border-bottom: none !important;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
            font-size: 0.85em;
            padding: 14px 12px;
        }

        #patients-table th,
        #patients-table td {
            border: none !important; /* Remove visible grid lines */
            border-bottom: none !important;
        }

        #patients-table tbody td {
            padding: 10px 12px; /* Compact rows */
            font-size: 0.90em;
            font-weight: 600;
        }

        #patients-table tbody tr:nth-child(odd) {
            background-color: #fff;
        }

        #patients-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        #patients-table tbody tr:hover {
            background-color: rgba(233, 196, 106, 0.14);
        }

        /* Column alignment */
        #patients-table .patients-id-cell,
        #patients-table .patients-actions-cell,
        #patients-table .patients-id-col,
        #patients-table .patients-actions-col {
            text-align: center;
            white-space: nowrap;
        }

        #patients-table .patients-desc-cell {
            max-width: 320px;
            overflow: hidden;
        }

        #patients-table .patients-desc {
            max-width: 320px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
            text-overflow: ellipsis;
        }

        /* Action buttons: compact, icon-only look */
        #patients-table .action-btns {
            justify-content: center;
        }

        #patients-table .action-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
        }

        #patients-table .action-btn i {
            font-size: 14px;
        }

        #patients-table .action-btn:hover {
            filter: brightness(1.08);
        }

        @media (max-width: 900px) {
            #patients-table .patients-desc {
                -webkit-line-clamp: 2;
            }

            #patients-table thead th,
            #patients-table tbody td {
                padding: 10px 10px;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- Patients Section -->
<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fa-solid fa-hospital-user"></i> PATIENTS</h2>

        <div class="filter-container">
            <div class="filter-group">
                <label for="filter-patient-gender"><i class="fas fa-venus-mars"></i> Gender:</label>
                <select id="filter-patient-gender" onchange="filterPatients()">
                    <option value="">All Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-patient-age"><i class="fas fa-calendar-alt"></i> Age Category:</label>
                <select id="filter-patient-age" onchange="filterPatients()">
                    <option value="">All Ages</option>
                    <option value="child">Child (0-12)</option>
                    <option value="teen">Teen (13-19)</option>
                    <option value="adult">Adult (20-59)</option>
                    <option value="senior">Senior (60+)</option>
                </select>
            </div>
            
            <div class="filter-group search-group">
                <label for="filter-patient-search"><i class="fas fa-search"></i> Search:</label>
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="filter-patient-search" class="search-input" 
                           placeholder="Search by name, ID, email..." onkeyup="filterPatients()">
                    <button type="button" class="search-clear-btn" id="clear-search-btn" onclick="clearPatientSearch()" style="display:none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <button class="btn btn-accent" onclick="printPatients()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <div class="table-responsive">
            <table id="patients-table">
                <thead>
                    <tr>
                        <th class="patients-id-col">Patient ID</th>
                        <th>Name</th>
                        <th>Birthdate</th>
                        <th>Gender</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th class="patients-address-col">Address</th>
                        <th class="patients-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($patientResult) > 0) {
                        while ($row = mysqli_fetch_assoc($patientResult)) { 
                            // Calculate age from birthdate
                            $birthdate = new DateTime($row['birthdate']);
                            $today = new DateTime();
                            $age = $birthdate->diff($today)->y;
                            
                            // Determine age category
                            $ageCategory = '';
                            if ($age <= 12) {
                                $ageCategory = 'child';
                            } else if ($age >= 13 && $age <= 19) {
                                $ageCategory = 'teen';
                            } else if ($age >= 20 && $age <= 59) {
                                $ageCategory = 'adult';
                            } else {
                                $ageCategory = 'senior';
                            }
                            
                            // Full name for search
                            $fullName = strtolower($row['first_name'] . ' ' . $row['last_name']);
                            $searchText = strtolower($row['patient_id'] . ' ' . $fullName . ' ' . $row['email']);
                    ?>
                        <tr class="patient-row" 
                            data-patient-id="<?php echo htmlspecialchars($row['patient_id']); ?>"
                            data-gender="<?php echo htmlspecialchars(strtolower($row['gender'])); ?>"
                            data-age-category="<?php echo htmlspecialchars($ageCategory); ?>"
                            data-search="<?php echo htmlspecialchars($searchText); ?>"
                            data-age="<?php echo $age; ?>">
                            <td class="patients-id-cell"><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($row['birthdate'])); ?></td>
                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td class="patients-desc-cell">
                                <div class="patients-desc" title="<?php echo htmlspecialchars($row['address']); ?>">
                                    <?php echo htmlspecialchars($row['address']); ?>
                                </div>
                            </td>
                            <td class="patients-actions-cell">
                                <div class="action-btns">
                                    <button class="action-btn btn-primary" title="Edit" onclick="editPatient('<?php echo $row['patient_id']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button class="action-btn btn-danger" title="Archive" onclick="archivePatient(<?php echo $row['patient_id']; ?>)">
                                        <i class="fa-solid fa-box-archive"></i>
                                    </button>

                                    <button class="action-btn btn-gray" title="See More" onclick="seeMoreDetails('<?php echo $row['patient_id']; ?>', event)">
                                        <i class="fa-solid fa-circle-info"></i>
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
                                <p>No Patients found</p>
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
            mysqli_data_seek($patientResult, 0);
            if(mysqli_num_rows($patientResult) > 0) {
                while ($row = mysqli_fetch_assoc($patientResult)) { 
                    // Calculate age from birthdate
                    $birthdate = new DateTime($row['birthdate']);
                    $today = new DateTime();
                    $age = $birthdate->diff($today)->y;
                    
                    // Determine age category
                    $ageCategory = '';
                    if ($age <= 12) {
                        $ageCategory = 'child';
                    } else if ($age >= 13 && $age <= 19) {
                        $ageCategory = 'teen';
                    } else if ($age >= 20 && $age <= 59) {
                        $ageCategory = 'adult';
                    } else {
                        $ageCategory = 'senior';
                    }
                    
                    // Full name for search
                    $fullName = strtolower($row['first_name'] . ' ' . $row['last_name']);
                    $searchText = strtolower($row['patient_id'] . ' ' . $fullName . ' ' . $row['email']);
            ?>
                <div class="patient-card patient-row" 
                     data-patient-id="<?php echo htmlspecialchars($row['patient_id']); ?>"
                     data-gender="<?php echo htmlspecialchars(strtolower($row['gender'])); ?>"
                     data-age-category="<?php echo htmlspecialchars($ageCategory); ?>"
                     data-search="<?php echo htmlspecialchars($searchText); ?>"
                     data-age="<?php echo $age; ?>">
                    <div class="patient-card-header">
                        <div>
                            <div class="patient-card-id">Patient #<?php echo htmlspecialchars($row['patient_id']); ?></div>
                            <div class="patient-card-name"><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></div>
                        </div>
                    </div>
                    <div class="patient-card-body">
                        <div class="patient-card-field">
                            <div class="patient-card-label">Birthdate</div>
                            <div class="patient-card-value"><?php echo date('M j, Y', strtotime($row['birthdate'])); ?> (<?php echo $age; ?> years old)</div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Gender</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['gender']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Email</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['email']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Phone</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['phone']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Address</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['address']); ?></div>
                        </div>
                    </div>
                    <div class="patient-card-actions">
                        <button class="action-btn btn-primary" title="Edit" onclick="editPatient('<?php echo $row['patient_id']; ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn btn-danger" title="Archive" onclick="archivePatient(<?php echo $row['patient_id']; ?>)">
                            <i class="fa-solid fa-box-archive"></i>
                        </button>
                        <button class="action-btn btn-gray" title="See More" onclick="seeMoreDetails('<?php echo $row['patient_id']; ?>', event)">
                            <i class="fa-solid fa-circle-info"></i>
                        </button>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                    <p>No Patients found</p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Pagination Controls for Patients -->
        <div class="pagination-container" id="patients-pagination-container">
            <div class="pagination-info" id="patients-pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="patients-prev-page-btn" onclick="changePatientsPage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-numbers" id="patients-pagination-numbers"></div>
                <button class="pagination-btn" id="patients-next-page-btn" onclick="changePatientsPage(1)" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Patient Modal -->
<div id="editPatientModal" class="modal-overlay" style="display:none;">
    <div class="modal-panel">
        <button class="modal-close" onclick="closeEditPatientModal()" aria-label="Close edit patient dialog">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-heading">
            <span class="modal-badge accent">Edit patient</span>
            <h3>Update patient information</h3>
            <p>Modify personal details, contact information, or address without losing context.</p>
        </div>
        <form id="editPatientForm" onsubmit="handleEditPatientSubmit(event)" class="modal-form">
            <input type="hidden" name="patient_id" id="editPatientId">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="editFirstName">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" id="editFirstName" required class="form-control" placeholder="Enter first name">
                </div>

                <div class="form-group">
                    <label class="form-label" for="editLastName">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" id="editLastName" required class="form-control" placeholder="Enter last name">
                </div>

                <div class="form-group">
                    <label class="form-label" for="editBirthdate">Birthdate <span class="required">*</span></label>
                    <input type="date" name="birthdate" id="editBirthdate" required class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label" for="editGender">Gender <span class="required">*</span></label>
                    <select name="gender" id="editGender" required class="form-control">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editEmail">Email <span class="required">*</span></label>
                    <input type="email" name="email" id="editEmail" required class="form-control" placeholder="Enter email address">
                </div>

                <div class="form-group">
                    <label class="form-label" for="editPhone">Phone <span class="required">*</span></label>
                    <input type="text" name="phone" id="editPhone" required class="form-control" placeholder="Enter phone number">
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="editAddress">Address <span class="required">*</span></label>
                    <input type="text" name="address" id="editAddress" required class="form-control" placeholder="Enter full address">
                </div>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-success btn-wide">
                    <i class="fas fa-pencil-alt"></i> Update Patient
                </button>
                <button type="button" onclick="closeEditPatientModal()" class="btn btn-link">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Treatment History Modal -->
<div id="treatmentHistoryModal" class="treatment-modal">
    <div class="treatment-modal-content">
        <!-- Content will be dynamically generated by seeMoreDetails() -->
    </div>
</div>

<script>
    // Pagination state for Patients
    let patientsCurrentPage = 1;
    let patientsRowsPerPage = 5;
    
    // Detect mobile/tablet and adjust rows per page
    function updateRowsPerPage() {
        if (window.innerWidth <= 1024) {
            // Mobile and tablet: 2 cards per page
            patientsRowsPerPage = 2;
        } else {
            // Desktop: 5 rows per page
            patientsRowsPerPage = 5;
        }
    }
    
    // Update on load and resize
    updateRowsPerPage();
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const oldRowsPerPage = patientsRowsPerPage;
            updateRowsPerPage();
            if (oldRowsPerPage !== patientsRowsPerPage && typeof getVisiblePatientsRows === 'function') {
                // Recalculate pagination if rows per page changed
                patientsCurrentPage = 1;
                const visibleRows = getVisiblePatientsRows();
                if (typeof updatePatientsPagination === 'function' && typeof showPatientsPage === 'function') {
                    updatePatientsPagination(visibleRows);
                    showPatientsPage(visibleRows, patientsCurrentPage);
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

    function showPatientUpdatedNotification(patientId, patientName) {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = 'notification success';
        
        notification.innerHTML = `
            <div class="notification-icon success-scale-animation">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M5 13l4 4L19 7" class="check-animation" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; margin-bottom: 4px; color: #1F2937;">Patient Updated</div>
                <div style="font-size: 14px; color: #6B7280;">Patient ${patientName} (ID: ${patientId}) has been updated successfully.</div>
            </div>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #9CA3AF; font-size: 18px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">&times;</button>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease-out';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 400);
        }, 5000);
    }

    const patientsMap = <?php echo json_encode($patientsMap); ?>;

    function updatePatientName() {
        const selectedID = document.getElementById("patient_id").value;
        document.getElementById("patient_name").value = patientsMap[selectedID] || '';
    }
    
    // Utility function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // See More Patient Modal
    function seeMoreDetails(patientId, event) {
        // Prevent event propagation if event is provided
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        
        const modal = document.getElementById("treatmentHistoryModal");
        if (!modal) {
            console.error("Treatment history modal not found");
            alert("Error: Modal element not found. Please refresh the page.");
            return;
        }
        
        let modalContent = modal.querySelector(".treatment-modal-content");
        
        // If modal content doesn't exist, create it
        if (!modalContent) {
            modalContent = document.createElement("div");
            modalContent.className = "treatment-modal-content";
            modal.appendChild(modalContent);
        }
        
        // Create modal content with all three sections using card layout
        const newModalContent = `
            <div class="treatment-modal-header">
                <h3><i class="fa-solid fa-user"></i> Patient Details - ID: ${patientId}</h3>
                <div class="treatment-modal-actions">
                    <button type="button" class="btn btn-primary" onclick="exportPatientDetails('${patientId}')">
                        <i class="fa-solid fa-print"></i> Export/Print
                    </button>
                    <button type="button" class="treatment-close-btn" onclick="closeTreatmentModal()" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="treatment-modal-body">
                <!-- Treatment History Section -->
                <div class="section-container">
                    <h3><i class="fa-solid fa-notes-medical"></i> Treatment History</h3>
                    <div id="treatmentHistoryCards" class="cards-container">
                        <div class="loading-message">Loading treatment history...</div>
                    </div>
                </div>
                
                <!-- Appointment History Section -->
                <div class="section-container">
                    <h3><i class="fa-solid fa-calendar-check"></i> Appointment History</h3>
                    <div id="appointmentHistoryCards" class="cards-container">
                        <div class="loading-message">Loading appointments...</div>
                    </div>
                </div>
                
                <!-- Last Transaction Section -->
                <div class="section-container">
                    <h3><i class="fa-solid fa-money-bill-wave"></i> Last Transaction</h3>
                    <div id="transactionHistoryCards" class="cards-container">
                        <div class="loading-message">Loading transaction...</div>
                    </div>
                </div>
            </div>
        `;
        
        // Update modal content - use the correct selector
        if (modalContent) {
            modalContent.innerHTML = newModalContent;
            
            // Add click event listener to modal content to prevent propagation
            modalContent.addEventListener("click", function(e) {
                e.stopPropagation();
            });
        }
        
        // Load all data after a small delay to ensure DOM is ready
        setTimeout(() => {
            loadTreatmentHistory(patientId);
            loadAppointmentHistory(patientId);
            loadLastTransaction(patientId);
        }, 100);
        
        // Show the modal with proper initialization
        modal.classList.add("modal-open");
        // Use requestAnimationFrame to ensure smooth transition
        requestAnimationFrame(() => {
            modal.style.opacity = "1";
            modal.style.visibility = "visible";
        });
        
        // Prevent accidental closes for a brief moment after opening
        modal.setAttribute("data-just-opened", "true");
        setTimeout(() => {
            modal.removeAttribute("data-just-opened");
        }, 300);
    }

    function loadTreatmentHistory(patientId) {
        const cardsContainer = document.getElementById("treatmentHistoryCards");
        if (!cardsContainer) {
            console.error("Treatment history cards container not found");
            return;
        }
        
        // Ensure modal stays open even if fetch fails
        const modal = document.getElementById("treatmentHistoryModal");
        if (!modal || !modal.classList.contains("modal-open")) {
            return;
        }
        
        fetch("../controllers/getTreatmentHistory.php?patient_id=" + encodeURIComponent(patientId))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Clear loading message
                cardsContainer.innerHTML = "";
                
                if (data.status === "success" && data.data && Array.isArray(data.data) && data.data.length > 0) {
                    // Render treatment cards
                    data.data.forEach(treatment => {
                        const card = document.createElement('div');
                        card.className = 'detail-card';
                        card.innerHTML = `
                            <div class="detail-card-header">
                                <span class="detail-card-title">${escapeHtml(treatment.treatment || 'N/A')}</span>
                                <span class="detail-card-cost">₱${parseFloat(treatment.treatment_cost || 0).toFixed(2)}</span>
                            </div>
                            <div class="detail-card-body">
                                <div class="detail-card-field">
                                    <span class="detail-card-label">Prescription:</span>
                                    <span class="detail-card-value">${escapeHtml(treatment.prescription_given || 'N/A')}</span>
                                </div>
                                <div class="detail-card-field">
                                    <span class="detail-card-label">Notes:</span>
                                    <span class="detail-card-value">${escapeHtml(treatment.notes || 'N/A')}</span>
                                </div>
                                <div class="detail-card-field">
                                    <span class="detail-card-label">Date:</span>
                                    <span class="detail-card-value">${escapeHtml(treatment.created_at || 'N/A')}</span>
                                </div>
                            </div>`;
                        cardsContainer.appendChild(card);
                    });
                } else if (data.status === "empty" || (data.status === "success" && (!data.data || !Array.isArray(data.data) || data.data.length === 0))) {
                    cardsContainer.innerHTML = '<div class="no-data-message">No treatment history found.</div>';
                } else {
                    cardsContainer.innerHTML = '<div class="no-data-message error">Error: ' + escapeHtml(data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error("Error fetching treatment history:", error);
                cardsContainer.innerHTML = '<div class="no-data-message error">Error loading treatment history: ' + escapeHtml(error.message) + '</div>';
            });
    }

    function loadAppointmentHistory(patientId) {
        const cardsContainer = document.getElementById("appointmentHistoryCards");
        if (!cardsContainer) {
            console.error("Appointment history cards container not found");
            return;
        }
        
        fetch("../controllers/getAppointmentHistory.php?patient_id=" + patientId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === "success" && data.data && data.data.length > 0) {
                    cardsContainer.innerHTML = "";
                    data.data.forEach(appointment => {
                        const card = `
                            <div class="detail-card">
                                <div class="detail-card-header">
                                    <span class="detail-card-title">Appointment #${escapeHtml(appointment.appointment_id || 'N/A')}</span>
                                </div>
                                <div class="detail-card-body">
                                    <div class="detail-card-field">
                                        <span class="detail-card-label">Dentist:</span>
                                        <span class="detail-card-value">${escapeHtml(appointment.dentist_name || 'N/A')}</span>
                                    </div>
                                    <div class="detail-card-field">
                                        <span class="detail-card-label">Service:</span>
                                        <span class="detail-card-value">${escapeHtml(appointment.service_name || 'N/A')}</span>
                                    </div>
                                    <div class="detail-card-field">
                                        <span class="detail-card-label">Branch:</span>
                                        <span class="detail-card-value">${escapeHtml(appointment.branch || 'N/A')}</span>
                                    </div>
                                    <div class="detail-card-field">
                                        <span class="detail-card-label">Date:</span>
                                        <span class="detail-card-value">${escapeHtml(appointment.appointment_date || 'N/A')}</span>
                                    </div>
                                    <div class="detail-card-field">
                                        <span class="detail-card-label">Time:</span>
                                        <span class="detail-card-value">${escapeHtml(appointment.appointment_time || 'N/A')}</span>
                                    </div>
                                </div>
                            </div>`;
                        cardsContainer.insertAdjacentHTML("beforeend", card);
                    });
                } else if (data.status === "empty" || (data.status === "success" && (!data.data || data.data.length === 0))) {
                    cardsContainer.innerHTML = '<div class="no-data-message">No appointment history found.</div>';
                } else {
                    cardsContainer.innerHTML = '<div class="no-data-message error">Error: ' + (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error("Error fetching appointment history:", error);
                cardsContainer.innerHTML = '<div class="no-data-message error">Error loading appointments: ' + error.message + '</div>';
            });
    }

    function loadLastTransaction(patientId) {
        const cardsContainer = document.getElementById("transactionHistoryCards");
        if (!cardsContainer) {
            console.error("Transaction history cards container not found");
            return;
        }
        
        fetch("../controllers/getLastTransaction.php?patient_id=" + patientId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === "success" && data.data) {
                    cardsContainer.innerHTML = "";
                    const transaction = data.data;
                    const card = `
                        <div class="detail-card">
                            <div class="detail-card-header">
                                <span class="detail-card-title">Payment #${escapeHtml(transaction.payment_id || 'N/A')}</span>
                                <span class="status status-${(transaction.status || '').toLowerCase()}">${escapeHtml(transaction.status || 'N/A')}</span>
                            </div>
                            <div class="detail-card-body">
                                <div class="detail-card-field">
                                    <span class="detail-card-label">Method:</span>
                                    <span class="detail-card-value">${escapeHtml(transaction.method || 'N/A')}</span>
                                </div>
                                <div class="detail-card-field">
                                    <span class="detail-card-label">Account Name:</span>
                                    <span class="detail-card-value">${escapeHtml(transaction.account_name || 'N/A')}</span>
                                </div>
                                <div class="detail-card-field">
                                    <span class="detail-card-label">Amount:</span>
                                    <span class="detail-card-value amount">₱${parseFloat(transaction.amount || 0).toFixed(2)}</span>
                                </div>
                                <div class="detail-card-field">
                                    <span class="detail-card-label">Reference No:</span>
                                    <span class="detail-card-value">${escapeHtml(transaction.reference_no || 'N/A')}</span>
                                </div>
                            </div>
                        </div>`;
                    cardsContainer.insertAdjacentHTML("beforeend", card);
                } else if (data.status === "empty" || (data.status === "success" && !data.data)) {
                    cardsContainer.innerHTML = '<div class="no-data-message">No transaction history found.</div>';
                } else {
                    cardsContainer.innerHTML = '<div class="no-data-message error">Error: ' + (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error("Error fetching transaction history:", error);
                cardsContainer.innerHTML = '<div class="no-data-message error">Error loading transaction: ' + error.message + '</div>';
            });
    }

    // Close modal
    function closeTreatmentModal() {
        const modal = document.getElementById("treatmentHistoryModal");
        if (modal) {
            modal.classList.remove("modal-open");
            modal.style.opacity = "0";
            modal.style.visibility = "hidden";
        }
    }

    // Close when clicking outside modal (on backdrop only)
    document.addEventListener("click", function(event) {
        const modal = document.getElementById("treatmentHistoryModal");
        if (!modal || !modal.classList.contains("modal-open")) {
            return;
        }
        
        // Don't close if modal was just opened (prevent accidental closes)
        if (modal.getAttribute("data-just-opened") === "true") {
            return;
        }
        
        // Only close if clicking directly on the modal backdrop (not on modal content)
        if (event.target === modal) {
            closeTreatmentModal();
        }
    });

    function exportPatientDetails(patientId) {
        // Fetch all patient information
        Promise.all([
            fetch('../controllers/getPatients.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ patient_id: patientId, first_name: '', last_name: '', birthdate: '', gender: '', email: '', phone: '', address: '' })),
            fetch('../controllers/getTreatmentHistory.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ status: 'error', data: [] })),
            fetch('../controllers/getAppointmentHistory.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ status: 'error', data: [] })),
            fetch('../controllers/getLastTransaction.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ status: 'error', data: null }))
        ]).then(([patientData, treatmentData, appointmentData, transactionData]) => {
            const patientName = patientData.first_name && patientData.last_name 
                ? `${patientData.first_name} ${patientData.last_name}` 
                : `Patient ID: ${patientId}`;
            
            // Create print window
            const printWindow = window.open('', '_blank');
            const currentDate = new Date().toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            let htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Patient Details - ${patientName}</title>
                    <style>
                        @media print {
                            @page {
                                margin: 1cm;
                            }
                        }
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 3px solid #333;
                            padding-bottom: 20px;
                            margin-bottom: 30px;
                        }
                        .header h1 {
                            margin: 0;
                            color: #2c3e50;
                            font-size: 24px;
                        }
                        .header h2 {
                            margin: 10px 0;
                            color: #34495e;
                            font-size: 18px;
                            font-weight: normal;
                        }
                        .patient-info {
                            margin-bottom: 30px;
                            padding: 15px;
                            background-color: #f8f9fa;
                            border-left: 4px solid #007bff;
                        }
                        .patient-info p {
                            margin: 5px 0;
                            font-size: 14px;
                        }
                        .patient-info strong {
                            color: #2c3e50;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 20px;
                            font-size: 12px;
                        }
                        th {
                            background-color: #007bff;
                            color: white;
                            padding: 12px;
                            text-align: left;
                            border: 1px solid #ddd;
                        }
                        td {
                            padding: 10px;
                            border: 1px solid #ddd;
                        }
                        tr:nth-child(even) {
                            background-color: #f8f9fa;
                        }
                        .no-data {
                            text-align: center;
                            padding: 40px;
                            color: #999;
                            font-style: italic;
                        }
                        .footer {
                            margin-top: 40px;
                            padding-top: 20px;
                            border-top: 2px solid #ddd;
                            text-align: center;
                            font-size: 11px;
                            color: #666;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Landero Dental Clinic</h1>
                        <h2>Patient Details Report</h2>
                    </div>
                    
                    <div class="patient-info">
                        <p><strong>Patient ID:</strong> ${patientId}</p>
                        <p><strong>Patient Name:</strong> ${patientName}</p>
                        <p><strong>Email:</strong> ${patientData.email || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${patientData.phone || 'N/A'}</p>
                        <p><strong>Address:</strong> ${patientData.address || 'N/A'}</p>
                        <p><strong>Report Date:</strong> ${currentDate}</p>
                    </div>
            `;
            
            // Treatment History
            if (treatmentData.status === 'success' && treatmentData.data && treatmentData.data.length > 0) {
                htmlContent += `
                    <h3>Treatment History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Treatment</th>
                                <th>Prescription</th>
                                <th>Notes</th>
                                <th>Cost</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                treatmentData.data.forEach(treatment => {
                    htmlContent += `
                        <tr>
                            <td>${treatment.treatment || 'N/A'}</td>
                            <td>${treatment.prescription_given || 'N/A'}</td>
                            <td>${treatment.notes || 'N/A'}</td>
                            <td>₱${parseFloat(treatment.treatment_cost || 0).toFixed(2)}</td>
                            <td>${treatment.created_at || 'N/A'}</td>
                        </tr>
                    `;
                });
                htmlContent += `</tbody></table>`;
            }
            
            // Appointment History
            if (appointmentData.status === 'success' && appointmentData.data && appointmentData.data.length > 0) {
                htmlContent += `
                    <h3>Appointment History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Dentist</th>
                                <th>Service</th>
                                <th>Branch</th>
                                <th>Date</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                appointmentData.data.forEach(appointment => {
                    htmlContent += `
                        <tr>
                            <td>${appointment.appointment_id || 'N/A'}</td>
                            <td>${appointment.dentist_name || 'N/A'}</td>
                            <td>${appointment.service_name || 'N/A'}</td>
                            <td>${appointment.branch || 'N/A'}</td>
                            <td>${appointment.appointment_date || 'N/A'}</td>
                            <td>${appointment.appointment_time || 'N/A'}</td>
                        </tr>
                    `;
                });
                htmlContent += `</tbody></table>`;
            }
            
            // Transaction History
            if (transactionData.status === 'success' && transactionData.data) {
                htmlContent += `
                    <h3>Last Transaction</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Method</th>
                                <th>Account Name</th>
                                <th>Amount</th>
                                <th>Reference No</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${transactionData.data.payment_id || 'N/A'}</td>
                                <td>${transactionData.data.method || 'N/A'}</td>
                                <td>${transactionData.data.account_name || 'N/A'}</td>
                                <td>₱${parseFloat(transactionData.data.amount || 0).toFixed(2)}</td>
                                <td>${transactionData.data.reference_no || 'N/A'}</td>
                                <td>${transactionData.data.status || 'N/A'}</td>
                            </tr>
                        </tbody>
                    </table>
                `;
            }
            
            htmlContent += `
                    <div class="footer">
                        <p>Generated on ${currentDate}</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(htmlContent);
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }).catch(error => {
            console.error('Error generating print document:', error);
            alert('Error loading patient details. Please try again.');
        });
    }

    function handleEditPatientSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const patientId = document.getElementById('editPatientId').value;
        const patientName = document.getElementById('editFirstName').value + ' ' + document.getElementById('editLastName').value;
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        
        fetch('../controllers/updatePatient.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                // If it's HTML or redirect, assume success
                return { success: true };
            }
        })
        .then(data => {
            if (data.success || data.status === 'success' || !data.message) {
                showPatientUpdatedNotification(patientId, patientName);
                applyPatientEditToUI({
                    patientId: patientId,
                    firstName: document.getElementById('editFirstName').value.trim(),
                    lastName: document.getElementById('editLastName').value.trim(),
                    birthdate: document.getElementById('editBirthdate').value,
                    gender: document.getElementById('editGender').value.trim(),
                    email: document.getElementById('editEmail').value.trim(),
                    phone: document.getElementById('editPhone').value.trim(),
                    address: document.getElementById('editAddress').value.trim()
                });
                closeEditPatientModal();
            } else {
                showNotification('error', 'Error', data.message || 'Failed to update patient. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while updating patient. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    function applyPatientEditToUI(updatedPatient) {
        if (!updatedPatient || !updatedPatient.patientId) return;

        const fullName = `${updatedPatient.firstName} ${updatedPatient.lastName}`.trim();
        const birthDateObj = new Date(updatedPatient.birthdate);
        const validBirthDate = !Number.isNaN(birthDateObj.getTime());
        const today = new Date();
        let age = 0;
        if (validBirthDate) {
            age = today.getFullYear() - birthDateObj.getFullYear();
            const monthDiff = today.getMonth() - birthDateObj.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDateObj.getDate())) {
                age--;
            }
        }
        let ageCategory = 'senior';
        if (age <= 12) ageCategory = 'child';
        else if (age <= 19) ageCategory = 'teen';
        else if (age <= 59) ageCategory = 'adult';
        const searchText = `${updatedPatient.patientId} ${fullName} ${updatedPatient.email}`.toLowerCase();

        // Update desktop row
        const desktopRow = document.querySelector(`tr.patient-row[data-patient-id="${updatedPatient.patientId}"]`);
        if (desktopRow) {
            desktopRow.setAttribute('data-gender', (updatedPatient.gender || '').toLowerCase());
            desktopRow.setAttribute('data-age-category', ageCategory);
            desktopRow.setAttribute('data-search', searchText);
            desktopRow.setAttribute('data-age', String(age));

            const cells = desktopRow.querySelectorAll('td');
            if (cells[1]) cells[1].textContent = fullName;
            if (cells[2]) cells[2].textContent = validBirthDate ? birthDateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : updatedPatient.birthdate;
            if (cells[3]) cells[3].textContent = updatedPatient.gender;
            if (cells[4]) cells[4].textContent = updatedPatient.email;
            if (cells[5]) cells[5].textContent = updatedPatient.phone;
            const addressWrap = desktopRow.querySelector('.patients-desc');
            if (addressWrap) {
                addressWrap.textContent = updatedPatient.address;
                addressWrap.setAttribute('title', updatedPatient.address);
            }
        }

        // Update mobile card
        const mobileCard = document.querySelector(`.patient-card.patient-row[data-patient-id="${updatedPatient.patientId}"]`);
        if (mobileCard) {
            mobileCard.setAttribute('data-gender', (updatedPatient.gender || '').toLowerCase());
            mobileCard.setAttribute('data-age-category', ageCategory);
            mobileCard.setAttribute('data-search', searchText);
            mobileCard.setAttribute('data-age', String(age));

            const nameEl = mobileCard.querySelector('.patient-card-name');
            if (nameEl) nameEl.textContent = fullName;

            const valueEls = mobileCard.querySelectorAll('.patient-card-value');
            if (valueEls[0]) valueEls[0].textContent = `${validBirthDate ? birthDateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : updatedPatient.birthdate} (${age} years old)`;
            if (valueEls[1]) valueEls[1].textContent = updatedPatient.gender;
            if (valueEls[2]) valueEls[2].textContent = updatedPatient.email;
            if (valueEls[3]) valueEls[3].textContent = updatedPatient.phone;
            if (valueEls[4]) valueEls[4].textContent = updatedPatient.address;
        }

        // Re-apply active filters and pagination so UI stays consistent.
        filterPatients();
    }

    function editPatient(patientId) {
        const editModal = document.getElementById('editPatientModal');
        editModal.style.display = 'flex';
        
        fetch('../controllers/getPatients.php?patient_id=' + encodeURIComponent(patientId))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                document.getElementById('editPatientId').value = data.patient_id;
                document.getElementById('editFirstName').value = data.first_name || '';
                document.getElementById('editLastName').value = data.last_name || '';
                document.getElementById('editBirthdate').value = data.birthdate || '';
                document.getElementById('editGender').value = data.gender || 'Male';
                document.getElementById('editEmail').value = data.email || '';
                document.getElementById('editPhone').value = data.phone || '';
                document.getElementById('editAddress').value = data.address || '';
                
                // Focus on first input for better UX
                setTimeout(() => {
                    const firstInput = editModal.querySelector('#editFirstName');
                    if (firstInput) firstInput.focus();
                }, 100);
            })
            .catch(error => {
                console.error('Error fetching patient:', error);
                showNotification('error', 'Error Loading Patient', error.message || 'Failed to load patient details.');
            });
    }

    function closeEditPatientModal() {
        const editModal = document.getElementById('editPatientModal');
        const editForm = document.getElementById('editPatientForm');
        if (editModal) {
            editModal.style.display = 'none';
        }
        if (editForm) {
            // Clear any validation states
            editForm.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('is-invalid', 'is-valid');
            });
        }
    }

    function archivePatient(patientId) {
        if (!patientId || patientId <= 0) {
            showNotification('error', 'Invalid Input', 'Invalid patient ID. Please try again.');
            return;
        }

        // Use custom confirmation with notification
        if (confirm('Are you sure you want to archive this patient? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../controllers/archivePatient.php';

            const patientIdInput = document.createElement('input');
            patientIdInput.type = 'hidden';
            patientIdInput.name = 'patient_id';
            patientIdInput.value = patientId;

            form.appendChild(patientIdInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Filter Patients
    function filterPatients() {
        const searchInput = document.getElementById("filter-patient-search");
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
        patientsCurrentPage = 1;
        
        // Get visible rows and update pagination
        const visibleRows = getVisiblePatientsRows();
        
        // Check if we're on mobile/tablet
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Ensure we have rows before updating
        if (visibleRows.length > 0) {
            if (isMobileOrTablet) {
                // On mobile/tablet: Show all items, hide pagination
                updatePatientsPagination(visibleRows);
                showPatientsPage(visibleRows, 1);
            } else {
                // On desktop: Use pagination
                updatePatientsPagination(visibleRows);
                showPatientsPage(visibleRows, patientsCurrentPage);
            }
        } else {
            // Hide all rows if no matches
            const allRows = document.querySelectorAll(".patient-row");
            allRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            updatePatientsPagination([]);
        }
    }
    
    // Clear Patient Search
    function clearPatientSearch() {
        const searchInput = document.getElementById("filter-patient-search");
        const clearBtn = document.getElementById("clear-search-btn");
        
        searchInput.value = "";
        clearBtn.style.display = "none";
        filterPatients(); // Re-filter to show all patients
        searchInput.focus(); // Focus back on the search input
    }
    
    // Print Patients
    function printPatients() {
        window.print();
    }
    
    // Update Patients Pagination
    function updatePatientsPagination(visibleRows) {
        const totalRows = visibleRows.length;
        const totalPages = Math.ceil(totalRows / patientsRowsPerPage);
        const paginationContainer = document.getElementById("patients-pagination-container");
        const paginationInfo = document.getElementById("patients-pagination-info");
        const paginationNumbers = document.getElementById("patients-pagination-numbers");
        const prevBtn = document.getElementById("patients-prev-page-btn");
        const nextBtn = document.getElementById("patients-next-page-btn");

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
        if (patientsCurrentPage > totalPages && totalPages > 0) {
            patientsCurrentPage = totalPages;
        }
        if (patientsCurrentPage < 1) {
            patientsCurrentPage = 1;
        }

        // Update info
        const startRow = (patientsCurrentPage - 1) * patientsRowsPerPage + 1;
        const endRow = Math.min(patientsCurrentPage * patientsRowsPerPage, totalRows);
        if (paginationInfo) paginationInfo.textContent = `Showing ${startRow}-${endRow} of ${totalRows} patients`;

        // Update buttons
        if (prevBtn) prevBtn.disabled = patientsCurrentPage === 1;
        if (nextBtn) nextBtn.disabled = patientsCurrentPage >= totalPages;

        // Generate page numbers
        if (paginationNumbers) paginationNumbers.innerHTML = "";
        const maxPagesToShow = 5;
        let startPage = Math.max(1, patientsCurrentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        // First page and ellipsis
        if (startPage > 1 && paginationNumbers) {
            createPatientsPageNumber(1, paginationNumbers);
            if (startPage > 2) {
                createPatientsEllipsis(paginationNumbers);
            }
        }

        // Page numbers
        if (paginationNumbers) {
            for (let i = startPage; i <= endPage; i++) {
                createPatientsPageNumber(i, paginationNumbers);
            }
        }

        // Last page and ellipsis
        if (endPage < totalPages && paginationNumbers) {
            if (endPage < totalPages - 1) {
                createPatientsEllipsis(paginationNumbers);
            }
            createPatientsPageNumber(totalPages, paginationNumbers);
        }
    }

    // Create Patients page number button
    function createPatientsPageNumber(pageNum, container) {
        const pageBtn = document.createElement("button");
        pageBtn.className = "pagination-number" + (pageNum === patientsCurrentPage ? " active" : "");
        pageBtn.textContent = pageNum;
        pageBtn.onclick = () => goToPatientsPage(pageNum);
        container.appendChild(pageBtn);
    }

    // Create Patients ellipsis
    function createPatientsEllipsis(container) {
        const ellipsis = document.createElement("span");
        ellipsis.className = "pagination-number ellipsis";
        ellipsis.textContent = "...";
        container.appendChild(ellipsis);
    }

    // Show Patients specific page
    function showPatientsPage(visibleRows, page) {
        // Check if we're on mobile/tablet (no pagination)
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Hide all patient rows first (both table rows and cards)
        const allPatientRows = document.querySelectorAll(".patient-row");
        
        if (isMobileOrTablet) {
            // On mobile/tablet: Show all visible rows/cards (no pagination)
            allPatientRows.forEach(row => {
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
            allPatientRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            
            const startIndex = (page - 1) * patientsRowsPerPage;
            const endIndex = startIndex + patientsRowsPerPage;
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

    // Get visible Patients rows based on current filters
    function getVisiblePatientsRows() {
        const selectedGender = document.getElementById("filter-patient-gender").value.toLowerCase();
        const selectedAge = document.getElementById("filter-patient-age").value.toLowerCase();
        const searchInput = document.getElementById("filter-patient-search");
        const searchText = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const rows = document.querySelectorAll(".patient-row");
        const visibleRows = [];
        
        rows.forEach(row => {
            const rowGender = row.getAttribute("data-gender").toLowerCase();
            const rowAgeCategory = row.getAttribute("data-age-category").toLowerCase();
            const rowSearch = row.getAttribute("data-search").toLowerCase();
            
            const matchesGender = selectedGender === "" || rowGender === selectedGender;
            const matchesAge = selectedAge === "" || rowAgeCategory === selectedAge;
            const matchesSearch = searchText === "" || rowSearch.includes(searchText);
            
            if (matchesGender && matchesAge && matchesSearch) {
                visibleRows.push(row);
            }
        });
        
        return visibleRows;
    }

    // Go to Patients specific page
    function goToPatientsPage(page) {
        const visibleRows = getVisiblePatientsRows();
        if (visibleRows.length === 0) return;

        patientsCurrentPage = page;
        updatePatientsPagination(visibleRows);
        showPatientsPage(visibleRows, patientsCurrentPage);
    }

    // Change Patients page (previous/next)
    function changePatientsPage(direction) {
        const visibleRows = getVisiblePatientsRows();
        if (visibleRows.length === 0) return;

        const totalPages = Math.ceil(visibleRows.length / patientsRowsPerPage);
        const newPage = patientsCurrentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            goToPatientsPage(newPage);
        }
    }

    // Initialize pagination on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Update rows per page based on screen size
        updateRowsPerPage();
        
        // Ensure all rows are visible initially before filtering
        const allRows = document.querySelectorAll(".patient-row");
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
            filterPatients();
        }, 100);

        // Add form validation feedback for Edit Patient Form
        const editForm = document.getElementById('editPatientForm');
        if (editForm) {
            // Real-time validation feedback
            editForm.querySelectorAll('.form-control').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    } else if (this.value.trim()) {
                        this.classList.add('is-valid');
                        this.classList.remove('is-invalid');
                    }
                });
            });
        }
    });

    // Close modal when clicking outside
    window.addEventListener("click", function(event) {
        const editModal = document.getElementById("editPatientModal");
        if (editModal && event.target === editModal) {
            closeEditPatientModal();
        }
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
