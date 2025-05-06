<?php
/**
 * Authentication and User Management Functions
 *
 * Handles user registration, login, logout, password reset, email verification,
 * session management, and security features.
 *
 * @version 1.1
 * @license MIT
 */

// Strict types for better type safety
declare(strict_types=1);

// Core dependencies
require_once __DIR__ . '/db.php';       // Database connection ($conn)
require_once __DIR__ . '/functions.php'; // Utility functions (like redirect, sanitize_input)
require_once __DIR__ . '/config.php';    // Application configuration (APP_URL, APP_NAME, SMTP settings, etc.)

// Composer autoloader for libraries like PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// --- Constants ---
define('TOKEN_EXPIRY_ACTIVATION', 24 * 60 * 60); // 24 hours
define('TOKEN_EXPIRY_RESET', 1 * 60 * 60);      // 1 hour
define('REMEMBER_ME_EXPIRY', 30 * 24 * 60 * 60); // 30 days
define('LOGIN_ATTEMPT_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60); // 15 minutes

// --- Session Management ---

/**
 * Securely starts or resumes a session if not already started.
 * Configures session cookie parameters for security.
 */
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // More secure session cookie settings
        session_set_cookie_params([
            'lifetime' => 0, // Expires when browser closes
            'path' => '/',
            'domain' => '', // Set your domain in production if needed
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Only send over HTTPS
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Lax' // Mitigate CSRF
        ]);
        session_start();
    }
}

/**
 * Regenerates the session ID to prevent session fixation.
 * Call this after login or privilege level changes.
 */
function regenerate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Checks if a user is currently logged in.
 *
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in(): bool
{
    secure_session_start();
    return isset($_SESSION['user_id']);
}

/**
 * Redirects the user to the login page if they are not logged in.
 * Sets a flash message explaining the reason.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash_message('Please login to access this page.', 'warning');
        redirect('sign-in.php');
    }
}

/**
 * Creates the user session variables after successful login.
 * Regenerates the session ID.
 *
 * @param array $user Associative array containing user data (id, fullname, email, role).
 */
function create_user_session(array $user): void
{
    secure_session_start();
    // Ensure essential keys exist
    if (!isset($user['id'], $user['fullname'], $user['email'], $user['role'])) {
        error_log("Attempted to create session with incomplete user data.");
        // Handle error appropriately, maybe throw an exception or return false
        return;
    }
    // Prevent session fixation
    regenerate_session();

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['fullname'] = (string)$user['fullname'];
    $_SESSION['email'] = (string)$user['email'];
    $_SESSION['role'] = (string)$user['role'];
    $_SESSION['last_activity'] = time(); // For potential inactivity timeout
}

/**
 * Logs the user out by destroying the session and clearing cookies.
 */
function logout(): void
{
    secure_session_start();

    // Clear remember me token from database if exists
    if (isset($_COOKIE['remember_selector'])) {
        clear_remember_token($_COOKIE['remember_selector']);
    }

    // Unset all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Delete the remember me cookie
    setcookie('remember_selector', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    setcookie('remember_validator', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);

    // Destroy the session
    session_destroy();

    // Redirect to login page after logout
    redirect('sign-in.php');
}

// --- Password Hashing ---

/**
 * Hashes a password using the recommended default algorithm (currently bcrypt).
 *
 * @param string $password The plain text password.
 * @return string The hashed password.
 */
function hash_password(string $password): string
{
    // PASSWORD_DEFAULT is recommended as it will be updated with newer PHP versions
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        // Handle hashing failure, though unlikely with PASSWORD_DEFAULT
        error_log("Password hashing failed.");
        throw new RuntimeException("Could not hash password.");
    }
    return $hashedPassword;
}

/**
 * Verifies a password against a stored hash.
 * Also handles potential rehashing if the algorithm or options change.
 *
 * @param string $password The plain text password to verify.
 * @param string $hash The stored password hash.
 * @param int|null $user_id The user ID, required for potential rehashing.
 * @return bool True if the password matches, false otherwise.
 */
