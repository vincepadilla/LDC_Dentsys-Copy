<?php
require('../fpdf/fpdf.php');
include_once("config.php");

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$appointment_id = $_GET['id'];

// Fetch appointment data
$stmt = $con->prepare("SELECT * FROM tbl_appointments WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Appointment not found.");
}

$appointment = $result->fetch_assoc();

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();

$pdf->Image('logo.png', 10, 10, 30); // x=10mm, y=10mm, width=30mm

// Set title below logo
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,50,'Dental Clinic Appointment Receipt',0,1,'C'); // add space due to logo height

$pdf->SetFont('Arial','',12);
$pdf->Ln(5);
$pdf->Cell(50,10,'Appointment ID:',0,0);
$pdf->Cell(0,10,$appointment['appointment_id'],0,1);

$pdf->Cell(50,10,'Date:',0,0);
$pdf->Cell(0,10,$appointment['appointment_date'],0,1);

$pdf->Cell(50,10,'Time:',0,0);
$pdf->Cell(0,10,$appointment['appointment_time'],0,1);

$pdf->Cell(50,10,'Service:',0,0);
$pdf->Cell(0,10,$appointment['service'],0,1);

$pdf->Cell(50,10,'Dentist:',0,0);
$pdf->Cell(0,10,$appointment['dentist'],0,1);

$pdf->Cell(50,10,'Status:',0,0);
$pdf->Cell(0,10,$appointment['status'],0,1);

$pdf->Ln(15);
$pdf->Cell(0,10,'Thank you for choosing our clinic!',0,1,'C');

// Output PDF
$pdf->Output('I', 'Appointment_Receipt.pdf');
?>
