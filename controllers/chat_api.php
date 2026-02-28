<?php
// --- CONFIGURATION --- 

// Use secure API key helper instead of dotenv
require_once __DIR__ . '/../config/api_key_helper.php';

// Try to load dotenv if available (optional)
$rootDir = dirname(__DIR__);
$vendorPath = $rootDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendorPath)) {
    try {
        require $vendorPath;
        $dotenv = Dotenv\Dotenv::createImmutable($rootDir);
        $dotenv->safeLoad();
    } catch (Exception $e) {
        // Dotenv not available or failed, continue without it
        error_log("Dotenv not available: " . $e->getMessage());
    }
}

// Get API key securely using the helper function
$geminiApiKey = getGeminiApiKey();

// Additional fallback for environment variables (if dotenv loaded)
if (!$geminiApiKey) {
    $geminiApiKey = $_ENV['GEMINI_API_KEY'] ?? 
                    $_SERVER['GEMINI_API_KEY'] ?? 
                    getenv('GEMINI_API_KEY') ??
                    ($_ENV['GOOGLE_API_KEY'] ?? $_SERVER['GOOGLE_API_KEY'] ?? getenv('GOOGLE_API_KEY') ?? null);
}

if (!$geminiApiKey) {
    http_response_code(500);
    echo json_encode([
        'answer' => 'Server misconfiguration: GEMINI_API_KEY is not set. Please configure the API key in config/gemini.local.php or set it as an environment variable.',
        'history' => [],
        'source' => 'error'
    ]);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    exit(0);
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'])) {
    echo json_encode(['answer' => 'Invalid request', 'history' => []]);
    exit;
}

$userMessage = trim($data['message']);
$chatHistory = $data['history'] ?? [];

// Predefined responses for common questions
$predefinedResponses = [
    "what services do you offer?" => "We offer comprehensive dental services including:\n\nGeneral Dentistry\n- Oral Prophylaxis\n- Fluoride Application\n- Pit & Fissure Sealants\n- Tooth Restoration (Pasta)\n- Root Canal Treatment\n\nOrthodontics\n- Braces\n- Retainers\n\nOral Surgery\n- Tooth Extraction (Bunot)\n\nEndodontics\n- Root Canal Treatment\n\nProsthodontics\n- Crowns\n- Dentures\n\nIs there a specific service you'd like to know more about?",
    
    "how do i book an appointment?" => "Booking an appointment is easy! Here's our step-by-step process:\n\nStep 1: Select Service\n- Choose from our dental services\n\nStep 2: Select Sub-service\n- Pick the specific treatment you need\n\nStep 3: Choose Date & Time\n- Select your preferred appointment schedule\n\nStep 4: Payment Method\nChoose between:\n\nDigital Payment (GCash/PayMaya):\n- Input necessary payment details\n- Upload the transaction receipt\n- Your slot will be confirmed immediately\n\nCash Payment:\n- Your slot will be placed on HOLD\n- You need to pay at the clinic to confirm your appointment\n- Payment must be made before your scheduled date\n\nFinal Step:\nAfter booking, you'll receive a confirmation via email and SMS once the dentist approves your appointment.\n\nReady to book your appointment? Visit our booking page or contact us:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com",
    
    "what are your opening hours?" => "Our clinic hours are:\n\nMonday - Friday: 8:00 AM - 5:00 PM\nSaturday: 9:00 AM - 2:00 PM\nSunday: Closed\n\nWe recommend booking appointments in advance.",
    
    "where are you located?" => "We're conveniently located at:\n\nLandero Dental Clinic\nAnahaw St. Comembo\nTaguig City\n\nContact us:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com",
    
    "do you accept insurance?" => "Yes, we accept most major dental insurance plans including:\n\nDelta Dental\nMetLife\nCigna\nAetna\nBlue Cross Blue Shield\n\nPlease bring your insurance card to your appointment. We also offer flexible payment plans.",
    
    "how much is a dental checkup?" => "The cost of dental checkups varies based on the specific treatment procedure needed. \n\nWe provide personalized treatment plans and cost estimates after your initial examination. The final price depends on the services required and your individual dental needs.\n\nFor accurate pricing information, we recommend scheduling a consultation where we can assess your specific requirements and provide a detailed cost breakdown."
];

// Check if it's a predefined question
$normalizedMessage = strtolower($userMessage);
$isPredefinedQuestion = false;
$responseText = '';

foreach ($predefinedResponses as $question => $answer) {
    if (strpos($normalizedMessage, $question) !== false || similar_text($normalizedMessage, $question) > 80) {
        $responseText = $answer;
        $isPredefinedQuestion = true;
        break;
    }
}

