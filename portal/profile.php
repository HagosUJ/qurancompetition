<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/profile.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

// --- Authentication & Session Management ---
if (!is_logged_in()) {
    set_flash_message($_SESSION['language'] === 'ar' ? "يجب تسجيل الدخول لعرض ملفك الشخصي." : "You must be logged in to view your profile.", 'error');
    redirect('sign-in.php');
    exit;
}

$language = $_SESSION['language'] ?? 'en'; // Default to English
$is_rtl = ($language === 'ar');

// Session timeout check
$timeout_duration = SESSION_TIMEOUT_DURATION ?? 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    logout_user('sign-in.php?reason=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_fullname = $_SESSION['user_fullname'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_role = $_SESSION['user_role'] ?? 'user';
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png'; // Default avatar

// Variables for form feedback
$profile_error = '';
$profile_success = '';
$password_error = '';
$password_success = '';
$profile_field_error = '';
$password_field_error = '';

// Define upload directory and allowed types/size
define('PROFILE_PIC_UPLOAD_DIR', 'Uploads/profile_pictures/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024); // 2MB

// Ensure upload directory exists and is writable
if (!is_dir(PROFILE_PIC_UPLOAD_DIR)) {
    mkdir(PROFILE_PIC_UPLOAD_DIR, 0775, true);
}
if (!is_writable(PROFILE_PIC_UPLOAD_DIR)) {
    error_log("Profile picture upload directory is not writable: " . PROFILE_PIC_UPLOAD_DIR);
}

// --- Language-Specific Strings ---
$translations = [
    'en' => [
        'page_title' => 'My Profile | Musabaqa',
        'page_header' => 'My Profile',
        'dashboard' => 'Dashboard',
        'profile' => 'Profile',
        'account_details' => 'Account Details',
        'change_password' => 'Change Password',
        'change_profile_picture' => 'Change Profile Picture',
        'full_name' => 'Full Name',
        'email_address' => 'Email Address',
        'update_profile' => 'Update Profile',
        'current_password' => 'Current Password',
        'new_password' => 'New Password',
        'confirm_new_password' => 'Confirm New Password',
        'password_requirements' => 'Password should be at least 8 characters, include upper & lower case, a number, and a special character.',
        'profile_picture_instructions' => 'Max 2MB. Allowed types: JPG, PNG, GIF.',
        'error_invalid_request' => 'Invalid request. Please try again.',
        'error_empty_fullname' => 'Full name cannot be empty.',
        'error_empty_email' => 'Email cannot be empty.',
        'error_invalid_email' => 'Invalid email format.',
        'error_email_taken' => 'This email address is already registered by another user.',
        'error_invalid_file_type' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.',
        'error_file_too_large' => 'File is too large. Maximum size is 2MB.',
        'error_upload_failed' => 'Failed to upload profile picture. Please try again.',
        'error_upload_error' => 'Error uploading file. Code: %s',
        'error_no_changes' => 'No changes detected.',
        'error_empty_current_password' => 'Current password is required.',
        'error_empty_new_password' => 'New password is required.',
        'error_weak_password' => 'New password does not meet complexity requirements.',
        'error_password_mismatch' => 'New passwords do not match.',
        'error_incorrect_current_password' => 'Incorrect current password.',
        'success_profile_updated' => 'Profile updated successfully.',
        'success_password_updated' => 'Password updated successfully.',
        'error_profile_update_failed' => 'Failed to update profile. Please try again.',
        'error_password_update_failed' => 'Failed to update password. Please try again.',
        // JavaScript-specific translations
        'js_invalid_image' => 'Please select a valid image file (JPG, PNG, GIF).',
        'js_password_match_error' => 'New passwords do not match.',
        'js_password_feedback' => 'Password should be at least 8 characters, include upper & lower case, a number, and a special character.',
        'js_password_weak' => 'Weak. Needs: %s.',
        'js_password_medium' => 'Medium. Needs: %s.',
        'js_password_strong' => 'Strong. Consider adding: %s.',
        'js_password_very_strong' => 'Very Strong!',
    ],
    'ar' => [
        'page_title' => 'ملفي الشخصي | المسابقة',
        'page_header' => 'ملفي الشخصي',
        'dashboard' => 'لوحة التحكم',
        'profile' => 'الملف الشخصي',
        'account_details' => 'تفاصيل الحساب',
        'change_password' => 'تغيير كلمة المرور',
        'change_profile_picture' => 'تغيير صورة الملف الشخصي',
        'full_name' => 'الاسم الكامل',
        'email_address' => 'البريد الإلكتروني',
        'update_profile' => 'تحديث الملف الشخصي',
        'current_password' => 'كلمة المرور الحالية',
        'new_password' => 'كلمة المرور الجديدة',
        'confirm_new_password' => 'تأكيد كلمة المرور الجديدة',
        'password_requirements' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل، وتشمل أحرفًا كبيرة وصغيرة، ورقمًا، وحرفًا خاصًا.',
        'profile_picture_instructions' => 'الحد الأقصى 2 ميجابايت. الأنواع المسموح بها: JPG، PNG، GIF.',
        'error_invalid_request' => 'طلب غير صالح. يرجى المحاولة مرة أخرى.',
        'error_empty_fullname' => 'الاسم الكامل لا يمكن أن يكون فارغًا.',
        'error_empty_email' => 'البريد الإلكتروني لا يمكن أن يكون فارغًا.',
        'error_invalid_email' => 'تنسيق البريد الإلكتروني غير صالح.',
        'error_email_taken' => 'عنوان البريد الإلكتروني هذا مسجل بالفعل بواسطة مستخدم آخر.',
        'error_invalid_file_type' => 'نوع الملف غير صالح. يُسمح فقط بـ JPG، PNG، و GIF.',
        'error_file_too_large' => 'الملف كبير جدًا. الحد الأقصى للحجم هو 2 ميجابايت.',
        'error_upload_failed' => 'فشل رفع صورة الملف الشخصي. يرجى المحاولة مرة أخرى.',
        'error_upload_error' => 'خطأ في رفع الملف. الرمز: %s',
        'error_no_changes' => 'لم يتم اكتشاف أي تغييرات.',
        'error_empty_current_password' => 'كلمة المرور الحالية مطلوبة.',
        'error_empty_new_password' => 'كلمة المرور الجديدة مطلوبة.',
        'error_weak_password' => 'كلمة المرور الجديدة لا تلبي متطلبات التعقيد.',
        'error_password_mismatch' => 'كلمات المرور الجديدة غير متطابقة.',
        'error_incorrect_current_password' => 'كلمة المرور الحالية غير صحيحة.',
        'success_profile_updated' => 'تم تحديث الملف الشخصي بنجاح.',
        'success_password_updated' => 'تم تحديث كلمة المرور بنجاح.',
        'error_profile_update_failed' => 'فشل تحديث الملف الشخصي. يرجى المحاولة مرة أخرى.',
        'error_password_update_failed' => 'فشل تحديث كلمة المرور. يرجى المحاولة مرة أخرى.',
        // JavaScript-specific translations
        'js_invalid_image' => 'يرجى اختيار ملف صورة صالح (JPG، PNG، GIF).',
        'js_password_match_error' => 'كلمات المرور الجديدة غير متطابقة.',
        'js_password_feedback' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل، وتشمل أحرفًا كبيرة وصغيرة، ورقمًا، وحرفًا خاصًا.',
        'js_password_weak' => 'ضعيف. يحتاج إلى: %s.',
        'js_password_medium' => 'متوسط. يحتاج إلى: %s.',
        'js_password_strong' => 'قوي. يُفضل إضافة: %s.',
        'js_password_very_strong' => 'قوي جدًا!',
    ]
];

// --- Handle Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $profile_error = $translations[$language]['error_invalid_request'];
    } else {
        $new_fullname = sanitize_input($_POST['fullname']);
        $new_email = sanitize_input($_POST['email']);
        $profile_pic_path = $profile_picture;
        $update_needed = false;

        if (empty($new_fullname)) {
            $profile_error = $translations[$language]['error_empty_fullname'];
            $profile_field_error = 'fullname';
        } elseif (empty($new_email)) {
            $profile_error = $translations[$language]['error_empty_email'];
            $profile_field_error = 'email';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $profile_error = $translations[$language]['error_invalid_email'];
            $profile_field_error = 'email';
        } else {
            if ($new_email !== $user_email) {
                $conn = connect_db();
                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt_check_email->bind_param("si", $new_email, $user_id);
                $stmt_check_email->execute();
                $stmt_check_email->store_result();
                if ($stmt_check_email->num_rows > 0) {
                    $profile_error = $translations[$language]['error_email_taken'];
                    $profile_field_error = 'email';
                }
                $stmt_check_email->close();
                $conn->close();
                if (empty($profile_error)) $update_needed = true;
            }

            if ($new_fullname !== $user_fullname) {
                $update_needed = true;
            }

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];

                if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
                    $profile_error = $translations[$language]['error_invalid_file_type'];
                    $profile_field_error = 'profile_picture';
                } elseif ($file['size'] > MAX_IMAGE_SIZE) {
                    $profile_error = $translations[$language]['error_file_too_large'];
                    $profile_field_error = 'profile_picture';
                } else {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $unique_filename = uniqid('user_' . $user_id . '_', true) . '.' . strtolower($extension);
                    $destination = PROFILE_PIC_UPLOAD_DIR . $unique_filename;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        if ($profile_picture !== 'assets/media/avatars/blank.png' && file_exists($profile_picture)) {
                            unlink($profile_picture);
                        }
                        $profile_pic_path = $destination;
                        $update_needed = true;
                    } else {
                        $profile_error = $translations[$language]['error_upload_failed'];
                        $profile_field_error = 'profile_picture';
                        error_log("Failed to move uploaded file to: " . $destination);
                    }
                }
            } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                $profile_error = sprintf($translations[$language]['error_upload_error'], $_FILES['profile_picture']['error']);
                $profile_field_error = 'profile_picture';
            }

            if (empty($profile_error) && $update_needed) {
                $conn = connect_db();
                $stmt_update = $conn->prepare("UPDATE users SET fullname = ?, email = ?, profile_picture = ? WHERE id = ?");
                $stmt_update->bind_param("sssi", $new_fullname, $new_email, $profile_pic_path, $user_id);

                if ($stmt_update->execute()) {
                    $_SESSION['user_fullname'] = $new_fullname;
                    $_SESSION['user_email'] = $new_email;
                    $_SESSION['user_profile_pic'] = $profile_pic_path;
                    set_flash_message($translations[$language]['success_profile_updated'], 'success');
                } else {
                    error_log("Profile Update Error: " . $stmt_update->error);
                    set_flash_message($translations[$language]['error_profile_update_failed'], 'error');
                }
                $stmt_update->close();
                $conn->close();
                redirect('profile.php');
                exit;
            } elseif (empty($profile_error) && !$update_needed) {
                $profile_error = $translations[$language]['error_no_changes'];
            }
        }
        $user_fullname = $new_fullname;
        $user_email = $new_email;
    }
}

