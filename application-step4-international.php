<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step4-international.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely
require_once 'includes/countries.php'; // Include the country list helper

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

// Fetch main application status first
$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'international'");
if (!$stmt_app) {
    error_log("Prepare failed (App Check): " . $conn->error);
    die("Error verifying application status.");
}
$stmt_app->bind_param("i", $user_id);
$stmt_app->execute();
$result_app = $stmt_app->get_result();
if ($app = $result_app->fetch_assoc()) {
    $application_id = $app['id'];
    $application_status = $app['status'];
    $current_step = $app['current_step'];
    $is_submitted = ($application_status === 'Submitted' || $application_status === 'Under Review' || $application_status === 'Accepted' || $application_status === 'Rejected'); // Define submitted states

    // --- Step Access Control ---
    // Allow access if current step is 'step4', status is 'Documents Complete', or if already submitted (for viewing)
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
            $errors['fetch'] = "Could not load personal details for review.";
        }
        $stmt_step1->close();
    } else {
        $errors['fetch'] = "Error preparing to load personal details.";
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
        } else {
             // Nominator might be optional depending on rules, don't necessarily error
             // $errors['fetch'] = "Could not load nominator details for review.";
        }
        $stmt_step2->close();
    } else {
        $errors['fetch'] = "Error preparing to load nominator details.";
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
            $errors['fetch'] = "Could not load document details for review.";
        }
        $stmt_step3->close();
    } else {
        $errors['fetch'] = "Error preparing to load document details.";
        error_log("Prepare failed (Step 3 Fetch): " . $conn->error);
    }

} else {
    // No international application found for this user
    redirect('application.php?error=app_not_found_or_mismatch');
    exit;
}
$stmt_app->close();

