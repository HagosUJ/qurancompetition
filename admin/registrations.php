<?php
// Include database connection
try {
    if (!file_exists('includes/db.php')) {
        throw new Exception("Database file not found at 'includes/db.php'");
    }
    require_once('includes/db.php');
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection not established. Check db.php file.");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    $search_condition = "AND (fullname LIKE ? OR email LIKE ?)";
    $search_params = ["%$search%", "%$search%"];
}

// Count total users
$count_query = "SELECT COUNT(*) FROM users WHERE role = 'user' $search_condition";
$stmt = $pdo->prepare($count_query);
if (!empty($search_params)) {
    $stmt->execute($search_params);
} else {
    $stmt->execute();
}
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Fetch users for current page
$query = "SELECT id, fullname, email, created_at, status FROM users 
          WHERE role = 'user' $search_condition 
          ORDER BY created_at DESC 
          LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
if (!empty($search_params)) {
    $stmt->execute($search_params);
} else {
    $stmt->execute();
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Registration Management | Admin Portal</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
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

                    <!-- Page title -->
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box">
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Registration Management</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">Registration Management</h4>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Display success/error messages -->
                    <?php if(isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="ri-check-double-line me-1"></i>
                            <?php echo $_SESSION['success']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="ri-error-warning-line me-1"></i>
                            <?php echo $_SESSION['error']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <!-- Action buttons -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row justify-content-between">
                                        <div class="col-md-6">
                                            <form method="get" class="d-flex">
                                                <input type="text" name="search" class="form-control me-2" 
                                                       placeholder="Search by username or email" 
                                                       value="<?php echo htmlspecialchars($search); ?>">
                                                <button type="submit" style="width: 30%;" class="btn btn-primary">
                                                    <i class="ri-search-line"></i> Search
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                            <a href="reports.php?export=users" class="btn btn-info">
                                                <i class="ri-file-download-line"></i> Export
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="header-title">Registration List</h4>
                                    <p class="text-muted mb-0">
                                        Showing <?php echo min($total_users, $per_page); ?> of <?php echo $total_users; ?> users
                                    </p>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-centered table-striped table-hover dt-responsive nowrap w-100">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Full Name</th>
                                                    <th>Email</th>
                                                    <th>Date Registered</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
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
                                                    <td>
                                                        <a href="user-view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                            View Info <i class="ri-eye-line"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <?php if($total_pages > 1): ?>
                                <div class="card-footer">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mb-0">
                                            <!-- Previous page button -->
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" <?php echo ($page <= 1) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                                    <i class="ri-arrow-left-s-line me-1"></i> Previous
                                                </a>
                                            </li>
                                            
                                            <?php 
                                            // Show limited page numbers with ellipsis
                                            $start_page = max(1, min($page - 2, $total_pages - 4));
                                            $end_page = min($total_pages, max($page + 2, 5));
                                            
                                            // Always show first page
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                                                if ($start_page > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            // Show page numbers
                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                echo '<li class="page-item ' . ($i === $page ? 'active' : '') . '">';
                                                echo '<a class="page-link" href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            
                                            // Always show last page
                                            if ($end_page < $total_pages) {
                                                if ($end_page < $total_pages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>
                                            
                                            <!-- Next page button -->
                                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" <?php echo ($page >= $total_pages) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                                    Next <i class="ri-arrow-right-s-line ms-1"></i>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                                <?php endif; ?>
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

    <script>
        // Enhanced delete confirmation function
        function confirmDelete(userId, username) {
            Swal.fire({
                title: 'Delete User',
                html: 'Are you sure you want to delete user <strong>' + username + '</strong>?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                showCloseButton: true,
                footer: '<a href="user-view.php?id=' + userId + '">View user details first</a>'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show second confirmation with delete options
                    Swal.fire({
                        title: 'Select Deletion Type',
                        html: '<div class="text-start">' +
                              '<div class="form-check mb-2">' +
                              '<input class="form-check-input" type="radio" name="deleteType" id="softDelete" value="soft" checked>' +
                              '<label class="form-check-label" for="softDelete">' +
                              '<strong>Soft Delete</strong> - Mark as deleted but keep records' +
                              '</label>' +
                              '</div>' +
                              '<div class="form-check">' +
                              '<input class="form-check-input" type="radio" name="deleteType" id="hardDelete" value="hard">' +
                              '<label class="form-check-label" for="hardDelete">' +
                              '<strong>Hard Delete</strong> - Permanently remove all user data' +
                              '</label>' +
                              '</div>' +
                              '</div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Proceed with deletion',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Get delete type
                            const deleteType = document.querySelector('input[name="deleteType"]:checked').value;
                            
                            // Proceed to delete page with selected deletion type
                            window.location.href = 'user-delete.php?id=' + userId + '&type=' + deleteType;
                        }
                    });
                }
            });
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltips = document.querySelectorAll('[title]');
            tooltips.forEach(function(tooltip) {
                new bootstrap.Tooltip(tooltip);
            });
        });

        // Auto-dismiss alerts after 5 seconds
        window.setTimeout(function() {
            document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>