<?php
ob_start();
session_start();
require_once(__DIR__ . "/../database/config.php");

header("Content-Type: application/json");

function sendJson($data) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($data);
    exit();
}

function hasColumn($con, $table, $column) {
    $tableEscaped = mysqli_real_escape_string($con, $table);
    $columnEscaped = mysqli_real_escape_string($con, $column);
    $sql = "SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'";
    $result = mysqli_query($con, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

if (!isset($_SESSION['valid']) || $_SESSION['valid'] !== true) {
    sendJson([
        "success" => false,
        "message" => "Unauthorized access."
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJson([
        "success" => false,
        "message" => "Invalid request method."
    ]);
}

$appointmentId = trim($_POST["appointment_id"] ?? "");
if ($appointmentId === "") {
    sendJson([
        "success" => false,
        "message" => "Appointment ID is required."
    ]);
}

// Soft-archive fields on appointments table
if (!hasColumn($con, "appointments", "is_archived")) {
    $alterArchived = mysqli_query($con, "ALTER TABLE appointments ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
    if (!$alterArchived) {
        sendJson([
            "success" => false,
            "message" => "Failed to prepare archive fields (is_archived)."
        ]);
    }
}
if (!hasColumn($con, "appointments", "archived_at")) {
    $alterArchivedAt = mysqli_query($con, "ALTER TABLE appointments ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL");
    if (!$alterArchivedAt) {
        sendJson([
            "success" => false,
            "message" => "Failed to prepare archive fields (archived_at)."
        ]);
    }
}

$checkSql = "SELECT appointment_id, is_archived FROM appointments WHERE appointment_id = ? LIMIT 1";
$checkStmt = $con->prepare($checkSql);
if (!$checkStmt) {
    sendJson([
        "success" => false,
        "message" => "Failed to prepare appointment lookup."
    ]);
}

$checkStmt->bind_param("s", $appointmentId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$appointment = $checkResult->fetch_assoc();
$checkStmt->close();

if (!$appointment) {
    sendJson([
        "success" => false,
        "message" => "Appointment not found."
    ]);
}

if ((int)($appointment["is_archived"] ?? 0) === 1) {
    sendJson([
        "success" => true,
        "status" => "success",
        "message" => "Appointment is already archived."
    ]);
}

$archiveSql = "UPDATE appointments
               SET is_archived = 1, archived_at = NOW()
               WHERE appointment_id = ? AND COALESCE(is_archived, 0) = 0
               LIMIT 1";
$archiveStmt = $con->prepare($archiveSql);
if (!$archiveStmt) {
    sendJson([
        "success" => false,
        "message" => "Failed to archive appointment."
    ]);
}

$archiveStmt->bind_param("s", $appointmentId);
$ok = $archiveStmt->execute();
$affected = $archiveStmt->affected_rows;
$archiveStmt->close();

if (!$ok || $affected <= 0) {
    sendJson([
        "success" => false,
        "message" => "Failed to archive appointment."
    ]);
}

// Keep existing appointment archive flow intact; only sync related records by appointment_id.
$syncTargets = [
    "payment",
    "patient_bill_status"
];

foreach ($syncTargets as $tableName) {
    $syncSql = "UPDATE {$tableName}
                SET is_archived = 1
                WHERE appointment_id = ? AND COALESCE(is_archived, 0) = 0";
    $syncStmt = $con->prepare($syncSql);
    if (!$syncStmt) {
        // Best-effort sync: do not fail the appointment archive if related rows are missing/unavailable.
        continue;
    }

    $syncStmt->bind_param("s", $appointmentId);
    $syncStmt->execute();
    $syncStmt->close();
}

sendJson([
    "success" => true,
    "status" => "success",
    "message" => "Appointment archived successfully."
]);
?>
