<?php
session_start();
include_once("../database/config.php");

// Authentication and authorization check
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    header("Location: admin_verify.php");
    exit();
}

// Fetch data for various sections
require_once '../controllers/AdminDataController.php';
$dataController = new AdminDataController($con);

// Dashboard data
$dashboardStats = $dataController->getDashboardStats();
$todayAppointments = $dataController->getTodayAppointments();
$upcomingAppointments = $dataController->getUpcomingAppointments();
$appointmentHours = $dataController->getAppointmentHours();
$appointmentCounts = $dataController->getAppointmentCounts();

// Appointments data
$appointments = $dataController->getAllAppointments();
$patientsMap = $dataController->getPatientsMap();
$services = $dataController->getServices();
$activeDentists = $dataController->getActiveDentists();

// Services data
$servicesList = $dataController->getServicesList();
$serviceCategories = $dataController->getServiceCategories();

// Patients data
$patients = $dataController->getAllPatients();

// Dentists data
$dentists = $dataController->getAllDentists();

// Payment data
$payments = $dataController->getPaymentTransactions();
$paymentMethods = $dataController->getPaymentMethods();
$paymentStatuses = $dataController->getPaymentStatuses();

// Treatment history
$treatmentHistory = $dataController->getTreatmentHistory();

// Reports data
$reportData = $dataController->getReportData();
$monthlyServiceData = $dataController->getMonthlyServiceData();
$revenueData = $dataController->getRevenueData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dental Clinic</title>

    <!-- Bootstrap 5.3.8 -->
    <link href="../libraries/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --secondary-color: #858796;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .main-content {
            margin-left: 250px;
            transition: all 0.3s;
            min-height: 100vh;
            background-color: #f8f9fc;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        .sidebar-brand {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand img {
            max-width: 150px;
            height: auto;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.8rem 1rem;
            margin: 0.2rem 0;
            border-left: 4px solid transparent;
            transition: all 0.3s;
            border-radius: 0;
        }
        
        .nav-link:hover, .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #fff;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .sidebar.collapsed .sidebar-brand span {
            display: none;
        }
        
        /* Stats Cards */
        .stat-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }
        
        .border-left-primary { border-left: 0.25rem solid var(--primary-color) !important; }
        .border-left-success { border-left: 0.25rem solid var(--success-color) !important; }
        .border-left-info { border-left: 0.25rem solid var(--info-color) !important; }
        .border-left-warning { border-left: 0.25rem solid var(--warning-color) !important; }
        .border-left-danger { border-left: 0.25rem solid var(--danger-color) !important; }
        
        /* Notification System */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        
        .notification {
            animation: slideInRight 0.3s ease-out;
            border-left: 4px solid;
            margin-bottom: 10px;
        }
        
        .notification.success { border-left-color: var(--success-color); }
        .notification.warning { border-left-color: var(--warning-color); }
        .notification.error { border-left-color: var(--danger-color); }
        .notification.info { border-left-color: var(--info-color); }
        
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
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Status badges */
        .badge-pending { background-color: var(--warning-color); }
        .badge-confirmed { background-color: var(--success-color); }
        .badge-completed { background-color: var(--info-color); }
        .badge-cancelled { background-color: var(--danger-color); }
        .badge-no-show { background-color: var(--secondary-color); }
        .badge-rescheduled { background-color: #6f42c1; }
        
        /* Action buttons */
        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            border-radius: 50%;
        }
        
        /* Table improvements */
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--dark-color);
            background-color: #f8f9fc;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        /* Modal improvements */
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #224abe);
            color: white;
        }
        
        /* Form controls */
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Schedule view */
        .schedule-view .time-slot {
            height: 60px;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .schedule-view .time-slot:hover {
            background-color: #f8f9fa;
        }
        
        .schedule-view .time-slot.available {
            background-color: #d1f7c4;
        }
        
        .schedule-view .time-slot.booked {
            background-color: #ffeaea;
            cursor: not-allowed;
        }
        
        .schedule-view .time-slot.blocked {
            background-color: #ffd8d8;
            cursor: not-allowed;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.expanded {
                margin-left: 0;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-action {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }
            
            .stat-icon {
                font-size: 2rem;
            }
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .card {
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand d-flex align-items-center justify-content-center py-4">
            <img src="../assets/images/landerologo.png" alt="Logo" class="img-fluid">
            <span class="ms-2 d-none d-lg-inline">Admin Panel</span>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="#" onclick="showSection('dashboard')">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('appointments')">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('schedules')">
                    <i class="fas fa-calendar-days"></i>
                    <span>Time Slots</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('services')">
                    <i class="fas fa-teeth"></i>
                    <span>Services</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('patients')">
                    <i class="fas fa-hospital-user"></i>
                    <span>Patients</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('treatment')">
                    <i class="fas fa-notes-medical"></i>
                    <span>History</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('dentists')">
                    <i class="fas fa-user-doctor"></i>
                    <span>Dentists & Staff</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('payments')">
                    <i class="fas fa-money-bill"></i>
                    <span>Transactions</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="showSection('reports')">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item mt-4">
                <div class="dropdown">
                    <button class="nav-link dropdown-toggle w-100 text-start d-flex align-items-center" 
                            type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i>
                        <span class="ms-2">Controls</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="clinicControl.php"><i class="fas fa-building me-2"></i> Clinic Control</a></li>
                        <li><a class="dropdown-item" href="../views/userControl.php"><i class="fas fa-users me-2"></i> User Control</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="login.php?logout=true">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm border-bottom">
            <div class="container-fluid">
                <button class="btn btn-link text-dark" id="sidebarToggle">
                    <i class="fas fa-bars fa-lg"></i>
                </button>
                
                <div class="d-flex align-items-center ms-auto">
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle d-flex align-items-center" 
                                type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <span class="d-none d-md-inline"><?php echo $_SESSION['username'] ?? 'Admin'; ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="login.php?logout=true">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Notification Container -->
        <div class="notification-container" id="notificationContainer"></div>

        <!-- Dashboard Section -->
        <section id="dashboard" class="content-section">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard Overview</h1>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="printDashboard()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Appointments
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $dashboardStats['total_appointments']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-check fa-2x text-primary stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Dentists
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $dashboardStats['active_dentists']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-md fa-2x text-success stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Services
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $dashboardStats['total_services']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-teeth fa-2x text-info stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Today's Appointments
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $dashboardStats['todays_appointments']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-day fa-2x text-warning stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's & Upcoming Appointments -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Today's Appointments (<?php echo count($todayAppointments); ?>)
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($todayAppointments)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Patient</th>
                                                    <th>Service</th>
                                                    <th>Dentist</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($todayAppointments as $appointment): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($appointment['appointment_time']); ?></td>
                                                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($appointment['service_category']); ?></td>
                                                        <td><?php echo htmlspecialchars($appointment['dentist_name']); ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo strtolower($appointment['status']); ?>">
                                                                <?php echo htmlspecialchars($appointment['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No appointments scheduled for today</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Upcoming Appointments (<?php echo count($upcomingAppointments); ?>)
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($upcomingAppointments)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcomingAppointments as $appointment): ?>
                                            <div class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($appointment['patient_name']); ?></h6>
                                                    <small><?php echo date('M j', strtotime($appointment['appointment_date'])); ?></small>
                                                </div>
                                                <p class="mb-1 text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo htmlspecialchars($appointment['appointment_time']); ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No upcoming appointments</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Appointment Time Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="appointmentSummaryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Appointments Section -->
        <section id="appointments" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-calendar-alt me-2"></i>Appointments
                    </h1>
                    <div class="btn-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                            <i class="fas fa-plus me-2"></i>Add Appointment
                        </button>
                        <button class="btn btn-outline-secondary" onclick="printAppointments()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Date Category</label>
                                <select class="form-select" id="filter-date-category" onchange="handleDateCategoryChange()">
                                    <option value="">All Dates</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="custom">Custom Date</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-none" id="custom-date-container">
                                <label class="form-label">Custom Date</label>
                                <input type="date" class="form-control" id="filter-custom-date" onchange="filterAppointments()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="filter-status" onchange="filterAppointments()">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="rescheduled">Rescheduled</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="no-show">No-Show</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-secondary w-100" onclick="resetAppointmentFilters()">
                                    <i class="fas fa-redo me-2"></i>Reset Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointments Table -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="appointmentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Dentist</th>
                                        <th>Date & Time</th>
                                        <th>Branch</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): 
                                        $statusClass = 'badge-' . strtolower($appointment['status']);
                                        $statusText = ucfirst($appointment['status']);
                                    ?>
                                        <tr data-date="<?php echo $appointment['appointment_date']; ?>"
                                            data-status="<?php echo strtolower($appointment['status']); ?>">
                                            <td><?php echo htmlspecialchars($appointment['appointment_id']); ?></td>
                                            <td>
                                                <div class="fw-medium"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                                <small class="text-muted">ID: <?php echo htmlspecialchars($appointment['patient_id']); ?></small>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($appointment['service_category']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($appointment['sub_service']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['dentist_name']); ?></td>
                                            <td>
                                                <div><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                                <small class="text-muted"><?php echo $appointment['appointment_time']; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['branch'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <?php if (strtolower($appointment['status']) === 'pending'): ?>
                                                        <button class="btn btn-success btn-sm btn-action me-1" 
                                                                onclick="confirmAppointment(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="Confirm">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-warning btn-sm btn-action me-1"
                                                                onclick="openRescheduleModal(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="Reschedule">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm btn-action me-1"
                                                                onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="Cancel">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        <button class="btn btn-secondary btn-sm btn-action"
                                                                onclick="markNoShow(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="No-Show">
                                                            <i class="fas fa-eye-slash"></i>
                                                        </button>
                                                    <?php elseif (strtolower($appointment['status']) === 'completed'): ?>
                                                        <button class="btn btn-info btn-sm btn-action me-1"
                                                                onclick="viewAppointmentDetails(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-primary btn-sm btn-action me-1"
                                                                onclick="openFollowUpModal(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="Follow Up">
                                                            <i class="fas fa-arrow-right"></i>
                                                        </button>
                                                        <button class="btn btn-secondary btn-sm btn-action"
                                                                onclick="markNoShow(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="No-Show">
                                                            <i class="fas fa-eye-slash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-warning btn-sm btn-action me-1"
                                                                onclick="openRescheduleModal(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="Reschedule">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </button>
                                                        <button class="btn btn-info btn-sm btn-action me-1"
                                                                onclick="viewAppointmentDetails(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if (strtolower($appointment['status']) !== 'cancelled' && strtolower($appointment['status']) !== 'no-show'): ?>
                                                            <button class="btn btn-success btn-sm btn-action me-1"
                                                                    onclick="markAsCompleted(<?php echo $appointment['appointment_id']; ?>)"
                                                                    title="Mark as Completed">
                                                                <i class="fas fa-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-secondary btn-sm btn-action"
                                                                onclick="markNoShow(<?php echo $appointment['appointment_id']; ?>)"
                                                                title="No-Show">
                                                            <i class="fas fa-eye-slash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section id="services" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-teeth me-2"></i>Services
                    </h1>
                    <div class="btn-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                            <i class="fas fa-plus me-2"></i>Add Service
                        </button>
                        <button class="btn btn-outline-secondary" onclick="printServices()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="filter-service-category" onchange="filterServices()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($serviceCategories as $category): ?>
                                        <option value="<?php echo htmlspecialchars(strtolower($category)); ?>">
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Services Table -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="servicesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Sub Service</th>
                                        <th>Description</th>
                                        <th>Price</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($servicesList as $service): ?>
                                        <tr data-category="<?php echo htmlspecialchars(strtolower($service['service_category'])); ?>">
                                            <td><?php echo htmlspecialchars($service['service_id']); ?></td>
                                            <td><?php echo htmlspecialchars($service['service_category']); ?></td>
                                            <td><?php echo htmlspecialchars($service['sub_service']); ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" 
                                                     title="<?php echo htmlspecialchars($service['description']); ?>">
                                                    <?php echo htmlspecialchars($service['description']); ?>
                                                </div>
                                            </td>
                                            <td class="fw-bold">₱<?php echo number_format($service['price'], 2); ?></td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <button class="btn btn-primary btn-sm btn-action me-1"
                                                            onclick="editService(<?php echo $service['service_id']; ?>)"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form action="../controllers/deleteService.php" method="POST" 
                                                          onsubmit="return confirm('Are you sure you want to delete this service?')"
                                                          class="d-inline">
                                                        <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm btn-action" title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Patients Section -->
        <section id="patients" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-hospital-user me-2"></i>Patients
                    </h1>
                    <button class="btn btn-outline-secondary" onclick="printPatients()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" id="filter-patient-gender" onchange="filterPatients()">
                                    <option value="">All Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Age Category</label>
                                <select class="form-select" id="filter-patient-age" onchange="filterPatients()">
                                    <option value="">All Ages</option>
                                    <option value="child">Child (0-12)</option>
                                    <option value="teen">Teen (13-19)</option>
                                    <option value="adult">Adult (20-59)</option>
                                    <option value="senior">Senior (60+)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="filter-patient-search" 
                                           placeholder="Search by name, ID, email..." onkeyup="filterPatients()">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearPatientSearch()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patients Table -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="patientsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Birthdate</th>
                                        <th>Gender</th>
                                        <th>Contact</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): 
                                        // Calculate age
                                        $birthdate = new DateTime($patient['birthdate']);
                                        $today = new DateTime();
                                        $age = $birthdate->diff($today)->y;
                                        
                                        // Determine age category
                                        $ageCategory = '';
                                        if ($age <= 12) $ageCategory = 'child';
                                        elseif ($age >= 13 && $age <= 19) $ageCategory = 'teen';
                                        elseif ($age >= 20 && $age <= 59) $ageCategory = 'adult';
                                        else $ageCategory = 'senior';
                                    ?>
                                        <tr data-gender="<?php echo strtolower($patient['gender']); ?>"
                                            data-age-category="<?php echo $ageCategory; ?>"
                                            data-search="<?php echo strtolower($patient['patient_id'] . ' ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' ' . $patient['email']); ?>">
                                            <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                            <td>
                                                <div class="fw-medium"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                                <small class="text-muted">Age: <?php echo $age; ?></small>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($patient['birthdate'])); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($patient['gender']); ?></span>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($patient['email']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($patient['phone']); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <button class="btn btn-primary btn-sm btn-action me-1"
                                                            onclick="editPatient(<?php echo $patient['patient_id']; ?>)"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-info btn-sm btn-action me-1"
                                                            onclick="viewPatientDetails(<?php echo $patient['patient_id']; ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm btn-action"
                                                            onclick="archivePatient(<?php echo $patient['patient_id']; ?>)"
                                                            title="Archive">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Dentists & Staff Section -->
        <section id="dentists" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-user-doctor me-2"></i>Dentists & Staff
                    </h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDentistModal">
                        <i class="fas fa-plus me-2"></i>Add Dentist/Staff
                    </button>
                </div>

                <!-- Dentists Table -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="dentistsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Specialization</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dentists as $dentist): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dentist['team_id']); ?></td>
                                            <td>
                                                <div class="fw-medium"><?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($dentist['specialization']); ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($dentist['email']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($dentist['phone']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $dentist['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($dentist['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <button class="btn btn-primary btn-sm btn-action me-1"
                                                            onclick="editDentist(<?php echo $dentist['team_id']; ?>)"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form action="../controllers/deleteStaff.php" method="POST" 
                                                          onsubmit="return confirm('Are you sure you want to delete this staff member?')"
                                                          class="d-inline">
                                                        <input type="hidden" name="team_id" value="<?php echo $dentist['team_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm btn-action" title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Treatment History Section -->
        <section id="treatment" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-notes-medical me-2"></i>Patient Treatment History
                    </h1>
                </div>

                <!-- Treatment History Table -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Treatment</th>
                                        <th>Prescription</th>
                                        <th>Cost</th>
                                        <th>Notes</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($treatmentHistory as $history): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($history['patient_id']); ?></td>
                                            <td><?php echo htmlspecialchars($history['treatment']); ?></td>
                                            <td><?php echo htmlspecialchars($history['prescription_given']); ?></td>
                                            <td class="fw-bold">₱<?php echo number_format($history['treatment_cost'], 2); ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 150px;" 
                                                     title="<?php echo htmlspecialchars($history['notes']); ?>">
                                                    <?php echo htmlspecialchars($history['notes']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <button class="btn btn-primary btn-sm btn-action"
                                                            onclick="printTreatmentHistory(<?php echo $history['patient_id']; ?>)"
                                                            title="Print">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Payment Transactions Section -->
        <section id="payments" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-money-bill me-2"></i>Payment Transactions
                    </h1>
                    <button class="btn btn-outline-secondary" onclick="printPayments()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Date Category</label>
                                <select class="form-select" id="filter-payment-date-category" onchange="handlePaymentDateCategoryChange()">
                                    <option value="">All Dates</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="custom">Custom Date</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-none" id="custom-payment-date-container">
                                <label class="form-label">Custom Date</label>
                                <input type="date" class="form-control" id="filter-payment-custom-date" onchange="filterPayments()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="filter-payment-status" onchange="filterPayments()">
                                    <option value="">All Status</option>
                                    <?php foreach ($paymentStatuses as $status): ?>
                                        <option value="<?php echo strtolower($status); ?>"><?php echo ucfirst($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Method</label>
                                <select class="form-select" id="filter-payment-method" onchange="filterPayments()">
                                    <option value="">All Methods</option>
                                    <?php foreach ($paymentMethods as $method): ?>
                                        <option value="<?php echo strtolower($method); ?>"><?php echo $method; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payments Table -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="paymentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Appointment ID</th>
                                        <th>Method</th>
                                        <th>Account Details</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr data-date="<?php echo $payment['appointment_date']; ?>"
                                            data-status="<?php echo strtolower($payment['status']); ?>"
                                            data-method="<?php echo strtolower($payment['method']); ?>">
                                            <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['appointment_id']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($payment['method']); ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?php echo htmlspecialchars($payment['account_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['account_number']); ?></small>
                                            </td>
                                            <td class="fw-bold">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($payment['status']); ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <?php 
                                                    $currentPaymentStatus = strtolower($payment['status'] ?? '');
                                                    if ($payment['proof_image']): 
                                                        $clean_path = ltrim($payment['proof_image'], '/');
                                                        $clean_path = str_replace('uploads/', '', $clean_path);
                                                        $image_path = '/uploads/' . $clean_path;
                                                    ?>
                                                        <button class="btn btn-info btn-sm btn-action me-1"
                                                                onclick="viewPaymentImage('<?php echo htmlspecialchars($image_path); ?>')"
                                                                title="View Proof">
                                                            <i class="fas fa-image"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($currentPaymentStatus !== 'paid' && $currentPaymentStatus !== 'refunded'): ?>
                                                        <button class="btn btn-success btn-sm btn-action me-1"
                                                                onclick="confirmPayment(<?php echo $payment['payment_id']; ?>)"
                                                                title="Confirm">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($currentPaymentStatus !== 'failed' && $currentPaymentStatus !== 'refunded'): ?>
                                                        <button class="btn btn-danger btn-sm btn-action"
                                                                onclick="markPaymentFailed(<?php echo $payment['payment_id']; ?>)"
                                                                title="Mark as Failed">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Reports Section -->
        <section id="reports" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
                    </h1>
                    <div class="btn-group">
                        <select class="form-select" id="reportType" onchange="filterReports()" style="width: auto;">
                            <option value="all">Show All Reports</option>
                            <option value="overview">Dashboard Overview</option>
                            <option value="service">Monthly Service Distribution</option>
                            <option value="appointments">Appointments Per Day</option>
                            <option value="financial">Revenue by Services</option>
                        </select>
                    </div>
                </div>

                <!-- Dashboard Overview Report -->
                <div id="overviewReport" class="report-section">
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-header py-3 bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-chart-pie me-2"></i>Dashboard Overview
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Stats Row -->
                                    <div class="row mb-4">
                                        <div class="col-md-3 mb-3">
                                            <div class="card border-left-primary h-100">
                                                <div class="card-body text-center">
                                                    <div class="text-primary mb-2">Total Appointments</div>
                                                    <div class="h3 font-weight-bold"><?php echo $reportData['total_appointments']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="card border-left-success h-100">
                                                <div class="card-body text-center">
                                                    <div class="text-success mb-2">Total Down Payment</div>
                                                    <div class="h3 font-weight-bold">₱<?php echo number_format($reportData['total_downpayment'], 2); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="card border-left-info h-100">
                                                <div class="card-body text-center">
                                                    <div class="text-info mb-2">Today's Appointments</div>
                                                    <div class="h3 font-weight-bold"><?php echo $reportData['todays_appointments']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="card border-left-warning h-100">
                                                <div class="card-body text-center">
                                                    <div class="text-warning mb-2">Total Revenue</div>
                                                    <div class="h3 font-weight-bold">₱<?php echo number_format($reportData['total_revenue'], 2); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Charts Row -->
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h6 class="m-0 font-weight-bold text-primary">Appointment Status</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="chart-container">
                                                        <canvas id="appointmentStatusChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h6 class="m-0 font-weight-bold text-primary">Total Downpayment by Services</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="chart-container">
                                                        <canvas id="serviceRevenueChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Additional Charts -->
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h6 class="m-0 font-weight-bold text-primary">Appointment Summary</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div id="appointmentSummary">
                                                        <?php
                                                        $statusQuery = mysqli_query($con, "SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
                                                        $appointmentStatuses = [];
                                                        while ($row = mysqli_fetch_assoc($statusQuery)) {
                                                            $appointmentStatuses[$row['status']] = $row['count'];
                                                        }
                                                        $totalAppointments = array_sum($appointmentStatuses);
                                                        $statusColors = [
                                                            'pending' => '#F59E0B',
                                                            'confirmed' => '#10B981', 
                                                            'rescheduled' => '#3B82F6',
                                                            'cancelled' => '#EF4444',
                                                            'no-show' => '#6B7280',
                                                            'completed' => '#06B6D4'
                                                        ];
                                                        
                                                        foreach ($appointmentStatuses as $status => $count) {
                                                            $color = $statusColors[strtolower($status)] ?? '#6B7280';
                                                            $percentage = $totalAppointments > 0 ? round(($count / $totalAppointments) * 100, 1) : 0;
                                                            echo "
                                                            <div class='d-flex justify-content-between align-items-center mb-2 p-2 border-bottom'>
                                                                <div class='d-flex align-items-center'>
                                                                    <div class='rounded-circle me-2' style='width: 12px; height: 12px; background: {$color};'></div>
                                                                    <span>" . ucfirst($status) . "</span>
                                                                </div>
                                                                <div>
                                                                    <span class='fw-bold'>{$count}</span>
                                                                    <span class='text-muted ms-2'>({$percentage}%)</span>
                                                                </div>
                                                            </div>
                                                            ";
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header">
                                                    <h6 class="m-0 font-weight-bold text-primary">Services Availed Count</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="chart-container">
                                                        <canvas id="servicesAvailedChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Service Distribution Report -->
                <div id="serviceReport" class="report-section d-none">
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-header py-3 bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-chart-bar me-2"></i>Monthly Service Distribution
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label">Select Month</label>
                                            <select class="form-select" id="monthSelect" onchange="updateServiceChart()">
                                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="servicePieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointments Per Day Report -->
                <div id="appointmentsReport" class="report-section d-none">
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-header py-3 bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-calendar-alt me-2"></i>Appointments Per Day (Last 30 Days)
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="appointmentsBarChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue by Services Report -->
                <div id="financialReport" class="report-section d-none">
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-header py-3 bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-money-bill-wave me-2"></i>Revenue by Services
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($revenueData['service_names'])): ?>
                                        <div class="row">
                                            <div class="col-md-7 mb-4">
                                                <div class="chart-container">
                                                    <canvas id="revenueByServicesChart"></canvas>
                                                </div>
                                            </div>
                                            <div class="col-md-5 mb-4">
                                                <div class="card h-100">
                                                    <div class="card-header">
                                                        <h6 class="m-0 font-weight-bold text-primary">Service Revenue Details</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="list-group list-group-flush">
                                                            <?php foreach ($revenueData['service_names'] as $index => $service): ?>
                                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <div class="fw-medium"><?php echo htmlspecialchars($service); ?></div>
                                                                        <small class="text-muted">
                                                                            <?php echo $revenueData['treatment_counts'][$index]; ?> treatments
                                                                        </small>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <div class="fw-bold">₱<?php echo number_format($revenueData['service_revenues'][$index], 2); ?></div>
                                                                        <small class="text-muted">
                                                                            <?php echo $revenueData['total_revenue'] > 0 ? 
                                                                                round(($revenueData['service_revenues'][$index] / $revenueData['total_revenue']) * 100, 1) : 0; ?>%
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-chart-pie fa-4x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Revenue Data Available</h5>
                                            <p class="text-muted">Revenue data will appear here once treatments are completed.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Time Slots Section -->
        <section id="schedules" class="content-section d-none">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-calendar-days me-2"></i>Time Slot Scheduling Control
                    </h1>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#blockDayModal">
                            <i class="fas fa-calendar-times me-2"></i>Block Day
                        </button>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#holidayModal">
                            <i class="fas fa-calendar-star me-2"></i>Holidays
                        </button>
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#emergencyClosureModal">
                            <i class="fas fa-exclamation-triangle me-2"></i>Emergency
                        </button>
                    </div>
                </div>

                <!-- Schedule Controls -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Select Dentist</label>
                                <select class="form-select" id="dentistSelectSchedule">
                                    <option value="">Select Dentist</option>
                                    <?php foreach ($activeDentists as $dentist): ?>
                                        <option value="<?php echo $dentist['team_id']; ?>">
                                            Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">View Type</label>
                                <select class="form-select" id="viewType" onchange="changeScheduleView()">
                                    <option value="weekly">Weekly View</option>
                                    <option value="monthly">Monthly View</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Weekly View -->
                <div id="weeklyView" class="schedule-view">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Weekly Schedule</h6>
                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm" onclick="changeWeek(-1)">
                                    <i class="fas fa-chevron-left"></i> Previous Week
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="changeWeek(1)">
                                    Next Week <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3 text-center">
                                <h5 id="currentWeekRange">Week of ...</h5>
                            </div>
                            <div class="weekly-schedule">
                                <div class="time-slots-header d-flex border-bottom mb-2">
                                    <div class="time-label fw-bold p-2" style="width: 150px;">Time</div>
                                    <?php
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                    $currentDate = new DateTime();
                                    $currentDate->modify('monday this week');
                                    
                                    for ($i = 0; $i < 6; $i++) {
                                        $dayDate = clone $currentDate;
                                        $dayDate->modify("+$i days");
                                        echo "<div class='day-header flex-fill text-center p-2 border-end'>";
                                        echo "<div class='day-name fw-bold'>{$days[$i]}</div>";
                                        echo "<div class='day-date'>{$dayDate->format('M j')}</div>";
                                        echo "</div>";
                                    }
                                    ?>
                                </div>
                                
                                <div class="time-slots-container">
                                    <?php
                                    $timeSlots = [
                                        'firstBatch' => '8:00-9:00 AM',
                                        'secondBatch' => '9:00-10:00 AM',
                                        'thirdBatch' => '10:00-11:00 AM',
                                        'fourthBatch' => '11:00-12:00 PM',
                                        'fifthBatch' => '1:00-2:00 PM',
                                        'sixthBatch' => '2:00-3:00 PM',
                                        'sevenBatch' => '3:00-4:00 PM',
                                        'eightBatch' => '4:00-5:00 PM',
                                        'nineBatch' => '5:00-6:00 PM',
                                        'tenBatch' => '6:00-7:00 PM',
                                        'lastBatch' => '7:00-8:00 PM'
                                    ];
                                    
                                    foreach ($timeSlots as $slotKey => $slotTime) {
                                        echo "<div class='time-slot-row d-flex border-bottom'>";
                                        echo "<div class='time-label p-2' style='width: 150px;'>{$slotTime}</div>";
                                        
                                        for ($i = 0; $i < 6; $i++) {
                                            $dayDate = clone $currentDate;
                                            $dayDate->modify("+$i days");
                                            $dateString = $dayDate->format('Y-m-d');
                                            
                                            echo "<div class='time-slot-cell flex-fill p-2 border-end' data-date='{$dateString}' data-slot='{$slotKey}'>";
                                            echo "<div class='slot-status available' onclick=\"toggleTimeSlot(this, '{$dateString}', '{$slotKey}')\">";
                                            echo "<i class='fas fa-check-circle'></i>";
                                            echo "<span>Available</span>";
                                            echo "</div>";
                                            echo "</div>";
                                        }
                                        echo "</div>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly View -->
                <div id="monthlyView" class="schedule-view d-none">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Monthly Schedule</h6>
                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm" onclick="changeMonth(-1)">
                                    <i class="fas fa-chevron-left"></i> Previous Month
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="changeMonth(1)">
                                    Next Month <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="monthlyCalendar">
                                <!-- Monthly calendar will be generated here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Blocked Time Slots -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-clock me-2"></i>Blocked Time Slots
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="blockedSlotsTable">
                                <thead>
                                    <tr>
                                        <th>Dentist</th>
                                        <th>Date</th>
                                        <th>Time Slot</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="blockedSlotsBody">
                                    <!-- Blocked slots will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- =============== MODALS =============== -->

    <!-- Add Appointment Modal -->
    <div class="modal fade" id="addAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add New Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addAppointmentForm" onsubmit="handleAddAppointmentSubmit(event)">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient ID</label>
                                <select class="form-select" name="patient_id" id="add_patient_id" required onchange="updatePatientName()">
                                    <option value="">Select Patient ID</option>
                                    <?php foreach ($patientsMap as $id => $name): ?>
                                        <option value="<?php echo $id; ?>"><?php echo $id; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Patient Name</label>
                                <input type="text" class="form-control" id="add_patient_name" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Service</label>
                                <select class="form-select" name="service_id" id="add_service_id" required>
                                    <option value="">Select Service</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['service_id']; ?>">
                                            <?php echo htmlspecialchars($service['service_category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dentist</label>
                                <select class="form-select" name="team_id" id="add_team_id" required>
                                    <option value="">Select Dentist</option>
                                    <?php foreach ($activeDentists as $dentist): ?>
                                        <option value="<?php echo $dentist['team_id']; ?>">
                                            Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Appointment Date</label>
                                <input type="date" class="form-control" name="appointment_date" id="add_appointment_date" 
                                       required min="<?php echo date('Y-m-d'); ?>" onchange="checkAvailabilityAdminAdd()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Appointment Time</label>
                                <select class="form-select" name="time_slot" id="add_appointment_time" required>
                                    <option value="">Select Time</option>
                                    <option value="firstBatch">Morning (8AM-9AM)</option>
                                    <option value="secondBatch">Morning (9AM-10AM)</option>
                                    <option value="thirdBatch">Morning (10AM-11AM)</option>
                                    <option value="fourthBatch">Afternoon (11AM-12PM)</option>
                                    <option value="fifthBatch">Afternoon (1PM-2PM)</option>
                                    <option value="sixthBatch">Afternoon (2PM-3PM)</option>
                                    <option value="sevenBatch">Afternoon (3PM-4PM)</option>
                                    <option value="eightBatch">Afternoon (4PM-5PM)</option>
                                    <option value="nineBatch">Afternoon (5PM-6PM)</option>
                                    <option value="tenBatch">Evening (6PM-7PM)</option>
                                    <option value="lastBatch">Evening (7PM-8PM)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Branch</label>
                                <select class="form-select" name="branch" id="add_branch" required>
                                    <option value="">Select Branch</option>
                                    <option value="Main">Main Branch</option>
                                    <option value="North">North Branch</option>
                                    <option value="South">South Branch</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Complete Appointment Modal -->
    <div class="modal fade" id="completeAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-to-slot me-2"></i>Complete Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="treatmentForm" onsubmit="handleTreatmentSubmit(event)">
                    <div class="modal-body">
                        <input type="hidden" id="treatment_patient_id" name="patient_id">
                        <input type="hidden" id="treatment_appointment_id" name="appointment_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Patient ID</label>
                            <input type="text" class="form-control" id="patient_id_display" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Treatment</label>
                            <input type="text" class="form-control" id="treatment_type" name="treatment" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prescription</label>
                            <input type="text" class="form-control" id="prescription_given" name="prescription_given" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="treatment_notes" name="treatment_notes" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Treatment Cost (₱)</label>
                            <input type="number" class="form-control" id="treatment_cost" name="treatment_cost" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Complete and Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">Reschedule Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="rescheduleForm" onsubmit="handleRescheduleSubmit(event)">
                    <div class="modal-body">
                        <input type="hidden" id="modalAppointmentID" name="appointment_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Select New Date</label>
                            <input type="date" class="form-control" id="new_date_resched" name="new_date_resched" 
                                   required min="<?php echo date('Y-m-d'); ?>" onchange="loadBookedSlots()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select New Time</label>
                            <select class="form-select" id="new_time_resched" name="new_time_slot" required>
                                <option value="">Select Time Slot</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Confirm Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Follow-Up Modal -->
    <div class="modal fade" id="followUpModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-arrow-right me-2"></i>Schedule Follow-Up Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="followUpForm" action="../controllers/saveFollowUp.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="followup_patient_id" name="patient_id">
                        <input type="hidden" id="followup_appointment_id" name="original_appointment_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Patient Name</label>
                            <input type="text" class="form-control" id="followup_patient_name" name="patient_name" readonly required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Follow-Up Date</label>
                            <input type="date" class="form-control" id="followup_date" name="appointment_date" 
                                   required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Follow-Up Time</label>
                            <select class="form-select" id="followup_time" name="time_slot" required>
                                <option value="">Select Time</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Follow-Up</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Service</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="../controllers/addServices.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Service Category</label>
                            <select class="form-select" name="service_category" required>
                                <option value="" disabled selected>Select a category</option>
                                <option value="General Dentistry">General Dentistry</option>
                                <option value="Orthodontics">Orthodontics</option>
                                <option value="Oral Surgery">Oral Surgery</option>
                                <option value="Endodontics">Endodontics</option>
                                <option value="Prosthodontics">Prosthodontics</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Service</label>
                            <input type="text" class="form-control" name="sub_service">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" class="form-control" name="price" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Service</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editServiceForm" method="POST" action="../controllers/updateService.php">
                    <div class="modal-body">
                        <input type="hidden" name="service_id" id="editServiceId">
                        
                        <div class="mb-3">
                            <label class="form-label">Service Category</label>
                            <select class="form-select" name="service_category" id="editServiceCategory" required>
                                <option value="General Dentistry">General Dentistry</option>
                                <option value="Orthodontics">Orthodontics</option>
                                <option value="Oral Surgery">Oral Surgery</option>
                                <option value="Endodontics">Endodontics</option>
                                <option value="Prosthodontics">Prosthodontics</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Service</label>
                            <input type="text" class="form-control" name="sub_service" id="editSubService">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" class="form-control" name="price" id="editPrice" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div class="modal fade" id="editPatientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Patient</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editPatientForm" onsubmit="handleEditPatientSubmit(event)">
                    <div class="modal-body">
                        <input type="hidden" name="patient_id" id="editPatientId">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="editLastName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birthdate</label>
                                <input type="date" class="form-control" name="birthdate" id="editBirthdate" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" id="editGender" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="editEmail" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="editPhone" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" id="editAddress" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Treatment History Modal -->
    <div class="modal fade" id="treatmentHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-notes-medical me-2"></i>Patient Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="treatmentHistoryContent">
                        <!-- Content will be loaded by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Dentist Modal -->
    <div class="modal fade" id="addDentistModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Dentist/Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="../controllers/addStaff.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">User ID</label>
                                <select class="form-select" name="userid" id="userid" required>
                                    <option value="">Select User ID</option>
                                    <!-- Options will be populated by JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="addFirstName" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="addLastName" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-control" name="specialization" id="addSpecialization" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="addEmail" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="addPhone" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="addStatus" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Dentist Modal -->
    <div class="modal fade" id="editDentistModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Dentist/Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editDentistForm" method="POST" action="../controllers/updateStaff.php">
                    <div class="modal-body">
                        <input type="hidden" name="team_id" id="editDentistId">
                        <input type="hidden" name="user_id" id="editDentistUserId">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="editDentistFirstName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="editDentistLastName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-control" name="specialization" id="editDentistSpecialization" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="editDentistStatus" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="editDentistEmail" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="editDentistPhone" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Details</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Proof Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Proof Image" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Block Day Modal -->
    <div class="modal fade" id="blockDayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-times me-2"></i>Block Entire Day</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="blockDayForm" onsubmit="handleBlockDaySubmit(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Date</label>
                            <input type="date" class="form-control" id="blockDayDate" name="closure_date" 
                                   required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Closure Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="closure_type" value="full_day" id="fullDay" checked>
                                <label class="form-check-label" for="fullDay">
                                    <i class="fas fa-ban text-danger me-2"></i>Full Day Closure (All appointments blocked)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="closure_type" value="no_new_appointments" id="noNewAppointments">
                                <label class="form-check-label" for="noNewAppointments">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>No New Appointments (Existing appointments remain)
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <select class="form-select" id="blockDayReason" name="reason" required>
                                <option value="">Select Reason</option>
                                <option value="Holiday">Holiday</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Staff Training">Staff Training</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Weather">Weather Conditions</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3" id="blockDayCustomReasonContainer" style="display: none;">
                            <label class="form-label">Custom Reason (if Other)</label>
                            <textarea class="form-control" id="blockDayCustomReason" name="custom_reason" rows="3"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="notifyPatients" name="notify_patients" checked>
                            <label class="form-check-label" for="notifyPatients">
                                Notify patients with appointments on this date
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Block Day</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Holiday Management Modal -->
    <div class="modal fade" id="holidayModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-star me-2"></i>Manage Holidays</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <button class="btn btn-primary" onclick="showAddHolidayForm()">
                            <i class="fas fa-plus me-2"></i>Add Holiday
                        </button>
                    </div>
                    
                    <div id="addHolidayForm" style="display: none;" class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Add New Holiday</h5>
                            <form id="holidayForm" onsubmit="handleHolidaySubmit(event)">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Holiday Name</label>
                                        <input type="text" class="form-control" id="holidayName" name="holiday_name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" id="holidayDate" name="holiday_date" 
                                               required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Recurrence</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recurrence" value="once" id="once" checked>
                                            <label class="form-check-label" for="once">One Time</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recurrence" value="yearly" id="yearly">
                                            <label class="form-check-label" for="yearly">Yearly (Recurring)</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-secondary" onclick="hideAddHolidayForm()">Cancel</button>
                                    <button type="submit" class="btn btn-success">Add Holiday</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div id="holidaysList">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Holiday Name</th>
                                    <th>Date</th>
                                    <th>Recurrence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="holidaysTableBody">
                                <!-- Holidays will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency Closure Modal -->
    <div class="modal fade" id="emergencyClosureModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Emergency Closure</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="emergencyClosureForm" onsubmit="handleEmergencyClosureSubmit(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Closure Duration</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="closure_duration" value="single_day" id="singleDay" checked>
                                <label class="form-check-label" for="singleDay">Single Day</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="closure_duration" value="date_range" id="dateRange">
                                <label class="form-check-label" for="dateRange">Date Range</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="emergencyStartDate" name="start_date" 
                                   required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3" id="emergencyEndDateContainer" style="display: none;">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="emergencyEndDate" name="end_date" 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Emergency Reason</label>
                            <textarea class="form-control" id="emergencyReason" name="reason" rows="4" required></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="emergencyNotifyPatients" name="notify_patients" checked>
                            <label class="form-check-label" for="emergencyNotifyPatients">
                                Notify all affected patients immediately
                            </label>
                        </div>
                        <div class="alert alert-warning">
                            <strong>⚠️ Warning:</strong> This will automatically cancel all appointments during the closure period. Affected patients will be notified.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Emergency Closure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="../libraries/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Main JavaScript -->
    <script>
        // Global variables
        const patientsMap = <?php echo json_encode($patientsMap); ?>;
        let currentWeekStart = getMondayOf(new Date());
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            setupEventListeners();
            updateWeekDisplay();
            generateMonthlyCalendar();
            loadBlockedSlots();
            populateAdminUsers();
            
            // Emergency closure form handling
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
            
            // Load schedule data when dentist is selected
            const dentistSelect = document.getElementById('dentistSelectSchedule');
            if (dentistSelect) {
                dentistSelect.addEventListener('change', function() {
                    if (document.getElementById('scheduleView').value === 'weekly') {
                        loadScheduleData();
                    } else {
                        generateMonthlyCalendar();
                    }
                });
            }
        });

        // ==================== SIDEBAR & NAVIGATION ====================
        function setupEventListeners() {
            // Sidebar toggle
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('mainContent').classList.toggle('expanded');
            });

            // Update patient name in add appointment modal
            document.getElementById('patient_id')?.addEventListener('change', function() {
                updatePatientName();
            });

            // Handle custom date filter
            document.getElementById('filter-date-category')?.addEventListener('change', handleDateCategoryChange);
            document.getElementById('filter-payment-date-category')?.addEventListener('change', handlePaymentDateCategoryChange);
            
            // Reason select change
            document.getElementById('blockDayReason')?.addEventListener('change', function() {
                const customReasonContainer = document.getElementById('blockDayCustomReasonContainer');
                customReasonContainer.style.display = this.value === 'Other' ? 'block' : 'none';
            });
        }

        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.add('d-none');
            });
            
            // Show selected section
            document.getElementById(sectionId).classList.remove('d-none');
            
            // Update active nav link
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            const navLink = document.querySelector(`[onclick="showSection('${sectionId}')"]`);
            if (navLink) navLink.classList.add('active');
        }

        // ==================== NOTIFICATION SYSTEM ====================
        function showNotification(type, title, message, duration = 5000) {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show notification ${type}`;
            notification.innerHTML = `
                <strong>${title}</strong> ${message}
                <button type="button" class="btn-close" onclick="closeNotification(this)"></button>
                <div class="notification-progress">
                    <div class="notification-progress-bar" style="width: 100%; animation: progressBar ${duration}ms linear forwards;"></div>
                </div>
            `;
            
            container.appendChild(notification);
            
            // Auto remove after duration
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }
        
        function closeNotification(btn) {
            const notification = btn.closest('.notification');
            if (notification) {
                notification.remove();
            }
        }

        // ==================== DASHBOARD FUNCTIONS ====================
        function initializeDashboard() {
            // Appointment summary chart
            const ctx = document.getElementById('appointmentSummaryChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($appointmentHours); ?>,
                    datasets: [{
                        label: 'Appointments per Hour',
                        data: <?php echo json_encode($appointmentCounts); ?>,
                        backgroundColor: '#4e73df',
                        borderColor: '#2e59d9',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Initialize report charts
            initializeReportCharts();
        }
        
        function printDashboard() {
            window.print();
        }

        // ==================== APPOINTMENT FUNCTIONS ====================
        function updatePatientName() {
            const selectedID = document.getElementById("add_patient_id")?.value || document.getElementById("patient_id")?.value;
            const nameField = document.getElementById("add_patient_name") || document.getElementById("patient_name");
            if (nameField && selectedID) {
                nameField.value = patientsMap[selectedID] || '';
            }
        }
        
        async function handleAddAppointmentSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            
            try {
                const response = await fetch('../controllers/addAppointment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Success', 'Appointment added successfully.');
                    bootstrap.Modal.getInstance(document.getElementById('addAppointmentModal')).hide();
                    event.target.reset();
                    document.getElementById('add_patient_name').value = '';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to add appointment.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        function handleDateCategoryChange() {
            const category = document.getElementById('filter-date-category').value;
            const container = document.getElementById('custom-date-container');
            
            if (category === 'custom') {
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
                filterAppointments();
            }
        }
        
        function filterAppointments() {
            const dateCategory = document.getElementById('filter-date-category').value;
            const status = document.getElementById('filter-status').value.toLowerCase();
            const customDate = document.getElementById('filter-custom-date')?.value;
            
            const rows = document.querySelectorAll('#appointmentsTable tbody tr');
            const today = new Date().toISOString().split('T')[0];
            
            rows.forEach(row => {
                const rowDate = row.getAttribute('data-date');
                const rowStatus = row.getAttribute('data-status');
                
                let showRow = true;
                
                // Date filtering
                if (dateCategory === 'today') {
                    showRow = rowDate === today;
                } else if (dateCategory === 'custom' && customDate) {
                    showRow = rowDate === customDate;
                }
                
                // Status filtering
                if (status && rowStatus !== status) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }
        
        function resetAppointmentFilters() {
            document.getElementById('filter-date-category').value = '';
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-custom-date').value = '';
            document.getElementById('custom-date-container').classList.add('d-none');
            
            // Show all rows
            document.querySelectorAll('#appointmentsTable tbody tr').forEach(row => {
                row.style.display = '';
            });
        }
        
        function printAppointments() {
            const printWindow = window.open('', '_blank');
            const table = document.getElementById('appointmentsTable');
            const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
            
            if (visibleRows.length === 0) {
                alert('No appointments to print.');
                return;
            }
            
            let htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Appointments Report</title>
                    <style>
                        @media print {
                            @page { margin: 1cm; }
                        }
                        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                        .header { text-align: center; border-bottom: 3px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                        .header h1 { margin: 0; color: #2c3e50; font-size: 24px; }
                        .header h2 { margin: 10px 0; color: #34495e; font-size: 18px; font-weight: normal; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
                        th { background-color: #007bff; color: white; padding: 12px; text-align: left; border: 1px solid #ddd; }
                        td { padding: 10px; border: 1px solid #ddd; }
                        tr:nth-child(even) { background-color: #f8f9fa; }
                        .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center; font-size: 11px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Landero Dental Clinic</h1>
                        <h2>Appointments Report</h2>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Dentist</th>
                                <th>Date & Time</th>
                                <th>Branch</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            visibleRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 7) {
                    htmlContent += `
                        <tr>
                            <td>${cells[0].textContent}</td>
                            <td>${cells[1].textContent}</td>
                            <td>${cells[2].textContent}</td>
                            <td>${cells[3].textContent}</td>
                            <td>${cells[4].textContent}</td>
                            <td>${cells[5].textContent}</td>
                            <td>${cells[6].textContent}</td>
                        </tr>
                    `;
                }
            });
            
            htmlContent += `
                        </tbody>
                    </table>
                    <div class="footer">
                        <p>Total Appointments: ${visibleRows.length}</p>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(htmlContent);
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }
        
        async function confirmAppointment(appointmentId) {
            if (!confirm(`Are you sure you want to confirm Appointment #${appointmentId}?`)) return;
            
            try {
                const response = await fetch('../controllers/confirmAppointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `appointment_id=${appointmentId}`
                });
                
                const data = await response.json();
                
                if (data.success || data.status === 'success') {
                    showNotification('success', 'Success', data.message || 'Appointment confirmed successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to confirm appointment.');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
            }
        }
        
        function openRescheduleModal(appointmentId) {
            document.getElementById('modalAppointmentID').value = appointmentId;
            
            // Populate time slot options
            const timeSelect = document.getElementById('new_time_resched');
            const timeSlots = [
                { value: 'firstBatch', label: 'Morning (8AM-9AM)' },
                { value: 'secondBatch', label: 'Morning (9AM-10AM)' },
                { value: 'thirdBatch', label: 'Morning (10AM-11AM)' },
                { value: 'fourthBatch', label: 'Afternoon (11AM-12PM)' },
                { value: 'fifthBatch', label: 'Afternoon (1PM-2PM)' },
                { value: 'sixthBatch', label: 'Afternoon (2PM-3PM)' },
                { value: 'sevenBatch', label: 'Afternoon (3PM-4PM)' },
                { value: 'eightBatch', label: 'Afternoon (4PM-5PM)' },
                { value: 'nineBatch', label: 'Afternoon (5PM-6PM)' },
                { value: 'tenBatch', label: 'Evening (6PM-7PM)' },
                { value: 'lastBatch', label: 'Evening (7PM-8PM)' }
            ];
            
            timeSelect.innerHTML = '<option value="">Select Time Slot</option>';
            timeSlots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.label;
                timeSelect.appendChild(option);
            });
            
            // Reset date field
            document.getElementById('new_date_resched').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
            modal.show();
        }
        
        async function handleRescheduleSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            try {
                const response = await fetch('../controllers/rescheduleAppointment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Success', 'Appointment rescheduled successfully.');
                    bootstrap.Modal.getInstance(document.getElementById('rescheduleModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to reschedule appointment.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        function openCompleteAppointmentModal(button) {
            const appointmentId = button.getAttribute('data-appointment-id');
            const patientId = button.getAttribute('data-patient-id');
            
            document.getElementById('treatment_appointment_id').value = appointmentId;
            document.getElementById('treatment_patient_id').value = patientId;
            document.getElementById('patient_id_display').value = patientId;
            
            const modal = new bootstrap.Modal(document.getElementById('completeAppointmentModal'));
            modal.show();
        }
        
        async function handleTreatmentSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            
            try {
                const response = await fetch('../controllers/saveTreatment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Success', 'Appointment completed and treatment saved.');
                    bootstrap.Modal.getInstance(document.getElementById('completeAppointmentModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to save treatment.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        // Mark appointment as completed (opens complete modal prefilled)
        function markAsCompleted(appointmentId) {
            fetch(`../controllers/getAppointmentDetails.php?id=${appointmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('treatment_appointment_id').value = appointmentId;
                        document.getElementById('treatment_patient_id').value = data.patient_id || '';
                        document.getElementById('patient_id_display').value = data.patient_id || '';

                        const modal = new bootstrap.Modal(document.getElementById('completeAppointmentModal'));
                        modal.show();
                    } else {
                        showNotification('error', 'Error', data.message || 'Unable to fetch appointment details.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching appointment details:', error);
                    showNotification('error', 'Error', 'An error occurred while fetching appointment details.');
                });
        }

        // Cancel appointment (admin)
        async function cancelAppointment(appointmentId) {
            if (!confirm(`Are you sure you want to cancel Appointment #${appointmentId}?`)) return;

            try {
                const response = await fetch('../controllers/adminCancelAppointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `appointment_id=${appointmentId}`
                });

                const data = await response.json().catch(() => ({ success: true }));

                if (data.success) {
                    showNotification('success', 'Cancelled', data.message || 'Appointment cancelled successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to cancel appointment.');
                }
            } catch (error) {
                console.error('Error cancelling appointment:', error);
                showNotification('error', 'Error', 'An error occurred while cancelling the appointment.');
            }
        }

        // View appointment details in modal
        function viewAppointmentDetails(appointmentId) {
            fetch(`../controllers/getAppointmentDetails.php?id=${appointmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const content = document.getElementById('treatmentHistoryContent');
                        const patientName = data.patient_name || (data.first_name ? `${data.first_name} ${data.last_name}` : 'N/A');
                        const service = data.sub_service || data.service_category || 'N/A';
                        const dentistName = data.dentist_name || (data.dentist_first ? `Dr. ${data.dentist_first} ${data.dentist_last}` : 'N/A');
                        const appointmentDate = data.appointment_date ? new Date(data.appointment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                        const statusBadge = `<span class="badge badge-${(data.status || '').toLowerCase()}">${data.status || 'N/A'}</span>`;

                        content.innerHTML = `
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h4 class="mb-3"><i class="fas fa-calendar-check me-2"></i>Appointment Details</h4>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Appointment Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Appointment ID:</strong> ${data.appointment_id || 'N/A'}</p>
                                            <p><strong>Status:</strong> ${statusBadge}</p>
                                            <p><strong>Date:</strong> ${appointmentDate}</p>
                                            <p><strong>Time:</strong> ${data.appointment_time || 'N/A'}</p>
                                            <p><strong>Branch:</strong> ${data.branch || 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Patient ID:</strong> ${data.patient_id || 'N/A'}</p>
                                            <p><strong>Name:</strong> ${patientName}</p>
                                            <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                                            <p><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                                            <p><strong>Gender:</strong> ${data.gender || 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-success text-white">
                                            <h5 class="mb-0"><i class="fas fa-teeth me-2"></i>Service Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Service Category:</strong> ${data.service_category || 'N/A'}</p>
                                            <p><strong>Sub Service:</strong> ${data.sub_service || 'N/A'}</p>
                                            ${data.service_description ? `<p><strong>Description:</strong> ${data.service_description}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-warning text-white">
                                            <h5 class="mb-0"><i class="fas fa-user-doctor me-2"></i>Dentist Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Dentist:</strong> ${dentistName}</p>
                                            ${data.specialization ? `<p><strong>Specialization:</strong> ${data.specialization}</p>` : ''}
                                            <p><strong>Team ID:</strong> ${data.team_id || 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;

                        const modal = new bootstrap.Modal(document.getElementById('treatmentHistoryModal'));
                        modal.show();
                    } else {
                        showNotification('error', 'Error', data.message || 'Unable to fetch appointment details.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching appointment details:', error);
                    showNotification('error', 'Error', 'An error occurred while fetching appointment details.');
                });
        }
        
        function openFollowUpModal(appointmentId) {
            // Fetch appointment details and populate modal
            fetch(`../controllers/getAppointmentDetails.php?id=${appointmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('followup_appointment_id').value = appointmentId;
                        document.getElementById('followup_patient_id').value = data.patient_id;
                        document.getElementById('followup_patient_name').value = data.patient_name;
                        
                        const modal = new bootstrap.Modal(document.getElementById('followUpModal'));
                        modal.show();
                    }
                });
        }

        // ==================== SERVICE FUNCTIONS ====================
        function filterServices() {
            const category = document.getElementById('filter-service-category').value.toLowerCase();
            const rows = document.querySelectorAll('#servicesTable tbody tr');
            
            rows.forEach(row => {
                const rowCategory = row.getAttribute('data-category');
                
                if (!category || rowCategory === category) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function printServices() {
            window.print();
        }
        
        function editService(serviceId) {
            fetch(`../controllers/getServices.php?id=${serviceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editServiceId').value = data.service_id;
                        document.getElementById('editServiceCategory').value = data.service_category;
                        document.getElementById('editSubService').value = data.sub_service;
                        document.getElementById('editDescription').value = data.description;
                        document.getElementById('editPrice').value = data.price;
                        
                        const modal = new bootstrap.Modal(document.getElementById('editServiceModal'));
                        modal.show();
                    }
                });
        }

        // ==================== PATIENT FUNCTIONS ====================
        function filterPatients() {
            const gender = document.getElementById('filter-patient-gender').value.toLowerCase();
            const age = document.getElementById('filter-patient-age').value.toLowerCase();
            const search = document.getElementById('filter-patient-search').value.toLowerCase();
            
            const rows = document.querySelectorAll('#patientsTable tbody tr');
            
            rows.forEach(row => {
                const rowGender = row.getAttribute('data-gender');
                const rowAge = row.getAttribute('data-age-category');
                const rowSearch = row.getAttribute('data-search');
                
                let showRow = true;
                
                if (gender && rowGender !== gender) showRow = false;
                if (age && rowAge !== age) showRow = false;
                if (search && !rowSearch.includes(search)) showRow = false;
                
                row.style.display = showRow ? '' : 'none';
            });
        }
        
        function clearPatientSearch() {
            document.getElementById('filter-patient-search').value = '';
            filterPatients();
        }
        
        function printPatients() {
            window.print();
        }
        
        function editPatient(patientId) {
            fetch(`../controllers/getPatients.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('editPatientId').value = data.patient_id;
                    document.getElementById('editFirstName').value = data.first_name;
                    document.getElementById('editLastName').value = data.last_name;
                    document.getElementById('editBirthdate').value = data.birthdate;
                    document.getElementById('editGender').value = data.gender;
                    document.getElementById('editEmail').value = data.email;
                    document.getElementById('editPhone').value = data.phone;
                    document.getElementById('editAddress').value = data.address;
                    
                    const modal = new bootstrap.Modal(document.getElementById('editPatientModal'));
                    modal.show();
                });
        }
        
        async function handleEditPatientSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
            
            try {
                const response = await fetch('../controllers/updatePatient.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Success', 'Patient information updated successfully.');
                    bootstrap.Modal.getInstance(document.getElementById('editPatientModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to update patient.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        function viewPatientDetails(patientId) {
            const modal = document.getElementById('treatmentHistoryModal');
            if (!modal) {
                console.error("Treatment history modal not found");
                return;
            }
            
            const content = document.getElementById('treatmentHistoryContent');
            content.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-user me-2"></i>Patient Details - ID: ${patientId}</h5>
                    <button type="button" class="btn btn-primary" onclick="exportPatientDetails('${patientId}')">
                        <i class="fas fa-print me-2"></i>Export/Print
                    </button>
                </div>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6>Patient Information</h6>
                                <div id="patientInfoContent">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 mb-4">
                        <h6>Treatment History</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Treatment</th>
                                        <th>Prescription</th>
                                        <th>Notes</th>
                                        <th>Cost</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="treatmentHistoryBody">
                                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-12 mb-4">
                        <h6>Appointment History</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
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
                                <tbody id="appointmentHistoryBody">
                                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6>Last Transaction</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
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
                                <tbody id="transactionHistoryBody">
                                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            // Load all data
            loadPatientInfo(patientId);
            loadTreatmentHistory(patientId);
            loadAppointmentHistory(patientId);
            loadLastTransaction(patientId);
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
        
        function loadPatientInfo(patientId) {
            fetch(`../controllers/getPatients.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    const content = document.getElementById('patientInfoContent');
                    if (content) {
                        content.innerHTML = `
                            <p><strong>ID:</strong> ${data.patient_id || 'N/A'}</p>
                            <p><strong>Name:</strong> ${data.first_name || ''} ${data.last_name || ''}</p>
                            <p><strong>Birthdate:</strong> ${data.birthdate ? new Date(data.birthdate).toLocaleDateString() : 'N/A'}</p>
                            <p><strong>Gender:</strong> ${data.gender || 'N/A'}</p>
                            <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                            <p><strong>Address:</strong> ${data.address || 'N/A'}</p>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading patient info:', error);
                });
        }
        
        function loadTreatmentHistory(patientId) {
            const tbody = document.getElementById('treatmentHistoryBody');
            if (!tbody) return;
            
            fetch(`../controllers/getTreatmentHistory.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success" && data.data && data.data.length > 0) {
                        tbody.innerHTML = '';
                        data.data.forEach(treatment => {
                            const row = `
                                <tr>
                                    <td>${escapeHtml(treatment.treatment || 'N/A')}</td>
                                    <td>${escapeHtml(treatment.prescription_given || 'N/A')}</td>
                                    <td>${escapeHtml(treatment.notes || 'N/A')}</td>
                                    <td>₱${parseFloat(treatment.treatment_cost || 0).toFixed(2)}</td>
                                    <td>${escapeHtml(treatment.created_at || 'N/A')}</td>
                                </tr>`;
                            tbody.insertAdjacentHTML("beforeend", row);
                        });
                    } else {
                        tbody.innerHTML = "<tr><td colspan='5' class='text-center'>No treatment history found.</td></tr>";
                    }
                })
                .catch(error => {
                    console.error("Error fetching treatment history:", error);
                    tbody.innerHTML = "<tr><td colspan='5' class='text-center text-danger'>Error loading treatment history</td></tr>";
                });
        }
        
        function loadAppointmentHistory(patientId) {
            const tbody = document.getElementById('appointmentHistoryBody');
            if (!tbody) return;
            
            fetch(`../controllers/getAppointmentHistory.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success" && data.data && data.data.length > 0) {
                        tbody.innerHTML = '';
                        data.data.forEach(appointment => {
                            const row = `
                                <tr>
                                    <td>${escapeHtml(appointment.appointment_id || 'N/A')}</td>
                                    <td>${escapeHtml(appointment.dentist_name || 'N/A')}</td>
                                    <td>${escapeHtml(appointment.service_name || 'N/A')}</td>
                                    <td>${escapeHtml(appointment.branch || 'N/A')}</td>
                                    <td>${escapeHtml(appointment.appointment_date || 'N/A')}</td>
                                    <td>${escapeHtml(appointment.appointment_time || 'N/A')}</td>
                                </tr>`;
                            tbody.insertAdjacentHTML("beforeend", row);
                        });
                    } else {
                        tbody.innerHTML = "<tr><td colspan='6' class='text-center'>No appointment history found.</td></tr>";
                    }
                })
                .catch(error => {
                    console.error("Error fetching appointment history:", error);
                    tbody.innerHTML = "<tr><td colspan='6' class='text-center text-danger'>Error loading appointments</td></tr>";
                });
        }
        
        function loadLastTransaction(patientId) {
            const tbody = document.getElementById('transactionHistoryBody');
            if (!tbody) return;
            
            fetch(`../controllers/getLastTransaction.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success" && data.data) {
                        const transaction = data.data;
                        tbody.innerHTML = `
                            <tr>
                                <td>${escapeHtml(transaction.payment_id || 'N/A')}</td>
                                <td>${escapeHtml(transaction.method || 'N/A')}</td>
                                <td>${escapeHtml(transaction.account_name || 'N/A')}</td>
                                <td>₱${parseFloat(transaction.amount || 0).toFixed(2)}</td>
                                <td>${escapeHtml(transaction.reference_no || 'N/A')}</td>
                                <td><span class="badge badge-${(transaction.status || '').toLowerCase()}">${escapeHtml(transaction.status || 'N/A')}</span></td>
                            </tr>`;
                    } else {
                        tbody.innerHTML = "<tr><td colspan='6' class='text-center'>No transaction history found.</td></tr>";
                    }
                })
                .catch(error => {
                    console.error("Error fetching transaction history:", error);
                    tbody.innerHTML = "<tr><td colspan='6' class='text-center text-danger'>Error loading transaction</td></tr>";
                });
        }
        
        function exportPatientDetails(patientId) {
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
                                @page { margin: 1cm; }
                            }
                            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                            .header { text-align: center; border-bottom: 3px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                            .header h1 { margin: 0; color: #2c3e50; font-size: 24px; }
                            .header h2 { margin: 10px 0; color: #34495e; font-size: 18px; font-weight: normal; }
                            .patient-info { margin-bottom: 30px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff; }
                            .patient-info p { margin: 5px 0; font-size: 14px; }
                            .section-title { font-size: 18px; color: #2c3e50; margin-top: 30px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #007bff; }
                            table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; font-size: 12px; }
                            th { background-color: #007bff; color: white; padding: 12px; text-align: left; border: 1px solid #ddd; }
                            td { padding: 10px; border: 1px solid #ddd; }
                            tr:nth-child(even) { background-color: #f8f9fa; }
                            .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center; font-size: 11px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Landero Dental Clinic</h1>
                            <h2>Patient Complete Details Report</h2>
                        </div>
                        <div class="patient-info">
                            <p><strong>Patient ID:</strong> ${patientId}</p>
                            <p><strong>Patient Name:</strong> ${patientName}</p>
                            ${patientData.birthdate ? `<p><strong>Birthdate:</strong> ${new Date(patientData.birthdate).toLocaleDateString()}</p>` : ''}
                            ${patientData.gender ? `<p><strong>Gender:</strong> ${patientData.gender}</p>` : ''}
                            ${patientData.email ? `<p><strong>Email:</strong> ${patientData.email}</p>` : ''}
                            ${patientData.phone ? `<p><strong>Phone:</strong> ${patientData.phone}</p>` : ''}
                            ${patientData.address ? `<p><strong>Address:</strong> ${patientData.address}</p>` : ''}
                            <p><strong>Report Date:</strong> ${currentDate}</p>
                        </div>
                `;
                
                // Treatment History
                htmlContent += `<div class="section-title">Treatment History</div>`;
                if (treatmentData.status === 'success' && treatmentData.data && treatmentData.data.length > 0) {
                    htmlContent += `
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
                } else {
                    htmlContent += `<p>No treatment history found.</p>`;
                }
                
                // Appointment History
                htmlContent += `<div class="section-title">Appointment History</div>`;
                if (appointmentData.status === 'success' && appointmentData.data && appointmentData.data.length > 0) {
                    htmlContent += `
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
                } else {
                    htmlContent += `<p>No appointment history found.</p>`;
                }
                
                // Last Transaction
                htmlContent += `<div class="section-title">Last Transaction</div>`;
                if (transactionData.status === 'success' && transactionData.data) {
                    const transaction = transactionData.data;
                    htmlContent += `
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
                                    <td>${transaction.payment_id || 'N/A'}</td>
                                    <td>${transaction.method || 'N/A'}</td>
                                    <td>${transaction.account_name || 'N/A'}</td>
                                    <td>₱${parseFloat(transaction.amount || 0).toFixed(2)}</td>
                                    <td>${transaction.reference_no || 'N/A'}</td>
                                    <td>${transaction.status || 'N/A'}</td>
                                </tr>
                            </tbody>
                        </table>
                    `;
                } else {
                    htmlContent += `<p>No transaction history found.</p>`;
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
        
        function archivePatient(patientId) {
            if (confirm('Are you sure you want to archive this patient? This action cannot be undone.')) {
                fetch('../controllers/archivePatient.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `patient_id=${patientId}`
                })
                .then(() => {
                    showNotification('success', 'Success', 'Patient archived successfully.');
                    setTimeout(() => location.reload(), 1500);
                });
            }
        }

        // ==================== DENTIST FUNCTIONS ====================
        async function populateAdminUsers() {
            try {
                const response = await fetch('../controllers/getadminUsers.php');
                const adminUsers = await response.json();
                
                const userSelect = document.getElementById('userid');
                userSelect.innerHTML = '<option value="">Select User ID</option>';
                
                adminUsers.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.user_id;
                    option.textContent = user.user_id;
                    option.setAttribute('data-firstname', user.first_name || '');
                    option.setAttribute('data-lastname', user.last_name || '');
                    option.setAttribute('data-email', user.email || '');
                    option.setAttribute('data-phone', user.phone || '');
                    userSelect.appendChild(option);
                });
                
                // Add change event listener
                userSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        document.getElementById('addFirstName').value = selectedOption.getAttribute('data-firstname');
                        document.getElementById('addLastName').value = selectedOption.getAttribute('data-lastname');
                        document.getElementById('addEmail').value = selectedOption.getAttribute('data-email');
                        document.getElementById('addPhone').value = selectedOption.getAttribute('data-phone');
                    } else {
                        document.getElementById('addFirstName').value = '';
                        document.getElementById('addLastName').value = '';
                        document.getElementById('addEmail').value = '';
                        document.getElementById('addPhone').value = '';
                    }
                });
            } catch (error) {
                console.error('Error fetching admin users:', error);
            }
        }
        
        function editDentist(teamId) {
            fetch(`../controllers/getStaff.php?team_id=${teamId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editDentistId').value = data.data.team_id;
                        document.getElementById('editDentistUserId').value = data.data.user_id;
                        document.getElementById('editDentistFirstName').value = data.data.first_name;
                        document.getElementById('editDentistLastName').value = data.data.last_name;
                        document.getElementById('editDentistSpecialization').value = data.data.specialization;
                        document.getElementById('editDentistEmail').value = data.data.email;
                        document.getElementById('editDentistPhone').value = data.data.phone;
                        document.getElementById('editDentistStatus').value = data.data.status;
                        
                        const modal = new bootstrap.Modal(document.getElementById('editDentistModal'));
                        modal.show();
                    }
                });
        }

        // ==================== PAYMENT FUNCTIONS ====================
        function handlePaymentDateCategoryChange() {
            const category = document.getElementById('filter-payment-date-category').value;
            const container = document.getElementById('custom-payment-date-container');
            
            if (category === 'custom') {
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
                filterPayments();
            }
        }
        
        function filterPayments() {
            const dateCategory = document.getElementById('filter-payment-date-category').value;
            const status = document.getElementById('filter-payment-status').value.toLowerCase();
            const method = document.getElementById('filter-payment-method').value.toLowerCase();
            const customDate = document.getElementById('filter-payment-custom-date')?.value;
            
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            const today = new Date().toISOString().split('T')[0];
            
            rows.forEach(row => {
                const rowDate = row.getAttribute('data-date');
                const rowStatus = row.getAttribute('data-status');
                const rowMethod = row.getAttribute('data-method');
                
                let showRow = true;
                
                // Date filtering
                if (dateCategory === 'today') {
                    showRow = rowDate === today;
                } else if (dateCategory === 'custom' && customDate) {
                    showRow = rowDate === customDate;
                }
                
                // Status filtering
                if (status && rowStatus !== status) {
                    showRow = false;
                }
                
                // Method filtering
                if (method && rowMethod !== method) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }
        
        function printPayments() {
            window.print();
        }
        
        function viewPaymentImage(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }
        
        async function confirmPayment(paymentId) {
            try {
                const response = await fetch('../controllers/confirmPayment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `payment_id=${paymentId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Success', 'Payment confirmed successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to confirm payment.');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
            }
        }
        
        async function markPaymentFailed(paymentId) {
            if (!confirm('Are you sure you want to mark this payment as failed?')) return;
            
            try {
                const response = await fetch('../controllers/failedPayment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `payment_id=${paymentId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Success', 'Payment marked as failed.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to mark payment as failed.');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
            }
        }
        
        // Mark No-Show
        async function markNoShow(appointmentId) {
            try {
                const response = await fetch('../controllers/noshowAppointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `appointment_id=${appointmentId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Success', 'Appointment marked as no-show.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to mark as no-show.');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred. Please try again.');
            }
        }
        
        // Load booked slots for reschedule
        function loadBookedSlots() {
            const dateInput = document.getElementById('new_date_resched');
            const timeSelect = document.getElementById('new_time_resched');
            const appointmentId = document.getElementById('modalAppointmentID')?.value;
            
            if (!dateInput || !timeSelect || !dateInput.value) return;
            
            // Get the dentist ID from the current appointment
            fetch(`../controllers/getAppointmentDetails.php?id=${appointmentId}`)
                .then(response => response.json())
                .then(appointmentData => {
                    if (!appointmentData.success) {
                        console.error('Failed to fetch appointment details');
                        return;
                    }
                    
                    const dentistId = appointmentData.team_id;
                    if (!dentistId) {
                        console.error('No dentist ID found');
                        return;
                    }
                    
                    // Fetch booked slots for the selected date and dentist
                    return fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${dateInput.value}&dentist_id=${dentistId}`)
                        .then(response => response.json());
                })
                .then(bookedSlots => {
                    if (!bookedSlots) return;
                    
                    // Enable all options first
                    Array.from(timeSelect.options).forEach(opt => {
                        if (opt.value !== '') {
                            opt.disabled = false;
                            opt.textContent = opt.textContent.replace(' (Booked)', '');
                        }
                    });
                    
                    // Disable booked slots
                    bookedSlots.forEach(slot => {
                        const option = timeSelect.querySelector(`option[value="${slot}"]`);
                        if (option) {
                            option.disabled = true;
                            option.textContent = option.textContent.replace(' (Booked)', '') + ' (Booked)';
                        }
                    });
                    timeSelect.value = '';
                })
                .catch(error => {
                    console.error('Error loading booked slots:', error);
                });
        }
        
        // Appointment availability checking for add appointment
        function checkAvailabilityAdminAdd() {
            const selectedDate = document.getElementById('add_appointment_date')?.value;
            const teamId = document.getElementById('add_team_id')?.value;
            const timeSelect = document.getElementById('add_appointment_time');
            
            if (!selectedDate || !teamId || !timeSelect) return;
            
            fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${selectedDate}&dentist_id=${teamId}`)
                .then(response => response.json())
                .then(bookedSlots => {
                    // Enable all options first
                    Array.from(timeSelect.options).forEach(option => {
                        if (option.value !== '') {
                            option.disabled = false;
                            option.textContent = option.textContent.replace(' (Booked)', '');
                        }
                    });
                    
                    // Disable booked slots
                    bookedSlots.forEach(slot => {
                        const option = timeSelect.querySelector(`option[value="${slot}"]`);
                        if (option) {
                            option.disabled = true;
                            option.textContent = option.textContent.replace(' (Booked)', '') + ' (Booked)';
                        }
                    });
                })
                .catch(error => {
                    console.error("Error fetching appointment data:", error);
                });
        }
        
        // Update availability when date or dentist changes
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('add_appointment_date');
            const dentistSelect = document.getElementById('add_team_id');
            
            if (dateInput) {
                dateInput.addEventListener('change', checkAvailabilityAdminAdd);
            }
            if (dentistSelect) {
                dentistSelect.addEventListener('change', checkAvailabilityAdminAdd);
            }
        });

        // ==================== REPORTS FUNCTIONS ====================
        function filterReports() {
            const selected = document.getElementById('reportType').value;
            const sections = document.querySelectorAll('.report-section');
            
            if (selected === 'all') {
                sections.forEach(section => {
                    section.classList.remove('d-none');
                });
            } else {
                sections.forEach(section => {
                    section.classList.add('d-none');
                });
                
                const selectedSection = document.getElementById(selected + 'Report');
                if (selectedSection) {
                    selectedSection.classList.remove('d-none');
                }
            }
        }
        
        function initializeReportCharts() {
            <?php
            // Get appointment status data
            $statusQuery = mysqli_query($con, "SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
            $appointmentStatuses = [];
            while ($row = mysqli_fetch_assoc($statusQuery)) {
                $appointmentStatuses[$row['status']] = $row['count'];
            }
            
            // Get service revenue data
            $serviceRevenueQuery = mysqli_query($con, "
                SELECT s.service_category, SUM(p.amount) as total_amount
                FROM payment p
                INNER JOIN appointments a ON p.appointment_id = a.appointment_id
                INNER JOIN services s ON a.service_id = s.service_id
                WHERE p.status = 'paid'
                GROUP BY s.service_category
            ");
            $serviceRevenueLabels = [];
            $serviceRevenueAmounts = [];
            while ($row = mysqli_fetch_assoc($serviceRevenueQuery)) {
                $serviceRevenueLabels[] = $row['service_category'];
                $serviceRevenueAmounts[] = (float)$row['total_amount'];
            }
            
            // Get services availed data
            $servicesAvailedQuery = mysqli_query($con, "
                SELECT s.sub_service, COUNT(*) as count
                FROM appointments a
                INNER JOIN services s ON a.service_id = s.service_id
                GROUP BY s.sub_service
                ORDER BY count DESC
            ");
            $servicesAvailedLabels = [];
            $servicesAvailedCounts = [];
            while ($row = mysqli_fetch_assoc($servicesAvailedQuery)) {
                $servicesAvailedLabels[] = $row['sub_service'];
                $servicesAvailedCounts[] = (int)$row['count'];
            }
            ?>
            
            // Appointment Status Chart
            const statusCtx = document.getElementById('appointmentStatusChart');
            if (statusCtx) {
                new Chart(statusCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_keys($appointmentStatuses)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_values($appointmentStatuses)); ?>,
                            backgroundColor: ['#F59E0B', '#10B981', '#3B82F6', '#EF4444', '#6B7280'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        cutout: '60%'
                    }
                });
            }
            
            // Service Revenue Chart
            const serviceRevenueCtx = document.getElementById('serviceRevenueChart');
            if (serviceRevenueCtx && <?php echo json_encode(!empty($serviceRevenueLabels)); ?>) {
                new Chart(serviceRevenueCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($serviceRevenueLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($serviceRevenueAmounts); ?>,
                            backgroundColor: ['#4F46E5', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4', '#8B5CF6'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            // Services Availed Chart
            const availedCtx = document.getElementById('servicesAvailedChart');
            if (availedCtx && <?php echo json_encode(!empty($servicesAvailedLabels)); ?>) {
                new Chart(availedCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($servicesAvailedLabels); ?>,
                        datasets: [{
                            label: 'Number of Appointments',
                            data: <?php echo json_encode($servicesAvailedCounts); ?>,
                            backgroundColor: '#4F46E5',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
            
            // Monthly Service Distribution Chart
            const monthlyData = <?php echo json_encode($monthlyServiceData); ?>;
            const colorPalette = ['#4F46E5', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4', '#8B5CF6', '#84CC16', '#EC4899'];
            
            window.updateServiceChart = function() {
                const selectedMonth = document.getElementById('monthSelect')?.value;
                if (!selectedMonth) return;
                
                const data = monthlyData[selectedMonth];
                const serviceCtx = document.getElementById('servicePieChart');
                
                if (!serviceCtx || !data) return;
                
                if (window.servicePieChart) window.servicePieChart.destroy();
                
                const labels = data.labels || [];
                const counts = data.counts || [];
                
                window.servicePieChart = new Chart(serviceCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: counts,
                            backgroundColor: labels.map((_, i) => colorPalette[i % colorPalette.length])
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: `Patients per Service - ${getMonthName(selectedMonth)} <?php echo date('Y'); ?>`
                            },
                            legend: { display: false }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                title: { display: true, text: 'Patients' } 
                            },
                            x: { 
                                title: { display: true, text: 'Services' } 
                            }
                        }
                    }
                });
            };
            
            function getMonthName(m) {
                const d = new Date(); d.setMonth(m - 1);
                return d.toLocaleString('default', { month: 'long' });
            }
            
            // Initialize monthly chart on page load
            if (document.getElementById('monthSelect')) {
                document.getElementById('monthSelect').addEventListener('change', updateServiceChart);
                // Initialize with current month
                if (typeof monthlyData !== 'undefined') {
                    updateServiceChart();
                }
            }
            
            
            // Appointments Per Day Chart
            <?php
            $sql = "SELECT appointment_date, COUNT(*) as count FROM appointments 
                    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY appointment_date ORDER BY appointment_date";
            $result = mysqli_query($con, $sql);
            $dates = [];
            $counts = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $dates[] = date('M j', strtotime($row['appointment_date']));
                $counts[] = (int)$row['count'];
            }
            ?>
            const appointmentsCtx = document.getElementById('appointmentsBarChart');
            if (appointmentsCtx && <?php echo json_encode(!empty($dates)); ?>) {
                new Chart(appointmentsCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($dates); ?>,
                        datasets: [{
                            label: 'Appointments',
                            data: <?php echo json_encode($counts); ?>,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.5)',
                            tension: 0.2,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: {
                                display: false
                            }
                        },
                        scales: { 
                            y: { 
                                beginAtZero: true 
                            } 
                        }
                    }
                });
            }
            
            // Revenue by Services Chart
            <?php if (!empty($revenueData['service_names'])): ?>
            const revenueByServicesCtx = document.getElementById('revenueByServicesChart');
            if (revenueByServicesCtx) {
                new Chart(revenueByServicesCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($revenueData['service_names']); ?>,
                        datasets: [{
                            data: <?php echo json_encode($revenueData['service_revenues']); ?>,
                            backgroundColor: [
                                '#4F46E5', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4',
                                '#8B5CF6', '#84CC16', '#EC4899', '#F97316', '#0EA5E9'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    boxWidth: 12
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return `${label}: ₱${value.toLocaleString()} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        }

        // ==================== SCHEDULE FUNCTIONS ====================
        function changeScheduleView() {
            const viewType = document.getElementById('viewType').value;
            document.getElementById('weeklyView').classList.toggle('d-none', viewType !== 'weekly');
            document.getElementById('monthlyView').classList.toggle('d-none', viewType !== 'monthly');
            
            if (viewType === 'monthly') {
                generateMonthlyCalendar();
            } else {
                loadScheduleData();
            }
        }
        
        function getMondayOf(date) {
            const d = new Date(date);
            const day = d.getDay();
            const diffToMonday = (day === 0) ? -6 : 1 - day;
            d.setDate(d.getDate() + diffToMonday);
            d.setHours(0,0,0,0);
            return d;
        }
        
        function updateWeekDisplay() {
            const weekEnd = new Date(currentWeekStart);
            weekEnd.setDate(weekEnd.getDate() + 5);
            const options = { month: 'short', day: 'numeric' };
            const startStr = currentWeekStart.toLocaleDateString('en-US', options);
            const endStr = weekEnd.toLocaleDateString('en-US', options);
            const weekRangeEl = document.getElementById('currentWeekRange');
            if (weekRangeEl) {
                weekRangeEl.textContent = `Week of ${startStr} - ${endStr}`;
            }
            updateDayHeadersAndCells();
        }
        
        function changeWeek(direction) {
            const newWeekStart = new Date(currentWeekStart);
            newWeekStart.setDate(newWeekStart.getDate() + (direction * 7));
            const thisWeekMonday = getMondayOf(new Date());
            
            if (newWeekStart < thisWeekMonday) {
                return;
            }
            
            currentWeekStart = newWeekStart;
            updateWeekDisplay();
            setTimeout(() => loadScheduleData(), 100);
        }
        
        function updateDayHeadersAndCells() {
            const dayDateEls = document.querySelectorAll('.time-slots-header .day-header .day-date');
            for (let i = 0; i < dayDateEls.length && i < 6; i++) {
                const d = new Date(currentWeekStart);
                d.setDate(currentWeekStart.getDate() + i);
                dayDateEls[i].textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
            
            const cells = document.querySelectorAll('.time-slot-cell');
            cells.forEach(cell => {
                const parentRow = cell.parentElement;
                if (!parentRow) return;
                const rowCells = Array.from(parentRow.querySelectorAll('.time-slot-cell'));
                const colIndex = rowCells.indexOf(cell);
                if (colIndex >= 0 && colIndex < 6) {
                    const dateForCell = new Date(currentWeekStart);
                    dateForCell.setDate(currentWeekStart.getDate() + colIndex);
                    const yyyy = dateForCell.getFullYear();
                    const mm = String(dateForCell.getMonth() + 1).padStart(2, '0');
                    const dd = String(dateForCell.getDate()).padStart(2, '0');
                    const isoDate = `${yyyy}-${mm}-${dd}`;
                    cell.setAttribute('data-date', isoDate);
                }
            });
        }
        
        function generateMonthlyCalendar() {
            const calendar = document.getElementById('monthlyCalendar');
            if (!calendar) return;
            
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                              'July', 'August', 'September', 'October', 'November', 'December'];
            const dentistId = document.getElementById('dentistSelectSchedule')?.value;
            
            const currentMonthEl = document.getElementById('currentMonth');
            if (currentMonthEl) {
                currentMonthEl.textContent = `${monthNames[currentMonth]} ${currentYear}`;
            }
            
            const firstDay = new Date(currentYear, currentMonth, 1);
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            const startingDay = firstDay.getDay();
            
            loadMonthlyScheduleData(dentistId, firstDay, lastDay).then(scheduleData => {
                let calendarHTML = '';
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                
                dayNames.forEach(day => {
                    calendarHTML += `<div class="calendar-day-header">${day}</div>`;
                });
                
                for (let i = 0; i < startingDay; i++) {
                    calendarHTML += `<div class="calendar-day other-month"></div>`;
                }
                
                for (let day = 1; day <= lastDay.getDate(); day++) {
                    const date = new Date(currentYear, currentMonth, day);
                    const dateStr = date.toISOString().split('T')[0];
                    const isToday = new Date().toDateString() === date.toDateString();
                    const dayClass = isToday ? 'calendar-day today' : 'calendar-day';
                    
                    const dayData = scheduleData[dateStr] || { blocked: 0, booked: 0, available: 0 };
                    const totalSlots = 11;
                    const available = totalSlots - dayData.blocked - dayData.booked;
                    
                    calendarHTML += `
                        <div class="${dayClass}" data-date="${dateStr}">
                            <div class="calendar-day-header">${day}</div>
                            <div class="day-slots">
                                <div><span class="slot-indicator available"></span> ${available} available</div>
                                <div><span class="slot-indicator blocked"></span> ${dayData.blocked} blocked</div>
                                <div><span class="slot-indicator booked"></span> ${dayData.booked} booked</div>
                            </div>
                        </div>
                    `;
                }
                
                calendar.innerHTML = calendarHTML;
            });
        }
        
        async function loadMonthlyScheduleData(dentistId, firstDay, lastDay) {
            if (!dentistId) return {};
            
            const scheduleData = {};
            
            try {
                const blockedResponse = await fetch('../controllers/get_blocked_slots.php');
                const blockedSlots = await blockedResponse.json();
                
                const startDate = firstDay.toISOString().split('T')[0];
                const endDate = lastDay.toISOString().split('T')[0];
                
                for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                    const dateStr = d.toISOString().split('T')[0];
                    scheduleData[dateStr] = { blocked: 0, booked: 0, available: 0 };
                }
                
                blockedSlots.forEach(slot => {
                    if (slot.dentist_id === dentistId && slot.date >= startDate && slot.date <= endDate) {
                        if (scheduleData[slot.date]) {
                            scheduleData[slot.date].blocked++;
                        }
                    }
                });
                
                const appointmentPromises = [];
                for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                    const dateStr = d.toISOString().split('T')[0];
                    appointmentPromises.push(
                        fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${dateStr}&dentist_id=${dentistId}`)
                            .then(res => res.json())
                            .then(slots => {
                                if (scheduleData[dateStr]) {
                                    scheduleData[dateStr].booked = slots.length;
                                }
                            })
                            .catch(() => {})
                    );
                }
                
                await Promise.all(appointmentPromises);
            } catch (error) {
                console.error('Error loading monthly schedule data:', error);
            }
            
            return scheduleData;
        }
        
        function changeMonth(direction) {
            currentMonth += direction;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            } else if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            generateMonthlyCalendar();
            setTimeout(() => loadScheduleData(), 100);
        }
        
        function loadBlockedSlots() {
            fetch('../controllers/get_blocked_slots.php')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('blockedSlotsBody');
                    if (!tbody) return;
                    
                    tbody.innerHTML = '';
                    
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No blocked time slots found</td></tr>';
                        return;
                    }
                    
                    data.forEach(slot => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${slot.dentist_name || 'N/A'}</td>
                            <td>${slot.date}</td>
                            <td>${slot.time_slot_display || slot.time_slot}</td>
                            <td>${slot.reason || 'N/A'}</td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="unblockSlot('${slot.id}')" title="Unblock">
                                    <i class="fas fa-unlock"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading blocked slots:', error);
                });
        }
        
        function unblockSlot(blockId) {
            if (!confirm('Are you sure you want to unblock this time slot?')) return;
            
            fetch('../controllers/update_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    block_id: blockId,
                    action: 'unblock_slot'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Success', 'Time slot unblocked successfully.');
                    loadBlockedSlots();
                    loadScheduleData();
                    const monthlyView = document.getElementById('monthlyView');
                    if (monthlyView && !monthlyView.classList.contains('d-none')) {
                        generateMonthlyCalendar();
                    }
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to unblock time slot.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while unblocking the time slot.');
            });
        }
        
        function loadScheduleData() {
            const dentistId = document.getElementById('dentistSelectSchedule')?.value;
            if (!dentistId) return;
            
            const dateCells = document.querySelectorAll('.time-slot-cell');
            const dates = new Set();
            dateCells.forEach(cell => {
                const date = cell.getAttribute('data-date');
                if (date) dates.add(date);
            });
            
            Promise.all([
                fetch('../controllers/get_blocked_slots.php').then(res => res.json()),
                Promise.all(Array.from(dates).map(date => 
                    fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${date}&dentist_id=${dentistId}`)
                        .then(res => res.json())
                        .then(slots => ({ date, slots }))
                        .catch(() => ({ date, slots: [] }))
                ))
            ])
            .then(([blockedSlots, appointmentData]) => {
                const appointmentsByDate = {};
                appointmentData.forEach(({ date, slots }) => {
                    appointmentsByDate[date] = new Set(slots);
                });
                
                document.querySelectorAll('.slot-status').forEach(slot => {
                    slot.className = 'slot-status available';
                    slot.innerHTML = '<i class="fas fa-check-circle"></i><span>Available</span>';
                    const cell = slot.closest('.time-slot-cell');
                    if (cell) {
                        const date = cell.getAttribute('data-date');
                        const slotKey = cell.getAttribute('data-slot');
                        slot.setAttribute('onclick', `toggleTimeSlot(this, '${date}', '${slotKey}')`);
                        slot.style.cursor = 'pointer';
                    }
                });
                
                Object.keys(appointmentsByDate).forEach(date => {
                    const bookedSlots = appointmentsByDate[date];
                    bookedSlots.forEach(timeSlot => {
                        const cell = document.querySelector(`[data-date="${date}"][data-slot="${timeSlot}"]`);
                        if (cell) {
                            const statusElement = cell.querySelector('.slot-status');
                            if (statusElement) {
                                statusElement.className = 'slot-status booked';
                                statusElement.innerHTML = '<i class="fas fa-calendar-check"></i><span>Booked</span>';
                                statusElement.removeAttribute('onclick');
                                statusElement.style.cursor = 'not-allowed';
                            }
                        }
                    });
                });
                
                blockedSlots.forEach(slot => {
                    if (slot.dentist_id === dentistId) {
                        const cell = document.querySelector(`[data-date="${slot.date}"][data-slot="${slot.time_slot}"]`);
                        if (cell) {
                            const statusElement = cell.querySelector('.slot-status');
                            if (statusElement && !statusElement.classList.contains('booked')) {
                                statusElement.className = 'slot-status blocked';
                                statusElement.innerHTML = '<i class="fas fa-times-circle"></i><span>Blocked</span>';
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error loading schedule data:', error);
            });
        }
        
        function toggleTimeSlot(element, date, slot) {
            if (element.classList.contains('booked') || element.style.cursor === 'not-allowed') {
                alert('This slot is already booked and cannot be modified.');
                return;
            }
            
            const currentStatus = element.classList.contains('available') ? 'available' : 
                                element.classList.contains('blocked') ? 'blocked' : 'booked';
            
            if (currentStatus === 'booked') {
                alert('This slot is already booked and cannot be modified.');
                return;
            }
            
            const newStatus = currentStatus === 'available' ? 'blocked' : 'available';
            
            if (newStatus === 'blocked') {
                const reason = prompt('Please provide a reason for blocking this time slot:', 'Blocked by admin');
                if (reason === null) return;
                if (reason.trim() === '') {
                    alert('Reason is required to block a time slot.');
                    return;
                }
                
                element.className = `slot-status ${newStatus}`;
                element.innerHTML = '<i class="fas fa-times-circle"></i><span>Blocked</span>';
                updateTimeSlotStatus(date, slot, newStatus, reason.trim());
            } else {
                if (!confirm('Are you sure you want to unblock this time slot?')) return;
                element.className = `slot-status ${newStatus}`;
                element.innerHTML = '<i class="fas fa-check-circle"></i><span>Available</span>';
                updateTimeSlotStatus(date, slot, newStatus);
            }
        }
        
        function updateTimeSlotStatus(date, slot, status, reason = '') {
            const dentistId = document.getElementById('dentistSelectSchedule')?.value;
            
            if (!dentistId) {
                alert('Please select a dentist first.');
                return;
            }
            
            const requestData = {
                dentist_id: dentistId,
                date: date,
                time_slot: slot,
                status: status,
                action: 'update_slot'
            };
            
            if (reason) {
                requestData.reason = reason;
            }
            
            fetch('../controllers/update_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Success', 'Time slot updated successfully.');
                    loadBlockedSlots();
                    loadScheduleData();
                    const monthlyView = document.getElementById('monthlyView');
                    if (monthlyView && !monthlyView.classList.contains('d-none')) {
                        generateMonthlyCalendar();
                    }
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to update time slot.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while updating the time slot.');
            });
        }
        
        function showAddHolidayForm() {
            const form = document.getElementById('addHolidayForm');
            if (form) form.style.display = 'block';
        }
        
        function hideAddHolidayForm() {
            const form = document.getElementById('addHolidayForm');
            if (form) {
                form.style.display = 'none';
                const holidayForm = document.getElementById('holidayForm');
                if (holidayForm) holidayForm.reset();
            }
        }
        
        async function handleBlockDaySubmit(event) {
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
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            const requestData = {
                action: 'block_day',
                date: closureDate,
                closure_type: closureType,
                reason: reason,
                custom_reason: customReason || '',
                notify_patients: notifyPatients
            };
            
            try {
                const response = await fetch('manage_clinic_closure.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Day Blocked Successfully', `Date ${closureDate} has been blocked.`);
                    bootstrap.Modal.getInstance(document.getElementById('blockDayModal')).hide();
                    loadBlockedSlots();
                    loadScheduleData();
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to block day.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while blocking the day.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        async function handleHolidaySubmit(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
            
            const requestData = {
                action: 'add_holiday',
                holiday_name: formData.get('holiday_name'),
                holiday_date: formData.get('holiday_date'),
                recurrence: formData.get('recurrence')
            };
            
            try {
                const response = await fetch('manage_clinic_closure.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Holiday Added', `Holiday "${requestData.holiday_name}" has been added.`);
                    hideAddHolidayForm();
                    loadHolidays();
                    loadScheduleData();
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to add holiday.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while adding holiday.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
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
                                <td>${holiday.holiday_name}</td>
                                <td>${holiday.holiday_date}</td>
                                <td>${holiday.recurrence === 'yearly' ? 'Yearly (Recurring)' : 'One Time'}</td>
                                <td class="text-center">
                                    <button class="btn btn-danger btn-sm" onclick="deleteHoliday(${holiday.id})" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No holidays found.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error loading holidays:', error);
                });
        }
        
        function deleteHoliday(holidayId) {
            if (!confirm('Are you sure you want to delete this holiday?')) return;
            
            fetch('manage_clinic_closure.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_holiday',
                    holiday_id: holidayId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Holiday Deleted', 'Holiday has been deleted successfully.');
                    loadHolidays();
                    loadScheduleData();
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to delete holiday.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while deleting holiday.');
            });
        }
        
        async function handleEmergencyClosureSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const closureDuration = formData.get('closure_duration');
            const startDate = formData.get('start_date');
            const endDate = formData.get('end_date');
            const reason = formData.get('reason');
            const notifyPatients = formData.get('notify_patients') === 'on';
            
            if (closureDuration === 'date_range' && !endDate) {
                showNotification('error', 'Error', 'Please provide an end date for the closure period.');
                return;
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            const requestData = {
                action: 'emergency_closure',
                closure_duration: closureDuration,
                start_date: startDate,
                end_date: endDate || startDate,
                reason: reason,
                notify_patients: notifyPatients
            };
            
            try {
                const response = await fetch('manage_clinic_closure.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'Emergency Closure Confirmed', 'All appointments during the closure period have been cancelled.');
                    bootstrap.Modal.getInstance(document.getElementById('emergencyClosureModal')).hide();
                    loadBlockedSlots();
                    loadScheduleData();
                } else {
                    showNotification('error', 'Error', data.message || 'Failed to process emergency closure.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('error', 'Error', 'An error occurred while processing emergency closure.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        // Handle emergency closure duration change
        // ==================== UTILITY FUNCTIONS ====================
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function printTreatmentHistory(patientId) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Treatment History</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; }
                        th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    <h1>Treatment History - Patient ID: ${patientId}</h1>
                    <p>Generated on ${new Date().toLocaleDateString()}</p>
                    <!-- Treatment history content would be fetched and displayed here -->
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>