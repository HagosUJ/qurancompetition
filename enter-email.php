<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/enter-email.php
require_once 'includes/auth.php';

// Start session if not already started
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
$email = '';
$field_error = '';

// Translation array for error messages
$translations = [
    'en' => [
        'Invalid form submission. Please try again.' => 'Invalid form submission. Please try again.',
        'Email address is required.' => 'Email address is required.',
        'Please enter a valid email address.' => 'Please enter a valid email address.',
        'Your account is not active. Password reset is not available. Please contact support if you believe this is an error.' => 'Your account is not active. Password reset is not available. Please contact support if you believe this is an error.',
        'Failed to initiate password reset. Please try again later.' => 'Failed to initiate password reset. Please try again later.',
        'An error occurred. Please try again later.' => 'An error occurred. Please try again later.',
        'Email is required' => 'Email is required',
        'Please enter a valid email address' => 'Please enter a valid email address'
    ],
    'ar' => [
        'Invalid form submission. Please try again.' => 'إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'Email address is required.' => 'عنوان البريد الإلكتروني مطلوب.',
        'Please enter a valid email address.' => 'يرجى إدخال عنوان بريد إلكتروني صالح.',
        'Your account is not active. Password reset is not available. Please contact support if you believe this is an error.' => 'حسابك غير نشط. إعادة تعيين كلمة المرور غير متاح. يرجى التواصل مع الدعم إذا كنت تعتقد أن هذا خطأ.',
        'Failed to initiate password reset. Please try again later.' => 'فشل في بدء إعادة تعيين كلمة المرور. يرجى المحاولة مرة أخرى لاحقًا.',
        'An error occurred. Please try again later.' => 'حدث خطأ. يرجى المحاولة مرة أخرى لاحقًا.',
        'Email is required' => 'البريد الإلكتروني مطلوب',
        'Please enter a valid email address' => 'يرجى إدخال عنوان بريد إلكتروني صالح'
    ]
];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = $translations[$current_lang]['Invalid form submission. Please try again.'];
    } else {
        $email = sanitize_input($_POST['email']);
        $language = isset($_POST['language']) && in_array($_POST['language'], ['en', 'ar']) ? $_POST['language'] : $current_lang;
        $_SESSION['language'] = $language;
        $_SESSION['lang'] = $language;

        // Validate input
        if (empty($email)) {
            $error = $translations[$current_lang]['Email address is required.'];
            $field_error = 'email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = $translations[$current_lang]['Please enter a valid email address.'];
            $field_error = 'email';
        } else {
            try {
                // Process password reset request
                $reset_status = send_password_reset_email($email);

                if ($reset_status === true) {
                    redirect('check-email.php?email=' . urlencode($email) . '&type=reset');
                    exit;
                } elseif ($reset_status === 'inactive') {
                    $error = $translations[$current_lang]['Your account is not active. Password reset is not available. Please contact support if you believe this is an error.'];
                    $field_error = 'email';
                } else {
                    $error = $translations[$current_lang]['Failed to initiate password reset. Please try again later.'];
                }
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = $translations[$current_lang]['An error occurred. Please try again later.'];
            }
        }
    }
}

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

