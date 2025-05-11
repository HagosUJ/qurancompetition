<?php
// Include database connection
try {
    // Check if file exists before requiring it
    if (!file_exists('includes/db.php')) {
        throw new Exception("Database file not found at 'includes/db.php'");
    }
    require_once('includes/db.php');
    
    // Verify $pdo is set after including db.php
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection not established. Check db.php file.");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Check if admin is logged in
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch recent users (top 5)
$recent_users_query = "SELECT id, fullname, email, created_at, status 
                       FROM users 
                       WHERE role = 'user' 
                       ORDER BY created_at DESC 
                       LIMIT 5";
$stmt = $pdo->prepare($recent_users_query);
$stmt->execute();
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total user count
$user_count_query = "SELECT COUNT(*) AS total_users FROM users WHERE role = 'user'";
$stmt = $pdo->prepare($user_count_query);
$stmt->execute();
$user_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

// --- Fetch Application Metrics ---

// Fetch count for Submitted Applications (awaiting review)
$submitted_applications_query = "SELECT COUNT(*) AS count FROM applications WHERE status = 'Submitted' OR status = 'Under Review'";
$stmt_submitted = $pdo->prepare($submitted_applications_query);
$stmt_submitted->execute();
$submitted_applications_count = $stmt_submitted->fetch(PDO::FETCH_ASSOC)['count'];

// Fetch count for Applications Pending Completion by users
$pending_completion_statuses = ['Not Started', 'Personal Info Complete', 'Sponsor Info Complete', 'Documents Uploaded', 'Information Requested'];
$placeholders_pending = implode(',', array_fill(0, count($pending_completion_statuses), '?'));
$pending_completion_applications_query = "SELECT COUNT(*) AS count FROM applications WHERE status IN ($placeholders_pending)";
$stmt_pending_completion = $pdo->prepare($pending_completion_applications_query);
$stmt_pending_completion->execute($pending_completion_statuses);
$pending_completion_applications_count = $stmt_pending_completion->fetch(PDO::FETCH_ASSOC)['count'];

// Placeholder for $new_users and $completed_profiles if their calculation is elsewhere
$new_users = $new_users ?? 0; // Assuming it might be calculated elsewhere
$completed_profiles = $completed_profiles ?? 0; // Assuming it might be calculated elsewhere
// Placeholder for chart data if dynamic data is intended for them
$user_counts = $user_counts ?? array_fill(0, 12, 0); // Example: 12 months, all zero
$months = $months ?? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];


