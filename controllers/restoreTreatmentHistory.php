<?php
declare(strict_types=1);

header('Content-Type: application/json');

// Avoid any accidental output before JSON
ob_start();

try {
    require_once(__DIR__ . '/../database/config.php');

    if (!isset($con) || !$con) {
        throw new Exception('Database connection not available.');
    }

    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    $treatmentId = isset($_POST['treatment_id']) ? trim($_POST['treatment_id']) : '';
    if ($treatmentId === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Missing treatment record ID.'
        ]);
        exit;
    }

    // Validate that record exists
    $selectStmt = mysqli_prepare($con, "SELECT treatment_id, is_archived FROM treatment_history WHERE treatment_id = ?");
    if (!$selectStmt) {
        throw new Exception('Failed to prepare select statement.');
    }
    mysqli_stmt_bind_param($selectStmt, 's', $treatmentId);
    mysqli_stmt_execute($selectStmt);
    $result = mysqli_stmt_get_result($selectStmt);
    if (!$result || mysqli_num_rows($result) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Treatment record not found.'
        ]);
        exit;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($selectStmt);

    // Update archived flag to active state (0)
    $updateStmt = mysqli_prepare($con, "UPDATE treatment_history SET is_archived = 0 WHERE treatment_id = ?");
    if (!$updateStmt) {
        throw new Exception('Failed to prepare update statement.');
    }
    mysqli_stmt_bind_param($updateStmt, 's', $treatmentId);
    $ok = mysqli_stmt_execute($updateStmt);
    $affected = mysqli_stmt_affected_rows($updateStmt);
    mysqli_stmt_close($updateStmt);

    if (!$ok || $affected < 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to restore treatment record.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Treatment record restored successfully.'
    ]);
    exit;
} catch (Throwable $e) {
    // Log error server-side if desired
    // error_log('restoreTreatmentHistory error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error while restoring record.'
    ]);
    exit;
} finally {
    // Discard any buffered unintended output
    ob_end_clean();
}

