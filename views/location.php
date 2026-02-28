<?php
define("TITLE", "Our Location | Dental Buddies PH");
include_once('../layouts/header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Koulen&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=arrow_forward"/>

    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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
            font-family: 'Metropolis';
            background-color: var(--light-color);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 1rem;
            text-align: center;
        }

        .hero-title {
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            font-size: clamp(1rem, 3vw, 1.2rem);
            max-width: 800px;
            margin: 0 auto 2rem;
            padding: 0 1rem;
        }

        /* Container */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Location Content */
        .location-content {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin: 2rem 0;
            align-items: center;
        }

        .map-container {
            flex: 1;
            min-width: 300px;
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .location-info {
            flex: 1;
            min-width: 300px;
        }

        .info-card {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .info-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card h3 i {
            font-size: 1.2rem;
        }

        .directions {
            margin-top: 2rem;
        }

        .directions h3 {
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .directions ol {
            padding-left: 1.5rem;
        }

        .directions li {
            margin-bottom: 0.8rem;
        }

        /* Hours Section */
        .hours-section {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 1rem;
            margin: 3rem 0;
            border-radius: 10px;
        }

        .hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .hours-card {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .hours-card h4 {
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

         /* Call to Action */
        .cta-section {
            text-align: center;
            margin: 3rem auto;
            padding: 0 1rem;
        }

        .cta-section h2 {
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            background-color: var(--accent-color);
            color: var(--secondary-color);
            padding: 0.7rem 1.5rem;
            border-radius: 50px;
            font-weight: bold;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: clamp(0.9rem, 3vw, 1rem);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .location-content {
                flex-direction: column;
            }
            
            .map-container {
                order: -1;
                height: 300px;
            }
            
            .info-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .hero-section {
                padding: 2rem 1rem;
            }
            
            .hours-card {
                padding: 1rem;
            }

            .btn {
                padding: 0.6rem 1.3rem;
            }
        }
    </style>
</head>

<body>

    <!-- Hero Section -->
    <section class="hero-section">
        <h1 class="hero-title">Our Location</h1>
        <p class="hero-subtitle">Visit us at our conveniently located dental clinic in Metro Manila</p>
    </section>

    <!-- Location Content -->
    <div class="container">
        <div class="location-content">
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3861.234567890123!2d121.01234567890123!3d14.56789012345678!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTTCsDM0JzA0LjQiTiAxMjHCsDAwJzQ1LjYiRQ!5e0!3m2!1sen!2sph!4v1234567890123!5m2!1sen!2sph" 
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            
            <div class="location-info">
                <div class="info-card">
                    <h3><i class="fas fa-map-marker-alt"></i> Address</h3>
                    <p>Doroteo Jose St, Santa Cruz<br>
                    Metro Manila, Quezon City 1003</p>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-phone-alt"></i> Contact</h3>
                    <p>Phone: 092286119877<br>
                    Email: dentalbuddies@gmail.com</p>
                </div>
                
            </div>
        </div>
    </div>


    <!-- Call to Action -->
       <div class="cta-section">
        <h2>Ready to Visit Us?</h2>
        <a href="index.php" class="btn">Book Your Appointment Now</a>
    </div>

    <?php include_once('../layouts/footer.php'); ?>

</body>
</html>