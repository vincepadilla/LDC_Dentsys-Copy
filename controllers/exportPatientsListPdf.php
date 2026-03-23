<?php
session_start();

require_once __DIR__ . "/../database/config.php";
require_once __DIR__ . "/../libraries/pdf/patientListReport.php";

header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Check if admin is logged in.
if (!isset($_SESSION["userID"]) || strtolower($_SESSION["role"] ?? "") !== "admin" || empty($_SESSION["admin_verified"])) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please login as admin."]);
    exit();
}

// Get all patients from the patients table (patient_information).
$patientSql = "SELECT patient_id, first_name, last_name, birthdate, gender, email, phone, address
               FROM patient_information
               ORDER BY patient_id ASC";

$patientResult = mysqli_query($con, $patientSql);
if ($patientResult === false) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch patient data from the database."
    ]);
    exit();
}

if (mysqli_num_rows($patientResult) === 0) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "message" => "No patient data found."]);
    exit();
}

$patients = [];
while ($row = mysqli_fetch_assoc($patientResult)) {
    // Format birthdate consistently for the PDF.
    $birthdateDisplay = $row["birthdate"] ?? "";
    if (!empty($birthdateDisplay)) {
        $dt = date_create($birthdateDisplay);
        if ($dt) {
            $birthdateDisplay = date_format($dt, "M j, Y");
        }
    }

    $patients[] = [
        "patient_id" => $row["patient_id"],
        "first_name" => $row["first_name"] ?? "",
        "last_name" => $row["last_name"] ?? "",
        "birthdate" => $birthdateDisplay,
        "gender" => $row["gender"] ?? "",
        "email" => $row["email"] ?? "",
        "phone" => $row["phone"] ?? "",
        "address" => $row["address"] ?? "",
    ];
}

$clinicName = "Landero Dental Clinic";
$generatedAt = date("F j, Y");
$filename = "patient_list_report.pdf";

try {
    $pdfBytes = generatePatientListReportPdfBytes($patients, $clinicName, $generatedAt);

    header("Content-Type: application/pdf");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("Content-Length: " . strlen($pdfBytes));
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    echo $pdfBytes;
    exit();
} catch (Throwable $e) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "success" => false,
        "message" => "Failed to generate PDF: " . $e->getMessage(),
    ]);
    exit();
}

