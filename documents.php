<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/documents.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

// --- Authentication & Session Management ---
if (!is_logged_in()) {
    set_flash_message($_SESSION['language'] === 'ar' ? "يجب تسجيل الدخول لعرض صفحة الوثائق." : "You must be logged in to view the documents page.", 'error');
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
    error_log("User ID missing from session for logged-in user on documents.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Application Verification ---
global $conn;
$application_id = null;
$contestant_type = null;
$application_status = null;
$current_step = null;

// Verify that the user has an application and is at the correct step
$stmt_app = $conn->prepare("SELECT id, status, current_step, contestant_type FROM applications WHERE user_id = ?");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        $contestant_type = $app['contestant_type'];
        $application_status = $app['status'];
        $current_step = $app['current_step'];

        // --- Redirect International Users ---
        if ($contestant_type === 'international') {
            redirect('application-step3-international.php');
            exit;
        }
        // --- End Redirect ---

        // Check if the user (Nigerian) should be on this step
        $allowed_statuses = ['Sponsor Info Complete', 'Documents Uploaded', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Information Requested'];
        $is_correct_prior_step = ($application_status === 'Sponsor Info Complete');
        $is_on_or_after_this_step = in_array($application_status, $allowed_statuses) && $current_step !== 'step1' && $current_step !== 'step2';

        if (!$is_correct_prior_step && !$is_on_or_after_this_step) {
            set_flash_message($_SESSION['language'] === 'ar' ? "يرجى إكمال الخطوة 2 (معلومات الراعي) قبل المتابعة." : "Please complete Step 2 (Sponsor Information) before proceeding.", 'warning');
            redirect('application-step2-nigerian.php?error=step2_incomplete');
            exit;
        }
    } else {
        set_flash_message($_SESSION['language'] === 'ar' ? "لم يتم العثور على طلب. يرجى البدء من جديد." : "No application found. Please start a new application.", 'error');
        redirect('application.php?error=app_not_found');
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die($_SESSION['language'] === 'ar' ? "خطأ في التحقق من حالة الطلب. يرجى المحاولة لاحقًا." : "Error verifying application status. Please try again later.");
}

// --- Define Required Documents (Nigerian Specific) ---
$required_documents = [
    'en' => [
        'national_id' => 'National ID Card / NIN Slip',
        'birth_certificate' => 'Birth Certificate / Declaration of Age',
        'recommendation_letter' => 'Recommendation Letter from Sponsor',
        'lg_indigene_certificate' => 'LG Indigene Certificate',
    ],
    'ar' => [
        'national_id' => 'بطاقة الهوية الوطنية / وثيقة رقم الهوية الوطنية',
        'birth_certificate' => 'شهادة الميلاد / إقرار العمر',
        'recommendation_letter' => 'خطاب توصية من الراعي',
        'lg_indigene_certificate' => 'شهادة السكان الأصليين للحكومة المحلية',
    ]
];

// --- Fetch Existing Documents ---
$existing_documents = [];
$stmt_docs = $conn->prepare("SELECT id, document_type, file_path, original_filename, created_at FROM application_documents WHERE application_id = ?");
if ($stmt_docs) {
    $stmt_docs->bind_param("i", $application_id);
    $stmt_docs->execute();
    $result_docs = $stmt_docs->get_result();
    while ($doc = $result_docs->fetch_assoc()) {
        if (array_key_exists($doc['document_type'], $required_documents[$language])) {
            $existing_documents[$doc['document_type']] = $doc;
        }
    }
    $stmt_docs->close();
} else {
    error_log("Failed to prepare statement for fetching existing documents: " . $conn->error);
}

// --- File Upload Configuration ---
$upload_dir = 'Uploads/documents/';
$allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// --- Language-Specific Strings ---
$translations = [
    'en' => [
        'page_title' => 'Application: Document Upload (Nigerian) | Musabaqa',
        'page_header' => 'Application - Step 3: Document Upload (Nigerian Applicants)',
        'dashboard' => 'Dashboard',
        'application' => 'Application',
        'step3' => 'Step 3',
        'required_documents' => 'Required Documents (Nigerian Applicants)',
        'instructions' => 'Please upload clear copies of the following documents. Allowed file types: PDF, DOC, DOCX, JPG, PNG. Max size: 5MB per file.',
        'back_button' => 'Back to Sponsor Info',
        'save_documents' => 'Save Documents',
        'save_continue' => 'Save and Continue to Review',
        'error_invalid_form' => 'Invalid form submission. Please try again.',
        'error_upload_dir' => 'Failed to create upload directory. Please contact support.',
        'error_invalid_file_type' => 'Invalid file type for %s. Allowed: %s.',
        'error_file_too_large' => 'File size for %s exceeds the limit (5MB).',
        'error_upload_failed' => 'Failed to move uploaded file for %s. Check permissions.',
        'error_upload_error' => 'Error uploading %s: Code %s.',
        'error_processing' => 'An error occurred while saving documents. Please try again.',
        'success_uploaded' => 'Documents uploaded successfully.',
        'error_step2_incomplete' => 'Please complete Step 2 (Sponsor Information) before proceeding.',
        'error_app_not_found' => 'No application found. Please start a new application.',
        'error_app_status' => 'Error verifying application status. Please try again later.',
    ],
    'ar' => [
        'page_title' => 'الطلب: رفع الوثائق (نيجيري) | المسابقة',
        'page_header' => 'الطلب - الخطوة 3: رفع الوثائق (المتقدمون النيجيريون)',
        'dashboard' => 'لوحة التحكم',
        'application' => 'الطلب',
        'step3' => 'الخطوة 3',
        'required_documents' => 'الوثائق المطلوبة (المتقدمون النيجيريون)',
        'instructions' => 'يرجى رفع نسخ واضحة من الوثائق التالية. أنواع الملفات المسموح بها: PDF، DOC، DOCX، JPG، PNG. الحد الأقصى للحجم: 5 ميجابايت لكل ملف.',
        'back_button' => 'العودة إلى معلومات الراعي',
        'save_documents' => 'حفظ الوثائق',
        'save_continue' => 'حفظ ومتابعة إلى المراجعة',
        'error_invalid_form' => 'إرسال النموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'error_upload_dir' => 'فشل إنشاء دليل الرفع. يرجى التواصل مع الدعم.',
        'error_invalid_file_type' => 'نوع الملف غير صالح لـ %s. المسموح: %s.',
        'error_file_too_large' => 'حجم الملف لـ %s يتجاوز الحد (5 ميجابايت).',
        'error_upload_failed' => 'فشل نقل الملف المرفوع لـ %s. تحقق من الأذونات.',
        'error_upload_error' => 'خطأ في رفع %s: الرمز %s.',
        'error_processing' => 'حدث خطأ أثناء حفظ الوثائق. يرجى المحاولة مرة أخرى.',
        'success_uploaded' => 'تم رفع الوثائق بنجاح.',
        'error_step2_incomplete' => 'يرجى إكمال الخطوة 2 (معلومات الراعي) قبل المتابعة.',
        'error_app_not_found' => 'لم يتم العثور على طلب. يرجى بدء طلب جديد.',
        'error_app_status' => 'خطأ في التحقق من حالة الطلب. يرجى المحاولة لاحقًا.',
    ]
];

// --- Form Processing ---
$errors = [];
$success = '';
$files_uploaded_in_request = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = $translations[$language]['error_invalid_form'];
    } else {
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors['form'] = $translations[$language]['error_upload_dir'];
                goto end_of_post_processing;
            }
        }

        $conn->begin_transaction();

        try {
            foreach ($required_documents[$language] as $doc_type => $doc_label) {
                if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$doc_type];
                    $file_name = $file['name'];
                    $file_tmp_path = $file['tmp_name'];
                    $file_size = $file['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $original_filename = sanitize_filename($file_name);

                    if (!in_array($file_ext, $allowed_types)) {
                        $errors[$doc_type] = sprintf($translations[$language]['error_invalid_file_type'], $doc_label, implode(', ', $allowed_types));
                        continue;
                    }
                    if ($file_size > $max_file_size) {
                        $errors[$doc_type] = sprintf($translations[$language]['error_file_too_large'], $doc_label);
                        continue;
                    }

                    $unique_filename = "user_{$user_id}_app_{$application_id}_doc_{$doc_type}_" . uniqid() . '.' . $file_ext;
                    $destination = $upload_dir . $unique_filename;

                    if (move_uploaded_file($file_tmp_path, $destination)) {
                        $files_uploaded_in_request[$doc_type] = $destination;

                        $stmt_check_doc = $conn->prepare("SELECT id, file_path FROM application_documents WHERE application_id = ? AND document_type = ?");
                        if (!$stmt_check_doc) throw new Exception("Prepare failed (Check Doc): " . $conn->error);
                        $stmt_check_doc->bind_param("is", $application_id, $doc_type);
                        $stmt_check_doc->execute();
                        $result_check_doc = $stmt_check_doc->get_result();
                        $existing_doc_record = $result_check_doc->fetch_assoc();
                        $stmt_check_doc->close();

                        $old_file_path = null;
                        if ($existing_doc_record) {
                            $old_file_path = $existing_doc_record['file_path'];
                            $stmt_save_doc = $conn->prepare("UPDATE application_documents SET file_path = ?, original_filename = ?, updated_at = NOW() WHERE id = ?");
                            if (!$stmt_save_doc) throw new Exception("Prepare failed (Update Doc): " . $conn->error);
                            $stmt_save_doc->bind_param("ssi", $destination, $original_filename, $existing_doc_record['id']);
                        } else {
                            $stmt_save_doc = $conn->prepare("INSERT INTO application_documents (application_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)");
                            if (!$stmt_save_doc) throw new Exception("Prepare failed (Insert Doc): " . $conn->error);
                            $stmt_save_doc->bind_param("isss", $application_id, $doc_type, $destination, $original_filename);
                        }

                        if (!$stmt_save_doc->execute()) {
                            throw new Exception("Execute failed (Save Doc): " . $stmt_save_doc->error);
                        }
                        $stmt_save_doc->close();

                        if ($old_file_path && file_exists($old_file_path) && $old_file_path !== $destination) {
                            @unlink($old_file_path);
                        }

                        $existing_documents[$doc_type] = [
                            'id' => $existing_doc_record['id'] ?? $conn->insert_id,
                            'document_type' => $doc_type,
                            'file_path' => $destination,
                            'original_filename' => $original_filename,
                            'created_at' => $existing_doc_record['created_at'] ?? date('Y-m-d H:i:s')
                        ];
                    } else {
                        $errors[$doc_type] = sprintf($translations[$language]['error_upload_failed'], $doc_label);
                    }
                } elseif (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errors[$doc_type] = sprintf($translations[$language]['error_upload_error'], $doc_label, $_FILES[$doc_type]['error']);
                }
            }

            $all_required_uploaded = true;
            foreach (array_keys($required_documents[$language]) as $req_doc_type) {
                if (!isset($existing_documents[$req_doc_type])) {
                    $all_required_uploaded = false;
                    break;
                }
            }

            if ($all_required_uploaded && $application_status === 'Sponsor Info Complete') {
                $new_status = 'Documents Uploaded';
                $next_step = 'review';
                $stmt_update_app = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ?");
                if (!$stmt_update_app) throw new Exception("Prepare failed (App Update): " . $conn->error);
                $stmt_update_app->bind_param("ssi", $new_status, $next_step, $application_id);
                if (!$stmt_update_app->execute()) {
                    throw new Exception("Execute failed (App Update): " . $stmt_update_app->error);
                }
                $stmt_update_app->close();
                $application_status = $new_status;
            } elseif (!empty($files_uploaded_in_request)) {
                $stmt_update_time = $conn->prepare("UPDATE applications SET last_updated = NOW() WHERE id = ?");
                if (!$stmt_update_time) throw new Exception("Prepare failed (App Time Update): " . $conn->error);
                $stmt_update_time->bind_param("i", $application_id);
                $stmt_update_time->execute();
                $stmt_update_time->close();
            }

            $conn->commit();
            if (!empty($files_uploaded_in_request) && empty($errors)) {
                $success = $translations[$language]['success_uploaded'];
            }

            if ($all_required_uploaded && $application_status === 'Documents Uploaded') {
                redirect('application-review.php');
                exit;
            }

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error processing documents for app ID {$application_id}: " . $e->getMessage());
            $errors['form'] = $translations[$language]['error_processing'];

            foreach ($files_uploaded_in_request as $doc_type => $filepath) {
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
            }
        }

        end_of_post_processing:;
    }
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
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
        .form-control.is-invalid { border-color: #dc3545; }
        .invalid-feedback { display: block; color: #dc3545; font-size: 0.875em; }
        .form-label { font-weight: 500; }
        .document-list-item { border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 1rem; }
        .document-list-item:last-child { border-bottom: none; }
        .file-info { font-size: 0.9em; color: #6c757d; }
        .upload-section { margin-bottom: 1.5rem; }
        <?php if ($is_rtl): ?>
        .form-label { text-align: right; }
        .invalid-feedback { text-align: right; }
        .text-muted { text-align: right; }
        .d-flex.justify-content-between { flex-direction: row-reverse; }
        .col-md-4, .col-md-8 { text-align: right; }
        .ri-check-double-line { margin-left: 0.25rem; margin-right: 0.5rem; }
        .file-info.ms-2 { margin-right: 0.5rem; margin-left: 0; }
        .btn i.ri-arrow-left-line { margin-left: 0.25rem; margin-right: -0.25rem; }
        .btn i.ri-arrow-right-line, .btn i.ri-save-line { margin-right: 0.25rem; margin-left: 0.5rem; }
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
                                        <li class="breadcrumb-item"><a href="application.php"><?php echo $translations[$language]['application']; ?></a></li>
                                        <li class="breadcrumb-item active"><?php echo $translations[$language]['step3']; ?></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

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
                    <?php if (isset($_GET['error']) && $_GET['error'] === 'step2_incomplete'): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="ri-alert-line me-1"></i> <?php echo $translations[$language]['error_step2_incomplete']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><?php echo $translations[$language]['required_documents']; ?></h5>
                                    <p class="text-muted mb-4"><?php echo $translations[$language]['instructions']; ?></p>

                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <?php foreach ($required_documents[$language] as $doc_type => $doc_label): ?>
                                            <div class="upload-section document-list-item">
                                                <div class="row align-items-center">
                                                    <div class="col-md-4">
                                                        <label for="<?php echo $doc_type; ?>" class="form-label"><?php echo htmlspecialchars($doc_label); ?> <span class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <?php if (isset($existing_documents[$doc_type])):
                                                            $doc = $existing_documents[$doc_type];
                                                            $file_url = htmlspecialchars($doc['file_path']);
                                                        ?>
                                                            <div class="mb-2">
                                                                <i class="ri-check-double-line text-success me-1"></i> <?php echo $language === 'ar' ? 'تم الرفع:' : 'Uploaded:'; ?>
                                                                <a href="<?php echo $file_url; ?>" target="_blank" title="<?php echo $language === 'ar' ? 'عرض' : 'View'; ?> <?php echo htmlspecialchars($doc['original_filename']); ?>">
                                                                    <?php echo htmlspecialchars($doc['original_filename']); ?>
                                                                </a>
                                                                <span class="file-info ms-2">(<?php echo $language === 'ar' ? 'تم الرفع:' : 'Uploaded:'; ?> <?php echo date($language === 'ar' ? 'd M Y H:i' : 'M d, Y H:i', strtotime($doc['created_at'])); ?>)</span>
                                                            </div>
                                                            <label for="<?php echo $doc_type; ?>" class="form-label text-muted small"><?php echo $language === 'ar' ? 'استبدال الملف (اختياري):' : 'Replace file (optional):'; ?></label>
                                                        <?php endif; ?>
                                                        <input type="file" class="form-control <?php echo isset($errors[$doc_type]) ? 'is-invalid' : ''; ?>" id="<?php echo $doc_type; ?>" name="<?php echo $doc_type; ?>" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                        <?php if (isset($errors[$doc_type])): ?>
                                                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors[$doc_type]); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <div class="mt-4 d-flex justify-content-between">
                                            <?php
                                            $prev_step_page = 'application-step2-nigerian.php';
                                            ?>
                                            <a href="<?php echo $prev_step_page; ?>" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> <?php echo $translations[$language]['back_button']; ?></a>

                                            <?php
                                            $all_required_uploaded_final = true;
                                            foreach (array_keys($required_documents[$language]) as $req_doc_type) {
                                                if (!isset($existing_documents[$req_doc_type])) {
                                                    $all_required_uploaded_final = false;
                                                    break;
                                                }
                                            }
                                            $button_text = $all_required_uploaded_final ? $translations[$language]['save_continue'] : $translations[$language]['save_documents'];
                                            $button_icon = $all_required_uploaded_final ? "ri-arrow-right-line" : "ri-save-line";
                                            ?>
                                            <button type="submit" class="btn btn-primary"><?php echo $button_text; ?> <i class="<?php echo $button_icon; ?> ms-1"></i></button>
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
    <script src="assets/js/app.min.js"></script>
</body>
</html>