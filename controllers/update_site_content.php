<?php
header('Content-Type: application/json');
session_start();
require_once(__DIR__ . "/../database/config.php");

$resp = ['success' => false, 'updated' => 0, 'message' => ''];

// Require authenticated admin/super-admin; keep behavior strict but do not redirect for JSON
$role = isset($_SESSION['role']) ? strtolower((string)$_SESSION['role']) : '';
if (!isset($_SESSION['userID']) || !in_array($role, ['admin', 'super-admin'], true)) {
	echo json_encode($resp);
	exit;
}

// Create table if missing (safe-guard)
@mysqli_query($con, "CREATE TABLE IF NOT EXISTS site_content (
	content_id INT AUTO_INCREMENT PRIMARY KEY,
	content_key VARCHAR(100) UNIQUE NOT NULL,
	content_value TEXT,
	content_type VARCHAR(50) DEFAULT 'text',
	section VARCHAR(50) DEFAULT 'general',
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode($resp);
	exit;
}

// Accept application/json or form-encoded
$payload = $_POST;
if (empty($payload)) {
	$raw = file_get_contents('php://input');
	if ($raw) {
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			$payload = $decoded;
		}
	}
}

$allowedKeys = [
	'hero_title', 'hero_subtitle',
	'services_title', 'services_subtitle',
	'contact_title', 'contact_subtitle', 'contact_help_title', 'contact_help_text',
	'contact_hours', 'contact_phone', 'contact_email',
	'location_title', 'location_subtitle', 'location_comembo', 'location_taytay',
	'dentist_title', 'dentist_subtitle', 'dentist_name', 'dentist_specialty', 'dentist_experience',
	'announcement_text'
];

// Load current content for change detection
$current = [];
if (count($allowedKeys) > 0) {
	$placeholders = implode(',', array_fill(0, count($allowedKeys), '?'));
	$sql = "SELECT content_key, content_value FROM site_content WHERE content_key IN ($placeholders)";
	if ($stmt = $con->prepare($sql)) {
		$types = str_repeat('s', count($allowedKeys));
		$stmt->bind_param($types, ...$allowedKeys);
		$stmt->execute();
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$current[$row['content_key']] = stripslashes((string)$row['content_value']);
		}
		$stmt->close();
	}
}

$updated = 0;
$stmt = $con->prepare("INSERT INTO site_content (content_key, content_value, content_type, section)
	VALUES (?, ?, 'text', ?)
	ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), section = VALUES(section), updated_at = CURRENT_TIMESTAMP");

if ($stmt) {
	foreach ($allowedKeys as $key) {
		if (!array_key_exists($key, $payload)) continue;
		$value = trim((string)$payload[$key]);

		// Skip if unchanged
		$cur = isset($current[$key]) ? trim((string)$current[$key]) : null;
		if ($cur !== null && $cur === $value) continue;

		$section = 'general';
		if (strpos($key, 'hero') !== false) $section = 'hero';
		elseif (strpos($key, 'service') !== false) $section = 'services';
		elseif (strpos($key, 'contact') !== false) $section = 'contact';
		elseif (strpos($key, 'location') !== false) $section = 'location';
		elseif (strpos($key, 'dentist') !== false) $section = 'dentist';

		$stmt->bind_param('sss', $key, $value, $section);
		if ($stmt->execute()) {
			$updated++;
		}
	}
	$stmt->close();
}

$resp['success'] = true;
$resp['updated'] = $updated;
$resp['message'] = $updated > 0 ? 'Content updated' : 'No changes';
echo json_encode($resp);
exit;
?>
