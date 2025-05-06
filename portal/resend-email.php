<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/resend-email.php
require_once 'includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// Validate CSRF token
$csrf_token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    set_flash_message("Invalid request. Please try again.", "error");
    header("Location: check-email.php");
    exit;
}

// Sanitize input
$email = isset($_GET['email']) ? sanitize_input($_GET['email']) : '';
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'verification';
$current_lang = $_SESSION['language'] ?? 'en';

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash_message($current_lang === 'ar' ? "البريد الإلكتروني غير صالح." : "Invalid email address.", "error");
    header("Location: check-email.php?type=" . urlencode($type));
    exit;
}

// Initialize success flag
$email_sent = false;
$message = "";
$error_message = "";

// Handle resend based on type
if ($type === 'reset') {
    // For password reset
    $user = get_user_by_email($email);
    if ($user) {
        $reset_token = bin2hex(random_bytes(32));
        if (store_reset_token($email, $reset_token)) {
            $email_sent = send_password_reset_email($email, $user['full_name'], $reset_token);
            if ($email_sent) {
                $message = $current_lang === 'ar' ? "تم إعادة إرسال رابط إعادة تعيين كلمة المرور بنجاح!" : "Password reset link resent successfully!";
            } else {
                $error_message = $current_lang === 'ar' ? "فشل في إعادة إرسال رابط إعادة تعيين كلمة المرور. يرجى المحاولة لاحقًا." : "Failed to resend password reset link. Please try again later.";
            }
        } else {
            $error_message = $current_lang === 'ar' ? "فشل في إنشاء رابط إعادة تعيين كلمة المرور. يرجى المحاولة لاحقًا." : "Failed to generate password reset link. Please try again later.";
        }
    } else {
        $error_message = $current_lang === 'ar' ? "البريد الإلكتروني غير مسجل." : "Email not registered.";
    }
} else {
    // For verification
    $user = get_user_by_email($email);
    if ($user && !$user['is_verified']) {
        $activation_token = bin2hex(random_bytes(32));
        if (store_verification_token($email, $activation_token)) {
            $email_sent = send_verification_email($email, $user['full_name'], $activation_token);
            if ($email_sent) {
                $message = $current_lang === 'ar' ? "تم إعادة إرسال بريد التفعيل بنجاح!" : "Verification email resent successfully!";
            } else {
                $error_message = $current_lang === 'ar' ? "فشل في إعادة إرسال بريد التفعيل. يرجى المحاولة لاحقًا." : "Failed to resend verification email. Please try again later.";
            }
        } else {
            $error_message = $current_lang === 'ar' ? "فشل في إنشاء رابط التفعيل. يرجى المحاولة لاحقًا." : "Failed to generate verification link. Please try again later.";
        }
    } else {
        $error_message = $current_lang === 'ar' ? "البريد الإلكتروني غير مسجل أو تم تفعيله بالفعل." : "Email not registered or already verified.";
    }
}

// Set flash message and redirect
if ($email_sent) {
    set_flash_message($message, "success");
    header("Location: check-email.php?email=" . urlencode($email) . "&type=" . urlencode($type) . "&email_sent=1");
} else {
    set_flash_message($error_message, "error");
    header("Location: check-email.php?email=" . urlencode($email) . "&type=" . urlencode($type));
}
exit;
?>