<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step2-international.php
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
    error_log("User ID missing from session for logged-in user on application-step2-international.php.");
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
$application_data = []; // To store existing nominator data
$application_status = 'Not Started';
$current_step = '';

$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'international'");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        $application_status = $app['status'];
        $current_step = $app['current_step'];

        // --- Step Access Control ---
        // Allow access if current step is 'step2' or if they completed step 1 ('Personal Info Complete')
        if ($current_step !== 'step2' && $application_status !== 'Personal Info Complete') {
             // Redirect back to the correct step or overview page
             $redirect_target = ($current_step && $current_step !== 'step2') ? 'application-' . $current_step . '-international.php' : 'application.php';
             redirect($redirect_target . '?error=step_sequence');
             exit;
        }


        // Fetch existing nominator details for this application step
        $stmt_details = $conn->prepare("SELECT * FROM application_nominators_international WHERE application_id = ?");
        if ($stmt_details) {
            $stmt_details->bind_param("i", $application_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                $application_data = $result_details->fetch_assoc();
            }
            $stmt_details->close();
        } else {
             error_log("Failed to prepare statement for fetching International Nominator details: " . $conn->error);
             $errors['form'] = "Could not load existing application details. Please try again later.";
        }

    } else {
        // No international application found for this user
        redirect('application.php?error=app_not_found_or_mismatch');
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die("Error verifying application status. Please try again later.");
}

// --- Define profile picture for topbar (fetch from step 1 if needed) ---
$profile_picture = $_SESSION['user_profile_picture'] ?? 'assets/images/users/avatar-1.jpg'; // Simplified for now

