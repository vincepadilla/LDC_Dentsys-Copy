<?php
session_start();
require_once(__DIR__ . "/../database/config.php");
require_once(__DIR__ . "/../libraries/reports_period_scope.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    header("Location: admin_verify.php");
    exit();
}

// Same period rules as controllers/exportAppointmentReportPdf.php (soft fallback on invalid GET)
$periodInput = [
    "range" => isset($_GET["range"]) ? (string)$_GET["range"] : "1y",
    "date_from" => isset($_GET["date_from"]) ? (string)$_GET["date_from"] : "",
    "date_to" => isset($_GET["date_to"]) ? (string)$_GET["date_to"] : "",
];
$period = reportsResolveReportingPeriod($periodInput);
$startStr = $period["startStr"];
$endStr = $period["endStr"];
$dateRangeLabel = $period["dateRangeLabel"];

// —— Total Appointments (period)
$totalAppointments = 0;
$st = mysqli_prepare($con, "
    SELECT COUNT(*) AS total
    FROM appointments a
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    if ($row = mysqli_fetch_assoc($r)) {
        $totalAppointments = (int)$row["total"];
    }
    mysqli_stmt_close($st);
}

$currentMonthLabel = date("F Y");

// —— Total Down Payment (period; tied to appointment date, same as PDF)
$totaldownPayment = 0.0;
$st = mysqli_prepare($con, "
    SELECT IFNULL(SUM(p.amount), 0) AS total
    FROM payment p
    INNER JOIN appointments a ON p.appointment_id = a.appointment_id
    WHERE COALESCE(p.is_archived, 0) = 0
      AND LOWER(TRIM(p.status)) = 'paid'
      AND COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    if ($row = mysqli_fetch_assoc($r)) {
        $totaldownPayment = (float)$row["total"];
    }
    mysqli_stmt_close($st);
}

// —— Today's Appointments (only if today falls inside the selected period)
$todayAppointments = 0;
$todayYmd = date("Y-m-d");
if ($todayYmd >= $startStr && $todayYmd <= $endStr) {
    $st = mysqli_prepare($con, "
        SELECT COUNT(*) AS total
        FROM appointments a
        WHERE COALESCE(a.is_archived, 0) = 0
          AND DATE(a.appointment_date) = CURDATE()
          AND a.appointment_date BETWEEN ? AND ?
    ");
    if ($st) {
        mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
        if ($row = mysqli_fetch_assoc($r)) {
            $todayAppointments = (int)$row["total"];
        }
        mysqli_stmt_close($st);
    }
}

// —— Appointment Status Breakdown (period)
$appointmentStatuses = [];
$st = mysqli_prepare($con, "
    SELECT a.status, COUNT(*) AS cnt
    FROM appointments a
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY a.status
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $appointmentStatuses[$row["status"]] = (int)$row["cnt"];
    }
    mysqli_stmt_close($st);
}

// —— Total Downpayment by Services (period)
$serviceRevenueLabels = [];
$serviceRevenueAmounts = [];
$st = mysqli_prepare($con, "
    SELECT s.service_category, SUM(p.amount) AS total_amount
    FROM payment p
    INNER JOIN appointments a ON p.appointment_id = a.appointment_id
    INNER JOIN services s ON a.service_id = s.service_id
    WHERE COALESCE(p.is_archived, 0) = 0
      AND LOWER(TRIM(p.status)) = 'paid'
      AND COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY s.service_category
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $serviceRevenueLabels[] = $row["service_category"];
        $serviceRevenueAmounts[] = (float)$row["total_amount"];
    }
    mysqli_stmt_close($st);
}

// —— Services Availed Count (period)
$servicesAvailedLabels = [];
$servicesAvailedCounts = [];
$st = mysqli_prepare($con, "
    SELECT s.sub_service, COUNT(*) AS count
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.service_id
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY s.sub_service
    ORDER BY count DESC
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $servicesAvailedLabels[] = $row["sub_service"];
        $servicesAvailedCounts[] = (int)$row["count"];
    }
    mysqli_stmt_close($st);
}

// —— Monthly Service Distribution (each calendar month overlapping the period)
$reportMonths = reportsMonthsInRange($startStr, $endStr);
$monthlyServiceData = [];
foreach ($reportMonths as $rm) {
    $monthlyServiceData[$rm["key"]] = [
        "labels" => [],
        "counts" => [],
        "total" => 0,
        "chartLabel" => $rm["label"],
    ];
}
$st = mysqli_prepare($con, "
    SELECT
        YEAR(a.appointment_date) AS y,
        MONTH(a.appointment_date) AS m,
        COALESCE(s.service_category, 'N/A') AS service_category,
        COUNT(*) AS cnt
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.service_id
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY YEAR(a.appointment_date), MONTH(a.appointment_date), COALESCE(s.service_category, 'N/A')
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $key = sprintf("%04d-%02d", (int)$row["y"], (int)$row["m"]);
        if (!isset($monthlyServiceData[$key])) {
            continue;
        }
        $monthlyServiceData[$key]["labels"][] = $row["service_category"];
        $monthlyServiceData[$key]["counts"][] = (int)$row["cnt"];
    }
    mysqli_stmt_close($st);
}
foreach ($monthlyServiceData as $k => $bucket) {
    $monthlyServiceData[$k]["total"] = array_sum($bucket["counts"]);
}

// —— Appointments Per Day (every day in selected period)
$dates = [];
$rawDates = [];
$counts = [];
$st = mysqli_prepare($con, "
    SELECT appointment_date, COUNT(*) AS count
    FROM appointments a
    WHERE COALESCE(a.is_archived, 0) = 0
      AND a.appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date
    ORDER BY appointment_date
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $dates[] = date("M j", strtotime($row["appointment_date"]));
        $rawDates[] = date("Y-m-d", strtotime($row["appointment_date"]));
        $counts[] = (int)$row["count"];
    }
    mysqli_stmt_close($st);
}

