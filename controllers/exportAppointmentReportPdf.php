<?php
session_start();

require_once __DIR__ . "/../database/config.php";
require_once __DIR__ . "/../libraries/reports_period_scope.php";
require_once __DIR__ . "/../libraries/pdf/clinicReportExport.php";
require_once __DIR__ . "/../libraries/gemini_clinic_report_summary.php";

header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION["userID"]) || strtolower($_SESSION["role"] ?? "") !== "admin" || empty($_SESSION["admin_verified"])) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please login as admin."]);
    exit();
}

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

$jsonBadRequest = function (string $msg): void {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "message" => $msg]);
    exit();
};

$resolved = reportsTryResolveReportingPeriodStrict($payload);
if ($resolved["error"] !== null) {
    $jsonBadRequest($resolved["error"]);
}
$period = $resolved["period"];
$startStr = $period["startStr"];
$endStr = $period["endStr"];
$rangePresetLabel = $period["rangePresetLabel"];
$dateRangeLabel = $period["dateRangeLabel"];
$generatedAt = date("F j, Y") . " at " . date("g:i A");

// —— Summary metrics (scoped to selected period; matches PDF tables)
$totalAppointments = 0;
$q1 = "
    SELECT COUNT(*) AS c
    FROM appointments a
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
";
$st = mysqli_prepare($con, $q1);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    if ($row = mysqli_fetch_assoc($r)) {
        $totalAppointments = (int)$row["c"];
    }
    mysqli_stmt_close($st);
}

$totalDownPayment = 0.0;
$q2 = "
    SELECT IFNULL(SUM(p.amount), 0) AS total
    FROM payment p
    INNER JOIN appointments a ON p.appointment_id = a.appointment_id
    WHERE COALESCE(p.is_archived, 0) = 0
      AND LOWER(TRIM(p.status)) = 'paid'
      AND COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
";
$st = mysqli_prepare($con, $q2);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    if ($row = mysqli_fetch_assoc($r)) {
        $totalDownPayment = (float)$row["total"];
    }
    mysqli_stmt_close($st);
}

$totalRevenue = 0.0;
$q4 = "
    SELECT IFNULL(SUM(th.treatment_cost), 0) AS total
    FROM treatment_history th
    WHERE IFNULL(th.treatment_cost, 0) > 0
      AND DATE(th.created_at) BETWEEN ? AND ?
";
$st = mysqli_prepare($con, $q4);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    if ($row = mysqli_fetch_assoc($r)) {
        $totalRevenue = (float)$row["total"];
    }
    mysqli_stmt_close($st);
}

$metrics = [
    "appointments" => $totalAppointments,
    "down_payment" => $totalDownPayment,
    "revenue" => $totalRevenue,
];

// —— Total down payment by service (booked service on appointment)
$downPaymentByServiceRows = [];
$downPaymentByServiceForAi = [];
$qDp = "
    SELECT
        COALESCE(NULLIF(TRIM(s.sub_service), ''), s.service_category, 'N/A') AS service_name,
        IFNULL(SUM(p.amount), 0) AS total
    FROM payment p
    INNER JOIN appointments a ON p.appointment_id = a.appointment_id
    LEFT JOIN services s ON a.service_id = s.service_id
    WHERE COALESCE(p.is_archived, 0) = 0
      AND LOWER(TRIM(p.status)) = 'paid'
      AND COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY COALESCE(NULLIF(TRIM(s.sub_service), ''), s.service_category, 'N/A')
    HAVING IFNULL(SUM(p.amount), 0) > 0.00001
    ORDER BY total DESC
";
$st = mysqli_prepare($con, $qDp);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $name = (string)$row["service_name"];
        $amt = (float)$row["total"];
        $downPaymentByServiceForAi[] = ["service" => $name, "amount_php" => round($amt, 2)];
        $downPaymentByServiceRows[] = [
            $name,
            "PHP " . number_format($amt, 2, ".", ","),
        ];
    }
    mysqli_stmt_close($st);
}

// —— Services availed count (appointments per booked service)
$servicesAvailedRows = [];
$servicesAvailedForAi = [];
$qAv = "
    SELECT
        COALESCE(NULLIF(TRIM(s.sub_service), ''), s.service_category, 'N/A') AS service_name,
        COUNT(*) AS cnt
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.service_id
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY COALESCE(NULLIF(TRIM(s.sub_service), ''), s.service_category, 'N/A')
    ORDER BY cnt DESC, service_name ASC
";
$st = mysqli_prepare($con, $qAv);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $name = (string)$row["service_name"];
        $cnt = (int)$row["cnt"];
        $servicesAvailedForAi[] = ["service" => $name, "appointment_count" => $cnt];
        $servicesAvailedRows[] = [$name, (string)$cnt];
    }
    mysqli_stmt_close($st);
}

