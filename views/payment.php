<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // Redirect to login page
    header("Location: login.php");
    exit("You must be logged in to view this page.");
}

define("TITLE", "Payment");
require_once(__DIR__ . "/../database/config.php");
// System settings loader (safe, with fallbacks)
require_once(__DIR__ . "/../includes/system_settings.php");

$fname = $lname = $birthdate = $age = $email = $gender = $phone = '';
$address = $service_id = $subService = $branch = $date = $time = '';
$request_note = '';
// Dynamic reservation/consultation fee (fallback to previous 500)
$__settings = getSystemSettings($con);
$reservationFee = toFloatSetting(getSetting($__settings, 'reservation_fee_amount', 500), 500.0);
$__gcashEnabled = toBoolSetting(getSetting($__settings, 'gcash_enabled', '1'));
$__mayaEnabled = toBoolSetting(getSetting($__settings, 'maya_enabled', '1'));
// Dynamic payment account details
$gcashAccountName = getSetting($__settings, 'gcash_account_name', '');
$gcashAccountNumber = getSetting($__settings, 'gcash_account_number', '');
$mayaAccountName = getSetting($__settings, 'maya_account_name', '');
$mayaAccountNumber = getSetting($__settings, 'maya_account_number', '');
// Dynamic QR codes (with safe path adjustments for views/)
$gcashQrStored = trim((string)getSetting($__settings, 'gcash_qr_code', ''));
$mayaQrStored = trim((string)getSetting($__settings, 'maya_qr_code', ''));
function viewPathForUpload($path) {
    if (!$path) return '';
    // If stored as 'uploads/...', make it reachable from views/ with '../'
    if (strpos($path, 'uploads/') === 0) {
        return '../' . $path;
    }
    return $path;
}
$gcashQrSrc = $gcashQrStored ? viewPathForUpload($gcashQrStored) : '../assets/images/qrcode.jpg';
$mayaQrSrc = $mayaQrStored ? viewPathForUpload($mayaQrStored) : '../assets/images/qrcode.jpg';

$dentist = 'Dr. Michelle Landero';

$timeRanges = [
    'firstBatch' => '8:00AM-9:00AM',
    'secondBatch' => '9:00AM-10:00AM',
    'thirdBatch' => '10:00AM-11:00AM',
    'fourthBatch' => '11:00AM-12:00PM',
    'fifthBatch' => '1:00PM-2:00PM',
    'sixthBatch' => '2:00PM-3:00PM',
    'sevenBatch' => '3:00PM-4:00PM',
    'eightBatch' => '4:00PM-5:00PM',
    'nineBatch' => '5:00PM-6:00PM',
    'tenBatch' => '6:00PM-7:00PM',
    'lastBatch' => '7:00PM-8:00PM'
];

// Calculate age
if (!empty($birthdate) && $birthdate !== 'N/A') {
    $birthDateObj = new DateTime($birthdate);
    $todayObj = new DateTime();
    $age = $todayObj->diff($birthDateObj)->y;
} else {
    $age = 'N/A';
}

