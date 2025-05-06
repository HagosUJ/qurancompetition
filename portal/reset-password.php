<?php
require_once 'includes/auth.php'; // Includes config, db, functions

// Start session for CSRF token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$user_id = null; // To store the validated user ID

// --- Initial Token Validation (on page load) ---
if (empty($token) || empty($email)) {
    $error = "Invalid password reset link parameters.";
} else {
    // Use the function from auth.php to verify the token
    $user_id = verify_reset_token($email, $token);
    if ($user_id === null) {
        $error = "Invalid or expired password reset link. Please request a new one if needed.";
        // Optionally log this attempt
        error_log("Invalid/Expired reset token attempt for email: {$email}");
    }
}

// --- Process Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id !== null) { // Only process if token was initially valid
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $password = $_POST['user_new_password'];
        $confirm_password = $_POST['user_confirm_password'];

        // Validate passwords
        if (empty($password) || empty($confirm_password)) {
            $error = "Both password fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!is_strong_password($password)) { // Use the strength check function
             $error = "Password does not meet complexity requirements (minimum 8 characters, including uppercase, lowercase, number, and special character).";
        } else {
            // No need to re-verify token here, $user_id is already validated

            // Hash new password using the function from auth.php
            $hashed_password = hash_password($password);

            // Update user password and clear reset token fields using the correct column names
            global $conn; // Get DB connection
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expiry = NULL WHERE id = ?");
            if (!$update_stmt) {
                 error_log("DB prepare failed for password update: " . $conn->error);
                 $error = "An internal error occurred. Please try again later.";
            } else {
                $update_stmt->bind_param("si", $hashed_password, $user_id);

                if ($update_stmt->execute()) {
                    $update_stmt->close();
                    // Password updated successfully
                    $success = "Password updated successfully. You can now sign in with your new password.";
                    // Optionally log successful reset
                    log_activity($user_id, 'password_reset', 'Password successfully reset.');

                    // Clear the validated user_id to prevent form resubmission issues
                    $user_id = null;

                    // Clear the token from the session if it was stored there (unlikely here, but good practice)
                    // unset($_SESSION['reset_token_data']);

                } else {
                    $update_stmt->close();
                    error_log("Failed to update password for user ID {$user_id}: " . $conn->error);
                    $error = "Failed to update password. Please try again.";
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id === null) {
    // Handle POST attempt when the initial token was already invalid
    $error = "Invalid or expired password reset link. Cannot process request.";
}

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Basic CSP, adjust if needed
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
 <head>
  <title>Majlisu Ahlil Qur'an - Reset Password</title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Reset your password for Majlisu Ahlil Qur'an International" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Removed apexcharts.css -->
  <link href="assets/vendors/keenicons/styles.bundle.css" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>
  <!-- Add custom styles if needed -->
  <style>
    .error-message { animation: fadeInDown 0.5s ease-in-out; border-left-width: 4px; }
    .success-message { animation: fadeInDown 0.5s ease-in-out; border-left-width: 4px; }
    @keyframes fadeInDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .input-error { border-color: #ef4444 !important; background-color: #fef2f2; }
    .input-error:focus-within { border-color: #ef4444 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important; }
    .field-error-text { color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem; animation: fadeIn 0.3s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
  </style>
 </head>
 <body class="antialiased flex h-full text-base text-gray-700 dark:bg-coal-500">
  <!-- Theme Mode -->
  <script>
   const defaultThemeMode = 'light';
   const getThemeMode = () => {
     const themeMode = localStorage.getItem('theme_mode') || defaultThemeMode;
     if (themeMode === 'system') {
       return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
     }
     return themeMode;
   }
   document.documentElement.setAttribute('data-theme-mode', getThemeMode());
   if (getThemeMode() === 'dark') { document.documentElement.classList.add('dark'); }
  </script>

  <!-- Page -->
  <style>
   .page-bg { background-image: url('assets/media/images/2600x1200/bg-10.png'); background-size: cover; }
   .dark .page-bg { background-image: url('assets/media/images/2600x1200/bg-10-dark.png'); }
  </style>
  <div class="flex items-center justify-center grow bg-center bg-no-repeat page-bg">
   <div class="card max-w-[400px] w-full shadow-lg"> <!-- Slightly wider card -->

    <!-- Display Messages -->
    <?php if (!empty($error)): ?>
      <div class="error-message bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 mx-4 mt-4 rounded" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="success-message bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 mx-4 mt-4 rounded" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
        <div class="mt-4 text-center">
          <a href="sign-in.php" class="btn btn-primary">Proceed to Sign In</a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Show Form only if token was initially valid and password not yet successfully reset -->
    <?php if ($user_id !== null && empty($success)): ?>
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?token=' . urlencode($token) . '&email=' . urlencode($email)); ?>" class="card-body flex flex-col gap-5 p-10" method="post" novalidate>
     <!-- CSRF Token -->
     <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

     <div class="text-center mb-2">
      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
       Reset Your Password
      </h3>
      <span class="text-sm text-gray-600 dark:text-gray-400">
       Enter and confirm your new password below.
      </span>
     </div>

     <div class="flex flex-col gap-1">
      <label class="form-label font-medium text-gray-900 dark:text-gray-200" for="user_new_password">
       New Password
      </label>
      <div class="input <?php echo (!empty($error) && (strpos($error, 'required') !== false || strpos($error, 'match') !== false || strpos($error, 'complexity') !== false)) ? 'input-error' : ''; ?>" data-toggle-password="true">
       <input id="user_new_password" name="user_new_password" placeholder="Enter a new password" type="password" required/>
       <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
        <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
        <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
       </button>
      </div>
       <!-- Specific error message for password field -->
       <?php if (!empty($error) && (strpos($error, 'required') !== false || strpos($error, 'match') !== false || strpos($error, 'complexity') !== false)): ?>
           <div class="field-error-text"><?php echo htmlspecialchars($error); ?></div>
       <?php endif; ?>
       <p class="text-xs text-gray-500 mt-1">Min 8 chars, incl. upper, lower, number, symbol.</p>
     </div>

     <div class="flex flex-col gap-1">
      <label class="form-label font-medium text-gray-900 dark:text-gray-200" for="user_confirm_password">
       Confirm New Password
      </label>
      <div class="input <?php echo (!empty($error) && (strpos($error, 'required') !== false || strpos($error, 'match') !== false)) ? 'input-error' : ''; ?>" data-toggle-password="true">
       <input id="user_confirm_password" name="user_confirm_password" placeholder="Re-enter the new password" type="password" required/>
       <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
        <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
        <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
       </button>
      </div>
       <!-- Specific error message for confirm password field -->
       <?php if (!empty($error) && strpos($error, 'match') !== false): ?>
           <div class="field-error-text"><?php echo htmlspecialchars($error); ?></div>
       <?php endif; ?>
     </div>

     <button type="submit" class="btn btn-primary flex justify-center grow mt-2">
      Update Password
     </button>
    </form>
    <?php elseif (empty($success) && !empty($error)): ?>
        <!-- If initial token check failed, show link back -->
        <div class="p-10 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Please return to the password reset request page.</p>
            <a href="enter-email.php" class="btn btn-secondary">Request Reset Link</a>
        </div>
    <?php endif; ?>

   </div>
  </div>
  <!-- End of Page -->

  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
  <!-- Removed apexcharts.min.js -->
  <!-- Add specific JS for password strength indicator if desired -->
 </body>
</html>