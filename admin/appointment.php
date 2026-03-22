<?php
session_start();
require_once(__DIR__ . "/../database/config.php");


$sql = "SELECT a.appointment_id, p.patient_id, p.first_name, p.last_name, s.service_category, s.sub_service,
               d.first_name as dentist_first, d.last_name as dentist_last,
               a.appointment_date, a.appointment_time, a.status, a.branch, a.request_note, a.service_id, a.team_id
        FROM appointments a
        LEFT JOIN patient_information p ON a.patient_id = p.patient_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
        WHERE COALESCE(a.is_archived, 0) = 0 AND LOWER(COALESCE(a.status, '')) != 'archived'
        ORDER BY a.appointment_date ASC";
$result = mysqli_query($con, $sql);

// Get all services for follow-up modal
$servicesSql = "SELECT service_id, service_category, sub_service FROM services ORDER BY service_category, service_id";
$servicesResult = mysqli_query($con, $servicesSql);
$patientsSql = "SELECT patient_id, first_name, last_name FROM patient_information ORDER BY first_name, last_name";
$patientsResult = mysqli_query($con, $patientsSql);
$dentistsSql = "SELECT team_id, first_name, last_name FROM multidisciplinary_dental_team WHERE status = 'active' ORDER BY first_name, last_name";
$dentistsResult = mysqli_query($con, $dentistsSql);
$defaultDentistId = '';
$defaultDentistName = '';
if ($dentistsResult && mysqli_num_rows($dentistsResult) > 0) {
    $firstDentist = mysqli_fetch_assoc($dentistsResult);
    if ($firstDentist) {
        $defaultDentistId = $firstDentist['team_id'] ?? '';
        $defaultDentistName = 'Dr. ' . trim(($firstDentist['first_name'] ?? '') . ' ' . ($firstDentist['last_name'] ?? ''));
    }
    mysqli_data_seek($dentistsResult, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="appointmentDesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- Centered Alert Modal (Appointments) -->
<style>
    #aptCenterAlertModal.modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.65);
        z-index: 10050;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    #aptCenterAlertModal .modal-panel {
        background: #fff;
        width: min(520px, 92%);
        border-radius: 16px;
        padding: 24px 24px 20px 24px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
        position: relative;
        animation: aptAlertIn 0.2s ease-out;
    }
    #aptCenterAlertModal .modal-heading h3 {
        margin: 0 0 6px 0;
        font-size: 18px;
        color: #111827;
    }
    #aptCenterAlertModal .modal-body {
        color: #4b5563;
        font-size: 14px;
    }
    #aptCenterAlertModal .modal-actions {
        margin-top: 18px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    @keyframes aptAlertIn {
        from { opacity: 0; transform: scale(0.96); }
        to { opacity: 1; transform: scale(1); }
    }
