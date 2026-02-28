<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // Redirect to login page
    header("Location: login.php");
    exit("You must be logged in to view this page.");
}

define("TITLE", "Payment");
include_once('../database/config.php');

// Include PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../libraries/PhpMailer/src/Exception.php';
require_once '../libraries/PhpMailer/src/PHPMailer.php';
require_once '../libraries/PhpMailer/src/SMTP.php';

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

// Override with POST values if form submitted (coming from index.php modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $fname = htmlspecialchars($_POST['fname'] ?? $fname);
    $lname = htmlspecialchars($_POST['lname'] ?? $lname);
    $birthdate = htmlspecialchars($_POST['birthdate'] ?? $birthdate);
    $age = htmlspecialchars($_POST['age'] ?? $age);
    $email = htmlspecialchars($_POST['email'] ?? $email);
    $gender = htmlspecialchars($_POST['gender'] ?? $gender);
    $phone = htmlspecialchars($_POST['phone'] ?? $phone);
    $address = htmlspecialchars($_POST['address'] ?? $address);

    // Get sub_service from POST (check both possible field names for compatibility)
    $subService = htmlspecialchars($_POST['sub_service'] ?? $_POST['subService'] ?? $subService);
    $subservice_id = ''; // Will store the subservice_id if it's a subservice

    // Map subService to service_id and subservice_id
    // For subservices, we need to use the parent service_id for the appointments table
    switch ($subService) {
        // General Dentistry
        case 'Checkups':                     
            $service_id = 'S001'; 
            $subservice_id = 'S001'; // Main service, no subservice
            break;
        case 'Oral Prophylaxis (Cleaning)':  
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1001'; // Subservice_id
            break;
        case 'Fluoride Application':         
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1002'; // Subservice_id
            break;
        case 'Pit & Fissure Sealants':       
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1003'; // Subservice_id
            break;
        case 'Tooth Restoration (Pasta)':    
            $service_id = 'S001'; // Parent service_id for appointments table
            $subservice_id = 'S1004'; // Subservice_id
            break;
        // Orthodontics
        case 'Braces':                       
            $service_id = 'S002'; 
            $subservice_id = 'S002'; // Main service, no subservice
            break;
        case 'Retainers':                    
            $service_id = 'S002'; // Parent service_id for appointments table
            $subservice_id = 'S2001'; // Subservice_id
            break;
        // Oral Surgery
        case 'Tooth Extraction (Bunot)':     
            $service_id = 'S003'; 
            $subservice_id = 'S003'; // Main service, no subservice
            break;
        // Endodontics
        case 'Root Canal Treatment':         
            $service_id = 'S004'; 
            $subservice_id = 'S004'; // Main service, no subservice
            break;
        // Prosthodontics
        case 'Crowns':                       
            $service_id = 'S005'; 
            $subservice_id = 'S005'; // Main service, no subservice
            break;
        case 'Dentures':                     
            $service_id = 'S005'; // Parent service_id for appointments table
            $subservice_id = 'S5001'; // Subservice_id
            break;
        default: 
            $service_id = 'N/A'; 
            $subservice_id = 'N/A';
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

    $branch = htmlspecialchars($_POST['branch'] ?? $branch);

    // Format branch names
    if (strtolower($branch) === 'comembo') {
        $branch = 'Comembo Branch';
    } elseif (strtolower($branch) === 'taytay') {
        $branch = 'Taytay Rizal Branch';
    }

    // If user clicked Reserve Appointment (walk-in), save to walkin_appointments table
    if (!empty($_POST['reserve_walkin'])) {
        // Get or create patient_id for the logged-in user
        $userID = $_SESSION['userID'];
        $userID_escaped = mysqli_real_escape_string($con, $userID);
        
        // Check if patient exists
        $checkPatientQuery = "SELECT patient_id FROM patient_information WHERE user_id = '$userID_escaped' LIMIT 1";
        $checkPatientResult = mysqli_query($con, $checkPatientQuery);
        $existingPatient = mysqli_fetch_assoc($checkPatientResult);
        
        if (empty($existingPatient)) {
            // Patient doesn't exist, create new patient record
            // Function to generate new prefixed ID
            function generateID($prefix, $table, $column, $con) {
                $query = "SELECT $column FROM $table ORDER BY $column DESC LIMIT 1";
                $result = mysqli_query($con, $query);
                $row = mysqli_fetch_assoc($result);
                if ($row) {
                    $lastNum = intval(substr($row[$column], strlen($prefix))) + 1;
                } else {
                    $lastNum = 1;
                }
                return $prefix . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
            }
            
            $patient_id = generateID('P', 'patient_information', 'patient_id', $con);
            $fname_escaped = mysqli_real_escape_string($con, trim($fname));
            $lname_escaped = mysqli_real_escape_string($con, trim($lname));
            $birthdate_escaped = mysqli_real_escape_string($con, trim($birthdate));
            $gender_escaped = mysqli_real_escape_string($con, trim($gender));
            $phone_escaped = mysqli_real_escape_string($con, trim($phone));
            $email_escaped = mysqli_real_escape_string($con, trim($email));
            $address_escaped = mysqli_real_escape_string($con, trim($address));
            
            $insertPatient = "INSERT INTO patient_information 
                (patient_id, user_id, first_name, last_name, birthdate, gender, phone, email, address) 
                VALUES 
                ('$patient_id', '$userID', '$fname_escaped', '$lname_escaped', '$birthdate_escaped', '$gender_escaped', '$phone_escaped', '$email_escaped', '$address_escaped')";
            
            if (!mysqli_query($con, $insertPatient)) {
                error_log('Patient creation error: ' . mysqli_error($con));
                echo "<script>alert('Error creating patient record. Please try again.');
                window.location.href='index.php';</script>";
                exit();
            }
        } else {
            $patient_id = $existingPatient['patient_id'];
        }
        
        // Ensure service_name is set - if still empty, use subService as fallback
        if (empty($service_name) || $service_name === 'Unknown Service') {
            $service_name = $subService ?: 'Walk-In Service';
        }
        
        // Generate walkin_id as VARCHAR (e.g., WI001, WI002, etc.)
        function generateWalkInID($con) {
            $query = "SELECT walkin_id FROM walkin_appointments ORDER BY walkin_id DESC LIMIT 1";
            $result = mysqli_query($con, $query);
            $row = mysqli_fetch_assoc($result);
            if ($row && !empty($row['walkin_id'])) {
                // Extract number from walkin_id (e.g., WI001 -> 1)
                $lastId = $row['walkin_id'];
                if (preg_match('/WI(\d+)/', $lastId, $matches)) {
                    $lastNum = intval($matches[1]) + 1;
                } else {
                    $lastNum = 1;
                }
            } else {
                $lastNum = 1;
            }
            return 'WI' . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
        }
        
        $walkin_id = generateWalkInID($con);
        
        // Prepare data for database insertion (from lines 322-342: Service, Sub-Service, Dentist, Branch)
        // Ensure sub_service is not empty - it's required
        if (empty($subService) || trim($subService) === '') {
            error_log('Walk-in error: sub_service is empty');
            echo "<script>alert('Error: Sub-service is required. Please go back and select a service.');
            window.location.href='index.php';</script>";
            exit();
        }
        
        $service_name_db = mysqli_real_escape_string($con, trim($service_name));
        $sub_service_db = mysqli_real_escape_string($con, trim($subService));
        $dentist_name_db = mysqli_real_escape_string($con, trim($dentist));
        $branch_db = mysqli_real_escape_string($con, trim($branch));
        $patient_id_escaped = mysqli_real_escape_string($con, $patient_id);
        $walkin_id_escaped = mysqli_real_escape_string($con, $walkin_id);
        
        // Insert into walkin_appointments table with walkin_id as VARCHAR
        $insertWalkIn = "INSERT INTO walkin_appointments 
            (walkin_id, patient_id, service, sub_service, dentist_name, branch, status) 
            VALUES 
            ('$walkin_id_escaped', '$patient_id_escaped', '$service_name_db', '$sub_service_db', '$dentist_name_db', '$branch_db', 'Walk-in')";
        
        if (mysqli_query($con, $insertWalkIn)) {
            
            // Retrieve patient email from database using patient_id
            $patientEmailQuery = "SELECT email, first_name, last_name FROM patient_information WHERE patient_id = '$patient_id_escaped' LIMIT 1";
            $patientEmailResult = mysqli_query($con, $patientEmailQuery);
            $patientData = mysqli_fetch_assoc($patientEmailResult);
            
            // Use email from database, fallback to POST data if not available
            $patientEmail = !empty($patientData['email']) ? $patientData['email'] : $email;
            
            // Send email ticket if patient email exists and is valid
            if (!empty($patientEmail) && filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'mlanderodentalclinic@gmail.com';
                    $mail->Password = 'xrfp cpvv ckdv jmht';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $patient_name = trim(($patientData['first_name'] ?? $fname) . ' ' . ($patientData['last_name'] ?? $lname));
                    if (empty(trim($patient_name))) {
                        $patient_name = 'Patient';
                    }

                    $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
                    $mail->addAddress($patientEmail, $patient_name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Walk-In Appointment Reservation - Landero Dental Clinic';

                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
                            <div style='background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                                <h2 style='color: #166088; margin-top: 0;'>Walk-In Appointment Reservation Confirmed</h2>
                                <p>Dear {$patient_name},</p>
                                <p>Your walk-in appointment has been successfully reserved. Please present this ticket when you visit the clinic.</p>
                                
                                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #166088;'>
                                    <h3 style='color: #166088; margin-top: 0;'>Appointment Ticket</h3>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Ticket ID:</strong> <span style='font-size: 18px; font-weight: bold; color: #166088;'>{$walkin_id}</span></p>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Patient Name:</strong> {$patient_name}</p>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Service:</strong> {$service_name_db}</p>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Sub-Service:</strong> {$sub_service_db}</p>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Dentist:</strong> {$dentist_name_db}</p>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Branch:</strong> {$branch_db}</p>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Payment Method:</strong> Cash</p>
                                    <p style='margin: 10px 0;'><strong style='color: #333;'>Status:</strong> Walk-in</p>
                                </div>
                                
                                <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                                    <p style='margin: 0; color: #856404;'><strong>Important:</strong> Please bring this ticket (Ticket ID: <strong>{$walkin_id}</strong>) when you visit the clinic. The exact appointment date and time will be finalized with the receptionist during your visit.</p>
                                </div>
                                
                                <p style='margin-top: 20px;'>Thank you for choosing Landero Dental Clinic!</p>
                                <p style='margin: 5px 0; color: #666; font-size: 14px;'>If you have any questions, please contact us.</p>
                            </div>
                        </div>
                    ";

                    $mail->send();
                } catch (Exception $e) {
                    // Email failed but appointment was created - log error
                    error_log("Walk-in appointment email sending failed for patient {$patient_id}: " . $mail->ErrorInfo);
                }
            }
            
            // Store in session for account.php to display
            $_SESSION['walkin_appointment'] = [
                'walkin_id' => $walkin_id,
                'created_at' => date('Y-m-d H:i:s'),
                'patient_id' => $patient_id,
                'first_name' => $fname,
                'last_name' => $lname,
                'age' => $age,
                'gender' => $gender,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'service_id' => $service_id,
                'service_name' => $service_name_db,
                'sub_service' => $sub_service_db,
                'branch' => $branch_db,
                'dentist' => $dentist_name_db,
                'payment_method' => 'Cash',
                'status' => 'Walk-in',
            ];

            header("Location: account.php?walkin=1");
            exit();
        } else {
            // Error saving to database
            error_log('Walk-in appointment error: ' . mysqli_error($con));
            echo "<script>alert('Error saving walk-in appointment. Please try again.');
            window.location.href='index.php';</script>";
            exit();
        }
    }
}

