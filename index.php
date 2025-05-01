<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/index.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

// --- Authentication & Session Management ---
if (!is_logged_in()) {
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

// Get user details
$user_id = $_SESSION['user_id'] ?? null;
$user_fullname = $_SESSION['user_fullname'] ?? 'Participant';
$user_role = $_SESSION['user_role'] ?? 'user';

if (!$user_id) {
    error_log("User ID missing from session for logged-in user on index.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// --- Fetch Musabaqa Application & Competition Data ---
global $conn;

// Fetch Application Status, Current Step, and Contestant Type
$app_status = 'Not Started';
$app_status_description = 'You have not started your application yet.';
$next_step_link = 'application.php'; // Link to start/continue application
$next_step_text = 'Start Application';
$progress = 0;
$contestant_type = null; // Initialize contestant type

$stmt_app = $conn->prepare("SELECT status, current_step, contestant_type FROM applications WHERE user_id = ?");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();

    if ($application = $result_app->fetch_assoc()) {
        $app_status = $application['status'] ?? 'Unknown';
        $current_step = $application['current_step'] ?? null; // Use null if not set
        $contestant_type = $application['contestant_type']; // Fetch the type

        // --- Determine Status Description, Next Step, and Progress ---
        switch ($app_status) {
            case 'Not Started':
                $progress = 5; // Slightly more than 0 if record exists
                // If type is known, link to specific step 1, otherwise link to main application page to select type
                $next_step_link = $contestant_type ?
                                  (($contestant_type === 'nigerian') ? 'application-step1-nigerian.php' : 'application-step1-international.php')
                                  : 'application.php';
                $next_step_text = 'Continue Application';
                $app_status_description = 'Begin filling out your application details.';
                break;
            case 'Personal Info Complete': // Example status after step 1
                $progress = 25;
                 // Link to step 2 (sponsor/nominator) based on type
                $next_step_link = $contestant_type ?
                                  (($contestant_type === 'nigerian') ? 'application-step2-nigerian.php' : 'application-step2-international.php')
                                  : 'application.php'; // Fallback if type missing
                $next_step_text = 'Add Sponsor Details';
                $app_status_description = 'Personal details saved. Next: Sponsor/Nominator information.';
                break;
            case 'Sponsor Info Complete': // Example status after step 2
                 $progress = 50;
                 $next_step_link = 'documents.php'; // Link to document upload page (assuming common)
                 $next_step_text = 'Upload Documents';
                 $app_status_description = 'Sponsor details saved. Please upload required documents.';
                 break;
            case 'Documents Uploaded':
                $progress = 75;
                $next_step_link = 'application-review.php'; // Page to review before submission
                $next_step_text = 'Review & Submit';
                $app_status_description = 'Documents uploaded. Please review and submit your application.';
                break;
                case 'Submitted':
                    case 'Under Review':
                        $progress = 90; // Submitted but not finished
                        // Link to the appropriate review page based on type
                        $next_step_link = ($contestant_type === 'international')
                                          ? 'application-step4-international.php' // International uses Step 4 for review
                                          : 'application-review.php';          // Nigerian uses the common review page
                        $next_step_text = 'View Submitted Application';
                        $app_status = 'Under Review'; // Normalize
                        $app_status_description = 'Your application has been submitted and is under review.';
                        break;
            case 'Approved':
                $progress = 100;
                $next_step_link = 'schedule.php'; // Link to competition schedule
                $next_step_text = 'View Competition Schedule';
                $app_status_description = 'Congratulations! Your application has been approved.';
                break;
            case 'Rejected':
                $progress = $application['previous_progress'] ?? 0; // Maybe store previous progress? Resetting to 0 is also fine.
                $next_step_link = 'application-feedback.php'; // Link to see rejection reason
                $next_step_text = 'View Feedback';
                $app_status_description = 'Your application requires attention. Please review feedback.';
                break;
            case 'Information Requested':
                 $progress = 60; // Example progress value
                 $next_step_link = 'provide-information.php'; // Link to provide requested info
                 $next_step_text = 'Provide Information';
                 $app_status_description = 'Additional information is required for your application.';
                 break;
            default:
                // Handle unknown status or cases where type might be missing unexpectedly
                $app_status = 'Unknown Status';
                $next_step_link = 'application.php'; // Go back to application start
                $next_step_text = 'Check Application';
                $app_status_description = 'There might be an issue with your application record.';
                $progress = 0;
                break;
        }
    } else {
        // No application record found, treat as 'Not Started'
        $app_status = 'Not Started';
        $next_step_link = 'application.php'; // Link to the page where they choose type
        $next_step_text = 'Start Application';
        $app_status_description = 'You have not started your application yet.';
        $progress = 0;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for fetching application status: " . $conn->error);
    $app_status = 'Error';
    $app_status_description = 'Could not retrieve application status. Please try again later.';
    $next_step_link = '#'; // Avoid linking anywhere specific on error
    $next_step_text = 'Refresh Page';
    $progress = 0;
}

// --- Fetch Competition Stage & Countdown ---
// TODO: Replace placeholders with data from config or database
$competition_stage = "Application Phase"; // Example: Fetch from `competition_settings` table
$next_deadline_name = "Application Submission"; // Example: Fetch from `competition_settings` table
$next_deadline_date = "2025-08-31 23:59:59"; // Example: Fetch from `competition_settings` table
$countdown_target_timestamp = strtotime($next_deadline_date);
// Ensure timestamp is valid, otherwise set to null or a far future date
if ($countdown_target_timestamp === false) {
    $countdown_target_timestamp = null; // Handle invalid date string
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
<html lang="en">
<head>
    <title>Dashboard | Musabaqa</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .progress-bar { background-color: #e9ecef; border-radius: .25rem; overflow: hidden; height: 10px; }
        .progress-bar-inner { background-color: #0d6efd; height: 100%; transition: width .6s ease; }
        .status-approved { color: #198754; }
        .status-rejected { color: #dc3545; }
        .status-under-review, .status-submitted { color: #ffc107; } /* Grouped similar */
        .status-information-requested { color: #0dcaf0; } /* Example */
        .status-not-started, .status-unknown-status, .status-error { color: #6c757d; } /* Grouped pending/error */
        .countdown-timer span { font-weight: bold; font-size: 1.1em; }
        .notification-badge { position: absolute; top: -5px; right: -5px; padding: 2px 6px; border-radius: 50%; background: red; color: white; font-size: 0.7rem; }
        .icon-container { position: relative; display: inline-block; }
        .cta-box .fs-20 { font-size: 36px !important; opacity: 0.6; } /* Make icons slightly larger and less prominent */
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
                                <h4 class="page-title">Welcome, <?php echo htmlspecialchars($user_fullname); ?>!</h4>
                                <div class="page-title-right">
                                    <form class="d-flex">
                                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-primary ms-2">
                                            <i class="ri-refresh-line"></i> Refresh
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
                                    <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="Competition Stage">Current Stage</h5>
                                    <h2 class="my-2 py-1 mb-2"><?php echo htmlspecialchars($competition_stage); ?></h2>
                                    <hr class="my-2">
                                    <p class="mb-1">Next Deadline: <strong><?php echo htmlspecialchars($next_deadline_name); ?></strong></p>
                                    <div class="countdown-timer" id="countdown">
                                        <?php if ($countdown_target_timestamp): ?>
                                            Loading countdown...
                                        <?php else: ?>
                                            No deadline set.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Application Progress -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="Application Status">Application Status</h5>
                                    <h2 class="my-2 py-1 mb-1 status-<?php echo strtolower(str_replace(' ', '-', $app_status)); ?>">
                                        <?php echo htmlspecialchars($app_status); ?>
                                    </h2>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($app_status_description); ?></p>
                                    <!-- Progress Bar -->
                                    <div class="progress-bar mb-1">
                                        <div class="progress-bar-inner" style="width: <?php echo $progress; ?>%;" role="progressbar" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span class="text-muted fs-13"><?php echo $progress; ?>% Complete</span>

                                    <?php // Only show button if there's a valid next step link ?>
                                    <?php if ($next_step_link !== '#'): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo htmlspecialchars($next_step_link); ?>" class="btn btn-primary btn-sm">
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
                                <h4 class="page-title">Manage Your Participation</h4>
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
                                            <h5 class="mt-0 fw-normal cta-box-title">My Application</h5>
                                            <p class="text-muted mb-2">View or continue application</p>
                                            <a href="application.php" class="link-primary link-offset-3 fw-bold">Go to Application <i class="ri-arrow-right-line"></i></a>
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
                                            <h5 class="mt-0 fw-normal cta-box-title">My Profile</h5>
                                            <p class="text-muted mb-2">Update personal details</p>
                                            <a href="profile.php" class="link-primary link-offset-3 fw-bold">Go to Profile <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-user-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Documents Link -->
                        <!-- <div class="col-xl-4 col-md-6">
                            <div class="card cta-box overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title">Documents</h5>
                                            <p class="text-muted mb-2">Upload & manage files</p>
                                            <a href="documents.php" class="link-primary link-offset-3 fw-bold">Manage Documents <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-file-upload-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                        <!-- Notifications Link -->
                         <!-- <div class="col-xl-4 col-md-6">
                            <div class="card cta-box overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="icon-container">
                                            <h5 class="mt-0 fw-normal cta-box-title">Notifications</h5>
                                            <p class="text-muted mb-2">View important updates</p>
                                            <a href="notifications.php" class="link-primary link-offset-3 fw-bold">View Notifications <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <div class="icon-container">
                                            <i class="ri-notification-3-line ms-3 fs-20"></i>
                                            <?php if ($unread_notifications > 0): ?>
                                                <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                        <!-- Schedule Link -->
                         <div class="col-xl-4 col-md-6">
                            <div class="card cta-box overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title">Schedule</h5>
                                            <p class="text-muted mb-2">View competition dates</p>
                                            <a href="schedule.php" class="link-primary link-offset-3 fw-bold">View Schedule <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-calendar-2-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                         <!-- Resources Link -->
                         <!-- <div class="col-xl-4 col-md-6">
                            <div class="card cta-box overflow-hidden">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 class="mt-0 fw-normal cta-box-title">Resources</h5>
                                            <p class="text-muted mb-2">Access helpful materials</p>
                                            <a href="resources.php" class="link-primary link-offset-3 fw-bold">View Resources <i class="ri-arrow-right-line"></i></a>
                                        </div>
                                        <i class="ri-book-open-line ms-3 fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div> -->

                    </div> <!-- end row -->

                     <!-- Admin-Specific Section (Conditional) -->
                     <?php if ($user_role === 'admin' || $user_role === 'reviewer'): ?>
                     <div class="row mt-4">
                          <div class="col-12 mb-2">
                              <h4 class="page-title">Admin Actions</h4>
                          </div>
                         <div class="col-xl-4 col-md-6">
                             <div class="card cta-box bg-light">
                                 <div class="card-body">
                                     <div class="d-flex align-items-center justify-content-between">
                                         <div>
                                             <h5 class="mt-0 fw-normal cta-box-title">Review Applications</h5>
                                             <a href="admin/review-applications.php" class="link-secondary link-offset-3 fw-bold">Go to Review <i class="ri-arrow-right-line"></i></a>
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
                                             <h5 class="mt-0 fw-normal cta-box-title">Manage Users</h5>
                                             <a href="admin/manage-users.php" class="link-secondary link-offset-3 fw-bold">Go to Users <i class="ri-arrow-right-line"></i></a>
                                         </div>
                                         <i class="ri-group-line ms-3 fs-20"></i>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <!-- Add more admin links as needed -->
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
            // Ensure the timestamp is valid before trying to use it
            const targetTimestamp = <?php echo $countdown_target_timestamp ? $countdown_target_timestamp * 1000 : 'null'; ?>;

            if (countdownElement && targetTimestamp) {
                function updateCountdown() {
                    const now = new Date().getTime();
                    const distance = targetTimestamp - now;

                    if (distance < 0) {
                        countdownElement.innerHTML = "Deadline Passed";
                        if (countdownInterval) clearInterval(countdownInterval); // Stop interval if deadline passed
                        return;
                    }

                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    countdownElement.innerHTML = `<span>${days}</span>d <span>${hours}</span>h <span>${minutes}</span>m <span>${seconds}</span>s remaining`;
                }

                updateCountdown(); // Initial call
                const countdownInterval = setInterval(updateCountdown, 1000); // Store interval ID
            } else if (countdownElement) {
                // Handle case where timestamp is null or element doesn't exist
                // Message is already set in PHP block
            }
        });
    </script>
       <script src="assets/js/pages/demo.dashboard.js"></script>

<!-- App js -->
<script src="assets/js/app.min.js"></script>

</body>
</html>