</style>
<div id="aptCenterAlertModal" class="modal-overlay">
    <div class="modal-panel">
        <div class="modal-heading">
            <h3 id="aptCenterAlertTitle">Notice</h3>
        </div>
        <div class="modal-body" id="aptCenterAlertMessage">Message</div>
        <div class="modal-actions">
            <button type="button" class="btn btn-success" onclick="closeAptCenterAlert()">OK</button>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fas fa-calendar-alt"></i> APPOINTMENTS</h2>
        <p style="color: #6b7280; margin-bottom: 30px;">Manage patient appointments, confirm, reschedule, cancel, and mark as completed.</p>
        
        <!-- Action Buttons -->
        <div class="action-buttons-container" style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 30px;">
            <button class="btn btn-primary" onclick="openAddAppointmentModal()">
                <i class="fas fa-plus-circle"></i> Add Appointment
            </button>
            <button class="btn btn-accent" onclick="printAppointments()">
                <i class="fas fa-print"></i> Print Appointments
            </button>
        </div>
        
        <div class="filter-container">
            <div class="filter-group">
                <label for="filter-date-category"><i class="fas fa-calendar-day"></i> Date Category:</label>
                <select id="filter-date-category" onchange="handleDateCategoryChange()">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="custom">Custom Date</option>
                </select>
                <input type="date" id="filter-date" onchange="filterAppointments()" style="display:none; margin-left:10px;">
            </div>
            
            <div class="filter-group">
                <label for="filter-status"><i class="fas fa-filter"></i> Status Category:</label>        
                <select id="filter-status" onchange="filterAppointments()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="reschedule">Reschedule</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no-show">No-Show</option>
                </select> 
            </div>

            <div class="filter-group">
                <label for="filter-search"><i class="fas fa-search"></i> Search:</label>
                <input type="text"
                       id="filter-search"
                       placeholder="Appointment ID, patient name, service"
                       oninput="filterAppointments()">
            </div>
        </div>

        <div class="table-responsive">
            <table id="appointments-table">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Patient Name</th>
                        <th>Service</th>
                        <th>Appointment Date</th>
                        <th>Appointment Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) { 
                            $statusClass = 'status-' . strtolower($row['status']);
                    ?>
                        <tr class="appointment-row" 
                            data-date="<?php echo $row['appointment_date']; ?>" 
                            data-status="<?php echo strtolower($row['status']); ?>"
                            data-appointment-id="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                            data-patient-name="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>"
                            data-service="<?php echo htmlspecialchars($row['sub_service']); ?>"
                            data-appointment-date="<?php echo date('M j, Y', strtotime($row['appointment_date'])); ?>"
                            data-appointment-time="<?php echo htmlspecialchars($row['appointment_time']); ?>"
                            data-dentist="<?php echo htmlspecialchars(trim($row['dentist_first'] . ' ' . $row['dentist_last'])); ?>"
                            data-request-note="<?php echo htmlspecialchars($row['request_note']); ?>"
                            data-branch="<?php echo htmlspecialchars($row['branch']); ?>"
                            data-service-id="<?php echo htmlspecialchars($row['service_id'] ?? ''); ?>"
                            data-team-id="<?php echo htmlspecialchars($row['team_id'] ?? ''); ?>">
                            <td><?php echo htmlspecialchars($row['appointment_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['sub_service']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($row['appointment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['appointment_time']); ?></td>
                            <td><span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button type="button"
                                            class="action-btn btn-gray"
                                            title="View Details"
                                            onclick="openAppointmentInfoModal(this)">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                    <?php if (strtolower($row['status']) === 'pending'): ?>
                                    <button type="button" class="action-btn btn-primary-confirmed" title="Confirm"
                                        data-appointment-id="<?php echo $row['appointment_id']; ?>"
                                        onclick="confirmAppointment(this)">
                                        <i class="fas fa-check"></i>
                                    </button>

                                    <a href="#" 
                                        class="action-btn btn-accent" 
                                        id="reschedBtn<?= $row['appointment_id'] ?>" 
                                        data-id="<?= $row['appointment_id'] ?>"
                                        onclick="return openReschedModalWithID(this, event);"
                                        title="Reschedule">
                                        <i class="fas fa-calendar-alt"></i>
                                    </a>

                                    <?php if (strtolower($row['status']) !== 'completed'): ?>
                                    <button type="button" class="action-btn btn-danger" title="No-Show"
                                        data-appointment-id="<?php echo $row['appointment_id']; ?>"
                                        onclick="markNoShow(this)">
                                        <i class="fa-regular fa-eye-slash"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <a href="#" 
                                        class="action-btn btn-accent <?php echo (in_array(strtolower($row['status']), ['completed', 'cancelled', 'no-show']) ? 'disabled-action' : ''); ?>" 
                                        id="reschedBtn<?= $row['appointment_id'] ?>" 
                                        data-id="<?= $row['appointment_id'] ?>"
                                        onclick="<?php echo (in_array(strtolower($row['status']), ['completed', 'cancelled', 'no-show']) ? 'event.preventDefault(); return false;' : 'return openReschedModalWithID(this, event);'); ?>"
                                        title="<?php echo (in_array(strtolower($row['status']), ['completed', 'cancelled', 'no-show']) ? 'Cannot reschedule this appointment' : 'Reschedule'); ?>">
                                        <i class="fas fa-calendar-alt"></i>
                                    </a>

                                    <?php if (strtolower($row['status']) !== 'completed'): ?>
                                    <button type="button" class="action-btn btn-completed" title="Mark as Completed"
                                        data-patientid="<?php echo htmlspecialchars($row['patient_id']); ?>"
                                        data-appointmentid="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                                        onclick="openCompleteAppointmentModal(this)">
                                        <i class="fa-solid fa-calendar-check"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="action-btn btn-archive" title="Archive"
                                        data-appointment-id="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                                        onclick="archiveAppointment(this)">
                                        <i class="fa-solid fa-box-archive"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if (strtolower($row['status']) === 'completed'): ?>
                                    <button type="button" class="action-btn btn-followup" title="Follow-Up"
                                        data-appointment-id="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                                        data-patient-id="<?php echo htmlspecialchars($row['patient_id']); ?>"
                                        data-patient-name="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>"
                                        onclick="openFollowUpModal(this)">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if (strtolower($row['status']) !== 'completed'): ?>
                                    <button type="button" class="action-btn btn-danger" title="No-Show"
                                        data-appointment-id="<?php echo $row['appointment_id']; ?>"
                                        onclick="markNoShow(this)">
                                        <i class="fa-regular fa-eye-slash"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <i class="fas fa-calendar-times fa-2x"></i>
                                <p>No appointments found</p>
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
            mysqli_data_seek($result, 0);
            if(mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $statusClass = 'status-' . strtolower($row['status']);
            ?>
                <div class="appointment-card appointment-row" 
                     data-date="<?php echo $row['appointment_date']; ?>" 
                     data-status="<?php echo strtolower($row['status']); ?>"
                     data-appointment-id="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                     data-patient-name="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>"
                     data-service="<?php echo htmlspecialchars($row['sub_service']); ?>"
                     data-appointment-date="<?php echo date('M j, Y', strtotime($row['appointment_date'])); ?>"
                     data-appointment-time="<?php echo htmlspecialchars($row['appointment_time']); ?>"
                     data-dentist="<?php echo htmlspecialchars(trim($row['dentist_first'] . ' ' . $row['dentist_last'])); ?>"
                     data-request-note="<?php echo htmlspecialchars($row['request_note']); ?>"
                     data-branch="<?php echo htmlspecialchars($row['branch']); ?>"
                     data-service-id="<?php echo htmlspecialchars($row['service_id'] ?? ''); ?>"
                     data-team-id="<?php echo htmlspecialchars($row['team_id'] ?? ''); ?>">
                    <div class="appointment-card-header">
                        <div>
                            <div class="appointment-card-id">Appointment #<?php echo htmlspecialchars($row['appointment_id']); ?></div>
                            <div class="appointment-card-patient"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                        </div>
                        <span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                    </div>
                    <div class="appointment-card-body">
                        <div class="appointment-card-field">
                            <div class="appointment-card-label">Service</div>
                            <div class="appointment-card-value"><?php echo htmlspecialchars($row['sub_service']); ?></div>
                        </div>
                        <div class="appointment-card-field">
                            <div class="appointment-card-label">Date</div>
                            <div class="appointment-card-value"><?php echo date('M j, Y', strtotime($row['appointment_date'])); ?></div>
                        </div>
                        <div class="appointment-card-field">
                            <div class="appointment-card-label">Time</div>
                            <div class="appointment-card-value"><?php echo htmlspecialchars($row['appointment_time']); ?></div>
                        </div>
                    </div>
                    <div class="appointment-card-actions">
                        <button type="button"
                                class="action-btn btn-info"
                                title="View Details"
                                onclick="openAppointmentInfoModal(this)">
                            <i class="fas fa-info-circle"></i> Info
                        </button>
                        <?php if (strtolower($row['status']) === 'pending'): ?>
                        <button type="button" class="action-btn btn-primary-confirmed" title="Confirm"
                            data-appointment-id="<?php echo $row['appointment_id']; ?>"
                            onclick="confirmAppointment(this)">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                        <a href="#" 
                            class="action-btn btn-accent" 
                            id="reschedBtnMobile<?= $row['appointment_id'] ?>" 
                            data-id="<?= $row['appointment_id'] ?>"
                            onclick="return openReschedModalWithID(this, event);"
                            title="Reschedule">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </a>
                        <button type="button" class="action-btn btn-danger" title="No-Show"
                            data-appointment-id="<?php echo $row['appointment_id']; ?>"
                            onclick="markNoShow(this)">
                            <i class="fa-regular fa-eye-slash"></i> No-Show
                        </button>
                        <?php else: ?>
                        <a href="#" 
                            class="action-btn btn-accent <?php echo (in_array(strtolower($row['status']), ['completed', 'cancelled', 'no-show']) ? 'disabled-action' : ''); ?>" 
                            id="reschedBtnMobile<?= $row['appointment_id'] ?>" 
                            data-id="<?= $row['appointment_id'] ?>"
                            onclick="<?php echo (in_array(strtolower($row['status']), ['completed', 'cancelled', 'no-show']) ? 'event.preventDefault(); return false;' : 'return openReschedModalWithID(this, event);'); ?>"
                            title="<?php echo (in_array(strtolower($row['status']), ['completed', 'cancelled', 'no-show']) ? 'Cannot reschedule this appointment' : 'Reschedule'); ?>">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </a>
                        <?php if (strtolower($row['status']) !== 'completed'): ?>
                        <button type="button" class="action-btn btn-completed" title="Mark as Completed"
                            data-patientid="<?php echo htmlspecialchars($row['patient_id']); ?>"
                            data-appointmentid="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                            onclick="openCompleteAppointmentModal(this)">
                            <i class="fa-solid fa-calendar-check"></i> Complete
                        </button>
                        <?php else: ?>
                        <button type="button" class="action-btn btn-archive" title="Archive"
                            data-appointment-id="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                            onclick="archiveAppointment(this)">
                            <i class="fa-solid fa-box-archive"></i> Archive
                        </button>
                        <?php endif; ?>
                        <?php if (strtolower($row['status']) === 'completed'): ?>
                        <button type="button" class="action-btn btn-followup" title="Follow-Up"
                            data-appointment-id="<?php echo htmlspecialchars($row['appointment_id']); ?>"
                            data-patient-id="<?php echo htmlspecialchars($row['patient_id']); ?>"
                            data-patient-name="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>"
                            onclick="openFollowUpModal(this)">
                            <i class="fa-solid fa-arrow-right"></i> Follow-Up
                        </button>
                        <?php endif; ?>
                        <?php if (strtolower($row['status']) !== 'completed'): ?>
                        <button type="button" class="action-btn btn-danger" title="No-Show"
                            data-appointment-id="<?php echo $row['appointment_id']; ?>"
                            onclick="markNoShow(this)">
                            <i class="fa-regular fa-eye-slash"></i> No-Show
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
                }
            } else {
            ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-calendar-times fa-2x"></i>
                    <p>No appointments found</p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Appointment Info Modal -->
        <div id="appointment-info-modal" class="modal treatment-modal" style="display: none;">
            <div class="modal-content treatment-modal-content">
                <div class="modal-card">
                    <div class="modal-header">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            <span>Appointment Information</span>
                        </h3>
                        <span class="close" onclick="closeAppointmentInfoModal()" aria-label="Close appointment information modal">&times;</span>
                    </div>

                    <div class="modal-body treatment-body">
                        <div class="appointment-info-grid">
                            <div class="treatment-group form-group">
                                <label>Appointment ID</label>
                                <input type="text" id="info_appointment_id" readonly>
                            </div>
                            <div class="treatment-group form-group">
                                <label>Patient Name</label>
                                <input type="text" id="info_patient_name" readonly>
                            </div>
                            <div class="treatment-group form-group">
                                <label>Service / Sub-Service</label>
                                <input type="text" id="info_service" readonly>
                            </div>
                            <div class="treatment-group form-group">
                                <label>Dentist</label>
                                <input type="text" id="info_dentist" readonly>
                            </div>
                            <div class="treatment-group form-group">
                                <label>Branch</label>
                                <input type="text" id="info_branch" readonly>
                            </div>
                            <div class="treatment-group form-group">
                                <label>Date</label>
                                <input type="text" id="info_date" readonly>
                            </div>
                            <div class="treatment-group form-group">
                                <label>Time</label>
                                <input type="text" id="info_time" readonly>
                            </div>
                            <div class="treatment-group form-group">
                                <label>Status</label>
                                <input type="text" id="info_status" readonly>
                            </div>
                            <div class="treatment-group form-group appointment-info-notes">
                                <label>Additional Service Request</label>
                                <textarea id="info_request_note" rows="4" readonly placeholder="No additional request provided."></textarea>
                            </div>
                        </div>
                    </div>

                    
                </div>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-container" id="pagination-container">
            <div class="pagination-info" id="pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="prev-page-btn" onclick="changePage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-numbers" id="pagination-numbers"></div>
                <button class="pagination-btn" id="next-page-btn" onclick="changePage(1)" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Appointment Modal -->
<div id="addAppointmentModal" class="modal treatment-modal" style="display: none;">
    <div class="modal-content treatment-modal-content">
        <div class="modal-card">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Appointment</span>
                </h3>
                <span class="close" onclick="closeAddAppointmentModal()" aria-label="Close add appointment modal">&times;</span>
            </div>

            <div class="modal-body treatment-body">
                <form id="addAppointmentForm" onsubmit="handleAddAppointmentSubmit(event)">
                    <div class="add-appointment-layout">
                        <div class="add-appointment-column">
                            <div class="add-appointment-panel">
                                <h4 class="add-appointment-panel-title">Appointment Information</h4>
                                <div class="appointment-info-grid">
                                    <div class="treatment-group form-group">
                                        <label>Patient ID <span class="required">*</span></label>
                                        <select id="add_patient_id" name="patient_id" required onchange="handleAddPatientSelection()">
                                            <option value="">-- Select Patient --</option>
                                            <?php if ($patientsResult && mysqli_num_rows($patientsResult) > 0): ?>
                                                <?php while ($patient = mysqli_fetch_assoc($patientsResult)): ?>
                                                    <?php $fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?>
                                                    <option value="<?php echo htmlspecialchars($patient['patient_id']); ?>" data-patient-name="<?php echo htmlspecialchars($fullName); ?>">
                                                        <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $fullName); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="treatment-group form-group">
                                        <label>Patient Details</label>
                                        <input type="text" id="add_patient_details" value="" placeholder="Patient details will appear here" readonly disabled>
                                        <small class="form-help">Auto-filled after selecting Patient ID</small>
                                    </div>

                                    <div class="treatment-group form-group">
                                        <label>Service <span class="required">*</span></label>
                                        <select id="add_service_id" name="service_id" required disabled>
                                            <option value="">-- Select Service --</option>
                                            <?php
                                            mysqli_data_seek($servicesResult, 0);
                                            if(mysqli_num_rows($servicesResult) > 0) {
                                                while ($service = mysqli_fetch_assoc($servicesResult)) {
                                                    $serviceDisplay = !empty($service['sub_service']) ? $service['sub_service'] : $service['service_category'];
                                            ?>
                                                <option value="<?php echo htmlspecialchars($service['service_id']); ?>">
                                                    <?php echo htmlspecialchars($service['service_category'] . ' - ' . $serviceDisplay); ?>
                                                </option>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="treatment-group form-group">
                                        <label>Appointment/Consultation Fee (₱) <span class="required">*</span></label>
                                        <input
                                            id="add_appointment_fee"
                                            name="appointment_fee"
                                            type="number"
                                            inputmode="numeric"
                                            min="0"
                                            max="9999"
                                            step="1"
                                            placeholder="Enter amount (min ₱500)"
                                            required
                                            disabled
                                            oninput="
                                                this.value = this.value.replace(/[^0-9]/g,'').slice(0,4);
                                                if (this.value !== '' && parseInt(this.value,10) < 0) this.value = '';
                                            "
                                        >
                                        <small class="form-help">Enter appointment fee (set to 0 if none)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="add-appointment-column">
                            <div class="add-appointment-panel">
                                <h4 class="add-appointment-panel-title">Schedule Details</h4>
                                <div class="appointment-info-grid">
                                    <div class="treatment-group form-group">
                                        <label>Dentist</label>
                                        <input type="text" value="<?php echo htmlspecialchars($defaultDentistName ?: 'Assigned by system'); ?>" readonly disabled>
                                        <input type="hidden" id="add_team_id" name="team_id" value="<?php echo htmlspecialchars($defaultDentistId); ?>" required disabled>
                                    </div>

                                    <div class="treatment-group form-group">
                                        <label>Branch <span class="required">*</span></label>
                                        <select id="add_branch" name="branch" required disabled>
                                            <option value="">-- Select Branch --</option>
                                            <option value="Comembo Branch">Comembo Branch</option>
                                            <option value="Taytay Rizal Branch">Taytay Rizal Branch</option>
                                        </select>
                                    </div>

                                    <div class="treatment-group form-group">
                                        <label>Preferred Date <span class="required">*</span></label>
                                        <input type="date" id="add_appointment_date" name="appointment_date" required min="<?= date('Y-m-d') ?>" disabled onchange="loadAddAppointmentBookedSlots()">
                                        <small class="form-help">Please select a date from today onwards</small>
                                    </div>

                                    <div class="treatment-group form-group">
                                        <label>Preferred Time Slot <span class="required">*</span></label>
                                        <select id="add_time_slot" name="time_slot" required disabled>
                                            <option value="">-- Select Time Slot --</option>
                                            <option value="firstBatch" data-slot="8:00AM-9:00AM" data-label="Morning (8:00AM-9:00AM)">Morning (8:00AM-9:00AM)</option>
                                            <option value="secondBatch" data-slot="9:00AM-10:00AM" data-label="Morning (9:00AM-10:00AM)">Morning (9:00AM-10:00AM)</option>
                                            <option value="thirdBatch" data-slot="10:00AM-11:00AM" data-label="Morning (10:00AM-11:00AM)">Morning (10:00AM-11:00AM)</option>
                                            <option value="fourthBatch" data-slot="11:00AM-12:00PM" data-label="Afternoon (11:00AM-12:00PM)">Afternoon (11:00AM-12:00PM)</option>
                                            <option value="fifthBatch" data-slot="1:00PM-2:00PM" data-label="Afternoon (1:00PM-2:00PM)">Afternoon (1:00PM-2:00PM)</option>
                                            <option value="sixthBatch" data-slot="2:00PM-3:00PM" data-label="Afternoon (2:00PM-3:00PM)">Afternoon (2:00PM-3:00PM)</option>
                                            <option value="sevenBatch" data-slot="3:00PM-4:00PM" data-label="Afternoon (3:00PM-4:00PM)">Afternoon (3:00PM-4:00PM)</option>
                                            <option value="eightBatch" data-slot="4:00PM-5:00PM" data-label="Afternoon (4:00PM-5:00PM)">Afternoon (4:00PM-5:00PM)</option>
                                            <option value="nineBatch" data-slot="5:00PM-6:00PM" data-label="Afternoon (5:00PM-6:00PM)">Afternoon (5:00PM-6:00PM)</option>
                                            <option value="tenBatch" data-slot="6:00PM-7:00PM" data-label="Evening (6:00PM-7:00PM)">Evening (6:00PM-7:00PM)</option>
                                            <option value="lastBatch" data-slot="7:00PM-8:00PM" data-label="Evening (7:00PM-8:00PM)">Evening (7:00PM-8:00PM)</option>
                                        </select>
                                        <small class="form-help" id="addTimeSlotHelp">Select patient and date first</small>
                                        <div id="loadingAddSlots" class="loading-indicator" style="display: none;">
                                            <i class="fas fa-spinner fa-spin"></i> Loading available slots...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="treatment-footer modal-footer">
                <div class="modal-actions">
                    <button type="button" onclick="closeAddAppointmentModal()" class="btn modal-close-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" form="addAppointmentForm" class="btn btn-success" id="addAppointmentSubmitBtn">
                        <i class="fas fa-check"></i> Add Appointment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="reschedModal" class="modal treatment-modal" style="display: none;">
    <div class="modal-content treatment-modal-content">
        <div class="modal-card">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-calendar-alt"></i>
                    <span>Reschedule Appointment</span>
                </h3>
                <span class="close" onclick="closeReschedModal()" aria-label="Close reschedule modal">&times;</span>
            </div>

            <div class="modal-body treatment-body">
                <div class="reschedule-content-grid">
                    <!-- Left Side: Current Appointment Info -->
                    <div class="reschedule-current-info">
                        <div class="current-appointment-section">
                            <h4>Current Appointment</h4>
                            <div class="appointment-info-grid">
                                <div class="treatment-group form-group">
                                    <label>Patient Name</label>
                                    <input type="text" id="currentPatientName" readonly>
                                </div>
                                <div class="treatment-group form-group">
                                    <label>Service</label>
                                    <input type="text" id="currentService" readonly>
                                </div>
                                <div class="treatment-group form-group">
                                    <label>Current Date</label>
                                    <input type="text" id="currentDate" readonly>
                                </div>
                                <div class="treatment-group form-group">
                                    <label>Current Time</label>
                                    <input type="text" id="currentTime" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Reschedule Form -->
                    <div class="reschedule-form-section">
                        <form id="rescheduleForm" onsubmit="handleRescheduleSubmit(event)">
                            <input type="hidden" id="modalAppointmentID" name="appointment_id">
                            <input type="hidden" id="modalHasRequestNote" value="0">
                            <input type="hidden" name="reschedule_source" value="admin">
                            
                            <div class="appointment-info-grid">
                                <div class="treatment-group form-group">
                                    <label>New Date <span class="required">*</span></label>
                                    <input type="date" 
                                           id="new_date_resched" 
                                           name="new_date_resched" 
                                           required 
                                           min="<?= date('Y-m-d') ?>" 
                                           onchange="loadBookedSlots()">
                                    <small class="form-help">Please select a date from today onwards</small>
                                </div>

                                <div class="treatment-group form-group">
                                    <label>New Time Slot <span class="required">*</span></label>
                                    <select id="new_time_resched" name="new_time_slot" required disabled>
                                        <option value="">-- Select Time Slot --</option>
                                        <option value="firstBatch" data-slot="8:00AM-9:00AM">Morning (8:00AM-9:00AM)</option>
                                        <option value="secondBatch" data-slot="9:00AM-10:00AM">Morning (9:00AM-10:00AM)</option>
                                        <option value="thirdBatch" data-slot="10:00AM-11:00AM">Morning (10:00AM-11:00AM)</option>
                                        <option value="fourthBatch" data-slot="11:00AM-12:00PM">Afternoon (11:00AM-12:00PM)</option>
                                        <option value="fifthBatch" data-slot="1:00PM-2:00PM">Afternoon (1:00PM-2:00PM)</option>
                                        <option value="sixthBatch" data-slot="2:00PM-3:00PM">Afternoon (2:00PM-3:00PM)</option>
                                        <option value="sevenBatch" data-slot="3:00PM-4:00PM">Afternoon (3:00PM-4:00PM)</option>
                                        <option value="eightBatch" data-slot="4:00PM-5:00PM">Afternoon (4:00PM-5:00PM)</option>
                                        <option value="nineBatch" data-slot="5:00PM-6:00PM">Afternoon (5:00PM-6:00PM)</option>
                                        <option value="tenBatch" data-slot="6:00PM-7:00PM">Evening (6:00PM-7:00PM)</option>
                                        <option value="lastBatch" data-slot="7:00PM-8:00PM">Evening (7:00PM-8:00PM)</option>
                                    </select>
                                    <small class="form-help" id="timeSlotHelp">Please select a date first</small>
                                    <small class="form-help" id="twoSlotHint" style="display:none; color:#4b5563;">This appointment has a request note and requires 2 consecutive time slots.</small>
                                    <div id="loadingSlots" class="loading-indicator" style="display: none;">
                                        <i class="fas fa-spinner fa-spin"></i> Loading available slots...
                                    </div>
                                </div>

                                <div class="treatment-group form-group appointment-info-notes">
                                    <label>Reason for Rescheduling <span class="required">*</span></label>
                                    <textarea id="reschedule_reason" 
                                              name="reschedule_reason" 
                                              rows="4" 
                                              placeholder="Please provide a reason for rescheduling this appointment (e.g., Dentist availability, Schedule conflict, etc.)"
                                              required></textarea>
                                    <small class="form-help">This reason will be included in the patient notification email</small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="treatment-footer modal-footer">
                <div class="modal-actions">
                    <button type="button" onclick="closeReschedModal()" class="btn modal-close-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" form="rescheduleForm" class="btn btn-success" id="rescheduleSubmitBtn">
                        <i class="fas fa-check"></i> Confirm Reschedule
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Complete Appointment Modal -->
<style>
    /* Keep Complete Appointment modal layout responsive (match Reschedule behavior) */
    #complete-appointment-modal .complete-appointment-two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }
    #complete-appointment-modal .complete-appointment-column-card {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
    }
    @media (max-width: 768px) {
        #complete-appointment-modal .complete-appointment-two-col {
            grid-template-columns: 1fr;
        }
    }

    /* Archive confirmation popup with smooth entry animation */
    #archiveConfirmModal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.55);
        z-index: 1200;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    #archiveConfirmModal.show {
        display: flex;
    }
    #archiveConfirmModal .archive-confirm-card {
        width: 100%;
        max-width: 460px;
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
        padding: 22px;
        animation: archiveConfirmPop 0.25s ease-out;
    }
    #archiveConfirmModal .archive-confirm-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }
    #archiveConfirmModal .archive-confirm-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #b45309;
        background: #fef3c7;
        font-size: 18px;
    }
    #archiveConfirmModal .archive-confirm-title {
        margin: 0;
        color: #111827;
        font-size: 18px;
        font-weight: 600;
    }
    #archiveConfirmModal .archive-confirm-message {
        margin: 8px 0 18px;
        color: #4b5563;
        line-height: 1.45;
    }
    #archiveConfirmModal .archive-confirm-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    @keyframes archiveConfirmPop {
        from {
            opacity: 0;
            transform: translateY(8px) scale(0.97);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>
<div id="archiveConfirmModal" role="dialog" aria-modal="true" aria-labelledby="archiveConfirmTitle">
    <div class="archive-confirm-card">
        <div class="archive-confirm-header">
            <span class="archive-confirm-icon"><i class="fa-solid fa-box-archive"></i></span>
            <h3 class="archive-confirm-title" id="archiveConfirmTitle">Archive Appointment</h3>
        </div>
        <p class="archive-confirm-message" id="archiveConfirmMessage"></p>
        <div class="archive-confirm-actions">
            <button type="button" class="btn modal-close-btn" id="archiveConfirmCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-completed" id="archiveConfirmProceedBtn">
                <i class="fas fa-check"></i> Archive
            </button>
        </div>
    </div>
</div>
<div id="complete-appointment-modal" class="modal complete-appointment-modal" style="display: none;">
    <div class="modal-content complete-appointment-modal-content">
        <div class="complete-appointment-header" style="background: #ffffff; border-bottom: 1px solid #e5e7eb; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); border-radius: 16px 16px 0 0;">
            <h3 style="margin: 0; font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 10px; color: #111827; background: none; padding: 0;">
                <i class="fa-solid fa-check-to-slot" style="color: var(--primary-color);"></i>
                <span>Complete Appointment</span>
            </h3>
            <span class="close"
                  onclick="closeCompleteAppointmentModal()"
                  aria-label="Close complete appointment modal"
                  style="position: static; background: rgba(0,0,0,0.04); color: #9ca3af; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 24px; cursor: pointer; transition: all 0.2s ease; line-height: 1;">&times;</span>
        </div>

        <div class="complete-appointment-body" style="padding: 20px 24px 18px;">
            <form id="treatmentForm" onsubmit="handleTreatmentSubmit(event)">
                <input type="hidden" id="treatment_patient_id" name="patient_id">
                <input type="hidden" id="treatment_appointment_id" name="appointment_id">

                <div class="complete-appointment-two-col">
                    <!-- LEFT COLUMN -->
                    <div class="complete-appointment-column-card">
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <div class="complete-appointment-form-group" style="margin-bottom: 0;">
                                <label for="patient_id">
                                    <i class="fas fa-id-card"></i> Patient ID:
                                </label>
                                <input type="text" id="patient_id" value="" readonly>
                                <small>Patient ID is automatically filled</small>
                            </div>

                            <div class="complete-appointment-form-group" style="margin-bottom: 0;">
                                <label for="prescription_given">
                                    <i class="fas fa-pills"></i> Prescription:
                                </label>
                                <textarea id="prescription_given" name="prescription_given" rows="3" placeholder="Enter prescribed medications and instructions" required></textarea>
                                <small>List all prescribed medications and dosage instructions</small>
                            </div>

                            <div class="complete-appointment-form-group" style="margin-bottom: 0;">
                                <label for="treatment_notes">
                                    <i class="fas fa-notes-medical"></i> Treatment Notes:
                                </label>
                                <textarea id="treatment_notes" name="treatment_notes" rows="4" placeholder="Enter detailed notes about the treatment, patient condition, and recommendations" required></textarea>
                                <small>Add any additional notes or observations about the treatment</small>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="complete-appointment-column-card">
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <div class="complete-appointment-form-group" style="margin-bottom: 0;">
                                <label for="treatment_type">
                                    <i class="fas fa-stethoscope"></i> Treatment:
                                </label>
                                <input type="text" id="treatment_type" name="treatment" placeholder="Enter treatment type (e.g., Cleaning, Extraction, Filling)" required>
                                <small>Specify the treatment provided to the patient</small>
                            </div>

                            <div class="complete-appointment-form-group" style="margin-bottom: 0;">
                                <label for="treatment_cost">
                                    <i class="fas fa-peso-sign"></i> Treatment Cost (₱):
                                </label>
                                <input type="number" id="treatment_cost" name="treatment_cost" step="0.01" min="0" placeholder="0.00" required>
                                <small>Enter the total cost of the treatment</small>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Buttons Footer - Always Visible -->
        <div class="complete-appointment-footer" style="padding: 20px 25px;">
            <div class="complete-appointment-actions" style="justify-content: flex-end; gap: 12px; flex-wrap: wrap;">
                <button type="button" onclick="closeCompleteAppointmentModal()" class="btn modal-close-btn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" form="treatmentForm" class="btn btn-completed" id="completeAppointmentSubmitBtn">
                    <i class="fas fa-check"></i> Complete and Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Follow-Up Modal -->
<div id="followUpModal" class="modal treatment-modal" style="display: none;">
    <div class="modal-content treatment-modal-content">
        <div class="modal-card">
            <div class="modal-header">
                <h3>
                    <i class="fa-solid fa-arrow-right"></i>
                    <span>Schedule Follow-Up Appointment</span>
                </h3>
                <span class="close" onclick="closeFollowUpModal()" aria-label="Close follow-up modal">&times;</span>
            </div>

            <div class="modal-body treatment-body">
                <form id="followUpForm" onsubmit="handleFollowUpSubmit(event)">
                    <input type="hidden" id="followup_patient_id" name="patient_id">
                    <input type="hidden" id="followup_appointment_id" name="original_appointment_id">
                    <input type="hidden" id="followup_team_id" name="team_id">
                    <input type="hidden" id="followup_branch" name="branch">
                    
                    <div class="appointment-info-grid">
                        <div class="treatment-group form-group">
                            <label>Patient Name <span class="required">*</span></label>
                            <input type="text" id="followup_patient_name" name="patient_name" readonly required>
                        </div>

                        <div class="treatment-group form-group">
                            <label>Service <span class="required">*</span></label>
                            <select name="service_id" id="followup_service_id" required>
                                <option value="" disabled selected>Select a service</option>
                                <?php 
                                mysqli_data_seek($servicesResult, 0);
                                if(mysqli_num_rows($servicesResult) > 0) {
                                    while ($service = mysqli_fetch_assoc($servicesResult)) { 
                                        $serviceDisplay = !empty($service['sub_service']) 
                                            ? $service['sub_service'] 
                                            : $service['service_category'];
                                ?>
                                    <option value="<?php echo htmlspecialchars($service['service_id']); ?>">
                                        <?php echo htmlspecialchars($service['service_category'] . ' - ' . $serviceDisplay); ?>
                                    </option>
                                <?php 
                                    }
                                } 
                                ?>
                            </select>
                            <small class="form-help">Select the service for this follow-up appointment</small>
                        </div>

                        <div class="treatment-group form-group">
                            <label>Follow-Up Date <span class="required">*</span></label>
                            <input type="date" id="followup_date" name="appointment_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" onchange="loadFollowUpBookedSlots()">
                            <small class="form-help">Please select a date from tomorrow onwards</small>
                        </div>

                        <div class="treatment-group form-group">
                            <label>Follow-Up Time <span class="required">*</span></label>
                            <select id="followup_time" name="time_slot" required>
                                <option value="">-- Select Time Slot --</option>
                                <option value="firstBatch" data-slot="8:00AM-9:00AM">Morning (8:00AM-9:00AM)</option>
                                <option value="secondBatch" data-slot="9:00AM-10:00AM">Morning (9:00AM-10:00AM)</option>
                                <option value="thirdBatch" data-slot="10:00AM-11:00AM">Morning (10:00AM-11:00AM)</option>
                                <option value="fourthBatch" data-slot="11:00AM-12:00PM">Afternoon (11:00AM-12:00PM)</option>
                                <option value="fifthBatch" data-slot="1:00PM-2:00PM">Afternoon (1:00PM-2:00PM)</option>
                                <option value="sixthBatch" data-slot="2:00PM-3:00PM">Afternoon (2:00PM-3:00PM)</option>
                                <option value="sevenBatch" data-slot="3:00PM-4:00PM">Afternoon (3:00PM-4:00PM)</option>
                                <option value="eightBatch" data-slot="4:00PM-5:00PM">Afternoon (4:00PM-5:00PM)</option>
                                <option value="nineBatch" data-slot="5:00PM-6:00PM">Afternoon (5:00PM-6:00PM)</option>
                                <option value="tenBatch" data-slot="6:00PM-7:00PM">Evening (6:00PM-7:00PM)</option>
                                <option value="lastBatch" data-slot="7:00PM-8:00PM">Evening (7:00PM-8:00PM)</option>
                            </select>
                            <small class="form-help" id="followupTimeSlotHelp">Booked slots will be disabled automatically</small>
                            <div id="loadingFollowUpSlots" class="loading-indicator" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Loading available slots...
                            </div>
                        </div>

                        <div class="treatment-group form-group appointment-info-notes">
                            <label>Reason for Follow-Up <span class="required">*</span></label>
                            <textarea id="followup_reason" 
                                      name="followup_reason" 
                                      rows="4" 
                                      placeholder="Please provide a reason for this follow-up appointment (e.g., Post-treatment check, Additional procedure needed, etc.)"
                                      required></textarea>
                            <small class="form-help">This reason will be included in the patient notification email</small>
                        </div>
                    </div>
                </form>
            </div>

            <div class="treatment-footer modal-footer">
                <div class="modal-actions">
                    <button type="button" onclick="closeFollowUpModal()" class="btn modal-close-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" form="followUpForm" class="btn btn-success" id="followUpSubmitBtn">
                        <i class="fas fa-check"></i> Save Follow-Up
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Centered alert helpers (Appointments)
    function showAptCenterAlert(title, message) {
        const modal = document.getElementById('aptCenterAlertModal');
        const titleEl = document.getElementById('aptCenterAlertTitle');
        const msgEl = document.getElementById('aptCenterAlertMessage');
        if (!modal || !titleEl || !msgEl) return;
        titleEl.textContent = title || 'Notice';
        msgEl.textContent = message || '';
        modal.style.display = 'flex';
    }
    function closeAptCenterAlert() {
        const modal = document.getElementById('aptCenterAlertModal');
        if (modal) modal.style.display = 'none';
    }
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

    // Refresh only appointment list containers and preserve current filters/pagination state.
    let isAppointmentRefreshInProgress = false;
    function refreshAppointmentList() {
        if (isAppointmentRefreshInProgress) {
            return Promise.resolve(false);
        }

        const tableBody = document.querySelector('#appointments-table tbody');
        const mobileCardView = document.querySelector('.mobile-card-view');
        if (!tableBody || !mobileCardView) {
            return Promise.reject(new Error('Appointment containers not found'));
        }

        const state = {
            dateCategory: document.getElementById('filter-date-category')?.value || '',
            date: document.getElementById('filter-date')?.value || '',
            status: document.getElementById('filter-status')?.value || '',
            page: currentPage
        };

        isAppointmentRefreshInProgress = true;
        const url = `${window.location.pathname}${window.location.search}${window.location.search ? '&' : '?'}_ts=${Date.now()}`;

        return fetch(url, { cache: 'no-store' })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTableBody = doc.querySelector('#appointments-table tbody');
                const newMobileCardView = doc.querySelector('.mobile-card-view');

                if (!newTableBody || !newMobileCardView) {
                    throw new Error('Updated appointment content not found');
                }

                tableBody.innerHTML = newTableBody.innerHTML;
                mobileCardView.innerHTML = newMobileCardView.innerHTML;

                const dateCategoryEl = document.getElementById('filter-date-category');
                const dateEl = document.getElementById('filter-date');
                const statusEl = document.getElementById('filter-status');
                if (dateCategoryEl) dateCategoryEl.value = state.dateCategory;
                if (dateEl) dateEl.value = state.date;
                if (statusEl) statusEl.value = state.status;

                handleDateCategoryChange();
                filterAppointments();

                const visibleRows = document.querySelectorAll(".appointment-row[data-visible='true']");
                const totalPages = Math.max(1, Math.ceil(visibleRows.length / rowsPerPage));
                currentPage = Math.min(Math.max(1, state.page), totalPages);
                goToPage(currentPage);
                return true;
            })
            .finally(() => {
                isAppointmentRefreshInProgress = false;
            });
    }
    
    // Navigate back to admin
    function navigateBack(event) {
        if (event) event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.style.opacity = '0';
            mainContent.style.transition = 'opacity 0.3s ease-in-out';
        }
        setTimeout(() => {
            window.location.href = '../views/admin.php';
        }, 300);
        return false;
    }
    
    // Open Appointment Info Modal
    function openAppointmentInfoModal(trigger) {
        const row = trigger.closest('.appointment-row');
        if (!row) return;

        const appointmentId = row.getAttribute('data-appointment-id') || '';
        const patientName = row.getAttribute('data-patient-name') || '';
        const service = row.getAttribute('data-service') || '';
        const dentist = row.getAttribute('data-dentist') || '';
        const branch = row.getAttribute('data-branch') || '';
        const date = row.getAttribute('data-appointment-date') || row.getAttribute('data-date') || '';
        const time = row.getAttribute('data-appointment-time') || '';
        const status = row.getAttribute('data-status') || '';
        const requestNote = row.getAttribute('data-request-note') || '';

        document.getElementById('info_appointment_id').value = appointmentId;
        document.getElementById('info_patient_name').value = patientName;
        document.getElementById('info_service').value = service;
        document.getElementById('info_dentist').value = dentist || 'Dr. Michelle Landero';
        document.getElementById('info_branch').value = branch;
        document.getElementById('info_date').value = date;
        document.getElementById('info_time').value = time;
        document.getElementById('info_status').value = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';

        const noteEl = document.getElementById('info_request_note');
        if (requestNote && requestNote.trim() !== '') {
            noteEl.value = requestNote;
        } else {
            noteEl.value = 'No additional request provided.';
        }

        const modal = document.getElementById('appointment-info-modal');
        if (modal) modal.style.display = 'block';
    }

    function closeAppointmentInfoModal() {
        const modal = document.getElementById('appointment-info-modal');
        if (modal) modal.style.display = 'none';
    }
    
    // Pagination Variables
    let currentPage = 1;
    const rowsPerPage = 5;
    
    // Handle Date Category Change
    function handleDateCategoryChange() {
        const dateCategory = document.getElementById("filter-date-category").value;
        const dateInput = document.getElementById("filter-date");
        
        if (dateCategory === "custom") {
            dateInput.style.display = "inline-block";
            dateInput.value = "";
        } else {
            dateInput.style.display = "none";
            dateInput.value = "";
            filterAppointments();
        }
    }
    
    // Filter appointments and update pagination
    function filterAppointments() {
        const dateCategory = document.getElementById("filter-date-category").value;
        const selectedDate = document.getElementById("filter-date").value;
        const selectedStatus = document.getElementById("filter-status").value.toLowerCase();
        const searchText = (document.getElementById("filter-search")?.value || "").toLowerCase().trim();
        // Get all appointment rows (includes both table rows TR and mobile cards div since cards have both classes)
        const allRows = document.querySelectorAll(".appointment-row");
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayStr = today.toISOString().split('T')[0];
        
        let weekStart = null, weekEnd = null;
        let monthStart = null, monthEnd = null;
        
        if (dateCategory === "week") {
            const dayOfWeek = today.getDay();
            const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
            weekStart = new Date(today);
            weekStart.setDate(today.getDate() - daysToMonday);
            weekStart.setHours(0, 0, 0, 0);
            weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6); // Monday to Sunday (7 days)
            weekEnd.setHours(23, 59, 59, 999);
        } else if (dateCategory === "month") {
            monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            monthStart.setHours(0, 0, 0, 0);
            monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            monthEnd.setHours(23, 59, 59, 999);
        }
        
        let visibleRows = [];
        
        // Helper function to check if date matches filter
        function matchesDateFilter(rowDate, dateCategory, selectedDate, todayStr, weekStart, weekEnd, monthStart, monthEnd) {
            if (dateCategory === "custom" && selectedDate) {
                return rowDate === selectedDate;
            } else if (dateCategory === "today") {
                return rowDate === todayStr;
            } else if (dateCategory === "week") {
                const rowDateObj = new Date(rowDate);
                rowDateObj.setHours(0, 0, 0, 0);
                return rowDateObj >= weekStart && rowDateObj <= weekEnd;
            } else if (dateCategory === "month") {
                const rowDateObj = new Date(rowDate);
                rowDateObj.setHours(0, 0, 0, 0);
                return rowDateObj >= monthStart && rowDateObj <= monthEnd;
            }
            return true; // "All Dates" or empty category
        }
        
        // Filter all appointment rows (both table rows and mobile cards)
        allRows.forEach(row => {
            const rowDate = row.getAttribute("data-date");
            const rowStatus = row.getAttribute("data-status") ? row.getAttribute("data-status").toLowerCase() : "";
            const rowSearchText = (
                (row.getAttribute("data-appointment-id") || '') + ' ' +
                (row.getAttribute("data-patient-name") || '') + ' ' +
                (row.getAttribute("data-service") || '')
            ).toLowerCase();
            
            const matchesDate = matchesDateFilter(rowDate, dateCategory, selectedDate, todayStr, weekStart, weekEnd, monthStart, monthEnd);
            const matchesStatus = selectedStatus === "" || rowStatus === selectedStatus;
            const matchesSearch = searchText === "" || rowSearchText.includes(searchText);
            
            if (matchesDate && matchesStatus && matchesSearch) {
                row.setAttribute("data-visible", "true");
                visibleRows.push(row);
            } else {
                row.setAttribute("data-visible", "false");
            }
        });

        // Reset to first page when filtering
        currentPage = 1;
        
        // Update pagination with filtered results
        updatePagination(visibleRows);
        showPage(visibleRows, currentPage);
    }
    
    // Update pagination controls
    function updatePagination(visibleRows) {
        // Filter rows based on current view (mobile cards on mobile, table rows on desktop)
        const isMobileView = window.innerWidth <= 768;
        const filteredRows = visibleRows ? visibleRows.filter(row => {
            if (isMobileView) {
                return row.classList.contains('appointment-card');
            } else {
                return row.tagName === 'TR';
            }
        }) : [];
        
        const totalRows = filteredRows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        const paginationContainer = document.getElementById("pagination-container");
        const paginationInfo = document.getElementById("pagination-info");
        const paginationNumbers = document.getElementById("pagination-numbers");
        const prevBtn = document.getElementById("prev-page-btn");
        const nextBtn = document.getElementById("next-page-btn");

        if (totalRows === 0) {
            paginationContainer.style.display = "none";
            return;
        }

        paginationContainer.style.display = "flex";

        const startRow = (currentPage - 1) * rowsPerPage + 1;
        const endRow = Math.min(currentPage * rowsPerPage, totalRows);
        paginationInfo.textContent = `Showing ${startRow}-${endRow} of ${totalRows} appointments`;

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage >= totalPages;

        paginationNumbers.innerHTML = "";
        
        // Responsive: Show fewer page numbers on smaller screens
        const isMobile = window.innerWidth <= 768;
        const isSmallMobile = window.innerWidth <= 480;
        const maxPagesToShow = isSmallMobile ? 3 : isMobile ? 4 : 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        if (startPage > 1) {
            createPageNumber(1, paginationNumbers);
            if (startPage > 2) {
                createEllipsis(paginationNumbers);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            createPageNumber(i, paginationNumbers);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                createEllipsis(paginationNumbers);
            }
            createPageNumber(totalPages, paginationNumbers);
        }
    }

    function createPageNumber(pageNum, container) {
        const pageBtn = document.createElement("button");
        pageBtn.className = "pagination-number" + (pageNum === currentPage ? " active" : "");
        pageBtn.textContent = pageNum;
        pageBtn.onclick = () => goToPage(pageNum);
        container.appendChild(pageBtn);
    }

    function createEllipsis(container) {
        const ellipsis = document.createElement("span");
        ellipsis.className = "pagination-number ellipsis";
        ellipsis.textContent = "...";
        container.appendChild(ellipsis);
    }

    function showPage(visibleRows, page) {
        if (!visibleRows || visibleRows.length === 0) {
            // Hide all rows if no visible rows
            document.querySelectorAll(".appointment-row").forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else if (row.classList.contains('appointment-card')) {
                    row.style.display = "none";
                }
            });
            return;
        }
        
        // Check if we're on mobile (table is hidden, mobile cards are shown)
        const isMobile = window.innerWidth <= 768;
        
        // Filter visibleRows to only include elements for current view
        const filteredRows = visibleRows.filter(row => {
            if (isMobile) {
                // On mobile: only show mobile cards
                return row.classList.contains('appointment-card');
            } else {
                // On desktop: only show table rows
                return row.tagName === 'TR';
            }
        });
        
        const startIndex = (page - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const rowsToShow = filteredRows.slice(startIndex, endIndex);

        // First, hide all appointment rows
        document.querySelectorAll(".appointment-row").forEach(row => {
            if (row.tagName === 'TR') {
                row.style.display = "none";
            } else if (row.classList.contains('appointment-card')) {
                row.style.display = "none";
            }
        });

        // Show only rows for current page
        rowsToShow.forEach(row => {
            if (row.tagName === 'TR') {
                // Table row: show on desktop
                row.style.display = "table-row";
            } else if (row.classList.contains('appointment-card')) {
                // Mobile card: show on mobile
                row.style.display = "block";
            }
        });
    }

    function goToPage(page) {
        // Get all currently visible rows based on filter
        const rows = document.querySelectorAll(".appointment-row[data-visible='true']");
        if (rows.length === 0) {
            updatePagination([]);
            showPage([], page);
            return;
        }

        currentPage = page;
        const visibleRows = Array.from(rows);
        updatePagination(visibleRows);
        showPage(visibleRows, currentPage);
    }

    function changePage(direction) {
        // Get all currently visible rows based on filter
        const rows = document.querySelectorAll(".appointment-row[data-visible='true']");
        if (rows.length === 0) return;

        const totalPages = Math.ceil(rows.length / rowsPerPage);
        const newPage = currentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            goToPage(newPage);
        }
    }
    
    // Confirm Appointment
    function confirmAppointment(button) {
        const appointmentId = button.getAttribute('data-appointment-id');
        if (!appointmentId) {
            showNotification('error', 'Error', 'Appointment ID not found. Please refresh the page.');
            return;
        }
        
        const originalHTML = button.innerHTML;
        const originalText = button.textContent.trim();
        button.disabled = true;
        // Preserve text if it exists (for mobile cards)
        if (originalText && originalText.length > 0 && !originalText.match(/^[<i]/)) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + originalText;
        } else {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        
        fetch('../controllers/confirmAppointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'appointment_id=' + encodeURIComponent(appointmentId)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                // Trim whitespace and try to parse as JSON
                text = text.trim();
                // Try to extract JSON from response (in case there's extra output)
                const jsonMatch = text.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    try {
                        const parsed = JSON.parse(jsonMatch[0]);
                        return parsed;
                    } catch (e) {
                        console.warn('Failed to parse extracted JSON:', e);
                    }
                }
                // If no JSON found or parsing failed, try to parse the whole text
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse response as JSON. Response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            // Check for success - be explicit about boolean true
            const isSuccess = data && (data.success === true || data.status === 'success');
            
            if (isSuccess) {
                showNotification('success', 'Appointment Confirmed', `Appointment #${appointmentId} has been confirmed.`);
                setTimeout(() => {
                    refreshAppointmentList().catch(error => {
                        console.error('Refresh error:', error);
                        showNotification('warning', 'Refresh Needed', 'Appointment was updated, but the list could not refresh automatically.');
                    });
                }, 1500);
            } else {
                // If data.success is explicitly false, show the error message
                const errorMsg = (data && data.message) ? data.message : 'Failed to confirm appointment. Please try again.';
                showNotification('error', 'Error', errorMsg);
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while confirming the appointment. Please try again.');
            button.disabled = false;
            button.innerHTML = originalHTML;
        });
    }
    
    // Mark No-Show
    function markNoShow(button) {
        const appointmentId = button.getAttribute('data-appointment-id');
        if (!appointmentId) {
            showNotification('error', 'Error', 'Appointment ID not found. Please refresh the page.');
            return;
        }
        
        if (!confirm(`Are you sure you want to mark Appointment #${appointmentId} as No-Show? An email notification will be sent to the patient.`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('appointment_id', appointmentId);
        
        const originalHTML = button.innerHTML;
        const originalText = button.textContent.trim();
        button.disabled = true;
        // Preserve text if it exists (for mobile cards)
        if (originalText && originalText.length > 0 && !originalText.match(/^[<i]/)) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + originalText;
        } else {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        
        fetch('../controllers/noshowAppointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data && (data.success === true || data.status === 'success')) {
                showNotification('success', 'Marked as No-Show', data.message || `Appointment #${appointmentId} has been marked as no-show.`);
                setTimeout(() => {
                    refreshAppointmentList().catch(error => {
                        console.error('Refresh error:', error);
                        showNotification('warning', 'Refresh Needed', 'Appointment was updated, but the list could not refresh automatically.');
                    });
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to mark as no-show. Please try again.');
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while marking as no-show. Please try again.');
            button.disabled = false;
            button.innerHTML = originalHTML;
        });
    }

    function showArchiveConfirmPopup(appointmentId) {
        return new Promise((resolve) => {
            const modal = document.getElementById('archiveConfirmModal');
            const messageEl = document.getElementById('archiveConfirmMessage');
            const cancelBtn = document.getElementById('archiveConfirmCancelBtn');
            const proceedBtn = document.getElementById('archiveConfirmProceedBtn');

            if (!modal || !messageEl || !cancelBtn || !proceedBtn) {
                resolve(false);
                return;
            }

            messageEl.textContent = `Archive Appointment #${appointmentId}? This will remove it from the active appointments list.`;
            modal.classList.add('show');

            let settled = false;
            const cleanup = () => {
                cancelBtn.removeEventListener('click', onCancel);
                proceedBtn.removeEventListener('click', onProceed);
                modal.removeEventListener('click', onBackdropClick);
                document.removeEventListener('keydown', onEsc);
                modal.classList.remove('show');
            };

            const finish = (value) => {
                if (settled) return;
                settled = true;
                cleanup();
                resolve(value);
            };

            const onCancel = () => finish(false);
            const onProceed = () => finish(true);
            const onBackdropClick = (event) => {
                if (event.target === modal) finish(false);
            };
            const onEsc = (event) => {
                if (event.key === 'Escape') finish(false);
            };

            cancelBtn.addEventListener('click', onCancel);
            proceedBtn.addEventListener('click', onProceed);
            modal.addEventListener('click', onBackdropClick);
            document.addEventListener('keydown', onEsc);
        });
    }

    // Archive completed appointment (move to archived_appointments, then delete from appointments)
    function archiveAppointment(button) {
        const appointmentId = button.getAttribute('data-appointment-id');
        if (!appointmentId) {
            showNotification('error', 'Error', 'Appointment ID not found. Please refresh the page.');
            return;
        }

        showArchiveConfirmPopup(appointmentId).then(confirmed => {
            if (!confirmed) return;

            const formData = new FormData();
            formData.append('appointment_id', appointmentId);

            const originalHTML = button.innerHTML;
            const originalText = button.textContent.trim();
            button.disabled = true;
            if (originalText && originalText.length > 0 && !originalText.match(/^[<i]/)) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + originalText;
            } else {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            fetch('../controllers/archiveAppointment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data && (data.success === true || data.status === 'success')) {
                    showNotification('success', 'Appointment Archived', data.message || `Appointment #${appointmentId} has been archived.`);
                    setTimeout(() => {
                        window.location.href = '../views/archives.php';
                    }, 900);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to archive appointment. Please try again.');
                    button.disabled = false;
                    button.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while archiving the appointment. Please try again.');
                button.disabled = false;
                button.innerHTML = originalHTML;
            });
        });
    }
    
    // Cancel Appointment by Admin
    function cancelAppointmentByAdmin(button) {
        const appointmentId = button.getAttribute('data-appointment-id');
        if (!appointmentId) {
            showNotification('error', 'Error', 'Appointment ID not found. Please refresh the page.');
            return;
        }
        
        if (!confirm(`Are you sure you want to cancel Appointment #${appointmentId}? An email notification will be sent to the patient.`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('appointment_id', appointmentId);
        
        const originalHTML = button.innerHTML;
        const originalText = button.textContent.trim();
        button.disabled = true;
        // Preserve text if it exists (for mobile cards)
        if (originalText && originalText.length > 0 && !originalText.match(/^[<i]/)) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + originalText;
        } else {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        
        fetch('../controllers/adminCancelAppointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch {
                        return { success: true };
                    }
                });
            }
        })
        .then(data => {
            if (data.success) {
                showNotification('success', 'Appointment Cancelled', data.message || 'Appointment has been cancelled and email notification sent.');
                setTimeout(() => {
                    refreshAppointmentList().catch(error => {
                        console.error('Refresh error:', error);
                        showNotification('warning', 'Refresh Needed', 'Appointment was updated, but the list could not refresh automatically.');
                    });
                }, 1500);
            } else {
                showNotification('error', 'Error', data.error || data.message || 'Failed to cancel appointment. Please try again.');
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while cancelling the appointment. Please try again.');
            button.disabled = false;
            button.innerHTML = originalHTML;
        });
    }
    
    // Handle Reschedule Form Submit
    function handleRescheduleSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const appointmentId = document.getElementById('modalAppointmentID').value;
        const newDate = document.getElementById('new_date_resched').value;
        const timeSelect = document.getElementById('new_time_resched');
        const selectedTimeSlot = timeSelect.value;
        const reason = document.getElementById('reschedule_reason').value.trim();
        const hasRequestNote = document.getElementById('modalHasRequestNote')?.value === '1';
        const slotOrder = ['firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch'];
        const getNextSlot = (slotKey) => {
            const idx = slotOrder.indexOf(slotKey);
            if (idx === -1 || idx + 1 >= slotOrder.length) return null;
            return slotOrder[idx + 1];
        };
        
        // Validation
        if (!newDate) {
            showNotification('error', 'Validation Error', 'Please select a new date.');
            return;
        }
        
        if (!selectedTimeSlot) {
            showNotification('error', 'Validation Error', 'Please select a time slot.');
            timeSelect.focus();
            return;
        }
        
        if (!reason) {
            showNotification('error', 'Validation Error', 'Please provide a reason for rescheduling.');
            document.getElementById('reschedule_reason').focus();
            return;
        }
        
        // Check if selected slot is disabled (booked)
        const selectedOption = timeSelect.options[timeSelect.selectedIndex];
        if (selectedOption.disabled) {
            showNotification('error', 'Slot Unavailable', 'The selected time slot is already booked. Please choose another slot.');
            timeSelect.focus();
            return;
        }

        if (hasRequestNote) {
            const nextSlot = getNextSlot(selectedTimeSlot);
            if (!nextSlot) {
                showNotification('error', 'Invalid Time Slot', 'This appointment requires 2 consecutive slots. Please select an earlier time.');
                return;
            }
            const nextOption = Array.from(timeSelect.options).find(opt => opt.value === nextSlot);
            if (!nextOption || nextOption.disabled) {
                showNotification('error', 'Second Slot Unavailable', 'The next consecutive slot is unavailable. Please choose another start time.');
                return;
            }
        }
        
        // Validate date is not in the past
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(newDate);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            showNotification('error', 'Invalid Date', 'Please select a date from today onwards.');
            return;
        }
        
        const timeText = selectedOption.textContent.split(' (')[0]; // Remove "(Booked)" if present
        const submitBtn = document.getElementById('rescheduleSubmitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        fetch('../controllers/rescheduleAppointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                return response.text().then(text => {
                    // Try to parse as JSON
                    try {
                        return JSON.parse(text);
                    } catch {
                        return { success: false, message: 'Invalid response from server' };
                    }
                });
            }
        })
        .then(data => {
            if (data.success === true || data.status === 'success') {
                const formattedDate = new Date(newDate).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                showNotification('success', 'Appointment Rescheduled', `Appointment #${appointmentId} has been rescheduled to ${formattedDate} at ${timeText}.`);
                closeReschedModal();
                setTimeout(() => {
                    refreshAppointmentList()
                        .catch(error => {
                            console.error('Refresh error:', error);
                            showNotification('warning', 'Refresh Needed', 'Appointment was updated, but the list could not refresh automatically.');
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        });
                }, 2000);
            } else {
                const errorMsg = data.message || 'Failed to reschedule appointment. Please try again.';
                showNotification('error', 'Reschedule Failed', errorMsg);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while rescheduling. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // Reschedule functions
    function openReschedModalWithID(btn, event) {
        if (event) {
            event.preventDefault();
        }
        const appointmentID = btn.getAttribute('data-id');
        const row = btn.closest('.appointment-row');
        const requestNote = row ? (row.getAttribute('data-request-note') || '').trim() : '';
        const hasRequestNote = requestNote !== '';
        
        if (!appointmentID) {
            showNotification('error', 'Error', 'Appointment ID not found. Please try again.');
            return false;
        }
        
        const modalAppointmentIDInput = document.getElementById('modalAppointmentID');
        if (modalAppointmentIDInput) {
            modalAppointmentIDInput.value = appointmentID;
        }
        const modalHasRequestNoteInput = document.getElementById('modalHasRequestNote');
        if (modalHasRequestNoteInput) {
            modalHasRequestNoteInput.value = hasRequestNote ? '1' : '0';
        }
        const twoSlotHint = document.getElementById('twoSlotHint');
        if (twoSlotHint) {
            twoSlotHint.style.display = hasRequestNote ? 'block' : 'none';
        }
        
        // Reset form
        const reschedForm = document.querySelector('#reschedModal form');
        if (reschedForm) {
            const dateInput = reschedForm.querySelector('#new_date_resched');
            const timeSelect = reschedForm.querySelector('#new_time_resched');
            const reasonTextarea = reschedForm.querySelector('#reschedule_reason');
            if (dateInput) dateInput.value = '';
            if (reasonTextarea) reasonTextarea.value = '';
            if (timeSelect) {
                timeSelect.value = '';
                // Reset all options
                const options = timeSelect.querySelectorAll('option:not(:first-child)');
                options.forEach(opt => {
                    opt.disabled = false;
                    opt.textContent = opt.textContent.split(' (Booked)')[0];
                });
            }
        }
        
        // Fetch and display current appointment details
        fetchAppointmentDetails(appointmentID);
        
        openReschedModal();
        return false;
    }
    
    // Fetch appointment details for display
    function fetchAppointmentDetails(appointmentId) {
        const patientNameEl = document.getElementById('currentPatientName');
        const serviceEl = document.getElementById('currentService');
        const dateEl = document.getElementById('currentDate');
        const timeEl = document.getElementById('currentTime');
        
        // Show loading state
        if (patientNameEl) patientNameEl.value = 'Loading...';
        if (serviceEl) serviceEl.value = 'Loading...';
        if (dateEl) dateEl.value = 'Loading...';
        if (timeEl) timeEl.value = 'Loading...';
        
        // Find the appointment row to get details
        const appointmentRow = document.querySelector(`[data-appointment-id="${appointmentId}"]`);
        if (appointmentRow) {
            // Try to get from data attributes first (more reliable)
            const patientName = appointmentRow.getAttribute('data-patient-name') || 'N/A';
            const service = appointmentRow.getAttribute('data-service') || 'N/A';
            const currentDate = appointmentRow.getAttribute('data-appointment-date') || 'N/A';
            const currentTime = appointmentRow.getAttribute('data-appointment-time') || 'N/A';
            
            if (patientNameEl) patientNameEl.value = patientName;
            if (serviceEl) serviceEl.value = service;
            if (dateEl) dateEl.value = currentDate;
            if (timeEl) timeEl.value = currentTime;
            
            return;
        }
        
        // If not found in table, try to fetch from API
        fetch(`../controllers/getAppointmentDetails.php?appointment_id=${appointmentId}`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch appointment details');
                return response.json();
            })
            .then(data => {
                if (data.success && data.appointment) {
                    const apt = data.appointment;
                    if (patientNameEl) patientNameEl.value = `${apt.first_name || ''} ${apt.last_name || ''}`.trim() || 'N/A';
                    if (serviceEl) serviceEl.value = apt.sub_service || apt.service_category || 'N/A';
                    if (dateEl) dateEl.value = apt.appointment_date ? new Date(apt.appointment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
                    if (timeEl) timeEl.value = apt.appointment_time || 'N/A';
                } else {
                    throw new Error('Appointment details not found');
                }
            })
            .catch(error => {
                console.error('Error fetching appointment details:', error);
                // Show error message if fetch fails
                if (patientNameEl) patientNameEl.value = 'Unable to load';
                if (serviceEl) serviceEl.value = 'Unable to load';
                if (dateEl) dateEl.value = 'Unable to load';
                if (timeEl) timeEl.value = 'Unable to load';
            });
    }

    function loadBookedSlots() {
        const dateInput = document.getElementById('new_date_resched');
        const timeSelect = document.getElementById('new_time_resched');
        const loadingIndicator = document.getElementById('loadingSlots');
        const timeSlotHelp = document.getElementById('timeSlotHelp');
        const appointmentId = document.getElementById('modalAppointmentID').value;
        const hasRequestNote = document.getElementById('modalHasRequestNote')?.value === '1';
        const slotOrder = ['firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch'];
        const getNextSlot = (slotKey) => {
            const idx = slotOrder.indexOf(slotKey);
            if (idx === -1 || idx + 1 >= slotOrder.length) return null;
            return slotOrder[idx + 1];
        };
        
        if (!dateInput || !timeSelect) return;
        
        // Reset time select
        timeSelect.value = '';
        
        if (!dateInput.value) {
            // Disable time select when no date is selected
            timeSelect.disabled = true;
            const options = timeSelect.querySelectorAll('option:not(:first-child)');
            options.forEach(opt => {
                opt.disabled = false;
                opt.textContent = opt.textContent.split(' (')[0];
            });
            if (loadingIndicator) loadingIndicator.style.display = 'none';
            if (timeSlotHelp) {
                timeSlotHelp.textContent = 'Please select a date first';
                timeSlotHelp.style.color = '';
            }
            return;
        }
        
        // Enable time select when date is selected
        timeSelect.disabled = false;
        
        // Validate date is not in the past
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(dateInput.value);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            showNotification('error', 'Invalid Date', 'Please select a date from today onwards.');
            dateInput.value = '';
            return;
        }
        
        // Show loading indicator
        if (loadingIndicator) loadingIndicator.style.display = 'block';
        if (timeSlotHelp) {
            timeSlotHelp.textContent = 'Checking available slots...';
            timeSlotHelp.style.color = '';
        }
        
        // Disable all options while loading
        const options = timeSelect.querySelectorAll('option:not(:first-child)');
        options.forEach(opt => {
            opt.disabled = true;
        });
        
        fetch(`../controllers/getAppointmentsAdminResched.php?new_date_resched=${encodeURIComponent(dateInput.value)}&appointment_id=${encodeURIComponent(appointmentId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(response => {
                // Handle both old format (array) and new format (object)
                let unavailableSlots = [];
                let clinicClosed = false;
                let closureReason = '';
                let closureType = '';
                
                if (Array.isArray(response)) {
                    // Old format - just array of slots
                    unavailableSlots = response;
                } else if (response && typeof response === 'object') {
                    // New format - object with closure info
                    unavailableSlots = response.unavailable_slots || [];
                    clinicClosed = response.clinic_closed || false;
                    closureReason = response.closure_reason || '';
                    closureType = response.closure_type || '';
                } else {
                    throw new Error('Invalid response format');
                }
                
                // Show closure warning if clinic is fully closed
                if (clinicClosed && closureType === 'full_day') {
                    if (timeSlotHelp) {
                        timeSlotHelp.textContent = `⚠️ Clinic is closed on this date: ${closureReason || 'Full day closure'}`;
                        timeSlotHelp.style.color = '#e74c3c';
                    }
                }
                
                const slotMapping = {
                    'firstBatch': 'Morning (8:00AM-9:00AM)',
                    'secondBatch': 'Morning (9:00AM-10:00AM)',
                    'thirdBatch': 'Morning (10:00AM-11:00AM)',
                    'fourthBatch': 'Afternoon (11:00AM-12:00PM)',
                    'fifthBatch': 'Afternoon (1:00PM-2:00PM)',
                    'sixthBatch': 'Afternoon (2:00PM-3:00PM)',
                    'sevenBatch': 'Afternoon (3:00PM-4:00PM)',
                    'eightBatch': 'Afternoon (4:00PM-5:00PM)',
                    'nineBatch': 'Afternoon (5:00PM-6:00PM)',
                    'tenBatch': 'Evening (6:00PM-7:00PM)',
                    'lastBatch': 'Evening (7:00PM-8:00PM)'
                };
                
                let availableCount = 0;
                const options = timeSelect.querySelectorAll('option:not(:first-child)');
                options.forEach(opt => {
                    if (opt.value === '') return;
                    
                    // Check if slot is unavailable (booked or blocked)
                    const isUnavailable = unavailableSlots.includes(opt.value);
                    
                    opt.disabled = isUnavailable;
                    
                    // Get base label from mapping to ensure consistent format
                    const baseLabel = slotMapping[opt.value] || opt.getAttribute('data-slot') || opt.textContent.split(' (')[0].trim();
                    
                    // Determine status text
                    let statusText = '';
                    if (isUnavailable) {
                        statusText = ' (Unavailable)';
                    }
                    
                    opt.textContent = baseLabel + statusText;
                    
                    if (!isUnavailable) availableCount++;
                });

                // If this appointment has request_note, require the next slot too.
                if (hasRequestNote) {
                    options.forEach(opt => {
                        if (opt.value === '' || opt.disabled) return;
                        const nextSlot = getNextSlot(opt.value);
                        if (!nextSlot) {
                            opt.disabled = true;
                            opt.textContent = (slotMapping[opt.value] || opt.textContent.split(' (')[0].trim()) + ' (Needs next slot)';
                            return;
                        }
                        if (unavailableSlots.includes(nextSlot)) {
                            opt.disabled = true;
                            opt.textContent = (slotMapping[opt.value] || opt.textContent.split(' (')[0].trim()) + ' (Next slot unavailable)';
                        }
                    });
                    availableCount = 0;
                    options.forEach(opt => {
                        if (!opt.disabled && opt.value !== '') availableCount++;
                    });
                }
                
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (timeSlotHelp && !(clinicClosed && closureType === 'full_day')) {
                    if (availableCount === 0) {
                        timeSlotHelp.textContent = '⚠️ No available slots for this date. Please select another date.';
                        timeSlotHelp.style.color = '#e74c3c';
                    } else {
                        timeSlotHelp.textContent = `✓ ${availableCount} slot(s) available`;
                        timeSlotHelp.style.color = '#27ae60';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading booked slots:', error);
                showNotification('error', 'Error', 'Failed to load available time slots. Please try again.');
                
                // Re-enable all options on error and restore original text
                const slotMapping = {
                    'firstBatch': 'Morning (8:00AM-9:00AM)',
                    'secondBatch': 'Morning (9:00AM-10:00AM)',
                    'thirdBatch': 'Morning (10:00AM-11:00AM)',
                    'fourthBatch': 'Afternoon (11:00AM-12:00PM)',
                    'fifthBatch': 'Afternoon (1:00PM-2:00PM)',
                    'sixthBatch': 'Afternoon (2:00PM-3:00PM)',
                    'sevenBatch': 'Afternoon (3:00PM-4:00PM)',
                    'eightBatch': 'Afternoon (4:00PM-5:00PM)',
                    'nineBatch': 'Afternoon (5:00PM-6:00PM)',
                    'tenBatch': 'Evening (6:00PM-7:00PM)',
                    'lastBatch': 'Evening (7:00PM-8:00PM)'
                };
                const options = timeSelect.querySelectorAll('option:not(:first-child)');
                options.forEach(opt => {
                    opt.disabled = false;
                    opt.textContent = slotMapping[opt.value] || opt.getAttribute('data-slot') || opt.textContent.split(' (')[0];
                });
                
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (timeSlotHelp) {
                    timeSlotHelp.textContent = 'Error loading slots. Please try again.';
                    timeSlotHelp.style.color = '#e74c3c';
                }
            });
    }

    function openReschedModal() {
        const modal = document.getElementById("reschedModal");
        if (modal) {
            modal.style.display = "block";
            document.body.style.overflow = 'hidden';
            // Focus on date input
            setTimeout(() => {
                const dateInput = document.getElementById('new_date_resched');
                if (dateInput) dateInput.focus();
            }, 100);
        }
    }

    function closeReschedModal() {
        const modal = document.getElementById("reschedModal");
        if (modal) {
            modal.style.display = "none";
            document.body.style.overflow = 'auto';
            const form = document.querySelector('#reschedModal form');
            if (form) {
                form.reset();
                // Reset time slot help text
                const timeSlotHelp = document.getElementById('timeSlotHelp');
                if (timeSlotHelp) {
                    timeSlotHelp.textContent = 'Please select a date first';
                    timeSlotHelp.style.color = '';
                }
                // Hide loading indicator
                const loadingIndicator = document.getElementById('loadingSlots');
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                // Reset all time slot options and disable the select
                const timeSelect = document.getElementById('new_time_resched');
                if (timeSelect) {
                    timeSelect.disabled = true;
                    const options = timeSelect.querySelectorAll('option:not(:first-child)');
                    options.forEach(opt => {
                        opt.disabled = false;
                        opt.textContent = opt.textContent.split(' (')[0];
                    });
                }
                // Reset reason field
                const reasonField = document.getElementById('reschedule_reason');
                if (reasonField) reasonField.value = '';
                const hasRequestNoteInput = document.getElementById('modalHasRequestNote');
                if (hasRequestNoteInput) hasRequestNoteInput.value = '0';
                const twoSlotHint = document.getElementById('twoSlotHint');
                if (twoSlotHint) twoSlotHint.style.display = 'none';
            }
            // Reset current appointment info (don't hide, just reset values)
            const patientNameEl = document.getElementById('currentPatientName');
            const serviceEl = document.getElementById('currentService');
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if (patientNameEl) patientNameEl.value = '-';
            if (serviceEl) serviceEl.value = '-';
            if (dateEl) dateEl.value = '-';
            if (timeEl) timeEl.value = '-';
        }
    }
    
    // Complete Appointment Modal
    function openCompleteAppointmentModal(button) {
        const patientId = button.getAttribute('data-patientid');
        const appointmentId = button.getAttribute('data-appointmentid');
        
        if (!patientId || !appointmentId) {
            showNotification('error', 'Error', 'Missing patient or appointment information.');
            return;
        }
        
        const modal = document.getElementById('complete-appointment-modal');
        const patientIdInput = document.getElementById('treatment_patient_id');
        const appointmentIdInput = document.getElementById('treatment_appointment_id');
        const patientIdDisplay = document.getElementById('patient_id');
        
        if (!modal) {
            showNotification('error', 'Error', 'Modal not found. Please refresh the page.');
            return;
        }
        
        if (!patientIdInput || !appointmentIdInput || !patientIdDisplay) {
            showNotification('error', 'Error', 'Form elements not found. Please refresh the page.');
            return;
        }
        
        patientIdInput.value = patientId;
        appointmentIdInput.value = appointmentId;
        patientIdDisplay.value = patientId;

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Focus on first input field
        setTimeout(() => {
            const treatmentInput = document.getElementById('treatment_type');
            if (treatmentInput) treatmentInput.focus();
        }, 100);
    }

    function closeCompleteAppointmentModal() {
        const modal = document.getElementById('complete-appointment-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            const form = document.getElementById('treatmentForm');
            if (form) {
                form.reset();
                // Reset patient ID display
                const patientIdDisplay = document.getElementById('patient_id');
                if (patientIdDisplay) patientIdDisplay.value = '';
            }
        }
    }
    
    // Handle Treatment Form Submit
    function handleTreatmentSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const appointmentId = document.getElementById('treatment_appointment_id').value;
        const treatment = document.getElementById('treatment_type').value.trim();
        const prescription = document.getElementById('prescription_given').value.trim();
        const notes = document.getElementById('treatment_notes').value.trim();
        const cost = document.getElementById('treatment_cost').value;
        
        // Validation
        if (!treatment) {
            showNotification('error', 'Validation Error', 'Please enter the treatment type.');
            document.getElementById('treatment_type').focus();
            return;
        }
        
        if (!prescription) {
            showNotification('error', 'Validation Error', 'Please enter the prescription.');
            document.getElementById('prescription_given').focus();
            return;
        }
        
        if (!notes) {
            showNotification('error', 'Validation Error', 'Please enter treatment notes.');
            document.getElementById('treatment_notes').focus();
            return;
        }
        
        if (!cost || parseFloat(cost) < 0) {
            showNotification('error', 'Validation Error', 'Please enter a valid treatment cost.');
            document.getElementById('treatment_cost').focus();
            return;
        }
        
        const submitBtn = document.getElementById('completeAppointmentSubmitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        fetch('../controllers/saveTreatment.php', {
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
                showNotification('success', 'Appointment Completed', `Appointment #${appointmentId} has been completed and treatment saved successfully.`);
                closeCompleteAppointmentModal();
                setTimeout(() => {
                    refreshAppointmentList()
                        .catch(error => {
                            console.error('Refresh error:', error);
                            showNotification('warning', 'Refresh Needed', 'Appointment was updated, but the list could not refresh automatically.');
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        });
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
    
    // Follow-Up Modal
    function openFollowUpModal(button) {
        const appointmentId = button.getAttribute('data-appointment-id');
        const patientId = button.getAttribute('data-patient-id');
        const patientName = button.getAttribute('data-patient-name');
        
        // Get appointment row to access additional data
        const appointmentRow = button.closest('.appointment-row');
        const teamId = appointmentRow ? appointmentRow.getAttribute('data-team-id') : '';
        const branch = appointmentRow ? appointmentRow.getAttribute('data-branch') : '';
        const currentServiceId = appointmentRow ? appointmentRow.getAttribute('data-service-id') : '';
        
        const patientIdInput = document.getElementById('followup_patient_id');
        const appointmentIdInput = document.getElementById('followup_appointment_id');
        const patientNameInput = document.getElementById('followup_patient_name');
        const teamIdInput = document.getElementById('followup_team_id');
        const branchInput = document.getElementById('followup_branch');
        const serviceSelect = document.getElementById('followup_service_id');
        
        if (patientIdInput) patientIdInput.value = patientId;
        if (appointmentIdInput) appointmentIdInput.value = appointmentId;
        if (patientNameInput) patientNameInput.value = patientName;
        if (teamIdInput) teamIdInput.value = teamId;
        if (branchInput) branchInput.value = branch;
        
        // Set current service as default if available
        if (serviceSelect && currentServiceId) {
            serviceSelect.value = currentServiceId;
        }
        
        const modal = document.getElementById('followUpModal');
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            // Focus on service select
            setTimeout(() => {
                const serviceSelect = document.getElementById('followup_service_id');
                if (serviceSelect) serviceSelect.focus();
            }, 100);
        }
    }
    
    function closeFollowUpModal() {
        const modal = document.getElementById('followUpModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            const form = document.getElementById('followUpForm');
            if (form) {
                form.reset();
                const reasonField = document.getElementById('followup_reason');
                if (reasonField) reasonField.value = '';
                
                // Reset time slot options
                const timeSelect = document.getElementById('followup_time');
                if (timeSelect) {
                    const options = timeSelect.querySelectorAll('option:not(:first-child)');
                    options.forEach(opt => {
                        opt.disabled = false;
                        opt.textContent = opt.getAttribute('data-slot') || opt.textContent.split(' (')[0];
                    });
                }
                
                // Reset help text
                const timeSlotHelp = document.getElementById('followupTimeSlotHelp');
                if (timeSlotHelp) {
                    timeSlotHelp.textContent = 'Booked slots will be disabled automatically';
                    timeSlotHelp.style.color = '';
                }
                
                // Hide loading indicator
                const loadingIndicator = document.getElementById('loadingFollowUpSlots');
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            }
        }
    }
    
    // Load booked slots for follow-up date
    function loadFollowUpBookedSlots() {
        const dateInput = document.getElementById('followup_date');
        const timeSelect = document.getElementById('followup_time');
        const loadingIndicator = document.getElementById('loadingFollowUpSlots');
        const timeSlotHelp = document.getElementById('followupTimeSlotHelp');
        
        if (!dateInput || !timeSelect) return;
        
        // Reset time select
        timeSelect.value = '';
        
        if (!dateInput.value) {
            const options = timeSelect.querySelectorAll('option:not(:first-child)');
            options.forEach(opt => {
                opt.disabled = false;
                opt.textContent = opt.getAttribute('data-slot') || opt.textContent.split(' (')[0];
            });
            if (loadingIndicator) loadingIndicator.style.display = 'none';
            if (timeSlotHelp) {
                timeSlotHelp.textContent = 'Booked slots will be disabled automatically';
                timeSlotHelp.style.color = '';
            }
            return;
        }
        
        // Validate date is not in the past
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(dateInput.value);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            showNotification('error', 'Invalid Date', 'Please select a date from today onwards.');
            dateInput.value = '';
            return;
        }
        
        // Show loading indicator
        if (loadingIndicator) loadingIndicator.style.display = 'block';
        if (timeSlotHelp) {
            timeSlotHelp.textContent = 'Checking available slots...';
            timeSlotHelp.style.color = '';
        }
        
        // Disable all options while loading
        const options = timeSelect.querySelectorAll('option:not(:first-child)');
        options.forEach(opt => {
            opt.disabled = true;
        });
        
        fetch(`../controllers/getAppointmentsAdminResched.php?new_date_resched=${encodeURIComponent(dateInput.value)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(response => {
                let unavailableSlots = [];
                let clinicClosed = false;
                let closureReason = '';
                let closureType = '';
                
                if (Array.isArray(response)) {
                    unavailableSlots = response;
                } else if (response && typeof response === 'object') {
                    unavailableSlots = response.unavailable_slots || [];
                    clinicClosed = response.clinic_closed || false;
                    closureReason = response.closure_reason || '';
                    closureType = response.closure_type || '';
                }
                
                // Show closure warning if clinic is fully closed
                if (clinicClosed && closureType === 'full_day') {
                    if (timeSlotHelp) {
                        timeSlotHelp.textContent = `⚠️ Clinic is closed on this date: ${closureReason || 'Full day closure'}`;
                        timeSlotHelp.style.color = '#e74c3c';
                    }
                }
                
                const slotMapping = {
                    'firstBatch': 'Morning (8:00AM-9:00AM)',
                    'secondBatch': 'Morning (9:00AM-10:00AM)',
                    'thirdBatch': 'Morning (10:00AM-11:00AM)',
                    'fourthBatch': 'Afternoon (11:00AM-12:00PM)',
                    'fifthBatch': 'Afternoon (1:00PM-2:00PM)',
                    'sixthBatch': 'Afternoon (2:00PM-3:00PM)',
                    'sevenBatch': 'Afternoon (3:00PM-4:00PM)',
                    'eightBatch': 'Afternoon (4:00PM-5:00PM)',
                    'nineBatch': 'Afternoon (5:00PM-6:00PM)',
                    'tenBatch': 'Evening (6:00PM-7:00PM)',
                    'lastBatch': 'Evening (7:00PM-8:00PM)'
                };
                
                let availableCount = 0;
                const options = timeSelect.querySelectorAll('option:not(:first-child)');
                options.forEach(opt => {
                    if (opt.value === '') return;
                    
                    const isUnavailable = unavailableSlots.includes(opt.value);
                    opt.disabled = isUnavailable;
                    
                    const baseLabel = slotMapping[opt.value] || opt.getAttribute('data-slot') || opt.textContent.split(' (')[0].trim();
                    let statusText = '';
                    if (isUnavailable) {
                        statusText = ' (Unavailable)';
                    }
                    
                    opt.textContent = baseLabel + statusText;
                    
                    if (!isUnavailable) availableCount++;
                });
                
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (timeSlotHelp && !(clinicClosed && closureType === 'full_day')) {
                    if (availableCount === 0) {
                        timeSlotHelp.textContent = '⚠️ No available slots for this date. Please select another date.';
                        timeSlotHelp.style.color = '#e74c3c';
                    } else {
                        timeSlotHelp.textContent = `✓ ${availableCount} slot(s) available`;
                        timeSlotHelp.style.color = '#27ae60';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading booked slots:', error);
                showNotification('error', 'Error', 'Failed to load available time slots. Please try again.');
                
                // Re-enable all options on error
                const slotMapping = {
                    'firstBatch': 'Morning (8:00AM-9:00AM)',
                    'secondBatch': 'Morning (9:00AM-10:00AM)',
                    'thirdBatch': 'Morning (10:00AM-11:00AM)',
                    'fourthBatch': 'Afternoon (11:00AM-12:00PM)',
                    'fifthBatch': 'Afternoon (1:00PM-2:00PM)',
                    'sixthBatch': 'Afternoon (2:00PM-3:00PM)',
                    'sevenBatch': 'Afternoon (3:00PM-4:00PM)',
                    'eightBatch': 'Afternoon (4:00PM-5:00PM)',
                    'nineBatch': 'Afternoon (5:00PM-6:00PM)',
                    'tenBatch': 'Evening (6:00PM-7:00PM)',
                    'lastBatch': 'Evening (7:00PM-8:00PM)'
                };
                const options = timeSelect.querySelectorAll('option:not(:first-child)');
                options.forEach(opt => {
                    opt.disabled = false;
                    opt.textContent = slotMapping[opt.value] || opt.getAttribute('data-slot') || opt.textContent.split(' (')[0];
                });
                
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (timeSlotHelp) {
                    timeSlotHelp.textContent = 'Error loading slots. Please try again.';
                    timeSlotHelp.style.color = '#e74c3c';
                }
            });
    }
    
    // Handle Follow-Up Form Submit
    function handleFollowUpSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const appointmentId = document.getElementById('followup_appointment_id').value;
        const followUpDate = document.getElementById('followup_date').value;
        const timeSlot = document.getElementById('followup_time').value;
        const serviceId = document.getElementById('followup_service_id').value;
        const reason = document.getElementById('followup_reason').value.trim();
        
        // Validation
        if (!serviceId) {
            showNotification('error', 'Validation Error', 'Please select a service.');
            document.getElementById('followup_service_id').focus();
            return;
        }
        
        if (!followUpDate) {
            showNotification('error', 'Validation Error', 'Please select a follow-up date.');
            return;
        }
        
        if (!timeSlot) {
            showNotification('error', 'Validation Error', 'Please select a time slot.');
            document.getElementById('followup_time').focus();
            return;
        }
        
        // Check if selected slot is disabled (booked)
        const timeSelect = document.getElementById('followup_time');
        const selectedOption = timeSelect.options[timeSelect.selectedIndex];
        if (selectedOption.disabled) {
            showNotification('error', 'Slot Unavailable', 'The selected time slot is already booked. Please choose another slot.');
            timeSelect.focus();
            return;
        }
        
        if (!reason) {
            showNotification('error', 'Validation Error', 'Please provide a reason for the follow-up.');
            document.getElementById('followup_reason').focus();
            return;
        }
        
        const submitBtn = document.getElementById('followUpSubmitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        fetch('../controllers/saveFollowUp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch {
                        return { success: false, message: 'Invalid response from server' };
                    }
                });
            }
        })
        .then(data => {
            if (data.success === true || data.status === 'success') {
                const formattedDate = new Date(followUpDate).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                showNotification('success', 'Follow-Up Scheduled', `Follow-up appointment has been scheduled for ${formattedDate}. Email notification sent to patient.`);
                closeFollowUpModal();
                setTimeout(() => {
                    refreshAppointmentList()
                        .catch(error => {
                            console.error('Refresh error:', error);
                            showNotification('warning', 'Refresh Needed', 'Appointment was updated, but the list could not refresh automatically.');
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        });
                }, 2000);
            } else {
                const errorMsg = data.message || 'Failed to schedule follow-up appointment. Please try again.';
                showNotification('error', 'Follow-Up Failed', errorMsg);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while scheduling the follow-up. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    function printAppointments() {
        window.print();
    }
    
    // Add Appointment Modal Functions
    function openAddAppointmentModal() {
        const modal = document.getElementById('addAppointmentModal');
        if (!modal) return;
        resetAddAppointmentForm();
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const patientSelect = document.getElementById('add_patient_id');
            if (patientSelect) patientSelect.focus();
        }, 100);
    }

    function closeAddAppointmentModal() {
        const modal = document.getElementById('addAppointmentModal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        resetAddAppointmentForm();
    }

    function resetAddAppointmentForm() {
        const form = document.getElementById('addAppointmentForm');
        if (form) form.reset();

        const patientDetails = document.getElementById('add_patient_details');
        if (patientDetails) patientDetails.value = '';

        const fieldsToDisable = [
            document.getElementById('add_service_id'),
            document.getElementById('add_team_id'),
            document.getElementById('add_branch'),
            document.getElementById('add_appointment_date'),
            document.getElementById('add_time_slot'),
            document.getElementById('add_appointment_fee')
        ];

        fieldsToDisable.forEach(field => {
            if (field) field.disabled = true;
        });

        const timeSelect = document.getElementById('add_time_slot');
        if (timeSelect) {
            resetAddTimeSlotOptions(timeSelect);
        }

        const loadingIndicator = document.getElementById('loadingAddSlots');
        if (loadingIndicator) loadingIndicator.style.display = 'none';

        const help = document.getElementById('addTimeSlotHelp');
        if (help) {
            help.textContent = 'Select patient and date first';
            help.style.color = '';
        }
    }

    function handleAddPatientSelection() {
        const patientSelect = document.getElementById('add_patient_id');
        const patientDetails = document.getElementById('add_patient_details');
        const selectedOption = patientSelect ? patientSelect.options[patientSelect.selectedIndex] : null;
        const hasPatient = patientSelect && patientSelect.value !== '';

        if (patientDetails) {
            if (hasPatient && selectedOption) {
                const patientId = patientSelect.value;
                const patientName = selectedOption.getAttribute('data-patient-name') || '';
                patientDetails.value = `${patientName} (${patientId})`;
            } else {
                patientDetails.value = '';
            }
        }

        const fields = [
            document.getElementById('add_service_id'),
            document.getElementById('add_team_id'),
            document.getElementById('add_branch'),
            document.getElementById('add_appointment_date'),
            document.getElementById('add_time_slot'),
            document.getElementById('add_appointment_fee')
        ];

        fields.forEach(field => {
            if (field) field.disabled = !hasPatient;
        });

        if (!hasPatient) {
            const dateInput = document.getElementById('add_appointment_date');
            const timeSelect = document.getElementById('add_time_slot');
            if (dateInput) dateInput.value = '';
            if (timeSelect) timeSelect.value = '';
        }
    }

    function loadAddAppointmentBookedSlots() {
        const patientId = document.getElementById('add_patient_id')?.value || '';
        const dateInput = document.getElementById('add_appointment_date');
        const timeSelect = document.getElementById('add_time_slot');
        const loadingIndicator = document.getElementById('loadingAddSlots');
        const timeSlotHelp = document.getElementById('addTimeSlotHelp');

        if (!patientId) {
            showNotification('error', 'Validation Error', 'Please select a patient first.');
            if (dateInput) dateInput.value = '';
            return;
        }
        if (!dateInput || !timeSelect) return;

        timeSelect.value = '';

        if (!dateInput.value) {
            resetAddTimeSlotOptions(timeSelect);
            if (loadingIndicator) loadingIndicator.style.display = 'none';
            if (timeSlotHelp) {
                timeSlotHelp.textContent = 'Select patient and date first';
                timeSlotHelp.style.color = '';
            }
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(dateInput.value);
        selectedDate.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            showNotification('error', 'Invalid Date', 'Please select a date from today onwards.');
            dateInput.value = '';
            return;
        }

        if (loadingIndicator) loadingIndicator.style.display = 'block';
        if (timeSlotHelp) {
            timeSlotHelp.textContent = 'Checking available slots...';
            timeSlotHelp.style.color = '';
        }

        const options = timeSelect.querySelectorAll('option:not(:first-child)');
        options.forEach(opt => {
            opt.disabled = true;
        });

        fetch(`../controllers/getAppointmentsAdminResched.php?new_date_resched=${encodeURIComponent(dateInput.value)}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(response => {
                let unavailableSlots = [];
                let clinicClosed = false;
                let closureReason = '';
                let closureType = '';

                if (Array.isArray(response)) {
                    unavailableSlots = response;
                } else if (response && typeof response === 'object') {
                    unavailableSlots = response.unavailable_slots || [];
                    clinicClosed = response.clinic_closed || false;
                    closureReason = response.closure_reason || '';
                    closureType = response.closure_type || '';
                }

                let availableCount = 0;
                const selectOptions = timeSelect.querySelectorAll('option:not(:first-child)');
                selectOptions.forEach(opt => {
                    const isUnavailable = unavailableSlots.includes(opt.value);
                    opt.disabled = isUnavailable;
                    const baseLabel = opt.getAttribute('data-label') || opt.textContent.split(' (')[0];
                    opt.textContent = `${baseLabel}${isUnavailable ? ' (Unavailable)' : ''}`;
                    if (!isUnavailable) availableCount++;
                });

                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (timeSlotHelp) {
                    if (clinicClosed && closureType === 'full_day') {
                        timeSlotHelp.textContent = `Clinic is closed on this date: ${closureReason || 'Full day closure'}`;
                        timeSlotHelp.style.color = '#e74c3c';
                    } else if (availableCount === 0) {
                        timeSlotHelp.textContent = 'No available slots for this date. Please select another date.';
                        timeSlotHelp.style.color = '#e74c3c';
                    } else {
                        timeSlotHelp.textContent = `${availableCount} slot(s) available`;
                        timeSlotHelp.style.color = '#27ae60';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading add appointment slots:', error);
                showNotification('error', 'Error', 'Failed to load available time slots. Please try again.');
                const selectOptions = timeSelect.querySelectorAll('option:not(:first-child)');
                selectOptions.forEach(opt => {
                    opt.disabled = false;
                });
                resetAddTimeSlotOptions(timeSelect);
                if (loadingIndicator) loadingIndicator.style.display = 'none';
                if (timeSlotHelp) {
                    timeSlotHelp.textContent = 'Error loading slots. Please try again.';
                    timeSlotHelp.style.color = '#e74c3c';
                }
            });
    }

    function resetAddTimeSlotOptions(timeSelect) {
        const options = timeSelect.querySelectorAll('option:not(:first-child)');
        options.forEach(opt => {
            opt.disabled = false;
            opt.textContent = opt.getAttribute('data-label') || opt.textContent.split(' (')[0];
        });
    }

    function handleAddAppointmentSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);

        const patientId = document.getElementById('add_patient_id')?.value || '';
        const appointmentDate = document.getElementById('add_appointment_date')?.value || '';
        const timeSelect = document.getElementById('add_time_slot');
        const selectedTime = timeSelect ? timeSelect.value : '';
        const serviceId = document.getElementById('add_service_id')?.value || '';
        const teamId = document.getElementById('add_team_id')?.value || '';
        const branch = document.getElementById('add_branch')?.value || '';
        const feeInput = document.getElementById('add_appointment_fee');
        const appointmentFee = feeInput ? feeInput.value.trim() : '';

        if (!patientId) {
            showNotification('error', 'Validation Error', 'Please select a patient ID first.');
            document.getElementById('add_patient_id')?.focus();
            return;
        }
        if (!serviceId || !teamId || !branch || !appointmentDate || !selectedTime) {
            showNotification('error', 'Validation Error', 'Please complete all required fields.');
            return;
        }
        // Validate fee
        if (!appointmentFee) {
            showNotification('error', 'Validation Error', 'Please enter the appointment/consultation fee.');
            if (feeInput) feeInput.focus();
            return;
        }
        const feeNum = parseInt(appointmentFee, 10);
        const isNoFee = feeNum === 0;
        const isValidPaidFee = feeNum >= 500 && feeNum <= 9999;
        if (Number.isNaN(feeNum) || (!isNoFee && !isValidPaidFee)) {
            showNotification('error', 'Validation Error', 'Fee must be 0 (if none) or between ₱500 and ₱9,999.');
            if (feeInput) feeInput.focus();
            return;
        }

        const selectedOption = timeSelect.options[timeSelect.selectedIndex];
        if (selectedOption && selectedOption.disabled) {
            showNotification('error', 'Slot Unavailable', 'The selected time slot is unavailable. Please choose another.');
            return;
        }

        const submitBtn = document.getElementById('addAppointmentSubmitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('../controllers/addAppointment.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                // Always read as text first to handle PHP notices or stray output
                return response.text().then(text => {
                    const raw = (text || '').toString().trim();
                    // Try to extract a JSON object from the response
                    const jsonMatch = raw.match(/\{[\s\S]*\}/);
                    if (jsonMatch) {
                        try {
                            return JSON.parse(jsonMatch[0]);
                        } catch (e) {
                            // fall through to heuristics below
                        }
                    }
                    // Heuristic: if response mentions success even with extra output, treat as success
                    const lower = raw.toLowerCase();
                    const inferredSuccess = /"success"\s*:\s*true/.test(lower) || /success/i.test(lower) && !/error|warning|notice/i.test(lower);
                    if (inferredSuccess) {
                        return { success: true, message: 'Appointment added successfully.' };
                    }
                    // Otherwise, surface a helpful snippet
                    const snippet = raw.slice(0, 220);
                    return { success: false, message: snippet || 'Invalid response from server' };
                });
            })
            .then(data => {
                if (data.success === true || data.status === 'success') {
                    showNotification('success', 'Appointment Added', data.message || 'Appointment has been added successfully.');
                    closeAddAppointmentModal();
                    setTimeout(() => {
                        refreshAppointmentList()
                            .catch(error => {
                                console.error('Refresh error:', error);
                                showNotification('warning', 'Refresh Needed', 'Appointment was added, but the list could not refresh automatically.');
                            })
                            .finally(() => {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            });
                    }, 1200);
                } else {
                    showAptCenterAlert('Add Appointment Failed', data.message || 'Failed to add appointment. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Add appointment error:', error);
                showAptCenterAlert('Error', (error && error.message) ? error.message : 'An error occurred while adding the appointment. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    }
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const allRows = document.querySelectorAll(".appointment-row");
        const allCards = document.querySelectorAll(".appointment-card");
        
        allRows.forEach(row => {
            row.setAttribute("data-visible", "true");
        });
        
        allCards.forEach(card => {
            card.setAttribute("data-visible", "true");
        });

        setTimeout(() => {
            filterAppointments();
        }, 100);
        
        // Update pagination on window resize for responsive behavior
        let resizeTimeout;
        let lastWidth = window.innerWidth;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                const currentWidth = window.innerWidth;
                const wasMobile = lastWidth <= 768;
                const isMobile = currentWidth <= 768;
                
                // If switching between mobile and desktop, refresh pagination
                if (wasMobile !== isMobile) {
                    const rows = document.querySelectorAll(".appointment-row[data-visible='true']");
                    if (rows.length > 0) {
                        const visibleRows = Array.from(rows);
                        // Reset to page 1 when switching views
                        currentPage = 1;
                        updatePagination(visibleRows);
                        showPage(visibleRows, currentPage);
                    }
                } else {
                    // Just update pagination display
                    const rows = document.querySelectorAll(".appointment-row[data-visible='true']");
                    if (rows.length > 0) {
                        const visibleRows = Array.from(rows);
                        updatePagination(visibleRows);
                        showPage(visibleRows, currentPage);
                    }
                }
                lastWidth = currentWidth;
            }, 250);
        });
        
        // Complete appointment modal close
        const completeModal = document.getElementById('complete-appointment-modal');
        if (completeModal) {
            const closeBtn = completeModal.querySelector('.close');
            
            if (closeBtn) {
                closeBtn.addEventListener('click', closeCompleteAppointmentModal);
            }
            
            window.addEventListener('click', function(event) {
                if (event.target === completeModal) {
                    closeCompleteAppointmentModal();
                }
            });
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const addModal = document.getElementById('addAppointmentModal');
            const reschedModal = document.getElementById('reschedModal');
            const followUpModal = document.getElementById('followUpModal');
            const aptAlertModal = document.getElementById('aptCenterAlertModal');
            
            if (event.target === addModal) {
                closeAddAppointmentModal();
            }
            if (event.target === reschedModal) {
                closeReschedModal();
            }
            if (event.target === followUpModal) {
                closeFollowUpModal();
            }
            if (event.target === aptAlertModal) {
                closeAptCenterAlert();
            }
        });
        
        // Escape key closes centered alert
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' || event.key === 'Esc') {
                closeAptCenterAlert();
            }
        });
    });
</script>

</body>
</html>
