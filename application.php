<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application.php
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
    error_log("User ID missing from session for logged-in user on application.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    // Handle error appropriately, maybe show an error page
    die("Error checking application status. Please try again later.");
}

// --- Handle Type Selection (POST Request) ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($application)) { // Only process if no application exists yet
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission. Please refresh and try again.";
    } else {
        $contestant_type = $_POST['contestant_type'] ?? '';

        if ($contestant_type === 'nigerian' || $contestant_type === 'international') {
            // Double-check if application was created between page load and POST
            $stmt_double_check = $conn->prepare("SELECT id FROM applications WHERE user_id = ?");
            $stmt_double_check->bind_param("i", $user_id);
            $stmt_double_check->execute();
            $result_double_check = $stmt_double_check->get_result();

            if ($result_double_check->num_rows === 0) {
                 // Insert new application record
                $initial_status = 'Not Started'; // Or 'Step 1 Pending'
                $initial_step = 'step1'; // Or specific step identifier

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
                        $error = "Could not start your application. Please try again.";
                    }
                    $stmt_insert->close();
                } else {
                     error_log("Failed to prepare insert statement for application: " . $conn->error);
                     $error = "An internal error occurred. Please try again later.";
                }
            } else {
                // Application was created concurrently, fetch it and proceed
                $application = $result_double_check->fetch_assoc(); // Re-fetch needed data if required later
                 // Decide where to redirect based on the newly created application's state (logic below)
            }
             $stmt_double_check->close();

        } else {
            $error = "Invalid contestant type selected.";
        }
    }
}

// --- Determine Action Based on Application State ---
$page_content = 'selection'; // Default: show type selection
$error = $_GET['error'] ?? $error; // Capture potential errors from redirects (like app_not_found)

