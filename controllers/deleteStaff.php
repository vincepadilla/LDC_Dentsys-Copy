<?php
include 'config.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['team_id'])) {
    $team_id = $_POST['team_id'];

    // Prepare and execute delete query for multidisciplinary_dental_team
    $stmt = $con->prepare("DELETE FROM multidisciplinary_dental_team WHERE team_id = ?");
    $stmt->bind_param("s", $team_id);

    if ($stmt->execute()) {
        echo "<script>
            alert('Staff deleted successfully!');
            window.location.href = 'admin.php';
        </script>";
        exit();
    } else {
        echo "<script>
            alert('Error deleting staff.');
            window.location.href = 'admin.php';
        </script>";
    }
} else {
    echo "<script>
        alert('Invalid request.');
        window.location.href = 'admin.php';
    </script>";
}
?>
