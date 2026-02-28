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

// Get admin/dentist info from multidisciplinary_dental_team
$user_id = $_SESSION['userID'];
$adminInfo = null;

$query = "SELECT * FROM multidisciplinary_dental_team WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $adminInfo = $result->fetch_assoc();
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Selection | Landero Dental Clinic</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            margin: 0;
        }

        .selection-container {
            max-width: 900px;
            width: 100%;
            max-height: 95vh;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
            display: flex;
            flex-direction: column;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .selection-header {
            background-color: var(--secondary-color);
            padding: 25px 30px;
            text-align: center;
            color: var(--white);
            flex-shrink: 0;
        }

        .selection-header img {
            width: 70px;
            height: auto;
            margin-bottom: 12px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .selection-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .selection-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .selection-content {
            padding: 25px 30px;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 25px;
            flex-shrink: 0;
        }

        .welcome-text h2 {
            font-size: 20px;
            color: var(--text-color);
            margin-bottom: 6px;
        }

        .welcome-text p {
            color: var(--text-light);
            font-size: 14px;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
            flex: 1;
        }

        .option-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .option-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background-color: var(--primary-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .option-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(72, 166, 167, 0.2);
        }

        .option-card:hover::before {
            transform: scaleX(1);
        }

        .option-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--white);
            transition: all 0.3s ease;
        }

        .option-card:hover .option-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .option-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .option-description {
            font-size: 13px;
            color: var(--text-light);
            line-height: 1.5;
            margin-bottom: 15px;
            flex: 1;
        }

        .option-arrow {
            color: var(--primary-color);
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        .option-card:hover .option-arrow {
            transform: translateX(5px);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .back-link a {
            color: var(--text-light);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .selection-container {
                max-height: 98vh;
            }

            .selection-header {
                padding: 20px 15px;
            }

            .selection-header img {
                width: 60px;
                margin-bottom: 10px;
            }

            .selection-header h1 {
                font-size: 20px;
                margin-bottom: 4px;
            }

            .selection-header p {
                font-size: 12px;
            }

            .selection-content {
                padding: 20px 15px;
            }

            .welcome-text {
                margin-bottom: 20px;
            }

            .welcome-text h2 {
                font-size: 18px;
            }

            .welcome-text p {
                font-size: 13px;
            }

            .options-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-top: 15px;
            }

            .option-card {
                padding: 20px 15px;
            }

            .option-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
                margin-bottom: 12px;
            }

            .option-title {
                font-size: 16px;
            }

            .option-description {
                font-size: 12px;
            }

            .back-link {
                margin-top: 15px;
                padding-top: 15px;
            }
        }

        @media (max-height: 700px) {
            .selection-header {
                padding: 15px 20px;
            }

            .selection-header img {
                width: 50px;
                margin-bottom: 8px;
            }

            .selection-header h1 {
                font-size: 18px;
                margin-bottom: 4px;
            }

            .selection-header p {
                font-size: 12px;
            }

            .selection-content {
                padding: 15px 20px;
            }

            .welcome-text {
                margin-bottom: 15px;
            }

            .welcome-text h2 {
                font-size: 16px;
            }

            .welcome-text p {
                font-size: 12px;
            }

            .options-grid {
                gap: 15px;
                margin-top: 15px;
            }

            .option-card {
                padding: 20px 15px;
            }

            .option-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
                margin-bottom: 10px;
            }

            .option-title {
                font-size: 16px;
                margin-bottom: 6px;
            }

            .option-description {
                font-size: 12px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="selection-container">
        <div class="selection-header">
            <img src="../assets/images/landerologo.png" alt="Clinic Logo">
            <h1>Welcome, <?php echo htmlspecialchars($adminInfo['first_name'] ?? $_SESSION['first_name'] ?? 'Admin'); ?>!</h1>
            <p>Choose where you'd like to go</p>
        </div>

        <div class="selection-content">
            <div class="welcome-text">
                <h2>Select Your Destination</h2>
                <p>Choose between managing your account or accessing the admin dashboard</p>
            </div>

            <div class="options-grid">
                <div class="option-card" onclick="window.location.href='edit_content.php'">
                    <div class="option-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="option-title">Edit Content</div>
                    <div class="option-description">
                        Edit website content including services, contact information, hero section, and more
                    </div>
                    <div class="option-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>

                <div class="option-card" onclick="window.location.href='admin.php'">
                    <div class="option-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="option-title">Admin Dashboard</div>
                    <div class="option-description">
                        Access the main admin dashboard to manage appointments, patients, and clinic operations
                    </div>
                    <div class="option-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
            </div>

            <div class="back-link">
                <a href="../controllers/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</body>
</html>
