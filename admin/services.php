<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../views/login.php");
    exit();
}


// Get unique service categories for filter
$categoriesQuery = "SELECT DISTINCT service_category FROM services WHERE service_category IS NOT NULL AND service_category != '' ORDER BY service_category";
$categoriesResult = mysqli_query($con, $categoriesQuery);
$serviceCategories = [];
while ($categoryRow = mysqli_fetch_assoc($categoriesResult)) {
    $serviceCategories[] = $categoryRow['service_category'];
}

// Get all services
$servicesSql = "SELECT service_id, service_category, sub_service, description, price FROM services ORDER BY service_category, service_id";
$servicesResult = mysqli_query($con, $servicesSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="serviceDesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        /* Notification System Styles (toast) */
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

        .notification.hide {
            animation: slideOutRight 0.3s ease-out forwards;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
            background: #E5E7EB;
            color: #6B7280;
        }

        .notification.success .notification-icon {
            background: #D1FAE5;
            color: #10B981;
        }

        .notification.warning .notification-icon {
            background: #FEF3C7;
            color: #F59E0B;
        }

        .notification.error .notification-icon {
            background: #FEE2E2;
            color: #EF4444;
        }

        .notification.info .notification-icon {
            background: #DBEAFE;
            color: #3B82F6;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 16px;
            margin: 0 0 4px 0;
            color: #111827;
        }

        .notification-message {
            font-size: 14px;
            color: #6B7280;
            margin: 0;
        }

        .notification-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: transparent;
            border: none;
            font-size: 20px;
            color: #9CA3AF;
            cursor: pointer;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .notification-close:hover {
            background: #F3F4F6;
            color: #374151;
        }

        .notification-progress {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 3px;
        }

        .notification-progress-bar {
            height: 100%;
            width: 100%;
            background: currentColor;
            transform-origin: left;
            transform: scaleX(1);
            animation-name: notificationProgress;
            animation-timing-function: linear;
            animation-fill-mode: forwards;
        }

        @keyframes notificationProgress {
            from {
                transform: scaleX(1);
            }
            to {
                transform: scaleX(0);
            }
        }

        /* Clean, organized table layout (patients-style), scoped to this page */
        #services-table {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            table-layout: auto;
        }

        #services-table thead th {
            background: linear-gradient(135deg, #48A6A7, #2a9d8f);
            color: #fff;
            border: none !important;
            border-bottom: none !important;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
            font-size: 0.85em;
            padding: 14px 12px;
        }

        #services-table th,
        #services-table td {
            border: none !important;
            border-bottom: none !important;
        }

        #services-table tbody td {
            padding: 10px 12px;
            font-size: 0.90em;
            font-weight: 600;
            vertical-align: top;
        }

        #services-table tbody tr:nth-child(odd) {
            background-color: #fff;
        }

        #services-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        #services-table tbody tr:hover {
            background-color: rgba(233, 196, 106, 0.14);
        }

        /* Alignment: Service ID + Actions columns */
        #services-table th:first-child,
        #services-table td:first-child {
            text-align: center;
            white-space: nowrap;
        }

        #services-table th:last-child,
        #services-table td:last-child {
            text-align: center;
            white-space: nowrap;
        }

        /* Description cell: readable wrapping for long text */
        #services-table th:nth-child(4),
        #services-table td:nth-child(4) {
            max-width: 420px;
            width: 420px;
        }

        #services-table td:nth-child(4) {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            line-height: 1.55;
            padding-top: 14px;
            padding-bottom: 14px;
            vertical-align: top;
        }

        /* Action buttons: compact icon buttons */
        #services-table .action-btns {
            justify-content: center;
        }

        #services-table .action-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
        }

        #services-table .action-btn i {
            font-size: 14px;
        }

        #services-table .action-btn:hover {
            filter: brightness(1.08);
        }

        @media (max-width: 900px) {
            #services-table thead th,
            #services-table tbody td {
                padding: 10px 10px;
            }

            #services-table th:nth-child(4),
            #services-table td:nth-child(4) {
                max-width: none;
                width: auto;
            }

            #services-table td:nth-child(4) {
                padding-top: 10px;
                padding-bottom: 10px;
                line-height: 1.45;
            }
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fas fa-procedures"></i> SERVICES</h2>

        <div class="filter-container">
            <div class="filter-group">
                <label for="filter-service-category"><i class="fas fa-filter"></i> Category:</label>
                <select id="filter-service-category" onchange="filterServices()">
                    <option value="">All Categories</option>
                    <?php foreach ($serviceCategories as $category): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($category)); ?>"><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button class="btn btn-primary" id="openAddServiceBtn">ADD NEW SERVICE</button>
        </div>

        <div class="table-responsive">
            <table id="services-table">
                <thead>
                    <tr>
                        <th>Service ID</th>
                        <th>Service Category</th>
                        <th>Sub Service</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($servicesResult) > 0) {
                        while ($row = mysqli_fetch_assoc($servicesResult)) { 
                    ?>
                        <tr class="service-row" data-category="<?php echo htmlspecialchars(strtolower($row['service_category'])); ?>">
                            <td><?php echo htmlspecialchars($row['service_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['service_category']); ?></td>
                            <td><?php echo htmlspecialchars($row['sub_service']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td>₱<?php echo number_format($row['price'], 2); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn btn-primary" title="Edit" onclick="editServicebtn('<?php echo $row['service_id']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <form action="../controllers/deleteService.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="service_id" value="<?php echo $row['service_id']; ?>">
                                        <button type="submit" class="action-btn btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this service?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                        <tr>
                            <td colspan="6" class="no-data">
                                <i class="fas fa-exclamation-circle fa-2x"></i>
                                <p>No services found</p>
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
            mysqli_data_seek($servicesResult, 0);
            if(mysqli_num_rows($servicesResult) > 0) {
                while ($row = mysqli_fetch_assoc($servicesResult)) { 
            ?>
                <div class="service-card service-row" data-category="<?php echo htmlspecialchars(strtolower($row['service_category'])); ?>">
                    <div class="service-card-header">
                        <div>
                            <div class="service-card-id">Service #<?php echo htmlspecialchars($row['service_id']); ?></div>
                            <div class="service-card-category"><?php echo htmlspecialchars($row['service_category']); ?></div>
                        </div>
                    </div>
                    <div class="service-card-body">
                        <div class="service-card-field">
                            <div class="service-card-label">Sub Service</div>
                            <div class="service-card-value"><?php echo htmlspecialchars($row['sub_service']); ?></div>
                        </div>
                        <div class="service-card-field">
                            <div class="service-card-label">Description</div>
                            <div class="service-card-value"><?php echo htmlspecialchars($row['description']); ?></div>
                        </div>
                        <div class="service-card-field">
                            <div class="service-card-label">Price</div>
                            <div class="service-card-value price">₱<?php echo number_format($row['price'], 2); ?></div>
                        </div>
                    </div>
                    <div class="service-card-actions">
                        <button class="action-btn btn-primary" title="Edit" onclick="editServicebtn('<?php echo $row['service_id']; ?>')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form action="../controllers/deleteService.php" method="POST" style="display:inline;">
                            <input type="hidden" name="service_id" value="<?php echo $row['service_id']; ?>">
                            <button type="submit" class="action-btn btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this service?')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                    <p>No services found</p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Pagination Controls for Services -->
        <div class="pagination-container" id="services-pagination-container">
            <div class="pagination-info" id="services-pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="services-prev-page-btn" onclick="changeServicesPage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-numbers" id="services-pagination-numbers"></div>
                <button class="pagination-btn" id="services-next-page-btn" onclick="changeServicesPage(1)" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Service Modal -->
<div id="addServiceModal" class="modal-overlay" style="display: none;">
    <div class="modal-panel">
        <button class="modal-close" onclick="closeAddModal()" aria-label="Close add service dialog">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-heading">
            <span class="modal-badge">New Service</span>
            <h3>Add a service offering</h3>
            <p>Capture fresh treatments or pricing updates with clarity.</p>
        </div>
        <form action="../controllers/addServices.php" method="POST" class="modal-form" id="addServiceForm">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label" for="service_category">Service Category <span class="required">*</span></label>
                    <select name="service_category" id="service_category" required class="form-control">
                        <option value="" disabled selected>Select a category</option>
                        <option value="General Dentistry">General Dentistry</option>
                        <option value="Orthodontics">Orthodontics</option>
                        <option value="Oral Surgery">Oral Surgery</option>
                        <option value="Endodontics">Endodontics</option>
                        <option value="Prosthodontics Treatments (Pustiso)">Prosthodontics Treatments (Pustiso)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sub_service">Sub Service</label>
                    <input type="text" name="sub_service" id="sub_service" class="form-control" placeholder="e.g., Teeth whitening, Root canal treatment">
                </div>

                <div class="form-group">
                    <label class="form-label" for="price">Price (₱) <span class="required">*</span></label>
                    <div class="input-with-unit">
                        <span class="currency-symbol">₱</span>
                        <input type="number" name="price" id="price" step="0.01" min="0" required class="form-control" placeholder="0.00">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="description">Description <span class="required">*</span></label>
                    <textarea name="description" id="description" required class="form-control" rows="2" placeholder="Describe the service in detail"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-success btn-wide">
                    <i class="fas fa-plus-circle"></i> Add Service
                </button>
                <button type="button" onclick="closeAddModal()" class="btn btn-link">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Service Modal -->
<div id="editServiceModal" class="modal-overlay" style="display:none;">
    <div class="modal-panel">
        <button class="modal-close" onclick="closeEditModal()" aria-label="Close edit service dialog">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-heading">
            <span class="modal-badge accent">Edit service</span>
            <h3>Fine-tune an existing service</h3>
            <p>Adjust description or pricing without losing context.</p>
        </div>
        <form id="editServiceForm" method="POST" action="../controllers/updateService.php" class="modal-form">
            <input type="hidden" name="service_id" id="editServiceId">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label" for="editServiceCategory">Service Category <span class="required">*</span></label>
                    <select name="service_category" id="editServiceCategory" required class="form-control">
                        <option value="" disabled>Select a category</option>
                        <option value="General Dentistry">General Dentistry</option>
                        <option value="Orthodontics">Orthodontics</option>
                        <option value="Oral Surgery">Oral Surgery</option>
                        <option value="Endodontics">Endodontics</option>
                        <option value="Prosthodontics Treatments (Pustiso)">Prosthodontics Treatments (Pustiso)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editSubService">Sub Service</label>
                    <input type="text" name="sub_service" id="editSubService" class="form-control" placeholder="e.g., Teeth whitening, Root canal treatment">
                </div>

                <div class="form-group">
                    <label class="form-label" for="editPrice">Price (₱) <span class="required">*</span></label>
                    <div class="input-with-unit">
                        <span class="currency-symbol">₱</span>
                        <input type="number" name="price" id="editPrice" step="0.01" min="0" required class="form-control" placeholder="0.00">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="editDescription">Description <span class="required">*</span></label>
                    <textarea name="description" id="editDescription" required class="form-control" rows="2" placeholder="Describe the service in detail"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-success btn-wide">
                    <i class="fas fa-pencil-alt"></i> Update Service
                </button>
                <button type="button" onclick="closeEditModal()" class="btn btn-link">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Notification Functions
    function showNotification(type, title, message, icon = null, duration = 5000) {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        let iconHTML = '';
        if (icon) {
            iconHTML = icon;
        } else {
            switch(type) {
                case 'success':
                    iconHTML = '<i class="fas fa-check"></i>';
                    break;
                case 'warning':
                    iconHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'error':
                    iconHTML = '<i class="fas fa-times-circle"></i>';
                    break;
                case 'info':
                    iconHTML = '<i class="fas fa-info-circle"></i>';
                    break;
            }
        }
        
        notification.innerHTML = `
            <div class="notification-icon">
                ${iconHTML}
            </div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">&times;</button>
            <div class="notification-progress">
                <div class="notification-progress-bar" style="color: ${getNotificationColor(type)}; animation-duration: ${duration}ms;"></div>
            </div>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            closeNotification(notification.querySelector('.notification-close'));
        }, duration);
    }
    
    function getNotificationColor(type) {
        const colors = {
            'success': '#10B981',
            'warning': '#F59E0B',
            'error': '#EF4444',
            'info': '#3B82F6'
        };
        return colors[type] || colors.info;
    }
    
    function closeNotification(btn) {
        const notification = btn.closest('.notification');
        if (notification) {
            notification.classList.add('hide');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    }

    // Navigate back to admin with fade animation
    function navigateBack(event) {
        event.preventDefault();
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.classList.add('page-fade-out');
        }
        setTimeout(() => {
            window.location.href = '../views/admin.php';
        }, 300);
    }

    // Pagination state for Services
    let servicesCurrentPage = 1;
    const servicesRowsPerPage = 5;

    // ==================== SERVICE FILTERING ====================
    
    // Filter Services
    function filterServices() {
        // Reset to first page after filtering
        servicesCurrentPage = 1;
        
        // Get visible rows and update pagination
        const visibleRows = getVisibleServicesRows();
        updateServicesPagination(visibleRows);
        showServicesPage(visibleRows, servicesCurrentPage);
    }
    
    // Print Services
    function printServices() {
        window.print();
    }
    
    // Update Services Pagination
    function updateServicesPagination(visibleRows) {
        // Filter rows based on current view (mobile cards on mobile, table rows on desktop)
        const isMobileView = window.innerWidth <= 768;
        const filteredRows = visibleRows ? visibleRows.filter(row => {
            if (isMobileView) {
                return row.classList.contains('service-card');
            } else {
                return row.tagName === 'TR';
            }
        }) : [];
        
        const totalRows = filteredRows.length;
        const totalPages = Math.ceil(totalRows / servicesRowsPerPage);
        const paginationContainer = document.getElementById("services-pagination-container");
        const paginationInfo = document.getElementById("services-pagination-info");
        const paginationNumbers = document.getElementById("services-pagination-numbers");
        const prevBtn = document.getElementById("services-prev-page-btn");
        const nextBtn = document.getElementById("services-next-page-btn");

        // Hide pagination if no rows
        if (totalRows === 0) {
            if (paginationContainer) paginationContainer.style.display = "none";
            return;
        }

        if (paginationContainer) paginationContainer.style.display = "flex";

        // Update info
        const startRow = (servicesCurrentPage - 1) * servicesRowsPerPage + 1;
        const endRow = Math.min(servicesCurrentPage * servicesRowsPerPage, totalRows);
        if (paginationInfo) paginationInfo.textContent = `Showing ${startRow}-${endRow} of ${totalRows} services`;

        // Responsive: Show fewer page numbers on smaller screens
        const isMobile = window.innerWidth <= 768;
        const isSmallMobile = window.innerWidth <= 480;
        const maxPagesToShow = isSmallMobile ? 3 : isMobile ? 4 : 5;

        // Update buttons
        if (prevBtn) prevBtn.disabled = servicesCurrentPage === 1;
        if (nextBtn) nextBtn.disabled = servicesCurrentPage >= totalPages;

        // Generate page numbers
        if (paginationNumbers) paginationNumbers.innerHTML = "";
        let startPage = Math.max(1, servicesCurrentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        // First page and ellipsis
        if (startPage > 1 && paginationNumbers) {
            createServicesPageNumber(1, paginationNumbers);
            if (startPage > 2) {
                createServicesEllipsis(paginationNumbers);
            }
        }

        // Page numbers
        if (paginationNumbers) {
            for (let i = startPage; i <= endPage; i++) {
                createServicesPageNumber(i, paginationNumbers);
            }
        }

        // Last page and ellipsis
        if (endPage < totalPages && paginationNumbers) {
            if (endPage < totalPages - 1) {
                createServicesEllipsis(paginationNumbers);
            }
            createServicesPageNumber(totalPages, paginationNumbers);
        }
    }

    // Create Services page number button
    function createServicesPageNumber(pageNum, container) {
        const pageBtn = document.createElement("button");
        pageBtn.className = "pagination-number" + (pageNum === servicesCurrentPage ? " active" : "");
        pageBtn.textContent = pageNum;
        pageBtn.onclick = () => goToServicesPage(pageNum);
        container.appendChild(pageBtn);
    }

    // Create Services ellipsis
    function createServicesEllipsis(container) {
        const ellipsis = document.createElement("span");
        ellipsis.className = "pagination-number ellipsis";
        ellipsis.textContent = "...";
        container.appendChild(ellipsis);
    }

    // Show Services specific page
    function showServicesPage(visibleRows, page) {
        if (!visibleRows || visibleRows.length === 0) {
            // Hide all rows if no visible rows
            document.querySelectorAll(".service-row").forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else if (row.classList.contains('service-card')) {
                    row.style.display = "none";
                }
            });
            return;
        }
        
        // Check if we're on mobile (table is hidden, mobile cards are shown)
        const isMobile = window.innerWidth <= 768;
        
        // Filter visibleRows to only include elements for current view
        const filteredRows = visibleRows.filter(row => {
            if (isMobile) {
                // On mobile: only show mobile cards
                return row.classList.contains('service-card');
            } else {
                // On desktop: only show table rows
                return row.tagName === 'TR';
            }
        });
        
        const startIndex = (page - 1) * servicesRowsPerPage;
        const endIndex = startIndex + servicesRowsPerPage;
        const rowsToShow = filteredRows.slice(startIndex, endIndex);

        // Hide all service rows first
        document.querySelectorAll(".service-row").forEach(row => {
            if (row.tagName === 'TR') {
                row.style.display = "none";
            } else if (row.classList.contains('service-card')) {
                row.style.display = "none";
            }
        });

        // Show only rows for current page
        rowsToShow.forEach(row => {
            if (row.tagName === 'TR') {
                // Table row: show on desktop
                row.style.display = "table-row";
            } else if (row.classList.contains('service-card')) {
                // Mobile card: show on mobile
                row.style.display = "block";
            }
        });
    }

    // Get visible Services rows based on current filters
    function getVisibleServicesRows() {
        const selectedCategory = document.getElementById("filter-service-category").value.toLowerCase();
        const rows = document.querySelectorAll(".service-row");
        const visibleRows = [];
        
        rows.forEach(row => {
            const rowCategory = row.getAttribute("data-category").toLowerCase();
            const matchesCategory = selectedCategory === "" || rowCategory === selectedCategory;
            if (matchesCategory) {
                visibleRows.push(row);
            }
        });
        
        return visibleRows;
    }

    // Go to Services specific page
    function goToServicesPage(page) {
        const visibleRows = getVisibleServicesRows();
        if (visibleRows.length === 0) return;

        servicesCurrentPage = page;
        updateServicesPagination(visibleRows);
        showServicesPage(visibleRows, servicesCurrentPage);
    }

    // Change Services page (previous/next)
    function changeServicesPage(direction) {
        const visibleRows = getVisibleServicesRows();
        if (visibleRows.length === 0) return;

        const totalPages = Math.ceil(visibleRows.length / servicesRowsPerPage);
        const newPage = servicesCurrentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            goToServicesPage(newPage);
        }
    }
    
    // ==================== END SERVICE FILTERING ====================

    // Modal functions
    function closeAddModal() {
        const modal = document.getElementById('addServiceModal');
        const form = document.getElementById('addServiceForm');
        if (form) {
            form.reset();
            // Clear any validation states
            form.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('is-invalid', 'is-valid');
            });
        }
        modal.style.display = 'none';
    }

    function closeEditModal() {
        const modal = document.getElementById('editServiceModal');
        const form = document.getElementById('editServiceForm');
        if (form) {
            // Clear any validation states
            form.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('is-invalid', 'is-valid');
            });
        }
        modal.style.display = 'none';
    }

    function editServicebtn(serviceId) {
        const modal = document.getElementById('editServiceModal');
        modal.style.display = 'flex';
        
        fetch('../controllers/getServices.php?id=' + encodeURIComponent(serviceId))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Fill modal fields
                document.getElementById('editServiceId').value = data.service_id;
                
                // Set category - handle both old and new category names
                const categorySelect = document.getElementById('editServiceCategory');
                const categoryValue = data.service_category || '';
                categorySelect.value = categoryValue;
                
                // If category doesn't match, try to find a close match
                if (!categorySelect.value && categoryValue) {
                    const options = Array.from(categorySelect.options);
                    const match = options.find(opt => 
                        opt.value.toLowerCase().includes(categoryValue.toLowerCase()) ||
                        categoryValue.toLowerCase().includes(opt.value.toLowerCase())
                    );
                    if (match) {
                        categorySelect.value = match.value;
                    }
                }
                
                document.getElementById('editSubService').value = data.sub_service || '';
                document.getElementById('editDescription').value = data.description || '';
                
                // Format price to 2 decimal places
                const price = parseFloat(data.price) || 0;
                document.getElementById('editPrice').value = price.toFixed(2);
            })
            .catch(error => {
                console.error('Error fetching service:', error);
                showNotification('error', 'Error Loading Service', error.message || 'Failed to load service details.');
            });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function () {
        // Add Service Modal
        const openServiceBtn = document.getElementById('openAddServiceBtn');
        const serviceModal = document.getElementById('addServiceModal');
        
        if (openServiceBtn) {
            openServiceBtn.addEventListener('click', function () {
                serviceModal.style.display = 'flex';
                // Focus on first input for better UX
                setTimeout(() => {
                    const firstInput = serviceModal.querySelector('#service_category');
                    if (firstInput) firstInput.focus();
                }, 100);
            });
        }

        // Close modals when clicking outside
        window.addEventListener('click', function (event) {
            if (event.target === serviceModal) {
                closeAddModal();
            }
            const editServiceModal = document.getElementById('editServiceModal');
            if (editServiceModal && event.target === editServiceModal) {
                closeEditModal();
            }
        });

        // Add form validation feedback
        const addForm = document.getElementById('addServiceForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                // Basic validation
                const price = document.getElementById('price');
                if (price && parseFloat(price.value) < 0) {
                    e.preventDefault();
                    showNotification('error', 'Invalid Price', 'Price cannot be negative.');
                    price.focus();
                    return false;
                }
            });

            // Real-time validation feedback
            addForm.querySelectorAll('.form-control').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    } else if (this.value.trim()) {
                        this.classList.add('is-valid');
                        this.classList.remove('is-invalid');
                    }
                });
            });
        }

        const editForm = document.getElementById('editServiceForm');
        if (editForm) {
            // Helpers for real-time UI updates (no full page reload).
            const formatPrice = (value) => {
                const num = Number(value);
                if (Number.isNaN(num)) return '0.00';
                return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            const findDesktopRowById = (serviceId) => {
                const rows = document.querySelectorAll('tr.service-row');
                for (const row of rows) {
                    const idCell = row.querySelector('td');
                    if (idCell && idCell.textContent.trim() === serviceId) return row;
                }
                return null;
            };

            const findMobileCardById = (serviceId) => {
                const cards = document.querySelectorAll('.service-card.service-row');
                for (const card of cards) {
                    const idEl = card.querySelector('.service-card-id');
                    if (idEl && idEl.textContent.includes(`#${serviceId}`)) return card;
                }
                return null;
            };

            const updateServiceUIFromData = (service) => {
                if (!service || !service.service_id) return;

                const serviceId = String(service.service_id);
                const category = service.service_category || '';
                const subService = service.sub_service || '';
                const description = service.description || '';
                const price = service.price ?? 0;

                // Desktop row
                const desktopRow = findDesktopRowById(serviceId);
                if (desktopRow) {
                    desktopRow.dataset.category = (category || '').toLowerCase();
                    const tds = desktopRow.querySelectorAll('td');
                    if (tds[0]) tds[0].textContent = serviceId;
                    if (tds[1]) tds[1].textContent = category;
                    if (tds[2]) tds[2].textContent = subService;
                    if (tds[3]) tds[3].textContent = description;
                    if (tds[4]) tds[4].textContent = `₱${formatPrice(price)}`;
                }

                // Mobile card
                const mobileCard = findMobileCardById(serviceId);
                if (mobileCard) {
                    mobileCard.dataset.category = (category || '').toLowerCase();
                    const idEl = mobileCard.querySelector('.service-card-id');
                    if (idEl) idEl.textContent = `Service #${serviceId}`;

                    const catEl = mobileCard.querySelector('.service-card-category');
                    if (catEl) catEl.textContent = category;

                    const fields = mobileCard.querySelectorAll('.service-card-field');
                    fields.forEach((field) => {
                        const label = field.querySelector('.service-card-label')?.textContent?.trim();
                        const valueEl = field.querySelector('.service-card-value');
                        if (!label || !valueEl) return;
                        if (label === 'Sub Service') valueEl.textContent = subService;
                        if (label === 'Description') valueEl.textContent = description;
                        if (label === 'Price') valueEl.textContent = `₱${formatPrice(price)}`;
                    });
                }
            };

            const fetchServiceDetails = async (serviceId) => {
                const res = await fetch(`../controllers/getServices.php?id=${encodeURIComponent(serviceId)}`, { method: 'GET' });
                if (!res.ok) throw new Error('Failed to fetch updated service.');
                return res.json();
            };

            const refreshServicesTablePreservePage = () => {
                const visibleRows = getVisibleServicesRows();
                const isMobileView = window.innerWidth <= 768;
                const filteredRows = visibleRows
                    ? visibleRows.filter(row => (isMobileView ? row.classList.contains('service-card') : row.tagName === 'TR'))
                    : [];

                const totalPages = Math.max(1, Math.ceil(filteredRows.length / servicesRowsPerPage));
                if (servicesCurrentPage > totalPages) servicesCurrentPage = totalPages;

                updateServicesPagination(visibleRows);
                showServicesPage(visibleRows, servicesCurrentPage);
            };

            // Intercept Update Service submit to avoid server redirect and update UI instantly.
            editForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const priceEl = document.getElementById('editPrice');
                if (priceEl && parseFloat(priceEl.value) < 0) {
                    showNotification('error', 'Invalid Price', 'Price cannot be negative.');
                    priceEl.focus();
                    return false;
                }

                const submitBtn = editForm.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : null;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                }

                const formData = new FormData(editForm);
                const serviceId = document.getElementById('editServiceId')?.value;

                try {
                    const res = await fetch(editForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const text = await res.text().catch(() => '');
                    const ok = text.toLowerCase().includes('service updated successfully');

                    if (!ok) {
                        showNotification('error', 'Update Failed', 'Error updating service. Please try again.');
                        return false;
                    }

                    showNotification('success', 'Service Updated', 'Service updated successfully.');
                    closeEditModal();

                    // Pull the canonical updated values and update the current UI row/card.
                    if (serviceId) {
                        const updated = await fetchServiceDetails(serviceId).catch(() => null);
                        if (updated && !updated.error) {
                            updateServiceUIFromData(updated);
                        }
                    }

                    refreshServicesTablePreservePage();
                    return true;
                } catch (err) {
                    console.error(err);
                    showNotification('error', 'Update Error', 'Error updating service. Please try again.');
                    return false;
                } finally {
                    if (submitBtn && originalText !== null) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            });

            // Real-time validation feedback
            editForm.querySelectorAll('.form-control').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    } else if (this.value.trim()) {
                        this.classList.add('is-valid');
                        this.classList.remove('is-invalid');
                    }
                });
            });
        }

        // Intercept Delete service forms to update UI instantly (no reload).
        const findDesktopRowById = (serviceId) => {
            const rows = document.querySelectorAll('tr.service-row');
            for (const row of rows) {
                const idCell = row.querySelector('td');
                if (idCell && idCell.textContent.trim() === serviceId) return row;
            }
            return null;
        };

        const findMobileCardById = (serviceId) => {
            const cards = document.querySelectorAll('.service-card.service-row');
            for (const card of cards) {
                const idEl = card.querySelector('.service-card-id');
                if (idEl && idEl.textContent.includes(`#${serviceId}`)) return card;
            }
            return null;
        };

        const refreshServicesTablePreservePage = () => {
            const visibleRows = getVisibleServicesRows();
            const isMobileView = window.innerWidth <= 768;
            const filteredRows = visibleRows
                ? visibleRows.filter(row => (isMobileView ? row.classList.contains('service-card') : row.tagName === 'TR'))
                : [];

            const totalPages = Math.max(1, Math.ceil(filteredRows.length / servicesRowsPerPage));
            if (servicesCurrentPage > totalPages) servicesCurrentPage = totalPages;

            updateServicesPagination(visibleRows);
            showServicesPage(visibleRows, servicesCurrentPage);
        };

        const deleteForms = document.querySelectorAll('form[action="../controllers/deleteService.php"]');
        deleteForms.forEach((form) => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const serviceId = form.querySelector('input[name="service_id"]')?.value;
                if (!serviceId) {
                    showNotification('error', 'Delete Failed', 'No service ID provided.');
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : null;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                }

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const text = await res.text().catch(() => '');
                    const ok = text.toLowerCase().includes('service deleted successfully');

                    if (!ok) {
                        showNotification('error', 'Delete Failed', 'Error deleting service. Please try again.');
                        return;
                    }

                    showNotification('success', 'Service Deleted', 'Service deleted successfully.');

                    // Remove from DOM: both desktop row and mobile card.
                    const desktopRow = findDesktopRowById(String(serviceId));
                    if (desktopRow) desktopRow.remove();

                    const mobileCard = findMobileCardById(String(serviceId));
                    if (mobileCard) mobileCard.remove();

                    refreshServicesTablePreservePage();
                } catch (err) {
                    console.error(err);
                    showNotification('error', 'Delete Error', 'Error deleting service. Please try again.');
                } finally {
                    if (submitBtn && originalText !== null) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            });
        });

        // Initialize pagination
        setTimeout(() => {
            filterServices();
        }, 100);
        
        // Update pagination on window resize for responsive behavior
        let resizeTimeout;
        let lastWidth = window.innerWidth;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                const currentWidth = window.innerWidth;
                const wasMobile = lastWidth <= 768;
                const isMobile = currentWidth <= 768;
                
                // If switching between mobile and desktop, refresh pagination
                if (wasMobile !== isMobile) {
                    const visibleRows = getVisibleServicesRows();
                    if (visibleRows.length > 0) {
                        // Reset to page 1 when switching views
                        servicesCurrentPage = 1;
                        updateServicesPagination(visibleRows);
                        showServicesPage(visibleRows, servicesCurrentPage);
                    }
                } else {
                    // Just update pagination display
                    const visibleRows = getVisibleServicesRows();
                    if (visibleRows.length > 0) {
                        updateServicesPagination(visibleRows);
                        showServicesPage(visibleRows, servicesCurrentPage);
                    }
                }
                lastWidth = currentWidth;
            }, 250);
        });
    });
</script>
</body>
</html>