error_log("enter-email.php loaded, language: $current_lang");
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
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
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
    [dir="rtl"] {
      font-family: 'Amiri', serif;
    }
    [dir="rtl"] .error-message {
      border-left-width: 0;
      border-right-width: 4px;
    }
    [dir="rtl"] .text-center {
      text-align: center;
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
   <div class="card max-w-[370px] w-full">
    <!-- Language Toggle -->
    <div class="flex justify-end p-4">
      <span class="lang-toggle text-sm font-medium" onclick="toggleLanguage()">
        <span class="lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">العربية</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">English</span>
      </span>
    </div>

    <!-- Error Messages -->
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
            <p class="text-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
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
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
      <input type="hidden" name="language" id="language-input" value="<?php echo htmlspecialchars($current_lang); ?>">

      <div class="text-center mb-2.5">
        <h3 class="text-lg font-medium text-gray-900 leading-none mb-2.5">
          <span class="lang-en">Forgot Password</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">نسيت كلمة المرور</span>
        </h3>
        <span class="text-2sm text-gray-700">
          <span class="lang-en">Enter your email to reset your password</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">أدخل بريدك الإلكتروني لإعادة تعيين كلمة المرور</span>
        </span>
      </div>

      <div class="flex flex-col gap-1">
        <label class="form-label font-normal text-gray-900" for="email">
          <span class="lang-en">Email</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">البريد الإلكتروني</span>
        </label>
        <input class="input <?php echo ($field_error === 'email') ? 'input-error' : ''; ?>" 
               id="email" name="email" placeholder="<?php echo $current_lang === 'ar' ? 'أدخل بريدك الإلكتروني' : 'Enter your email'; ?>" 
               type="email" value="<?php echo htmlspecialchars($email); ?>" required />
        <?php if ($field_error === 'email'): ?>
          <div class="field-error-text" id="email-error">
            <span class="lang-en"><?php echo htmlspecialchars($error); ?></span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>"><?php echo htmlspecialchars($error); ?></span>
          </div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary flex justify-center grow" id="submit-btn">
        <span class="spinner">
          <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </span>
        <span class="lang-en">Submit</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">إرسال</span>
      </button>

      <div class="text-center">
        <a href="sign-in.php" class="text-2sm link">
          <span class="lang-en">Back to Sign In</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">العودة إلى تسجيل الدخول</span>
        </a>
      </div>
    </form>
   </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
  <script src="assets/vendors/apexcharts/apexcharts.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const htmlRoot = document.getElementById('html-root');
    const langEnElements = document.querySelectorAll('.lang-en');
    const langArElements = document.querySelectorAll('.lang-ar');
    const languageInput = document.getElementById('language-input');
    const emailInput = document.getElementById('email');
    const submitBtn = document.getElementById('submit-btn');
    const form = document.getElementById('forgot_password_form');

    function setLanguage(lang) {
      console.log('Setting language to:', lang);
      if (lang === 'ar') {
        htmlRoot.setAttribute('dir', 'rtl');
        htmlRoot.setAttribute('lang', 'ar');
        langEnElements.forEach(el => {
          el.classList.add('hidden');
          console.log('Hiding EN element:', el.textContent);
        });
        langArElements.forEach(el => {
          el.classList.remove('hidden');
          console.log('Showing AR element:', el.textContent);
        });
        emailInput.placeholder = 'أدخل بريدك الإلكتروني';
      } else {
        htmlRoot.setAttribute('dir', 'ltr');
        htmlRoot.setAttribute('lang', 'en');
        langEnElements.forEach(el => {
          el.classList.remove('hidden');
          console.log('Showing EN element:', el.textContent);
        });
        langArElements.forEach(el => {
          el.classList.add('hidden');
          console.log('Hiding AR element:', el.textContent);
        });
        emailInput.placeholder = 'Enter your email';
      }
      sessionStorage.setItem('language', lang);
      languageInput.value = lang;
      fetch('update_language.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'language=' + encodeURIComponent(lang)
      }).then(response => {
        console.log('Language updated on server:', lang);
      }).catch(error => {
        console.error('Language update failed:', error);
      });
    }

    function toggleLanguage() {
      const currentLang = sessionStorage.getItem('language') || '<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>';
      const newLang = currentLang === 'en' ? 'ar' : 'en';
      console.log('Toggling language from', currentLang, 'to', newLang);
      setLanguage(newLang);
    }

    // Initialize language
    const savedLang = sessionStorage.getItem('language') || '<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>';
    console.log('Initializing language:', savedLang);
    setLanguage(savedLang);

    // Form validation
    form.addEventListener('submit', function(e) {
      let isValid = true;
      const currentLang = languageInput.value;

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
        errorDiv.innerHTML = currentLang === 'ar' ? 
          '<span class="lang-ar">البريد الإلكتروني مطلوب</span><span class="lang-en hidden">Email is required</span>' :
          '<span class="lang-en">Email is required</span><span class="lang-ar hidden">البريد الإلكتروني مطلوب</span>';
        emailInput.parentElement.appendChild(errorDiv);
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
        isValid = false;
        emailInput.classList.add('input-error');
        const errorDiv = document.createElement('div');
        errorDiv.id = 'email-error';
        errorDiv.className = 'field-error-text';
        errorDiv.innerHTML = currentLang === 'ar' ? 
          '<span class="lang-ar">يرجى إدخال عنوان بريد إلكتروني صالح</span><span class="lang-en hidden">Please enter a valid email address</span>' :
          '<span class="lang-en">Please enter a valid email address</span><span class="lang-ar hidden">يرجى إدخال عنوان بريد إلكتروني صالح</span>';
        emailInput.parentElement.appendChild(errorDiv);
      }

      if (!isValid) {
        e.preventDefault();
      } else {
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

    window.toggleLanguage = toggleLanguage;
  });
  </script>
</body>
</html>