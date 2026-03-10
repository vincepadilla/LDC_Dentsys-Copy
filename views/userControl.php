<?php
session_start();
include_once("../database/config.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'super-admin') {
    header("Location: login.php");
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

// Pagination settings
$recordsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure page is at least 1
$offset = ($currentPage - 1) * $recordsPerPage;

// Get total count of users for pagination
$countQuery = "
    SELECT COUNT(DISTINCT ua.user_id) as total
    FROM user_account ua
    WHERE ua.role != 'super-admin'
";
$countResult = mysqli_query($con, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get users with appointment count (paginated)
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
    WHERE ua.role != 'super-admin'
    GROUP BY ua.user_id, ua.username, ua.first_name, ua.last_name, ua.email, ua.phone, ua.role, ua.created_at, ua.status, p.patient_id
    ORDER BY ua.created_at DESC
    LIMIT $recordsPerPage OFFSET $offset
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
        
        /* Layout similar to edit_content.php */
        :root {
            --primary-color: #48A6A7;
            --secondary-color: #264653;
            --accent-color: #e9c46a;
            --dark-color: #343a40;
            --text-color: #333;
            --text-light: #777;
            --white: #fff;
            --success: #2a9d8f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f3f4f6;
            height: 100vh;
            padding: 20px;
            margin: 0;
            overflow-y: auto;
        }

        .content-container {
            width: 100%;
            margin: 0 auto;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
        }

        .content-body {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        /* Compact Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
            padding: 0;
            background: white;
        }

        .page-header-content {
            flex: 1;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .page-header-icon {
            width: 56px;
            height: 56px;
            background: #48A6A7;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .page-header-icon i {
            color: white;
            font-size: 24px;
        }

        .page-header-text {
            flex: 1;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 6px 0;
            line-height: 1.2;
        }

        .page-description {
            font-size: 15px;
            color: #6b7280;
            margin: 0;
            line-height: 1.5;
        }

        .back-btn {
            background: white;
            color: #48A6A7;
            border: 2px solid #48A6A7;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .back-btn:hover {
            background: #48A6A7;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(72, 166, 167, 0.3);
        }

        .main-content {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .main-content {
            animation: pageFadeIn 0.3s ease-in-out;
        }
        @keyframes pageFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Top Controls Wrapper */
        .controls-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
            align-items: stretch;
        }

        /* Action Buttons Section */
        .action-buttons-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            height: 100%;
            justify-content: space-between;
        }

        .action-buttons-container button {
            width: 100%;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 600;
            white-space: nowrap;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .action-buttons-container button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Filter and Search Section */
        .filter-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 20px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            width: 100%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            box-sizing: border-box;
            height: 100%;
            justify-content: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }

        .filter-group label {
            font-weight: 500;
            color: #374151;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            width: 100%;
            box-sizing: border-box;
            background: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(72, 166, 167, 0.1);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            margin-top: 0;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            width: 100%;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 14px;
            table-layout: auto;
        }

        .data-table thead {
            background: #48A6A7;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .data-table th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: white;
            border-bottom: 1px solid #d1d5db;
            white-space: nowrap;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table thead tr:first-child th:first-child {
            border-top-left-radius: 8px;
        }

        .data-table thead tr:first-child th:last-child {
            border-top-right-radius: 8px;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #d1d5db;
            color: #374151;
            font-size: 14px;
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .data-table tbody tr:hover {
            background: #f9fafb;
        }

        .data-table tbody tr:last-child td {
            border-bottom: 1px solid #d1d5db;
        }

        .action-btn {
            padding: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .action-btn i {
            display: block;
            opacity: 1;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .action-btn.btn-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .action-btn.btn-warning i {
            color: #d97706;
        }

        .action-btn.btn-warning:hover {
            background: #fde68a;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
        }

        .action-btn.btn-warning:hover i {
            color: #b45309;
        }

        .action-btn.btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .action-btn.btn-danger i {
            color: #dc2626;
        }

        .action-btn.btn-danger:hover {
            background: #fecaca;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
        }

        .action-btn.btn-danger:hover i {
            color: #b91c1c;
        }

        .action-btn.btn-success {
            background: #d1fae5;
            color: #059669;
        }

        .action-btn.btn-success i {
            color: #059669;
        }

        .action-btn.btn-success:hover {
            background: #a7f3d0;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
        }

        .action-btn.btn-success:hover i {
            color: #047857;
        }

        .action-btn.btn-info {
            background: #dbeafe;
            color: #2563eb;
        }

        .action-btn.btn-info i {
            color: #2563eb;
        }

        .action-btn.btn-info:hover {
            background: #bfdbfe;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
        }

        .action-btn.btn-info:hover i {
            color: #1d4ed8;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            background: #e0e7ff;
            color: #4338ca;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* User status badges */
        .status-active {
            background: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-blocked {
            background: #fee2e2;
            color: #991b1b;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .user-row.has-appointments {
            border-left: 3px solid #48A6A7;
        }
        
        .promo-btn {
            background-color: #48A6A7;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .promo-btn:hover {
            background-color: #3d8e90;
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .pagination-info {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .pagination-btn:hover:not(.disabled) {
            background: #f9fafb;
            border-color: #48A6A7;
            color: #48A6A7;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-numbers {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .pagination-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .pagination-number:hover {
            background: #f9fafb;
            border-color: #48A6A7;
            color: #48A6A7;
        }

        .pagination-number.active {
            background: #48A6A7;
            border-color: #48A6A7;
            color: white;
            cursor: default;
        }

        .pagination-number.active:hover {
            background: #3d8e90;
            border-color: #3d8e90;
            color: white;
        }

        .pagination-ellipsis {
            padding: 0 8px;
            color: #9ca3af;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }

            .pagination-controls {
                justify-content: center;
                flex-wrap: wrap;
            }

            .pagination-numbers {
                order: -1;
                width: 100%;
                justify-content: center;
            }
        }

        .btn-info {
            background-color: #48A6A7;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-info:hover {
            background-color: #3d8e90;
        }

        .btn-success {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-success:hover {
            background-color: #059669;
        }
        
        .btn-danger {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-secondary {
            background-color: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
        }
        
        .btn-warning {                  
            background-color: #f59e0b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-warning:hover {
            background-color: #d97706;
        }

        /* Add User Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 0;
            border: none;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            animation: modalSlideIn 0.3s ease-out;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: var(--primary-color);
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .close {
            color: #6b7280;
            font-size: 24px;
            font-weight: 300;
            cursor: pointer;
            transition: all 0.2s;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            line-height: 1;
        }

        .close:hover {
            color: #1f2937;
            background: #f3f4f6;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
            font-size: 13px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea,
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
            background: white;
        }

        .form-group textarea {
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus,
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(72, 166, 167, 0.1);
        }

        /* Recipient Options Styling */
        .recipient-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
        }

        .recipient-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }

        .recipient-option:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .recipient-option input[type="radio"] {
            width: auto;
            margin: 0;
            cursor: pointer;
        }

        .recipient-option span {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #374151;
        }

        .recipient-option span i {
            font-size: 16px;
        }

        .recipient-option input[type="radio"]:checked + span {
            color: #1f2937;
            font-weight: 500;
        }

        .recipient-option:has(input[type="radio"]:checked) {
            background: #f0f9ff;
            border-color: #0ea5e9;
        }

        .recipient-option:has(input[type="radio"]:checked) span i.fa-users {
            color: #3b82f6;
        }

        .recipient-option:has(input[type="radio"]:checked) span i.fa-calendar-check {
            color: #10b981;
        }

        .recipient-option:has(input[type="radio"]:checked) span i.fa-calendar-times {
            color: #f59e0b;
        }

        /* Info Box Styling */
        .info-box {
            background: #e0f2fe;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 12px;
            margin-top: 12px;
        }

        .info-box strong {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #0369a1;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .info-box strong i {
            font-size: 14px;
        }

        .info-box p {
            color: #075985;
            margin: 0;
            font-size: 12px;
            line-height: 1.5;
        }

        /* User Details Modal Styles */
        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .user-detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .user-detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-detail-value {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        .user-detail-value.badge-value {
            display: inline-block;
            width: fit-content;
            padding: 6px 12px;
            background: #dbeafe;
            color: #1e40af;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .user-detail-value.status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .user-detail-value.status-blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .user-details-section {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .user-details-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .user-details-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-details-section-title i {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .user-details-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        /* Two-column form layout */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        .form-row-full {
            grid-column: 1 / -1;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .form-actions button {
            min-width: 120px;
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .content-body {
                padding: 16px;
            }

            .page-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .page-header-content {
                flex-direction: column;
                gap: 12px;
            }

            .page-header-icon {
                width: 48px;
                height: 48px;
            }

            .page-title {
                font-size: 24px;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
            }

            .controls-wrapper {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .action-buttons-container {
                gap: 8px;
            }

            .filter-container {
                gap: 16px;
            }

            .data-table {
                font-size: 12px;
            }

            .data-table th,
            .data-table td {
                padding: 8px 10px;
            }

            .modal-content {
                margin: 5% auto;
                width: 95%;
                max-width: 100%;
            }

            .modal-header {
                padding: 16px 20px;
            }

            .modal-body {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .recipient-options {
                gap: 6px;
            }
            
            .recipient-option {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .info-box {
                padding: 10px;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Notification Container -->
    <div class="notification-container" id="notificationContainer"></div>

    <div class="content-container">
        <div class="content-body">
            <div class="main-content">
                <!-- Compact Page Header -->
                <div class="page-header">
                    <div class="page-header-content">
                        <div class="page-header-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="page-header-text">
                            <h1 class="page-title">User Control</h1>
                            <p class="page-description">Manage user accounts, view appointment history, and send promotional communications.</p>
                        </div>
                    </div>
                    <a href="super_admin_portal.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
        
                <!-- Top Controls -->
                <div class="controls-wrapper">
                    <!-- Action Buttons -->
                    <div class="action-buttons-container">
                        <button class="btn-success" onclick="openAddUserModal()">
                            <i class="fas fa-user-plus"></i> Add New User
                        </button>
                        <button class="promo-btn" onclick="openPromotionalEmailModal()">
                            <i class="fas fa-paper-plane"></i> Send Promotional Campaign
                        </button>
                        <button class="btn-info" onclick="exportUsersList()">
                            <i class="fas fa-download"></i> Export Users List
                        </button>
                    </div>
            
                    <!-- Filter and Search -->
                    <div class="filter-container">
                        <div class="filter-group">
                            <label for="filter-user-status"><i class="fas fa-filter"></i> Filter by Status:</label>
                            <select id="filter-user-status" onchange="filterUsers()">
                                <option value="">All Users</option>
                                <option value="active">Active</option>
                                <option value="blocked">Blocked</option>
                                <option value="has_appointments">Has Appointments</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search-users"><i class="fas fa-search"></i> Search Users:</label>
                            <input type="text" id="search-users" placeholder="Search by name, email..." onkeyup="filterUsers()">
                        </div>
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
                                <th>Role</th>
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
                            
                            $roleBadgeClass = '';
                            $roleText = ucfirst($user['role']);
                            if ($user['role'] === 'admin') {
                                $roleBadgeClass = 'status-active';
                            } elseif ($user['role'] === 'dentist') {
                                $roleBadgeClass = 'status-active';
                            } else {
                                $roleBadgeClass = 'status-active';
                            }
                            
                            echo "<tr class='{$rowClass}' data-status='{$statusText}' data-search='" . strtolower($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['email']) . "' data-has-appointments='" . ($hasAppointments ? 'yes' : 'no') . "'>";
                            echo "<td style='color: #64748b; font-weight: 500;'>{$user['user_id']}</td>";
                            echo "<td><strong style='color: #1e293b; font-size: 15px;'>{$user['first_name']} {$user['last_name']}</strong><br><small style='color: #94a3b8; font-size: 13px;'>@{$user['username']}</small></td>";
                            echo "<td style='color: #475569;'>{$user['email']}</td>";
                            echo "<td style='color: #475569;'>" . ($user['phone'] ? $user['phone'] : 'N/A') . "</td>";
                            echo "<td><span class='{$roleBadgeClass}' style='text-transform: capitalize;'>{$roleText}</span></td>";
                            echo "<td><span class='badge'>" . ($user['appointment_count'] > 0 ? $user['appointment_count'] : '0') . "</span></td>";
                            echo "<td style='color: #64748b;'>{$lastAppt}</td>";
                            echo "<td><span class='{$statusClass}'>{$statusText}</span></td>";
                            echo "<td>";
                            echo "<div style='display: flex; gap: 8px; align-items: center;'>";
                            echo "<button class='action-btn btn-warning' onclick='openEditUserModal(\"{$user['user_id']}\", \"" . htmlspecialchars($user['username'], ENT_QUOTES) . "\", \"" . htmlspecialchars($user['first_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($user['last_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($user['email'], ENT_QUOTES) . "\", \"" . htmlspecialchars($user['phone'] ?? '', ENT_QUOTES) . "\", \"{$user['role']}\")' title='Edit User'>";
                            echo "<i class='fas fa-edit'></i>";
                            echo "</button>";
                            echo "<button class='action-btn btn-info' style='background: #e0e7ff; color: #4338ca;' onclick='openChangeRoleModal(\"{$user['user_id']}\", \"" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES) . "\", \"{$user['role']}\")' title='Change Role'>";
                            echo "<i class='fas fa-user-tag'></i>";
                            echo "</button>";
                            if ($user['account_status'] !== 'blocked' && $user['account_status'] !== 'Blocked') {
                                echo "<button class='action-btn btn-danger' onclick='blockUser(\"{$user['user_id']}\", \"{$user['first_name']} {$user['last_name']}\")' title='Block User'>";
                                echo "<i class='fas fa-ban'></i>";
                                echo "</button>";
                            } else {
                                echo "<button class='action-btn btn-success' onclick='unblockUser(\"{$user['user_id']}\", \"{$user['first_name']} {$user['last_name']}\")' title='Unblock User'>";
                                echo "<i class='fas fa-check-circle'></i>";
                                echo "</button>";
                            }
                            echo "<button class='action-btn btn-info' onclick='viewUserDetails(\"{$user['user_id']}\")' title='View User'>";
                            echo "<i class='fas fa-eye'></i>";
                            echo "</button>";
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' style='text-align: center; padding: 30px;'>No users found.</td></tr>";
                    }
                    ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> users
                    </div>
                    <div class="pagination-controls">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                <i class="fas fa-chevron-left"></i> Previous
                            </span>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            if ($startPage > 1) {
                                echo "<a href='?page=1' class='pagination-number'>1</a>";
                                if ($startPage > 2) {
                                    echo "<span class='pagination-ellipsis'>...</span>";
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                if ($i == $currentPage) {
                                    echo "<span class='pagination-number active'>{$i}</span>";
                                } else {
                                    echo "<a href='?page={$i}' class='pagination-number'>{$i}</a>";
                                }
                            }
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo "<span class='pagination-ellipsis'>...</span>";
                                }
                                echo "<a href='?page={$totalPages}' class='pagination-number'>{$totalPages}</a>";
                            }
                            ?>
                        </div>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                Next <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit User</h3>
            <span class="close" onclick="closeEditUserModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editUserForm" onsubmit="handleEditUserSubmit(event)">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <!-- Row 1: Username | Email -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_username">Username *</label>
                        <input type="text" id="edit_username" name="username" required placeholder="Enter username">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required placeholder="Enter email address">
                    </div>
                </div>
                
                <!-- Row 2: First Name | Last Name -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_first_name">First Name *</label>
                        <input type="text" id="edit_first_name" name="first_name" required placeholder="Enter first name">
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Last Name *</label>
                        <input type="text" id="edit_last_name" name="last_name" required placeholder="Enter last name">
                    </div>
                </div>
                
                <!-- Row 3: Phone | Role -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_phone">Phone *</label>
                        <input type="tel" id="edit_phone" name="phone" required placeholder="Enter phone number">
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role *</label>
                        <select id="edit_role" name="role" required>
                            <option value="">Select a role</option>
                            <option value="patient">Patient</option>
                            <option value="admin">Admin</option>
                            <option value="dentist">Dentist</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" class="btn-warning">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New User</h3>
            <span class="close" onclick="closeAddUserModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="addUserForm" onsubmit="handleAddUserSubmit(event)">
                <!-- Row 1: Username | Email -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_username">Username *</label>
                        <input type="text" id="new_username" name="username" required placeholder="Enter username">
                    </div>
                    <div class="form-group">
                        <label for="new_email">Email *</label>
                        <input type="email" id="new_email" name="email" required placeholder="Enter email address">
                    </div>
                </div>
                
                <!-- Row 2: First Name | Last Name -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_first_name">First Name *</label>
                        <input type="text" id="new_first_name" name="first_name" required placeholder="Enter first name">
                    </div>
                    <div class="form-group">
                        <label for="new_last_name">Last Name *</label>
                        <input type="text" id="new_last_name" name="last_name" required placeholder="Enter last name">
                    </div>
                </div>
                
                <!-- Row 3: Phone | Role -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_phone">Phone *</label>
                        <input type="tel" id="new_phone" name="phone" required placeholder="Enter phone number">
                    </div>
                    <div class="form-group">
                        <label for="new_role">Role *</label>
                        <select id="new_role" name="role" required>
                            <option value="">Select a role</option>
                            <option value="patient">Patient</option>
                            <option value="admin">Admin</option>
                            <option value="dentist">Dentist</option>
                        </select>
                    </div>
                </div>
                
                <!-- Row 4: Password | Confirm Password -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">Password *</label>
                        <input type="password" id="new_password" name="password" required placeholder="Enter password (min 6 characters)" minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="new_confirm_password">Confirm Password *</label>
                        <input type="password" id="new_confirm_password" name="confirm_password" required placeholder="Confirm password" minlength="6">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn-success">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Promotional Email Modal -->
<div id="promotionalEmailModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> Send Promotional Campaign</h3>
            <span class="close" onclick="closePromotionalEmailModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="promotionalEmailForm" onsubmit="handlePromotionalEmailSubmit(event)">
                <!-- Two-column layout: Recipients | Email Subject + Info -->
                <div class="form-row">
                    <!-- Left Column: Recipients -->
                    <div class="form-group">
                        <label><strong>Recipients:</strong></label>
                        <div class="recipient-options">
                            <label class="recipient-option">
                                <input type="radio" name="recipients" value="all_users" checked>
                                <span><i class="fas fa-users"></i> All Users</span>
                            </label>
                            <label class="recipient-option">
                                <input type="radio" name="recipients" value="with_appointments">
                                <span><i class="fas fa-calendar-check"></i> With Appointments</span>
                            </label>
                            <label class="recipient-option">
                                <input type="radio" name="recipients" value="no_appointments">
                                <span><i class="fas fa-calendar-times"></i> Without Appointments</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Right Column: Email Subject + Info -->
                    <div class="form-group">
                        <label for="promoSubject"><strong>Email Subject:</strong></label>
                        <input type="text" id="promoSubject" name="subject" required placeholder="Enter email subject..." class="form-control">
                        
                        <div class="info-box">
                            <strong><i class="fas fa-info-circle"></i> Info:</strong>
                            <p>Promotional emails will be sent to all selected recipients.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Full-width Email Message -->
                <div class="form-group">
                    <label for="promoMessage"><strong>Email Message:</strong></label>
                    <textarea id="promoMessage" name="message" required placeholder="Write your promotional message here..." class="form-control" rows="6" style="resize: vertical; min-height: 150px;"></textarea>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closePromotionalEmailModal()">Cancel</button>
                    <button type="submit" class="promo-btn">
                        <i class="fas fa-paper-plane"></i> Send Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View User Details Modal -->
<div id="viewUserModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-user"></i> User Details</h3>
            <span class="close" onclick="closeViewUserModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="userDetailsContent" style="display: none;">
                <!-- User details will be loaded here -->
            </div>
            <div id="userDetailsLoading" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #3b82f6;"></i>
                <p style="margin-top: 16px; color: #64748b;">Loading user details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div id="changeRoleModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-tag"></i> Change User Role</h3>
            <span class="close" onclick="closeChangeRoleModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="changeRoleForm" onsubmit="handleChangeRoleSubmit(event)">
                <input type="hidden" id="change_role_user_id" name="user_id">
                
                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="change_role_user_name" class="form-control" disabled>
                </div>
                
                <div class="form-group">
                    <label>Current Role</label>
                    <input type="text" id="change_role_current" class="form-control" disabled>
                </div>
                
                <div class="form-group">
                    <label for="change_role_new">New Role *</label>
                    <select id="change_role_new" name="role" required class="form-control">
                        <option value="">Select a new role</option>
                        <option value="patient">Patient</option>
                        <option value="admin">Admin</option>
                        <option value="dentist">Dentist</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeChangeRoleModal()">Cancel</button>
                    <button type="submit" class="btn-info" style="background: #4338ca;">
                        <i class="fas fa-save"></i> Update Role
                    </button>
                </div>
            </form>
        </div>
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
    

    // Change Role Modal Functions
    function openChangeRoleModal(userId, userName, currentRole) {
        document.getElementById('change_role_user_id').value = userId;
        document.getElementById('change_role_user_name').value = userName;
        document.getElementById('change_role_current').value = currentRole.charAt(0).toUpperCase() + currentRole.slice(1);
        document.getElementById('change_role_new').value = currentRole.toLowerCase();
        document.getElementById('changeRoleModal').style.display = 'block';
    }

    function closeChangeRoleModal() {
        document.getElementById('changeRoleModal').style.display = 'none';
        document.getElementById('changeRoleForm').reset();
    }

    function handleChangeRoleSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        
        const requestData = {
            action: 'change_user_role',
            user_id: formData.get('user_id'),
            role: formData.get('role')
        };
        
        fetch('../controllers/manage_user_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (data.success) {
                showNotification('success', 'Role Updated', data.message);
                setTimeout(() => {
                    closeChangeRoleModal();
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to update role. Please try again.');
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while updating role.');
        });
    }

    // Edit User Modal Functions
    function openEditUserModal(userId, username, firstName, lastName, email, phone, role) {
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_first_name').value = firstName;
        document.getElementById('edit_last_name').value = lastName;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_phone').value = phone || '';
        document.getElementById('edit_role').value = role;
        document.getElementById('editUserModal').style.display = 'block';
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
        document.getElementById('editUserForm').reset();
    }

    function handleEditUserSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        
        const requestData = {
            action: 'edit_user',
            user_id: formData.get('user_id'),
            username: formData.get('username'),
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            role: formData.get('role')
        };
        
        fetch('../controllers/manage_user_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (data.success) {
                showNotification('success', 'User Updated', `User "${requestData.username}" has been updated successfully with role "${requestData.role}".`);
                setTimeout(() => {
                    closeEditUserModal();
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to update user. Please try again.');
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while updating user.');
        });
    }

    // Add User Modal Functions
    function openAddUserModal() {
        document.getElementById('addUserModal').style.display = 'block';
    }

    function closeAddUserModal() {
        document.getElementById('addUserModal').style.display = 'none';
        document.getElementById('addUserForm').reset();
    }

    function handleAddUserSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        if (password !== confirmPassword) {
            showNotification('error', 'Password Mismatch', 'Passwords do not match. Please try again.');
            return;
        }
        
        if (password.length < 6) {
            showNotification('error', 'Invalid Password', 'Password must be at least 6 characters long.');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        
        const requestData = {
            action: 'add_user',
            username: formData.get('username'),
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            role: formData.get('role'),
            password: password
        };
        
        fetch('../controllers/manage_user_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (data.success) {
                showNotification('success', 'User Added', `User "${requestData.username}" has been added successfully with role "${requestData.role}".`);
                setTimeout(() => {
                    closeAddUserModal();
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to add user. Please try again.');
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while adding user.');
        });
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
    
    // View user details
    function viewUserDetails(userId) {
        const modal = document.getElementById('viewUserModal');
        const loadingDiv = document.getElementById('userDetailsLoading');
        const contentDiv = document.getElementById('userDetailsContent');
        
        // Show modal and loading state
        modal.style.display = 'block';
        loadingDiv.style.display = 'block';
        contentDiv.style.display = 'none';
        contentDiv.innerHTML = '';
        
        // Fetch user details
        fetch('../controllers/manage_user_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_user_details',
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            loadingDiv.style.display = 'none';
            
            if (data.success && data.user) {
                const user = data.user;
                const statusClass = user.account_status.toLowerCase() === 'blocked' ? 'status-blocked' : 'status-active';
                
                contentDiv.innerHTML = `
                    <div class="user-details-section">
                        <div class="user-details-section-title">
                            <i class="fas fa-user"></i> Personal Information
                        </div>
                        <div class="user-details-grid">
                            <div class="user-detail-item">
                                <span class="user-detail-label">User ID</span>
                                <span class="user-detail-value">${user.user_id}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Username</span>
                                <span class="user-detail-value">${user.username}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">First Name</span>
                                <span class="user-detail-value">${user.first_name}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Last Name</span>
                                <span class="user-detail-value">${user.last_name}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Email</span>
                                <span class="user-detail-value">${user.email}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Phone</span>
                                <span class="user-detail-value">${user.phone}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-details-section">
                        <div class="user-details-section-title">
                            <i class="fas fa-shield-alt"></i> Account Information
                        </div>
                        <div class="user-details-grid">
                            <div class="user-detail-item">
                                <span class="user-detail-label">Role</span>
                                <span class="user-detail-value badge-value">${user.role}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Account Status</span>
                                <span class="user-detail-value ${statusClass}">${user.account_status}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Patient ID</span>
                                <span class="user-detail-value">${user.patient_id}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Account Created</span>
                                <span class="user-detail-value">${user.created_at}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Last Login</span>
                                <span class="user-detail-value">${user.last_login}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-details-section">
                        <div class="user-details-section-title">
                            <i class="fas fa-calendar"></i> Appointment Information
                        </div>
                        <div class="user-details-grid">
                            <div class="user-detail-item">
                                <span class="user-detail-label">Total Appointments</span>
                                <span class="user-detail-value badge-value">${user.appointment_count}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">First Appointment</span>
                                <span class="user-detail-value">${user.first_appointment_date}</span>
                            </div>
                            <div class="user-detail-item">
                                <span class="user-detail-label">Last Appointment</span>
                                <span class="user-detail-value">${user.last_appointment_date}</span>
                            </div>
                        </div>
                    </div>
                `;
                contentDiv.style.display = 'block';
            } else {
                contentDiv.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i>
                        <p style="color: #64748b; font-size: 16px;">${data.message || 'Failed to load user details'}</p>
                    </div>
                `;
                contentDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            loadingDiv.style.display = 'none';
            contentDiv.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i>
                    <p style="color: #64748b; font-size: 16px;">An error occurred while loading user details.</p>
                </div>
            `;
            contentDiv.style.display = 'block';
        });
    }
    
    function closeViewUserModal() {
        document.getElementById('viewUserModal').style.display = 'none';
        document.getElementById('userDetailsContent').innerHTML = '';
        document.getElementById('userDetailsContent').style.display = 'none';
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
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const promoModal = document.getElementById('promotionalEmailModal');
        const addUserModal = document.getElementById('addUserModal');
        const editUserModal = document.getElementById('editUserModal');
        const viewUserModal = document.getElementById('viewUserModal');
        const changeRoleModal = document.getElementById('changeRoleModal');
        
        if (event.target === promoModal) {
            closePromotionalEmailModal();
        }
        if (event.target === addUserModal) {
            closeAddUserModal();
        }
        if (event.target === editUserModal) {
            closeEditUserModal();
        }
        if (event.target === viewUserModal) {
            closeViewUserModal();
        }
        if (event.target === changeRoleModal) {
            closeChangeRoleModal();
        }
    });

</script>

</body>
</html>

