<?php
// filepath: /Users/user/Desktop/JCDA3/dashboard/includes/functions.php
/**
 * Core functions for the JCDA application
 * 
 * @package JCDA
 * @author Your Name
 * @version 1.1
 */

// Assuming functions.php is in admin/includes/
// Path to portal/includes/config.php
require_once __DIR__ . '/../../portal/includes/config.php'; 
// Path to admin/includes/db.php (assuming db.php is in the same directory as this functions.php)
require_once __DIR__ . '/db.php'; 

/**
 * Sanitize user input with enhanced protection
 * 
 * @param string $input Raw input
 * @param bool $allow_html Whether to allow HTML (false by default)
 * @return string Sanitized input
 */
function sanitize_input($input, $allow_html = false) {
    // ...existing code...
}

/**
 * Generate a cryptographically secure random string
 * 
 * @param int $length Length of the random string (bytes)
 * @return string Random hexadecimal string
 */
function generate_random_string($length = 16) {
    // ...existing code...
}

/**
 * Generate a CSRF token and store it in the session
 * 
 * @param string $form_name Identifier for the specific form
 * @return string CSRF token
 */
function generate_csrf_token($form_name = 'default') {
    // ...existing code...
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
    // ...existing code...
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function is_logged_in() {
    // ...existing code...
}

/**
 * Check if admin is logged in
 * 
 * @return bool True if admin is logged in
 */
function is_admin_logged_in() {
    // ...existing code...
}

/**
 * Redirect user to a specific page
 * 
 * @param string $location URL to redirect to
 * @return void
 */
function redirect($location) {
    // ...existing code...
}

/**
 * Get user information by ID with security measures
 * 
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function get_user_info($user_id) {
    // ...existing code...
}

/**
 * Get user profile by user ID
 * 
 * @param int $user_id User ID
 * @return array|false Profile data or false if not found
 */
function get_user_profile($user_id) {
    // ...existing code...
}

/**
 * Log an error with enhanced information
 * 
 * @param string $message Error message
 * @param string $severity Error severity (error, warning, info)
 * @return bool True if logged successfully
 */
function log_error($message, $severity = 'error') {
    // ...existing code...
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
    // ...existing code...
}

/**
 * Check if a payment is valid with improved error handling
 * 
 * @param int $payment_id Payment ID
 * @return bool True if payment is valid and completed
 */
function is_payment_valid($payment_id) {
    // ...existing code...
}

/**
 * Log an admin activity with enhanced security (Older version)
 * Consider using log_admin_action for new logging to admin_logs table.
 * 
 * @param string $admin_username Admin username
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool True if logged successfully
 */
function log_activity($admin_username, $action, $details = '') {
    global $pdo;

    try {
        // This function might be logging to a different table or an older version of admin_logs
        // Ensure the table and columns match if this is still in use.
        // The new admin_logs table has admin_user_id, target_type, target_id.
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
        log_error("Failed to log admin activity (log_activity function): " . $e->getMessage());
        return false;
    }
}

/**
 * Logs an admin action to the admin_logs table. (Newer, more detailed version)
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $action A description of the action performed.
 * @param int|null $admin_user_id The ID of the admin performing the action. Defaults to current session user_id.
 * @param string|null $admin_username The username of the admin. Defaults to current session user_fullname.
 * @param string|null $target_type The type of entity being affected (e.g., 'user', 'application').
 * @param int|null $target_id The ID of the affected entity.
 * @param string|null $details Additional details about the action (can be JSON or text).
 * @return bool True on success, false on failure.
 */
function log_admin_action(PDO $pdo, string $action, ?int $admin_user_id = null, ?string $admin_username = null, ?string $target_type = null, ?int $target_id = null, ?string $details = null): bool {
    if ($admin_user_id === null && isset($_SESSION['user_id'])) {
        $admin_user_id = (int)$_SESSION['user_id'];
    }
    // Ensure admin_username is fetched correctly based on your session variable for admin's name
    if ($admin_username === null && isset($_SESSION['user_fullname'])) { // Or $_SESSION['admin_username'] if you use that
        $admin_username = $_SESSION['user_fullname'];
    } elseif ($admin_username === null && $admin_user_id !== null) {
        // Attempt to fetch username if ID is provided but username is not
        // This is optional and adds a DB query; usually, username is available in session
        $user_info = get_user_info($admin_user_id); // Assuming get_user_info can fetch admin details
        if ($user_info && isset($user_info['fullname'])) { // or 'username'
            $admin_username = $user_info['fullname'];
        }
    }


    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $sql = "INSERT INTO admin_logs (admin_user_id, admin_username, action, target_type, target_id, details, ip_address, user_agent, created_at)
            VALUES (:admin_user_id, :admin_username, :action, :target_type, :target_id, :details, :ip_address, :user_agent, NOW())";

    try {
        $stmt = $pdo->prepare($sql);
        // Bind parameters, allowing nulls where appropriate
        $stmt->bindValue(':admin_user_id', $admin_user_id, $admin_user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':admin_username', $admin_username, $admin_username === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':target_type', $target_type, $target_type === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':target_id', $target_id, $target_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':details', $details, $details === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $ip_address, $ip_address === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $user_agent, $user_agent === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        // Log error to PHP error log, don't expose to user from this function
        log_error("Failed to log admin action (log_admin_action function): " . $e->getMessage());
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
    // ...existing code...
}

/**
 * Validate email address
 * 
 * @param string $email Email address to validate
 * @return bool True if email is valid
 */
function is_valid_email($email) {
    // ...existing code...
}

/**
 * Validate strong password
 * 
 * @param string $password Password to validate
 * @param int $min_length Minimum length (default 8)
 * @return bool|string True if password meets requirements, error message string if not
 */
function is_valid_password($password, $min_length = 8) {
    // ...existing code...
}

/**
 * Clean and validate a username
 * 
 * @param string $username Username to validate
 * @return bool|string True if valid, error message if not
 */
function validate_username($username) {
    // ...existing code...
}

/**
 * Generate a secure password hash
 * 
 * @param string $password The plain text password
 * @return string The hashed password
 */
function password_hash_secure($password) {
    // ...existing code...
}

/**
 * Validate and sanitize a phone number
 * 
 * @param string $phone Phone number to validate
 * @return bool|string Sanitized phone number or false if invalid
 */
function validate_phone($phone) {
    // ...existing code...
}

/**
 * Check if current session is secure
 * 
 * @return bool True if the session is secure
 */
function is_session_secure() {
    // ...existing code...
}

/**
 * Initialize a secure session with proper settings
 * 
 * @return bool True if session initialized successfully
 */
function initialize_secure_session() {
    // ...existing code...
}

/**
 * Safely regenerate session ID to prevent fixation attacks
 * 
 * @param bool $delete_old_session Whether to delete old session data
 * @return bool True if successful
 */
function regenerate_session_id($delete_old_session = false) {
    // ...existing code...
}
?>