// --- Submission Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_submitted) { // Only allow submission if not already submitted
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid form submission. Please try again.";
    } elseif (isset($_POST['submit_application'])) {
        // Final check: Ensure all required data seems present (basic check)
        if (empty($application_details) || empty($document_details['passport_scan_path'])) { // Add checks for other mandatory fields/docs
             $errors['form'] = "Cannot submit application. Some required information or documents appear to be missing. Please go back and complete all steps.";
        } else {
            // Begin transaction for application submission
            $conn->begin_transaction();
            
            try {
                $new_status = 'Submitted';
                $final_step = 'completed'; // Or null
                $submitted_at = date('Y-m-d H:i:s'); // Record submission time

                $stmt_submit = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, submitted_at = ?, last_updated = NOW() WHERE id = ? AND status != ?");
                if (!$stmt_submit) {
                    throw new Exception("Prepare failed (Submit App): " . $conn->error);
                }

                // FIXED: Changed type string from 'sssiss' to 'sssis' to match 5 parameters
                $stmt_submit->bind_param("sssis", $new_status, $final_step, $submitted_at, $application_id, $new_status);

                if (!$stmt_submit->execute()) {
                    throw new Exception("Execute failed (Submit App): " . $stmt_submit->error);
                }
                
                if ($stmt_submit->affected_rows > 0) {
                    // Success - commit the transaction
                    $conn->commit();
                    
                    $success = "Application submitted successfully! You will be notified once it has been reviewed.";
                    $is_submitted = true; // Update flag for the current page view
                    $application_status = $new_status; // Update status for the current page view

                    // Optional: Send notification email to admin/user
                    // send_submission_notification($user_id, $application_id);

                    // Redirect after a short delay or keep on page with success message
                    header("Refresh: 5; url=index.php"); // Redirect to dashboard after 5 seconds
                } else {
                    // This might happen if the status was already 'Submitted' (race condition or refresh)
                    $conn->rollback();
                    $errors['form'] = "Application might already be submitted or could not be updated.";
                }
                
                $stmt_submit->close();
            } catch (Exception $e) {
                // Roll back the transaction on error
                $conn->rollback();
                
                error_log("Error submitting International application for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = "An error occurred during submission. Please try again. If the problem persists, contact support.";
                // In production, you might not want to show the detailed error to users
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
function display_data($data, $key, $default = 'N/A') {
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : $default;
}

// Helper function to display file links
function display_file_link($data, $key, $label) {
    if (!empty($data[$key]) && file_exists($data[$key])) {
        $filename = basename($data[$key]);
        $filepath = htmlspecialchars($data[$key]);
        return "<a href='{$filepath}' target='_blank' class='existing-file-link'><i class='ri-eye-line'></i> View {$label} ({$filename})</a>";
    }
    return '<i>Not Provided</i>';
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Application: Step 4 - Review & Submit (International) | Musabaqa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .review-section { margin-bottom: 2rem; }
        .review-section h5 { border-bottom: 1px solid #eee; padding-bottom: 0.5rem; margin-bottom: 1rem; color: #343a40; }
        .review-item { display: flex; margin-bottom: 0.75rem; }
        .review-item strong { width: 200px; /* Adjust as needed */ color: #495057; flex-shrink: 0; }
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
                                <h4 class="page-title">Application - Step 4: Review & Submit (International)</h4>
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="application.php">Application</a></li>
                                        <li class="breadcrumb-item active">Step 4</li>
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
                                    <h5 class="alert-heading"><i class="ri-check-double-line me-1"></i> Application Submitted!</h5>
                                    Your application (Status: <?php echo htmlspecialchars($application_status); ?>) has been received. You will be notified of any updates. You can view your submitted details below.
                                </div>
                             <?php else: ?>
                                <div class="alert alert-warning bg-warning text-white border-0" role="alert">
                                    <h5 class="alert-heading"><i class="ri-eye-line me-1"></i> Review Your Application</h5>
                                    Please carefully review all the information below. If everything is correct, click the "Submit Application" button at the bottom. You cannot edit the application after submission.
                                </div>
                             <?php endif; ?>
                        </div>
                    </div>


                    <!-- Display Messages -->
                    <?php if (!empty($errors['form']) || !empty($errors['fetch'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="ri-close-circle-line me-1"></i> <?php echo htmlspecialchars($errors['form'] ?? $errors['fetch'] ?? 'An error occurred.'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                         <div class="alert alert-success alert-dismissible fade show" role="alert">
                             <i class="ri-check-line me-1"></i> <?php echo htmlspecialchars($success); ?>
                              <p class="mb-0 mt-2">You will be redirected to the dashboard shortly.</p>
                              <!-- No close button needed if redirecting -->
                         </div>
                    <?php endif; ?>


                    <!-- Review Form -->
                    <div class="row">
                        <div class="col-12">
                            <form id="step4-form-intl" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-file-list-3-line me-1"></i>Application Summary</h5>
                                    </div>
                                    <div class="card-body">

                                        <!-- Personal Information Section -->
                                        <div class="review-section">
                                            <h5><i class="ri-user-line me-1"></i>Personal Information <?php if (!$is_submitted): ?> (<a href="application-step1-international.php">Edit Step 1</a>)<?php endif; ?></h5>
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="review-item"><strong>Full Name (Passport):</strong> <span><?php echo display_data($application_details, 'full_name_passport'); ?></span></div>
                                                    <div class="review-item"><strong>Date of Birth:</strong> <span><?php echo display_data($application_details, 'dob'); ?></span></div>
                                                    <div class="review-item"><strong>Age:</strong> <span><?php echo display_data($application_details, 'age'); ?></span></div>
                                                    <div class="review-item"><strong>Residential Address:</strong> <span><?php echo display_data($application_details, 'address'); ?></span></div>
                                                    <div class="review-item"><strong>City:</strong> <span><?php echo display_data($application_details, 'city'); ?></span></div>
                                                    <div class="review-item"><strong>Country of Residence:</strong> <span><?php echo display_data($application_details, 'country_residence'); ?></span></div>
                                                    <div class="review-item"><strong>Nationality:</strong> <span><?php echo display_data($application_details, 'nationality'); ?></span></div>
                                                    <div class="review-item"><strong>Passport Number:</strong> <span><?php echo display_data($application_details, 'passport_number'); ?></span></div>
                                                    <div class="review-item"><strong>Phone Number:</strong> <span><?php echo display_data($application_details, 'phone_number'); ?></span></div>
                                                    <div class="review-item"><strong>Email Address:</strong> <span><?php echo display_data($application_details, 'email'); ?></span></div>
                                                    <div class="review-item"><strong>Health Status:</strong> <span><?php echo display_data($application_details, 'health_status'); ?></span></div>
                                                    <div class="review-item"><strong>Languages Spoken:</strong> <span><?php echo display_data($application_details, 'languages_spoken'); ?></span></div>
                                                    <div class="review-item"><strong>Category:</strong> <span><?php echo display_data($application_details, 'category'); ?></span></div>
                                                    <?php if (($application_details['category'] ?? '') === 'hifz'): ?>
                                                        <div class="review-item"><strong>Narration (Riwayah):</strong> <span><?php echo display_data($application_details, 'narration'); ?></span></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-4 text-center">
                                                    <strong>Passport Photo:</strong><br>
                                                    <?php if (!empty($application_details['photo_path']) && file_exists($application_details['photo_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($application_details['photo_path']) . '?t=' . time(); ?>" alt="Passport Photo" class="review-photo mt-2">
                                                    <?php else: ?>
                                                        <span><i>Not Provided</i></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nominator Information Section -->
                                        <div class="review-section">
                                            <h5><i class="ri-shield-user-line me-1"></i>Nominator/Sponsor Information <?php if (!$is_submitted): ?> (<a href="application-step2-international.php">Edit Step 2</a>)<?php endif; ?></h5>
                                             <?php if (!empty($nominator_details)): ?>
                                                <div class="review-item"><strong>Nominator Type:</strong> <span><?php echo display_data($nominator_details, 'nominator_type'); ?></span></div>
                                                <div class="review-item"><strong>Nominator Name:</strong> <span><?php echo display_data($nominator_details, 'nominator_name'); ?></span></div>
                                                <div class="review-item"><strong>Address:</strong> <span><?php echo display_data($nominator_details, 'nominator_address'); ?></span></div>
                                                <div class="review-item"><strong>City:</strong> <span><?php echo display_data($nominator_details, 'nominator_city'); ?></span></div>
                                                <div class="review-item"><strong>Country:</strong> <span><?php echo display_data($nominator_details, 'nominator_country'); ?></span></div>
                                                <div class="review-item"><strong>Phone:</strong> <span><?php echo display_data($nominator_details, 'nominator_phone'); ?></span></div>
                                                <div class="review-item"><strong>Email:</strong> <span><?php echo display_data($nominator_details, 'nominator_email'); ?></span></div>
                                                <div class="review-item"><strong>Relationship:</strong> <span><?php echo display_data($nominator_details, 'relationship'); ?></span></div>
                                                <div class="review-item"><strong>Nomination Letter:</strong> <span><?php echo display_file_link($nominator_details, 'nomination_letter_path', 'Nomination Letter'); ?></span></div>
                                             <?php else: ?>
                                                <p><i>No nominator information provided or required.</i></p>
                                             <?php endif; ?>
                                        </div>

                                        <!-- Documents Section -->
                                        <div class="review-section">
                                            <h5><i class="ri-file-upload-line me-1"></i>Uploaded Documents <?php if (!$is_submitted): ?> (<a href="application-step3-international.php">Edit Step 3</a>)<?php endif; ?></h5>
                                             <?php if (!empty($document_details)): ?>
                                                <div class="review-item"><strong>Passport Scan:</strong> <span><?php echo display_file_link($document_details, 'passport_scan_path', 'Passport Scan'); ?></span></div>
                                                <div class="review-item"><strong>Birth Certificate:</strong> <span><?php echo display_file_link($document_details, 'birth_certificate_path', 'Birth Certificate'); ?></span></div>
                                                <!-- Add other documents here -->
                                             <?php else: ?>
                                                <p><i>No document information found.</i></p>
                                             <?php endif; ?>
                                        </div>

                                    </div><!-- end card-body -->
                                </div> <!-- end card -->


                                <!-- Action Buttons -->
                                <?php if (!$is_submitted && empty($success)): // Show buttons only if not submitted and no success message is showing ?>
                                    <div class="alert alert-danger mt-3" role="alert">
                                        <i class="ri-alert-line me-1"></i> <strong>Declaration:</strong> By clicking "Submit Application", I declare that all the information provided is true and accurate to the best of my knowledge. I understand that providing false information may lead to disqualification.
                                    </div>
                                    <div class="d-flex justify-content-between mt-4 mb-4">
                                        <a href="application-step3-international.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Step 3</a>
                                        <button type="submit" name="submit_application" class="btn btn-success btn-lg"><i class="ri-send-plane-fill me-1"></i> Submit Application</button>
                                    </div>
                                <?php elseif ($is_submitted): ?>
                                     <div class="text-center mt-4 mb-4">
                                         <a href="index.php" class="btn btn-primary"><i class="ri-home-4-line me-1"></i> Back to Dashboard</a>
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