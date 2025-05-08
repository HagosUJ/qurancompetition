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

if (isset($_GET['state']) && $_GET['state'] === 'logout') {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Get basic statistics
try {
    // User statistics
    $user_count = $pdo->query("SELECT COUNT(*) as count FROM users")->fetchColumn();
    $new_users = $pdo->query("SELECT COUNT(*) as count FROM users WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $completed_profiles = $pdo->query("SELECT COUNT(*) as count FROM profiles WHERE updated = 1")->fetchColumn();
    
    // Payment statistics
    $total_payments = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE payment_status = 'completed'")->fetchColumn() ?? 0;
    
    // Registration trend data
    $months_query = $pdo->query("SELECT 
        DATE_FORMAT(registration_date, '%b %Y') as month,
        COUNT(*) as count 
        FROM users 
        WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
        ORDER BY registration_date");
    
    $months = [];
    $user_counts = [];
    while ($row = $months_query->fetch(PDO::FETCH_ASSOC)) {
        $months[] = $row['month'];
        $user_counts[] = $row['count'];
    }
    
    // Recent users
    $recent_users = $pdo->query("SELECT * FROM users ORDER BY registration_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    // Set defaults in case of database error
    $user_count = 0;
    $new_users = 0; 
    $completed_profiles = 0;
    $total_payments = 0;
    $months = [];
    $user_counts = [];
    $recent_users = [];
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
                                        <a href="javascript: void(0);" class="btn btn-success ms-2 flex-shrink-0">
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
                                                <a href="profiles.php" class="text-info">Manage Profiles <i class="ri-arrow-right-line"></i></a>
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

                    <div class="row">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="header-title">User Registration Trend</h4>
                                    <div class="dropdown">
                                        <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="ri-more-2-fill"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="reports.php?export=users" class="dropdown-item">Export Data</a>
                                            <a href="users.php" class="dropdown-item">View All Users</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body pt-0">
                                    <div id="registration-trend-chart" class="apex-charts" data-colors="#16a7e9"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="header-title">Profile Completion Status</h4>
                                    <div class="dropdown">
                                        <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="ri-more-2-fill"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="reports.php?export=profiles" class="dropdown-item">Export Data</a>
                                            <a href="profiles.php" class="dropdown-item">View All Profiles</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body pt-0">
                                    <div id="profile-completion-chart" class="apex-charts" data-colors="#16a7e9,#fa5c7c"></div>
                                </div>
                            </div>
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
                                                    <th>ID</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Registration Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($recent_users as $user): ?>
                                                <tr>
                                                    <td><?php echo $user['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td><?php echo $user['registration_date']; ?></td>
                                                    <td class="table-action">
                                                        <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="action-icon"> <i class="ri-pencil-fill"></i></a>
                                                        <a href="user_view.php?id=<?php echo $user['id']; ?>" class="action-icon"> <i class="ri-eye-fill"></i></a>
                                                        <a href="javascript:void(0);" class="action-icon" onclick="confirmDelete(<?php echo $user['id']; ?>)"> <i class="ri-delete-bin-fill"></i></a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
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
                    data: [25, 33, 28, 35, 30, 40]
                }],
                stroke: {width: 2, curve: 'smooth'},
                markers: {size: 0},
                colors: ['#16a7e9'],
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
            
            new ApexCharts(document.querySelector('#total-users-chart'), 
                {...cardChartOptions, colors: ['#16a7e9']}).render();
            new ApexCharts(document.querySelector('#new-users-chart'), 
                {...cardChartOptions, colors: ['#47ad77']}).render();
            new ApexCharts(document.querySelector('#completed-profiles-chart'), 
                {...cardChartOptions, colors: ['#f4bc30']}).render();
            new ApexCharts(document.querySelector('#payments-chart'), 
                {...cardChartOptions, colors: ['#fa5c7c']}).render();
                
            // Registration Trend Chart
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
                    data: <?php echo json_encode($user_counts); ?>
                }],
                xaxis: {
                    categories: <?php echo json_encode($months); ?>,
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
            
            // Profile Completion Chart
            new ApexCharts(document.querySelector('#profile-completion-chart'), {
                chart: {
                    type: 'pie',
                    height: 320
                },
                series: [<?php echo $completed_profiles; ?>, <?php echo max(0, $user_count - $completed_profiles); ?>],
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
        });
        
        // Delete confirmation function
        function confirmDelete(userId) {
            if (confirm("Are you sure you want to delete this user?")) {
                window.location.href = "user_delete.php?id=" + userId;
            }
        }
    </script>
</body>
</html>