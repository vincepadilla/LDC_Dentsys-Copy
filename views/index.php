<?php
session_start();
include_once('../database/config.php');
include_once('chat.php');

$isLoggedIn = isset($_SESSION['valid'], $_SESSION['userID']) && $_SESSION['valid'] === true;

$fname = $lname = $email = $phone = $birthdate = $gender = $age = $address = '';

$todayStr = date('Y-m-d');
$oneMonthLater = date('Y-m-d', strtotime('+1 month'));

if ($isLoggedIn) {
    $user_id = $_SESSION['userID'];

    $query = "
        SELECT ua.email, ua.first_name, ua.last_name, ua.phone,
               ua.birthdate, ua.gender, ua.address
        FROM user_account ua
        WHERE ua.user_id = ?
        LIMIT 1
    ";

    if ($stmt = $con->prepare($query)) {
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $stmt->bind_result($email, $fname, $lname, $phone, $birthdate, $gender, $address);
        $stmt->fetch();
        $stmt->close();

        if (!empty($birthdate)) {
            try {
                $birthDateObj = new DateTime($birthdate);
                $todayObj = new DateTime();
                $age = $todayObj->diff($birthDateObj)->y;
            } catch (Exception $e) {
                $age = '';
            }
        }
    }
}

// ✅ Override with POST data if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $fname = htmlspecialchars($_POST['fname'] ?? $fname);
    $lname = htmlspecialchars($_POST['lname'] ?? $lname);
    $email = htmlspecialchars($_POST['email'] ?? $email);
    $phone = htmlspecialchars($_POST['phone'] ?? $phone);
    $birthdate = htmlspecialchars($_POST['birthdate'] ?? $birthdate);
    $gender = htmlspecialchars($_POST['gender'] ?? $gender);
    $address = htmlspecialchars($_POST['address'] ?? $address);

    // Recalculate age if birthdate is updated
    if (!empty($birthdate)) {
        try {
            $birthDateObj = new DateTime($birthdate);
            $todayObj = new DateTime();
            $age = $todayObj->diff($birthDateObj)->y;
        } catch (Exception $e) {
            $age = '';
        }
    }
}

// ✅ Load website content from database
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

// Get all content from database
$contentQuery = "SELECT content_key, content_value FROM site_content";
$contentResult = mysqli_query($con, $contentQuery);
$siteContent = [];

