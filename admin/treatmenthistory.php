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

// Get treatment history data
$historySql = "SELECT th.patient_id, th.treatment, th.prescription_given, th.treatment_cost, th.notes, th.created_at,
                      CONCAT(p.first_name, ' ', p.last_name) as patient_name
               FROM treatment_history th
               LEFT JOIN patient_information p ON th.patient_id = p.patient_id
               ORDER BY th.created_at DESC";
$historyResult = mysqli_query($con, $historySql);

// Get unique treatments for filter
$treatmentsQuery = "SELECT DISTINCT treatment FROM treatment_history WHERE treatment IS NOT NULL AND treatment != '' ORDER BY treatment";
$treatmentsResult = mysqli_query($con, $treatmentsQuery);
$treatments = [];
while ($treatmentRow = mysqli_fetch_assoc($treatmentsResult)) {
    $treatments[] = $treatmentRow['treatment'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment History - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="treatmenthistoryDesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Notification System Styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 400px;
        }

        .notification {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 320px;
            animation: slideInRight 0.4s ease-out;
            position: relative;
            overflow: hidden;
        }

        .notification.success {
            border-left: 4px solid #10B981;
        }

        .notification.warning {
            border-left: 4px solid #F59E0B;
        }

        .notification.error {
            border-left: 4px solid #EF4444;
        }

        .notification.info {
            border-left: 4px solid #3B82F6;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- Treatment History Section -->
<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fa-solid fa-notes-medical"></i> PATIENT TREATMENT HISTORY</h2>

        <div class="filter-container">
            <div class="filter-group">
                <label for="filter-treatment-type"><i class="fas fa-stethoscope"></i> Treatment:</label>
                <select id="filter-treatment-type" onchange="filterTreatmentHistory()">
                    <option value="">All Treatments</option>
                    <?php foreach ($treatments as $treatment): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($treatment)); ?>">
                            <?php echo htmlspecialchars($treatment); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group search-group">
                <label for="filter-treatment-search"><i class="fas fa-search"></i> Search:</label>
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="filter-treatment-search" class="search-input" 
                           placeholder="Search by patient ID, name, treatment..." onkeyup="filterTreatmentHistory()">
                    <button type="button" class="search-clear-btn" id="clear-search-btn" onclick="clearTreatmentSearch()" style="display:none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <button class="btn btn-accent" onclick="printTreatmentHistory()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <div class="table-responsive">
            <table id="treatment-history-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Treatment</th>
                        <th>Prescription Given</th>
                        <th>Treatment Cost</th>
                        <th>Notes</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($historyResult) > 0) {
                        mysqli_data_seek($historyResult, 0);
                        while ($row = mysqli_fetch_assoc($historyResult)) { 
                            $searchText = strtolower($row['patient_id'] . ' ' . ($row['patient_name'] ?? '') . ' ' . $row['treatment']);
                    ?>
                        <tr class="history-row" 
                            data-treatment="<?php echo htmlspecialchars(strtolower($row['treatment'])); ?>"
                            data-search="<?php echo htmlspecialchars($searchText); ?>">
                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['treatment']); ?></td>
                            <td><?php echo htmlspecialchars($row['prescription_given']); ?></td>
                            <td>₱<?php echo number_format($row['treatment_cost'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="action-btn btn-primary" title="Export/Print" onclick="printTreatmentHistoryByPatient('<?php echo htmlspecialchars($row['patient_id']); ?>')">
                                        <i class="fa-solid fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                        <tr>
                            <td colspan="8" class="no-data">
                                <i class="fas fa-calendar-times fa-2x"></i>
                                <p>No Treatment History found</p>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="mobile-card-view">
            <?php 
            // Reset result pointer
            mysqli_data_seek($historyResult, 0);
            if(mysqli_num_rows($historyResult) > 0) {
                while ($row = mysqli_fetch_assoc($historyResult)) { 
                    $searchText = strtolower($row['patient_id'] . ' ' . ($row['patient_name'] ?? '') . ' ' . $row['treatment']);
            ?>
                <div class="patient-card history-row" 
                     data-treatment="<?php echo htmlspecialchars(strtolower($row['treatment'])); ?>"
                     data-search="<?php echo htmlspecialchars($searchText); ?>">
                    <div class="patient-card-header">
                        <div>
                            <div class="patient-card-id">Patient #<?php echo htmlspecialchars($row['patient_id']); ?></div>
                            <div class="patient-card-name"><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="patient-card-body">
                        <div class="patient-card-field">
                            <div class="patient-card-label">Treatment</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['treatment']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Prescription</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['prescription_given']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Cost</div>
                            <div class="patient-card-value">₱<?php echo number_format($row['treatment_cost'], 2); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Notes</div>
                            <div class="patient-card-value"><?php echo htmlspecialchars($row['notes']); ?></div>
                        </div>
                        <div class="patient-card-field">
                            <div class="patient-card-label">Date</div>
                            <div class="patient-card-value"><?php echo date('M j, Y', strtotime($row['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="patient-card-actions">
                        <button class="action-btn btn-primary" title="Export/Print" onclick="printTreatmentHistoryByPatient('<?php echo htmlspecialchars($row['patient_id']); ?>')">
                            <i class="fa-solid fa-print"></i> Print
                        </button>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-calendar-times fa-2x"></i>
                    <p>No Treatment History found</p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Pagination Controls -->
        <div class="pagination-container" id="treatment-pagination-container">
            <div class="pagination-info" id="treatment-pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="treatment-prev-page-btn" onclick="changeTreatmentPage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-numbers" id="treatment-pagination-numbers"></div>
                <button class="pagination-btn" id="treatment-next-page-btn" onclick="changeTreatmentPage(1)" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Pagination state for Treatment History
    let treatmentCurrentPage = 1;
    let treatmentRowsPerPage = 5;
    
    // Detect mobile/tablet and adjust rows per page
    function updateRowsPerPage() {
        if (window.innerWidth <= 1024) {
            // Mobile and tablet: 2 cards per page
            treatmentRowsPerPage = 2;
        } else {
            // Desktop: 5 rows per page
            treatmentRowsPerPage = 5;
        }
    }
    
    // Update on load and resize
    updateRowsPerPage();
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const oldRowsPerPage = treatmentRowsPerPage;
            updateRowsPerPage();
            if (oldRowsPerPage !== treatmentRowsPerPage && typeof getVisibleTreatmentRows === 'function') {
                // Recalculate pagination if rows per page changed
                treatmentCurrentPage = 1;
                const visibleRows = getVisibleTreatmentRows();
                if (typeof updateTreatmentPagination === 'function' && typeof showTreatmentPage === 'function') {
                    updateTreatmentPagination(visibleRows);
                    showTreatmentPage(visibleRows, treatmentCurrentPage);
                }
            }
        }, 250);
    });

    // Notification function
    function showNotification(type, title, message, icon = null, duration = 5000) {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Default icons based on type
        let iconHTML = '';
        if (icon) {
            iconHTML = icon;
        } else {
            switch(type) {
                case 'success':
                    iconHTML = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    iconHTML = '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'warning':
                    iconHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'info':
                    iconHTML = '<i class="fas fa-info-circle"></i>';
                    break;
            }
        }
        
        notification.innerHTML = `
            <div style="flex-shrink: 0; font-size: 24px; color: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : type === 'warning' ? '#F59E0B' : '#3B82F6'};">
                ${iconHTML}
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; margin-bottom: 4px; color: #1F2937;">${title}</div>
                <div style="font-size: 14px; color: #6B7280;">${message}</div>
            </div>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #9CA3AF; font-size: 18px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">&times;</button>
        `;
        
        container.appendChild(notification);
        
        // Auto remove after duration
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease-out';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 400);
        }, duration);
    }
    
    // Utility function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Filter Treatment History
    function filterTreatmentHistory() {
        const searchInput = document.getElementById("filter-treatment-search");
        const searchText = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const clearBtn = document.getElementById("clear-search-btn");
        
        // Show/hide clear button based on input
        if (clearBtn) {
            if (searchText !== "") {
                clearBtn.style.display = "flex";
            } else {
                clearBtn.style.display = "none";
            }
        }
        
        // Update rows per page based on current screen size
        updateRowsPerPage();
        
        // Reset to first page after filtering
        treatmentCurrentPage = 1;
        
        // Get visible rows and update pagination
        const visibleRows = getVisibleTreatmentRows();
        
        // Check if we're on mobile/tablet
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Ensure we have rows before updating
        if (visibleRows.length > 0) {
            if (isMobileOrTablet) {
                // On mobile/tablet: Show all items, hide pagination
                updateTreatmentPagination(visibleRows);
                showTreatmentPage(visibleRows, 1);
            } else {
                // On desktop: Use pagination
                updateTreatmentPagination(visibleRows);
                showTreatmentPage(visibleRows, treatmentCurrentPage);
            }
        } else {
            // Hide all rows if no matches
            const allRows = document.querySelectorAll(".history-row");
            allRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            updateTreatmentPagination([]);
        }
    }
    
    // Clear Treatment Search
    function clearTreatmentSearch() {
        const searchInput = document.getElementById("filter-treatment-search");
        const clearBtn = document.getElementById("clear-search-btn");
        
        searchInput.value = "";
        clearBtn.style.display = "none";
        filterTreatmentHistory(); // Re-filter to show all treatments
        searchInput.focus(); // Focus back on the search input
    }
    
    // Print Treatment History (All)
    function printTreatmentHistory() {
        window.print();
    }
    
    // Print Treatment History by Patient
    function printTreatmentHistoryByPatient(patientId) {
        // Fetch patient information
        Promise.all([
            fetch('../controllers/getPatients.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ patient_id: patientId, first_name: '', last_name: '' })),
            fetch('../controllers/getTreatmentHistory.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ status: 'error', data: [] }))
        ]).then(([patientData, treatmentData]) => {
            const patientName = patientData.first_name && patientData.last_name 
                ? `${patientData.first_name} ${patientData.last_name}` 
                : `Patient ID: ${patientId}`;
            
            // Create print window
            const printWindow = window.open('', '_blank');
            const currentDate = new Date().toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            let htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Treatment History - ${patientName}</title>
                    <style>
                        @media print {
                            @page {
                                margin: 1cm;
                            }
                        }
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 3px solid #333;
                            padding-bottom: 20px;
                            margin-bottom: 30px;
                        }
                        .header h1 {
                            margin: 0;
                            color: #2c3e50;
                            font-size: 24px;
                        }
                        .header h2 {
                            margin: 10px 0;
                            color: #34495e;
                            font-size: 18px;
                            font-weight: normal;
                        }
                        .patient-info {
                            margin-bottom: 30px;
                            padding: 15px;
                            background-color: #f8f9fa;
                            border-left: 4px solid #007bff;
                        }
                        .patient-info p {
                            margin: 5px 0;
                            font-size: 14px;
                        }
                        .patient-info strong {
                            color: #2c3e50;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 20px;
                            font-size: 12px;
                        }
                        th {
                            background-color: #007bff;
                            color: white;
                            padding: 12px;
                            text-align: left;
                            border: 1px solid #ddd;
                        }
                        td {
                            padding: 10px;
                            border: 1px solid #ddd;
                        }
                        tr:nth-child(even) {
                            background-color: #f8f9fa;
                        }
                        .no-data {
                            text-align: center;
                            padding: 40px;
                            color: #999;
                            font-style: italic;
                        }
                        .footer {
                            margin-top: 40px;
                            padding-top: 20px;
                            border-top: 2px solid #ddd;
                            text-align: center;
                            font-size: 11px;
                            color: #666;
                        }
                        .total-cost {
                            margin-top: 20px;
                            text-align: right;
                            font-size: 16px;
                            font-weight: bold;
                            color: #2c3e50;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Landero Dental Clinic</h1>
                        <h2>Patient Treatment History Report</h2>
                    </div>
                    
                    <div class="patient-info">
                        <p><strong>Patient ID:</strong> ${patientId}</p>
                        <p><strong>Patient Name:</strong> ${patientName}</p>
                        <p><strong>Report Date:</strong> ${currentDate}</p>
                    </div>
            `;
            
            if (treatmentData.status === 'success' && treatmentData.data && treatmentData.data.length > 0) {
                htmlContent += `
                    <table>
                        <thead>
                            <tr>
                                <th>Treatment</th>
                                <th>Prescription Given</th>
                                <th>Notes</th>
                                <th>Treatment Cost</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                let totalCost = 0;
                treatmentData.data.forEach(treatment => {
                    const cost = parseFloat(treatment.treatment_cost) || 0;
                    totalCost += cost;
                    htmlContent += `
                        <tr>
                            <td>${treatment.treatment || 'N/A'}</td>
                            <td>${treatment.prescription_given || 'N/A'}</td>
                            <td>${treatment.notes || 'N/A'}</td>
                            <td>₱${cost.toFixed(2)}</td>
                            <td>${treatment.created_at || 'N/A'}</td>
                        </tr>
                    `;
                });
                
                htmlContent += `
                        </tbody>
                    </table>
                    <div class="total-cost">
                        <strong>Total Treatment Cost: ₱${totalCost.toFixed(2)}</strong>
                    </div>
                `;
            } else {
                htmlContent += `
                    <div class="no-data">
                        <p>No treatment history found for this patient.</p>
                    </div>
                `;
            }
            
            htmlContent += `
                    <div class="footer">
                        <p>Generated on ${currentDate}</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(htmlContent);
            printWindow.document.close();
            
            // Wait for content to load, then print
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }).catch(error => {
            console.error('Error generating print document:', error);
            showNotification('error', 'Error', 'Error loading treatment history. Please try again.');
        });
    }
    
    // Update Treatment Pagination
    function updateTreatmentPagination(visibleRows) {
        const totalRows = visibleRows.length;
        const totalPages = Math.ceil(totalRows / treatmentRowsPerPage);
        const paginationContainer = document.getElementById("treatment-pagination-container");
        const paginationInfo = document.getElementById("treatment-pagination-info");
        const paginationNumbers = document.getElementById("treatment-pagination-numbers");
        const prevBtn = document.getElementById("treatment-prev-page-btn");
        const nextBtn = document.getElementById("treatment-next-page-btn");

        // Check if we're on mobile/tablet (hide pagination)
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        if (isMobileOrTablet) {
            // Hide pagination on mobile/tablet
            if (paginationContainer) paginationContainer.style.display = "none";
            return;
        }

        // Hide pagination if no rows
        if (totalRows === 0) {
            if (paginationContainer) paginationContainer.style.display = "none";
            return;
        }

        if (paginationContainer) paginationContainer.style.display = "flex";
        
        // Ensure current page is valid
        if (treatmentCurrentPage > totalPages && totalPages > 0) {
            treatmentCurrentPage = totalPages;
        }
        if (treatmentCurrentPage < 1) {
            treatmentCurrentPage = 1;
        }

        // Update info
        const startRow = (treatmentCurrentPage - 1) * treatmentRowsPerPage + 1;
        const endRow = Math.min(treatmentCurrentPage * treatmentRowsPerPage, totalRows);
        if (paginationInfo) paginationInfo.textContent = `Showing ${startRow}-${endRow} of ${totalRows} records`;

        // Update buttons
        if (prevBtn) prevBtn.disabled = treatmentCurrentPage === 1;
        if (nextBtn) nextBtn.disabled = treatmentCurrentPage >= totalPages;

        // Generate page numbers
        if (paginationNumbers) paginationNumbers.innerHTML = "";
        const maxPagesToShow = 5;
        let startPage = Math.max(1, treatmentCurrentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        // First page and ellipsis
        if (startPage > 1 && paginationNumbers) {
            createTreatmentPageNumber(1, paginationNumbers);
            if (startPage > 2) {
                createTreatmentEllipsis(paginationNumbers);
            }
        }

        // Page numbers
        if (paginationNumbers) {
            for (let i = startPage; i <= endPage; i++) {
                createTreatmentPageNumber(i, paginationNumbers);
            }
        }

        // Last page and ellipsis
        if (endPage < totalPages && paginationNumbers) {
            if (endPage < totalPages - 1) {
                createTreatmentEllipsis(paginationNumbers);
            }
            createTreatmentPageNumber(totalPages, paginationNumbers);
        }
    }

    // Create Treatment page number button
    function createTreatmentPageNumber(pageNum, container) {
        const pageBtn = document.createElement("button");
        pageBtn.className = "pagination-number" + (pageNum === treatmentCurrentPage ? " active" : "");
        pageBtn.textContent = pageNum;
        pageBtn.onclick = () => goToTreatmentPage(pageNum);
        container.appendChild(pageBtn);
    }

    // Create Treatment ellipsis
    function createTreatmentEllipsis(container) {
        const ellipsis = document.createElement("span");
        ellipsis.className = "pagination-number ellipsis";
        ellipsis.textContent = "...";
        container.appendChild(ellipsis);
    }

    // Show Treatment specific page
    function showTreatmentPage(visibleRows, page) {
        // Check if we're on mobile/tablet (no pagination)
        const isMobileOrTablet = window.innerWidth <= 1024;
        
        // Hide all treatment rows first (both table rows and cards)
        const allTreatmentRows = document.querySelectorAll(".history-row");
        
        if (isMobileOrTablet) {
            // On mobile/tablet: Show all visible rows/cards (no pagination)
            allTreatmentRows.forEach(row => {
                // Check if it's a table row or card
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    // It's a card - check if it's in visibleRows
                    row.style.display = "none";
                }
            });
            
            // Show all visible rows/cards
            visibleRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "block";
                }
            });
        } else {
            // On desktop: Use pagination
            allTreatmentRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            
            const startIndex = (page - 1) * treatmentRowsPerPage;
            const endIndex = startIndex + treatmentRowsPerPage;
            const rowsToShow = visibleRows.slice(startIndex, endIndex);
            
            // Show only rows/cards for current page
            rowsToShow.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "block";
                }
            });
        }
    }

    // Get visible Treatment rows based on current filters
    function getVisibleTreatmentRows() {
        const selectedTreatment = document.getElementById("filter-treatment-type").value.toLowerCase();
        const searchInput = document.getElementById("filter-treatment-search");
        const searchText = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const rows = document.querySelectorAll(".history-row");
        const visibleRows = [];
        
        rows.forEach(row => {
            const rowTreatment = row.getAttribute("data-treatment").toLowerCase();
            const rowSearch = row.getAttribute("data-search").toLowerCase();
            
            const matchesTreatment = selectedTreatment === "" || rowTreatment === selectedTreatment;
            const matchesSearch = searchText === "" || rowSearch.includes(searchText);
            
            if (matchesTreatment && matchesSearch) {
                visibleRows.push(row);
            }
        });
        
        return visibleRows;
    }

    // Go to Treatment specific page
    function goToTreatmentPage(page) {
        const visibleRows = getVisibleTreatmentRows();
        if (visibleRows.length === 0) return;

        treatmentCurrentPage = page;
        updateTreatmentPagination(visibleRows);
        showTreatmentPage(visibleRows, treatmentCurrentPage);
    }

    // Change Treatment page (previous/next)
    function changeTreatmentPage(direction) {
        const visibleRows = getVisibleTreatmentRows();
        if (visibleRows.length === 0) return;

        const totalPages = Math.ceil(visibleRows.length / treatmentRowsPerPage);
        const newPage = treatmentCurrentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            goToTreatmentPage(newPage);
        }
    }

    // Initialize pagination on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Update rows per page based on screen size
        updateRowsPerPage();
        
        // Ensure all rows are visible initially before filtering
        const allRows = document.querySelectorAll(".history-row");
        allRows.forEach(row => {
            // Check if it's a table row or card
            if (row.tagName === 'TR') {
                row.style.display = "table-row";
            } else {
                // It's a card
                row.style.display = "block";
            }
        });
        
        // Then apply filters and pagination
        setTimeout(() => {
            filterTreatmentHistory();
        }, 100);
    });

    // Navigate back function
    function navigateBack(event) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
            setTimeout(() => {
                window.location.href = '../views/admin.php';
            }, 300);
        } else {
            window.location.href = '../views/admin.php';
        }
    }
</script>

</body>
</html>
