<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application.php
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
$user_fullname = $_SESSION['user_fullname'] ?? 'Participant';
$user_role = $_SESSION['user_role'] ?? 'user';
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png'; // Default avatar

if (!$user_id) {
    error_log("User ID missing from session for logged-in user on application.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Language-Specific Strings ---
$translations = [
    'en' => [
        'page_title' => 'My Application | Musabaqa',
        'my_application' => 'My Application',
        'begin_application' => 'Begin Your Application',
        'select_contestant_category' => 'Please select your contestant category to proceed.',
        'nigerian_contestant' => 'Nigerian Contestant',
        'nigerian_description' => 'Select if you are applying from within Nigeria.',
        'international_contestant' => 'International Contestant',
        'international_description' => 'Select if you are applying from outside Nigeria.',
        'application_status' => 'Application Status',
        'status_label' => 'Status: %s',
        'status_submitted' => 'We have received your application and it is currently being reviewed. You will be notified of any updates.',
        'status_under_review' => 'We have received your application and it is currently being reviewed. You will be notified of any updates.',
        'status_approved' => 'Congratulations! Your application has been approved. Please check the schedule and resources pages for further information.',
        'status_rejected' => 'Unfortunately, your application could not be approved at this time. Please check your notifications or the feedback section for more details.',
        'view_submitted_application' => 'View Submitted Application',
        'view_competition_schedule' => 'View Competition Schedule',
        'view_feedback' => 'View Feedback',
        'error_invalid_submission' => 'Invalid form submission. Please refresh and try again.',
        'error_invalid_type' => 'Invalid contestant type selected.',
        'error_start_application' => 'Could not start your application. Please try again.',
        'error_internal' => 'An internal error occurred. Please try again later.',
        'error_missing_type' => 'There\'s an issue with your application record (missing type). Please select your contestant type again.',
        'error_unexpected_status' => 'Your application has an unexpected status. Please contact support or try selecting your type again.',
        'confirm_selection' => 'Confirm Selection',
        'confirm_message' => 'You have selected: <strong>%s</strong>',
        'confirm_warning' => 'Are you sure you want to proceed? This choice cannot be changed later.',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'contestant_type_nigerian' => 'Nigerian Contestant',
        'contestant_type_international' => 'International Contestant',
        'application_status_summary' => 'Your application as a %s contestant is currently %s.',
    ],
    'ar' => [
        'page_title' => 'طلبي | المسابقة',
        'my_application' => 'طلبي',
        'begin_application' => 'ابدأ طلبك',
        'select_contestant_category' => 'يرجى اختيار فئة المتسابق للمتابعة.',
        'nigerian_contestant' => 'متسابق نيجيري',
        'nigerian_description' => 'اختر إذا كنت تقدم الطلب من داخل نيجيريا.',
        'international_contestant' => 'متسابق دولي',
        'international_description' => 'اختر إذا كنت تقدم الطلب من خارج نيجيريا.',
        'application_status' => 'حالة الطلب',
        'status_label' => 'الحالة: %s',
        'status_submitted' => 'لقد تلقينا طلبك ويتم مراجعته حاليًا. سيتم إعلامك بأي تحديثات.',
        'status_under_review' => 'لقد تلقينا طلبك ويتم مراجعته حاليًا. سيتم إعلامك بأي تحديثات.',
        'status_approved' => 'تهانينا! تمت الموافقة على طلبك. يرجى التحقق من صفحات الجدول والموارد لمزيد من المعلومات.',
        'status_rejected' => 'للأسف، لم يتم الموافقة على طلبك في الوقت الحالي. يرجى التحقق من الإشعارات أو قسم التعليقات لمزيد من التفاصيل.',
        'view_submitted_application' => 'عرض الطلب المقدم',
        'view_competition_schedule' => 'عرض جدول المسابقة',
        'view_feedback' => 'عرض التعليقات',
        'error_invalid_submission' => 'إرسال نموذج غير صالح. يرجى تحديث الصفحة وإعادة المحاولة.',
        'error_invalid_type' => 'تم اختيار نوع متسابق غير صالح.',
        'error_start_application' => 'تعذر بدء طلبك. يرجى المحاولة مرة أخرى.',
        'error_internal' => 'حدث خطأ داخلي. يرجى المحاولة مرة أخرى لاحقًا.',
        'error_missing_type' => 'هناك مشكلة في سجل طلبك (نوع مفقود). يرجى اختيار نوع المتسابق مرة أخرى.',
        'error_unexpected_status' => 'طلبك في حالة غير متوقعة. يرجى التواصل مع الدعم أو محاولة اختيار نوعك مرة أخرى.',
        'confirm_selection' => 'تأكيد الاختيار',
        'confirm_message' => 'لقد اخترت: <strong>%s</strong>',
        'confirm_warning' => 'هل أنت متأكد من أنك تريد المتابعة؟ لا يمكن تغيير هذا الاختيار لاحقًا.',
        'cancel' => 'إلغاء',
        'confirm' => 'تأكيد',
        'contestant_type_nigerian' => 'متسابق نيجيري',
        'contestant_type_international' => 'متسابق دولي',
        'application_status_summary' => 'طلبك كمتسابق %s في حالة %s حاليًا.',
    ]
];

// --- Fetch Existing Application Data ---
global $conn;
$application = null;
$stmt_check = $conn->prepare("SELECT id, status, current_step, contestant_type FROM applications WHERE user_id = ?");
if ($stmt_check) {
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $application = $result_check->fetch_assoc();
    }
    $stmt_check->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die($translations[$language]['error_internal']);
}

// --- Handle Type Selection (POST Request) ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($application)) { // Only process if no application exists yet
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = $translations[$language]['error_invalid_submission'];
    } else {
        $contestant_type = $_POST['contestant_type'] ?? '';

        if ($contestant_type === 'nigerian' || $contestant_type === 'international') {
            // Double-check if application was created between page load and POST
            $stmt_double_check = $conn->prepare("SELECT id, status, current_step, contestant_type FROM applications WHERE user_id = ?");
            $stmt_double_check->bind_param("i", $user_id);
            $stmt_double_check->execute();
            $result_double_check = $stmt_double_check->get_result();

            if ($result_double_check->num_rows === 0) {
                // Insert new application record
                $initial_status = 'Not Started';
                $initial_step = 'step1';

                $stmt_insert = $conn->prepare("INSERT INTO applications (user_id, contestant_type, status, current_step, created_at, last_updated) VALUES (?, ?, ?, ?, NOW(), NOW())");
                if ($stmt_insert) {
                    $stmt_insert->bind_param("isss", $user_id, $contestant_type, $initial_status, $initial_step);
                    if ($stmt_insert->execute()) {
                        // Redirect to the appropriate step 1 page
                        if ($contestant_type === 'nigerian') {
                            redirect('application-step1-nigerian.php');
                        } else {
                            redirect('application-step1-international.php');
                        }
                        exit;
                    } else {
                        error_log("Failed to insert new application for user {$user_id}: " . $stmt_insert->error);
                        $error = $translations[$language]['error_start_application'];
                    }
                    $stmt_insert->close();
                } else {
                    error_log("Failed to prepare insert statement for application: " . $conn->error);
                    $error = $translations[$language]['error_internal'];
                }
            } else {
                // Application was created concurrently
                $application = $result_double_check->fetch_assoc();
            }
            $stmt_double_check->close();
        } else {
            $error = $translations[$language]['error_invalid_type'];
        }
    }
}

