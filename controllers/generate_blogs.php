<?php

    define('DB_HOST', 'localhost');
    define('DB_USER', 'root'); 
    define('DB_PASS', '');     
    define('DB_NAME', 'dentalclinicAppointment_db');
    define('DB_CHARSET', 'utf8mb4');

    define('BLOG_LIMIT', 10);

    // Gemini API Configuration (Your key is now hardcoded here)
    define('GEMINI_API_KEY', 'AIzaSyBspIeePTQCxRjjThpDVVk2RrqQ5L72yEo');
    define('GEMINI_MODEL', 'gemini-2.5-flash');
    define('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY);

    define('BLOG_PROMPT', "Write a compelling, positive, and informative blog post for a modern dental clinic's website. 
    The topic should be a common dental health issue or a popular service (e.g., teeth whitening, preventing cavities, gum health). 
    The post must be structured in **JSON format** with two keys: 'title' (a catchy headline) and 'content' (the full blog post, 
    formatted using simple HTML paragraphs <p> and bullet points <ul>/<li>). The length should be around 500 words.");

    function getPdoConnection() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            return new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
?>