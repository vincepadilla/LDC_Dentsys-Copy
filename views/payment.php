<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // Redirect to login page
    header("Location: login.php");
    exit("You must be logged in to view this page.");
}

define("TITLE", "Payment");
include_once('../layouts/header.php');
include_once('../database/config.php');

$fname = $lname = $birthdate = $age = $email = $gender = $phone = '';
$address = $service_id = $subService = $branch = $date = $time = '';
$price = 500;

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
?>

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
                    <div class="appointment-details">
                        <div class="detail-row">
                            <div class="detail-label">Service</div>
                            <div class="detail-value"><?= ucwords($service_name) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Sub-Service</div>
                            <div class="detail-value"><?= ucwords($subService) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Dentist</div>
                            <div class="detail-value"><?= strtoupper($dentist) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Branch</div>
                            <div class="detail-value"><?= strtoupper($branch) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Date</div>
                            <div class="detail-value"><?= date('F j, Y', strtotime($date)) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Time Slot</div>
                            <div class="detail-value"><?= $time ?></div>
                        </div>
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
            </div>

            <!-- Payment Information -->
            <div class="payment-section">
                <div class="section-header">
                    <h2>Payment Information</h2>
                    <p>Complete payment to confirm booking.</p>
                </div>

                <div class="payment-method-section">
                    <h3 class="section-title">Payment Method</h3>
                    
                    <div class="payment-method-selector">
                        <select name="paymentMethod" id="paymentMethod" required>
                            <option value="">Select payment method</option>
                            <option value="GCash">GCash</option>
                            <option value="PayMaya">PayMaya</option>
                        </select>
                    </div>

                    <!-- GCash Section -->
                    <div id="gcashDetails" class="payment-details" style="display: none;">
                        <div class="payment-option">
                            <div class="payment-header">
                                <div class="payment-logo">
                                    <i class="fas fa-mobile-alt"></i>
                                    <span>GCash</span>
                                </div>
                                <div class="payment-qr">
                                    <p>Scan to Pay via GCash</p>
                                    <div class="qr-code">
                                        <img src="../assets/images/gcash.jpg" alt="GCash QR Code">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="account-info">
                                <p>Or use Account Number:</p>
                                <div class="account-number">09123456789</div>
                            </div>
                            
                            <div class="payment-form">
                                <div class="form-group">
                                    <label for="gcashaccName">Account Name</label>
                                    <input type="text" name="gcashaccName" id="gcashaccName" placeholder="Your Account Name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gcashNum">GCash Number</label>
                                    <input type="text" name="gcashNum" id="gcashNum" placeholder="Your GCash Account Number" maxlength="11" pattern="\d{11}">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gcashAmount">Payment Amount You've Sent</label>
                                    <input type="number" name="gcashAmount" id="gcashAmount" placeholder="Amount Sent" min="500" step="0.01">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gcashrefNum">Reference Number <span style="color: #dc2626;">*</span></label>
                                    <input type="text" name="gcashrefNum" id="gcashrefNum" placeholder="Reference No." required>
                                    <small class="reference-error" id="gcashrefNumError" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 5px;"></small>
                                </div>
                                
                                <div class="form-group">
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
                    <div id="mayaDetails" class="payment-details" style="display: none;">
                        <div class="payment-option">
                            <div class="payment-header">
                                <div class="payment-logo">
                                    <i class="fas fa-wallet"></i>
                                    <span>PayMaya</span>
                                </div>
                                <div class="payment-qr">
                                    <p>Scan to Pay via PayMaya</p>
                                    <div class="qr-code">
                                        <img src="../assets/images/maya.png" alt="Maya QR Code">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="account-info">
                                <p>Or use Account Number:</p>
                                <div class="account-number">0915 067 2948</div>
                            </div>
                            
                            <div class="payment-form">
                                <div class="form-group">
                                    <label for="mayaaccName">Account Name</label>
                                    <input type="text" name="mayaaccName" id="mayaaccName" placeholder="Your Account Name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="mayaNum">PayMaya Number</label>
                                    <input type="text" name="mayaNum" id="mayaNum" placeholder="Your PayMaya Account Number" maxlength="11" pattern="\d{11}" inputmode="numeric">
                                    <small class="form-hint">Enter 11 digits only</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="mayaAmount">Payment Amount You've Sent</label>
                                    <input type="number" name="mayaAmount" id="mayaAmount" placeholder="Amount Sent" min="500" step="0.01">
                                </div>
                                
                                <div class="form-group">
                                    <label for="mayarefNum">Reference Number <span style="color: #dc2626;">*</span></label>
                                    <input type="text" name="mayarefNum" id="mayarefNum" placeholder="Reference No." required>
                                    <small class="reference-error" id="mayarefNumError" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 5px;"></small>
                                </div>
                                
                                <div class="form-group">
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
                
                <div class="fee-notice">
                    <p><strong>Consultation Fee:</strong> ₱500.00</p>
                    <p>This appointment fee will be deducted from the total payment.</p>
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

    if (method === 'GCash') {
        document.getElementById('gcashDetails').style.display = 'block';
        toggleFields(gcashFields, true);
    } else if (method === 'PayMaya') {
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