if (isset($_GET['state']) && $_GET['state'] === 'logout') {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header("Location: login.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Admin Dashboard | JCDA Admin Portal</title>
    <?php include 'layouts/title-meta.php'; ?>

    <!-- Plugin css -->
    <link rel="stylesheet" href="assets/vendor/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="assets/vendor/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css">

    <?php include 'layouts/head-css.php'; ?>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

    <?php include 'layouts/menu.php';?>

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->

        <div class="content-page">
            <div class="content">

                <!-- Start Content-->
                <div class="container-fluid">

                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box">
                                <div class="page-title-right">
                                    <form class="d-flex">
                                        <a href="index.php" class="btn btn-success ms-2 flex-shrink-0">
                                            <i class="ri-refresh-line"></i> Refresh
                                        </a>
                                    </form>
                                </div>
                                <h4 class="page-title">Dashboard</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-4 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-6">
                                            <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="Total Users">Total Users</h5>
                                            <h2 class="my-2 py-1"><?php echo number_format($user_count); ?></h2>
                                            <p class="mb-0 text-muted text-truncate">
                                                <a href="users.php" class="text-info">View All Users <i class="ri-arrow-right-line"></i></a>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-end">
                                                <div id="total-users-chart" data-colors="#16a7e9"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-6">
                                            <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="New Users">New Users</h5>
                                            <h2 class="my-2 py-1"><?php echo number_format($new_users); ?></h2>
                                            <p class="mb-0 text-muted text-truncate">
                                                <span class="text-success me-2">Last 30 days</span>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-end">
                                                <div id="new-users-chart" data-colors="#47ad77"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-6">
                                            <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="Completed Profiles">Completed Profiles</h5>
                                            <h2 class="my-2 py-1"><?php echo number_format($completed_profiles); ?></h2>
                                            <p class="mb-0 text-muted text-truncate">
                                                <a href="users.php" class="text-info">Manage Profiles <i class="ri-arrow-right-line"></i></a>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-end">
                                                <div id="completed-profiles-chart" data-colors="#f4bc30"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Application Metrics Row -->
                    <div class="row">
                        <div class="col-xl-4 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-6">
                                            <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="Submitted Applications">Submitted Applications</h5>
                                            <h2 class="my-2 py-1"><?php echo number_format($submitted_applications_count); ?></h2>
                                            <p class="mb-0 text-muted text-truncate">
                                                <a href="manage_applications.php?status=Submitted" class="text-info">Review Applications <i class="ri-arrow-right-line"></i></a>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-end">
                                                <div id="submitted-applications-chart" data-colors="#007bff"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-6">
                                            <h5 class="text-uppercase fs-13 mt-0 text-truncate" title="Applications Pending Completion">Pending Completion</h5>
                                            <h2 class="my-2 py-1"><?php echo number_format($pending_completion_applications_count); ?></h2>
                                            <p class="mb-0 text-muted text-truncate">
                                                <a href="manage_applications.php?status=Not Started" class="text-warning">View In-Progress <i class="ri-arrow-right-line"></i></a>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-end">
                                                <div id="pending-completion-chart" data-colors="#ffc107"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                         <div class="col-xl-4 col-lg-6">
                            <!-- Placeholder for a potential third card in this row or leave empty -->
                        </div>
                    </div>


                    <!-- Recent Users Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="header-title">Recent Users</h4>
                                    <a href="users.php" class="btn btn-sm btn-info">View All</a>
                                </div>
                                <div class="card-body pt-0">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-sm table-centered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>S/N</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Registration Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($recent_users as $index => $user): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                    <td>
                                                        <?php if(strtolower($user['status']) == 'active'): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php elseif(strtolower($user['status']) == 'pending'): ?>
                                                            <span class="badge bg-warning">Pending</span>
                                                        <?php elseif(strtolower($user['status']) == 'suspended'): ?>
                                                            <span class="badge bg-danger">Suspended</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($user['status'])); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($recent_users)): ?>
                                                    <tr><td colspan="5" class="text-center">No recent users found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- container -->

            </div>
            <!-- content -->

            <?php include 'layouts/footer.php'; ?>

        </div>

        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

    </div>
    <!-- END wrapper -->

    <?php include 'layouts/right-sidebar.php'; ?>

    <?php include 'layouts/footer-scripts.php'; ?>

    <!-- Daterangepicker js -->
    <script src="assets/vendor/daterangepicker/moment.min.js"></script>
    <script src="assets/vendor/daterangepicker/daterangepicker.js"></script>

    <!-- ApexCharts js -->
    <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>
    
    <!-- Dashboard Charts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Card Charts
            const cardChartOptions = {
                chart: {
                    type: 'line',
                    height: 60,
                    sparkline: {enabled: true}
                },
                series: [{
                    data: [25, 33, 28, 35, 30, 40] // Static data for sparklines
                }],
                stroke: {width: 2, curve: 'smooth'},
                markers: {size: 0},
                tooltip: {
                    fixed: {enabled: false},
                    x: {show: false},
                    y: {
                        title: {
                            formatter: function (seriesName) {
                                return '';
                            }
                        }
                    },
                    marker: {show: false}
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'light',
                        type: "horizontal",
                        shadeIntensity: 0.25,
                        gradientToColors: undefined,
                        inverseColors: true,
                        opacityFrom: 0.85,
                        opacityTo: 0.85,
                        stops: [50, 0, 100]
                    }
                }
            };
            
            if (document.querySelector('#total-users-chart')) {
                new ApexCharts(document.querySelector('#total-users-chart'), 
                    {...cardChartOptions, colors: ['#16a7e9']}).render();
            }
            if (document.querySelector('#new-users-chart')) {
                new ApexCharts(document.querySelector('#new-users-chart'), 
                    {...cardChartOptions, colors: ['#47ad77']}).render();
            }
            if (document.querySelector('#completed-profiles-chart')) {
                new ApexCharts(document.querySelector('#completed-profiles-chart'), 
                    {...cardChartOptions, colors: ['#f4bc30']}).render();
            }
            // New charts for application metrics
            if (document.querySelector('#submitted-applications-chart')) {
                new ApexCharts(document.querySelector('#submitted-applications-chart'), 
                    {...cardChartOptions, colors: ['#007bff']}).render();
            }
            if (document.querySelector('#pending-completion-chart')) {
                new ApexCharts(document.querySelector('#pending-completion-chart'), 
                    {...cardChartOptions, colors: ['#ffc107']}).render();
            }
                
            // Registration Trend Chart (if data is available)
            const userCountsData = <?php echo json_encode($user_counts); ?>;
            const monthsData = <?php echo json_encode($months); ?>;
            if (document.querySelector('#registration-trend-chart') && userCountsData && monthsData) {
                new ApexCharts(document.querySelector('#registration-trend-chart'), {
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {show: false}
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '45%',
                            borderRadius: 4
                        }
                    },
                    dataLabels: {enabled: false},
                    stroke: {show: true, width: 2, colors: ['transparent']},
                    series: [{
                        name: 'New Users',
                        data: userCountsData
                    }],
                    xaxis: {
                        categories: monthsData,
                    },
                    yaxis: {
                        title: {text: 'User Count'}
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function (val) {
                                return val + " users"
                            }
                        }
                    },
                    colors: ['#3e60d5', '#47ad77', '#fa5c7c']
                }).render();
            }
            
            // Profile Completion Chart (if data is available)
            const completedProfilesCount = <?php echo $completed_profiles; ?>;
            const totalUserCountForProfileChart = <?php echo $user_count; ?>;
            if (document.querySelector('#profile-completion-chart')) {
                new ApexCharts(document.querySelector('#profile-completion-chart'), {
                    chart: {
                        type: 'pie',
                        height: 320
                    },
                    series: [completedProfilesCount, Math.max(0, totalUserCountForProfileChart - completedProfilesCount)],
                    labels: ['Completed', 'Incomplete'],
                    colors: ['#47ad77', '#fa5c7c'],
                    legend: {
                        show: true,
                        position: 'bottom',
                        horizontalAlign: 'center',
                        floating: false,
                        fontSize: '14px',
                        offsetX: 0,
                        offsetY: 7
                    },
                    responsive: [{
                        breakpoint: 600,
                        options: {
                            chart: {
                                height: 240
                            },
                            legend: {
                                show: false
                            }
                        }
                    }]
                }).render();
            }
        });
        
    </script>
</body>
</html>