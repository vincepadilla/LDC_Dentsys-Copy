<?php
// Safe, reusable site content helpers loaded across views
// Returns associative array: [content_key => content_value]
if (!function_exists('getSiteContent')) {
	function getSiteContent($con) {
		$content = [];

		if (!isset($con) || !($con instanceof mysqli)) {
			return $content;
		}

		// Ensure table exists, but do not error to the user
		@mysqli_query($con, "CREATE TABLE IF NOT EXISTS site_content (
			content_id INT AUTO_INCREMENT PRIMARY KEY,
			content_key VARCHAR(100) UNIQUE NOT NULL,
			content_value TEXT,
			content_type VARCHAR(50) DEFAULT 'text',
			section VARCHAR(50) DEFAULT 'general',
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$res = @mysqli_query($con, "SELECT content_key, content_value FROM site_content");
		if ($res) {
			while ($row = mysqli_fetch_assoc($res)) {
				$key = isset($row['content_key']) ? (string)$row['content_key'] : '';
				if ($key === '') continue;
				$content[$key] = stripslashes((string)($row['content_value'] ?? ''));
			}
			mysqli_free_result($res);
		}

		return $content;
	}
}

// Get a specific content value with a default fallback
if (!function_exists('getContent')) {
	function getContent(array $content, $key, $default = '') {
		if (!array_key_exists($key, $content) || $content[$key] === '' || $content[$key] === null) {
			return $default;
		}
		return $content[$key];
	}
}
?>
