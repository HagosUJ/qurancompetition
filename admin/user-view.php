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

// Get user ID from URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0) {
    // Redirect if no valid ID provided
    header("Location: users.php");
    exit;
}

// Fetch user information
try {
    // Basic user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // User not found
        $_SESSION['error'] = "User not found";
        header("Location: users.php");
        exit;
    }
    
    // Get profile information if exists
    $profile_query = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $profile_query->execute([$user_id]);
    $profile = $profile_query->fetch(PDO::FETCH_ASSOC);
    
    // Get payment history
    $payment_query = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 5");
    $payment_query->execute([$user_id]);
    $payments = $payment_query->fetchAll(PDO::FETCH_ASSOC);
    
    // Get login history
    $login_query = $pdo->prepare("SELECT * FROM user_logs WHERE user_id = ? AND action = 'Login' ORDER BY created_at DESC LIMIT 5");
    $login_query->execute([$user_id]);
    $logins = $login_query->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving user data";
    error_log("User view error: " . $e->getMessage());
    header("Location: users.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View User | JCDA Admin Portal</title>
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
                                        <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                                        <li class="breadcrumb-item active">View User</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">User Details</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="m-0">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                            <?php if($user['account_status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif($user['account_status'] === 'suspended'): ?>
                                                <span class="badge bg-danger">Suspended</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </h5>
                                        <div>
                                            <a href="user-edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary me-2">
                                                <i class="ri-pencil-line me-1"></i> Edit User
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $user_id; ?>)" class="btn btn-danger me-2">
                                                <i class="ri-delete-bin-line me-1"></i> Delete User
                                            </a>
                                            <a href="user-reset-password.php?id=<?php echo $user_id; ?>" class="btn btn-warning">
                                                <i class="ri-lock-line me-1"></i> Reset Password
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- User Information -->
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="header-title">Basic Information</h4>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <?php if(isset($profile['profile_photo']) && !empty($profile['profile_photo'])): ?>
                                            <img src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile Photo" class="rounded-circle avatar-lg">
                                        <?php else: ?>
                                            <div class="avatar-lg rounded-circle bg-soft-primary mx-auto">
                                                <span class="avatar-title font-22 text-primary"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <h4 class="mt-3"><?php echo htmlspecialchars($user['username']); ?></h4>
                                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>

                                    <div class="mb-3">
                                        <h5 class="mb-3">Account Details</h5>
                                        <div class="d-flex justify-content-between mb-2">
                                            <p class="mb-0 text-muted">User ID:</p>
                                            <p class="mb-0"><?php echo $user['id']; ?></p>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <p class="mb-0 text-muted">Role:</p>
                                            <p class="mb-0"><?php echo htmlspecialchars($user['role'] ?? 'Member'); ?></p>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <p class="mb-0 text-muted">Registration Date:</p>
                                            <p class="mb-0"><?php echo date('M d, Y H:i', strtotime($user['registration_date'])); ?></p>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <p class="mb-0 text-muted">Last Login:</p>
                                            <p class="mb-0">
                                                <?php 
                                                if (!empty($logins)) {
                                                    echo date('M d, Y H:i', strtotime($logins[0]['created_at']));
                                                } else {
                                                    echo 'Never';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <p class="mb-0 text-muted">Status:</p>
                                            <p class="mb-0">
                                                <?php if($user['account_status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif($user['account_status'] === 'suspended'): ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Toggle status button -->
                                    <div class="text-center">
                                        <?php if($user['account_status'] === 'active'): ?>
                                            <a href="user-status.php?id=<?php echo $user_id; ?>&status=suspend" class="btn btn-sm btn-outline-danger">
                                                <i class="ri-forbid-line me-1"></i> Suspend Account
                                            </a>
                                        <?php else: ?>
                                            <a href="user-status.php?id=<?php echo $user_id; ?>&status=activate" class="btn btn-sm btn-outline-success">
                                                <i class="ri-checkbox-circle-line me-1"></i> Activate Account
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Profile Information -->
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="header-title">Profile Information</h4>
                                    <?php if($profile): ?>
                                        <span class="badge <?php echo $profile['updated'] ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $profile['updated'] ? 'Complete' : 'Incomplete'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No Profile</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if($profile): ?>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">Full Name</label>
                                                    <p><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">Phone</label>
                                                    <p><?php echo htmlspecialchars($profile['phone'] ?? 'Not provided'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">Date of Birth</label>
                                                    <p><?php echo isset($profile['date_of_birth']) ? date('M d, Y', strtotime($profile['date_of_birth'])) : 'Not provided'; ?></p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">Gender</label>
                                                    <p><?php echo htmlspecialchars($profile['gender'] ?? 'Not provided'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Address</label>
                                            <p><?php echo htmlspecialchars($profile['address'] ?? 'Not provided'); ?></p>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">City</label>
                                                    <p><?php echo htmlspecialchars($profile['city'] ?? 'Not provided'); ?></p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">State</label>
                                                    <p><?php echo htmlspecialchars($profile['state'] ?? 'Not provided'); ?></p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">ZIP Code</label>
                                                    <p><?php echo htmlspecialchars($profile['zip_code'] ?? 'Not provided'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Bio</label>
                                            <p><?php echo nl2br(htmlspecialchars($profile['bio'] ?? 'Not provided')); ?></p>
                                        </div>
                                        
                                        <div class="text-end mt-3">
                                            <a href="profile-edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                                                <i class="ri-pencil-line me-1"></i> Edit Profile
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <div class="avatar-lg mx-auto">
                                                <div class="avatar-title bg-light text-primary display-4 rounded-circle">
                                                    <i class="ri-profile-line"></i>
                                                </div>
                                            </div>
                                            <h4 class="text-center mt-3">No Profile Information</h4>
                                            <p class="text-muted">This user hasn't completed their profile yet.</p>
                                            <a href="profile-create.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary mt-2">
                                                <i class="ri-add-line me-1"></i> Create Profile
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Tabs for Payment History and Login History -->
                            <div class="card">
                                <div class="card-header">
                                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-bs-toggle="tab" href="#payment-history" role="tab">
                                                Payment History
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#login-history" role="tab">
                                                Login History
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content">
                                        <!-- Payment History Tab -->
                                        <div class="tab-pane fade show active" id="payment-history" role="tabpanel">
                                            <?php if(!empty($payments)): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-centered table-hover mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Transaction ID</th>
                                                                <th>Amount</th>
                                                                <th>Status</th>
                                                                <th>Date</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach($payments as $payment): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                                                    <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                                                    <td>
                                                                        <?php if($payment['payment_status'] === 'completed'): ?>
                                                                            <span class="badge bg-success">Completed</span>
                                                                        <?php elseif($payment['payment_status'] === 'pending'): ?>
                                                                            <span class="badge bg-warning">Pending</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-danger">Failed</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                                                                    <td>
                                                                        <a href="payment-view.php?id=<?php echo $payment['id']; ?>" class="action-icon" title="View Details">
                                                                            <i class="ri-eye-line"></i>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="text-end mt-3">
                                                    <a href="payments.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-light">View All Payments</a>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-3">
                                                    <div class="avatar-md mx-auto">
                                                        <div class="avatar-title bg-light text-primary display-4 rounded-circle">
                                                            <i class="ri-bank-card-line"></i>
                                                        </div>
                                                    </div>
                                                    <h5 class="text-center mt-3">No Payment Records</h5>
                                                    <p class="text-muted">This user has not made any payments yet.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Login History Tab -->
                                        <div class="tab-pane fade" id="login-history" role="tabpanel">
                                            <?php if(!empty($logins)): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-centered table-hover mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Date & Time</th>
                                                                <th>IP Address</th>
                                                                <th>Device</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach($logins as $login): ?>
                                                                <tr>
                                                                    <td><?php echo date('M d, Y H:i:s', strtotime($login['created_at'])); ?></td>
                                                                    <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                                                                    <td><?php echo htmlspecialchars($login['user_agent'] ?? 'Unknown'); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="text-end mt-3">
                                                    <a href="user-logs.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-light">View All Logins</a>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-3">
                                                    <div class="avatar-md mx-auto">
                                                        <div class="avatar-title bg-light text-primary display-4 rounded-circle">
                                                            <i class="ri-login-circle-line"></i>
                                                        </div>
                                                    </div>
                                                    <h5 class="text-center mt-3">No Login History</h5>
                                                    <p class="text-muted">This user has not logged in yet.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
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

    <script>
        // Delete confirmation function
        function confirmDelete(userId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'user-delete.php?id=' + userId;
                }
            });
        }
    </script>
</body>
</html>