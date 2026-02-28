<?php
include_once("../database/config.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $service_category = $_POST['service_category'] ?? null;
    $sub_service = $_POST['sub_service'] ?? null;
    $description = $_POST['description'] ?? null;
    $price = $_POST['price'] ?? null;

    if ($service_category && $sub_service && $description && is_numeric($price)) {

        // 1. Determine the prefix based on category
        switch ($service_category) {
            case "General Dentistry":
                $prefix = "S";
                $base_id = 0; // numeric part starts from 001
                break;
            case "Orthodontics":
                $prefix = "S2";
                $base_id = 0; // numeric part starts from 001
                break;
            case "Oral Surgery":
                $prefix = "S0"; // adjust if needed
                $base_id = 3; // example
                break;
            case "Endodontics":
                $prefix = "S0"; 
                $base_id = 4; 
                break;
            case "Prosthodontics Treatments (Pustiso)":
                $prefix = "S5"; 
                $base_id = 0; 
                break;
            default:
                $prefix = "S";
                $base_id = 0;
        }

        // 2. Get the last service_id for this category
        $query = "SELECT service_id FROM services WHERE service_category = ? ORDER BY service_id DESC LIMIT 1";
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $service_category);
        $stmt->execute();
        $stmt->bind_result($last_id);
        $stmt->fetch();
        $stmt->close();

        if ($last_id) {
            // Extract numeric part
            preg_match('/\d+$/', $last_id, $matches);
            $num = intval($matches[0]) + 1;
        } else {
            $num = $base_id + 1;
        }

        // Format the numeric part with leading zeros
        if ($service_category === "Prosthodontics Treatments (Pustiso)") {
            $new_id = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT); // e.g., S5001
        } else {
            $new_id = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT); // e.g., S001, S002
        }

        // 3. Insert into DB
        $stmt = $con->prepare("INSERT INTO services (service_id, service_category, sub_service, description, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $new_id, $service_category, $sub_service, $description, $price);

        if ($stmt->execute()) {
            echo "<script>alert('Service added successfully!'); window.location.href='../admin/services.php';</script>";
        } else {
            echo "<script>alert('Error adding service.'); window.history.back();</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('Please fill in all fields correctly.'); window.history.back();</script>";
    }
} else {
    header("Location: ../admin/services.php");
    exit();
}
?>
