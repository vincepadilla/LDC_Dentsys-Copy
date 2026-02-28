<?php
/**
 * API Key Encryption Helper
 * Provides secure encryption/decryption for API keys
 */

/**
 * Simple encryption function for API keys
 * Uses base64 encoding with a simple cipher to obfuscate the key
 */
function encryptApiKey($key) {
    if (empty($key)) {
        return '';
    }
    // Simple encryption: base64 + reverse + XOR with a simple key
    $encrypted = base64_encode($key);
    $encrypted = strrev($encrypted);
    // Add a simple XOR cipher
    $cipherKey = 'LDCDENTSY2024';
    $result = '';
    for ($i = 0; $i < strlen($encrypted); $i++) {
        $result .= chr(ord($encrypted[$i]) ^ ord($cipherKey[$i % strlen($cipherKey)]));
    }
    return base64_encode($result);
}

/**
 * Decrypt API key
 */
function decryptApiKey($encryptedKey) {
    if (empty($encryptedKey)) {
        return '';
    }
    try {
        // Decode base64
        $encrypted = base64_decode($encryptedKey);
        if ($encrypted === false) {
            return '';
        }
        // Reverse XOR cipher
        $cipherKey = 'LDCDENTSY2024';
        $result = '';
        for ($i = 0; $i < strlen($encrypted); $i++) {
            $result .= chr(ord($encrypted[$i]) ^ ord($cipherKey[$i % strlen($cipherKey)]));
        }
        // Reverse and decode
        $result = strrev($result);
        return base64_decode($result);
    } catch (Exception $e) {
        error_log("API key decryption error: " . $e->getMessage());
        return '';
    }
}

/**
 * Get Gemini API Key securely
 * Tries multiple sources in order of preference
 */
function getGeminiApiKey() {
    $apiKey = null;
    
    // 1. Try environment variable first (most secure)
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? 
              $_SERVER['GEMINI_API_KEY'] ?? 
              getenv('GEMINI_API_KEY') ?? 
              null;
    
    // 2. Try config file (encrypted or plain)
    if (!$apiKey) {
        $rootDir = dirname(__DIR__);
        $configPath = $rootDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'gemini.local.php';
        
        if (is_file($configPath)) {
            $config = require $configPath;
            
            if (is_array($config) && !empty($config['api_key'])) {
                $storedKey = $config['api_key'];
                
                // Check if it's encrypted (starts with base64 pattern and is longer)
                if (strlen($storedKey) > 50 && base64_decode($storedKey, true) !== false) {
                    // Try to decrypt
                    $apiKey = decryptApiKey($storedKey);
                } else {
                    // Plain text key
                    $apiKey = trim($storedKey);
                }
            } elseif (is_string($config) && trim($config) !== '') {
                $apiKey = trim($config);
            }
        }
    }
    
    // 3. Fallback to constant if defined
    if (!$apiKey && defined('GEMINI_API_KEY')) {
        $apiKey = (string) GEMINI_API_KEY;
    }
    
    return $apiKey ? trim($apiKey) : null;
}
