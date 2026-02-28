<?php
include("../database/config.php");

header('Content-Type: application/json');

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);
    
    error_log("Fetching service with ID: " . $id);
    
    // Remove intval() since IDs are VARCHAR like "S001"
    if(empty($id)) {
        echo json_encode(['error' => 'Empty ID provided']);
        exit;
    }
    
    // Use prepared statement with "s" for string instead of "i" for integer
    $query = "SELECT service_id, service_category, sub_service, description, price 
              FROM services WHERE service_id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "s", $id);  // "s" for string, not "i" for integer
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if($row = mysqli_fetch_assoc($result)) {
        error_log("Found service: " . json_encode($row));
        echo json_encode($row);
    } else {
        error_log("No service found with ID: " . $id);
        echo json_encode(['error' => 'Service not found']);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['error' => 'No ID provided']);
}

mysqli_close($con);
?>