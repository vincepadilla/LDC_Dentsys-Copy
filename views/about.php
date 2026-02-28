<?php
define("TITLE", "About Us | Dental Buddies PH");
include_once('header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
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
            font-family: "Poppins", sans-serif;
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

        /* Buttons */
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

        /* Container */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Headings */
        h2 {
            color: var(--secondary-color);
            text-align: center;
            font-size: clamp(1.5rem, 4vw, 2rem);
            margin: 2rem 0 1.5rem;
            line-height: 1.3;
        }

        /* About Content */
        .about-text {
            max-width: 800px;
            margin: 0 auto 2rem;
            padding: 0 1rem;
            text-align: center;
        }

        .about-text p {
            margin-bottom: 1.5rem;
            font-size: clamp(0.95rem, 3vw, 1.05rem);
        }

        /* Mission & Vision */
        .mission-vision {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin: 2rem 0;
        }

        .mv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .mv-card {
            background-color: var(--light-color);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 5px solid var(--primary-color);
        }

        .mv-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            text-align: center;
        }

        .mv-card h3 i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .mv-card p {
            font-size: clamp(0.9rem, 3vw, 1rem);
            text-align: center;
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

        /* Responsive Adjustments */
        @media (max-width: 480px) {
            .hero-section {
                padding: 2rem 1rem;
            }
            
            .mission-vision {
                padding: 1rem;
            }
            
            .mv-card {
                padding: 1.2rem;
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
        <h1 class="hero-title">Our Story</h1>
        <p class="hero-subtitle">Dental Buddies PH was founded with a simple mission: to make quality dental care accessible and convenient for every Filipino.</p>
        <a href="#our-mission" class="btn">Learn About Our Mission</a>
    </section>

    <!-- About Content -->
    <div class="container">
        <div class="about-text">
            <h2>Who We Are</h2>
            <p>Dental Buddies PH is a trusted dental clinic founded in 2011. Our team is led by two experienced and compassionate dentists — Dr. Allen Lobregat and Dr. Carol Ong — who are committed to providing excellent and affordable dental care.</p>
        </div>

        <!-- Mission & Vision -->
        <div class="mission-vision" id="our-mission">
            <h2>Our Mission & Vision</h2>
            <div class="mv-grid">
                <div class="mv-card">
                    <h3><i class="fas fa-bullseye"></i> Our Mission</h3>
                    <p>To provide exceptional dental care in a comfortable environment, using the latest technology to ensure the best outcomes for our patients.</p>
                </div>
                <div class="mv-card">
                    <h3><i class="fas fa-eye"></i> Our Vision</h3>
                    <p>To be Metro Manila's most trusted dental clinic, known for our compassionate care and commitment to dental health education.</p>
                </div>
                <div class="mv-card">
                    <h3><i class="fas fa-heart"></i> Our Values</h3>
                    <p>Patient-centered care, honesty in treatment recommendations, and continuous improvement in our services and techniques.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="cta-section">
        <h2>Join Our Growing Family of Smiling Patients</h2>
        <a href="index.php" class="btn">Book Your Appointment Now</a>
    </div>

<?php include_once('footer.php'); ?>

</body>
</html>