<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step2-nigerian.php
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
    error_log("User ID missing from session for logged-in user on application-step2-nigerian.php.");
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
$sponsor_data = []; // To store existing sponsor data

// Verify that the user has a Nigerian application and is at the correct step
$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'nigerian'");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        // Check if the user should be on this step
        // Allow access if step 1 is complete OR if they are already on step 2
        if (!in_array($app['status'], ['Personal Info Complete', 'Sponsor Info Complete', 'Documents Uploaded', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Information Requested']) || ($app['status'] === 'Not Started' && $app['current_step'] !== 'step2')) {
             // If status is 'Not Started' but step is 'step2', it might be okay if they navigated back, but generally, they should have completed step 1 first.
             // Redirect back to step 1 if they haven't completed it.
             redirect('application-step1-nigerian.php?error=step1_incomplete');
             exit;
        }
         // If status is beyond this step, maybe redirect to review or dashboard? For now, allow viewing/editing.
         // if (in_array($app['status'], ['Documents Uploaded', 'Submitted', ...])) { redirect('application-review.php'); exit; }


        // Fetch existing sponsor details for this application step
        // Assuming a table named 'application_sponsor_details_nigerian'
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
             // Handle error - maybe show a message
        }

    } else {
        // No application found or type mismatch
        redirect('application.php?error=app_not_found_or_mismatch');
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die("Error verifying application status. Please try again later.");
}

