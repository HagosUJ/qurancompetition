<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/sign-up.php
require_once 'includes/auth.php';

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
    redirect('dashboard.php');
}

$error = '';
$success = '';
$field_error = '';
$fullname = '';
$email = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please try again.";
    } else {
        // Get and sanitize user input
        $fullname = sanitize_input($_POST['fullname']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $terms = isset($_POST['terms']) ? true : false;
        
        // Validate inputs
        if (empty($fullname)) {
            $error = "Full name is required.";
            $field_error = 'fullname';
        } elseif (empty($email)) {
            $error = "Email address is required.";
            $field_error = 'email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $field_error = 'email';
        } elseif (empty($password)) {
            $error = "Password is required.";
            $field_error = 'password';
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
            $field_error = 'password';
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
            $field_error = 'confirm_password';
        } elseif (!$terms) {
            $error = "You must accept the Terms & Conditions.";
            $field_error = 'terms';
        } else {
            // Process registration
            list($success_status, $message, $user_id) = register_user($fullname, $email, $password);
            
            if ($success_status) {
                $success = $message;
                
                // Clear the form fields after successful submission
                $fullname = '';
                $email = '';
            } else {
                $error = $message;
                
                // Determine which field caused the error
                if (strpos(strtolower($message), 'email') !== false) {
                    $field_error = 'email';
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

?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
 <head>
  <title>Majlisu Ahlil Qur'an - Sign Up</title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Sign up for Majlisu Ahlil Qur'an International Qur'an Competition" name="description"/>
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
    
    .checkbox-error .checkbox {
      border-color: #ef4444 !important;
    }
    
    .checkbox-error .checkbox-label {
      color: #ef4444 !important;
    }
    
    .password-strength {
      height: 5px;
      margin-top: 5px;
      border-radius: 2px;
      transition: all 0.3s ease;
    }
    
    .strength-weak {
      width: 25%;
      background-color: #ef4444;
    }
    
    .strength-medium {
      width: 50%;
      background-color: #f59e0b;
    }
    
    .strength-strong {
      width: 75%;
      background-color: #3b82f6;
    }
    
    .strength-very-strong {
      width: 100%;
      background-color: #10b981;
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
    
    <!-- Success Message -->
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
    
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="card-body flex flex-col gap-5 p-10" id="sign_up_form" method="post" novalidate>
      <!-- CSRF Protection -->
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      
     <div class="text-center mb-2.5">
      <h3 class="text-lg font-medium text-gray-900 leading-none mb-2.5">
       Sign up
      </h3>
      <div class="flex items-center justify-center font-medium">
       <span class="text-2sm text-gray-700 me-1.5">
        Already have an account?
       </span>
       <a class="text-2sm link" href="sign-in.php">
        Sign in
       </a>
      </div>
     </div>
     
     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900" for="fullname">
       Full Name
      </label>
      <input class="input <?php echo ($field_error === 'fullname') ? 'input-error' : ''; ?>" 
             id="fullname" name="fullname" placeholder="Enter your full name" type="text" 
             value="<?php echo htmlspecialchars($fullname); ?>" required/>
      <?php if ($field_error === 'fullname'): ?>
        <div class="field-error-text" id="fullname-error"><?php echo $error; ?></div>
      <?php endif; ?>
     </div>
     
     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900" for="email">
       Email
      </label>
      <input class="input <?php echo ($field_error === 'email') ? 'input-error' : ''; ?>" 
             id="email" name="email" placeholder="email@email.com" type="email" 
             value="<?php echo htmlspecialchars($email); ?>" required/>
      <?php if ($field_error === 'email'): ?>
        <div class="field-error-text" id="email-error"><?php echo $error; ?></div>
      <?php endif; ?>
     </div>
     
     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900" for="password">
       Password
      </label>
      <div class="input <?php echo ($field_error === 'password') ? 'input-error' : ''; ?>" data-toggle-password="true">
       <input id="password" name="password" placeholder="Enter password" type="password" required/>
       <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
        <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
        <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
       </button>
      </div>
      <div class="password-strength" id="password-strength"></div>
      <div class="text-xs text-gray-500 mt-1" id="password-feedback">Password should be at least 8 characters</div>
      <?php if ($field_error === 'password'): ?>
        <div class="field-error-text" id="password-error"><?php echo $error; ?></div>
      <?php endif; ?>
     </div>
     
     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900" for="confirm_password">
       Confirm Password
      </label>
      <div class="input <?php echo ($field_error === 'confirm_password') ? 'input-error' : ''; ?>" data-toggle-password="true">
       <input id="confirm_password" name="confirm_password" placeholder="Confirm password" type="password" required/>
       <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
        <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
        <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
       </button>
      </div>
      <?php if ($field_error === 'confirm_password'): ?>
        <div class="field-error-text" id="confirm-password-error"><?php echo $error; ?></div>
      <?php endif; ?>
     </div>
     
     <label class="checkbox-group <?php echo ($field_error === 'terms') ? 'checkbox-error' : ''; ?>">
      <input class="checkbox checkbox-sm" id="terms" name="terms" required type="checkbox"/>
      <span class="checkbox-label">
       I Accept the <a href="#" class="link">Terms & Conditions</a>
      </span>
     </label>
     <?php if ($field_error === 'terms'): ?>
        <div class="field-error-text mt-0" id="terms-error"><?php echo $error; ?></div>
     <?php endif; ?>
     
     <button type="submit" class="btn btn-primary flex justify-center grow" id="submit-btn">
       <span class="spinner">
         <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
           <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
           <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
         </svg>
       </span>
      Sign Up
     </button>
    </form>
   </div>
  </div>
  <!-- End of Page -->
  
  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
  <script src="assets/vendors/apexcharts/apexcharts.min.js"></script>
  
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('sign_up_form');
    const fullnameInput = document.getElementById('fullname');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const termsCheckbox = document.getElementById('terms');
    const submitBtn = document.getElementById('submit-btn');
    const passwordStrength = document.getElementById('password-strength');
    const passwordFeedback = document.getElementById('password-feedback');
    
    // Password strength checker
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;
      let feedback = '';
      
      // Clear previous strength indicators
      passwordStrength.className = 'password-strength';
      
      if (password.length < 1) {
        passwordFeedback.textContent = 'Password should be at least 8 characters';
        return;
      }
      
      // Length check
      if (password.length >= 8) {
        strength += 1;
      } else {
        feedback = 'Password should be at least 8 characters';
      }
      
      // Complexity checks
      if (password.match(/[a-z]/) && password.match(/[A-Z]/)) {
        strength += 1;
      } else if (strength > 0) {
        feedback = 'Try adding both uppercase and lowercase letters';
      }
      
      if (password.match(/\d/)) {
        strength += 1;
      } else if (strength > 0) {
        feedback = 'Try adding numbers';
      }
      
      if (password.match(/[^a-zA-Z\d]/)) {
        strength += 1;
      } else if (strength > 0) {
        feedback = 'Try adding special characters';
      }
      
      // Update UI based on strength
      if (strength === 1) {
        passwordStrength.className = 'password-strength strength-weak';
        passwordFeedback.textContent = feedback || 'Weak password';
        passwordFeedback.className = 'text-xs text-red-500 mt-1';
      } else if (strength === 2) {
        passwordStrength.className = 'password-strength strength-medium';
        passwordFeedback.textContent = feedback || 'Medium strength password';
        passwordFeedback.className = 'text-xs text-amber-500 mt-1';
      } else if (strength === 3) {
        passwordStrength.className = 'password-strength strength-strong';
        passwordFeedback.textContent = feedback || 'Strong password';
        passwordFeedback.className = 'text-xs text-blue-500 mt-1';
      } else if (strength >= 4) {
        passwordStrength.className = 'password-strength strength-very-strong';
        passwordFeedback.textContent = 'Very strong password';
        passwordFeedback.className = 'text-xs text-green-500 mt-1';
      }
    });
    
    // Validate form before submission
    form.addEventListener('submit', function(e) {
      let isValid = true;
      
      // Reset visual error states
      fullnameInput.classList.remove('input-error');
      emailInput.classList.remove('input-error');
      passwordInput.parentElement.classList.remove('input-error');
      confirmPasswordInput.parentElement.classList.remove('input-error');
      termsCheckbox.parentElement.classList.remove('checkbox-error');
      
      // Remove existing error messages
      const errorMessages = document.querySelectorAll('.field-error-text');
      errorMessages.forEach(msg => msg.remove());
      
      // Validate full name
      if (!fullnameInput.value.trim()) {
        isValid = false;
        fullnameInput.classList.add('input-error');
        appendError(fullnameInput, 'Full name is required');
      }
      
      // Validate email
      if (!emailInput.value.trim()) {
        isValid = false;
        emailInput.classList.add('input-error');
        appendError(emailInput, 'Email is required');
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
        isValid = false;
        emailInput.classList.add('input-error');
        appendError(emailInput, 'Please enter a valid email address');
      }
      
      // Validate password
      if (!passwordInput.value) {
        isValid = false;
        passwordInput.parentElement.classList.add('input-error');
        appendError(passwordInput.parentElement, 'Password is required');
      } else if (passwordInput.value.length < 8) {
        isValid = false;
        passwordInput.parentElement.classList.add('input-error');
        appendError(passwordInput.parentElement, 'Password must be at least 8 characters');
      }
      
      // Validate confirm password
      if (!confirmPasswordInput.value) {
        isValid = false;
        confirmPasswordInput.parentElement.classList.add('input-error');
        appendError(confirmPasswordInput.parentElement, 'Please confirm your password');
      } else if (confirmPasswordInput.value !== passwordInput.value) {
        isValid = false;
        confirmPasswordInput.parentElement.classList.add('input-error');
        appendError(confirmPasswordInput.parentElement, 'Passwords do not match');
      }
      
      // Validate terms acceptance
      if (!termsCheckbox.checked) {
        isValid = false;
        termsCheckbox.parentElement.classList.add('checkbox-error');
        appendError(termsCheckbox.parentElement, 'You must accept the Terms & Conditions');
      }
      
      if (!isValid) {
        e.preventDefault();
      } else {
        // Show loading state
        submitBtn.classList.add('loading');
      }
    });
    
    // Helper to append error messages
    function appendError(element, message) {
      const errorDiv = document.createElement('div');
      errorDiv.className = 'field-error-text';
      errorDiv.textContent = message;
      element.parentElement.appendChild(errorDiv);
    }
    
    // Input event listeners to clear errors when typing
    fullnameInput.addEventListener('input', function() {
      fullnameInput.classList.remove('input-error');
      removeErrorFor(fullnameInput);
    });
    
    emailInput.addEventListener('input', function() {
      emailInput.classList.remove('input-error');
      removeErrorFor(emailInput);
    });
    
    passwordInput.addEventListener('input', function() {
      passwordInput.parentElement.classList.remove('input-error');
      removeErrorFor(passwordInput.parentElement);
    });
    
    confirmPasswordInput.addEventListener('input', function() {
      confirmPasswordInput.parentElement.classList.remove('input-error');
      removeErrorFor(confirmPasswordInput.parentElement);
    });
    
    termsCheckbox.addEventListener('change', function() {
      termsCheckbox.parentElement.classList.remove('checkbox-error');
      removeErrorFor(termsCheckbox.parentElement);
    });
    
    // Helper to remove error messages
    function removeErrorFor(element) {
      const errorElement = element.parentElement.querySelector('.field-error-text');
      if (errorElement) errorElement.remove();
    }
    
    // Focus on first input
    setTimeout(function() {
      if (!fullnameInput.value) {
        fullnameInput.focus();
      }
    }, 100);
  });
  </script>
 </body>
</html>