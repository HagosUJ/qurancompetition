<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-review.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

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
if (!$user_id) {
    error_log("User ID missing from session for logged-in user on application-review.php.");
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
        'page_title' => 'Application: Review & Submit | Musabaqa',
        'page_header' => 'Application - Step 4: Review & Submit',
        'dashboard' => 'Dashboard',
        'application' => 'Application',
        'review' => 'Review',
        'personal_info' => 'Personal Information',
        'sponsor_info' => 'Sponsor/Nominator Information',
        'uploaded_documents' => 'Uploaded Documents',
        'edit' => 'Edit',
        'full_name' => 'Full Name',
        'dob' => 'Date of Birth',
        'age' => 'Age',
        'address' => 'Address',
        'state_of_origin' => 'State of Origin',
        'lga_of_origin' => 'LGA of Origin',
        'nationality' => 'Nationality',
        'passport_number' => 'Passport Number',
        'passport_expiry' => 'Passport Expiry',
        'phone_number' => 'Phone Number',
        'email_address' => 'Email Address',
        'health_status' => 'Health Status',
        'languages_spoken' => 'Languages Spoken',
        'category' => 'Category',
        'category_qiraat' => 'Qira\'at (Males)',
        'category_hifz' => 'Hifz (Females)',
        'narration' => 'Narration (Riwayah)',
        'passport_photo' => 'Passport Photo',
        'view_photo' => 'View Photo',
        'sponsor_name' => 'Sponsor Name',
        'sponsor_address' => 'Sponsor Address',
        'sponsor_phone' => 'Sponsor Phone',
        'sponsor_email' => 'Sponsor Email',
        'sponsor_occupation' => 'Sponsor Occupation',
        'sponsor_relationship' => 'Relationship',
        'national_id' => 'National ID Card / Passport Data Page',
        'birth_certificate' => 'Birth Certificate / Declaration of Age',
        'recommendation_letter' => 'Recommendation Letter from Sponsor/Nominator',
        'missing' => 'Missing',
        'uploaded_at' => 'Uploaded: %s',
        'ready_to_submit' => 'Ready to Submit?',
        'review_instructions' => 'Please review all information carefully. Once submitted, you may not be able to make changes.',
        'cannot_submit_docs' => 'You cannot submit until all required documents are uploaded.',
        'submit_application' => 'Submit Application',
        'back_to_documents' => 'Back to Documents',
        'back_to_dashboard' => 'Back to Dashboard',
        'error_invalid_submission' => 'Invalid form submission. Please try again.',
        'error_already_submitted' => 'This application has already been submitted.',
        'error_not_ready' => 'Application is not ready for submission. Current status: %s',
        'error_submission_failed' => 'An error occurred during submission. Please try again.',
        'error_docs_incomplete' => 'Please complete Step 3 (Document Upload) before reviewing.',
        'warning_missing_docs' => 'Some required documents are missing. Please upload them before submitting.',
        'info_submitted' => 'This application has been submitted and is under review.',
        'success_submitted' => 'Application submitted successfully!',
    ],
    'ar' => [
        'page_title' => 'الطلب: المراجعة والإرسال | المسابقة',
        'page_header' => 'الطلب - الخطوة الرابعة: المراجعة والإرسال',
        'dashboard' => 'لوحة التحكم',
        'application' => 'الطلب',
        'review' => 'المراجعة',
        'personal_info' => 'المعلومات الشخصية',
        'sponsor_info' => 'معلومات الراعي/المرشح',
        'uploaded_documents' => 'الوثائق المرفوعة',
        'edit' => 'تعديل',
        'full_name' => 'الاسم الكامل',
        'dob' => 'تاريخ الميلاد',
        'age' => 'العمر',
        'address' => 'العنوان',
        'state_of_origin' => 'الولاية الأصلية',
        'lga_of_origin' => 'منطقة الحكم المحلي الأصلية',
        'nationality' => 'الجنسية',
        'passport_number' => 'رقم جواز السفر',
        'passport_expiry' => 'تاريخ انتهاء جواز السفر',
        'phone_number' => 'رقم الهاتف',
        'email_address' => 'البريد الإلكتروني',
        'health_status' => 'الحالة الصحية',
        'languages_spoken' => 'اللغات المتحدث بها',
        'category' => 'الفئة',
        'category_qiraat' => 'القراءات (الذكور)',
        'category_hifz' => 'الحفظ (الإناث)',
        'narration' => 'الرواية',
        'passport_photo' => 'صورة جواز السفر',
        'view_photo' => 'عرض الصورة',
        'sponsor_name' => 'اسم الراعي',
        'sponsor_address' => 'عنوان الراعي',
        'sponsor_phone' => 'هاتف الراعي',
        'sponsor_email' => 'بريد الراعي الإلكتروني',
        'sponsor_occupation' => 'مهنة الراعي',
        'sponsor_relationship' => 'العلاقة',
        'national_id' => 'بطاقة الهوية الوطنية / صفحة بيانات جواز السفر',
        'birth_certificate' => 'شهادة الميلاد / إقرار العمر',
        'recommendation_letter' => 'خطاب توصية من الراعي/المرشح',
        'missing' => 'مفقود',
        'uploaded_at' => 'تم الرفع: %s',
        'ready_to_submit' => 'هل أنت جاهز للإرسال؟',
        'review_instructions' => 'يرجى مراجعة جميع المعلومات بعناية. بمجرد الإرسال، قد لا تتمكن من إجراء تغييرات.',
        'cannot_submit_docs' => 'لا يمكنك الإرسال حتى يتم رفع جميع الوثائق المطلوبة.',
        'submit_application' => 'إرسال الطلب',
        'back_to_documents' => 'العودة إلى الوثائق',
        'back_to_dashboard' => 'العودة إلى لوحة التحكم',
        'error_invalid_submission' => 'إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'error_already_submitted' => 'تم إرسال هذا الطلب بالفعل.',
        'error_not_ready' => 'الطلب غير جاهز للإرسال. الحالة الحالية: %s',
        'error_submission_failed' => 'حدث خطأ أثناء الإرسال. يرجى المحاولة مرة أخرى.',
        'error_docs_incomplete' => 'يرجى إكمال الخطوة الثالثة (رفع الوثائق) قبل المراجعة.',
        'warning_missing_docs' => 'بعض الوثائق المطلوبة مفقودة. يرجى رفعها قبل الإرسال.',
        'info_submitted' => 'تم إرسال هذا الطلب وهو قيد المراجعة.',
        'success_submitted' => 'تم إرسال الطلب بنجاح!',
    ]
];

