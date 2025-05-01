<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step3-international.php
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
if (!$user_id) {
    error_log("User ID missing from session for logged-in user on application-step3-international.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png'; // Default avatar

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Application Verification ---
global $conn;
$application_id = null;
$application_data = []; // To store existing document data
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
        // Allow access if current step is 'step3' or if they completed step 2 ('Nominator Info Complete')
        if ($current_step !== 'step3' && $application_status !== 'Nominator Info Complete') {
             $redirect_target = ($current_step && $current_step !== 'step3') ? 'application-' . $current_step . '-international.php' : 'application.php';
             redirect($redirect_target . '?error=step_sequence');
             exit;
        }

        // Fetch existing document details for this application step
        $stmt_details = $conn->prepare("SELECT * FROM application_documents_international WHERE application_id = ?");
        if ($stmt_details) {
            $stmt_details->bind_param("i", $application_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                $application_data = $result_details->fetch_assoc();
            }
            $stmt_details->close();
        } else {
             error_log("Failed to prepare statement for fetching International Document details: " . $conn->error);
             $errors['form'] = "Could not load existing document information. Please try again later.";
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

// --- Define profile picture for topbar ---
$profile_picture = $_SESSION['user_profile_picture'] ?? 'assets/images/users/avatar-1.jpg'; // Simplified

// --- Form Processing ---
$errors = [];
$success = '';
$upload_dir = 'uploads/documents/'; // Define upload directory for documents
$allowed_types = ['pdf', 'jpg', 'jpeg', 'png']; // Allowed document formats
$max_file_size = 5 * 1024 * 1024; // 5MB limit per document

// Helper function for file upload processing
function process_file_upload($file_key, $application_id, $doc_type, $upload_dir, $allowed_types, $max_file_size, &$errors, $current_path = null) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES[$file_key]['tmp_name'];
        $file_name = $_FILES[$file_key]['name'];
        $file_size = $_FILES[$file_key]['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            $errors[$file_key] = "Invalid file type for {$doc_type}. Allowed: " . implode(', ', $allowed_types) . ".";
            return null; // Indicate error
        } elseif ($file_size > $max_file_size) {
            $errors[$file_key] = "File size for {$doc_type} exceeds the limit (" . ($max_file_size / 1024 / 1024) . "MB).";
            return null; // Indicate error
        } else {
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[$file_key] = "Failed to create upload directory for documents.";
                    return false; // Indicate critical failure
                }
            }
            $safe_basename = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($file_name));
            $unique_filename = "app_{$application_id}_{$doc_type}_" . uniqid() . '_' . $safe_basename;
            $destination = $upload_dir . $unique_filename;

            if (move_uploaded_file($file_tmp_path, $destination)) {
                // Delete old file if replacing
                if ($current_path && file_exists($current_path) && $current_path !== $destination) {
                    @unlink($current_path);
                }
                return $destination; // Return new path
            } else {
                error_log("move_uploaded_file failed for {$doc_type}: From '{$file_tmp_path}' to '{$destination}' for app {$application_id}");
                $errors[$file_key] = "Failed to upload {$doc_type}.";
                return null; // Indicate error
            }
        }
    } elseif (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[$file_key] = "Error uploading {$doc_type}: Code " . $_FILES[$file_key]['error'];
        return null; // Indicate error
    }
    return $current_path; // No new file uploaded, return existing path
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid form submission. Please try again.";
    } else {

        $passport_scan_path = $application_data['passport_scan_path'] ?? null;
        $birth_certificate_path = $application_data['birth_certificate_path'] ?? null;

        $new_passport_path = process_file_upload(
            'passport_scan', $application_id, 'passport', $upload_dir, $allowed_types, $max_file_size, $errors, $passport_scan_path
        );
        if ($new_passport_path === false) goto skip_db_ops_intl_docs; // Critical directory error

        $new_birth_cert_path = process_file_upload(
            'birth_certificate', $application_id, 'birth_cert', $upload_dir, $allowed_types, $max_file_size, $errors, $birth_certificate_path
        );
         if ($new_birth_cert_path === false) goto skip_db_ops_intl_docs; // Critical directory error

        // Update paths if upload was successful (returned a string path)
        $passport_scan_path = ($new_passport_path !== null) ? $new_passport_path : $passport_scan_path;
        $birth_certificate_path = ($new_birth_cert_path !== null) ? $new_birth_cert_path : $birth_certificate_path;

        // --- Validation ---
        // Check if required documents are present *after* processing uploads
        if (empty($passport_scan_path)) {
            // Only add error if no file exists AND no new file was attempted or failed validation
            if (!isset($_FILES['passport_scan']) || $_FILES['passport_scan']['error'] == UPLOAD_ERR_NO_FILE || isset($errors['passport_scan'])) {
                 $errors['passport_scan'] = "Passport Scan is required.";
            }
        }
        // Add validation for other required documents here

        skip_db_ops_intl_docs: // Label for goto jump in case of critical file system error

        // --- Database Operation ---
        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Check if record exists in 'application_documents_international'
                $stmt_check = $conn->prepare("SELECT id FROM application_documents_international WHERE application_id = ?");
                if (!$stmt_check) throw new Exception("Prepare failed (Check Docs): " . $conn->error);
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    // Update existing record
                    $sql = "UPDATE application_documents_international SET
                                passport_scan_path = ?, birth_certificate_path = ?, updated_at = NOW()
                            WHERE application_id = ?";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (UPDATE Docs): " . $conn->error);
                    // 2 data fields + application_id = 3 params (s s i)
                    $stmt_save->bind_param("ssi",
                        $passport_scan_path, $birth_certificate_path,
                        $application_id
                    );
                } else {
                    // Insert new record
                    $sql = "INSERT INTO application_documents_international
                                (application_id, passport_scan_path, birth_certificate_path)
                            VALUES (?, ?, ?)"; // 3 placeholders
                    $stmt_save = $conn->prepare($sql);
                     if (!$stmt_save) throw new Exception("Prepare failed (INSERT Docs): " . $conn->error);
                     // 3 params (i s s)
                    $stmt_save->bind_param("iss",
                        $application_id, $passport_scan_path, $birth_certificate_path
                    );
                }

                if (!$stmt_save->execute()) {
                    throw new Exception("Execute failed (Save Docs): " . $stmt_save->error);
                }
                $stmt_save->close();

                // Update main application status/step
                if ($application_status === 'Nominator Info Complete') {
                    $new_status = 'Documents Complete'; // Or 'Ready for Review'
                    $next_step = 'step4'; // Assuming step 4 is review/submit
                    $stmt_update_app = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ?");
                    if (!$stmt_update_app) throw new Exception("Prepare failed (App Update): " . $conn->error);
                    $stmt_update_app->bind_param("ssi", $new_status, $next_step, $application_id);
                    if (!$stmt_update_app->execute()) {
                         throw new Exception("Execute failed (App Update): " . $stmt_update_app->error);
                    }
                    $stmt_update_app->close();
                } else {
                     // Just update the timestamp if editing
                     $stmt_update_time = $conn->prepare("UPDATE applications SET last_updated = NOW() WHERE id = ?");
                     if (!$stmt_update_time) throw new Exception("Prepare failed (App Time Update): " . $conn->error);
                     $stmt_update_time->bind_param("i", $application_id);
                     $stmt_update_time->execute();
                     $stmt_update_time->close();
                }

                $conn->commit();
                $success = "Documents uploaded successfully.";

                // Update $application_data with new paths
                 $application_data['passport_scan_path'] = $passport_scan_path;
                 $application_data['birth_certificate_path'] = $birth_certificate_path;

                // Redirect to next step:
                 redirect('application-step4-international.php'); // *** Make sure this file exists ***
                 exit;

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving International application step 3 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = "An error occurred while saving document information. Details: " . htmlspecialchars($e->getMessage());
                // Attempt to rollback file uploads if they happened during the failed transaction
                if ($new_passport_path && $new_passport_path !== ($application_data['passport_scan_path'] ?? null) && file_exists($new_passport_path)) {
                    @unlink($new_passport_path);
                }
                 if ($new_birth_cert_path && $new_birth_cert_path !== ($application_data['birth_certificate_path'] ?? null) && file_exists($new_birth_cert_path)) {
                    @unlink($new_birth_cert_path);
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
    <title>Application: Step 3 - Document Upload (International) | Musabaqa</title>
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
        .upload-section { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .upload-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
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
                                <h4 class="page-title">Application - Step 3: Document Upload (International)</h4>
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="application.php">Application</a></li>
                                        <li class="breadcrumb-item active">Step 3</li>
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
                                <div class="progress-bar" role="progressbar" style="width: 75%;" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">Step 3 of 4</div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                     <div class="row">
                        <div class="col-12">
                             <div class="alert alert-info bg-info text-white border-0" role="alert">
                                Please upload clear scans or photos of the required documents. Allowed formats: PDF, JPG, PNG. Max size: 5MB per file.
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


                    <!-- Document Upload Form -->
                    <div class="row">
                        <div class="col-12">
                            <form id="step3-form-intl" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-file-upload-line me-1"></i>Required Documents</h5>
                                    </div>
                                    <div class="card-body">

                                        <!-- Passport Scan Upload -->
                                        <div class="upload-section">
                                            <label for="passport_scan" class="form-label">Passport Data Page Scan <span class="text-danger">*</span></label>
                                            <p class="text-muted fs-13">Upload a clear scan or photo of the page showing your photo, name, date of birth, and passport number.</p>
                                            <input type="file" class="form-control <?php echo isset($errors['passport_scan']) ? 'is-invalid' : ''; ?>" id="passport_scan" name="passport_scan" accept=".pdf,.jpg,.jpeg,.png">
                                            <?php if (isset($errors['passport_scan'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['passport_scan']); ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($application_data['passport_scan_path']) && file_exists($application_data['passport_scan_path'])): ?>
                                                <div class="mt-2">
                                                    <span class="file-upload-info">Current Passport Scan:</span>
                                                    <a href="<?php echo htmlspecialchars($application_data['passport_scan_path']); ?>" target="_blank" class="existing-file-link ms-2">
                                                        <i class="ri-eye-line"></i> <?php echo htmlspecialchars(basename($application_data['passport_scan_path'])); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Birth Certificate Upload (Optional) -->
                                        <div class="upload-section">
                                            <label for="birth_certificate" class="form-label">Birth Certificate Scan (Optional)</label>
                                            <p class="text-muted fs-13">If available, upload a scan or photo of your birth certificate.</p>
                                            <input type="file" class="form-control <?php echo isset($errors['birth_certificate']) ? 'is-invalid' : ''; ?>" id="birth_certificate" name="birth_certificate" accept=".pdf,.jpg,.jpeg,.png">
                                            <?php if (isset($errors['birth_certificate'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['birth_certificate']); ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($application_data['birth_certificate_path']) && file_exists($application_data['birth_certificate_path'])): ?>
                                                <div class="mt-2">
                                                    <span class="file-upload-info">Current Birth Certificate:</span>
                                                    <a href="<?php echo htmlspecialchars($application_data['birth_certificate_path']); ?>" target="_blank" class="existing-file-link ms-2">
                                                        <i class="ri-eye-line"></i> <?php echo htmlspecialchars(basename($application_data['birth_certificate_path'])); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Add sections for other documents if needed -->

                                    </div><!-- end card-body -->
                                </div> <!-- end card -->


                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-between mt-4 mb-4">
                                    <a href="application-step2-international.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Step 2</a>
                                    <button type="submit" class="btn btn-primary">Save and Continue to Step 4 <i class="ri-arrow-right-line ms-1"></i></button>
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

            // Optional: Update file input labels to show filename
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', function(e) {
                    const fileName = e.target.files[0] ? e.target.files[0].name : 'Choose file';
                    // Find the label associated with this input (might need adjustment based on HTML structure)
                    // const label = document.querySelector(`label[for="${e.target.id}"]`);
                    // if (label) { label.textContent = fileName; } // This might overwrite the main label text
                    console.log(`Selected file for ${e.target.id}:`, fileName);
                });
            });
        });
    </script>

</body>
</html>