function verify_password(string $password, string $hash, ?int $user_id = null): bool
{
    global $conn;

    if (password_verify($password, $hash)) {
        // Check if a rehash is needed (e.g., if cost factor changed)
        if ($user_id !== null && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            try {
                $newHash = hash_password($password);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $newHash, $user_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    error_log("Failed to prepare statement for password rehash. User ID: " . $user_id);
                }
            } catch (Exception $e) {
                error_log("Error during password rehash for User ID " . $user_id . ": " . $e->getMessage());
            }
        }
        return true;
    }
    return false;
}

// --- User Registration & Verification ---

/**
 * Registers a new user, hashes their password, generates an activation token,
 * and sends a verification email.
 *
 * @param string $fullname User's full name.
 * @param string $email User's email address.
 * @param string $password User's plain text password.
 * @return array [bool $success, string $message, ?int $user_id]
 */
function register_user(string $fullname, string $email, string $password): array
{
    global $conn;

    // Basic validation (more specific validation should happen before calling this)
    if (empty($fullname) || empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, "Invalid input provided.", null];
    }

    // Check if email already exists
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            return [false, "Email address is already registered.", null];
        }
        $stmt->close();

        // Hash password
        $hashed_password = hash_password($password);

        // Generate activation token
        $activation_token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $activation_token); // Store the hash
        $expiry_timestamp = time() + TOKEN_EXPIRY_ACTIVATION;
        $expiry_datetime = date('Y-m-d H:i:s', $expiry_timestamp);

        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, status, activation_hash, activation_expiry) VALUES (?, ?, ?, 'pending', ?, ?)");
        if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);
        $stmt->bind_param("sssss", $fullname, $email, $hashed_password, $token_hash, $expiry_datetime);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            $stmt->close();

            // Send verification email (pass the raw token)
            $email_sent = send_verification_email($email, $fullname, $activation_token);

            if ($email_sent) {
                return [true, "Registration successful! Please check your email to activate your account.", $user_id];
            } else {
                // Log failure but inform user registration was partially successful
                error_log("Registration successful for {$email} but verification email failed.");
                return [true, "Registration successful! However, we couldn't send the verification email. Please contact support.", $user_id];
            }
        } else {
            $stmt->close();
            error_log("User registration failed for {$email}: " . $conn->error);
            return [false, "Registration failed due to a server error. Please try again later.", null];
        }
    } catch (Exception $e) {
        error_log("Registration Exception: " . $e->getMessage());
        return [false, "An unexpected error occurred during registration.", null];
    }
}

/**
 * Sends an account verification email using PHPMailer.
 *
 * @param string $email Recipient email address.
 * @param string $name Recipient name.
 * @param string $token The raw activation token (not the hash).
 * @return bool True if email was sent successfully, false otherwise.
 */
function send_verification_email(string $email, string $name, string $token): bool
{
    $verification_link = APP_URL . "/portal/verify.php?token=" . $token . "&email=" . urlencode($email);
    $subject = APP_NAME . ' - Verify Your Account';
    $body = '
        <p>Dear ' . htmlspecialchars($name) . ',</p>
        <p>Thank you for registering with ' . APP_NAME . '. To complete your registration and verify your account, please click the button below:</p>
        <p style="text-align: center;"><a href="' . $verification_link . '" style="display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">Verify My Account</a></p>
        <p>If the button doesn\'t work, copy and paste this link into your browser: ' . $verification_link . '</p>
        <p>This link expires in ' . (TOKEN_EXPIRY_ACTIVATION / 3600) . ' hours.</p>
        <p>If you did not create an account, please ignore this email.</p>
        <p>Regards,<br>' . APP_NAME . ' Team</p>';
    $altBody = "Dear {$name},\n\nVerify your account by visiting: {$verification_link}\n\nThis link expires in " . (TOKEN_EXPIRY_ACTIVATION / 3600) . " hours.\n\nRegards,\n" . APP_NAME . " Team";

    return send_email($email, $name, $subject, $body, $altBody);
}

