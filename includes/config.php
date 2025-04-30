<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change to your database username
define('DB_PASS', ''); // Change to your database password
define('DB_NAME', 'quran_competition');

// Application settings
define('APP_NAME', 'Majlisu Ahlil Qur\'an International');
define('APP_URL', 'http://localhost/musabaqa'); // Change to your domain

// Email/SMTP configuration
define('SMTP_HOST', 'smtp.resend.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'resend');
define('SMTP_PASSWORD', 're_SHRSWkq7_HhnNYX9k4c5mp6fdwmcK9TA6');
define('SMTP_FROM_EMAIL', 'noreply@jcda.com.ng');
define('SMTP_FROM_NAME', 'JCDA');
define('SMTP_ENCRYPTION', 'tls'); // Options: 'tls', 'ssl', or ''

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

define('SESSION_TIMEOUT_DURATION', 1800); // Session timeout in seconds (1800 = 30 minutes)
define('SESSION_REGENERATE_TIME', 300); // Regenerate session ID every 5 minutes
?>