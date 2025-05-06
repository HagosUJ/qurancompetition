<?php


// Require Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Specify the path to the directory containing the .env file (one level up from 'includes')
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Handle the error appropriately - e.g., log it, display a friendly message
    error_log("Error loading .env file: " . $e->getMessage());
    die("Configuration error: Could not load the .env file. Please ensure it exists in the project root.");
} catch (\Throwable $t) {
    // Catch any other potential errors during loading
    error_log("General error loading .env file: " . $t->getMessage());
    die("Configuration error: An unexpected issue occurred while loading settings.");
}


// --- Database configuration ---
// Use getenv() or $_ENV/$_SERVER. Provide defaults if a value might be optional.
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'quran_competition');

// --- Application settings ---
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Majlisu Ahlil Qur\'an International');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/musabaqa');

// --- Email/SMTP configuration ---
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? null);
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587)); // Cast to int
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? null);
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? null);
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@example.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Musabaqa App');
define('SMTP_ENCRYPTION', $_ENV['SMTP_ENCRYPTION'] ?? 'tls'); // 'tls', 'ssl', or ''

// --- Session settings ---
// These are often better set directly rather than defining constants for ini_set
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// Consider setting session save path, cookie lifetime, secure flag etc. here too if needed

// Start session *after* setting configurations
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants for session timing (can still be useful)
define('SESSION_TIMEOUT_DURATION', (int)($_ENV['SESSION_TIMEOUT_DURATION'] ?? 1800));
define('SESSION_REGENERATE_TIME', (int)($_ENV['SESSION_REGENERATE_TIME'] ?? 300));

// Optional: Validate required environment variables
try {
    $dotenv->required(['DB_HOST', 'DB_USER', 'DB_NAME', 'APP_URL'])->notEmpty();
    // Add other required variables like SMTP details if they are essential
    // $dotenv->required(['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL'])->notEmpty();
} catch (\Dotenv\Exception\ValidationException $e) {
    error_log("Missing or empty required environment variables: " . $e->getMessage());
    die("Configuration error: Missing required settings. Check your .env file and ensure all required variables are set and not empty.");
}

?>