<?php
session_start();
require_once(__DIR__ . "/../database/config.php");
header("Content-Type: application/json");

function hasColumn($con, $table, $column) {
    $tableEscaped = mysqli_real_escape_string($con, $table);
    $columnEscaped = mysqli_real_escape_string($con, $column);
    $sql = "SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'";
    $result = mysqli_query($con, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$treatmentId = trim($_POST['treatment_id'] ?? '');
if ($treatmentId === '') {
    echo json_encode(["success" => false, "message" => "Treatment ID is required."]);
    exit();
}
$appointmentId = trim($_POST['appointment_id'] ?? '');

mysqli_begin_transaction($con);
try {
    // Ensure soft-archive fields exist for tables involved in all-transactions records.
    if (!hasColumn($con, "treatment_history", "is_archived")) {
        mysqli_query($con, "ALTER TABLE treatment_history ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!hasColumn($con, "patient_bill_status", "is_archived")) {
        mysqli_query($con, "ALTER TABLE patient_bill_status ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!hasColumn($con, "payment", "is_archived")) {
        mysqli_query($con, "ALTER TABLE payment ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Archive the selected treatment transaction.
    $txStmt = $con->prepare("UPDATE treatment_history SET is_archived = 1 WHERE treatment_id = ? AND COALESCE(is_archived, 0) = 0 LIMIT 1");
    if (!$txStmt) {
        throw new Exception("Failed to prepare transaction archive.");
    }
    $txStmt->bind_param("s", $treatmentId);
    $ok = $txStmt->execute();
    $affected = $txStmt->affected_rows;
    $txStmt->close();

    if (!$ok || $affected <= 0) {
        throw new Exception("Transaction not found or already archived.");
    }

    // Archive related bill-status records if present (safe when none exists).
    $pbsStmt = $con->prepare("UPDATE patient_bill_status SET is_archived = 1 WHERE treatment_id = ? AND COALESCE(is_archived, 0) = 0");
    if ($pbsStmt) {
        $pbsStmt->bind_param("s", $treatmentId);
        $pbsStmt->execute();
        $pbsStmt->close();
    }

    // Archive related appointment payment so it no longer appears in Appointment Transactions.
    if ($appointmentId !== '') {
        $paymentStmt = $con->prepare("UPDATE payment SET is_archived = 1 WHERE appointment_id = ? AND COALESCE(is_archived, 0) = 0");
        if ($paymentStmt) {
            $paymentStmt->bind_param("s", $appointmentId);
            $paymentStmt->execute();
            $paymentStmt->close();
        }
    }

    mysqli_commit($con);
    echo json_encode(["success" => true, "message" => "Transaction and related records archived successfully."]);
} catch (Exception $e) {
    mysqli_rollback($con);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
