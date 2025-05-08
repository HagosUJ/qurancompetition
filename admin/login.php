<?php
session_start();

// Initialize variables
$error = '';
$email = '';
$pdo = null;

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
    $error = "Database connection error: " . $e->getMessage();
    // Log the error for administrators
    error_log("Login page error: " . $e->getMessage());
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for maximum login attempts
function checkLoginAttempts($db, $ip) {
    if (!$db instanceof PDO) {
        return false; // Can't check attempts without database
    }
    
    try {
        $timeWindow = time() - (15 * 60); // 15 minutes window
        
        // Check if the table exists first
        $tableCheck = $db->query("SHOW TABLES LIKE 'admin_logs'");
        if ($tableCheck->rowCount() == 0) {
            // Table doesn't exist, log this issue
            error_log("admin_logs table not found in database");
            return false;
        }
        
        $stmt = $db->prepare("SELECT COUNT(*) as attempts FROM admin_logs WHERE ip_address = ? AND action = 'Failed Login' AND created_at > FROM_UNIXTIME(?)");
        $stmt->execute([$ip, $timeWindow]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($result['attempts']) && $result['attempts'] >= 5; // 5 attempts limit
    } catch (PDOException $e) {
        error_log("Login attempt check failed: " . $e->getMessage());
        return false; // Default to allowing login if check fails
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security verification failed, please try again";
    } else if (!$pdo instanceof PDO) {
        $error = "Cannot process login due to database connection issue";
    } else {
        // Get client IP
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check for excessive login attempts - temporarily disable for debugging
        // if (checkLoginAttempts($pdo, $ip)) {
        //     $error = "Too many failed login attempts. Please try again later.";
        // } else {
            // Validate input
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';
            
            // Basic validation
            if (empty($email) || empty($password)) {
                $error = "Email and password are required";
            } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address";
            } else {
                try {
                    // Debug - check if users table exists and has the expected structure
                    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
                    if ($tableCheck->rowCount() == 0) {
                        $error = "Required database table not found";
                        error_log("users table not found in database");
                    } else {
                        // Check column structure (for debugging)
                        $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
                        if ($columnCheck->rowCount() == 0) {
                            $error = "Database schema mismatch";
                            error_log("role column not found in users table");
                        } else {
                            // Check credentials against users table with admin role
                            $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
                            $stmt->execute([$email]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($user) {
                                // Verify password and admin role
                                if (password_verify($password, $user['password'])) {
                                    if ($user['role'] === 'admin') {
                                        // Log successful login - check if admin_logs table exists
                                        $tableCheck = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
                                        if ($tableCheck->rowCount() > 0) {
                                            try {
                                                $action = "Admin Login";
                                                $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_username, action, ip_address) VALUES (?, ?, ?)");
                                                $log_stmt->execute([$user['email'], $action, $ip]);
                                            } catch (PDOException $e) {
                                                // Just log the error, but don't prevent login
                                                error_log("Failed to log successful login: " . $e->getMessage());
                                            }
                                        }
                                        
                                        // Set secure session
                                        session_regenerate_id(true);
                                        $_SESSION['admin_id'] = $user['id'];
                                        $_SESSION['admin_email'] = $user['email'];
                                        $_SESSION['admin_username'] = $user['username'];
                                        $_SESSION['admin_logged_in'] = true;
                                        $_SESSION['admin_role'] = 'admin';
                                        $_SESSION['last_activity'] = time();
                                        
                                        // Handle remember me - only if auth_tokens table exists
                                        if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                                            $tableCheck = $pdo->query("SHOW TABLES LIKE 'auth_tokens'");
                                            if ($tableCheck->rowCount() > 0) {
                                                $selector = bin2hex(random_bytes(16));
                                                $validator = bin2hex(random_bytes(32));
                                                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                                                
                                                // Hash the validator before storing in the database
                                                $hashedValidator = password_hash($validator, PASSWORD_DEFAULT);
                                                
                                                // Store in database
                                                try {
                                                    $token_stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, selector, token, expires) VALUES (?, ?, ?, ?)");
                                                    $token_stmt->execute([$user['id'], $selector, $hashedValidator, date('Y-m-d H:i:s', $expires)]);
                                                    
                                                    // Set cookie
                                                    $cookie = $selector . ':' . $validator;
                                                    setcookie('admin_remember', $cookie, $expires, '/', '', true, true);
                                                } catch (PDOException $e) {
                                                    error_log("Failed to set remember-me token: " . $e->getMessage());
                                                    // Continue without remember me
                                                }
                                            }
                                        }
                                        
                                        // Redirect to dashboard
                                        header("Location: index.php");
                                        exit;
                                    } else {
                                        $error = "Insufficient privileges. Admin access required.";
                                    }
                                } else {
                                    $error = "Invalid email or password";
                                }
                            } else {
                                $error = "Invalid email or password";
                            }
                            
                            // Failed login - log attempt if possible
                            if ($error) {
                                sleep(1); // Add delay to prevent brute force
                                
                                $tableCheck = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
                                if ($tableCheck->rowCount() > 0) {
                                    try {
                                        $action = "Failed Login";
                                        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_username, action, ip_address) VALUES (?, ?, ?)");
                                        $log_stmt->execute([$email, $action, $ip]);
                                    } catch (PDOException $e) {
                                        error_log("Failed to log failed login attempt: " . $e->getMessage());
                                    }
                                }
                            }
                        }
                    }
                } catch (PDOException $e) {
                    $error = "Login processing error: " . $e->getMessage(); // Enhanced error message
                    error_log("Login error: " . $e->getMessage());
                }
            }
        // }
    }
    
    // Regenerate CSRF token after submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Log In | Admin Portal</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
</head>

<body class="authentication-bg position-relative">

<?php include 'layouts/background.php'; ?>

    <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5 position-relative">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-4 col-lg-5">
                    <div class="card">

                        <!-- Logo -->
                        <div class="card-header py-4 text-center bg-primary">
                            <a href="index.php">
                                <span><img src="assets/images/logo.png" alt="logo" height="22"></span>
                            </a>
                        </div>

                        <div class="card-body p-4">

                            <div class="text-center w-75 m-auto">
                                <h4 class="text-dark-50 text-center pb-0 fw-bold">Admin Sign In</h4>
                                <p class="text-muted mb-4">Enter your email and password to access admin panel.</p>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>

                            <form method="post">
                                <!-- CSRF Protection -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input class="form-control" type="email" id="email" name="email" required 
                                           value="<?php echo htmlspecialchars($email); ?>"
                                           placeholder="Enter your email">
                                </div>

                                <div class="mb-3">
                                    <a href="auth-recoverpw.php" class="text-muted float-end fs-12">Forgot your password?</a>
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group input-group-merge">
                                        <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
                                        <div class="input-group-text" data-password="false">
                                            <span class="password-eye"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="checkbox-signin" name="remember">
                                        <label class="form-check-label" for="checkbox-signin">Remember me</label>
                                    </div>
                                </div>

                                <div class="mb-3 text-center">
                                    <button class="btn btn-primary" type="submit">Log In</button>
                                </div>

                            </form>
                        </div> <!-- end card-body -->
                    </div>
                    <!-- end card -->

                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <p class="text-muted bg-body">Access restricted to administrators only.</p>
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