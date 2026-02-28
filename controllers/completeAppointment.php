<?php
include_once("config.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {
    $appointment_id = intval($_POST['appointment_id']);

    $stmt = $con->prepare("UPDATE tbl_appointments SET status = 'Completed' WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);

    if ($stmt->execute()) {
        echo '<script>
        alert("Appointment has Mark as Completed!");
        window.location.href = "admin.php?message=Appointment+completed";
        </script>';
        exit();

    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $con->close();
} else {
    header("Location: admin.php");
    exit();
}