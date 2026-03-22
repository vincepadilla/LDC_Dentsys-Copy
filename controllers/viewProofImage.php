<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

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

// Fetch payment details with appointment and patient information
$query = "SELECT 
            p.payment_id, 
            p.appointment_id, 
            p.method, 
            p.account_name, 
            p.account_number,
            p.reference_no, 
            p.proof_image, 
            p.amount,
            p.status as payment_status,
            p.created_at as payment_date,
            a.appointment_date,
            a.appointment_time,
            a.status as appointment_status,
            a.branch,
            pi.patient_id,
            pi.first_name,
            pi.last_name,
            pi.email,
            pi.phone,
            pi.address,
            s.service_category,
            s.sub_service,
            s.description as service_description,
            s.price as service_price,
            CONCAT(d.first_name, ' ', d.last_name) as dentist_name,
            d.specialization
          FROM payment p
          LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
          LEFT JOIN patient_information pi ON a.patient_id = pi.patient_id
          LEFT JOIN services s ON a.service_id = s.service_id
          LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
          WHERE p.payment_id = ? 
          LIMIT 1";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    die('Payment not found');
}

// Check if proof image exists (only required for PDF generation)
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'pdf';
if ($format !== 'image' && empty($payment['proof_image'])) {
    http_response_code(404);
    die('Proof image not found');
}

// If format=image, output raw image for modal display (only if proof_image exists)
if ($format === 'image') {
    if (empty($payment['proof_image'])) {
        http_response_code(404);
        die('Proof image not found');
    }
    
    // Normalize the image path - extract filename and resolve to absolute path
    $proof_image = trim($payment['proof_image']);
    $proof_image = str_replace('\\', '/', $proof_image); // Normalize backslashes (Windows)

    // Remove all relative path prefixes (../ or ./) recursively
    while (preg_match('#^\.\.?/#', $proof_image)) {
        $proof_image = preg_replace('#^\.\.?/#', '', $proof_image);
    }

    // Remove leading slashes
    $proof_image = ltrim($proof_image, '/');

    // Extract just the filename - get everything after the last slash or after 'uploads/'
    if (stripos($proof_image, 'uploads/') !== false) {
        $parts = explode('uploads/', $proof_image);
        $filename = trim(end($parts));
        $filename = ltrim($filename, '/');
    } else {
        $filename = basename($proof_image);
    }

    // Build absolute file path using __DIR__ (controllers folder)
    $uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $image_path = $uploadsDir . $filename;

    // Check if file exists
    if (!file_exists($image_path)) {
        // Fallback: try with just filename in uploads root
        $altPath = $uploadsDir . basename($filename);
        if (file_exists($altPath)) {
            $image_path = $altPath;
        } else {
            http_response_code(404);
            die('Proof image file not found');
        }
    }

    // Get image info
    $image_info = getimagesize($image_path);
    if ($image_info === false) {
        http_response_code(500);
        die('Invalid image file or unsupported format');
    }
    
    $mime = $image_info['mime'];
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($image_path));
    header('Cache-Control: private, max-age=3600');
    readfile($image_path);
    exit;
}

// For PDF format, normalize the image path
$proof_image = trim($payment['proof_image']);
$proof_image = str_replace('\\', '/', $proof_image); // Normalize backslashes (Windows)

// Remove all relative path prefixes (../ or ./) recursively
while (preg_match('#^\.\.?/#', $proof_image)) {
    $proof_image = preg_replace('#^\.\.?/#', '', $proof_image);
}

// Remove leading slashes
$proof_image = ltrim($proof_image, '/');

// Extract just the filename - get everything after the last slash or after 'uploads/'
if (stripos($proof_image, 'uploads/') !== false) {
    $parts = explode('uploads/', $proof_image);
    $filename = trim(end($parts));
    $filename = ltrim($filename, '/');
} else {
    $filename = basename($proof_image);
}

// Build absolute file path using __DIR__ (controllers folder)
$uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$image_path = $uploadsDir . $filename;

// Check if file exists
if (!file_exists($image_path)) {
    // Fallback: try with just filename in uploads root
    $altPath = $uploadsDir . basename($filename);
    if (file_exists($altPath)) {
        $image_path = $altPath;
    } else {
        http_response_code(404);
        die('Proof image file not found');
    }
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
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, 'Payment Receipt & Proof', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Landero Dental Clinic', 0, 1, 'C');
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
    
    function SectionTitle($title)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        $this->Ln(2);
    }
    
    function InfoRow($label, $value, $labelWidth = 60)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($labelWidth, 6, $label . ':', 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 6, $value, 0, 1, 'L');
    }
}

