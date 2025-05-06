<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/sign-in.php
require_once 'includes/auth.php';

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log session start
error_log("Starting sign-in.php, session ID: " . session_id());

// Prevent redirect loop
if (isset($_SESSION['sign_in_loop_prevent'])) {
    error_log("Redirect loop detected in sign-in.php");
    die("Redirect loop detected. Please clear browser cookies and try again.");
}
$_SESSION['sign_in_loop_prevent'] = true;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("Generated new CSRF token: " . $_SESSION['csrf_token']);
}

// Redirect if already logged in
if (is_logged_in()) {
    error_log("User already logged in, redirecting to index.php");
    unset($_SESSION['sign_in_loop_prevent']);
    redirect("index.php", "You are already logged in.", "success");
}

// Initialize variables
$error = '';
$email = '';
$field_error = '';

// Check for lockout
$ip_address = $_SERVER['REMOTE_ADDR'];
$email_identifier = isset($_POST['email']) ? sanitize_input($_POST['email']) : 'unknown';
$locked_until = get_lockout_expiry($ip_address, $email_identifier);
if ($locked_until > time()) {
    $wait_time = ceil(($locked_until - time()) / 60);
    $error = "Too many login attempts. Please try again after {$wait_time} minutes.";
    error_log("Account locked for IP: $ip_address, email: $email_identifier until " . date('Y-m-d H:i:s', $locked_until));
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $locked_until <= time()) {
    error_log("Processing POST request, CSRF token received: " . ($_POST['csrf_token'] ?? 'none'));
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission. Please try again.";
        error_log("CSRF token validation failed, expected: {$_SESSION['csrf_token']}, received: " . ($_POST['csrf_token'] ?? 'none'));
    } else {
        // Get and sanitize input
        $email = sanitize_input($_POST['email']);
        $password = $_POST['user_password'] ?? '';
        $remember = isset($_POST['check']) ? true : false;
        $language = isset($_POST['language']) && in_array($_POST['language'], ['en', 'ar']) ? $_POST['language'] : 'en';

        // Update session language
        $_SESSION['language'] = $language;
        $_SESSION['lang'] = $language;
        error_log("Set language to: $language");

        // Validate input
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
            $field_error = empty($email) ? 'email' : 'password';
            error_log("Validation failed: empty email or password");
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $field_error = 'email';
            error_log("Validation failed: invalid email format");
        } else {
            // Process login
            error_log("Calling process_login for email: $email");
            list($success, $message, $error_field) = process_login($email, $password, $remember);

            if ($success) {
                error_log("Login successful for email: $email, redirecting to index.php");
                unset($_SESSION['sign_in_loop_prevent']);
                redirect("index.php", "Login successful.", "success");
            } else {
                $error = $message;
                $field_error = $error_field ?? '';
                error_log("Login failed for email: $email, message: $message, field_error: $error_field");

                // Check lockout status
                $login_attempts = get_login_attempts($ip_address, $email);
                if ($login_attempts >= LOGIN_ATTEMPT_LIMIT) {
                    $locked_until = get_lockout_expiry($ip_address, $email);
                    $wait_time = ceil(($locked_until - time()) / 60);
                    $error = "Too many failed login attempts. Your account is temporarily locked for {$wait_time} minutes.";
                    $field_error = '';
                    error_log("Account locked after $login_attempts attempts for email: $email");
                }
            }
        }
    }
}

