<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/portal/profile.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

// --- Authentication ---
require_login();
$language = $_SESSION['language'] ?? 'en'; // Default to English
$is_rtl = ($language === 'ar');

// Session timeout check
$timeout_duration = SESSION_TIMEOUT_DURATION ?? 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    logout_user('sign-in.php?reason=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// Get user details
$user_id = (int)$_SESSION['user_id'];
$user_fullname = $_SESSION['fullname'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_role = $_SESSION['role'] ?? 'user';
$profile_picture = $_SESSION['profile_picture'] ?? 'assets/media/avatars/blank.png';

// --- Language-Specific Strings ---
$translations = [
    'en' => [
        'page_title' => 'My Profile | Musabaqa',
        'profile' => 'My Profile',
        'personal_info' => 'Personal Information',
        'full_name' => 'Full Name',
        'email' => 'Email Address',
        'update_info' => 'Update Information',
        'change_password' => 'Change Password',
        'current_password' => 'Current Password',
        'new_password' => 'New Password',
        'confirm_new_password' => 'Confirm New Password',
        'update_password' => 'Update Password',
        'profile_picture' => 'Profile Picture',
        'upload_picture' => 'Upload New Picture',
        'save_picture' => 'Save Picture',
        'success_update_info' => 'Personal information updated successfully.',
        'error_update_info' => 'Failed to update personal information. Please try again.',
        'error_email_exists' => 'This email address is already in use by another account.',
        'error_invalid_email' => 'Please enter a valid email address.',
        'success_password' => 'Password updated successfully.',
        'error_password_current' => 'Current password is incorrect.',
        'error_password_mismatch' => 'New passwords do not match.',
        'error_password_length' => 'New password must be at least 8 characters long.',
        'error_password_update' => 'Failed to update password. Please try again.',
        'success_picture' => 'Profile picture updated successfully.',
        'error_picture_type' => 'Only JPG, JPEG, PNG, or GIF files are allowed.',
        'error_picture_size' => 'Profile picture must be less than 2MB.',
        'error_picture_upload' => 'Failed to upload profile picture. Please try again.',
        'select_language' => 'Select Language',
        'english' => 'English',
        'arabic' => 'Arabic',
        'refresh' => 'Refresh',
    ],
    'ar' => [
        'page_title' => 'ملفي الشخصي | المسابقة',
        'profile' => 'ملفي الشخصي',
        'personal_info' => 'المعلومات الشخصية',
        'full_name' => 'الاسم الكامل',
        'email' => 'عنوان البريد الإلكتروني',
        'update_info' => 'تحديث المعلومات',
        'change_password' => 'تغيير كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'new_password' => 'كلمة المرور الجديدة',
        'confirm_new_password' => 'تأكيد كلمة المرور الجديدة',
        'update_password' => 'تحديث كلمة المرور',
        'profile_picture' => 'الصورة الشخصية',
        'upload_picture' => 'رفع صورة جديدة',
        'save_picture' => 'حفظ الصورة',
        'success_update_info' => 'تم تحديث المعلومات الشخصية بنجاح.',
        'error_update_info' => 'فشل تحديث المعلومات الشخصية. حاول مرة أخرى.',
        'error_email_exists' => 'عنوان البريد الإلكتروني مستخدم بالفعل بواسطة حساب آخر.',
        'error_invalid_email' => 'الرجاء إدخال عنوان بريد إلكتروني صالح.',
        'success_password' => 'تم تحديث كلمة المرور بنجاح.',
        'error_password_current' => 'كلمة المرور الحالية غير صحيحة.',
        'error_password_mismatch' => 'كلمات المرور الجديدة غير متطابقة.',
        'error_password_length' => 'يجب أن تكون كلمة المرور الجديدة 8 أحرف على الأقل.',
        'error_password_update' => 'فشل تحديث كلمة المرور. حاول مرة أخرى.',
        'success_picture' => 'تم تحديث الصورة الشخصية بنجاح.',
        'error_picture_type' => 'يُسمح فقط بملفات JPG، JPEG، PNG، أو GIF.',
        'error_picture_size' => 'يجب أن تكون الصورة الشخصية أقل من 2 ميغابايت.',
        'error_picture_upload' => 'فشل رفع الصورة الشخصية. حاول مرة أخرى.',
        'select_language' => 'اختر اللغة',
        'english' => 'الإنجليزية',
        'arabic' => 'العربية',
        'refresh' => 'تحديث',
    ]
];

// --- Handle Form Submissions ---

// Update Personal Information
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $new_fullname = sanitize_input($_POST['fullname']);
    $new_email = sanitize_input($_POST['email']);

    // Validate inputs
    if (empty($new_fullname) || empty($new_email)) {
        set_flash_message($translations[$language]['error_update_info'], 'error');
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message($translations[$language]['error_invalid_email'], 'error');
    } else {
        // Check if email is already in use by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            set_flash_message($translations[$language]['error_email_exists'], 'error');
        } else {
            // Update user information
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_fullname, $new_email, $user_id);
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['fullname'] = $new_fullname;
                $_SESSION['email'] = $new_email;
                set_flash_message($translations[$language]['success_update_info'], 'success');
            } else {
                set_flash_message($translations[$language]['error_update_info'], 'error');
                error_log("Failed to update user info for User ID {$user_id}: " . $conn->error);
            }
        }
        $stmt->close();
    }
    redirect('profile.php');
}

// Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_new_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        set_flash_message($translations[$language]['error_password_update'], 'error');
    } elseif ($new_password !== $confirm_password) {
        set_flash_message($translations[$language]['error_password_mismatch'], 'error');
    } elseif (strlen($new_password) < 8) {
        set_flash_message($translations[$language]['error_password_length'], 'error');
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (verify_password($current_password, $user['password'], $user_id)) {
            // Update password
            if (update_password($user_id, $new_password)) {
                set_flash_message($translations[$language]['success_password'], 'success');
            } else {
                set_flash_message($translations[$language]['error_password_update'], 'error');
                error_log("Failed to update password for User ID {$user_id}.");
            }
        } else {
            set_flash_message($translations[$language]['error_password_current'], 'error');
        }
    }
    redirect('profile.php');
}

// Upload Profile Picture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture']) && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    $upload_dir = 'uploads/avatars/';
    $filename = $user_id . '_' . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $file['name']);
    $filepath = $upload_dir . $filename;

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash_message($translations[$language]['error_picture_upload'], 'error');
    } elseif (!in_array($file['type'], $allowed_types)) {
        set_flash_message($translations[$language]['error_picture_type'], 'error');
    } elseif ($file['size'] > $max_size) {
        set_flash_message($translations[$language]['error_picture_size'], 'error');
    } else {
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Update database
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $filepath, $user_id);
            if ($stmt->execute()) {
                // Update session
                $_SESSION['profile_picture'] = $filepath;
                set_flash_message($translations[$language]['success_picture'], 'success');
            } else {
                set_flash_message($translations[$language]['error_picture_upload'], 'error');
                error_log("Failed to update profile picture for User ID {$user_id}: " . $conn->error);
                // Delete the uploaded file if DB update fails
                unlink($filepath);
            }
            $stmt->close();
        } else {
            set_flash_message($translations[$language]['error_picture_upload'], 'error');
            error_log("Failed to move uploaded profile picture for User ID {$user_id}.");
        }
    }
    redirect('profile.php');
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; form-action 'self';");
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
<head>
    <title><?php echo $translations[$language]['page_title']; ?></title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .profile-picture-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #e9ecef;
        }
        .btn-custom-blue {
            background-color: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out;
        }
        .btn-custom-blue:hover {
            background-color: #2563eb;
        }
        .language-switcher {
            min-width: 120px;
        }
        .alert-dismissible .btn-close {
            padding: 0.8rem;
        }
        @media (max-width: 576px) {
            .profile-picture-img {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <?php include 'layouts/menu.php'; ?>

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->

        <div class="content-page">
            <div class="content">

                <!-- Start Content-->
                <div class="container-fluid">

                    <!-- Page Title -->
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 class="page-title"><?php echo $translations[$language]['profile']; ?></h4>
                                <div class="page-title-right d-flex align-items-center">
                                    <!-- Language Switcher -->
                                    <form method="POST" class="me-2">
                                        <select name="language" class="form-select language-switcher" onchange="this.form.submit()">
                                            <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>><?php echo $translations[$language]['english']; ?></option>
                                            <option value="ar" <?php echo $language === 'ar' ? 'selected' : ''; ?>><?php echo $translations[$language]['arabic']; ?></option>
                                        </select>
                                    </form>
                                    <!-- Refresh Button -->
                                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn-custom-blue">
                                        <i class="ri-refresh-line"></i> <?php echo $translations[$language]['refresh']; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Display Flash Messages -->
                    <?php
                    if (isset($_SESSION['flash_message'])) {
                        // Handle both array and string cases
                        if (is_array($_SESSION['flash_message']) && isset($_SESSION['flash_message']['message'], $_SESSION['flash_message']['type'])) {
                            $message = htmlspecialchars($_SESSION['flash_message']['message']);
                            $type = $_SESSION['flash_message']['type'] === 'success' ? 'success' : 'danger';
                        } else {
                            // Fallback for when flash_message is a string
                            $message = htmlspecialchars((string)$_SESSION['flash_message']);
                            $type = 'info'; // Default to info for legacy string messages
                        }
                        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                                {$message}
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                              </div>";
                        unset($_SESSION['flash_message']);
                    }
                    ?>

                    <!-- Profile Content -->
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $translations[$language]['personal_info']; ?></h5>
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="fullname" class="form-label"><?php echo $translations[$language]['full_name']; ?></label>
                                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user_fullname); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label"><?php echo $translations[$language]['email']; ?></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                                        </div>
                                        <button type="submit" name="update_info" class="btn btn-custom-blue"><?php echo $translations[$language]['update_info']; ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $translations[$language]['change_password']; ?></h5>
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label"><?php echo $translations[$language]['current_password']; ?></label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label"><?php echo $translations[$language]['new_password']; ?></label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_new_password" class="form-label"><?php echo $translations[$language]['confirm_new_password']; ?></label>
                                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                                        </div>
                                        <button type="submit" name="change_password" class="btn btn-custom-blue"><?php echo $translations[$language]['update_password']; ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Picture -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $translations[$language]['profile_picture']; ?></h5>
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-picture-img me-3">
                                        <div>
                                            <p class="mb-0 text-muted"><?php echo $translations[$language]['upload_picture']; ?></p>
                                        </div>
                                    </div>
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
                                        </div>
                                        <button type="submit" name="upload_picture" class="btn btn-custom-blue"><?php echo $translations[$language]['save_picture']; ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- container-fluid -->
            </div> <!-- content -->

            <?php include 'layouts/footer.php'; ?>

        </div>
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

    </div>
    <!-- END wrapper -->

    <?php include 'layouts/right-sidebar.php'; ?>
    <?php include 'layouts/footer-scripts.php'; ?>
    <script src="assets/js/app.min.js"></script>
</body>
</html>