// Create PDF instance
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Appointment Details Section
$pdf->SectionTitle('Appointment Details');
$pdf->InfoRow('Appointment ID', htmlspecialchars($payment['appointment_id'] ?? 'N/A'));
$pdf->InfoRow('Patient Name', htmlspecialchars(trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')) ?: 'N/A'));
$pdf->InfoRow('Patient ID', htmlspecialchars($payment['patient_id'] ?? 'N/A'));
if (!empty($payment['email'])) {
    $pdf->InfoRow('Email', htmlspecialchars($payment['email']));
}
if (!empty($payment['phone'])) {
    $pdf->InfoRow('Phone', htmlspecialchars($payment['phone']));
}
if (!empty($payment['appointment_date'])) {
    $appointmentDate = date('F j, Y', strtotime($payment['appointment_date']));
    $appointmentTime = !empty($payment['appointment_time']) ? date('g:i A', strtotime($payment['appointment_time'])) : 'N/A';
    $pdf->InfoRow('Appointment Date', $appointmentDate . ' at ' . $appointmentTime);
}
if (!empty($payment['sub_service']) || !empty($payment['service_category'])) {
    $serviceName = !empty($payment['sub_service']) ? $payment['sub_service'] : ($payment['service_category'] ?? 'N/A');
    $pdf->InfoRow('Service', htmlspecialchars($serviceName));
}
if (!empty($payment['dentist_name'])) {
    $pdf->InfoRow('Dentist', htmlspecialchars($payment['dentist_name']));
    if (!empty($payment['specialization'])) {
        $pdf->InfoRow('Specialization', htmlspecialchars($payment['specialization']));
    }
}
if (!empty($payment['branch'])) {
    $pdf->InfoRow('Branch', htmlspecialchars($payment['branch']));
}
$pdf->InfoRow('Appointment Status', htmlspecialchars(ucfirst($payment['appointment_status'] ?? 'N/A')));

$pdf->Ln(5);

// Payment Details Section
$pdf->SectionTitle('Payment Details');
$pdf->InfoRow('Payment ID', htmlspecialchars($payment['payment_id']));
$pdf->InfoRow('Payment Method', htmlspecialchars(ucfirst($payment['method'] ?? 'N/A')));
if (!empty($payment['amount'])) {
    // Use PHP for peso sign (FPDF doesn't handle UTF-8 peso sign well)
    $pdf->InfoRow('Amount', 'PHP ' . number_format(floatval($payment['amount']), 2));
}
if (!empty($payment['account_name']) && strtolower($payment['method']) !== 'cash') {
    $pdf->InfoRow('Account Name', htmlspecialchars($payment['account_name']));
}
if (!empty($payment['account_number']) && strtolower($payment['method']) !== 'cash') {
    $pdf->InfoRow('Account Number', htmlspecialchars($payment['account_number']));
}
if (!empty($payment['reference_no']) && strtolower($payment['method']) !== 'cash') {
    $pdf->InfoRow('Reference Number', htmlspecialchars($payment['reference_no']));
}
$pdf->InfoRow('Payment Status', htmlspecialchars(ucfirst($payment['payment_status'] ?? 'N/A')));
if (!empty($payment['payment_date'])) {
    $pdf->InfoRow('Payment Date', date('F j, Y g:i A', strtotime($payment['payment_date'])));
}

$pdf->Ln(5);

// Add separator line
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// Proof Image Section
$pdf->SectionTitle('Payment Proof Image');

// Calculate image dimensions to fit on page - make it smaller to ensure it fits completely
$max_width = 150; // Reduced width to ensure image fits completely
$max_height = 120; // Reduced height to ensure image fits completely on page

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

// Check if image would go beyond page bottom, if so, add a new page
if ($y + $display_height > $pdf->GetPageHeight() - 20) {
    $pdf->AddPage();
    $y = $pdf->GetY();
}

// Add image to PDF (FPDF Image method accepts dimensions in mm)
// FPDF will automatically handle the image format based on file extension
$pdf->Image($image_path, $x, $y, $display_width, $display_height);

// Output PDF (I = inline, opens in browser)
$pdf->Output('I', 'Payment_Receipt_' . $payment['payment_id'] . '.pdf');

if (!empty($tmp_for_pdf) && file_exists($tmp_for_pdf)) {
    @unlink($tmp_for_pdf);
}
exit;
?>
