<?php
session_start();
include_once('../database/config.php');

// Check if user is logged in (admin)
if (!isset($_SESSION['valid']) || $_SESSION['valid'] !== true) {
    header("Location: ../views/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get POST values (patient_id is VARCHAR like P026)
    $id = isset($_POST['patient_id']) ? trim($_POST['patient_id']) : '';
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Debug log
    error_log("updatePatient.php POST DATA: " . json_encode($_POST));

    // Validate patient ID (must not be empty)
    if (empty($id)) {
        echo "<script>
                alert('Invalid patient ID.');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    // Required field validation
    if (
        empty($first) || empty($last) || empty($birthdate) || 
        empty($gender) || empty($email) || empty($phone) || empty($address)
    ) {
        echo "<script>
                alert('All fields are required.');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
                alert('Invalid email format.');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    // Birthdate validation
    $birthdateTimestamp = strtotime($birthdate);
    if ($birthdateTimestamp === false || $birthdateTimestamp > time()) {
        echo "<script>
                alert('Invalid birthdate.');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    // Gender validation
    if (!in_array($gender, ['Male', 'Female'])) {
        echo "<script>
                alert('Invalid gender selected.');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    // Prepare UPDATE query
    $stmt = $con->prepare("
        UPDATE patient_information
        SET first_name=?, last_name=?, birthdate=?, gender=?, email=?, phone=?, address=?
        WHERE patient_id=?
    ");

    if (!$stmt) {
        echo "<script>
                alert('Database error: " . addslashes($con->error) . "');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    // IMPORTANT: use "ssssssss" (all strings)
    $stmt->bind_param("ssssssss",
        $first,
        $last,
        $birthdate,
        $gender,
        $email,
        $phone,
        $address,
        $id
    );

    // Execute update
    if ($stmt->execute()) {
        $stmt->close();
        echo "<script>
                alert('Patient updated successfully!');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        echo "<script>
                alert('Error updating patient: " . addslashes($error) . "');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

} else {
    header("Location: ../views/admin.php");
    exit();
}
?>
