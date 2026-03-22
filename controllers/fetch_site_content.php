<?php
header('Content-Type: application/json');
session_start();
require_once(__DIR__ . "/../database/config.php");
require_once(__DIR__ . "/../includes/site_content.php");

$response = [
	'success' => false,
	'data' => [],
];

try {
	$content = getSiteContent($con);

	// Provide safe defaults so UI never breaks if DB empty
	$defaults = [
		'hero_title' => 'Your Smile Deserves the Best Care',
		'hero_subtitle' => 'Professional dental care in a comfortable and friendly environment',
		'services_title' => 'Our Services',
		'services_subtitle' => 'Comprehensive dental care for the whole family',
		'announcement_text' => '',
		'contact_title' => 'Contact Us',
		'contact_subtitle' => 'Send us a message about appointments, services, or any other concerns about us.',
		'contact_help_title' => 'We\'re here to help',
		'contact_help_text' => 'Call us, send an email, or use the form to send your questions and we\'ll get back to you as soon as possible.',
		'contact_hours' => 'Mon - Sun: 8:00 AM - 8:00 PM',
		'contact_phone' => '0922 861 1987',
		'contact_email' => 'landerodentalclinic@gmail.com',
		'location_title' => 'Visit Our Clinics',
		'location_subtitle' => 'Find us in Comembo, Taguig City or Taytay, Rizal. Use the map and contact details below for easy navigation.',
		'location_comembo' => 'Anahaw St. Comembo, Taguig City',
		'location_taytay' => 'Lot 5 Block 2, Turquoise Corner, Golden City Subd, Amber, Dolores, Taytay, 1920 Rizal',
		'dentist_title' => 'Our Dentist',
		'dentist_subtitle' => 'Meet Our Professional Dentist',
		'dentist_name' => 'Dr. Michelle Landero',
		'dentist_specialty' => 'Dentist',
		'dentist_experience' => 'With over 10 years of experience in providing exceptional dental care.'
	];

	foreach ($defaults as $k => $v) {
		if (!isset($content[$k]) || $content[$k] === '') {
			$content[$k] = $v;
		}
	}

	$response['success'] = true;
	$response['data'] = $content;
	echo json_encode($response);
	exit;
} catch (Throwable $e) {
	// Do not expose internal errors
	echo json_encode($response);
	exit;
}
?>
