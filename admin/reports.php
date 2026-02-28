<?php
session_start();
include_once("../database/config.php");

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
$totaldownPayment = mysqli_fetch_assoc(mysqli_query($con, "SELECT IFNULL(SUM(amount), 0) AS total FROM payment WHERE status = 'paid'"))['total'];

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
    WHERE p.status = 'paid'
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
$counts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dates[] = date('M j', strtotime($row['appointment_date']));
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
                    <option value="financial">Revenue by Services</option>
                </select>
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
        let pieChart, appointmentsChart, revenueByServicesChart, appointmentStatusChart, serviceRevenueChart, servicesAvailedChart;

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
            
            // All reports are visible by default
            filterReports(); // This will show all reports initially
        });
    </script>
</body>
</html>
