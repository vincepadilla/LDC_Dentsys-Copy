<?php
header('Content-Type: application/json');

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/SMTP.php';
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$patientName = trim($_POST['patient_name'] ?? '');
$patientId = trim($_POST['patient_id'] ?? '');
$appointmentId = trim($_POST['appointment_id'] ?? '');
$treatmentName = trim($_POST['treatment_name'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');
$transactionDate = trim($_POST['transaction_date'] ?? '');
$appointmentFee = floatval($_POST['appointment_fee'] ?? 0);
$treatmentCost = floatval($_POST['treatment_cost'] ?? 0);
$totalAmount = floatval($_POST['total_amount'] ?? 0);

try {
    // Pull canonical values from DB (same source as invoice.php) when IDs are available.
    if ($appointmentId !== '') {
        $stmt = $con->prepare("
            SELECT 
                a.appointment_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                s.sub_service AS service_name,
                p.first_name,
                p.last_name
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.service_id
            LEFT JOIN patient_information p ON a.patient_id = p.patient_id
            WHERE a.appointment_id = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("s", $appointmentId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                if ($patientId === '' && !empty($row['patient_id'])) {
                    $patientId = (string)$row['patient_id'];
                }
                $dbName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if ($patientName === '' && $dbName !== '') {
                    $patientName = $dbName;
                }
                if ($treatmentName === '' && !empty($row['service_name'])) {
                    $treatmentName = (string)$row['service_name'];
                }
                if ($transactionDate === '' && !empty($row['appointment_date'])) {
                    $transactionDate = (string)$row['appointment_date'] . (!empty($row['appointment_time']) ? ' ' . (string)$row['appointment_time'] : '');
                }
            }
        }

        $payStmt = $con->prepare("
            SELECT method, amount, created_at
            FROM payment
            WHERE appointment_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        if ($payStmt) {
            $payStmt->bind_param("s", $appointmentId);
            $payStmt->execute();
            $pay = $payStmt->get_result()->fetch_assoc();
            $payStmt->close();
            if ($pay) {
                if ($paymentMethod === '' && !empty($pay['method'])) {
                    $paymentMethod = (string)$pay['method'];
                }
                // In this system this represents appointment fee paid upon booking.
                $appointmentFee = max(0, floatval($pay['amount'] ?? $appointmentFee));
                if ($transactionDate === '' && !empty($pay['created_at'])) {
                    $transactionDate = (string)$pay['created_at'];
                }
            }
        }
    }

    if ($patientId !== '') {
        $tStmt = $con->prepare("
            SELECT COALESCE(SUM(th.treatment_cost), 0) AS total_treatment_cost
            FROM treatment_history th
            WHERE th.patient_id = ?
        ");
        if ($tStmt) {
            $tStmt->bind_param("s", $patientId);
            $tStmt->execute();
            $tRow = $tStmt->get_result()->fetch_assoc();
            $tStmt->close();
            if ($tRow && isset($tRow['total_treatment_cost'])) {
                $treatmentCost = floatval($tRow['total_treatment_cost']);
            }
        }
    }
    $totalAmount = max($treatmentCost - $appointmentFee, 0);

    // Generate receipt PDF attachment.
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'LANDERO DENTAL CLINIC', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Billing Receipt', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Date Issued: ' . date('F j, Y g:i A'), 0, 1);
    if ($appointmentId !== '') {
        $pdf->Cell(0, 6, 'Appointment ID: ' . $appointmentId, 0, 1);
    }
    if ($patientId !== '') {
        $pdf->Cell(0, 6, 'Patient ID: ' . $patientId, 0, 1);
    }
    $pdf->Cell(0, 6, 'Patient Name: ' . ($patientName !== '' ? $patientName : 'N/A'), 0, 1);
    $pdf->Cell(0, 6, 'Treatment/Service: ' . ($treatmentName !== '' ? $treatmentName : 'N/A'), 0, 1);
    $pdf->Cell(0, 6, 'Payment Method: ' . ($paymentMethod !== '' ? $paymentMethod : 'N/A'), 0, 1);
    $pdf->Cell(0, 6, 'Transaction Date: ' . ($transactionDate !== '' ? $transactionDate : 'N/A'), 0, 1);
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(130, 8, 'Description', 1, 0, 'L');
    $pdf->Cell(60, 8, 'Amount (PHP)', 1, 1, 'R');

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(130, 8, 'Treatment Fee', 1, 0, 'L');
    $pdf->Cell(60, 8, number_format($treatmentCost, 2), 1, 1, 'R');

    $pdf->Cell(130, 8, 'Appointment Fee (Deducted)', 1, 0, 'L');
    $pdf->Cell(60, 8, number_format($appointmentFee, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(130, 9, 'Total Amount Due', 1, 0, 'L');
    $pdf->Cell(60, 9, number_format($totalAmount, 2), 1, 1, 'R');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, 'Thank you for trusting Landero Dental Clinic. Please keep this receipt for your records.');

    $pdfBytes = $pdf->Output('S');
    $safeRef = $appointmentId !== '' ? $appointmentId : ($patientId !== '' ? $patientId : date('YmdHis'));
    $filename = 'Billing_Receipt_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $safeRef) . '.pdf';

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mlanderodentalclinic@gmail.com';
    $mail->Password = 'xrfp cpvv ckdv jmht';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
    $mail->addAddress($email, $patientName !== '' ? $patientName : 'Patient');
    $mail->addStringAttachment($pdfBytes, $filename, 'base64', 'application/pdf');
    $mail->isHTML(true);
    $mail->Subject = 'Landero Dental Clinic - Billing Receipt';
    $mail->Body = '
        <p>Dear ' . htmlspecialchars($patientName !== '' ? $patientName : 'Patient', ENT_QUOTES, 'UTF-8') . ',</p>
        <p>Your billing receipt is attached as a PDF file.</p>
        <p>Please keep it for your records.</p>
        <p>Thank you,<br>Landero Dental Clinic</p>
    ';
    $mail->AltBody = "Dear " . ($patientName !== '' ? $patientName : 'Patient') . ",\n\nYour billing receipt is attached as a PDF file.\n\nThank you,\nLandero Dental Clinic";
    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Receipt sent successfully with PDF attachment.'
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send receipt: ' . $e->getMessage()
    ]);
}
exit;

