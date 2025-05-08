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

// Initialize variables
$errors = [];
$success_message = '';
$account_statuses = ['active', 'pending', 'suspended'];
$roles = ['admin', 'member', 'moderator'];

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
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving user data";
    error_log("User edit error: " . $e->getMessage());
    header("Location: users.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'member';
    $account_status = isset($_POST['account_status']) ? trim($_POST['account_status']) : 'active';
    
    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Username must be between 3 and 50 characters";
    }
    
    // Check if username already exists for another user
    $check_username = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_username->execute([$username, $user_id]);
    if ($check_username->rowCount() > 0) {
        $errors[] = "Username already exists. Please choose a different one.";
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists for another user
    $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_email->execute([$email, $user_id]);
    if ($check_email->rowCount() > 0) {
        $errors[] = "Email already exists. Please choose a different one.";
    }
    
    // Validate role
    if (!in_array($role, $roles)) {
        $errors[] = "Invalid role selected";
    }
    
    // Validate account status
    if (!in_array($account_status, $account_statuses)) {
        $errors[] = "Invalid account status selected";
    }
    
    // If password is being updated
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if (!empty($password)) {
        // Validate password
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        // Check if passwords match
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // If there are no errors, update the user
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update basic user information
            if (!empty($password)) {
                // Update with new password
                $update_stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, account_status = ?, password_hash = ? WHERE id = ?");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt->execute([$username, $email, $role, $account_status, $hashed_password, $user_id]);
            } else {
                // Update without changing password
                $update_stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, account_status = ? WHERE id = ?");
                $update_stmt->execute([$username, $email, $role, $account_status, $user_id]);
            }
            
            // Log admin action
            $admin_id = $_SESSION['admin_id'];
            $action = "Updated user: " . $username;
            $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, user_id) VALUES (?, ?, ?)");
            $log_stmt->execute([$admin_id, $action, $user_id]);
            
            $pdo->commit();
            $success_message = "User updated successfully";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
            error_log("User update error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit User | JCDA Admin Portal</title>
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
                                        <li class="breadcrumb-item active">Edit User</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">Edit User</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="header-title">Edit User Information</h4>
                                        <a href="user-view.php?id=<?php echo $user_id; ?>" class="btn btn-sm btn-primary">
                                            <i class="ri-eye-line me-1"></i> View User
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($success_message)): ?>
                                        <div class="alert alert-success" role="alert">
                                            <?php echo $success_message; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <ul class="mb-0">
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?php echo $error; ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <form method="post" action="user-edit.php?id=<?php echo $user_id; ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                                    <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                                    <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="role" class="form-label">Role</label>
                                                    <select name="role" id="role" class="form-select">
                                                        <?php foreach ($roles as $role_option): ?>
                                                            <option value="<?php echo $role_option; ?>" <?php echo ($user['role'] === $role_option) ? 'selected' : ''; ?>>
                                                                <?php echo ucfirst($role_option); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="account_status" class="form-label">Account Status</label>
                                                    <select name="account_status" id="account_status" class="form-select">
                                                        <?php foreach ($account_statuses as $status): ?>
                                                            <option value="<?php echo $status; ?>" <?php echo ($user['account_status'] === $status) ? 'selected' : ''; ?>>
                                                                <?php echo ucfirst($status); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                                                    <input type="password" name="password" id="password" class="form-control">
                                                    <small class="text-muted">Minimum 8 characters</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Account Info</label>
                                                    <div class="d-flex justify-content-between py-2 px-3 bg-light rounded">
                                                        <div>
                                                            <span class="text-muted">Registration Date:</span>
                                                            <span class="ms-2"><?php echo date('M d, Y H:i', strtotime($user['registration_date'])); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-muted">User ID:</span>
                                                            <span class="ms-2"><?php echo $user['id']; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <a href="users.php" class="btn btn-secondary me-1">Cancel</a>
                                            <button type="submit" class="btn btn-primary">Update User</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Edit Profile Section -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4 class="header-title">Profile Management</h4>
                                    <p class="text-muted mb-0">Manage this user's profile data</p>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-center">
                                        <?php 
                                        // Get profile status
                                        $profile_query = $pdo->prepare("SELECT id, updated FROM profiles WHERE user_id = ?");
                                        $profile_query->execute([$user_id]);
                                        $profile = $profile_query->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($profile): ?>
                                            <div class="text-center">
                                                <div class="mb-3">
                                                    <span class="badge <?php echo $profile['updated'] ? 'bg-success' : 'bg-warning'; ?> fs-6 px-3 py-2">
                                                        Profile <?php echo $profile['updated'] ? 'Complete' : 'Incomplete'; ?>
                                                    </span>
                                                </div>
                                                <p>You can view and edit this user's profile information.</p>
                                                <div class="mt-3">
                                                    <a href="profile-edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary me-2">
                                                        <i class="ri-pencil-line me-1"></i> Edit Profile
                                                    </a>
                                                    <a href="user-view.php?id=<?php echo $user_id; ?>" class="btn btn-info">
                                                        <i class="ri-eye-line me-1"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-3">
                                                <div class="avatar-md mx-auto mb-3">
                                                    <div class="avatar-title bg-light text-primary display-4 rounded-circle">
                                                        <i class="ri-profile-line"></i>
                                                    </div>
                                                </div>
                                                <h5>No Profile Found</h5>
                                                <p class="text-muted">This user doesn't have a profile yet.</p>
                                                <a href="profile-create.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary mt-2">
                                                    <i class="ri-add-line me-1"></i> Create Profile
                                                </a>
                                            </div>
                                        <?php endif; ?>
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
        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (password !== confirm) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>