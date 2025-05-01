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
    redirect('index.php'); // Redirect to index/dashboard
}

$error = '';
$success = '';
$field_error = '';
$fullname = '';
$email = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission. Please try again.";
    } else {
        // Get and sanitize user input
        $fullname = sanitize_input($_POST['fullname']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password']; // Don't sanitize password before hashing
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
        } elseif (!is_strong_password($password)) { // Use the strength check function
            $error = "Password does not meet complexity requirements.";
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
                // Set flash message for the next page
                set_flash_message($message, 'success');

                // Redirect to a page indicating email verification is needed
                redirect('check-email.php?email=' . urlencode($email) . '&type=verification');
                exit; // Important to exit after redirect

            } else {
                $error = $message;

                // Determine which field caused the error (e.g., email exists)
                if (stripos($message, 'email') !== false) {
                    $field_error = 'email';
                } else {
                    // If it's not an email error, keep it as a general error
                    $field_error = ''; // Reset field error if it's a general DB issue etc.
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
  <title>Sign Up | <?php echo defined('APP_NAME') ? APP_NAME : 'Musabaqa'; ?></title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Sign up for Majlisu Ahlil Qur'an International Qur'an Competition" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
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
      border-color: #ef4444 !important; /* red-500 */
      background-color: #fef2f2; /* red-50 */
    }

    .input-error:focus-within {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
    }

    .input:focus-within {
      border-color: #3b82f6; /* blue-500 */
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
      margin-top: -10px; /* Half of spinner height */
      margin-left: -10px; /* Half of spinner width */
      display: none; /* Hidden by default */
    }

    .btn-primary.loading {
      color: transparent !important; /* Hide button text */
      pointer-events: none; /* Prevent clicking while loading */
    }

    .btn-primary.loading .spinner {
      display: block; /* Show spinner when loading */
    }

    .field-error-text {
      color: #ef4444; /* red-500 */
      font-size: 0.75rem; /* text-xs */
      margin-top: 0.25rem; /* mt-1 */
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
      background-color: #e5e7eb; /* gray-200 default background */
    }
    .password-strength > div {
        height: 100%;
        border-radius: 2px;
        transition: width 0.3s ease;
        width: 0; /* Start with 0 width */
    }

    .strength-weak > div {
      width: 25%;
      background-color: #ef4444; /* red-500 */
    }

    .strength-medium > div {
      width: 50%;
      background-color: #f59e0b; /* amber-500 */
    }

    .strength-strong > div {
      width: 75%;
      background-color: #3b82f6; /* blue-500 */
    }

    .strength-very-strong > div {
      width: 100%;
      background-color: #10b981; /* green-500 */
    }
  </style>
 </head>
 <body class="antialiased flex h-full text-base text-gray-700 dark:bg-coal-600">
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
            <p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="card-body flex flex-col gap-5 p-10" id="sign_up_form" method="post" novalidate>
      <!-- CSRF Protection -->
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

     <div class="text-center mb-2.5">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white leading-none mb-2.5">
       Sign up
      </h3>
      <div class="flex items-center justify-center font-medium">
       <span class="text-2sm text-gray-700 dark:text-gray-300 me-1.5">
        Already have an account?
       </span>
       <a class="text-2sm link" href="sign-in.php">
        Sign in
       </a>
      </div>
     </div>

     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="fullname">
       Full Name
      </label>
      <input class="input <?php echo ($field_error === 'fullname') ? 'input-error' : ''; ?>"
             id="fullname" name="fullname" placeholder="Enter your full name" type="text"
             value="<?php echo htmlspecialchars($fullname); ?>" required/>
      <?php if ($field_error === 'fullname'): ?>
        <div class="field-error-text" id="fullname-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
     </div>

     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="email">
       Email
      </label>
      <input class="input <?php echo ($field_error === 'email') ? 'input-error' : ''; ?>"
             id="email" name="email" placeholder="email@email.com" type="email"
             value="<?php echo htmlspecialchars($email); ?>" required/>
      <?php if ($field_error === 'email'): ?>
        <div class="field-error-text" id="email-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
     </div>

     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="password">
       Password
      </label>
      <div class="input <?php echo ($field_error === 'password') ? 'input-error' : ''; ?>" data-toggle-password="true">
       <input id="password" name="password" placeholder="Enter password" type="password" required autocomplete="new-password"/>
       <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
        <!-- Swapped classes for inverted toggle fix -->
        <i class="ki-filled ki-eye text-gray-500 hidden toggle-password-active:block"></i>
        <i class="ki-filled ki-eye-slash text-gray-500 toggle-password-active:hidden"></i> 
       </button>
      </div>
      <div class="password-strength" id="password-strength"><div></div></div>
      <div class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="password-feedback">Password should be at least 8 characters, include upper & lower case, a number, and a special character.</div>
      <?php if ($field_error === 'password'): ?>
        <div class="field-error-text" id="password-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
     </div>

     <div class="flex flex-col gap-1">
      <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="confirm_password">
       Confirm Password
      </label>
      <div class="input <?php echo ($field_error === 'confirm_password') ? 'input-error' : ''; ?>" data-toggle-password="true">
       <input id="confirm_password" name="confirm_password" placeholder="Confirm password" type="password" required/>
       <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
         <!-- Swapped classes for inverted toggle fix -->
        <i class="ki-filled ki-eye text-gray-500 hidden toggle-password-active:block"></i> 
        <i class="ki-filled ki-eye-slash text-gray-500 toggle-password-active:hidden"></i> 
       </button>
      </div>
      <?php if ($field_error === 'confirm_password'): ?>
        <div class="field-error-text" id="confirm-password-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
     </div>

     <label class="checkbox-group <?php echo ($field_error === 'terms') ? 'checkbox-error' : ''; ?>">
      <input class="checkbox checkbox-sm" id="terms" name="terms" required type="checkbox"/>
      <span class="checkbox-label dark:text-gray-300">
       I Accept the <a href="terms.php" class="link" target="_blank">Terms & Conditions</a>
      </span>
     </label>
     <?php if ($field_error === 'terms'): ?>
        <div class="field-error-text mt-0" id="terms-error"><?php echo htmlspecialchars($error); ?></div>
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

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Form validation elements
    const form = document.getElementById('sign_up_form');
    const fullnameInput = document.getElementById('fullname');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const termsCheckbox = document.getElementById('terms');
    const submitBtn = document.getElementById('submit-btn');
    const passwordStrengthDiv = document.getElementById('password-strength');
    const passwordStrengthInnerDiv = passwordStrengthDiv.querySelector('div');
    const passwordFeedback = document.getElementById('password-feedback');

    // Password strength checker function
    function checkPasswordStrength(password) {
        let strength = 0;
        let feedbackMessages = [];

        // Reset strength indicator
        passwordStrengthInnerDiv.style.width = '0%';
        passwordStrengthDiv.className = 'password-strength'; // Reset class

        if (!password) {
            return { strength: 0, feedback: 'Password should be at least 8 characters, include upper & lower case, a number, and a special character.' };
        }

        // Length check
        if (password.length >= 8) {
            strength += 1;
        } else {
            feedbackMessages.push('at least 8 characters');
        }

        // Uppercase letter
        if (/[A-Z]/.test(password)) {
            strength += 1;
        } else {
            feedbackMessages.push('an uppercase letter');
        }

        // Lowercase letter
        if (/[a-z]/.test(password)) {
            strength += 1;
        } else {
            feedbackMessages.push('a lowercase letter');
        }

        // Number
        if (/\d/.test(password)) {
            strength += 1;
        } else {
            feedbackMessages.push('a number');
        }

        // Special character
        if (/[^a-zA-Z\d]/.test(password)) { // Simple check for non-alphanumeric
            strength += 1;
        } else {
            feedbackMessages.push('a special character');
        }

        // Determine overall strength class and feedback text
        let strengthClass = '';
        let feedbackText = '';
        let feedbackColorClass = 'text-gray-500 dark:text-gray-400'; // Default color

        if (strength <= 1) {
            strengthClass = 'strength-weak';
            feedbackText = 'Weak. Needs: ' + feedbackMessages.join(', ') + '.';
            feedbackColorClass = 'text-red-500';
        } else if (strength === 2) {
            strengthClass = 'strength-weak'; // Still consider 2 weak
            feedbackText = 'Weak. Needs: ' + feedbackMessages.join(', ') + '.';
            feedbackColorClass = 'text-red-500';
        } else if (strength === 3) {
            strengthClass = 'strength-medium';
            feedbackText = 'Medium. Needs: ' + feedbackMessages.join(', ') + '.';
            feedbackColorClass = 'text-amber-500';
        } else if (strength === 4) {
            strengthClass = 'strength-strong';
            feedbackText = 'Strong. Consider adding: ' + feedbackMessages.join(', ') + '.';
            feedbackColorClass = 'text-blue-500';
        } else if (strength >= 5) {
            strengthClass = 'strength-very-strong';
            feedbackText = 'Very Strong!';
            feedbackColorClass = 'text-green-500';
        }

        return { strength, strengthClass, feedback: feedbackText, feedbackColorClass };
    }


    // Event listener for password input
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      const { strength, strengthClass, feedback, feedbackColorClass } = checkPasswordStrength(password);

      // Update UI
      passwordStrengthDiv.className = 'password-strength ' + strengthClass;
      passwordFeedback.textContent = feedback;
      passwordFeedback.className = 'text-xs mt-1 ' + feedbackColorClass; // Update color class
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

      // --- Client-side Validations ---
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

      // *** Get current password value right before comparison ***
      const currentPasswordValue = passwordInput.value;
      const { strength } = checkPasswordStrength(currentPasswordValue); // Get strength level

      if (!currentPasswordValue) {
        isValid = false;
        passwordInput.parentElement.classList.add('input-error');
        appendError(passwordInput.parentElement, 'Password is required');
      } else if (strength < 3) { // Require at least 'Medium' strength (adjust as needed)
        isValid = false;
        passwordInput.parentElement.classList.add('input-error');
        appendError(passwordInput.parentElement, 'Password does not meet complexity requirements.');
      }

      // Validate confirm password
      const currentConfirmPasswordValue = confirmPasswordInput.value; // Get current value
      if (!currentConfirmPasswordValue) {
        isValid = false;
        confirmPasswordInput.parentElement.classList.add('input-error');
        appendError(confirmPasswordInput.parentElement, 'Please confirm your password');
      } else if (currentConfirmPasswordValue !== currentPasswordValue) { // Compare current values
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
      // --- End Client-side Validations ---


      if (!isValid) {
        e.preventDefault(); // Stop submission if validation fails
      } else {
        // Show loading state ONLY if client-side validation passes
        submitBtn.classList.add('loading');
        // The form will now submit naturally for server-side processing
      }
    });

    // Helper to append error messages below the input's parent div
    function appendError(element, message) {
      // Check if error already exists for this element's parent
      const parent = element.closest('.flex.flex-col.gap-1, .checkbox-group'); // Find the container div
      if (!parent || parent.querySelector('.field-error-text')) {
          return; // Don't add duplicate errors or if parent not found
      }
      const errorDiv = document.createElement('div');
      errorDiv.className = 'field-error-text';
      // Special case for terms checkbox to align better
      if (element.id === 'terms') {
          errorDiv.classList.add('mt-0');
      }
      errorDiv.textContent = message;
      parent.appendChild(errorDiv); // Append to the container div
    }

    // Input event listeners to clear errors when typing/changing
    [fullnameInput, emailInput, passwordInput, confirmPasswordInput].forEach(input => {
        input.addEventListener('input', function() {
            const parentWrapper = input.closest('.input'); // Get the div with class 'input' if it exists
            const elementToRemoveErrorFrom = parentWrapper || input; // Use wrapper if exists, else the input itself
            elementToRemoveErrorFrom.classList.remove('input-error');
            removeErrorFor(elementToRemoveErrorFrom);
        });
    });

    termsCheckbox.addEventListener('change', function() {
      termsCheckbox.parentElement.classList.remove('checkbox-error');
      removeErrorFor(termsCheckbox.parentElement);
    });

    // Helper to remove error messages associated with an element's container
    function removeErrorFor(element) {
      const parent = element.closest('.flex.flex-col.gap-1, .checkbox-group'); // Find the container div
      if (parent) {
          const errorElement = parent.querySelector('.field-error-text');
          if (errorElement) errorElement.remove();
      }
    }

    // Focus on first input field on page load (optional enhancement)
    setTimeout(function() {
      const firstErrorFieldContainer = form.querySelector('.input-error, .checkbox-error');
      if (firstErrorFieldContainer) {
          const inputToFocus = firstErrorFieldContainer.querySelector('input');
          if (inputToFocus) {
              inputToFocus.focus();
          }
      } else if (!fullnameInput.value) {
          fullnameInput.focus();
      } else if (!emailInput.value) {
          emailInput.focus();
      }
    }, 100); // Small delay

  });
  </script>
 </body>
</html>