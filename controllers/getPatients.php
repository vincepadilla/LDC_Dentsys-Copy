<?php
include("../database/config.php");
header('Content-Type: application/json');

if(isset($_GET['patient_id'])) {
    $patient_id = mysqli_real_escape_string($con, $_GET['patient_id']);
    
    error_log("Fetching patient with ID: " . $patient_id);
    
    if(empty($patient_id)) {
        echo json_encode(['error' => 'Empty patient ID provided']);
        exit;
    }
    
    $query = "SELECT patient_id, first_name, last_name, birthdate, gender, email, phone, address 
              FROM patient_information WHERE patient_id = ?";
    $stmt = mysqli_prepare($con, $query);
    
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($con));
        echo json_encode(['error' => 'Database query preparation failed']);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $patient_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        echo json_encode(['error' => 'Database query execution failed']);
        mysqli_stmt_close($stmt);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);

    if($row = mysqli_fetch_assoc($result)) {
        error_log("Found patient: " . json_encode($row));
        echo json_encode($row);
    } else {
        error_log("No patient found with ID: " . $patient_id);
        echo json_encode(['error' => 'Patient not found']);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['error' => 'No patient ID provided']);
}

mysqli_close($con);
?>