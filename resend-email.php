<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/resend-email.php
require_once 'includes/auth.php';

// Start session securely
secure_session_start();

// --- Basic Rate Limiting (Session-based) ---
$resend_limit = 3; // Max resends per session per hour
$resend_timespan = 3600; // 1 hour in seconds

if (!isset($_SESSION['resend_attempts'])) {
    $_SESSION['resend_attempts'] = [];
}

$now = time();
// Clear old attempts
$_SESSION['resend_attempts'] = array_filter($_SESSION['resend_attempts'], function($timestamp) use ($now, $resend_timespan) {
    return ($now - $timestamp) < $resend_timespan;
});

if (count($_SESSION['resend_attempts']) >= $resend_limit) {
    set_flash_message("You have reached the maximum number of resend requests. Please try again later.", 'error');
    redirect('check-email.php' . (isset($_GET['email']) ? '?email=' . urlencode($_GET['email']) . '&type=' . urlencode($_GET['type'] ?? 'verification') : ''));
    exit;
}
// --- End Rate Limiting ---


// Verify CSRF token
if (!isset($_GET['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    set_flash_message("Invalid request. Please try again.", 'error');
    // Redirect back, trying to preserve context if possible
    redirect('check-email.php' . (isset($_GET['email']) ? '?email=' . urlencode($_GET['email']) . '&type=' . urlencode($_GET['type'] ?? 'verification') : ''));
    exit; // Stop execution
}

// Get and sanitize parameters
$email = isset($_GET['email']) ? sanitize_input($_GET['email']) : null;
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'verification'; // 'verification' or 'reset'

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash_message("Invalid email address provided.", 'error');
    redirect('sign-in.php'); // Redirect to a safe default
    exit;
}

$success = false;
$message = '';

try {
    if ($type === 'reset') {
        // Resend password reset email
        // The function already handles finding the user and generating a new token
        $email_sent = send_password_reset_email($email);
        // Note: send_password_reset_email always returns true for security,
        // so we rely on internal logging for actual failures.
        // We assume success unless an exception occurs.
        $success = true; // Assume success for user feedback consistency
        $message = "Password reset email has been resent to " . htmlspecialchars($email) . ". Please check your inbox (and spam folder).";

    } elseif ($type === 'verification') {
        // Resend verification email - Requires a new function in auth.php
        $email_sent = resend_verification_email($email); // We need to create this function

        if ($email_sent) {
            $success = true;
            $message = "Verification email has been resent to " . htmlspecialchars($email) . ". Please check your inbox (and spam folder).";
        } else {
            // Check if the user might already be active or email doesn't exist
            // (resend_verification_email should handle this logic and return false)
            $message = "Could not resend verification email. The account may already be active, or the email address might not be registered for activation.";
        }
    } else {
        $message = "Invalid request type.";
    }
} catch (Exception $e) {
    error_log("Resend Email Exception: " . $e->getMessage());
    $message = "An unexpected error occurred while trying to resend the email. Please try again later.";
    $success = false; // Ensure success is false on exception
}

// Record the attempt (even if it failed, to prevent spamming invalid types/emails)
$_SESSION['resend_attempts'][] = $now;

// Set flash message based on outcome
set_flash_message($message, $success ? 'success' : 'error');

// Redirect back to the check-email page with original parameters
redirect('check-email.php?email=' . urlencode($email) . '&type=' . urlencode($type));
exit;

?>