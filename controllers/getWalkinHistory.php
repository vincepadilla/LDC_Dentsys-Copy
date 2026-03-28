<?php
header('Content-Type: application/json');
require_once(__DIR__ . "/../database/config.php");

$patientId = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
if ($patientId === '') {
	echo json_encode(['status' => 'error', 'message' => 'Missing patient_id']);
	exit();
}

$patientIdDb = mysqli_real_escape_string($con, $patientId);
$sql = "
	SELECT walkin_id, patient_id, service, sub_service, dentist_name, branch, status, created_at
	FROM walkin_appointments
	WHERE patient_id = '$patientIdDb'
	ORDER BY created_at DESC
";
$res = mysqli_query($con, $sql);
if (!$res) {
	echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
	exit();
}

$rows = [];
while ($row = mysqli_fetch_assoc($res)) {
	$rows[] = [
		'walkin_id'    => $row['walkin_id'] ?? null,
		'service'      => $row['service'] ?? null,
		'sub_service'  => $row['sub_service'] ?? null,
		'dentist_name' => $row['dentist_name'] ?? null,
		'branch'       => $row['branch'] ?? null,
		'status'       => $row['status'] ?? null,
		'created_at'   => $row['created_at'] ?? null,
	];
}

if (empty($rows)) {
	echo json_encode(['status' => 'empty', 'data' => []]);
	exit();
}

echo json_encode(['status' => 'success', 'data' => $rows]);
exit();
