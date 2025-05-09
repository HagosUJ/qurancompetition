<?php
require_once 'db.php';

// Sanitize user input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Sanitize filename
function sanitize_filename($filename) {
    // Remove potentially harmful characters, replace spaces, etc.
    $filename = preg_replace("/[^a-zA-Z0-9\.\-\_]/", "_", $filename);
    // Remove leading/trailing underscores/dots
    $filename = trim($filename, '_.-');
    // Prevent multiple consecutive underscores/dots
    $filename = preg_replace('/[_.-]{2,}/', '_', $filename);
    // Limit length if necessary
    $filename = substr($filename, 0, 200); // Example limit
    return $filename ?: 'uploaded_file'; // Return default if empty after sanitization
}

// Display error message
function display_error($message) {
    return '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
              <span class="block sm:inline">' . $message . '</span>
            </div>';
}

// Display success message
function display_success($message) {
    return '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
              <span class="block sm:inline">' . $message . '</span>
            </div>';
}

// Redirect with a flash message
function redirect($url, $message = "", $type = "success") {
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit();
}

/**
 * Sets a flash message in the session.
 *
 * @param string $message The message content.
 * @param string $type The message type (e.g., 'success', 'error', 'warning', 'info'). Defaults to 'success'.
 */
function set_flash_message(string $message, string $type = 'success') {
    // Ensure session is active (though it should be from auth.php)
    if (session_status() === PHP_SESSION_NONE) {
        error_log("Warning: Session not started before calling set_flash_message.");
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Get flash message and clear it
function get_flash_message() {
    $message = "";
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'success';
        
        if ($type == 'success') {
            $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 mx-4 rounded shadow-sm" role="alert">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm">' . $_SESSION['flash_message'] . '</p>
                    </div>
                </div>
            </div>';
        } else {
            $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 mx-4 rounded shadow-sm" role="alert">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm">' . $_SESSION['flash_message'] . '</p>
                    </div>
                </div>
            </div>';
        }
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    return $message;
}

/**
 * Checks if a password meets complexity requirements.
 * Requires at least 8 characters and at least two of: uppercase, lowercase, number, special character.
 *
 * @param string $password The password to check.
 * @return bool True if the password is strong enough, false otherwise.
 */
function is_strong_password(string $password): bool
{
    // Minimum 8 characters
    if (strlen($password) < 8) {
        return false;
    }

    // Check for character types
    $has_uppercase = preg_match('/[A-Z]/', $password);
    $has_lowercase = preg_match('/[a-z]/', $password);
    $has_number = preg_match('/[0-9]/', $password);
    $has_special = preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $password);

    // Count how many character types are present
    $types_count = $has_uppercase + $has_lowercase + $has_number + $has_special;

    // Require at least two character types
    return $types_count >= 2;
}

/**
 * Logs out the current user by destroying the session and redirecting.
 *
 * @param string $redirect_url The URL to redirect to after logout. Defaults to 'sign-in.php'.
 */
function logout_user(string $redirect_url = 'sign-in.php') {
    // Ensure session is started before trying to manipulate it
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Unset all session variables
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // Redirect to the specified page
    redirect($redirect_url);
}
?>