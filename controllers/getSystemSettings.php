<?php
// Returns system settings as JSON for real-time UI updates (safe + cache-busted by clients)
header('Content-Type: application/json');

require_once(__DIR__ . '/../database/config.php');
require_once(__DIR__ . '/../includes/system_settings.php');

$settings = getSystemSettings($con);

// Provide typed projection with safe fallbacks for convenience in the client
$response = [
	'settings' => $settings,
	'typed' => [
		'advance_booking_limit' => toIntSetting(getSetting($settings, 'advance_booking_limit', 30), 30),
		'appointment_slot_duration' => toIntSetting(getSetting($settings, 'appointment_slot_duration', 60), 60),
		'max_appointments_per_day' => toIntSetting(getSetting($settings, 'max_appointments_per_day', 0), 0),
		'walk_ins_enabled' => toBoolSetting(getSetting($settings, 'walk_ins_enabled', '1')),
		'maintenance_mode' => toBoolSetting(getSetting($settings, 'maintenance_mode', '0')),
		'reservation_fee_amount' => toFloatSetting(getSetting($settings, 'reservation_fee_amount', 500), 500.0),
		'gcash_enabled' => toBoolSetting(getSetting($settings, 'gcash_enabled', '1')),
		'maya_enabled' => toBoolSetting(getSetting($settings, 'maya_enabled', '1')),
	],
	'ts' => time(),
];

echo json_encode($response);
exit;

