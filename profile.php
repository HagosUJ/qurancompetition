<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/profile.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

// --- Authentication & Session Management ---
if (!is_logged_in()) {
    set_flash_message("You must be logged in to view your profile.", 'error');
    redirect('sign-in.php');
    exit;
}

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
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png'; // Default avatar// Default avatar

// Variables for form feedback
$profile_error = '';
$profile_success = '';
$password_error = '';
$password_success = '';
$profile_field_error = '';
$password_field_error = '';

// Define upload directory and allowed types/size
define('PROFILE_PIC_UPLOAD_DIR', 'uploads/profile_pictures/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024); // 2MB

// Ensure upload directory exists and is writable
if (!is_dir(PROFILE_PIC_UPLOAD_DIR)) {
    mkdir(PROFILE_PIC_UPLOAD_DIR, 0775, true); // Create recursively with appropriate permissions
}
if (!is_writable(PROFILE_PIC_UPLOAD_DIR)) {
    error_log("Profile picture upload directory is not writable: " . PROFILE_PIC_UPLOAD_DIR);
    // Optionally set a persistent error message for admins
}


// --- Handle Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $profile_error = "Invalid request. Please try again.";
    } else {
        // Get and sanitize input
        $new_fullname = sanitize_input($_POST['fullname']);
        $new_email = sanitize_input($_POST['email']);
        $profile_pic_path = $user_profile_pic; // Keep current pic by default
        $update_needed = false;

        // Validate input
        if (empty($new_fullname)) {
            $profile_error = "Full name cannot be empty.";
            $profile_field_error = 'fullname';
        } elseif (empty($new_email)) {
            $profile_error = "Email cannot be empty.";
            $profile_field_error = 'email';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $profile_error = "Invalid email format.";
            $profile_field_error = 'email';
        } else {
            // Check if email is changing and if the new email is already taken
            if ($new_email !== $user_email) {
                $conn = connect_db();
                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt_check_email->bind_param("si", $new_email, $user_id);
                $stmt_check_email->execute();
                $stmt_check_email->store_result();
                if ($stmt_check_email->num_rows > 0) {
                    $profile_error = "This email address is already registered by another user.";
                    $profile_field_error = 'email';
                }
                $stmt_check_email->close();
                $conn->close();
                if (empty($profile_error)) $update_needed = true;
            }

            // Check if fullname changed
            if ($new_fullname !== $user_fullname) {
                $update_needed = true;
            }

            // --- Handle Profile Picture Upload ---
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];

                // Validate file type
                if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
                    $profile_error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                    $profile_field_error = 'profile_picture';
                }
                // Validate file size
                elseif ($file['size'] > MAX_IMAGE_SIZE) {
                    $profile_error = "File is too large. Maximum size is 2MB.";
                    $profile_field_error = 'profile_picture';
                } else {
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $unique_filename = uniqid('user_' . $user_id . '_', true) . '.' . strtolower($extension);
                    $destination = PROFILE_PIC_UPLOAD_DIR . $unique_filename;

                    // Attempt to move the file
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        // Delete old profile picture if it's not the default
                        if ($user_profile_pic !== 'assets/media/avatars/blank.png' && file_exists($user_profile_pic)) {
                            unlink($user_profile_pic);
                        }
                        $profile_pic_path = $destination; // Set new path for DB update
                        $update_needed = true;
                    } else {
                        $profile_error = "Failed to upload profile picture. Please try again.";
                        $profile_field_error = 'profile_picture';
                        error_log("Failed to move uploaded file to: " . $destination);
                    }
                }
            } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Handle other upload errors
                $profile_error = "Error uploading file. Code: " . $_FILES['profile_picture']['error'];
                $profile_field_error = 'profile_picture';
            }
            // --- End Profile Picture Upload ---


            // If no validation errors and changes were made or pic uploaded, proceed with update
            if (empty($profile_error) && $update_needed) {
                $conn = connect_db();
                $stmt_update = $conn->prepare("UPDATE users SET fullname = ?, email = ?, profile_picture = ? WHERE id = ?");
                $stmt_update->bind_param("sssi", $new_fullname, $new_email, $profile_pic_path, $user_id);

                if ($stmt_update->execute()) {
                    // Update session variables
                    $_SESSION['user_fullname'] = $new_fullname;
                    $_SESSION['user_email'] = $new_email;
                    $_SESSION['user_profile_pic'] = $profile_pic_path;
                    set_flash_message("Profile updated successfully.", 'success');
                } else {
                    error_log("Profile Update Error: " . $stmt_update->error);
                    set_flash_message("Failed to update profile. Please try again.", 'error');
                }
                $stmt_update->close();
                $conn->close();
                // Redirect to clear POST data and show flash message
                redirect('profile.php');
                exit;
            } elseif (empty($profile_error) && !$update_needed) {
                 $profile_error = "No changes detected."; // Inform user if nothing changed
            }
        }
        // If there was an error, update local variables to show submitted (but failed) values
        $user_fullname = $new_fullname;
        $user_email = $new_email;
        // Don't update $user_profile_pic here, keep the original one on error
    }
}

