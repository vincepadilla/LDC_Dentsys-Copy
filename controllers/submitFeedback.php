<?php
session_start();
include_once("config.php");

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userID'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: account.php");
    exit();
}

// Get form data
$feedback_text = trim($_POST['feedback_text'] ?? '');
$appointment_id = $_POST['appointment_id'] ?? null;

// Validate feedback text
if (empty($feedback_text)) {
    $_SESSION['feedback_error'] = "Feedback text is required.";
    header("Location: account.php");
    exit();
}

if (strlen($feedback_text) < 10) {
    $_SESSION['feedback_error'] = "Feedback must be at least 10 characters long.";
    header("Location: account.php");
    exit();
}

if (strlen($feedback_text) > 500) {
    $_SESSION['feedback_error'] = "Feedback must not exceed 500 characters.";
    header("Location: account.php");
    exit();
}

// Check if user already has feedback
$check_feedback = $con->prepare("SELECT feedback_id FROM feedback WHERE user_id = ?");
$check_feedback->bind_param("s", $user_id);
$check_feedback->execute();
$check_result = $check_feedback->get_result();

if ($check_result->num_rows > 0) {
    $_SESSION['feedback_error'] = "You have already posted feedback. Each user can only post one feedback.";
    header("Location: account.php");
    exit();
}
$check_feedback->close();

// Get user's name
$user_query = $con->prepare("SELECT first_name, last_name FROM user_account WHERE user_id = ?");
$user_query->bind_param("s", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();
$user_query->close();

$patient_name = ($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? '');

// Insert feedback with pending status (needs admin approval)
$insert_feedback = $con->prepare("
    INSERT INTO feedback (user_id, patient_name, feedback_text, appointment_id, status) 
    VALUES (?, ?, ?, ?, 'pending')
");
$insert_feedback->bind_param("sssi", $user_id, $patient_name, $feedback_text, $appointment_id);

if ($insert_feedback->execute()) {
    $_SESSION['feedback_success'] = "Thank you! Your feedback has been submitted and is pending approval. It will be displayed on our website once approved.";
} else {
    $_SESSION['feedback_error'] = "Error posting feedback. Please try again.";
}

$insert_feedback->close();
header("Location: account.php");
exit();

