<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

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
$totalRecords = $walkinResult ? mysqli_num_rows($walkinResult) : 0;
$lastUpdated = date('M d, Y h:i A');

// Load services for dependent dropdowns (service_category and sub_service)
$categoriesQuery = "SELECT DISTINCT service_category 
					FROM services 
					WHERE service_category IS NOT NULL AND service_category <> '' 
					ORDER BY service_category";
$categoriesResult = mysqli_query($con, $categoriesQuery);
$serviceCategories = [];
while ($row = $categoriesResult && mysqli_num_rows($categoriesResult) ? mysqli_fetch_assoc($categoriesResult) : null) {
	$serviceCategories[] = $row['service_category'];
}

// Build mapping: category => [sub_service values], include category name if sub_service is empty
$servicesMap = [];
$servicesSql = "SELECT service_category, COALESCE(NULLIF(TRIM(sub_service),''), service_category) AS sub_service
				FROM services
				WHERE service_category IS NOT NULL AND service_category <> ''
				ORDER BY service_category, sub_service";
$servicesRes = mysqli_query($con, $servicesSql);
if ($servicesRes && mysqli_num_rows($servicesRes) > 0) {
	while ($s = mysqli_fetch_assoc($servicesRes)) {
		$cat = $s['service_category'];
		$sub = $s['sub_service'];
		if (!isset($servicesMap[$cat])) $servicesMap[$cat] = [];
		// avoid duplicates
		if (!in_array($sub, $servicesMap[$cat], true)) {
			$servicesMap[$cat][] = $sub;
		}
	}
}
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
    <link rel="stylesheet" href="walkinrecordsDesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg-page: #f3f4f6;
            --bg-surface: #ffffff;
            --border-soft: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --accent: #2563eb;
            --accent-soft: #eff6ff;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
        }

        .main-content {
            padding: 24px 16px 32px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.2s ease;
        }

        .container {
            max-width: none;
            margin: 0;
            padding-right: 16px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            color: #4b5563;
            text-decoration: none;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .back-button i {
            font-size: 12px;
        }

        .back-button:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #111827;
            box-shadow: 0 1px 3px rgba(15,23,42,0.12);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin: 12px 0 20px;
        }

        .page-title-group h2 {
            margin: 0 0 4px;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--text-main);
        }

        .page-title-group p {
            margin: 0;
            font-size: 13px;
            color: var(--text-muted);
        }

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

        /* Table & Layout Styles */
        .table-responsive {
            overflow-x: auto;
            background: var(--bg-surface);
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            margin-top: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #ffffff;
            background: linear-gradient(135deg, #48A6A7 0%, #2a9d8f 100%);
            border-bottom: none;
            border: none;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: none;
            transition: background-color 0.2s;
        }

        /* Zebra striping: odd = light gray, even = white */
        tbody tr:nth-child(odd) {
            background-color: #f3f4f6;
        }

        tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        tbody tr:hover {
            background-color: #e5e7eb;
        }

        tbody td {
            padding: 12px 16px;
            color: #1F2937;
            font-size: 14px;
            border: none;
        }

        /* Status pill */
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

        /* Action buttons - compact icon buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #4b5563;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
        }

        .btn-icon:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #111827;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.12);
        }

        .btn-icon:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-add-appointment.btn-icon {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .btn-add-appointment.btn-icon:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .btn-complete.btn-icon {
            border-color: #a7f3d0;
            background: #ecfdf5;
            color: #047857;
        }

        .btn-complete.btn-icon:hover {
            background: #d1fae5;
            border-color: #6ee7b7;
            color: #047857;
        }

        .btn-complete.btn-icon:disabled {
            background: #f3f4f6;
            border-color: #e5e7eb;
            color: #9ca3af;
        }

        .btn-icon-label {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
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
            background: var(--bg-surface);
            padding: 16px 18px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            border: 1px solid var(--border-soft);
            margin-bottom: 16px;
        }

        .filter-row {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            flex: 1;
            min-width: 220px;
        }

        .filter-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
            font-size: 13px;
        }

        .filter-input,
        .filter-select {
            width: 100%;
            padding: 9px 14px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            background: #f9fafb;
            font-family: inherit;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            background: #ffffff;
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

        .filter-select {
            cursor: pointer;
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

            .walkin-card-actions .btn-complete,
            .walkin-card-actions .btn-add-appointment {
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

            .filter-row {
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
                padding: 14px;
            }

            .filter-group {
                width: 100%;
            }

            .filter-input,
            .filter-select {
                font-size: 14px;
                padding: 9px 14px;
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

        /* Table footer */
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
            padding: 10px 4px 0;
            margin-top: 8px;
        }

        .table-footer-left {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .table-footer-right {
            text-align: right;
        }

        .table-footer-label {
            font-weight: 500;
            color: #4b5563;
        }

        @media (max-width: 640px) {
            .table-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .table-footer-right {
                text-align: left;
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

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 900;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Complete Walk-in modal (based on appointment.php Add Appointment modal layout) */
        #complete-walkin-modal.treatment-modal {
            position: fixed;
            inset: 0;
            z-index: 1100;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            justify-content: center;
            align-items: center;
            padding: 18px;
        }

        #complete-walkin-modal .treatment-modal-content {
            width: min(1120px, 96vw);
            max-height: 94vh;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.22);
            overflow: hidden;
        }

        #complete-walkin-modal .modal-card {
            display: flex;
            flex-direction: column;
            max-height: 94vh;
        }

        #complete-walkin-modal .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }

        #complete-walkin-modal .modal-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: transparent !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: 0 !important;
        }

        #complete-walkin-modal .complete-walkin-close {
            position: static;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            color: #9ca3af;
            background: rgba(0,0,0,0.04);
            transition: background 0.2s ease, color 0.2s ease;
        }

        #complete-walkin-modal .complete-walkin-close:hover {
            background: rgba(0,0,0,0.08);
            color: #6b7280;
        }

        #complete-walkin-modal .treatment-body {
            padding: 20px 24px;
            overflow: auto;
        }

        #complete-walkin-modal .walkin-complete-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        #complete-walkin-modal .walkin-complete-panel {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 12px;
            padding: 18px;
        }

        #complete-walkin-modal .walkin-complete-panel-title {
            margin: 0 0 14px;
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }

        #complete-walkin-modal .appointment-info-grid {
            display: grid;
            gap: 16px;
        }

        #complete-walkin-modal .treatment-group.form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #complete-walkin-modal input[type="text"],
        #complete-walkin-modal input[type="number"],
        #complete-walkin-modal textarea {
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 15px;
            line-height: 1.4;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #111827;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        #complete-walkin-modal input[readonly] {
            background: #f3f4f6;
            color: #6b7280;
        }

        #complete-walkin-modal textarea {
            min-height: 132px;
            resize: vertical;
        }

        #complete-walkin-modal #walkin_prescription_given {
            min-height: 150px;
        }

        #complete-walkin-modal #walkin_treatment_notes {
            min-height: 140px;
        }

        #complete-walkin-modal .form-help {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #6b7280;
        }

        #complete-walkin-modal .modal-footer {
            padding: 18px 24px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
        }

        #complete-walkin-modal .modal-actions {
            margin-top: 0;
            padding-top: 0;
            border-top: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            gap: 12px;
        }

        #complete-walkin-modal .modal-actions .btn {
            height: 44px;
            width: 100%;
            min-width: 0;
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            line-height: 1;
        }

        @media (min-width: 640px) {
            #complete-walkin-modal .modal-actions {
                flex-direction: row;
                justify-content: flex-end;
                align-items: center;
                gap: 12px;
            }
            #complete-walkin-modal .modal-actions .btn {
                width: auto;
                min-width: 160px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            #complete-walkin-modal .treatment-modal-content {
                width: 100%;
                max-height: 95vh;
            }
            #complete-walkin-modal .modal-header,
            #complete-walkin-modal .treatment-body,
            #complete-walkin-modal .modal-footer {
                padding-left: 16px;
                padding-right: 16px;
            }
            #complete-walkin-modal .walkin-complete-layout {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            #complete-walkin-modal .modal-actions {
                gap: 10px;
            }
        }

        .modal-panel {
            background: #fff;
            width: min(900px, 95%);
            max-height: 95vh;
            border-radius: 18px;
            padding: 35px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-close {
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

        .modal-close:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        .modal-heading {
            margin-bottom: 28px;
        }

        .modal-heading h3 {
            margin: 8px 0;
            font-size: 22px;
            color: #111827;
        }

        .modal-heading p {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
        }

        .modal-badge {
            display: inline-flex;
            padding: 6px 12px;
            background: #ecfeff;
            color: #036466;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .modal-badge.accent {
            background: #e0f2fe;
            color: #0f172a;
        }

        .modal-form .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
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

        .form-label .required {
            color: #EF4444;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            font-size: 15px;
            background: #f8fafc;
            transition: border 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: #48a6a7;
            box-shadow: 0 0 0 2px rgba(72, 166, 167, 0.15);
            outline: none;
            background: #fff;
        }

        .form-control::placeholder {
            color: #9ca3af;
            opacity: 1;
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23374151' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        .form-control.is-valid {
            border-color: #10B981;
            background: #f0fdf4;
        }

        .form-control.is-invalid {
            border-color: #EF4444;
            background: #fef2f2;
        }

        .modal-actions {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-wide {
            flex: 0 1 auto;
            min-width: 180px;
        }

        .btn-success {
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

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-link {
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

        .btn-link:hover {
            background: #e5e7eb;
            border-color: #4b5563;
            color: #111827;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .modal-panel {
                width: calc(100% - 20px);
                padding: 20px;
                max-height: 90vh;
            }

            .modal-form .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .form-group.full-width {
                grid-column: 1;
            }

            .modal-actions {
                flex-direction: column-reverse;
                gap: 10px;
            }

            .btn-wide,
            .btn-link {
                width: 100%;
                min-width: auto;
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
            <i class="fas fa-arrow-left"></i>
            <span>Back to Admin</span>
        </a>

        <div class="page-header">
            <div class="page-title-group">
                <h2>Walk-In Records</h2>
                <p>Manage walk-in patient appointments and mark them as completed.</p>
            </div>
        </div>
        
        <!-- Filter Container -->
        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search-walkin" class="filter-label">
                        <i class="fas fa-search"></i> Search
                    </label>
                <input type="text" 
                       id="search-walkin" 
                       placeholder="Search by Walk-in ID or Patient Name..." 
                       onkeyup="filterWalkinRecords()"
                       class="filter-input">
                </div>
                
                <div class="filter-group">
                    <label for="filter-branch" class="filter-label">
                        <i class="fas fa-building"></i> Branch
                    </label>
                    <select id="filter-branch" 
                            onchange="filterWalkinRecords()"
                            class="filter-select">
                        <option value="">All Branches</option>
                        <option value="Comembo Branch">Comembo Branch</option>
                        <option value="Taytay Rizal Branch">Taytay Rizal Branch</option>
                    </select>
                </div>
                
                <div class="filter-group" style="flex: 0 0 auto;">
                    <label class="filter-label" style="visibility: hidden;">Add Walk-in</label>
                    <button id="add-walkin-btn" type="button"
                            style="
                                display: inline-flex;
                                align-items: center;
                                gap: 8px;
                                padding: 9px 14px;
                                border-radius: 10px;
                                border: 1px solid #bfdbfe;
                                background: #eff6ff;
                                color: #1d4ed8;
                                font-weight: 600;
                                font-size: 14px;
                                line-height: 1;
                                cursor: pointer;
                                transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
                                height: 38px;
                            ">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Walk-in</span>
                    </button>
                </div>
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
                                    <button class="btn-complete btn-icon" 
                                            onclick="markAsCompleted('<?php echo htmlspecialchars($row['walkin_id']); ?>', this)"
                                            <?php echo $isCompleted ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check-circle"></i>
                                        <span class="btn-icon-label">
                                            <?php echo $isCompleted ? 'Completed' : 'Mark as Completed'; ?>
                                        </span>
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
        <div class="table-footer">
            <div class="table-footer-left">
                <span class="table-footer-label">Showing</span>
                <span id="records-visible-count"><?php echo (int)$totalRecords; ?></span>
                <span>of</span>
                <span id="records-total-count"><?php echo (int)$totalRecords; ?></span>
                <span>records</span>
            </div>
            <div class="table-footer-right">
                <span class="table-footer-label">Last updated:</span>
                <span id="records-last-updated"><?php echo htmlspecialchars($lastUpdated); ?></span>
            </div>
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
                        <button class="btn-complete btn-icon" 
                                onclick="markAsCompleted('<?php echo htmlspecialchars($row['walkin_id']); ?>', this)"
                                <?php echo $isCompleted ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle"></i>
                            <span class="btn-icon-label">
                                <?php echo $isCompleted ? 'Completed' : 'Mark as Completed'; ?>
                            </span>
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
<div id="complete-walkin-modal" class="modal treatment-modal" style="display: none;">
    <div class="modal-content treatment-modal-content">
        <div class="modal-card">
            <div class="modal-header">
                <h3>
                    <i class="fa-solid fa-check-to-slot"></i>
                    <span>Complete Walk-in Appointment</span>
                </h3>
                <span class="close complete-walkin-close"
                      onclick="closeCompleteWalkinModal()"
                      aria-label="Close complete walk-in appointment modal">&times;</span>
            </div>

            <div class="modal-body treatment-body">
                <form id="walkinTreatmentForm" onsubmit="handleWalkinTreatmentSubmit(event)">
                    <input type="hidden" id="walkin_treatment_patient_id" name="patient_id">
                    <input type="hidden" id="walkin_treatment_walkin_id" name="walkin_id">

                    <div class="walkin-complete-layout">
                        <div class="walkin-complete-panel">
                            <h4 class="walkin-complete-panel-title">Patient and Prescription</h4>
                            <div class="appointment-info-grid">
                                <div class="treatment-group form-group">
                                    <label for="walkin_patient_id">
                                        <i class="fas fa-id-card"></i> Patient ID
                                    </label>
                                    <input type="text" id="walkin_patient_id" value="" readonly>
                                    <small class="form-help">Patient ID is automatically filled</small>
                                </div>

                                <div class="treatment-group form-group">
                                    <label for="walkin_prescription_given">
                                        <i class="fas fa-pills"></i> Prescription
                                    </label>
                                    <textarea id="walkin_prescription_given" name="prescription_given" rows="5" placeholder="Enter prescribed medications and instructions" required></textarea>
                                    <small class="form-help">List all prescribed medications and dosage instructions</small>
                                </div>
                            </div>
                        </div>

                        <div class="walkin-complete-panel">
                            <h4 class="walkin-complete-panel-title">Treatment Details</h4>
                            <div class="appointment-info-grid">
                                <div class="treatment-group form-group">
                                    <label for="walkin_treatment_type">
                                        <i class="fas fa-stethoscope"></i> Treatment
                                    </label>
                                    <input type="text" id="walkin_treatment_type" name="treatment" placeholder="Enter treatment type (e.g., Cleaning, Extraction, Filling)" required>
                                    <small class="form-help">Specify the treatment provided to the patient</small>
                                </div>

                                <div class="treatment-group form-group">
                                    <label for="walkin_treatment_cost">
                                        <i class="fas fa-peso-sign"></i> Treatment Cost (₱)
                                    </label>
                                    <input type="number" id="walkin_treatment_cost" name="treatment_cost" step="0.01" min="0" placeholder="0.00" required>
                                    <small class="form-help">Enter the total cost of the treatment</small>
                                </div>

                                <div class="treatment-group form-group">
                                    <label for="walkin_treatment_notes">
                                        <i class="fas fa-notes-medical"></i> Treatment Notes
                                    </label>
                                    <textarea id="walkin_treatment_notes" name="treatment_notes" rows="5" placeholder="Enter detailed notes about the treatment, patient condition, and recommendations" required></textarea>
                                    <small class="form-help">Add any additional notes or observations about the treatment</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="treatment-footer modal-footer">
                <div class="modal-actions">
                    <button type="button" onclick="closeCompleteWalkinModal()" class="btn btn-link" id="cancelCompleteWalkin">
                        Cancel
                    </button>
                    <button type="submit" form="walkinTreatmentForm" class="btn btn-success btn-wide" id="completeWalkinSubmitBtn">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Walk-in Modal -->
<div id="add-walkin-modal" class="modal-overlay" style="display:none;">
	<div class="modal-panel">
		<button class="modal-close" onclick="closeAddWalkinModal()" aria-label="Close add walk-in dialog">
			<i class="fas fa-times"></i>
		</button>
		<div class="modal-heading">
			<span class="modal-badge accent">Add walk-in</span>
			<h3>Create walk-in record</h3>
			<p>Enter patient details and select the requested service. Fields marked with * are required.</p>
		</div>
		<form id="addWalkinForm" class="modal-form" onsubmit="handleAddWalkinSubmit(event)">
			<!-- Section 1: Create User / Patient Information -->
			<div class="form-grid" style="margin-bottom:18px;">
				<div class="form-group">
					<label class="form-label" for="wi_first_name">First Name <span class="required">*</span></label>
					<input type="text" id="wi_first_name" name="first_name" class="form-control" placeholder="Enter first name" required>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_last_name">Last Name <span class="required">*</span></label>
					<input type="text" id="wi_last_name" name="last_name" class="form-control" placeholder="Enter last name" required>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_email">Email <span class="required">*</span></label>
					<input type="email" id="wi_email" name="email" class="form-control" placeholder="Enter email address" required>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_phone">Phone <span class="required">*</span></label>
					<input type="text" id="wi_phone" name="phone" class="form-control" placeholder="11-digit phone number" maxlength="11" pattern="\d{11}" required>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_birthdate">Birthdate <span class="required">*</span></label>
					<input type="date" id="wi_birthdate" name="birthdate" class="form-control" value="2000-01-01" required>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_gender">Gender <span class="required">*</span></label>
					<select id="wi_gender" name="gender" class="form-control" required>
						<option value="Male">Male</option>
						<option value="Female">Female</option>
					</select>
				</div>
				<div class="form-group full-width">
					<label class="form-label" for="wi_address">Address <span class="required">*</span></label>
					<input type="text" id="wi_address" name="address" class="form-control" placeholder="Enter full address" required>
				</div>
			</div>

			<!-- Section 2: Walk-in Service Information -->
			<div class="form-grid">
				<div class="form-group">
					<label class="form-label" for="wi_service">Service <span class="required">*</span></label>
					<select id="wi_service" name="service" class="form-control" required>
						<option value="" disabled selected>Select service</option>
						<?php foreach ($serviceCategories as $cat): ?>
							<option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_sub_service">Sub-Service <span class="required">*</span></label>
					<select id="wi_sub_service" name="sub_service" class="form-control" required disabled>
						<option value="" disabled selected>Select sub-service</option>
					</select>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_branch">Branch <span class="required">*</span></label>
					<select id="wi_branch" name="branch" class="form-control" required>
						<option value="" disabled selected>Select branch</option>
						<option value="Comembo Branch">Comembo Branch</option>
						<option value="Taytay Rizal Branch">Taytay Rizal Branch</option>
					</select>
				</div>
				<div class="form-group">
					<label class="form-label" for="wi_status">Status <span class="required">*</span></label>
					<select id="wi_status" class="form-control" disabled>
						<option value="Walk-in" selected>Walk-in</option>
					</select>
					<input type="hidden" name="status" value="Walk-in">
				</div>
				<!-- Notes/date fields not required by current logic; can be added later if needed -->
			</div>

			<div class="modal-actions">
				<button type="button" class="btn btn-link" onclick="closeAddWalkinModal()">Cancel</button>
				<button type="submit" class="btn btn-success btn-wide">
					<i class="fas fa-user-plus"></i> Add Walk-in
				</button>
			</div>
		</form>
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

        modal.style.display = 'flex';
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
        // The submit button is outside the form (uses form="walkinTreatmentForm"), so query the DOM, not the form.
        const submitBtn = document.getElementById('completeWalkinSubmitBtn') || document.querySelector('button[form="walkinTreatmentForm"][type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }
        
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
                    // Redirect to treatment history page after successful save
                    window.location.href = 'treatmenthistory.php';
                }, 2000);
            } else {
                const errorMsg = data.message || 'Failed to save treatment. Please try again.';
                showNotification('error', 'Error', errorMsg);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showNotification('error', 'Error', 'An error occurred while saving treatment: ' + error.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
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
        const visibleCountEl = document.getElementById('records-visible-count');
        const totalCountEl = document.getElementById('records-total-count');
        
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

        // Initialize footer counts on load
        const initialVisibleCountEl = document.getElementById('records-visible-count');
        const totalCountEl = document.getElementById('records-total-count');
        if (initialVisibleCountEl && totalCountEl) {
            // On initial load, visible count equals total records
            initialVisibleCountEl.textContent = totalCountEl.textContent;
        }
    });
</script>
<script>
	// ======== Add Walk-in Modal & Service Dropdown Logic ========
	const SERVICES_MAP = <?php echo json_encode($servicesMap); ?>;

	// Open modal
	document.getElementById('add-walkin-btn')?.addEventListener('click', function() {
		openAddWalkinModal();
	});

	function openAddWalkinModal() {
		const modal = document.getElementById('add-walkin-modal');
		if (!modal) return;
		const form = document.getElementById('addWalkinForm');
		if (form) {
			form.reset();
			// Ensure sub-service is disabled until service selected
			const sub = document.getElementById('wi_sub_service');
			if (sub) {
				sub.innerHTML = '<option value="" disabled selected>Select sub-service</option>';
				sub.disabled = true;
			}
		}
		modal.style.display = 'flex';
		setTimeout(() => {
			document.getElementById('wi_first_name')?.focus();
		}, 100);
		// Click outside to close
		window.addEventListener('click', handleAddWalkinBackdrop);
	}

	function closeAddWalkinModal() {
		const modal = document.getElementById('add-walkin-modal');
		if (!modal) return;
		modal.style.display = 'none';
		window.removeEventListener('click', handleAddWalkinBackdrop);
	}

	function handleAddWalkinBackdrop(e) {
		const modal = document.getElementById('add-walkin-modal');
		if (e.target === modal) {
			closeAddWalkinModal();
		}
	}

	// Populate dependent sub_service when service changes
	document.getElementById('wi_service')?.addEventListener('change', function() {
		const category = this.value || '';
		const subEl = document.getElementById('wi_sub_service');
		if (!subEl) return;
		subEl.innerHTML = '<option value="" disabled selected>Select sub-service</option>';
		if (category && SERVICES_MAP && Array.isArray(SERVICES_MAP[category])) {
			SERVICES_MAP[category].forEach(function(sub) {
				const opt = document.createElement('option');
				opt.value = sub;
				opt.textContent = sub;
				subEl.appendChild(opt);
			});
			subEl.disabled = false;
		} else {
			// No subs found; keep disabled
			subEl.disabled = true;
		}
	});

	// Enforce 11-digit numeric phone input
	(function enforcePhoneDigits() {
		const phoneEl = document.getElementById('wi_phone');
		if (!phoneEl) return;
		phoneEl.addEventListener('input', function() {
			this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
		});
	})();

	// Stub submit handler (UI only; backend integration can be added later)
	function handleAddWalkinSubmit(event) {
		event.preventDefault();
		// Client-side phone validation
		const phoneEl = document.getElementById('wi_phone');
		if (phoneEl && !/^\d{11}$/.test(phoneEl.value)) {
			showNotification('error', 'Validation', 'Phone number must be exactly 11 digits.');
			phoneEl.classList.add('is-invalid');
			phoneEl.focus();
			return;
		}
		const form = event.target;
		const submitBtn = form.querySelector('button[type="submit"]');
		const original = submitBtn ? submitBtn.innerHTML : '';
		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
		}

		const formData = new FormData(form);
		fetch('../controllers/createWalkinAdmin.php', {
			method: 'POST',
			body: formData
		})
		.then(async (response) => {
			const text = await response.text().catch(() => '');
			let data = null;
			try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
			if (!response.ok) {
				const msg = data && (data.message || data.error) ? (data.message || data.error) : (text || 'Request failed.');
				throw new Error(msg);
			}
			if (!data) throw new Error(text || 'Unexpected server response.');
			return data;
		})
		.then(data => {
			if (data.success) {
				showNotification('success', 'Walk-in Added', data.message || 'Walk-in record created.');
				closeAddWalkinModal();
				// Simple approach: reload to reflect new record in both table and cards
				setTimeout(() => { window.location.reload(); }, 1000);
			} else {
				const msg = data.message || 'Failed to create walk-in record.';
				showNotification('error', 'Validation', msg);
				// Field-level highlights
				if (data.field === 'email') {
					document.getElementById('wi_email')?.classList.add('is-invalid');
					document.getElementById('wi_email')?.focus();
				} else if (data.field === 'phone') {
					document.getElementById('wi_phone')?.classList.add('is-invalid');
					document.getElementById('wi_phone')?.focus();
				}
			}
		})
		.catch(err => {
			console.error('Create walk-in error:', err);
			showNotification('error', 'Error', err && err.message ? err.message : 'An error occurred while creating walk-in record.');
		})
		.finally(() => {
			if (submitBtn) {
				submitBtn.disabled = false;
				submitBtn.innerHTML = original;
			}
		});
	}
</script>
</body>
</html>
