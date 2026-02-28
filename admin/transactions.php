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

// Get payment transactions data
$paymentSql = "SELECT p.payment_id, p.appointment_id, p.method, p.account_name, 
                      p.account_number, p.amount, p.reference_no, p.proof_image, p.status,
                      a.patient_id, a.appointment_date
               FROM payment p
               LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
               ORDER BY a.appointment_date DESC, p.payment_id DESC";
$paymentResult = mysqli_query($con, $paymentSql);

// Get unique payment methods for filter
$methodsQuery = "SELECT DISTINCT method FROM payment WHERE method IS NOT NULL AND method != '' ORDER BY method";
$methodsResult = mysqli_query($con, $methodsQuery);
$paymentMethods = [];
while ($methodRow = mysqli_fetch_assoc($methodsResult)) {
    $paymentMethods[] = $methodRow['method'];
}

// Get unique payment statuses for filter
$statusQuery = "SELECT DISTINCT status FROM payment WHERE status IS NOT NULL AND status != '' ORDER BY 
                CASE status 
                    WHEN 'pending' THEN 1 
                    WHEN 'paid' THEN 2 
                    WHEN 'failed' THEN 3 
                    WHEN 'refunded' THEN 4 
                    ELSE 5 
                END";
$statusResult = mysqli_query($con, $statusQuery);
$paymentStatuses = [];
while ($statusRow = mysqli_fetch_assoc($statusResult)) {
    $paymentStatuses[] = $statusRow['status'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Transactions - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="transactionsDesign.css">
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

        .btn-primary-confirmedPayment {
            background: var(--success);
            color: var(--white);
        }

        .btn-primary-confirmedPayment:hover {
            background: #27ae60;
        }
    </style>
</head>
<body>

<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- Transactions Section -->
<div class="main-content">
    <div class="container">
        <a href="../views/admin.php" class="back-button" onclick="navigateBack(event)">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
        <h2><i class="fa-solid fa-money-bill"></i> PAYMENT TRANSACTIONS</h2>

        <div class="filter-container">
            <div class="filter-group">
                <label for="filter-payment-date-category"><i class="fas fa-calendar-day"></i> Date Category:</label>
                <select id="filter-payment-date-category" onchange="handlePaymentDateCategoryChange()">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="custom">Custom Date</option>
                </select>
                <input type="date" id="filter-payment-date" onchange="filterPayments()" style="display:none; margin-left:10px;">
            </div>
            
            <div class="filter-group">
                <label for="filter-payment-status"><i class="fas fa-filter"></i> Status Category:</label>
                <select id="filter-payment-status" onchange="filterPayments()">
                    <option value="">All Status</option>
                    <?php foreach ($paymentStatuses as $status): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($status)); ?>">
                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-payment-method"><i class="fas fa-credit-card"></i> Payment Method:</label>
                <select id="filter-payment-method" onchange="filterPayments()">
                    <option value="">All Methods</option>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($method)); ?>"><?php echo htmlspecialchars($method); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button class="btn btn-accent" onclick="printPayments()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <div class="table-responsive">
            <table id="payment-table">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Appointment ID</th>
                        <th>Method</th>
                        <th>Account Name</th>
                        <th>Account Number</th>
                        <th>Amount</th>
                        <th>Reference No.</th>
                        <th>Proof</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($paymentResult) > 0) {
                        mysqli_data_seek($paymentResult, 0);
                        while ($row = mysqli_fetch_assoc($paymentResult)) { 
                            $appointmentDate = $row['appointment_date'] ?? '';
                            $paymentStatus = strtolower($row['status'] ?? '');
                            $paymentMethod = strtolower($row['method'] ?? '');
                            $searchText = strtolower($row['payment_id'] . ' ' . $row['appointment_id'] . ' ' . $paymentMethod . ' ' . ($row['account_name'] ?? '') . ' ' . ($row['reference_no'] ?? ''));
                    ?>
                        <tr class="payment-row" 
                            data-date="<?php echo htmlspecialchars($appointmentDate); ?>" 
                            data-status="<?php echo htmlspecialchars($paymentStatus); ?>"
                            data-method="<?php echo htmlspecialchars($paymentMethod); ?>"
                            data-search="<?php echo htmlspecialchars($searchText); ?>">
                            <td><?php echo htmlspecialchars($row['payment_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['appointment_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['method']); ?></td>
                            <td>
                                <?php 
                                $paymentMethodDisplay = strtolower(trim($row['method'] ?? ''));
                                if ($paymentMethodDisplay === 'cash') {
                                    echo 'N/A';
                                } else {
                                    echo htmlspecialchars($row['account_name'] ?? '');
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($paymentMethodDisplay === 'cash') {
                                    echo 'N/A';
                                } else {
                                    echo htmlspecialchars($row['account_number'] ?? '');
                                }
                                ?>
                            </td>
                            <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                            <td>
                                <?php 
                                if ($paymentMethodDisplay === 'cash') {
                                    echo 'N/A';
                                } else {
                                    echo htmlspecialchars($row['reference_no'] ?? '');
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($row['proof_image'])): ?>
                                    <?php 
                                    $clean_path = ltrim($row['proof_image'], '/');
                                    $clean_path = str_replace('uploads/', '', $clean_path);
                                    $image_path = '/uploads/' . $clean_path;
                                    ?>
                                    <button type="button" onclick="viewProofPDF('<?php echo htmlspecialchars($row['payment_id']); ?>')" 
                                        class="view-image-btn">
                                        View Image
                                    </button>
                                <?php else: ?>
                                    <span>No Image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status status-<?php echo htmlspecialchars(strtolower($row['status'] ?? 'pending')); ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['status'] ?? 'Pending')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <?php 
                                    $currentStatus = strtolower($row['status'] ?? '');
                                    // Only show confirm button if status is not 'paid' or 'refunded'
                                    if ($currentStatus !== 'paid' && $currentStatus !== 'refunded'): 
                                    ?>
                                    <button type="button" class="action-btn btn-primary-confirmedPayment" title="Confirm"
                                        data-payment-id="<?php echo $row['payment_id']; ?>"
                                        data-payment-amount="<?php echo htmlspecialchars($row['amount']); ?>"
                                        onclick="confirmPayment(this)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Only show failed button if status is not 'failed' or 'refunded'
                                    if ($currentStatus !== 'failed' && $currentStatus !== 'refunded'): 
                                    ?>
                                    <button type="button" class="action-btn btn-danger" title="Mark as failed"
                                        data-payment-id="<?php echo $row['payment_id']; ?>"
                                        onclick="markPaymentFailed(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php 
                                    // Edit Button
                                    if ($currentStatus != 'paid' && $currentStatus != 'failed'): 
                                    ?>
                                    <button type="button" class="action-btn btn-primary" title="Edit Status"
                                        data-payment-id="<?php echo $row['payment_id']; ?>" 
                                        onclick="editPayment(this)">
                                        <i class="fas fa-edit"></i>
                                    </button>   
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                        <tr>
                            <td colspan="10" class="no-data">
                                <i class="fas fa-exclamation-circle fa-2x"></i>
                                <p>No Payment found</p>
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
            mysqli_data_seek($paymentResult, 0);
            if(mysqli_num_rows($paymentResult) > 0) {
                while ($row = mysqli_fetch_assoc($paymentResult)) { 
                    $appointmentDate = $row['appointment_date'] ?? '';
                    $paymentStatus = strtolower($row['status'] ?? '');
                    $paymentMethod = strtolower($row['method'] ?? '');
                    $paymentMethodDisplay = strtolower(trim($row['method'] ?? ''));
                    $searchText = strtolower($row['payment_id'] . ' ' . $row['appointment_id'] . ' ' . $paymentMethod . ' ' . ($row['account_name'] ?? '') . ' ' . ($row['reference_no'] ?? ''));
            ?>
                <div class="payment-card payment-row" 
                     data-date="<?php echo htmlspecialchars($appointmentDate); ?>" 
                     data-status="<?php echo htmlspecialchars($paymentStatus); ?>"
                     data-method="<?php echo htmlspecialchars($paymentMethod); ?>"
                     data-search="<?php echo htmlspecialchars($searchText); ?>">
                    <div class="payment-card-header">
                        <div>
                            <div class="payment-card-id">Payment #<?php echo htmlspecialchars($row['payment_id']); ?></div>
                            <div class="payment-card-appointment">Appointment #<?php echo htmlspecialchars($row['appointment_id']); ?></div>
                        </div>
                        <span class="status status-<?php echo htmlspecialchars(strtolower($row['status'] ?? 'pending')); ?>">
                            <?php echo htmlspecialchars(ucfirst($row['status'] ?? 'Pending')); ?>
                        </span>
                    </div>
                    <div class="payment-card-body">
                        <div class="payment-card-field">
                            <div class="payment-card-label">Method</div>
                            <div class="payment-card-value"><?php echo htmlspecialchars($row['method']); ?></div>
                        </div>
                        <div class="payment-card-field">
                            <div class="payment-card-label">Amount</div>
                            <div class="payment-card-value">₱<?php echo number_format($row['amount'], 2); ?></div>
                        </div>
                        <?php if ($paymentMethodDisplay !== 'cash'): ?>
                        <div class="payment-card-field">
                            <div class="payment-card-label">Account Name</div>
                            <div class="payment-card-value"><?php echo htmlspecialchars($row['account_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="payment-card-field">
                            <div class="payment-card-label">Account Number</div>
                            <div class="payment-card-value"><?php echo htmlspecialchars($row['account_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="payment-card-field">
                            <div class="payment-card-label">Reference No.</div>
                            <div class="payment-card-value"><?php echo htmlspecialchars($row['reference_no'] ?? 'N/A'); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="payment-card-field">
                            <div class="payment-card-label">Proof</div>
                            <div class="payment-card-value">
                                <?php if (!empty($row['proof_image'])): ?>
                                    <?php 
                                    $clean_path = ltrim($row['proof_image'], '/');
                                    $clean_path = str_replace('uploads/', '', $clean_path);
                                    $image_path = '/uploads/' . $clean_path;
                                    ?>
                                    <button type="button" onclick="viewProofPDF('<?php echo htmlspecialchars($row['payment_id']); ?>')" 
                                        class="view-image-btn">
                                        View
                                    </button>
                                <?php else: ?>
                                    <span>No Image</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="payment-card-actions">
                        <?php 
                        $currentStatus = strtolower($row['status'] ?? '');
                        if ($currentStatus !== 'paid' && $currentStatus !== 'refunded'): 
                        ?>
                        <button type="button" class="action-btn btn-primary-confirmedPayment" title="Confirm"
                            data-payment-id="<?php echo $row['payment_id']; ?>"
                            data-payment-amount="<?php echo htmlspecialchars($row['amount']); ?>"
                            onclick="confirmPayment(this)">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                        <?php endif; ?>
                        
                        <?php 
                        if ($currentStatus !== 'failed' && $currentStatus !== 'refunded'): 
                        ?>
                        <button type="button" class="action-btn btn-danger" title="Mark as failed"
                            data-payment-id="<?php echo $row['payment_id']; ?>"
                            onclick="markPaymentFailed(this)">
                            <i class="fas fa-times"></i> Failed
                        </button>
                        <?php endif; ?>

                        <?php 
                        if ($currentStatus != 'paid' && $currentStatus != 'failed'): 
                        ?>
                        <button type="button" class="action-btn btn-primary" title="Edit Status"
                            data-payment-id="<?php echo $row['payment_id']; ?>" 
                            onclick="editPayment(this)">
                            <i class="fas fa-edit"></i> Edit
                        </button>   
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                }
            } else { 
            ?>
                <div class="no-data" style="text-align: center; padding: 30px; color: #6b7280;">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                    <p>No Payment found</p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Pagination Controls -->
        <div class="pagination-container" id="payment-pagination-container">
            <div class="pagination-info" id="payment-pagination-info"></div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="payment-prev-btn" onclick="changePaymentPage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-numbers" id="payment-pagination-numbers"></div>
                <button class="pagination-btn" id="payment-next-btn" onclick="changePaymentPage(1)" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal" style="display:none;">
    <span onclick="closeModal()" class="close-modal">&times;</span>
    <img id="modalImage" src="" alt="Proof Image" class="modal-image">
</div>

<!-- Edit Payment Modal -->
<div id="editPaymentModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3><i class="fa-solid fa-money-bill"></i> EDIT PAYMENT STATUS</h3>
        <form id="editPaymentForm" method="POST" action="../controllers/updatePayment.php">
            <input type="hidden" name="payment_id" id="editPaymentId">

            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="editPaymentStatus">Status:</label>
                    <select name="status" id="editPaymentStatus" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Update Status
                </button>
                <button type="button" onclick="closeEditPaymentModal()" class="modal-close-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Pagination state for Payments
    let paymentsCurrentPage = 1;
    let paymentsRowsPerPage = 5;
    
    // Detect mobile/tablet and adjust rows per page
    function updateRowsPerPage() {
        if (window.innerWidth <= 1024) {
            // Mobile and tablet: 2 cards per page
            paymentsRowsPerPage = 2;
        } else {
            // Desktop: 5 rows per page
            paymentsRowsPerPage = 5;
        }
    }
    
    // Update on load and resize
    updateRowsPerPage();
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const oldRowsPerPage = paymentsRowsPerPage;
            updateRowsPerPage();
            if (oldRowsPerPage !== paymentsRowsPerPage && typeof getVisiblePaymentsRows === 'function') {
                paymentsCurrentPage = 1;
                const visibleRows = getVisiblePaymentsRows();
                if (typeof updatePaymentsPagination === 'function' && typeof showPaymentsPage === 'function') {
                    updatePaymentsPagination(visibleRows);
                    showPaymentsPage(visibleRows, paymentsCurrentPage);
                }
            }
        }, 250);
    });

    // Notification function
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
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease-out';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 400);
        }, duration);
    }

    // Confirm Payment
    function confirmPayment(button) {
        const paymentId = button.getAttribute('data-payment-id');
        const amount = button.getAttribute('data-payment-amount') || '0';
        const formData = new FormData();
        formData.append('payment_id', paymentId);
        
        const originalHTML = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch('../controllers/confirmPayment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                return { success: true };
            }
        })
        .then(data => {
            if (data.success || data.status === 'success' || !data.message) {
                showNotification('success', 'Payment Confirmed', `Payment ID ${paymentId} has been confirmed. Amount: ₱${parseFloat(amount).toFixed(2)}`);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to confirm payment. Please try again.');
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while confirming payment. Please try again.');
            button.disabled = false;
            button.innerHTML = originalHTML;
        });
    }
    
    // Mark Payment as Failed
    function markPaymentFailed(button) {
        const paymentId = button.getAttribute('data-payment-id');
        const formData = new FormData();
        formData.append('payment_id', paymentId);
        
        const originalHTML = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch('../controllers/failedPayment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                return { success: true };
            }
        })
        .then(data => {
            if (data.success || data.status === 'success' || !data.message) {
                showNotification('warning', 'Payment Marked as Failed', `Payment ID ${paymentId} has been marked as failed.`);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to mark payment as failed. Please try again.');
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while marking payment as failed. Please try again.');
            button.disabled = false;
            button.innerHTML = originalHTML;
        });
    }

    // Edit Payment
    function editPayment(button) {
        const paymentId = button.getAttribute('data-payment-id');
        
        fetch('../controllers/getPayment.php?payment_id=' + encodeURIComponent(paymentId))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const payment = data.data;
                    document.getElementById('editPaymentId').value = payment.payment_id;
                    document.getElementById('editPaymentStatus').value = payment.status || 'pending';
                    document.getElementById('editPaymentModal').style.display = 'flex';
                } else {
                    showNotification('error', 'Error Loading Payment', data.message || 'Unknown error occurred.');
                }
            })
            .catch(error => {
                console.error('Error fetching payment:', error);
                showNotification('error', 'Error Loading Payment', error.message || 'Failed to load payment details.');
            });
    }

    function closeEditPaymentModal() {
        document.getElementById('editPaymentModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener("click", function(event) {
        const editModal = document.getElementById("editPaymentModal");
        if (event.target === editModal) {
            closeEditPaymentModal();
        }
    });

    // Handle form submission
    document.getElementById('editPaymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        
        fetch('../controllers/updatePayment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.status === 'success') {
                showNotification('success', 'Payment Updated', 'Payment status has been updated successfully.');
                closeEditPaymentModal();
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to update payment. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while updating payment. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });

    // View Proof Image as PDF
    function viewProofPDF(paymentId) {
        if (!paymentId) {
            alert('Payment ID is missing. Cannot view proof image.');
            return;
        }
        // Open PDF in new tab
        const pdfUrl = '../controllers/viewProofImage.php?payment_id=' + encodeURIComponent(paymentId);
        window.open(pdfUrl, '_blank');
    }

    // View Image (kept for backward compatibility if needed)
    function viewImage(imageSrc) {
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        if (modal && modalImg) {
            modalImg.src = imageSrc;
            modal.style.display = "flex"; 
        }
    }

    function closeModal() {
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        if (modal) {
            modal.style.display = "none";
        }
        if (modalImg) {
            modalImg.src = ""; 
        }
    }

    // Close image modal when clicking outside
    window.addEventListener("click", function(event) {
        const imageModal = document.getElementById("imageModal");
        if (event.target === imageModal) {
            closeModal();
        }
    });

    // Handle Payment Date Category Change
    function handlePaymentDateCategoryChange() {
        const dateCategory = document.getElementById("filter-payment-date-category").value;
        const dateInput = document.getElementById("filter-payment-date");
        
        if (dateCategory === "custom") {
            dateInput.style.display = "inline-block";
            dateInput.value = "";
        } else {
            dateInput.style.display = "none";
            dateInput.value = "";
            filterPayments();
        }
    }

    // Filter Payments
    function filterPayments() {
        const dateCategory = document.getElementById("filter-payment-date-category").value;
        const selectedDate = document.getElementById("filter-payment-date").value;
        const selectedStatus = document.getElementById("filter-payment-status").value.toLowerCase();
        const selectedMethod = document.getElementById("filter-payment-method").value.toLowerCase();
        const rows = document.querySelectorAll(".payment-row");
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayStr = today.toISOString().split('T')[0];
        
        let weekStart = null, weekEnd = null;
        let monthStart = null, monthEnd = null;
        
        if (dateCategory === "week") {
            const dayOfWeek = today.getDay();
            const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
            weekStart = new Date(today);
            weekStart.setDate(today.getDate() - daysToMonday);
            weekStart.setHours(0, 0, 0, 0);
            weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 5);
            weekEnd.setHours(23, 59, 59, 999);
        } else if (dateCategory === "month") {
            monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            monthStart.setHours(0, 0, 0, 0);
            monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            monthEnd.setHours(23, 59, 59, 999);
        }
        
        const visibleRows = [];
        
        rows.forEach(row => {
            const rowDate = row.getAttribute("data-date");
            const rowStatus = row.getAttribute("data-status").toLowerCase();
            const rowMethod = row.getAttribute("data-method").toLowerCase();
            
            let matchesDate = true;
            
            if (dateCategory === "custom" && selectedDate) {
                matchesDate = rowDate === selectedDate;
            } else if (dateCategory === "today") {
                matchesDate = rowDate === todayStr;
            } else if (dateCategory === "week") {
                if (rowDate) {
                    const rowDateObj = new Date(rowDate);
                    rowDateObj.setHours(0, 0, 0, 0);
                    matchesDate = rowDateObj >= weekStart && rowDateObj <= weekEnd;
                } else {
                    matchesDate = false;
                }
            } else if (dateCategory === "month") {
                if (rowDate) {
                    const rowDateObj = new Date(rowDate);
                    rowDateObj.setHours(0, 0, 0, 0);
                    matchesDate = rowDateObj >= monthStart && rowDateObj <= monthEnd;
                } else {
                    matchesDate = false;
                }
            } else if (dateCategory === "") {
                matchesDate = true;
            }
            
            const matchesStatus = selectedStatus === "" || rowStatus === selectedStatus;
            const matchesMethod = selectedMethod === "" || rowMethod === selectedMethod;
            
            if (matchesDate && matchesStatus && matchesMethod) {
                visibleRows.push(row);
            } else {
                row.style.display = "none";
            }
        });
        
        paymentsCurrentPage = 1;
        updatePaymentsPagination(visibleRows);
    }
    
    // Update Payments Pagination
    function updatePaymentsPagination(visibleRows) {
        const totalRows = visibleRows.length;
        const totalPages = Math.ceil(totalRows / paymentsRowsPerPage);
        const paginationContainer = document.getElementById("payment-pagination-container");
        const paginationInfo = document.getElementById("payment-pagination-info");
        const paginationNumbers = document.getElementById("payment-pagination-numbers");
        const prevBtn = document.getElementById("payment-prev-btn");
        const nextBtn = document.getElementById("payment-next-btn");

        const isMobileOrTablet = window.innerWidth <= 1024;
        
        if (isMobileOrTablet) {
            if (paginationContainer) paginationContainer.style.display = "none";
            return;
        }

        if (totalRows === 0) {
            if (paginationContainer) paginationContainer.style.display = "none";
            return;
        }

        if (paginationContainer) paginationContainer.style.display = "flex";
        
        if (paymentsCurrentPage > totalPages && totalPages > 0) {
            paymentsCurrentPage = totalPages;
        }
        if (paymentsCurrentPage < 1) {
            paymentsCurrentPage = 1;
        }

        const startRow = (paymentsCurrentPage - 1) * paymentsRowsPerPage + 1;
        const endRow = Math.min(paymentsCurrentPage * paymentsRowsPerPage, totalRows);
        if (paginationInfo) paginationInfo.textContent = `Showing ${startRow}-${endRow} of ${totalRows} transactions`;

        if (prevBtn) prevBtn.disabled = paymentsCurrentPage === 1;
        if (nextBtn) nextBtn.disabled = paymentsCurrentPage >= totalPages;

        if (paginationNumbers) paginationNumbers.innerHTML = "";
        const maxPagesToShow = 5;
        let startPage = Math.max(1, paymentsCurrentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        if (startPage > 1 && paginationNumbers) {
            createPaymentsPageNumber(1, paginationNumbers);
            if (startPage > 2) {
                createPaymentsEllipsis(paginationNumbers);
            }
        }

        if (paginationNumbers) {
            for (let i = startPage; i <= endPage; i++) {
                createPaymentsPageNumber(i, paginationNumbers);
            }
        }

        if (endPage < totalPages && paginationNumbers) {
            if (endPage < totalPages - 1) {
                createPaymentsEllipsis(paginationNumbers);
            }
            createPaymentsPageNumber(totalPages, paginationNumbers);
        }

        // Show the current page
        showPaymentsPage(visibleRows, paymentsCurrentPage);
    }

    function createPaymentsPageNumber(pageNum, container) {
        const pageBtn = document.createElement("button");
        pageBtn.className = "pagination-number" + (pageNum === paymentsCurrentPage ? " active" : "");
        pageBtn.textContent = pageNum;
        pageBtn.onclick = () => goToPaymentsPage(pageNum);
        container.appendChild(pageBtn);
    }

    function createPaymentsEllipsis(container) {
        const ellipsis = document.createElement("span");
        ellipsis.className = "pagination-number ellipsis";
        ellipsis.textContent = "...";
        container.appendChild(ellipsis);
    }

    function showPaymentsPage(visibleRows, page) {
        const isMobileOrTablet = window.innerWidth <= 1024;
        const allPaymentRows = document.querySelectorAll(".payment-row");
        
        if (isMobileOrTablet) {
            allPaymentRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            
            visibleRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "block";
                }
            });
        } else {
            allPaymentRows.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "none";
                } else {
                    row.style.display = "none";
                }
            });
            
            const startIndex = (page - 1) * paymentsRowsPerPage;
            const endIndex = startIndex + paymentsRowsPerPage;
            const rowsToShow = visibleRows.slice(startIndex, endIndex);
            
            rowsToShow.forEach(row => {
                if (row.tagName === 'TR') {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "block";
                }
            });
        }
    }

    function getVisiblePaymentsRows() {
        const dateCategory = document.getElementById("filter-payment-date-category").value;
        const selectedDate = document.getElementById("filter-payment-date").value;
        const selectedStatus = document.getElementById("filter-payment-status").value.toLowerCase();
        const selectedMethod = document.getElementById("filter-payment-method").value.toLowerCase();
        const rows = document.querySelectorAll(".payment-row");
        const visibleRows = [];
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayStr = today.toISOString().split('T')[0];
        
        let weekStart = null, weekEnd = null;
        let monthStart = null, monthEnd = null;
        
        if (dateCategory === "week") {
            const dayOfWeek = today.getDay();
            const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
            weekStart = new Date(today);
            weekStart.setDate(today.getDate() - daysToMonday);
            weekStart.setHours(0, 0, 0, 0);
            weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 5);
            weekEnd.setHours(23, 59, 59, 999);
        } else if (dateCategory === "month") {
            monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            monthStart.setHours(0, 0, 0, 0);
            monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            monthEnd.setHours(23, 59, 59, 999);
        }
        
        rows.forEach(row => {
            const rowDate = row.getAttribute("data-date");
            const rowStatus = row.getAttribute("data-status").toLowerCase();
            const rowMethod = row.getAttribute("data-method").toLowerCase();
            
            let matchesDate = true;
            
            if (dateCategory === "custom" && selectedDate) {
                matchesDate = rowDate === selectedDate;
            } else if (dateCategory === "today") {
                matchesDate = rowDate === todayStr;
            } else if (dateCategory === "week") {
                if (rowDate) {
                    const rowDateObj = new Date(rowDate);
                    rowDateObj.setHours(0, 0, 0, 0);
                    matchesDate = rowDateObj >= weekStart && rowDateObj <= weekEnd;
                } else {
                    matchesDate = false;
                }
            } else if (dateCategory === "month") {
                if (rowDate) {
                    const rowDateObj = new Date(rowDate);
                    rowDateObj.setHours(0, 0, 0, 0);
                    matchesDate = rowDateObj >= monthStart && rowDateObj <= monthEnd;
                } else {
                    matchesDate = false;
                }
            } else if (dateCategory === "") {
                matchesDate = true;
            }
            
            const matchesStatus = selectedStatus === "" || rowStatus === selectedStatus;
            const matchesMethod = selectedMethod === "" || rowMethod === selectedMethod;
            
            if (matchesDate && matchesStatus && matchesMethod) {
                visibleRows.push(row);
            }
        });
        
        return visibleRows;
    }

    function goToPaymentsPage(page) {
        const visibleRows = getVisiblePaymentsRows();
        if (visibleRows.length === 0) return;

        paymentsCurrentPage = page;
        updatePaymentsPagination(visibleRows);
        showPaymentsPage(visibleRows, paymentsCurrentPage);
    }

    function changePaymentPage(direction) {
        const visibleRows = getVisiblePaymentsRows();
        if (visibleRows.length === 0) return;

        const totalPages = Math.ceil(visibleRows.length / paymentsRowsPerPage);
        const newPage = paymentsCurrentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            goToPaymentsPage(newPage);
        }
    }

    function printPayments() {
        window.print();
    }

    // Initialize pagination on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateRowsPerPage();
        
        const allRows = document.querySelectorAll(".payment-row");
        allRows.forEach(row => {
            if (row.tagName === 'TR') {
                row.style.display = "table-row";
            } else {
                row.style.display = "block";
            }
        });
        
        setTimeout(() => {
            filterPayments();
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
