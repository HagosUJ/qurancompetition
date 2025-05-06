<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/schedule.php
require_once 'includes/auth.php'; // Includes config, db, functions, starts session securely

// --- Authentication & Session Management ---
if (!is_logged_in()) {
    redirect('sign-in.php');
    exit;
}

$language = $_SESSION['language'] ?? 'en'; // Default to English
$is_rtl = ($language === 'ar');

// Session timeout check
$timeout_duration = SESSION_TIMEOUT_DURATION ?? 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    logout_user('sign-in.php?reason=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// Get user details
$user_id = $_SESSION['user_id'] ?? null;
$user_fullname = $_SESSION['user_fullname'] ?? ($language === 'ar' ? 'مشارك' : 'Participant');
$profile_picture = $_SESSION['user_profile_pic'] ?? 'assets/media/avatars/blank.png'; // Default avatar

if (!$user_id) {
    error_log("User ID missing from session for logged-in user on schedule.php.");
    logout_user('sign-in.php?reason=error');
    exit;
}

// --- Language-Specific Strings ---
$translations = [
    'en' => [
        'page_title' => 'Competition Schedule | Musabaqa',
        'page_header' => 'Competition Schedule',
        'dashboard' => 'Dashboard',
        'schedule' => 'Schedule',
        'schedule_title' => 'Schedule of Events (Participant View)',
        'no_schedule' => 'The competition schedule is not yet available. Please check back later.',
        'schedule_note' => 'Note: This schedule is preliminary and subject to change. Please check notifications for any updates.',
    ],
    'ar' => [
        'page_title' => 'جدول المسابقة | المسابقة',
        'page_header' => 'جدول المسابقة',
        'dashboard' => 'لوحة التحكم',
        'schedule' => 'الجدول',
        'schedule_title' => 'جدول الأحداث (منظور المشارك)',
        'no_schedule' => 'جدول المسابقة غير متوفر حاليًا. يرجى التحقق لاحقًا.',
        'schedule_note' => 'ملاحظة: هذا الجدول مبدئي وقابل للتغيير. يرجى التحقق من الإشعارات للحصول على أي تحديثات.',
    ]
];

// --- Schedule Data ---
$schedule_events = [
    'en' => [
        [
            'date_range' => 'August 20th - 21st, 2025',
            'event' => 'Arrival in Abuja',
            'notes' => 'Participants arrive in Abuja.'
        ],
        [
            'date_range' => 'August 23rd - 26th, 2025',
            'event' => 'Musabaqa (Competition Proper)',
            'notes' => 'Main competition events take place. (Specific timings TBC)'
        ],
        [
            'date_range' => 'August 27th, 2025',
            'event' => 'Travel from Jos to Abuja',
            'notes' => 'Participants travel back to Abuja from Jos.'
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
        ],
    ],
    'ar' => [
        [
            'date_range' => '20 - 21 أغسطس 2025',
            'event' => 'الوصول إلى أبوجا',
            'notes' => 'وصول المشاركين إلى أبوجا.'
        ],
        [
            'date_range' => '23 - 26 أغسطس 2025',
            'event' => 'المسابقة (الفعالية الرئيسية)',
            'notes' => 'تقام فعاليات المسابقة الرئيسية. (التوقيتات الدقيقة سيتم تحديدها لاحقًا)'
        ],
        [
            'date_range' => '27 أغسطس 2025',
            'event' => 'السفر من جوس إلى أبوجا',
            'notes' => 'يسافر المشاركون من جوس إلى أبوجا.'
        ],
        [
            'date_range' => '30 أغسطس 2025',
            'event' => 'حفل الختام وتوزيع الجوائز',
            'notes' => 'حفل الختام الرسمي وتوزيع الجوائز.'
        ],
        [
            'date_range' => '31 أغسطس - 1 سبتمبر 2025',
            'event' => 'مغادرة المشاركين الدوليين',
            'notes' => 'مغادرة المشاركين الدوليين.'
        ],
    ]
];

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-XSS-Protection: 1; mode=block");

?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
<head>
    <meta charset="utf-8" />
    <title><?php echo $translations[$language]['page_title']; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            left: -8px;
            top: 5px;
            width: 14px;
            height: 14px;
            background-color: #0d6efd;
            border-radius: 50%;
            border: 2px solid #fff;
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
        <?php if ($is_rtl): ?>
        .schedule-item {
            border-left: none;
            border-right: 3px solid #0d6efd;
            padding-left: 0;
            padding-right: 1.5rem;
        }
        .schedule-item::before {
            left: auto;
            right: -8px;
        }
        <?php endif; ?>
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
                                <h4 class="page-title"><?php echo $translations[$language]['page_header']; ?></h4>
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php"><?php echo $translations[$language]['dashboard']; ?></a></li>
                                        <li class="breadcrumb-item active"><?php echo $translations[$language]['schedule']; ?></li>
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
                                    <h5 class="card-title mb-4"><?php echo $translations[$language]['schedule_title']; ?></h5>

                                    <?php if (!empty($schedule_events[$language])): ?>
                                        <?php foreach ($schedule_events[$language] as $item): ?>
                                            <div class="schedule-item">
                                                <p class="schedule-date mb-1"><?php echo htmlspecialchars($item['date_range']); ?></p>
                                                <h6 class="schedule-event mb-1"><?php echo htmlspecialchars($item['event']); ?></h6>
                                                <?php if (!empty($item['notes'])): ?>
                                                    <p class="schedule-notes mb-0"><?php echo htmlspecialchars($item['notes']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted"><?php echo $translations[$language]['no_schedule']; ?></p>
                                    <?php endif; ?>

                                    <p class="mt-4 text-muted small">
                                        <?php echo $translations[$language]['schedule_note']; ?>
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
    <script src="assets/js/app.min.js"></script>

</body>
</html>