// —— Revenue by services (treatment_history, full period)
$revenueByServicesRows = [];
$revenueByServicesForAi = [];
$qRev = "
    SELECT
        th.treatment AS service_name,
        COUNT(*) AS treatment_count,
        SUM(IFNULL(th.treatment_cost, 0)) AS revenue
    FROM treatment_history th
    WHERE IFNULL(th.treatment_cost, 0) > 0
      AND th.treatment IS NOT NULL AND TRIM(th.treatment) <> ''
      AND DATE(th.created_at) BETWEEN ? AND ?
    GROUP BY th.treatment
    ORDER BY revenue DESC
";
$st = mysqli_prepare($con, $qRev);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $name = (string)$row["service_name"];
        $tc = (int)$row["treatment_count"];
        $rev = (float)$row["revenue"];
        $revenueByServicesForAi[] = [
            "service" => $name,
            "treatment_count" => $tc,
            "revenue_php" => round($rev, 2),
        ];
    }
    mysqli_stmt_close($st);
}

$revSvcTotal = 0.0;
foreach ($revenueByServicesForAi as $row) {
    $revSvcTotal += (float)$row["revenue_php"];
}
foreach ($revenueByServicesForAi as $row) {
    $rev = (float)$row["revenue_php"];
    $pct = $revSvcTotal > 0 ? ($rev / $revSvcTotal) * 100 : 0.0;
    $revenueByServicesRows[] = [
        $row["service"],
        (string)(int)$row["treatment_count"],
        "PHP " . number_format($rev, 2, ".", ","),
        number_format($pct, 1) . "%",
    ];
}

// —— Monthly service distribution: month + service + count
$monthlyServiceDetailRows = [];
$monthlyServiceDetailForAi = [];
$qMs = "
    SELECT
        YEAR(a.appointment_date) AS y,
        MONTH(a.appointment_date) AS m,
        COALESCE(NULLIF(TRIM(s.sub_service), ''), s.service_category, 'N/A') AS service_name,
        COUNT(*) AS cnt
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.service_id
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY YEAR(a.appointment_date), MONTH(a.appointment_date),
        COALESCE(NULLIF(TRIM(s.sub_service), ''), s.service_category, 'N/A')
    ORDER BY y ASC, m ASC, cnt DESC, service_name ASC
";
$st = mysqli_prepare($con, $qMs);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $y = (int)$row["y"];
        $m = (int)$row["m"];
        $label = date("M Y", mktime(0, 0, 0, $m, 1, $y));
        $svc = (string)$row["service_name"];
        $cnt = (int)$row["cnt"];
        $monthlyServiceDetailForAi[] = [
            "month" => $label,
            "service" => $svc,
            "booking_count" => $cnt,
        ];
        $monthlyServiceDetailRows[] = [$label, $svc, (string)$cnt];
    }
    mysqli_stmt_close($st);
}

$monthlyBookingTotalsRows = clinicReportMonthlyTotalsFromDetail($monthlyServiceDetailRows);

// —— Monthly revenue by service (treatment_history)
$monthlyRevenueRows = [];
$monthlyRevenueForAi = [];
$q6 = "
    SELECT
        YEAR(th.created_at) AS y,
        MONTH(th.created_at) AS m,
        th.treatment AS service_name,
        SUM(IFNULL(th.treatment_cost, 0)) AS revenue
    FROM treatment_history th
    WHERE IFNULL(th.treatment_cost, 0) > 0
      AND th.treatment IS NOT NULL AND TRIM(th.treatment) <> ''
      AND DATE(th.created_at) BETWEEN ? AND ?
    GROUP BY YEAR(th.created_at), MONTH(th.created_at), th.treatment
    ORDER BY y ASC, m ASC, revenue DESC, th.treatment ASC
";
$st = mysqli_prepare($con, $q6);
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $y = (int)$row["y"];
        $m = (int)$row["m"];
        $label = date("M Y", mktime(0, 0, 0, $m, 1, $y));
        $rev = (float)$row["revenue"];
        $svcName = (string)$row["service_name"];
        $monthlyRevenueForAi[] = [
            "month" => $label,
            "service" => $svcName,
            "revenue_php" => round($rev, 2),
        ];
        $monthlyRevenueRows[] = [
            $label,
            $svcName,
            "PHP " . number_format($rev, 2, ".", ","),
        ];
    }
    mysqli_stmt_close($st);
}

