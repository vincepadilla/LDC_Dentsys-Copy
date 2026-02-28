<?php
session_start();
include_once("../database/config.php");

// Redirect if not logged in or not admin
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if admin is verified
if (empty($_SESSION['admin_verified'])) {
    header("Location: admin_verify.php");
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
    // Remove any escaped slashes that might have been stored incorrectly
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
    <style>
        :root {
            --primary-color: #48A6A7;
            --secondary-color: #264653;
            --accent-color: #e9c46a;
            --light-color: #F2EFE7;
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
            overflow: hidden;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-color);
            height: 100vh;
            padding: 15px;
            margin: 0;
            overflow-y: auto;
        }

        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
        }

        .content-header {
            background-color: var(--secondary-color);
            padding: 20px 25px;
            border-radius: 16px 16px 0 0;
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .content-header h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .content-body {
            background: white;
            border-radius: 0 0 16px 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            flex: 1;
            overflow-y: auto;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .section-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            top: 2px;
        }

        .tab-btn:hover {
            color: var(--primary-color);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(72, 166, 167, 0.1);
        }

        .btn {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background-color: #3a8586;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(72, 166, 167, 0.3);
        }

        .btn-save-all {
            width: 100%;
            justify-content: center;
            margin-top: 20px;
            padding: 15px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .feedback-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .feedback-item.pending {
            border-left: 4px solid #f59e0b;
        }

        .feedback-item.approved {
            border-left: 4px solid #10b981;
        }

        .feedback-item.rejected {
            border-left: 4px solid #ef4444;
        }

        .feedback-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .feedback-patient-info {
            flex: 1;
        }

        .feedback-patient-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .feedback-date {
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: 5px;
        }

        .feedback-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .feedback-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .feedback-status.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .feedback-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .feedback-text {
            color: #374151;
            line-height: 1.6;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .feedback-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-approve {
            background-color: var(--success);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-approve:hover {
            background-color: #238a7d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(42, 157, 143, 0.3);
        }

        .btn-reject {
            background-color: #e76f51;
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-reject:hover {
            background-color: #d65a3f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 111, 81, 0.3);
        }

        .btn-view-approved {
            background-color: var(--text-light);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .btn-view-approved:hover {
            background-color: var(--dark-color);
        }

        .no-feedback {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .no-feedback i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.4;
            color: #9ca3af;
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .content-body {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .section-tabs {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="content-container">
        <div class="content-header">
            <h1><i class="fas fa-edit"></i> Edit Website Content</h1>
            <a href="admin_selection.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Selection
            </a>
        </div>

        <div class="content-body">
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

            <div class="section-tabs">
                <button class="tab-btn active" onclick="showTab('hero')">
                    <i class="fas fa-home"></i> Hero Section
                </button>
                <button class="tab-btn" onclick="showTab('services')">
                    <i class="fas fa-teeth"></i> Services
                </button>
                <button class="tab-btn" onclick="showTab('contact')">
                    <i class="fas fa-envelope"></i> Contact
                </button>
                <button class="tab-btn" onclick="showTab('location')">
                    <i class="fas fa-map-marker-alt"></i> Location
                </button>
                <button class="tab-btn" onclick="showTab('dentist')">
                    <i class="fas fa-user-doctor"></i> Dentist
                </button>
                <button class="tab-btn" onclick="showTab('feedback')">
                    <i class="fas fa-comments"></i> Patient Feedback
                    <span id="pendingCount" style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 8px; display: none;">0</span>
                </button>
            </div>

            <form id="contentForm" action="../controllers/updateContent.php" method="POST">
                <!-- Hero Section -->
                <div id="hero" class="tab-content active">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-home"></i> Hero Section Content
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="hero_title">Hero Title</label>
                                <input type="text" name="hero_title" id="hero_title" 
                                       value="<?php echo htmlspecialchars($contentData['hero_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="hero_subtitle">Hero Subtitle</label>
                                <textarea name="hero_subtitle" id="hero_subtitle" required><?php echo htmlspecialchars($contentData['hero_subtitle']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Services Section -->
                <div id="services" class="tab-content">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-teeth"></i> Services Section
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="services_title">Services Title</label>
                                <input type="text" name="services_title" id="services_title" 
                                       value="<?php echo htmlspecialchars($contentData['services_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="services_subtitle">Services Subtitle</label>
                                <textarea name="services_subtitle" id="services_subtitle" required><?php echo htmlspecialchars($contentData['services_subtitle']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Section -->
                <div id="contact" class="tab-content">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-envelope"></i> Contact Information
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="contact_title">Contact Section Title</label>
                                <input type="text" name="contact_title" id="contact_title" 
                                       value="<?php echo htmlspecialchars($contentData['contact_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_subtitle">Contact Section Subtitle</label>
                                <textarea name="contact_subtitle" id="contact_subtitle" required><?php echo htmlspecialchars($contentData['contact_subtitle']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="contact_help_title">Help Title</label>
                                <input type="text" name="contact_help_title" id="contact_help_title" 
                                       value="<?php echo htmlspecialchars($contentData['contact_help_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_help_text">Help Text</label>
                                <textarea name="contact_help_text" id="contact_help_text" required><?php echo htmlspecialchars($contentData['contact_help_text']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="contact_hours">Operating Hours</label>
                                <input type="text" name="contact_hours" id="contact_hours" 
                                       value="<?php echo htmlspecialchars($contentData['contact_hours']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_phone">Phone Number</label>
                                <input type="text" name="contact_phone" id="contact_phone" 
                                       value="<?php echo htmlspecialchars($contentData['contact_phone']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_email">Email Address</label>
                                <input type="email" name="contact_email" id="contact_email" 
                                       value="<?php echo htmlspecialchars($contentData['contact_email']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location Section -->
                <div id="location" class="tab-content">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-map-marker-alt"></i> Location Information
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="location_title">Location Section Title</label>
                                <input type="text" name="location_title" id="location_title" 
                                       value="<?php echo htmlspecialchars($contentData['location_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="location_subtitle">Location Section Subtitle</label>
                                <textarea name="location_subtitle" id="location_subtitle" required><?php echo htmlspecialchars($contentData['location_subtitle']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="location_comembo">Comembo Branch Address</label>
                                <input type="text" name="location_comembo" id="location_comembo" 
                                       value="<?php echo htmlspecialchars($contentData['location_comembo']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="location_taytay">Taytay Branch Address</label>
                                <input type="text" name="location_taytay" id="location_taytay" 
                                       value="<?php echo htmlspecialchars($contentData['location_taytay']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dentist Section -->
                <div id="dentist" class="tab-content">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-doctor"></i> Dentist Information
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="dentist_title">Dentist Section Title</label>
                                <input type="text" name="dentist_title" id="dentist_title" 
                                       value="<?php echo htmlspecialchars($contentData['dentist_title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="dentist_subtitle">Dentist Section Subtitle</label>
                                <textarea name="dentist_subtitle" id="dentist_subtitle" required><?php echo htmlspecialchars($contentData['dentist_subtitle']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="dentist_name">Dentist Name</label>
                                <input type="text" name="dentist_name" id="dentist_name" 
                                       value="<?php echo htmlspecialchars($contentData['dentist_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="dentist_specialty">Specialty</label>
                                <input type="text" name="dentist_specialty" id="dentist_specialty" 
                                       value="<?php echo htmlspecialchars($contentData['dentist_specialty']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="dentist_experience">Experience Description</label>
                                <textarea name="dentist_experience" id="dentist_experience" required><?php echo htmlspecialchars($contentData['dentist_experience']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Management Section -->
                <div id="feedback" class="tab-content">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-comments"></i> Patient Feedback Management
                        </div>
                        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="btn-view-approved" onclick="filterFeedbacks('all')" id="filter-all">
                                <i class="fas fa-list"></i> All Feedback
                            </button>
                            <button class="btn-view-approved" onclick="filterFeedbacks('pending')" id="filter-pending" style="background: var(--accent-color);">
                                <i class="fas fa-clock"></i> Pending
                            </button>
                            <button class="btn-view-approved" onclick="filterFeedbacks('approved')" id="filter-approved" style="background: var(--success);">
                                <i class="fas fa-check"></i> Approved
                            </button>
                            <button class="btn-view-approved" onclick="filterFeedbacks('rejected')" id="filter-rejected" style="background: #e76f51;">
                                <i class="fas fa-times"></i> Rejected
                            </button>
                        </div>
                        <div id="feedbackList" style="margin-top: 20px;">
                            <div style="text-align: center; padding: 40px; color: var(--text-light);">
                                <i class="fas fa-spinner fa-spin" style="font-size: 30px; margin-bottom: 15px;"></i>
                                <p>Loading feedback...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-save-all">
                    <i class="fas fa-save"></i> Save All Changes
                </button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.closest('.tab-btn').classList.add('active');
            
            // Load feedbacks if feedback tab is opened
            if (tabName === 'feedback') {
                loadFeedbacks();
            }
        }

        let currentFilter = 'all';

        function loadFeedbacks(filter = 'all') {
            currentFilter = filter;
            const feedbackList = document.getElementById('feedbackList');
            feedbackList.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-light);"><i class="fas fa-spinner fa-spin" style="font-size: 30px; margin-bottom: 15px;"></i><p>Loading feedback...</p></div>';
            
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
                        feedbackList.innerHTML = '<div class="no-feedback"><i class="fas fa-exclamation-circle"></i><p>Error loading feedbacks</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    feedbackList.innerHTML = '<div class="no-feedback"><i class="fas fa-exclamation-circle"></i><p>Error loading feedbacks</p></div>';
                });
        }

        function filterFeedbacks(status) {
            loadFeedbacks(status);
        }

        function updateFilterButtons(activeFilter) {
            document.querySelectorAll('[id^="filter-"]').forEach(btn => {
                btn.style.opacity = '0.7';
                btn.style.transform = 'scale(1)';
            });
            const activeBtn = document.getElementById(`filter-${activeFilter}`);
            if (activeBtn) {
                activeBtn.style.opacity = '1';
                activeBtn.style.transform = 'scale(1.05)';
            }
        }

        function displayFeedbacks(feedbacks, filter = 'all') {
            const feedbackList = document.getElementById('feedbackList');
            
            if (feedbacks.length === 0) {
                let message = 'No feedback found.';
                if (filter === 'pending') message = 'No pending feedback. All feedback has been reviewed.';
                else if (filter === 'approved') message = 'No approved feedback yet.';
                else if (filter === 'rejected') message = 'No rejected feedback.';
                
                feedbackList.innerHTML = `<div class="no-feedback"><i class="fas fa-inbox"></i><h3>${message}</h3></div>`;
                return;
            }
            
            feedbackList.innerHTML = '';
            
            feedbacks.forEach(feedback => {
                const item = document.createElement('div');
                item.className = `feedback-item ${feedback.status}`;
                item.id = `feedback-${feedback.feedback_id}`;
                
                const date = new Date(feedback.created_at);
                const formattedDate = date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                item.innerHTML = `
                    <div class="feedback-header-info">
                        <div class="feedback-patient-info">
                            <div class="feedback-patient-name">${escapeHtml(feedback.patient_name)}</div>
                            <div class="feedback-date">${formattedDate}</div>
                            ${feedback.appointment_id ? `<div class="feedback-date">Appointment ID: ${feedback.appointment_id}</div>` : ''}
                        </div>
                        <span class="feedback-status ${feedback.status}">${feedback.status}</span>
                    </div>
                    <div class="feedback-text">${escapeHtml(feedback.feedback_text)}</div>
                    <div class="feedback-actions">
                        ${feedback.status === 'pending' ? `
                            <button class="btn-approve" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'approved')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-reject" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'rejected')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        ` : ''}
                        ${feedback.status === 'rejected' ? `
                            <button class="btn-approve" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'approved')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        ` : ''}
                        ${feedback.status === 'approved' ? `
                            <button class="btn-reject" onclick="updateFeedbackStatus(${feedback.feedback_id}, 'rejected')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        ` : ''}
                    </div>
                `;
                
                feedbackList.appendChild(item);
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
                    // Remove the feedback item or reload
                    const item = document.getElementById(`feedback-${feedbackId}`);
                    if (item) {
                        item.style.opacity = '0.5';
                        setTimeout(() => {
                            loadFeedbacks();
                        }, 500);
                    } else {
                        loadFeedbacks(currentFilter);
                    }
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
