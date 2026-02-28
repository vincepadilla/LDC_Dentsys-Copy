<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landero Dental Clinic</title>
    
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
        }

        a {
            text-decoration: none;
            color: inherit;
            font-weight: 600;
            transition: var(--transition);
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .nav-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            
        }

        /* Header Styles */
        header {
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 2px solid black;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
        }

        .navbar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar .logo span {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .logo img {
            width: 70px;
            height: auto;
            transition: var(--transition);
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        .navbar .logo p {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
            align-items: center;
        }

        .nav-links li a {
            font-weight: 600;
            font-size: 16px;
            position: relative;
            padding: 8px 0;
        }

        .nav-links li a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-color);
            transition: var(--transition);
        }

        .nav-links li a:hover:after {
            width: 100%;
        }

        .nav-links .active {
            color: var(--primary-color);
        }

        .menu-toggle {
            display: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        /* Demo Content Styles */
        .demo-content {
            padding: 60px 20px;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .demo-content h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--secondary-color);
        }

        .demo-content p {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 30px;
        }

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
        @media (max-width: 992px) {
            .demo-content h1 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .nav-links {
                position: fixed;
                top: 100px;
                left: -100%;
                width: 100%;
                height: calc(100vh - 100px);
                background: var(--white);
                flex-direction: column;
                align-items: center;
                padding-top: 40px;
                transition: var(--transition);
                gap: 0;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            }
            
            .nav-links.active {
                left: 0;
            }
            
            .nav-links li {
                margin: 15px 0;
            }
            
            .nav-links li a {
                font-size: 18px;
                padding: 10px 20px;
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
            .navbar .logo p {
                font-size: 18px;
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
    <!-- Header -->
    <header>
        <div class="nav-container">
            <nav class="navbar">
                <a href="#" class="logo">
                    <!-- Replace with your actual logo path -->
                    <img src="../assets/images/landerologo.png" alt="Landero Dental Clinic Logo">
                    <span>Landero Dental Clinic</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="/">Home</a></li>
                    <li><a href="/#services">Services</a></li>
                    <li><a href="/#dentists">Dentists</a></li>
                    <li><a href="/#contact">Contact</a></li>
                </ul>
                
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <script>
        // Mobile menu toggle
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });
        
        // Close mobile menu when clicking a link
        const navLinks = document.querySelectorAll('.nav-links a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                document.querySelector('.nav-links').classList.remove('active');
            });
        });

        // Smooth scrolling for anchor links
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".nav-links a").forEach(anchor => {
                anchor.addEventListener("click", function (event) {
                    if (this.getAttribute("href").startsWith("#")) {
                        event.preventDefault();
                        const targetId = this.getAttribute("href").substring(1);
                        const targetElement = document.getElementById(targetId);
                        
                        if (targetElement) {
                            targetElement.scrollIntoView({
                                behavior: "smooth"
                            });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
