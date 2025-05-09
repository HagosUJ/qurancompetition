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
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Get user ID from URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0) {
    header("Location: users.php");
    exit;
}

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT id, fullname, email, role, status, created_at, updated_at, last_login, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: users.php");
        exit;
    }

    // Fetch application details (Nigerian or International)
    $stmt = $pdo->prepare("
        SELECT 
            a.id AS application_id, 
            a.contestant_type, 
            COALESCE(nd.full_name_nid, id.full_name_passport) AS full_name,
            COALESCE(nd.dob, id.dob) AS dob,
            COALESCE(nd.phone_number, id.phone_number) AS phone_number,
            COALESCE(nd.address, id.address) AS address,
            COALESCE(NULL, id.city) AS city,
            COALESCE(nd.state, id.country_residence) AS state,
            COALESCE(nd.languages_spoken, id.languages_spoken) AS languages_spoken
        FROM 
            applications a
        LEFT JOIN 
            application_details_nigerian nd ON a.id = nd.application_id
        LEFT JOIN 
            application_details_international id ON a.id = id.application_id
        WHERE 
            a.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch login attempts
    $stmt = $pdo->prepare("SELECT ip_address, email_identifier, attempt_time FROM login_attempts WHERE email_identifier = ? ORDER BY attempt_time DESC LIMIT 10");
    $stmt->execute([$user['email']]);
    $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
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
                                            <?php echo htmlspecialchars($user['fullname']); ?>
                                            <?php if($user['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif($user['status'] === 'suspended'): ?>
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
                                        <?php if(isset($user['profile_picture']) && !empty($user['profile_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Photo" class="rounded-circle avatar-lg">
                                        <?php elseif($profile && isset($profile['application_id'])): ?>
                                            <div class="avatar-lg rounded-circle bg-soft-primary mx-auto">
                                                <span class="avatar-title font-22 text-primary"><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="avatar-lg rounded-circle bg-soft-primary mx-auto">
                                                <span class="avatar-title font-22 text-primary"><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <h4 class="mt-3"><?php echo htmlspecialchars($user['fullname']); ?></h4>
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
                                    <p class="mb-0"><?php echo htmlspecialchars($user['role'] ?? 'user'); ?></p>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <p class="mb-0 text-muted">Registration Date:</p>
                                    <p class="mb-0"><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></p>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <p class="mb-0 text-muted">Last Login:</p>
                                    <p class="mb-0"><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></p>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <p class="mb-0 text-muted">Status:</p>
                                    <p class="mb-0">
                                        <?php if($user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif($user['status'] === 'suspended'): ?>
                                            <span class="badge bg-danger">Suspended</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Toggle status button -->
                            <div class="text-center">
                                <?php if($user['status'] === 'active'): ?>
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
                            <?php if($profile && isset($profile['application_id'])): ?>
                                <span class="badge bg-success">Complete</span>
                            <?php else: ?>
                                <span class="badge bg-danger">No Profile</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if($profile && isset($profile['application_id'])): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Full Name</label>
                                            <p><?php echo htmlspecialchars($profile['full_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Phone</label>
                                            <p><?php echo htmlspecialchars($profile['phone_number'] ?? 'Not provided'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Date of Birth</label>
                                            <p><?php echo isset($profile['dob']) ? date('M

 d, Y', strtotime($profile['dob'])) : 'Not provided'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Contestant Type</label>
                                            <p><?php echo htmlspecialchars(ucfirst($profile['contestant_type'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Address</label>
                                    <p><?php echo htmlspecialchars($profile['address'] ?? 'Not provided'); ?></p>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">City</label>
                                            <p><?php echo htmlspecialchars($profile['city'] ?? 'Not provided'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">State/Country</label>
                                            <p><?php echo htmlspecialchars($profile['state'] ?? 'Not provided'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Languages Spoken</label>
                                    <p><?php echo htmlspecialchars($profile['languages_spoken'] ?? 'Not provided'); ?></p>
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
                                    <p class="text-muted">This user hasn't submitted an application yet.</p>
                                    <a href="profile-create.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary mt-2">
                                        <i class="ri-add-line me-1"></i> Create Profile
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Login History -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="header-title">Login History</h4>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($logins) || $user['last_login']): ?>
                                <div class="table-responsive">
                                    <table class="table table-centered table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>IP Address</th>
                                                <th>Type</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($user['last_login']): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y H:i:s', strtotime($user['last_login'])); ?></td>
                                                    <td>-</td>
                                                    <td>Successful Login</td>
                                                    <td>Last successful login</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach($logins as $login): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y H:i:s', strtotime($login['attempt_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                                                    <td>Failed Attempt</td>
                                                    <td>Email: <?php echo htmlspecialchars($login['email_identifier']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-3">
                                    <a href="user-logs.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-light">View All Login Attempts</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <div class="avatar-md mx-auto">
                                        <div class="avatar-title bg-light text-primary display-4 rounded-circle">
                                            <i class="ri-login-circle-line"></i>
                                        </div>
                                    </div>
                                    <h5 class="text-center mt-3">No Login History</h5>
                                    <p class="text-muted">This user has no recorded login attempts.</p>
                                </div>
                            <?php endif; ?>
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