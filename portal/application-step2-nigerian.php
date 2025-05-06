<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step2-nigerian.php
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
    error_log("User ID missing from session for logged-in user on application-step2-nigerian.php.");
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
        'page_title' => 'Application: Sponsor Information (Nigerian) | Musabaqa',
        'page_header' => 'Application - Step 2: Sponsor/Nominator Information',
        'dashboard' => 'Dashboard',
        'application' => 'Application',
        'step_2' => 'Step 2',
        'sponsor_details' => 'Sponsor/Nominator Details',
        'sponsor_instructions' => 'Provide the information for the person sponsoring or nominating you for the competition.',
        'sponsor_name' => 'Full Name',
        'sponsor_name_required' => 'Sponsor/Nominator Name is required.',
        'sponsor_occupation' => 'Occupation',
        'sponsor_occupation_required' => 'Sponsor/Nominator Occupation is required.',
        'sponsor_address' => 'Address',
        'sponsor_address_required' => 'Sponsor/Nominator Address is required.',
        'sponsor_phone' => 'Phone Number',
        'sponsor_phone_required' => 'Sponsor/Nominator Phone Number is required.',
        'sponsor_phone_invalid' => 'Invalid Phone Number format.',
        'sponsor_email' => 'Email',
        'sponsor_email_invalid' => 'Valid Sponsor/Nominator Email is required.',
        'sponsor_relationship' => 'Relationship to Contestant',
        'sponsor_relationship_required' => 'Relationship to Contestant is required.',
        'sponsor_relationship_placeholder' => 'e.g., Teacher, Parent, Guardian, Community Leader',
        'back_to_personal_info' => 'Back to Personal Info',
        'save_continue' => 'Save and Continue to Documents',
        'error_invalid_submission' => 'Invalid form submission. Please try again.',
        'error_save' => 'An error occurred while saving sponsor information. Please try again.',
        'error_step1_incomplete' => 'Please complete Step 1 (Personal Information) before proceeding.',
        'success_save' => 'Sponsor/Nominator information saved successfully.',
    ],
    'ar' => [
        'page_title' => 'الطلب: معلومات الراعي/المرشح (نيجيري) | المسابقة',
        'page_header' => 'الطلب - الخطوة الثانية: معلومات الراعي/المرشح',
        'dashboard' => 'لوحة التحكم',
        'application' => 'الطلب',
        'step_2' => 'الخطوة الثانية',
        'sponsor_details' => 'تفاصيل الراعي/المرشح',
        'sponsor_instructions' => 'قدم معلومات الشخص الذي يرعاك أو يرشحك للمسابقة.',
        'sponsor_name' => 'الاسم الكامل',
        'sponsor_name_required' => 'اسم الراعي/المرشح مطلوب.',
        'sponsor_occupation' => 'المهنة',
        'sponsor_occupation_required' => 'مهنة الراعي/المرشح مطلوبة.',
        'sponsor_address' => 'العنوان',
        'sponsor_address_required' => 'عنوان الراعي/المرشح مطلوب.',
        'sponsor_phone' => 'رقم الهاتف',
        'sponsor_phone_required' => 'رقم هاتف الراعي/المرشح مطلوب.',
        'sponsor_phone_invalid' => 'تنسيق رقم الهاتف غير صالح.',
        'sponsor_email' => 'البريد الإلكتروني',
        'sponsor_email_invalid' => 'البريد الإلكتروني الصالح للراعي/المرشح مطلوب.',
        'sponsor_relationship' => 'العلاقة بالمتسابق',
        'sponsor_relationship_required' => 'العلاقة بالمتسابق مطلوبة.',
        'sponsor_relationship_placeholder' => 'مثال: معلم، والد، وصي، قائد مجتمع',
        'back_to_personal_info' => 'العودة إلى المعلومات الشخصية',
        'save_continue' => 'حفظ والمتابعة إلى الوثائق',
        'error_invalid_submission' => 'إرسال نموذج غير صالح. يرجى المحاولة مرة أخرى.',
        'error_save' => 'حدث خطأ أثناء حفظ معلومات الراعي. يرجى المحاولة مرة أخرى.',
        'error_step1_incomplete' => 'يرجى إكمال الخطوة الأولى (المعلومات الشخصية) قبل المتابعة.',
        'success_save' => 'تم حفظ معلومات الراعي/المرشح بنجاح.',
    ]
];

