<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . "/../database/config.php");

if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit();
}

if (empty($_SESSION['admin_verified'])) {
	echo json_encode(['success' => false, 'message' => 'Admin verification required']);
	exit();
}

function generatePatientID($con) {
	$query = "SELECT patient_id FROM patient_information ORDER BY patient_id DESC LIMIT 1";
	$result = mysqli_query($con, $query);
	if ($result && ($row = mysqli_fetch_assoc($result)) && preg_match('/^P(\d+)$/', $row['patient_id'], $m)) {
		$next = intval($m[1]) + 1;
		return 'P' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
	}
	return 'P001';
}

// Collect and sanitize input
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name  = isset($_POST['last_name'])  ? trim($_POST['last_name'])  : '';
$birthdate  = isset($_POST['birthdate'])  ? trim($_POST['birthdate'])  : '';
$gender     = isset($_POST['gender'])     ? trim($_POST['gender'])     : '';
$phone      = isset($_POST['phone'])      ? trim($_POST['phone'])      : '';
$email      = isset($_POST['email'])      ? trim($_POST['email'])      : '';
$address    = isset($_POST['address'])    ? trim($_POST['address'])    : '';

if ($first_name === '' || $last_name === '' || $birthdate === '' || $gender === '' || $phone === '' || $email === '' || $address === '') {
	echo json_encode(['success' => false, 'message' => 'All required fields must be provided.']);
	exit();
}

// Duplicate validation: email and phone must be unique (check user_account)
$emailEscaped = mysqli_real_escape_string($con, $email);
$phoneEscaped = mysqli_real_escape_string($con, $phone);

$dupEmailRes = mysqli_query($con, "SELECT user_id FROM user_account WHERE email = '$emailEscaped' LIMIT 1");
if ($dupEmailRes && mysqli_num_rows($dupEmailRes) > 0) {
	echo json_encode(['success' => false, 'field' => 'email', 'message' => 'Email is already registered.']);
	exit();
}
$dupPhoneRes = mysqli_query($con, "SELECT user_id FROM user_account WHERE phone = '$phoneEscaped' LIMIT 1");
if ($dupPhoneRes && mysqli_num_rows($dupPhoneRes) > 0) {
	echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Phone number is already registered.']);
	exit();
}

// Generate a new user_id like register.php (U0001, U0002, ...)
$userIdResult = mysqli_query($con, "SELECT user_id FROM user_account ORDER BY user_id DESC LIMIT 1");
if ($userIdResult && mysqli_num_rows($userIdResult) > 0) {
	$row = mysqli_fetch_assoc($userIdResult);
	$lastID = intval(substr($row['user_id'], 1)) + 1;
	$new_user_id = "U" . str_pad($lastID, 4, "0", STR_PAD_LEFT);
} else {
	$new_user_id = "U0001";
}

// Derive a username for admin-added patients (use email if available; fallback to first.last plus suffix)
// Requirement: username should be like her first name when admin adds patient
$base_username = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $first_name));
if ($base_username === '') {
	$base_username = 'user' . strtolower($new_user_id);
}
// Ensure uniqueness by appending numeric suffix if necessary
$username_clean = $base_username;
$suffix = 0;
while (true) {
	$checkU = mysqli_query($con, "SELECT 1 FROM user_account WHERE username = '" . mysqli_real_escape_string($con, $username_clean) . "' LIMIT 1");
	if ($checkU && mysqli_num_rows($checkU) === 0) {
		break;
	}
	$suffix++;
	$username_clean = $base_username . $suffix;
}

// Prepare user_account insert
$new_user_id_db = mysqli_real_escape_string($con, $new_user_id);
$username_db    = mysqli_real_escape_string($con, $username_clean);
$first_name_uc  = mysqli_real_escape_string($con, $first_name);
$last_name_uc   = mysqli_real_escape_string($con, $last_name);
$birthdate_uc   = mysqli_real_escape_string($con, $birthdate);
$gender_uc      = mysqli_real_escape_string($con, $gender);
$address_uc     = mysqli_real_escape_string($con, $address);
$email_uc       = mysqli_real_escape_string($con, $email);
$phone_uc       = mysqli_real_escape_string($con, $phone);

// Insert into user_account first; minimal required fields, using 'patient' role
$insertUserSql = "
	INSERT INTO user_account
		(user_id, role, status, last_login, username, first_name, last_name, birthdate, gender, address, password_hash, email, phone, contactNumber_verify, created_at)
	VALUES
		('$new_user_id_db', 'patient', 'active', NULL, '$username_db', '$first_name_uc', '$last_name_uc', '$birthdate_uc', '$gender_uc', '$address_uc', 'N/A', '$email_uc', '$phone_uc', 'N/A', NOW())
";

if (!mysqli_query($con, $insertUserSql)) {
	$error = mysqli_error($con);
	echo json_encode(['success' => false, 'message' => 'Failed to create user account: ' . $error]);
	exit();
}

$patient_id = generatePatientID($con);

$patient_id_db = mysqli_real_escape_string($con, $patient_id);
$first_name_db = mysqli_real_escape_string($con, $first_name);
$last_name_db  = mysqli_real_escape_string($con, $last_name);
$birthdate_db  = mysqli_real_escape_string($con, $birthdate);
$gender_db     = mysqli_real_escape_string($con, $gender);
$phone_db      = mysqli_real_escape_string($con, $phone);
$email_db      = mysqli_real_escape_string($con, $email);
$address_db    = mysqli_real_escape_string($con, $address);

$sql = "
	INSERT INTO patient_information
		(patient_id, user_id, first_name, last_name, birthdate, gender, phone, email, address)
	VALUES
		('$patient_id_db', '$new_user_id_db', '$first_name_db', '$last_name_db', '$birthdate_db', '$gender_db', '$phone_db', '$email_db', '$address_db')
";

if (!mysqli_query($con, $sql)) {
	$error = mysqli_error($con);
	// Best effort rollback of user_account (if FK fails, etc.)
	mysqli_query($con, "DELETE FROM user_account WHERE user_id = '$new_user_id_db' LIMIT 1");
	echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
	exit();
}

echo json_encode([
	'success' => true,
	'message' => 'Patient created successfully.',
	'record'  => [
		'patient_id' => $patient_id,
		'user_id'    => $new_user_id,
		'first_name' => $first_name,
		'last_name'  => $last_name,
		'birthdate'  => $birthdate,
		'gender'     => $gender,
		'phone'      => $phone,
		'email'      => $email,
		'address'    => $address
	]
]);
exit();
