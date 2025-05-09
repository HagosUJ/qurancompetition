<?php
// File: create_admin.php - Script to add an admin user
// This script should be deleted after use for security reasons

// Initialize variables
$success = false;
$error = '';
$message = '';

// Include database connection
try {
    if (!file_exists('includes/db.php')) {
        die("Database file not found at 'includes/db.php'");
    }
    require_once('includes/db.php');
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        die("Database connection not established. Check db.php file.");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if form is submitted with required fields
    if (
        isset($_POST['fullname']) && !empty($_POST['fullname']) &&
        isset($_POST['email']) && !empty($_POST['email']) &&
        isset($_POST['password']) && !empty($_POST['password']) &&
        isset($_POST['confirm_password']) && !empty($_POST['confirm_password']) &&
        isset($_POST['secret_key']) && !empty($_POST['secret_key'])
    ) {
        // Validate inputs
        $fullname = filter_var(trim($_POST['fullname']), FILTER_SANITIZE_STRING);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $secret_key = $_POST['secret_key'];
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        }
        // Check if passwords match
        else if ($password !== $confirm_password) {
            $error = "Passwords do not match";
        }
        // Check secret key - this adds an extra layer of security
        // Change this to your own secure key
        else if ($secret_key !== "MajlisuAhlilQuranAdmin2023") {
            $error = "Invalid secret key";
        }
        // Check password strength
        else if (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        }
        else {
            try {
                // First check if user already exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check_stmt->execute([$email]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error = "A user with this email already exists";
                } else {
                    // Generate password hash
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Try to insert the new admin user
                    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status, created_at) VALUES (?, ?, ?, 'admin', 'active', NOW())");
                    $result = $stmt->execute([$fullname, $email, $password_hash]);
                    
                    if ($result) {
                        $success = true;
                        $message = "Admin user created successfully! You can now log in.";
                    } else {
                        $error = "Failed to create admin user";
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                // Log more details for debugging
                error_log("Admin creation error: " . $e->getMessage());
            }
        }
    } else {
        $error = "Please fill in all the fields";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Admin User</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .secret-key-info {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
        }
        .container-narrow {
            max-width: 600px;
        }
    </style>
</head>

<body class="authentication-bg position-relative">

<?php include 'layouts/background.php'; ?>

    <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5 position-relative">
        <div class="container container-narrow">
            <div class="row justify-content-center">
                <div class="col-xxl-8 col-lg-10">
                    <div class="card">
                        <!-- Logo -->
                        <div class="card-header py-4 text-center bg-primary">
                            <a href="index.php">
                                <span><img src="assets/images/logo.png" alt="logo" height="22"></span>
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <div class="text-center w-75 m-auto">
                                <h4 class="text-dark-50 text-center pb-0 fw-bold">Create Admin User</h4>
                                <p class="text-muted mb-4">Create a new administrator account.</p>
                            </div>

                            <div class="alert alert-warning mb-4">
                                <strong>Security Warning:</strong> This script creates an administrator account with full system access. 
                                Delete this file immediately after creating your admin user. Keep the secret key confidential.
                            </div>

                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                                <div class="text-center mt-4">
                                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                                </div>
                            <?php else: ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>

                                <form method="post">
                                    <div class="mb-3">
                                        <label for="fullname" class="form-label">Full Name</label>
                                        <input class="form-control" type="text" id="fullname" name="fullname" required 
                                               placeholder="Enter full name">
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input class="form-control" type="email" id="email" name="email" required 
                                               placeholder="Enter email address">
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group input-group-merge">
                                            <input type="password" id="password" name="password" class="form-control" required 
                                                   placeholder="Enter password">
                                            <div class="input-group-text" data-password="false">
                                                <span class="password-eye"></span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Password must be at least 8 characters long</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <div class="input-group input-group-merge">
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required 
                                                   placeholder="Confirm password">
                                            <div class="input-group-text" data-password="false">
                                                <span class="password-eye"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="secret_key" class="form-label">Secret Key</label>
                                        <input class="form-control" type="password" id="secret_key" name="secret_key" required 
                                               placeholder="Enter secret key">
                                        <div class="secret-key-info">
                                            The default secret key is: MajlisuAhlilQuranAdmin2023
                                        </div>
                                    </div>

                                    <div class="mb-3 text-center">
                                        <button class="btn btn-primary" type="submit">Create Admin User</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div> <!-- end card-body -->
                    </div>
                    <!-- end card -->

                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <p class="text-muted bg-body">Return to <a href="login.php" class="text-muted ms-1"><b>Login</b></a></p>
                        </div> <!-- end col -->
                    </div>
                    <!-- end row -->
                </div> <!-- end col -->
            </div>
            <!-- end row -->
        </div>
        <!-- end container -->
    </div>
    <!-- end page -->

    <footer class="footer footer-alt fw-medium">
        <span class="bg-body">
            <script>document.write(new Date().getFullYear())</script> © All Rights Reserved
        </span>
    </footer>
    
    <?php include 'layouts/footer-scripts.php'; ?>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

</body>
</html>