// --- Form Processing ---
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid form submission. Please try again.";
    } else {
        // Sanitize and retrieve POST data
        $sponsor_name = sanitize_input($_POST['sponsor_name'] ?? '');
        $sponsor_address = sanitize_input($_POST['sponsor_address'] ?? '');
        $sponsor_phone = sanitize_input($_POST['sponsor_phone'] ?? '');
        $sponsor_email = filter_input(INPUT_POST, 'sponsor_email', FILTER_VALIDATE_EMAIL);
        $sponsor_occupation = sanitize_input($_POST['sponsor_occupation'] ?? '');
        $sponsor_relationship = sanitize_input($_POST['sponsor_relationship'] ?? ''); // Relationship to contestant

        // --- Validation ---
        if (empty($sponsor_name)) $errors['sponsor_name'] = "Sponsor/Nominator Name is required.";
        if (empty($sponsor_address)) $errors['sponsor_address'] = "Sponsor/Nominator Address is required.";
        if (empty($sponsor_phone)) $errors['sponsor_phone'] = "Sponsor/Nominator Phone Number is required.";
        if (!empty($sponsor_phone) && !preg_match('/^[0-9\+\-\s]+$/', $sponsor_phone)) $errors['sponsor_phone'] = "Invalid Phone Number format.";
        if ($sponsor_email === false) $errors['sponsor_email'] = "Valid Sponsor/Nominator Email is required.";
        if (empty($sponsor_occupation)) $errors['sponsor_occupation'] = "Sponsor/Nominator Occupation is required.";
        if (empty($sponsor_relationship)) $errors['sponsor_relationship'] = "Relationship to Contestant is required.";

        // --- Database Operation ---
        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Check if record exists
                $stmt_check = $conn->prepare("SELECT id FROM application_sponsor_details_nigerian WHERE application_id = ?");
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    // Update existing record
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
                    // Insert new record
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

                // Update main application status/step only if it's currently 'Personal Info Complete'
                // Avoid overwriting later statuses if user comes back to edit
                $stmt_get_status = $conn->prepare("SELECT status FROM applications WHERE id = ?");
                $stmt_get_status->bind_param("i", $application_id);
                $stmt_get_status->execute();
                $current_app_status = $stmt_get_status->get_result()->fetch_assoc()['status'];
                $stmt_get_status->close();

                if ($current_app_status === 'Personal Info Complete') {
                    $new_status = 'Sponsor Info Complete';
                    $next_step = 'documents'; // Next step is document upload
                    $stmt_update_app = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ?");
                     if (!$stmt_update_app) throw new Exception("Prepare failed (App Update): " . $conn->error);
                    $stmt_update_app->bind_param("ssi", $new_status, $next_step, $application_id);
                    if (!$stmt_update_app->execute()) {
                         throw new Exception("Execute failed (App Update): " . $stmt_update_app->error);
                    }
                    $stmt_update_app->close();
                } else {
                    // If status is already past 'Personal Info Complete', just update the timestamp
                     $stmt_update_time = $conn->prepare("UPDATE applications SET last_updated = NOW() WHERE id = ?");
                     if (!$stmt_update_time) throw new Exception("Prepare failed (App Time Update): " . $conn->error);
                     $stmt_update_time->bind_param("i", $application_id);
                     $stmt_update_time->execute();
                     $stmt_update_time->close();
                }


                $conn->commit();
                $success = "Sponsor/Nominator information saved successfully.";

                // Update $sponsor_data with new values for pre-filling if staying on page
                 $sponsor_data = [
                    'sponsor_name' => $sponsor_name, 'sponsor_address' => $sponsor_address,
                    'sponsor_phone' => $sponsor_phone, 'sponsor_email' => $sponsor_email,
                    'sponsor_occupation' => $sponsor_occupation, 'sponsor_relationship' => $sponsor_relationship
                 ];

                // Redirect to next step (documents)
                redirect('documents.php');
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving Nigerian application step 2 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = "An error occurred while saving sponsor information. Please try again.";
            }
        }
    }
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Adjust CSP if needed, especially for images if served from different origin
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Application: Sponsor Information (Nigerian) | Musabaqa</title>
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
                                <h4 class="page-title">Application - Step 2: Sponsor/Nominator Information</h4>
                                <!-- Optional Breadcrumb or Actions -->
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="application.php">Application</a></li>
                                        <li class="breadcrumb-item active">Step 2</li>
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
                             <i class="ri-alert-line me-1"></i> Please complete Step 1 (Personal Information) before proceeding.
                         </div>
                    <?php endif; ?>


                    <!-- Application Form -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Sponsor/Nominator Details</h5>
                                    <p class="text-muted mb-4">Provide the information for the person sponsoring or nominating you for the competition.</p>

                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <div class="row">
                                            <!-- Sponsor Name -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['sponsor_name']) ? 'is-invalid' : ''; ?>" id="sponsor_name" name="sponsor_name" value="<?php echo htmlspecialchars($sponsor_data['sponsor_name'] ?? ''); ?>" required>
                                                <?php if (isset($errors['sponsor_name'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_name']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Sponsor Occupation -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_occupation" class="form-label">Occupation <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['sponsor_occupation']) ? 'is-invalid' : ''; ?>" id="sponsor_occupation" name="sponsor_occupation" value="<?php echo htmlspecialchars($sponsor_data['sponsor_occupation'] ?? ''); ?>" required>
                                                <?php if (isset($errors['sponsor_occupation'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_occupation']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Sponsor Address -->
                                            <div class="col-md-12 mb-3">
                                                <label for="sponsor_address" class="form-label">Address <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['sponsor_address']) ? 'is-invalid' : ''; ?>" id="sponsor_address" name="sponsor_address" rows="3" required><?php echo htmlspecialchars($sponsor_data['sponsor_address'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['sponsor_address'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_address']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Sponsor Phone -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control <?php echo isset($errors['sponsor_phone']) ? 'is-invalid' : ''; ?>" id="sponsor_phone" name="sponsor_phone" value="<?php echo htmlspecialchars($sponsor_data['sponsor_phone'] ?? ''); ?>" required>
                                                <?php if (isset($errors['sponsor_phone'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_phone']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Sponsor Email -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_email" class="form-label">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control <?php echo isset($errors['sponsor_email']) ? 'is-invalid' : ''; ?>" id="sponsor_email" name="sponsor_email" value="<?php echo htmlspecialchars($sponsor_data['sponsor_email'] ?? ''); ?>" required>
                                                <?php if (isset($errors['sponsor_email'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_email']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                             <!-- Relationship to Contestant -->
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_relationship" class="form-label">Relationship to Contestant <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['sponsor_relationship']) ? 'is-invalid' : ''; ?>" id="sponsor_relationship" name="sponsor_relationship" value="<?php echo htmlspecialchars($sponsor_data['sponsor_relationship'] ?? ''); ?>" placeholder="e.g., Teacher, Parent, Guardian, Community Leader" required>
                                                <?php if (isset($errors['sponsor_relationship'])): ?><div class="invalid-feedback"><?php echo $errors['sponsor_relationship']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- TODO: Add Sponsor Signature/Letter Upload if required -->
                                        <!-- Example:
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="sponsor_letter" class="form-label">Sponsor/Nomination Letter (Optional)</label>
                                                <input type="file" class="form-control" id="sponsor_letter" name="sponsor_letter" accept=".pdf,.doc,.docx,.jpg,.png">
                                                // Add logic to handle file upload and storage
                                            </div>
                                        </div>
                                        -->

                                        <div class="mt-4 d-flex justify-content-between">
                                            <a href="application-step1-nigerian.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Personal Info</a>
                                            <button type="submit" class="btn btn-primary">Save and Continue to Documents <i class="ri-arrow-right-line ms-1"></i></button>
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
    
    <script src="assets/js/pages/demo.dashboard.js"></script> <!-- If needed for any dashboard-like elements -->
    <script src="assets/js/app.min.js"></script> <!-- Essential for template functionality -->

    <!-- Add any page-specific JS here if needed -->

</body>
</html>