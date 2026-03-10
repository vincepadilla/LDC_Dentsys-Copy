<?php
session_start();
include_once("../database/config.php");

// Redirect if not logged in or not admin
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super-admin') {
    header("Location: login.php");
    exit();
}

// Create site_content table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS site_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(100) UNIQUE NOT NULL,
    content_value TEXT,
    content_type VARCHAR(50) DEFAULT 'text',
    section VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

mysqli_query($con, $createTableQuery);

// Get all content
$contentQuery = "SELECT content_key, content_value, content_type, section FROM site_content ORDER BY section, content_key";
$contentResult = mysqli_query($con, $contentQuery);
$contentData = [];

while ($row = mysqli_fetch_assoc($contentResult)) {
    $contentData[$row['content_key']] = stripslashes($row['content_value']);
}

// Default values if not in database
$defaults = [
    'hero_title' => 'Your Smile Deserves the Best Care',
    'hero_subtitle' => 'Professional dental care in a comfortable and friendly environment',
    'services_title' => 'Our Services',
    'services_subtitle' => 'Comprehensive dental care for the whole family',
    'contact_title' => 'Contact Us',
    'contact_subtitle' => 'Send us a message about appointments, services, or any other concerns about us.',
    'contact_help_title' => 'We\'re here to help',
    'contact_help_text' => 'Call us, send an email, or use the form to send your questions and we\'ll get back to you as soon as possible.',
    'contact_hours' => 'Mon - Sun: 8:00 AM - 8:00 PM',
    'contact_phone' => '0922 861 1987',
    'contact_email' => 'landerodentalclinic@gmail.com',
    'location_title' => 'Visit Our Clinics',
    'location_subtitle' => 'Find us in Comembo, Taguig City or Taytay, Rizal. Use the map and contact details below for easy navigation.',
    'location_comembo' => 'Anahaw St. Comembo, Taguig City',
    'location_taytay' => 'Lot 2 Block 5, Turquoise Corner, Golden City Subd, Amber, Dolores, Taytay, 1920 Rizal',
    'dentist_title' => 'Our Dentist',
    'dentist_subtitle' => 'Meet Our Professional Dentist',
    'dentist_name' => 'Dr. Michelle Landero',
    'dentist_specialty' => 'Dentist',
    'dentist_experience' => 'With over 10 years of experience in providing exceptional dental care.'
];

foreach ($defaults as $key => $value) {
    if (!isset($contentData[$key])) {
        $contentData[$key] = $value;
    }
}

// Display success/error messages
$success_msg = '';
$error_msg = '';

if (isset($_SESSION['content_success'])) {
    $success_msg = $_SESSION['content_success'];
    unset($_SESSION['content_success']);
}