// —— Revenue by Services (period; total = sum of rows, matches PDF headline revenue)
$serviceNames = [];
$serviceRevenues = [];
$treatmentCounts = [];
$st = mysqli_prepare($con, "
    SELECT
        th.treatment,
        SUM(th.treatment_cost) AS total_revenue,
        COUNT(*) AS treatment_count
    FROM treatment_history th
    WHERE th.treatment_cost > 0
      AND th.treatment IS NOT NULL AND th.treatment != ''
      AND DATE(th.created_at) BETWEEN ? AND ?
    GROUP BY th.treatment
    ORDER BY total_revenue DESC
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $serviceNames[] = $row["treatment"];
        $tr = (float)$row["total_revenue"];
        $serviceRevenues[] = $tr;
        $treatmentCounts[] = (int)$row["treatment_count"];
    }
    mysqli_stmt_close($st);
}
$totalRevenue = array_sum($serviceRevenues);

// —— Monthly Revenue by Services (variable month columns within period)
$nReportMonths = count($reportMonths);
$monthlyRevenueByServicesData = []; // [monthKey][service_name] => ['revenue'=>,'count'=>]
$monthlyRevenueServiceTotals = [];

$st = mysqli_prepare($con, "
    SELECT
        YEAR(th.created_at) AS year,
        MONTH(th.created_at) AS month,
        th.treatment AS service_name,
        SUM(th.treatment_cost) AS total_revenue,
        COUNT(*) AS treatment_count
    FROM treatment_history th
    WHERE th.treatment_cost > 0
      AND th.treatment IS NOT NULL AND th.treatment != ''
      AND DATE(th.created_at) BETWEEN ? AND ?
    GROUP BY YEAR(th.created_at), MONTH(th.created_at), th.treatment
    ORDER BY YEAR(th.created_at) ASC, MONTH(th.created_at) ASC, total_revenue DESC
");
if ($st) {
    mysqli_stmt_bind_param($st, "ss", $startStr, $endStr);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($r)) {
        $mk = sprintf("%04d-%02d", (int)$row["year"], (int)$row["month"]);
        $serviceName = $row["service_name"];
        $revenue = (float)$row["total_revenue"];
        $count = (int)$row["treatment_count"];
        if (!isset($monthlyRevenueByServicesData[$mk])) {
            $monthlyRevenueByServicesData[$mk] = [];
        }
        $monthlyRevenueByServicesData[$mk][$serviceName] = [
            "revenue" => $revenue,
            "count" => $count,
        ];
        $monthlyRevenueServiceTotals[$serviceName] = ($monthlyRevenueServiceTotals[$serviceName] ?? 0) + $revenue;
    }
    mysqli_stmt_close($st);
}

$monthlyRevenueServiceNames = array_keys($monthlyRevenueServiceTotals);
arsort($monthlyRevenueServiceTotals);
$monthlyRevenueServiceNames = array_keys($monthlyRevenueServiceTotals);

$monthlyRevenueMonthLabels = [];
foreach ($reportMonths as $rm) {
    $monthlyRevenueMonthLabels[] = $rm["label"];
}

$monthlyRevenueTotalsByMonth = array_fill(0, max(1, $nReportMonths), 0.0);
$monthlyRevenueRevenuesMatrix = [];
$monthlyRevenueCountsMatrix = [];

foreach ($monthlyRevenueServiceNames as $serviceName) {
    $revenues = array_fill(0, max(1, $nReportMonths), 0.0);
    $countsRow = array_fill(0, max(1, $nReportMonths), 0);
    foreach ($reportMonths as $mi => $rm) {
        $mk = $rm["key"];
        $cell = $monthlyRevenueByServicesData[$mk][$serviceName] ?? null;
        if ($cell) {
            $revenues[$mi] = (float)$cell["revenue"];
            $countsRow[$mi] = (int)$cell["count"];
            $monthlyRevenueTotalsByMonth[$mi] += (float)$cell["revenue"];
        }
    }
    $monthlyRevenueRevenuesMatrix[] = $revenues;
    $monthlyRevenueCountsMatrix[] = $countsRow;
}

$hasMonthlyRevenueData = $nReportMonths > 0 && array_sum($monthlyRevenueTotalsByMonth) > 0;

// Default month column: last month in range with any revenue, else last index
$defaultMonthIdx = max(0, $nReportMonths - 1);
for ($mi = $nReportMonths - 1; $mi >= 0; $mi--) {
    if (($monthlyRevenueTotalsByMonth[$mi] ?? 0) > 0) {
        $defaultMonthIdx = $mi;
        break;
    }
}

$monthlyRevenueCurrentMonthTotal = $monthlyRevenueTotalsByMonth[$defaultMonthIdx] ?? 0.0;
$monthlyRevenueCurrentMonthDetails = [];
if ($nReportMonths > 0 && isset($reportMonths[$defaultMonthIdx])) {
    $defaultMk = $reportMonths[$defaultMonthIdx]["key"];
    foreach ($monthlyRevenueServiceNames as $serviceName) {
        $cell = $monthlyRevenueByServicesData[$defaultMk][$serviceName] ?? null;
        if ($cell && (float)$cell["revenue"] > 0) {
            $monthlyRevenueCurrentMonthDetails[] = [
                "service_name" => $serviceName,
                "revenue" => (float)$cell["revenue"],
                "count" => (int)$cell["count"],
            ];
        }
    }
}
usort($monthlyRevenueCurrentMonthDetails, function ($a, $b) {
    return $b["revenue"] <=> $a["revenue"];
});

$defaultMonthKey = $nReportMonths > 0 ? $reportMonths[$defaultMonthIdx]["key"] : "";
$monthKeyToIndex = [];
foreach ($reportMonths as $mi => $rm) {
    $monthKeyToIndex[$rm["key"]] = $mi;
}

