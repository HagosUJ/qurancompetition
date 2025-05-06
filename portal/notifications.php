<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/notifications.php
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
    error_log("User ID missing from session for logged-in user on notifications.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// --- Fetch Notifications ---
global $conn;
$notifications = [];
$fetch_error = '';

// Prepare SQL query to fetch notifications for the user, newest first
$sql = "SELECT id, subject, message, created_at, is_read
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    } else {
        $fetch_error = "Error fetching notifications results: " . $conn->error;
        error_log($fetch_error);
    }
    $stmt->close();
} else {
    $fetch_error = "Error preparing notification statement: " . $conn->error;
    error_log($fetch_error);
}

// --- Mark Notifications as Read (Simple Approach: Mark all as read on page load) ---
// A more sophisticated approach might mark only specific ones via AJAX/links
// For simplicity, we'll mark all fetched notifications as read now.
if (!empty($notifications) && !$fetch_error) {
    $update_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $update_stmt = $conn->prepare($update_sql);
    if ($update_stmt) {
        $update_stmt->bind_param("i", $user_id);
        if (!$update_stmt->execute()) {
            error_log("Failed to mark notifications as read for user {$user_id}: " . $update_stmt->error);
            // Non-critical error, don't necessarily show to user
        }
        $update_stmt->close();
    } else {
        error_log("Failed to prepare statement to mark notifications as read: " . $conn->error);
    }
}


// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Adjust CSP if necessary, copying from index.php is a good starting point
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Notifications | Musabaqa</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .notification-item {
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 0;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item.unread {
            /* Styles for unread items before they are marked read by the script */
            /* background-color: #f8f9fa; */ /* Subtle background */
            /* font-weight: bold; */ /* Make text bold */
        }
        .notification-subject {
            font-weight: 500;
            color: #343a40;
        }
        .notification-message {
            color: #495057;
            margin-bottom: 0.25rem;
        }
        .notification-time {
            font-size: 0.8em;
            color: #6c757d;
        }
        .notification-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: #6c757d;
        }
        .notification-item.unread .notification-icon {
             color: #0d6efd; /* Primary color for unread icon */
        }
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
                                <h4 class="page-title">Notifications</h4>
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Notifications</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications Content -->
                    <div class="row">
                        <div class="col-lg-10 col-xl-8">
                            <div class="card">
                                <div class="card-body">
                                    <!-- <h5 class="card-title mb-3">Your Notifications</h5> -->

                                    <?php if ($fetch_error): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <i class="ri-error-warning-line me-1"></i> Could not load notifications. Please try again later.
                                        </div>
                                    <?php elseif (empty($notifications)): ?>
                                        <div class="text-center py-4">
                                            <i class="ri-mail-open-line fs-1 text-muted"></i>
                                            <p class="mt-2 text-muted">You have no notifications.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="notification-list">
                                            <?php foreach ($notifications as $notification): ?>
                                                <?php
                                                    // Determine icon based on read status (before marking read)
                                                    $icon_class = $notification['is_read'] ? 'ri-mail-open-line' : 'ri-mail-fill';
                                                    $item_class = $notification['is_read'] ? '' : 'unread'; // Class applied before JS marks read
                                                ?>
                                                <div class="notification-item d-flex align-items-start <?php echo $item_class; ?>">
                                                    <i class="<?php echo $icon_class; ?> notification-icon"></i>
                                                    <div class="flex-grow-1">
                                                        <div class="notification-subject"><?php echo htmlspecialchars($notification['subject']); ?></div>
                                                        <div class="notification-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></div>
                                                        <div class="notification-time">
                                                            <?php
                                                                // Format the date/time nicely
                                                                $date = new DateTime($notification['created_at']);
                                                                echo $date->format('M j, Y \a\t g:i A');
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <!-- Optional: Add a link/button here for specific actions -->
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

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

    <!-- Include app.min.js for template functionality -->
    <script src="assets/js/app.min.js"></script>

    <!-- Add any page-specific JS here if needed -->
    <script>
        // Optional: If you want to visually update the 'unread' status immediately without a page reload
        // after the PHP script marks them read, you could add JS here.
        // However, since the PHP marks them read on load, they will appear as read on subsequent visits.
        // Example (if needed):
        // document.addEventListener('DOMContentLoaded', function() {
        //     const unreadItems = document.querySelectorAll('.notification-item.unread');
        //     unreadItems.forEach(item => {
        //         // Maybe change icon or remove bold style if you added one
        //         const icon = item.querySelector('.notification-icon');
        //         if (icon) {
        //             icon.classList.remove('ri-mail-fill');
        //             icon.classList.add('ri-mail-open-line');
        //         }
        //         item.classList.remove('unread'); // Remove the visual 'unread' marker
        //     });
        // });
    </script>

</body>
</html>