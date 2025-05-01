<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/schedule.php
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

// Get user details (optional for this page, but good practice)
$user_id = $_SESSION['user_id'] ?? null;
$user_fullname = $_SESSION['user_fullname'] ?? 'Participant';
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png'; // Default avatar

if (!$user_id) {
    error_log("User ID missing from session for logged-in user on schedule.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// --- Schedule Data (Extracted from provided text) ---
// In a real application, this might come from a database or config file
$schedule_events = [
    [
        'date_range' => 'August 20th - 21st, 2025',
        'event' => 'Arrival in Abuja',
        'notes' => 'Participants arrive in Abuja.'
    ],
    [
        'date_range' => 'August 23rd - 26th, 2025',
        'event' => 'Musabaqa (Competition Proper)',
        'notes' => 'Main competition events take place. (Specific timings TBC)'
        // Note: The original text mentioned "Opening" but the date range suggests the competition itself. Clarified here.
    ],
     [
        'date_range' => 'August 27th, 2025',
        'event' => 'Travel from Jos to Abuja',
        'notes' => 'Participants travel back to Abuja from Jos.'
        // Note: The original text also mentioned travel *to* Jos on Aug 2nd, which seems out of sequence with the other dates.
        // I've omitted it for clarity unless it's confirmed to be correct and relevant.
    ],
    [
        'date_range' => 'August 30th, 2025',
        'event' => 'Closing Ceremony & Prize Distribution',
        'notes' => 'Official closing ceremony and awarding of prizes.'
    ],
    [
        'date_range' => 'August 31st - September 1st, 2025',
        'event' => 'Departure of International Participants',
        'notes' => 'International participants depart.'
        // Note: Departure for Nigerian participants might be different and could be added if known.
    ],
];


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
    <title>Competition Schedule | Musabaqa</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .schedule-item {
            border-left: 3px solid #0d6efd; /* Primary color border */
            padding-left: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .schedule-item::before {
            content: '';
            position: absolute;
            left: -8px; /* Adjust to center the circle on the line */
            top: 5px; /* Adjust vertical position */
            width: 14px;
            height: 14px;
            background-color: #0d6efd;
            border-radius: 50%;
            border: 2px solid #fff; /* Optional: white border around circle */
        }
        .schedule-date {
            font-weight: 600;
            color: #495057;
        }
        .schedule-event {
            font-size: 1.1em;
            font-weight: 500;
            color: #212529;
        }
        .schedule-notes {
            font-size: 0.9em;
            color: #6c757d;
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
                                <h4 class="page-title">Competition Schedule</h4>
                                <div class="page-title-right">
                                     <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Schedule</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Content -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Schedule of Events (Participant View)</h5>

                                    <?php if (!empty($schedule_events)): ?>
                                        <?php foreach ($schedule_events as $item): ?>
                                            <div class="schedule-item">
                                                <p class="schedule-date mb-1"><?php echo htmlspecialchars($item['date_range']); ?></p>
                                                <h6 class="schedule-event mb-1"><?php echo htmlspecialchars($item['event']); ?></h6>
                                                <?php if (!empty($item['notes'])): ?>
                                                    <p class="schedule-notes mb-0"><?php echo htmlspecialchars($item['notes']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted">The competition schedule is not yet available. Please check back later.</p>
                                    <?php endif; ?>

                                    <p class="mt-4 text-muted small">
                                        Note: This schedule is preliminary and subject to change. Please check notifications for any updates.
                                    </p>

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

</body>
</html>