/**
 * Verifies an activation token, activates the user account if valid.
 *
 * @param string $email The user's email.
 * @param string $token The raw activation token from the URL.
 * @return bool True if activation was successful, false otherwise.
 */
function verify_activation_token(string $email, string $token): bool
{
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT id, activation_hash, activation_expiry FROM users WHERE email = ? AND status = 'pending'");
        if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            // Verify token hash and expiry
            $token_hash = hash('sha256', $token);
            if (hash_equals($user['activation_hash'], $token_hash) && strtotime($user['activation_expiry']) > time()) {
                // Activate user and clear token
                $update_stmt = $conn->prepare("UPDATE users SET status = 'active', activation_hash = NULL, activation_expiry = NULL WHERE id = ?");
                if (!$update_stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);
                $update_stmt->bind_param("i", $user['id']);
                $success = $update_stmt->execute();
                $update_stmt->close();
                return $success;
            } else {
                // Token invalid or expired
                // Optionally: Clear expired tokens here or via a cron job
                if (strtotime($user['activation_expiry']) <= time()) {
                    // Clear expired token
                    $clear_stmt = $conn->prepare("UPDATE users SET activation_hash = NULL, activation_expiry = NULL WHERE id = ?");
                    if ($clear_stmt) {
                        $clear_stmt->bind_param("i", $user['id']);
                        $clear_stmt->execute();
                        $clear_stmt->close();
                    }
                }
            }
        } else {
            $stmt->close(); // Close statement even if no user found
        }
    } catch (Exception $e) {
        error_log("Activation Verification Exception: " . $e->getMessage());
    }
    return false;
}

// --- Login & Rate Limiting ---

/**
 * Processes user login, verifies credentials, checks status, handles rate limiting and remember me.
 *
 * @param string $email User's email.
 * @param string $password User's plain text password.
 * @param bool $remember Whether to set a "Remember Me" cookie.
 * @return array [bool $success, string $message, ?string $field_error]
 */
function process_login(string $email, string $password, bool $remember = false): array
{
    global $conn;
    secure_session_start();

    // --- Rate Limiting Check ---
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $login_attempts = get_login_attempts($ip_address, $email);

    if ($login_attempts >= LOGIN_ATTEMPT_LIMIT) {
        $lockout_expiry = get_lockout_expiry($ip_address, $email);
        if ($lockout_expiry > time()) {
            $wait_time = ceil(($lockout_expiry - time()) / 60);
            return [false, "Too many failed login attempts. Please try again in {$wait_time} minutes.", null];
        } else {
            // Lockout expired, reset attempts before proceeding
            clear_login_attempts($ip_address, $email);
        }
    }
    // --- End Rate Limiting Check ---

    try {
        $stmt = $conn->prepare("SELECT id, email, password, fullname, role, status FROM users WHERE email = ?");
        if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            // Verify password (pass user ID for potential rehashing)
            if (verify_password($password, $user['password'], (int)$user['id'])) {
                // Check account status
                if ($user['status'] === 'active') {
                    // --- Login Success ---
                    clear_login_attempts($ip_address, $email); // Clear attempts on success
                    create_user_session($user); // Creates session and regenerates ID

                    // Handle "Remember Me"
                    if ($remember) {
                        set_remember_me_cookie((int)$user['id']);
                    }

                    // Update last login time
                    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("i", $user['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } else {
                         error_log("Failed to prepare statement for updating last login. User ID: " . $user['id']);
                    }

                    return [true, "Login successful.", null];
                    // --- End Login Success ---

                } elseif ($user['status'] === 'pending') {
                    record_login_attempt($ip_address, $email); // Still counts as an attempt
                    return [false, "Your account is pending activation. Please check your email.", null];
                } else { // Suspended, banned, etc.
                    record_login_attempt($ip_address, $email); // Still counts as an attempt
                    return [false, "Your account is currently inactive or suspended. Please contact support.", null];
                }
            } else {
                // --- Incorrect Password ---
                record_login_attempt($ip_address, $email);
                $remaining_attempts = LOGIN_ATTEMPT_LIMIT - get_login_attempts($ip_address, $email);
                $message = "The password you entered is incorrect.";
                if ($remaining_attempts <= 2 && $remaining_attempts > 0) {
                     $message .= " {$remaining_attempts} attempts remaining.";
                } elseif ($remaining_attempts <= 0) {
                     set_lockout($ip_address, $email);
                     $message = "Too many failed login attempts. Your account is temporarily locked.";
                }
                return [false, $message, "password"];
                // --- End Incorrect Password ---
            }
        } else {
            // --- Email Not Found ---
            $stmt->close();
            record_login_attempt($ip_address, $email); // Record attempt even if email doesn't exist to prevent enumeration
            return [false, "No account found with this email address.", "email"];
            // --- End Email Not Found ---
        }
    } catch (Exception $e) {
        error_log("Login Exception: " . $e->getMessage());
        return [false, "An unexpected error occurred during login.", null];
    }
}

