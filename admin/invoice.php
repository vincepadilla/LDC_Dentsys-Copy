<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

// Basic admin guard (same pattern as other admin pages)
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../views/login.php");
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    header("Location: ../views/admin_verify.php");
    exit();
}

// Get identifiers from query string
$appointmentId = isset($_GET['appointment_id']) ? trim($_GET['appointment_id']) : '';
$patientId     = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
$treatmentId   = isset($_GET['treatment_id']) ? trim($_GET['treatment_id']) : '';

if ($appointmentId === '' && $patientId === '' && $treatmentId === '') {
    die("Missing appointment_id, patient_id, or treatment_id.");
}

// Fetch appointment, patient, dentist, and payment info
$appointment = null;
$patient     = null;
$payment     = null;
$treatments  = [];

// Appointment & patient
if ($appointmentId !== '') {
    $stmt = $con->prepare("
        SELECT 
            a.appointment_id,
            a.patient_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.service_id,
            a.branch,
            a.team_id,
            s.sub_service AS service_name,
            d.first_name AS dentist_first,
            d.last_name  AS dentist_last
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
        WHERE a.appointment_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $appointmentId);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($appointment && empty($patientId)) {
        $patientId = $appointment['patient_id'];
    }
}

// Patient information
if ($patientId !== '') {
    $stmt = $con->prepare("
        SELECT 
            p.patient_id,
            p.first_name,
            p.last_name,
            p.email,
            p.address
        FROM patient_information p
        WHERE p.patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Payment for this appointment (if any)
if ($appointmentId !== '') {
    $stmt = $con->prepare("
        SELECT 
            p.payment_id,
            p.method,
            p.amount,
            p.status,
            p.created_at
        FROM payment p
        WHERE p.appointment_id = ?
        ORDER BY p.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $appointmentId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Treatments: prioritize exact treatment_id from All Transactions receipt flow.
if ($treatmentId !== '') {
    $treatmentsStmt = $con->prepare("
        SELECT
            th.treatment_id,
            th.patient_id,
            th.treatment,
            th.treatment_cost,
            th.prescription_given,
            th.notes,
            th.created_at
        FROM treatment_history th
        WHERE th.treatment_id = ?
        LIMIT 1
    ");
    if ($treatmentsStmt) {
        $treatmentsStmt->bind_param("s", $treatmentId);
        $treatmentsStmt->execute();
        $result = $treatmentsStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $treatments[] = $row;
        }
        $treatmentsStmt->close();
    }
    // Backfill patient_id using matched treatment if not provided.
    if ($patientId === '' && !empty($treatments[0]['patient_id'])) {
        $patientId = $treatments[0]['patient_id'];
    }
}

// If no exact treatment match, use the previous patient-based fallback logic.
if (empty($treatments) && $patientId !== '') {
    if ($appointment && !empty($appointment['appointment_date'])) {
        $treatmentsStmt = $con->prepare("
            SELECT 
                th.treatment_id,
                th.treatment,
                th.treatment_cost,
                th.prescription_given,
                th.notes,
                th.created_at
            FROM treatment_history th
            WHERE th.patient_id = ?
              AND DATE(th.created_at) BETWEEN DATE_SUB(?, INTERVAL 7 DAY) AND DATE_ADD(?, INTERVAL 30 DAY)
            ORDER BY th.created_at ASC
        ");
        $apptDate = $appointment['appointment_date'];
        $treatmentsStmt->bind_param("sss", $patientId, $apptDate, $apptDate);
    } else {
        $treatmentsStmt = $con->prepare("
            SELECT 
                th.treatment_id,
                th.treatment,
                th.treatment_cost,
                th.prescription_given,
                th.notes,
                th.created_at
            FROM treatment_history th
            WHERE th.patient_id = ?
            ORDER BY th.created_at DESC
            LIMIT 10
        ");
        $treatmentsStmt->bind_param("s", $patientId);
    }

    $treatmentsStmt->execute();
    $result = $treatmentsStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $treatments[] = $row;
    }
    $treatmentsStmt->close();
}

// Compute billing items
$items = [];
$appointmentFee = 0;
$treatmentTotal = 0;

if ($appointment) {
    // If there is a payment record, use that as appointment fee; otherwise 0
    $appointmentFee = $payment ? max(0, floatval($payment['amount'])) : 0;
    // Safely build appointment date/time text to avoid Unix epoch defaults
    $descDateTime = 'Not specified';
    if (!empty($appointment['appointment_date']) || !empty($appointment['appointment_time'])) {
        $rawDate = $appointment['appointment_date'] ?? '';
        $rawTime = $appointment['appointment_time'] ?? '';
        $dateTimeString = trim($rawDate . ' ' . $rawTime);
        $timestamp = strtotime($dateTimeString);
        if ($timestamp && $timestamp > 0) {
            $descDateTime = date('M j, Y g:i A', $timestamp);
        } elseif (!empty($rawDate)) {
            // Fallback: format using date only
            $dateOnlyTs = strtotime($rawDate);
            if ($dateOnlyTs && $dateOnlyTs > 0) {
                $descDateTime = date('M j, Y', $dateOnlyTs);
            }
        }
    }
    $items[] = [
        'label'       => 'Appointment Fee (Deducted) - ' . ($appointment['service_name'] ?? 'Consultation'),
        'description' => 'Appointment at ' . $descDateTime,
        'qty'         => 1,
        // Store as negative so the UI can render it as a deduction.
        'unit_price'  => -$appointmentFee,
        'amount'      => -$appointmentFee,
    ];
}

foreach ($treatments as $t) {
    $cost = floatval($t['treatment_cost'] ?? 0);
    $treatmentTotal += $cost;
    $items[] = [
        'label'       => $t['treatment'] ?: 'Treatment',
        'description' => 'Date: ' . date('M j, Y g:i A', strtotime($t['created_at'])),
        'qty'         => 1,
        'unit_price'  => $cost,
        'amount'      => $cost,
    ];
}

$subtotal      = $treatmentTotal;      // Treatment fee only
$discount      = $appointmentFee;     // Appointment fee is deducted from the treatment fee
$totalDue      = max($subtotal - $discount, 0);
$amountPaid    = $payment ? floatval($payment['amount']) : 0.00;

// Invoice metadata
$invoiceNumber = 'INV-' . str_pad($appointmentId ?: ($payment['payment_id'] ?? $patientId ?? '0'), 6, '0', STR_PAD_LEFT);
$invoiceDate   = date('M j, Y');

// Clinic info & logo
// Try to load from system_settings if available, otherwise fall back to sensible defaults
$clinicName    = "Landero Dental Clinic";
$clinicAddress = "Anahaw St. Comembo, Taguig City/Lot 2 Block 5, Turquoise Corner, Golden City Subd, Amber, Dolores, Taytay, 1920 Rizal";
$clinicPhone   = "0922 861 1987";
$clinicEmail   = "landerodentalclinic@gmail.com";
$logoPath      = "../assets/images/landerologo.png";
$signaturePath      = "../assets/signature/sham_signature.png";
// Safely attempt to fetch a stored logo path or clinic details from system_settings
try {
    if (isset($con) && $con instanceof mysqli) {
        // Generic helper to fetch a single setting_value by key
        $settingKeys = ['clinic_logo', 'logo_path', 'site_logo'];
        $foundLogo   = null;

        $settingsStmt = $con->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key IN (?, ?, ?)
        ");

        if ($settingsStmt) {
            $settingsStmt->bind_param(
                "sss",
                $settingKeys[0],
                $settingKeys[1],
                $settingKeys[2]
            );
            if ($settingsStmt->execute()) {
                $settingsRes = $settingsStmt->get_result();
                while ($row = $settingsRes->fetch_assoc()) {
                    if (!empty($row['setting_value'])) {
                        $foundLogo = $row['setting_value'];
                        break;
                    }
                }
            }
            $settingsStmt->close();
        }

        if (!empty($foundLogo)) {
            $logoPath = $foundLogo;
        }
    }
} catch (Throwable $e) {
    // If settings table or query fails, silently fall back to default logo path
}

// As a final guard, ensure the logo file is reachable; if not, fall back to older known location
if (!empty($logoPath) && !preg_match('/^https?:\/\//i', $logoPath)) {
    $fsPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . $logoPath;
    if (!is_file($fsPath)) {
        // Try legacy location used in older receipts
        if (is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . "../landerologo.png")) {
            $logoPath = "../landerologo.png";
        }
    }
}

// Pre-compute dentist display name once for reuse (appointment box + signature)
$dentistName = '';
if (!empty($appointment)) {
    $dentistFirst = $appointment['dentist_first'] ?? '';
    $dentistLast  = $appointment['dentist_last'] ?? '';
    $dentistName  = trim($dentistFirst . ' ' . $dentistLast);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($clinicName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-page: #f3f4f6;
            --bg-surface: #ffffff;
            --border-soft: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --accent: #2563eb;
            --accent-soft: #eff6ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
        }

        .invoice-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .invoice-container {
            width: 100%;
            max-width: 900px;
            background: var(--bg-surface);
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(15,23,42,0.12);
            padding: 32px 32px 40px;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            border-bottom: 1px solid var(--border-soft);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .clinic-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .clinic-logo {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: radial-gradient(circle at 30% 30%, #e0f2fe, #bfdbfe);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(37,99,235,0.25);
        }

        .clinic-logo img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .clinic-text h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .clinic-text p {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .invoice-meta {
            text-align: right;
            font-size: 13px;
        }

        .invoice-meta h2 {
            margin: 0 0 6px 0;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--accent);
        }

        .invoice-meta div {
            margin-top: 2px;
        }

        .section-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .info-grid {
            display: flex;
            gap: 18px 32px;
            margin-bottom: 20px;
            align-items: flex-start;
        }

        .info-grid > div {
            flex: 1 1 0;
            min-width: 0;
        }

        .info-box {
            padding: 12px 14px;
            border-radius: 10px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 13px;
            font-weight: 500;
        }

        .billing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 14px;
            font-size: 13px;
        }

        .billing-table thead th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 500;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            background: #f3f4f6;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
        }

        .billing-table tbody tr:nth-child(odd) {
            background: #f9fafb;
        }

        .billing-table tbody tr:nth-child(even) {
            background: #ffffff;
        }

        .billing-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .billing-table td.text-right {
            text-align: right;
        }

        .totals {
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 16px;
            align-items: flex-start;
        }

        .totals-summary {
            font-size: 12px;
            color: var(--text-muted);
        }

        .totals-box {
            border-radius: 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 10px 14px;
            font-size: 13px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .totals-row strong {
            font-weight: 600;
        }

        .totals-row.total {
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #d1d5db;
        }

        .totals-row.total span:last-child {
            color: #059669;
            font-weight: 700;
        }

        .note-section {
            margin-top: 20px;
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
            gap: 16px 24px;
        }

        .note-box {
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 13px;
        }

        .note-box p {
            margin: 4px 0;
        }

        .signature-block {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 30px;
            font-size: 13px;
            flex-wrap: nowrap;
        }

        .signature-area {
            flex: 1;
        }

        .signature-area:first-child {
            max-width: 60%;
        }

        .signature-line {
            height: 42px;
            border-bottom: 1px solid #9ca3af;
            margin-bottom: 4px;
        }

        .signature-label {
            font-size: 12px;
            color: #4b5563;
        }

        

        .invoice-actions {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            border-radius: 999px;
            border: 1px solid transparent;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            background: #111827;
            color: #f9fafb;
        }

        .btn-primary {
            background: #2563eb;
            border-color: #1d4ed8;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-outline {
            background: transparent;
            border-color: #d1d5db;
            color: #4b5563;
        }

        .btn-outline:hover {
            background: #f3f4f6;
        }

        @media (max-width: 768px) {
            .invoice-container {
                padding: 20px 16px 28px;
            }

            .invoice-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .invoice-meta {
                text-align: left;
            }

            .totals {
                grid-template-columns: 1fr;
            }

            .note-section {
                grid-template-columns: 1fr;
            }

            .signature-block {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media print {
            body {
                background: #ffffff;
            }
            .invoice-page {
                padding: 0;
            }
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                max-width: 100%;
            }
            .invoice-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-page">
        <div class="invoice-container">
            <div class="invoice-header">
                <div class="clinic-brand">
                    <div class="clinic-logo">
                        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="<?php echo htmlspecialchars($clinicName); ?> Logo">
                    </div>
                    <div class="clinic-text">
                        <h1><?php echo htmlspecialchars($clinicName); ?></h1>
                        <p><?php echo htmlspecialchars($clinicAddress); ?> · <?php echo htmlspecialchars($clinicPhone); ?></p>
                        <p><?php echo htmlspecialchars($clinicEmail); ?></p>
                    </div>
                </div>
                <div class="invoice-meta">
                    <h2>Invoice</h2>
                    <div><strong>No.:</strong> <?php echo htmlspecialchars($invoiceNumber); ?></div>
                    <div><strong>Date:</strong> <?php echo htmlspecialchars($invoiceDate); ?></div>
                    <?php if ($payment): ?>
                    <div><strong>Payment ID:</strong> <?php echo htmlspecialchars($payment['payment_id']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-grid">
                <div>
                    <div class="section-title">Patient</div>
                    <div class="info-box">
                        <div class="info-label">Patient Name</div>
                        <div class="info-value">
                            <?php
                            $name = $patient ? trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) : '';
                            echo htmlspecialchars($name ?: 'N/A');
                            ?>
                        </div>
                        <div class="info-label" style="margin-top:6px;">Patient ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['patient_id'] ?? $patientId ?? 'N/A'); ?></div>
                        <?php if (!empty($patient['email'])): ?>
                            <div class="info-label" style="margin-top:6px;">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($patient['email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="section-title">Appointment</div>
                    <div class="info-box">
                        <div class="info-label">Dentist</div>
                        <div class="info-value">
                            <?php
                            if ($appointment) {
                                echo htmlspecialchars($dentistName !== '' ? $dentistName : 'Not yet assigned');
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>

                        <div class="info-label" style="margin-top:6px;">Service</div>
                        <div class="info-value">
                            <?php
                            if ($appointment && !empty($appointment['service_name'])) {
                                echo htmlspecialchars($appointment['service_name']);
                            } elseif ($appointment) {
                                echo 'Consultation';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>

                        <div class="info-label" style="margin-top:6px;">Appointment Date</div>
                        <div class="info-value">
                            <?php
                            if ($appointment && !empty($appointment['appointment_date'])) {
                                $apptTime = $appointment['appointment_time'] ?? '00:00:00';
                                echo htmlspecialchars(date('M j, Y', strtotime($appointment['appointment_date'])));
                            } elseif ($appointment) {
                                echo 'Not specified';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>

                        <div class="info-label" style="margin-top:6px;">Appointment Time</div>
                        <div class="info-value">
                            <?php
                            if ($appointment && !empty($appointment['appointment_time'])) {
                                echo htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                            } elseif ($appointment) {
                                echo 'Not specified';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>

                        <div class="info-label" style="margin-top:6px;">Status</div>
                        <div class="info-value">
                            <?php
                            if ($appointment && isset($appointment['status']) && $appointment['status'] !== '') {
                                echo htmlspecialchars(ucfirst($appointment['status']));
                            } elseif ($appointment) {
                                echo 'Not specified';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>

                        <div class="info-label" style="margin-top:6px;">Branch / Clinic</div>
                        <div class="info-value">
                            <?php
                            if ($appointment && !empty($appointment['branch'])) {
                                echo htmlspecialchars($appointment['branch']);
                            } elseif ($appointment) {
                                echo 'Not specified';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-title">Billing Details</div>
            <table class="billing-table">
                <thead>
                    <tr>
                        <th style="width: 24%;">Item</th>
                        <th style="width: 34%;">Description</th>
                        <th style="width: 12%;">Qty</th>
                        <th style="width: 15%;" class="text-right">Unit Price</th>
                        <th style="width: 15%;" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                                $unitPrice = floatval($item['unit_price'] ?? 0);
                                $amount = floatval($item['amount'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['label']); ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo (int)$item['qty']; ?></td>
                                <td class="text-right">
                                    <?php if ($unitPrice < 0): ?>
                                        <span style="color: #ef4444;">-₱<?php echo number_format(abs($unitPrice), 2); ?></span>
                                    <?php else: ?>
                                        ₱<?php echo number_format($unitPrice, 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($amount < 0): ?>
                                        <span style="color: #ef4444;">-₱<?php echo number_format(abs($amount), 2); ?></span>
                                    <?php else: ?>
                                        ₱<?php echo number_format($amount, 2); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color: var(--text-muted); padding: 16px;">
                                No billing items found for this appointment.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="totals">
                <div class="totals-summary">
                    <?php if (!empty($treatments)): ?>
                        <div><strong>Treatments included:</strong></div>
                        <ul style="padding-left: 18px; margin: 4px 0 0 0;">
                            <?php foreach ($treatments as $t): ?>
                                <li>
                                    <?php echo htmlspecialchars($t['treatment'] ?? 'Treatment'); ?> ·
                                    ₱<?php echo number_format($t['treatment_cost'] ?? 0, 2); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div style="color: var(--text-muted);">No recorded treatments associated with this appointment in the current period.</div>
                    <?php endif; ?>
                </div>
                <div class="totals-box">
                    <div class="totals-row">
                        <span>Treatment Fee</span>
                        <span>₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="totals-row">
                        <span>Appointment Fee (Deducted)</span>
                        <span style="color: #ef4444;">-₱<?php echo number_format($discount, 2); ?></span>
                    </div>
                    <div class="totals-row total">
                        <span>Total Due (Remaining Balance)</span>
                        <span>₱<?php echo number_format($totalDue, 2); ?></span>
                    </div>
                    
                </div>
            </div>

            <div class="note-section">
                <div>
                    <div class="section-title">Prescription</div>
                    <div class="note-box">
                        <?php
                        $prescription = '';
                        foreach ($treatments as $t) {
                            if (!empty($t['prescription_given'])) {
                                $prescription = $t['prescription_given'];
                                break;
                            }
                        }
                        if ($prescription):
                        ?>
                            <p><?php echo nl2br(htmlspecialchars($prescription)); ?></p>
                        <?php else: ?>
                            <p style="color: var(--text-muted);">No prescription included.</p>
                        <?php endif; ?>
                    </div>

                    <div class="section-title" style="margin-top:14px;">Dentist Notes & Follow-up</div>
                    <div class="note-box">
                        <?php
                        $notes = '';
                        foreach ($treatments as $t) {
                            if (!empty($t['notes'])) {
                                $notes = $t['notes'];
                                break;
                            }
                        }
                        ?>
                        <?php if ($notes): ?>
                            <p><?php echo nl2br(htmlspecialchars($notes)); ?></p>
                        <?php else: ?>
                            <p style="color: var(--text-muted);">No additional notes recorded.</p>
                        <?php endif; ?>
                        <p style="margin-top:10px;"><strong>Follow-up Instructions:</strong><br>
                            <span style="color: var(--text-muted);">Please follow your dentist's recommendations. Schedule a follow-up visit if symptoms persist or as advised.</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="invoice-actions">
                <button class="btn btn-outline" onclick="window.history.back();">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button class="btn btn-primary" onclick="window.print();">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
            </div>
        </div>
    </div>
</body>
</html>

