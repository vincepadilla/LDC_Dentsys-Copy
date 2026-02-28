<?php
include_once('../database/config.php');

// Check if the service_id is sent via POST
if (isset($_POST['service_id'])) {
    $service_id = trim($_POST['service_id']);  // Sanitize service_id (VARCHAR like "S001")

    // SQL to delete the service
    $sql = "DELETE FROM services WHERE service_id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $service_id);

    if ($stmt->execute()) {
        // Display success alert and redirect to services.php
        echo "<script>
                alert('Service deleted successfully!');
                window.location.href = '../admin/services.php';
              </script>";
        exit();
    } else {
        // Display error alert and redirect to services.php
        echo "<script>
                alert('Error deleting service. Please try again.');
                window.location.href = '../admin/services.php';
              </script>";
        exit();
    }
} else {
    // Redirect back if service_id is not provided
    echo "<script>
            alert('No service ID provided.');
            window.location.href = '../admin/services.php';
          </script>";
    exit();
}
?>
