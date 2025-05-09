<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/index.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

// --- Handle Language Switch ---
if (isset($_POST['language'])) {
    $new_language = $_POST['language'];
    if (in_array($new_language, ['en', 'ar'])) {
        $_SESSION['language'] = $new_language;
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh page to apply language
        exit;
    }
}

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
$_SESSION['fullname'] = $_SESSION['user_fullname'] ?? null; // Align with topbar.php
$user_fullname = $_SESSION['fullname'] ?? ($language === 'ar' ? 'مشارك' : 'Participant');
$user_role = $_SESSION['user_role'] ?? 'user';
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png';

// If fullname is not set, fetch from database
if (empty($_SESSION['fullname'])) {
    global $conn;
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['fullname'] = $row['fullname'];
        $user_fullname = $row['fullname'];
    } else {
        $_SESSION['fullname'] = $language === 'ar' ? 'مشارك' : 'Participant';
        $user_fullname = $_SESSION['fullname'];
    }
    $stmt->close();
}

if (!$user_id) {
    error_log("User ID missing from session for logged-in user on index.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// --- Language-Specific Strings ---
$translations = [
    'en' => [
        'welcome' => 'Welcome, %s!',
        'page_title' => 'Dashboard | Musabaqa',
        'current_stage' => 'Current Stage',
        'next_deadline' => 'Next Deadline: <strong>%s</strong>',
        'no_deadline' => 'No deadline set.',
        'deadline_passed' => 'Deadline Passed',
        'application_status' => 'Application Status',
        'progress_complete' => '%d%% Complete',
        'manage_participation' => 'Manage Your Participation',
        'my_application' => 'My Application',
        'view_continue_application' => 'View or continue application',
        'go_to_application' => 'Go to Application',
        'my_profile' => 'My Profile',
        'update_personal_details' => 'Update personal details',
        'go_to_profile' => 'Go to Profile',
        'schedule' => 'Schedule',
        'view_competition_dates' => 'View competition dates',
        'view_schedule' => 'View Schedule',
        'admin_actions' => 'Admin Actions',
        'review_applications' => 'Review Applications',
        'go_to_review' => 'Go to Review',
        'manage_users' => 'Manage Users',
        'go_to_users' => 'Go to Users',
        'refresh' => 'Refresh',
        'continue_application' => 'Continue Application',
        'start_application' => 'Start Application',
        'add_sponsor_details' => 'Add Sponsor Details',
        'upload_documents' => 'Upload Documents',
        'review_submit' => 'Review & Submit',
        'view_submitted_application' => 'View Submitted Application',
        'view_competition_schedule' => 'View Competition Schedule',
        'view_feedback' => 'View Feedback',
        'provide_information' => 'Provide Information',
        'check_application' => 'Check Application',
        'refresh_page' => 'Refresh Page',
        'select_language' => 'Select Language',
        'english' => 'English',
        'arabic' => 'Arabic',
    ],
    'ar' => [
        'welcome' => 'مرحبًا، %s!',
        'page_title' => 'لوحة التحكم | المسابقة',
        'current_stage' => 'المرحلة الحالية',
        'next_deadline' => 'الموعد النهائي التالي: <strong>%s</strong>',
        'no_deadline' => 'لم يتم تحديد موعد نهائي.',
        'deadline_passed' => 'انتهى الموعد النهائي',
        'application_status' => 'حالة الطلب',
        'progress_complete' => 'مكتمل بنسبة %d%%',
        'manage_participation' => 'إدارة مشاركتك',
        'my_application' => 'طلبي',
        'view_continue_application' => 'عرض أو متابعة الطلب',
        'go_to_application' => 'الذهاب إلى الطلب',
        'my_profile' => 'ملفي الشخصي',
        'update_personal_details' => 'تحديث التفاصيل الشخصية',
        'go_to_profile' => 'الذهاب إلى الملف الشخصي',
        'schedule' => 'الجدول',
        'view_competition_dates' => 'عرض تواريخ المسابقة',
        'view_schedule' => 'عرض الجدول',
        'admin_actions' => 'إجراءات المشرف',
        'review_applications' => 'مراجعة الطلبات',
        'go_to_review' => 'الذهاب إلى المراجعة',
        'manage_users' => 'إدارة المستخدمين',
        'go_to_users' => 'الذهاب إلى المستخدمين',
        'refresh' => 'تحديث',
        'continue_application' => 'متابعة الطلب',
        'start_application' => 'بدء الطلب',
        'add_sponsor_details' => 'إضافة تفاصيل الكفيل',
        'upload_documents' => 'رفع المستندات',
        'review_submit' => 'مراجعة وإرسال',
        'view_submitted_application' => 'عرض الطلب المقدم',
        'view_competition_schedule' => 'عرض جدول المسابقة',
        'view_feedback' => 'عرض التعليقات',
        'provide_information' => 'تقديم المعلومات',
        'check_application' => 'فحص الطلب',
        'refresh_page' => 'تحديث الصفحة',
        'select_language' => 'اختر اللغة',
        'english' => 'الإنجليزية',
        'arabic' => 'العربية',
    ]
];

// --- Fetch Musabaqa Application & Competition Data ---
global $conn;

// Fetch Application Status, Current Step, and Contestant Type
$app_status = 'Not Started';
$app_status_description = $translations[$language]['start_application'];
$next_step_link = 'application.php';
$next_step_text = $translations[$language]['start_application'];
$progress = 0;
$contestant_type = null;

$stmt_app = $conn->prepare("SELECT status, current_step, contestant_type FROM applications WHERE user_id = ?");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();

    if ($application = $result_app->fetch_assoc()) {
        $app_status = $application['status'] ?? 'Unknown';
        $current_step = $application['current_step'] ?? null;
        $contestant_type = $application['contestant_type'];

        switch ($app_status) {
            case 'Not Started':
                $progress = 5;
                $next_step_link = $contestant_type ?
                                  (($contestant_type === 'nigerian') ? 'application-step1-nigerian.php' : 'application-step1-international.php')
                                  : 'application.php';
                $next_step_text = $translations[$language]['continue_application'];
                $app_status_description = $translations[$language]['start_application'];
                break;
            case 'Personal Info Complete':
                $progress = 25;
                $next_step_link = $contestant_type ?
                                  (($contestant_type === 'nigerian') ? 'application-step2-nigerian.php' : 'application-step2-international.php')
                                  : 'application.php';
                $next_step_text = $translations[$language]['add_sponsor_details'];
                $app_status_description = $translations[$language]['add_sponsor_details'];
                break;
            case 'Sponsor Info Complete':
                 $progress = 50;
                 $next_step_link = 'documents.php';
                 $next_step_text = $translations[$language]['upload_documents'];
                 $app_status_description = $translations[$language]['upload_documents'];
                 break;
            case 'Documents Uploaded':
                $progress = 75;
                $next_step_link = 'application-review.php';
                $next_step_text = $translations[$language]['review_submit'];
                $app_status_description = $translations[$language]['review_submit'];
                break;
            case 'Submitted':
            case 'Under Review':
                $progress = 90;
                $next_step_link = ($contestant_type === 'international')
                                  ? 'application-step4-international.php'
                                  : 'application-review.php';
                $next_step_text = $translations[$language]['view_submitted_application'];
                $app_status = 'Under Review';
                $app_status_description = $translations[$language]['view_submitted_application'];
                break;
            case 'Approved':
                $progress = 100;
                $next_step_link = 'schedule.php';
                $next_step_text = $translations[$language]['view_competition_schedule'];
                $app_status_description = $translations[$language]['view_competition_schedule'];
                break;
            case 'Rejected':
                $progress = $application['previous_progress'] ?? 0;
                $next_step_link = 'application-feedback.php';
                $next_step_text = $translations[$language]['view_feedback'];
                $app_status_description = $translations[$language]['view_feedback'];
                break;
            case 'Information Requested':
                 $progress = 60;
                 $next_step_link = 'provide-information.php';
                 $next_step_text = $translations[$language]['provide_information'];
                 $app_status_description = $translations[$language]['provide_information'];
                 break;
            default:
                $app_status = 'Unknown Status';
                $next_step_link = 'application.php';
                $next_step_text = $translations[$language]['check_application'];
                $app_status_description = $translations[$language]['check_application'];
                $progress = 0;
                break;
        }
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for fetching application status: " . $conn->error);
    $app_status = 'Error';
    $app_status_description = $translations[$language]['refresh_page'];
    $next_step_link = '#';
    $next_step_text = $translations[$language]['refresh_page'];
    $progress = 0;
}

// --- Fetch Competition Stage & Countdown ---
$competition_stage = "Application Phase";
$next_deadline_name = "Application Submission";
$next_deadline_date = "2025-08-31 23:59:59";
$countdown_target_timestamp = strtotime($next_deadline_date);
if ($countdown_target_timestamp === false) {
    $countdown_target_timestamp = null;
    error_log("Invalid deadline date format for countdown: " . $next_deadline_date);
}

// Fetch Unread Notification Count
$unread_notifications = 0;
$stmt_notif = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
if ($stmt_notif) {
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    if ($notif_data = $result_notif->fetch_assoc()) {
        $unread_notifications = (int)$notif_data['count'];
    }
    $stmt_notif->close();
} else {
     error_log("Failed to prepare statement for fetching notification count: " . $conn->error);
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
        .progress-bar { background-color: #e9ecef; border-radius: .25rem; overflow: hidden; height: 10px; }
        .progress-bar-inner { background-color: #2e6b5e; height: 100%; transition: width .6s ease; }
        .status-approved { color: #198754; }
        .status-rejected { color: #dc3545; }
        .status-under-review, .status-submitted { color: #ffc107; }
        .status-information-requested { color: #0dcaf0; }
        .status-not-started, .status-unknown-status, .status-error { color: #6c757d; }
        .countdown-timer span { font-weight: bold; font-size: 1.1em; }
        .notification-badge { position: absolute; top: -5px; right: -5px; padding: 2px 6px; border-radius: 50%; background: red; color: white; font-size: 0.7rem; }
        .icon-container { position: relative; display: inline-block; }
        .cta-box .fs-20 { font-size: 36px !important; opacity: 0.6; }
        .btn-custom-blue {
            display: inline-block;
            background-color: #2e6b5e;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out;
        }
        .btn-custom-blue:hover {
            background-color: #2e6b5e;
            color: white;
            text-decoration: none;
        }
        .btn-custom-blue i {
            vertical-align: middle;
        }
        .language-switcher {
            min-width: 120px;
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

                    <!-- Welcome Row -->
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 class="page-title"><?php echo sprintf($translations[$language]['welcome'], htmlspecialchars($user_fullname)); ?></h4>
                                <div class="page-title-right d-flex align-items-center">
                                    <!-- Language Switcher -->
                                    <form method="POST" class="me-2">
                                        <select name="language" class="form-select language-switcher" onchange="this.form.submit()">
                                            <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>><?php echo $translations[$language]['english']; ?></option>
                                            <option value="ar" <?php echo $language === 'ar' ? 'selected' : ''; ?>><?php echo $translations[$language]['arabic']; ?></option>
                                        </select>
                                    </form>
                                    <!-- Refresh Button -->
                                    <form class="d-flex">
                                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn-custom-blue">
                                            <i class="ri-refresh-line"></i> <?php echo $translations[$language]['refresh']; ?>
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Competition Status Overview Row -->
                    <div class="row">
                        <!-- Competition Stage & Countdown -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="<?php echo $translations[$language]['current_stage']; ?>"><?php echo $translations[$language]['current_stage']; ?></h5>
                                    <h2 class="my-2 py-1 mb-2"><?php echo htmlspecialchars($competition_stage); ?></h2>
                                    <hr class="my-2">
                                    <p class="mb-1"><?php echo sprintf($translations[$language]['next_deadline'], htmlspecialchars($next_deadline_name)); ?></p>
                                    <div class="countdown-timer" id="countdown">
                                        <?php if ($countdown_target_timestamp): ?>
                                            Loading countdown...
                                        <?php else: ?>
                                            <?php echo $translations[$language]['no_deadline']; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Application Progress -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="<?php echo $translations[$language]['application_status']; ?>"><?php echo $translations[$language]['application_status']; ?></h5>
                                    <h2 class="my-2 py-1 mb-1 status-<?php echo strtolower(str_replace(' ', '-', $app_status)); ?>">
                                        <?php echo htmlspecialchars($app_status); ?>
                                    </h2>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($app_status_description); ?></p>
                                    <!-- Progress Bar -->
                                    <div class="progress-bar mb-1">
                                        <div class="progress-bar-inner" style="width: <?php echo $progress; ?>%;" role="progressbar" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span class="text-muted fs-13"><?php echo sprintf($translations[$language]['progress_complete'], $progress); ?></span>

                                    <?php if ($next_step_link !== '#'): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo htmlspecialchars($next_step_link); ?>" class="btn-custom-blue">
                                                <?php echo htmlspecialchars($next_step_text); ?> <i class="ri-arrow-right-line ms-1"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div><!-- end row -->

                    <!-- Quick Links/Actions Row -->
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box mt-2">
                                <h4 class="page-title"><?php echo $translations[$language]['manage_participation']; ?></h4>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Application Link -->
                        <div class="col-xl-4 col-md-6">
                            <div class="card cta-box overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title"><?php echo $translations[$language]['my_application']; ?></h5>
                                            <p class="text-muted mb-2"><?php echo $translations[$language]['view_continue_application']; ?></p>
                                            <a href="application.php" class="link-primary link-offset-3 fw-bold"><?php echo $translations[$language]['go_to_application']; ?> <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-file-list-3-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Profile Link -->
                        <div class="col-xl-4 col-md-6">
                            <div class="card cta-box overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title"><?php echo $translations[$language]['my_profile']; ?></h5>
                                            <p class="text-muted mb-2"><?php echo $translations[$language]['update_personal_details']; ?></p>
                                            <a href="profile.php" class="link-primary link-offset-3 fw-bold"><?php echo $translations[$language]['go_to_profile']; ?> <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-user-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Schedule Link -->
                        <div class="col-xl-4 col-md-6">
                            <div class="card cta-box overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title"><?php echo $translations[$language]['schedule']; ?></h5>
                                            <p class="text-muted mb-2"><?php echo $translations[$language]['view_competition_dates']; ?></p>
                                            <a href="schedule.php" class="link-primary link-offset-3 fw-bold"><?php echo $translations[$language]['view_schedule']; ?> <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-calendar-2-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- end row -->

                    <!-- Admin-Specific Section (Conditional) -->
                    <?php if ($user_role === 'admin' || $user_role === 'reviewer'): ?>
                    <div class="row mt-4">
                        <div class="col-12 mb-2">
                            <h4 class="page-title"><?php echo $translations[$language]['admin_actions']; ?></h4>
                        </div>
                        <div class="col-xl-4 col-md-6">
                            <div class="card cta-box bg-light">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title"><?php echo $translations[$language]['review_applications']; ?></h5>
                                            <a href="admin/review-applications.php" class="link-secondary link-offset-3 fw-bold"><?php echo $translations[$language]['go_to_review']; ?> <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-file-search-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6">
                            <div class="card cta-box bg-light">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title"><?php echo $translations[$language]['manage_users']; ?></h5>
                                            <a href="admin/manage-users.php" class="link-secondary link-offset-3 fw-bold"><?php echo $translations[$language]['go_to_users']; ?> <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-group-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

    <?php include 'layouts/right-sidebar.php'; ?>
    <?php include 'layouts/footer-scripts.php'; ?>

    <!-- Countdown Timer Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const countdownElement = document.getElementById('countdown');
            
            // Set a fixed 10-day countdown from the current date
            const now = new Date().getTime();
            const targetTimestamp = now + (7 * 24 * 60 * 60 * 1000); // 10 days in milliseconds
            
            if (countdownElement) {
                function updateCountdown() {
                    const currentTime = new Date().getTime();
                    const distance = targetTimestamp - currentTime;
        
                    if (distance < 0) {
                        countdownElement.innerHTML = "<?php echo $translations[$language]['deadline_passed']; ?>";
                        if (countdownInterval) clearInterval(countdownInterval);
                        return;
                    }
        
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
                    countdownElement.innerHTML = `<span>${days}</span>d <span>${hours}</span>h <span>${minutes}</span>m <span>${seconds}</span>s remaining`;
                }
        
                updateCountdown();
                const countdownInterval = setInterval(updateCountdown, 1000);
            }
        });
    </script>
    <script src="assets/js/pages/demo.dashboard.js"></script>
    <script src="assets/js/app.min.js"></script>
    
</body>
</html>