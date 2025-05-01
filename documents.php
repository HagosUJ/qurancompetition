<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/documents.php
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
    error_log("User ID missing from session for logged-in user on documents.php.");
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
$contestant_type = null;
$application_status = null;
$current_step = null;

// Verify that the user has an application and is at the correct step
$stmt_app = $conn->prepare("SELECT id, status, current_step, contestant_type FROM applications WHERE user_id = ?");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        $contestant_type = $app['contestant_type'];
        $application_status = $app['status'];
        $current_step = $app['current_step'];

        // --- Redirect International Users ---
        if ($contestant_type === 'international') {
            // International users have their own Step 3 page
            redirect('application-step3-international.php');
            exit;
        }
        // --- End Redirect ---

        // Check if the user (Nigerian) should be on this step
        // Allow access if step 2 is complete OR if they are already on documents step or beyond
        $allowed_statuses = ['Sponsor Info Complete', 'Documents Uploaded', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Information Requested'];
        $is_correct_prior_step = ($application_status === 'Sponsor Info Complete');
        $is_on_or_after_this_step = in_array($application_status, $allowed_statuses) && $current_step !== 'step1' && $current_step !== 'step2'; // Crude check, adjust as needed

        if (!$is_correct_prior_step && !$is_on_or_after_this_step) {
             // Redirect back to step 2 (Nigerian) if they haven't completed it.
             redirect('application-step2-nigerian.php?error=step2_incomplete');
             exit;
        }
         // If status is submitted/reviewed, maybe redirect to review or dashboard?
         // if (in_array($application_status, ['Submitted', 'Under Review', 'Approved', 'Rejected'])) { redirect('application-review.php'); exit; }

    } else {
        // No application found
        redirect('application.php?error=app_not_found');
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die("Error verifying application status. Please try again later.");
}

// --- Define Required Documents (Nigerian Specific) ---
$required_documents = [
    'national_id' => 'National ID Card / NIN Slip', // Nigerian specific
    'birth_certificate' => 'Birth Certificate / Declaration of Age',
    'recommendation_letter' => 'Recommendation Letter from Sponsor', // Nigerian specific term
    'lg_indigene_certificate' => 'LG Indigene Certificate', // Nigerian specific
    // Add/remove documents specific to Nigerian applicants
];

// --- Fetch Existing Documents (Using the generic application_documents table) ---
$existing_documents = [];
// IMPORTANT: This assumes a generic 'application_documents' table exists and is used for Nigerians.
// If Nigerians also use a specific table like 'application_documents_nigerian', update the query.
$stmt_docs = $conn->prepare("SELECT id, document_type, file_path, original_filename, created_at FROM application_documents WHERE application_id = ?");
if ($stmt_docs) {
    $stmt_docs->bind_param("i", $application_id);
    $stmt_docs->execute();
    $result_docs = $stmt_docs->get_result();
    while ($doc = $result_docs->fetch_assoc()) {
        // Only store documents relevant to the Nigerian required list
        if (array_key_exists($doc['document_type'], $required_documents)) {
            $existing_documents[$doc['document_type']] = $doc;
        }
    }
    $stmt_docs->close();
} else {
    error_log("Failed to prepare statement for fetching existing documents: " . $conn->error);
    // Non-fatal error
}


