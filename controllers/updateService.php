<?php
include_once('../database/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['service_id']);
    $category = trim($_POST['service_category']);
    $sub_service = trim($_POST['sub_service']);
    $desc = trim($_POST['description']);
    $price = (float)$_POST['price'];

    // Use prepared statement for security
    $query = "UPDATE services SET 
              service_category = ?, 
              sub_service = ?, 
              description = ?, 
              price = ? 
              WHERE service_id = ?";
    
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "sssds", $category, $sub_service, $desc, $price, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Service updated successfully'); window.location.href='../admin/services.php';</script>";
    } else {
        echo "<script>alert('Error updating service: " . mysqli_error($con) . "'); window.location.href='../admin/services.php';</script>";
    }

    mysqli_stmt_close($stmt);
    mysqli_close($con);
}
?>