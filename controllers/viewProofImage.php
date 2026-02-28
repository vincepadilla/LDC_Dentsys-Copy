<?php
session_start();
include_once('../database/config.php');

// Check if user is admin
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

// Get payment_id from query parameter
$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';

if (empty($payment_id)) {
    http_response_code(400);
    die('Payment ID is required');
}

// Fetch payment details including proof image path
$query = "SELECT payment_id, appointment_id, method, account_name, reference_no, proof_image, created_at 
          FROM payment 
          WHERE payment_id = ? 
          LIMIT 1";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment || empty($payment['proof_image'])) {
    http_response_code(404);
    die('Payment or proof image not found');
}

// Normalize the image path - extract just the filename
$proof_image = trim($payment['proof_image']);

// Remove all relative path prefixes (../ or ./) recursively
while (preg_match('#^\.\.?/#', $proof_image)) {
    $proof_image = preg_replace('#^\.\.?/#', '', $proof_image);
}

// Remove leading slashes
$proof_image = ltrim($proof_image, '/');

// Extract just the filename - get everything after the last slash or after 'uploads/'
if (strpos($proof_image, 'uploads/') !== false) {
    // Get everything after the last 'uploads/'
    $parts = explode('uploads/', $proof_image);
    $filename = trim(end($parts));
    // Remove any leading slashes from filename
    $filename = ltrim($filename, '/');
} else {
    // No uploads/ found, get just the filename (after last slash)
    $filename = basename($proof_image);
}

// Build the actual file path (relative to controllers folder)
$image_path = '../uploads/' . $filename;

// Check if file exists
if (!file_exists($image_path)) {
    http_response_code(404);
    die('Proof image file not found: ' . htmlspecialchars($image_path));
}

// Get image info
$image_info = getimagesize($image_path);
if ($image_info === false) {
    http_response_code(500);
    die('Invalid image file or unsupported format');
}

// Check if image format is supported by FPDF (JPEG, PNG, GIF)
$image_type = $image_info[2];
if (!in_array($image_type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
    http_response_code(500);
    die('Unsupported image format. Only JPEG, PNG, and GIF are supported.');
}

// Include FPDF library
require_once('../libraries/fpdf/fpdf.php');

// Create PDF
class PDF extends FPDF
{
    function Header()
    {
        // Header
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, 'Payment Proof Image', 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer()
    {
        // Footer
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Create PDF instance
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Add payment information
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Payment Information', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(60, 7, 'Payment ID:', 0, 0, 'L');
$pdf->Cell(0, 7, htmlspecialchars($payment['payment_id']), 0, 1, 'L');

$pdf->Cell(60, 7, 'Appointment ID:', 0, 0, 'L');
$pdf->Cell(0, 7, htmlspecialchars($payment['appointment_id']), 0, 1, 'L');

$pdf->Cell(60, 7, 'Payment Method:', 0, 0, 'L');
$pdf->Cell(0, 7, htmlspecialchars($payment['method']), 0, 1, 'L');

if (!empty($payment['account_name'])) {
    $pdf->Cell(60, 7, 'Account Name:', 0, 0, 'L');
    $pdf->Cell(0, 7, htmlspecialchars($payment['account_name']), 0, 1, 'L');
}

if (!empty($payment['reference_no'])) {
    $pdf->Cell(60, 7, 'Reference Number:', 0, 0, 'L');
    $pdf->Cell(0, 7, htmlspecialchars($payment['reference_no']), 0, 1, 'L');
}

$pdf->Cell(60, 7, 'Date:', 0, 0, 'L');
$pdf->Cell(0, 7, date('F j, Y g:i A', strtotime($payment['created_at'])), 0, 1, 'L');

$pdf->Ln(8);

// Add separator line
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// Calculate image dimensions to fit on page
$max_width = 190; // A4 width (210mm) - 20mm margins
$max_height = 250; // Leave space for header and footer

$img_width_px = $image_info[0];
$img_height_px = $image_info[1];

// Calculate aspect ratio
$aspect_ratio = $img_width_px / $img_height_px;

// Calculate display dimensions to fit within max dimensions while maintaining aspect ratio
if ($max_width / $aspect_ratio <= $max_height) {
    // Width is the limiting factor
    $display_width = $max_width;
    $display_height = $max_width / $aspect_ratio;
} else {
    // Height is the limiting factor
    $display_height = $max_height;
    $display_width = $max_height * $aspect_ratio;
}

// Center the image horizontally
$x = ($pdf->GetPageWidth() - $display_width) / 2;
$y = $pdf->GetY();

// Add image to PDF (FPDF Image method accepts dimensions in mm)
// FPDF will automatically handle the image format based on file extension
$pdf->Image($image_path, $x, $y, $display_width, $display_height);

// Output PDF (I = inline, opens in browser)
$pdf->Output('I', 'Payment_Proof_' . $payment['payment_id'] . '.pdf');
?>