// Clear loop prevention flag if rendering the page
unset($_SESSION['sign_in_loop_prevent']);

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline' 'unsafe-eval'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
?>
<!DOCTYPE html>
<html class="h-full" id="html-root" lang="en">
<head>
    <title>Majlisu Ahlil Qur'an - Sign In</title>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
    <meta content="Sign in to Majlisu Ahlil Qur'an International Qur'an Competition" name="description"/>
    <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet"/>
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
        .lang-toggle {
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .lang-toggle:hover {
            color: #3b82f6;
        }
    </style>
</head>
<body class="antialiased flex h-full text-base text-gray-700">
<div class="flex items-center justify-center grow bg-center bg-no-repeat" style="background-image: url('assets/media/images/2600x1200/bg-10.png'); background-size: cover;">
    <div class="card max-w-[370px] w-full">
        <!-- Language Toggle -->
        <div class="flex justify-end p-4">
            <span class="lang-toggle text-sm font-medium" onclick="toggleLanguage()">
                <span class="lang-en">العربية</span>
                <span class="lang-ar hidden">English</span>
            </span>
        </div>

        <!-- Flash Messages -->
        <?php echo get_flash_message(); ?>

        <!-- Error Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 mx-4 mt-4 rounded shadow-md" role="alert">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium lang-en <?php echo $_SESSION['language'] === 'ar' ? 'hidden' : ''; ?>"><?php echo htmlspecialchars($error); ?></p>
                        <p class="text-sm font-medium lang-ar <?php echo $_SESSION['language'] === 'en' ? 'hidden' : ''; ?>">
                            <?php
                            $translations = [
                                "Too many login attempts. Please try again after" => "محاولات تسجيل دخول كثيرة جدًا. يرجى المحاولة مرة أخرى بعد",
                                "minutes." => "دقائق.",
                                "Invalid form submission. Please try again." => "إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.",
                                "Please enter both email and password." => "يرجى إدخال البريد الإلكتروني وكلمة المرور.",
                                "Please enter a valid email address." => "يرجى إدخال عنوان بريد إلكتروني صالح.",
                                "Too many failed login attempts. Your account is temporarily locked for" => "محاولات تسجيل دخول فاشلة كثيرة جدًا. حسابك مقفل مؤقتًا لمدة",
                                "Incorrect password." => "كلمة المرور غير صحيحة.",
                                "Email not found." => "البريد الإلكتروني غير موجود.",
                                "Your account is pending verification." => "حسابك في انتظار التحقق.",
                                "Your account is inactive." => "حسابك غير نشط.",
                                "attempts remaining." => "محاولات متبقية.",
                                "An unexpected error occurred." => "حدث خطأ غير متوقع."
                            ];
                            $error_ar = htmlspecialchars($error);
                            foreach ($translations as $en => $ar) {
                                $error_ar = str_replace($en, $ar, $error_ar);
                            }
                            echo $error_ar;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="card-body flex flex-col gap-5 p-10" id="sign_in_form" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="language" id="language-input" value="<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>">

            <div class="text-center mb-2.5">
                <h3 class="text-lg font-medium text-gray-900 leading-none mb-2.5">
                    <span class="lang-en">Sign in</span>
                    <span class="lang-ar hidden">تسجيل الدخول</span>
                </h3>
                <div class="flex items-center justify-center font-medium">
                    <span class="text-2sm text-gray-700 me-1.5">
                        <span class="lang-en">Need an account?</span>
                        <span class="lang-ar hidden">هل تحتاج إلى حساب؟</span>
                    </span>
                    <a class="text-2sm link" href="sign-up.php">
                        <span class="lang-en">Sign up</span>
                        <span class="lang-ar hidden">إنشاء حساب</span>
                    </a>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="form-label font-normal text-gray-900" for="email">
                    <span class="lang-en">Email</span>
                    <span class="lang-ar hidden">البريد الإلكتروني</span>
                </label>
                <input class="input <?php echo ($field_error === 'email') ? 'input-error' : ''; ?>"
                       id="email" name="email" placeholder="email@email.com" type="email"
                       value="<?php echo htmlspecialchars($email); ?>" autocomplete="email" required/>
                <?php if ($field_error === 'email'): ?>
                    <div class="field-error-text">
                        <p class="lang-en <?php echo $_SESSION['language'] === 'ar' ? 'hidden' : ''; ?>"><?php echo htmlspecialchars($error); ?></p>
                        <p class="lang-ar <?php echo $_SESSION['language'] === 'en' ? 'hidden' : ''; ?>">
                            <?php
                            $error_ar = htmlspecialchars($error);
                            $translations = [
                                "Please enter both email and password." => "يرجى إدخال البريد الإلكتروني وكلمة المرور.",
                                "Please enter a valid email address." => "يرجى إدخال عنوان بريد إلكتروني صالح.",
                                "Email not found." => "البريد الإلكتروني غير موجود."
                            ];
                            foreach ($translations as $en => $ar) {
                                $error_ar = str_replace($en, $ar, $error_ar);
                            }
                            echo $error_ar;
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-1">
                    <label class="form-label font-normal text-gray-900" for="user_password">
                        <span class="lang-en">Password</span>
                        <span class="lang-ar hidden">كلمة المرور</span>
                    </label>
                    <a class="text-2sm link shrink-0" href="enter-email.php">
                        <span class="lang-en">Forgot Password?</span>
                        <span class="lang-ar hidden">نسيت كلمة المرور؟</span>
                    </a>
                </div>
                <div class="input <?php echo ($field_error === 'password') ? 'input-error' : ''; ?>" data-toggle-password="true">
                    <input id="user_password" name="user_password" placeholder="Enter Password" type="password"
                           autocomplete="current-password" required/>
                           <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
            <i class="ki-filled ki-eye text-gray-500 toggle-password-active:hidden"></i>
            <i class="ki-filled ki-eye-slash text-gray-500 hidden toggle-password-active:block"></i>
          </button>
                </div>
                <?php if ($field_error === 'password'): ?>
                    <div class="field-error-text">
                        <p class="lang-en <?php echo $_SESSION['language'] === 'ar' ? 'hidden' : ''; ?>"><?php echo htmlspecialchars($error); ?></p>
                        <p class="lang-ar <?php echo $_SESSION['language'] === 'en' ? 'hidden' : ''; ?>">
                            <?php
                            $error_ar = htmlspecialchars($error);
                            $translations = [
                                "Please enter both email and password." => "يرجى إدخال البريد الإلكتروني وكلمة المرور.",
                                "Incorrect password." => "كلمة المرور غير صحيحة."
                            ];
                            foreach ($translations as $en => $ar) {
                                $error_ar = str_replace($en, $ar, $error_ar);
                            }
                            echo $error_ar;
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <label class="checkbox-group">
                <input class="checkbox checkbox-sm" id="check" name="check" type="checkbox" value="1"/>
                <span class="checkbox-label">
                    <span class="lang-en">Remember me</span>
                    <span class="lang-ar hidden">تذكرني</span>
                </span>
            </label>

            <button type="submit" class="btn btn-primary flex justify-center grow" id="submit-btn">
                <span class="spinner">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
                <span class="lang-en">Sign In</span>
                <span class="lang-ar hidden">تسجيل الدخول</span>
            </button>
        </form>
    </div>
</div>
<script src="assets/js/core.bundle.js"> </script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const htmlRoot = document.getElementById('html-root');
    const langEnElements = document.querySelectorAll('.lang-en');
    const langArElements = document.querySelectorAll('.lang-ar');
    const languageInput = document.getElementById('language-input');

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
        languageInput.value = lang;
        // Update session language via AJAX to ensure consistency
        fetch('update_language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'language=' + encodeURIComponent(lang)
        });
    }

    function toggleLanguage() {
        const currentLang = sessionStorage.getItem('language') || '<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>';
        setLanguage(currentLang === 'en' ? 'ar' : 'en');
    }

    // Initialize language
    const savedLang = sessionStorage.getItem('language') || '<?php echo htmlspecialchars($_SESSION['language'] ?? 'en'); ?>';
    setLanguage(savedLang);

    window.toggleLanguage = toggleLanguage;

    // Form submission handling
    const form = document.getElementById('sign_in_form');
    const submitBtn = document.getElementById('submit-btn');

    form.addEventListener('submit', function(e) {
        submitBtn.classList.add('loading');
    });
});
</script>
</body>
</html>