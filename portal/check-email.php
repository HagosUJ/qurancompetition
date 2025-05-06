<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/check-email.php
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

// Retrieve email from GET parameter (sanitize it)
$email = isset($_GET['email']) ? sanitize_input($_GET['email']) : '';
$email_sent = isset($_GET['email_sent']) && $_GET['email_sent'] == '1';
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'verification';

// Determine the appropriate resend link based on the type
$resend_link = ($type === 'reset') ? 'enter-email.php' : 'sign-up.php';

// Translation array for success messages
$translations = [
    'en' => [
        'Email sent successfully!' => 'Email sent successfully!',
        'Email resent successfully!' => 'Email resent successfully!'
    ],
    'ar' => [
        'Email sent successfully!' => 'تم إرسال البريد الإلكتروني بنجاح!',
        'Email resent successfully!' => 'تم إعادة إرسال البريد الإلكترonic بنجاح!'
    ]
];

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

error_log("check-email.php loaded, language: $current_lang, type: $type");
?>

<!DOCTYPE html>
<html class="h-full" id="html-root" data-theme="true" data-theme-mode="light" dir="<?php echo $current_lang === 'ar' ? 'rtl' : 'ltr'; ?>" lang="<?php echo $current_lang; ?>">
<head>
  <title>
    <span class="lang-en">Majlisu Ahlil Qur'an - Check Your Email</span>
    <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">مجلس أهل القرآن - تحقق من بريدك الإلكتروني</span>
  </title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Check your email to complete the process" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet"/>
  <link href="assets/vendors/apexcharts/apexcharts.css" rel="stylesheet"/>
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
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
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
      <?php 
      $flash = get_flash_message();
      if ($flash):
        $message_class = strpos($flash, 'successfully') !== false ? 'success-message' : 'error-message';
      ?>
        <div class="<?php echo $message_class; ?> p-4 mb-4 rounded shadow-md" role="alert">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 <?php echo strpos($flash, 'successfully') !== false ? 'text-green-500' : 'text-red-500'; ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium">
                <span class="lang-en"><?php echo htmlspecialchars($flash); ?></span>
                <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">
                  <?php echo htmlspecialchars(isset($translations['ar'][$flash]) ? $translations['ar'][$flash] : $flash); ?>
                </span>
              </p>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="card-body p-10">
      <div class="flex justify-center py-10">
        <img alt="Email Illustration" class="dark:hidden max-h-[130px]" src="assets/media/illustrations/30.svg"/>
        <img alt="Email Illustration Dark" class="light:hidden max-h-[130px]" src="assets/media/illustrations/30-dark.svg"/>
      </div>
      <h3 class="text-lg font-medium text-gray-900 text-center mb-3">
        <span class="lang-en">Check your email</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">تحقق من بريدك الإلكتروني</span>
      </h3>
      <div class="text-2sm text-center text-gray-700 mb-7.5">
        <?php if ($type === 'reset'): ?>
          <span class="lang-en">We have sent a password reset link to</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">لقد أرسلنا رابط إعادة تعيين كلمة المرور إلى</span>
        <?php else: ?>
          <span class="lang-en">Please click the link sent to</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">يرجى النقر على الرابط المرسل إلى</span>
        <?php endif; ?>

        <?php if (!empty($email)): ?>
          <span class="text-gray-900 font-medium">
            <?php echo htmlspecialchars($email); ?>
          </span>
        <?php else: ?>
          <span class="lang-en">your email address</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">عنوان بريدك الإلكتروني</span>
        <?php endif; ?>

        <?php if ($type === 'reset'): ?>
          <span class="lang-en">to help you reset your password.</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">لمساعدتك في إعادة تعيين كلمة المرور.</span>
        <?php else: ?>
          <span class="lang-en">to verify your account.</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">لتفعيل حسابك.</span>
        <?php endif; ?>
        <br/>
        <span class="lang-en">Thank you.</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">شكرًا.</span>
      </div>
      <div class="flex justify-center mb-5">
        <a class="btn btn-primary flex justify-center" href="sign-in.php">
          <span class="lang-en">Back to Sign In</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">العودة إلى تسجيل الدخول</span>
        </a>
      </div>
      <div class="flex items-center justify-center gap-1">
        <span class="text-xs text-gray-700">
          <span class="lang-en">Didn’t receive the email?</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">لم تستلم البريد الإلكتروني؟</span>
        </span>
        <?php if (!empty($email)): ?>
          <a class="text-xs font-medium link"
             href="resend-email.php?email=<?php echo urlencode($email); ?>&type=<?php echo urlencode($type); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>">
            <span class="lang-en">Resend</span>
            <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">إعادة إرسال</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
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
      }
      sessionStorage.setItem('language', lang);
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

    window.toggleLanguage = toggleLanguage;
  });
  </script>
</body>
</html>