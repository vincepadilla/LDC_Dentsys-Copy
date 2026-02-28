<?php
session_start();
include_once('../database/config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin' || empty($_SESSION['admin_verified'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get form data
    $patient_id = trim($_POST['patient_id'] ?? '');
    $service_id = trim($_POST['service_id'] ?? '');
    $team_id = trim($_POST['team_id'] ?? '');
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $time_slot = trim($_POST['time_slot'] ?? '');
    $branch = trim($_POST['branch'] ?? '');

    // Validate required fields
    if (empty($patient_id) || empty($service_id) || empty($team_id) || empty($appointment_date) || empty($time_slot) || empty($branch)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    // Time slot mapping
    $timeMap = [
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

    if (!isset($timeMap[$time_slot])) {
        echo json_encode(['success' => false, 'message' => 'Invalid time slot selected.']);
        exit();
    }

    $appointment_time = $timeMap[$time_slot];

    // Validate date is not in the past
    $today = date('Y-m-d');
    if ($appointment_date < $today) {
        echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past.']);
        exit();
    }

    // Check for double booking
    $checkBooking = $con->prepare("
        SELECT appointment_id FROM appointments 
        WHERE appointment_date = ? 
        AND time_slot = ? 
        AND team_id = ? 
        AND status NOT IN ('Cancelled', 'No-show')
        LIMIT 1
    ");
    $checkBooking->bind_param("sss", $appointment_date, $time_slot, $team_id);
    $checkBooking->execute();
    $bookingResult = $checkBooking->get_result();

    if ($bookingResult->num_rows > 0) {
        $checkBooking->close();
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please select another time.']);
        exit();
    }
    $checkBooking->close();

    // Generate appointment ID
    function generateAppointmentID($con) {
        $query = "SELECT appointment_id FROM appointments ORDER BY appointment_id DESC LIMIT 1";
        $result = mysqli_query($con, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $lastID = $row['appointment_id'];
            $number = intval(substr($lastID, 1));
            $newNumber = $number + 1;
            return 'A' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
        }
        return 'A001';
    }

    $appointment_id = generateAppointmentID($con);

    // Insert appointment
    $insert = $con->prepare("
        INSERT INTO appointments 
        (appointment_id, patient_id, team_id, service_id, branch, appointment_date, appointment_time, time_slot, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
    ");
    $insert->bind_param("ssssssss", $appointment_id, $patient_id, $team_id, $service_id, $branch, $appointment_date, $appointment_time, $time_slot);

    if ($insert->execute()) {
        // Get patient and service details for email
        $patientQuery = $con->prepare("
            SELECT p.first_name, p.last_name, p.email, u.user_id
            FROM patient_information p
            LEFT JOIN users u ON p.user_id = u.user_id
            WHERE p.patient_id = ?
        ");
        $patientQuery->bind_param("s", $patient_id);
        $patientQuery->execute();
        $patientResult = $patientQuery->get_result();
        $patient = $patientResult->fetch_assoc();
        $patientQuery->close();

        // Get service details
        $serviceQuery = $con->prepare("SELECT service_category, sub_service FROM services WHERE service_id = ?");
        $serviceQuery->bind_param("s", $service_id);
        $serviceQuery->execute();
        $serviceResult = $serviceQuery->get_result();
        $service = $serviceResult->fetch_assoc();
        $serviceQuery->close();

        // Get dentist details
        $dentistQuery = $con->prepare("SELECT first_name, last_name FROM multidisciplinary_dental_team WHERE team_id = ?");
        $dentistQuery->bind_param("s", $team_id);
        $dentistQuery->execute();
        $dentistResult = $dentistQuery->get_result();
        $dentist = $dentistResult->fetch_assoc();
        $dentistQuery->close();

        // Send email notification if patient email exists
        if (!empty($patient['email'])) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'padillavincehenrick@gmail.com';
                $mail->Password = 'glxd csoa ispj bvjg';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $patient_name = trim($patient['first_name'] . ' ' . $patient['last_name']);
                $service_name = !empty($service['sub_service']) ? $service['sub_service'] : $service['service_category'];
                $dentist_name = 'Dr. ' . trim($dentist['first_name'] . ' ' . $dentist['last_name']);

                $mail->setFrom('padillavincehenrick@gmail.com', 'Landero Dental Clinic');
                $mail->addAddress($patient['email'], $patient_name);

                $mail->isHTML(true);
                $mail->Subject = 'New Appointment Scheduled - Landero Dental Clinic';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #166088;'>Appointment Scheduled</h2>
                        <p>Dear {$patient_name},</p>
                        <p>A new appointment has been scheduled for you:</p>
                        <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <p><strong>Appointment ID:</strong> {$appointment_id}</p>
                            <p><strong>Service:</strong> {$service_name}</p>
                            <p><strong>Dentist:</strong> {$dentist_name}</p>
                            <p><strong>Date:</strong> " . date('F j, Y', strtotime($appointment_date)) . "</p>
                            <p><strong>Time:</strong> {$appointment_time}</p>
                            <p><strong>Branch:</strong> {$branch}</p>
                        </div>
                        <p>Please confirm your appointment in your account. Thank you!</p>
                    </div>
                ";

                $mail->send();
            } catch (Exception $e) {
                // Email failed but appointment was created
                error_log("Email sending failed: " . $mail->ErrorInfo);
            }
        }

        $insert->close();
        echo json_encode(['success' => true, 'message' => 'Appointment added successfully.', 'appointment_id' => $appointment_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add appointment: ' . $insert->error]);
    }
    $insert->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
