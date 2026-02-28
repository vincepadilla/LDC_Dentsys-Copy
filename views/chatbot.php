<?php
// Start session to track chatbot state and logged-in users
session_start();
header('Content-Type: application/json');

// Get message from user
$data = json_decode(file_get_contents('php://input'), true);
$message = strtolower(trim($data['message'] ?? ''));

// Retrieve or initialize conversation state
$state = $_SESSION['chat_state'] ?? 'start';
$isLoggedIn = isset($_SESSION['user_id']); // check if logged in
$userName = $_SESSION['name'] ?? 'Patient';

// Default response
$response = [
    'reply' => "I'm sorry, I didn't understand that.",
    'state' => $state
];

// --- MAIN CHATBOT LOGIC ---
switch ($state) {
    case 'start':
        if (str_contains($message, 'hello') || str_contains($message, 'hi')) {
            $response['reply'] = "Hello $userName! 👋 Welcome to the Dental Clinic. How can I assist you today? You can ask about our services, hours, or how to book an appointment.";
        }
        elseif (str_contains($message, 'hours') || str_contains($message, 'open')) {
            $response['reply'] = "🕒 Our clinic hours are:\nMon–Fri: 9:00 AM - 6:00 PM\nSat: 10:00 AM - 4:00 PM\nSun: Closed.";
        }
        elseif (str_contains($message, 'location') || str_contains($message, 'address')) {
            $response['reply'] = "📍 We are located at 123 Dental Street, Smileytown, Philippines.";
        }
        elseif (str_contains($message, 'appointment') || str_contains($message, 'book')) {
            // Ask whether new or existing
            $response['reply'] = "Sure! To proceed with booking, may I know if you are a *new* or *existing* patient?";
            $response['state'] = 'awaiting_patient_type';
        }
        elseif (
            str_contains($message, 'emergency') || 
            str_contains($message, 'pain') || 
            str_contains($message, 'toothache') || 
            str_contains($message, 'broken') ||
            str_contains($message, 'bleeding') ||
            str_contains($message, 'swelling') ||
            str_contains($message, 'swollen') ||
            str_contains($message, 'abscess')
        ) {
            $response['reply'] = "I understand you're having an issue. To help me triage, what is your primary symptom?\n1️⃣ Pain\n2️⃣ Bleeding\n3️⃣ Swelling\n4️⃣ Broken/Chipped Tooth\n5️⃣ Other";
            $response['state'] = 'awaiting_triage_symptom';
        }
        break;

    // --- PATIENT TYPE HANDLING ---
    case 'awaiting_patient_type':
        if (str_contains($message, 'new')) {
            $response['reply'] = "🦷 Welcome, new patient!\n\nBefore booking, please register and log in to complete your patient details. "
                               . "You’ll also need to pay a small consultation fee as a *down payment* to confirm your booking and prevent no-shows.\n\n"
                               . "👉 You need to register first and login\nOnce logged in, you can book directly through our Appointment Page.";
            $response['state'] = 'start';
        }
        elseif (str_contains($message, 'existing')) {
            if ($isLoggedIn) {
                $response['reply'] = "Welcome back, $userName! 😄\nYou can book your next appointment directly here";
                $response['state'] = 'start';
            } else {
                $response['reply'] = "Welcome back! Please log in first so we can load your patient details and let you book an appointment.";
                $response['state'] = 'start';
            }
        }
        else {
            $response['reply'] = "Please let me know if you are a 'new' or 'existing' patient.";
            $response['state'] = 'awaiting_patient_type';
        }
        break;

    // --- SERVICES ---
    case 'awaiting_service':
        $service = htmlspecialchars($message);
        $response['reply'] = "Great! We’ve noted your request for a {$service}. Our staff will contact you shortly to confirm your schedule. 😊";
        $response['state'] = 'start';
        break;

    // --- TRIAGE QUESTIONS ---
    case 'awaiting_triage_symptom':
        if (str_contains($message, '1') || str_contains($message, 'pain')) {
            $response['reply'] = "On a scale of 1 (mild) to 10 (severe), how would you rate your pain?";
            $response['state'] = 'awaiting_pain_level';
        }
        elseif (str_contains($message, '2') || str_contains($message, 'bleeding')) {
            $response['reply'] = "Is the bleeding *continuous* or *uncontrollable*? (Yes/No)";
            $response['state'] = 'awaiting_bleeding_level';
        }
        elseif (str_contains($message, '3') || str_contains($message, 'swelling')) {
            $response['reply'] = "Is the swelling causing *difficulty in breathing or swallowing*? (Yes/No)";
            $response['state'] = 'awaiting_swelling_level';
        }
        elseif (str_contains($message, '4') || str_contains($message, 'broken')) {
            $response['reply'] = "Is the broken/chipped tooth *causing sharp pain*? (Yes/No)";
            $response['state'] = 'awaiting_broken_tooth_status';
        }
        else {
            $response['reply'] = "Please describe your concern briefly (e.g., 'lost a filling', 'crown fell off').";
            $response['state'] = 'awaiting_other_emergency';
        }
        break;

    case 'awaiting_pain_level':
        $pain_level = intval(preg_replace('/[^0-9]/', '', $message));
        if ($pain_level >= 7) {
            $response['reply'] = "🚨 Pain level {$pain_level}/10 indicates a severe case. Please call us immediately at (02) 8123-4567 for an emergency appointment.";
            $response['state'] = 'start';
        } elseif ($pain_level > 0) {
            $response['reply'] = "Got it — pain level {$pain_level}/10. We recommend booking a check-up soon. Please call our clinic at (02) 8123-4567 to schedule your visit.";
            $response['state'] = 'start';
        } else {
            $response['reply'] = "Please provide a number between 1 and 10.";
            $response['state'] = 'awaiting_pain_level';
        }
        break;

    case 'awaiting_bleeding_level':
        if (str_contains($message, 'yes')) {
            $response['reply'] = "🚨 Uncontrollable bleeding is a serious emergency. Go to the nearest hospital or call our clinic at (02) 8123-4567 immediately.";
        } else {
            $response['reply'] = "Apply firm pressure with clean gauze. If it continues, please call us. Let’s schedule a visit soon.";
        }
        $response['state'] = 'start';
        break;

    case 'awaiting_swelling_level':
        if (str_contains($message, 'yes')) {
            $response['reply'] = "🚨 Swelling that affects breathing/swallowing is an emergency. Please go to the nearest hospital immediately.";
        } else {
            $response['reply'] = "Please apply a cold compress and call our clinic at (02) 8123-4567 to book a check-up. Swelling might indicate infection.";
        }
        $response['state'] = 'start';
        break;

    case 'awaiting_broken_tooth_status':
        if (str_contains($message, 'yes')) {
            $response['reply'] = "Sharp pain suggests nerve exposure. Avoid chewing on that side and contact us for an urgent appointment.";
        } else {
            $response['reply'] = "Thank you. Please rinse with warm salt water and avoid that side. Book a check-up to assess the damage.";
        }
        $response['state'] = 'start';
        break;

    case 'awaiting_other_emergency':
        $issue = htmlspecialchars($message);
        $response['reply'] = "Thank you for sharing (‘{$issue}’). Our staff will review your concern and contact you soon. For urgent matters, please call (02) 8123-4567.";
        $response['state'] = 'start';
        break;
}

// Save chatbot state
$_SESSION['chat_state'] = $response['state'];

// Return JSON response
echo json_encode($response);
?>
