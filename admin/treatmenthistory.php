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

// Get treatment history data
$historySql = "SELECT th.treatment_id, th.patient_id, th.treatment, th.prescription_given, th.treatment_cost, th.notes, th.created_at,
                      th.is_archived,
                      CONCAT(p.first_name, ' ', p.last_name) as patient_name
               FROM treatment_history th
               LEFT JOIN patient_information p ON th.patient_id = p.patient_id
               ORDER BY th.created_at DESC";
$historyResult = mysqli_query($con, $historySql);

// Get unique treatments for filter
$treatmentsQuery = "SELECT DISTINCT treatment FROM treatment_history WHERE treatment IS NOT NULL AND treatment != '' ORDER BY treatment";
$treatmentsResult = mysqli_query($con, $treatmentsQuery);
$treatments = [];
while ($treatmentRow = mysqli_fetch_assoc($treatmentsResult)) {
    $treatments[] = $treatmentRow['treatment'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment History - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="treatmenthistoryDesign.css">
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

        /* Icon-only action buttons (Edit/Delete) */
        .action-btn.action-btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 999px;
        }
        .action-btn.action-btn-icon i {
            font-size: 14px;
        }
        .patient-card-actions .action-btn.action-btn-icon {
            flex: 0 0 auto !important;
            min-width: auto !important;
            padding: 0 !important;
            width: 36px !important;
            height: 36px !important;
            justify-content: center;
        }
        /* Keep Edit/Delete icons horizontally on small screens */
        @media (max-width: 768px) {
            .patient-card-actions .action-btns {
                flex-direction: row !important;
            }
        }

        /* Modern Modals (styled like Admin Services "Add/Edit Service" popups) */
        #editTreatmentModal.modal-overlay,
        #deleteTreatmentConfirmModal.modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 900;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        #editTreatmentModal .modal-panel,
        #deleteTreatmentConfirmModal .modal-panel {
            background: #fff;
            width: min(900px, 95%);
            max-height: 95vh;
            border-radius: 18px;
            padding: 35px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            animation: treatmentModalSlideIn 0.3s ease-out;
        }

        /* Restore modal: compact, centered, fade+scale */
        #restoreTreatmentConfirmModal.modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        #restoreTreatmentConfirmModal .modal-panel {
            background: #fff;
            width: min(560px, 95%);
            max-height: 90vh;
            border-radius: 18px;
            padding: 28px 28px 24px 28px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
            position: relative;
            overflow: hidden;
            animation: restoreModalIn 0.22s ease-out;
        }
        @keyframes restoreModalIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes restoreModalOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.96); }
        }
        #restoreTreatmentConfirmModal .modal-heading h3 {
            margin: 8px 0;
            font-size: 20px;
            color: #111827;
        }
        #restoreTreatmentConfirmModal .modal-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        /* Centered Alert Modal (for precise success/error messages) */
        #centerAlertModal.modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 10001;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        #centerAlertModal .modal-panel {
            background: #fff;
            width: min(520px, 92%);
            border-radius: 16px;
            padding: 24px 24px 20px 24px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
            position: relative;
            animation: restoreModalIn 0.2s ease-out;
        }
        #centerAlertModal .modal-heading h3 {
            margin: 0 0 6px 0;
            font-size: 18px;
            color: #111827;
        }
        #centerAlertModal .modal-body {
            color: #4b5563;
            font-size: 14px;
        }
        #centerAlertModal .modal-actions {
            margin-top: 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        @keyframes treatmentModalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        #editTreatmentModal .modal-close,
        #deleteTreatmentConfirmModal .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: #f3f4f6;
            color: #374151;
            font-size: 16px;
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        #editTreatmentModal .modal-close:hover,
        #deleteTreatmentConfirmModal .modal-close:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        #editTreatmentModal .modal-heading,
        #deleteTreatmentConfirmModal .modal-heading {
            margin-bottom: 28px;
        }
        #editTreatmentModal .modal-heading h3,
        #deleteTreatmentConfirmModal .modal-heading h3 {
            margin: 8px 0;
            font-size: 22px;
            color: #111827;
        }
        #editTreatmentModal .modal-heading p,
        #deleteTreatmentConfirmModal .modal-heading p {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
        }

        #editTreatmentModal .modal-badge,
        #deleteTreatmentConfirmModal .modal-badge {
            display: inline-flex;
            padding: 6px 12px;
            background: #ecfeff;
            color: #036466;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        #editTreatmentModal .modal-badge.accent,
        #deleteTreatmentConfirmModal .modal-badge.accent {
            background: #e0f2fe;
            color: #0f172a;
        }

        #editTreatmentModal .modal-form .form-grid,
        #deleteTreatmentConfirmModal .modal-form .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 24px;
        }

        #editTreatmentModal .form-group,
        #deleteTreatmentConfirmModal .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        #editTreatmentModal .form-group.full-width,
        #deleteTreatmentConfirmModal .form-group.full-width {
            grid-column: 1 / -1;
        }

        #editTreatmentModal .form-label,
        #deleteTreatmentConfirmModal .form-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        #editTreatmentModal .form-label .required,
        #deleteTreatmentConfirmModal .form-label .required {
            color: #EF4444;
            font-weight: 700;
        }

        #editTreatmentModal .form-control,
        #deleteTreatmentConfirmModal .form-control {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            font-size: 15px;
            background: #f8fafc;
            transition: border 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            font-family: inherit;
        }
        #editTreatmentModal .form-control:focus,
        #deleteTreatmentConfirmModal .form-control:focus {
            outline: none;
            border-color: #48A6A7;
            box-shadow: 0 0 0 2px rgba(72, 166, 167, 0.15);
            background: #fff;
        }
        #editTreatmentModal textarea.form-control,
        #deleteTreatmentConfirmModal textarea.form-control {
            resize: vertical;
            min-height: 70px;
            font-family: inherit;
        }

        #editTreatmentModal .modal-actions,
        #deleteTreatmentConfirmModal .modal-actions {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        #editTreatmentModal .btn-wide,
        #deleteTreatmentConfirmModal .btn-wide {
            flex: 0 1 auto;
            min-width: 180px;
        }

        #editTreatmentModal .btn-success,
        #deleteTreatmentConfirmModal .btn-success {
            background: #10B981;
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }
        #editTreatmentModal .btn-success:hover,
        #deleteTreatmentConfirmModal .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        #editTreatmentModal .btn-link,
        #deleteTreatmentConfirmModal .btn-link {
            border: 2px solid #6b7280;
            background: #f3f4f6;
            color: #374151;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 15px;
            min-width: 120px;
        }
        #editTreatmentModal .btn-link:hover,
        #deleteTreatmentConfirmModal .btn-link:hover {
            background: #e5e7eb;
            border-color: #4b5563;
            color: #111827;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        #editTreatmentModal .btn-danger,
        #deleteTreatmentConfirmModal .btn-danger {
            background: #EF4444;
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);
        }
        #editTreatmentModal .btn-danger:hover,
        #deleteTreatmentConfirmModal .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 768px) {
            #editTreatmentModal .modal-panel,
            #deleteTreatmentConfirmModal .modal-panel {
                width: calc(100% - 20px);
                padding: 20px;
                max-height: 90vh;
            }
            #editTreatmentModal .modal-form .form-grid,
            #deleteTreatmentConfirmModal .modal-form .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            #editTreatmentModal .modal-actions,
            #deleteTreatmentConfirmModal .modal-actions {
                flex-direction: column-reverse;
                gap: 10px;
            }
            #editTreatmentModal .btn-wide,
            #deleteTreatmentConfirmModal .btn-wide,
            #editTreatmentModal .btn-link,
            #deleteTreatmentConfirmModal .btn-link {
                width: 100%;
                min-width: auto;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- Treatment History Section -->
