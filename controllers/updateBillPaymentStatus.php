<?php
session_start();
include_once('../database/config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Unauthorized access.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit();
}

$patient_id = trim($_POST['patient_id'] ?? '');
$treatment_id = trim($_POST['treatment_id'] ?? '');
$appointment_id = trim($_POST['appointment_id'] ?? '');
$bill_status_id = trim($_POST['bill_status_id'] ?? '');
$payment_status = trim($_POST['payment_status'] ?? 'unpaid');
$total_amount = floatval($_POST['total_amount'] ?? 0);

if (empty($patient_id)) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Patient ID is required.'
    ]);
    exit();
}

if (!in_array($payment_status, ['paid', 'unpaid'])) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid payment status.'
    ]);
    exit();
}

// Ensure patient_bill_status table exists
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

$user_id = $_SESSION['userID'];

try {
    if (!empty($bill_status_id)) {
        // Update existing record
        $updateSql = "UPDATE patient_bill_status 
                     SET payment_status = ?, 
                         total_amount = ?,
                         updated_by = ?,
                         updated_at = NOW()
                     WHERE id = ?";
        $stmt = $con->prepare($updateSql);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $con->error);
        }
        $stmt->bind_param("sdis", $payment_status, $total_amount, $user_id, $bill_status_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Payment status updated successfully.'
            ]);
        } else {
            $stmt->close();
            throw new Exception('Failed to update payment status: ' . $stmt->error);
        }
    } else {
        // Create new record
        // Generate ID
        $queryLast = $con->query("SELECT id FROM patient_bill_status ORDER BY id DESC LIMIT 1");
        if ($queryLast && $queryLast->num_rows > 0) {
            $row = $queryLast->fetch_assoc();
            $lastID = intval(substr($row['id'], 2)) + 1;
            $new_id = "BS" . str_pad($lastID, 4, "0", STR_PAD_LEFT);
        } else {
            $new_id = "BS0001";
        }
        
        $insertSql = "INSERT INTO patient_bill_status 
                     (id, patient_id, treatment_id, appointment_id, total_amount, payment_status, updated_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $con->prepare($insertSql);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $con->error);
        }
        
        $treatment_id_val = !empty($treatment_id) ? $treatment_id : null;
        $appointment_id_val = !empty($appointment_id) ? $appointment_id : null;
        
        $stmt->bind_param("ssssdss", $new_id, $patient_id, $treatment_id_val, $appointment_id_val, $total_amount, $payment_status, $user_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Payment status created successfully.',
                'bill_status_id' => $new_id
            ]);
        } else {
            $stmt->close();
            throw new Exception('Failed to create payment status: ' . $stmt->error);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$con->close();
?>