// --- Determine Action Based on Application State ---
$page_content = 'selection'; // Default: show type selection
$error = $_GET['error'] ?? $error; // Capture potential errors from redirects

if ($application) {
    $app_id = $application['id'];
    $app_status = $application['status'];
    $current_step = $application['current_step'];
    $contestant_type = $application['contestant_type'];

    // If application exists but type is missing
    if (empty($contestant_type)) {
        error_log("Application {$app_id} exists for user {$user_id} but contestant_type is missing.");
        $page_content = 'selection';
        $error = $translations[$language]['error_missing_type'];
    }
    // Redirect for in-progress applications
    elseif (in_array($app_status, ['Not Started', 'Personal Info Complete', 'Sponsor Info Complete', 'Documents Uploaded', 'Documents Complete', 'Information Requested'])) {
        $next_page = 'index.php'; // Default fallback

        switch ($current_step) {
            case 'step1':
                $next_page = ($contestant_type === 'nigerian') ? 'application-step1-nigerian.php' : 'application-step1-international.php';
                break;
            case 'step2':
                $next_page = ($contestant_type === 'nigerian') ? 'application-step2-nigerian.php' : 'application-step2-international.php';
                break;
            case 'step3':
                $next_page = ($contestant_type === 'international') ? 'application-step3-international.php' : 'documents.php';
                break;
            case 'step4':
                $next_page = ($contestant_type === 'international') ? 'application-step4-international.php' : 'application-review.php';
                break;
            case 'documents':
                $next_page = 'documents.php';
                break;
            case 'review':
                $next_page = 'application-review.php';
                break;
            default:
                if ($app_status === 'Personal Info Complete') {
                    $next_page = ($contestant_type === 'nigerian') ? 'application-step2-nigerian.php' : 'application-step2-international.php';
                } elseif ($app_status === 'Sponsor Info Complete') {
                    $next_page = ($contestant_type === 'international') ? 'application-step3-international.php' : 'documents.php';
                } elseif ($app_status === 'Documents Uploaded' || $app_status === 'Documents Complete') {
                    $next_page = ($contestant_type === 'international') ? 'application-step4-international.php' : 'application-review.php';
                } elseif ($app_status === 'Information Requested') {
                    $next_page = 'provide-information.php';
                } elseif ($app_status === 'Not Started') {
                    $next_page = ($contestant_type === 'nigerian') ? 'application-step1-nigerian.php' : 'application-step1-international.php';
                } else {
                    error_log("Unknown application state for user {$user_id}, app {$app_id}: status='{$app_status}', step='{$current_step}'. Redirecting to index.");
                    $next_page = 'index.php';
                }
                break;
        }

        if (basename($_SERVER['PHP_SELF']) !== basename($next_page)) {
            redirect($next_page);
            exit;
        } else {
            error_log("Redirect loop detected for user {$user_id}, app {$app_id} on application.php. Showing selection page.");
            $page_content = 'selection';
            $error = $error ?: $translations[$language]['error_unexpected_status'];
        }
    }
    // Show status summary for completed/terminal states
    elseif (in_array($app_status, ['Submitted', 'Under Review', 'Approved', 'Rejected'])) {
        $page_content = 'status_summary';
    }
    // Handle unexpected statuses
    else {
        error_log("Unexpected application status '{$app_status}' for user {$user_id}, app {$app_id}. Showing selection page.");
        $page_content = 'selection';
        $error = $translations[$language]['error_unexpected_status'];
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
    <title><?php echo $translations[$language]['page_title']; ?></title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .selection-card { cursor: pointer; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .selection-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .icon-lg { font-size: 3rem; }
    </style>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <?php include 'layouts/menu.php'; // Include the sidebar menu ?>

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
                                <h4 class="page-title"><?php echo $translations[$language]['my_application']; ?></h4>
                            </div>
                        </div>
                    </div>

                    <!-- Display Error Messages -->
                    <?php if (!empty($error)): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="ri-close-circle-line me-1"></i> <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($page_content === 'selection'): ?>
                        <!-- Contestant Type Selection -->
                        <div class="row justify-content-center mt-4">
                            <div class="col-lg-8 col-xl-6">
                                <div class="card">
                                    <div class="card-body p-4">
                                        <div class="text-center mb-4">
                                            <h4 class="header-title"><?php echo $translations[$language]['begin_application']; ?></h4>
                                            <p class="text-muted fs-15"><?php echo $translations[$language]['select_contestant_category']; ?></p>
                                        </div>

                                        <form id="typeSelectionForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="contestant_type" id="contestant_type_input">

                                            <div class="row">
                                                <div class="col-md-6 mb-3 mb-md-0">
                                                    <div class="card text-center selection-card h-100" onclick="showConfirmationModal('nigerian')">
                                                        <div class="card-body">
                                                            <i class="ri-flag-fill icon-lg text-success mb-2"></i>
                                                            <h5 class="card-title"><?php echo $translations[$language]['nigerian_contestant']; ?></h5>
                                                            <p class="card-text text-muted"><?php echo $translations[$language]['nigerian_description']; ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card text-center selection-card h-100" onclick="showConfirmationModal('international')">
                                                        <div class="card-body">
                                                            <i class="ri-earth-line icon-lg text-success mb-2"></i>
                                                            <h5 class="card-title"><?php echo $translations[$language]['international_contestant']; ?></h5>
                                                            <p class="card-text text-muted"><?php echo $translations[$language]['international_description']; ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->
                            </div> <!-- end col -->
                        </div> <!-- end row -->

                    <?php elseif ($page_content === 'status_summary'): ?>
                        <!-- Application Status Summary -->
                        <div class="row justify-content-center mt-4">
                            <div class="col-lg-8 col-xl-6">
                                <div class="card">
                                    <div class="card-body p-4">
                                        <div class="text-center mb-4">
                                            <h4 class="header-title"><?php echo $translations[$language]['application_status']; ?></h4>
                                        </div>

                                        <div class="alert alert-<?php
                                            switch ($application['status']) {
                                                case 'Approved': echo 'success'; break;
                                                case 'Rejected': echo 'danger'; break;
                                                case 'Under Review': case 'Submitted': echo 'info'; break;
                                                default: echo 'secondary'; break;
                                            }
                                        ?>" role="alert">
                                            <h5 class="alert-heading"><?php echo sprintf($translations[$language]['status_label'], htmlspecialchars($application['status'])); ?></h5>
                                            <p><?php
                                                $contestant_type_label = ($application['contestant_type'] === 'nigerian') ?
                                                    $translations[$language]['contestant_type_nigerian'] :
                                                    $translations[$language]['contestant_type_international'];
                                                echo sprintf(
                                                    $translations[$language]['application_status_summary'],
                                                    htmlspecialchars($contestant_type_label),
                                                    strtolower(htmlspecialchars($application['status']))
                                                );
                                            ?></p>
                                            <hr>
                                            <?php
                                                $status_message = '';
                                                $next_action_link = null;
                                                $next_action_text = '';

                                                switch ($application['status']) {
                                                    case 'Submitted':
                                                    case 'Under Review':
                                                        $status_message = $translations[$language]['status_submitted'];
                                                        $next_action_link = ($application['contestant_type'] === 'international')
                                                                          ? 'application-step4-international.php'
                                                                          : 'application-review.php';
                                                        $next_action_text = $translations[$language]['view_submitted_application'];
                                                        break;
                                                    case 'Approved':
                                                        $status_message = $translations[$language]['status_approved'];
                                                        $next_action_link = 'schedule.php';
                                                        $next_action_text = $translations[$language]['view_competition_schedule'];
                                                        break;
                                                    case 'Rejected':
                                                        $status_message = $translations[$language]['status_rejected'];
                                                        $next_action_link = 'application-feedback.php';
                                                        $next_action_text = $translations[$language]['view_feedback'];
                                                        break;
                                                }
                                                echo '<p class="mb-0">' . htmlspecialchars($status_message) . '</p>';
                                            ?>
                                        </div>

                                        <?php if ($next_action_link): ?>
                                        <div class="text-center mt-3">
                                            <a href="<?php echo htmlspecialchars($next_action_link); ?>" class="btn btn-primary">
                                                <?php echo htmlspecialchars($next_action_text); ?> <i class="ri-arrow-right-line ms-1"></i>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->
                            </div> <!-- end col -->
                        </div> <!-- end row -->

                    <?php endif; ?>

                </div> <!-- container-fluid -->
            </div> <!-- content -->

            <?php include 'layouts/footer.php'; ?>

        </div>
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

    </div>
    <!-- END wrapper -->

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel"><?php echo $translations[$language]['confirm_selection']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo sprintf(
                        $translations[$language]['confirm_message'],
                        '<strong id="selectedTypeDisplay"></strong>'
                    ); ?></p>
                    <p class="text-danger fw-bold"><?php echo $translations[$language]['confirm_warning']; ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $translations[$language]['cancel']; ?></button>
                    <button type="button" class="btn btn-primary" id="confirmSelectionBtn"><?php echo $translations[$language]['confirm']; ?></button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'layouts/right-sidebar.php'; ?>
    <?php include 'layouts/footer-scripts.php'; ?>

    <!-- Removed redundant dashboard script -->
    <script src="assets/js/app.min.js"></script>

    <?php if ($page_content === 'selection'): ?>
    <script>
        let selectedContestantType = null;
        const confirmationModalElement = document.getElementById('confirmationModal');
        const confirmationModal = confirmationModalElement ? new bootstrap.Modal(confirmationModalElement) : null;
        const selectedTypeDisplay = document.getElementById('selectedTypeDisplay');
        const confirmBtn = document.getElementById('confirmSelectionBtn');
        const typeInput = document.getElementById('contestant_type_input');
        const typeForm = document.getElementById('typeSelectionForm');

        function showConfirmationModal(type) {
            if (!confirmationModal || !selectedTypeDisplay || !typeInput || !typeForm) {
                console.error("Modal or form elements not found.");
                alert("<?php echo $translations[$language]['error_internal']; ?>");
                return;
            }

            selectedContestantType = type;
            selectedTypeDisplay.textContent = (type === 'nigerian' ? '<?php echo $translations[$language]['contestant_type_nigerian']; ?>' : '<?php echo $translations[$language]['contestant_type_international']; ?>');
            confirmationModal.show();
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (selectedContestantType && typeInput && typeForm) {
                    typeInput.value = selectedContestantType;
                    typeForm.submit();
                } else {
                    console.error("Selected type or form elements missing on confirm.");
                    alert("<?php echo $translations[$language]['error_internal']; ?>");
                    confirmationModal.hide();
                }
            });
        } else {
            console.error("Confirmation button not found.");
        }
    </script>
    <?php endif; ?>

</body>
</html>