while ($row = mysqli_fetch_assoc($contentResult)) {
    // Remove any escaped slashes that might have been stored incorrectly
    $siteContent[$row['content_key']] = stripslashes($row['content_value']);
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

// Merge defaults with database content
foreach ($defaults as $key => $defaultValue) {
    if (!isset($siteContent[$key]) || empty($siteContent[$key])) {
        $siteContent[$key] = $defaultValue;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landero Dental Clinic - Professional Dental Care</title>
    <link rel="stylesheet" href="../assets/css/styles.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .nav-links .login-status { display: inline-flex; align-items: center; margin-left: 8px; font-size: 18px; }

        .services-grid .service-card {
            overflow: hidden;
        }

        .service-image {
            width: 100%;
            height: 180px;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 18px;
            box-shadow: inset 0 0 40px rgba(0, 0, 0, 0.08);
        }

        .service-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        @media (max-width: 600px) {
            .service-image {
                height: 220px;
            }
        }

        /* Closure Modal Popup Styles */
        .closure-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease-out;
            backdrop-filter: blur(4px);
        }

        .closure-modal-overlay.show {
            opacity: 1;
        }

        .closure-modal-container {
            position: relative;
            z-index: 10001;
        }

        .closure-modal-content {
            background: white;
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.7) translateY(-50px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .closure-modal-overlay.show .closure-modal-content {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .closure-modal-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }

        .closure-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .closure-icon i {
            font-size: 40px;
            color: white;
        }

        .closure-modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .closure-modal-body {
            padding: 30px;
        }

        .closure-date-info,
        .closure-reason-info {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #dc2626;
        }

        .closure-date-info i,
        .closure-reason-info i {
            font-size: 24px;
            color: #dc2626;
            margin-top: 5px;
        }

        .closure-date-info strong,
        .closure-reason-info strong {
            display: block;
            color: #1f2937;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .closure-date-info p,
        .closure-reason-info p {
            margin: 0;
            color: #4b5563;
            font-size: 15px;
            line-height: 1.5;
        }

        .closure-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
        }

        .closure-badge.full-day {
            background: #fee2e2;
            color: #991b1b;
        }

        .closure-badge.no-new {
            background: #fef3c7;
            color: #92400e;
        }

        .closure-modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e5e7eb;
            text-align: right;
            background: #f9fafb;
            border-radius: 0 0 20px 20px;
        }

        .btn-close-modal {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-close-modal:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        @media (max-width: 600px) {
            .closure-modal-content {
                width: 95%;
                max-width: 95%;
                padding: 0;
            }

            .closure-modal-header {
                padding: 20px;
            }

            .closure-icon {
                width: 60px;
                height: 60px;
            }

            .closure-icon i {
                font-size: 30px;
            }

            .closure-modal-header h2 {
                font-size: 20px;
            }

            .closure-modal-body {
                padding: 20px;
            }

            .closure-date-info,
            .closure-reason-info {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Feedback Section Styles */
        .feedback-section {
            padding: 80px 0;
            background:rgb(255, 255, 255);
        }

        .feedback-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .feedback-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border-left: 4px solid var(--primary-color);
        }

        .feedback-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .feedback-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .feedback-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 20px;
            flex-shrink: 0;
        }

        .feedback-author {
            flex: 1;
        }

        .feedback-author-name {
            font-weight: 600;
            font-size: 16px;
            color: #111827;
            margin: 0 0 4px 0;
        }

        .feedback-date {
            font-size: 13px;
            color: #6B7280;
            margin: 0;
        }

        .feedback-text {
            color: #4B5563;
            line-height: 1.7;
            font-size: 15px;
            margin: 0;
        }

        .feedback-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #6B7280;
        }

        .feedback-empty i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .feedback-loading {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .feedback-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
<header>
    <div class="container">
        <nav class="navbar">
            <a href="#" class="logo">
                <img src="../assets/images/landerologo.png" alt="Landero Dental Clinic Logo">
                <span>Landero Dental Clinic</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="#services">Services</a></li>
                <li><a href="#dentists">Dentists</a></li>
                <li><a href="#contact">Contact</a></li>
                <?php if (isset($_SESSION['valid'])): ?>
                    <!-- Account link first -->
                    <li><a href="account.php" class="nav-btn">Account</a></li>
                <?php else: ?>
                    <!-- Login link for non-logged in users -->
                    <li><a href="/views/login.php" class="nav-btn">Login</a></li>
                <?php endif; ?>
            </ul>

            <div class="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
    </div>
</header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h1><?php echo htmlspecialchars($siteContent['hero_title']); ?></h1>
                <p><?php echo htmlspecialchars($siteContent['hero_subtitle']); ?></p>
                <div class="btn-container">
                    <a href="#services" class="btn btn-primary">Book an Appointment</a>
                    <a href="learnmore.php" class="btn btn-outline">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-title">
                <h2><?php echo htmlspecialchars($siteContent['services_title']); ?></h2>
                <p><?php echo htmlspecialchars($siteContent['services_subtitle']); ?></p>
            </div>
            
            <div class="services-grid">
                <div class="service-card" data-service="S001">
                    <div class="service-image">
                        <img src="../assets/images/generaldentistry.jpg" alt="General Dentistry service">
                    </div>
                    <h3>General Dentistry</h3>
                    <p>Regular checkups, cleanings, Fillings, and Preventive Care.</p>
                    <button class="service-book-btn">Book Appointment</button>
                </div>
                
                <div class="service-card" data-service="S002">
                    <div class="service-image">
                        <img src="../assets/images/ortho.jpg" alt="Orthodontics service">
                    </div>
                    <h3>Orthodontics</h3>
                    <p>Braces and aligners for a perfectly straight smile.</p>
                    <button class="service-book-btn">Book Appointment</button>
                </div>

                <div class="service-card" data-service="S003">
                    <div class="service-image">
                        <img src="../assets/images/oralsur2.jpg" alt="Oral Surgery service">
                    </div>
                    <h3>Oral Surgery</h3>
                    <p>Gentle extractions and surgical care for a healthier smile.</p>
                    <button class="service-book-btn">Book Appointment</button>
                </div>

                <div class="service-card" data-service="S004">
                    <div class="service-image">
                        <img src="../assets/images/endo.jpg" alt="Endodontics service">
                    </div>
                    <h3>Endodontics</h3>
                    <p>Save your natural teeth with expert root canal care.</p>
                    <button class="service-book-btn">Book Appointment</button>
                </div>

                <div class="service-card" data-service="S005">
                    <div class="service-image">
                        <img src="../assets/images/prosti.jpg" alt="Prosthodontics service">
                    </div>
                    <h3>Prosthodontics</h3>
                    <p>Bring back your perfect smile with natural-looking tooth replacements.</p>
                    <button class="service-book-btn">Book Appointment</button>
                </div>

            </div>

            <div class="text-center">
                <a href="view_services.php" class="btn btn-services">View All Services Description</a>
            </div>
        </div>
    </section>

    <section class="location-section" id="location">
        <div class="container">
            <div class="section-title">
                <h2><?php echo htmlspecialchars($siteContent['location_title']); ?></h2>
                <p><?php echo htmlspecialchars($siteContent['location_subtitle']); ?></p>
            </div>
            <div class="location-grid">
                <div class="map-wrapper">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3670.79139120257!2d121.06162947487168!3d14.549215285931064!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c9b949abde59%3A0x858201c5605ed9f2!2sLandero%20Dental%20Clinic!5e1!3m2!1sen!2sph!4v1763285594933!5m2!1sen!2sph"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div class="location-details">
                    <h3>Main Address</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i><span><strong>Comembo Branch: </strong><?php echo htmlspecialchars($siteContent['location_comembo']); ?></span></li>
                        <li><i class="fas fa-map-marker-alt"></i><span><strong>Taytay Branch: </strong><?php echo htmlspecialchars($siteContent['location_taytay']); ?></span></li>
                        <li><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($siteContent['contact_phone']); ?></span></li>
                        <li><i class="fas fa-envelope"></i><span><?php echo htmlspecialchars($siteContent['contact_email']); ?></span></li>
                    </ul>
                    <p style="margin-top: 20px;">Need detailed directions? Visit our full <a href="location.php" style="color:var(--primary-color); text-decoration: underline;">location page</a>.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="contact-section" id="contact-form">
        <div class="container">
            <div class="section-title">
                <h2><?php echo htmlspecialchars($siteContent['contact_title']); ?></h2>
                <p><?php echo htmlspecialchars($siteContent['contact_subtitle']); ?></p>
            </div>
            <div class="contact-grid">
                <div class="contact-info-card">
                    <h3><?php echo htmlspecialchars($siteContent['contact_help_title']); ?></h3>
                    <p><?php echo htmlspecialchars($siteContent['contact_help_text']); ?></p>
                    <ul style="list-style:none; padding:0; margin:20px 0 0;">
                        <li style="margin-bottom:12px;"><i class="fas fa-clock" style="color:var(--primary-color); margin-right:10px;"></i><?php echo htmlspecialchars($siteContent['contact_hours']); ?></li>
                        <li style="margin-bottom:12px;"><i class="fas fa-phone" style="color:var(--primary-color); margin-right:10px;"></i><?php echo htmlspecialchars($siteContent['contact_phone']); ?></li>
                        <li><i class="fas fa-envelope" style="color:var(--primary-color); margin-right:10px;"></i><?php echo htmlspecialchars($siteContent['contact_email']); ?></li>
                    </ul>
                </div>
                <div class="contact-form-card">
                    <form action="https://formspree.io/f/mkgkewyl" method="POST">
                        <div>
                            <label for="contact_name" style="display:block; margin-bottom:6px; font-weight:600;">Full Name</label>
                            <input type="text" id="contact_name" name="name" required>
                        </div>
                        <div>
                            <label for="contact_email" style="display:block; margin-bottom:6px; font-weight:600;">Email Address</label>
                            <input type="email" id="contact_email" name="email" placeholder="you@example.com" required>
                        </div>
                        <div>
                            <label for="contact_message" style="display:block; margin-bottom:6px; font-weight:600;">Message</label>
                            <textarea id="contact_message" name="message" placeholder="How can we assist you?" required></textarea>
                        </div>
                        <button type="submit">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    
    
    <!-- Testimonials -->
    <section class="testimonials" id="dentists">
        <div class="container">
            <div class="section-title">
                <h2><?php echo htmlspecialchars($siteContent['dentist_title']); ?></h2>
                <p><?php echo htmlspecialchars($siteContent['dentist_subtitle']); ?></p>
            </div>
            
            <div class="dentist-grid">
                <div class="dentist-card">
                    <div class="dentist-image">
                        <img src="../assets/images/dentisticon.png" alt="<?php echo htmlspecialchars($siteContent['dentist_name']); ?>">
                    </div>
                    <div class="dentist-info">
                        <h4><?php echo htmlspecialchars($siteContent['dentist_name']); ?></h4>
                        <p class="specialty"><?php echo htmlspecialchars($siteContent['dentist_specialty']); ?></p>
                        <p class="experience"><?php echo htmlspecialchars($siteContent['dentist_experience']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Patient Feedback Section -->
    <section class="feedback-section" id="feedback">
        <div class="container">
            <div class="section-title">
                <h2>Patient Feedback</h2>
                <p>See what our patients have to say about their experience</p>
            </div>
            
            <div class="feedback-grid" id="feedbackGrid">
                <div class="feedback-loading" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 30px; color: var(--primary-color);"></i>
                    <p style="margin-top: 15px; color: #6B7280;">Loading feedback...</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <i class="fas fa-tooth"></i>
                        <h3>Landero Dental Clinic</h3>
                    </div>
                    <p>Providing exceptional dental care with a personal touch since 2011.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/mlgalman.dmd" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/landero_dental/" target="_blank"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#services">Services</a></li>
                        <li><a href="#dentists">Dentists</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="blogs.php">Blogs</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="location.php">Location</a></li>
                    </ul>
                </div>
                
                <div class="footer-col" id="contact">
                    <h3>Contact Us</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($siteContent['location_comembo']); ?></span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($siteContent['contact_phone']); ?></span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($siteContent['contact_email']); ?></span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span><?php echo htmlspecialchars($siteContent['contact_hours']); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Landero Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Appointment Popup Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Book Your Appointment</h2>
            <form action="/payment" method="POST" id="appointmentForm">
                <input type="hidden" name="fname" value="<?php echo htmlspecialchars($fname); ?>">
                <input type="hidden" name="lname" value="<?php echo htmlspecialchars($lname); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender); ?>">
                <input type="hidden" name="address" value="<?php echo htmlspecialchars($address); ?>">
                <input type="hidden" name="birthdate" value="<?php echo htmlspecialchars($birthdate); ?>">
                <input type="hidden" name="age" value="<?php echo htmlspecialchars($age); ?>">
                
                 <!-- Service and Sub-Service side by side -->
            <div class="service-fields-row">
                <div class="form-group">
                    <label for="popup_service">Service Needed</label>
                    <select id="popup_service" name="service_id" required onchange="updateSubServices()" disabled>
                        <option value="">Select a service</option>
                        <option value="S001">General Dentistry</option>
                        <option value="S002">Orthodontics</option>
                        <option value="S003">Oral Surgery</option>
                        <option value="S004">Endodontics</option>
                        <option value="S005">Prosthodontics</option>
                    </select>
                </div>

                <div class="form-group" id="popup-sub-service-container" style="display: none;">
                    <label for="popup_sub_service">Sub-Service</label>
                    <select id="popup_sub_service" name="sub_service" required>
                        <option value="">Select a sub-service</option>
                    </select>
                </div>
            </div>

                <!-- Branch and Payment Method side by side -->
                <div class="service-fields-row">
                    <div class="form-group">
                        <label for="popup_branch">Select Branch</label>
                        <select id="popup_branch" name="branch" required>
                            <option value="">Select Branch</option>
                            <option value="comembo">Comembo Branch</option>
                            <option value="taytay">Taytay Rizal Branch</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="popup_payment_method">Payment Method</label>
                        <select id="popup_payment_method" name="payment_mode" required>
                            <option value="digital" selected>Digital Payment</option>
                            <option value="walkin">Walk-In Payment</option>
                        </select>
                    </div>
                </div>

                <!-- Preferred Date and Preferred Time side by side -->
                <div class="service-fields-row">
                    <div class="form-group" id="popup_date_group">
                        <label for="popup_date">Preferred Date</label>
                        <input type="date" id="popup_date" name="date"
                            min="<?php echo $today; ?>"
                            max="<?php echo $oneMonthLater; ?>"
                            required>
                    </div>

                    <div class="form-group" id="popup_time_group">
                        <label for="popup_time">Preferred Time</label>
                        <select id="popup_time" name="time" required>
                            <option value="">Select a time</option>
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
                </div>

                <!-- Additional Service Request -->
                <div class="form-group">
                    <label for="popup_request_note">Request</label>
                    <textarea
                        id="popup_request_note"
                        name="request_note"
                        rows="3"
                        placeholder="Example: After my cleaning, I’d like to request teeth whitening (if possible). Please advise the recommended order of procedures."></textarea>
                    <small class="appointment-modal-note">You may request another service. The dentist will confirm if it’s possible and advise the proper order of procedures.</small>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <div class="login-required-message" style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
                        <p style="margin: 0; color: #856404;">
                            <i class="fas fa-info-circle"></i> You need to <a href="login.php" style="color: #0066cc; text-decoration: underline;">log in</a> to book an appointment.
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="submit-btn">
                    <button type="submit" class="btn btn-primary" id="popupBookBtn" <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>BOOK APPOINTMENT</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>                    
   <script>
    // Mobile menu toggle
    document.querySelector('.menu-toggle').addEventListener('click', function() {
        document.querySelector('.nav-links').classList.toggle('active');
    });

    // Smooth scrolling
    document.querySelectorAll(".nav-links a").forEach(anchor => {
        anchor.addEventListener("click", function (event) {
            if (this.getAttribute("href").startsWith("#")) {
                event.preventDefault();
                const targetId = this.getAttribute("href").substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: "smooth" });
                }
                document.querySelector('.nav-links').classList.remove('active'); // close menu
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Set minimum date for appointment (tomorrow)
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);
        const dd = String(tomorrow.getDate()).padStart(2, '0');
        const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const yyyy = tomorrow.getFullYear();
        const minDate = yyyy + '-' + mm + '-' + dd;
        const popupDate = document.getElementById('popup_date');
        if (popupDate) popupDate.min = minDate;

        // Fetch and disable closed dates
        fetchClosedDates();

        // Initialize modal functionality
        initializeModal();
    });
    
    // Fetch closed dates and disable them in the date picker
    function fetchClosedDates() {
        fetch('../controllers/getClosedDates.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.closed_dates && data.closed_dates.length > 0) {
                    const popupDate = document.getElementById('popup_date');
                    if (popupDate) {
                        // Store closed dates for validation
                        window.closedDates = data.closed_dates;
                        
                        // Add event listener to disable closed dates
                        popupDate.addEventListener('input', function() {
                            const selectedDate = this.value;
                            const isClosed = data.closed_dates.some(closed => closed.date === selectedDate);
                            
                            if (isClosed) {
                                const closedDateInfo = data.closed_dates.find(closed => closed.date === selectedDate);
                                // Show animated popup and clear date
                                setTimeout(() => {
                                    showClosurePopup(selectedDate, closedDateInfo.reason || 'Clinic closure', closedDateInfo.closure_type || 'full_day');
                                    this.value = '';
                                }, 100);
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching closed dates:', error);
            });
    }
    
    // Show animated closure popup
    function showClosurePopup(date, reason, closureType) {
        // Format date for display
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'closure-modal-overlay';
        modalOverlay.id = 'closureModalOverlay';
        
        // Create modal container
        const modalContainer = document.createElement('div');
        modalContainer.className = 'closure-modal-container';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'closure-modal-content';
        
        const closureTypeBadge = closureType === 'full_day' 
            ? '<span class="closure-badge full-day">Full Day Closure</span>'
            : '<span class="closure-badge no-new">No New Appointments</span>';
        
        modalContent.innerHTML = `
            <div class="closure-modal-header">
                <div class="closure-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2>Clinic Closed</h2>
            </div>
            <div class="closure-modal-body">
                <div class="closure-date-info">
                    <i class="fas fa-calendar-times"></i>
                    <div>
                        <strong>Selected Date:</strong>
                        <p>${formattedDate}</p>
                    </div>
                </div>
                <div class="closure-reason-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Reason:</strong>
                        <p>${reason}</p>
                    </div>
                </div>
                ${closureTypeBadge}
            </div>
            <div class="closure-modal-footer">
                <button class="btn-close-modal" onclick="closeClosurePopup()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        `;
        
        modalContainer.appendChild(modalContent);
        modalOverlay.appendChild(modalContainer);
        document.body.appendChild(modalOverlay);
        
        // Animate in
        setTimeout(() => {
            modalOverlay.classList.add('show');
        }, 10);
    }
    
    // Close closure popup
    function closeClosurePopup() {
        const modalOverlay = document.getElementById('closureModalOverlay');
        if (modalOverlay) {
            modalOverlay.classList.remove('show');
            setTimeout(() => {
                modalOverlay.remove();
            }, 300);
        }
    }
    
    // Close popup when clicking outside
    document.addEventListener('click', function(event) {
        const modalOverlay = document.getElementById('closureModalOverlay');
        if (modalOverlay && event.target === modalOverlay) {
            closeClosurePopup();
        }
    });

    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>; 

    function initializeModal() {
        const modal = document.getElementById("appointmentModal");
        const serviceCards = document.querySelectorAll(".service-book-btn");
        const closeModal = document.querySelector(".close-modal");

        if (!modal || !closeModal) return;

        // Open modal only if logged in
        serviceCards.forEach(card => {
            card.addEventListener("click", function() {
                console.log("Clicked service button"); // Debug
                console.log("isLoggedIn:", isLoggedIn);

                if (!isLoggedIn) {
                    if (confirm("You need to log in before booking. Do you want to log in now?")) {
                        window.location.href = "login.php";
                    }
                    return; // stop modal from opening
                }

                const serviceCard = this.closest('.service-card');
                if (!serviceCard) {
                    console.log("Could not find closest service-card");
                    return;
                }
                const serviceId = serviceCard.getAttribute('data-service');
                console.log("Service ID:", serviceId);

                const serviceSelect = document.getElementById('popup_service');
                if (serviceSelect) {
                    serviceSelect.value = serviceId;
                    updateSubServices();
                }

                console.log("Opening modal...");
                modal.style.display = "block";
            });
        });

        // Close modal
        closeModal.addEventListener("click", function() {
            modal.style.display = "none";
        });

        // Close modal when clicking outside
        window.addEventListener("click", function(event) {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    }

    // Update sub-services function
    function updateSubServices() {
        const serviceSelect = document.getElementById("popup_service");
        if (!serviceSelect) return;

        const service = serviceSelect.value;
        const subServiceContainer = document.getElementById("popup-sub-service-container");
        const subServiceSelect = document.getElementById("popup_sub_service");
        if (!subServiceContainer || !subServiceSelect) return;

        subServiceSelect.innerHTML = '<option value="">Select a sub-service</option>';

        if (service === "S001") {
            subServiceSelect.innerHTML += '<option value="Checkups">Checkups</option>';
            subServiceSelect.innerHTML += '<option value="Oral Prophylaxis (Cleaning)">Oral Prophylaxis (Cleaning)</option>';
            subServiceSelect.innerHTML += '<option value="Fluoride Application">Flouride Application</option>';
            subServiceSelect.innerHTML += '<option value="Pit & Fissure Sealants">Pit & Fissure Sealants</option>';
            subServiceSelect.innerHTML += '<option value="Tooth Restoration (Pasta)">Tooth Restoration (Pasta)</option>';
            subServiceContainer.style.display = 'block';

        } else if (service === "S002") {
            subServiceSelect.innerHTML += '<option value="Braces">Braces</option>';
            subServiceSelect.innerHTML += '<option value="Retainers">Retainers</option>';
            subServiceContainer.style.display = 'block';

        } else if(service == "S003") {
            subServiceSelect.innerHTML += '<option value="Tooth Extraction (Bunot)">Tooth Extraction (Bunot)</option>';
            subServiceContainer.style.display = 'block';

        } else if(service == "S004") {
            subServiceSelect.innerHTML += '<option value="Root Canal Treatment">Root Canal Treatment</option>';
            subServiceContainer.style.display = 'block';

        } else if(service == "S005") {
            subServiceSelect.innerHTML += '<option value="Crowns">Crowns</option>';
            subServiceSelect.innerHTML += '<option value="Dentures">Dentures</option>';
            subServiceContainer.style.display = 'block';
        }
        
        else {
            subServiceContainer.style.display = 'none';
        }
    }

    // Time availability check for popup
    function checkAvailability() {
        var selectedDate = $("#popup_date").val();
        var closureWarningDiv = $("#closure-warning");
        
        // Remove any existing closure warning
        if (closureWarningDiv.length) {
            closureWarningDiv.remove();
        }
        
        if (selectedDate) {
            $.ajax({
                url: '../controllers/getAppointments.php',
                type: 'GET',
                data: { date: selectedDate },
                dataType: 'json',
                success: function(response) {
                    // Handle new response format
                    var bookedSlots = [];
                    var clinicClosed = false;
                    var closureReason = '';
                    var closureType = '';
                    
                    if (Array.isArray(response)) {
                        // Old format - just array of slots
                        bookedSlots = response;
                    } else {
                        // New format - object with closure info
                        bookedSlots = response.unavailable_slots || [];
                        clinicClosed = response.clinic_closed || false;
                        closureReason = response.closure_reason || '';
                        closureType = response.closure_type || '';
                    }
                    
                    // Show closure warning if clinic is closed
                    if (clinicClosed) {
                        var warningHtml = `
                            <div id="closure-warning" style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 5px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 20px;"></i>
                                <div>
                                    <strong>Clinic Closed</strong>
                                    <p style="margin: 5px 0 0 0;">${closureReason || 'The clinic is closed on this date. Please select another date.'}</p>
                                </div>
                            </div>
                        `;
                        $("#popup_date").after(warningHtml);
                        
                        // Disable time slot dropdown
                        $("#popup_time").prop("disabled", true);
                        $("#popup_time").val('');
                        $("#popup_time").html('<option value="">Clinic is closed on this date</option>');
                        
                        // Disable submit button
                        $("#popupBookBtn").prop("disabled", true);
                    } else {
                        // Enable time slot dropdown
                        $("#popup_time").prop("disabled", false);
                        
                        // Reset time slots
                        var timeSlotsHtml = `
                            <option value="">Select a time</option>
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
                        `;
                        $("#popup_time").html(timeSlotsHtml);
                        
                        // Disable booked/blocked slots
                        $("#popup_time option").prop("disabled", false);
                        bookedSlots.forEach(slot => {
                            $("#popup_time option[value='" + slot + "']").prop("disabled", true);
                        });
                        
                        if ($("#popup_time option:selected").prop("disabled")) {
                            $("#popup_time").val('');
                        }
                        
                        // Enable submit button if logged in
                        if (isLoggedIn) {
                            $("#popupBookBtn").prop("disabled", false);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching appointment data:", error);
                }
            });
        } else {
            $("#popup_time option").prop("disabled", false);
            $("#popup_time").val('');
            if (closureWarningDiv.length) {
                closureWarningDiv.remove();
            }
        }
    }

    const popupDateInput = document.getElementById('popup_date');
    if (popupDateInput) popupDateInput.addEventListener('change', checkAvailability);

    const appointmentForm = document.getElementById('appointmentForm');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function(e) {
            if (!isLoggedIn) {
                e.preventDefault();
                if (confirm("You need to log in before booking. Do you want to log in now?")) {
                    window.location.href = "login.php";
                }
                return;
            }

            const paymentModeEl = document.getElementById('popup_payment_method');
            const paymentMode = paymentModeEl ? paymentModeEl.value : 'digital';

            if (paymentMode === 'walkin') {
                // Route Walk-In Payment to walkin.php and allow submission without date/time
                appointmentForm.action = "/views/walkin.php";

                const dateEl = document.getElementById('popup_date');
                const timeEl = document.getElementById('popup_time');
                if (dateEl) dateEl.required = false;
                if (timeEl) timeEl.required = false;
                // No further validation here; backend walk-in flow will handle details.
            } else {
                // Digital Payment: keep existing payment.php flow
                appointmentForm.action = "/views/payment.php";
            }
        });
    }

    // Payment mode behavior: toggle date/time visibility + requirement
    (function initPaymentModeToggle() {
        const paymentModeEl = document.getElementById('popup_payment_method');
        const dateGroup = document.getElementById('popup_date_group');
        const timeGroup = document.getElementById('popup_time_group');
        const dateEl = document.getElementById('popup_date');
        const timeEl = document.getElementById('popup_time');

        if (!paymentModeEl || !dateGroup || !timeGroup || !dateEl || !timeEl) return;

        function setDateTimeVisible(visible) {
            dateGroup.style.display = visible ? '' : 'none';
            timeGroup.style.display = visible ? '' : 'none';

            // Digital Payment keeps current behavior: required fields, visible
            // Walk-In Payment: hide fields and remove required validation
            dateEl.required = !!visible;
            timeEl.required = !!visible;

            if (!visible) {
                // Clear any closure warning & re-enable controls
                const closureWarningDiv = document.getElementById('closure-warning');
                if (closureWarningDiv) closureWarningDiv.remove();
                $("#popup_time").prop("disabled", false);
                if (isLoggedIn) $("#popupBookBtn").prop("disabled", false);
            }
        }

        async function applyPaymentMode() {
            const mode = paymentModeEl.value;
            if (mode === 'walkin') {
                setDateTimeVisible(false);
                // Don't force user to pick date/time in the modal; walk-in flow handles it separately.
            } else {
                setDateTimeVisible(true);
                // Restore normal availability behavior if a date is selected
                if (dateEl.value) {
                    checkAvailability();
                }
            }
        }

        paymentModeEl.addEventListener('change', applyPaymentMode);
        applyPaymentMode(); // initialize
    })();
    // Load feedbacks for homepage
    async function loadFeedbacks() {
        try {
            const response = await fetch('../controllers/getFeedbacks.php');
            const data = await response.json();
            
            const feedbackGrid = document.getElementById('feedbackGrid');
            if (!feedbackGrid) return;
            
            if (data.success && data.feedbacks && data.feedbacks.length > 0) {
                feedbackGrid.innerHTML = '';
                
                data.feedbacks.forEach(feedback => {
                    const card = document.createElement('div');
                    card.className = 'feedback-card';
                    
                    // Get initials for avatar
                    const nameParts = feedback.patient_name.trim().split(' ');
                    const initials = nameParts.length >= 2 
                        ? (nameParts[0][0] + nameParts[nameParts.length - 1][0]).toUpperCase()
                        : nameParts[0][0].toUpperCase();
                    
                    // Format date
                    const date = new Date(feedback.created_at);
                    const formattedDate = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    
                    card.innerHTML = `
                        <div class="feedback-header">
                            <div class="feedback-avatar">${initials}</div>
                            <div class="feedback-author">
                                <h4 class="feedback-author-name">${escapeHtml(feedback.patient_name)}</h4>
                                <p class="feedback-date">${formattedDate}</p>
                            </div>
                        </div>
                        <p class="feedback-text">${escapeHtml(feedback.feedback_text)}</p>
                    `;
                    
                    feedbackGrid.appendChild(card);
                });
            } else {
                feedbackGrid.innerHTML = `
                    <div class="feedback-empty">
                        <i class="fas fa-comment-slash"></i>
                        <h3>No feedback yet</h3>
                        <p>Be the first to share your experience with us!</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading feedbacks:', error);
            const feedbackGrid = document.getElementById('feedbackGrid');
            if (feedbackGrid) {
                feedbackGrid.innerHTML = `
                    <div class="feedback-empty">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Unable to load feedback</h3>
                        <p>Please try again later.</p>
                    </div>
                `;
            }
        }
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Load feedbacks when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadFeedbacks();
    });
</script>

</body>
</html>
