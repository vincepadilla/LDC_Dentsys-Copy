<?php
include_once('../database/config.php'); // Make sure to include your database connection

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $team_id = trim($_POST['team_id']);
    $user_id = trim($_POST['user_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $specialization = trim($_POST['specialization']);
    $status = trim($_POST['status']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Validate required fields
    if (empty($team_id) || empty($first_name) || empty($last_name) || empty($specialization) || empty($email) || empty($phone) || empty($status)) {
        echo "<script>alert('All fields are required.'); window.history.back();</script>";
        exit;
    }

    // Start transaction to ensure both updates succeed or both fail
    $con->begin_transaction();

    try {
        // Update the multidisciplinary_dental_team table
        $updateTeamSql = "UPDATE multidisciplinary_dental_team SET 
                         first_name = ?, last_name = ?, specialization = ?, 
                         status = ?, email = ?, phone = ? 
                         WHERE team_id = ?";

        if ($stmt = $con->prepare($updateTeamSql)) {
            $stmt->bind_param("sssssss", $first_name, $last_name, $specialization, $status, $email, $phone, $team_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating team table: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Error preparing team update statement: " . $con->error);
        }

        // Update the users table if user_id is provided
        if (!empty($user_id)) {
            $updateUserSql = "UPDATE user_account SET 
                             first_name = ?, last_name = ?, email = ?, phone = ?,
                             first_name = CONCAT(?, ' ', ?)
                             WHERE user_id = ?";

            if ($stmt = $con->prepare($updateUserSql)) {
                $stmt->bind_param("sssssss", $first_name, $last_name, $email, $phone, $first_name, $last_name, $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating users table: " . $stmt->error);
                }
                $stmt->close();
            } else {
                throw new Exception("Error preparing user update statement: " . $con->error);
            }
        }

        // Commit transaction if all updates succeeded
        $con->commit();
        echo "<script>alert('Staff details updated successfully!'); window.location.href='../views/admin.php';</script>";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $con->rollback();
        echo "<script>alert('Error updating staff: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
}
?>