// --- Login Rate Limiting Helper Functions ---

/**
 * Records a failed login attempt for a given IP/email combination.
 */
function record_login_attempt(string $ip_address, string $email_identifier): void
{
    // Implement storage (e.g., database table, cache) for attempts
    // Example using a hypothetical DB table 'login_attempts'
    // (ip_address, email_identifier, attempt_time, attempts_count, lockout_until)
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email_identifier, attempt_time, attempts_count) VALUES (?, ?, NOW(), 1)
                                ON DUPLICATE KEY UPDATE attempts_count = attempts_count + 1, attempt_time = NOW()");
        if ($stmt) {
            $stmt->bind_param("ss", $ip_address, $email_identifier);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

/**
 * Gets the current number of failed login attempts.
 */
function get_login_attempts(string $ip_address, string $email_identifier): int
{
    global $conn;
    try {
        // Clear old attempts first (e.g., older than the lockout time)
        $cutoff_time = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_TIME * 2); // Clear attempts older than 2x lockout time
        $clear_stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < ? AND ip_address = ? AND email_identifier = ?");
         if ($clear_stmt) {
            $clear_stmt->bind_param("sss", $cutoff_time, $ip_address, $email_identifier);
            $clear_stmt->execute();
            $clear_stmt->close();
        }

        $stmt = $conn->prepare("SELECT attempts_count FROM login_attempts WHERE ip_address = ? AND email_identifier = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $ip_address, $email_identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return (int)$row['attempts_count'];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to get login attempts: " . $e->getMessage());
    }
    return 0;
}

/**
 * Clears login attempts for a specific IP/email.
 */