<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fa-solid fa-notes-medical"></i> PATIENT TREATMENT HISTORY</h2>

        <div class="filter-container">
            <div class="filter-group">
                <label for="filter-treatment-type"><i class="fas fa-stethoscope"></i> Treatment:</label>
                <select id="filter-treatment-type" onchange="filterTreatmentHistory()">
                    <option value="">All Treatments</option>
                    <?php foreach ($treatments as $treatment): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($treatment)); ?>">
                            <?php echo htmlspecialchars($treatment); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group search-group">
                <label for="filter-treatment-search"><i class="fas fa-search"></i> Search:</label>
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="filter-treatment-search" class="search-input" 
                           placeholder="Search by patient ID, name, treatment..." onkeyup="filterTreatmentHistory()">
                    <button type="button" class="search-clear-btn" id="clear-search-btn" onclick="clearTreatmentSearch()" style="display:none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <button class="btn btn-accent" onclick="printTreatmentHistory()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <div class="table-responsive">
            <table id="treatment-history-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Treatment</th>
                        <th>Prescription Given</th>
                        <th>Treatment Cost</th>
                        <th>Notes</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($historyResult) > 0) {
                        mysqli_data_seek($historyResult, 0);
                        while ($row = mysqli_fetch_assoc($historyResult)) { 
                            $searchText = strtolower($row['patient_id'] . ' ' . ($row['patient_name'] ?? '') . ' ' . $row['treatment']);
                    ?>
                        <tr class="history-row"
                            data-treatment-id="<?php echo htmlspecialchars($row['treatment_id'] ?? ''); ?>"
                            data-treatment="<?php echo htmlspecialchars(strtolower($row['treatment'] ?? '')); ?>"
                            data-treatment-raw="<?php echo htmlspecialchars($row['treatment'] ?? ''); ?>"
                            data-prescription-given="<?php echo htmlspecialchars($row['prescription_given'] ?? ''); ?>"
                            data-treatment-cost="<?php echo htmlspecialchars($row['treatment_cost'] ?? ''); ?>"
                            data-notes="<?php echo htmlspecialchars($row['notes'] ?? ''); ?>"
                            data-created-at="<?php echo htmlspecialchars($row['created_at'] ?? ''); ?>"
                            data-archived="<?php echo htmlspecialchars($row['is_archived'] ?? '0'); ?>"
                            data-patient-id="<?php echo htmlspecialchars($row['patient_id'] ?? ''); ?>"
                            data-patient-name="<?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?>"
                            data-search="<?php echo htmlspecialchars($searchText); ?>">
                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['treatment']); ?></td>
                            <td><?php echo htmlspecialchars($row['prescription_given']); ?></td>
                            <td>₱<?php echo number_format($row['treatment_cost'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="action-btn btn-success action-btn-icon restore-action-btn" title="Restore"
                                            onclick="openRestoreTreatmentModal('<?php echo htmlspecialchars($row['treatment_id']); ?>')" style="display: <?php echo (!empty($row['is_archived']) && intval($row['is_archived']) === 1) ? 'inline-flex' : 'none'; ?>;">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <button type="button" class="action-btn btn-primary action-btn-icon" title="Edit"
                                            onclick="openEditTreatmentModal('<?php echo htmlspecialchars($row['treatment_id']); ?>')">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>

                                    <button type="button" class="action-btn btn-danger action-btn-icon" title="Delete"
                                            onclick="openDeleteTreatmentModal('<?php echo htmlspecialchars($row['treatment_id']); ?>')">
                                        <i class="fa-solid fa-trash-alt"></i>
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
                                <i class="fas fa-calendar-times fa-2x"></i>
                                <p>No Treatment History found</p>
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
            mysqli_data_seek($historyResult, 0);
            if(mysqli_num_rows($historyResult) > 0) {
                while ($row = mysqli_fetch_assoc($historyResult)) { 
                    $searchText = strtolower($row['patient_id'] . ' ' . ($row['patient_name'] ?? '') . ' ' . $row['treatment']);
            ?>
                <div class="patient-card history-row"
                     data-treatment-id="<?php echo htmlspecialchars($row['treatment_id'] ?? ''); ?>"
                     data-treatment="<?php echo htmlspecialchars(strtolower($row['treatment'] ?? '')); ?>"
                     data-treatment-raw="<?php echo htmlspecialchars($row['treatment'] ?? ''); ?>"
                     data-prescription-given="<?php echo htmlspecialchars($row['prescription_given'] ?? ''); ?>"
                     data-treatment-cost="<?php echo htmlspecialchars($row['treatment_cost'] ?? ''); ?>"
                     data-notes="<?php echo htmlspecialchars($row['notes'] ?? ''); ?>"
                     data-created-at="<?php echo htmlspecialchars($row['created_at'] ?? ''); ?>"
                     data-archived="<?php echo htmlspecialchars($row['is_archived'] ?? '0'); ?>"
                     data-patient-id="<?php echo htmlspecialchars($row['patient_id'] ?? ''); ?>"
                     data-patient-name="<?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?>"
                     data-search="<?php echo htmlspecialchars($searchText); ?>">
                    <div class="patient-card-header">
                        <div>
                            <div class="patient-card-id">Patient #<?php echo htmlspecialchars($row['patient_id']); ?></div>
                            <div class="patient-card-name"><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="patient-card-body">
                        <div class="patient-card-field">
                            <div class="patient-card-label">Treatment</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['treatment']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Prescription</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['prescription_given']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Cost</div>
                            <div class="patient-card-value">₱<?php echo number_format($row['treatment_cost'], 2); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Notes</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['notes']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Date</div>
                            <div class="patient-card-value"><?php echo date('M j, Y', strtotime($row['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="patient-card-actions">
                        <div class="action-btns">
                            <button type="button" class="action-btn btn-success action-btn-icon restore-action-btn" title="Restore"
                                    onclick="openRestoreTreatmentModal('<?php echo htmlspecialchars($row['treatment_id']); ?>')" style="display: <?php echo (!empty($row['is_archived']) && intval($row['is_archived']) === 1) ? 'inline-flex' : 'none'; ?>;">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            <button type="button" class="action-btn btn-primary action-btn-icon" title="Edit"
                                    onclick="openEditTreatmentModal('<?php echo htmlspecialchars($row['treatment_id']); ?>')">
                                <i class="fa-solid fa-edit"></i>
                            </button>
                            <button type="button" class="action-btn btn-danger action-btn-icon" title="Delete"
                                    onclick="openDeleteTreatmentModal('<?php echo htmlspecialchars($row['treatment_id']); ?>')">
                                <i class="fa-solid fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-calendar-times fa-2x"></i>
                    <p>No Treatment History found</p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Pagination Controls -->
        <div class="pagination-container" id="treatment-pagination-container">
            <div class="pagination-info" id="treatment-pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="treatment-prev-page-btn" onclick="changeTreatmentPage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-numbers" id="treatment-pagination-numbers"></div>
                <button class="pagination-btn" id="treatment-next-page-btn" onclick="changeTreatmentPage(1)" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Treatment Modal -->
<div id="editTreatmentModal" class="modal-overlay" style="display:none;">
    <div class="modal-panel">
        <button class="modal-close" onclick="closeEditTreatmentModal()" aria-label="Close edit treatment dialog" type="button">
            <i class="fas fa-times"></i>
        </button>

        <div class="modal-heading">
            <span class="modal-badge accent">Edit treatment</span>
            <h3>Update treatment record</h3>
            <p id="editTreatmentModalSubtitle">Edit the details below and update the record.</p>
        </div>

        <form id="editTreatmentForm" method="POST" class="modal-form">
            <input type="hidden" name="treatment_id" id="editTreatmentId">

            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label" for="editTreatment">
                        Treatment <span class="required">*</span>
                    </label>
                    <input type="text" name="treatment" id="editTreatment" class="form-control" placeholder="e.g., Root canal treatment" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editPrescriptionGiven">
                        Prescription Given <span class="required">*</span>
                    </label>
                    <input type="text" name="prescription_given" id="editPrescriptionGiven" class="form-control" placeholder="e.g., Amoxicillin" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editTreatmentCost">
                        Treatment Cost (₱) <span class="required">*</span>
                    </label>
                    <input type="number" name="treatment_cost" id="editTreatmentCost" class="form-control" step="0.01" min="0" required placeholder="0.00">
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="editTreatmentNotes">
                        Notes <span class="required">*</span>
                    </label>
                    <textarea name="treatment_notes" id="editTreatmentNotes" class="form-control" rows="3" placeholder="Treatment notes" required></textarea>
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-success btn-wide">
                    <i class="fas fa-pencil-alt"></i> Update Treatment
                </button>
                <button type="button" onclick="closeEditTreatmentModal()" class="btn btn-link">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteTreatmentConfirmModal" class="modal-overlay" style="display:none;">
    <div class="modal-panel" style="width: min(650px, 95%);">
        <button class="modal-close" onclick="closeDeleteTreatmentConfirmModal()" aria-label="Close delete confirmation dialog" type="button">
            <i class="fas fa-times"></i>
        </button>

        <div class="modal-heading">
            <span class="modal-badge">Delete treatment</span>
            <h3>Confirm deletion</h3>
            <p id="deleteTreatmentSummary">This action cannot be undone.</p>
        </div>

        <form id="deleteTreatmentForm" method="POST" style="margin:0;">
            <input type="hidden" name="treatment_id" id="deleteTreatmentId">
            <div class="modal-actions">
                <button type="button" onclick="closeDeleteTreatmentConfirmModal()" class="btn btn-link">Cancel</button>
                <button type="button" id="confirmDeleteTreatmentBtn" class="btn btn-danger btn-wide">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restoreTreatmentConfirmModal" class="modal-overlay" style="display:none;">
    <div class="modal-panel" style="width: min(650px, 95%);">
        <div class="modal-heading">
            <span class="modal-badge accent">Restore treatment</span>
            <h3>Restore Treatment Record</h3>
            <p id="restoreTreatmentSummary">Are you sure you want to restore this treatment record?</p>
        </div>

        <form id="restoreTreatmentForm" method="POST" style="margin:0;">
            <input type="hidden" name="treatment_id" id="restoreTreatmentId">
            <div class="modal-actions">
                <button type="button" onclick="closeRestoreTreatmentConfirmModal()" class="btn btn-link">Cancel</button>
                <button type="button" id="confirmRestoreTreatmentBtn" class="btn btn-success btn-wide">
                    <i class="fa-solid fa-rotate-left"></i> Restore
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Centered Alert Modal -->
<div id="centerAlertModal" class="modal-overlay">
    <div class="modal-panel">
        <div class="modal-heading">
            <h3 id="centerAlertTitle">Notice</h3>
        </div>
        <div class="modal-body" id="centerAlertMessage">Message</div>
        <div class="modal-actions">
            <button type="button" class="btn btn-success" onclick="closeCenterAlert()">OK</button>
        </div>
    </div>
    </div>

<script>
    // Pagination state for Treatment History
    let treatmentCurrentPage = 1;
    let treatmentRowsPerPage = 5;
    
    // Detect mobile/tablet and adjust rows per page
    function updateRowsPerPage() {
        if (window.innerWidth <= 1024) {
            // Mobile and tablet: 2 cards per page
            treatmentRowsPerPage = 2;
        } else {
            // Desktop: 5 rows per page
            treatmentRowsPerPage = 5;
        }
    }
    
    // Update on load and resize
    updateRowsPerPage();
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const oldRowsPerPage = treatmentRowsPerPage;
            updateRowsPerPage();
            if (oldRowsPerPage !== treatmentRowsPerPage && typeof getVisibleTreatmentRows === 'function') {
                // Recalculate pagination if rows per page changed
                treatmentCurrentPage = 1;
                const visibleRows = getVisibleTreatmentRows();
                if (typeof updateTreatmentPagination === 'function' && typeof showTreatmentPage === 'function') {
                    updateTreatmentPagination(visibleRows);
                    showTreatmentPage(visibleRows, treatmentCurrentPage);
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
    
    // Utility function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Filter Treatment History
    function filterTreatmentHistory() {
        const searchInput = document.getElementById("filter-treatment-search");
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
        treatmentCurrentPage = 1;
        
        // Get visible rows and update pagination
        const visibleRows = getVisibleTreatmentRows();
        
        // Check if we're on mobile/tablet
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Ensure we have rows before updating
        if (visibleRows.length > 0) {
            if (isMobileOrTablet) {
                // On mobile/tablet: Show all items, hide pagination
                updateTreatmentPagination(visibleRows);
                showTreatmentPage(visibleRows, 1);
            } else {
                // On desktop: Use pagination
                updateTreatmentPagination(visibleRows);
                showTreatmentPage(visibleRows, treatmentCurrentPage);
            }
        } else {
            // Hide all rows if no matches
            const allRows = document.querySelectorAll(".history-row");
            allRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            updateTreatmentPagination([]);
        }
    }
    
    // Clear Treatment Search
    function clearTreatmentSearch() {
        const searchInput = document.getElementById("filter-treatment-search");
        const clearBtn = document.getElementById("clear-search-btn");
        
        searchInput.value = "";
        clearBtn.style.display = "none";
        filterTreatmentHistory(); // Re-filter to show all treatments
        searchInput.focus(); // Focus back on the search input
    }
    
    // Print Treatment History (All)
    function printTreatmentHistory() {
        window.print();
    }
    
    // Print Treatment History by Patient
    function printTreatmentHistoryByPatient(patientId) {
        // Fetch patient information
        Promise.all([
            fetch('../controllers/getPatients.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ patient_id: patientId, first_name: '', last_name: '' })),
            fetch('../controllers/getTreatmentHistory.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ status: 'error', data: [] }))
        ]).then(([patientData, treatmentData]) => {
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
                    <title>Treatment History - ${patientName}</title>
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
                        .total-cost {
                            margin-top: 20px;
                            text-align: right;
                            font-size: 16px;
                            font-weight: bold;
                            color: #2c3e50;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Landero Dental Clinic</h1>
                        <h2>Patient Treatment History Report</h2>
                    </div>
                    
                    <div class="patient-info">
                        <p><strong>Patient ID:</strong> ${patientId}</p>
                        <p><strong>Patient Name:</strong> ${patientName}</p>
                        <p><strong>Report Date:</strong> ${currentDate}</p>
                    </div>
            `;
            
            if (treatmentData.status === 'success' && treatmentData.data && treatmentData.data.length > 0) {
                htmlContent += `
                    <table>
                        <thead>
                            <tr>
                                <th>Treatment</th>
                                <th>Prescription Given</th>
                                <th>Notes</th>
                                <th>Treatment Cost</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                let totalCost = 0;
                treatmentData.data.forEach(treatment => {
                    const cost = parseFloat(treatment.treatment_cost) || 0;
                    totalCost += cost;
                    htmlContent += `
                        <tr>
                            <td>${treatment.treatment || 'N/A'}</td>
                            <td>${treatment.prescription_given || 'N/A'}</td>
                            <td>${treatment.notes || 'N/A'}</td>
                            <td>₱${cost.toFixed(2)}</td>
                            <td>${treatment.created_at || 'N/A'}</td>
                        </tr>
                    `;
                });
                
                htmlContent += `
                        </tbody>
                    </table>
                    <div class="total-cost">
                        <strong>Total Treatment Cost: ₱${totalCost.toFixed(2)}</strong>
                    </div>
                `;
            } else {
                htmlContent += `
                    <div class="no-data">
                        <p>No treatment history found for this patient.</p>
                    </div>
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
            
            // Wait for content to load, then print
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }).catch(error => {
            console.error('Error generating print document:', error);
            showNotification('error', 'Error', 'Error loading treatment history. Please try again.');
        });
    }
    
    // Update Treatment Pagination
    function updateTreatmentPagination(visibleRows) {
        const totalRows = visibleRows.length;
        const totalPages = Math.ceil(totalRows / treatmentRowsPerPage);
        const paginationContainer = document.getElementById("treatment-pagination-container");
        const paginationInfo = document.getElementById("treatment-pagination-info");
        const paginationNumbers = document.getElementById("treatment-pagination-numbers");
        const prevBtn = document.getElementById("treatment-prev-page-btn");
        const nextBtn = document.getElementById("treatment-next-page-btn");

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
        if (treatmentCurrentPage > totalPages && totalPages > 0) {
            treatmentCurrentPage = totalPages;
        }
        if (treatmentCurrentPage < 1) {
            treatmentCurrentPage = 1;
        }

        // Update info
        const startRow = (treatmentCurrentPage - 1) * treatmentRowsPerPage + 1;
        const endRow = Math.min(treatmentCurrentPage * treatmentRowsPerPage, totalRows);
        if (paginationInfo) paginationInfo.textContent = `Showing ${startRow}-${endRow} of ${totalRows} records`;

        // Update buttons
        if (prevBtn) prevBtn.disabled = treatmentCurrentPage === 1;
        if (nextBtn) nextBtn.disabled = treatmentCurrentPage >= totalPages;

        // Generate page numbers
        if (paginationNumbers) paginationNumbers.innerHTML = "";
        const maxPagesToShow = 5;
        let startPage = Math.max(1, treatmentCurrentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        // First page and ellipsis
        if (startPage > 1 && paginationNumbers) {
            createTreatmentPageNumber(1, paginationNumbers);
            if (startPage > 2) {
                createTreatmentEllipsis(paginationNumbers);
            }
        }

        // Page numbers
        if (paginationNumbers) {
            for (let i = startPage; i <= endPage; i++) {
                createTreatmentPageNumber(i, paginationNumbers);
            }
        }

        // Last page and ellipsis
        if (endPage < totalPages && paginationNumbers) {
            if (endPage < totalPages - 1) {
                createTreatmentEllipsis(paginationNumbers);
            }
            createTreatmentPageNumber(totalPages, paginationNumbers);
        }
    }

    // Create Treatment page number button
    function createTreatmentPageNumber(pageNum, container) {
        const pageBtn = document.createElement("button");
        pageBtn.className = "pagination-number" + (pageNum === treatmentCurrentPage ? " active" : "");
        pageBtn.textContent = pageNum;
        pageBtn.onclick = () => goToTreatmentPage(pageNum);
        container.appendChild(pageBtn);
    }

    // Create Treatment ellipsis
    function createTreatmentEllipsis(container) {
        const ellipsis = document.createElement("span");
        ellipsis.className = "pagination-number ellipsis";
        ellipsis.textContent = "...";
        container.appendChild(ellipsis);
    }

    // Show Treatment specific page
    function showTreatmentPage(visibleRows, page) {
        // Check if we're on mobile/tablet (no pagination)
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Hide all treatment rows first (both table rows and cards)
        const allTreatmentRows = document.querySelectorAll(".history-row");
        
        if (isMobileOrTablet) {
            // On mobile/tablet: Show all visible rows/cards (no pagination)
            allTreatmentRows.forEach(row => {
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
            allTreatmentRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            
            const startIndex = (page - 1) * treatmentRowsPerPage;
            const endIndex = startIndex + treatmentRowsPerPage;
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

    // Get visible Treatment rows based on current filters
    function getVisibleTreatmentRows() {
        const selectedTreatment = document.getElementById("filter-treatment-type").value.toLowerCase();
        const searchInput = document.getElementById("filter-treatment-search");
        const searchText = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const rows = document.querySelectorAll(".history-row");
        const visibleRows = [];
        
        rows.forEach(row => {
            const rowTreatment = row.getAttribute("data-treatment").toLowerCase();
            const rowSearch = row.getAttribute("data-search").toLowerCase();
            
            const matchesTreatment = selectedTreatment === "" || rowTreatment === selectedTreatment;
            const matchesSearch = searchText === "" || rowSearch.includes(searchText);
            
            if (matchesTreatment && matchesSearch) {
                visibleRows.push(row);
            }
        });
        
        return visibleRows;
    }

    // Go to Treatment specific page
    function goToTreatmentPage(page) {
        const visibleRows = getVisibleTreatmentRows();
        if (visibleRows.length === 0) return;

        treatmentCurrentPage = page;
        updateTreatmentPagination(visibleRows);
        showTreatmentPage(visibleRows, treatmentCurrentPage);
    }

    // Change Treatment page (previous/next)
    function changeTreatmentPage(direction) {
        const visibleRows = getVisibleTreatmentRows();
        if (visibleRows.length === 0) return;

        const totalPages = Math.ceil(visibleRows.length / treatmentRowsPerPage);
        const newPage = treatmentCurrentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            goToTreatmentPage(newPage);
        }
    }

    // Initialize pagination on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Update rows per page based on screen size
        updateRowsPerPage();
        
        // Ensure all rows are visible initially before filtering
        const allRows = document.querySelectorAll(".history-row");
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
            filterTreatmentHistory();
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

    // ==================== Edit/Delete Treatment Modals ====================

    function closeEditTreatmentModal() {
        const modal = document.getElementById('editTreatmentModal');
        if (modal) modal.style.display = 'none';
    }

    function openEditTreatmentModal(treatmentId) {
        const modal = document.getElementById('editTreatmentModal');
        const editIdInput = document.getElementById('editTreatmentId');
        const treatmentInput = document.getElementById('editTreatment');
        const prescriptionInput = document.getElementById('editPrescriptionGiven');
        const costInput = document.getElementById('editTreatmentCost');
        const notesInput = document.getElementById('editTreatmentNotes');
        const subtitle = document.getElementById('editTreatmentModalSubtitle');

        if (!modal || !editIdInput || !treatmentInput || !prescriptionInput || !costInput || !notesInput) return;

        const rowEl = document.querySelector('.history-row[data-treatment-id="' + treatmentId + '"]');
        if (!rowEl) {
            showNotification('error', 'Error', 'Treatment record not found.');
            return;
        }

        const patientId = rowEl.getAttribute('data-patient-id') || '';
        const patientName = rowEl.getAttribute('data-patient-name') || '';

        const treatmentRaw = rowEl.getAttribute('data-treatment-raw') || '';
        const prescriptionGiven = rowEl.getAttribute('data-prescription-given') || '';
        const treatmentCost = rowEl.getAttribute('data-treatment-cost') || '';
        const notes = rowEl.getAttribute('data-notes') || '';

        editIdInput.value = treatmentId;
        treatmentInput.value = treatmentRaw;
        prescriptionInput.value = prescriptionGiven;

        const costNum = parseFloat(treatmentCost);
        costInput.value = Number.isFinite(costNum) ? costNum.toFixed(2) : '';
        notesInput.value = notes;

        if (subtitle) {
            const who = patientName && patientName !== 'N/A'
                ? `${patientName} (Patient ${patientId})`
                : `Patient ${patientId}`;
            subtitle.textContent = `Editing record for ${who}.`;
        }

        modal.style.display = 'flex';
        setTimeout(() => {
            const firstField = modal.querySelector('#editTreatment');
            if (firstField) firstField.focus();
        }, 50);
    }

    function closeDeleteTreatmentConfirmModal() {
        const modal = document.getElementById('deleteTreatmentConfirmModal');
        if (modal) modal.style.display = 'none';
    }

    function openDeleteTreatmentModal(treatmentId) {
        const modal = document.getElementById('deleteTreatmentConfirmModal');
        const idInput = document.getElementById('deleteTreatmentId');
        const summary = document.getElementById('deleteTreatmentSummary');

        if (!modal || !idInput) return;

        const rowEl = document.querySelector('.history-row[data-treatment-id="' + treatmentId + '"]');
        if (!rowEl) {
            showNotification('error', 'Error', 'Treatment record not found.');
            return;
        }

        const patientId = rowEl.getAttribute('data-patient-id') || '';
        const patientName = rowEl.getAttribute('data-patient-name') || '';
        const treatmentRaw = rowEl.getAttribute('data-treatment-raw') || '';

        idInput.value = treatmentId;
        if (summary) {
            const who = patientName && patientName !== 'N/A'
                ? `${patientName} (Patient ${patientId})`
                : `Patient #${patientId}`;
            summary.textContent = `Delete this treatment record for ${who}`;
        }

        modal.style.display = 'flex';
    }

    function closeRestoreTreatmentConfirmModal() {
        const modal = document.getElementById('restoreTreatmentConfirmModal');
        if (modal) modal.style.display = 'none';
    }

    function openRestoreTreatmentModal(treatmentId) {
        const modal = document.getElementById('restoreTreatmentConfirmModal');
        const idInput = document.getElementById('restoreTreatmentId');
        const summary = document.getElementById('restoreTreatmentSummary');

        if (!modal || !idInput) return;

        const rowEl = document.querySelector('.history-row[data-treatment-id="' + treatmentId + '"]');
        if (!rowEl) {
            showNotification('error', 'Error', 'Treatment record not found.');
            return;
        }

        const patientId = rowEl.getAttribute('data-patient-id') || '';
        const patientName = rowEl.getAttribute('data-patient-name') || '';

        idInput.value = treatmentId;
        if (summary) {
            const who = patientName && patientName !== 'N/A'
                ? `${patientName} (${patientId})`
                : `Patient ${patientId}`;
            summary.textContent = `Are you sure you want to restore this treatment record for ${who}?`;
        }

        modal.style.display = 'flex';
    }

    function refreshTreatmentViewAfterMutation() {
        if (typeof getVisibleTreatmentRows !== 'function' || typeof updateTreatmentPagination !== 'function' || typeof showTreatmentPage !== 'function') return;
        const visibleRows = getVisibleTreatmentRows();
        updateTreatmentPagination(visibleRows);
        showTreatmentPage(visibleRows, treatmentCurrentPage);
    }

    async function updateTreatmentRecord() {
        const editForm = document.getElementById('editTreatmentForm');
        if (!editForm) return;

        const updateBtn = editForm.querySelector('button[type="submit"]');
        if (updateBtn) updateBtn.disabled = true;

        try {
            const payload = new URLSearchParams(new FormData(editForm));

            const res = await fetch('../controllers/updateTreatmentHistory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });

            const data = await res.json();
            if (!data || data.success !== true) {
                showNotification('error', 'Update failed', data?.message || 'Please try again.');
                return;
            }

            closeEditTreatmentModal();
            showNotification('success', 'Updated', data.message || 'Treatment history updated successfully.');

            if (data.record) {
                applyUpdatedTreatmentToDom(data.record);
            }

            refreshTreatmentViewAfterMutation();
        } catch (err) {
            console.error(err);
            showNotification('error', 'Error', 'Something went wrong while updating.');
        } finally {
            if (updateBtn) updateBtn.disabled = false;
        }
    }

    function applyUpdatedTreatmentToDom(record) {
        const treatmentId = record.treatment_id;
        if (!treatmentId) return;

        const tableRow = document.querySelector('tr.history-row[data-treatment-id="' + treatmentId + '"]');
        if (tableRow && tableRow.cells && tableRow.cells.length >= 7) {
            tableRow.cells[0].textContent = record.patient_id || '';
            tableRow.cells[1].textContent = record.patient_name || 'N/A';
            tableRow.cells[2].textContent = record.treatment || '';
            tableRow.cells[3].textContent = record.prescription_given || '';
            tableRow.cells[4].textContent = '₱' + (Number(record.treatment_cost) || 0).toFixed(2);
            tableRow.cells[5].textContent = record.notes || '';
            tableRow.cells[6].textContent = record.created_at_formatted || '';

            // Update filtering/search attributes
            tableRow.setAttribute('data-treatment', (record.treatment || '').toLowerCase());
            tableRow.setAttribute('data-treatment-raw', record.treatment || '');
            tableRow.setAttribute('data-prescription-given', record.prescription_given || '');
            tableRow.setAttribute('data-treatment-cost', record.treatment_cost || '');
            tableRow.setAttribute('data-notes', record.notes || '');
            tableRow.setAttribute('data-created-at', record.created_at || '');
            tableRow.setAttribute('data-patient-id', record.patient_id || '');
            tableRow.setAttribute('data-patient-name', record.patient_name || 'N/A');

            const newSearch = (record.patient_id || '') + ' ' + (record.patient_name || '') + ' ' + (record.treatment || '');
            tableRow.setAttribute('data-search', (newSearch || '').toLowerCase());
        }

        const cardRow = document.querySelector('.patient-card.history-row[data-treatment-id="' + treatmentId + '"]');
        if (cardRow) {
            const cardIdEl = cardRow.querySelector('.patient-card-id');
            const cardNameEl = cardRow.querySelector('.patient-card-name');
            const values = cardRow.querySelectorAll('.patient-card-value');

            if (cardIdEl) cardIdEl.textContent = 'Patient #' + (record.patient_id || '');
            if (cardNameEl) cardNameEl.textContent = record.patient_name || 'N/A';

            if (values && values.length >= 5) {
                values[0].textContent = record.treatment || '';
                values[1].textContent = record.prescription_given || '';
                values[2].textContent = '₱' + (Number(record.treatment_cost) || 0).toFixed(2);
                values[3].textContent = record.notes || '';
                values[4].textContent = record.created_at_formatted || '';
            }

            cardRow.setAttribute('data-treatment', (record.treatment || '').toLowerCase());
            cardRow.setAttribute('data-treatment-raw', record.treatment || '');
            cardRow.setAttribute('data-prescription-given', record.prescription_given || '');
            cardRow.setAttribute('data-treatment-cost', record.treatment_cost || '');
            cardRow.setAttribute('data-notes', record.notes || '');
            cardRow.setAttribute('data-created-at', record.created_at || '');
            cardRow.setAttribute('data-patient-id', record.patient_id || '');
            cardRow.setAttribute('data-patient-name', record.patient_name || 'N/A');
            const newSearch = (record.patient_id || '') + ' ' + (record.patient_name || '') + ' ' + (record.treatment || '');
            cardRow.setAttribute('data-search', (newSearch || '').toLowerCase());
        }
    }

    async function deleteTreatmentRecord() {
        const deleteForm = document.getElementById('deleteTreatmentForm');
        const idInput = document.getElementById('deleteTreatmentId');
        const confirmBtn = document.getElementById('confirmDeleteTreatmentBtn');

        if (!deleteForm || !idInput || !confirmBtn) return;

        const treatmentId = idInput.value;
        if (!treatmentId) {
            showNotification('error', 'Error', 'No treatment record selected.');
            return;
        }

        confirmBtn.disabled = true;
        try {
            const payload = new URLSearchParams(new FormData(deleteForm));

            const res = await fetch('../controllers/deleteTreatmentHistory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });

            const data = await res.json();
            if (!data || data.success !== true) {
                showNotification('error', 'Delete failed', data?.message || 'Please try again.');
                return;
            }

            closeDeleteTreatmentConfirmModal();
            showNotification('success', 'Deleted', data.message || 'Treatment record deleted successfully.');

            const tableRow = document.querySelector('tr.history-row[data-treatment-id="' + treatmentId + '"]');
            const cardRow = document.querySelector('.patient-card.history-row[data-treatment-id="' + treatmentId + '"]');

            if (tableRow) tableRow.remove();
            if (cardRow) cardRow.remove();

            refreshTreatmentViewAfterMutation();
        } catch (err) {
            console.error(err);
            showNotification('error', 'Error', 'Something went wrong while deleting.');
        } finally {
            confirmBtn.disabled = false;
        }
    }

    function applyRestoreStateToDom(treatmentId) {
        const tableRow = document.querySelector('tr.history-row[data-treatment-id="' + treatmentId + '"]');
        const cardRow = document.querySelector('.patient-card.history-row[data-treatment-id="' + treatmentId + '"]');

        if (tableRow) {
            tableRow.setAttribute('data-archived', '0');
            const restoreBtn = tableRow.querySelector('.restore-action-btn');
            if (restoreBtn) restoreBtn.style.display = 'none';
        }
        if (cardRow) {
            cardRow.setAttribute('data-archived', '0');
            const restoreBtn = cardRow.querySelector('.restore-action-btn');
            if (restoreBtn) restoreBtn.style.display = 'none';
        }
    }

    async function restoreTreatmentRecord() {
        const restoreForm = document.getElementById('restoreTreatmentForm');
        const idInput = document.getElementById('restoreTreatmentId');
        const confirmBtn = document.getElementById('confirmRestoreTreatmentBtn');

        if (!restoreForm || !idInput || !confirmBtn) return;

        const treatmentId = idInput.value;
        if (!treatmentId) {
            showCenterAlert('Error', 'No treatment record selected.');
            return;
        }

        confirmBtn.disabled = true;
        try {
            const payload = new URLSearchParams(new FormData(restoreForm));

            const res = await fetch('../controllers/restoreTreatmentHistory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });

            let data;
            try {
                data = await res.json();
            } catch (e) {
                throw new Error('Invalid JSON response received from server.');
            }
            if (!data || data.success !== true) {
                showCenterAlert('Restore failed', (data && data.message) ? data.message : 'Please try again.');
                return;
            }

            closeRestoreTreatmentConfirmModal();
            showCenterAlert('Restored', data.message || 'Treatment record restored successfully.');

            applyRestoreStateToDom(treatmentId);
            refreshTreatmentViewAfterMutation();
        } catch (err) {
            console.error(err);
            showCenterAlert('Error', err && err.message ? err.message : 'Something went wrong while restoring.');
        } finally {
            confirmBtn.disabled = false;
        }
    }

    // Centered alert helpers
    function showCenterAlert(title, message) {
        const modal = document.getElementById('centerAlertModal');
        const titleEl = document.getElementById('centerAlertTitle');
        const msgEl = document.getElementById('centerAlertMessage');
        if (!modal || !titleEl || !msgEl) return;
        titleEl.textContent = title || 'Notice';
        msgEl.textContent = message || '';
        modal.style.display = 'flex';
    }
    function closeCenterAlert() {
        const modal = document.getElementById('centerAlertModal');
        if (modal) modal.style.display = 'none';
    }

    // Bind modal events once DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const editForm = document.getElementById('editTreatmentForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                updateTreatmentRecord();
            });
        }

        const confirmBtn = document.getElementById('confirmDeleteTreatmentBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                deleteTreatmentRecord();
            });
        }

        const confirmRestoreBtn = document.getElementById('confirmRestoreTreatmentBtn');
        if (confirmRestoreBtn) {
            confirmRestoreBtn.addEventListener('click', function() {
                restoreTreatmentRecord();
            });
        }

        const editModal = document.getElementById('editTreatmentModal');
        const deleteModal = document.getElementById('deleteTreatmentConfirmModal');
        const restoreModal = document.getElementById('restoreTreatmentConfirmModal');
        const alertModal = document.getElementById('centerAlertModal');

        window.addEventListener('click', function(event) {
            if (editModal && event.target === editModal) {
                closeEditTreatmentModal();
            }
            if (deleteModal && event.target === deleteModal) {
                closeDeleteTreatmentConfirmModal();
            }
            if (restoreModal && event.target === restoreModal) {
                closeRestoreTreatmentConfirmModal();
            }
            if (alertModal && event.target === alertModal) {
                closeCenterAlert();
            }
        });

        // Escape to close modals
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' || event.key === 'Esc') {
                closeEditTreatmentModal();
                closeDeleteTreatmentConfirmModal();
                closeRestoreTreatmentConfirmModal();
                closeCenterAlert();
            }
        });
    });

</script>

</body>
</html>
