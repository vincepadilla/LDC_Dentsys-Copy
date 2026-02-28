<?php
session_start();
require('../libraries/fpdf/fpdf.php');
include_once("../database/config.php");

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    die("Unauthorized access.");
}

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$appointment_id = $_GET['id'];
$user_id = $_SESSION['userID'];

// Fetch comprehensive appointment data with patient, service, payment, and dentist information
$stmt = $con->prepare("
    SELECT 
        a.appointment_id, 
        a.appointment_date, 
        a.appointment_time, 
        a.status,
        a.created_at,
        a.branch,
        s.service_category,
        s.sub_service,
        s.description as service_description,
        s.price as service_price,
        ua.user_id,
        ua.username,
        ua.first_name,
        ua.last_name,
        ua.email,
        ua.phone,
        p.patient_id,
        p.birthdate,
        p.gender,
        p.address,
        d.team_id,
        d.first_name as dentist_first_name,
        d.last_name as dentist_last_name,
        pay.payment_id,
        pay.method as payment_method,
        pay.account_name,
        pay.account_number,
        pay.amount as payment_amount,
        pay.reference_no,
        pay.status as payment_status,
        pay.created_at as payment_created_at
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.service_id
    INNER JOIN patient_information p ON a.patient_id = p.patient_id
    INNER JOIN user_account ua ON p.user_id = ua.user_id
    LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
    LEFT JOIN payment pay ON a.appointment_id = pay.appointment_id
    WHERE a.appointment_id = ? AND ua.user_id = ?
");
$stmt->bind_param("ss", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Appointment not found or you don't have permission to view this receipt.");
}

$data = $result->fetch_assoc();

// Create PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// Colors
$headerColor = array(41, 128, 185); // Blue
$textColor = array(44, 62, 80); // Dark blue-gray
$lightGray = array(236, 240, 241); // Light gray

// Header Section
$pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Rect(0, 0, 210, 50, 'F');

// Add logo
$logoPath = '../landerologo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 5, 30, 0);
}

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetY(15);
$pdf->Cell(0, 8, 'LANDERO DENTAL CLINIC', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetY(23);
$pdf->Cell(0, 6, 'APPOINTMENT RECEIPT', 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->SetY(30);
$pdf->Cell(0, 5, 'Official Appointment Receipt', 0, 1, 'C');

// Reset text color
$pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
$pdf->SetY(60);

// Receipt Information
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Receipt Information', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->Cell(95, 7, 'Receipt Number: ' . $data['appointment_id'], 0, 0, 'L', true);
$pdf->Cell(95, 7, 'Date Generated: ' . date('F j, Y g:i A'), 0, 1, 'R', true);
$pdf->Ln(5);

// Patient Information Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, 'PATIENT INFORMATION', 0, 1, 'L', true);
$pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
$pdf->SetFont('Arial', '', 10);

$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->Cell(95, 7, 'Patient Name: ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']), 0, 1, 'L', true);
$pdf->Ln(3);

// Appointment Details Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, 'APPOINTMENT DETAILS', 0, 1, 'L', true);
$pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
$pdf->SetFont('Arial', '', 10);

$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->Cell(95, 7, 'Appointment ID: ' . htmlspecialchars($data['appointment_id']), 0, 0, 'L', true);
$pdf->Cell(95, 7, 'Status: ' . htmlspecialchars($data['status']), 0, 1, 'L', true);

$pdf->Cell(95, 7, 'Appointment Date: ' . ($data['appointment_date'] ? date('F j, Y', strtotime($data['appointment_date'])) : 'N/A'), 0, 0, 'L', false);
$pdf->Cell(95, 7, 'Appointment Time: ' . htmlspecialchars($data['appointment_time'] ?? 'N/A'), 0, 1, 'L', false);

$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->Cell(95, 7, 'Service Category: ' . htmlspecialchars($data['service_category'] ?? 'N/A'), 0, 0, 'L', true);
$pdf->Cell(95, 7, 'Sub Service: ' . htmlspecialchars($data['sub_service'] ?? 'N/A'), 0, 1, 'L', true);

$dentistName = 'N/A';
if (!empty($data['dentist_first_name']) && !empty($data['dentist_last_name'])) {
    $dentistName = 'Dr. ' . htmlspecialchars($data['dentist_first_name'] . ' ' . $data['dentist_last_name']);
}

$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->Cell(95, 7, 'Dentist: ' . $dentistName, 0, 0, 'L', true);
$pdf->Cell(95, 7, 'Branch: ' . htmlspecialchars($data['branch'] ?? 'N/A'), 0, 1, 'L', true);

$pdf->Cell(95, 7, 'Service Price: ' . ($data['service_price'] ? 'PHP ' . number_format($data['service_price'], 2) : 'N/A'), 0, 0, 'L', false);
$pdf->Cell(95, 7, 'Date Created: ' . ($data['created_at'] ? date('F j, Y g:i A', strtotime($data['created_at'])) : 'N/A'), 0, 1, 'L', false);
$pdf->Ln(3);

// Payment/Transaction Details Section
if (!empty($data['payment_id'])) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'PAYMENT/TRANSACTION DETAILS', 0, 1, 'L', true);
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->SetFont('Arial', '', 10);

    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->Cell(95, 7, 'Payment Method: ' . htmlspecialchars($data['payment_method'] ?? 'N/A'), 0, 0, 'L', true);
    $pdf->Cell(95, 7, 'Amount: ' . ($data['payment_amount'] ? 'PHP ' . number_format($data['payment_amount'], 2) : 'N/A'), 0, 1, 'L', true);

    $pdf->Cell(95, 7, 'Payment Status: ' . ucfirst(htmlspecialchars($data['payment_status'] ?? 'N/A')), 0, 0, 'L', false);
    $pdf->Cell(95, 7, 'Payment Date: ' . ($data['payment_created_at'] ? date('F j, Y g:i A', strtotime($data['payment_created_at'])) : 'N/A'), 0, 1, 'L', false);
} else {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'PAYMENT/TRANSACTION DETAILS', 0, 1, 'L', true);
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->Cell(0, 7, 'No payment information available for this appointment.', 0, 1, 'L', true);
}

$pdf->Ln(10);

// Footer Section
$pdf->SetY(-30);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell(0, 5, 'This is an official receipt generated on ' . date('F j, Y g:i A'), 0, 1, 'C');
$pdf->Cell(0, 5, 'Please keep this receipt for your records.', 0, 1, 'C');
$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
$pdf->Cell(0, 5, 'Thank you for choosing our dental clinic!', 0, 1, 'C');

// Output PDF
$pdf->Output('I', 'Appointment_Receipt_' . $data['appointment_id'] . '.pdf');
?>