// --- Handle Password Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
     // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $password_error = "Invalid request. Please try again.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        // Validate input
        if (empty($current_password)) {
            $password_error = "Current password is required.";
            $password_field_error = 'current_password';
        } elseif (empty($new_password)) {
            $password_error = "New password is required.";
            $password_field_error = 'new_password';
        } elseif (!is_strong_password($new_password)) {
            $password_error = "New password does not meet complexity requirements.";
            $password_field_error = 'new_password';
        } elseif ($new_password !== $confirm_new_password) {
            $password_error = "New passwords do not match.";
            $password_field_error = 'confirm_new_password';
        } else {
            $conn = connect_db();
            // Verify current password
            $stmt_verify = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_verify->bind_param("i", $user_id);
            $stmt_verify->execute();
            $result = $stmt_verify->get_result();
            $user = $result->fetch_assoc();
            $stmt_verify->close();

            if ($user && password_verify($current_password, $user['password'])) {
                // Hash the new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the password in the database
                $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update_pass->bind_param("si", $new_password_hash, $user_id);

                if ($stmt_update_pass->execute()) {
                    set_flash_message("Password updated successfully.", 'success');
                    // Optionally: Force logout or update session state if needed
                } else {
                    error_log("Password Update Error: " . $stmt_update_pass->error);
                    set_flash_message("Failed to update password. Please try again.", 'error');
                }
                $stmt_update_pass->close();
            } else {
                $password_error = "Incorrect current password.";
                $password_field_error = 'current_password';
            }
            $conn->close();
            // Redirect to clear POST data and show flash message
            redirect('profile.php');
            exit;
        }
    }
}


// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:;"); // Added blob: for image preview
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile | Musabaqa</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <!-- Page specific styles -->
    <style>
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6; /* gray-300 */
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
        /* Ensure password toggle icons are visible */
        [data-toggle-password="true"] .btn-icon i { display: inline-block !important; }
        [data-toggle-password="true"] .btn-icon .toggle-password-active\:hidden { display: none !important; } /* Hide slash initially */
        [data-toggle-password="true"].toggle-password-active .btn-icon .toggle-password-active\:hidden { display: inline-block !important; } /* Show slash when active */
        [data-toggle-password="true"].toggle-password-active .btn-icon .hidden.toggle-password-active\:block { display: none !important; } /* Hide eye when active */

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

                    <!-- Page Title Row -->
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 class="page-title">My Profile</h4>
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Profile</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Flash Messages -->
                    <div id="flash-message-container">
                        <?php echo get_flash_message(); ?>
                    </div>

                    <div class="row">
                        <!-- Profile Update Column -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="header-title mb-4">Account Details</h4>

                                    <!-- Display Profile Update Errors (if not field specific) -->
                                    <?php if (!empty($profile_error) && empty($profile_field_error)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo htmlspecialchars($profile_error); ?>
                                        </div>
                                    <?php endif; ?>

                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="profile_form" enctype="multipart/form-data" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <!-- Profile Picture -->
                                        <div class="mb-3 text-center">
                                            <img src="<?php echo htmlspecialchars($user_profile_pic); ?>?t=<?php echo time(); ?>" alt="Profile Picture" id="profile_picture_preview" class="profile-picture-preview img-thumbnail">
                                            <div>
                                                <label for="profile_picture" class="form-label">Change Profile Picture</label>
                                                <input class="form-control <?php echo ($profile_field_error === 'profile_picture') ? 'is-invalid' : ''; ?>" type="file" id="profile_picture" name="profile_picture" accept="image/png, image/jpeg, image/gif">
                                                <?php if ($profile_field_error === 'profile_picture'): ?>
                                                    <div class="invalid-feedback d-block field-error-text"><?php echo htmlspecialchars($profile_error); ?></div>
                                                <?php endif; ?>
                                                <small class="form-text text-muted">Max 2MB. Allowed types: JPG, PNG, GIF.</small>
                                            </div>
                                        </div>

                                        <!-- Full Name -->
                                        <div class="mb-3">
                                            <label for="fullname" class="form-label">Full Name</label>
                                            <input type="text" id="fullname" name="fullname" class="form-control <?php echo ($profile_field_error === 'fullname') ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_fullname); ?>" required>
                                            <?php if ($profile_field_error === 'fullname'): ?>
                                                <div class="invalid-feedback field-error-text"><?php echo htmlspecialchars($profile_error); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Email -->
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" id="email" name="email" class="form-control <?php echo ($profile_field_error === 'email') ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_email); ?>" required>
                                            <?php if ($profile_field_error === 'email'): ?>
                                                <div class="invalid-feedback field-error-text"><?php echo htmlspecialchars($profile_error); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" name="update_profile" class="btn btn-primary" id="profile-submit-btn">
                                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                <span>Update Profile</span>
                                            </button>
                                        </div>
                                    </form>
                                </div> <!-- end card-body -->
                            </div> <!-- end card -->
                        </div> <!-- end col -->

                        <!-- Password Update Column -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="header-title mb-4">Change Password</h4>

                                    <!-- Display Password Update Errors (if not field specific) -->
                                    <?php if (!empty($password_error) && empty($password_field_error)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo htmlspecialchars($password_error); ?>
                                        </div>
                                    <?php endif; ?>

                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="password_form" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <!-- Current Password -->
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <div class="input-group input-group-merge <?php echo ($password_field_error === 'current_password') ? 'is-invalid' : ''; ?>" data-toggle-password="true">
                                                <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter current password" required>
                                                <button class="input-group-text btn-icon" data-toggle-password-trigger="true" type="button" tabindex="-1">
                                                    <i class="ri-eye-line hidden toggle-password-active:block"></i>
                                                    <i class="ri-eye-off-line toggle-password-active:hidden"></i>
                                                </button>
                                            </div>
                                            <?php if ($password_field_error === 'current_password'): ?>
                                                <div class="invalid-feedback d-block field-error-text"><?php echo htmlspecialchars($password_error); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- New Password -->
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <div class="input-group input-group-merge <?php echo ($password_field_error === 'new_password') ? 'is-invalid' : ''; ?>" data-toggle-password="true">
                                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter new password" required autocomplete="new-password">
                                                 <button class="input-group-text btn-icon" data-toggle-password-trigger="true" type="button" tabindex="-1">
                                                    <i class="ri-eye-line hidden toggle-password-active:block"></i>
                                                    <i class="ri-eye-off-line toggle-password-active:hidden"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength mt-1" id="password-strength"><div></div></div>
                                            <div class="text-muted fs-13 mt-1" id="password-feedback">Password should be at least 8 characters, include upper & lower case, a number, and a special character.</div>
                                            <?php if ($password_field_error === 'new_password'): ?>
                                                <div class="invalid-feedback d-block field-error-text"><?php echo htmlspecialchars($password_error); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Confirm New Password -->
                                        <div class="mb-3">
                                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                                            <div class="input-group input-group-merge <?php echo ($password_field_error === 'confirm_new_password') ? 'is-invalid' : ''; ?>" data-toggle-password="true">
                                                <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" placeholder="Confirm new password" required>
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
                                                <span>Change Password</span>
                                            </button>
                                        </div>
                                    </form>
                                </div> <!-- end card-body -->
                            </div> <!-- end card -->
                        </div> <!-- end col -->
                    </div> <!-- end row -->

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

    <!-- Page specific scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Profile Picture Preview ---
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
                    // Optionally reset preview or show error if invalid file type selected client-side
                    // profilePicPreview.src = '<?php echo htmlspecialchars($user_profile_pic); ?>'; // Reset to original
                    alert('Please select a valid image file (JPG, PNG, GIF).');
                    profilePicInput.value = ''; // Clear the invalid selection
                }
            });
        }

        // --- Form Validation & Loading States ---
        const profileForm = document.getElementById('profile_form');
        const profileSubmitBtn = document.getElementById('profile-submit-btn');
        const passwordForm = document.getElementById('password_form');
        const passwordSubmitBtn = document.getElementById('password-submit-btn');

        function handleFormSubmit(form, button) {
            form.addEventListener('submit', function(e) {
                // Basic client-side check for required fields (server-side is primary)
                let isValid = true;
                form.querySelectorAll('[required]').forEach(input => {
                    if (!input.value.trim() && input.type !== 'file') { // Don't check file input value directly
                        isValid = false;
                        input.classList.add('is-invalid');
                        // You could add a generic client-side error message here if needed
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                // Specific validation for password match
                if (form.id === 'password_form') {
                    const newPass = document.getElementById('new_password');
                    const confirmPass = document.getElementById('confirm_new_password');
                    if (newPass.value && confirmPass.value && newPass.value !== confirmPass.value) {
                        isValid = false;
                        confirmPass.classList.add('is-invalid');
                        appendError(confirmPass.closest('.mb-3'), 'New passwords do not match', 'password-match-error');
                    } else {
                         removeError('password-match-error');
                    }
                    // Add client-side strength check if desired
                }


                if (!isValid) {
                    e.preventDefault(); // Stop submission
                } else {
                    button.classList.add('loading'); // Show loading state
                    button.disabled = true; // Prevent multiple clicks
                }
            });

             // Remove validation errors on input
            form.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('input', function() {
                    input.classList.remove('is-invalid');
                    // Remove specific error messages if needed
                    const parentDiv = input.closest('.mb-3');
                    if (parentDiv) {
                        const errorMsg = parentDiv.querySelector('.field-error-text, .invalid-feedback');
                        if (errorMsg && !errorMsg.classList.contains('server-error')) { // Keep server errors
                           // errorMsg.remove(); // Or just hide it
                        }
                    }
                     removeError('password-match-error'); // Clear password match error on input
                });
            });
        }

        if (profileForm && profileSubmitBtn) {
            handleFormSubmit(profileForm, profileSubmitBtn);
        }
        if (passwordForm && passwordSubmitBtn) {
            handleFormSubmit(passwordForm, passwordSubmitBtn);
        }


        // --- Password Strength Indicator ---
        const newPasswordInput = document.getElementById('new_password');
        const passwordStrengthDiv = document.getElementById('password-strength');
        const passwordStrengthInnerDiv = passwordStrengthDiv ? passwordStrengthDiv.querySelector('div') : null;
        const passwordFeedback = document.getElementById('password-feedback');

        function checkPasswordStrength(password) {
            // (Same strength checking logic as before)
            let strength = 0; let feedbackMessages = [];
            if (!passwordStrengthInnerDiv) return; // Exit if elements not found
            passwordStrengthInnerDiv.style.width = '0%'; passwordStrengthDiv.className = 'password-strength mt-1';
            if (!password) { if(passwordFeedback) passwordFeedback.textContent = 'Password should be at least 8 characters, include upper & lower case, a number, and a special character.'; return; }
            if (password.length >= 8) strength += 1; else feedbackMessages.push('at least 8 characters');
            if (/[A-Z]/.test(password)) strength += 1; else feedbackMessages.push('an uppercase letter');
            if (/[a-z]/.test(password)) strength += 1; else feedbackMessages.push('a lowercase letter');
            if (/\d/.test(password)) strength += 1; else feedbackMessages.push('a number');
            if (/[^a-zA-Z\d]/.test(password)) strength += 1; else feedbackMessages.push('a special character');
            let strengthClass = '', feedbackText = '', feedbackColorClass = 'text-muted';
            if (strength <= 2) { strengthClass = 'strength-weak'; feedbackText = 'Weak. Needs: ' + feedbackMessages.join(', ') + '.'; feedbackColorClass = 'text-danger'; }
            else if (strength === 3) { strengthClass = 'strength-medium'; feedbackText = 'Medium. Needs: ' + feedbackMessages.join(', ') + '.'; feedbackColorClass = 'text-warning'; }
            else if (strength === 4) { strengthClass = 'strength-strong'; feedbackText = 'Strong. Consider adding: ' + feedbackMessages.join(', ') + '.'; feedbackColorClass = 'text-info'; }
            else if (strength >= 5) { strengthClass = 'strength-very-strong'; feedbackText = 'Very Strong!'; feedbackColorClass = 'text-success'; }
            passwordStrengthDiv.classList.add(strengthClass);
            if(passwordFeedback) {
                passwordFeedback.textContent = feedbackText;
                passwordFeedback.className = 'fs-13 mt-1 ' + feedbackColorClass;
            }
        }

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
            });
             checkPasswordStrength(newPasswordInput.value); // Initial check in case field is pre-filled
        }

        // --- Password Visibility Toggle ---
        // Using event delegation for potentially multiple toggles
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

        // Helper to add client-side error messages
        function appendError(parentElement, message, errorId = null) {
            removeError(errorId); // Remove existing error with the same ID first
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback d-block field-error-text'; // Use Bootstrap classes
            if (errorId) errorDiv.id = errorId;
            errorDiv.textContent = message;
            parentElement.appendChild(errorDiv);
        }

        // Helper to remove client-side error messages by ID
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