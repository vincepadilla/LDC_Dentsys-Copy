<?php
session_start();

require_once __DIR__ . "/../database/config.php";
require_once __DIR__ . "/../libraries/pdf/reportExport.php";

header("X-Content-Type-Options: nosniff");


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$raw = file_get_contents("php://input");
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "message" => "Invalid export payload."]);
    exit();
}

$sections = is_array($payload["sections"] ?? null) ? $payload["sections"] : [];
$reportTitle = (string)($payload["reportTitle"] ?? "Report Export");
$slug = (string)($payload["slug"] ?? "report_export");

// Basic empty-check: avoid generating an empty PDF.
$totalRows = 0;
foreach ($sections as $s) {
    if (!is_array($s)) continue;
    $rows = $s["rows"] ?? [];
    if (is_array($rows)) $totalRows += count($rows);
}

if ($totalRows <= 0) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "message" => "No data available to export."]);
    exit();
}

$clinicName = "Landero Dental Clinic";
$generatedAt = date("F j, Y");
$todayStamp = date("Y-m-d");
$filename = $slug . "_" . $todayStamp . ".pdf";

try {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Content-Type: application/pdf");
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $pdfBytes = generateReportExportPdfBytes($sections, $reportTitle, $clinicName, $generatedAt);
    header("Content-Length: " . strlen($pdfBytes));
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

