<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step1-international.php
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
    error_log("User ID missing from session for logged-in user on application-step1-international.php.");
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
        'page_title' => 'Application: Step 1 (International) | Musabaqa',
        'page_header' => 'Application - Step 1: Personal Information (International)',
        'dashboard' => 'Dashboard',
        'application' => 'Application',
        'step_1' => 'Step 1',
        'welcome_message' => 'Assalamu Alaikum / Peace be upon you, <strong>%s</strong>! Please fill in your personal details accurately.',
        'personal_information' => 'Contestant\'s Personal Information',
        'passport_photograph' => 'Passport Photograph',
        'competition_details' => 'Competition Details',
        'full_name_passport' => 'Full Name (as on Passport)',
        'full_name_required' => 'Full Name is required.',
        'dob' => 'Date of Birth',
        'dob_required' => 'Date of Birth is required.',
        'dob_invalid_format' => 'Invalid Date of Birth format (use YYYY-MM-DD).',
        'dob_future' => 'Date of Birth cannot be in the future.',
        'dob_invalid' => 'Please enter a valid Date of Birth.',
        'age' => 'Age (auto-calculated)',
        'age_invalid' => 'Could not calculate age from Date of Birth.',
        'address' => 'Residential Address',
        'address_required' => 'Residential Address is required.',
        'city' => 'City',
        'city_required' => 'City is required.',
        'country_residence' => 'Country of Residence',
        'country_residence_required' => 'Country of Residence is required.',
        'select_country' => '-- Select Country --',
        'nationality' => 'Nationality',
        'nationality_required' => 'Nationality is required.',
        'nationality_placeholder' => 'e.g., Ghanaian, Egyptian',
        'passport_number' => 'Passport Number',
        'passport_number_required' => 'Passport Number is required.',
        'phone_number' => 'Phone Number (with country code)',
        'phone_number_required' => 'Phone Number is required.',
        'phone_number_invalid' => 'Invalid Phone Number format.',
        'phone_number_placeholder' => 'e.g., +234 801 234 5678',
        'email' => 'Email Address',
        'email_required' => 'Valid Email is required.',
        'email_placeholder' => 'your.email@example.com',
        'health_status' => 'Health Status',
        'health_status_required' => 'Health Status is required.',
        'health_status_placeholder' => 'Briefly describe your health status (e.g., Good, Any allergies?)',
        'languages_spoken' => 'Languages Spoken Fluently',
        'languages_spoken_required' => 'Languages Spoken is required.',
        'languages_spoken_placeholder' => 'e.g., English, Arabic, French',
        'upload_photo' => 'Upload Photo',
        'photo_instructions' => 'Clear, recent photo with plain background (JPG, PNG, max 2MB).',
        'photo_required' => 'Passport-Sized Photo is required.',
        'photo_invalid_type' => 'Invalid file type. Only JPG, JPEG, PNG allowed.',
        'photo_size_exceeded' => 'File size exceeds the limit (2MB).',
        'photo_upload_failed' => 'Failed to upload photo. Please ensure the \'uploads/photos\' directory is writable by the web server.',
        'photo_upload_error' => 'Error uploading photo: Code %s',
        'photo_preview' => 'Photo Preview',
        'view_current_photo' => 'View Current Photo',
        'image_preview_placeholder' => 'Image preview will appear here',
        'category' => 'Category Participating In',
        'category_required' => 'Please select a valid category.',
        'select_category' => '-- Select Category --',
        'category_qiraat' => 'First Category: The Seven Qira\'at via Ash-Shatibiyyah (Males Only)',
        'category_hifz' => 'Second Category: Full Qur\'an Memorization (Females Only)',
        'narration' => 'Narration (Riwayah)',
        'narration_required' => 'Narration is required for the Hifz category.',
        'narration_placeholder' => 'e.g., Warsh \'an Nafi, Hafs \'an Asim, Qalun \'an Nafi',
        'cancel_back' => 'Cancel / Back to Overview',
        'save_continue' => 'Save and Continue to Step 2',
        'error_invalid_submission' => 'Invalid form submission. Please try again.',
        'error_load_details' => 'Could not load existing application details. Please try again later.',
        'error_save' => 'An error occurred while saving your information. Please check your inputs and try again.',
        'success_save' => 'Personal information saved successfully. Proceeding to the next step...',
        'error_app_not_found' => 'Application not found or type mismatch.',
        'error_verify_app' => 'Error verifying application status. Please try again later.',
        // JavaScript alerts
        'js_invalid_photo_type' => 'Invalid file type. Please select a JPG or PNG image.',
        'js_photo_size_exceeded' => 'File size exceeds the limit of %sMB.',
    ],
    'ar' => [
        'page_title' => 'الطلب: الخطوة الأولى (دولي) | المسابقة',
        'page_header' => 'الطلب - الخطوة الأولى: المعلومات الشخصية (دولي)',
        'dashboard' => 'لوحة التحكم',
        'application' => 'الطلب',
        'step_1' => 'الخطوة الأولى',
        'welcome_message' => 'السلام عليكم ورحمة الله وبركاته، <strong>%s</strong>! يرجى تعبئة تفاصيلك الشخصية بدقة.',
        'personal_information' => 'المعلومات الشخصية للمتسابق',
        'passport_photograph' => 'صورة جواز السفر',
        'competition_details' => 'تفاصيل المسابقة',
        'full_name_passport' => 'الاسم الكامل (كما في جواز السفر)',
        'full_name_required' => 'الاسم الكامل مطلوب.',
        'dob' => 'تاريخ الميلاد',
        'dob_required' => 'تاريخ الميلاد مطلوب.',
        'dob_invalid_format' => 'تنسيق تاريخ الميلاد غير صالح (استخدم YYYY-MM-DD).',
        'dob_future' => 'لا يمكن أن يكون تاريخ الميلاد في المستقبل.',
        'dob_invalid' => 'يرجى إدخال تاريخ ميلاد صالح.',
        'age' => 'العمر (يتم حسابه تلقائيًا)',
        'age_invalid' => 'تعذر حساب العمر من تاريخ الميلاد.',
        'address' => 'العنوان السكني',
        'address_required' => 'العنوان السكني مطلوب.',
        'city' => 'المدينة',
        'city_required' => 'المدينة مطلوبة.',
        'country_residence' => 'بلد الإقامة',
        'country_residence_required' => 'بلد الإقامة مطلوب.',
        'select_country' => '-- اختر البلد --',
        'nationality' => 'الجنسية',
        'nationality_required' => 'الجنسية مطلوبة.',
        'nationality_placeholder' => 'مثال: غاني، مصري',
        'passport_number' => 'رقم جواز السفر',
        'passport_number_required' => 'رقم جواز السفر مطلوب.',
        'phone_number' => 'رقم الهاتف (مع رمز البلد)',
        'phone_number_required' => 'رقم الهاتف مطلوب.',
        'phone_number_invalid' => 'تنسيق رقم الهاتف غير صالح.',
        'phone_number_placeholder' => 'مثال: +234 801 234 5678',
        'email' => 'عنوان البريد الإلكتروني',
        'email_required' => 'البريد الإلكتروني الصالح مطلوب.',
        'email_placeholder' => 'your.email@example.com',
        'health_status' => 'الحالة الصحية',
        'health_status_required' => 'الحالة الصحية مطلوبة.',
        'health_status_placeholder' => 'صف بإيجاز حالتك الصحية (مثال: جيدة، أي حساسيات؟)',
        'languages_spoken' => 'اللغات التي تتحدثها بطلاقة',
        'languages_spoken_required' => 'اللغات التي تتحدثها مطلوبة.',
        'languages_spoken_placeholder' => 'مثال: الإنجليزية، العربية، الفرنسية',
        'upload_photo' => 'رفع الصورة',
        'photo_instructions' => 'صورة حديثة واضحة بخلفية سادة (JPG، PNG، بحد أقصى 2 ميجابايت).',
        'photo_required' => 'صورة بحجم جواز السفر مطلوبة.',
        'photo_invalid_type' => 'نوع الملف غير صالح. يُسمح فقط بـ JPG، JPEG، PNG.',
        'photo_size_exceeded' => 'حجم الملف يتجاوز الحد (2 ميجابايت).',
        'photo_upload_failed' => 'فشل في رفع الصورة. يرجى التأكد من أن مجلد \'uploads/photos\' قابل للكتابة بواسطة خادم الويب.',
        'photo_upload_error' => 'خطأ في رفع الصورة: الكود %s',
        'photo_preview' => 'معاينة الصورة',
        'view_current_photo' => 'عرض الصورة الحالية',
        'image_preview_placeholder' => 'ستظهر معاينة الصورة هنا',
        'category' => 'الفئة المشاركة فيها',
        'category_required' => 'يرجى اختيار فئة صالحة.',
        'select_category' => '-- اختر الفئة --',
        'category_qiraat' => 'الفئة الأولى: القراءات السبع عبر الشاطبية (للذكور فقط)',
        'category_hifz' => 'الفئة الثانية: حفظ القرآن الكامل (للإناث فقط)',
        'narration' => 'الرواية',
        'narration_required' => 'الرواية مطلوبة لفئة الحفظ.',
        'narration_placeholder' => 'مثال: ورش عن نافع، حفص عن عاصم، قالون عن نافع',
        'cancel_back' => 'إلغاء / العودة إلى النظرة العامة',
        'save_continue' => 'حفظ ومتابعة إلى الخطوة الثانية',
        'error_invalid_submission' => 'إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'error_load_details' => 'تعذر تحميل تفاصيل الطلب الحالية. يرجى المحاولة مرة أخرى لاحقًا.',
        'error_save' => 'حدث خطأ أثناء حفظ المعلومات. يرجى التحقق من مدخلاتك والمحاولة مرة أخرى.',
        'success_save' => 'تم حفظ المعلومات الشخصية بنجاح. جارٍ المتابعة إلى الخطوة التالية...',
        'error_app_not_found' => 'الطلب غير موجود أو هناك عدم تطابق في النوع.',
        'error_verify_app' => 'خطأ في التحقق من حالة الطلب. يرجى المحاولة مرة أخرى لاحقًا.',
        // JavaScript alerts
        'js_invalid_photo_type' => 'نوع الملف غير صالح. يرجى اختيار صورة JPG أو PNG.',
        'js_photo_size_exceeded' => 'حجم الملف يتجاوز الحد الأقصى %s ميجابايت.',
    ]
];

