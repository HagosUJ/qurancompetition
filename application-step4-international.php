<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step4-international.php
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
    error_log("User ID missing from session for logged-in user on application-step4-international.php.");
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
        'page_title' => 'Application: Step 4 - Review & Submit (International) | Musabaqa',
        'page_header' => 'Application - Step 4: Review & Submit (International)',
        'dashboard' => 'Dashboard',
        'application' => 'Application',
        'step_4' => 'Step 4',
        'application_submitted' => 'Application Submitted!',
        'submitted_message' => 'Your application (Status: %s) has been received. You will be notified of any updates. You can view your submitted details below.',
        'review_instructions' => 'Please carefully review all the information below. If everything is correct, click the "Submit Application" button at the bottom. You cannot edit the application after submission.',
        'application_summary' => 'Application Summary',
        'personal_information' => 'Personal Information',
        'edit_step_1' => 'Edit Step 1',
        'full_name_passport' => 'Full Name (Passport)',
        'date_of_birth' => 'Date of Birth',
        'age' => 'Age',
        'residential_address' => 'Residential Address',
        'city' => 'City',
        'country_residence' => 'Country of Residence',
        'nationality' => 'Nationality',
        'passport_number' => 'Passport Number',
        'phone_number' => 'Phone Number',
        'email_address' => 'Email Address',
        'health_status' => 'Health Status',
        'languages_spoken' => 'Languages Spoken',
        'category' => 'Category',
        'narration' => 'Narration (Riwayah)',
        'passport_photo' => 'Passport Photo',
        'not_provided' => 'Not Provided',
        'nominator_information' => 'Nominator/Sponsor Information',
        'edit_step_2' => 'Edit Step 2',
        'no_nominator_info' => 'No nominator information provided or required.',
        'nominator_type' => 'Nominator Type',
        'nominator_name' => 'Nominator Name',
        'nominator_address' => 'Address',
        'nominator_city' => 'City',
        'nominator_country' => 'Country',
        'nominator_phone' => 'Phone',
        'nominator_email' => 'Email',
        'nominator_relationship' => 'Relationship',
        'nomination_letter' => 'Nomination Letter',
        'uploaded_documents' => 'Uploaded Documents',
        'edit_step_3' => 'Edit Step 3',
        'no_document_info' => 'No document information found.',
        'passport_scan' => 'Passport Scan',
        'birth_certificate' => 'Birth Certificate',
        'view_file' => 'View %s',
        'declaration' => 'Declaration: By clicking "Submit Application", I declare that all the information provided is true and accurate to the best of my knowledge. I understand that providing false information may lead to disqualification.',
        'back_to_step_3' => 'Back to Step 3',
        'submit_application' => 'Submit Application',
        'back_to_dashboard' => 'Back to Dashboard',
        'error_invalid_submission' => 'Invalid form submission. Please try again.',
        'error_missing_info' => 'Cannot submit application. Some required information or documents appear to be missing. Please go back and complete all steps.',
        'error_already_submitted' => 'Application might already be submitted or could not be updated.',
        'error_submission' => 'An error occurred during submission. Please try again. If the problem persists, contact support.',
        'error_fetch' => 'Could not load %s details for review.',
        'success_submission' => 'Application submitted successfully! You will be notified once it has been reviewed.',
        'redirect_message' => 'You will be redirected to the dashboard shortly.',
    ],
    'ar' => [
        'page_title' => 'الطلب: الخطوة الرابعة - المراجعة والإرسال (دولي) | المسابقة',
        'page_header' => 'الطلب - الخطوة الرابعة: المراجعة والإرسال (دولي)',
        'dashboard' => 'لوحة التحكم',
        'application' => 'الطلب',
        'step_4' => 'الخطوة الرابعة',
        'application_submitted' => 'تم إرسال الطلب!',
        'submitted_message' => 'تم استلام طلبك (الحالة: %s). سيتم إعلامك بأي تحديثات. يمكنك عرض التفاصيل المقدمة أدناه.',
        'review_instructions' => 'يرجى مراجعة جميع المعلومات أدناه بعناية. إذا كان كل شيء صحيحًا، انقر فوق زر "إرسال الطلب" في الأسفل. لا يمكن تعديل الطلب بعد الإرسال.',
        'application_summary' => 'ملخص الطلب',
        'personal_information' => 'المعلومات الشخصية',
        'edit_step_1' => 'تعديل الخطوة الأولى',
        'full_name_passport' => 'الاسم الكامل (جواز السفر)',
        'date_of_birth' => 'تاريخ الميلاد',
        'age' => 'العمر',
        'residential_address' => 'عنوان الإقامة',
        'city' => 'المدينة',
        'country_residence' => 'بلد الإقامة',
        'nationality' => 'الجنسية',
        'passport_number' => 'رقم جواز السفر',
        'phone_number' => 'رقم الهاتف',
        'email_address' => 'عنوان البريد الإلكتروني',
        'health_status' => 'الحالة الصحية',
        'languages_spoken' => 'اللغات المتحدث بها',
        'category' => 'الفئة',
        'narration' => 'الرواية',
        'passport_photo' => 'صورة جواز السفر',
        'not_provided' => 'غير مقدم',
        'nominator_information' => 'معلومات المرشح/الكفيل',
        'edit_step_2' => 'تعديل الخطوة الثانية',
        'no_nominator_info' => 'لم يتم تقديم معلومات المرشح أو ليست مطلوبة.',
        'nominator_type' => 'نوع المرشح',
        'nominator_name' => 'اسم المرشح',
        'nominator_address' => 'العنوان',
        'nominator_city' => 'المدينة',
        'nominator_country' => 'البلد',
        'nominator_phone' => 'الهاتف',
        'nominator_email' => 'البريد الإلكتروني',
        'nominator_relationship' => 'العلاقة',
        'nomination_letter' => 'خطاب الترشيح',
        'uploaded_documents' => 'المستندات المرفوعة',
        'edit_step_3' => 'تعديل الخطوة الثالثة',
        'no_document_info' => 'لم يتم العثور على معلومات المستندات.',
        'passport_scan' => 'مسح جواز السفر',
        'birth_certificate' => 'شهادة الميلاد',
        'view_file' => 'عرض %s',
        'declaration' => 'الإقرار: بالنقر على "إرسال الطلب"، أقر بأن جميع المعلومات المقدمة صحيحة ودقيقة على حد علمي. أفهم أن تقديم معلومات خاطئة قد يؤدي إلى الاستبعاد.',
        'back_to_step_3' => 'العودة إلى الخطوة الثالثة',
        'submit_application' => 'إرسال الطلب',
        'back_to_dashboard' => 'العودة إلى لوحة التحكم',
        'error_invalid_submission' => 'إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'error_missing_info' => 'لا يمكن إرسال الطلب. يبدو أن بعض المعلومات أو المستندات المطلوبة مفقودة. يرجى العودة وإكمال جميع الخطوات.',
        'error_already_submitted' => 'قد يكون الطلب قد تم إرساله بالفعل أو لم يتم تحديثه.',
        'error_submission' => 'حدث خطأ أثناء الإرسال. يرجى المحاولة مرة أخرى. إذا استمرت المشكلة، تواصل مع الدعم.',
        'error_fetch' => 'تعذر تحميل تفاصيل %s للمراجعة.',
        'success_submission' => 'تم إرسال الطلب بنجاح! سيتم إعلامك بمجرد مراجعته.',
        'redirect_message' => 'سيتم إعادة توجيهك إلى لوحة التحكم قريبًا.',
    ]
];

