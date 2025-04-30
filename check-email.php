<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/check-email.php
require_once 'includes/auth.php'; // Include authentication functions if needed (e.g., for flash messages)

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists (ensure it's available)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve email from GET parameter (sanitize it)
$email = isset($_GET['email']) ? sanitize_input($_GET['email']) : '';
$email_sent = isset($_GET['email_sent']) && $_GET['email_sent'] == '1';
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'verification'; // 'verification' or 'reset'

// Determine the appropriate resend link based on the type
$resend_link = ($type === 'reset') ? 'enter-email.php' : 'sign-up.php'; // Adjust if needed

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
 <head>
  <title>Majlisu Ahlil Qur'an - Check Your Email</title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Check your email to complete the process" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="assets/vendors/apexcharts/apexcharts.css" rel="stylesheet"/>
  <link href="assets/vendors/keenicons/styles.bundle.css" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>

  <!-- Custom Styles (Optional: Add if specific styling is needed) -->
  <style>
    .success-message {
      animation: fadeInDown 0.5s ease-in-out;
      border-left-width: 4px;
    }

    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
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
   if (getThemeMode() === 'dark') {
     document.documentElement.classList.add('dark');
   }
  </script>
  <!-- End of Theme Mode -->

  <!-- Page -->
  <style>
   .page-bg {
     background-image: url('assets/media/images/2600x1200/bg-10.png');
     background-size: cover;
   }
   .dark .page-bg {
     background-image: url('assets/media/images/2600x1200/bg-10-dark.png');
   }
  </style>
  <div class="flex items-center justify-center grow bg-center bg-no-repeat page-bg">
   <div class="card max-w-[440px] w-full">

    <!-- Optional Success Message -->
    <?php if ($email_sent): ?>
      <div class="success-message bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 mx-4 mt-4 rounded shadow-md" role="alert">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium">Email sent successfully!</p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Flash Messages -->
    <div id="flash-message-container" class="mx-4 mt-4">
      <?php echo get_flash_message(); ?>
    </div>

    <div class="card-body p-10">
     <div class="flex justify-center py-10">
      <img alt="Email Illustration" class="dark:hidden max-h-[130px]" src="assets/media/illustrations/30.svg"/>
      <img alt="Email Illustration Dark" class="light:hidden max-h-[130px]" src="assets/media/illustrations/30-dark.svg"/>
     </div>
     <h3 class="text-lg font-medium text-gray-900 text-center mb-3">
      Check your email
     </h3>
     <div class="text-2sm text-center text-gray-700 mb-7.5">
      <?php if ($type === 'reset'): ?>
        We have sent a password reset link to
      <?php else: ?>
        Please click the link sent to
      <?php endif; ?>

      <?php if (!empty($email)): ?>
        <span class="text-gray-900 font-medium">
         <?php echo htmlspecialchars($email); ?>
        </span>
      <?php else: ?>
        your email address
      <?php endif; ?>

      <?php if ($type === 'reset'): ?>
        to help you reset your password.
      <?php else: ?>
        to verify your account.
      <?php endif; ?>
      <br/>
      Thank you.
     </div>
     <div class="flex justify-center mb-5">
      <a class="btn btn-primary flex justify-center" href="sign-in.php"> <!-- Or index.php / dashboard.php -->
       Back to Sign In
      </a>
     </div>
     <div class="flex items-center justify-center gap-1">
      <span class="text-xs text-gray-700">
       Didn’t receive the email?
      </span>
      <?php if (!empty($email)): ?>
      <a class="text-xs font-medium link"
         href="resend-email.php?email=<?php echo urlencode($email); ?>&type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>">
       Resend
      </a>
      <?php endif; ?>
     </div>
    </div>
   </div>
  </div>
  <!-- End of Page -->

  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
  <script src="assets/vendors/apexcharts/apexcharts.min.js"></script>
  <!-- End of Scripts -->
 </body>
</html>