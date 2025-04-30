<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-review.php
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
if (!$user_id) {
    error_log("User ID missing from session for logged-in user on application-review.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Application Verification & Data Fetching ---
global $conn;
$application_id = null;
$contestant_type = null;
$application_status = null;
$application_data = [];
$sponsor_data = [];
$documents_data = [];
$required_documents = []; // Define required docs based on type later if needed
$all_required_docs_uploaded = false;

// Verify application and get basic info
$stmt_app = $conn->prepare("SELECT id, status, current_step, contestant_type FROM applications WHERE user_id = ?");
if (!$stmt_app) {
    error_log("Prepare failed (App Check): " . $conn->error);
    die("Error verifying application status. Please try again later.");
}
$stmt_app->bind_param("i", $user_id);
$stmt_app->execute();
$result_app = $stmt_app->get_result();
if ($app = $result_app->fetch_assoc()) {
    $application_id = $app['id'];
    $contestant_type = $app['contestant_type'];
    $application_status = $app['status'];

    // Check if user should be on this step (must have completed documents)
    // Allow access if status is 'Documents Uploaded' or beyond.
    if (!in_array($application_status, ['Documents Uploaded', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Information Requested'])) {
         // Redirect back to documents page if they haven't completed it.
         redirect('documents.php?error=docs_incomplete');
         exit;
    }

    // Define required documents (can be adjusted based on type if needed)
    $required_documents = [
        'national_id' => 'National ID Card / Passport Data Page',
        'birth_certificate' => 'Birth Certificate / Declaration of Age',
        'recommendation_letter' => 'Recommendation Letter from Sponsor/Nominator',
    ];

    // Fetch Details based on contestant type
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
        // Consider showing an error message
    }

    // Fetch Sponsor Details based on contestant type
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
        // Consider showing an error message
    }

    // Fetch Uploaded Documents
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
        // Consider showing an error message
    }

    // Check if all required documents are present
    $all_required_docs_uploaded = true;
    foreach (array_keys($required_documents) as $req_doc_type) {
        if (!isset($documents_data[$req_doc_type])) {
            $all_required_docs_uploaded = false;
            break;
        }
    }


} else {
    // No application found
    redirect('application.php?error=app_not_found');
    exit;
}
$stmt_app->close();


// --- Form Processing (Submission) ---
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid form submission. Please try again.";
    }
    // Check if application can be submitted (not already submitted and all docs uploaded)
    elseif ($application_status === 'Submitted') {
        $errors['form'] = "This application has already been submitted.";
    }
    elseif (!$all_required_docs_uploaded) {
         $errors['form'] = "Cannot submit application. Please ensure all required documents are uploaded.";
    }
    elseif ($application_status !== 'Documents Uploaded') {
         // Should ideally not happen if entry check is correct, but as a safeguard
         $errors['form'] = "Application is not ready for submission. Current status: " . htmlspecialchars($application_status);
    }
    else {
        // Proceed with submission
        try {
            $conn->begin_transaction();

            $new_status = 'Submitted';
            $current_step = 'submitted'; // Or keep as 'review'? Let's use 'submitted'
            $stmt_submit = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ? AND status = 'Documents Uploaded'"); // Extra check on status
            if (!$stmt_submit) throw new Exception("Prepare failed (Submit App): " . $conn->error);

            $stmt_submit->bind_param("ssi", $new_status, $current_step, $application_id);

            if (!$stmt_submit->execute()) {
                throw new Exception("Execute failed (Submit App): " . $stmt_submit->error);
            }

            // Check if the update actually happened (in case status wasn't 'Documents Uploaded')
            if ($stmt_submit->affected_rows === 0) {
                 throw new Exception("Application status was not 'Documents Uploaded' or application ID not found during submission attempt.");
            }

            $stmt_submit->close();
            $conn->commit();

            // Update status locally for display
            $application_status = $new_status;
            $success = "Application submitted successfully!";

            // Redirect after a short delay or immediately
            // header("Refresh: 3; URL=dashboard.php?submission=success");
             redirect('index.php?submission=success'); // Redirect to dashboard or a confirmation page
             exit;

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error submitting application for app ID {$application_id}: " . $e->getMessage());
            $errors['form'] = "An error occurred during submission. Please try again. Details: " . $e->getMessage();
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
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : $default;
}
function display_date($data, $key, $format = 'M d, Y', $default = 'N/A') {
    return isset($data[$key]) && $data[$key] !== '' ? date($format, strtotime($data[$key])) : $default;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Application: Review & Submit | Musabaqa</title>
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
                                <h4 class="page-title">Application - Step 4: Review & Submit</h4>
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="application.php">Application</a></li>
                                        <li class="breadcrumb-item active">Review</li>
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
                             <i class="ri-alert-line me-1"></i> Please complete Step 3 (Document Upload) before reviewing.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                         </div>
                    <?php endif; ?>
                     <?php if (!$all_required_docs_uploaded && $application_status !== 'Submitted'): ?>
                         <div class="alert alert-warning" role="alert">
                             <i class="ri-alert-line me-1"></i> Some required documents are missing. Please upload them before submitting.
                         </div>
                     <?php endif; ?>


                    <!-- Review Sections -->
                    <div class="row review-section">

                        <!-- Personal Information -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="ri-user-line me-1"></i>Personal Information</h5>
                                    <?php if ($application_status !== 'Submitted'): ?>
                                        <a href="<?php echo ($contestant_type === 'nigerian' ? 'application-step1-nigerian.php' : 'application-step1-international.php'); ?>" class="edit-link"><i class="ri-pencil-line me-1"></i>Edit</a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <dl class="row">
                                        <dt class="col-sm-5">Full Name:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, ($contestant_type === 'nigerian' ? 'full_name_nid' : 'full_name_passport')); ?></dd>

                                        <dt class="col-sm-5">Date of Birth:</dt>
                                        <dd class="col-sm-7"><?php echo display_date($application_data, 'dob'); ?></dd>

                                        <dt class="col-sm-5">Age:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'age'); ?></dd>

                                        <dt class="col-sm-5">Address:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'address'); ?></dd>

                                        <?php if ($contestant_type === 'nigerian'): ?>
                                            <dt class="col-sm-5">State of Origin:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'state'); ?></dd>
                                            <dt class="col-sm-5">LGA of Origin:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'lga'); ?></dd>
                                        <?php else: // International ?>
                                            <dt class="col-sm-5">Nationality:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'nationality'); ?></dd>
                                            <dt class="col-sm-5">Passport Number:</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'passport_number'); ?></dd>
                                            <dt class="col-sm-5">Passport Expiry:</dt>
                                            <dd class="col-sm-7"><?php echo display_date($application_data, 'passport_expiry'); ?></dd>
                                        <?php endif; ?>

                                        <dt class="col-sm-5">Phone Number:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'phone_number'); ?></dd>

                                        <dt class="col-sm-5">Email Address:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'email'); ?></dd>

                                        <dt class="col-sm-5">Health Status:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'health_status'); ?></dd>

                                        <dt class="col-sm-5">Languages Spoken:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'languages_spoken'); ?></dd>

                                        <dt class="col-sm-5">Category:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($application_data, 'category') === 'qiraat' ? 'Qira\'at (Males)' : 'Hifz (Females)'; ?></dd>

                                        <?php if (($application_data['category'] ?? '') === 'hifz'): ?>
                                            <dt class="col-sm-5">Narration (Riwayah):</dt>
                                            <dd class="col-sm-7"><?php echo display_data($application_data, 'narration'); ?></dd>
                                        <?php endif; ?>

                                        <?php if (!empty($application_data['photo_path']) && file_exists($application_data['photo_path'])): ?>
                                            <dt class="col-sm-5">Passport Photo:</dt>
                                            <dd class="col-sm-7"><a href="<?php echo htmlspecialchars($application_data['photo_path']); ?>" target="_blank">View Photo</a></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            </div>
                        </div><!-- /col -->

                        <!-- Sponsor Information -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="ri-user-star-line me-1"></i>Sponsor/Nominator Information</h5>
                                     <?php if ($application_status !== 'Submitted'): ?>
                                        <a href="<?php echo ($contestant_type === 'nigerian' ? 'application-step2-nigerian.php' : 'application-step2-international.php'); ?>" class="edit-link"><i class="ri-pencil-line me-1"></i>Edit</a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                     <dl class="row">
                                        <dt class="col-sm-5">Sponsor Name:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_name'); ?></dd>

                                        <dt class="col-sm-5">Sponsor Address:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_address'); ?></dd>

                                        <dt class="col-sm-5">Sponsor Phone:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_phone'); ?></dd>

                                        <dt class="col-sm-5">Sponsor Email:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_email'); ?></dd>

                                        <dt class="col-sm-5">Sponsor Occupation:</dt>
                                        <dd class="col-sm-7"><?php echo display_data($sponsor_data, 'sponsor_occupation'); ?></dd>

                                        <dt class="col-sm-5">Relationship:</dt>
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
                                    <h5 class="mb-0"><i class="ri-file-list-3-line me-1"></i>Uploaded Documents</h5>
                                     <?php if ($application_status !== 'Submitted'): ?>
                                        <a href="documents.php" class="edit-link"><i class="ri-pencil-line me-1"></i>Edit/Upload</a>
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
                                                    <span class="text-muted small ms-2">(Uploaded: <?php echo display_date($doc, 'created_at', 'M d, Y H:i'); ?>)</span>
                                                <?php else: ?>
                                                    <span class="missing-doc">Missing</span>
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
                                    <i class="ri-information-line me-1"></i> This application has been submitted and is under review.
                                </div>
                                <div class="d-flex justify-content-start">
                                     <a href="dashboard.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Dashboard</a>
                                </div>
                             <?php elseif ($application_status === 'Documents Uploaded'): ?>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Ready to Submit?</h5>
                                        <p class="text-muted">Please review all information carefully. Once submitted, you may not be able to make changes.</p>
                                        <?php if (!$all_required_docs_uploaded): ?>
                                             <p class="text-danger fw-bold">You cannot submit until all required documents are uploaded.</p>
                                        <?php endif; ?>
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="d-inline-block">
                                             <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                             <a href="documents.php" class="btn btn-secondary me-2"><i class="ri-arrow-left-line me-1"></i> Back to Documents</a>
                                             <button type="submit" class="btn btn-success btn-lg" <?php echo !$all_required_docs_uploaded ? 'disabled' : ''; ?>>
                                                 <i class="ri-check-double-line me-1"></i> Submit Application
                                             </button>
                                        </form>
                                    </div>
                                </div>
                             <?php else: ?>
                                 <div class="alert alert-warning" role="alert">
                                     <i class="ri-alert-line me-1"></i> Application is not yet ready for submission. Status: <?php echo htmlspecialchars($application_status); ?>
                                 </div>
                                  <div class="d-flex justify-content-start">
                                     <a href="documents.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Documents</a>
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

    <script src="assets/js/pages/demo.dashboard.js"></script> <!-- If needed for any dashboard-like elements -->
    <script src="assets/js/app.min.js"></script> <!-- Essential for template functionality -->
 

</body>
</html>