// --- Application Verification ---
global $conn;
$application_id = null;
$application_data = []; // To store existing data for pre-filling
$application_status = 'Not Started'; // Default status

$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'international'");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        $application_status = $app['status'];

        // Fetch existing details for this application step
        $stmt_details = $conn->prepare("SELECT * FROM application_details_international WHERE application_id = ?");
        if ($stmt_details) {
            $stmt_details->bind_param("i", $application_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                $application_data = $result_details->fetch_assoc();
            }
            $stmt_details->close();
        } else {
            error_log("Failed to prepare statement for fetching International details: " . $conn->error);
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
    die($translations[$language]['error_verify_app']);
}

// --- Define profile picture for topbar ---
$profile_picture = $application_data['photo_path'] ?? $_SESSION['user_profile_picture'] ?? 'assets/images/users/avatar-1.jpg';

// --- Form Processing ---
$errors = [];
$success = '';
$upload_dir = 'Uploads/photos/';
$allowed_types = ['jpg', 'jpeg', 'png'];
$max_file_size = 2 * 1024 * 1024; // 2MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = $translations[$language]['error_invalid_submission'];
    } else {
        // Sanitize and retrieve POST data
        $full_name_passport = sanitize_input($_POST['full_name_passport'] ?? '');
        $dob = sanitize_input($_POST['dob'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $country_residence = sanitize_input($_POST['country_residence'] ?? '');
        $nationality = sanitize_input($_POST['nationality'] ?? '');
        $passport_number = sanitize_input($_POST['passport_number'] ?? '');
        $phone_number = sanitize_input($_POST['phone_number'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $health_status = sanitize_input($_POST['health_status'] ?? '');
        $languages_spoken = sanitize_input($_POST['languages_spoken'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $narration = ($category === 'hifz') ? sanitize_input($_POST['narration'] ?? '') : null;

        // --- Validation ---
        $age = null;
        if (empty($full_name_passport)) $errors['full_name_passport'] = $translations[$language]['full_name_required'];
        if (empty($dob)) {
            $errors['dob'] = $translations[$language]['dob_required'];
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $errors['dob'] = $translations[$language]['dob_invalid_format'];
        } else {
            try {
                $birthDate = new DateTime($dob);
                $today = new DateTime('today');
                if ($birthDate > $today) {
                    $errors['dob'] = $translations[$language]['dob_future'];
                } else {
                    $age = $birthDate->diff($today)->y;
                    if ($age < 1) {
                        $errors['dob'] = $translations[$language]['dob_invalid'];
                    }
                }
            } catch (Exception $e) {
                $errors['dob'] = $translations[$language]['dob_invalid'];
            }
        }
        if ($age === null && empty($errors['dob'])) $errors['age'] = $translations[$language]['age_invalid'];

        if (empty($address)) $errors['address'] = $translations[$language]['address_required'];
        if (empty($city)) $errors['city'] = $translations[$language]['city_required'];
        if (empty($country_residence)) $errors['country_residence'] = $translations[$language]['country_residence_required'];
        if (empty($nationality)) $errors['nationality'] = $translations[$language]['nationality_required'];
        if (empty($passport_number)) $errors['passport_number'] = $translations[$language]['passport_number_required'];
        if (empty($phone_number)) $errors['phone_number'] = $translations[$language]['phone_number_required'];
        if (!empty($phone_number) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $phone_number)) $errors['phone_number'] = $translations[$language]['phone_number_invalid'];
        if ($email === false) $errors['email'] = $translations[$language]['email_required'];
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
                $errors['photo'] = $translations[$language]['photo_size_exceeded'];
            } else {
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $errors['photo'] = $translations[$language]['photo_upload_failed'];
                        goto skip_file_move_intl;
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
                    $errors['photo'] = $translations[$language]['photo_upload_failed'];
                }
            }
        } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors['photo'] = sprintf($translations[$language]['photo_upload_error'], $_FILES['photo']['error']);
        } elseif (empty($photo_path)) {
            $errors['photo'] = $translations[$language]['photo_required'];
        }

        skip_file_move_intl:

        if (empty($errors) && $age === null) {
            $errors['age'] = $translations[$language]['age_invalid'];
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                $stmt_check = $conn->prepare("SELECT id FROM application_details_international WHERE application_id = ?");
                if (!$stmt_check) throw new Exception("Prepare failed (Check): " . $conn->error);
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    $sql = "UPDATE application_details_international SET
                                full_name_passport = ?, dob = ?, age = ?, address = ?, city = ?, country_residence = ?,
                                nationality = ?, passport_number = ?, phone_number = ?, email = ?, health_status = ?,
                                languages_spoken = ?, photo_path = ?, category = ?, narration = ?, updated_at = NOW()
                            WHERE application_id = ?";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (UPDATE): " . $conn->error);
                    $stmt_save->bind_param("ssissssssssssssi",
                        $full_name_passport, $dob, $age, $address, $city, $country_residence, $nationality,
                        $passport_number, $phone_number, $email, $health_status, $languages_spoken,
                        $photo_path, $category, $narration, $application_id);
                } else {
                    $sql = "INSERT INTO application_details_international
                                (application_id, full_name_passport, dob, age, address, city, country_residence, nationality,
                                 passport_number, phone_number, email, health_status, languages_spoken, photo_path,
                                 category, narration)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (INSERT): " . $conn->error);
                    $stmt_save->bind_param("isssisssssssssss",
                        $application_id, $full_name_passport, $dob, $age, $address, $city, $country_residence, $nationality,
                        $passport_number, $phone_number, $email, $health_status, $languages_spoken,
                        $photo_path, $category, $narration);
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
                    'full_name_passport' => $full_name_passport, 'dob' => $dob, 'age' => $age, 'address' => $address,
                    'city' => $city, 'country_residence' => $country_residence, 'nationality' => $nationality,
                    'passport_number' => $passport_number, 'phone_number' => $phone_number, 'email' => $email,
                    'health_status' => $health_status, 'languages_spoken' => $languages_spoken,
                    'photo_path' => $photo_path, 'category' => $category, 'narration' => $narration
                ];
                $profile_picture = $photo_path ?? $profile_picture;

                redirect('application-step2-international.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving International application step 1 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = $translations[$language]['error_save'] . " Details: " . $e->getMessage();
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
                            <form id="step1-form-intl" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- Personal Details Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-user-line me-1"></i><?php echo $translations[$language]['personal_information']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Full Name (Passport) -->
                                            <div class="col-md-6 mb-3">
                                                <label for="full_name_passport" class="form-label"><?php echo $translations[$language]['full_name_passport']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['full_name_passport']) ? 'is-invalid' : ''; ?>" id="full_name_passport" name="full_name_passport" value="<?php echo htmlspecialchars($application_data['full_name_passport'] ?? ''); ?>" required>
                                                <?php if (isset($errors['full_name_passport'])): ?><div class="invalid-feedback"><?php echo $errors['full_name_passport']; ?></div><?php endif; ?>
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
                                            <div class="col-md-8 mb-3">
                                                <label for="address" class="form-label"><?php echo $translations[$language]['address']; ?> <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" id="address" name="address" rows="2" required><?php echo htmlspecialchars($application_data['address'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?php echo $errors['address']; ?></div><?php endif; ?>
                                            </div>
                                            <!-- City -->
                                            <div class="col-md-4 mb-3">
                                                <label for="city" class="form-label"><?php echo $translations[$language]['city']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>" id="city" name="city" value="<?php echo htmlspecialchars($application_data['city'] ?? ''); ?>" required>
                                                <?php if (isset($errors['city'])): ?><div class="invalid-feedback"><?php echo $errors['city']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Country of Residence Dropdown -->
                                            <div class="col-md-4 mb-3">
                                                <label for="country_residence" class="form-label"><?php echo $translations[$language]['country_residence']; ?> <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['country_residence']) ? 'is-invalid' : ''; ?>" id="country_residence" name="country_residence" required>
                                                    <option value="" disabled <?php echo empty($application_data['country_residence']) ? 'selected' : ''; ?>><?php echo $translations[$language]['select_country']; ?></option>
                                                    <?php
                                                        $countries = get_countries();
                                                        $selected_country = $application_data['country_residence'] ?? ($_POST['country_residence'] ?? '');
                                                        foreach ($countries as $code => $name) {
                                                            $selected = ($name === $selected_country) ? 'selected' : '';
                                                            echo "<option value=\"" . htmlspecialchars($name) . "\" $selected>" . htmlspecialchars($name) . "</option>";
                                                        }
                                                    ?>
                                                </select>
                                                <?php if (isset($errors['country_residence'])): ?><div class="invalid-feedback"><?php echo $errors['country_residence']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nationality -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nationality" class="form-label"><?php echo $translations[$language]['nationality']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['nationality']) ? 'is-invalid' : ''; ?>" id="nationality" name="nationality" value="<?php echo htmlspecialchars($application_data['nationality'] ?? ''); ?>" required placeholder="<?php echo $translations[$language]['nationality_placeholder']; ?>">
                                                <?php if (isset($errors['nationality'])): ?><div class="invalid-feedback"><?php echo $errors['nationality']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Passport Number -->
                                            <div class="col-md-4 mb-3">
                                                <label for="passport_number" class="form-label"><?php echo $translations[$language]['passport_number']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['passport_number']) ? 'is-invalid' : ''; ?>" id="passport_number" name="passport_number" value="<?php echo htmlspecialchars($application_data['passport_number'] ?? ''); ?>" required>
                                                <?php if (isset($errors['passport_number'])): ?><div class="invalid-feedback"><?php echo $errors['passport_number']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Phone Number -->
                                            <div class="col-md-6 mb-3">
                                                <label for="phone_number" class="form-label"><?php echo $translations[$language]['phone_number']; ?> <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control <?php echo isset($errors['phone_number']) ? 'is-invalid' : ''; ?>" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($application_data['phone_number'] ?? ''); ?>" required placeholder="<?php echo $translations[$language]['phone_number_placeholder']; ?>">
                                                <?php if (isset($errors['phone_number'])): ?><div class="invalid-feedback"><?php echo $errors['phone_number']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label"><?php echo $translations[$language]['email']; ?> <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($application_data['email'] ?? $_SESSION['user_email'] ?? ''); ?>" required placeholder="<?php echo $translations[$language]['email_placeholder']; ?>">
                                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Health Status -->
                                            <div class="col-md-6 mb-3">
                                                <label for="health_status" class="form-label"><?php echo $translations[$language]['health_status']; ?> <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['health_status']) ? 'is-invalid' : ''; ?>" id="health_status" name="health_status" rows="2" required placeholder="<?php echo $translations[$language]['health_status_placeholder']; ?>"><?php echo htmlspecialchars($application_data['health_status'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['health_status'])): ?><div class="invalid-feedback"><?php echo $errors['health_status']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Languages Spoken -->
                                            <div class="col-md-6 mb-3">
                                                <label for="languages_spoken" class="form-label"><?php echo $translations[$language]['languages_spoken']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['languages_spoken']) ? 'is-invalid' : ''; ?>" id="languages_spoken" name="languages_spoken" value="<?php echo htmlspecialchars($application_data['languages_spoken'] ?? ''); ?>" required placeholder="<?php echo $translations[$language]['languages_spoken_placeholder']; ?>">
                                                <?php if (isset($errors['languages_spoken'])): ?><div class="invalid-feedback"><?php echo $errors['languages_spoken']; ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Photo Upload Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-image-line me-1"></i><?php echo $translations[$language]['passport_photograph']; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="photo" class="form-label"><?php echo $translations[$language]['upload_photo']; ?> <span class="text-danger">*</span></label>
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
                                                        <span id="preview-placeholder" class="text-muted"><?php echo $translations[$language]['image_preview_placeholder']; ?></span>
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
                                                <input type="text" class="form-control <?php echo isset($errors['narration']) ? 'is-invalid' : ''; ?>" id="narration" name="narration" value="<?php echo htmlspecialchars($application_data['narration'] ?? ''); ?>" placeholder="<?php echo $translations[$language]['narration_placeholder']; ?>">
                                                <?php if (isset($errors['narration'])): ?><div class="invalid-feedback"><?php echo $errors['narration']; ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-between mt-4 mb-4">
                                    <a href="index.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['cancel_back']; ?></a>
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
        // Calculate age
        function calculateAge() {
            const dobInput = document.getElementById('dob');
            const ageInput = document.getElementById('age');
            const dobValue = dobInput.value;

            ageInput.value = '';
            dobInput.classList.remove('is-invalid');
            const feedback = dobInput.parentNode.querySelector('.invalid-feedback');
            if(feedback) feedback.textContent = '';

            if (dobValue) {
                try {
                    const birthDate = new Date(dobValue);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (isNaN(birthDate.getTime())) {
                        throw new Error("Invalid date format");
                    }
                    if (birthDate > today) {
                        dobInput.classList.add('is-invalid');
                        if(feedback) feedback.textContent = '<?php echo $translations[$language]['dob_future']; ?>';
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
                        if(feedback) feedback.textContent = '<?php echo $translations[$language]['dob_invalid']; ?>';
                    }
                } catch (e) {
                    dobInput.classList.add('is-invalid');
                    if(feedback) feedback.textContent = '<?php echo $translations[$language]['dob_invalid']; ?>';
                }
            }
        }

        // Toggle Narration field
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

            reader.onload = function(){
                output.src = reader.result;
                output.style.display = 'block';
                if(placeholder) placeholder.style.display = 'none';
            };

            if (event.target.files[0]) {
                const fileType = event.target.files[0].type;
                if (!['image/jpeg', 'image/png', 'image/jpg'].includes(fileType)) {
                    alert('<?php echo $translations[$language]['js_invalid_photo_type']; ?>');
                    event.target.value = '';
                    output.style.display = 'none';
                    if(placeholder) placeholder.style.display = 'block';
                    return;
                }
                const fileSize = event.target.files[0].size;
                const maxSize = <?php echo $max_file_size; ?>;
                if (fileSize > maxSize) {
                    alert('<?php echo sprintf($translations[$language]['js_photo_size_exceeded'], ($max_file_size / 1024 / 1024)); ?>');
                    event.target.value = '';
                    output.style.display = 'none';
                    if(placeholder) placeholder.style.display = 'block';
                    return;
                }
                reader.readAsDataURL(event.target.files[0]);
            } else {
                output.style.display = 'none';
                if(placeholder) placeholder.style.display = 'block';
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
            toggleNarration();
        });
    </script>

</body>
</html>