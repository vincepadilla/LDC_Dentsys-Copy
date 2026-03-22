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


$archivedAppointmentsSql = "SELECT a.appointment_id,
                                   CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) AS patient_name,
                                   COALESCE(s.sub_service, s.service_category, 'N/A') AS service,
                                   a.appointment_date,
                                   a.appointment_time,
                                   a.status,
                                   a.archived_at
                            FROM appointments a
                            LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                            LEFT JOIN services s ON a.service_id = s.service_id
                            WHERE COALESCE(a.is_archived, 0) = 1 OR LOWER(COALESCE(a.status, '')) = 'archived'
                            ORDER BY a.archived_at DESC, a.appointment_date DESC";
$archivedAppointmentsResult = mysqli_query($con, $archivedAppointmentsSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Appointments - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="../admin/treatmenthistoryDesign.css">
    <style>
        .action-btn {
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .action-btn:hover {
            background: #b91c1c;
        }
        .confirm-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .confirm-modal-content {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .confirm-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }
        .cancel-btn {
            background: #e5e7eb;
            color: #111827;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
        }
        .confirm-delete-btn {
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fas fa-archive"></i> ARCHIVED APPOINTMENTS</h2>

        <div class="table-responsive">
            <table id="archived-appointments-table">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Patient Name</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Archived Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($archivedAppointmentsResult && mysqli_num_rows($archivedAppointmentsResult) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($archivedAppointmentsResult)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['appointment_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['service'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    echo !empty($row['appointment_date'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($row['appointment_date'])))
                                        : 'N/A';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['appointment_time'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['status'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    echo !empty($row['archived_at'])
                                        ? htmlspecialchars(date('M j, Y g:i A', strtotime($row['archived_at'])))
                                        : 'N/A';
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <i class="fas fa-calendar-times fa-2x"></i>
                                <p>No archived appointments found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-card-view">
            <?php if ($archivedAppointmentsResult && mysqli_num_rows($archivedAppointmentsResult) > 0): ?>
                <?php mysqli_data_seek($archivedAppointmentsResult, 0); ?>
                <?php while ($row = mysqli_fetch_assoc($archivedAppointmentsResult)): ?>
                    <div class="patient-card">
                        <div class="patient-card-header">
                            <div>
                                <div class="patient-card-id">Appointment #<?php echo htmlspecialchars($row['appointment_id'] ?? 'N/A'); ?></div>
                                <div class="patient-card-name"><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="patient-card-body">
                            <div class="patient-card-field">
                                <div class="patient-card-label">Service</div>
                                <div class="patient-card-value"><?php echo htmlspecialchars($row['service'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="patient-card-field">
                                <div class="patient-card-label">Date</div>
                                <div class="patient-card-value">
                                    <?php
                                    echo !empty($row['appointment_date'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($row['appointment_date'])))
                                        : 'N/A';
                                    ?>
                                </div>
                            </div>
                            <div class="patient-card-field">
                                <div class="patient-card-label">Time</div>
                                <div class="patient-card-value"><?php echo htmlspecialchars($row['appointment_time'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="patient-card-field">
                                <div class="patient-card-label">Status</div>
                                <div class="patient-card-value"><?php echo htmlspecialchars($row['status'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="patient-card-field">
                                <div class="patient-card-label">Archived Date</div>
                                <div class="patient-card-value">
                                    <?php
                                    echo !empty($row['archived_at'])
                                        ? htmlspecialchars(date('M j, Y g:i A', strtotime($row['archived_at'])))
                                        : 'N/A';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-calendar-times fa-2x"></i>
                    <p>No archived appointments found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