function clear_login_attempts(string $ip_address, string $email_identifier): void
{
    global $conn;
    try {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND email_identifier = ?");
         if ($stmt) {
            $stmt->bind_param("ss", $ip_address, $email_identifier);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to clear login attempts: " . $e->getMessage());
    }
}

/**
 * Sets a lockout expiry time.
 */
function set_lockout(string $ip_address, string $email_identifier): void
{
     global $conn;
    try {
        $lockout_until = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
        $stmt = $conn->prepare("UPDATE login_attempts SET lockout_until = ? WHERE ip_address = ? AND email_identifier = ?");
         if ($stmt) {
            $stmt->bind_param("sss", $lockout_until, $ip_address, $email_identifier);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to set lockout: " . $e->getMessage());
    }
}

/**
 * Gets the lockout expiry timestamp.
 */
function get_lockout_expiry(string $ip_address, string $email_identifier): int
{
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT lockout_until FROM login_attempts WHERE ip_address = ? AND email_identifier = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $ip_address, $email_identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return $row['lockout_until'] ? strtotime($row['lockout_until']) : 0;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to get lockout expiry: " . $e->getMessage());
    }
    return 0;
}

// --- Remember Me Functionality ---

/**
 * Sets the "Remember Me" cookie and database token.
 * Uses a secure selector/validator approach.
 *
 * @param int $user_id The ID of the user to remember.
 */
function set_remember_me_cookie(int $user_id): void
{
    global $conn;
    try {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $hashed_validator = hash('sha256', $validator);
        $expires_timestamp = time() + REMEMBER_ME_EXPIRY;
        $expires_datetime = date('Y-m-d H:i:s', $expires_timestamp);

        // Store in database (replace previous tokens for this user)
        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires) VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE selector = VALUES(selector), hashed_validator = VALUES(hashed_validator), expires = VALUES(expires)");
         if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);

        $stmt->bind_param("isss", $user_id, $selector, $hashed_validator, $expires_datetime);
        $stmt->execute();
        $stmt->close();

        // Set cookies
        $cookie_options = [
            'expires' => $expires_timestamp,
            'path' => '/',
            'domain' => '', // Set your domain if needed
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        setcookie('remember_selector', $selector, $cookie_options);
        setcookie('remember_validator', $validator, $cookie_options); // Store raw validator in cookie

    } catch (Exception $e) {
        error_log("Failed to set remember me cookie for User ID {$user_id}: " . $e->getMessage());
        // Clear potentially partially set cookies
        setcookie('remember_selector', '', time() - 3600, '/');
        setcookie('remember_validator', '', time() - 3600, '/');
    }
}

/**
 * Attempts to log in a user based on the "Remember Me" cookie.
 *
 * @return bool True if login was successful, false otherwise.
 */