// --- File Upload Configuration ---
$upload_dir = 'uploads/documents/'; // Common directory okay if filenames are unique
$allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// --- Form Processing ---
$errors = [];
$success = '';
$files_uploaded_in_request = []; // Track files uploaded in this specific POST request

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid form submission. Please try again.";
    } else {
        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                 $errors['form'] = "Failed to create upload directory. Please contact support.";
                 goto end_of_post_processing;
            }
        }

        $conn->begin_transaction();

        try {
            // Loop through the defined NIGERIAN document types
            foreach ($required_documents as $doc_type => $doc_label) {
                if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$doc_type];
                    $file_name = $file['name'];
                    $file_tmp_path = $file['tmp_name'];
                    $file_size = $file['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $original_filename = sanitize_filename($file_name);

                    // Validation
                    if (!in_array($file_ext, $allowed_types)) {
                        $errors[$doc_type] = "Invalid file type for {$doc_label}. Allowed: " . implode(', ', $allowed_types);
                        continue;
                    }
                    if ($file_size > $max_file_size) {
                        $errors[$doc_type] = "File size for {$doc_label} exceeds the limit (" . ($max_file_size / 1024 / 1024) . "MB).";
                        continue;
                    }

                    // Generate unique filename (includes user/app ID)
                    $unique_filename = "user_{$user_id}_app_{$application_id}_doc_{$doc_type}_" . uniqid() . '.' . $file_ext;
                    $destination = $upload_dir . $unique_filename;

                    // Move the uploaded file
                    if (move_uploaded_file($file_tmp_path, $destination)) {
                        $files_uploaded_in_request[$doc_type] = $destination;

                        // Check if document record exists in the generic table
                        $stmt_check_doc = $conn->prepare("SELECT id, file_path FROM application_documents WHERE application_id = ? AND document_type = ?");
                        if (!$stmt_check_doc) throw new Exception("Prepare failed (Check Doc): " . $conn->error);
                        $stmt_check_doc->bind_param("is", $application_id, $doc_type);
                        $stmt_check_doc->execute();
                        $result_check_doc = $stmt_check_doc->get_result();
                        $existing_doc_record = $result_check_doc->fetch_assoc();
                        $stmt_check_doc->close();

                        $old_file_path = null;
                        if ($existing_doc_record) {
                            // Update existing record
                            $old_file_path = $existing_doc_record['file_path'];
                            $stmt_save_doc = $conn->prepare("UPDATE application_documents SET file_path = ?, original_filename = ?, updated_at = NOW() WHERE id = ?");
                            if (!$stmt_save_doc) throw new Exception("Prepare failed (Update Doc): " . $conn->error);
                            $stmt_save_doc->bind_param("ssi", $destination, $original_filename, $existing_doc_record['id']);
                        } else {
                            // Insert new record
                            $stmt_save_doc = $conn->prepare("INSERT INTO application_documents (application_id, document_type, file_path, original_filename) VALUES (?, ?, ?, ?)");
                             if (!$stmt_save_doc) throw new Exception("Prepare failed (Insert Doc): " . $conn->error);
                            $stmt_save_doc->bind_param("isss", $application_id, $doc_type, $destination, $original_filename);
                        }

                        if (!$stmt_save_doc->execute()) {
                            throw new Exception("Execute failed (Save Doc): " . $stmt_save_doc->error);
                        }
                        $stmt_save_doc->close();

                        // Delete old file *after* successful DB update
                        if ($old_file_path && file_exists($old_file_path) && $old_file_path !== $destination) {
                            @unlink($old_file_path); // Use @ to suppress errors if file already gone
                        }

                        // Update the $existing_documents array for immediate display feedback
                        $existing_documents[$doc_type] = [
                            'id' => $existing_doc_record['id'] ?? $conn->insert_id,
                            'document_type' => $doc_type,
                            'file_path' => $destination,
                            'original_filename' => $original_filename,
                            'created_at' => $existing_doc_record['created_at'] ?? date('Y-m-d H:i:s')
                        ];

                    } else {
                        $errors[$doc_type] = "Failed to move uploaded file for {$doc_label}. Check permissions.";
                    }
                } elseif (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errors[$doc_type] = "Error uploading {$doc_label}: Code " . $_FILES[$doc_type]['error'];
                }
            } // End foreach

            // Check if all NIGERIAN required documents are now uploaded
            $all_required_uploaded = true;
            foreach (array_keys($required_documents) as $req_doc_type) {
                if (!isset($existing_documents[$req_doc_type])) {
                    $all_required_uploaded = false;
                    break;
                }
            }

            // Update main application status/step for NIGERIAN applicant
            if ($all_required_uploaded && $application_status === 'Sponsor Info Complete') {
                $new_status = 'Documents Uploaded';
                $next_step = 'review'; // Nigerian review step/page name
                $stmt_update_app = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ?");
                if (!$stmt_update_app) throw new Exception("Prepare failed (App Update): " . $conn->error);
                $stmt_update_app->bind_param("ssi", $new_status, $next_step, $application_id);
                if (!$stmt_update_app->execute()) {
                    throw new Exception("Execute failed (App Update): " . $stmt_update_app->error);
                }
                $stmt_update_app->close();
                $application_status = $new_status; // Update status locally
            } elseif (!empty($files_uploaded_in_request)) {
                 $stmt_update_time = $conn->prepare("UPDATE applications SET last_updated = NOW() WHERE id = ?");
                 if (!$stmt_update_time) throw new Exception("Prepare failed (App Time Update): " . $conn->error);
                 $stmt_update_time->bind_param("i", $application_id);
                 $stmt_update_time->execute();
                 $stmt_update_time->close();
            }

            $conn->commit();
            if (!empty($files_uploaded_in_request) && empty($errors)) {
                 $success = "Documents uploaded successfully.";
            }

             // Redirect NIGERIAN user to review page if all docs are uploaded and status updated
            if ($all_required_uploaded && $application_status === 'Documents Uploaded') {
                 redirect('application-review.php'); // Assuming this is the Nigerian review page
                 exit;
            }

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error processing documents for app ID {$application_id}: " . $e->getMessage());
            $errors['form'] = "An error occurred while saving documents. Please try again.";

            foreach ($files_uploaded_in_request as $doc_type => $filepath) {
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
            }
        }

        end_of_post_processing:; // Label for goto jump

    } // End CSRF check
} // End POST check

