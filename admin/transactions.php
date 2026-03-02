<?php
session_start();
include_once("../database/config.php");

// Base path for controller URLs (works when admin is in subfolder)
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
if (empty($basePath) || $basePath === '\\') {
    $basePath = '';
}
$viewProofImageUrl = $basePath . '/controllers/viewProofImage.php';

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

// Ensure refund_requests table exists
$createRefundTable = "CREATE TABLE IF NOT EXISTS refund_requests (
  id varchar(10) NOT NULL,
  payment_id varchar(10) NOT NULL,
  appointment_id varchar(10) NOT NULL,
  user_id varchar(10) NOT NULL,
  status enum('pending','processed','refunded') NOT NULL DEFAULT 'pending',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY payment_id (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($con, $createRefundTable);
// Ensure existing DBs can store "refunded" (safe to run repeatedly)
@mysqli_query($con, "ALTER TABLE refund_requests MODIFY status ENUM('pending','processed','refunded') NOT NULL DEFAULT 'pending'");

// Ensure patient_bill_status table exists to track if patient has paid their total bill
$createBillStatusTable = "CREATE TABLE IF NOT EXISTS patient_bill_status (
  id varchar(10) NOT NULL,
  patient_id varchar(10) NOT NULL,
  treatment_id varchar(10) DEFAULT NULL,
  appointment_id varchar(10) DEFAULT NULL,
  total_amount decimal(10,2) NOT NULL,
  payment_status enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  updated_by varchar(10) DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  KEY patient_id (patient_id),
  KEY treatment_id (treatment_id),
  KEY appointment_id (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($con, $createBillStatusTable);

// Get refund requests data
$refundSql = "SELECT rr.id, rr.payment_id, rr.appointment_id, rr.user_id, rr.status, rr.created_at,
                     p.amount, p.method, p.status as payment_status,
                     pi.first_name, pi.last_name, pi.email,
                     a.appointment_date, a.appointment_time,
                     s.service_category, s.sub_service
              FROM refund_requests rr
              LEFT JOIN payment p ON rr.payment_id = p.payment_id
              LEFT JOIN patient_information pi ON rr.user_id = pi.user_id
              LEFT JOIN appointments a ON rr.appointment_id = a.appointment_id
              LEFT JOIN services s ON a.service_id = s.service_id
              ORDER BY rr.created_at DESC";
$refundResult = mysqli_query($con, $refundSql);

// All Transactions - Get ALL completed treatments from treatment_history
// Try to match with appointment payments if available
$allTransactionsSql = "SELECT 
                        th.treatment_id,
                        th.patient_id,
                        th.treatment,
                        th.treatment_cost,
                        th.created_at as treatment_date,
                        th.prescription_given,
                        th.notes,
                        CONCAT(pi.first_name, ' ', pi.last_name) as patient_name,
                        a.appointment_id,
                        a.appointment_date,
                        a.status as appointment_status,
                        p.payment_id,
                        COALESCE(p.amount, 0) as appointment_fee,
                        p.method as payment_method,
                        p.status as payment_status,
                        s.sub_service as service_name,
                        COALESCE(pbs.payment_status, 'unpaid') as bill_payment_status,
                        pbs.id as bill_status_id
                       FROM treatment_history th
                       LEFT JOIN patient_information pi ON th.patient_id = pi.patient_id
                       LEFT JOIN appointments a ON th.patient_id = a.patient_id 
                           AND a.status IN ('Completed', 'Complete', 'Paid')
                           AND a.appointment_date <= DATE_ADD(th.created_at, INTERVAL 30 DAY)
                           AND a.appointment_date >= DATE_SUB(th.created_at, INTERVAL 7 DAY)
                       LEFT JOIN payment p ON a.appointment_id = p.appointment_id
                       LEFT JOIN services s ON a.service_id = s.service_id
                       LEFT JOIN patient_bill_status pbs ON th.patient_id = pbs.patient_id 
                           AND (pbs.treatment_id = th.treatment_id OR (pbs.treatment_id IS NULL AND pbs.appointment_id = a.appointment_id))
                       ORDER BY th.created_at DESC";
$allTransactionsResult = mysqli_query($con, $allTransactionsSql);
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

        /* Tab Styles */
        .tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 12px 24px;
            border: none;
            background: #f3f4f6;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-button:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .tab-button.active {
            background: var(--primary-color);
            color: white;
        }

        .tab-content {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
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

        <!-- Tabs -->
        <div class="tabs-container" style="margin-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
            <button class="tab-button active" onclick="switchTab('payments')" id="tab-payments">
                <i class="fas fa-credit-card"></i> Appointment Transactions
            </button>
            <button class="tab-button" onclick="switchTab('refunds')" id="tab-refunds">
                <i class="fas fa-undo"></i> Refund Requests
                <?php 
                $pendingCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM refund_requests WHERE status = 'pending'"));
                if ($pendingCount > 0): 
                ?>
                    <span class="badge" style="background: #EF4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 5px;"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-button" onclick="switchTab('all-transactions')" id="tab-all-transactions">
                <i class="fas fa-list"></i> All Transactions
            </button>
        </div>

        <!-- Payments Tab Content -->
        <div id="payments-tab" class="tab-content">
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
        <!-- End Payments Tab Content -->

        <!-- Refund Requests Tab Content -->
        <div id="refunds-tab" class="tab-content" style="display: none;">
            <div class="table-responsive">
                <table id="refund-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Appointment ID</th>
                            <th>Patient Name</th>
                            <th>Service</th>
                            <th>Amount</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if(mysqli_num_rows($refundResult) > 0) {
                            mysqli_data_seek($refundResult, 0);
                            while ($row = mysqli_fetch_assoc($refundResult)) { 
                        ?>
                            <tr class="refund-row">
                                <td><?php echo htmlspecialchars($row['payment_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['appointment_id']); ?></td>
                                <td><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($row['sub_service'] ?? $row['service_category'] ?? 'N/A'); ?></td>
                                <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <span class="status status-<?php echo htmlspecialchars(strtolower($row['status'] ?? 'pending')); ?>">
                                        <?php echo htmlspecialchars(ucfirst($row['status'] ?? 'Pending')); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <?php if ($row['status'] === 'pending'): ?>
                                        <button type="button" class="action-btn btn-success" title="Process Refund"
                                            data-refund-id="<?php echo $row['id']; ?>"
                                            data-payment-id="<?php echo $row['payment_id']; ?>"
                                            onclick="processRefund(this)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="action-btn btn-primary" title="View Details"
                                            data-refund-id="<?php echo $row['id']; ?>"
                                            onclick="viewRefundDetails(this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            }
                        } else { 
                        ?>
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                    <p>No refund requests found</p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- End Refund Requests Tab Content -->

        <!-- All Transactions Tab Content -->
        <div id="all-transactions-tab" class="tab-content" style="display: none;">
            <div class="table-responsive">
                <table id="all-transactions-table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Service/Treatment</th>
                            <th>Appointment Fee</th>
                            <th>Treatment Cost</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($allTransactionsResult && mysqli_num_rows($allTransactionsResult) > 0) {
                            while ($row = mysqli_fetch_assoc($allTransactionsResult)) {
                                $treatmentCost = floatval($row['treatment_cost'] ?? 0);
                                $appointmentFee = floatval($row['appointment_fee'] ?? 0);
                                $total = $treatmentCost + $appointmentFee;
                                $transactionDate = $row['treatment_date'] ?? $row['appointment_date'];
                                $billStatus = strtolower($row['bill_payment_status'] ?? 'unpaid');
                                $billStatusId = $row['bill_status_id'] ?? null;
                        ?>
                            <tr class="all-tx-row" 
                                data-patient-id="<?php echo htmlspecialchars($row['patient_id']); ?>"
                                data-date="<?php echo htmlspecialchars($transactionDate); ?>"
                                data-bill-status-id="<?php echo htmlspecialchars($billStatusId); ?>">
                                <td><?php echo htmlspecialchars($row['patient_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['treatment'] ?? $row['service_name'] ?? 'N/A'); ?></td>
                                <td>₱<?php echo number_format($appointmentFee, 2); ?></td>
                                <td>₱<?php echo number_format($treatmentCost, 2); ?></td>
                                <td><strong style="color: #10B981; font-size: 16px;">₱<?php echo number_format($total, 2); ?></strong></td>
                                <td>
                                    <span class="badge" style="background: <?php echo $billStatus === 'paid' ? '#10B981' : '#EF4444'; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                        <?php echo $billStatus === 'paid' ? 'Paid' : 'Unpaid'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($transactionDate)); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button type="button" class="action-btn btn-primary" 
                                                title="View Bill Summary"
                                                onclick="viewBillSummary('<?php echo htmlspecialchars($row['patient_id']); ?>', '<?php echo htmlspecialchars($row['treatment_id']); ?>', '<?php echo htmlspecialchars($row['appointment_id']); ?>', '<?php echo htmlspecialchars($billStatusId); ?>', '<?php echo htmlspecialchars($billStatus); ?>', <?php echo $total; ?>)">
                                            <i class="fas fa-file-invoice"></i>
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
                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                    <p>No completed transactions found</p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- End All Transactions Tab Content -->
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

<!-- Refund Details Modal -->
<div id="refundDetailsModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 760px;">
        <h3><i class="fas fa-undo"></i> PAYMENT DETAILS</h3>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 12px;">
            <div><strong>Payment ID:</strong> <span id="rd_payment_id">-</span></div>
            <div><strong>Method:</strong> <span id="rd_method">-</span></div>
            <div><strong>Account Name:</strong> <span id="rd_account_name">N/A</span></div>
            <div><strong>Account Number:</strong> <span id="rd_account_number">N/A</span></div>
            <div><strong>Amount:</strong> <span id="rd_amount">-</span></div>
            <div style="grid-column: 1 / -1;">
                <strong>Proof:</strong>
                <span id="rd_proof_text">-</span>
                <button type="button" class="view-image-btn" id="rd_view_proof_btn" style="margin-left:10px; display:none;">
                    View PDF
                </button>
            </div>
        </div>

        <div style="margin-top: 16px; display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeRefundDetailsModal()" class="modal-close-btn">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
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

    // View Proof Image - show in modal (image) or open PDF in new tab
    function viewProofPDF(paymentId) {
        if (!paymentId) {
            alert('Payment ID is missing. Cannot view proof image.');
            return;
        }
        const baseUrl = '<?php echo htmlspecialchars($viewProofImageUrl); ?>';
        const imageUrl = baseUrl + '?payment_id=' + encodeURIComponent(paymentId) + '&format=image';
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        if (modal && modalImg) {
            modalImg.src = imageUrl;
            modal.style.display = "flex";
        } else {
            window.open(baseUrl + '?payment_id=' + encodeURIComponent(paymentId), '_blank', 'noopener,noreferrer');
        }
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

    // Tab switching function
    function switchTab(tabName) {
        // Hide all tab contents
        document.getElementById('payments-tab').style.display = 'none';
        document.getElementById('refunds-tab').style.display = 'none';
        document.getElementById('all-transactions-tab').style.display = 'none';
        
        // Remove active class from all tabs
        document.getElementById('tab-payments').classList.remove('active');
        document.getElementById('tab-refunds').classList.remove('active');
        document.getElementById('tab-all-transactions').classList.remove('active');
        
        // Show selected tab content
        if (tabName === 'payments') {
            document.getElementById('payments-tab').style.display = 'block';
            document.getElementById('tab-payments').classList.add('active');
        } else if (tabName === 'refunds') {
            document.getElementById('refunds-tab').style.display = 'block';
            document.getElementById('tab-refunds').classList.add('active');
        } else if (tabName === 'all-transactions') {
            document.getElementById('all-transactions-tab').style.display = 'block';
            document.getElementById('tab-all-transactions').classList.add('active');
        }
    }
    
    // Utility function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // View Bill Summary function
    function viewBillSummary(patientId, treatmentId, appointmentId, billStatusId = null, currentStatus = 'unpaid', totalAmount = 0) {
        // Fetch patient information and transaction details
        Promise.all([
            fetch('../controllers/getPatients.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ patient_id: patientId, first_name: '', last_name: '' })),
            fetch('../controllers/getTreatmentHistory.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ status: 'error', data: [] })),
            fetch('../controllers/getPatientPayments.php?patient_id=' + encodeURIComponent(patientId))
                .then(response => response.json())
                .catch(() => ({ status: 'error', data: [] }))
        ]).then(([patientData, treatmentData, paymentData]) => {
            const patientName = patientData.first_name && patientData.last_name 
                ? `${patientData.first_name} ${patientData.last_name}` 
                : `Patient ID: ${patientId}`;
            
            // Calculate totals
            let totalTreatmentCost = 0;
            let totalAppointmentFee = 0;
            
            if (treatmentData.status === 'success' && treatmentData.data) {
                treatmentData.data.forEach(treatment => {
                    totalTreatmentCost += parseFloat(treatment.treatment_cost || 0);
                });
            }
            
            if (paymentData.status === 'success' && paymentData.data) {
                paymentData.data.forEach(payment => {
                    totalAppointmentFee += parseFloat(payment.amount || 0);
                });
            }
            
            // Create and show modal
            showBillSummaryModal(patientId, patientName, treatmentData, paymentData, totalTreatmentCost, totalAppointmentFee, treatmentId, appointmentId, billStatusId, currentStatus, totalAmount);
        }).catch(error => {
            console.error('Error loading bill summary:', error);
            showNotification('error', 'Error', 'Failed to load bill summary. Please try again.');
        });
    }
    
    // Show Bill Summary Modal
    function showBillSummaryModal(patientId, patientName, treatmentData, paymentData, totalTreatmentCost, totalAppointmentFee, treatmentId, appointmentId, billStatusId, currentStatus, totalAmount) {
        const modal = document.getElementById('billSummaryModal');
        if (!modal) {
            // Create modal if it doesn't exist - simple and clean design
            const modalHTML = `
                <div id="billSummaryModal" class="modal" style="display:none;">
                    <div class="modal-content" style="max-width: 800px; width: 95%; max-height: 95vh; overflow-y: auto; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                        <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                        </div>
                        <div id="billSummaryContent" style="padding: 20px;"></div>
                        <div style="padding: 15px 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px;">
                            <button onclick="printBillSummary()" style="background: #374151; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button onclick="closeBillSummaryModal()" style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        const content = document.getElementById('billSummaryContent');
        const currentDate = new Date().toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        let htmlContent = `
            <div style="margin-bottom: 25px;">
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb;">
                    <thead>
                        <tr style="background: #374151;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Patient ID</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Patient Name</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; color: #374151; font-size: 13px;">${patientId}</td>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; color: #374151; font-size: 13px;">${escapeHtml(patientName)}</td>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; color: #374151; font-size: 13px;">${currentDate}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-bottom: 25px;">
                <h4 style="margin: 0 0 15px 0; color: #1f2937; font-size: 16px; font-weight: 600;">Treatment History</h4>
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb;">
                    <thead>
                        <tr style="background: #374151;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Treatment</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Prescription</th>
                            <th style="padding: 12px; text-align: right; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Cost</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        if (treatmentData.status === 'success' && treatmentData.data && treatmentData.data.length > 0) {
            treatmentData.data.forEach((treatment) => {
                // Format date like in image: "Jan 22, 2026 12:47 PM"
                let formattedDate = 'N/A';
                if (treatment.created_at) {
                    const date = new Date(treatment.created_at);
                    formattedDate = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    }) + ' ' + date.toLocaleTimeString('en-US', { 
                        hour: 'numeric', 
                        minute: '2-digit',
                        hour12: true 
                    });
                }
                htmlContent += `
                    <tr>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; color: #374151; font-size: 13px;">${escapeHtml(treatment.treatment || 'N/A')}</td>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">${escapeHtml(treatment.prescription_given || 'N/A')}</td>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; text-align: right; color: #059669; font-weight: 600; font-size: 13px;">₱${parseFloat(treatment.treatment_cost || 0).toFixed(2)}</td>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">${formattedDate}</td>
                    </tr>
                `;
            });
        } else {
            htmlContent += `
                <tr>
                    <td colspan="4" style="padding: 20px; text-align: center; color: #9ca3af; font-size: 13px;">No treatment history found</td>
                </tr>
            `;
        }
        
        htmlContent += `
                    </tbody>
                </table>
                <div style="margin-top: 12px; text-align: right;">
                    <span style="color: #374151; font-size: 14px;">Total Treatment Cost: <strong style="color: #059669;">₱${totalTreatmentCost.toFixed(2)}</strong></span>
                </div>
            </div>
            
            <div style="margin-bottom: 25px;">
                <h4 style="margin: 0 0 15px 0; color: #1f2937; font-size: 16px; font-weight: 600;">Appointment Payments</h4>
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb;">
                    <thead>
                        <tr style="background: #374151;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Payment ID</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Method</th>
                            <th style="padding: 12px; text-align: right; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Amount</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Status</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: white; text-transform: uppercase;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        if (paymentData.status === 'success' && paymentData.data && paymentData.data.length > 0) {
            paymentData.data.forEach((payment) => {
                const statusColor = payment.status === 'paid' ? '#10b981' : payment.status === 'pending' ? '#f59e0b' : '#ef4444';
                // Format date like in image: "Dec 19, 2025"
                let formattedDate = 'N/A';
                if (payment.created_at) {
                    formattedDate = new Date(payment.created_at).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                }
                htmlContent += `
                    <tr>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; color: #374151; font-size: 13px;">${escapeHtml(payment.payment_id || 'N/A')}</td>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">${escapeHtml(payment.method || 'N/A')}</td>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; text-align: right; color: #059669; font-weight: 600; font-size: 13px;">₱${parseFloat(payment.amount || 0).toFixed(2)}</td>
                        <td style="padding: 12px; border: 1px solid #e5e7eb;">
                            <span style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block;">
                                ${escapeHtml(payment.status || 'N/A')}
                            </span>
                        </td>
                        <td style="padding: 12px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">${formattedDate}</td>
                    </tr>
                `;
            });
        } else {
            htmlContent += `
                <tr>
                    <td colspan="5" style="padding: 20px; text-align: center; color: #9ca3af; font-size: 13px;">No payment records found</td>
                </tr>
            `;
        }
        
        const grandTotal = totalTreatmentCost + totalAppointmentFee;
        const billStatus = currentStatus || 'unpaid';
        const statusColor = billStatus === 'paid' ? '#10b981' : '#ef4444';
        const statusText = billStatus === 'paid' ? 'Paid' : 'Unpaid';
        
        htmlContent += `
                    </tbody>
                </table>
                <div style="margin-top: 12px; text-align: right;">
                    <span style="color: #374151; font-size: 14px;">Total Appointment Fees: <strong style="color: #059669;">₱${totalAppointmentFee.toFixed(2)}</strong></span>
                </div>
            </div>
            
            <div style="padding: 15px; background: #f3f4f6; border-radius: 6px; margin-top: 20px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong style="color: #374151; font-size: 16px;">Total Amount:</strong>
                    <strong style="color: #059669; font-size: 20px;">₱${grandTotal.toFixed(2)}</strong>
                </div>
            </div>
            
            <div style="padding: 15px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <strong style="color: #374151; font-size: 14px;">Payment Status:</strong>
                    <span id="billStatusBadge" style="background: ${billStatus === 'paid' ? '#10b981' : '#ef4444'}; color: white; padding: 6px 16px; border-radius: 12px; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                        ${billStatus === 'paid' ? 'Paid' : 'Unpaid'}
                    </span>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button onclick="updateBillPaymentStatus('${patientId}', '${treatmentId || ''}', '${appointmentId || ''}', '${billStatusId || ''}', 'paid', ${grandTotal})" 
                            id="markPaidBtn"
                            style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; flex: 1; ${billStatus === 'paid' ? 'opacity: 0.6; cursor: not-allowed;' : ''}"
                            ${billStatus === 'paid' ? 'disabled' : ''}>
                        <i class="fas fa-check-circle"></i> Mark as Paid
                    </button>
                    <button onclick="updateBillPaymentStatus('${patientId}', '${treatmentId || ''}', '${appointmentId || ''}', '${billStatusId || ''}', 'unpaid', ${grandTotal})" 
                            id="markUnpaidBtn"
                            style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; flex: 1; ${billStatus === 'unpaid' ? 'opacity: 0.6; cursor: not-allowed;' : ''}"
                            ${billStatus === 'unpaid' ? 'disabled' : ''}>
                        <i class="fas fa-times-circle"></i> Mark as Unpaid
                    </button>
                </div>
            </div>
        `;
        
        // Store values for update function
        window.currentBillData = {
            patientId: patientId,
            treatmentId: treatmentId || '',
            appointmentId: appointmentId || '',
            billStatusId: billStatusId || '',
            totalAmount: grandTotal
        };
        
        content.innerHTML = htmlContent;
        document.getElementById('billSummaryModal').style.display = 'flex';
    }
    
    function closeBillSummaryModal() {
        const modal = document.getElementById('billSummaryModal');
        if (modal) modal.style.display = 'none';
    }
    
    // Update Bill Payment Status
    function updateBillPaymentStatus(patientId, treatmentId, appointmentId, billStatusId, newStatus, totalAmount) {
        if (!confirm(`Are you sure you want to mark this bill as ${newStatus.toUpperCase()}?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('patient_id', patientId);
        formData.append('treatment_id', treatmentId || '');
        formData.append('appointment_id', appointmentId || '');
        formData.append('bill_status_id', billStatusId || '');
        formData.append('payment_status', newStatus);
        formData.append('total_amount', totalAmount);
        
        fetch('../controllers/updateBillPaymentStatus.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.status === 'success') {
                showNotification('success', 'Status Updated', `Bill payment status has been updated to ${newStatus.toUpperCase()}.`);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to update payment status. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while updating payment status. Please try again.');
        });
    }
    
    function printBillSummary() {
        const modalContent = document.getElementById('billSummaryContent');
        if (!modalContent) return;
        
        // Clone the content to avoid modifying the original
        const contentClone = modalContent.cloneNode(true);
        
        // Remove inline styles that don't print well and add print-friendly styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                @page { margin: 1.5cm; }
                body { margin: 0; }
            }
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            .print-header {
                text-align: center;
                border-bottom: 3px solid #667eea;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .print-header h1 {
                margin: 0;
                color: #667eea;
                font-size: 28px;
            }
            .print-header h2 {
                margin: 10px 0 0 0;
                color: #6b7280;
                font-size: 16px;
                font-weight: normal;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                page-break-inside: avoid;
            }
            th, td {
                padding: 12px;
                border: 1px solid #ddd;
            }
            th {
                background-color: #667eea !important;
                color: white !important;
                font-weight: 600;
            }
            tr:nth-child(even) {
                background-color: #f9fafb;
            }
            .print-total {
                background: #f0f9ff;
                padding: 15px;
                border-radius: 8px;
                margin: 15px 0;
                border-left: 4px solid #3b82f6;
            }
                    .print-grand-total {
                        background: #f0fdf4;
                        border: 2px solid #10b981;
                        padding: 15px;
                        border-radius: 6px;
                        margin-top: 20px;
                    }
                    .print-grand-total h3 {
                        margin: 0;
                        color: #059669;
                    }
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Bill Summary - Landero Dental Clinic</title>
            </head>
            <body>
                <div class="print-header">
                    <h1>Landero Dental Clinic</h1>
                    <h2>Bill Summary Report</h2>
                </div>
                ${contentClone.innerHTML}
            </body>
            </html>
        `);
        printWindow.document.head.appendChild(style);
        printWindow.document.close();
        setTimeout(() => printWindow.print(), 300);
    }
    
    // Close modal when clicking outside
    window.addEventListener("click", function(event) {
        const billModal = document.getElementById("billSummaryModal");
        if (event.target === billModal) {
            closeBillSummaryModal();
        }
    });

    // Process refund function
    function processRefund(button) {
        const refundId = button.getAttribute('data-refund-id');
        const paymentId = button.getAttribute('data-payment-id');
        
        if (!confirm('Are you sure you want to approve this refund? This will mark the refund as refunded and update the payment status to refunded.')) {
            return;
        }

        const formData = new FormData();
        formData.append('refund_id', refundId);
        formData.append('payment_id', paymentId);

        showNotification('info', 'Processing...', 'Processing refund request...', '<i class="fas fa-spinner fa-spin"></i>', 2000);

        fetch('../controllers/processRefund.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Refund Processed', data.message || 'Refund has been processed successfully.');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to process refund.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while processing the refund.');
        });
    }

    // View refund details function
    function viewRefundDetails(button) {
        const refundId = button.getAttribute('data-refund-id');
        
        fetch('../controllers/getRefundDetails.php?id=' + encodeURIComponent(refundId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openRefundDetailsModal(data.refund);
            } else {
                showNotification('error', 'Error', data.message || 'Failed to load refund details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Error', 'An error occurred while loading refund details.');
        });
    }

    function openRefundDetailsModal(refund) {
        document.getElementById('rd_payment_id').textContent = refund.payment_id || '-';
        document.getElementById('rd_method').textContent = refund.method || '-';
        document.getElementById('rd_amount').textContent = (refund.amount !== null && refund.amount !== undefined) ? ('₱' + parseFloat(refund.amount).toFixed(2)) : '-';
        document.getElementById('rd_account_name').textContent = refund.account_name || 'N/A';
        document.getElementById('rd_account_number').textContent = refund.account_number || 'N/A';

        const proofText = document.getElementById('rd_proof_text');
        const proofBtn = document.getElementById('rd_view_proof_btn');
        proofBtn.style.display = 'none';
        proofBtn.onclick = null;

        if (refund.has_proof && refund.payment_id) {
            proofText.textContent = 'Available';
            proofBtn.style.display = 'inline-block';
            proofBtn.onclick = function () { openProofPdfTab(refund.payment_id); };
        } else {
            proofText.textContent = 'No Image';
        }

        document.getElementById('refundDetailsModal').style.display = 'flex';
    }

    // Open proof as PDF in a new tab (viewProofImage.php defaults to PDF when format is omitted)
    function openProofPdfTab(paymentId) {
        if (!paymentId) return;
        const baseUrl = '<?php echo htmlspecialchars($viewProofImageUrl); ?>';
        window.open(baseUrl + '?payment_id=' + encodeURIComponent(paymentId), '_blank', 'noopener,noreferrer');
    }

    function closeRefundDetailsModal() {
        const modal = document.getElementById('refundDetailsModal');
        if (modal) modal.style.display = 'none';
    }

    // Close refund details modal when clicking outside
    window.addEventListener("click", function(event) {
        const refundModal = document.getElementById("refundDetailsModal");
        if (event.target === refundModal) {
            closeRefundDetailsModal();
        }
    });

    // Check if URL has refund_payment_id parameter to switch to refunds tab
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('refund_payment_id')) {
        document.addEventListener('DOMContentLoaded', function () {
            switchTab('refunds');
        });
    }
</script>

</body>
</html>
