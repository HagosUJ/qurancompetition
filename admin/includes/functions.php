<?php
// filepath: /Users/user/Desktop/JCDA3/dashboard/includes/functions.php
/**
 * Core functions for the JCDA application
 * 
 * @package JCDA
 * @author Your Name
 * @version 1.1
 */

require_once '../../portal/includes/config.php';
require_once 'db.php';

/**
 * Sanitize user input with enhanced protection
 * 
 * @param string $input Raw input
 * @param bool $allow_html Whether to allow HTML (false by default)
 * @return string Sanitized input
 */
function sanitize_input($input, $allow_html = false) {
    // Trim whitespace first
    $input = trim($input);
    
    if ($allow_html) {
        // If HTML is allowed, use a more permissive sanitization
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    } else {
        // No HTML allowed - strip all tags and encode special chars
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate a cryptographically secure random string
 * 
 * @param int $length Length of the random string (bytes)
 * @return string Random hexadecimal string
 */
function generate_random_string($length = 16) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate a CSRF token and store it in the session
 * 
 * @param string $form_name Identifier for the specific form
 * @return string CSRF token
 */
function generate_csrf_token($form_name = 'default') {
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$form_name] = [
        'token' => $token,
        'time' => time()
    ];
    
    return $token;
}

/**
 * Verify CSRF token from form submission
 * 
 * @param string $token The token to verify
 * @param string $form_name Identifier for the specific form
 * @param int $timeout Optional timeout in seconds (default 1 hour)
 * @return bool True if token is valid
 */
function verify_csrf_token($token, $form_name = 'default', $timeout = 3600) {
    if (!isset($_SESSION['csrf_tokens'][$form_name])) {
        return false;
    }
    
    $stored = $_SESSION['csrf_tokens'][$form_name];
    
    // Check if token has expired
    if (time() - $stored['time'] > $timeout) {
        unset($_SESSION['csrf_tokens'][$form_name]);
        return false;
    }
    
    // Verify token with timing-safe comparison
    if (!hash_equals($stored['token'], $token)) {
        return false;
    }
    
    // Token is used, remove it (one-time use)
    unset($_SESSION['csrf_tokens'][$form_name]);
    return true;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if admin is logged in
 * 
 * @return bool True if admin is logged in
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']) && 
           isset($_SESSION['admin_role']) && !empty($_SESSION['admin_role']);
}

/**
 * Redirect user to a specific page
 * 
 * @param string $location URL to redirect to
 * @return void
 */
function redirect($location) {
    header("Location: $location");
    exit;
}

/**
 * Get user information by ID with security measures
 * 
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function get_user_info($user_id) {
    global $pdo;
    
    // Validate user ID is numeric
    $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
    if (!$user_id) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        log_error("Database error in get_user_info: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user profile by user ID
 * 
 * @param int $user_id User ID
 * @return array|false Profile data or false if not found
 */
function get_user_profile($user_id) {
    global $pdo;
    
    // Validate user ID is numeric
    $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
    if (!$user_id) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        log_error("Database error in get_user_profile: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an error with enhanced information
 * 
 * @param string $message Error message
 * @param string $severity Error severity (error, warning, info)
 * @return bool True if logged successfully
 */
function log_error($message, $severity = 'error') {
    $log_file = __DIR__ . '/../logs/error.log';
    $dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            // Can't create directory, try to log to system log
            error_log("Failed to create log directory: $dir");
            return false;
        }
    }
    
    if (!file_exists($log_file)) {
        touch($log_file);
        chmod($log_file, 0644); // Secure permissions
    }
    
    // Enhanced log with more details
    $log_entry = sprintf(
        "[%s] [%s] [%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        $severity,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP',
        session_id() ?? 'No session',
        $message
    );
    
    return error_log($log_entry, 3, $log_file);
}

/**
 * Send an email with better error handling
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param array $attachments Optional array of file paths to attach
 * @return bool True if email sent successfully, false otherwise
 */
function send_email($to, $subject, $message, $attachments = []) {
    // Validate email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_error("Invalid recipient email address: $to");
        return false;
    }
    
    try {
        // Basic PHP mail function implementation
        $from = defined('SITE_EMAIL') ? SITE_EMAIL : 'noreply@jcda.org';
        $site_name = defined('SITE_NAME') ? SITE_NAME : 'JCDA Website';
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $site_name . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if (!$result) {
            log_error("Failed to send email to: $to, Subject: $subject");
            return false;
        }
        
        return true;
        
        /* 
        // Example PHPMailer implementation (uncomment and use if PHPMailer is installed)
        
        // require_once __DIR__ . '/../vendor/autoload.php';
        // use PHPMailer\PHPMailer\PHPMailer;
        // use PHPMailer\PHPMailer\SMTP;
        // use PHPMailer\PHPMailer\Exception;
        
        // $mail = new PHPMailer(true);
        // $mail->isSMTP();
        // $mail->Host = SMTP_HOST;
        // $mail->SMTPAuth = true;
        // $mail->Username = SMTP_USER;
        // $mail->Password = SMTP_PASS;
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port = SMTP_PORT;
        
        // $mail->setFrom(SITE_EMAIL, SITE_NAME);
        // $mail->addAddress($to);
        // $mail->Subject = $subject;
        // $mail->isHTML(true);
        // $mail->Body = $message;
        
        // // Add attachments if any
        // foreach ($attachments as $attachment) {
        //     if (file_exists($attachment)) {
        //         $mail->addAttachment($attachment);
        //     }
        // }
        
        // return $mail->send();
        */
    } catch (Exception $e) {
        log_error("Mail error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a payment is valid with improved error handling
 * 
 * @param int $payment_id Payment ID
 * @return bool True if payment is valid and completed
 */
function is_payment_valid($payment_id) {
    global $pdo;
    
    // Validate payment ID
    $payment_id = filter_var($payment_id, FILTER_VALIDATE_INT);
    if (!$payment_id) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT payment_status FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $payment && $payment['payment_status'] === 'completed';
    } catch (PDOException $e) {
        log_error("Database error in is_payment_valid: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an admin activity with enhanced security
 * 
 * @param string $admin_username Admin username
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool True if logged successfully
 */
function log_activity($admin_username, $action, $details = '') {
    global $pdo;

    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_username, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            sanitize_input($admin_username),
            sanitize_input($action),
            sanitize_input($details),
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        return true;
    } catch (PDOException $e) {
        log_error("Failed to log admin activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Validates a date string with improved accuracy
 * 
 * @param string $date Date string in Y-m-d format
 * @param int $min_age Minimum age required (default 18)
 * @return bool Returns true if date is valid and user meets minimum age requirement
 */
function validate_date($date, $min_age = 18) {
    // Handle empty or null input
    if (empty($date)) {
        return false;
    }
    
    // Check if the date is in valid format
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        return false;
    }
    
    // Convert date string to timestamp
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return false;
    }
    
    // Check if date is valid (e.g., not 2023-02-30)
    $date_parts = explode('-', $date);
    if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        return false;
    }
    
    // Check for future dates
    if ($timestamp > time()) {
        return false;
    }
    
    // Calculate age using DateTime for better accuracy
    $birth_date = new DateTime($date);
    $today = new DateTime('today');
    $age = $birth_date->diff($today)->y;
    
    // Check if user meets minimum age requirement
    return ($age >= $min_age);
}

/**
 * Validate email address
 * 
 * @param string $email Email address to validate
 * @return bool True if email is valid
 */
function is_valid_email($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate strong password
 * 
 * @param string $password Password to validate
 * @param int $min_length Minimum length (default 8)
 * @return bool|string True if password meets requirements, error message string if not
 */
function is_valid_password($password, $min_length = 8) {
    $errors = [];
    
    // Check minimum length
    if (strlen($password) < $min_length) {
        $errors[] = "Password must be at least $min_length characters long";
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must include at least one uppercase letter";
    }
    
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must include at least one lowercase letter";
    }
    
    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must include at least one number";
    }
    
    // Check for at least one special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must include at least one special character";
    }
    
    if (empty($errors)) {
        return true;
    }
    
    return implode(", ", $errors);
}

/**
 * Clean and validate a username
 * 
 * @param string $username Username to validate
 * @return bool|string True if valid, error message if not
 */
function validate_username($username) {
    // Trim and sanitize
    $username = trim($username);
    
    // Check length
    if (strlen($username) < 3 || strlen($username) > 20) {
        return "Username must be between 3 and 20 characters";
    }
    
    // Check for allowed characters
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        return "Username can only contain letters, numbers, dots, underscores and hyphens";
    }
    
    return true;
}

/**
 * Generate a secure password hash
 * 
 * @param string $password The plain text password
 * @return string The hashed password
 */
function password_hash_secure($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64MB
        'time_cost'   => 4,     // 4 iterations
        'threads'     => 3,     // 3 threads
    ]);
}

