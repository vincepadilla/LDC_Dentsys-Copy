<?php
// Start output buffering to prevent any accidental output
ob_start();

// Suppress errors and warnings that might output HTML
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Check if user is admin
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Unauthorized access.'
    ]);
    ob_end_flush();
    exit;
}

// Check if admin is verified
if (empty($_SESSION['admin_verified'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin verification required.'
    ]);
    ob_end_flush();
    exit;
}

// Include config file - capture any output from die() statements
$config_output = '';
try {
    ob_start();
    include '../database/config.php';
    $config_output = ob_get_clean();
    
    // If config output anything (like from die()), we have a connection problem
    if ($config_output !== '') {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database connection failed. Please check your database configuration.'
    ]);
    ob_end_flush();
    exit;
}

// Check if connection was successful
if (!isset($con) || !$con) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database connection failed. Please check your database configuration.'
    ]);
    ob_end_flush();
    exit;
}

// Set JSON header early
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $patient_id = trim($_POST['patient_id'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $prescription_given = trim($_POST['prescription_given'] ?? '');
    $notes = trim($_POST['treatment_notes'] ?? '');
    $treatment_cost = trim($_POST['treatment_cost'] ?? '');
    $walkin_id = trim($_POST['walkin_id'] ?? '');

    $errors = [];
    if (empty($patient_id)) $errors[] = "Patient ID is required.";
    if (empty($treatment)) $errors[] = "Treatment type is required.";
    if (empty($prescription_given)) $errors[] = "Prescription is required.";
    if (empty($notes)) $errors[] = "Treatment notes are required.";
    if ($treatment_cost === '' || !is_numeric($treatment_cost) || $treatment_cost < 0) {
        $errors[] = "Please enter a valid treatment cost.";
    }
    if (empty($walkin_id)) $errors[] = "Walk-in ID is required.";

    $checkPatient = $con->prepare("SELECT patient_id FROM patient_information WHERE patient_id = ?");
    $checkPatient->bind_param("s", $patient_id);
    $checkPatient->execute();
    $result = $checkPatient->get_result();

    if ($result->num_rows === 0) {
        $errors[] = "Patient ID not found in records.";
    }
    $checkPatient->close();

    // Check if walk-in record exists
    $checkWalkin = $con->prepare("SELECT walkin_id, status FROM walkin_appointments WHERE walkin_id = ?");
    $checkWalkin->bind_param("s", $walkin_id);
    $checkWalkin->execute();
    $walkinResult = $checkWalkin->get_result();

    if ($walkinResult->num_rows === 0) {
        $errors[] = "Walk-in record not found.";
    }
    $checkWalkin->close();

    if (!empty($errors)) {
        ob_clean(); // Clear any output
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => implode("\n", $errors)
        ]);
        ob_end_flush();
        exit;
    }

    // Generate treatment ID
    $queryLast = $con->query("SELECT treatment_id FROM treatment_history ORDER BY treatment_id DESC LIMIT 1");
    if ($queryLast && $queryLast->num_rows > 0) {
        $row = $queryLast->fetch_assoc();
        $lastID = intval(substr($row['treatment_id'], 2)) + 1;
        $treatment_id = "TR" . str_pad($lastID, 4, "0", STR_PAD_LEFT);
    } else {
        $treatment_id = "TR0001";
    }

    $created_at = date('Y-m-d H:i:s');
    $updated_at = date('Y-m-d H:i:s');

    // Start transaction
    $con->begin_transaction();

    try {
        // Insert treatment history
        $insert = $con->prepare("INSERT INTO treatment_history 
            (treatment_id, patient_id, treatment, prescription_given, notes, treatment_cost, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$insert) {
            throw new Exception('Database prepare error: ' . $con->error);
        }

        $insert->bind_param(
            "sssssdss",
            $treatment_id,
            $patient_id,
            $treatment,
            $prescription_given,
            $notes,
            $treatment_cost,
            $created_at,
            $updated_at
        );

        if (!$insert->execute()) {
            throw new Exception('Failed to save treatment record: ' . $insert->error);
        }
        $insert->close();

        // Mark walk-in as completed
        $updateWalkin = $con->prepare("UPDATE walkin_appointments SET status = 'Completed' WHERE walkin_id = ?");
        if (!$updateWalkin) {
            throw new Exception('Database prepare error: ' . $con->error);
        }
        
        $updateWalkin->bind_param("s", $walkin_id);
        if (!$updateWalkin->execute()) {
            throw new Exception('Failed to update walk-in status: ' . $updateWalkin->error);
        }
        $updateWalkin->close();

        // Commit transaction
        $con->commit();
        
        ob_clean(); // Clear any output
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Treatment record saved successfully and walk-in marked as completed!',
            'patient_id' => $patient_id,
            'walkin_id' => $walkin_id
        ]);
        ob_end_flush();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $con->rollback();
        
        ob_clean(); // Clear any output
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        ob_end_flush();
    }

} else {
    ob_clean(); // Clear any output
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    ob_end_flush();
}
?>
