<?php
include_once('config.php'); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $userId         = trim($_POST['userid']);
    $firstName      = trim($_POST['first_name']);
    $lastName       = trim($_POST['last_name']);
    $specialization = trim($_POST['specialization']);
    $email          = trim($_POST['email']);
    $phone          = trim($_POST['phone']);
    $status         = trim($_POST['status']);

    // Validate required fields
    if (empty($userId) || empty($firstName) || empty($lastName) || empty($specialization) || empty($email) || empty($phone) || empty($status)) {
        echo "<script>alert('All fields are required.'); window.history.back();</script>";
        exit;
    }

    $getID = $con->query("SELECT team_id FROM multidisciplinary_dental_team ORDER BY team_id DESC LIMIT 1");

    if ($getID->num_rows > 0) {
        $row = $getID->fetch_assoc();
        $lastID = $row['team_id'];      
        $num = intval(substr($lastID, 1)); 
        $newNum = $num + 1;
        $newID = "T" . str_pad($newNum, 3, "0", STR_PAD_LEFT); // → T006
    } else {
        
        $newID = "T001";
    }

    $stmt = $con->prepare("INSERT INTO multidisciplinary_dental_team (team_id, user_id, first_name, last_name, specialization, email, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("ssssssss", $newID, $userId, $firstName, $lastName, $specialization, $email, $phone, $status);

        if ($stmt->execute()) {
            echo "<script>alert('Dentist added successfully!'); window.location.href='admin.php';</script>";
        } else {
            echo "<script>alert('Failed to add dentist.'); window.history.back();</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('Database error.'); window.history.back();</script>";
    }

    $con->close();
}
?>