// Calculate age (fallback)
if (!empty($birthdate) && $birthdate !== 'N/A') {
    try {
        $birthDateObj = new DateTime($birthdate);
        $todayObj = new DateTime();
        $age = $todayObj->diff($birthDateObj)->y;
    } catch (Exception $e) {
        // ignore
    }
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
    <style>
        /* Hover tooltip for calendar times */
        .cal-tooltip {
            position: fixed;
            z-index: 99999;
            max-width: 320px;
            background: rgba(17, 24, 39, 0.95);
            color: #fff;
            border-radius: 10px;
            padding: 12px 12px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
            font-size: 12px;
            line-height: 1.35;
            pointer-events: none;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity 120ms ease, transform 120ms ease;
        }
        .cal-tooltip.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .cal-tooltip .tt-title {
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .cal-tooltip .tt-row {
            margin: 6px 0;
        }
        .cal-tooltip .tt-label {
            font-weight: 700;
            display: inline-block;
            margin-right: 6px;
        }
        .cal-tooltip ul {
            margin: 6px 0 0 0;
            padding-left: 18px;
        }
        .cal-tooltip li {
            margin: 2px 0;
        }
        .cal-tooltip .tt-none {
            color: rgba(255,255,255,0.75);
            font-style: italic;
        }
        
        /* Responsive tooltip for mobile devices */
        @media (max-width: 480px) {
            .cal-tooltip {
                max-width: calc(100vw - 24px);
                padding: 10px;
                font-size: 11px;
                border-radius: 8px;
            }
            .cal-tooltip .tt-title {
                font-size: 11px;
                margin-bottom: 6px;
            }
            .cal-tooltip .tt-row {
                margin: 5px 0;
            }
            .cal-tooltip ul {
                padding-left: 16px;
                margin: 4px 0 0 0;
            }
            .cal-tooltip li {
                margin: 1px 0;
            }
        }
        
        @media (max-width: 360px) {
            .cal-tooltip {
                font-size: 10px;
                padding: 8px;
            }
            .cal-tooltip .tt-title {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>

<div class="payment-container">
    <div class="header-section">
        <h1>Walk-In Appointment Reservation</h1>
        <p>Review your details and check the clinic's schedule before reserving.</p>
    </div>

    <form id="paymentForm" action="walkin.php" method="POST">
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
                <input type="hidden" name="sub_service" value="<?= htmlspecialchars($subService) ?>">
                <input type="hidden" name="subService" value="<?= htmlspecialchars($subService) ?>">
                <input type="hidden" name="subservice_id" value="<?= $subservice_id ?? '' ?>">
                <input type="hidden" name="dentist" value="<?= $dentist ?>">
                <input type="hidden" name="branch" value="<?= $branch ?>">
                <!-- For walk-in flow, date and time are chosen at the clinic, so they are not required here -->
            </div>

            <!-- Doctor Availability (replaces Payment Information for walk-in) -->
            <div class="payment-section">
                <div class="section-header">
                    <h2>Doctor's Availability</h2>
                    <p>Below are the dentist’s available dates and times. Please check the schedule to know when you may visit.</p>
                </div>

                <div class="payment-method-section">
                    <h3 class="section-title">Clinic Calendar</h3>
                    <div id="doctorAvailabilityCalendar" class="availability-calendar"></div>
                    <p style="margin-top: 15px; color: #555; font-size: 0.9rem;">
                        This calendar shows general clinic operating days. Exact appointment date and time for walk-in payment
                        will be finalized with the receptionist during your visit.
                    </p>

                    <!-- Walk-in payments are always treated as Cash in the backend -->
                    <input type="hidden" name="paymentMethod" value="Cash">
                    <input type="hidden" name="reserve_walkin" value="1">

                    <div class="payment-form" style="margin-top: 20px;">
                        <button type="submit" class="pay-button" id="cashPayBtn">Submit</button>
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
// Doctor / clinic availability calendar based on admin time-slot scheduling
(function() {
    const calendarEl = document.getElementById('doctorAvailabilityCalendar');
    if (!calendarEl) return;

    const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    const totalSlotsPerDay = 11; // should match admin time-slot configuration

    const timeSlotKeys = ['firstBatch','secondBatch','thirdBatch','fourthBatch','fifthBatch','sixthBatch','sevenBatch','eightBatch','nineBatch','tenBatch','lastBatch'];
    const timeSlotLabels = {
        firstBatch:  '8:00AM-9:00AM',
        secondBatch: '9:00AM-10:00AM',
        thirdBatch:  '10:00AM-11:00AM',
        fourthBatch: '11:00AM-12:00PM',
        fifthBatch:  '1:00PM-2:00PM',
        sixthBatch:  '2:00PM-3:00PM',
        sevenBatch:  '3:00PM-4:00PM',
        eightBatch:  '4:00PM-5:00PM',
        nineBatch:   '5:00PM-6:00PM',
        tenBatch:    '6:00PM-7:00PM',
        lastBatch:   '7:00PM-8:00PM'
    };

    // Helper to format dates in local time as YYYY-MM-DD (avoids timezone shift issues)
    function formatLocalDate(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    let currentMonth = (new Date()).getMonth();
    let currentYear  = (new Date()).getFullYear();

    // Tooltip element
    const tooltipEl = document.createElement('div');
    tooltipEl.className = 'cal-tooltip';
    document.body.appendChild(tooltipEl);

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function uniqueValidKeys(arr) {
        const s = new Set();
        (arr || []).forEach(k => {
            if (timeSlotLabels[k]) s.add(k);
        });
        // keep consistent order
        return timeSlotKeys.filter(k => s.has(k));
    }

    function buildTooltipHtml(dateStr, dayData) {
        const bookedKeys = uniqueValidKeys(dayData?.bookedSlots || []);
        const blockedKeys = uniqueValidKeys(dayData?.blockedSlots || []);
        const availableKeys = timeSlotKeys.filter(k => !bookedKeys.includes(k) && !blockedKeys.includes(k));

        function listHtml(keys) {
            if (!keys.length) return `<div class="tt-none">None</div>`;
            return `<ul>${keys.map(k => `<li>${escapeHtml(timeSlotLabels[k])}</li>`).join('')}</ul>`;
        }

        return `
            <div class="tt-title">${escapeHtml(dateStr)}</div>
            <div class="tt-row"><span class="tt-label">Available (${availableKeys.length}):</span>${listHtml(availableKeys)}</div>
            <div class="tt-row"><span class="tt-label">Booked (${bookedKeys.length}):</span>${listHtml(bookedKeys)}</div>
            <div class="tt-row"><span class="tt-label">Unavailable (${blockedKeys.length}):</span>${listHtml(blockedKeys)}</div>
        `;
    }

    function positionTooltip(anchorRect) {
        const padding = 12;
        const maxW = Math.min(340, window.innerWidth - padding * 2);
        tooltipEl.style.maxWidth = maxW + 'px';

        const ttRect = tooltipEl.getBoundingClientRect();
        let left = anchorRect.left + (anchorRect.width / 2) - (ttRect.width / 2);
        let top  = anchorRect.top - ttRect.height - 10;

        // If not enough space above, place below
        if (top < padding) top = anchorRect.bottom + 10;

        // Clamp to viewport
        left = Math.max(padding, Math.min(left, window.innerWidth - ttRect.width - padding));
        top  = Math.max(padding, Math.min(top, window.innerHeight - ttRect.height - padding));

        tooltipEl.style.left = left + 'px';
        tooltipEl.style.top  = top + 'px';
    }

    async function loadMonthlyData(firstDay, lastDay) {
        const scheduleData = {};

        try {
            // 1) Load all blocked slots (for all dentists)
            const blockedResponse = await fetch('../controllers/get_blocked_slots.php');
            const blockedSlots = await blockedResponse.json();

            // 2) Pre-initialize all dates in range (using local date, not UTC)
            for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                const dateStr = formatLocalDate(d);
                scheduleData[dateStr] = { blockedSlots: [], bookedSlots: [] };
            }

            // 3) Collect blocked slots per date (dedupe later for display)
            blockedSlots.forEach(slot => {
                const date = slot.date;
                if (scheduleData[date]) {
                    scheduleData[date].blockedSlots.push(slot.time_slot);
                }
            });

            // 4) Load appointments per day (no dentist filter = whole clinic)
            const appointmentPromises = [];
            for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                const dateStr = formatLocalDate(d);
                appointmentPromises.push(
                    fetch(`../controllers/getAppointmentsAdmin.php?appointment_date=${dateStr}`)
                        .then(res => res.ok ? res.json() : [])
                        .then(slots => {
                            if (!Array.isArray(slots)) return;
                            if (!scheduleData[dateStr]) {
                                scheduleData[dateStr] = { blockedSlots: [], bookedSlots: [] };
                            }
                            scheduleData[dateStr].bookedSlots = slots;
                        })
                        .catch(() => {
                            // Fail silently for this date
                        })
                );
            }

            await Promise.all(appointmentPromises);
        } catch (err) {
            console.error('Error loading monthly availability data:', err);
        }

        return scheduleData;
    }

    async function renderCalendar() {
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay  = new Date(currentYear, currentMonth + 1, 0);

        const year  = currentYear;
        const month = currentMonth;

        const startingDay = firstDay.getDay(); // 0 = Sun

        // Fetch availability data for this month
        const scheduleData = await loadMonthlyData(firstDay, lastDay);

        let html = '<div class="availability-header">';
        html += '<button type="button" class="cal-nav prev">&lt;</button>';
        html += `<span class="cal-month">${monthNames[month]} ${year}</span>`;
        html += '<button type="button" class="cal-nav next">&gt;</button>';
        html += '</div>';

        html += '<div class="cal-grid">';
        const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        weekdays.forEach(d => {
            html += `<div class="cal-cell cal-head">${d}</div>`;
        });

        // Empty cells before first day
        for (let i = 0; i < startingDay; i++) {
            html += '<div class="cal-cell cal-empty"></div>';
        }

        const today = new Date();

        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const dateStr = formatLocalDate(date);
            const isToday = date.toDateString() === today.toDateString();

            const dayData = scheduleData[dateStr] || { blockedSlots: [], bookedSlots: [] };
            const bookedKeys = uniqueValidKeys(dayData.bookedSlots);
            const blockedKeys = uniqueValidKeys(dayData.blockedSlots);
            const availableKeys = timeSlotKeys.filter(k => !bookedKeys.includes(k) && !blockedKeys.includes(k));
            let available = availableKeys.length;
            let bookedCount = bookedKeys.length;
            let blockedCount = blockedKeys.length;

            let statusClass = 'status-available';
            let statusLabel = 'Plenty of slots';

            if (available === 0) {
                statusClass = 'status-full';
                statusLabel = 'Fully booked';
            } else if (bookedCount > 0 || blockedCount > 0) {
                statusClass = 'status-limited';
                statusLabel = 'Limited slots';
            }

            const classes = ['cal-cell', 'cal-day', statusClass];
            if (isToday) classes.push('cal-today');

            html += `
                <div class="${classes.join(' ')}" data-date="${dateStr}">
                    <div class="day-number">${day}</div>
                    <div class="day-status-text">${statusLabel}</div>
                    <div class="day-status-bars">
                        <span class="bar available-bar" title="Available">${available}</span>
                        <span class="bar booked-bar" title="Booked">${bookedCount}</span>
                        <span class="bar blocked-bar" title="Blocked">${blockedCount}</span>
                    </div>
                </div>
            `;
        }

        html += '</div>';
        html += '<div class="cal-legend">';
        html += '<span class="legend-item"><span class="legend-dot legend-available"></span> Available</span>';
        html += '<span class="legend-item"><span class="legend-dot legend-limited"></span> Limited</span>';
        html += '<span class="legend-item"><span class="legend-dot legend-full"></span> Fully booked / blocked</span>';
        html += '</div>';

        calendarEl.innerHTML = html;

        const prevBtn = calendarEl.querySelector('.cal-nav.prev');
        const nextBtn = calendarEl.querySelector('.cal-nav.next');
        if (prevBtn) prevBtn.onclick = () => { changeMonth(-1); };
        if (nextBtn) nextBtn.onclick = () => { changeMonth(1); };

        // Hover tooltip events (show times) - with touch support for mobile
        const dayCells = calendarEl.querySelectorAll('.cal-day[data-date]');
        let activeTooltipCell = null;
        
        function showTooltip(cell) {
            const dateStr = cell.getAttribute('data-date');
            const dayData = scheduleData[dateStr] || { blockedSlots: [], bookedSlots: [] };
            tooltipEl.innerHTML = buildTooltipHtml(dateStr, dayData);
            tooltipEl.classList.add('visible');
            // position after content set
            positionTooltip(cell.getBoundingClientRect());
            activeTooltipCell = cell;
        }
        
        function hideTooltip() {
            tooltipEl.classList.remove('visible');
            activeTooltipCell = null;
        }
        
        dayCells.forEach(cell => {
            // Mouse events for desktop
            cell.addEventListener('mouseenter', () => {
                if (!('ontouchstart' in window)) {
                    showTooltip(cell);
                }
            });
            cell.addEventListener('mouseleave', () => {
                if (!('ontouchstart' in window)) {
                    hideTooltip();
                }
            });
            
            // Touch events for mobile devices
            cell.addEventListener('touchstart', (e) => {
                e.preventDefault();
                // Hide previous tooltip if any
                if (activeTooltipCell && activeTooltipCell !== cell) {
                    hideTooltip();
                }
                // Toggle tooltip on tap
                if (activeTooltipCell === cell) {
                    hideTooltip();
                } else {
                    showTooltip(cell);
                }
            });
            
            // Click event as fallback for some mobile browsers
            cell.addEventListener('click', (e) => {
                if ('ontouchstart' in window) {
                    e.preventDefault();
                    if (activeTooltipCell === cell) {
                        hideTooltip();
                    } else {
                        showTooltip(cell);
                    }
                }
            });
        });
        
        // Hide tooltip when tapping outside on mobile
        document.addEventListener('touchstart', (e) => {
            if (activeTooltipCell && !activeTooltipCell.contains(e.target) && !tooltipEl.contains(e.target)) {
                hideTooltip();
            }
        });
    }

    function changeMonth(direction) {
        currentMonth += direction;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        } else if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar();
    }

    // Initial render
    renderCalendar();
})();
</script>

</body>
</html>