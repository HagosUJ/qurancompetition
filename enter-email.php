<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/enter-email.php
require_once 'includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$email = '';
$field_error = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $email = sanitize_input($_POST['email']);
        
        // Validate input
        if (empty($email)) {
            $error = "Email address is required.";
            $field_error = 'email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $field_error = 'email';
          } else {
            try {
                // Process password reset request
                $reset_status = send_password_reset_email($email); // Capture the return status

                if ($reset_status === true) {
                    // User active (or doesn't exist) - proceed to check email page
                    redirect('check-email.php?email=' . urlencode($email) . '&type=reset');
                    exit;
                } elseif ($reset_status === 'inactive') {
                    // User exists but is inactive
                    $error = "Your account is not active. Password reset is not available. Please contact support if you believe this is an error.";
                    $field_error = 'email'; // Highlight the email field
                } else {
                    // Should ideally not happen with current logic, but handle potential false return
                    $error = "Failed to initiate password reset. Please try again later.";
                }
            } catch (Exception $e) {
                // Log the error for debugging
                error_log("Password reset error: " . $e->getMessage());
                $error = "An error occurred. Please try again later.";
            }
        }
    }
}
// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
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
    
    <!-- Error Messages - Only show global error if there's no field-specific error -->
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
    
    <!-- Success Messages -->
    <?php if (!empty($success)): ?>
      <div class="error-message bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 mx-4 mt-4 rounded shadow-md" role="alert">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium"><?php echo $success; ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>
    
    <!-- Flash Messages -->
    <div id="flash-message-container">
      <?php echo get_flash_message(); ?>
    </div>
    
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="card-body flex flex-col gap-5 p-10" id="forgot_password_form" method="post" novalidate>
      <!-- CSRF Protection -->
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      
     <div class="text-center mb-2.5">
      <h3 class="text-lg font-medium text-gray-900 leading-none mb-2.5">
       Forgot Password
      </h3>
      <span class="text-2sm text-gray-700">
       Enter your email to reset your password
      </span>
     </div>
     
     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900" for="email">
       Email
      </label>
      <input class="input <?php echo ($field_error === 'email') ? 'input-error' : ''; ?>" 
             id="email" name="email" placeholder="Enter your email" type="email" 
             value="<?php echo htmlspecialchars($email); ?>" required />
      <?php if ($field_error === 'email'): ?>
        <div class="field-error-text" id="email-error"><?php echo $error; ?></div>
      <?php endif; ?>
     </div>
     
     <button type="submit" class="btn btn-primary flex justify-center grow" id="submit-btn">
      <span class="spinner">
        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </span>
      Submit
     </button>
     
     <div class="text-center">
      <a href="sign-in.php" class="text-2sm link">Back to Sign In</a>
     </div>
    </form>
   </div>
  </div>
  
  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
  <script src="assets/vendors/apexcharts/apexcharts.min.js"></script>
  
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('forgot_password_form');
    const emailInput = document.getElementById('email');
    const submitBtn = document.getElementById('submit-btn');
    
    // Validate form before submission
    form.addEventListener('submit', function(e) {
      let isValid = true;
      
      // Reset visual error states
      emailInput.classList.remove('input-error');
      
      const emailErrorElement = document.getElementById('email-error');
      if (emailErrorElement) emailErrorElement.remove();
      
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
    
    // Focus on email input
    setTimeout(function() {
      emailInput.focus();
    }, 100);
  });
  </script>
</body>
</html>