// --- Application Verification & Data Fetching ---
global $conn;
$application_id = null;
$application_details = [];
$nominator_details = [];
$document_details = [];
$application_status = 'Not Started';
$current_step = '';
$is_submitted = false; // Flag to check if already submitted

$errors = [];
$success = '';

$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'international'");
if (!$stmt_app) {
    error_log("Prepare failed (App Check): " . $conn->error);
    die($translations[$language]['error_verify_app']);
}
$stmt_app->bind_param("i", $user_id);
$stmt_app->execute();
$result_app = $stmt_app->get_result();
if ($app = $result_app->fetch_assoc()) {
    $application_id = $app['id'];
    $application_status = $app['status'];
    $current_step = $app['current_step'];
    $is_submitted = ($application_status === 'Submitted' || $application_status === 'Under Review' || $application_status === 'Accepted' || $application_status === 'Rejected');

    // --- Step Access Control ---
    if ($current_step !== 'step4' && $application_status !== 'Documents Complete' && !$is_submitted) {
        $redirect_target = ($current_step && $current_step !== 'step4') ? 'application-' . $current_step . '-international.php' : 'application.php';
        redirect($redirect_target . '?error=step_sequence');
        exit;
    }

    // Fetch Step 1 Details (Personal Info)
    $stmt_step1 = $conn->prepare("SELECT * FROM application_details_international WHERE application_id = ?");
    if ($stmt_step1) {
        $stmt_step1->bind_param("i", $application_id);
        $stmt_step1->execute();
        $result_step1 = $stmt_step1->get_result();
        if ($result_step1->num_rows > 0) {
            $application_details = $result_step1->fetch_assoc();
        } else {
            $errors['fetch'] = sprintf($translations[$language]['error_fetch'], $translations[$language]['personal_information']);
        }
        $stmt_step1->close();
    } else {
        $errors['fetch'] = sprintf($translations[$language]['error_fetch'], $translations[$language]['personal_information']);
        error_log("Prepare failed (Step 1 Fetch): " . $conn->error);
    }

    // Fetch Step 2 Details (Nominator Info)
    $stmt_step2 = $conn->prepare("SELECT * FROM application_nominators_international WHERE application_id = ?");
    if ($stmt_step2) {
        $stmt_step2->bind_param("i", $application_id);
        $stmt_step2->execute();
        $result_step2 = $stmt_step2->get_result();
        if ($result_step2->num_rows > 0) {
            $nominator_details = $result_step2->fetch_assoc();
        }
        $stmt_step2->close();
    } else {
        $errors['fetch'] = sprintf($translations[$language]['error_fetch'], $translations[$language]['nominator_information']);
        error_log("Prepare failed (Step 2 Fetch): " . $conn->error);
    }

    // Fetch Step 3 Details (Documents)
    $stmt_step3 = $conn->prepare("SELECT * FROM application_documents_international WHERE application_id = ?");
    if ($stmt_step3) {
        $stmt_step3->bind_param("i", $application_id);
        $stmt_step3->execute();
        $result_step3 = $stmt_step3->get_result();
        if ($result_step3->num_rows > 0) {
            $document_details = $result_step3->fetch_assoc();
        } else {
            $errors['fetch'] = sprintf($translations[$language]['error_fetch'], $translations[$language]['uploaded_documents']);
        }
        $stmt_step3->close();
    } else {
        $errors['fetch'] = sprintf($translations[$language]['error_fetch'], $translations[$language]['uploaded_documents']);
        error_log("Prepare failed (Step 3 Fetch): " . $conn->error);
    }
} else {
    redirect('application.php?error=app_not_found_or_mismatch');
    exit;
}
$stmt_app->close();