$hasAnyActivity =
    $totalAppointments > 0
    || $totalDownPayment > 0.00001
    || $totalRevenue > 0.00001
    || count($monthlyServiceDetailRows) > 0
    || count($monthlyRevenueRows) > 0
    || count($downPaymentByServiceRows) > 0
    || count($servicesAvailedRows) > 0
    || count($revenueByServicesRows) > 0;

$noDataReportSummary =
    "No report data is available for the selected period. All metrics recorded zero activity, indicating no appointments or revenue transactions during this timeframe.";

if (!$hasAnyActivity) {
    $downPaymentByServiceRows = [["No activity in range", "PHP 0.00"]];
    $servicesAvailedRows = [["No activity in range", "0"]];
    $revenueByServicesRows = [["No activity in range", "0", "PHP 0.00", "0.0%"]];
    $monthlyServiceDetailRows = [["—", "—", "0"]];
    $monthlyRevenueRows = [["—", "—", "PHP 0.00"]];
    $downPaymentByServiceForAi = [];
    $servicesAvailedForAi = [];
    $revenueByServicesForAi = [];
    $monthlyServiceDetailForAi = [];
    $monthlyRevenueForAi = [];
    $monthlyBookingTotalsRows = [];
    $reportSummary = $noDataReportSummary;
} else {
    $geminiPayload = [
        "reporting" => [
            "preset" => $rangePresetLabel,
            "period_label" => $dateRangeLabel,
            "date_start" => $startStr,
            "date_end" => $endStr,
            "headline_totals_note" => "Headline totals (appointments, down payments, revenue from services) are computed only from records whose dates fall within the selected period.",
        ],
        "metrics" => [
            "total_appointments" => $totalAppointments,
            "total_down_payment_php" => round($totalDownPayment, 2),
            "total_revenue_services_php" => round($totalRevenue, 2),
        ],
        "down_payment_by_service" => $downPaymentByServiceForAi,
        "services_availed" => $servicesAvailedForAi,
        "revenue_by_services" => $revenueByServicesForAi,
        "monthly_booking_totals_by_month" => array_map(function ($r) {
            return ["month" => (string)$r[0], "total_bookings" => (int)$r[1]];
        }, $monthlyBookingTotalsRows),
        "monthly_service_distribution_detail" => $monthlyServiceDetailForAi,
        "monthly_revenue_by_service" => $monthlyRevenueForAi,
        "derived_insights" => clinicReportBuildDerivedInsights($monthlyBookingTotalsRows, $monthlyRevenueForAi),
    ];

    $reportSummary = geminiGenerateClinicReportSummary($geminiPayload);
    if ($reportSummary === null || $reportSummary === "") {
        $d = $geminiPayload["derived_insights"];
        $peakB = $d["booking_peak"];
        $lowB = $d["booking_low"];
        $topS = $d["top_service_by_revenue"];
        $peakRm = $d["peak_revenue_month"];

        $parts = [];
        $parts[] = "For the selected window ({$rangePresetLabel}, {$dateRangeLabel}), there were {$totalAppointments} appointment(s), down payments totaled PHP " . number_format($totalDownPayment, 2, ".", ",") . ", and revenue from services was PHP " . number_format($totalRevenue, 2, ".", ",") . ".";
        if (is_array($peakB) && is_array($lowB) && ($peakB["month"] ?? "") !== ($lowB["month"] ?? "")) {
            $parts[] = " Booking volume was highest in " . $peakB["month"] . " (" . (int)$peakB["count"] . ") and lowest in " . $lowB["month"] . " (" . (int)$lowB["count"] . ").";
        } elseif (is_array($peakB)) {
            $parts[] = " Peak booking activity in a single month occurred in " . $peakB["month"] . " (" . (int)$peakB["count"] . " bookings).";
        }
        if (is_array($topS)) {
            $parts[] = " Leading treatment revenue was " . $topS["service"] . " (PHP " . number_format((float)$topS["total_php"], 2, ".", ",") . " total).";
        }
        if (is_array($peakRm)) {
            $parts[] = " The strongest revenue month was " . $peakRm["month"] . " (PHP " . number_format((float)$peakRm["total_php"], 2, ".", ",") . " combined across services).";
        }
        $reportSummary = implode("", $parts);
    }
}

$clinicName = "Landero Dental Clinic";
$todayStamp = date("Y-m-d");
$filename = "clinic_report_" . $todayStamp . ".pdf";

try {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Content-Type: application/pdf");
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $pdfBytes = generateClinicReportPdfBytes(
        $clinicName,
        $dateRangeLabel,
        $generatedAt,
        $metrics,
        $reportSummary,
        $downPaymentByServiceRows,
        $servicesAvailedRows,
        $revenueByServicesRows,
        $monthlyServiceDetailRows,
        $monthlyRevenueRows
    );
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
