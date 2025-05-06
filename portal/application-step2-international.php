<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step2-international.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely
require_once 'includes/countries.php'; // Include the country list helper

// --- Authentication & Session Management ---
if (!is_logged_in()) {
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

// Get user details
$user_id = $_SESSION['user_id'] ?? null;
$user_fullname = $_SESSION['user_fullname'] ?? 'Participant';
if (!$user_id) {
    error_log("User ID missing from session for logged-in user on application-step2-international.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png'; // Default avatar

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Language-Specific Strings ---
$translations = [
    'en' => [
        'page_title' => 'Application: Step 2 - Nominator Details (International) | Musabaqa',
        'page_header' => 'Application - Step 2: Nominator Details (International)',
        'dashboard' => 'Dashboard',
        'application' => 'Application',
        'step_2' => 'Step 2',
        'welcome_message' => 'Please provide details about the organization or individual nominating/sponsoring you.',
        'nominator_information' => 'Nominator/Sponsor Information',
        'nomination_letter' => 'Letter of Nomination/Sponsorship',
        'nominator_type' => 'Nominator/Sponsor Type',
        'nominator_type_required' => 'Nominator/Sponsor Type is required.',
        'select_type' => '-- Select Type --',
        'nominator_type_organization' => 'Organization / Institution',
        'nominator_type_individual' => 'Individual',
        'nominator_name' => 'Nominator/Sponsor Name',
        'nominator_name_required' => 'Nominator/Sponsor Name is required.',
        'nominator_name_placeholder' => 'Full name of person or organization',
        'nominator_address' => 'Address',
        'nominator_city' => 'City',
        'nominator_country' => 'Country',
        'select_country' => '-- Select Country --',
        'nominator_phone' => 'Phone Number',
        'nominator_phone_placeholder' => 'e.g., +1 212 555 1234',
        'nominator_email' => 'Email Address',
        'nominator_email_invalid' => 'Invalid Email format.',
        'nominator_email_placeholder' => 'nominator@example.com',
        'relationship' => 'Relationship to Contestant (Optional)',
        'relationship_placeholder' => 'e.g., Teacher, Imam, Organization Representative',
        'upload_letter' => 'Upload Letter',
        'letter_instructions' => 'Upload the official nomination or sponsorship letter (PDF, DOC, DOCX, JPG, PNG, max 5MB).',
        'letter_invalid_type' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG.',
        'letter_size_exceeded' => 'File size exceeds the limit (5MB).',
        'letter_upload_failed' => 'Failed to upload document.',
        'letter_upload_error' => 'Error uploading document: Code %s',
        'current_letter_uploaded' => 'Current letter uploaded:',
        'view_letter' => 'View Letter',
        'back_to_step_1' => 'Back to Step 1',
        'save_continue' => 'Save and Continue to Step 3',
        'error_invalid_submission' => 'Invalid form submission. Please try again.',
        'error_load_details' => 'Could not load existing application details. Please try again later.',
        'error_save' => 'An error occurred while saving your information. Please check your inputs and try again.',
        'success_save' => 'Nominator information saved successfully.',
        'error_app_not_found' => 'Application not found or type mismatch.',
        'error_verify_app' => 'Error verifying application status. Please try again later.',
        'error_step_sequence' => 'Please complete the previous step first.',
    ],
    'ar' => [
        'page_title' => 'الطلب: الخطوة الثانية - تفاصيل المرشح (دولي) | المسابقة',
        'page_header' => 'الطلب - الخطوة الثانية: تفاصيل المرشح (دولي)',
        'dashboard' => 'لوحة التحكم',
        'application' => 'الطلب',
        'step_2' => 'الخطوة الثانية',
        'welcome_message' => 'يرجى تقديم تفاصيل المنظمة أو الفرد الذي يرشحك/يكفلك.',
        'nominator_information' => 'معلومات المرشح/الكفيل',
        'nomination_letter' => 'خطاب الترشيح/الكفالة',
        'nominator_type' => 'نوع المرشح/الكفيل',
        'nominator_type_required' => 'نوع المرشح/الكفيل مطلوب.',
        'select_type' => '-- اختر النوع --',
        'nominator_type_organization' => 'منظمة / مؤسسة',
        'nominator_type_individual' => 'فرد',
        'nominator_name' => 'اسم المرشح/الكفيل',
        'nominator_name_required' => 'اسم المرشح/الكفيل مطلوب.',
        'nominator_name_placeholder' => 'الاسم الكامل للشخص أو المنظمة',
        'nominator_address' => 'العنوان',
        'nominator_city' => 'المدينة',
        'nominator_country' => 'البلد',
        'select_country' => '-- اختر البلد --',
        'nominator_phone' => 'رقم الهاتف',
        'nominator_phone_placeholder' => 'مثال: +1 212 555 1234',
        'nominator_email' => 'عنوان البريد الإلكتروني',
        'nominator_email_invalid' => 'تنسيق البريد الإلكتروني غير صالح.',
        'nominator_email_placeholder' => 'nominator@example.com',
        'relationship' => 'العلاقة بالمتسابق (اختياري)',
        'relationship_placeholder' => 'مثال: معلم، إمام، ممثل المنظمة',
        'upload_letter' => 'رفع الخطاب',
        'letter_instructions' => 'ارفع خطاب الترشيح أو الكفالة الرسمي (PDF، DOC، DOCX، JPG، PNG، بحد أقصى 5 ميجابايت).',
        'letter_invalid_type' => 'نوع الملف غير صالح. المسموح: PDF، DOC، DOCX، JPG، JPEG، PNG.',
        'letter_size_exceeded' => 'حجم الملف يتجاوز الحد (5 ميجابايت).',
        'letter_upload_failed' => 'فشل في رفع المستند.',
        'letter_upload_error' => 'خطأ في رفع المستند: الكود %s',
        'current_letter_uploaded' => 'الخطاب الحالي تم رفعه:',
        'view_letter' => 'عرض الخطاب',
        'back_to_step_1' => 'العودة إلى الخطوة الأولى',
        'save_continue' => 'حفظ ومتابعة إلى الخطوة الثالثة',
        'error_invalid_submission' => 'إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'error_load_details' => 'تعذر تحميل تفاصيل الطلب الحالية. يرجى المحاولة مرة أخرى لاحقًا.',
        'error_save' => 'حدث خطأ أثناء حفظ المعلومات. يرجى التحقق من مدخلاتك والمحاولة مرة أخرى.',
        'success_save' => 'تم حفظ معلومات المرشح بنجاح.',
        'error_app_not_found' => 'الطلب غير موجود أو هناك عدم تطابق في النوع.',
        'error_verify_app' => 'خطأ في التحقق من حالة الطلب. يرجى المحاولة مرة أخرى لاحقًا.',
        'error_step_sequence' => 'يرجى إكمال الخطوة السابقة أولاً.',
    ]
];

// --- Application Verification ---
global $conn;
$application_id = null;
$application_data = []; // To store existing nominator data
$application_status = 'Not Started';
$current_step = '';

$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'international'");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        $application_status = $app['status'];
        $current_step = $app['current_step'];

        // --- Step Access Control ---
        if ($current_step !== 'step2' && $application_status !== 'Personal Info Complete') {
            $redirect_target = ($current_step && $current_step !== 'step2') ? 'application-' . $current_step . '-international.php' : 'application.php';
            redirect($redirect_target . '?error=step_sequence');
            exit;
        }

        // Fetch existing nominator details for this application step
        $stmt_details = $conn->prepare("SELECT * FROM application_nominators_international WHERE application_id = ?");
        if ($stmt_details) {
            $stmt_details->bind_param("i", $application_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                $application_data = $result_details->fetch_assoc();
            }
            $stmt_details->close();
        } else {
            error_log("Failed to prepare statement for fetching International Nominator details: " . $conn->error);
            $errors['form'] = $translations[$language]['error_load_details'];
        }
    } else {
        redirect('application.php?error=app_not_found_or_mismatch');
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die($translations[$language]['error_verify_app']);
}

// --- Define profile picture for topbar ---
$profile_picture = $_SESSION['user_profile_picture'] ?? 'assets/images/users/avatar-1.jpg';

// --- Form Processing ---
$errors = [];
$success = '';
$upload_dir = 'Uploads/nominations/';
$allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = $translations[$language]['error_invalid_submission'];
    } else {
        // Sanitize and retrieve POST data
        $nominator_type = sanitize_input($_POST['nominator_type'] ?? '');
        $nominator_name = sanitize_input($_POST['nominator_name'] ?? '');
        $nominator_address = sanitize_input($_POST['nominator_address'] ?? '');
        $nominator_city = sanitize_input($_POST['nominator_city'] ?? '');
        $nominator_country = sanitize_input($_POST['nominator_country'] ?? '');
        $nominator_phone = sanitize_input($_POST['nominator_phone'] ?? '');
        $nominator_email = filter_input(INPUT_POST, 'nominator_email', FILTER_VALIDATE_EMAIL);
        $relationship = sanitize_input($_POST['relationship'] ?? '');

        // --- Validation ---
        if (empty($nominator_type) || !in_array($nominator_type, ['Organization', 'Individual'])) $errors['nominator_type'] = $translations[$language]['nominator_type_required'];
        if (empty($nominator_name)) $errors['nominator_name'] = $translations[$language]['nominator_name_required'];
        if ($nominator_email === false && !empty($_POST['nominator_email'])) $errors['nominator_email'] = $translations[$language]['nominator_email_invalid'];

        // --- File Upload Handling ---
        $nomination_letter_path = $application_data['nomination_letter_path'] ?? null;
        $new_file_uploaded = false;

        if (isset($_FILES['nomination_letter']) && $_FILES['nomination_letter']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['nomination_letter']['tmp_name'];
            $file_name = $_FILES['nomination_letter']['name'];
            $file_size = $_FILES['nomination_letter']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                $errors['nomination_letter'] = $translations[$language]['letter_invalid_type'];
            } elseif ($file_size > $max_file_size) {
                $errors['nomination_letter'] = $translations[$language]['letter_size_exceeded'];
            } else {
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $errors['nomination_letter'] = $translations[$language]['letter_upload_failed'];
                        goto skip_file_move_nomination_intl;
                    }
                }
                $safe_basename = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($file_name));
                $unique_filename = "app_{$application_id}_nomination_" . uniqid() . '_' . $safe_basename;
                $destination = $upload_dir . $unique_filename;

                if (move_uploaded_file($file_tmp_path, $destination)) {
                    $old_letter_path = $application_data['nomination_letter_path'] ?? null;
                    if ($old_letter_path && file_exists($old_letter_path) && $old_letter_path !== $destination) {
                        @unlink($old_letter_path);
                    }
                    $nomination_letter_path = $destination;
                    $new_file_uploaded = true;
                } else {
                    error_log("move_uploaded_file failed for nomination letter: From '{$file_tmp_path}' to '{$destination}' for app {$application_id}");
                    $errors['nomination_letter'] = $translations[$language]['letter_upload_failed'];
                }
            }
        } elseif (isset($_FILES['nomination_letter']) && $_FILES['nomination_letter']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors['nomination_letter'] = sprintf($translations[$language]['letter_upload_error'], $_FILES['nomination_letter']['error']);
        }

        skip_file_move_nomination_intl:

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                $stmt_check = $conn->prepare("SELECT id FROM application_nominators_international WHERE application_id = ?");
                if (!$stmt_check) throw new Exception("Prepare failed (Check Nominator): " . $conn->error);
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    $sql = "UPDATE application_nominators_international SET
                                nominator_type = ?, nominator_name = ?, nominator_address = ?, nominator_city = ?,
                                nominator_country = ?, nominator_phone = ?, nominator_email = ?, relationship = ?,
                                nomination_letter_path = ?, updated_at = NOW()
                            WHERE application_id = ?";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (UPDATE Nominator): " . $conn->error);
                    $stmt_save->bind_param("sssssssssi",
                        $nominator_type, $nominator_name, $nominator_address, $nominator_city,
                        $nominator_country, $nominator_phone, $nominator_email, $relationship,
                        $nomination_letter_path, $application_id);
                } else {
                    $sql = "INSERT INTO application_nominators_international
                                (application_id, nominator_type, nominator_name, nominator_address, nominator_city,
                                 nominator_country, nominator_phone, nominator_email, relationship, nomination_letter_path)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (INSERT Nominator): " . $conn->error);
                    $stmt_save->bind_param("isssssssss",
                        $application_id, $nominator_type, $nominator_name, $nominator_address, $nominator_city,
                        $nominator_country, $nominator_phone, $nominator_email, $relationship, $nomination_letter_path);
                }

                if (!$stmt_save->execute()) {
                    throw new Exception("Execute failed (Save Nominator): " . $stmt_save->error);
                }
                $stmt_save->close();

                if ($application_status === 'Personal Info Complete') {
                    $new_status = 'Nominator Info Complete';
                    $next_step = 'step3';
                    $stmt_update_app = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ?");
                    if (!$stmt_update_app) throw new Exception("Prepare failed (App Update): " . $conn->error);
                    $stmt_update_app->bind_param("ssi", $new_status, $next_step, $application_id);
                    if (!$stmt_update_app->execute()) {
                        throw new Exception("Execute failed (App Update): " . $stmt_update_app->error);
                    }
                    $stmt_update_app->close();
                } else {
                    $stmt_update_time = $conn->prepare("UPDATE applications SET last_updated = NOW() WHERE id = ?");
                    if (!$stmt_update_time) throw new Exception("Prepare failed (App Time Update): " . $conn->error);
                    $stmt_update_time->bind_param("i", $application_id);
                    $stmt_update_time->execute();
                    $stmt_update_time->close();
                }

                $conn->commit();
                $success = $translations[$language]['success_save'];

                $application_data = [
                    'nominator_type' => $nominator_type, 'nominator_name' => $nominator_name,
                    'nominator_address' => $nominator_address, 'nominator_city' => $nominator_city,
                    'nominator_country' => $nominator_country, 'nominator_phone' => $nominator_phone,
                    'nominator_email' => $nominator_email, 'relationship' => $relationship,
                    'nomination_letter_path' => $nomination_letter_path
                ];

                redirect('application-step3-international.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving International application step 2 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = $translations[$language]['error_save'] . " Details: " . htmlspecialchars($e->getMessage());
                if ($new_file_uploaded && isset($destination) && file_exists($destination)) {
                    @unlink($destination);
                }
            }
        }
    }
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; frame-src 'none';");
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
        .form-control.is-invalid, .form-select.is-invalid { border-color: #dc3545; }
        .invalid-feedback { display: block; color: #dc3545; font-size: 0.875em; }
        .form-label { font-weight: 500; }
        .progress-bar { background-color: #0acf97; }
        .step-indicator { margin-bottom: 1.5rem; }
        .file-upload-info { font-size: 0.9em; margin-top: 5px; }
        .existing-file-link { font-size: 0.9em; }
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
                                <h4 class="page-title"><?php echo $translations[$language]['page_header']; ?></h4>
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php"><?php echo $translations[$language]['dashboard']; ?></a></li>
                                        <li class="breadcrumb-item"><a href="application.php"><?php echo $translations[$language]['application']; ?></a></li>
                                        <li class="breadcrumb-item active"><?php echo $translations[$language]['step_2']; ?></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step Indicator -->
                    <div class="row step-indicator">
                        <div class="col-12">
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">Step 2 of 4</div>
                            </div>
                        </div>
                    </div>

                    <!-- Welcome Message -->
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-secondary bg-secondary text-white border-0" role="alert">
                                <?php echo $translations[$language]['welcome_message']; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Display Messages -->
                    <?php if (!empty($errors['form'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="ri-close-circle-line me-1"></i> <?php echo htmlspecialchars($errors['form']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="ri-check-line me-1"></i> <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Nominator/Sponsor Form -->
                    <div class="row">
                        <div class="col-12">
                            <form id="step2-form-intl" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- Nominator Details Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-shield-user-line me-1"></i><?php echo $translations[$language]['nominator_information']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Nominator Type -->
                                            <div class="col-md-6 mb-3">
                                                <label for="nominator_type" class="form-label"><?php echo $translations[$language]['nominator_type']; ?> <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['nominator_type']) ? 'is-invalid' : ''; ?>" id="nominator_type" name="nominator_type" required>
                                                    <option value="" disabled <?php echo empty($application_data['nominator_type']) ? 'selected' : ''; ?>><?php echo $translations[$language]['select_type']; ?></option>
                                                    <option value="Organization" <?php echo (($application_data['nominator_type'] ?? '') === 'Organization') ? 'selected' : ''; ?>><?php echo $translations[$language]['nominator_type_organization']; ?></option>
                                                    <option value="Individual" <?php echo (($application_data['nominator_type'] ?? '') === 'Individual') ? 'selected' : ''; ?>><?php echo $translations[$language]['nominator_type_individual']; ?></option>
                                                </select>
                                                <?php if (isset($errors['nominator_type'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_type']); ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nominator Name -->
                                            <div class="col-md-6 mb-3">
                                                <label for="nominator_name" class="form-label"><?php echo $translations[$language]['nominator_name']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['nominator_name']) ? 'is-invalid' : ''; ?>" id="nominator_name" name="nominator_name" value="<?php echo htmlspecialchars($application_data['nominator_name'] ?? ''); ?>" required placeholder="<?php echo $translations[$language]['nominator_name_placeholder']; ?>">
                                                <?php if (isset($errors['nominator_name'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_name']); ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Nominator Address -->
                                            <div class="col-md-8 mb-3">
                                                <label for="nominator_address" class="form-label"><?php echo $translations[$language]['nominator_address']; ?></label>
                                                <textarea class="form-control <?php echo isset($errors['nominator_address']) ? 'is-invalid' : ''; ?>" id="nominator_address" name="nominator_address" rows="2"><?php echo htmlspecialchars($application_data['nominator_address'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['nominator_address'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_address']); ?></div><?php endif; ?>
                                            </div>
                                            <!-- Nominator City -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_city" class="form-label"><?php echo $translations[$language]['nominator_city']; ?></label>
                                                <input type="text" class="form-control <?php echo isset($errors['nominator_city']) ? 'is-invalid' : ''; ?>" id="nominator_city" name="nominator_city" value="<?php echo htmlspecialchars($application_data['nominator_city'] ?? ''); ?>">
                                                <?php if (isset($errors['nominator_city'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_city']); ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Nominator Country -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_country" class="form-label"><?php echo $translations[$language]['nominator_country']; ?></label>
                                                <select class="form-select <?php echo isset($errors['nominator_country']) ? 'is-invalid' : ''; ?>" id="nominator_country" name="nominator_country">
                                                    <option value="" <?php echo empty($application_data['nominator_country']) ? 'selected' : ''; ?>><?php echo $translations[$language]['select_country']; ?></option>
                                                    <?php
                                                        $countries = get_countries();
                                                        $selected_n_country = $application_data['nominator_country'] ?? ($_POST['nominator_country'] ?? '');
                                                        foreach ($countries as $code => $name) {
                                                            $selected = ($name === $selected_n_country) ? 'selected' : '';
                                                            echo "<option value=\"" . htmlspecialchars($name) . "\" $selected>" . htmlspecialchars($name) . "</option>";
                                                        }
                                                    ?>
                                                </select>
                                                <?php if (isset($errors['nominator_country'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_country']); ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nominator Phone -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_phone" class="form-label"><?php echo $translations[$language]['nominator_phone']; ?></label>
                                                <input type="tel" class="form-control <?php echo isset($errors['nominator_phone']) ? 'is-invalid' : ''; ?>" id="nominator_phone" name="nominator_phone" value="<?php echo htmlspecialchars($application_data['nominator_phone'] ?? ''); ?>" placeholder="<?php echo $translations[$language]['nominator_phone_placeholder']; ?>">
                                                <?php if (isset($errors['nominator_phone'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_phone']); ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nominator Email -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_email" class="form-label"><?php echo $translations[$language]['nominator_email']; ?></label>
                                                <input type="email" class="form-control <?php echo isset($errors['nominator_email']) ? 'is-invalid' : ''; ?>" id="nominator_email" name="nominator_email" value="<?php echo htmlspecialchars($application_data['nominator_email'] ?? ''); ?>" placeholder="<?php echo $translations[$language]['nominator_email_placeholder']; ?>">
                                                <?php if (isset($errors['nominator_email'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_email']); ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Relationship (Optional) -->
                                            <div class="col-md-12 mb-3">
                                                <label for="relationship" class="form-label"><?php echo $translations[$language]['relationship']; ?></label>
                                                <input type="text" class="form-control <?php echo isset($errors['relationship']) ? 'is-invalid' : ''; ?>" id="relationship" name="relationship" value="<?php echo htmlspecialchars($application_data['relationship'] ?? ''); ?>" placeholder="<?php echo $translations[$language]['relationship_placeholder']; ?>">
                                                <?php if (isset($errors['relationship'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['relationship']); ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Nomination Letter Upload Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-file-text-line me-1"></i><?php echo $translations[$language]['nomination_letter']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <label for="nomination_letter" class="form-label"><?php echo $translations[$language]['upload_letter']; ?></label>
                                                <p class="text-muted fs-13"><?php echo $translations[$language]['letter_instructions']; ?></p>
                                                <input type="file" class="form-control <?php echo isset($errors['nomination_letter']) ? 'is-invalid' : ''; ?>" id="nomination_letter" name="nomination_letter" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                <?php if (isset($errors['nomination_letter'])): ?>
                                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['nomination_letter']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($application_data['nomination_letter_path']) && file_exists($application_data['nomination_letter_path'])): ?>
                                                    <div class="mt-2">
                                                        <span class="file-upload-info"><?php echo $translations[$language]['current_letter_uploaded']; ?></span>
                                                        <a href="<?php echo htmlspecialchars($application_data['nomination_letter_path']); ?>" target="_blank" class="existing-file-link ms-2">
                                                            <i class="ri-eye-line"></i> <?php echo htmlspecialchars(basename($application_data['nomination_letter_path'])); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div><!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-between mt-4 mb-4">
                                    <a href="application-step1-international.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['back_to_step_1']; ?></a>
                                    <button type="submit" class="btn btn-primary"><?php echo $translations[$language]['save_continue']; ?> <i class="ri-arrow-right-line ms-1"></i></button>
                                </div>
                            </form>
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
    <script src="assets/js/app.min.js"></script>

    <!-- Page Specific Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]:not([disabled])'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>