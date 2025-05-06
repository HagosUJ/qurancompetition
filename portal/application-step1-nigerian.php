<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step1-nigerian.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely
require_once 'includes/nigeria_data.php'; // Include the state/LGA data

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
$user_fullname = $_SESSION['user_fullname'] ?? ($language === 'ar' ? 'مشارك' : 'Participant');
if (!$user_id) {
    error_log("User ID missing from session for logged-in user on application-step1-nigerian.php.");
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
        'page_title' => 'Application: Step 1 (Nigerian) | Musabaqa',
        'page_header' => 'Application - Step 1: Personal Information',
        'dashboard' => 'Dashboard',
        'application' => 'Application',
        'step_1' => 'Step 1',
        'welcome_message' => 'Assalamu Alaikum, <strong>%s</strong>! Please fill in your personal details accurately.',
        'contestant_info' => 'Contestant\'s Personal Information',
        'full_name_nid' => 'Full Name (as on National ID Card)',
        'full_name_required' => 'Full Name is required.',
        'dob' => 'Date of Birth',
        'dob_required' => 'Date of Birth is required.',
        'dob_invalid' => 'Invalid Date of Birth format (use YYYY-MM-DD).',
        'dob_future' => 'Date of Birth cannot be in the future.',
        'dob_invalid_general' => 'Please enter a valid Date of Birth.',
        'age' => 'Age (auto-calculated)',
        'age_error' => 'Could not calculate age from Date of Birth.',
        'address' => 'Residential Address',
        'address_required' => 'Address is required.',
        'state' => 'State of Origin',
        'state_required' => 'State of Origin is required.',
        'lga' => 'LGA of Origin',
        'lga_required' => 'LGA of Origin is required.',
        'select_state' => '-- Select State --',
        'select_lga' => '-- Select LGA --',
        'select_state_first' => '-- Select State First --',
        'phone_number' => 'Phone Number',
        'phone_number_required' => 'Phone Number is required.',
        'phone_number_invalid' => 'Invalid Phone Number format.',
        'email' => 'Email Address',
        'email_invalid' => 'Valid Email is required.',
        'health_status' => 'Health Status',
        'health_status_required' => 'Health Status is required.',
        'languages_spoken' => 'Languages Spoken Fluently',
        'languages_spoken_required' => 'Languages Spoken is required.',
        'photo_upload' => 'Passport Photograph',
        'photo_instructions' => 'Clear, recent photo with plain background (JPG, PNG, max 2MB).',
        'photo_required' => 'Passport-Sized Photo is required.',
        'photo_invalid_type' => 'Invalid file type. Only JPG, JPEG, PNG allowed.',
        'photo_size_limit' => 'File size exceeds the limit (2MB).',
        'photo_upload_error' => 'Failed to upload photo. Please ensure the \'uploads/photos\' directory is writable by the web server.',
        'photo_preview' => 'Photo Preview',
        'photo_preview_placeholder' => 'Image preview will appear here',
        'view_current_photo' => 'View Current Photo',
        'competition_details' => 'Competition Details',
        'category' => 'Category Participating In',
        'category_required' => 'Please select a valid category.',
        'category_qiraat' => 'First Category: The Seven Qira\'at via Ash-Shatibiyyah (Males Only)',
        'category_hifz' => 'Second Category: Full Qur\'an Memorization (Females Only)',
        'narration' => 'Narration (Riwayah)',
        'narration_required' => 'Narration is required for the Hifz category.',
        'cancel' => 'Cancel / Back to Overview',
        'save_continue' => 'Save and Continue to Step 2',
        'error_invalid_submission' => 'Invalid form submission. Please try again.',
        'error_save' => 'An error occurred while saving your information. Please check your inputs and try again.',
        'error_load_details' => 'Could not load existing application details. Please try again later.',
        'error_app_not_found' => 'Application not found or type mismatch.',
        'success_save' => 'Personal information saved successfully. Proceeding to the next step...',
        'js_invalid_file_type' => 'Invalid file type. Please select a JPG or PNG image.',
        'js_file_size_exceed' => 'File size exceeds the limit of %sMB.',
        'js_invalid_dob' => 'Invalid Date of Birth.',
        'js_dob_future' => 'Date of Birth cannot be in the future.',
        'js_dob_invalid_general' => 'Please enter a valid Date of Birth.',
    ],
    'ar' => [
        'page_title' => 'الطلب: الخطوة الأولى (نيجيري) | المسابقة',
        'page_header' => 'الطلب - الخطوة الأولى: المعلومات الشخصية',
        'dashboard' => 'لوحة التحكم',
        'application' => 'الطلب',
        'step_1' => 'الخطوة الأولى',
        'welcome_message' => 'السلام عليكم، <strong>%s</strong>! يرجى ملء التفاصيل الشخصية بدقة.',
        'contestant_info' => 'معلومات المتسابق الشخصية',
        'full_name_nid' => 'الاسم الكامل (كما في بطاقة الهوية الوطنية)',
        'full_name_required' => 'الاسم الكامل مطلوب.',
        'dob' => 'تاريخ الميلاد',
        'dob_required' => 'تاريخ الميلاد مطلوب.',
        'dob_invalid' => 'تنسيق تاريخ الميلاد غير صالح (استخدم YYYY-MM-DD).',
        'dob_future' => 'لا يمكن أن يكون تاريخ الميلاد في المستقبل.',
        'dob_invalid_general' => 'يرجى إدخال تاريخ ميلاد صالح.',
        'age' => 'العمر (يتم حسابه تلقائيًا)',
        'age_error' => 'تعذر حساب العمر من تاريخ الميلاد.',
        'address' => 'عنوان الإقامة',
        'address_required' => 'العنوان مطلوب.',
        'state' => 'الولاية الأصلية',
        'state_required' => 'الولاية الأصلية مطلوبة.',
        'lga' => 'منطقة الحكم المحلي الأصلية',
        'lga_required' => 'منطقة الحكم المحلي الأصلية مطلوبة.',
        'select_state' => '-- اختر الولاية --',
        'select_lga' => '-- اختر منطقة الحكم المحلي --',
        'select_state_first' => '-- اختر الولاية أولاً --',
        'phone_number' => 'رقم الهاتف',
        'phone_number_required' => 'رقم الهاتف مطلوب.',
        'phone_number_invalid' => 'تنسيق رقم الهاتف غير صالح.',
        'email' => 'عنوان البريد الإلكتروني',
        'email_invalid' => 'البريد الإلكتروني الصالح مطلوب.',
        'health_status' => 'الحالة الصحية',
        'health_status_required' => 'الحالة الصحية مطلوبة.',
        'languages_spoken' => 'اللغات المتحدث بها بطلاقة',
        'languages_spoken_required' => 'اللغات المتحدث بها مطلوبة.',
        'photo_upload' => 'صورة جواز السفر',
        'photo_instructions' => 'صورة واضحة حديثة بخلفية سادة (JPG، PNG، الحد الأقصى 2 ميغابايت).',
        'photo_required' => 'صورة بحجم جواز السفر مطلوبة.',
        'photo_invalid_type' => 'نوع الملف غير صالح. يُسمح فقط بـ JPG، JPEG، PNG.',
        'photo_size_limit' => 'حجم الملف يتجاوز الحد (2 ميغابايت).',
        'photo_upload_error' => 'فشل في رفع الصورة. يرجى التأكد من أن دليل \'uploads/photos\' قابل للكتابة بواسطة خادم الويب.',
        'photo_preview' => 'معاينة الصورة',
        'photo_preview_placeholder' => 'ستظهر معاينة الصورة هنا',
        'view_current_photo' => 'عرض الصورة الحالية',
        'competition_details' => 'تفاصيل المسابقة',
        'category' => 'الفئة المشارك فيها',
        'category_required' => 'يرجى اختيار فئة صالحة.',
        'category_qiraat' => 'الفئة الأولى: القراءات السبع عبر الشاطبية (للذكور فقط)',
        'category_hifz' => 'الفئة الثانية: حفظ القرآن الكامل (للإناث فقط)',
        'narration' => 'الرواية',
        'narration_required' => 'الرواية مطلوبة لفئة الحفظ.',
        'cancel' => 'إلغاء / العودة إلى النظرة العامة',
        'save_continue' => 'حفظ والمتابعة إلى الخطوة الثانية',
        'error_invalid_submission' => 'إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'error_save' => 'حدث خطأ أثناء حفظ المعلومات. يرجى التحقق من مدخلاتك والمحاولة مرة أخرى.',
        'error_load_details' => 'تعذر تحميل تفاصيل الطلب الحالية. يرجى المحاولة مرة أخرى لاحقًا.',
        'error_app_not_found' => 'الطلب غير موجود أو نوع غير مطابق.',
        'success_save' => 'تم حفظ المعلومات الشخصية بنجاح. جارٍ الانتقال إلى الخطوة التالية...',
        'js_invalid_file_type' => 'نوع الملف غير صالح. يرجى اختيار صورة JPG أو PNG.',
        'js_file_size_exceed' => 'حجم الملف يتجاوز الحد الأقصى %s ميغابايت.',
        'js_invalid_dob' => 'تاريخ الميلاد غير صالح.',
        'js_dob_future' => 'لا يمكن أن يكون تاريخ الميلاد في المستقبل.',
        'js_dob_invalid_general' => 'يرجى إدخال تاريخ ميلاد صالح.',
    ]
];

