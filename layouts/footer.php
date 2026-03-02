<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landero Dental Clinic - Footer</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #48A6A7;
            --primary-dark: #3a8586;
            --secondary-color: #264653;
            --accent-color: #e9c46a;
            --light-color: #F2EFE7;
            --dark-color: #343a40;
            --text-color: #333;
            --text-light: #777;
            --white: #fff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Poppins", sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-color);
            min-height: 100vh;
            flex-direction: column;
        }

        .footer-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Footer Styles */
        footer {
            background: var(--secondary-color);
            color: var(--white);
            padding: 70px 0 20px;
            margin-top: auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 50px;
        }

        .footer-col h3 {
            margin-bottom: 25px;
            font-size: 1.3rem;
            position: relative;
            padding-bottom: 10px;
            color: var(--white);
        }

        .footer-col h3:after {
            content: '';
            position: absolute;
            width: 50px;
            height: 2px;
            background-color: var(--primary-color);
            bottom: 0;
            left: 0;
        }

        .footer-col p {
            margin-bottom: 25px;
            line-height: 1.7;
            opacity: 0.8;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .footer-logo i {
            font-size: 1.8rem;
            color: var(--primary-color);
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: var(--white);
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .social-links a:hover {
            background-color: var(--primary-color);
            transform: translateY(-3px);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            opacity: 0.8;
            transition: var(--transition);
            position: relative;
            padding-left: 0;
        }

        .footer-links a:before {
            content: '›';
            margin-right: 8px;
            color: var(--primary-color);
            transition: var(--transition);
        }

        .footer-links a:hover {
            opacity: 1;
            padding-left: 5px;
            color: var(--primary-color);
        }

        .contact-info {
            list-style: none;
        }

        .contact-info li {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }

        .contact-info i {
            margin-right: 15px;
            margin-top: 4px;
            color: var(--primary-color);
            font-size: 1.1rem;
            min-width: 20px;
        }

        .contact-info span {
            opacity: 0.8;
            line-height: 1.6;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0.7;
        }

        /* Demo button styles */
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 16px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .footer-grid {
                gap: 30px;
            }
            
            .demo-content {
                padding: 40px 20px;
            }
            
            .demo-content h1 {
                font-size: 2rem;
            }
            
            .demo-content p {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
            
            .demo-content h1 {
                font-size: 1.8rem;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <i class="fas fa-tooth"></i>
                        <h3>Landero Dental Clinic</h3>
                    </div>
                    <p>Providing exceptional dental care with a personal touch since 2011.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <!-- PHP condition for login/account link would go here -->
                        <li><a href="/login">Login</a></li>
                        <li><a href="/#services">Services</a></li>
                        <li><a href="/#dentists">Dentists</a></li>
                        <li><a href="/about">About Us</a></li>
                        <li><a href="/blogs">Blogs</a></li>
                        <li><a href="/#contact">Contact</a></li>
                        <li><a href="/location">Location</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Contact Us</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Anahaw St. Comembo Taguig City</span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span>09228611987</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>landero@gmail.com</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Mon-Sun: 8AM-8PM</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Landero Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
