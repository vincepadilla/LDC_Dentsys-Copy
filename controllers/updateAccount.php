<?php
session_start();
include_once("../database/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action.']);
    exit();
}

$userID = $_SESSION['userID'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get POST values, sanitize
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthdate = $_POST['birthdate'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $address = trim($_POST['address'] ?? '');

    // Update users table
    $stmtUser = $con->prepare("UPDATE user_account SET username = ?, first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?");
    $stmtUser->bind_param("ssssss", $username, $first_name, $last_name, $email, $phone, $userID);
    $updateUser = $stmtUser->execute();
    $stmtUser->close();

    // Update patient_information table
    $stmtPatient = $con->prepare("UPDATE patient_information SET birthdate = ?, gender = ?, address = ?, email = ?, first_name = ?, last_name = ? WHERE user_id = ?");
    $stmtPatient->bind_param("sssssss", $birthdate, $gender, $address, $email, $first_name, $last_name, $userID);
    $updatePatient = $stmtPatient->execute();
    $stmtPatient->close();

    if ($updateUser && $updatePatient) {
        echo json_encode(['success' => true, 'message' => 'Account updated successfully']);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update account. Please try again.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