// If it's a predefined question, return the predefined response
if ($isPredefinedQuestion) {
    // Remove asterisks from long responses
    if (strlen($responseText) > 100) {
        $responseText = str_replace('*', '', $responseText);
    }
    
    $chatHistory[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];
    $chatHistory[] = ['role' => 'model', 'parts' => [['text' => $responseText]]];
    
    echo json_encode([
        'answer' => $responseText,
        'history' => $chatHistory,
        'source' => 'predefined'
    ]);
    exit;
}

// --- CONTINUE WITH AI PROCESSING FOR OTHER QUESTIONS ---
$geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($geminiApiKey);

// Prepare the conversation context
$contents = [];

// Add system context
$systemContext = [
    'role' => 'user',
    'parts' => [[
        'text' => "You are a helpful dental clinic assistant for Landero Dental Clinic. Provide friendly, professional responses about dental services, appointments, and general dental care. Keep responses concise but informative. If you don't know specific details about this clinic, suggest they contact the clinic directly.\n\nClinic Information:\n- Name: Landero Dental Clinic\n- Services: General Dentistry (Oral Prophylaxis, Fluoride Application, Pit & Fissure Sealants, Tooth Restoration/Pasta, Root Canal Treatment), Orthodontics (Braces, Retainers), Oral Surgery (Tooth Extraction/Bunot), Endodontics (Root Canal Treatment), Prosthodontics (Crowns, Dentures)\n- Hours: Mon-Fri 9AM-6PM, Sat 9AM-2PM, Sun Closed\n- Location: Anahaw St. Comembo, Taguig City\n- Phone: 09458471502\n- Email: landerodentalclinic@gmail.com\n- Insurance: Accepts most major providers\n- Booking Process: Select Service → Select Sub-service → Choose Date/Time → Payment Method (GCash/PayMaya with receipt upload or Cash with hold status)\n- Pricing: Do not provide specific prices. State that costs vary based on treatment procedure and individual needs. Recommend consultation for accurate pricing.\n\nBe helpful and encourage patients to book appointments for specific concerns. Do not use icons, asterisks, or emojis in responses. For long responses (over 100 characters), remove all asterisks from the text."
    ]]
];
$contents[] = $systemContext;

// Add chat history (with safety checks)
if (is_array($chatHistory)) {
    foreach ($chatHistory as $message) {
        if (isset($message['role']) && isset($message['parts'][0]['text'])) {
            $contents[] = [
                'role' => $message['role'],
                'parts' => [['text' => $message['parts'][0]['text']]]
            ];
        }
    }
}

// Add current message
$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $userMessage]]
];

// Prepare the request data
$requestData = [
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 1024,
    ]
];

// Make API request to Gemini
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $geminiApiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || !empty($curlError)) {
    error_log("Chat API cURL error: " . $curlError);
    $errorResponse = [
        'answer' => "I apologize, but I'm having trouble connecting right now. Please try again in a moment or contact us directly:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com",
        'history' => $chatHistory,
        'source' => 'error'
    ];
    echo json_encode($errorResponse);
    exit;
}

$responseData = json_decode($response, true);

// Check for API errors in response
if (isset($responseData['error'])) {
    error_log("Chat API Error: " . json_encode($responseData['error']));
    $errorResponse = [
        'answer' => "I'm sorry, I encountered an error processing your request. Please try again or contact us directly:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com",
        'history' => $chatHistory,
        'source' => 'error'
    ];
    echo json_encode($errorResponse);
    exit;
}

if ($httpCode !== 200) {
    error_log("Chat API HTTP error: " . $httpCode . " - Response: " . substr($response, 0, 200));
    $errorResponse = [
        'answer' => "I'm sorry, I couldn't process your question. Please try rephrasing it or contact us directly:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com",
        'history' => $chatHistory,
        'source' => 'error'
    ];
    echo json_encode($errorResponse);
    exit;
}

// Check if response has the expected structure
if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    error_log("Chat API: Invalid response structure - " . json_encode($responseData));
    $errorResponse = [
        'answer' => "I'm sorry, I couldn't process your question. Please try rephrasing it or contact us directly:\nPhone: 09458471502\nEmail: landerodentalclinic@gmail.com",
        'history' => $chatHistory,
        'source' => 'error'
    ];
    echo json_encode($errorResponse);
    exit;
}

$aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];

// Remove asterisks from long responses
if (strlen($aiResponse) > 100) {
    $aiResponse = str_replace('*', '', $aiResponse);
}

// Update chat history
$chatHistory[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];
$chatHistory[] = ['role' => 'model', 'parts' => [['text' => $aiResponse]]];

// Return the response
echo json_encode([
    'answer' => $aiResponse,
    'history' => $chatHistory,
    'source' => 'ai'
]);
?>