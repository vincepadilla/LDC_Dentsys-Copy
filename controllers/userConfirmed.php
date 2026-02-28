<?php
include_once("config.php");

if (isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);

    $stmt = $con->prepare("UPDATE tbl_appointments SET status = 'Confirmed' WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);

    if ($stmt->execute()) {
        echo '<script>
        alert("Confirmed successfully!");
        window.location.href = "account.php?message=Schedule+confirmed";
        </script>';
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $con->close();
} else {
    header("Location: account.php");
    exit();
}