// --- Application Verification ---
global $conn;
$application_id = null;
$sponsor_data = [];

$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'nigerian'");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        if (!in_array($app['status'], ['Personal Info Complete', 'Sponsor Info Complete', 'Documents Uploaded', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Information Requested']) || ($app['status'] === 'Not Started' && $app['current_step'] !== 'step2')) {
            redirect('application-step1-nigerian.php?error=step1_incomplete');
            exit;
        }

        $stmt_details = $conn->prepare("SELECT * FROM application_sponsor_details_nigerian WHERE application_id = ?");
        if ($stmt_details) {
            $stmt_details->bind_param("i", $application_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                $sponsor_data = $result_details->fetch_assoc();
            }
            $stmt_details->close();
        } else {
            error_log("Failed to prepare statement for fetching Nigerian sponsor details: " . $conn->error);
            $errors['form'] = $translations[$language]['error_save'];
        }
    } else {
        redirect('application.php?error=app_not_found_or_mismatch');
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die($translations[$language]['error_save']);
}

// --- Form Processing ---
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = $translations[$language]['error_invalid_submission'];
    } else {
        $sponsor_name = sanitize_input($_POST['sponsor_name'] ?? '');
        $sponsor_address = sanitize_input($_POST['sponsor_address'] ?? '');
        $sponsor_phone = sanitize_input($_POST['sponsor_phone'] ?? '');
        $sponsor_email = filter_input(INPUT_POST, 'sponsor_email', FILTER_VALIDATE_EMAIL);
        $sponsor_occupation = sanitize_input($_POST['sponsor_occupation'] ?? '');
        $sponsor_relationship = sanitize_input($_POST['sponsor_relationship'] ?? '');

        if (empty($sponsor_name)) $errors['sponsor_name'] = $translations[$language]['sponsor_name_required'];
        if (empty($sponsor_address)) $errors['sponsor_address'] = $translations[$language]['sponsor_address_required'];
        if (empty($sponsor_phone)) $errors['sponsor_phone'] = $translations[$language]['sponsor_phone_required'];
        if (!empty($sponsor_phone) && !preg_match('/^[0-9\+\-\s]+$/', $sponsor_phone)) $errors['sponsor_phone'] = $translations[$language]['sponsor_phone_invalid'];
        if ($sponsor_email === false) $errors['sponsor_email'] = $translations[$language]['sponsor_email_invalid'];
        if (empty($sponsor_occupation)) $errors['sponsor_occupation'] = $translations[$language]['sponsor_occupation_required'];
        if (empty($sponsor_relationship)) $errors['sponsor_relationship'] = $translations[$language]['sponsor_relationship_required'];

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                $stmt_check = $conn->prepare("SELECT id FROM application_sponsor_details_nigerian WHERE application_id = ?");
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    $sql = "UPDATE application_sponsor_details_nigerian SET
                                sponsor_name = ?, sponsor_address = ?, sponsor_phone = ?, sponsor_email = ?,
                                sponsor_occupation = ?, sponsor_relationship = ?
                            WHERE application_id = ?";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (UPDATE): " . $conn->error);
                    $stmt_save->bind_param("ssssssi",
                        $sponsor_name, $sponsor_address, $sponsor_phone, $sponsor_email,
                        $sponsor_occupation, $sponsor_relationship, $application_id
                    );
                } else {
                    $sql = "INSERT INTO application_sponsor_details_nigerian
                                (application_id, sponsor_name, sponsor_address, sponsor_phone, sponsor_email,
                                 sponsor_occupation, sponsor_relationship)
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (INSERT): " . $conn->error);
                    $stmt_save->bind_param("issssss",
                        $application_id, $sponsor_name, $sponsor_address, $sponsor_phone, $sponsor_email,
                        $sponsor_occupation, $sponsor_relationship
                    );
                }

                if (!$stmt_save->execute()) {
                    throw new Exception("Execute failed: " . $stmt_save->error);
                }
                $stmt_save->close();

                $stmt_get_status = $conn->prepare("SELECT status FROM applications WHERE id = ?");
                $stmt_get_status->bind_param("i", $application_id);
                $stmt_get_status->execute();
                $current_app_status = $stmt_get_status->get_result()->fetch_assoc()['status'];
                $stmt_get_status->close();

                if ($current_app_status === 'Personal Info Complete') {
                    $new_status = 'Sponsor Info Complete';
                    $next_step = 'documents';
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

                $sponsor_data = [
                    'sponsor_name' => $sponsor_name, 'sponsor_address' => $sponsor_address,
                    'sponsor_phone' => $sponsor_phone, 'sponsor_email' => $sponsor_email,
                    'sponsor_occupation' => $sponsor_occupation, 'sponsor_relationship' => $sponsor_relationship
                ];

                redirect('documents.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving Nigerian application step 2 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = $translations[$language]['error_save'];
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    $errors['form'] .= " Details: " . htmlspecialchars($e->getMessage());
                }
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

                    <!-- Display Messages -->
                    <?php if (!empty($errors['form'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="ri-close-circle-line me-1"></i> <?php echo htmlspecialchars($errors['form']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="ri-check-line me-1"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error']) && $_GET['error'] === 'step1_incomplete'): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="ri-alert-line me-1"></i> <?php echo $translations[$language]['error_step1_incomplete']; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Application Form -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4"><?php echo $translations[$language]['sponsor_details']; ?></h5>
                                    <p class="text-muted mb-4"><?php echo $translations[$language]['sponsor_instructions']; ?></p>

                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <div class="row">
                                            <!-- Sponsor Name -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_name" class="form-label"><?php echo $translations[$language]['sponsor_name']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['sponsor_name']) ? 'is-invalid' : ''; ?>" id="sponsor_name" name="sponsor_name" value="<?php echo htmlspecialchars($sponsor_data['sponsor_name'] ?? ''); ?>" required>
                                                <?php if (isset($errors['sponsor_name'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_name']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Sponsor Occupation -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_occupation" class="form-label"><?php echo $translations[$language]['sponsor_occupation']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['sponsor_occupation']) ? 'is-invalid' : ''; ?>" id="sponsor_occupation" name="sponsor_occupation" value="<?php echo htmlspecialchars($sponsor_data['sponsor_occupation'] ?? ''); ?>" required>
                                                <?php if (isset($errors['sponsor_occupation'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_occupation']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Sponsor Address -->
                                            <div class="col-md-12 mb-3">
                                                <label for="sponsor_address" class="form-label"><?php echo $translations[$language]['sponsor_address']; ?> <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['sponsor_address']) ? 'is-invalid' : ''; ?>" id="sponsor_address" name="sponsor_address" rows="3" required><?php echo htmlspecialchars($sponsor_data['sponsor_address'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['sponsor_address'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_address']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Sponsor Phone -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_phone" class="form-label"><?php echo $translations[$language]['sponsor_phone']; ?> <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control <?php echo isset($errors['sponsor_phone']) ? 'is-invalid' : ''; ?>" id="sponsor_phone" name="sponsor_phone" value="<?php echo htmlspecialchars($sponsor_data['sponsor_phone'] ?? ''); ?>" required placeholder="<?php echo $language === 'ar' ? 'مثال: 08012345678' : 'e.g., 08012345678'; ?>">
                                                <?php if (isset($errors['sponsor_phone'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_phone']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Sponsor Email -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_email" class="form-label"><?php echo $translations[$language]['sponsor_email']; ?> <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control <?php echo isset($errors['sponsor_email']) ? 'is-invalid' : ''; ?>" id="sponsor_email" name="sponsor_email" value="<?php echo htmlspecialchars($sponsor_data['sponsor_email'] ?? ''); ?>" required placeholder="<?php echo $language === 'ar' ? 'بريد.الراعي@مثال.com' : 'sponsor.email@example.com'; ?>">
                                                <?php if (isset($errors['sponsor_email'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_email']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Relationship to Contestant -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_relationship" class="form-label"><?php echo $translations[$language]['sponsor_relationship']; ?> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['sponsor_relationship']) ? 'is-invalid' : ''; ?>" id="sponsor_relationship" name="sponsor_relationship" value="<?php echo htmlspecialchars($sponsor_data['sponsor_relationship'] ?? ''); ?>" placeholder="<?php echo $translations[$language]['sponsor_relationship_placeholder']; ?>" required>
                                                <?php if (isset($errors['sponsor_relationship'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_relationship']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="mt-4 d-flex justify-content-between">
                                            <a href="application-step1-nigerian.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?php echo $translations[$language]['back_to_personal_info']; ?></a>
                                            <button type="submit" class="btn btn-primary"><?php echo $translations[$language]['save_continue']; ?> <i class="ri-arrow-right-line ms-1"></i></button>
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
    <script src="assets/js/app.min.js"></script>

</body>
</html>