// --- Application Verification & Data Fetching ---
global $conn;
$application_id = null;
$contestant_type = null;
$application_status = null;
$application_data = [];
$sponsor_data = [];
$documents_data = [];
$required_documents = [];
$all_required_docs_uploaded = false;

// Define required documents with translated labels
$required_documents = [
    'national_id' => $translations[$language]['national_id'],
    'birth_certificate' => $translations[$language]['birth_certificate'],
    'recommendation_letter' => $translations[$language]['recommendation_letter'],
];

// Verify application and get basic info
$stmt_app = $conn->prepare("SELECT id, status, current_step, contestant_type FROM applications WHERE user_id = ?");
if (!$stmt_app) {
    error_log("Prepare failed (App Check): " . $conn->error);
    die($translations[$language]['error_submission_failed']);
}
$stmt_app->bind_param("i", $user_id);
$stmt_app->execute();
$result_app = $stmt_app->get_result();
if ($app = $result_app->fetch_assoc()) {
    $application_id = $app['id'];
    $contestant_type = $app['contestant_type'];
    $application_status = $app['status'];

    if (!in_array($application_status, ['Documents Uploaded', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Information Requested'])) {
        redirect('documents.php?error=docs_incomplete');
        exit;
    }

    $details_table = ($contestant_type === 'nigerian') ? 'application_details_nigerian' : 'application_details_international';
    $stmt_details = $conn->prepare("SELECT * FROM {$details_table} WHERE application_id = ?");
    if ($stmt_details) {
        $stmt_details->bind_param("i", $application_id);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();
        if ($result_details->num_rows > 0) {
            $application_data = $result_details->fetch_assoc();
        }
        $stmt_details->close();
    } else {
        error_log("Failed to prepare statement for fetching {$details_table}: " . $conn->error);
        $errors['form'] = $translations[$language]['error_submission_failed'];
    }

    $sponsor_table = ($contestant_type === 'nigerian') ? 'application_sponsor_details_nigerian' : 'application_sponsor_details_international';
    $stmt_sponsor = $conn->prepare("SELECT * FROM {$sponsor_table} WHERE application_id = ?");
    if ($stmt_sponsor) {
        $stmt_sponsor->bind_param("i", $application_id);
        $stmt_sponsor->execute();
        $result_sponsor = $stmt_sponsor->get_result();
        if ($result_sponsor->num_rows > 0) {
            $sponsor_data = $result_sponsor->fetch_assoc();
        }
        $stmt_sponsor->close();
    } else {
        error_log("Failed to prepare statement for fetching {$sponsor_table}: " . $conn->error);
        $errors['form'] = $translations[$language]['error_submission_failed'];
    }

    $stmt_docs = $conn->prepare("SELECT id, document_type, file_path, original_filename, created_at FROM application_documents WHERE application_id = ?");
    if ($stmt_docs) {
        $stmt_docs->bind_param("i", $application_id);
        $stmt_docs->execute();
        $result_docs = $stmt_docs->get_result();
        while ($doc = $result_docs->fetch_assoc()) {
            $documents_data[$doc['document_type']] = $doc;
        }
        $stmt_docs->close();
    } else {
        error_log("Failed to prepare statement for fetching documents: " . $conn->error);
        $errors['form'] = $translations[$language]['error_submission_failed'];
    }

    $all_required_docs_uploaded = true;
    foreach (array_keys($required_documents) as $req_doc_type) {
        if (!isset($documents_data[$req_doc_type])) {
            $all_required_docs_uploaded = false;
            break;
        }
    }
} else {
    redirect('application.php?error=app_not_found');
    exit;
}
$stmt_app->close();

// --- Form Processing (Submission) ---
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = $translations[$language]['error_invalid_submission'];
    } elseif ($application_status === 'Submitted') {
        $errors['form'] = $translations[$language]['error_already_submitted'];
    } elseif (!$all_required_docs_uploaded) {
        $errors['form'] = $translations[$language]['cannot_submit_docs'];
    } elseif ($application_status !== 'Documents Uploaded') {
        $errors['form'] = sprintf($translations[$language]['error_not_ready'], htmlspecialchars($application_status));
    } else {
        try {
            $conn->begin_transaction();

            $new_status = 'Submitted';
            $current_step = 'submitted';
            $stmt_submit = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ? AND status = 'Documents Uploaded'");
            if (!$stmt_submit) throw new Exception("Prepare failed (Submit App): " . $conn->error);

            $stmt_submit->bind_param("ssi", $new_status, $current_step, $application_id);

            if (!$stmt_submit->execute()) {
                throw new Exception("Execute failed (Submit App): " . $stmt_submit->error);
            }

            if ($stmt_submit->affected_rows === 0) {
                throw new Exception("Application status was not 'Documents Uploaded' or application ID not found during submission attempt.");
            }

            $stmt_submit->close();
            $conn->commit();

            $application_status = $new_status;
            $success = $translations[$language]['success_submitted'];

            redirect('index.php?submission=success');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error submitting application for app ID {$application_id}: " . $e->getMessage());
            $errors['form'] = $translations[$language]['error_submission_failed'];
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                $errors['form'] .= " Details: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-XSS-Protection: 1; mode=block");

// Helper function to display data safely
function display_data($data, $key, $default = 'N/A') {
    global $language, $translations;
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : $translations[$language]['missing'];
}
function display_date($data, $key, $format = 'M d, Y', $default = 'N/A') {
    global $language, $translations;
    return isset($data[$key]) && $data[$key] !== '' ? date($format, strtotime($data[$key])) : $translations[$language]['missing'];
}

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
        .review-section dt { font-weight: 500; color: #495057; }
        .review-section dd { margin-bottom: 0.75rem; color: #6c757d; }
        .review-section .card-header { background-color: #f8f9fa; }
        .missing-doc { color: #dc3545; font-style: italic; }
        .edit-link { font-size: 0.85em; margin-left: 10px; }
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
                                        <li class="breadcrumb-item active"><?php echo $translations[$language]['review']; ?></li>
                                    </ol>
                                </div>
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
                    <?php if (isset($_GET['error']) && $_GET['error'] === 'docs_incomplete'): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="ri утесть
                            <i class="ri-alert-line me-1"></i> <?php echo $translations[$language]['error_docs_incomplete']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!$all_required_docs_uploaded && $application_status !== 'Submitted'): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="ri-alert-line me-1"></i> <?php echo $translations[$language]['warning_missing_docs']; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Review Sections -->
                    <div class="row review-section">

                        <!-- Personal Information -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="ri-user-line me-1"></i><?php echo $translations[$language]['personal_info']; ?></h5>
                                    <?php if ($application_status !== 'Submitted'): ?>
                                        <a href="<?php echo ($contestant_type === 'nigerian' ? 'application-step1-nigerian.php' : 'application-step1-international.php'); ?>" class="edit-link"><i class="ri-pencil-line me-1"></i><?php echo $translations[$language]['edit']; ?></a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <dl class="row">
                                        <dt class="col-sm-5"><?php echo $translations[$language]['full_name']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, ($contestant_type === 'nigerian' ? 'full_name_nid' : 'full_name_passport')); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['dob']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_date($application_data, 'dob'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['age']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'age'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['address']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'address'); ?></dd>

                                        <?php if ($contestant_type === 'nigerian'): ?>
                                            <dt class="col-sm-5"><?php echo $translations[$language]['state_of_origin']; ?>:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'state'); ?></dd>
                                            <dt class="col-sm-5"><?php echo $translations[$language]['lga_of_origin']; ?>:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'lga'); ?></dd>
                                        <?php else: ?>
                                            <dt class="col-sm-5"><?php echo $translations[$language]['nationality']; ?>:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'nationality'); ?></dd>
                                            <dt class="col-sm-5"><?php echo $translations[$language]['passport_number']; ?>:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'passport_number'); ?></dd>
                                            <dt class="col-sm-5"><?php echo $translations[$language]['passport_expiry']; ?>:</dt>
                                            <dd class="col-sm-7"><?php echo display_date($application_data, 'passport_expiry'); ?></dd>
                                        <?php endif; ?>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['phone_number']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'phone_number'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['email_address']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'email'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['health_status']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'health_status'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['languages_spoken']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'languages_spoken'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['category']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'category') === 'qiraat' ? $translations[$language]['category_qiraat'] : $translations[$language]['category_hifz']; ?></dd>

                                        <?php if (($application_data['category'] ?? '') === 'hifz'): ?>
                                            <dt class="col-sm-5"><?php echo $translations[$language]['narration']; ?>:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'narration'); ?></dd>
                                        <?php endif; ?>

                                        <?php if (!empty($application_data['photo_path']) && file_exists($application_data['photo_path'])): ?>
                                            <dt class="col-sm-5"><?php echo $translations[$language]['passport_photo']; ?>:</dt>
                                            <dd class="col-sm-7"><a href="<?php echo htmlspecialchars($application_data['photo_path']); ?>" target="_blank"><?php echo $translations[$language]['view_photo']; ?></a></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            </div>
                        </div><!-- /col -->

                        <!-- Sponsor Information -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="ri-user-star-line me-1"></i><?php echo $translations[$language]['sponsor_info']; ?></h5>
                                    <?php if ($application_status !== 'Submitted'): ?>
                                        <a href="<?php echo ($contestant_type === 'nigerian' ? 'application-step2-nigerian.php' : 'application-step2-international.php'); ?>" class="edit-link"><i class="ri-pencil-line me-1"></i><?php echo $translations[$language]['edit']; ?></a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <dl class="row">
                                        <dt class="col-sm-5"><?php echo $translations[$language]['sponsor_name']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_name'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['sponsor_address']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_address'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['sponsor_phone']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_phone'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['sponsor_email']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_email'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['sponsor_occupation']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_occupation'); ?></dd>

                                        <dt class="col-sm-5"><?php echo $translations[$language]['sponsor_relationship']; ?>:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_relationship'); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div><!-- /col -->

                    </div><!-- /row -->

                    <div class="row review-section">
                        <!-- Uploaded Documents -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="ri-file-list-3-line me-1"></i><?php echo $translations[$language]['uploaded_documents']; ?></h5>
                                    <?php if ($application_status !== 'Submitted'): ?>
                                        <a href="documents.php" class="edit-link"><i class="ri-pencil-line me-1"></i><?php echo $translations[$language]['edit']; ?></a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <dl class="row">
                                        <?php foreach ($required_documents as $doc_type => $doc_label): ?>
                                            <dt class="col-sm-5"><?php echo htmlspecialchars($doc_label); ?>:</dt>
                                            <dd class="col-sm-7">
                                                <?php if (isset($documents_data[$doc_type])):
                                                    $doc = $documents_data[$doc_type];
                                                ?>
                                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars($doc['original_filename']); ?>
                                                    </a>
                                                    <span class="text-muted small ms-2"><?php echo sprintf($translations[$language]['uploaded_at'], display_date($doc, 'created_at', 'M d, Y H:i')); ?></span>
                                                <?php else: ?>
                                                    <span class="missing-doc"><?php echo $translations[$language]['missing']; ?></span>
                                                <?php endif; ?>
                                            </dd>
                                        <?php endforeach; ?>
                                    </dl>
                                </div>
                            </div>
                        </div><!-- /col -->
                    </div><!-- /row -->

                    <!-- Submission Area -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <?php if ($application_status === 'Submitted'): ?>
                                <div class="alert alert-info" role="alert">
                                    <i class="ri-information-line me-1"></i> <?php echo $translations[$language]['info_submitted']; ?>
                                </div>
                                <div class="d-flex justify-content-start">
                                    <a href="index.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['back_to_dashboard']; ?></a>
                                </div>
                            <?php elseif ($application_status === 'Documents Uploaded'): ?>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title"><?php echo $translations[$language]['ready_to_submit']; ?></h5>
                                        <p class="text-muted"><?php echo $translations[$language]['review_instructions']; ?></p>
                                        <?php if (!$all_required_docs_uploaded): ?>
                                            <p class="text-danger fw-bold"><?php echo $translations[$language]['cannot_submit_docs']; ?></p>
                                        <?php endif; ?>
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <a href="documents.php" class="btn btn-secondary me-2"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['back_to_documents']; ?></a>
                                            <button type="submit" class="btn btn-success btn-lg" <?php echo !$all_required_docs_uploaded ? 'disabled' : ''; ?>>
                                                <i class="ri-check-double-line me-1"></i><?php echo $translations[$language]['submit_application']; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning" role="alert">
                                    <i class="ri-alert-line me-1"></i> <?php echo sprintf($translations[$language]['error_not_ready'], htmlspecialchars($application_status)); ?>
                                </div>
                                <div class="d-flex justify-content-start">
                                    <a href="documents.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['back_to_documents']; ?></a>
                                </div>
                            <?php endif; ?>
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