// --- Application Verification ---
global $conn;
$application_id = null;
$application_data = [];
$application_status = 'Not Started';

$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'nigerian'");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        $application_status = $app['status'];

        $stmt_details = $conn->prepare("SELECT * FROM application_details_nigerian WHERE application_id = ?");
        if ($stmt_details) {
            $stmt_details->bind_param("i", $application_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                $application_data = $result_details->fetch_assoc();
            }
            $stmt_details->close();
        } else {
            error_log("Failed to prepare statement for fetching Nigerian details: " . $conn->error);
            $errors['form'] = $translations[$language]['error_load_details'];
        }
    } else {
        if (!isset($_GET['error'])) {
            redirect('application.php?error=app_not_found_or_mismatch');
        } else {
            die($translations[$language]['error_app_not_found']);
        }
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die($translations[$language]['error_load_details']);
}

// --- Define profile picture for topbar ---
$profile_picture = $application_data['photo_path'] ?? $_SESSION['user_profile_picture'] ?? 'assets/images/users/avatar-1.jpg';

// --- Form Processing ---
$errors = [];
$success = '';
$upload_dir = 'uploads/photos/';
$allowed_types = ['jpg', 'jpeg', 'png'];
$max_file_size = 2 * 1024 * 1024; // 2MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = $translations[$language]['error_invalid_submission'];
    } else {
        $full_name_nid = sanitize_input($_POST['full_name_nid'] ?? '');
        $dob = sanitize_input($_POST['dob'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $lga = sanitize_input($_POST['lga'] ?? '');
        $state = sanitize_input($_POST['state'] ?? '');
        $phone_number = sanitize_input($_POST['phone_number'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $health_status = sanitize_input($_POST['health_status'] ?? '');
        $languages_spoken = sanitize_input($_POST['languages_spoken'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $narration = ($category === 'hifz') ? sanitize_input($_POST['narration'] ?? '') : null;

        // --- Validation ---
        $age = null;
        if (empty($full_name_nid)) $errors['full_name_nid'] = $translations[$language]['full_name_required'];
        if (empty($dob)) {
            $errors['dob'] = $translations[$language]['dob_required'];
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $errors['dob'] = $translations[$language]['dob_invalid'];
        } else {
            try {
                $birthDate = new DateTime($dob);
                $today = new DateTime('today');
                if ($birthDate > $today) {
                    $errors['dob'] = $translations[$language]['dob_future'];
                } else {
                    $age = $birthDate->diff($today)->y;
                    if ($age < 1) {
                        $errors['dob'] = $translations[$language]['dob_invalid_general'];
                    }
                }
            } catch (Exception $e) {
                $errors['dob'] = $translations[$language]['dob_invalid_general'];
            }
        }
        if ($age === null && empty($errors['dob'])) $errors['age'] = $translations[$language]['age_error'];

        if (empty($address)) $errors['address'] = $translations[$language]['address_required'];
        if (empty($state)) $errors['state'] = $translations[$language]['state_required'];
        if (empty($lga)) $errors['lga'] = $translations[$language]['lga_required'];
        if (empty($phone_number)) $errors['phone_number'] = $translations[$language]['phone_number_required'];
        if (!empty($phone_number) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $phone_number)) $errors['phone_number'] = $translations[$language]['phone_number_invalid'];
        if ($email === false) $errors['email'] = $translations[$language]['email_invalid'];
        if (empty($health_status)) $errors['health_status'] = $translations[$language]['health_status_required'];
        if (empty($languages_spoken)) $errors['languages_spoken'] = $translations[$language]['languages_spoken_required'];
        if (empty($category) || !in_array($category, ['qiraat', 'hifz'])) $errors['category'] = $translations[$language]['category_required'];
        if ($category === 'hifz' && empty($narration)) $errors['narration'] = $translations[$language]['narration_required'];

        // --- File Upload Handling ---
        $photo_path = $application_data['photo_path'] ?? null;
        $new_file_uploaded = false;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['photo']['tmp_name'];
            $file_name = $_FILES['photo']['name'];
            $file_size = $_FILES['photo']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                $errors['photo'] = $translations[$language]['photo_invalid_type'];
            } elseif ($file_size > $max_file_size) {
                $errors['photo'] = $translations[$language]['photo_size_limit'];
            } else {
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $errors['photo'] = $translations[$language]['photo_upload_error'];
                        goto skip_file_move;
                    }
                }

                $unique_filename = "user_{$user_id}_app_{$application_id}_" . uniqid() . '.' . $file_ext;
                $destination = $upload_dir . $unique_filename;

                if (move_uploaded_file($file_tmp_path, $destination)) {
                    $old_photo_path = $application_data['photo_path'] ?? null;
                    if ($old_photo_path && file_exists($old_photo_path) && $old_photo_path !== $destination) {
                        @unlink($old_photo_path);
                    }
                    $photo_path = $destination;
                    $new_file_uploaded = true;
                } else {
                    error_log("move_uploaded_file failed: From '{$file_tmp_path}' to '{$destination}' for user {$user_id}");
                    $errors['photo'] = $translations[$language]['photo_upload_error'];
                }
            }
        } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors['photo'] = sprintf($translations[$language]['photo_upload_error'], $_FILES['photo']['error']);
        } elseif (empty($photo_path)) {
            $errors['photo'] = $translations[$language]['photo_required'];
        }

        skip_file_move:

        if (empty($errors) && $age === null) {
            $errors['age'] = $translations[$language]['age_error'];
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                $stmt_check = $conn->prepare("SELECT id FROM application_details_nigerian WHERE application_id = ?");
                if (!$stmt_check) throw new Exception("Prepare failed (Check): " . $conn->error);
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    $sql = "UPDATE application_details_nigerian SET
                                full_name_nid = ?, dob = ?, age = ?, address = ?, lga = ?, state = ?,
                                phone_number = ?, email = ?, health_status = ?, languages_spoken = ?, photo_path = ?,
                                category = ?, narration = ?, updated_at = NOW()
                            WHERE application_id = ?";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (UPDATE): " . $conn->error);
                    $stmt_save->bind_param("ssissssssssssi",
                        $full_name_nid, $dob, $age, $address, $lga, $state, $phone_number, $email,
                        $health_status, $languages_spoken, $photo_path, $category, $narration,
                        $application_id
                    );
                } else {
                    $sql = "INSERT INTO application_details_nigerian
                                (application_id, full_name_nid, dob, age, address, lga, state, phone_number, email,
                                 health_status, languages_spoken, photo_path, category, narration)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (INSERT): " . $conn->error);
                    $stmt_save->bind_param("isssisssssssss",
                        $application_id, $full_name_nid, $dob, $age, $address, $lga, $state, $phone_number, $email,
                        $health_status, $languages_spoken, $photo_path, $category, $narration
                    );
                }

                if (!$stmt_save->execute()) {
                    throw new Exception("Execute failed: " . $stmt_save->error);
                }
                $stmt_save->close();

                if ($application_status === 'Not Started') {
                    $new_status = 'Personal Info Complete';
                    $next_step = 'step2';
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
                    'full_name_nid' => $full_name_nid, 'dob' => $dob, 'age' => $age, 'address' => $address,
                    'lga' => $lga, 'state' => $state, 'phone_number' => $phone_number, 'email' => $email,
                    'health_status' => $health_status, 'languages_spoken' => $languages_spoken,
                    'photo_path' => $photo_path, 'category' => $category, 'narration' => $narration
                ];
                $profile_picture = $photo_path ?? $profile_picture;

                redirect('application-step2-nigerian.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving Nigerian application step 1 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = $translations[$language]['error_save'];
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    $errors['form'] .= " Details: " . htmlspecialchars($e->getMessage());
                }
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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:;");

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
        #photo-preview-container {
            margin-top: 10px;
            border: 1px dashed #ced4da;
            padding: 1rem;
            border-radius: .25rem;
            min-height: 170px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
        }
        #photo-preview {
            max-height: 150px;
            max-width: 100%;
            border-radius: .25rem;
            object-fit: cover;
        }
        .existing-photo-link { font-size: 0.9em; }
        .step-indicator { margin-bottom: 1.5rem; }
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
                                        <li class="breadcrumb-item active"><?php echo $translations[$language]['step_1']; ?></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step Indicator -->
                    <div class="row step-indicator">
                        <div class="col-12">
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Step 1 of 4</div>
                            </div>
                        </div>
                    </div>

                    <!-- Welcome Message -->
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info bg-info text-white border-0" role="alert">
                                <?php echo sprintf($translations[$language]['welcome_message'], htmlspecialchars($user_fullname)); ?>
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

                    <!-- Application Form -->
                    <div class="row">
                        <div class="col-12">
                            <form id="step1-form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- Personal Details Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-user-line me-1"></i><?php echo $translations[$language]['contestant_info']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Full Name -->
                                            <div class="col-md-6 mb-3">
                                                <label for="full_name_nid" class="form-label"><?php echo $translations[$language]['full_name_nid']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['full_name_nid']) ? 'is-invalid' : ''; ?>" id="full_name_nid" name="full_name_nid" value="<?php echo htmlspecialchars($application_data['full_name_nid'] ?? ''); ?>" required>
                                                <?php if (isset($errors['full_name_nid'])): ?><div class="invalid-feedback"><?php echo $errors['full_name_nid']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Date of Birth -->
                                            <div class="col-md-3 mb-3">
                                                <label for="dob" class="form-label"><?php echo $translations[$language]['dob']; ?> <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control <?php echo isset($errors['dob']) ? 'is-invalid' : ''; ?>" id="dob" name="dob" value="<?php echo htmlspecialchars($application_data['dob'] ?? ''); ?>" required onchange="calculateAge()">
                                                <?php if (isset($errors['dob'])): ?><div class="invalid-feedback"><?php echo $errors['dob']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Age (Readonly) -->
                                            <div class="col-md-3 mb-3">
                                                <label for="age" class="form-label"><?php echo $translations[$language]['age']; ?></label>
                                                <input type="number" class="form-control <?php echo isset($errors['age']) ? 'is-invalid' : ''; ?>" id="age" name="age" value="<?php echo htmlspecialchars($application_data['age'] ?? ''); ?>" readonly required title="<?php echo $translations[$language]['age']; ?>">
                                                <?php if (isset($errors['age'])): ?><div class="invalid-feedback"><?php echo $errors['age']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Address -->
                                            <div class="col-md-12 mb-3">
                                                <label for="address" class="form-label"><?php echo $translations[$language]['address']; ?> <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" id="address" name="address" rows="3" required><?php echo htmlspecialchars($application_data['address'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?php echo $errors['address']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- State Dropdown -->
                                            <div class="col-md-6 mb-3">
                                                <label for="state" class="form-label"><?php echo $translations[$language]['state']; ?> <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['state']) ? 'is-invalid' : ''; ?>" id="state" name="state" required onchange="populateLGAs()">
                                                    <option value="" disabled <?php echo empty($application_data['state']) ? 'selected' : ''; ?>><?php echo $translations[$language]['select_state']; ?></option>
                                                    <?php
                                                        $states = get_nigerian_states();
                                                        $selected_state = $application_data['state'] ?? ($_POST['state'] ?? '');
                                                        foreach ($states as $state_name) {
                                                            $selected = ($state_name === $selected_state) ? 'selected' : '';
                                                            echo "<option value=\"" . htmlspecialchars($state_name) . "\" $selected>" . htmlspecialchars($state_name) . "</option>";
                                                        }
                                                    ?>
                                                </select>
                                                <?php if (isset($errors['state'])): ?><div class="invalid-feedback"><?php echo $errors['state']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- LGA Dropdown -->
                                            <div class="col-md-6 mb-3">
                                                <label for="lga" class="form-label"><?php echo $translations[$language]['lga']; ?> <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['lga']) ? 'is-invalid' : ''; ?>" id="lga" name="lga" required <?php echo empty($selected_state) ? 'disabled' : ''; ?>>
                                                    <option value="" disabled <?php echo empty($application_data['lga']) && empty($_POST['lga']) ? 'selected' : ''; ?>><?php echo $translations[$language]['select_lga']; ?></option>
                                                    <?php
                                                        if (!empty($selected_state)) {
                                                            $lgas = get_lgas_for_state($selected_state);
                                                            $selected_lga = $application_data['lga'] ?? ($_POST['lga'] ?? '');
                                                            foreach ($lgas as $lga_name) {
                                                                $selected = ($lga_name === $selected_lga) ? 'selected' : '';
                                                                echo "<option value=\"" . htmlspecialchars($lga_name) . "\" $selected>" . htmlspecialchars($lga_name) . "</option>";
                                                            }
                                                        }
                                                    ?>
                                                </select>
                                                <?php if (isset($errors['lga'])): ?><div class="invalid-feedback"><?php echo $errors['lga']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Phone Number -->
                                            <div class="col-md-6 mb-3">
                                                <label for="phone_number" class="form-label"><?php echo $translations[$language]['phone_number']; ?> <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control <?php echo isset($errors['phone_number']) ? 'is-invalid' : ''; ?>" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($application_data['phone_number'] ?? ''); ?>" required placeholder="<?php echo $language === 'ar' ? 'مثال: 08012345678' : 'e.g., 08012345678'; ?>">
                                                <?php if (isset($errors['phone_number'])): ?><div class="invalid-feedback"><?php echo $errors['phone_number']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label"><?php echo $translations[$language]['email']; ?> <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($application_data['email'] ?? $_SESSION['user_email'] ?? ''); ?>" required placeholder="<?php echo $language === 'ar' ? 'بريدك.الإلكتروني@مثال.com' : 'your.email@example.com'; ?>">
                                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Health Status -->
                                            <div class="col-md-6 mb-3">
                                                <label for="health_status" class="form-label"><?php echo $translations[$language]['health_status']; ?> <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['health_status']) ? 'is-invalid' : ''; ?>" id="health_status" name="health_status" rows="2" required placeholder="<?php echo $language === 'ar' ? 'صف بإيجاز حالتك الصحية (مثل، جيدة، أي حساسيات؟)' : 'Briefly describe your health status (e.g., Good, Any allergies?)'; ?>"><?php echo htmlspecialchars($application_data['health_status'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['health_status'])): ?><div class="invalid-feedback"><?php echo $errors['health_status']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Languages Spoken -->
                                            <div class="col-md-6 mb-3">
                                                <label for="languages_spoken" class="form-label"><?php echo $translations[$language]['languages_spoken']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['languages_spoken']) ? 'is-invalid' : ''; ?>" id="languages_spoken" name="languages_spoken" value="<?php echo htmlspecialchars($application_data['languages_spoken'] ?? ''); ?>" required placeholder="<?php echo $language === 'ar' ? 'مثال: الإنجليزية، الهوسا، اليوروبا' : 'e.g., English, Hausa, Yoruba'; ?>">
                                                <?php if (isset($errors['languages_spoken'])): ?><div class="invalid-feedback"><?php echo $errors['languages_spoken']; ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Photo Upload Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-image-line me-1"></i><?php echo $translations[$language]['photo_upload']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="photo" class="form-label"><?php echo $translations[$language]['photo_upload']; ?> <span class="text-danger">*</span></label>
                                                <p class="text-muted fs-13"><?php echo $translations[$language]['photo_instructions']; ?></p>
                                                <input type="file" class="form-control <?php echo isset($errors['photo']) ? 'is-invalid' : ''; ?>" id="photo" name="photo" accept=".jpg,.jpeg,.png" onchange="previewPhoto(event)">
                                                <?php if (isset($errors['photo'])): ?><div class="invalid-feedback"><?php echo $errors['photo']; ?></div><?php endif; ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label"><?php echo $translations[$language]['photo_preview']; ?></label>
                                                <div id="photo-preview-container">
                                                    <?php if (!empty($application_data['photo_path']) && file_exists($application_data['photo_path'])): ?>
                                                        <img id="photo-preview" src="<?php echo htmlspecialchars($application_data['photo_path']) . '?t=' . time(); ?>" alt="<?php echo $translations[$language]['photo_preview']; ?>">
                                                    <?php else: ?>
                                                        <img id="photo-preview" src="#" alt="<?php echo $translations[$language]['photo_preview']; ?>" style="display: none;">
                                                        <span id="preview-placeholder" class="text-muted"><?php echo $translations[$language]['photo_preview_placeholder']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($application_data['photo_path']) && file_exists($application_data['photo_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($application_data['photo_path']); ?>" target="_blank" class="existing-photo-link d-block mt-1 text-center"><?php echo $translations[$language]['view_current_photo']; ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div><!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Competition Details Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-trophy-line me-1"></i><?php echo $translations[$language]['competition_details']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Category -->
                                            <div class="col-md-6 mb-3">
                                                <label for="category" class="form-label"><?php echo $translations[$language]['category']; ?> <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" id="category" name="category" required onchange="toggleNarration()">
                                                    <option value="" disabled <?php echo empty($application_data['category']) ? 'selected' : ''; ?>><?php echo $translations[$language]['select_category']; ?></option>
                                                    <option value="qiraat" <?php echo (($application_data['category'] ?? '') === 'qiraat') ? 'selected' : ''; ?>><?php echo $translations[$language]['category_qiraat']; ?></option>
                                                    <option value="hifz" <?php echo (($application_data['category'] ?? '') === 'hifz') ? 'selected' : ''; ?>><?php echo $translations[$language]['category_hifz']; ?></option>
                                                </select>
                                                <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?php echo $errors['category']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Narration (Conditional) -->
                                            <div class="col-md-6 mb-3" id="narration-field" style="display: <?php echo (($application_data['category'] ?? '') === 'hifz') ? 'block' : 'none'; ?>;">
                                                <label for="narration" class="form-label"><?php echo $translations[$language]['narration']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['narration']) ? 'is-invalid' : ''; ?>" id="narration" name="narration" value="<?php echo htmlspecialchars($application_data['narration'] ?? ''); ?>" placeholder="<?php echo $language === 'ar' ? 'مثال: ورش عن نافع، حفص عن عاصم، قالون عن نافع' : 'e.g., Warsh \'an Nafi, Hafs \'an Asim, Qalun \'an Nafi'; ?>">
                                                <?php if (isset($errors['narration'])): ?><div class="invalid-feedback"><?php echo $errors['narration']; ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-between mt-4 mb-4">
                                    <a href="index.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['cancel']; ?></a>
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
        // Store Nigeria geo data in JS
        const nigeriaGeoData = <?php echo get_nigeria_geo_data_json(); ?>;
        const selectedLGA = "<?php echo htmlspecialchars($application_data['lga'] ?? ($_POST['lga'] ?? '')); ?>";

        // Translations for JavaScript
        const translations = {
            invalid_file_type: "<?php echo $translations[$language]['js_invalid_file_type']; ?>",
            file_size_exceed: "<?php echo $translations[$language]['js_file_size_exceed']; ?>",
            invalid_dob: "<?php echo $translations[$language]['js_invalid_dob']; ?>",
            dob_future: "<?php echo $translations[$language]['js_dob_future']; ?>",
            dob_invalid_general: "<?php echo $translations[$language]['js_dob_invalid_general']; ?>",
            select_lga: "<?php echo $translations[$language]['select_lga']; ?>",
            select_state_first: "<?php echo $translations[$language]['select_state_first']; ?>"
        };

        // Function to calculate age
        function calculateAge() {
            const dobInput = document.getElementById('dob');
            const ageInput = document.getElementById('age');
            const dobValue = dobInput.value;

            ageInput.value = '';
            dobInput.classList.remove('is-invalid');
            const feedback = dobInput.parentNode.querySelector('.invalid-feedback');
            if (feedback) feedback.textContent = '';

            if (dobValue) {
                try {
                    const birthDate = new Date(dobValue);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (isNaN(birthDate.getTime())) {
                        throw new Error(translations.invalid_dob);
                    }

                    if (birthDate > today) {
                        dobInput.classList.add('is-invalid');
                        if (feedback) feedback.textContent = translations.dob_future;
                        return;
                    }

                    let age = today.getFullYear() - birthDate.getFullYear();
                    const m = today.getMonth() - birthDate.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }
                    ageInput.value = age >= 0 ? age : '';
                    if (age < 0) {
                        dobInput.classList.add('is-invalid');
                        if (feedback) feedback.textContent = translations.dob_invalid_general;
                    }
                } catch (e) {
                    dobInput.classList.add('is-invalid');
                    if (feedback) feedback.textContent = e.message || translations.invalid_dob;
                }
            }
        }

        // Function to populate LGAs based on selected state
        function populateLGAs() {
            const stateSelect = document.getElementById('state');
            const lgaSelect = document.getElementById('lga');
            const selectedState = stateSelect.value;

            lgaSelect.innerHTML = `<option value="" disabled selected>${translations.select_lga}</option>`;

            if (selectedState && nigeriaGeoData[selectedState]) {
                lgaSelect.disabled = false;
                const lgas = nigeriaGeoData[selectedState];
                lgas.forEach(lga => {
                    const option = document.createElement('option');
                    option.value = lga;
                    option.textContent = lga;
                    if (lga === selectedLGA) {
                        option.selected = true;
                        lgaSelect.querySelector('option[disabled]').selected = false;
                    }
                    lgaSelect.appendChild(option);
                });
                if (selectedLGA && lgaSelect.value === "") {
                    lgaSelect.value = selectedLGA;
                }
                if (lgaSelect.value === "") {
                    lgaSelect.querySelector('option[disabled]').selected = true;
                }
            } else {
                lgaSelect.disabled = true;
                lgaSelect.innerHTML = `<option value="" disabled selected>${translations.select_state_first}</option>`;
            }
        }

        // Toggle Narration field based on Category selection
        function toggleNarration() {
            const categorySelect = document.getElementById('category');
            const narrationField = document.getElementById('narration-field');
            const narrationInput = document.getElementById('narration');
            if (categorySelect.value === 'hifz') {
                narrationField.style.display = 'block';
                narrationInput.required = true;
            } else {
                narrationField.style.display = 'none';
                narrationInput.required = false;
                narrationInput.value = '';
            }
        }

        // Preview uploaded photo
        function previewPhoto(event) {
            const reader = new FileReader();
            const output = document.getElementById('photo-preview');
            const placeholder = document.getElementById('preview-placeholder');

            reader.onload = function() {
                output.src = reader.result;
                output.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
            };

            if (event.target.files[0]) {
                const fileType = event.target.files[0].type;
                if (!['image/jpeg', 'image/png', 'image/jpg'].includes(fileType)) {
                    alert(translations.invalid_file_type);
                    event.target.value = '';
                    output.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'block';
                    output.src = '#';
                    return;
                }
                const fileSize = event.target.files[0].size;
                const maxSize = <?php echo $max_file_size; ?>;
                if (fileSize > maxSize) {
                    alert(translations.file_size_exceed.replace('%s', (maxSize / 1024 / 1024)));
                    event.target.value = '';
                    output.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'block';
                    output.src = '#';
                    return;
                }

                reader.readAsDataURL(event.target.files[0]);
            } else {
                output.style.display = 'none';
                if (placeholder) placeholder.style.display = 'block';
                output.src = '#';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]:not([disabled])'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            calculateAge();
            populateLGAs();
            toggleNarration();
        });
    </script>

</body>
</html>