<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/sign-in.php
require_once 'includes/auth.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php'); // Changed from dashboard.php
}

// Initialize variables
$error = '';
$email = '';
$field_error = '';
$login_attempt = $_SESSION['login_attempt'] ?? 0;

// Check if login is locked due to too many attempts
$locked_until = $_SESSION['login_locked_until'] ?? 0;
if ($locked_until > time()) {
    $wait_time = ceil(($locked_until - time()) / 60);
    $error = "Too many login attempts. Please try again after {$wait_time} minutes.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $locked_until <= time()) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please try again.";
    } else {
        // Track login attempts
        $_SESSION['login_attempt'] = ++$login_attempt;

        // Get and sanitize user input
        $email = sanitize_input($_POST['email']);
        $password = $_POST['user_password'];
        $remember = isset($_POST['check']) ? true : false;

        // Validate input
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
            $field_error = empty($email) ? 'email' : 'password';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $field_error = 'email';
        } else {
            // Process login
            list($success, $message, $error_field) = process_login($email, $password, $remember);

            if ($success) {
                // Reset login attempts on success
                unset($_SESSION['login_attempt']);
                unset($_SESSION['login_locked_until']);

                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Direct redirect without SweetAlert
                header("Location: index.php"); // Changed from dashboard.php
                exit();
            } else {
                $error = $message;
                $field_error = $error_field ?? '';

                // Lock account after 5 failed attempts
                if ($_SESSION['login_attempt'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + (15 * 60); // Lock for 15 minutes
                    $error = "Too many failed login attempts. Your account is temporarily locked for 15 minutes.";
                    $field_error = '';
                }
            }
        }
    }
}

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline' 'unsafe-eval'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
 <head>
  <title>Majlisu Ahlil Qur'an - Sign In</title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Sign in to Majlisu Ahlil Qur'an International Qur'an Competition" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="assets/vendors/apexcharts/apexcharts.css" rel="stylesheet"/>
  <link href="assets/vendors/keenicons/styles.bundle.css" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>

  <!-- Custom Styles -->
  <style>
    .error-message {
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

    .input-error {
      border-color: #ef4444 !important;
      background-color: #fef2f2;
    }

    .input-error:focus-within {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
    }

    .input:focus-within {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    }

    .btn-primary {
      position: relative;
      overflow: hidden;
    }

    .btn-primary .spinner {
      position: absolute;
      top: 50%;
      left: 50%;
      margin-top: -10px;
      margin-left: -10px;
      display: none;
    }

    .btn-primary.loading {
      color: transparent;
    }

    .btn-primary.loading .spinner {
      display: block;
    }

    .field-error-text {
      color: #ef4444;
      font-size: 0.75rem;
      margin-top: 0.25rem;
      animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
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
     <div class="card max-w-[370px] w-full">
    <!-- Error Messages - Only show global error message if there's no field-specific error -->
    <?php if (!empty($error) && empty($field_error)): ?>
      <div class="error-message bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 mx-4 mt-4 rounded shadow-md" role="alert">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium"><?php echo $error; ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Flash Messages -->
    <div id="flash-message-container">
      <?php echo get_flash_message(); ?>
    </div>
    

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="card-body flex flex-col gap-5 p-10" id="sign_in_form" method="post" novalidate>
      <!-- CSRF Protection -->
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

      <div class="text-center mb-2.5">
        <h3 class="text-lg font-medium text-gray-900 leading-none mb-2.5">
         Sign in
        </h3>
        <div class="flex items-center justify-center font-medium">
         <span class="text-2sm text-gray-700 me-1.5">
          Need an account?
         </span>
         <a class="text-2sm link" href="sign-up.php">
          Sign up
         </a>
        </div>
      </div>

      <!-- <div class="grid grid-cols-2 gap-2.5">
        <a class="btn btn-light btn-sm justify-center" href="#">
         <img alt="" class="size-3.5 shrink-0" src="assets/media/brand-logos/google.svg"/>
         Use Google
        </a>
        <a class="btn btn-light btn-sm justify-center" href="#">
         <img alt="" class="size-3.5 shrink-0 dark:hidden" src="assets/media/brand-logos/apple-black.svg"/>
         <img alt="" class="size-3.5 shrink-0 light:hidden" src="assets/media/brand-logos/apple-white.svg"/>
         Use Apple
        </a>
      </div>

      <div class="flex items-center gap-2">
        <span class="border-t border-gray-200 w-full"></span>
        <span class="text-2xs text-gray-500 font-medium uppercase">Or</span>
        <span class="border-t border-gray-200 w-full"></span>
      </div> -->

      <div class="flex flex-col gap-1">
        <label class="form-label font-normal text-gray-900" for="email">
         Email
        </label>
        <input class="input <?php echo ($field_error === 'email') ? 'input-error' : ''; ?>"
               id="email" name="email" placeholder="email@email.com" type="email"
               value="<?php echo htmlspecialchars($email); ?>" autocomplete="email" required
               pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"/>
        <?php if ($field_error === 'email'): ?>
        <div class="field-error-text" id="email-error"><?php echo $error; ?></div>
        <?php endif; ?>
      </div>

      <div class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-1">
          <label class="form-label font-normal text-gray-900" for="user_password">
            Password
          </label>
          <a class="text-2sm link shrink-0" href="enter-email.php">
            Forgot Password?
          </a>
        </div>
        <div class="input <?php echo ($field_error === 'password') ? 'input-error' : ''; ?>" data-toggle-password="true">
          <input id="user_password" name="user_password" placeholder="Enter Password" type="password"
                value="" autocomplete="current-password" required/>
          <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
            <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
            <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
          </button>
        </div>
        
        <?php if ($field_error === 'password'): ?>
        <div class="field-error-text" id="password-error"><?php echo $error; ?></div>
        <?php endif; ?>
      </div>

      <label class="checkbox-group">
        <input class="checkbox checkbox-sm" id="check" name="check" type="checkbox" value="1"/>
        <span class="checkbox-label">
         Remember me
        </span>
      </label>

      <button type="submit" class="btn btn-primary flex justify-center grow" id="submit-btn">
        <span class="spinner">
          <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </span>
        Sign In
      </button>
    </form>
   </div>
  </div>
  <!-- End of Page -->

  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"> </script>
<script src="assets/vendors/apexcharts/apexcharts.min.js">
</script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('sign_in_form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('user_password');
    const submitBtn = document.getElementById('submit-btn');

    // Validate form before submission
    form.addEventListener('submit', function(e) {
      let isValid = true;

      // Reset visual error states
      emailInput.classList.remove('input-error');
      passwordInput.parentElement.classList.remove('input-error');

      const emailErrorElement = document.getElementById('email-error');
      const passwordErrorElement = document.getElementById('password-error');

      // Remove existing error messages if present
      if (emailErrorElement) emailErrorElement.remove();
      if (passwordErrorElement) passwordErrorElement.remove();

      // Validate email
      if (!emailInput.value.trim()) {
        isValid = false;
        emailInput.classList.add('input-error');

        const errorDiv = document.createElement('div');
        errorDiv.id = 'email-error';
        errorDiv.className = 'field-error-text';
        errorDiv.textContent = 'Email is required';
        emailInput.parentElement.appendChild(errorDiv);
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
        isValid = false;
        emailInput.classList.add('input-error');

        const errorDiv = document.createElement('div');
        errorDiv.id = 'email-error';
        errorDiv.className = 'field-error-text';
        errorDiv.textContent = 'Please enter a valid email address';
        emailInput.parentElement.appendChild(errorDiv);
      }

      // Validate password
      if (!passwordInput.value) {
        isValid = false;
        passwordInput.parentElement.classList.add('input-error');

        const errorDiv = document.createElement('div');
        errorDiv.id = 'password-error';
        errorDiv.className = 'field-error-text';
        errorDiv.textContent = 'Password is required';
        passwordInput.parentElement.parentElement.appendChild(errorDiv);
      }

      if (!isValid) {
        e.preventDefault();
      } else {
        // Show loading state
        submitBtn.classList.add('loading');
      }
    });

    // Input event listeners to clear errors when typing
    emailInput.addEventListener('input', function() {
      emailInput.classList.remove('input-error');
      const errorElement = document.getElementById('email-error');
      if (errorElement) errorElement.remove();
    });

    passwordInput.addEventListener('input', function() {
      passwordInput.parentElement.classList.remove('input-error');
      const errorElement = document.getElementById('password-error');
      if (errorElement) errorElement.remove();
    });

    // Focus on first input
    setTimeout(function() {
      if (!emailInput.value) {
        emailInput.focus();
      }
    }, 100);
  });
  </script>
 </body>
</html>