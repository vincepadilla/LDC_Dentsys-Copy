<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . "/../database/config.php");

// Auth: admin only
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit();
}
if (empty($_SESSION['admin_verified'])) {
	echo json_encode(['success' => false, 'message' => 'Admin verification required']);
	exit();
}

// Helpers
function generateUserId($con) {
	$res = mysqli_query($con, "SELECT user_id FROM user_account ORDER BY user_id DESC LIMIT 1");
	if ($res && mysqli_num_rows($res) > 0) {
		$row = mysqli_fetch_assoc($res);
		$lastID = intval(substr($row['user_id'], 1)) + 1;
		return "U" . str_pad($lastID, 4, "0", STR_PAD_LEFT);
	}
	return "U0001";
}
function generatePatientId($con) {
	$q = "SELECT patient_id FROM patient_information ORDER BY patient_id DESC LIMIT 1";
	$r = mysqli_query($con, $q);
	if ($r && ($row = mysqli_fetch_assoc($r)) && preg_match('/^P(\d+)$/', $row['patient_id'], $m)) {
		$next = intval($m[1]) + 1;
		return 'P' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
	}
	return 'P001';
}
function generateWalkInId($con) {
	$q = "SELECT walkin_id FROM walkin_appointments ORDER BY walkin_id DESC LIMIT 1";
	$r = mysqli_query($con, $q);
	if ($r && ($row = mysqli_fetch_assoc($r)) && !empty($row['walkin_id'])) {
		if (preg_match('/WI(\d+)/', $row['walkin_id'], $m)) {
			$n = intval($m[1]) + 1;
			return 'WI' . str_pad($n, 3, '0', STR_PAD_LEFT);
		}
	}
	return 'WI001';
}

// Input
$first_name   = trim($_POST['first_name']   ?? '');
$last_name    = trim($_POST['last_name']    ?? '');
$email        = trim($_POST['email']        ?? '');
$phone        = trim($_POST['phone']        ?? '');
$birthdate    = trim($_POST['birthdate']    ?? '');
$gender       = trim($_POST['gender']       ?? '');
$address      = trim($_POST['address']      ?? '');
$service      = trim($_POST['service']      ?? '');
$sub_service  = trim($_POST['sub_service']  ?? '');
$dentist_name = 'Dr. Michelle Landero';
$branch       = trim($_POST['branch']       ?? '');
$status       = trim($_POST['status']       ?? 'Walk-in');

// Basic validation
if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $birthdate === '' || $gender === '' || $address === '' ||
	$service === '' || $sub_service === '' || $branch === '' || $status === '') {
	echo json_encode(['success' => false, 'message' => 'All required fields must be provided.']);
	exit();
}

// Enforce unique email/phone in user_account
$emailEsc = mysqli_real_escape_string($con, $email);
$phoneEsc = mysqli_real_escape_string($con, $phone);
if (!preg_match('/^\d{11}$/', $phoneEsc)) {
	echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Phone number must be exactly 11 digits.']);
	exit();
}
$dupEmail = mysqli_query($con, "SELECT 1 FROM user_account WHERE email = '$emailEsc' LIMIT 1");
if ($dupEmail && mysqli_num_rows($dupEmail) > 0) {
	echo json_encode(['success' => false, 'field' => 'email', 'message' => 'Email is already registered.']);
	exit();
}
$dupPhone = mysqli_query($con, "SELECT 1 FROM user_account WHERE phone = '$phoneEsc' LIMIT 1");
if ($dupPhone && mysqli_num_rows($dupPhone) > 0) {
	echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Phone number is already registered.']);
	exit();
}

// Generate IDs
$new_user_id = generateUserId($con);
$new_patient_id = generatePatientId($con);
$new_walkin_id = generateWalkInId($con);

// Derive username from first name (ensure not null, and unique)
$base_username = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $first_name));
if ($base_username === '') {
	$base_username = 'user' . strtolower($new_user_id);
}
$username_try = $base_username;
$suffix = 0;
while (true) {
	$uEsc = mysqli_real_escape_string($con, $username_try);
	$chk = mysqli_query($con, "SELECT 1 FROM user_account WHERE username = '$uEsc' LIMIT 1");
	if ($chk && mysqli_num_rows($chk) === 0) break;
	$suffix++;
	$username_try = $base_username . $suffix;
}

// Sanitize for queries
$user_id_db   = mysqli_real_escape_string($con, $new_user_id);
$patient_id_db= mysqli_real_escape_string($con, $new_patient_id);
$walkin_id_db = mysqli_real_escape_string($con, $new_walkin_id);
$username_db  = mysqli_real_escape_string($con, $username_try);
$first_db     = mysqli_real_escape_string($con, $first_name);
$last_db      = mysqli_real_escape_string($con, $last_name);
$birth_db     = mysqli_real_escape_string($con, $birthdate);
$gender_db    = mysqli_real_escape_string($con, $gender);
$email_db     = $emailEsc;
$phone_db     = $phoneEsc;
$addr_db      = mysqli_real_escape_string($con, $address);
$service_db   = mysqli_real_escape_string($con, $service);
$sub_db       = mysqli_real_escape_string($con, $sub_service);
$dent_db      = mysqli_real_escape_string($con, $dentist_name);
$branch_db    = mysqli_real_escape_string($con, $branch);
$status_db    = mysqli_real_escape_string($con, $status);

// Start transaction for atomicity
mysqli_begin_transaction($con);
try {
	// 1) user_account
	$insertUser = "
		INSERT INTO user_account
			(user_id, role, status, last_login, username, first_name, last_name, birthdate, gender, address, password_hash, email, phone, contactNumber_verify, created_at)
		VALUES
			('$user_id_db', 'patient', 'active', NULL, '$username_db', '$first_db', '$last_db', '$birth_db', '$gender_db', '$addr_db', 'N/A', '$email_db', '$phone_db', 'N/A', NOW())
	";
	if (!mysqli_query($con, $insertUser)) {
		throw new Exception('Failed to create user account: ' . mysqli_error($con));
	}

	// 2) patient_information
	$insertPatient = "
		INSERT INTO patient_information
			(patient_id, user_id, first_name, last_name, birthdate, gender, phone, email, address)
		VALUES
			('$patient_id_db', '$user_id_db', '$first_db', '$last_db', '$birth_db', '$gender_db', '$phone_db', '$email_db', '$addr_db')
	";
	if (!mysqli_query($con, $insertPatient)) {
		throw new Exception('Failed to create patient: ' . mysqli_error($con));
	}

	// 3) walkin_appointments
	$insertWalkin = "
		INSERT INTO walkin_appointments
			(walkin_id, patient_id, service, sub_service, dentist_name, branch, status, created_at)
		VALUES
			('$walkin_id_db', '$patient_id_db', '$service_db', '$sub_db', '$dent_db', '$branch_db', '$status_db', NOW())
	";
	if (!mysqli_query($con, $insertWalkin)) {
		throw new Exception('Failed to create walk-in record: ' . mysqli_error($con));
	}

	mysqli_commit($con);

	echo json_encode([
		'success' => true,
		'message' => 'Walk-in record created successfully.',
		'data' => [
			'user_id' => $new_user_id,
			'patient_id' => $new_patient_id,
			'walkin_id' => $new_walkin_id
		]
	]);
	exit();
} catch (Exception $e) {
	mysqli_rollback($con);
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	exit();
}
?>