if ($application) {
    $app_id = $application['id']; // Get app ID for potential use
    $app_status = $application['status'];
    $current_step = $application['current_step'];
    $contestant_type = $application['contestant_type'];

    // If application exists but type somehow wasn't set (error state)
    if (empty($contestant_type)) {
         error_log("Application {$app_id} exists for user {$user_id} but contestant_type is missing.");
         $page_content = 'selection'; // Show selection again or an error message
         $error = "There's an issue with your application record (missing type). Please select your contestant type again.";
         // Consider adding logic here to delete or fix the broken record if desired.
    }
    // --- Redirect Logic for In-Progress Applications ---
    // Check statuses that indicate the application is actively being worked on or needs user action
    elseif (in_array($app_status, ['Not Started', 'Personal Info Complete', 'Sponsor Info Complete', 'Documents Uploaded', 'Information Requested'])) {

        $next_page = 'index.php'; // Default fallback

        // Determine the target page based primarily on current_step
        switch ($current_step) {
            case 'step1':
                // Should be on step 1 page
                $next_page = ($contestant_type === 'nigerian') ? 'application-step1-nigerian.php' : 'application-step1-international.php';
                break;
            case 'step2':
                // Should be on step 2 page (Sponsor/Nominator)
                $next_page = ($contestant_type === 'nigerian') ? 'application-step2-nigerian.php' : 'application-step2-international.php';
                break;
            case 'documents':
                 // Should be on documents page
                 $next_page = 'documents.php'; // Assuming documents page is common
                 break;
            case 'review':
                 // Should be on review page
                 $next_page = 'application-review.php';
                 break;
            // Add more specific steps if needed
            default:
                // If current_step is unknown or null, try inferring from status
                if ($app_status === 'Personal Info Complete') {
                    $next_page = ($contestant_type === 'nigerian') ? 'application-step2-nigerian.php' : 'application-step2-international.php';
                } elseif ($app_status === 'Sponsor Info Complete') {
                    $next_page = 'documents.php';
                } elseif ($app_status === 'Documents Uploaded') {
                    $next_page = 'application-review.php';
                } elseif ($app_status === 'Information Requested') {
                    $next_page = 'provide-information.php'; // Assuming this page exists
                } elseif ($app_status === 'Not Started') {
                     // If status is Not Started but step isn't 'step1', maybe reset step? Or go to step 1 page.
                     $next_page = ($contestant_type === 'nigerian') ? 'application-step1-nigerian.php' : 'application-step1-international.php';
                } else {
                    // Fallback if step is unknown and status doesn't help
                    error_log("Unknown application state for user {$user_id}, app {$app_id}: status='{$app_status}', step='{$current_step}'. Redirecting to index.");
                    $next_page = 'index.php';
                }
                break;
        }

        // Perform the redirect only if the calculated next page is different from the current page
        // Prevents redirect loops if the user somehow lands back on application.php when they shouldn't
        if (basename($_SERVER['PHP_SELF']) !== basename($next_page)) {
            redirect($next_page);
            exit;
        } else {
             // If logic dictates redirecting to self, something is wrong. Show selection or error.
             error_log("Redirect loop detected for user {$user_id}, app {$app_id} on application.php. Showing selection page.");
             $page_content = 'selection';
             $error = $error ?: "There was an issue navigating your application. Please select your type again.";
        }
    }
    // --- Show Status Summary for Completed/Terminal States ---
    elseif (in_array($app_status, ['Submitted', 'Under Review', 'Approved', 'Rejected'])) {
        $page_content = 'status_summary';
        // Fetch more details if needed for the summary view (already handled below)
    }
     // --- Handle Unknown/Unexpected Statuses ---
    else {
         error_log("Unexpected application status '{$app_status}' for user {$user_id}, app {$app_id}. Showing selection page.");
         $page_content = 'selection'; // Fallback to selection page for safety
         $error = "Your application has an unexpected status. Please contact support or try selecting your type again.";
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
<html lang="en">
<head>
    <title>My Application | Musabaqa</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        /* Add specific styles if needed */
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
                                <h4 class="page-title">My Application</h4>
                                <!-- Optional Breadcrumb or Actions -->
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
                                            <h4 class="header-title">Begin Your Application</h4>
                                            <p class="text-muted fs-15">Please select your contestant category to proceed.</p>
                                        </div>

                                        <form id="typeSelectionForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="contestant_type" id="contestant_type_input">

                                            <div class="row">
                                                <div class="col-md-6 mb-3 mb-md-0">
                                                    <div class="card text-center selection-card h-100" onclick="submitType('nigerian')">
                                                        <div class="card-body">
                                                        <i class="ri-flag-fill icon-lg text-success mb-2"></i>
                                                            <h5 class="card-title">Nigerian Contestant</h5>
                                                            <p class="card-text text-muted">Select if you are applying from within Nigeria.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card text-center selection-card h-100" onclick="submitType('international')">
                                                        <div class="card-body">
                                                            <i class="ri-earth-line icon-lg text-success mb-2"></i>
                                                            <h5 class="card-title">International Contestant</h5>
                                                            <p class="card-text text-muted">Select if you are applying from outside Nigeria.</p>
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
                                            <h4 class="header-title">Application Status</h4>
                                        </div>

                                        <div class="alert alert-<?php
                                            // Determine alert class based on status
                                            switch ($application['status']) {
                                                case 'Approved': echo 'success'; break;
                                                case 'Rejected': echo 'danger'; break;
                                                case 'Under Review': case 'Submitted': echo 'info'; break;
                                                default: echo 'secondary'; break;
                                            }
                                        ?>" role="alert">
                                            <h5 class="alert-heading">Status: <?php echo htmlspecialchars($application['status']); ?></h5>
                                            <p>Your application as a<?php echo ($application['contestant_type'] === 'nigerian') ? ' Nigerian' : 'n International'; ?> contestant is currently <?php echo strtolower(htmlspecialchars($application['status'])); ?>.</p>
                                            <hr>
                                            <?php
                                                // Provide context-specific messages
                                                $status_message = '';
                                                $next_action_link = null;
                                                $next_action_text = '';

                                                switch ($application['status']) {
                                                    case 'Submitted':
                                                    case 'Under Review':
                                                        $status_message = 'We have received your application and it is currently being reviewed. You will be notified of any updates.';
                                                        $next_action_link = 'application-review.php'; // Link to view submitted data
                                                        $next_action_text = 'View Submitted Application';
                                                        break;
                                                    case 'Approved':
                                                        $status_message = 'Congratulations! Your application has been approved. Please check the schedule and resources pages for further information.';
                                                        $next_action_link = 'schedule.php';
                                                        $next_action_text = 'View Competition Schedule';
                                                        break;
                                                    case 'Rejected':
                                                        $status_message = 'Unfortunately, your application could not be approved at this time. Please check your notifications or the feedback section for more details.';
                                                         $next_action_link = 'application-feedback.php'; // Link to feedback page
                                                         $next_action_text = 'View Feedback';
                                                        break;
                                                    // Add cases for other statuses if needed
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

    <?php include 'layouts/right-sidebar.php'; ?>
    <?php include 'layouts/footer-scripts.php'; ?>
    
    <script src="assets/js/pages/demo.dashboard.js"></script> <!-- If needed for any dashboard-like elements -->
    <script src="assets/js/app.min.js"></script> <!-- Essential for template functionality -->

    <?php if ($page_content === 'selection'): ?>
    <script>
        // Simple script to submit the form when a card is clicked
        function submitType(type) {
            const typeInput = document.getElementById('contestant_type_input');
            const typeForm = document.getElementById('typeSelectionForm');

            if (typeInput && typeForm) {
                typeInput.value = type;
                typeForm.submit();
            } else {
                console.error("Form or input element not found for type selection.");
                alert("An error occurred. Please try refreshing the page."); // User feedback
            }
        }
    </script>
       
    <?php endif; ?>
    

</body>
</html>