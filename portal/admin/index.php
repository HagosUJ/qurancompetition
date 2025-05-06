<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/admin/index.php
require_once '../includes/auth.php'; // Adjust path for includes

// --- Admin Authentication & Authorization ---
if (!is_logged_in()) {
    redirect('../sign-in.php?reason=admin_required'); // Redirect to main sign-in
    exit;
}

// Check if the user has the 'admin' role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Optional: Log this attempt
    error_log("User ID {$_SESSION['user_id']} attempted to access admin area without admin role.");
    // Redirect non-admins to the main dashboard or an error page
    redirect('../index.php?error=unauthorized');
    exit;
}

// Session timeout check (copy from main index.php)
$timeout_duration = SESSION_TIMEOUT_DURATION ?? 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    logout_user('../sign-in.php?reason=timeout'); // Adjust redirect path
    exit;
}
$_SESSION['last_activity'] = time();

// Get admin details
$admin_id = $_SESSION['user_id'];
$admin_fullname = $_SESSION['user_fullname'] ?? 'Admin';

// --- Fetch Admin-Specific Data (Example: Counts) ---
global $conn;
$pending_applications_count = 0;
$total_users_count = 0;

// Count pending applications (Example: status 'Submitted' or 'Under Review')
$stmt_pending = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status IN ('Submitted', 'Under Review')");
if ($stmt_pending) {
    $stmt_pending->execute();
    $result_pending = $stmt_pending->get_result();
    if ($data_pending = $result_pending->fetch_assoc()) {
        $pending_applications_count = (int)$data_pending['count'];
    }
    $stmt_pending->close();
} else {
    error_log("Admin Dashboard: Failed to prepare statement for pending applications count: " . $conn->error);
}

// Count total users
$stmt_users = $conn->prepare("SELECT COUNT(*) as count FROM users");
if ($stmt_users) {
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    if ($data_users = $result_users->fetch_assoc()) {
        $total_users_count = (int)$data_users['count'];
    }
    $stmt_users->close();
} else {
    error_log("Admin Dashboard: Failed to prepare statement for total users count: " . $conn->error);
}


// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Adjust CSP if necessary
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard | Musabaqa</title>
    <?php include '../layouts/title-meta.php'; // Adjust path ?>
    <?php include '../layouts/head-css.php'; // Adjust path ?>
    <style>
        /* Add any admin-specific styles if needed */
        .stat-card i { font-size: 2.5rem; opacity: 0.6; }
    </style>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <?php include '../layouts/menu.php'; // Adjust path - TODO: Consider an admin-specific menu ?>

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
                                <h4 class="page-title">Admin Dashboard</h4>
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                                        <li class="breadcrumb-item active">Dashboard</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Stats Row -->
                    <div class="row">
                        <div class="col-xl-4 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Pending Applications">Pending Applications</h5>
                                            <h3 class="my-2 py-1"><?php echo $pending_applications_count; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <a href="review-applications.php" class="text-primary">View Details <i class="ri-arrow-right-line"></i></a>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <i class="ri-file-list-3-line stat-card"></i>
                                        </div>
                                    </div>
                                </div> <!-- end card-body-->
                            </div> <!-- end card-->
                        </div> <!-- end col-->

                        <div class="col-xl-4 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Users">Total Users</h5>
                                            <h3 class="my-2 py-1"><?php echo $total_users_count; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <a href="manage-users.php" class="text-primary">Manage Users <i class="ri-arrow-right-line"></i></a>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <i class="ri-group-line stat-card"></i>
                                        </div>
                                    </div>
                                </div> <!-- end card-body-->
                            </div> <!-- end card-->
                        </div> <!-- end col-->

                        <!-- Add more stat cards as needed (e.g., Approved Applications, Resources Count) -->

                    </div> <!-- end row -->


                    <!-- Admin Actions Row -->
                     <div class="row mt-3">
                        <div class="col-12 mb-2">
                            <h4 class="page-title">Management Actions</h4>
                        </div>
                        <div class="col-xl-4 col-md-6">
                             <div class="card cta-box bg-primary text-white">
                                 <div class="card-body">
                                     <div class="d-flex align-items-center justify-content-between">
                                         <div>
                                             <h5 class="mt-0 fw-normal cta-box-title text-white">Review Applications</h5>
                                             <p class="text-white-50 mb-2">View and process submitted applications</p>
                                             <a href="review-applications.php" class="link-light link-offset-3 fw-bold">Go to Review <i class="ri-arrow-right-line"></i></a>
                                         </div>
                                         <i class="ri-file-search-line ms-3 fs-20"></i>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <div class="col-xl-4 col-md-6">
                             <div class="card cta-box bg-info text-white">
                                 <div class="card-body">
                                     <div class="d-flex align-items-center justify-content-between">
                                         <div>
                                             <h5 class="mt-0 fw-normal cta-box-title text-white">Manage Users</h5>
                                             <p class="text-white-50 mb-2">View, edit, or manage user accounts</p>
                                             <a href="manage-users.php" class="link-light link-offset-3 fw-bold">Go to Users <i class="ri-arrow-right-line"></i></a>
                                         </div>
                                         <i class="ri-group-line ms-3 fs-20"></i>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <div class="col-xl-4 col-md-6">
                             <div class="card cta-box bg-success text-white">
                                 <div class="card-body">
                                     <div class="d-flex align-items-center justify-content-between">
                                         <div>
                                             <h5 class="mt-0 fw-normal cta-box-title text-white">Manage Resources</h5>
                                             <p class="text-white-50 mb-2">Add or update competition resources</p>
                                             <a href="manage-resources.php" class="link-light link-offset-3 fw-bold">Go to Resources <i class="ri-arrow-right-line"></i></a>
                                         </div>
                                         <i class="ri-book-open-line ms-3 fs-20"></i>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <div class="col-xl-4 col-md-6">
                             <div class="card cta-box bg-warning text-dark"> <!-- Changed background for contrast -->
                                 <div class="card-body">
                                     <div class="d-flex align-items-center justify-content-between">
                                         <div>
                                             <h5 class="mt-0 fw-normal cta-box-title">Send Notifications</h5>
                                             <p class="text-dark-50 mb-2">Send messages to users or groups</p>
                                             <a href="send-notification.php" class="link-dark link-offset-3 fw-bold">Compose Message <i class="ri-arrow-right-line"></i></a>
                                         </div>
                                         <i class="ri-mail-send-line ms-3 fs-20"></i>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <!-- Add more action boxes as needed (e.g., Manage Schedule, Settings) -->
                     </div>


                </div> <!-- container-fluid -->
            </div> <!-- content -->

            <?php include '../layouts/footer.php'; // Adjust path ?>

        </div>
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

    </div>
    <!-- END wrapper -->

    <?php include '../layouts/right-sidebar.php'; // Adjust path ?>
    <?php include '../layouts/footer-scripts.php'; // Adjust path ?>

    <!-- Include app.min.js for template functionality -->
    <script src="../assets/js/app.min.js"></script> <?php // Adjust path ?>

    <!-- Add any page-specific JS here if needed -->

</body>
</html>