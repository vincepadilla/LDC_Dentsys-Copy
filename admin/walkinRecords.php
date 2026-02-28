<?php
session_start();
include_once("../database/config.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../views/login.php");
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    header("Location: ../views/admin_verify.php");
    exit();
}

// Get walk-in appointments data with patient information
$walkinSql = "SELECT w.walkin_id, w.patient_id, w.service, w.sub_service, w.dentist_name, w.branch, w.status,
                     p.first_name, p.last_name
              FROM walkin_appointments w
              LEFT JOIN patient_information p ON w.patient_id = p.patient_id
              ORDER BY w.walkin_id DESC";
$walkinResult = mysqli_query($con, $walkinSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Records - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="walkinrecordsDesign.css">
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

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        thead th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #E5E7EB;
            transition: background-color 0.2s;
        }

        tbody tr:hover {
            background-color: #F9FAFB;
        }

        tbody td {
            padding: 15px;
            color: #1F2937;
            font-size: 14px;
        }

        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-walk-in {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .status-completed {
            background: #D1FAE5;
            color: #065F46;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-complete {
            background: #10B981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-complete:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .btn-complete:disabled {
            background: #9CA3AF;
            cursor: not-allowed;
            transform: none;
        }

        .btn-complete i {
            font-size: 12px;
        }

        .patient-link {
            color: #3B82F6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .patient-link:hover {
            color: #2563EB;
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6B7280;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.4;
            color: #9CA3AF;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }

        /* Filter Container Styles */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-group input::placeholder {
            color: #9CA3AF;
        }

        /* Mobile Card View Styles */
        .mobile-card-view {
            display: none;
        }

        .walkin-card {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .walkin-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .walkin-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #E5E7EB;
        }

        .walkin-card-id {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .walkin-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .walkin-card-body {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }

        .walkin-card-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .walkin-card-label {
            font-size: 12px;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .walkin-card-value {
            font-size: 14px;
            color: #1F2937;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .walkin-card-value i {
            color: #667eea;
            font-size: 14px;
            width: 16px;
        }

        .walkin-card-value .patient-link {
            color: #3B82F6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .walkin-card-value .patient-link:hover {
            color: #2563EB;
            text-decoration: underline;
        }

        .walkin-card-actions {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #E5E7EB;
        }

        .walkin-card-actions .btn-complete {
            flex: 1;
            justify-content: center;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            /* iPad and smaller tablets */
            .table-responsive {
                display: none !important;
            }

            .mobile-card-view {
                display: block !important;
                margin-top: 15px;
            }

            .walkin-card-body {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-container {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            /* Mobile devices */
            .table-responsive {
                display: none !important;
            }

            .mobile-card-view {
                display: block !important;
                margin-top: 15px;
            }

            .walkin-card {
                margin-bottom: 12px;
                padding: 15px;
            }

            .walkin-card-body {
                grid-template-columns: 1fr;
            }

            .walkin-card-header {
                flex-direction: column;
                gap: 10px;
            }

            .filter-container {
                padding: 15px;
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group input,
            .filter-group select {
                font-size: 14px;
                padding: 8px 12px;
            }

            .notification-container {
                right: 10px;
                top: 60px;
                max-width: calc(100% - 20px);
            }

            .notification {
                min-width: auto;
                width: 100%;
            }
        }

        @media (min-width: 1025px) {
            /* Desktop - show table, hide cards */
            .mobile-card-view {
                display: none !important;
            }

            .table-responsive {
                display: block !important;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fas fa-clipboard-list"></i> WALK-IN RECORDS</h2>
        <p style="color: #6b7280; margin-bottom: 30px;">Manage walk-in patient appointments and mark them as completed.</p>
        
        <!-- Filter Container -->
        <div class="filter-container" style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end;">
            <div class="filter-group" style="flex: 1; min-width: 200px;">
                <label for="search-walkin" style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;">
                    <i class="fas fa-search"></i> Search
                </label>
                <input type="text" 
                       id="search-walkin" 
                       placeholder="Search by Walk-in ID or Patient Name..." 
                       onkeyup="filterWalkinRecords()"
                       style="width: 100%; padding: 10px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; font-family: 'Poppins', sans-serif;">
            </div>
            
            <div class="filter-group" style="flex: 1; min-width: 200px;">
                <label for="filter-branch" style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;">
                    <i class="fas fa-building"></i> Branch
                </label>
                <select id="filter-branch" 
                        onchange="filterWalkinRecords()"
                        style="width: 100%; padding: 10px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; transition: border-color 0.3s; font-family: 'Poppins', sans-serif;">
                    <option value="">All Branches</option>
                    <option value="Comembo Branch">Comembo Branch</option>
                    <option value="Taytay Rizal Branch">Taytay Rizal Branch</option>
                </select>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="walkin-table">
                <thead>
                    <tr>
                        <th>Walk-in ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Services</th>
                        <th>Dentist</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($walkinResult) > 0) {
                        while ($row = mysqli_fetch_assoc($walkinResult)) { 
                            $patientName = trim($row['first_name'] . ' ' . $row['last_name']);
                            $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                            $isCompleted = strtolower($row['status']) === 'completed';
                            $searchText = strtolower($row['walkin_id'] . ' ' . $patientName . ' ' . $row['patient_id']);
                    ?>
                        <tr class="walkin-row" 
                            data-status="<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"
                            data-branch="<?php echo htmlspecialchars($row['branch']); ?>"
                            data-search="<?php echo htmlspecialchars($searchText); ?>"
                            data-patient-id="<?php echo htmlspecialchars($row['patient_id']); ?>"
                            data-walkin-id="<?php echo htmlspecialchars($row['walkin_id']); ?>">
                            <td><?php echo htmlspecialchars($row['walkin_id']); ?></td>
                            <td>
                                <a href="#" class="patient-link" onclick="viewPatient('<?php echo htmlspecialchars($row['patient_id']); ?>')">
                                    <?php echo htmlspecialchars($row['patient_id']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($patientName ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['sub_service'] ?: $row['service']); ?></td>
                            <td><?php echo htmlspecialchars($row['dentist_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['branch']); ?></td>
                            <td>
                                <span class="status <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-complete" 
                                            onclick="markAsCompleted('<?php echo htmlspecialchars($row['walkin_id']); ?>', this)"
                                            <?php echo $isCompleted ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo $isCompleted ? 'Completed' : 'Mark as Completed'; ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <p>No walk-in records found</p>
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
            mysqli_data_seek($walkinResult, 0);
            if(mysqli_num_rows($walkinResult) > 0) {
                while ($row = mysqli_fetch_assoc($walkinResult)) { 
                    $patientName = trim($row['first_name'] . ' ' . $row['last_name']);
                    $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                    $isCompleted = strtolower($row['status']) === 'completed';
                    $searchText = strtolower($row['walkin_id'] . ' ' . $patientName . ' ' . $row['patient_id']);
            ?>
                <div class="walkin-card walkin-row" 
                     data-status="<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"
                     data-branch="<?php echo htmlspecialchars($row['branch']); ?>"
                     data-search="<?php echo htmlspecialchars($searchText); ?>"
                     data-patient-id="<?php echo htmlspecialchars($row['patient_id']); ?>"
                     data-walkin-id="<?php echo htmlspecialchars($row['walkin_id']); ?>">
                    <div class="walkin-card-header">
                        <div>
                            <div class="walkin-card-id"><?php echo htmlspecialchars($row['walkin_id']); ?></div>
                            <div class="walkin-card-title">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($patientName ?: 'N/A'); ?>
                            </div>
                        </div>
                        <span class="status <?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </div>
                    <div class="walkin-card-body">
                        <div class="walkin-card-field">
                            <div class="walkin-card-label">Patient ID</div>
                            <div class="walkin-card-value">
                                <a href="#" class="patient-link" onclick="viewPatient('<?php echo htmlspecialchars($row['patient_id']); ?>')">
                                    <i class="fas fa-id-card"></i>
                                    <?php echo htmlspecialchars($row['patient_id']); ?>
                                </a>
                            </div>
                        </div>
                        <div class="walkin-card-field">
                            <div class="walkin-card-label">Services</div>
                            <div class="walkin-card-value">
                                <i class="fa-solid fa-teeth"></i>
                                <?php echo htmlspecialchars($row['sub_service'] ?: $row['service']); ?>
                            </div>
                        </div>
                        <div class="walkin-card-field">
                            <div class="walkin-card-label">Dentist</div>
                            <div class="walkin-card-value">
                                <i class="fas fa-user-doctor"></i>
                                <?php echo htmlspecialchars($row['dentist_name']); ?>
                            </div>
                        </div>
                        <div class="walkin-card-field">
                            <div class="walkin-card-label">Branch</div>
                            <div class="walkin-card-value">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($row['branch']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="walkin-card-actions">
                        <button class="btn-complete" 
                                onclick="markAsCompleted('<?php echo htmlspecialchars($row['walkin_id']); ?>', this)"
                                <?php echo $isCompleted ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle"></i>
                            <?php echo $isCompleted ? 'Completed' : 'Mark as Completed'; ?>
                        </button>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No walk-in records found</p>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- Complete Walk-in Modal -->
<div id="complete-walkin-modal" class="complete-walkin-modal">
    <div class="complete-walkin-content">
        <div class="complete-walkin-header">
            <h3><i class="fa-solid fa-check-to-slot"></i>Complete Walk-in Appointment</h3>
            <span class="complete-walkin-close">&times;</span>
        </div>
        <div class="complete-walkin-body">
            <form id="walkinTreatmentForm" onsubmit="handleWalkinTreatmentSubmit(event)">
                <input type="hidden" id="walkin_treatment_patient_id" name="patient_id">
                <input type="hidden" id="walkin_treatment_walkin_id" name="walkin_id">

                <div class="complete-walkin-form-group">
                    <label for="walkin_patient_id">Patient ID:</label>
                    <input type="text" id="walkin_patient_id" value="" readonly>
                </div>
                
                <div class="complete-walkin-form-group">
                    <label for="walkin_treatment_type">Treatment:</label>
                    <input type="text" id="walkin_treatment_type" name="treatment" required>
                </div>
                
                <div class="complete-walkin-form-group">
                    <label for="walkin_prescription_given">Prescription:</label>
                    <input type="text" id="walkin_prescription_given" name="prescription_given" required>
                </div>
                
                <div class="complete-walkin-form-group">
                    <label for="walkin_treatment_notes">Notes:</label>
                    <input type="text" id="walkin_treatment_notes" name="treatment_notes" required>
                </div>
                
                <div class="complete-walkin-form-group">
                    <label for="walkin_treatment_cost">Treatment Cost (₱):</label>
                    <input type="number" id="walkin_treatment_cost" name="treatment_cost" step="0.01" min="0" required>
                </div>
                
                <div class="complete-walkin-actions">
                    <button type="button" class="btn btn-danger" id="cancelCompleteWalkin">CANCEL</button>
                    <button type="submit" class="btn btn-completed">COMPLETE AND SAVE</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function navigateBack(event) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        setTimeout(() => {
            window.location.href = '../views/admin.php';
        }, 300);
    }

    function viewPatient(patientId) {
        // Navigate to patient details page
        window.location.href = `patients.php?patient_id=${patientId}`;
    }

    function markAsCompleted(walkinId, buttonElement) {
        // Get patient ID from the row data attribute
        const button = buttonElement || document.querySelector(`button[onclick*="${walkinId}"]`);
        const row = button.closest('.walkin-row');
        
        if (!row) {
            showNotification('error', 'Error', 'Row not found. Please refresh the page.');
            return;
        }
        
        const patientId = row.getAttribute('data-patient-id');
        
        if (!patientId) {
            showNotification('error', 'Error', 'Patient ID not found. Please refresh the page.');
            return;
        }
        
        // Open the modal
        openCompleteWalkinModal(walkinId, patientId);
    }
    
    // Complete Walk-in Modal Functions
    function openCompleteWalkinModal(walkinId, patientId) {
        if (!walkinId || !patientId) {
            showNotification('error', 'Error', 'Missing walk-in or patient information.');
            return;
        }
        
        const modal = document.getElementById('complete-walkin-modal');
        const patientIdInput = document.getElementById('walkin_treatment_patient_id');
        const walkinIdInput = document.getElementById('walkin_treatment_walkin_id');
        const patientIdDisplay = document.getElementById('walkin_patient_id');
        
        if (!modal) {
            showNotification('error', 'Error', 'Modal not found. Please refresh the page.');
            return;
        }
        
        if (!patientIdInput || !walkinIdInput || !patientIdDisplay) {
            showNotification('error', 'Error', 'Form elements not found. Please refresh the page.');
            return;
        }
        
        patientIdInput.value = patientId;
        walkinIdInput.value = walkinId;
        patientIdDisplay.value = patientId;

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeCompleteWalkinModal() {
        const modal = document.getElementById('complete-walkin-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            const form = document.getElementById('walkinTreatmentForm');
            if (form) form.reset();
        }
    }
    
    // Handle Walk-in Treatment Form Submit
    function handleWalkinTreatmentSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const walkinId = document.getElementById('walkin_treatment_walkin_id').value;
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        fetch('../controllers/saveWalkinTreatment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                return response.text().then(text => {
                    text = text.trim();
                    const jsonMatch = text.match(/\{[\s\S]*\}/);
                    if (jsonMatch) {
                        try {
                            return JSON.parse(jsonMatch[0]);
                        } catch (e) {
                            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                        }
                    }
                    throw new Error('No JSON found in response: ' + text.substring(0, 100));
                });
            }
        })
        .then(data => {
            if (data.success === true || data.status === 'success') {
                showNotification('success', 'Walk-in Completed', `Walk-in #${walkinId} has been completed and treatment saved.`);
                closeCompleteWalkinModal();
                form.reset();
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                const errorMsg = data.message || 'Failed to save treatment. Please try again.';
                showNotification('error', 'Error', errorMsg);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showNotification('error', 'Error', 'An error occurred while saving treatment: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // Notification System
    function showNotification(type, title, message, duration = 5000) {
        const container = document.getElementById('notificationContainer');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        let iconHTML = '';
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
        
        notification.innerHTML = `
            <div class="notification-icon">
                ${iconHTML}
            </div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">&times;</button>
        `;
        
        container.appendChild(notification);
        
        // Auto remove after duration
        setTimeout(() => {
            closeNotification(notification.querySelector('.notification-close'));
        }, duration);
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

    // Filter Walk-in Records
    function filterWalkinRecords() {
        const searchInput = document.getElementById('search-walkin');
        const branchFilter = document.getElementById('filter-branch');
        
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedBranch = branchFilter.value;
        
        // Get all rows (both table and cards)
        const tableRows = document.querySelectorAll('#walkin-table tbody .walkin-row');
        const cardRows = document.querySelectorAll('.mobile-card-view .walkin-card');
        
        let visibleCount = 0;
        
        // Filter table rows
        tableRows.forEach(row => {
            const branch = row.getAttribute('data-branch') || '';
            const searchData = row.getAttribute('data-search') || '';
            
            const matchesBranch = !selectedBranch || branch === selectedBranch;
            const matchesSearch = !searchTerm || searchData.includes(searchTerm);
            
            if (matchesBranch && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Filter card rows
        cardRows.forEach(card => {
            const branch = card.getAttribute('data-branch') || '';
            const searchData = card.getAttribute('data-search') || '';
            
            const matchesBranch = !selectedBranch || branch === selectedBranch;
            const matchesSearch = !searchTerm || searchData.includes(searchTerm);
            
            if (matchesBranch && matchesSearch) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Show empty state if no results
        const emptyStateTable = document.querySelector('#walkin-table tbody .empty-state');
        const emptyStateCard = document.querySelector('.mobile-card-view .empty-state');
        
        if (visibleCount === 0) {
            // Hide all rows first
            tableRows.forEach(row => row.style.display = 'none');
            cardRows.forEach(card => card.style.display = 'none');
            
            // Show empty state in table if it exists
            if (emptyStateTable) {
                emptyStateTable.style.display = '';
            } else if (tableRows.length > 0) {
                // Create empty state row if it doesn't exist
                const tbody = document.querySelector('#walkin-table tbody');
                if (tbody && !tbody.querySelector('.empty-state')) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.className = 'empty-state';
                    emptyRow.innerHTML = `
                        <td colspan="8" style="text-align: center; padding: 60px 20px; color: #6B7280;">
                            <i class="fas fa-clipboard-list" style="font-size: 64px; margin-bottom: 20px; opacity: 0.4; color: #9CA3AF;"></i>
                            <p style="font-size: 16px; font-weight: 500; margin: 0;">No walk-in records found matching your search</p>
                        </td>
                    `;
                    tbody.appendChild(emptyRow);
                }
            }
            
            // Show empty state in cards if it exists
            if (emptyStateCard) {
                emptyStateCard.style.display = 'block';
            } else if (cardRows.length > 0) {
                // Create empty state card if it doesn't exist
                const cardContainer = document.querySelector('.mobile-card-view');
                if (cardContainer && !cardContainer.querySelector('.empty-state')) {
                    const emptyCard = document.createElement('div');
                    emptyCard.className = 'empty-state';
                    emptyCard.innerHTML = `
                        <i class="fas fa-clipboard-list"></i>
                        <p>No walk-in records found matching your search</p>
                    `;
                    cardContainer.appendChild(emptyCard);
                }
            }
        } else {
            // Hide empty states if results are found
            if (emptyStateTable) {
                emptyStateTable.style.display = 'none';
            }
            if (emptyStateCard) {
                emptyStateCard.style.display = 'none';
            }
        }
    }
    
    // Event listeners for modal
    document.addEventListener('DOMContentLoaded', function() {
        const completeModal = document.getElementById('complete-walkin-modal');
        if (completeModal) {
            const closeBtn = completeModal.querySelector('.complete-walkin-close');
            const cancelBtn = document.getElementById('cancelCompleteWalkin');
            
            if (closeBtn) {
                closeBtn.addEventListener('click', closeCompleteWalkinModal);
            }
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeCompleteWalkinModal);
            }
            
            window.addEventListener('click', function(event) {
                if (event.target === completeModal) {
                    closeCompleteWalkinModal();
                }
            });
        }
    });
</script>
</body>
</html>
