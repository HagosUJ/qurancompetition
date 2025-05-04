<?php
require_once 'db.php';
require_once 'functions.php';

// Constants
define('LOGIN_ATTEMPT_LIMIT', 5);
define('LOCKOUT_DURATION', 15 * 60); // 15 minutes in seconds
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes in seconds

// Secure session start
function secure_session_start() {
    $session_name = 'musabaqa_session';
    $secure = true; // HTTPS only
    $httponly = true;

    // Session cookie parameters
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Strict'
    ]);

    session_name($session_name);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        error_log("Session started, ID: " . session_id());
    }

    // Set default language if not set
    if (!isset($_SESSION['language'])) {
        $_SESSION['language'] = 'en';
        $_SESSION['lang'] = 'en';
        error_log("Default language set to 'en'");
    }

    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
        error_log("Session ID regenerated: " . session_id());
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        logout();
        error_log("Session timed out, user logged out");
    }
    $_SESSION['last_activity'] = time();
}

// Initialize session
secure_session_start();

// Check if user is logged in
function is_logged_in() {
    $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    error_log("is_logged_in check: " . ($is_logged_in ? "true" : "false") . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));
    return $is_logged_in;
}

// Record login attempt
function record_login_attempt($ip_address, $email_identifier, $success = false) {
    global $conn;
    $attempt_time = date('Y-m-d H:i:s');
    $login_attempt_limit = LOGIN_ATTEMPT_LIMIT; // Store constant in variable
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO login_attempts (ip_address, email_identifier, attempt_time, attempts_count)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                attempts_count = IF(attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempts_count + 1),
                attempt_time = ?,
                lockout_until = IF(attempts_count + 1 >= ?, NOW() + INTERVAL 15 MINUTE, lockout_until)
        ");
        $stmt->bind_param("sssss", $ip_address, $email_identifier, $attempt_time, $attempt_time, $login_attempt_limit);
        $stmt->execute();
        error_log("Recorded login attempt for IP: $ip_address, email: $email_identifier, success: " . ($success ? 'true' : 'false'));
    } catch (Exception $e) {
        error_log("Error recording login attempt: " . $e->getMessage());
    }
}

// Get login attempts
function get_login_attempts($ip_address, $email_identifier) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT attempts_count
            FROM login_attempts
            WHERE ip_address = ? AND email_identifier = ?
            AND attempt_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->bind_param("ss", $ip_address, $email_identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $attempts = $row['attempts_count'] ?? 0;
        error_log("Login attempts for IP: $ip_address, email: $email_identifier: $attempts");
        return $attempts;
    } catch (Exception $e) {
        error_log("Error getting login attempts: " . $e->getMessage());
        return 0;
    }
}

// Get lockout expiry
function get_lockout_expiry($ip_address, $email_identifier) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT lockout_until
            FROM login_attempts
            WHERE ip_address = ? AND email_identifier = ?
        ");
        $stmt->bind_param("ss", $ip_address, $email_identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $lockout_until = isset($row['lockout_until']) && $row['lockout_until'] ? strtotime($row['lockout_until']) : 0;
        error_log("Lockout expiry for IP: $ip_address, email: $email_identifier: " . ($lockout_until ? date('Y-m-d H:i:s', $lockout_until) : 'none'));
        return $lockout_until;
    } catch (Exception $e) {
        error_log("Error getting lockout expiry: " . $e->getMessage());
        return 0;
    }
}

// Clear login attempts
function clear_login_attempts($ip_address, $email_identifier) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            DELETE FROM login_attempts
            WHERE ip_address = ? AND email_identifier = ?
        ");
        $stmt->bind_param("ss", $ip_address, $email_identifier);
        $stmt->execute();
        error_log("Cleared login attempts for IP: $ip_address, email: $email_identifier");
    } catch (Exception $e) {
        error_log("Error clearing login attempts: " . $e->getMessage());
    }
}

// Process login
function process_login($email, $password, $remember = false) {
    global $conn;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $email_identifier = $email;

    // Check lockout
    $lockout_expiry = get_lockout_expiry($ip_address, $email_identifier);
    if ($lockout_expiry > time()) {
        $wait_time = ceil(($lockout_expiry - time()) / 60);
        error_log("Login blocked due to lockout for email: $email");
        return [false, "Too many login attempts. Please try again after {$wait_time} minutes.", null];
    }

    try {
        // Fetch user
        $stmt = $conn->prepare("SELECT id, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            record_login_attempt($ip_address, $email_identifier, false);
            error_log("Email not found: $email");
            return [false, "Email not found.", 'email'];
        }

        $user = $result->fetch_assoc();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            record_login_attempt($ip_address, $email_identifier, false);
            $attempts = get_login_attempts($ip_address, $email_identifier);
            $remaining = LOGIN_ATTEMPT_LIMIT - $attempts;
            error_log("Incorrect password for email: $email, attempts: $attempts");
            return [false, "Incorrect password. $remaining attempts remaining.", 'password'];
        }

        // Check account status
        if ($user['status'] === 'pending') {
            record_login_attempt($ip_address, $email_identifier, false);
            error_log("Account pending verification for email: $email");
            return [false, "Your account is pending verification.", null];
        }
        if ($user['status'] === 'inactive') {
            record_login_attempt($ip_address, $email_identifier, false);
            error_log("Account inactive for email: $email");
            return [false, "Your account is inactive.", null];
        }

        // Successful login
        create_user_session($user, $remember);
        clear_login_attempts($ip_address, $email_identifier);
        record_login_attempt($ip_address, $email_identifier, true);
        error_log("Successful login for email: $email, user_id: {$user['id']}");
        return [true, "Login successful.", null];

    } catch (Exception $e) {
        record_login_attempt($ip_address, $email_identifier, false);
        error_log("Login error for email: $email, message: " . $e->getMessage());
        return [false, "An unexpected error occurred.", null];
    }
}

// Create user session
function create_user_session($user, $remember = false) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('remember_me', $token, time() + (30 * 24 * 3600), '/', '', true, true);
        // Store token in database
        global $conn;
        $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->bind_param("si", $token, $user['id']);
        $stmt->execute();
    }
    error_log("User session created for user_id: {$user['id']}, email: {$user['email']}");
}

// Logout
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    error_log("User logged out, session destroyed");
}
?>