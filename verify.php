<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/verify.php
require_once 'includes/auth.php'; // Includes db.php, config.php, functions.php implicitly if set up correctly

// Start session if not already started (good practice, needed for potential flash messages)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

// Check if token and email are provided
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    // Sanitize email just in case, although it's mainly used for lookup
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

    // Call the verification function from auth.php
    if (verify_activation_token($email, $token)) {
        $success = "Your account has been successfully verified. You can now sign in.";
        // Optional: Set a flash message if redirecting immediately
        // set_flash_message('Account verified successfully!', 'success');
        // redirect('sign-in.php'); // Example redirect
    } else {
        // The function verify_activation_token handles logging the specific reason
        $error = "Invalid or expired verification link. Please try signing up again or contact support.";
    }

} else {
    $error = "Invalid verification link parameters.";
}

// Set security headers (should be done before any HTML output)
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Adjust CSP as needed for this specific page (likely simpler than signup/signin)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
 <head>
  <title>Majlisu Ahlil Qur'an - Verify Account</title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Verify your account for Majlisu Ahlil Qur'an International Qur'an Competition" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Removed apexcharts.css as it's likely not needed here -->
  <link href="assets/vendors/keenicons/styles.bundle.css" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>
  <!-- Add page-specific styles if needed -->
  <style>
   .page-bg {
     background-image: url('assets/media/images/2600x1200/bg-10.png'); /* Adjust if needed */
     background-size: cover;
   }
   .dark .page-bg {
     background-image: url('assets/media/images/2600x1200/bg-10-dark.png'); /* Adjust if needed */
   }
  </style>
 </head>
 <body class="antialiased flex h-full text-base text-gray-700 dark:bg-coal-500">
  <!-- Theme Mode script -->
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
   if (getThemeMode() === 'dark') {
     document.documentElement.classList.add('dark');
   }
  </script>

  <!-- Page -->
  <div class="flex items-center justify-center grow bg-center bg-no-repeat page-bg">
   <div class="card max-w-[500px] w-full shadow-lg">
    <div class="card-body p-10 text-center">
      <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-6">Account Verification</h2>

      <!-- Flash Messages (Optional but good practice) -->
      <div id="flash-message-container" class="mb-4">
        <?php echo get_flash_message(); ?>
      </div>

      <?php if (!empty($error)): ?>
      <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded relative mb-6" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
      <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded relative mb-6" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
      </div>
      <?php endif; ?>

      <div class="mt-6">
        <?php if (!empty($success)): ?>
          <a href="sign-in.php" class="btn btn-primary w-full sm:w-auto">Proceed to Sign In</a>
        <?php else: ?>
          <!-- Provide helpful next steps on error -->
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">If you continue to have issues, please try signing up again or contact support.</p>
          <a href="sign-up.php" class="btn btn-secondary w-full sm:w-auto mr-2">Back to Sign Up</a>
          <a href="contact.php" class="btn btn-outline w-full sm:w-auto mt-2 sm:mt-0">Contact Support</a> <!-- Example link -->
        <?php endif; ?>
      </div>
    </div>
   </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
  <!-- Removed apexcharts.min.js -->
  <!-- End of Scripts -->
 </body>
</html>