<?php
require_once 'includes/auth.php'; // Includes config, db, functions

// Start session for CSRF token and language
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default language if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
    $_SESSION['lang'] = 'en';
}
$current_lang = $_SESSION['language'] ?? 'en';

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
        } elseif (!is_strong_password($password)) { // Updated strength check
            $error = "Password must be at least 8 characters long and include uppercase, lowercase, and a number.";
        } else {
            // Hash new password using the function from auth.php
            $hashed_password = hash_password($password);

            // Update user password and clear reset token fields
            global $conn; // Get DB connection
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expiry = NULL WHERE id = ?");
            if (!$update_stmt) {
                error_log("DB prepare failed for password update: " . $conn->error);
                $error = "An internal error occurred. Please try again later.";
            } else {
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                if ($update_stmt->execute()) {
                    $update_stmt->close();
                    $success = "Password updated successfully. You can now sign in with your new password.";
                    $user_id = null; // Prevent form resubmission
                } else {
                    $update_stmt->close();
                    error_log("Failed to update password for user ID {$user_id}: " . $conn->error);
                    $error = "Failed to update password. Please try again.";
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id === null) {
    $error = "Invalid or expired password reset link. Cannot process request.";
}

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// Translation array for messages
$translations = [
    'en' => [
        'Reset Your Password' => 'Reset Your Password',
        'Enter and confirm your new password below.' => 'Enter and confirm your new password below.',
        'New Password' => 'New Password',
        'Confirm New Password' => 'Confirm New Password',
        'Update Password' => 'Update Password',
        'Min 8 chars, incl. upper, lower, number.' => 'Min 8 chars, incl. upper, lower, number.',
        'Back to Sign In' => 'Back to Sign In',
        'Request Reset Link' => 'Request Reset Link',
        'Please return to the password reset request page.' => 'Please return to the password reset request page.',
        'Weak' => 'Weak',
        'Medium' => 'Medium',
        'Strong' => 'Strong'
    ],
    'ar' => [
        'Reset Your Password' => 'إعادة تعيين كلمة المرور',
        'Enter and confirm your new password below.' => 'أدخل وتأكيد كلمة المرور الجديدة أدناه.',
        'New Password' => 'كلمة المرور الجديدة',
        'Confirm New Password' => 'تأكيد كلمة المرور الجديدة',
        'Update Password' => 'تحديث كلمة المرور',
        'Min 8 chars, incl. upper, lower, number.' => '8 أحرف كحد أدنى، تتضمن أحرف كبيرة، صغيرة، ورقم.',
        'Back to Sign In' => 'العودة إلى تسجيل الدخول',
        'Request Reset Link' => 'طلب رابط إعادة التعيين',
        'Please return to the password reset request page.' => 'يرجى العودة إلى صفحة طلب إعادة تعيين كلمة المرور.',
        'Weak' => 'ضعيف',
        'Medium' => 'متوسط',
        'Strong' => 'قوي'
    ]
];
?>
<!DOCTYPE html>
<html class="h-full" id="html-root" data-theme="true" data-theme-mode="light" dir="<?php echo $current_lang === 'ar' ? 'rtl' : 'ltr'; ?>" lang="<?php echo $current_lang; ?>">
<head>
  <title>
    <span class="lang-en">Majlisu Ahlil Qur'an - Reset Password</span>
    <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">مجلس أهل القرآن - إعادة تعيين كلمة المرور</span>
  </title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Reset your password for Majlisu Ahlil Qur'an International" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet"/>
  <link href="assets/vendors/keenicons/styles.bundle.css" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>
  <!-- Custom Styles -->
  <style>
    .success-message, .error-message {
      animation: fadeInDown 0.5s ease-in-out;
      border-left-width: 4px;
    }
    .success-message {
      background-color: #d1fae5;
      border-color: #10b981;
      color: #065f46;
    }
    .error-message {
      background-color: #fee2e2;
      border-color: #ef4444;
      color: #991b1b;
    }
    .input-error {
      border-color: #ef4444 !important;
      background-color: #fef2f2;
    }
    .input-error:focus-within {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
    }
    .field-error-text {
      color: #ef4444;
      font-size: 0.75rem;
      margin-top: 0.25rem;
      animation: fadeIn 0.3s ease-in-out;
    }
    .password-strength {
      display: flex;
      align-items: center;
      margin-top: 0.5rem;
    }
    .strength-bar {
      flex-grow: 1;
      height: 6px;
      border-radius: 3px;
      transition: width 0.3s ease, background-color 0.3s ease;
    }
    .strength-weak .strength-bar {
      width: 33%;
      background-color: #ef4444;
    }
    .strength-medium .strength-bar {
      width: 66%;
      background-color: #f59e0b;
    }
    .strength-strong .strength-bar {
      width: 100%;
      background-color: #10b981;
    }
    .strength-text {
      margin-left: 0.5rem;
      font-size: 0.75rem;
      font-weight: 500;
    }
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    [dir="rtl"] {
      font-family: 'Amiri', serif;
    }
    [dir="rtl"] .success-message, [dir="rtl"] .error-message {
      border-left-width: 0;
      border-right-width: 4px;
    }
    [dir="rtl"] .text-center {
      text-align: center;
    }
    [dir="rtl"] .strength-text {
      margin-left: 0;
      margin-right: 0.5rem;
    }
    .lang-toggle {
      cursor: pointer;
      transition: color 0.2s ease;
    }
    .lang-toggle:hover {
      color: #3b82f6;
    }
    .lang-en.hidden, .lang-ar.hidden {
      display: none !important;
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
   <div class="card max-w-[440px] w-full">
    <!-- Language Toggle -->
    <div class="flex justify-end p-4">
      <span class="lang-toggle text-sm font-medium" onclick="toggleLanguage()">
        <span class="lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">العربية</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">English</span>
      </span>
    </div>

    <!-- Flash Messages -->
    <div id="flash-message-container" class="mx-4 mt-4">
      <?php if (!empty($error)): ?>
        <div class="error-message p-4 mb-4 rounded shadow-md" role="alert">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium">
                <span class="lang-en"><?php echo htmlspecialchars($error); ?></span>
                <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">
                  <?php echo htmlspecialchars(isset($translations['ar'][$error]) ? $translations['ar'][$error] : $error); ?>
                </span>
              </p>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="success-message p-4 mb-4 rounded shadow-md" role="alert">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium">
                <span class="lang-en"><?php echo htmlspecialchars($success); ?></span>
                <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">
                  <?php echo htmlspecialchars(isset($translations['ar'][$success]) ? $translations['ar'][$success] : $success); ?>
                </span>
              </p>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Form or Success Action -->
    <?php if ($user_id !== null && empty($success)): ?>
      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?token=' . urlencode($token) . '&email=' . urlencode($email)); ?>" class="card-body p-10" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="flex justify-center py-10">
          <img alt="Password Reset Illustration" class="dark:hidden max-h-[130px]" src="assets/media/illustrations/30.svg"/>
          <img alt="Password Reset Illustration Dark" class="light:hidden max-h-[130px]" src="assets/media/illustrations/30-dark.svg"/>
        </div>
        <h3 class="text-lg font-medium text-gray-900 text-center mb-3">
          <span class="lang-en"><?php echo $translations['en']['Reset Your Password']; ?></span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Reset Your Password']; ?></span>
        </h3>
        <div class="text-2sm text-center text-gray-700 mb-7.5">
          <span class="lang-en"><?php echo $translations['en']['Enter and confirm your new password below.']; ?></span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Enter and confirm your new password below.']; ?></span>
        </div>
        <div class="flex flex-col gap-1 mb-5">
          <label class="form-label font-medium text-gray-900 dark:text-gray-200" for="user_new_password">
            <span class="lang-en"><?php echo $translations['en']['New Password']; ?></span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['New Password']; ?></span>
          </label>
          <div class="input <?php echo (!empty($error) && (strpos($error, 'required') !== false || strpos($error, 'match') !== false || strpos($error, 'complexity') !== false)) ? 'input-error' : ''; ?>" data-toggle-password="true">
            <input id="user_new_password" name="user_new_password" placeholder="<?php echo $current_lang === 'en' ? 'Enter a new password' : 'أدخل كلمة مرور جديدة'; ?>" type="password" required/>
            <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
              <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
              <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
            </button>
          </div>
          <div class="password-strength strength-weak" id="password-strength">
            <div class="strength-bar"></div>
            <span class="strength-text">
              <span class="lang-en"><?php echo $translations['en']['Weak']; ?></span>
              <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Weak']; ?></span>
            </span>
          </div>
          <?php if (!empty($error) && (strpos($error, 'required') !== false || strpos($error, 'match') !== false || strpos($error, 'complexity') !== false)): ?>
            <div class="field-error-text"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          <p class="text-xs text-gray-500 mt-1">
            <span class="lang-en"><?php echo $translations['en']['Min 8 chars, incl. upper, lower, number.']; ?></span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Min 8 chars, incl. upper, lower, liczba.']; ?></span>
          </p>
        </div>
        <div class="flex flex-col gap-1 mb-5">
          <label class="form-label font-medium text-gray-900 dark:text-gray-200" for="user_confirm_password">
            <span class="lang-en"><?php echo $translations['en']['Confirm New Password']; ?></span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Confirm New Password']; ?></span>
          </label>
          <div class="input <?php echo (!empty($error) && (strpos($error, 'required') !== false || strpos($error, 'match') !== false)) ? 'input-error' : ''; ?>" data-toggle-password="true">
            <input id="user_confirm_password" name="user_confirm_password" placeholder="<?php echo $current_lang === 'en' ? 'Re-enter the new password' : 'أعد إدخال كلمة المرور الجديدة'; ?>" type="password" required/>
            <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
              <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
              <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
            </button>
          </div>
          <?php if (!empty($error) && strpos($error, 'match') !== false): ?>
            <div class="field-error-text"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
        </div>
        <div class="flex justify-center">
          <button type="submit" class="btn btn-primary flex justify-center">
            <span class="lang-en"><?php echo $translations['en']['Update Password']; ?></span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Update Password']; ?></span>
          </button>
        </div>
      </form>
    <?php elseif (!empty($success)): ?>
      <div class="card-body p-10">
        <div class="flex justify-center py-10">
          <img alt="Success Illustration" class="dark:hidden max-h-[130px]" src="assets/media/illustrations/30.svg"/>
          <img alt="Success Illustration Dark" class="light:hidden max-h-[130px]" src="assets/media/illustrations/30-dark.svg"/>
        </div>
        <h3 class="text-lg font-medium text-gray-900 text-center mb-3">
          <span class="lang-en"><?php echo $translations['en']['Reset Your Password']; ?></span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Reset Your Password']; ?></span>
        </h3>
        <div class="flex justify-center mb-5">
          <a class="btn btn-primary flex justify-center" href="sign-in.php">
            <span class="lang-en"><?php echo $translations['en']['Back to Sign In']; ?></span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Back to Sign In']; ?></span>
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="card-body p-10">
        <div class="flex justify-center py-10">
          <img alt="Error Illustration" class="dark:hidden max-h-[130px]" src="assets/media/illustrations/30.svg"/>
          <img alt="Error Illustration Dark" class="light:hidden max-h-[130px]" src="assets/media/illustrations/30-dark.svg"/>
        </div>
        <div class="text-2sm text-center text-gray-700 mb-7.5">
          <span class="lang-en"><?php echo $translations['en']['Please return to the password reset request page.']; ?></span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Please return to the password reset request page.']; ?></span>
        </div>
        <div class="flex justify-center mb-5">
          <a class="btn btn-primary flex justify-center" href="enter-email.php">
            <span class="lang-en"><?php echo $translations['en']['Request Reset Link']; ?></span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo $translations['ar']['Request Reset Link']; ?></span>
          </a>
        </div>
      </div>
    <?php endif; ?>
   </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const htmlRoot = document.getElementById('html-root');
    const langEnElements = document.querySelectorAll('.lang-en');
    const langArElements = document.querySelectorAll('.lang-ar');

    function setLanguage(lang) {
      if (lang === 'ar') {
        htmlRoot.setAttribute('dir', 'rtl');
        htmlRoot.setAttribute('lang', 'ar');
        langEnElements.forEach(el => el.classList.add('hidden'));
        langArElements.forEach(el => el.classList.remove('hidden'));
      } else {
        htmlRoot.setAttribute('dir', 'ltr');
        htmlRoot.setAttribute('lang', 'en');
        langEnElements.forEach(el => el.classList.remove('hidden'));
        langArElements.forEach(el => el.classList.add('hidden'));
      }
      sessionStorage.setItem('language', lang);
      fetch('update_language.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'language=' + encodeURIComponent(lang)
      });
    }

    function toggleLanguage() {
      const currentLang = sessionStorage.getItem('language') || '<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>';
      const newLang = currentLang === 'en' ? 'ar' : 'en';
      setLanguage(newLang);
    }

    // Initialize language
    const savedLang = sessionStorage.getItem('language') || '<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>';
    setLanguage(savedLang);

    window.toggleLanguage = toggleLanguage;

    // Password Strength Indicator
    const passwordInput = document.getElementById('user_new_password');
    const strengthContainer = document.getElementById('password-strength');
    const strengthBar = strengthContainer.querySelector('.strength-bar');
    const strengthTextEn = strengthContainer.querySelector('.lang-en');
    const strengthTextAr = strengthContainer.querySelector('.lang-ar');

    function updatePasswordStrength() {
      const password = passwordInput.value;
      let strength = 'weak';
      let score = 0;

      if (password.length >= 8) score++;
      if (/[A-Z]/.test(password)) score++;
      if (/[a-z]/.test(password)) score++;
      if (/[0-9]/.test(password)) score++;
      if (password.length >= 12) score++;

      if (score <= 2) {
        strength = 'weak';
        strengthContainer.className = 'password-strength strength-weak';
        strengthTextEn.textContent = '<?php echo $translations['en']['Weak']; ?>';
        strengthTextAr.textContent = '<?php echo $translations['ar']['Weak']; ?>';
      } else if (score <= 4) {
        strength = 'medium';
        strengthContainer.className = 'password-strength strength-medium';
        strengthTextEn.textContent = '<?php echo $translations['en']['Medium']; ?>';
        strengthTextAr.textContent = '<?php echo $translations['ar']['Medium']; ?>';
      } else {
        strength = 'strong';
        strengthContainer.className = 'password-strength strength-strong';
        strengthTextEn.textContent = '<?php echo $translations['en']['Strong']; ?>';
        strengthTextAr.textContent = '<?php echo $translations['ar']['Strong']; ?>';
      }
    }

    passwordInput.addEventListener('input', updatePasswordStrength);
  });
  </script>
</body>
</html>