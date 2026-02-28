<?php
include_once('../database/config.php'); 
define("TITLE", "View Services");
include_once('../layouts/header.php');

// Fetch services data
$sql = "SELECT service_id, service_category, sub_service, description FROM services";
$result = mysqli_query($con, $sql);
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

    <title>Available Dental Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #48A6A7;
            --secondary-color: #264653;
            --accent-color:rgb(255, 226, 151);
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
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            text-align: center;
            margin: 2rem 0 3rem;
        }

        .page-header h1 {
            color: var(--secondary-color);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-color);
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Grid Layout */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .service-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .service-header {
            background: linear-gradient(135deg, var(--primary-color), #3a8d8e);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .service-header h3 {
            margin: 0;
            font-size: 1.4rem;
        }

        .service-category {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .service-description {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .description-title {
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }

        .description-content {
            color: var(--text-color);
            line-height: 1.6;
            flex-grow: 1;
        }

        .description-item {
            margin-bottom: 0.8rem;
            padding-left: 1rem;
            position: relative;
        }

        .description-item:before {
            content: "•";
            color: var(--primary-color);
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .service-footer {
            padding: 1rem 1.5rem;
            background-color: rgba(72, 166, 167, 0.1);
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }

        .learn-more-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .learn-more-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .note {
            text-align: center;
            margin: 3rem auto;
            padding: 1.5rem;
            max-width: 800px;
            color: var(--text-color);
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            background-color: rgba(255, 226, 151, 0.2);
            border-radius: 8px;
        }

        .note h3 {
            color: var(--secondary-color);
            margin-top: 0;
        }

        /* Responsive Grid Adjustments */
        @media (min-width: 1200px) {
            .services-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .services-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .service-card {
                max-width: 100%;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="page-header">
            <h1>Our Dental Services</h1>
            <p>Explore our comprehensive range of dental treatments and procedures designed to keep your smile healthy and beautiful.</p>
        </div>

        <div class="services-grid">
            <?php
            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    echo '<div class="service-card">';
                    echo '<div class="service-header">';
                    echo '<h3>' . htmlspecialchars($row["sub_service"]) . '</h3>';
                    echo '<div class="service-category">' . htmlspecialchars($row["service_category"]) . '</div>';
                    echo '</div>';
                    
                    echo '<div class="service-description">';
                    echo '<div class="description-title">Service Details</div>';
                    echo '<div class="description-content">';
                    
                    // Process description content
                    $description = trim($row["description"]);
                    
                    // If description contains bullet points or newlines, format accordingly
                    if (strpos($description, "\n") !== false || strpos($description, "•") !== false) {
                        // Replace bullet points and split by newlines
                        $lines = preg_split('/\n|\•/', $description);
                        
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                echo '<div class="description-item">' . htmlspecialchars($line) . '</div>';
                            }
                        }
                    } else {
                        // If it's a single paragraph
                        echo '<div class="description-item">' . htmlspecialchars($description) . '</div>';
                    }
                    
                    echo '</div>'; // Close description-content
                    echo '</div>'; // Close service-description
                    
                    echo '<div class="service-footer">';
                    echo '</div>';
                    
                    echo '</div>'; // Close service-card
                }
            } else {
                echo '<p style="text-align: center; grid-column: 1 / -1; padding: 2rem;">No services available at the moment.</p>';
            }
            ?>
        </div>

        <div class="note">
            <h3>Personalized Treatment Plans</h3>
            <p>Each patient receives a customized treatment plan based on their unique dental needs. During your consultation, we'll discuss the specific procedures recommended for you and provide detailed information about your treatment options.</p>
        </div>
    </div>

<?php include_once('../layouts/footer.php');?>

</body>
</html>