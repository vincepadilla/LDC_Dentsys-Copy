<?php
// Lightweight, reusable system settings loader
// Safe-by-default: falls back to provided defaults and never throws to the user

if (!function_exists('getSystemSettings')) {
	/**
	 * Load all settings from `system_settings` table into an associative array.
	 * Returns [setting_key => setting_value]. Safe fallback to empty array on any failure.
	 *
	 * @param mysqli $con
	 * @return array<string, string>
	 */
	function getSystemSettings($con) {
		$settings = [];

		// Ensure we have a valid mysqli connection object
		if (!isset($con) || !($con instanceof mysqli)) {
			return $settings;
		}

		// Check if table exists first to avoid errors on fresh installs
		$check = @mysqli_query($con, "SHOW TABLES LIKE 'system_settings'");
		if (!$check || mysqli_num_rows($check) === 0) {
			return $settings;
		}

		$query = "SELECT setting_key, setting_value FROM system_settings";
		$result = @mysqli_query($con, $query);
		if ($result) {
			while ($row = mysqli_fetch_assoc($result)) {
				$key = isset($row['setting_key']) ? (string)$row['setting_key'] : '';
				if ($key === '') {
					continue;
				}
				$settings[$key] = (string)($row['setting_value'] ?? '');
			}
			mysqli_free_result($result);
		}

		return $settings;
	}
}

if (!function_exists('getSetting')) {
	/**
	 * Get a specific setting from the loaded settings with a default fallback.
	 *
	 * @param array<string, string> $settings
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	function getSetting(array $settings, $key, $default = null) {
		if (!array_key_exists($key, $settings)) {
			return $default;
		}
		return $settings[$key];
	}
}

if (!function_exists('toBoolSetting')) {
	/**
	 * Cast a stringly-typed setting to boolean.
	 * Accepts: "1","true","on","yes" (case-insensitive) as true. Everything else false.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	function toBoolSetting($value) {
		if (is_bool($value)) return $value;
		$normalized = strtolower(trim((string)$value));
		return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
	}
}

if (!function_exists('toIntSetting')) {
	/**
	 * Cast a setting to int with safe fallback.
	 *
	 * @param mixed $value
	 * @param int $default
	 * @return int
	 */
	function toIntSetting($value, $default = 0) {
		if ($value === null || $value === '') return $default;
		if (is_numeric($value)) return (int)$value;
		return $default;
	}
}

if (!function_exists('toFloatSetting')) {
	/**
	 * Cast a setting to float with safe fallback.
	 *
	 * @param mixed $value
	 * @param float $default
	 * @return float
	 */
	function toFloatSetting($value, $default = 0.0) {
		if ($value === null || $value === '') return $default;
		if (is_numeric($value)) return (float)$value;
		return $default;
	}
}

