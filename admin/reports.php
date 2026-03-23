<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['admin_verified'])) {
    header("Location: admin_verify.php");
    exit();
}

// Prepare data for reports
// Total Appointments
$totalAppointments = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total FROM appointments"))['total'];

// Total Down Payment
$totaldownPayment = mysqli_fetch_assoc(mysqli_query($con, "
    SELECT IFNULL(SUM(p.amount), 0) AS total
    FROM payment p
    WHERE COALESCE(p.is_archived, 0) = 0
      AND LOWER(TRIM(p.status)) = 'paid'
"))['total'];

$totalRevenue = mysqli_fetch_assoc(mysqli_query($con, "SELECT IFNULL(SUM(treatment_cost), 0) AS total FROM treatment_history"))['total'];

// Today's Appointments
$todayAppointments = mysqli_fetch_assoc(mysqli_query($con, "
    SELECT COUNT(*) AS total FROM appointments 
    WHERE DATE(appointment_date) = CURDATE()
"))['total'];

// Appointment Status Breakdown
$statusQuery = mysqli_query($con, "
    SELECT status, COUNT(*) as count 
    FROM appointments 
    GROUP BY status
");
$appointmentStatuses = [];
while ($row = mysqli_fetch_assoc($statusQuery)) {
    $appointmentStatuses[$row['status']] = $row['count'];
}

// Total Downpayment by Services
$serviceRevenueQuery = mysqli_query($con, "
    SELECT s.service_category, SUM(p.amount) as total_amount
    FROM payment p
    INNER JOIN appointments a ON p.appointment_id = a.appointment_id
    INNER JOIN services s ON a.service_id = s.service_id
    WHERE COALESCE(p.is_archived, 0) = 0
      AND LOWER(TRIM(p.status)) = 'paid'
    GROUP BY s.service_category
");
$serviceRevenueLabels = [];
$serviceRevenueAmounts = [];
while ($row = mysqli_fetch_assoc($serviceRevenueQuery)) {
    $serviceRevenueLabels[] = $row['service_category'];
    $serviceRevenueAmounts[] = (float)$row['total_amount'];
}

// Services Availed Count (based on sub_service)
$servicesAvailedQuery = mysqli_query($con, "
    SELECT s.sub_service, COUNT(*) as count
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.service_id
    GROUP BY s.sub_service
    ORDER BY count DESC
");
$servicesAvailedLabels = [];
$servicesAvailedCounts = [];
while ($row = mysqli_fetch_assoc($servicesAvailedQuery)) {
    $servicesAvailedLabels[] = $row['sub_service'];
    $servicesAvailedCounts[] = (int)$row['count'];
}

// Monthly Service Distribution
$monthlyServiceData = [];
$currentYear = date('Y');
for ($month = 1; $month <= 12; $month++) {
    $sql = "SELECT s.service_category, COUNT(*) AS count
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.service_id
            WHERE MONTH(a.appointment_date) = $month 
            AND YEAR(a.appointment_date) = $currentYear
            GROUP BY s.service_category";
    $result = mysqli_query($con, $sql);
    $services = [];
    $counts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $services[] = $row['service_category'];
        $counts[] = (int)$row['count'];
    }
    $monthlyServiceData[$month] = [
        'labels' => $services,
        'counts' => $counts,
        'total' => array_sum($counts)
    ];
}

// Appointments Per Day (Last 30 days)
$sql = "SELECT appointment_date, COUNT(*) as count FROM appointments 
        WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY appointment_date ORDER BY appointment_date";
$result = mysqli_query($con, $sql);
$dates = [];
$rawDates = [];
$counts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dates[] = date('M j', strtotime($row['appointment_date']));
    $rawDates[] = date('Y-m-d', strtotime($row['appointment_date']));
    $counts[] = (int)$row['count'];
}

// Revenue by Services
$revenueQuery = mysqli_query($con, "
    SELECT 
        th.treatment,
        SUM(th.treatment_cost) as total_revenue,
        COUNT(*) as treatment_count
    FROM treatment_history th
    WHERE th.treatment_cost > 0
    GROUP BY th.treatment
    ORDER BY total_revenue DESC
");

$serviceNames = [];
$serviceRevenues = [];
$treatmentCounts = [];
$totalRevenue = 0;

while ($row = mysqli_fetch_assoc($revenueQuery)) {
    $serviceNames[] = $row['treatment'];
    $serviceRevenues[] = (float)$row['total_revenue'];
    $treatmentCounts[] = (int)$row['treatment_count'];
    $totalRevenue += $row['total_revenue'];
}

// Monthly Revenue by Services (group by year/month/service)
$currentMonth = (int)date('n');
$monthlyRevenueByServicesData = []; // [month][service_name] => ['revenue' => float, 'count' => int]
$monthlyRevenueServiceTotals = []; // [service_name] => total revenue across the year (for sorting)

$monthlyRevenueQuery = mysqli_query($con, "
    SELECT
        YEAR(th.created_at) as year,
        MONTH(th.created_at) as month,
        th.treatment as service_name,
        SUM(th.treatment_cost) as total_revenue,
        COUNT(*) as treatment_count
    FROM treatment_history th
    WHERE th.treatment_cost > 0
      AND th.treatment IS NOT NULL AND th.treatment != ''
      AND YEAR(th.created_at) = $currentYear
    GROUP BY YEAR(th.created_at), MONTH(th.created_at), th.treatment
    ORDER BY YEAR(th.created_at) ASC, MONTH(th.created_at) ASC, total_revenue DESC
");

while ($row = mysqli_fetch_assoc($monthlyRevenueQuery)) {
    $month = (int)$row['month'];
    $serviceName = $row['service_name'];
    $revenue = (float)$row['total_revenue'];
    $count = (int)$row['treatment_count'];

    if (!isset($monthlyRevenueByServicesData[$month])) {
        $monthlyRevenueByServicesData[$month] = [];
    }

    $monthlyRevenueByServicesData[$month][$serviceName] = [
        'revenue' => $revenue,
        'count' => $count
    ];

    $monthlyRevenueServiceTotals[$serviceName] = ($monthlyRevenueServiceTotals[$serviceName] ?? 0) + $revenue;
}

$monthlyRevenueServiceNames = array_keys($monthlyRevenueServiceTotals);
arsort($monthlyRevenueServiceTotals);
$monthlyRevenueServiceNames = array_keys($monthlyRevenueServiceTotals); // sorted by total revenue desc

$monthlyRevenueMonthLabels = [];
$monthlyRevenueTotalsByMonth = array_fill(0, 12, 0.0); // index: 0=Jan
for ($m = 1; $m <= 12; $m++) {
    $monthlyRevenueMonthLabels[] = date('M', mktime(0, 0, 0, $m, 10));
}

$monthlyRevenueRevenuesMatrix = []; // [serviceIndex][monthIndex] => revenue
$monthlyRevenueCountsMatrix = []; // [serviceIndex][monthIndex] => count

foreach ($monthlyRevenueServiceNames as $serviceName) {
    $revenues = array_fill(0, 12, 0.0);
    $counts = array_fill(0, 12, 0);

    for ($m = 1; $m <= 12; $m++) {
        $idx = $m - 1;
        $cell = $monthlyRevenueByServicesData[$m][$serviceName] ?? null;
        if ($cell) {
            $revenues[$idx] = (float)$cell['revenue'];
            $counts[$idx] = (int)$cell['count'];
            $monthlyRevenueTotalsByMonth[$idx] += (float)$cell['revenue'];
        }
    }

    $monthlyRevenueRevenuesMatrix[] = $revenues;
    $monthlyRevenueCountsMatrix[] = $counts;
}

$hasMonthlyRevenueData = array_sum($monthlyRevenueTotalsByMonth) > 0;

// Pre-render current month details for default view
$monthlyRevenueCurrentMonthTotal = $monthlyRevenueTotalsByMonth[$currentMonth - 1] ?? 0.0;
$monthlyRevenueCurrentMonthDetails = [];
foreach ($monthlyRevenueServiceNames as $serviceName) {
    $cell = $monthlyRevenueByServicesData[$currentMonth][$serviceName] ?? null;
    if ($cell && (float)$cell['revenue'] > 0) {
        $monthlyRevenueCurrentMonthDetails[] = [
            'service_name' => $serviceName,
            'revenue' => (float)$cell['revenue'],
            'count' => (int)$cell['count']
        ];
    }
}
usort($monthlyRevenueCurrentMonthDetails, function ($a, $b) {
    return $b['revenue'] <=> $a['revenue'];
});

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
                        <div class="stat-label">Total Appointments</div>
                        <div class="stat-value"><?php echo $totalAppointments; ?></div>
                    </div>
                    <div class="report-stat-card">
                        <div class="stat-label">Total Down Payment</div>
                        <div class="stat-value">₱<?php echo number_format($totaldownPayment, 2); ?></div>
                    </div>
                    <div class="report-stat-card">
                        <div class="stat-label">Today's Appointments</div>
                        <div class="stat-value"><?php echo $todayAppointments; ?></div>
                    </div>
                    <div class="report-stat-card">
                        <div class="stat-label">Total Revenue By Services</div>
                        <div class="stat-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
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
                            for ($m = 1; $m <= 12; $m++) {
                                $monthName = date('F', mktime(0, 0, 0, $m, 10));
                                $selected = $m == date('n') ? 'selected' : '';
                                echo "<option value='$m' $selected>$monthName</option>";
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
                                        for ($m = 1; $m <= 12; $m++) {
                                            $monthName = date('F', mktime(0, 0, 0, $m, 10));
                                            $selected = $m === $currentMonth ? 'selected' : '';
                                            echo "<option value='$m' $selected>$monthName</option>";
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
                        <p>No Monthly Revenue data is available for <?php echo htmlspecialchars($currentYear); ?>.</p>
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
            'hasData' => $hasMonthlyRevenueData
        ]); ?>;
        const monthlyRevenueColorPalette = ['#4F46E5', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4', '#8B5CF6', '#84CC16', '#EC4899', '#F97316', '#0EA5E9'];

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
            const selectedMonth = document.getElementById('monthSelect').value;
            const data = monthlyData[selectedMonth];
            const serviceCtx = document.getElementById('servicePieChart').getContext('2d');
            const colorGuide = document.getElementById('colorGuide');

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
                            text: `Patients per Service - ${getMonthName(selectedMonth)} <?php echo $currentYear; ?>`
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

        function getMonthName(m) {
            const d = new Date(); d.setMonth(m - 1);
            return d.toLocaleString('default', { month: 'long' });
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

            const selectedMonth = parseInt(select.value, 10);
            const monthIdx = selectedMonth - 1;
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

        function buildReportPdfExportPayload(reportType) {
            const filenameSlugMap = {
                financial: 'revenue_by_services',
                monthlyRevenue: 'monthly_revenue_by_services',
                service: 'monthly_service_distribution',
                appointments: 'appointments_per_day',
                all: 'reports_all'
            };

            const filenameSlug = filenameSlugMap[reportType] ?? 'report_export';

            const formatRevenue = (value) => {
                const num = Number(value ?? 0);
                if (Number.isNaN(num)) return 'PHP 0.00';
                return `PHP ${num.toFixed(2)}`;
            };

            const sections = [];

            // Revenue by Services
            const serviceNames = revenueByServicesExport?.serviceNames ?? [];
            const treatmentCountsArr = revenueByServicesExport?.treatmentCounts ?? [];
            const serviceRevenuesArr = revenueByServicesExport?.serviceRevenues ?? [];
            const totalRevenueVal = Number(revenueByServicesExport?.totalRevenue ?? 0);

            // Monthly Revenue by Services (selected month)
            const monthlyRevenueMonthSelect = document.getElementById('monthlyRevenueMonthSelect');
            const selectedMonthlyRevenueMonth = monthlyRevenueMonthSelect ? parseInt(monthlyRevenueMonthSelect.value, 10) : (new Date().getMonth() + 1);
            const monthlyRevenueMonthStr = `${currentYear}-${pad2(selectedMonthlyRevenueMonth)}`;
            const monthIdxForMonthlyRevenue = selectedMonthlyRevenueMonth - 1;
            const monthlyRevenueServices = monthlyRevenueByServices?.services ?? [];
            const monthlyRevenueRevenuesMatrix = monthlyRevenueByServices?.revenues ?? [];

            // Monthly Service Distribution (selected month)
            const monthSelect = document.getElementById('monthSelect');
            const selectedServiceMonth = monthSelect ? parseInt(monthSelect.value, 10) : (new Date().getMonth() + 1);
            const serviceMonthStr = `${currentYear}-${pad2(selectedServiceMonth)}`;
            const monthData = monthlyData?.[selectedServiceMonth];
            const serviceCategoryLabels = monthData?.labels ?? [];
            const serviceCategoryCountsArr = monthData?.counts ?? [];

            // Appointments Per Day
            const dates = appointmentsPerDayExport?.rawDates ?? [];
            const countsArr = appointmentsPerDayExport?.counts ?? [];

            const shouldAddFinancial = () => {
                if (!serviceNames.length) return false;
                return totalRevenueVal >= 0;
            };

            const financialSectionRows = () => {
                return serviceNames.map((name, idx) => {
                    const treatments = Number(treatmentCountsArr[idx] ?? 0);
                    const revenue = Number(serviceRevenuesArr[idx] ?? 0);
                    const pct = totalRevenueVal > 0 ? ((revenue / totalRevenueVal) * 100).toFixed(1) : '0.0';
                    return [name, treatments, formatRevenue(revenue), `${pct}%`];
                });
            };

            const monthlyRevenueSectionRows = () => {
                if (!monthlyRevenueByServices?.hasData) return [];
                if (!monthlyRevenueServices.length) return [];

                return monthlyRevenueServices.map((serviceName, serviceIdx) => {
                    const revenue = Number(monthlyRevenueRevenuesMatrix[serviceIdx]?.[monthIdxForMonthlyRevenue] ?? 0);
                    return revenue > 0 ? [monthlyRevenueMonthStr, serviceName, formatRevenue(revenue)] : null;
                }).filter(Boolean);
            };

            const serviceSectionRows = () => {
                if (!serviceCategoryLabels.length) return [];
                return serviceCategoryLabels.map((label, idx) => [serviceMonthStr, label, Number(serviceCategoryCountsArr[idx] ?? 0)]);
            };

            const appointmentsSectionRows = () => {
                if (!dates.length) return [];
                return dates.map((d, idx) => [d, Number(countsArr[idx] ?? 0)]);
            };

            const addSectionIfRows = (title, columns, rows) => {
                if (!rows || !rows.length) return;
                sections.push({ title, columns, rows });
            };

            switch (reportType) {
                case 'financial': {
                    if (!shouldAddFinancial()) return null;
                    addSectionIfRows(
                        'Revenue by Services',
                        ['Service Name', 'Total Treatments', 'Total Revenue', 'Percentage of Total Revenue'],
                        financialSectionRows()
                    );
                    break;
                }
                case 'monthlyRevenue': {
                    const rows = monthlyRevenueSectionRows();
                    addSectionIfRows(
                        'Monthly Revenue by Services',
                        ['Month', 'Service Name', 'Total Revenue'],
                        rows
                    );
                    break;
                }
                case 'service': {
                    const rows = serviceSectionRows();
                    addSectionIfRows(
                        'Monthly Service Distribution',
                        ['Month', 'Service Category', 'Patients Count'],
                        rows
                    );
                    break;
                }
                case 'appointments': {
                    const rows = appointmentsSectionRows();
                    addSectionIfRows(
                        'Appointments Per Day',
                        ['Date', 'Appointments Count'],
                        rows
                    );
                    break;
                }
                case 'all': {
                    if (shouldAddFinancial()) {
                        addSectionIfRows(
                            'Revenue by Services',
                            ['Service Name', 'Total Treatments', 'Total Revenue', 'Percentage of Total Revenue'],
                            financialSectionRows()
                        );
                    }
                    addSectionIfRows(
                        'Monthly Revenue by Services',
                        ['Month', 'Service Name', 'Total Revenue'],
                        monthlyRevenueSectionRows()
                    );
                    addSectionIfRows(
                        'Monthly Service Distribution',
                        ['Month', 'Service Category', 'Patients Count'],
                        serviceSectionRows()
                    );
                    addSectionIfRows(
                        'Appointments Per Day',
                        ['Date', 'Appointments Count'],
                        appointmentsSectionRows()
                    );
                    break;
                }
                default:
                    return null;
            }

            if (!sections.length) return null;

            const reportTitle =
                reportType === 'all' ? 'Reports & Analytics (Export)' :
                reportType === 'financial' ? 'Revenue by Services' :
                reportType === 'monthlyRevenue' ? 'Monthly Revenue by Services' :
                reportType === 'service' ? 'Monthly Service Distribution' :
                reportType === 'appointments' ? 'Appointments Per Day' :
                'Report Export';

            return {
                slug: filenameSlug,
                reportTitle,
                sections
            };
        }

        async function exportActiveReportToPDF() {
            const reportType = document.getElementById('reportType')?.value ?? 'all';
            const payload = buildReportPdfExportPayload(reportType);
            if (!payload) return showNoDataExport();

            const filename = `${payload.slug}_${getLocalDateStamp()}.pdf`;

            try {
                const response = await fetch("../controllers/exportReportsPdf.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "include",
                    body: JSON.stringify(payload)
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
            const selectedMonth = monthSelect ? parseInt(monthSelect.value, 10) : (new Date().getMonth() + 1);
            const monthStr = `${currentYear}-${pad2(selectedMonth)}`;
            const monthIdx = selectedMonth - 1;

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
            const selectedMonth = monthSelect ? parseInt(monthSelect.value, 10) : (new Date().getMonth() + 1);

            const monthData = monthlyData?.[selectedMonth];
            const labels = monthData?.labels ?? [];
            const countsArr = monthData?.counts ?? [];

            if (!labels.length) return showNoDataExport();

            const monthStr = `${currentYear}-${pad2(selectedMonth)}`;

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
                const selectedMonth = monthSelect ? parseInt(monthSelect.value, 10) : (new Date().getMonth() + 1);
                const monthStr = `${currentYear}-${pad2(selectedMonth)}`;
                const monthIdx = selectedMonth - 1;

                const services = monthlyRevenueByServices.services ?? [];
                const revenuesMatrix = monthlyRevenueByServices.revenues ?? [];

                services.forEach((serviceName, serviceIdx) => {
                    const revenue = Number(revenuesMatrix[serviceIdx]?.[monthIdx] ?? 0);
                    if (revenue > 0) {
                        rows.push(['Monthly Revenue by Services', monthStr, '', serviceName, '', '', revenue.toFixed(2), '', '', '']);
                    }
                });
            }

            // Monthly Service Distribution (use currently selected month)
            const serviceMonthSelect = document.getElementById('monthSelect');
            const serviceSelectedMonth = serviceMonthSelect ? parseInt(serviceMonthSelect.value, 10) : (new Date().getMonth() + 1);
            const monthData = monthlyData?.[serviceSelectedMonth];
            const labels = monthData?.labels ?? [];
            const countsArr = monthData?.counts ?? [];
            if (labels.length) {
                const monthStr = `${currentYear}-${pad2(serviceSelectedMonth)}`;
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
                exportPdfBtn.addEventListener('click', exportActiveReportToPDF);
            }
        });
    </script>
</body>
</html>