function login_with_remember_token(): bool
{
    global $conn;
    secure_session_start();

    if (is_logged_in() || !isset($_COOKIE['remember_selector']) || !isset($_COOKIE['remember_validator'])) {
        return false; // Already logged in or cookies not set
    }

    $selector = $_COOKIE['remember_selector'];
    $validator = $_COOKIE['remember_validator'];

    try {
        $stmt = $conn->prepare("SELECT rt.user_id, rt.hashed_validator, rt.expires, u.id, u.fullname, u.email, u.role, u.status
                                FROM remember_tokens rt
                                JOIN users u ON rt.user_id = u.id
                                WHERE rt.selector = ? AND rt.expires > NOW()");
        if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);

        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($token_data = $result->fetch_assoc()) {
            $stmt->close();

            // Verify the validator
            $hashed_validator_from_cookie = hash('sha256', $validator);
            if (hash_equals($token_data['hashed_validator'], $hashed_validator_from_cookie)) {
                // Token is valid, log the user in
                if ($token_data['status'] === 'active') {
                    // Important: Create session *before* potentially setting new remember me cookie
                    create_user_session($token_data);

                    // Refresh the remember me token (optional but recommended for security)
                    // Delete the old one first
                    clear_remember_token($selector);
                    // Set a new one
                    set_remember_me_cookie((int)$token_data['user_id']);

                    // Update last login
                     $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("i", $token_data['user_id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }

                    return true;
                } else {
                    // User associated with token is not active
                    clear_remember_token($selector); // Clear invalid token
                    clear_remember_me_cookies();
                }
            } else {
                // Invalid validator - potential tampering
                clear_remember_token($selector); // Clear invalid token
                clear_remember_me_cookies();
            }
        } else {
             $stmt->close(); // Close statement if no token found
             // No valid token found for selector, clear cookies
             clear_remember_me_cookies();
        }
    } catch (Exception $e) {
        error_log("Remember Me Login Exception: " . $e->getMessage());
        clear_remember_me_cookies(); // Clear cookies on error
    }

    return false;
}

/**
 * Clears a specific remember me token from the database.
 *
 * @param string $selector The selector of the token to clear.
 */
function clear_remember_token(string $selector): void
{
    global $conn;
    try {
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        if ($stmt) {
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to clear remember token for selector {$selector}: " . $e->getMessage());
    }
}

/**
 * Clears the remember me cookies from the browser.
 */
function clear_remember_me_cookies(): void
{
    if (isset($_COOKIE['remember_selector'])) {
        setcookie('remember_selector', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    if (isset($_COOKIE['remember_validator'])) {
        setcookie('remember_validator', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

// --- Password Reset ---

/**
 * Initiates the password reset process for a given email.
 * Returns true if the process completed (or user not found/email invalid for security).
 * Returns 'inactive' if the user exists but is not active.
 *
 * @param string $email
 * @return bool|string
 */
function send_password_reset_email(string $email): bool|string // Changed return type hint
{
    global $conn;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return true; // Don't process invalid emails, but don't reveal error
    }

    try {
        $stmt = $conn->prepare("SELECT id, fullname, status FROM users WHERE email = ?");
        if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            // Check user status
            if ($user['status'] === 'active') {
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $reset_token); // Store hash
                $expiry_timestamp = time() + TOKEN_EXPIRY_RESET;
                $expiry_datetime = date('Y-m-d H:i:s', $expiry_timestamp);

                // Update user record with reset token hash and expiry
                $update_stmt = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expiry = ? WHERE id = ?");
                 if (!$update_stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);

                $update_stmt->bind_param("ssi", $token_hash, $expiry_datetime, $user['id']);

                if ($update_stmt->execute()) {
                    $update_stmt->close();
                    // Send the actual email (pass raw token)
                    $email_sent = send_actual_reset_email($email, $user['fullname'], $reset_token);
                    if (!$email_sent) {
                        error_log("Failed to send password reset email to {$email}.");
                    }
                    // Even if email fails, we return true for security.
                    return true; // Return true on successful process for active user
                } else {
                     $update_stmt->close();
                     error_log("Failed to update reset token for {$email}: " . $conn->error);
                     return true; // Still return true for security
                }
            } else {
                 // User exists but is not active - return specific indicator
                 error_log("Password reset requested for inactive/pending user: {$email}");
                 return 'inactive'; // Return 'inactive' status
            }
        } else {
            // Email not found - do nothing, return true.
             $stmt->close();
             return true; // Return true for non-existent user
        }
    } catch (Exception $e) {
        error_log("Password Reset Request Exception: " . $e->getMessage());
        // Still return true for security.
        return true;
    }
}

/**
 * Sends the actual password reset email containing the link.
 *
 * @param string $email Recipient email.
 * @param string $name Recipient name.
 * @param string $token Raw reset token.
 * @return bool True if email sent successfully, false otherwise.
 */
function send_actual_reset_email(string $email, string $name, string $token): bool
{
    $reset_link = APP_URL . "/portal/reset-password.php?token=" . $token . "&email=" . urlencode($email);
    $subject = APP_NAME . ' - Password Reset Request';
    $body = '
        <p>Dear ' . htmlspecialchars($name) . ',</p>
        <p>We received a request to reset your password. Click the button below:</p>
        <p style="text-align: center;"><a href="' . $reset_link . '" style="display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">Reset Password</a></p>
        <p>If the button doesn\'t work, copy/paste this link: ' . $reset_link . '</p>
        <p>This link expires in ' . (TOKEN_EXPIRY_RESET / 60) . ' minutes.</p>
        <p>If you did not request this, please ignore this email.</p>
        <p>Regards,<br>' . APP_NAME . ' Team</p>';
    $altBody = "Dear {$name},\n\nReset your password by visiting: {$reset_link}\n\nThis link expires in " . (TOKEN_EXPIRY_RESET / 60) . " minutes.\n\nRegards,\n" . APP_NAME . " Team";

    return send_email($email, $name, $subject, $body, $altBody);
}

/**
 * Verifies a password reset token.
 *
 * @param string $email The user's email.
 * @param string $token The raw reset token from the URL.
 * @return int|null The user ID if the token is valid, null otherwise.
 */
function verify_reset_token(string $email, string $token): ?int
{
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT id, reset_token_hash, reset_token_expiry FROM users WHERE email = ? AND status = 'active'");
         if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            // Verify token hash and expiry
            $token_hash = hash('sha256', $token);
            if ($user['reset_token_hash'] !== null &&
                hash_equals($user['reset_token_hash'], $token_hash) &&
                strtotime($user['reset_token_expiry']) > time())
            {
                // Token is valid
                return (int)$user['id'];
            } else {
                 // Token invalid or expired - clear it if expired
                 if ($user['reset_token_hash'] !== null && strtotime($user['reset_token_expiry']) <= time()) {
                     clear_reset_token((int)$user['id']);
                 }
            }
        } else {
             $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Reset Token Verification Exception: " . $e->getMessage());
    }
    return null;
}

/**
 * Updates the user's password and clears the reset token.
 *
 * @param int $user_id The ID of the user whose password to update.
 * @param string $new_password The new plain text password.
 * @return bool True on success, false on failure.
 */
function update_password(int $user_id, string $new_password): bool
{
    global $conn;
    try {
        $hashed_password = hash_password($new_password);

        // Update password and clear reset token fields
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expiry = NULL WHERE id = ?");
         if (!$stmt) throw new RuntimeException("DB prepare failed: " . $conn->error);

        $stmt->bind_param("si", $hashed_password, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    } catch (Exception $e) {
        error_log("Password Update Exception for User ID {$user_id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Clears the reset token for a user (e.g., after expiry or successful reset).
 *
 * @param int $user_id The user's ID.
 */
function clear_reset_token(int $user_id): void
{
    global $conn;
    try {
        $stmt = $conn->prepare("UPDATE users SET reset_token_hash = NULL, reset_token_expiry = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to clear reset token for User ID {$user_id}: " . $e->getMessage());
    }
}
/**
 * Sets a flash message to be displayed on the next page load.
 *
 * @param string $message The message content (can be HTML).
 * @param string $type The type of message ('success', 'error', 'warning', 'info').
 */
function set_flash_message(string $message, string $type): void
{
    secure_session_start();
    $_SESSION['flash_message'] = [
        'content' => $message,
        'type' => $type
    ];
}

/**
 * Retrieves and clears the flash message.
 *
 * @return array|null [string $content, string $type] or null if no message.
 */
function get_flash_message(): ?array
{
    secure_session_start();
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// --- Email Sending Utility ---

/**
 * General purpose email sending function using PHPMailer.
 * Reads configuration from config.php constants.
 *
 * @param string $toEmail Recipient email address.
 * @param string $toName Recipient name.
 * @param string $subject Email subject.
 * @param string $bodyHTML HTML email body.
 * @param string $bodyText Plain text email body.
 * @return bool True if email sent successfully, false otherwise.
 */
function send_email(string $toEmail, string $toName, string $subject, string $bodyHTML, string $bodyText): bool
{
    // Ensure config constants are defined
    if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD') ||
        !defined('SMTP_PORT') || !defined('SMTP_ENCRYPTION') || !defined('SMTP_FROM_EMAIL') ||
        !defined('SMTP_FROM_NAME')) {
        error_log("SMTP configuration constants are not defined in config.php");
        return false;
    }

    $mail = new PHPMailer(true); // Enable exceptions

    try {
        // Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for testing
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = defined('PHPMailer::ENCRYPTION_STARTTLS') && SMTP_ENCRYPTION === 'tls'
                            ? PHPMailer::ENCRYPTION_STARTTLS
                            : (defined('PHPMailer::ENCRYPTION_SMTPS') && SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : '');
        $mail->Port       = (int)SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHTML;
        $mail->AltBody = $bodyText;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log("Email sending failed to {$toEmail}: " . $mail->ErrorInfo);
        return false;
    } catch (Exception $e) {
        // Catch other potential exceptions
        error_log("General exception during email sending to {$toEmail}: " . $e->getMessage());
        return false;
    }
}

?>