$defaultServiceMonthKey = $defaultMonthKey;
if ($nReportMonths > 0) {
    $defaultServiceMonthKey = $reportMonths[0]["key"];
    for ($i = $nReportMonths - 1; $i >= 0; $i--) {
        $k = $reportMonths[$i]["key"];
        if (($monthlyServiceData[$k]["total"] ?? 0) > 0) {
            $defaultServiceMonthKey = $k;
            break;
        }
    }
}

// Chart title year for monthly service chart (first month in range)
$currentYear = $nReportMonths > 0 ? (string)$reportMonths[0]["y"] : date("Y");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="reportsDesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Notification Container -->
    <div class="notification-container" id="notificationContainer"></div>

    <!-- Export PDF: date range modal -->
    <div id="pdfExportModal" class="pdf-export-modal" aria-hidden="true">
        <div class="pdf-export-modal__backdrop" data-close-pdf-modal></div>
        <div class="pdf-export-modal__panel" role="dialog" aria-modal="true" aria-labelledby="pdfExportModalTitle">
            <div class="pdf-export-modal__header">
                <h3 id="pdfExportModalTitle">Export clinic report</h3>
                <button type="button" class="pdf-export-modal__close" data-close-pdf-modal aria-label="Close">&times;</button>
            </div>
            <p class="pdf-export-modal__intro">Choose a preset period or a custom date range. All PDF figures use the selected dates (end date inclusive).</p>
            <div class="pdf-export-modal__options">
                <label class="pdf-export-modal__option">
                    <input type="radio" name="pdfExportRange" value="cy" <?php echo $period['range'] === 'cy' ? 'checked' : ''; ?>>
                    <span class="pdf-export-modal__option-body">
                        <span class="pdf-export-modal__option-title">This year</span>
                        <span class="pdf-export-modal__option-hint">Jan 1 through today (<?php echo (int)date('Y'); ?>)</span>
                    </span>
                </label>
                <label class="pdf-export-modal__option">
                    <input type="radio" name="pdfExportRange" value="1y" <?php echo $period['range'] === '1y' ? 'checked' : ''; ?>>
                    <span class="pdf-export-modal__option-body">
                        <span class="pdf-export-modal__option-title">Past 1 year</span>
                    </span>
                </label>
                <label class="pdf-export-modal__option">
                    <input type="radio" name="pdfExportRange" value="6m" <?php echo $period['range'] === '6m' ? 'checked' : ''; ?>>
                    <span class="pdf-export-modal__option-body">
                        <span class="pdf-export-modal__option-title">Last 6 months</span>
                    </span>
                </label>
                <label class="pdf-export-modal__option">
                    <input type="radio" name="pdfExportRange" value="custom" id="pdfExportRangeCustom" <?php echo $period['range'] === 'custom' ? 'checked' : ''; ?>>
                    <span class="pdf-export-modal__option-body">
                        <span class="pdf-export-modal__option-title">Custom date range</span>
                        <span class="pdf-export-modal__option-hint">Choose start and end dates</span>
                    </span>
                </label>
            </div>
            <div id="pdfExportCustomWrap" class="pdf-export-modal__custom-wrap" <?php echo $period['range'] !== 'custom' ? 'hidden' : ''; ?>>
                <div class="pdf-export-modal__custom-fields">
                    <label class="pdf-export-modal__date-label">From
                        <input type="date" id="pdfExportDateFrom" class="pdf-export-modal__date-input" max="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo $period['range'] === 'custom' ? htmlspecialchars($startStr, ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </label>
                    <label class="pdf-export-modal__date-label">To
                        <input type="date" id="pdfExportDateTo" class="pdf-export-modal__date-input" max="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo $period['range'] === 'custom' ? htmlspecialchars($endStr, ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </label>
                </div>
                <p class="pdf-export-modal__custom-hint">Maximum span: 5 years. End date cannot be after today.</p>
            </div>
            <div class="pdf-export-modal__actions">
                <button type="button" class="pdf-export-modal__btn pdf-export-modal__btn--secondary" data-close-pdf-modal>Cancel</button>
                <button type="button" class="pdf-export-modal__btn pdf-export-modal__btn--primary" id="pdfExportConfirmBtn">
                    <i class="fas fa-check"></i> Export PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Reports Section -->
    <div class="main-content">
        <div class="container">
            <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
            <div class="reports-container">
            <h2 class="report-header">
                <i class="fa-solid fa-square-poll-vertical"></i> REPORTS & ANALYTICS
            </h2>

            <!-- Report Selector -->
            <div class="report-selector">
                <label for="reportType">Filter Reports:</label>
                <select id="reportType" onchange="filterReports()">
                    <option value="all" selected>Show All Reports</option>
                    <option value="service">Monthly Service Distribution</option>
                    <option value="appointments">Appointments Per Day</option>
                    <option value="monthlyRevenue">Monthly Revenue by Services</option>
                    <option value="financial">Revenue by Services</option>
                </select>
                
                <button type="button" id="exportPdfBtn" class="export-csv-btn export-pdf-btn" title="Export active report to PDF">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>

            <!-- Dashboard Overview -->
            <div id="overviewReport" class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-chart-pie"></i> Dashboard Overview</h3>
                </div>

                <!-- Stats Cards Row -->
                <div class="stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-card__main">
                            <div class="stat-label">Total Appointments</div>
                            <div class="stat-value"><?php echo $totalAppointments; ?></div>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-card__main">
                            <div class="stat-label">Total Down Payment</div>
                            <div class="stat-value">₱<?php echo number_format($totaldownPayment, 2); ?></div>
                        </div>
                        <div class="report-stat-card__footer report-stat-card__footer--spacer" aria-hidden="true"></div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-card__main">
                            <div class="stat-label">Today's Appointments</div>
                            <div class="stat-value"><?php echo $todayAppointments; ?></div>
                        </div>
                        <div class="report-stat-card__footer report-stat-card__footer--spacer" aria-hidden="true"></div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-card__main">
                            <div class="stat-label">Total Revenue By Services</div>
                            <div class="stat-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
                        </div>
                        <div class="report-stat-card__footer report-stat-card__footer--spacer" aria-hidden="true"></div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="charts-row">
                    <!-- Appointment Status Chart -->
                    <div class="chart-box">
                        <h3>Appointment Status</h3>
                        <canvas id="appointmentStatusChart"></canvas>
                    </div>

                    <!-- Total Downpayment by Services -->
                    <div class="chart-box">
                        <h3>Total Downpayment by Services</h3>
                        <canvas id="serviceRevenueChart"></canvas>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="charts-row">
                    <!-- Appointment Summary -->
                    <div class="chart-box">
                        <h3>Appointment Summary</h3>
                        <div class="status-summary">
                            <?php
                            $statusColors = [
                                'pending' => '#F59E0B',
                                'confirmed' => '#10B981', 
                                'rescheduled' => '#3B82F6',
                                'cancelled' => '#EF4444',
                                'no-show' => '#6B7280'
                            ];
                            
                            foreach ($appointmentStatuses as $status => $count) {
                                $color = $statusColors[strtolower($status)] ?? '#6B7280';
                                $percentage = $totalAppointments > 0 ? round(($count / $totalAppointments) * 100, 1) : 0;
                                echo "
                                <div class='status-item'>
                                    <div class='status-info'>
                                        <div class='status-dot' style='background: $color'></div>
                                        <span class='status-name'>" . ucfirst($status) . "</span>
                                    </div>
                                    <div class='status-numbers'>
                                        <span class='status-count'>$count</span>
                                        <span class='status-percentage'>($percentage%)</span>
                                    </div>
                                </div>
                                ";
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Services Availed Count -->
                    <div class="chart-box">
                        <h3>Services Availed Count</h3>
                        <canvas id="servicesAvailedChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Service Distribution -->
            <div id="serviceReport" class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-chart-bar"></i> Monthly Service Distribution</h3>
                </div>

                <div class="chart-box">
                    <div class="chart-controls">
                        <label for="monthSelect">Select Month:</label>
                        <select id="monthSelect" onchange="updateChart()">
                            <?php
                            foreach ($reportMonths as $rm) {
                                $sel = ($rm['key'] === $defaultServiceMonthKey) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($rm['key'], ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>' . htmlspecialchars($rm['label'], ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <canvas id="servicePieChart"></canvas>
                    <div id="colorGuide" class="color-guide"></div>
                </div>
            </div>

            <!-- Appointments Per Day -->
            <div id="appointmentsReport" class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-calendar-alt"></i> Appointments Per Day</h3>
                </div>
                <div class="chart-box">
                    <canvas id="appointmentsBarChart"></canvas>
                </div>
            </div>

            <!-- Monthly Revenue by Services Report -->
            <div id="monthlyRevenueReport" class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Monthly Revenue by Services</h3>
                </div>

                <?php if ($hasMonthlyRevenueData): ?>
                    <div class="revenue-content">
                        <div class="chart-container">
                            <div class="chart-box">
                                <div id="monthlyRevenueChartLoader" class="chart-loading-overlay">
                                    <div class="chart-spinner" aria-label="Loading"></div>
                                    <span>Loading chart...</span>
                                </div>

                                <div class="chart-controls">
                                    <label for="monthlyRevenueMonthSelect">Select Month:</label>
                                    <select id="monthlyRevenueMonthSelect" onchange="updateMonthlyRevenueDetails()">
                                        <?php
                                        foreach ($reportMonths as $rm) {
                                            $sel = ($rm['key'] === $defaultMonthKey) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($rm['key'], ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>' . htmlspecialchars($rm['label'], ENT_QUOTES, 'UTF-8') . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <canvas id="monthlyRevenueByServicesChart"></canvas>
                            </div>
                        </div>

                        <div class="service-details">
                            <h4>Service Revenue Details</h4>
                            <div class="service-list" id="monthlyRevenueServiceDetailsList">
                                <?php if (!empty($monthlyRevenueCurrentMonthDetails)): ?>
                                    <?php foreach ($monthlyRevenueCurrentMonthDetails as $detail): ?>
                                        <?php
                                        $pct = $monthlyRevenueCurrentMonthTotal > 0 ? round(($detail['revenue'] / $monthlyRevenueCurrentMonthTotal) * 100, 1) : 0;
                                        ?>
                                        <div class="service-item">
                                            <div class="service-info">
                                                <div class="service-name"><?php echo htmlspecialchars($detail['service_name']); ?></div>
                                                <div class="service-stats">
                                                    <span class="treatment-count"><?php echo (int)$detail['count']; ?> treatments</span>
                                                    <span class="service-revenue">₱<?php echo number_format((float)$detail['revenue'], 2); ?></span>
                                                </div>
                                            </div>
                                            <div class="revenue-percentage"><?php echo $pct; ?>%</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="monthly-revenue-no-data">No data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data-message">
                        <div class="no-data-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3>No data available</h3>
                        <p>No Monthly Revenue data is available for <?php echo htmlspecialchars($dateRangeLabel, ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Revenue by Services Report -->
            <div id="financialReport" class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Revenue by Services</h3>
                </div>

                <?php if (!empty($serviceNames)): ?>
                    <!-- Revenue Chart and Details -->
                    <div class="revenue-content">
                        <div class="chart-container">
                            <div class="chart-box">
                                <canvas id="revenueByServicesChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Service Details -->
                        <div class="service-details">
                            <h4>Service Revenue Details</h4>
                            <div class="service-list">
                                <?php foreach ($serviceNames as $index => $service): ?>
                                <div class="service-item">
                                    <div class="service-info">
                                        <div class="service-name"><?php echo htmlspecialchars($service); ?></div>
                                        <div class="service-stats">
                                            <span class="treatment-count"><?php echo $treatmentCounts[$index]; ?> treatments</span>
                                            <span class="service-revenue">₱<?php echo number_format($serviceRevenues[$index], 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="revenue-percentage">
                                        <?php echo $totalRevenue > 0 ? round(($serviceRevenues[$index] / $totalRevenue) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Data Message -->
                    <div class="no-data-message">
                        <div class="no-data-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3>No Revenue Data Available</h3>
                        <p>Revenue data will appear here once treatments are completed and recorded in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Back navigation function
        function navigateBack(event) {
            event.preventDefault();
            window.location.href = '../views/admin.php';
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const monthlyData = <?php echo json_encode($monthlyServiceData); ?>;
        const colorPalette = ['#4F46E5', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4', '#8B5CF6', '#84CC16', '#EC4899'];

        const monthlyRevenueByServices = <?php echo json_encode([
            'monthLabels' => $monthlyRevenueMonthLabels,
            'services' => $monthlyRevenueServiceNames,
            'revenues' => $monthlyRevenueRevenuesMatrix,
            'counts' => $monthlyRevenueCountsMatrix,
            'totalsByMonth' => $monthlyRevenueTotalsByMonth,
            'hasData' => $hasMonthlyRevenueData,
            'monthKeys' => array_column($reportMonths, 'key'),
        ]); ?>;
        const monthlyRevenueColorPalette = ['#4F46E5', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4', '#8B5CF6', '#84CC16', '#EC4899', '#F97316', '#0EA5E9'];

        const monthKeyToIndex = <?php echo json_encode($monthKeyToIndex, JSON_UNESCAPED_UNICODE); ?>;

        const currentYear = <?php echo json_encode($currentYear); ?>;

        const revenueByServicesExport = <?php echo json_encode([
            'serviceNames' => $serviceNames,
            'treatmentCounts' => $treatmentCounts,
            'serviceRevenues' => $serviceRevenues,
            'totalRevenue' => (float)$totalRevenue
        ]); ?>;

        const appointmentsPerDayExport = <?php echo json_encode([
            'dates' => $dates,
            'rawDates' => $rawDates,
            'counts' => $counts
        ]); ?>;
        const dashboardPeriodForPdfExport = <?php echo json_encode([
            'range' => $period['range'],
            'date_from' => $startStr,
            'date_to' => $endStr
        ], JSON_UNESCAPED_UNICODE); ?>;

        let pieChart, appointmentsChart, revenueByServicesChart, appointmentStatusChart, serviceRevenueChart, servicesAvailedChart, monthlyRevenueByServicesChart;

        // Initialize Dashboard Charts
        function initDashboardCharts() {
            // Appointment Status Chart
            const statusCtx = document.getElementById('appointmentStatusChart').getContext('2d');
            appointmentStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_keys($appointmentStatuses)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($appointmentStatuses)); ?>,
                        backgroundColor: ['#F59E0B', '#10B981', '#3B82F6', '#EF4444', '#6B7280'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });

            // Total Downpayment by Services Chart
            const serviceRevenueCtx = document.getElementById('serviceRevenueChart').getContext('2d');
            serviceRevenueChart = new Chart(serviceRevenueCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($serviceRevenueLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($serviceRevenueAmounts); ?>,
                        backgroundColor: colorPalette,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ₱${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Revenue by Services Chart
            <?php if (!empty($serviceNames)): ?>
            const revenueByServicesCtx = document.getElementById('revenueByServicesChart');
            if (revenueByServicesCtx) {
                revenueByServicesChart = new Chart(revenueByServicesCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($serviceNames); ?>,
                        datasets: [{
                            data: <?php echo json_encode($serviceRevenues); ?>,
                            backgroundColor: [
                                '#4F46E5', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4',
                                '#8B5CF6', '#84CC16', '#EC4899', '#F97316', '#0EA5E9'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    },
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map(function(label, i) {
                                                const value = data.datasets[0].data[i];
                                                return {
                                                    text: label,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    hidden: isNaN(data.datasets[0].data[i]) || chart.getDatasetMeta(0).data[i].hidden,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return `${label}: ₱${value.toLocaleString()} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        scales: {},
                        animation: {
                            animateScale: true,
                            animateRotate: true
                        },
                        cutout: '0%'
                    }
                });
            }
            <?php endif; ?>

            // Services Availed Count Bar Chart
            const availedCtx = document.getElementById('servicesAvailedChart').getContext('2d');
            servicesAvailedChart = new Chart(availedCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($servicesAvailedLabels); ?>,
                    datasets: [{
                        label: 'Number of Appointments',
                        data: <?php echo json_encode($servicesAvailedCounts); ?>,
                        backgroundColor: '#4F46E5',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Appointments Chart
            const appointmentsCtx = document.getElementById('appointmentsBarChart').getContext('2d');
            appointmentsChart = new Chart(appointmentsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [{
                        label: 'Appointments',
                        data: <?php echo json_encode($counts); ?>,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgb(63, 137, 255)',
                        tension: 0.2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: {
                            display: false
                        }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true 
                        } 
                    }
                }
            });
        }

        function updateChart() {
            const monthSelect = document.getElementById('monthSelect');
            const selectedKey = monthSelect ? monthSelect.value : '';
            const data = monthlyData[selectedKey];
            if (!data) return;
            const serviceCtx = document.getElementById('servicePieChart').getContext('2d');
            const colorGuide = document.getElementById('colorGuide');
            const titleSuffix = monthSelect && monthSelect.selectedOptions[0] ? monthSelect.selectedOptions[0].text : '';

            colorGuide.innerHTML = '';
            data.labels.forEach((label, index) => {
                colorGuide.innerHTML += `
                    <div class="color-item">
                        <div class="color-dot" style="background:${colorPalette[index % colorPalette.length]}"></div>
                        <span>${label}</span>
                    </div>`;
            });

            if (pieChart) pieChart.destroy();
            pieChart = new Chart(serviceCtx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.counts,
                        backgroundColor: data.labels.map((_, i) => colorPalette[i % colorPalette.length])
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: `Patients per Service - ${titleSuffix}`
                        },
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            title: { display: true, text: 'Patients' } 
                        },
                        x: { 
                            title: { display: true, text: 'Services' } 
                        }
                    }
                }
            });
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function (m) {
                switch (m) {
                    case '&': return '&amp;';
                    case '<': return '&lt;';
                    case '>': return '&gt;';
                    case '"': return '&quot;';
                    case "'": return '&#039;';
                    default: return m;
                }
            });
        }

        function updateMonthlyRevenueDetails() {
            const select = document.getElementById('monthlyRevenueMonthSelect');
            const list = document.getElementById('monthlyRevenueServiceDetailsList');
            if (!select || !list || !monthlyRevenueByServices?.hasData) return;

            const selectedKey = select.value;
            const monthIdx = monthKeyToIndex[selectedKey];
            if (monthIdx === undefined || monthIdx < 0) return;
            const totalRevenueForMonth = monthlyRevenueByServices.totalsByMonth[monthIdx] ?? 0;

            const services = monthlyRevenueByServices.services;
            const revenuesMatrix = monthlyRevenueByServices.revenues;
            const countsMatrix = monthlyRevenueByServices.counts;

            const items = [];
            for (let i = 0; i < services.length; i++) {
                const revenue = Number(revenuesMatrix[i]?.[monthIdx] ?? 0);
                if (revenue > 0) {
                    items.push({
                        service: services[i],
                        revenue,
                        count: Number(countsMatrix[i]?.[monthIdx] ?? 0)
                    });
                }
            }

            items.sort((a, b) => b.revenue - a.revenue);

            if (!items.length) {
                list.innerHTML = `<div class="monthly-revenue-no-data">No data available</div>`;
                return;
            }

            list.innerHTML = items.map((item) => {
                const pct = totalRevenueForMonth > 0 ? ((item.revenue / totalRevenueForMonth) * 100).toFixed(1) : '0.0';
                return `
                    <div class="service-item">
                        <div class="service-info">
                            <div class="service-name">${escapeHtml(item.service)}</div>
                            <div class="service-stats">
                                <span class="treatment-count">${item.count} treatments</span>
                                <span class="service-revenue">₱${item.revenue.toLocaleString()}</span>
                            </div>
                        </div>
                        <div class="revenue-percentage">${pct}%</div>
                    </div>
                `;
            }).join('');
        }

        function initMonthlyRevenueByServicesChart() {
            const canvas = document.getElementById('monthlyRevenueByServicesChart');
            const loader = document.getElementById('monthlyRevenueChartLoader');
            if (!canvas || !monthlyRevenueByServices?.hasData) return;

            const ctx = canvas.getContext('2d');
            const datasets = monthlyRevenueByServices.services.map((serviceName, serviceIdx) => {
                return {
                    label: serviceName,
                    data: monthlyRevenueByServices.revenues[serviceIdx],
                    backgroundColor: monthlyRevenueColorPalette[serviceIdx % monthlyRevenueColorPalette.length],
                    borderRadius: 6,
                    borderWidth: 0
                };
            });

            if (loader) loader.classList.remove('hidden');

            monthlyRevenueByServicesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyRevenueByServices.monthLabels,
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 900,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                usePointStyle: true,
                                boxWidth: 10,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const service = context.dataset.label || '';
                                    const value = context.parsed.y ?? 0;
                                    return `${service}: ₱${Number(value).toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return `₱${Number(value).toLocaleString()}`;
                                }
                            }
                        }
                    }
                }
            });

            if (loader) {
                setTimeout(() => loader.classList.add('hidden'), 950);
            }
        }

        function pad2(n) {
            return String(n).padStart(2, '0');
        }

        function getLocalDateStamp() {
            const now = new Date();
            return `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`;
        }

        function toCSVValue(value) {
            if (value === null || value === undefined) return '';
            const str = String(value);
            if (/[",\n\r]/.test(str)) {
                return `"${str.replace(/"/g, '""')}"`;
            }
            return str;
        }

        function buildCSV(headers, rows) {
            const headerLine = headers.map(toCSVValue).join(',');
            const rowLines = rows.map((row) => row.map(toCSVValue).join(',')).join('\n');
            return `${headerLine}\n${rowLines}\n`;
        }

        function downloadCSV(filename, csvContent) {
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        function downloadPDF(filename, pdfBlob) {
            const url = URL.createObjectURL(pdfBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        function showNoDataExport() {
            alert('No data available to export.');
        }

        function pdfExportTodayYmd() {
            return getLocalDateStamp();
        }

        function initPdfCustomDateDefaults() {
            const fromEl = document.getElementById("pdfExportDateFrom");
            const toEl = document.getElementById("pdfExportDateTo");
            if (!fromEl || !toEl) return;
            const today = pdfExportTodayYmd();
            const d = new Date();
            d.setDate(d.getDate() - 30);
            const pad = (n) => String(n).padStart(2, "0");
            const defFrom = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            if (!fromEl.value) fromEl.value = defFrom;
            if (!toEl.value) toEl.value = today;
            fromEl.max = today;
            toEl.max = today;
        }

        function syncPdfCustomPanel() {
            const wrap = document.getElementById("pdfExportCustomWrap");
            const customRadio = document.getElementById("pdfExportRangeCustom");
            if (!wrap || !customRadio) return;
            const show = customRadio.checked;
            wrap.hidden = !show;
            if (show) initPdfCustomDateDefaults();
        }

        function openPdfExportModal() {
            const modal = document.getElementById("pdfExportModal");
            if (!modal) return;
            modal.classList.add("is-open");
            modal.setAttribute("aria-hidden", "false");
            syncPdfCustomPanel();
        }

        function closePdfExportModal() {
            const modal = document.getElementById("pdfExportModal");
            if (!modal) return;
            modal.classList.remove("is-open");
            modal.setAttribute("aria-hidden", "true");
        }

        function getSelectedPdfExportRange() {
            const sel = document.querySelector('input[name="pdfExportRange"]:checked');
            return sel ? sel.value : "1y";
        }

        function buildPdfExportPayload() {
            // Always export using the same dashboard period to keep totals consistent.
            const range = dashboardPeriodForPdfExport?.range || "1y";
            if (range === "custom") {
                return {
                    range: "custom",
                    date_from: dashboardPeriodForPdfExport?.date_from || "",
                    date_to: dashboardPeriodForPdfExport?.date_to || ""
                };
            }
            return { range };
        }

        async function runAppointmentPdfExport() {
            const range = dashboardPeriodForPdfExport?.range || "1y";
            if (range === "custom") {
                const dateFrom = dashboardPeriodForPdfExport?.date_from || "";
                const dateTo = dashboardPeriodForPdfExport?.date_to || "";
                if (!dateFrom || !dateTo) {
                    alert("Please select both start and end dates for a custom range.");
                    return;
                }
                if (dateFrom > dateTo) {
                    alert("The start date must be on or before the end date.");
                    return;
                }
                const today = pdfExportTodayYmd();
                if (dateTo > today) {
                    alert("The end date cannot be after today.");
                    return;
                }
                if (dateFrom > today) {
                    alert("The start date cannot be in the future.");
                    return;
                }
                const startMs = new Date(dateFrom + "T12:00:00").getTime();
                const endMs = new Date(dateTo + "T12:00:00").getTime();
                const spanDays = Math.floor((endMs - startMs) / 86400000) + 1;
                if (spanDays > 1826) {
                    alert("Custom range cannot exceed 5 years. Please choose a shorter period.");
                    return;
                }
            }

            const filename = `clinic_report_${getLocalDateStamp()}.pdf`;

            try {
                const response = await fetch("../controllers/exportAppointmentReportPdf.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "include",
                    body: JSON.stringify(buildPdfExportPayload())
                });

                const contentType = (response.headers.get("content-type") || "").toLowerCase();
                if (contentType.includes("application/json")) {
                    const data = await response.json().catch(() => null);
                    alert((data && data.message) ? data.message : "No data available to export.");
                    return;
                }

                if (!contentType.includes("application/pdf")) {
                    const text = await response.text().catch(() => "");
                    alert(text || "Failed to export PDF.");
                    return;
                }

                const blob = await response.blob();
                downloadPDF(filename, blob);
                closePdfExportModal();
            } catch (err) {
                console.error(err);
                alert("Error exporting PDF. Please try again.");
            }
        }

        function exportRevenueByServicesCSV() {
            const serviceNames = revenueByServicesExport?.serviceNames ?? [];
            const treatmentCountsArr = revenueByServicesExport?.treatmentCounts ?? [];
            const serviceRevenuesArr = revenueByServicesExport?.serviceRevenues ?? [];
            const totalRevenueVal = Number(revenueByServicesExport?.totalRevenue ?? 0);

            if (!serviceNames.length) return showNoDataExport();

            const headers = ['Service Name', 'Total Treatments', 'Total Revenue', 'Percentage of Total Revenue'];
            const rows = serviceNames.map((name, idx) => {
                const treatments = Number(treatmentCountsArr[idx] ?? 0);
                const revenue = Number(serviceRevenuesArr[idx] ?? 0);
                const pct = totalRevenueVal > 0 ? ((revenue / totalRevenueVal) * 100).toFixed(1) : '0.0';
                return [name, treatments, revenue.toFixed(2), pct];
            });

            const filename = `revenue_by_services_${getLocalDateStamp()}.csv`;
            downloadCSV(filename, buildCSV(headers, rows));
        }

        function exportMonthlyRevenueByServicesCSV() {
            if (!monthlyRevenueByServices?.hasData) return showNoDataExport();

            const monthSelect = document.getElementById('monthlyRevenueMonthSelect');
            const selectedKey = monthSelect ? monthSelect.value : '';
            const monthIdx = monthKeyToIndex[selectedKey];
            if (monthIdx === undefined || monthIdx < 0) return showNoDataExport();
            const monthStr = selectedKey;

            const services = monthlyRevenueByServices.services ?? [];
            const revenuesMatrix = monthlyRevenueByServices.revenues ?? [];
            const countsMatrix = monthlyRevenueByServices.counts ?? [];

            if (!services.length) return showNoDataExport();

            const headers = ['Month', 'Service Name', 'Total Revenue'];
            const rows = services.map((serviceName, serviceIdx) => {
                const revenue = Number(revenuesMatrix[serviceIdx]?.[monthIdx] ?? 0);
                const count = Number(countsMatrix[serviceIdx]?.[monthIdx] ?? 0);
                // Keep only actual records shown for the selected month (revenue > 0).
                // Count is not required by spec for this export, but we still compute it to align with panel selection.
                return revenue > 0 ? [monthStr, serviceName, revenue.toFixed(2)] : null;
            }).filter(Boolean);

            if (!rows.length) return showNoDataExport();

            const filename = `monthly_revenue_by_services_${getLocalDateStamp()}.csv`;
            downloadCSV(filename, buildCSV(headers, rows));
        }

        function exportMonthlyServiceDistributionCSV() {
            const monthSelect = document.getElementById('monthSelect');
            const selectedKey = monthSelect ? monthSelect.value : '';

            const monthData = monthlyData?.[selectedKey];
            const labels = monthData?.labels ?? [];
            const countsArr = monthData?.counts ?? [];

            if (!labels.length) return showNoDataExport();

            const monthStr = selectedKey;

            const headers = ['Month', 'Service Category', 'Patients Count'];
            const rows = labels.map((label, idx) => [monthStr, label, Number(countsArr[idx] ?? 0)]);

            const filename = `monthly_service_distribution_${getLocalDateStamp()}.csv`;
            downloadCSV(filename, buildCSV(headers, rows));
        }

        function exportAppointmentsPerDayCSV() {
            const dates = appointmentsPerDayExport?.rawDates ?? [];
            const countsArr = appointmentsPerDayExport?.counts ?? [];
            if (!dates.length) return showNoDataExport();

            const headers = ['Date', 'Appointments Count'];
            const rows = dates.map((d, idx) => [d, Number(countsArr[idx] ?? 0)]);

            const filename = `appointments_per_day_${getLocalDateStamp()}.csv`;
            downloadCSV(filename, buildCSV(headers, rows));
        }

        function exportAllReportsToCSV() {
            const headers = [
                'Report Type',
                'Month',
                'Date',
                'Service Name',
                'Service Category',
                'Total Treatments',
                'Total Revenue',
                'Percentage',
                'Patients Count',
                'Appointments Count'
            ];

            const rows = [];

            // Revenue by Services
            const serviceNames = revenueByServicesExport?.serviceNames ?? [];
            const treatmentCountsArr = revenueByServicesExport?.treatmentCounts ?? [];
            const serviceRevenuesArr = revenueByServicesExport?.serviceRevenues ?? [];
            const totalRevenueVal = Number(revenueByServicesExport?.totalRevenue ?? 0);

            if (serviceNames.length && totalRevenueVal >= 0) {
                serviceNames.forEach((name, idx) => {
                    const treatments = Number(treatmentCountsArr[idx] ?? 0);
                    const revenue = Number(serviceRevenuesArr[idx] ?? 0);
                    const pct = totalRevenueVal > 0 ? ((revenue / totalRevenueVal) * 100).toFixed(1) : '0.0';
                    rows.push(['Revenue by Services', '', '', name, '', treatments, revenue.toFixed(2), pct, '', '']);
                });
            }

            // Monthly Revenue by Services (use currently selected month in the month dropdown)
            if (monthlyRevenueByServices?.hasData) {
                const monthSelect = document.getElementById('monthlyRevenueMonthSelect');
                const selectedKey = monthSelect ? monthSelect.value : '';
                const monthIdx = monthKeyToIndex[selectedKey];
                if (monthIdx !== undefined && monthIdx >= 0) {
                    const monthStr = selectedKey;
                    const services = monthlyRevenueByServices.services ?? [];
                    const revenuesMatrix = monthlyRevenueByServices.revenues ?? [];
                    services.forEach((serviceName, serviceIdx) => {
                        const revenue = Number(revenuesMatrix[serviceIdx]?.[monthIdx] ?? 0);
                        if (revenue > 0) {
                            rows.push(['Monthly Revenue by Services', monthStr, '', serviceName, '', '', revenue.toFixed(2), '', '', '']);
                        }
                    });
                }
            }

            // Monthly Service Distribution (use currently selected month)
            const serviceMonthSelect = document.getElementById('monthSelect');
            const serviceSelectedKey = serviceMonthSelect ? serviceMonthSelect.value : '';
            const monthData = monthlyData?.[serviceSelectedKey];
            const labels = monthData?.labels ?? [];
            const countsArr = monthData?.counts ?? [];
            if (labels.length) {
                const monthStr = serviceSelectedKey;
                labels.forEach((label, idx) => {
                    rows.push(['Monthly Service Distribution', monthStr, '', '', label, '', '', '', Number(countsArr[idx] ?? 0), '']);
                });
            }

            // Appointments Per Day
            const rawDates = appointmentsPerDayExport?.rawDates ?? [];
            const apptCountsArr = appointmentsPerDayExport?.counts ?? [];
            if (rawDates.length) {
                rawDates.forEach((d, idx) => {
                    rows.push(['Appointments Per Day', '', d, '', '', '', '', '', '', Number(apptCountsArr[idx] ?? 0)]);
                });
            }

            if (!rows.length) return showNoDataExport();

            const filename = `reports_all_${getLocalDateStamp()}.csv`;
            downloadCSV(filename, buildCSV(headers, rows));
        }

        function exportActiveReportToCSV() {
            const reportType = document.getElementById('reportType')?.value ?? 'all';
            if (reportType === 'all') {
                exportAllReportsToCSV();
                return;
            }

            switch (reportType) {
                case 'financial':
                    exportRevenueByServicesCSV();
                    break;
                case 'monthlyRevenue':
                    exportMonthlyRevenueByServicesCSV();
                    break;
                case 'service':
                    exportMonthlyServiceDistributionCSV();
                    break;
                case 'appointments':
                    exportAppointmentsPerDayCSV();
                    break;
                default:
                    alert('No export handler found for this report type.');
            }
        }

        function filterReports() {
            const selected = document.getElementById('reportType').value;
            const reportSections = document.querySelectorAll('.report-section');
            
            if (selected === 'all') {
                // Show all reports
                reportSections.forEach(section => {
                    section.style.display = 'block';
                });
            } else {
                // Hide all reports first
                reportSections.forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show only the selected report
                const selectedSection = document.getElementById(selected + 'Report');
                if (selectedSection) {
                    selectedSection.style.display = 'block';
                    
                    // Smooth scroll to the selected report section
                    setTimeout(() => {
                        selectedSection.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start'
                        });
                    }, 100);
                }
            }
        }

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateChart();
            initDashboardCharts();
            initMonthlyRevenueByServicesChart();
            updateMonthlyRevenueDetails();
            
            // All reports are visible by default
            filterReports(); // This will show all reports initially

            const exportBtn = document.getElementById('exportCsvBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', exportActiveReportToCSV);
            }

            const exportPdfBtn = document.getElementById('exportPdfBtn');
            if (exportPdfBtn) {
                exportPdfBtn.addEventListener('click', openPdfExportModal);
            }

            const pdfModal = document.getElementById('pdfExportModal');
            if (pdfModal) {
                pdfModal.querySelectorAll('[data-close-pdf-modal]').forEach((el) => {
                    el.addEventListener('click', closePdfExportModal);
                });
                pdfModal.addEventListener('click', (e) => {
                    if (e.target === pdfModal) closePdfExportModal();
                });
                pdfModal.querySelectorAll('input[name="pdfExportRange"]').forEach((radio) => {
                    radio.addEventListener('change', syncPdfCustomPanel);
                });
            }

            const pdfExportConfirmBtn = document.getElementById('pdfExportConfirmBtn');
            if (pdfExportConfirmBtn) {
                pdfExportConfirmBtn.addEventListener('click', runAppointmentPdfExport);
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closePdfExportModal();
            });
        });
    </script>
</body>
</html>
