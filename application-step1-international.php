<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/application-step1-international.php
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
$user_fullname = $_SESSION['user_fullname'] ?? 'Participant'; // Get user's name for welcome message
if (!$user_id) {
    error_log("User ID missing from session for logged-in user on application-step1-international.php.");
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
$application_data = []; // To store existing data for pre-filling
$application_status = 'Not Started'; // Default status

$stmt_app = $conn->prepare("SELECT id, status, current_step FROM applications WHERE user_id = ? AND contestant_type = 'international'");
if ($stmt_app) {
    $stmt_app->bind_param("i", $user_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    if ($app = $result_app->fetch_assoc()) {
        $application_id = $app['id'];
        $application_status = $app['status'];
        // Optionally check status/step if needed

        // Fetch existing details for this application step
        // *** ASSUMING a table named 'application_details_international' exists ***
        $stmt_details = $conn->prepare("SELECT * FROM application_details_international WHERE application_id = ?");
        if ($stmt_details) {
            $stmt_details->bind_param("i", $application_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                $application_data = $result_details->fetch_assoc();
            }
            $stmt_details->close();
        } else {
             error_log("Failed to prepare statement for fetching International details: " . $conn->error);
             $errors['form'] = "Could not load existing application details. Please try again later.";
        }

    } else {
        // No application found or type mismatch
        if (!isset($_GET['error'])) {
            redirect('application.php?error=app_not_found_or_mismatch');
        } else {
             die("Application not found or type mismatch.");
        }
        exit;
    }
    $stmt_app->close();
} else {
    error_log("Failed to prepare statement for checking application: " . $conn->error);
    die("Error verifying application status. Please try again later.");
}

// --- Define profile picture for topbar ---
$profile_picture = $application_data['photo_path'] ?? $_SESSION['user_profile_picture'] ?? 'assets/images/users/avatar-1.jpg';

// --- Form Processing ---
$errors = [];
$success = '';
$upload_dir = 'uploads/photos/'; // Define upload directory
$allowed_types = ['jpg', 'jpeg', 'png'];
$max_file_size = 2 * 1024 * 1024; // 2MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid form submission. Please try again.";
    } else {
        // Sanitize and retrieve POST data
        $full_name_passport = sanitize_input($_POST['full_name_passport'] ?? '');
        $dob = sanitize_input($_POST['dob'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $city = sanitize_input($_POST['city'] ?? ''); // Added City
        $country_residence = sanitize_input($_POST['country_residence'] ?? ''); // Changed from state
        $nationality = sanitize_input($_POST['nationality'] ?? ''); // Added Nationality
        $passport_number = sanitize_input($_POST['passport_number'] ?? ''); // Added Passport Number
        $phone_number = sanitize_input($_POST['phone_number'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $health_status = sanitize_input($_POST['health_status'] ?? '');
        $languages_spoken = sanitize_input($_POST['languages_spoken'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $narration = ($category === 'hifz') ? sanitize_input($_POST['narration'] ?? '') : null;

        // --- Validation ---
        $age = null; // Initialize age
        if (empty($full_name_passport)) $errors['full_name_passport'] = "Full Name is required.";
        if (empty($dob)) {
            $errors['dob'] = "Date of Birth is required.";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $errors['dob'] = "Invalid Date of Birth format (use YYYY-MM-DD).";
        } else {
            // Calculate age if DOB is valid
            try {
                $birthDate = new DateTime($dob);
                $today = new DateTime('today');
                if ($birthDate > $today) {
                    $errors['dob'] = "Date of Birth cannot be in the future.";
                } else {
                    $age = $birthDate->diff($today)->y;
                    if ($age < 1) {
                        $errors['dob'] = "Please enter a valid Date of Birth.";
                    }
                }
            } catch (Exception $e) {
                $errors['dob'] = "Invalid Date of Birth.";
            }
        }
        if ($age === null && empty($errors['dob'])) $errors['age'] = "Could not calculate age from Date of Birth.";

        if (empty($address)) $errors['address'] = "Residential Address is required.";
        if (empty($city)) $errors['city'] = "City is required.";
        if (empty($country_residence)) $errors['country_residence'] = "Country of Residence is required.";
        if (empty($nationality)) $errors['nationality'] = "Nationality is required.";
        if (empty($passport_number)) $errors['passport_number'] = "Passport Number is required.";
        // Add more specific passport validation if needed (regex)

        if (empty($phone_number)) $errors['phone_number'] = "Phone Number is required.";
        if (!empty($phone_number) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $phone_number)) $errors['phone_number'] = "Invalid Phone Number format.";
        if ($email === false) $errors['email'] = "Valid Email is required.";
        if (empty($health_status)) $errors['health_status'] = "Health Status is required.";
        if (empty($languages_spoken)) $errors['languages_spoken'] = "Languages Spoken is required.";
        if (empty($category) || !in_array($category, ['qiraat', 'hifz'])) $errors['category'] = "Please select a valid category.";
        if ($category === 'hifz' && empty($narration)) $errors['narration'] = "Narration is required for the Hifz category.";

        // --- File Upload Handling (Identical to Nigerian form) ---
        $photo_path = $application_data['photo_path'] ?? null;
        $new_file_uploaded = false;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['photo']['tmp_name'];
            $file_name = $_FILES['photo']['name'];
            $file_size = $_FILES['photo']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                $errors['photo'] = "Invalid file type. Only JPG, JPEG, PNG allowed.";
            } elseif ($file_size > $max_file_size) {
                $errors['photo'] = "File size exceeds the limit (2MB).";
            } else {
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $errors['photo'] = "Failed to create photo upload directory. Check server permissions.";
                        goto skip_file_move_intl;
                    }
                }
                $unique_filename = "user_{$user_id}_app_{$application_id}_" . uniqid() . '.' . $file_ext;
                $destination = $upload_dir . $unique_filename;

                if (move_uploaded_file($file_tmp_path, $destination)) {
                    $old_photo_path = $application_data['photo_path'] ?? null;
                    if ($old_photo_path && file_exists($old_photo_path) && $old_photo_path !== $destination) {
                        @unlink($old_photo_path);
                    }
                    $photo_path = $destination;
                    $new_file_uploaded = true;
                } else {
                    error_log("move_uploaded_file failed: From '{$file_tmp_path}' to '{$destination}' for user {$user_id}");
                    $errors['photo'] = "Failed to upload photo. Please ensure the 'uploads/photos' directory is writable by the web server.";
                }
            }
        } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors['photo'] = "Error uploading photo: Code " . $_FILES['photo']['error'];
        } elseif (empty($photo_path)) {
             $errors['photo'] = "Passport-Sized Photo is required.";
        }

        skip_file_move_intl: // Label for goto jump

        // --- Database Operation ---
        if (empty($errors) && $age === null) {
             $errors['age'] = "Age could not be determined from Date of Birth.";
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Check if record exists in 'application_details_international'
                $stmt_check = $conn->prepare("SELECT id FROM application_details_international WHERE application_id = ?");
                if (!$stmt_check) throw new Exception("Prepare failed (Check): " . $conn->error);
                $stmt_check->bind_param("i", $application_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $existing_record = $result_check->fetch_assoc();
                $stmt_check->close();

                if ($existing_record) {
                    // Update existing record
                    $sql = "UPDATE application_details_international SET
                                full_name_passport = ?, dob = ?, age = ?, address = ?, city = ?, country_residence = ?,
                                nationality = ?, passport_number = ?, phone_number = ?, email = ?, health_status = ?,
                                languages_spoken = ?, photo_path = ?, category = ?, narration = ?, updated_at = NOW()
                            WHERE application_id = ?";
                    $stmt_save = $conn->prepare($sql);
                    if (!$stmt_save) throw new Exception("Prepare failed (UPDATE): " . $conn->error);
                    // 15 data fields + application_id = 16 params (s s i s s s s s s s s s s s i) - Check types carefully
                    $stmt_save->bind_param("ssissssssssssssi",
                        $full_name_passport, $dob, $age, $address, $city, $country_residence, $nationality,
                        $passport_number, $phone_number, $email, $health_status, $languages_spoken,
                        $photo_path, $category, $narration,
                        $application_id
                    );
                } else {
                    // Insert new record
                    $sql = "INSERT INTO application_details_international
                                (application_id, full_name_passport, dob, age, address, city, country_residence, nationality,
                                 passport_number, phone_number, email, health_status, languages_spoken, photo_path,
                                 category, narration)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 16 placeholders
                    $stmt_save = $conn->prepare($sql);
                     if (!$stmt_save) throw new Exception("Prepare failed (INSERT): " . $conn->error);
                     // 16 params (i s s i s s s s s s s s s s s) - Check types carefully
                    $stmt_save->bind_param("isssisssssssssss",
                        $application_id, $full_name_passport, $dob, $age, $address, $city, $country_residence, $nationality,
                        $passport_number, $phone_number, $email, $health_status, $languages_spoken,
                        $photo_path, $category, $narration
                    );
                }

                if (!$stmt_save->execute()) {
                    throw new Exception("Execute failed: " . $stmt_save->error);
                }
                $stmt_save->close();

                // Update main application status/step (Identical logic to Nigerian form)
                if ($application_status === 'Not Started') {
                    $new_status = 'Personal Info Complete';
                    $next_step = 'step2';
                    $stmt_update_app = $conn->prepare("UPDATE applications SET status = ?, current_step = ?, last_updated = NOW() WHERE id = ?");
                    if (!$stmt_update_app) throw new Exception("Prepare failed (App Update): " . $conn->error);
                    $stmt_update_app->bind_param("ssi", $new_status, $next_step, $application_id);
                    if (!$stmt_update_app->execute()) {
                         throw new Exception("Execute failed (App Update): " . $stmt_update_app->error);
                    }
                    $stmt_update_app->close();
                } else {
                     $stmt_update_time = $conn->prepare("UPDATE applications SET last_updated = NOW() WHERE id = ?");
                     if (!$stmt_update_time) throw new Exception("Prepare failed (App Time Update): " . $conn->error);
                     $stmt_update_time->bind_param("i", $application_id);
                     $stmt_update_time->execute();
                     $stmt_update_time->close();
                }

                $conn->commit();
                $success = "Personal information saved successfully. Proceeding to the next step...";

                // Update $application_data with new values
                 $application_data = [
                    'full_name_passport' => $full_name_passport, 'dob' => $dob, 'age' => $age, 'address' => $address,
                    'city' => $city, 'country_residence' => $country_residence, 'nationality' => $nationality,
                    'passport_number' => $passport_number, 'phone_number' => $phone_number, 'email' => $email,
                    'health_status' => $health_status, 'languages_spoken' => $languages_spoken,
                    'photo_path' => $photo_path, 'category' => $category, 'narration' => $narration
                 ];
                 $profile_picture = $photo_path ?? $profile_picture;

                // Redirect to next step:
                 redirect('application-step2-international.php'); // *** Make sure this file exists ***
                 exit;

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error saving International application step 1 for app ID {$application_id}: " . $e->getMessage());
                $errors['form'] = "An error occurred while saving your information. Please check your inputs and try again. Details: " . $e->getMessage();
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
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:;"); // Allow blob: for image preview
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Application: Step 1 (International) | Musabaqa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .form-control.is-invalid, .form-select.is-invalid { border-color: #dc3545; }
        .invalid-feedback { display: block; color: #dc3545; font-size: 0.875em; }
        .form-label { font-weight: 500; }
        .progress-bar { background-color: #0acf97; }
        #photo-preview-container {
            margin-top: 10px;
            border: 1px dashed #ced4da;
            padding: 1rem;
            border-radius: .25rem;
            min-height: 170px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
        }
        #photo-preview {
            max-height: 150px;
            max-width: 100%;
            border-radius: .25rem;
            object-fit: cover;
        }
        .existing-photo-link { font-size: 0.9em; }
        .step-indicator { margin-bottom: 1.5rem; }
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
                                <h4 class="page-title">Application - Step 1: Personal Information (International)</h4>
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="application.php">Application</a></li>
                                        <li class="breadcrumb-item active">Step 1</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step Indicator -->
                    <div class="row step-indicator">
                        <div class="col-12">
                            <div class="progress" style="height: 10px;">
                                <!-- Assuming 4 steps total: Personal, Sponsor/Nominator, Docs, Review -->
                                <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Step 1 of 4</div>
                            </div>
                        </div>
                    </div>

                    <!-- Welcome Message -->
                     <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info bg-info text-white border-0" role="alert">
                                Assalamu Alaikum / Peace be upon you, <strong><?php echo htmlspecialchars($user_fullname); ?>!</strong> Please fill in your personal details accurately.
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


                    <!-- Application Form -->
                    <div class="row">
                        <div class="col-12">
                            <form id="step1-form-intl" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- Personal Details Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-user-line me-1"></i>Contestant's Personal Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Full Name (Passport) -->
                                            <div class="col-md-6 mb-3">
                                                <label for="full_name_passport" class="form-label">Full Name (as on Passport) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['full_name_passport']) ? 'is-invalid' : ''; ?>" id="full_name_passport" name="full_name_passport" value="<?php echo htmlspecialchars($application_data['full_name_passport'] ?? ''); ?>" required>
                                                <?php if (isset($errors['full_name_passport'])): ?><div class="invalid-feedback"><?php echo $errors['full_name_passport']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Date of Birth -->
                                            <div class="col-md-3 mb-3">
                                                <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control <?php echo isset($errors['dob']) ? 'is-invalid' : ''; ?>" id="dob" name="dob" value="<?php echo htmlspecialchars($application_data['dob'] ?? ''); ?>" required onchange="calculateAge()">
                                                <?php if (isset($errors['dob'])): ?><div class="invalid-feedback"><?php echo $errors['dob']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Age (Readonly) -->
                                            <div class="col-md-3 mb-3">
                                                <label for="age" class="form-label">Age (auto-calculated)</label>
                                                <input type="number" class="form-control <?php echo isset($errors['age']) ? 'is-invalid' : ''; ?>" id="age" name="age" value="<?php echo htmlspecialchars($application_data['age'] ?? ''); ?>" readonly required title="Age is calculated automatically from your Date of Birth">
                                                <?php if (isset($errors['age'])): ?><div class="invalid-feedback"><?php echo $errors['age']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Address -->
                                            <div class="col-md-8 mb-3">
                                                <label for="address" class="form-label">Residential Address <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" id="address" name="address" rows="2" required><?php echo htmlspecialchars($application_data['address'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?php echo $errors['address']; ?></div><?php endif; ?>
                                            </div>
                                            <!-- City -->
                                            <div class="col-md-4 mb-3">
                                                <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>" id="city" name="city" value="<?php echo htmlspecialchars($application_data['city'] ?? ''); ?>" required>
                                                <?php if (isset($errors['city'])): ?><div class="invalid-feedback"><?php echo $errors['city']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Country of Residence Dropdown -->
                                            <div class="col-md-4 mb-3">
                                                <label for="country_residence" class="form-label">Country of Residence <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['country_residence']) ? 'is-invalid' : ''; ?>" id="country_residence" name="country_residence" required>
                                                    <option value="" disabled <?php echo empty($application_data['country_residence']) ? 'selected' : ''; ?>>-- Select Country --</option>
                                                    <?php
                                                        $countries = get_countries(); // Assumes function exists in includes/countries.php
                                                        $selected_country = $application_data['country_residence'] ?? ($_POST['country_residence'] ?? '');
                                                        foreach ($countries as $code => $name) {
                                                            $selected = ($name === $selected_country) ? 'selected' : ''; // Match by name
                                                            echo "<option value=\"" . htmlspecialchars($name) . "\" $selected>" . htmlspecialchars($name) . "</option>";
                                                        }
                                                    ?>
                                                </select>
                                                <?php if (isset($errors['country_residence'])): ?><div class="invalid-feedback"><?php echo $errors['country_residence']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Nationality -->
                                            <div class="col-md-4 mb-3">
                                                <label for="nationality" class="form-label">Nationality <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['nationality']) ? 'is-invalid' : ''; ?>" id="nationality" name="nationality" value="<?php echo htmlspecialchars($application_data['nationality'] ?? ''); ?>" required placeholder="e.g., Ghanaian, Egyptian">
                                                <?php if (isset($errors['nationality'])): ?><div class="invalid-feedback"><?php echo $errors['nationality']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Passport Number -->
                                            <div class="col-md-4 mb-3">
                                                <label for="passport_number" class="form-label">Passport Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['passport_number']) ? 'is-invalid' : ''; ?>" id="passport_number" name="passport_number" value="<?php echo htmlspecialchars($application_data['passport_number'] ?? ''); ?>" required>
                                                <?php if (isset($errors['passport_number'])): ?><div class="invalid-feedback"><?php echo $errors['passport_number']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Phone Number -->
                                            <div class="col-md-6 mb-3">
                                                <label for="phone_number" class="form-label">Phone Number (with country code) <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control <?php echo isset($errors['phone_number']) ? 'is-invalid' : ''; ?>" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($application_data['phone_number'] ?? ''); ?>" required placeholder="e.g., +234 801 234 5678">
                                                <?php if (isset($errors['phone_number'])): ?><div class="invalid-feedback"><?php echo $errors['phone_number']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($application_data['email'] ?? $_SESSION['user_email'] ?? ''); ?>" required placeholder="your.email@example.com">
                                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Health Status -->
                                            <div class="col-md-6 mb-3">
                                                <label for="health_status" class="form-label">Health Status <span class="text-danger">*</span></label>
                                                <textarea class="form-control <?php echo isset($errors['health_status']) ? 'is-invalid' : ''; ?>" id="health_status" name="health_status" rows="2" required placeholder="Briefly describe your health status (e.g., Good, Any allergies?)"><?php echo htmlspecialchars($application_data['health_status'] ?? ''); ?></textarea>
                                                <?php if (isset($errors['health_status'])): ?><div class="invalid-feedback"><?php echo $errors['health_status']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Languages Spoken -->
                                            <div class="col-md-6 mb-3">
                                                <label for="languages_spoken" class="form-label">Languages Spoken Fluently <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['languages_spoken']) ? 'is-invalid' : ''; ?>" id="languages_spoken" name="languages_spoken" value="<?php echo htmlspecialchars($application_data['languages_spoken'] ?? ''); ?>" required placeholder="e.g., English, Arabic, French">
                                                <?php if (isset($errors['languages_spoken'])): ?><div class="invalid-feedback"><?php echo $errors['languages_spoken']; ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Photo Upload Card (Identical HTML to Nigerian form) -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-image-line me-1"></i>Passport Photograph</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="photo" class="form-label">Upload Photo <span class="text-danger">*</span></label>
                                                <p class="text-muted fs-13">Clear, recent photo with plain background (JPG, PNG, max 2MB).</p>
                                                <input type="file" class="form-control <?php echo isset($errors['photo']) ? 'is-invalid' : ''; ?>" id="photo" name="photo" accept=".jpg,.jpeg,.png" onchange="previewPhoto(event)">
                                                <?php if (isset($errors['photo'])): ?><div class="invalid-feedback"><?php echo $errors['photo']; ?></div><?php endif; ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Photo Preview</label>
                                                <div id="photo-preview-container">
                                                    <?php if (!empty($application_data['photo_path']) && file_exists($application_data['photo_path'])): ?>
                                                        <img id="photo-preview" src="<?php echo htmlspecialchars($application_data['photo_path']) . '?t=' . time(); ?>" alt="Current Photo">
                                                    <?php else: ?>
                                                        <img id="photo-preview" src="#" alt="Photo Preview" style="display: none;">
                                                        <span id="preview-placeholder" class="text-muted">Image preview will appear here</span>
                                                    <?php endif; ?>
                                                </div>
                                                 <?php if (!empty($application_data['photo_path']) && file_exists($application_data['photo_path'])): ?>
                                                     <a href="<?php echo htmlspecialchars($application_data['photo_path']); ?>" target="_blank" class="existing-photo-link d-block mt-1 text-center">View Current Photo</a>
                                                 <?php endif; ?>
                                            </div>
                                        </div>
                                    </div><!-- end card-body -->
                                </div> <!-- end card -->


                                <!-- Competition Details Card (Identical HTML to Nigerian form) -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="ri-trophy-line me-1"></i>Competition Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Category -->
                                            <div class="col-md-6 mb-3">
                                                <label for="category" class="form-label">Category Participating In <span class="text-danger">*</span></label>
                                                <select class="form-select <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" id="category" name="category" required onchange="toggleNarration()">
                                                    <option value="" disabled <?php echo empty($application_data['category']) ? 'selected' : ''; ?>>-- Select Category --</option>
                                                    <option value="qiraat" <?php echo (($application_data['category'] ?? '') === 'qiraat') ? 'selected' : ''; ?>>First Category: The Seven Qira'at via Ash-Shatibiyyah (Males Only)</option>
                                                    <option value="hifz" <?php echo (($application_data['category'] ?? '') === 'hifz') ? 'selected' : ''; ?>>Second Category: Full Qur'an Memorization (Females Only)</option>
                                                </select>
                                                <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?php echo $errors['category']; ?></div><?php endif; ?>
                                            </div>

                                            <!-- Narration (Conditional) -->
                                            <div class="col-md-6 mb-3" id="narration-field" style="display: <?php echo (($application_data['category'] ?? '') === 'hifz') ? 'block' : 'none'; ?>;">
                                                <label for="narration" class="form-label">Narration (Riwayah) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo isset($errors['narration']) ? 'is-invalid' : ''; ?>" id="narration" name="narration" value="<?php echo htmlspecialchars($application_data['narration'] ?? ''); ?>" placeholder="e.g., Warsh 'an Nafi', Hafs 'an Asim, Qalun 'an Nafi'">
                                                <?php if (isset($errors['narration'])): ?><div class="invalid-feedback"><?php echo $errors['narration']; ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </div> <!-- end card-body -->
                                </div> <!-- end card -->

                                <!-- Action Buttons -->
                                <div class="d-flex justify-content-between mt-4 mb-4">
                                    <a href="index.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Cancel / Back to Overview</a>
                                    <button type="submit" class="btn btn-primary">Save and Continue to Step 2 <i class="ri-arrow-right-line ms-1"></i></button>
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
    <script src="assets/js/app.min.js"></script> <!-- Essential for template functionality -->

    <!-- Page Specific Scripts (Mostly identical to Nigerian, minus LGA population) -->
    <script>
        // Function to calculate age (Identical to Nigerian form)
        function calculateAge() {
            const dobInput = document.getElementById('dob');
            const ageInput = document.getElementById('age');
            const dobValue = dobInput.value;

            ageInput.value = '';
            dobInput.classList.remove('is-invalid');
            const feedback = dobInput.parentNode.querySelector('.invalid-feedback');
            if(feedback) feedback.textContent = '';

            if (dobValue) {
                try {
                    const birthDate = new Date(dobValue);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (isNaN(birthDate.getTime())) {
                         throw new Error("Invalid date format");
                    }
                    if (birthDate > today) {
                        dobInput.classList.add('is-invalid');
                        if(feedback) feedback.textContent = 'Date of Birth cannot be in the future.';
                        return;
                    }
                    let age = today.getFullYear() - birthDate.getFullYear();
                    const m = today.getMonth() - birthDate.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }
                    ageInput.value = age >= 0 ? age : '';
                    if (age < 0) {
                         dobInput.classList.add('is-invalid');
                         if(feedback) feedback.textContent = 'Please enter a valid Date of Birth.';
                    }
                } catch (e) {
                    dobInput.classList.add('is-invalid');
                     if(feedback) feedback.textContent = 'Invalid Date of Birth.';
                }
            }
        }

        // Toggle Narration field (Identical to Nigerian form)
        function toggleNarration() {
            const categorySelect = document.getElementById('category');
            const narrationField = document.getElementById('narration-field');
            const narrationInput = document.getElementById('narration');
            if (categorySelect.value === 'hifz') {
                narrationField.style.display = 'block';
                narrationInput.required = true;
            } else {
                narrationField.style.display = 'none';
                narrationInput.required = false;
                narrationInput.value = '';
            }
        }

        // Preview uploaded photo (Identical to Nigerian form)
        function previewPhoto(event) {
            const reader = new FileReader();
            const output = document.getElementById('photo-preview');
            const placeholder = document.getElementById('preview-placeholder');

            reader.onload = function(){
                output.src = reader.result;
                output.style.display = 'block';
                if(placeholder) placeholder.style.display = 'none';
            };

            if (event.target.files[0]) {
                const fileType = event.target.files[0].type;
                if (!['image/jpeg', 'image/png', 'image/jpg'].includes(fileType)) {
                    alert('Invalid file type. Please select a JPG or PNG image.');
                    event.target.value = '';
                    output.style.display = 'none';
                    if(placeholder) placeholder.style.display = 'block';
                    return;
                }
                 const fileSize = event.target.files[0].size;
                 const maxSize = <?php echo $max_file_size; ?>;
                 if (fileSize > maxSize) {
                     alert('File size exceeds the limit of ' + (maxSize / 1024 / 1024) + 'MB.');
                     event.target.value = '';
                     output.style.display = 'none';
                     if(placeholder) placeholder.style.display = 'block';
                     return;
                 }
                reader.readAsDataURL(event.target.files[0]);
            } else {
                 output.style.display = 'none';
                 if(placeholder) placeholder.style.display = 'block';
                 output.src = '#';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]:not([disabled])'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            calculateAge();
            toggleNarration();
        });
    </script>

</body>
</html>