// --- Form Processing ---
$errors = [];
$success = '';
$upload_dir = 'uploads/nominations/'; // Define upload directory for letters
$allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']; // Allowed letter formats
$max_file_size = 5 * 1024 * 1024; // 5MB limit for letter

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid form submission. Please try again.";
    } else {
        // Sanitize and retrieve POST data
        $nominator_type = sanitize_input($_POST['nominator_type'] ?? '');
        $nominator_name = sanitize_input($_POST['nominator_name'] ?? '');
        $nominator_address = sanitize_input($_POST['nominator_address'] ?? '');
        $nominator_city = sanitize_input($_POST['nominator_city'] ?? '');
        $nominator_country = sanitize_input($_POST['nominator_country'] ?? '');
        $nominator_phone = sanitize_input($_POST['nominator_phone'] ?? '');
        $nominator_email = filter_input(INPUT_POST, 'nominator_email', FILTER_VALIDATE_EMAIL);
        $relationship = sanitize_input($_POST['relationship'] ?? ''); // Optional

        // --- Validation ---
        if (empty($nominator_type) || !in_array($nominator_type, ['Organization', 'Individual'])) $errors['nominator_type'] = "Nominator/Sponsor Type is required.";
        if (empty($nominator_name)) $errors['nominator_name'] = "Nominator/Sponsor Name is required.";
        // Add more validation as needed (e.g., phone format, country exists)
        if ($nominator_email === false && !empty($_POST['nominator_email'])) $errors['nominator_email'] = "Invalid Email format."; // Only error if provided and invalid

        // --- File Upload Handling (Nomination Letter) ---
        $nomination_letter_path = $application_data['nomination_letter_path'] ?? null;
        $new_file_uploaded = false;

        if (isset($_FILES['nomination_letter']) && $_FILES['nomination_letter']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['nomination_letter']['tmp_name'];
            $file_name = $_FILES['nomination_letter']['name'];
            $file_size = $_FILES['nomination_letter']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                $errors['nomination_letter'] = "Invalid file type. Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG.";
            } elseif ($file_size > $max_file_size) {
                $errors['nomination_letter'] = "File size exceeds the limit (" . ($max_file_size / 1024 / 1024) . "MB).";
            } else {
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $errors['nomination_letter'] = "Failed to create upload directory for documents.";
                        goto skip_file_move_nomination_intl;
                    }
                }
                // Sanitize filename before using it
                $safe_basename = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($file_name));
                $unique_filename = "app_{$application_id}_nomination_" . uniqid() . '_' . $safe_basename;
                $destination = $upload_dir . $unique_filename;

                if (move_uploaded_file($file_tmp_path, $destination)) {
                    // Delete old file if replacing
                    $old_letter_path = $application_data['nomination_letter_path'] ?? null;
                    if ($old_letter_path && file_exists($old_letter_path) && $old_letter_path !== $destination) {
                        @unlink($old_letter_path);
                    }
                    $nomination_letter_path = $destination;
                    $new_file_uploaded = true;
                } else {
                    error_log("move_uploaded_file failed for nomination letter: From '{$file_tmp_path}' to '{$destination}' for app {$application_id}");
                    $errors['nomination_letter'] = "Failed to upload document.";
                }
            }
        } elseif (isset($_FILES['nomination_letter']) && $_FILES['nomination_letter']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors['nomination_letter'] = "Error uploading document: Code " . $_FILES['nomination_letter']['error'];
        } elseif (empty($nomination_letter_path)) {
             // Make letter optional or required based on your rules
             // $errors['nomination_letter'] = "Nomination/Sponsorship Letter is required."; // Uncomment if required
        }

        skip_file_move_nomination_intl: // Label for goto jump

        // --- Database Operation ---
        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Check if record exists in 'application_nominators_international'
                $stmt_check = $conn->prepare("SELECT id FROM application_nominators_international WHERE application_id = ?");
                if (!$stmt_check) throw new Exception("Prepare failed (Check Nominator): " . $conn->error);
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    // Update existing record
                    $sql = "UPDATE application_nominators_international SET
                                nominator_type = ?, nominator_name = ?, nominator_address = ?, nominator_city = ?,
                                nominator_country = ?, nominator_phone = ?, nominator_email = ?, relationship = ?,
                                nomination_letter_path = ?, updated_at = NOW()
                            WHERE application_id = ?";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (UPDATE Nominator): " . $conn->error);
                    // 9 data fields + application_id = 10 params (s s s s s s s s s i)
                    $stmt_save->bind_param("sssssssssi",
                        $nominator_type, $nominator_name, $nominator_address, $nominator_city,
                        $nominator_country, $nominator_phone, $nominator_email, $relationship,
                        $nomination_letter_path,
                        $application_id
                    );
                } else {
                    // Insert new record
                    $sql = "INSERT INTO application_nominators_international
                                (application_id, nominator_type, nominator_name, nominator_address, nominator_city,
                                 nominator_country, nominator_phone, nominator_email, relationship, nomination_letter_path)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 10 placeholders
                    $stmt_save = $conn->prepare($sql);
                     if (!$stmt_save) throw new Exception("Prepare failed (INSERT Nominator): " . $conn->error);
                     // 10 params (i s s s s s s s s s)
                    $stmt_save->bind_param("isssssssss",
                        $application_id, $nominator_type, $nominator_name, $nominator_address, $nominator_city,
                        $nominator_country, $nominator_phone, $nominator_email, $relationship, $nomination_letter_path
                    );
                }

                if (!$stmt_save->execute()) {
                    throw new Exception("Execute failed (Save Nominator): " . $stmt_save->error);
                }
                $stmt_save->close();

                // Update main application status/step
                // Only update status if moving forward from the previous step's status
                if ($application_status === 'Personal Info Complete') {
                    $new_status = 'Nominator Info Complete'; // Or similar status
                    $next_step = 'step3'; // Assuming step 3 is next
                    $stmt_update_app = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ?");
                    if (!$stmt_update_app) throw new Exception("Prepare failed (App Update): " . $conn->error);
                    $stmt_update_app->bind_param("ssi", $new_status, $next_step, $application_id);
                    if (!$stmt_update_app->execute()) {
                         throw new Exception("Execute failed (App Update): " . $stmt_update_app->error);
                    }
                    $stmt_update_app->close();
                } else {
                     // Just update the timestamp if already past this stage but editing
                     $stmt_update_time = $conn->prepare("UPDATE applications SET last_updated = NOW() WHERE id = ?");
                     if (!$stmt_update_time) throw new Exception("Prepare failed (App Time Update): " . $conn->error);
                     $stmt_update_time->bind_param("i", $application_id);
                     $stmt_update_time->execute();
                     $stmt_update_time->close();
                }


                $conn->commit();
                $success = "Nominator information saved successfully.";

                // Update $application_data with new values
                 $application_data = [
                    'nominator_type' => $nominator_type, 'nominator_name' => $nominator_name,
                    'nominator_address' => $nominator_address, 'nominator_city' => $nominator_city,
                    'nominator_country' => $nominator_country, 'nominator_phone' => $nominator_phone,
                    'nominator_email' => $nominator_email, 'relationship' => $relationship,
                    'nomination_letter_path' => $nomination_letter_path
                 ];

                // Redirect to next step:
                 redirect('application-step3-international.php'); // *** Make sure this file exists ***
                 exit;

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving International application step 2 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = "An error occurred while saving your information. Details: " . htmlspecialchars($e->getMessage());
                // Rollback file upload if it happened during the failed transaction
                if ($new_file_uploaded && isset($destination) && file_exists($destination)) {
                    @unlink($destination);
                }
            }
        }
    }
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
// ... other headers ...

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Application: Step 2 - Nominator Details (International) | Musabaqa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        /* Existing styles */
        .form-control.is-invalid, .form-select.is-invalid { border-color: #dc3545; }
        .invalid-feedback { display: block; color: #dc3545; font-size: 0.875em; }
        .form-label { font-weight: 500; }
        .progress-bar { background-color: #0acf97; }
        .step-indicator { margin-bottom: 1.5rem; }
        .file-upload-info { font-size: 0.9em; margin-top: 5px; }
        .existing-file-link { font-size: 0.9em; }
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
                                <h4 class="page-title">Application - Step 2: Nominator Details (International)</h4>
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

                    <!-- Step Indicator -->
                    <div class="row step-indicator">
                        <div class="col-12">
                            <div class="progress" style="height: 10px;">
                                <!-- Assuming 4 steps total -->
                                <div class="progress-bar" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">Step 2 of 4</div>
                            </div>
                        </div>
                    </div>

                    <!-- Welcome Message -->
                     <div class="row">
                        <div class="col-12">
                             <div class="alert alert-secondary bg-secondary text-white border-0" role="alert">
                                Please provide details about the organization or individual nominating/sponsoring you.
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


                    <!-- Nominator/Sponsor Form -->
                    <div class="row">
                        <div class="col-12">
                            <form id="step2-form-intl" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- Nominator Details Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-shield-user-line me-1"></i>Nominator/Sponsor Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Nominator Type -->
                                            <div class="col-md-6 mb-3">
                                                <label for="nominator_type" class="form-label">Nominator/Sponsor Type <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['nominator_type']) ? 'is-invalid' : ''; ?>" id="nominator_type" name="nominator_type" required>
                                                    <option value="" disabled <?php echo empty($application_data['nominator_type']) ? 'selected' : ''; ?>>-- Select Type --</option>
                                                    <option value="Organization" <?php echo (($application_data['nominator_type'] ?? '') === 'Organization') ? 'selected' : ''; ?>>Organization / Institution</option>
                                                    <option value="Individual" <?php echo (($application_data['nominator_type'] ?? '') === 'Individual') ? 'selected' : ''; ?>>Individual</option>
                                                </select>
                                                <?php if (isset($errors['nominator_type'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_type']); ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nominator Name -->
                                            <div class="col-md-6 mb-3">
                                                <label for="nominator_name" class="form-label">Nominator/Sponsor Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['nominator_name']) ? 'is-invalid' : ''; ?>" id="nominator_name" name="nominator_name" value="<?php echo htmlspecialchars($application_data['nominator_name'] ?? ''); ?>" required placeholder="Full name of person or organization">
                                                <?php if (isset($errors['nominator_name'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_name']); ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Nominator Address -->
                                            <div class="col-md-8 mb-3">
                                                <label for="nominator_address" class="form-label">Address</label> <!-- Optional? -->
                                                <textarea class="form-control <?php echo isset($errors['nominator_address']) ? 'is-invalid' : ''; ?>" id="nominator_address" name="nominator_address" rows="2"><?php echo htmlspecialchars($application_data['nominator_address'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['nominator_address'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_address']); ?></div><?php endif; ?>
                                            </div>
                                             <!-- Nominator City -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_city" class="form-label">City</label> <!-- Optional? -->
                                                <input type="text" class="form-control <?php echo isset($errors['nominator_city']) ? 'is-invalid' : ''; ?>" id="nominator_city" name="nominator_city" value="<?php echo htmlspecialchars($application_data['nominator_city'] ?? ''); ?>">
                                                <?php if (isset($errors['nominator_city'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_city']); ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Nominator Country -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_country" class="form-label">Country</label> <!-- Optional? -->
                                                <select class="form-select <?php echo isset($errors['nominator_country']) ? 'is-invalid' : ''; ?>" id="nominator_country" name="nominator_country">
                                                    <option value="" <?php echo empty($application_data['nominator_country']) ? 'selected' : ''; ?>>-- Select Country --</option>
                                                    <?php
                                                        $countries = get_countries();
                                                        $selected_n_country = $application_data['nominator_country'] ?? ($_POST['nominator_country'] ?? '');
                                                        foreach ($countries as $code => $name) {
                                                            $selected = ($name === $selected_n_country) ? 'selected' : '';
                                                            echo "<option value=\"" . htmlspecialchars($name) . "\" $selected>" . htmlspecialchars($name) . "</option>";
                                                        }
                                                    ?>
                                                </select>
                                                <?php if (isset($errors['nominator_country'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_country']); ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nominator Phone -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_phone" class="form-label">Phone Number</label> <!-- Optional? -->
                                                <input type="tel" class="form-control <?php echo isset($errors['nominator_phone']) ? 'is-invalid' : ''; ?>" id="nominator_phone" name="nominator_phone" value="<?php echo htmlspecialchars($application_data['nominator_phone'] ?? ''); ?>" placeholder="e.g., +1 212 555 1234">
                                                <?php if (isset($errors['nominator_phone'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_phone']); ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nominator Email -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nominator_email" class="form-label">Email Address</label> <!-- Optional? -->
                                                <input type="email" class="form-control <?php echo isset($errors['nominator_email']) ? 'is-invalid' : ''; ?>" id="nominator_email" name="nominator_email" value="<?php echo htmlspecialchars($application_data['nominator_email'] ?? ''); ?>" placeholder="nominator@example.com">
                                                <?php if (isset($errors['nominator_email'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['nominator_email']); ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                         <div class="row">
                                             <!-- Relationship (Optional) -->
                                            <div class="col-md-12 mb-3">
                                                <label for="relationship" class="form-label">Relationship to Contestant (Optional)</label>
                                                <input type="text" class="form-control <?php echo isset($errors['relationship']) ? 'is-invalid' : ''; ?>" id="relationship" name="relationship" value="<?php echo htmlspecialchars($application_data['relationship'] ?? ''); ?>" placeholder="e.g., Teacher, Imam, Organization Representative">
                                                <?php if (isset($errors['relationship'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['relationship']); ?></div><?php endif; ?>
                                            </div>
                                         </div>

                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Nomination Letter Upload Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-file-text-line me-1"></i>Letter of Nomination/Sponsorship</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <label for="nomination_letter" class="form-label">Upload Letter</label> <!-- Make required? -->
                                                <p class="text-muted fs-13">Upload the official nomination or sponsorship letter (PDF, DOC, DOCX, JPG, PNG, max 5MB).</p>
                                                <input type="file" class="form-control <?php echo isset($errors['nomination_letter']) ? 'is-invalid' : ''; ?>" id="nomination_letter" name="nomination_letter" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                <?php if (isset($errors['nomination_letter'])): ?>
                                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['nomination_letter']); ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($application_data['nomination_letter_path']) && file_exists($application_data['nomination_letter_path'])): ?>
                                                    <div class="mt-2">
                                                        <span class="file-upload-info">Current letter uploaded:</span>
                                                        <a href="<?php echo htmlspecialchars($application_data['nomination_letter_path']); ?>" target="_blank" class="existing-file-link ms-2">
                                                            <i class="ri-eye-line"></i> <?php echo htmlspecialchars(basename($application_data['nomination_letter_path'])); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div><!-- end card-body -->
                                </div> <!-- end card -->


                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-between mt-4 mb-4">
                                    <a href="application-step1-international.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Step 1</a>
                                    <button type="submit" class="btn btn-primary">Save and Continue to Step 3 <i class="ri-arrow-right-line ms-1"></i></button>
                                </div>

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

    <!-- Page Specific Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]:not([disabled])'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // Optional: Update file input label to show filename
            const fileInput = document.getElementById('nomination_letter');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0] ? e.target.files[0].name : 'Upload Letter'; // Default text
                    // You might need a custom file input structure in HTML to display this nicely
                    console.log("Selected file:", fileName);
                });
            }
        });
    </script>

</body>
</html>