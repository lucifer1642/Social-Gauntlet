<?php
// ==============================================
// config.php — Central configuration file
// ==============================================
// Loads environment variables and starts session

session_start();

// Load .env file manually (no external library needed)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // skip comments
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Database constants
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'personality_stress_tester');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Gemini API
define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY'] ?? '');
define('GEMINI_MODEL_CHAT', 'gemini-1.5-flash');
define('GEMINI_MODEL_ANALYSIS', 'gemini-1.5-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/');

// App constants
define('APP_NAME', 'The Social Gauntlet');

// Dynamic BASE_URL calculation
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$baseDir = '';

// List of known subdirectories to strip to find the project root
$subfolders = ['/app/', '/auth/', '/api/', '/includes/', '/sql/'];
foreach ($subfolders as $folder) {
    if (($pos = strpos($scriptPath, $folder)) !== false) {
        $baseDir = substr($scriptPath, 0, $pos);
        break;
    }
}

// Fallback for root-level files (like index.php)
if ($baseDir === '' && $scriptPath !== '') {
    $baseDir = rtrim(dirname($scriptPath), '/');
}

define('BASE_URL', $baseDir);
define('MAX_EXCHANGES_PER_ROUND', 6);
define('MIN_EXCHANGES_PER_ROUND', 4);
define('TOTAL_ROUNDS', 5);

// Error reporting (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