/**
 * Validate and sanitize a phone number
 * 
 * @param string $phone Phone number to validate
 * @return bool|string Sanitized phone number or false if invalid
 */
function validate_phone($phone) {
    // Remove all non-digit characters
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check if the result is a valid phone number
    if (strlen($cleaned) < 10 || strlen($cleaned) > 15) {
        return false;
    }
    
    return $cleaned;
}

/**
 * Check if current session is secure
 * 
 * @return bool True if the session is secure
 */
function is_session_secure() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Check if session has been properly initialized
    if (!isset($_SESSION['initiated'])) {
        return false;
    }
    
    // Check for session fixation (regenerate ID periodically)
    if (isset($_SESSION['last_regeneration'])) {
        $regenerate_after = 30 * 60; // 30 minutes
        if (time() - $_SESSION['last_regeneration'] > $regenerate_after) {
            // Should regenerate, but return true since session is still valid
            return true;
        }
    }
    
    return true;
}

/**
 * Initialize a secure session with proper settings
 * 
 * @return bool True if session initialized successfully
 */
function initialize_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Start the session
    if (session_start()) {
        // Mark as initiated
        $_SESSION['initiated'] = true;
        
        // Set last regeneration time if not set
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        }
        
        return true;
    }
    
    return false;
}

/**
 * Safely regenerate session ID to prevent fixation attacks
 * 
 * @param bool $delete_old_session Whether to delete old session data
 * @return bool True if successful
 */
function regenerate_session_id($delete_old_session = false) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Regenerate session ID
    $result = session_regenerate_id($delete_old_session);
    
    if ($result) {
        $_SESSION['last_regeneration'] = time();
    }
    
    return $result;
}
?>