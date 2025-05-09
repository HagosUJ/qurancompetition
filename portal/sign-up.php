<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/portal/sign-up.php
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
    redirect('index.php');
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
        } elseif (!is_strong_password($password)) {
            $error = "Password must be at least 8 characters with at least two character types.";
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
                redirect('check-email.php?email=' . urlencode($email) . '&type=verification');
                exit;
            } else {
                $error = $message;
                if (stripos($message, 'email') !== false) {
                    $field_error = 'email';
                } else {
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
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline' 'unsafe-eval'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" id="html-root" lang="en">
<head>
    <title>Sign Up | <?php echo defined('APP_NAME') ? APP_NAME : 'Musabaqa'; ?></title>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
    <meta content="Sign up for Majlisu Ahlil Qur'an International Qur'an Competition" name="description"/>
    <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet"/>
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
            color: transparent !important;
            pointer-events: none;
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
            background-color: #e5e7eb;
        }
        .password-strength > div {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
            width: 0;
        }

        .strength-weak > div {
            width: 33%;
            background-color: #ef4444;
        }

        .strength-medium > div {
            width: 66%;
            background-color: #f59e0b;
        }

        .strength-strong > div {
            width: 100%;
            background-color: #10b981;
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

        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .logo {
            max-width: 150px;
            height: auto;
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
        <!-- Language Toggle -->
        <div class="flex justify-end p-4">
            <span class="lang-toggle text-sm font-medium" onclick="toggleLanguage()">
                <span class="lang-en">العربية</span>
                <span class="lang-ar hidden">English</span>
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
                        <p class="text-sm font-medium lang-en"><?php echo htmlspecialchars($error); ?></p>
                        <p class="text-sm font-medium lang-ar hidden">
                            <?php
                            $translations = [
                                "Invalid form submission. Please try again." => "إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.",
                                "Email address is already registered." => "البريد الإلكتروني مسجل بالفعل."
                            ];
                            $error_ar = $error;
                            foreach ($translations as $en => $ar) {
                                $error_ar = str_replace($en, $ar, $error_ar);
                            }
                            echo htmlspecialchars($error_ar);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="card-body flex flex-col gap-5 p-10" id="sign_up_form" method="post" novalidate>
            <!-- CSRF Protection -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Logo -->
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Majlisu Ahlil Qur'an Logo" class="logo">
            </div>

            <div class="text-center mb-2.5">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white leading-none mb-2.5">
                    <span class="lang-en">Sign Up</span>
                    <span class="lang-ar hidden">إنشاء حساب</span>
                </h3>
                <div class="flex items-center justify-center font-medium">
                    <span class="text-2sm text-gray-700 dark:text-gray-300 me-1.5">
                        <span class="lang-en">Already have an account?</span>
                        <span class="lang-ar hidden">لديك حساب بالفعل؟</span>
                    </span>
                    <a class="text-2sm link" href="sign-in.php">
                        <span class="lang-en">Sign In</span>
                        <span class="lang-ar hidden">تسجيل الدخول</span>
                    </a>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="fullname">
                    <span class="lang-en">Full Name</span>
                    <span class="lang-ar hidden">الاسم الكامل</span>
                </label>
                <input class="input <?php echo ($field_error === 'fullname') ? 'input-error' : ''; ?>"
                       id="fullname" name="fullname" placeholder="Enter your full name" type="text"
                       value="<?php echo htmlspecialchars($fullname); ?>" required/>
                <?php if ($field_error === 'fullname'): ?>
                    <div class="field-error-text" id="fullname-error">
                        <span class="lang-en"><?php echo htmlspecialchars($error); ?></span>
                        <span class="lang-ar hidden">الاسم الكامل مطلوب</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1">
                <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="email">
                    <span class="lang-en">Email</span>
                    <span class="lang-ar hidden">البريد الإلكتروني</span>
                </label>
                <input class="input <?php echo ($field_error === 'email') ? 'input-error' : ''; ?>"
                       id="email" name="email" placeholder="email@email.com" type="email"
                       value="<?php echo htmlspecialchars($email); ?>" required/>
                <?php if ($field_error === 'email'): ?>
                    <div class="field-error-text" id="email-error">
                        <span class="lang-en"><?php echo htmlspecialchars($error); ?></span>
                        <span class="lang-ar hidden">
                            <?php
                            $translations = [
                                "Email address is required." => "عنوان البريد الإلكتروني مطلوب.",
                                "Please enter a valid email address." => "يرجى إدخال عنوان بريد إلكتروني صالح.",
                                "Email address is already registered." => "البريد الإلكتروني مسجل بالفعل."
                            ];
                            $error_ar = $error;
                            foreach ($translations as $en => $ar) {
                                $error_ar = str_replace($en, $ar, $error_ar);
                            }
                            echo htmlspecialchars($error_ar);
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1">
                <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="password">
                    <span class="lang-en">Password</span>
                    <span class="lang-ar hidden">كلمة المرور</span>
                </label>
                <div class="input <?php echo ($field_error === 'password') ? 'input-error' : ''; ?>" data-toggle-password="true">
                    <input id="password" name="password" placeholder="Enter password" type="password" required autocomplete="new-password"/>
                    <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
                        <i class="ki-filled ki-eye text-gray-500 hidden toggle-password-active:block"></i>
                        <i class="ki-filled ki-eye-slash text-gray-500 toggle-password-active:hidden"></i>
                    </button>
                </div>
                <div class="password-strength" id="password-strength"><div></div></div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="password-feedback">
                    <span class="lang-en">Password must be at least 8 characters with at least two character types.</span>
                    <span class="lang-ar hidden">يجب أن تكون كلمة المرور 8 أحرف على الأقل مع نوعين من الأحرف على الأقل.</span>
                </div>
                <?php if ($field_error === 'password'): ?>
                    <div class="field-error-text" id="password-error">
                        <span class="lang-en"><?php echo htmlspecialchars($error); ?></span>
                        <span class="lang-ar hidden">يجب أن تكون كلمة المرور 8 أحرف على الأقل مع نوعين من الأحرف على الأقل.</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1">
                <label class="form-label font-normal text-gray-900 dark:text-gray-200" for="confirm_password">
                    <span class="lang-en">Confirm Password</span>
                    <span class="lang-ar hidden">تأكيد كلمة المرور</span>
                </label>
                <div class="input <?php echo ($field_error === 'confirm_password') ? 'input-error' : ''; ?>" data-toggle-password="true">
                    <input id="confirm_password" name="confirm_password" placeholder="Confirm password" type="password" required/>
                    <button class="btn btn-icon" data-toggle-password-trigger="true" type="button">
                        <i class="ki-filled ki-eye text-gray-500 hidden toggle-password-active:block"></i>
                        <i class="ki-filled ki-eye-slash text-gray-500 toggle-password-active:hidden"></i>
                    </button>
                </div>
                <?php if ($field_error === 'confirm_password'): ?>
                    <div class="field-error-text" id="confirm-password-error">
                        <span class="lang-en"><?php echo htmlspecialchars($error); ?></span>
                        <span class="lang-ar hidden">كلمات المرور غير متطابقة.</span>
                    </div>
                <?php endif; ?>
            </div>

            <label class="checkbox-group <?php echo ($field_error === 'terms') ? 'checkbox-error' : ''; ?>">
                <input class="checkbox checkbox-sm" id="terms" name="terms" required type="checkbox"/>
                <span class="checkbox-label dark:text-gray-300">
                    <span class="lang-en">I Accept the <a href="terms.php" class="link" target="_blank">Terms & Conditions</a></span>
                    <span class="lang-ar hidden">أوافق على <a href="terms.php" class="link" target="_blank">الشروط والأحكام</a></span>
                </span>
            </label>
            <?php if ($field_error === 'terms'): ?>
                <div class="field-error-text mt-0" id="terms-error">
                    <span class="lang-en"><?php echo htmlspecialchars($error); ?></span>
                    <span class="lang-ar hidden">يجب قبول الشروط والأحكام.</span>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary flex justify-center grow" id="submit-btn">
                <span class="spinner">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
                <span class="lang-en">Sign Up</span>
                <span class="lang-ar hidden">إنشاء حساب</span>
            </button>
        </form>
    </div>
</div>
<!-- End of Page -->

<!-- Scripts -->
<script src="assets/js/core.bundle.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Language handling
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
        localStorage.setItem('language', lang);
    }

    function toggleLanguage() {
        const currentLang = localStorage.getItem('language') || 'en';
        setLanguage(currentLang === 'en' ? 'ar' : 'en');
    }

    // Initialize language
    const savedLang = localStorage.getItem('language') || 'en';
    setLanguage(savedLang);

    // Expose toggleLanguage to global scope for onclick
    window.toggleLanguage = toggleLanguage;

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

    // Password strength checker function - FIXED to properly count character types
    function checkPasswordStrength(password) {
        // Reset strength indicator
        passwordStrengthInnerDiv.style.width = '0%';
        passwordStrengthDiv.className = 'password-strength';
        
        if (!password) {
            return {
                strength: 0,
                feedback: '<span class="lang-en">Password must be at least 8 characters with at least two character types.</span><span class="lang-ar hidden">يجب أن تكون كلمة المرور 8 أحرف على الأقل مع نوعين من الأحرف.</span>',
                feedbackColorClass: 'text-gray-500 dark:text-gray-400'
            };
        }

        // Check minimum length
        const hasMinLength = password.length >= 8;
        
        // Count character types present
        let charTypesCount = 0;
        if (/[A-Z]/.test(password)) charTypesCount++;
        if (/[a-z]/.test(password)) charTypesCount++;
        if (/\d/.test(password)) charTypesCount++;
        if (/[^a-zA-Z\d]/.test(password)) charTypesCount++;
        
        // Calculate feedback messages for missing requirements
        let feedbackMessages = [];
        if (!hasMinLength) {
            feedbackMessages.push('<span class="lang-en">at least 8 characters</span><span class="lang-ar hidden">8 أحرف على الأقل</span>');
        }
        
        // Add missing character types to feedback
        if (!/[A-Z]/.test(password)) {
            feedbackMessages.push('<span class="lang-en">uppercase letter</span><span class="lang-ar hidden">حرف كبير</span>');
        }
        if (!/[a-z]/.test(password)) {
            feedbackMessages.push('<span class="lang-en">lowercase letter</span><span class="lang-ar hidden">حرف صغير</span>');
        }
        if (!/\d/.test(password)) {
            feedbackMessages.push('<span class="lang-en">number</span><span class="lang-ar hidden">رقم</span>');
        }
        if (!/[^a-zA-Z\d]/.test(password)) {
            feedbackMessages.push('<span class="lang-en">special character</span><span class="lang-ar hidden">حرف خاص</span>');
        }

        // Set strength based on requirements met
        let strength = 0;
        if (!hasMinLength || charTypesCount < 2) {
            strength = 1; // Weak
        } else if (charTypesCount === 2) {
            strength = 2; // Medium (meets minimum requirements)
        } else {
            strength = 3; // Strong (exceeds minimum requirements)
        }

        // Determine UI feedback
        let strengthClass = '';
        let feedbackText = '';
        let feedbackColorClass = '';

        if (strength === 1) {
            strengthClass = 'strength-weak';
            feedbackText = '<span class="lang-en">Weak. Need: </span><span class="lang-ar hidden">ضعيف. يحتاج: </span>' + 
                feedbackMessages.join(', ') + '.';
            feedbackColorClass = 'text-red-500';
        } else if (strength === 2) {
            strengthClass = 'strength-medium';
            feedbackText = '<span class="lang-en">Medium. Password meets minimum requirements.</span><span class="lang-ar hidden">متوسط. كلمة المرور تلبي الحد الأدنى من المتطلبات.</span>';
            feedbackColorClass = 'text-amber-500';
        } else {
            strengthClass = 'strength-strong';
            feedbackText = '<span class="lang-en">Strong. Password exceeds minimum requirements.</span><span class="lang-ar hidden">قوي. كلمة المرور تتجاوز الحد الأدنى من المتطلبات.</span>';
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
        passwordFeedback.innerHTML = feedback;
        passwordFeedback.className = 'text-xs mt-1 ' + feedbackColorClass;
        setLanguage(localStorage.getItem('language') || 'en');
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
            appendError(fullnameInput, '<span class="lang-en">Full name is required</span><span class="lang-ar hidden">الاسم الكامل مطلوب</span>');
        }

        // Validate email
        if (!emailInput.value.trim()) {
            isValid = false;
            emailInput.classList.add('input-error');
            appendError(emailInput, '<span class="lang-en">Email is required</span><span class="lang-ar hidden">البريد الإلكتروني مطلوب</span>');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
            isValid = false;
            emailInput.classList.add('input-error');
            appendError(emailInput, '<span class="lang-en">Please enter a valid email address</span><span class="lang-ar hidden">يرجى إدخال عنوان بريد إلكتروني صالح</span>');
        }

        // Validate password - FIXED to use strength >= 2
        const currentPasswordValue = passwordInput.value;
        const { strength } = checkPasswordStrength(currentPasswordValue);
        if (!currentPasswordValue) {
            isValid = false;
            passwordInput.parentElement.classList.add('input-error');
            appendError(passwordInput.parentElement, '<span class="lang-en">Password is required</span><span class="lang-ar hidden">كلمة المرور مطلوبة</span>');
        } else if (strength < 2) { // Changed from < 3 to < 2 to match backend
            isValid = false;
            passwordInput.parentElement.classList.add('input-error');
            appendError(passwordInput.parentElement, '<span class="lang-en">Password must be at least 8 characters with at least two character types.</span><span class="lang-ar hidden">يجب أن تكون كلمة المرور 8 أحرف على الأقل مع نوعين من الأحرف على الأقل.</span>');
        }

        // Validate confirm password
        const currentConfirmPasswordValue = confirmPasswordInput.value;
        if (!currentConfirmPasswordValue) {
            isValid = false;
            confirmPasswordInput.parentElement.classList.add('input-error');
            appendError(confirmPasswordInput.parentElement, '<span class="lang-en">Please confirm your password</span><span class="lang-ar hidden">يرجى تأكيد كلمة المرور</span>');
        } else if (currentConfirmPasswordValue !== currentPasswordValue) {
            isValid = false;
            confirmPasswordInput.parentElement.classList.add('input-error');
            appendError(confirmPasswordInput.parentElement, '<span class="lang-en">Passwords do not match</span><span class="lang-ar hidden">كلمات المرور غير متطابقة</span>');
        }

        // Validate terms acceptance
        if (!termsCheckbox.checked) {
            isValid = false;
            termsCheckbox.parentElement.classList.add('checkbox-error');
            appendError(termsCheckbox.parentElement, '<span class="lang-en">You must accept the Terms & Conditions</span><span class="lang-ar hidden">يجب قبول الشروط والأحكام</span>');
        }

        if (!isValid) {
            e.preventDefault();
            setLanguage(localStorage.getItem('language') || 'en');
        } else {
            submitBtn.classList.add('loading');
        }
    });

    // Helper to append error messages below the input's parent div
    function appendError(element, message) {
        const parent = element.closest('.flex.flex-col.gap-1, .checkbox-group');
        if (!parent || parent.querySelector('.field-error-text')) {
            return;
        }
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error-text';
        if (element.id === 'terms') {
            errorDiv.classList.add('mt-0');
        }
        errorDiv.innerHTML = message;
        parent.appendChild(errorDiv);
        setLanguage(localStorage.getItem('language') || 'en');
    }

    // Input event listeners to clear errors when typing/changing
    [fullnameInput, emailInput, passwordInput, confirmPasswordInput].forEach(input => {
        input.addEventListener('input', function() {
            const parentWrapper = input.closest('.input');
            const elementToRemoveErrorFrom = parentWrapper || input;
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
        const parent = element.closest('.flex.flex-col.gap-1, .checkbox-group');
        if (parent) {
            const errorElement = parent.querySelector('.field-error-text');
            if (errorElement) errorElement.remove();
        }
    }

    // Focus on first input field on page load
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
    }, 100);
});
</script>
</body>
</html>