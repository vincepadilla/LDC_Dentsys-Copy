<?php
define("TITLE", "Learn More About Landero Dental Clinic");
include_once('../layouts/header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <title><?php echo TITLE; ?></title>
    <style>
        :root {
            --primary-color: #48A6A7;
            --secondary-color: #264653;
            --accent-color: rgb(255, 226, 151);
            --light-color: #F2EFE7;
            --dark-color: #343a40;
            --text-color: #333;
            --white: #fff;
            --shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: "Poppins", sans-serif;
            background-color: var(--light-color);
            margin: 0;
            padding: 0;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 5rem 1rem;
            text-align: center;
        }

        .hero-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .btn {
            display: inline-block;
            background-color: var(--accent-color);
            color: var(--secondary-color);
            padding: 0.8rem 1.8rem;
            border-radius: 50px;
            font-weight: bold;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h2 {
            color: var(--secondary-color);
            text-align: center;
            font-size: 2rem;
            margin: 3rem 0 2rem;
        }

        .features-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .feature-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--shadow);
            width: 280px;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid #e0e0e0;
            padding: 2rem;
            text-align: center;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .feature-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: var(--secondary-color);
        }

        .how-it-works {
            background-color: var(--white);
            padding: 3rem 1rem;
            margin: 3rem 0;
        }

        .step {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .step-number {
            background-color: var(--primary-color);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            flex-shrink: 0;
            margin-right: 1.5rem;
        }

        .step-content {
            text-align: left;
        }

        .step-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        /* FAQ Section Styles */
        .faq-section {
            background-color: var(--primary-color);
            color: white;
            padding: 3rem 1rem;
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: var(--transition);
        }

        .faq-question {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .faq-question:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }

        .faq-item.active .faq-answer {
            padding: 0 1.5rem 1.5rem;
            max-height: 500px;
        }

        .faq-toggle {
            transition: transform 0.3s ease;
        }

        .faq-item.active .faq-toggle {
            transform: rotate(180deg);
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
            }
            
            .step-number {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .feature-card {
                width: 90%;
                max-width: 350px;
            }
        }
    </style>
</head>

<body>

    <!-- Hero Section -->
    <section class="hero-section">
        <h1 class="hero-title">Your Smile Journey Starts Here</h1>
        <p class="hero-subtitle">Dental Buddies PH is revolutionizing dental care in the Philippines with our convenient online appointment system designed to make dental visits hassle-free.</p>
        <a href="#how-it-works" class="btn">See How It Works</a>
    </section>

    <!-- Key Features -->
    <div class="container">
        <h2>Why Choose Dental Buddies PH?</h2>
        <div class="features-container">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3 class="feature-title">Easy Online Booking</h3>
                <p>Book appointments 24/7 from your phone or computer - no need to call during clinic hours.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="feature-title">Reminder Alerts</h3>
                <p>Get email reminders so you never miss your dental appointment.</p>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <h2>How It Works</h2>
            
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3 class="step-title">Create Your Account</h3>
                    <p>Sign up in just a minute with your basic information. After Login fill and update the required information</p>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3 class="step-title">Book Your Appointment</h3>
                    <p>Select your preferred date and time with just a few clicks. Receive instant confirmation.</p>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3 class="step-title">Visit the Clinic</h3>
                    <p>Arrive at your scheduled time with all your details already in the system.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h2 style="color: white;">Frequently Asked Questions</h2>
            <div class="faq-container">
                <!-- FAQ Item 1 -->
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How do I book an appointment?</span>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Simply create an account, log in, and use our booking system to select services and your preferred dentist, date, and time. You'll receive a confirmation email once your appointment status is confirmed.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Will the appointment fee be deducted from the total amount of my service?</span>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, the appointment fee will be deducted from the total cost of your selected service. It serves as a partial payment to secure your slot.</p>
                    </div>
                </div>
                
                <!-- FAQ Item 2 -->
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Is there a cancellation fee?</span>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Cancellations Policy:
                            If your appointment status is Pending, you may cancel it within 24 hours without any charge.
                            However, if the status is already Confirmed, a 10% cancellation fee will be deducted from your refunded appointment fee.
                            Late cancellations (less than 24 hours before the appointment) will also incur a 10% fee. </p>
                    </div>
                </div>
                
                <!-- FAQ Item 3 -->
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What payment methods are accepted for Appointment Fee?</span>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Gcash and Paymaya</p>
                    </div>
                </div>
                
                <!-- FAQ Item 4 -->
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How do I reschedule my appointment?</span>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Click account, go to "Your Recent Appointment", and select the option to reschedule. You can choose a new date and time based on available slots.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What if I'm running late to my appointment?</span>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Please call the clinic directly if you're running late. They may still accommodate you or ask to reschedule. Being more than 15 minutes late will result in cancellation of your slot.</p>
                    </div>
                </div>

                <!-- New FAQ item -->
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What if I miss my appointment and it's not showing?</span>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <p>If you miss your appointment twice, it will be automatically cancelled. Additionally, 10% will be deducted from your appointment fee as a penalty.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <div class="container" style="text-align: center; margin: 4rem auto;">
        <h2>Ready to Experience Better Dental Care?</h2>
        <a href="index.php" class="btn" style="font-size: 1.2rem; padding: 1rem 2.5rem;">Get Started Now</a>
    </div>

    <script>
        // FAQ Dropdown Functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                faqItem.classList.toggle('active');
                
                // Close other open FAQs
                document.querySelectorAll('.faq-item').forEach(item => {
                    if (item !== faqItem && item.classList.contains('active')) {
                        item.classList.remove('active');
                    }
                });
            });
        });
    </script>

<?php include_once('../layouts/footer.php'); ?>

</body>
</html>