// --- Security Headers ---
// ... (keep existing headers) ...
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Application: Document Upload (Nigerian) | Musabaqa</title> <!-- Updated Title -->
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        /* ... (keep existing styles) ... */
        .form-control.is-invalid { border-color: #dc3545; }
        .invalid-feedback { display: block; color: #dc3545; font-size: 0.875em; }
        .form-label { font-weight: 500; }
        .document-list-item { border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 1rem; }
        .document-list-item:last-child { border-bottom: none; }
        .file-info { font-size: 0.9em; color: #6c757d; }
        .upload-section { margin-bottom: 1.5rem; }
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
                                <!-- Updated Title -->
                                <h4 class="page-title">Application - Step 3: Document Upload (Nigerian Applicants)</h4>
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
                     <?php if (isset($_GET['error']) && $_GET['error'] === 'step2_incomplete'): ?>
                         <div class="alert alert-warning alert-dismissible fade show" role="alert">
                             <i class="ri-alert-line me-1"></i> Please complete Step 2 (Sponsor Information) before proceeding.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                         </div>
                    <?php endif; ?>


                    <!-- Document Upload Form -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Required Documents (Nigerian Applicants)</h5> <!-- Updated Title -->
                                    <p class="text-muted mb-4">Please upload clear copies of the following documents. Allowed file types: PDF, DOC, DOCX, JPG, PNG. Max size: 5MB per file.</p>

                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <!-- Loop through NIGERIAN required documents -->
                                        <?php foreach ($required_documents as $doc_type => $doc_label): ?>
                                            <div class="upload-section document-list-item">
                                                <div class="row align-items-center">
                                                    <div class="col-md-4">
                                                        <label for="<?php echo $doc_type; ?>" class="form-label"><?php echo htmlspecialchars($doc_label); ?> <span class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <?php if (isset($existing_documents[$doc_type])):
                                                            $doc = $existing_documents[$doc_type];
                                                            $file_url = htmlspecialchars($doc['file_path']);
                                                        ?>
                                                            <div class="mb-2">
                                                                <i class="ri-check-double-line text-success me-1"></i> Uploaded:
                                                                <a href="<?php echo $file_url; ?>" target="_blank" title="View <?php echo htmlspecialchars($doc['original_filename']); ?>">
                                                                    <?php echo htmlspecialchars($doc['original_filename']); ?>
                                                                </a>
                                                                <span class="file-info ms-2">(Uploaded: <?php echo date('M d, Y H:i', strtotime($doc['created_at'])); ?>)</span>
                                                            </div>
                                                            <label for="<?php echo $doc_type; ?>" class="form-label text-muted small">Replace file (optional):</label>
                                                        <?php endif; ?>

                                                        <input type="file" class="form-control <?php echo isset($errors[$doc_type]) ? 'is-invalid' : ''; ?>" id="<?php echo $doc_type; ?>" name="<?php echo $doc_type; ?>" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                        <?php if (isset($errors[$doc_type])): ?>
                                                            <div class="invalid-feedback"><?php echo $errors[$doc_type]; ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>


                                        <div class="mt-4 d-flex justify-content-between">
                                             <?php
                                                // Back button always points to Nigerian Step 2 now
                                                $prev_step_page = 'application-step2-nigerian.php';
                                             ?>
                                            <a href="<?php echo $prev_step_page; ?>" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Sponsor Info</a>

                                            <?php
                                                // Check if all NIGERIAN required documents are uploaded
                                                $all_required_uploaded_final = true;
                                                foreach (array_keys($required_documents) as $req_doc_type) {
                                                    if (!isset($existing_documents[$req_doc_type])) {
                                                        $all_required_uploaded_final = false;
                                                        break;
                                                    }
                                                }
                                                $button_text = $all_required_uploaded_final ? "Save and Continue to Review" : "Save Documents";
                                                $button_icon = $all_required_uploaded_final ? "ri-arrow-right-line" : "ri-save-line";
                                            ?>
                                            <button type="submit" class="btn btn-primary"><?php echo $button_text; ?> <i class="<?php echo $button_icon; ?> ms-1"></i></button>
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

    <!-- Removed redundant dashboard script -->
    <script src="assets/js/app.min.js"></script>

    <!-- Optional: JavaScript for Delete Confirmation (Keep if needed) -->
    <?php /*
    <script>
        function confirmDelete(docId, docType) {
            // ... (keep existing delete confirmation JS if implemented) ...
        }
    </script>
    */ ?>

</body>
</html>