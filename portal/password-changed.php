<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/password-changed.php
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

// Check if user was redirected here properly
if (!isset($_GET['success']) || $_GET['success'] !== '1') {
    redirect('sign-in.php');
}

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

error_log("password-changed.php loaded, language: $current_lang");
?>

<!DOCTYPE html>
<html class="h-full" id="html-root" data-theme="true" data-theme-mode="light" dir="<?php echo $current_lang === 'ar' ? 'rtl' : 'ltr'; ?>" lang="<?php echo $current_lang; ?>">
<head>
  <title>
    <span class="lang-en">Majlisu Ahlil Qur'an - Password Changed</span>
    <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">مجلس أهل القرآن - تم تغيير كلمة المرور</span>
  </title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Password successfully changed for Majlisu Ahlil Qur'an International" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>

  <!-- Custom Styles -->
  <style>
    [dir="rtl"] {
      font-family: 'Amiri', serif;
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
    .page-bg {
      background-image: url('assets/media/images/2600x1200/bg-10.png');
      background-size: cover;
    }
    .dark .page-bg {
      background-image: url('assets/media/images/2600x1200/bg-10-dark.png');
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
  <div class="flex items-center justify-center grow bg-center bg-no-repeat page-bg">
   <div class="card max-w-[500px] w-full">
    <!-- Language Toggle -->
    <div class="flex justify-end p-4">
      <span class="lang-toggle text-sm font-medium" onclick="toggleLanguage()">
        <span class="lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">العربية</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">English</span>
      </span>
    </div>

    <div class="card-body p-10 text-center">
      <div class="mb-6">
        <i class="ki-duotone ki-shield-tick fs-5x text-success">
          <span class="path1"></span>
          <span class="path2"></span>
        </i>
      </div>

      <h2 class="text-2xl font-bold mb-4">
        <span class="lang-en">Password Changed!</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">تم تغيير كلمة المرور!</span>
      </h2>
      <p class="mb-6">
        <span class="lang-en">Your password has been changed successfully. You can now sign in with your new password.</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">تم تغيير كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.</span>
      </p>

      <div class="text-center">
        <a href="sign-in.php" class="btn btn-primary">
          <span class="lang-en">Sign In</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">تسجيل الدخول</span>
        </a>
      </div>
    </div>
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