// Override with POST values if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $fname = htmlspecialchars($_POST['fname'] ?? $fname);
    $lname = htmlspecialchars($_POST['lname'] ?? $lname);
    $birthdate = htmlspecialchars($_POST['birthdate'] ?? $birthdate);
    $age = htmlspecialchars($_POST['age'] ?? $age);
    $email = htmlspecialchars($_POST['email'] ?? $email);
    $gender = htmlspecialchars($_POST['gender'] ?? $gender);
    $phone = htmlspecialchars($_POST['phone'] ?? $phone);
    $address = htmlspecialchars($_POST['address'] ?? $address);
    $request_note = trim($_POST['request_note'] ?? '');

    // Get sub_service from POST - check multiple possible field names
    $subService = trim($_POST['sub_service'] ?? $_POST['subService'] ?? 'N/A');
    // Decode any HTML entities that might have been encoded
    $subService = html_entity_decode($subService, ENT_QUOTES, 'UTF-8');
    // Clean and normalize
    $subService = trim($subService);
    $subservice_id = ''; // Will store the subservice_id if it's a subservice

    // Map subService to service_id and subservice_id
    // For subservices, we need to use the parent service_id for the appointments table
    // Use case-insensitive comparison and handle variations
    $subServiceLower = strtolower($subService);
    switch (true) {
        // General Dentistry
        case (stripos($subService, 'Checkups') !== false || $subServiceLower === 'checkups'):                     
            $service_id = 'S001'; 
            $subservice_id = 'S001'; // Main service, no subservice
            $subService = 'Checkups'; // Normalize
            break;
        case (stripos($subService, 'Oral Prophylaxis') !== false || stripos($subService, 'Cleaning') !== false):  
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1001'; // Subservice_id
            $subService = 'Oral Prophylaxis (Cleaning)'; // Normalize
            break;
        case (stripos($subService, 'Fluoride') !== false):         
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1002'; // Subservice_id
            $subService = 'Fluoride Application'; // Normalize
            break;
        case (stripos($subService, 'Pit') !== false && stripos($subService, 'Fissure') !== false):       
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1003'; // Subservice_id
            $subService = 'Pit & Fissure Sealants'; // Normalize
            break;
        case (stripos($subService, 'Tooth Restoration') !== false || stripos($subService, 'Pasta') !== false):    
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1004'; // Subservice_id
            $subService = 'Tooth Restoration (Pasta)'; // Normalize
            break;
        // Orthodontics
        case (stripos($subService, 'Braces') !== false && stripos($subService, 'Retainers') === false):                       
            $service_id = 'S002'; 
            $subservice_id = 'S002'; // Main service, no subservice
            $subService = 'Braces'; // Normalize
            break;
        case (stripos($subService, 'Retainers') !== false):                    
            $service_id = 'S002'; // Parent service_id for appointments table
            $subservice_id = 'S2001'; // Subservice_id
            $subService = 'Retainers'; // Normalize
            break;
        // Oral Surgery
        case (stripos($subService, 'Tooth Extraction') !== false || stripos($subService, 'Bunot') !== false):     
            $service_id = 'S003'; 
            $subservice_id = 'S003'; // Main service, no subservice
            $subService = 'Tooth Extraction (Bunot)'; // Normalize
            break;
        // Endodontics
        case (stripos($subService, 'Root Canal') !== false):         
            $service_id = 'S004'; 
            $subservice_id = 'S004'; // Main service, no subservice
            $subService = 'Root Canal Treatment'; // Normalize
            break;
        // Prosthodontics
        case (stripos($subService, 'Crowns') !== false && stripos($subService, 'Dentures') === false):                       
            $service_id = 'S005'; 
            $subservice_id = 'S005'; // Main service, no subservice
            $subService = 'Crowns'; // Normalize
            break;
        case (stripos($subService, 'Dentures') !== false):                     
            $service_id = 'S005'; // Parent service_id for appointments table
            $subservice_id = 'S5001'; // Subservice_id
            $subService = 'Dentures'; // Normalize
            break;
        default: 
            // Try exact match as fallback
            switch ($subService) {
                case 'Checkups':                     
                    $service_id = 'S001'; 
                    $subservice_id = 'S001';
                    break;
                case 'Oral Prophylaxis (Cleaning)':  
                    $service_id = 'S001';
                    $subservice_id = 'S1001';
                    break;
                case 'Fluoride Application':         
                    $service_id = 'S001';
                    $subservice_id = 'S1002';
                    break;
                case 'Pit & Fissure Sealants':       
                    $service_id = 'S001';
                    $subservice_id = 'S1003';
                    break;
                case 'Tooth Restoration (Pasta)':    
                    $service_id = 'S001';
                    $subservice_id = 'S1004';
                    break;
                case 'Braces':                       
                    $service_id = 'S002'; 
                    $subservice_id = 'S002';
                    break;
                case 'Retainers':                    
                    $service_id = 'S002';
                    $subservice_id = 'S2001';
                    break;
                case 'Tooth Extraction (Bunot)':     
                    $service_id = 'S003'; 
                    $subservice_id = 'S003';
                    break;
                case 'Root Canal Treatment':         
                    $service_id = 'S004'; 
                    $subservice_id = 'S004';
                    break;
                case 'Crowns':                       
                    $service_id = 'S005'; 
                    $subservice_id = 'S005';
                    break;
                case 'Dentures':                     
                    $service_id = 'S005';
                    $subservice_id = 'S5001';
                    break;
                default: 
                    $service_id = 'N/A'; 
                    $subservice_id = 'N/A';
                    break;
            }
            break;
    }

    // Map service_id to category name
    switch ($service_id) {
        case 'S001': 
        case 'S1001':
        case 'S1002':
        case 'S1003':
        case 'S1004':
            $service_name = 'General Dentistry'; 
            break;

        case 'S002': 
        case 'S2001':
            $service_name = 'Orthodontics'; 
            break;

        case 'S003': 
            $service_name = 'Oral Surgery'; 
            break;

        case 'S004': 
            $service_name = 'Endodontics'; 
            break;

        case 'S005': 
        case 'S5001':
            $service_name = 'Prosthodontics Treatments (Pustiso)'; 
            break;

        default: 
            $service_name = 'Unknown Service'; 
            break;
    }

    $branch = htmlspecialchars($_POST['branch'] ?? 'N/A');
    $date = htmlspecialchars($_POST['date'] ?? 'N/A');
    $time = isset($_POST['time']) && isset($timeRanges[$_POST['time']]) ? $timeRanges[$_POST['time']] : 'N/A';
    $time_slot = htmlspecialchars($_POST['time'] ?? '');

    // Format branch names
    if (strtolower($branch) === 'comembo') {
        $branch = 'Comembo Branch';
    } elseif (strtolower($branch) === 'taytay') {
        $branch = 'Taytay Rizal Branch';
    }
    
    // Validate required fields are present
    if (empty($date) || $date === 'N/A' || empty($time_slot)) {
        echo "<script>
            alert('Please select a valid date and time slot for your appointment.');
            window.location.href='index.php';
        </script>";
        exit();
    }
    
    // Validate service_id is valid (not 'N/A' or empty)
    if (empty($service_id) || $service_id === 'N/A' || empty($subService) || $subService === 'N/A') {
        // Log for debugging
        error_log("Payment.php - Invalid service: subService='$subService', service_id='$service_id'");
        error_log("Payment.php - POST data: " . print_r($_POST, true));
        
        echo "<script>
            alert('Invalid service selected: " . addslashes($subService) . ". Please select a valid service and try again.');
            window.location.href='index.php#appointment';
        </script>";
        exit();
    }

    // Check if clinic is closed on the selected date
    $clinicClosed = false;
    $closureReason = '';
    $closureType = '';
    
    // Check if clinic_closures table exists
    $checkTable = "SHOW TABLES LIKE 'clinic_closures'";
    $tableExists = mysqli_query($con, $checkTable);
    
    if ($tableExists && mysqli_num_rows($tableExists) > 0) {
        $closureQuery = "SELECT closure_type, reason FROM clinic_closures WHERE closure_date = ? AND status = 'active' LIMIT 1";
        $closureStmt = $con->prepare($closureQuery);
        if ($closureStmt) {
            $closureStmt->bind_param("s", $date);
            $closureStmt->execute();
            $closureResult = $closureStmt->get_result();
            
            if ($closureRow = $closureResult->fetch_assoc()) {
                $closureType = $closureRow['closure_type'];
                $closureReason = $closureRow['reason'];
                
                // Block appointment if it's a full day closure
                if ($closureType === 'full_day') {
                    $clinicClosed = true;
                }
            }
            $closureStmt->close();
        }
    }
        
    
    // If clinic is closed, prevent proceeding to payment
    if ($clinicClosed) {
        $formattedDate = date('F j, Y', strtotime($date));
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Clinic Closed</title>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
            <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' rel='stylesheet'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Poppins', sans-serif;
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 20px;
                    padding: 40px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                    text-align: center;
                    max-width: 500px;
                    width: 100%;
                    animation: slideIn 0.4s ease-out;
                }
                @keyframes slideIn {
                    from { transform: translateY(-30px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                .error-icon {
                    width: 80px;
                    height: 80px;
                    background: #fee2e2;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
                .error-icon i {
                    font-size: 40px;
                    color: #dc2626;
                }
                h1 {
                    color: #1f2937;
                    margin-bottom: 15px;
                    font-size: 24px;
                }
                .error-message {
                    color: #6b7280;
                    margin-bottom: 30px;
                    line-height: 1.6;
                }
                .closure-details {
                    background: #fef3c7;
                    border-left: 4px solid #f59e0b;
                    padding: 15px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    text-align: left;
                }
                .closure-details strong {
                    color: #92400e;
                    display: block;
                    margin-bottom: 5px;
                }
                .closure-details p {
                    color: #78350f;
                    margin: 0;
                }
                .btn-back {
                    background: #3b82f6;
                    color: white;
                    padding: 12px 30px;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    display: inline-block;
                }
                .btn-back:hover {
                    background: #2563eb;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <div class='error-icon'>
                    <i class='fas fa-exclamation-triangle'></i>
                </div>
                <h1>Clinic Closed</h1>
                <div class='error-message'>
                    Sorry, the clinic is closed on the selected date.
                </div>
                <div class='closure-details'>
                    <strong>Selected Date:</strong>
                    <p>$formattedDate</p>
                    <strong style='margin-top: 10px;'>Reason:</strong>
                    <p>" . htmlspecialchars($closureReason) . "</p>
                </div>
                <a href='index.php' class='btn-back'>
                    <i class='fas fa-arrow-left'></i> Select Another Date
                </a>
            </div>
        </body>
        </html>";
        exit();
    }
    
    // Check if the selected time slot is blocked
    $blockedSlotQuery = "SELECT block_id, reason FROM blocked_time_slots WHERE date = ? AND time_slot = ? LIMIT 1";
    $blockedStmt = $con->prepare($blockedSlotQuery);
    if ($blockedStmt) {
        $blockedStmt->bind_param("ss", $date, $time_slot);
        $blockedStmt->execute();
        $blockedResult = $blockedStmt->get_result();
        
        if ($blockedResult->num_rows > 0) {
            $blockedRow = $blockedResult->fetch_assoc();
            $blockedReason = $blockedRow['reason'] ?? 'Time slot is not available';
            $formattedDate = date('F j, Y', strtotime($date));
            echo "<!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Time Slot Unavailable</title>
                <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
                <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' rel='stylesheet'>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: 'Poppins', sans-serif;
                        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        padding: 20px;
                    }
                    .error-container {
                        background: white;
                        border-radius: 20px;
                        padding: 40px;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                        text-align: center;
                        max-width: 500px;
                        width: 100%;
                        animation: slideIn 0.4s ease-out;
                    }
                    @keyframes slideIn {
                        from { transform: translateY(-30px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    .error-icon {
                        width: 80px;
                        height: 80px;
                        background: #fee2e2;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 20px;
                    }
                    .error-icon i {
                        font-size: 40px;
                        color: #dc2626;
                    }
                    h1 {
                        color: #1f2937;
                        margin-bottom: 15px;
                        font-size: 24px;
                    }
                    .error-message {
                        color: #6b7280;
                        margin-bottom: 30px;
                        line-height: 1.6;
                    }
                    .btn-back {
                        background: #3b82f6;
                        color: white;
                        padding: 12px 30px;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        text-decoration: none;
                        display: inline-block;
                    }
                    .btn-back:hover {
                        background: #2563eb;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                    }
                </style>
            </head>
            <body>
                <div class='error-container'>
                    <div class='error-icon'>
                        <i class='fas fa-clock'></i>
                    </div>
                    <h1>Time Slot Unavailable</h1>
                    <div class='error-message'>
                        The selected time slot is not available on $formattedDate.<br>
                        Reason: " . htmlspecialchars($blockedReason) . "
                    </div>
                    <a href='index.php' class='btn-back'>
                        <i class='fas fa-arrow-left'></i> Select Another Time Slot
                    </a>
                </div>
            </body>
            </html>";
            exit();
        }
        $blockedStmt->close();
    }
}

// Guard: missing full name or appointment details -> animated alert then redirect
if (empty(trim(($fname ?? ''))) || empty(trim(($lname ?? ''))) || empty(trim(($service_id ?? ''))) || $service_id === 'N/A' || empty(trim(($subService ?? ''))) || $subService === 'N/A' || empty(trim(($date ?? ''))) || empty(trim(($time_slot ?? '')))) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Missing Details</title>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' rel='stylesheet'>
        <style>
            * { box-sizing: border-box; }
            body {
                font-family: 'Poppins', sans-serif;
                background: linear-gradient(135deg, #fdf2f8 0%, #e0f2fe 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .toast {
                background: #fff;
                border-radius: 14px;
                box-shadow: 0 20px 50px rgba(0,0,0,0.12);
                padding: 22px 24px;
                display: flex;
                align-items: center;
                gap: 14px;
                border-left: 5px solid #ef4444;
                animation: slideIn 0.35s ease-out;
                max-width: 520px;
                width: 100%;
            }
            .toast .icon {
                width: 46px;
                height: 46px;
                border-radius: 50%;
                background: #fee2e2;
                color: #b91c1c;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 22px;
                flex-shrink: 0;
                animation: pop 0.4s ease-out;
            }
            .toast .content {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .toast .title {
                font-weight: 700;
                color: #111827;
                font-size: 16px;
            }
            .toast .msg {
                color: #6b7280;
                font-size: 14px;
            }
            @keyframes slideIn {
                from { transform: translateY(-10px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes pop {
                0% { transform: scale(0.8); }
                60% { transform: scale(1.08); }
                100% { transform: scale(1); }
            }
        </style>
    </head>
    <body>
        <div class='toast'>
            <div class='icon'><i class='fas fa-exclamation-circle'></i></div>
            <div class='content'>
                <div class='title'>Missing required details</div>
                <div class='msg'>Please provide your full name and complete appointment details before proceeding.</div>
            </div>
        </div>
        <script>
            setTimeout(function(){ window.location.href = 'index.php'; }, 2200);
        </script>
    </body>
    </html>";
    exit();
}
?>

<?php include_once('../layouts/header.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm & Pay - SmileCare Dental</title>
    <link rel="stylesheet" href="../assets/css/paymentstyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Koulen&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

	<style>
		/* Scoped styles for Appointment Details card layout */
		.appointment-card {
			background: #ffffff;
			border-radius: 14px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
			padding: 20px;
		}
		.appointment-card .card-header {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-bottom: 16px;
		}
		.appointment-card .card-header .icon {
			background: #e8f3f3;
			color: #2b7a7b;
			border-radius: 10px;
			width: 36px;
			height: 36px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			font-size: 16px;
		}
		.appointment-card .card-header .title {
			font-weight: 600;
			color: #163d3e;
			font-size: 18px;
		}
		.appointment-card .summary-boxes {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 12px;
		}
		.appointment-card .summary-box {
			background: #f6fbfb;
			border: 1px solid #e3efef;
			border-radius: 12px;
			padding: 14px;
			display: flex;
			flex-direction: column;
			gap: 6px;
			min-height: 64px;
		}
		.appointment-card .summary-box .label {
			font-size: 12px;
			color: #6b7280;
			letter-spacing: 0.02em;
		}
		.appointment-card .summary-box .value {
			font-size: 16px;
			font-weight: 600;
			color: #0f172a;
		}
		.appointment-card .divider {
			border: 0;
			border-top: 1px solid #eef2f7;
			margin: 16px 0;
		}
		.appointment-card .details-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 12px 16px;
		}
		.appointment-card .detail-item {
			display: flex;
			flex-direction: column;
			gap: 6px;
			min-height: 56px;
		}
		.appointment-card .detail-item .detail-label {
			font-size: 12px;
			color: #6b7280;
		}
		.appointment-card .detail-item .detail-value {
			font-size: 16px;
			color: #0f172a;
			font-weight: 600;
		}
		/* Badge styles for Service and Sub-Service */
		.appointment-card .badge {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			background: #f0f9ff;
			color: #0369a1;
			border: 1px solid #e0f2fe;
			border-radius: 999px;
			padding: 6px 10px;
			font-size: 13px;
			font-weight: 600;
			width: fit-content;
		}
		.appointment-card .badge .dot {
			width: 6px;
			height: 6px;
			border-radius: 999px;
			background: currentColor;
		}
		/* Emphasis for Dentist and Branch */
		.appointment-card .emphasis {
			font-size: 17px;
			font-weight: 700;
			color: #0b3b3c;
		}
		/* Responsive behavior */
		@media (max-width: 768px) {
			.appointment-card .summary-boxes {
				grid-template-columns: 1fr;
			}
			.appointment-card .details-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>

	<style>
		/* Scoped compact 2-column layout for Payment Information forms */
		.payment-details .payment-form {
			margin-top: 10px;
		}
		.payment-details .payment-form .form-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 12px 16px;
			align-items: start;
		}
		.payment-details .payment-form .form-group {
			display: flex;
			flex-direction: column;
			gap: 6px;
			margin-bottom: 0; /* tighten vertical spacing; grid gap controls spacing */
		}
		.payment-details .payment-form label {
			font-size: 13px;
			color: #6b7280;
		}
		.payment-details .payment-form input[type="text"],
		.payment-details .payment-form input[type="number"],
		.payment-details .payment-form input[type="file"] {
			padding: 9px 12px; /* slightly reduced for compactness */
			font-size: 14px;
			border-radius: 8px;
		}
		/* Keep full-width elements spanning both columns */
		.payment-details .payment-form .file-upload,
		.payment-details .payment-form .confirmation-checkbox,
		.payment-details .payment-form .pay-button {
			grid-column: 1 / -1;
		}
		/* Ensure upload group itself is full width */
		.payment-details .payment-form .form-group.full-width {
			grid-column: 1 / -1;
		}
		/* Responsive: stack to single column on mobile */
		@media (max-width: 768px) {
			.payment-details .payment-form .form-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>
	
	<style>
		/* QR/payment display alignment for both GCash and Maya */
        #gcashDetails .payment-qr,
        #mayaDetails .payment-qr {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .payment-qr .qr-stack {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 10px;
        }

        .payment-qr .qr-stack #gcashAccountNameDisplay,
        .payment-qr .qr-stack #mayaAccountNameDisplay,
        .payment-qr .qr-stack .scan-label {
            width: 100%;
            margin: 0;
            text-align: center;
        }

        .payment-qr .qr-stack #gcashAccountNameDisplay,
        .payment-qr .qr-stack #mayaAccountNameDisplay {
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1.4;
        }

        .payment-qr .qr-stack .scan-label {
            margin-top: 2px;
            margin-bottom: 2px;
        }

        .payment-qr .qr-code {
            display: flex;
            justify-content: center;
            align-items: center;
            width: auto;
            max-width: 220px;
            margin: 0 auto;
            padding: 0;
        }

        .payment-qr .qr-code img {
            display: block;
            width: 180px;
            max-width: 180px;
            height: auto;
            object-fit: contain;
            margin: 0 auto;
        }

        /* Mobile */
        @media (max-width: 768px) {
            #gcashDetails .payment-qr,
            #mayaDetails .payment-qr {
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                text-align: center;
            }

            .payment-qr .qr-stack {
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                gap: 8px;
            }

            .payment-qr .qr-stack #gcashAccountNameDisplay,
            .payment-qr .qr-stack #mayaAccountNameDisplay,
            .payment-qr .qr-stack .scan-label {
                width: 100%;
                text-align: center;
                margin: 0;
            }

            .payment-qr .qr-stack #gcashAccountNameDisplay,
            .payment-qr .qr-stack #mayaAccountNameDisplay {
                max-width: 240px;
                margin-left: auto;
                margin-right: auto;
                word-break: break-word;
                overflow-wrap: break-word;
            }

            .payment-qr .qr-code {
                width: auto;
                max-width: 200px;
                margin: 0 auto;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .payment-qr .qr-code img {
                width: 160px;
                max-width: 160px;
                height: auto;
                margin: 0 auto;
                display: block;
            }
        }
	</style>

<div class="payment-container">
    <div class="header-section">
        <h1>Complete Your Payment</h1>
        <p>Review your appointment details and proceed with payment.</p>
    </div>

    <form id="paymentForm" action="../controllers/appointmentProcess.php" method="POST" enctype="multipart/form-data">
        <div class="content-grid">
            <!-- Appointment Summary -->
            <div class="summary-section">
                <div class="section-header">
                    <h2>Appointment Summary</h2>
                    <p>Please verify your appointment details.</p>
                </div>

                <div class="info-section">
                    <h3 class="section-title">A. Patient Information</h3>
                    <div class="patient-details">
                        <div class="patient-row">
                            <div class="patient-label">Full Name:</div>
                            <div class="patient-value"><?= strtoupper("$fname $lname") ?></div>
                        </div>
                        <div class="patient-row">
                            <div class="patient-label">Age:</div>
                            <div class="patient-value"><?= $age ?></div>
                        </div>
                        <div class="patient-row">
                            <div class="patient-label">Gender:</div>
                            <div class="patient-value"><?= strtoupper($gender) ?></div>
                            <input type="hidden" name="address" value="<?= $address ?>">
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3 class="section-title">C. Appointment Details</h3>
					<div class="appointment-card">
						<div class="card-header">
							<div class="icon"><i class="fas fa-calendar-check"></i></div>
							<div class="title">Appointment Details</div>
						</div>
						<div class="summary-boxes">
							<div class="summary-box">
								<div class="label">Date</div>
								<div class="value"><?= date('F j, Y', strtotime($date)) ?></div>
							</div>
							<div class="summary-box">
								<div class="label">Time Slot</div>
								<div class="value"><?= $time ?></div>
							</div>
						</div>
						<hr class="divider">
						<div class="details-grid">
							<!-- Left column -->
							<div class="detail-item">
								<div class="detail-label">Service</div>
								<div class="detail-value">
									<span class="badge"><span class="dot"></span><?= ucwords($service_name) ?></span>
								</div>
							</div>
							<!-- Right column -->
							<div class="detail-item">
								<div class="detail-label">Sub-Service</div>
								<div class="detail-value">
									<span class="badge"><span class="dot"></span><?= ucwords($subService) ?></span>
								</div>
							</div>
							<!-- Left column -->
							<div class="detail-item">
								<div class="detail-label">Dentist</div>
								<div class="detail-value emphasis"><?= strtoupper($dentist) ?></div>
							</div>
							<!-- Right column -->
							<div class="detail-item">
								<div class="detail-label">Branch</div>
								<div class="detail-value emphasis"><?= strtoupper($branch) ?></div>
							</div>
						</div>
						<?php if (!empty($request_note)): ?>
							<hr class="divider">
							<div class="details-grid">
								<div class="detail-item" style="grid-column: 1 / -1;">
									<div class="detail-label">Additional Service Request</div>
									<div class="detail-value"><?= nl2br(htmlspecialchars($request_note)) ?></div>
								</div>
							</div>
						<?php endif; ?>
					</div>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" name="fname" value="<?= $fname ?>">
                <input type="hidden" name="lname" value="<?= $lname ?>">
                <input type="hidden" name="age" value="<?= $age ?>">
                <input type="hidden" name="birthdate" value="<?= $birthdate ?>">
                <input type="hidden" name="gender" value="<?= $gender ?>">
                <input type="hidden" name="email" value="<?= $email ?>">
                <input type="hidden" name="phone" value="<?= $phone ?>">
                <input type="hidden" name="street" value="<?= $address ?>">
                <input type="hidden" name="service_id" value="<?= $service_id ?>">
                <input type="hidden" name="subService" value="<?= $subService ?>">
                <input type="hidden" name="subservice_id" value="<?= $subservice_id ?>">
                <input type="hidden" name="dentist" value="<?= $dentist ?>">
                <input type="hidden" name="branch" value="<?= $branch ?>">
                <input type="hidden" name="date" value="<?= $date ?>">
                <input type="hidden" name="time" value="<?= htmlspecialchars($_POST['time'] ?? '') ?>">
                <input type="hidden" name="request_note" value="<?= htmlspecialchars($request_note) ?>">
            </div>

            <!-- Payment Information -->
            <div class="payment-section">

                <div class="fee-notice">
					<!-- Use dynamic reservation/consultation fee from settings -->
					<p><strong>Consultation Fee:</strong> ₱<?php echo number_format($reservationFee, 2); ?></p>
                    <p>This appointment fee will be deducted from the total payment.</p>
                </div>
                <div class="section-header">
                    <h2>Payment Information</h2>
                    <p>Complete payment to confirm booking.</p>
                </div>

                <div class="payment-method-section">
                    <h3 class="section-title">Payment Method</h3>
                    
                    <div class="payment-method-selector">
                        <select name="paymentMethod" id="paymentMethod" required>
							<option value="">Select payment method</option>
							<?php if ($__gcashEnabled): ?>
								<option value="GCash">GCash</option>
							<?php endif; ?>
							<?php if ($__mayaEnabled): ?>
								<option value="PayMaya">PayMaya</option>
							<?php endif; ?>
							<?php if (!$__gcashEnabled && !$__mayaEnabled): ?>
								<option value="" disabled>No payment methods available</option>
							<?php endif; ?>
                        </select>
                    </div>

                    <!-- GCash Section -->
					<div id="gcashDetails" class="payment-details" style="display: none;<?php echo $__gcashEnabled ? '' : 'pointer-events:none;opacity:0.6;'; ?>">
                        <div class="payment-option">
                            <div class="payment-header">
                                
                                <div class="payment-qr">
									<div class="qr-stack">
										<p id="gcashAccountNameDisplay" <?php echo empty($gcashAccountName) ? 'style="display:none;"' : ''; ?>><strong>Account Name: <?php echo htmlspecialchars($gcashAccountName); ?></strong></p>
										<p class="scan-label">Scan to Pay via GCash</p>
										<div class="qr-code">
											<img id="gcashQrImg" src="<?php echo htmlspecialchars($gcashQrSrc); ?>" alt="GCash QR Code">
										</div>
									</div>
                                </div>
                            </div>
                            
                            <div class="account-info">
                                <p>Or use Account Number:</p>
                                <div class="account-number" id="gcashAccountNumberDisplay"><?php echo htmlspecialchars($gcashAccountNumber ?: ''); ?></div>
                            </div>
                            
                            <div class="payment-form">
							<div class="form-grid">
								<div class="form-group">
									<label for="gcashaccName">Account Name</label>
									<input 
										type="text" 
										name="gcashaccName" 
										id="gcashaccName" 
										placeholder="Your Account Name"
										pattern="[A-Za-z\s]+"
										oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')"
										maxlength="50"
										title="Only letters and spaces are allowed">
								</div>
									
									<div class="form-group">
										<label for="gcashNum">GCash Number</label>
										<input type="text" name="gcashNum" id="gcashNum" placeholder="Your GCash Account Number" maxlength="11" pattern="\d{11}">
									</div>
									
									<div class="form-group">
										<label for="gcashAmount">Payment Amount You've Sent</label>
										<input 
											type="number" 
											name="gcashAmount" 
											id="gcashAmount" 
											placeholder="Amount Sent" 
											min="<?php echo htmlspecialchars((string)$reservationFee, ENT_QUOTES, 'UTF-8'); ?>"
											max="9999"
											step="0.01"
											oninput="if(this.value.length > 4) this.value = this.value.slice(0,4);"
										>
									</div>

									<div class="form-group">
										<label for="gcashrefNum">Reference Number <span style="color: #dc2626;">*</span></label>
										<input 
											type="text" 
											name="gcashrefNum" 
											id="gcashrefNum" 
											placeholder="Reference No." 
											maxlength="15"
											pattern="\d{1,15}"
											inputmode="numeric"
											oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,15);"
											required
										>
										<small class="reference-error" id="gcashrefNumError" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 5px;"></small>
									</div>
								</div>
                                
								<div class="form-group full-width">
                                    <label for="proofImage">Upload Receipt</label>
                                    <div class="file-upload">
                                        <input type="file" name="proofImage" id="proofImage">
                                        <span class="file-text">Choose File No file chosen</span>
                                    </div>
                                </div>
                                
                                <div class="confirmation-checkbox">
                                    <input type="checkbox" id="gcashConfirm" onchange="togglePayButton('gcash')">
                                    <label for="gcashConfirm">I confirm that the above details are correct and I agree to proceed with the payment.</label>
                                </div>
                                
                                <button type="submit" class="pay-button" id="gcashPayBtn" disabled>Submit</button>
                            </div>
                        </div>
                    </div>

                    <!-- PayMaya Section -->
					<div id="mayaDetails" class="payment-details" style="display: none;<?php echo $__mayaEnabled ? '' : 'pointer-events:none;opacity:0.6;'; ?>">
                        <div class="payment-option">
                            <div class="payment-header">
                                
                                <div class="payment-qr">
									<div class="qr-stack">
	                                    <p id="mayaAccountNameDisplay" <?php echo empty($mayaAccountName) ? 'style="display:none;"' : ''; ?>><strong>Account Name: <?php echo htmlspecialchars($mayaAccountName); ?></strong></p>
	                                    <p class="scan-label">Scan to Pay via PayMaya</p>
	                                    <div class="qr-code">
	                                        <img id="mayaQrImg" src="<?php echo htmlspecialchars($mayaQrSrc); ?>" alt="Maya QR Code">
	                                    </div>
									</div>
                                </div>
                            </div>
                            
                            <div class="account-info">
                                <p>Or use Account Number:</p>
                                <div class="account-number" id="mayaAccountNumberDisplay"><?php echo htmlspecialchars($mayaAccountNumber ?: ''); ?></div>
                            </div>
                            
                            <div class="payment-form">
							<div class="form-grid">
								<div class="form-group">
									<label for="mayaaccName">Account Name</label>
									<input 
										type="text"
										name="mayaaccName"
										id="mayaaccName"
										placeholder="Your Account Name"
										pattern="[A-Za-z\s]+"
										maxlength="50"
										oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')"
										title="Only letters and spaces are allowed">
								</div>
									
									<div class="form-group">
										<label for="mayaNum">PayMaya Number</label>
										<input type="text" name="mayaNum" id="mayaNum" placeholder="Your PayMaya Account Number" maxlength="11" pattern="\d{11}" inputmode="numeric">
										
									</div>
									
									<div class="form-group">
										<label for="mayaAmount">Payment Amount You've Sent</label>
										<input 
											type="number" 
											name="mayaAmount" 
											id="mayaAmount" 
											placeholder="Amount Sent" 
											min="<?php echo htmlspecialchars((string)$reservationFee, ENT_QUOTES, 'UTF-8'); ?>"
											max="9999"
											step="0.01"
											oninput="if(this.value.length > 4) this.value = this.value.slice(0,4);"
										>
									</div>
									
									<div class="form-group">
										<label for="mayarefNum">Reference Number <span style="color: #dc2626;">*</span></label>
										<input 
											type="text" 
											name="mayarefNum" 
											id="mayarefNum" 
											placeholder="Reference No." 
											maxlength="15"
											pattern="\d{1,15}"
											inputmode="numeric"
											oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,15);"
											required
										>
										<small class="reference-error" id="mayarefNumError" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 5px;"></small>
									</div>
								</div>
                                
								<div class="form-group full-width">
                                    <label for="proofImageMaya">Upload Receipt</label>
                                    <div class="file-upload">
                                        <input type="file" name="proofImageMaya" id="proofImageMaya">
                                        <span class="file-text">Choose File No file chosen</span>
                                    </div>
                                </div>
                                
                                <div class="confirmation-checkbox">
                                    <input type="checkbox" id="mayaConfirm" onchange="togglePayButton('maya')">
                                    <label for="mayaConfirm">I confirm that the above details are correct and I agree to proceed with the payment.</label>
                                </div>
                                
                                <button type="submit" class="pay-button" id="mayaPayBtn" disabled>Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Hidden IDs -->
        <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?? '' ?>">
        <input type="hidden" name="appointment_id" value="<?= $appointment_id ?? '' ?>">
    </form>
</div>

<?php include_once('../layouts/footer.php'); ?>

<script>
// Dynamic reservation fee (from PHP) for client-side validation; fallback handled server-side
const RESERVATION_FEE = <?php echo json_encode((float)$reservationFee); ?>;
const GCASH_ENABLED = <?php echo $__gcashEnabled ? 'true' : 'false'; ?>;
const MAYA_ENABLED = <?php echo $__mayaEnabled ? 'true' : 'false'; ?>;
let currentReservationFee = Number(RESERVATION_FEE) || 500;

// Realtime: poll system settings so fee/payment methods reflect without refresh
(function initRealtimePaymentSettings() {
	const SETTINGS_URL = '../controllers/getSystemSettings.php';
	let state = {
		fee: currentReservationFee,
		gcash: GCASH_ENABLED,
		maya: MAYA_ENABLED,
		gcashName: <?php echo json_encode((string)$gcashAccountName); ?>,
		gcashNumber: <?php echo json_encode((string)$gcashAccountNumber); ?>,
		mayaName: <?php echo json_encode((string)$mayaAccountName); ?>,
		mayaNumber: <?php echo json_encode((string)$mayaAccountNumber); ?>,
		gcashQr: <?php echo json_encode((string)$gcashQrStored); ?>,
		mayaQr: <?php echo json_encode((string)$mayaQrStored); ?>
	};

	function setMinAmounts(fee) {
		const gcashAmount = document.getElementById('gcashAmount');
		const mayaAmount = document.getElementById('mayaAmount');
		if (gcashAmount) gcashAmount.min = String(fee);
		if (mayaAmount) mayaAmount.min = String(fee);
	}

	function updateFeeDisplay(fee) {
		// Update the static text in the fee notice if present
		const feeNotice = document.querySelector('.fee-notice strong');
		if (feeNotice) {
			feeNotice.textContent = 'Consultation Fee:';
			const sibling = feeNotice.parentElement; // <p>
			if (sibling) {
				// Replace the whole line with formatted fee
				sibling.innerHTML = '<strong>Consultation Fee:</strong> ₱' + (Number(fee).toFixed(2));
			}
		}
	}

	function ensurePaymentOptions(gcashEnabled, mayaEnabled) {
		const select = document.getElementById('paymentMethod');
		if (!select) return;

		function ensureOption(value, label, enabled) {
			let opt = Array.from(select.options).find(o => o.value === value);
			if (enabled) {
				if (!opt) {
					opt = document.createElement('option');
					opt.value = value;
					opt.text = label;
					select.appendChild(opt);
				}
			} else if (opt) {
				if (select.value === value) {
					select.value = '';
					const evt = new Event('change');
					select.dispatchEvent(evt);
				}
				opt.remove();
			}
		}

		ensureOption('GCash', 'GCash', !!gcashEnabled);
		ensureOption('PayMaya', 'PayMaya', !!mayaEnabled);

		// Also visually disable the detail sections when turned off
		const gcashDetails = document.getElementById('gcashDetails');
		const mayaDetails = document.getElementById('mayaDetails');
		if (gcashDetails) {
			gcashDetails.style.pointerEvents = gcashEnabled ? '' : 'none';
			gcashDetails.style.opacity = gcashEnabled ? '' : '0.6';
			if (!gcashEnabled && gcashDetails.style.display !== 'none') {
				gcashDetails.style.display = 'none';
			}
		}
		if (mayaDetails) {
			mayaDetails.style.pointerEvents = mayaEnabled ? '' : 'none';
			mayaDetails.style.opacity = mayaEnabled ? '' : '0.6';
			if (!mayaEnabled && mayaDetails.style.display !== 'none') {
				mayaDetails.style.display = 'none';
			}
		}
	}

	function setAccountNameDisplay(id, value) {
		const el = document.getElementById(id);
		if (!el) return;
		const trimmed = (value || '').trim();
		if (trimmed) {
			el.style.display = '';
			el.innerHTML = '<strong>Account Name: ' + escapeHtml(trimmed) + '</strong>';
		} else {
			el.style.display = 'none';
			el.innerHTML = '';
		}
	}

	function setAccountNumberDisplay(id, value) {
		const el = document.getElementById(id);
		if (!el) return;
		const trimmed = (value || '').trim();
		el.textContent = trimmed;
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function toViewQrUrl(storedPath, fallback) {
		const value = (storedPath || '').trim();
		if (!value) return fallback;
		// mirror server-side path adjustment: if starts with 'uploads/' prefix '../'
		const adjusted = value.startsWith('uploads/') ? ('../' + value) : value;
		// add cache-busting to ensure fresh image after update
		const ts = Date.now();
		const sep = adjusted.includes('?') ? '&' : '?';
		return adjusted + sep + 'v=' + ts;
	}

	function setQrImage(imgId, storedPath, fallback) {
		const img = document.getElementById(imgId);
		if (!img) return;
		const nextUrl = toViewQrUrl(storedPath, fallback);
		// Only update if different to avoid flicker
		if (img.getAttribute('data-current-src') !== nextUrl) {
			img.src = nextUrl;
			img.setAttribute('data-current-src', nextUrl);
		}
	}

	async function poll() {
		try {
			const res = await fetch(SETTINGS_URL, { cache: 'no-store' });
			if (!res.ok) return;
			const data = await res.json();
			const typed = data && data.typed ? data.typed : null;
			const raw = data && data.settings ? data.settings : null;
			if (!typed) return;

			// Fee
			if (Number.isFinite(typed.reservation_fee_amount) && typed.reservation_fee_amount !== state.fee) {
				state.fee = typed.reservation_fee_amount;
				window.RESERVATION_FEE = state.fee;
				currentReservationFee = Number(state.fee) || 500;
				updateFeeDisplay(state.fee);
				setMinAmounts(state.fee);
			}

			// Methods
			if (typeof typed.gcash_enabled === 'boolean' && typed.gcash_enabled !== state.gcash ||
				typeof typed.maya_enabled === 'boolean' && typed.maya_enabled !== state.maya) {
				state.gcash = !!typed.gcash_enabled;
				state.maya = !!typed.maya_enabled;
				window.GCASH_ENABLED = state.gcash;
				window.MAYA_ENABLED = state.maya;
				ensurePaymentOptions(state.gcash, state.maya);
			}

			// Account details (strings)
			const nextGcashName = ((raw && raw.gcash_account_name) ? String(raw.gcash_account_name) : '').trim();
			const nextGcashNumber = ((raw && raw.gcash_account_number) ? String(raw.gcash_account_number) : '').trim();
			const nextMayaName = ((raw && raw.maya_account_name) ? String(raw.maya_account_name) : '').trim();
			const nextMayaNumber = ((raw && raw.maya_account_number) ? String(raw.maya_account_number) : '').trim();

			if (nextGcashName !== state.gcashName) {
				state.gcashName = nextGcashName;
				setAccountNameDisplay('gcashAccountNameDisplay', state.gcashName);
			}
			if (nextGcashNumber !== state.gcashNumber) {
				state.gcashNumber = nextGcashNumber;
				setAccountNumberDisplay('gcashAccountNumberDisplay', state.gcashNumber);
			}
			if (nextMayaName !== state.mayaName) {
				state.mayaName = nextMayaName;
				setAccountNameDisplay('mayaAccountNameDisplay', state.mayaName);
			}
			if (nextMayaNumber !== state.mayaNumber) {
				state.mayaNumber = nextMayaNumber;
				setAccountNumberDisplay('mayaAccountNumberDisplay', state.mayaNumber);
			}

			// QR codes
			const nextGcashQr = ((raw && raw.gcash_qr_code) ? String(raw.gcash_qr_code) : '').trim();
			const nextMayaQr = ((raw && raw.maya_qr_code) ? String(raw.maya_qr_code) : '').trim();
			if (nextGcashQr !== state.gcashQr) {
				state.gcashQr = nextGcashQr;
				setQrImage('gcashQrImg', state.gcashQr, '../assets/images/qrcode.jpg');
			}
			if (nextMayaQr !== state.mayaQr) {
				state.mayaQr = nextMayaQr;
				setQrImage('mayaQrImg', state.mayaQr, '../assets/images/qrcode.jpg');
			}
		} catch (e) {
			// Silent fail; keep UI stable
		}
	}

	// Initialize current UI
	setMinAmounts(state.fee);
	updateFeeDisplay(state.fee);
	ensurePaymentOptions(state.gcash, state.maya);
	// Initialize account displays from initial state
	setAccountNameDisplay('gcashAccountNameDisplay', state.gcashName);
	setAccountNumberDisplay('gcashAccountNumberDisplay', state.gcashNumber);
	setAccountNameDisplay('mayaAccountNameDisplay', state.mayaName);
	setAccountNumberDisplay('mayaAccountNumberDisplay', state.mayaNumber);
	// Initialize QR images from initial state
	setQrImage('gcashQrImg', state.gcashQr, '../assets/images/qrcode.jpg');
	setQrImage('mayaQrImg', state.mayaQr, '../assets/images/qrcode.jpg');

	// Poll immediately, then every 3 seconds for near-instant updates
	poll();
	setInterval(poll, 3000);
})();

document.getElementById("mayaAmount").addEventListener("input", function() {
    if (this.value > 9999) {
        this.value = 9999;
    }
});

(function () {
  const ids = ["gcashAmount", "mayaAmount"];

  ids.forEach(id => {
    const input = document.getElementById(id);
    if (!input) return;

    // create / get an error <small> right after the input
    let error = input.parentElement.querySelector(".amountError");
    if (!error) {
      error = document.createElement("small");
      error.className = "amountError";
      error.style.color = "red";
      input.parentElement.appendChild(error);
    }

    // block letters and symbols: e, E, +, -
    input.addEventListener("keydown", (e) => {
      if (["e", "E", "+", "-"].includes(e.key)) {
        e.preventDefault();
      }
    });

    // validate min 500
    input.addEventListener("input", () => {
      const value = parseFloat(input.value);

			if (input.value !== "" && (isNaN(value) || value < currentReservationFee)) {
				const feeLabel = Number(currentReservationFee).toFixed(2);
				error.textContent = "Minimum payment amount is ₱" + feeLabel;
				input.setCustomValidity("Minimum payment amount is ₱" + feeLabel);
      } else {
        error.textContent = "";
        input.setCustomValidity("");
      }
    });
  });
})();
const paymentMethodSelect = document.getElementById('paymentMethod');

const gcashFields = ['gcashaccName', 'gcashNum', 'gcashAmount', 'gcashrefNum', 'proofImage'];
const mayaFields = ['mayaaccName', 'mayaNum', 'mayaAmount', 'mayarefNum', 'proofImageMaya'];

function toggleFields(fields, show) {
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.required = show;
            el.disabled = !show;
        }
    });
}

paymentMethodSelect.addEventListener('change', function () {
    const method = this.value;
    document.getElementById('gcashDetails').style.display = 'none';
    document.getElementById('mayaDetails').style.display = 'none';

    toggleFields(gcashFields, false);
    toggleFields(mayaFields, false);

    document.getElementById('gcashPayBtn').disabled = true;
    document.getElementById('mayaPayBtn').disabled = true;
    document.getElementById('gcashConfirm').checked = false;
    document.getElementById('mayaConfirm').checked = false;

	if (method === 'GCash' && GCASH_ENABLED) {
        document.getElementById('gcashDetails').style.display = 'block';
        toggleFields(gcashFields, true);
	} else if (method === 'PayMaya' && MAYA_ENABLED) {
        document.getElementById('mayaDetails').style.display = 'block';
        toggleFields(mayaFields, true);
    }
});

function togglePayButton(type) {
    const btn = document.getElementById(type + 'PayBtn');
    const confirm = document.getElementById(type + 'Confirm');
    if (btn && confirm) {
        btn.disabled = !confirm.checked;
    }
}

// File upload text update
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
            const fileText = this.parentElement.querySelector('.file-text');
            if (fileText) {
                fileText.textContent = fileName;
            }
        });
    });

    // PayMaya number validation - 11 digits only
    const mayaNumInput = document.getElementById('mayaNum');
    if (mayaNumInput) {
        mayaNumInput.addEventListener('input', function(e) {
            // Remove any non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            // Limit to 11 digits
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });

        mayaNumInput.addEventListener('blur', function() {
            if (this.value.length > 0 && this.value.length !== 11) {
                this.setCustomValidity('PayMaya number must be exactly 11 digits');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });

        mayaNumInput.addEventListener('input', function() {
            if (this.value.length === 11) {
                this.setCustomValidity('');
            }
        });
    }

    // GCash number validation - 11 digits only (if not already handled)
    const gcashNumInput = document.getElementById('gcashNum');
    if (gcashNumInput) {
        gcashNumInput.addEventListener('input', function(e) {
            // Remove any non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            // Limit to 11 digits
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });

        gcashNumInput.addEventListener('blur', function() {
            if (this.value.length > 0 && this.value.length !== 11) {
                this.setCustomValidity('GCash number must be exactly 11 digits');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });

        gcashNumInput.addEventListener('input', function() {
            if (this.value.length === 11) {
                this.setCustomValidity('');
            }
        });
    }

    // Reference number validation function
    function checkReferenceNumber(referenceNo, paymentMethod, errorElementId) {
        if (!referenceNo || referenceNo.trim() === '') {
            return Promise.resolve(false);
        }
        
        const formData = new FormData();
        formData.append('reference_no', referenceNo.trim());
        formData.append('payment_method', paymentMethod);
        
        return fetch('../controllers/checkReferenceNumber.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const errorElement = document.getElementById(errorElementId);
            if (data.exists) {
                if (errorElement) {
                    errorElement.textContent = data.message;
                    errorElement.style.display = 'block';
                }
                return true; // Reference number exists
            } else {
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
                return false; // Reference number is available
            }
        })
        .catch(error => {
            console.error('Error checking reference number:', error);
            return false;
        });
    }

    // GCash reference number validation
    const gcashRefNumInput = document.getElementById('gcashrefNum');
    if (gcashRefNumInput) {
        let gcashRefCheckTimeout;
        gcashRefNumInput.addEventListener('blur', function() {
            const refNum = this.value.trim();
            if (refNum) {
                clearTimeout(gcashRefCheckTimeout);
                gcashRefCheckTimeout = setTimeout(() => {
                    checkReferenceNumber(refNum, 'GCash', 'gcashrefNumError');
                }, 500);
            } else {
                document.getElementById('gcashrefNumError').style.display = 'none';
            }
        });
    }

    // PayMaya reference number validation
    const mayaRefNumInput = document.getElementById('mayarefNum');
    if (mayaRefNumInput) {
        let mayaRefCheckTimeout;
        mayaRefNumInput.addEventListener('blur', function() {
            const refNum = this.value.trim();
            if (refNum) {
                clearTimeout(mayaRefCheckTimeout);
                mayaRefCheckTimeout = setTimeout(() => {
                    checkReferenceNumber(refNum, 'PayMaya', 'mayarefNumError');
                }, 500);
            } else {
                document.getElementById('mayarefNumError').style.display = 'none';
            }
        });
    }

    // Form validation before submit
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            const paymentMethod = document.getElementById('paymentMethod').value;
            let hasError = false;
            
            if (paymentMethod === 'PayMaya') {
                const mayaNum = document.getElementById('mayaNum').value.trim();
                if (mayaNum.length !== 11 || !/^\d{11}$/.test(mayaNum)) {
                    e.preventDefault();
                    alert('Please enter a valid 11-digit PayMaya number.');
                    document.getElementById('mayaNum').focus();
                    return false;
                }
                
                // Check reference number
                const mayaRefNum = document.getElementById('mayarefNum').value.trim();
                if (mayaRefNum) {
                    e.preventDefault(); // Prevent submit while checking
                    checkReferenceNumber(mayaRefNum, 'PayMaya', 'mayarefNumError')
                        .then(exists => {
                            if (exists) {
                                alert('This reference number has already been used. Please use a different reference number.');
                                document.getElementById('mayarefNum').focus();
                            } else {
                                // Re-submit the form if reference number is valid
                                paymentForm.submit();
                            }
                        });
                    return false;
                }
            } else if (paymentMethod === 'GCash') {
                const gcashNum = document.getElementById('gcashNum').value.trim();
                if (gcashNum.length !== 11 || !/^\d{11}$/.test(gcashNum)) {
                    e.preventDefault();
                    alert('Please enter a valid 11-digit GCash number.');
                    document.getElementById('gcashNum').focus();
                    return false;
                }
                
                // Check reference number
                const gcashRefNum = document.getElementById('gcashrefNum').value.trim();
                if (gcashRefNum) {
                    e.preventDefault(); // Prevent submit while checking
                    checkReferenceNumber(gcashRefNum, 'GCash', 'gcashrefNumError')
                        .then(exists => {
                            if (exists) {
                                alert('This reference number has already been used. Please use a different reference number.');
                                document.getElementById('gcashrefNum').focus();
                            } else {
                                // Re-submit the form if reference number is valid
                                paymentForm.submit();
                            }
                        });
                    return false;
                }
            }
        });
    }
});
</script>

</body>
</html>