// --- Submission Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_submitted) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = $translations[$language]['error_invalid_submission'];
    } elseif (isset($_POST['submit_application'])) {
        if (empty($application_details) || empty($document_details['passport_scan_path'])) {
            $errors['form'] = $translations[$language]['error_missing_info'];
        } else {
            $conn->begin_transaction();
            try {
                $new_status = 'Submitted';
                $final_step = 'completed';
                $submitted_at = date('Y-m-d H:i:s');

                $stmt_submit = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, submitted_at = ?, last_updated = NOW() WHERE id = ? AND status != ?");
                if (!$stmt_submit) {
                    throw new Exception("Prepare failed (Submit App): " . $conn->error);
                }
                $stmt_submit->bind_param("sssis", $new_status, $final_step, $submitted_at, $application_id, $new_status);

                if (!$stmt_submit->execute()) {
                    throw new Exception("Execute failed (Submit App): " . $stmt_submit->error);
                }

                if ($stmt_submit->affected_rows > 0) {
                    $conn->commit();
                    $success = $translations[$language]['success_submission'];
                    $is_submitted = true;
                    $application_status = $new_status;
                    header("Refresh: 5; url=index.php");
                } else {
                    $conn->rollback();
                    $errors['form'] = $translations[$language]['error_already_submitted'];
                }
                $stmt_submit->close();
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error submitting International application for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = $translations[$language]['error_submission'];
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    $errors['form'] .= " Details: " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// --- Define profile picture for topbar ---
$profile_picture = $application_details['photo_path'] ?? $_SESSION['user_profile_picture'] ?? 'assets/images/users/avatar-1.jpg';

// Helper function to display data safely
function display_data($data, $key, $default = '') {
    $default = empty($default) ? ($_SESSION['language'] === 'ar' ? 'غير مقدم' : 'Not Provided') : $default;
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : $default;
}

// Helper function to display file links
function display_file_link($data, $key, $label) {
    global $translations, $language;
    if (!empty($data[$key]) && file_exists($data[$key])) {
        $filename = basename($data[$key]);
        $filepath = htmlspecialchars($data[$key]);
        return "<a href='{$filepath}' target='_blank' class='existing-file-link'><i class='ri-eye-line'></i> " . sprintf($translations[$language]['view_file'], htmlspecialchars($label)) . " ({$filename})</a>";
    }
    return '<i>' . $translations[$language]['not_provided'] . '</i>';
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
        .review-section { margin-bottom: 2rem; }
        .review-section h5 { border-bottom: 1px solid #eee; padding-bottom: 0.5rem; margin-bottom: 1rem; color: #343a40; }
        .review-item { display: flex; margin-bottom: 0.75rem; }
        .review-item strong { width: 200px; color: #495057; flex-shrink: 0; }
        .review-item span { word-break: break-word; }
        .review-photo { max-height: 150px; max-width: 150px; border: 1px solid #dee2e6; border-radius: .25rem; object-fit: cover; }
        .existing-file-link { display: inline-block; margin-top: 5px; }
        .progress-bar { background-color: #0acf97; }
        .step-indicator { margin-bottom: 1.5rem; }
        .submission-confirmation { text-align: center; padding: 2rem; background-color: #e9f7f1; border: 1px solid #0acf97; border-radius: .25rem; }
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
                                        <li class="breadcrumb-item active"><?php echo $translations[$language]['step_4']; ?></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step Indicator -->
                    <div class="row step-indicator">
                        <div class="col-12">
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">Complete</div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions / Status -->
                    <div class="row">
                        <div class="col-12">
                            <?php if ($is_submitted): ?>
                                <div class="alert alert-success bg-success text-white border-0" role="alert">
                                    <h5 class="alert-heading"><i class="ri-check-double-line me-1"></i><?php echo $translations[$language]['application_submitted']; ?></h5>
                                    <?php echo sprintf($translations[$language]['submitted_message'], htmlspecialchars($application_status)); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning bg-warning text-white border-0" role="alert">
                                    <h5 class="alert-heading"><i class="ri-eye-line me-1"></i><?php echo $translations[$language]['review_instructions']; ?></h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Display Messages -->
                    <?php if (!empty($errors['form']) || !empty($errors['fetch'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="ri-close-circle-line me-1"></i> <?php echo htmlspecialchars($errors['form'] ?? $errors['fetch'] ?? $translations[$language]['error_submission']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="ri-check-line me-1"></i> <?php echo htmlspecialchars($success); ?>
                            <p class="mb-0 mt-2"><?php echo $translations[$language]['redirect_message']; ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Review Form -->
                    <div class="row">
                        <div class="col-12">
                            <form id="step4-form-intl" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-file-list-3-line me-1"></i><?php echo $translations[$language]['application_summary']; ?></h5>
                                    </div>
                                    <div class="card-body">

                                        <!-- Personal Information Section -->
                                        <div class="review-section">
                                            <h5><i class="ri-user-line me-1"></i><?php echo $translations[$language]['personal_information']; ?> <?php if (!$is_submitted): ?> (<a href="application-step1-international.php"><?php echo $translations[$language]['edit_step_1']; ?></a>)<?php endif; ?></h5>
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="review-item"><strong><?php echo $translations[$language]['full_name_passport']; ?>:</strong> <span><?php echo display_data($application_details, 'full_name_passport'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['date_of_birth']; ?>:</strong> <span><?php echo display_data($application_details, 'dob'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['age']; ?>:</strong> <span><?php echo display_data($application_details, 'age'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['residential_address']; ?>:</strong> <span><?php echo display_data($application_details, 'address'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['city']; ?>:</strong> <span><?php echo display_data($application_details, 'city'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['country_residence']; ?>:</strong> <span><?php echo display_data($application_details, 'country_residence'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['nationality']; ?>:</strong> <span><?php echo display_data($application_details, 'nationality'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['passport_number']; ?>:</strong> <span><?php echo display_data($application_details, 'passport_number'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['phone_number']; ?>:</strong> <span><?php echo display_data($application_details, 'phone_number'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['email_address']; ?>:</strong> <span><?php echo display_data($application_details, 'email'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['health_status']; ?>:</strong> <span><?php echo display_data($application_details, 'health_status'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['languages_spoken']; ?>:</strong> <span><?php echo display_data($application_details, 'languages_spoken'); ?></span></div>
                                                    <div class="review-item"><strong><?php echo $translations[$language]['category']; ?>:</strong> <span><?php echo display_data($application_details, 'category'); ?></span></div>
                                                    <?php if (($application_details['category'] ?? '') === 'hifz'): ?>
                                                        <div class="review-item"><strong><?php echo $translations[$language]['narration']; ?>:</strong> <span><?php echo display_data($application_details, 'narration'); ?></span></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-4 text-center">
                                                    <strong><?php echo $translations[$language]['passport_photo']; ?>:</strong><br>
                                                    <?php if (!empty($application_details['photo_path']) && file_exists($application_details['photo_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($application_details['photo_path']) . '?t=' . time(); ?>" alt="<?php echo $translations[$language]['passport_photo']; ?>" class="review-photo mt-2">
                                                    <?php else: ?>
                                                        <span><i><?php echo $translations[$language]['not_provided']; ?></i></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nominator Information Section -->
                                        <div class="review-section">
                                            <h5><i class="ri-shield-user-line me-1"></i><?php echo $translations[$language]['nominator_information']; ?> <?php if (!$is_submitted): ?> (<a href="application-step2-international.php"><?php echo $translations[$language]['edit_step_2']; ?></a>)<?php endif; ?></h5>
                                            <?php if (!empty($nominator_details)): ?>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_type']; ?>:</strong> <span><?php echo display_data($nominator_details, 'nominator_type'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_name']; ?>:</strong> <span><?php echo display_data($nominator_details, 'nominator_name'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_address']; ?>:</strong> <span><?php echo display_data($nominator_details, 'nominator_address'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_city']; ?>:</strong> <span><?php echo display_data($nominator_details, 'nominator_city'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_country']; ?>:</strong> <span><?php echo display_data($nominator_details, 'nominator_country'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_phone']; ?>:</strong> <span><?php echo display_data($nominator_details, 'nominator_phone'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_email']; ?>:</strong> <span><?php echo display_data($nominator_details, 'nominator_email'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nominator_relationship']; ?>:</strong> <span><?php echo display_data($nominator_details, 'relationship'); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['nomination_letter']; ?>:</strong> <span><?php echo display_file_link($nominator_details, 'nomination_letter_path', $translations[$language]['nomination_letter']); ?></span></div>
                                            <?php else: ?>
                                                <p><i><?php echo $translations[$language]['no_nominator_info']; ?></i></p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Documents Section -->
                                        <div class="review-section">
                                            <h5><i class="ri-file-upload-line me-1"></i><?php echo $translations[$language]['uploaded_documents']; ?> <?php if (!$is_submitted): ?> (<a href="application-step3-international.php"><?php echo $translations[$language]['edit_step_3']; ?></a>)<?php endif; ?></h5>
                                            <?php if (!empty($document_details)): ?>
                                                <div class="review-item"><strong><?php echo $translations[$language]['passport_scan']; ?>:</strong> <span><?php echo display_file_link($document_details, 'passport_scan_path', $translations[$language]['passport_scan']); ?></span></div>
                                                <div class="review-item"><strong><?php echo $translations[$language]['birth_certificate']; ?>:</strong> <span><?php echo display_file_link($document_details, 'birth_certificate_path', $translations[$language]['birth_certificate']); ?></span></div>
                                            <?php else: ?>
                                                <p><i><?php echo $translations[$language]['no_document_info']; ?></i></p>
                                            <?php endif; ?>
                                        </div>

                                    </div><!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Action Buttons -->
                                <?php if (!$is_submitted && empty($success)): ?>
                                    <div class="alert alert-danger mt-3" role="alert">
                                        <i class="ri-alert-line me-1"></i> <strong><?php echo $translations[$language]['declaration']; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mt-4 mb-4">
                                        <a href="application-step3-international.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['back_to_step_3']; ?></a>
                                        <button type="submit" name="submit_application" class="btn btn-success btn-lg"><i class="ri-send-plane-fill me-1"></i><?php echo $translations[$language]['submit_application']; ?></button>
                                    </div>
                                <?php elseif ($is_submitted): ?>
                                    <div class="text-center mt-4 mb-4">
                                        <a href="index.php" class="btn btn-primary"><i class="ri-home-4-line me-1"></i><?php echo $translations[$language]['back_to_dashboard']; ?></a>
                                    </div>
                                <?php endif; ?>

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

</body>
</html>