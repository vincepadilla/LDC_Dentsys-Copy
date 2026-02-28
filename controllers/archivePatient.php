<?php
session_start();
include_once('../database/config.php');

// Check if user is logged in (admin)
if (!isset($_SESSION['valid']) || $_SESSION['valid'] !== true) {
    header("Location: ../views/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id'])) {
    $patient_id = isset($_POST['patient_id']) ? trim($_POST['patient_id']) : '';

    // Validate patient ID (patient_id is VARCHAR like "P026")
    if (empty($patient_id)) {
        echo "<script>
                alert('Invalid patient ID.');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    // Prepare and execute delete query for patient_information
    $stmt = $con->prepare("DELETE FROM patient_information WHERE patient_id = ?");
    
    if (!$stmt) {
        echo "<script>
                alert('Database error: Failed to prepare statement. " . addslashes($con->error) . "');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }

    $stmt->bind_param("s", $patient_id);

    if ($stmt->execute()) {
        $stmt->close();
        echo "<script>
                alert('Patient archived successfully!');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        echo "<script>
                alert('Error archiving patient: " . addslashes($error) . "');
                window.location.href = '../views/admin.php';
              </script>";
        exit();
    }
} else {
    echo "<script>
            alert('Invalid request.');
            window.location.href = 'admin.php';
          </script>";
    exit();
}
?>