if (isset($_SESSION['content_error'])) {
    $error_msg = $_SESSION['content_error'];
    unset($_SESSION['content_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Content | Landero Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            background: #f3f4f6;
        }

        .edit-content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .edit-content-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .edit-content-subtitle {
            color: #6b7280;
            margin-top: 6px;
            font-size: 14px;
        }

        .back-to-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #48A6A7;
            border: 2px solid #48A6A7;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .back-to-dashboard:hover {
            background: #48A6A7;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(72, 166, 167, 0.3);
        }

        .section-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
            border-bottom: 2px solid #e5e7eb;
            flex-wrap: wrap;
            overflow-x: auto;
            padding-bottom: 0;
        }

        .tab-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #6b7280;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            top: 2px;
            white-space: nowrap;
        }

        .tab-button:hover {
            color: #48A6A7;
            background: #f9fafb;
        }

        .tab-button.active {
            color: #48A6A7;
            border-bottom-color: #48A6A7;
            font-weight: 600;
        }

        .tab-button i {
            font-size: 16px;
        }

        .tab-badge {
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 6px;
            display: none;
        }

        .tab-button.active .tab-badge {
            background: #48A6A7;
        }

        .content-area {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .section-description {
            color: #6b7280;
            font-size: 14px;
            margin-top: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-label-required::after {
            content: " *";
            color: #ef4444;
        }

        .form-input,
        .form-textarea {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            color: #111827;
            background: white;
            transition: all 0.2s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #48A6A7;
            box-shadow: 0 0 0 3px rgba(72, 166, 167, 0.1);
        }

        .form-help {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: #48A6A7;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 32px;
        }

        .btn-save:hover {
            background: #3d8e90;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 166, 167, 0.4);
        }

        /* Feedback Management Styles */
        .feedback-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e5e7eb;
            background: white;
            color: #6b7280;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: #48A6A7;
            color: #48A6A7;
        }

        .filter-btn.active {
            background: #48A6A7;
            color: white;
            border-color: #48A6A7;
        }

        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .feedback-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
        }

        .feedback-card:hover {
            border-color: #48A6A7;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .feedback-card.pending {
            border-left: 4px solid #f59e0b;
        }

        .feedback-card.approved {
            border-left: 4px solid #10b981;
        }

        .feedback-card.rejected {
            border-left: 4px solid #ef4444;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .feedback-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .feedback-meta {
            font-size: 13px;
            color: #6b7280;
        }

        .feedback-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .feedback-status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .feedback-status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .feedback-status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .feedback-text {
            background: white;
            padding: 16px;
            border-radius: 8px;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
        }

        .feedback-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 16px;
            }

            .edit-content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .feedback-filters {
                flex-direction: column;
            }

            .filter-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar Toggle (mobile) -->
    <div class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../assets/images/landerologo.png" alt="Clinic Logo">
        </div>
        <nav class="sidebar-nav">
            <a href="super_admin_portal.php">
                <i class="fas fa-tachometer-alt"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="userControl.php">
                <i class="fas fa-users-cog"></i>
                <span class="sidebar-text">User Control</span>
            </a>
            <a href="edit_content.php" class="active">
                <i class="fas fa-edit"></i>
                <span class="sidebar-text">Edit Content</span>
            </a>
            <a href="../controllers/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="sidebar-text">Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="edit-content-header">
            <div>
                <div class="edit-content-title">
                    <i class="fas fa-edit"></i>
                    Edit Website Content
                </div>
                <div class="edit-content-subtitle">
                    Manage and update your website content sections
                </div>
            </div>
            <a href="super_admin_portal.php" class="back-to-dashboard">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <form id="contentForm" action="../controllers/updateContent.php" method="POST">
            <div class="content-area">
                <!-- Section Tabs -->
                <div class="section-tabs">
                    <button type="button" class="tab-button active" onclick="showSection('hero')">
                        <i class="fas fa-home"></i>
                        <span>Hero Section</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('services')">
                        <i class="fas fa-teeth"></i>
                        <span>Services</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('contact')">
                        <i class="fas fa-envelope"></i>
                        <span>Contact</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('location')">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Location</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('dentist')">
                        <i class="fas fa-user-doctor"></i>
                        <span>Dentist</span>
                    </button>
                    <button type="button" class="tab-button" onclick="showSection('feedback')">
                        <i class="fas fa-comments"></i>
                        <span>Patient Feedback</span>
                        <span class="tab-badge" id="pendingCount">0</span>
                    </button>
                </div>
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($success_msg); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_msg): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($error_msg); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Hero Section -->
                    <div id="hero" class="tab-content active">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-home"></i>
                                Hero Section
                            </div>
                            <div class="section-description">
                                Update the main hero section that appears at the top of your homepage
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label form-label-required">Hero Title</label>
                                <input type="text" name="hero_title" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['hero_title']); ?>" required>
                                <div class="form-help">Main headline displayed on the homepage</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Hero Subtitle</label>
                                <textarea name="hero_subtitle" class="form-textarea" required><?php echo htmlspecialchars($contentData['hero_subtitle']); ?></textarea>
                                <div class="form-help">Supporting text below the main title</div>
                            </div>
                        </div>
                    </div>

                    <!-- Services Section -->
                    <div id="services" class="tab-content">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-teeth"></i>
                                Services Section
                            </div>
                            <div class="section-description">
                                Manage the services section content displayed on your website
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label form-label-required">Services Title</label>
                                <input type="text" name="services_title" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['services_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Services Subtitle</label>
                                <textarea name="services_subtitle" class="form-textarea" required><?php echo htmlspecialchars($contentData['services_subtitle']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Section -->
                    <div id="contact" class="tab-content">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-envelope"></i>
                                Contact Information
                            </div>
                            <div class="section-description">
                                Update contact details and information displayed on the contact section
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label form-label-required">Contact Section Title</label>
                                <input type="text" name="contact_title" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['contact_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Contact Section Subtitle</label>
                                <textarea name="contact_subtitle" class="form-textarea" required><?php echo htmlspecialchars($contentData['contact_subtitle']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Help Title</label>
                                <input type="text" name="contact_help_title" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['contact_help_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Help Text</label>
                                <textarea name="contact_help_text" class="form-textarea" required><?php echo htmlspecialchars($contentData['contact_help_text']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Operating Hours</label>
                                <input type="text" name="contact_hours" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['contact_hours']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Phone Number</label>
                                <input type="text" name="contact_phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['contact_phone']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Email Address</label>
                                <input type="email" name="contact_email" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['contact_email']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Location Section -->
                    <div id="location" class="tab-content">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-map-marker-alt"></i>
                                Location Information
                            </div>
                            <div class="section-description">
                                Update branch addresses and location details
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label form-label-required">Location Section Title</label>
                                <input type="text" name="location_title" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['location_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Location Section Subtitle</label>
                                <textarea name="location_subtitle" class="form-textarea" required><?php echo htmlspecialchars($contentData['location_subtitle']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Comembo Branch Address</label>
                                <input type="text" name="location_comembo" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['location_comembo']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Taytay Branch Address</label>
                                <input type="text" name="location_taytay" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['location_taytay']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Dentist Section -->
                    <div id="dentist" class="tab-content">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-user-doctor"></i>
                                Dentist Information
                            </div>
                            <div class="section-description">
                                Manage dentist profile and information displayed on the website
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label form-label-required">Dentist Section Title</label>
                                <input type="text" name="dentist_title" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['dentist_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Dentist Section Subtitle</label>
                                <textarea name="dentist_subtitle" class="form-textarea" required><?php echo htmlspecialchars($contentData['dentist_subtitle']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Dentist Name</label>
                                <input type="text" name="dentist_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['dentist_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Specialty</label>
                                <input type="text" name="dentist_specialty" class="form-input" 
                                       value="<?php echo htmlspecialchars($contentData['dentist_specialty']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label form-label-required">Experience Description</label>
                                <textarea name="dentist_experience" class="form-textarea" required><?php echo htmlspecialchars($contentData['dentist_experience']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback Management Section -->
                    <div id="feedback" class="tab-content">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-comments"></i>
                                Patient Feedback Management
                            </div>
                            <div class="section-description">
                                Review and manage patient feedback submissions
                            </div>
                        </div>
                        <div class="feedback-filters">
                            <button type="button" class="filter-btn active" onclick="filterFeedbacks('all')">
                                <i class="fas fa-list"></i> All
                            </button>
                            <button type="button" class="filter-btn" onclick="filterFeedbacks('pending')">
                                <i class="fas fa-clock"></i> Pending
                            </button>
                            <button type="button" class="filter-btn" onclick="filterFeedbacks('approved')">
                                <i class="fas fa-check"></i> Approved
                            </button>
                            <button type="button" class="filter-btn" onclick="filterFeedbacks('rejected')">
                                <i class="fas fa-times"></i> Rejected
                            </button>
                        </div>
                        <div id="feedbackList" class="feedback-list">
                            <div class="empty-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <h3>Loading feedback...</h3>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Save All Changes
                    </button>
                </div>
        </form>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            const menuToggle = document.querySelector(".menu-toggle");
            const overlay = document.getElementById("sidebarOverlay");

            sidebar.classList.toggle("active");
            menuToggle.classList.toggle("active");

            if (window.innerWidth <= 768) {
                if (overlay) {
                    overlay.classList.toggle("active");
                }
            }
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById("sidebar");
            const menuToggle = document.querySelector(".menu-toggle");
            const overlay = document.getElementById("sidebarOverlay");

            if (!sidebar || !menuToggle) return;

            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnToggle = menuToggle.contains(event.target);

            if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !isClickInsideSidebar && !isClickOnToggle) {
                sidebar.classList.remove('active');
                menuToggle.classList.remove('active');
                if (overlay) {
                    overlay.classList.remove('active');
                }
            }
        });

        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.tab-content').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected section
            document.getElementById(sectionName).classList.add('active');

            // Add active class to clicked tab button
            event.target.closest('.tab-button').classList.add('active');

            // Load feedbacks if feedback section is opened
            if (sectionName === 'feedback') {
                loadFeedbacks('all');
            }
        }

        let currentFilter = 'all';

        function loadFeedbacks(filter = 'all') {
            currentFilter = filter;
            const feedbackList = document.getElementById('feedbackList');
            feedbackList.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><h3>Loading feedback...</h3></div>';

            const url = filter === 'all' 
                ? '../controllers/getPendingFeedbacks.php'
                : `../controllers/getPendingFeedbacks.php?status=${filter}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFeedbacks(data.feedbacks, filter);
                        updatePendingCount(data.pending_count || 0);
                        updateFilterButtons(filter);
                    } else {
                        feedbackList.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Error loading feedbacks</h3></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    feedbackList.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Error loading feedbacks</h3></div>';
                });
        }

        function filterFeedbacks(status) {
            loadFeedbacks(status);
        }

        function updateFilterButtons(activeFilter) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = event.target.closest('.filter-btn');
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }

        function displayFeedbacks(feedbacks, filter = 'all') {
            const feedbackList = document.getElementById('feedbackList');

            if (feedbacks.length === 0) {
                let message = 'No feedback found.';
                if (filter === 'pending') message = 'No pending feedback. All feedback has been reviewed.';
                else if (filter === 'approved') message = 'No approved feedback yet.';
                else if (filter === 'rejected') message = 'No rejected feedback.';

                feedbackList.innerHTML = `<div class="empty-state"><i class="fas fa-inbox"></i><h3>${message}</h3></div>`;
                return;
            }

            feedbackList.innerHTML = '';

            feedbacks.forEach(feedback => {
                const card = document.createElement('div');
                card.className = `feedback-card ${feedback.status}`;

                const date = new Date(feedback.created_at);
                const formattedDate = date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                card.innerHTML = `
                    <div class="feedback-header">
                        <div class="feedback-info">
                            <h4>${escapeHtml(feedback.patient_name)}</h4>
                            <div class="feedback-meta">${formattedDate}</div>
                            ${feedback.appointment_id ? `<div class="feedback-meta">Appointment ID: ${feedback.appointment_id}</div>` : ''}
                        </div>
                        <span class="feedback-status-badge ${feedback.status}">${feedback.status}</span>
                    </div>
                    <div class="feedback-text">${escapeHtml(feedback.feedback_text)}</div>
                    <div class="feedback-actions">
                        ${feedback.status === 'pending' ? `
                            <button class="btn-action btn-approve" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'approved')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-action btn-reject" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'rejected')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        ` : ''}
                        ${feedback.status === 'rejected' ? `
                            <button class="btn-action btn-approve" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'approved')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        ` : ''}
                        ${feedback.status === 'approved' ? `
                            <button class="btn-action btn-reject" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'rejected')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        ` : ''}
                    </div>
                `;

                feedbackList.appendChild(card);
            });
        }

        function updateFeedbackStatus(feedbackId, status) {
            if (!confirm(`Are you sure you want to ${status === 'approved' ? 'approve' : 'reject'} this feedback?`)) {
                return;
            }

            fetch('../controllers/updateFeedbackStatus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `feedback_id=${feedbackId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadFeedbacks(currentFilter);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update feedback status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating feedback status');
            });
        }

        function updatePendingCount(count) {
            const countElement = document.getElementById('pendingCount');
            if (countElement) {
                if (count > 0) {
                    countElement.textContent = count;
                    countElement.style.display = 'inline-block';
                } else {
                    countElement.style.display = 'none';
                }
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Load pending count on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetch('../controllers/getPendingFeedbacks.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updatePendingCount(data.pending_count || 0);
                    }
                })
                .catch(error => console.error('Error loading pending count:', error));
        });
    </script>
</body>
</html>