// --- Handle Password Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $password_error = $translations[$language]['error_invalid_request'];
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        if (empty($current_password)) {
            $password_error = $translations[$language]['error_empty_current_password'];
            $password_field_error = 'current_password';
        } elseif (empty($new_password)) {
            $password_error = $translations[$language]['error_empty_new_password'];
            $password_field_error = 'new_password';
        } elseif (!is_strong_password($new_password)) {
            $password_error = $translations[$language]['error_weak_password'];
            $password_field_error = 'new_password';
        } elseif ($new_password !== $confirm_new_password) {
            $password_error = $translations[$language]['error_password_mismatch'];
            $password_field_error = 'confirm_new_password';
        } else {
            $conn = connect_db();
            $stmt_verify = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_verify->bind_param("i", $user_id);
            $stmt_verify->execute();
            $result = $stmt_verify->get_result();
            $user = $result->fetch_assoc();
            $stmt_verify->close();

            if ($user && password_verify($current_password, $user['password'])) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update_pass->bind_param("si", $new_password_hash, $user_id);

                if ($stmt_update_pass->execute()) {
                    set_flash_message($translations[$language]['success_password_updated'], 'success');
                } else {
                    error_log("Password Update Error: " . $stmt_update_pass->error);
                    set_flash_message($translations[$language]['error_password_update_failed'], 'error');
                }
                $stmt_update_pass->close();
            } else {
                $password_error = $translations[$language]['error_incorrect_current_password'];
                $password_field_error = 'current_password';
            }
            $conn->close();
            redirect('profile.php');
            exit;
        }
    }
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:;");
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
<head>
    <meta charset="utf-8" />
    <title><?php echo $translations[$language]['page_title']; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
            margin-bottom: 1rem;
        }
        .input-error { border-color: #dc3545 !important; background-color: #f8d7da !important; }
        .field-error-text { color: #dc3545; font-size: 0.875em; margin-top: .25rem; }
        .password-strength { height: 5px; margin-top: 5px; border-radius: 2px; transition: all 0.3s ease; background-color: #e9ecef; }
        .password-strength > div { height: 100%; border-radius: 2px; transition: width 0.3s ease; width: 0; }
        .strength-weak > div { width: 25%; background-color: #dc3545; }
        .strength-medium > div { width: 50%; background-color: #ffc107; }
        .strength-strong > div { width: 75%; background-color: #0d6efd; }
        .strength-very-strong > div { width: 100%; background-color: #198754; }
        .btn .spinner-border { display: none; }
        .btn.loading .spinner-border { display: inline-block; }
        .btn.loading span:not(.spinner-border) { visibility: hidden; }
        [data-toggle-password="true"] .btn-icon i { display: inline-block !important; }
        [data-toggle-password="true"] .btn-icon .toggle-password-active\:hidden { display: none !important; }
        [data-toggle-password="true"].toggle-password-active .btn-icon .toggle-password-active\:hidden { display: inline-block !important; }
        [data-toggle-password="true"].toggle-password-active .btn-icon .hidden.toggle-password-active\:block { display: none !important; }
        <?php if ($is_rtl): ?>
        .text-end { text-align: left !important; }
        .input-group .btn-icon { order: -1; }
        .form-label { text-align: right; }
        .invalid-feedback { text-align: right; }
        .text-muted { text-align: right; }
        <?php endif; ?>
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'layouts/menu.php'; ?>
        <div class="content-page">
            <div class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 class="page-title"><?php echo $translations[$language]['page_header']; ?></h4>
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php"><?php echo $translations[$language]['dashboard']; ?></a></li>
                                        <li class="breadcrumb-item active"><?php echo $translations[$language]['profile']; ?></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="flash-message-container">
                        <?php echo get_flash_message(); ?>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="header-title mb-4"><?php echo $translations[$language]['account_details']; ?></h4>
                                    <?php if (!empty($profile_error) && empty($profile_field_error)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo htmlspecialchars($profile_error); ?>
                                        </div>
                                    <?php endif; ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="profile_form" enctype="multipart/form-data" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="mb-3 text-center">
                                            <img src="<?php echo htmlspecialchars($profile_picture); ?>?t=<?php echo time(); ?>" alt="<?php echo $translations[$language]['change_profile_picture']; ?>" id="profile_picture_preview" class="profile-picture-preview img-thumbnail">
                                            <div>
                                                <label for="profile_picture" class="form-label"><?php echo $translations[$language]['change_profile_picture']; ?></label>
                                                <input class="form-control <?php echo ($profile_field_error === 'profile_picture') ? 'is-invalid' : ''; ?>" type="file" id="profile_picture" name="profile_picture" accept="image/png, image/jpeg, image/gif">
                                                <?php if ($profile_field_error === 'profile_picture'): ?>
                                                    <div class="invalid-feedback d-block field-error-text"><?php echo htmlspecialchars($profile_error); ?></div>
                                                <?php endif; ?>
                                                <small class="form-text text-muted"><?php echo $translations[$language]['profile_picture_instructions']; ?></small>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="fullname" class="form-label"><?php echo $translations[$language]['full_name']; ?></label>
                                            <input type="text" id="fullname" name="fullname" class="form-control <?php echo ($profile_field_error === 'fullname') ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_fullname); ?>" required>
                                            <?php if ($profile_field_error === 'fullname'): ?>
                                                <div class="invalid-feedback field-error-text"><?php echo htmlspecialchars($profile_error); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label"><?php echo $translations[$language]['email_address']; ?></label>
                                            <input type="email" id="email" name="email" class="form-control <?php echo ($profile_field_error === 'email') ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_email); ?>" required>
                                            <?php if ($profile_field_error === 'email'): ?>
                                                <div class="invalid-feedback field-error-text"><?php echo htmlspecialchars($profile_error); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" name="update_profile" class="btn btn-primary" id="profile-submit-btn">
                                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                <span><?php echo $translations[$language]['update_profile']; ?></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="header-title mb-4"><?php echo $translations[$language]['change_password']; ?></h4>
                                    <?php if (!empty($password_error) && empty($password_field_error)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo htmlspecialchars($password_error); ?>
                                        </div>
                                    <?php endif; ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="password_form" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label"><?php echo $translations[$language]['current_password']; ?></label>
                                            <div class="input-group input-group-merge <?php echo ($password_field_error === 'current_password') ? 'is-invalid' : ''; ?>" data-toggle-password="true">
                                                <input type="password" id="current_password" name="current_password" class="form-control" placeholder="<?php echo $translations[$language]['current_password']; ?>" required>
                                                <button class="input-group-text btn-icon" data-toggle-password-trigger="true" type="button" tabindex="-1">
                                                    <i class="ri-eye-line hidden toggle-password-active:block"></i>
                                                    <i class="ri-eye-off-line toggle-password-active:hidden"></i>
                                                </button>
                                            </div>
                                            <?php if ($password_field_error === 'current_password'): ?>
                                                <div class="invalid-feedback d-block field-error-text"><?php echo htmlspecialchars($password_error); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label"><?php echo $translations[$language]['new_password']; ?></label>
                                            <div class="input-group input-group-merge <?php echo ($password_field_error === 'new_password') ? 'is-invalid' : ''; ?>" data-toggle-password="true">
                                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="<?php echo $translations[$language]['new_password']; ?>" required autocomplete="new-password">
                                                <button class="input-group-text btn-icon" data-toggle-password-trigger="true" type="button" tabindex="-1">
                                                    <i class="ri-eye-line hidden toggle-password-active:block"></i>
                                                    <i class="ri-eye-off-line toggle-password-active:hidden"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength mt-1" id="password-strength"><div></div></div>
                                            <div class="text-muted fs-13 mt-1" id="password-feedback"><?php echo $translations[$language]['password_requirements']; ?></div>
                                            <?php if ($password_field_error === 'new_password'): ?>
                                                <div class="invalid-feedback d-block field-error-text"><?php echo htmlspecialchars($password_error); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_new_password" class="form-label"><?php echo $translations[$language]['confirm_new_password']; ?></label>
                                            <div class="input-group input-group-merge <?php echo ($password_field_error === 'confirm_new_password') ? 'is-invalid' : ''; ?>" data-toggle-password="true">
                                                <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" placeholder="<?php echo $translations[$language]['confirm_new_password']; ?>" required>
                                                <button class="input-group-text btn-icon" data-toggle-password-trigger="true" type="button" tabindex="-1">
                                                    <i class="ri-eye-line hidden toggle-password-active:block"></i>
                                                    <i class="ri-eye-off-line toggle-password-active:hidden"></i>
                                                </button>
                                            </div>
                                            <?php if ($password_field_error === 'confirm_new_password'): ?>
                                                <div class="invalid-feedback d-block field-error-text"><?php echo htmlspecialchars($password_error); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" name="update_password" class="btn btn-primary" id="password-submit-btn">
                                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                <span><?php echo $translations[$language]['change_password']; ?></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'layouts/footer.php'; ?>
        </div>
    </div>
    <?php include 'layouts/right-sidebar.php'; ?>
    <?php include 'layouts/footer-scripts.php'; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const translations = {
            invalid_image: '<?php echo $translations[$language]['js_invalid_image']; ?>',
            password_match_error: '<?php echo $translations[$language]['js_password_match_error']; ?>',
            password_feedback: '<?php echo $translations[$language]['js_password_feedback']; ?>',
            password_weak: '<?php echo $translations[$language]['js_password_weak']; ?>',
            password_medium: '<?php echo $translations[$language]['js_password_medium']; ?>',
            password_strong: '<?php echo $translations[$language]['js_password_strong']; ?>',
            password_very_strong: '<?php echo $translations[$language]['js_password_very_strong']; ?>'
        };

        const profilePicInput = document.getElementById('profile_picture');
        const profilePicPreview = document.getElementById('profile_picture_preview');
        if (profilePicInput && profilePicPreview) {
            profilePicInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicPreview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                } else if (file) {
                    alert(translations.invalid_image);
                    profilePicInput.value = '';
                }
            });
        }

        const profileForm = document.getElementById('profile_form');
        const profileSubmitBtn = document.getElementById('profile-submit-btn');
        const passwordForm = document.getElementById('password_form');
        const passwordSubmitBtn = document.getElementById('password-submit-btn');

        function handleFormSubmit(form, button) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                form.querySelectorAll('[required]').forEach(input => {
                    if (!input.value.trim() && input.type !== 'file') {
                        isValid = false;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                if (form.id === 'password_form') {
                    const newPass = document.getElementById('new_password');
                    const confirmPass = document.getElementById('confirm_new_password');
                    if (newPass.value && confirmPass.value && newPass.value !== confirmPass.value) {
                        isValid = false;
                        confirmPass.classList.add('is-invalid');
                        appendError(confirmPass.closest('.mb-3'), translations.password_match_error, 'password-match-error');
                    } else {
                        removeError('password-match-error');
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                } else {
                    button.classList.add('loading');
                    button.disabled = true;
                }
            });

            form.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('input', function() {
                    input.classList.remove('is-invalid');
                    const parentDiv = input.closest('.mb-3');
                    if (parentDiv) {
                        const errorMsg = parentDiv.querySelector('.field-error-text, .invalid-feedback');
                        if (errorMsg && !errorMsg.classList.contains('server-error')) {
                            // errorMsg.remove();
                        }
                    }
                    removeError('password-match-error');
                });
            });
        }

        if (profileForm && profileSubmitBtn) {
            handleFormSubmit(profileForm, profileSubmitBtn);
        }
        if (passwordForm && passwordSubmitBtn) {
            handleFormSubmit(passwordForm, passwordSubmitBtn);
        }

        const newPasswordInput = document.getElementById('new_password');
        const passwordStrengthDiv = document.getElementById('password-strength');
        const passwordStrengthInnerDiv = passwordStrengthDiv ? passwordStrengthDiv.querySelector('div') : null;
        const passwordFeedback = document.getElementById('password-feedback');

        function checkPasswordStrength(password) {
            let strength = 0;
            let feedbackMessages = [];
            if (!passwordStrengthInnerDiv) return;
            passwordStrengthDiv.className = 'password-strength mt-1';
            if (!password) {
                if (passwordFeedback) passwordFeedback.textContent = translations.password_feedback;
                return;
            }
            if (password.length >= 8) strength += 1; else feedbackMessages.push('<?php echo $language === 'ar' ? '8 أحرف على الأقل' : 'at least 8 characters'; ?>');
            if (/[A-Z]/.test(password)) strength += 1; else feedbackMessages.push('<?php echo $language === 'ar' ? 'حرف كبير' : 'an uppercase letter'; ?>');
            if (/[a-z]/.test(password)) strength += 1; else feedbackMessages.push('<?php echo $language === 'ar' ? 'حرف صغير' : 'a lowercase letter'; ?>');
            if (/\d/.test(password)) strength += 1; else feedbackMessages.push('<?php echo $language === 'ar' ? 'رقم' : 'a number'; ?>');
            if (/[^a-zA-Z\d]/.test(password)) strength += 1; else feedbackMessages.push('<?php echo $language === 'ar' ? 'حرف خاص' : 'a special character'; ?>');
            let strengthClass = '', feedbackText = '', feedbackColorClass = 'text-muted';
            if (strength <= 2) {
                strengthClass = 'strength-weak';
                feedbackText = translations.password_weak.replace('%s', feedbackMessages.join(', '));
                feedbackColorClass = 'text-danger';
            } else if (strength === 3) {
                strengthClass = 'strength-medium';
                feedbackText = translations.password_medium.replace('%s', feedbackMessages.join(', '));
                feedbackColorClass = 'text-warning';
            } else if (strength === 4) {
                strengthClass = 'strength-strong';
                feedbackText = translations.password_strong.replace('%s', feedbackMessages.join(', '));
                feedbackColorClass = 'text-info';
            } else if (strength >= 5) {
                strengthClass = 'strength-very-strong';
                feedbackText = translations.password_very_strong;
                feedbackColorClass = 'text-success';
            }
            passwordStrengthDiv.classList.add(strengthClass);
            if (passwordFeedback) {
                passwordFeedback.textContent = feedbackText;
                passwordFeedback.className = 'fs-13 mt-1 ' + feedbackColorClass;
            }
        }

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
            });
            checkPasswordStrength(newPasswordInput.value);
        }

        document.body.addEventListener('click', function(event) {
            const trigger = event.target.closest('[data-toggle-password-trigger="true"]');
            if (trigger) {
                const wrapper = trigger.closest('[data-toggle-password="true"]');
                const input = wrapper ? wrapper.querySelector('input[type="password"], input[type="text"]') : null;
                if (input) {
                    const isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');
                    wrapper.classList.toggle('toggle-password-active', isPassword);
                }
            }
        });

        function appendError(parentElement, message, errorId = null) {
            removeError(errorId);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback d-block field-error-text';
            if (errorId) errorDiv.id = errorId;
            errorDiv.textContent = message;
            parentElement.appendChild(errorDiv);
        }

        function removeError(errorId) {
            if (!errorId) return;
            const existingError = document.getElementById(errorId);
            if (existingError) {
                existingError.remove();
            }
        }
    });
    </script>
</body>
</html>