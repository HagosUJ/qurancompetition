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

// Initialize variables
$errors = [];
$success = false;

// Check for CSRF token if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid security token. Please try again.";
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Get user ID from URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no valid ID, redirect to users page
if ($user_id === 0) {
    $_SESSION['error'] = "Invalid user ID";
    header("Location: users.php");
    exit;
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User not found";
        header("Location: users.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving user data";
    header("Location: users.php");
    exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Determine delete type (soft or hard delete)
    $delete_type = isset($_POST['delete_type']) ? $_POST['delete_type'] : 'soft';
    
    try {
        $pdo->beginTransaction();
        
        // Get admin info for logging
        $admin_id = $_SESSION['admin_id'];
        
        if ($delete_type === 'hard') {
            // Hard delete - Delete related records first
            
            // Delete profile
            $stmt = $pdo->prepare("DELETE FROM profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete payments
            $stmt = $pdo->prepare("DELETE FROM payments WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user logs
            $stmt = $pdo->prepare("DELETE FROM user_logs WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete email logs
            $stmt = $pdo->prepare("DELETE FROM email_logs WHERE recipient_id = ?");
            $stmt->execute([$user_id]);
            
            // Finally delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Log admin action
            $action = "Hard deleted user: " . $user['username'] . " (ID: $user_id)";
        } else {
            // Soft delete - Just mark as deleted
            $stmt = $pdo->prepare("UPDATE users SET 
                account_status = 'deleted', 
                deleted_at = NOW(), 
                deleted_by = ? 
                WHERE id = ?");
            $stmt->execute([$admin_id, $user_id]);
            
            // Log admin action
            $action = "Soft deleted user: " . $user['username'] . " (ID: $user_id)";
        }
        
        // Log the action
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, user_id) VALUES (?, ?, ?)");
        $log_stmt->execute([$admin_id, $action, $user_id]);
        
        $pdo->commit();
        
        // Set success message and redirect
        $_SESSION['success'] = "User " . ($delete_type === 'hard' ? "permanently" : "soft") . " deleted successfully.";
        header("Location: users.php");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = "Database error: " . $e->getMessage();
        error_log("User deletion error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Delete User | JCDA Admin Portal</title>
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
                                        <li class="breadcrumb-item active">Delete User</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">Delete User</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-danger">
                                    <h4 class="header-title text-white mb-0">Confirm User Deletion</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <ul class="mb-0">
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?php echo $error; ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-center mb-4">
                                        <div class="avatar-lg mx-auto">
                                            <div class="avatar-title bg-light text-danger display-4 rounded-circle">
                                                <i class="ri-delete-bin-7-line"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                                        <h4 class="mt-0">You are about to delete this user</h4>
                                        <p class="text-muted">
                                            Please confirm that you want to delete the following user:
                                        </p>
                                        
                                        <div class="mb-4">
                                            <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                                            <p class="mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                                            <p class="mb-1">Role: <?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
                                            <p>User ID: <?php echo $user_id; ?></p>
                                        </div>
                                        
                                        <div class="alert alert-warning">
                                            <i class="ri-alert-line me-1"></i>
                                            This action cannot be undone. Please be certain.
                                        </div>

                                        <form method="post" action="user-delete.php?id=<?php echo $user_id; ?>">
                                            <!-- CSRF token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Deletion Type</label>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="radio" name="delete_type" id="soft_delete" value="soft" checked>
                                                    <label class="form-check-label" for="soft_delete">
                                                        <strong>Soft Delete</strong> - Mark as deleted but keep records
                                                    </label>
                                                    <small class="d-block text-muted">User will appear deleted but data is preserved and can be restored later.</small>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="delete_type" id="hard_delete" value="hard">
                                                    <label class="form-check-label" for="hard_delete">
                                                        <strong>Hard Delete</strong> - Permanently remove all user data
                                                    </label>
                                                    <small class="d-block text-danger">This will permanently delete all user data including profiles, payments, and history.</small>
                                                </div>
                                            </div>

                                            <div class="mt-4">
                                                <a href="user-view.php?id=<?php echo $user_id; ?>" class="btn btn-light me-1">Cancel</a>
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to delete this user?');">
                                                    Confirm Delete
                                                </button>
                                            </div>
                                        </form>
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
</body>
</html><?php include 'layouts/session.php'; ?>
<?php include 'layouts/main.php'; ?>

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

// Initialize variables
$errors = [];
$success = false;

// Check for CSRF token if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid security token. Please try again.";
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Get user ID from URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no valid ID, redirect to users page
if ($user_id === 0) {
    $_SESSION['error'] = "Invalid user ID";
    header("Location: users.php");
    exit;
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User not found";
        header("Location: users.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving user data";
    header("Location: users.php");
    exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Determine delete type (soft or hard delete)
    $delete_type = isset($_POST['delete_type']) ? $_POST['delete_type'] : 'soft';
    
    try {
        $pdo->beginTransaction();
        
        // Get admin info for logging
        $admin_id = $_SESSION['admin_id'];
        
        if ($delete_type === 'hard') {
            // Hard delete - Delete related records first
            
            // Delete profile
            $stmt = $pdo->prepare("DELETE FROM profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete payments
            $stmt = $pdo->prepare("DELETE FROM payments WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user logs
            $stmt = $pdo->prepare("DELETE FROM user_logs WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete email logs
            $stmt = $pdo->prepare("DELETE FROM email_logs WHERE recipient_id = ?");
            $stmt->execute([$user_id]);
            
            // Finally delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Log admin action
            $action = "Hard deleted user: " . $user['username'] . " (ID: $user_id)";
        } else {
            // Soft delete - Just mark as deleted
            $stmt = $pdo->prepare("UPDATE users SET 
                account_status = 'deleted', 
                deleted_at = NOW(), 
                deleted_by = ? 
                WHERE id = ?");
            $stmt->execute([$admin_id, $user_id]);
            
            // Log admin action
            $action = "Soft deleted user: " . $user['username'] . " (ID: $user_id)";
        }
        
        // Log the action
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, user_id) VALUES (?, ?, ?)");
        $log_stmt->execute([$admin_id, $action, $user_id]);
        
        $pdo->commit();
        
        // Set success message and redirect
        $_SESSION['success'] = "User " . ($delete_type === 'hard' ? "permanently" : "soft") . " deleted successfully.";
        header("Location: users.php");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = "Database error: " . $e->getMessage();
        error_log("User deletion error: " . $e->getMessage());
    }
}
?>

<head>
    <title>Delete User | JCDA Admin Portal</title>
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
                                        <li class="breadcrumb-item active">Delete User</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">Delete User</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-danger">
                                    <h4 class="header-title text-white mb-0">Confirm User Deletion</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <ul class="mb-0">
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?php echo $error; ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-center mb-4">
                                        <div class="avatar-lg mx-auto">
                                            <div class="avatar-title bg-light text-danger display-4 rounded-circle">
                                                <i class="ri-delete-bin-7-line"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                                        <h4 class="mt-0">You are about to delete this user</h4>
                                        <p class="text-muted">
                                            Please confirm that you want to delete the following user:
                                        </p>
                                        
                                        <div class="mb-4">
                                            <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                                            <p class="mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                                            <p class="mb-1">Role: <?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
                                            <p>User ID: <?php echo $user_id; ?></p>
                                        </div>
                                        
                                        <div class="alert alert-warning">
                                            <i class="ri-alert-line me-1"></i>
                                            This action cannot be undone. Please be certain.
                                        </div>

                                        <form method="post" action="user-delete.php?id=<?php echo $user_id; ?>">
                                            <!-- CSRF token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Deletion Type</label>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="radio" name="delete_type" id="soft_delete" value="soft" checked>
                                                    <label class="form-check-label" for="soft_delete">
                                                        <strong>Soft Delete</strong> - Mark as deleted but keep records
                                                    </label>
                                                    <small class="d-block text-muted">User will appear deleted but data is preserved and can be restored later.</small>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="delete_type" id="hard_delete" value="hard">
                                                    <label class="form-check-label" for="hard_delete">
                                                        <strong>Hard Delete</strong> - Permanently remove all user data
                                                    </label>
                                                    <small class="d-block text-danger">This will permanently delete all user data including profiles, payments, and history.</small>
                                                </div>
                                            </div>

                                            <div class="mt-4">
                                                <a href="user-view.php?id=<?php echo $user_id; ?>" class="btn btn-light me-1">Cancel</a>
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to delete this user?');">
                                                    Confirm Delete
                                                </button>
                                            </div>
                                        </form>
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
</body>
</html>