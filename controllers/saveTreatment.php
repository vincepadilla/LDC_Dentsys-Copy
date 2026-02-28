<?php
// Start output buffering to prevent any accidental output
ob_start();

// Suppress errors and warnings that might output HTML
error_reporting(0);
ini_set('display_errors', 0);

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
    $appointment_id = trim($_POST['appointment_id'] ?? '');

    $errors = [];
    if (empty($patient_id)) $errors[] = "Patient ID is required.";
    if (empty($treatment)) $errors[] = "Treatment type is required.";
    if (empty($prescription_given)) $errors[] = "Prescription is required.";
    if (empty($notes)) $errors[] = "Treatment notes are required.";
    if ($treatment_cost === '' || !is_numeric($treatment_cost) || $treatment_cost < 0) {
        $errors[] = "Please enter a valid treatment cost.";
    }

    $checkPatient = $con->prepare("SELECT patient_id FROM patient_information WHERE patient_id = ?");
    $checkPatient->bind_param("s", $patient_id);
    $checkPatient->execute();
    $result = $checkPatient->get_result();

    if ($result->num_rows === 0) {
        $errors[] = "Patient ID not found in records.";
    }
    $checkPatient->close();

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

    $insert = $con->prepare("INSERT INTO treatment_history 
        (treatment_id, patient_id, treatment, prescription_given, notes, treatment_cost, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$insert) {
        ob_clean(); // Clear any output
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Database prepare error: ' . $con->error
        ]);
        ob_end_flush();
        exit;
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

    // 🧾 Execute and validate
    if ($insert->execute()) {

        // 🦷 Optional: Mark appointment as complete if provided
        if (!empty($appointment_id)) {
            $updateStatus = $con->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?");
            $updateStatus->bind_param("s", $appointment_id);
            $updateStatus->execute();
            $updateStatus->close();
        }

        $insert->close();
        
        ob_clean(); // Clear any output
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Treatment record saved successfully!',
            'patient_id' => $patient_id,
            'appointment_id' => $appointment_id
        ]);
        ob_end_flush();
    } else {
        $error_message = $insert->error ? $insert->error : 'Database execution failed';
        $insert->close();
        
        ob_clean(); // Clear any output
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to save treatment record: ' . $error_message
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
