<?php
/**
 * Resend Verification Emails Script with Majlis SMTP
 *
 * Resends verification emails to users with 'pending' status using Majlis SMTP.
 * Processes all pending users with batch processing, UI, rate limiting, and anti-spam features.
 * Prevents duplicate emails by checking last_verification_sent timestamp.
 *
 * @version 1.5
 * @license MIT
 */

declare(strict_types=1);

// Core dependencies
require_once __DIR__ . '/includes/db.php';       // Database connection ($conn)
require_once __DIR__ . '/includes/functions.php'; // Utility functions (redirect, sanitize_input)
require_once __DIR__ . '/includes/config.php';    // Application configuration (APP_URL, APP_NAME)
require_once __DIR__ . '/vendor/autoload.php';    // Composer autoloader

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Log file and constants
define('LOG_FILE', __DIR__ . '/logs/email_resend_majlis.log');
define('RATE_LIMIT_DELAY', 1); // Seconds between each email send
define('TOKEN_EXPIRY_ACTIVATION', 24 * 60 * 60); // 24 hours
define('BATCH_SIZE', 10); // Number of users per batch
define('MIN_RESEND_INTERVAL', 24 * 60 * 60); // Minimum interval before resending (24 hours)

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', '1'); // Requires HTTPS
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Log message function
function log_message(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        if (!mkdir($log_dir, 0755, true) && !is_dir($log_dir)) {
            error_log("Cannot create log directory: $log_dir");
            return;
        }
    }
    if (!file_exists(LOG_FILE) || is_writable(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
    } else {
        error_log("Log file is not writable: " . LOG_FILE);
    }
}

// Ensure last_verification_sent column exists
try {
    $conn->query("ALTER TABLE users ADD COLUMN last_verification_sent DATETIME DEFAULT NULL");
    log_message("Added last_verification_sent column to users table");
} catch (Exception $e) {
    // Ignore if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
        log_message("Failed to add last_verification_sent column: " . $e->getMessage());
    }
}

// Custom send_verification_email for Majlis SMTP
function send_verification_email(string $email, string $name, string $token): bool
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.majlisuahlilquran.org';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply2@majlisuahlilquran.org';
        $mail->Password = '8ehr(^NvA^FC';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL for port 465
        $mail->Port = 465;

        // Anti-spam headers
        $mail->DKIM_domain = 'majlisuahlilquran.org'; // Match From email domain
        $mail->DKIM_selector = 'default'; // Adjust based on SMTP provider's DKIM setup
        $mail->DKIM_private = null; // Set to DKIM private key path if provided
        $mail->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $mail->DKIM_domain);
        $mail->set('Date', date('r'));

        // Recipients
        $mail->setFrom('noreply2@majlisuahlilquran.org', 'JCDA');
        $mail->addAddress($email, $name);
        $mail->addReplyTo('noreply2@majlisuahlilquran.org', 'JCDA');

        // Content
        $verification_link = APP_URL . "/portal/verify.php?token=" . $token . "&email=" . urlencode($email);
        $unsubscribe_link = APP_URL . "/portal/unsubscribe.php?email=" . urlencode($email);
        $subject = APP_NAME . ' - Verify Your Account';
        $body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Verify Your Account</title>
            </head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                    <h2 style="color: #4CAF50;">Welcome to ' . htmlspecialchars(APP_NAME) . '</h2>
                    <p>Dear ' . htmlspecialchars($name) . ',</p>
                    <p>Thank you for registering with us. To complete your registration and verify your account, please click the button below:</p>
                    <p style="text-align: center;">
                        <a href="' . $verification_link . '" style="display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">Verify My Account</a>
                    </p>
                    <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
                    <p><a href="' . $verification_link . '">' . $verification_link . '</a></p>
                    <p>This link expires in ' . (TOKEN_EXPIRY_ACTIVATION / 3600) . ' hours.</p>
                    <p>If you did not create an account, please ignore this email or <a href="' . $unsubscribe_link . '">unsubscribe</a>.</p>
                    <p>Regards,<br>' . htmlspecialchars(APP_NAME) . ' Team</p>
                    <hr style="border-top: 1px solid #eee;">
                    <p style="font-size: 12px; color: #777;">
                        You are receiving this email because you registered at ' . APP_URL . '.<br>
                        <a href="' . $unsubscribe_link . '">Unsubscribe</a> from future emails.
                    </p>
                </div>
            </body>
            </html>';
        $altBody = "Dear {$name},\n\nVerify your account by visiting: {$verification_link}\n\nThis link expires in " . (TOKEN_EXPIRY_ACTIVATION / 3600) . " hours.\n\nIf you did not create an account, please ignore this email or visit: {$unsubscribe_link}\n\nRegards,\n" . APP_NAME . " Team";

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        log_message("Email sending failed to {$email}: " . $mail->ErrorInfo);
        return false;
    } catch (Exception $e) {
        log_message("General exception during email sending to {$email}: " . $e->getMessage());
        return false;
    }
}

// Process a batch of users
function process_email_batch(int $offset, int $limit, int &$total_sent): array
{
    global $conn;
    $users = [];
    $sent = 0;
    $failed = 0;
    $skipped = 0;
    $errors = [];

    try {
        // Query users with offset and limit, checking last_verification_sent
        $stmt = $conn->prepare("SELECT id, fullname, email, activation_hash, activation_expiry, last_verification_sent 
                                FROM users 
                                WHERE status = 'pending' 
                                AND activation_hash IS NOT NULL 
                                AND activation_expiry > NOW() 
                                AND (last_verification_sent IS NULL OR last_verification_sent < NOW() - INTERVAL ? SECOND)
                                LIMIT ? OFFSET ?");
        if (!$stmt) {
            throw new RuntimeException("DB prepare failed: " . $conn->error);
        }
        $min_interval_for_batch = MIN_RESEND_INTERVAL; // Assign constant to a local variable
        $stmt->bind_param("iii", $min_interval_for_batch, $limit, $offset); // Use the variable
        $stmt->execute();
        $result = $stmt->get_result();

        while ($user = $result->fetch_assoc()) {
            $users[] = [
                'id' => $user['id'],
                'fullname' => $user['fullname'],
                'email' => $user['email'],
                'status' => 'Pending',
                'error' => ''
            ];
        }
        $stmt->close();

        foreach ($users as $index => $user) {
            // Generate new activation token
            $new_token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $new_token);
            $expiry_timestamp = time() + TOKEN_EXPIRY_ACTIVATION;
            $expiry_datetime = date('Y-m-d H:i:s', $expiry_timestamp);
            $sent_datetime = date('Y-m-d H:i:s');

            // Update user's activation token and last_verification_sent
            $update_stmt = $conn->prepare("UPDATE users SET activation_hash = ?, activation_expiry = ?, last_verification_sent = ? WHERE id = ?");
            if (!$update_stmt) {
                $error = "Failed to prepare update statement for user ID {$user['id']}: " . $conn->error;
                $errors[] = $error;
                log_message($error);
                $users[$index]['status'] = 'Failed';
                $users[$index]['error'] = $error;
                $failed++;
                continue;
            }

            $update_stmt->bind_param("sssi", $token_hash, $expiry_datetime, $sent_datetime, $user['id']);
            if (!$update_stmt->execute()) {
                $error = "Failed to update activation token for user ID {$user['id']}: " . $conn->error;
                $errors[] = $error;
                log_message($error);
                $users[$index]['status'] = 'Failed';
                $users[$index]['error'] = $error;
                $failed++;
                $update_stmt->close();
                continue;
            }
            $update_stmt->close();

            // Send verification email
            $email_sent = send_verification_email($user['email'], $user['fullname'], $new_token);

            if ($email_sent) {
                $sent++;
                $total_sent++;
                log_message("Verification email resent successfully to {$user['email']} (User ID: {$user['id']})");
                $users[$index]['status'] = 'Sent';
            } else {
                $failed++;
                $error = "Failed to resend verification email to {$user['email']} (User ID: {$user['id']})";
                $errors[] = $error;
                log_message($error);
                $users[$index]['status'] = 'Failed';
                $users[$index]['error'] = $error;
            }

            // Rate limiting
            if ($index < count($users) - 1) {
                sleep(RATE_LIMIT_DELAY);
            }
        }
    } catch (Exception $e) {
        $error = "Exception during batch processing (offset $offset): " . $e->getMessage();
        $errors[] = $error;
        log_message($error);
    }

    return [$sent, $failed, $skipped, $errors, $users];
}

// Get total pending users eligible for resending
function get_total_pending_users(): int
{
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as total 
                            FROM users 
                            WHERE status = 'pending' 
                            AND activation_hash IS NOT NULL 
                            AND activation_expiry > NOW()
                            AND (last_verification_sent IS NULL OR last_verification_sent < NOW() - INTERVAL ? SECOND)");
    if (!$stmt) {
        log_message("Failed to prepare count query: " . $conn->error);
        return 0;
    }
    $min_interval = MIN_RESEND_INTERVAL; // Assign constant to a variable
    $stmt->bind_param("i", $min_interval); // Use the variable here
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (int)$row['total'];
}

// Handle AJAX batch processing
if (isset($_GET['action']) && $_GET['action'] === 'process_batch') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $total_sent = isset($_SESSION['total_sent']) ? (int)$_SESSION['total_sent'] : 0;
    [$sent, $failed, $skipped, $errors, $users] = process_email_batch($offset, BATCH_SIZE, $total_sent);
    $_SESSION['total_sent'] = $total_sent;
    echo json_encode([
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'errors' => $errors,
        'users' => $users,
        'offset' => $offset + count($users),
        'total_sent' => $total_sent
    ]);
    exit;
}

// Reset total_sent on initial load
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    $_SESSION['total_sent'] = 0;
}

// Get initial user list for display
$total = get_total_pending_users();
$users = [];
if ($total > 0) {
    $initial_limit = BATCH_SIZE;
    [$sent, $failed, $skipped, $errors, $users] = process_email_batch(0, $initial_limit, $_SESSION['total_sent']);
}
$sent = 0;
$failed = 0;
$skipped = 0;
$errors = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification Emails (Majlis) | <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/vendors/keenicons/styles.bundle.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background-color: #f9fafb;
        }
        .status-sent {
            color: #10b981;
            font-weight: 500;
        }
        .status-failed, .status-skipped {
            color: #ef4444;
            font-weight: 500;
        }
        .status-pending {
            color: #6b7280;
            font-weight: 500;
        }
        .progress-container {
            margin-bottom: 20px;
        }
        .progress-bar {
            width: 0;
            height: 10px;
            background-color: #4CAF50;
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        .progress-bg {
            background-color: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 8px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #2563eb;
        }
        .btn:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        .error {
            color: #ef4444;
            font-size: 14px;
        }
        .note {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Resend Verification Emails (Majlis)</h1>
        <p class="note">Note: Emails are sent to pending users who haven’t received a verification email in the last 24 hours.</p>
        <form id="resend-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <button type="submit" class="btn" id="resend-btn">Resend Emails</button>
        </form>

        <div class="progress-container">
            <p>Progress: <span id="progress-text">0%</span></p>
            <div class="progress-bg">
                <div class="progress-bar" id="progress-bar"></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody id="user-table">
                <?php foreach ($users as $user): ?>
                    <tr data-user-id="<?php echo htmlspecialchars((string)$user['id']); ?>">
                        <td><?php echo htmlspecialchars((string)$user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="status-<?php echo strtolower($user['status']); ?>">
                            <?php echo htmlspecialchars($user['status']); ?>
                        </td>
                        <td class="error"><?php echo htmlspecialchars($user['error']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary" id="summary" style="display: none;">
            <p><strong>Total Eligible Users:</strong> <span id="total-users"><?php echo $total; ?></span></p>
            <p><strong>Emails Sent:</strong> <span id="sent-emails">0</span></p>
            <p><strong>Emails Failed:</strong> <span id="failed-emails">0</span></p>
            <p><strong>Emails Skipped:</strong> <span id="skipped-emails">0</span></p>
            <div id="error-list"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resend-form');
            const resendBtn = document.getElementById('resend-btn');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const userTable = document.getElementById('user-table');
            const summary = document.getElementById('summary');
            const totalUsers = <?php echo $total; ?>;
            let totalSent = 0;
            let totalFailed = 0;
            let totalSkipped = 0;
            let totalProcessed = 0;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                resendBtn.disabled = true;
                resendBtn.textContent = 'Processing...';
                summary.style.display = 'block';
                processBatch(0);
            });

            function processBatch(offset) {
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
                formData.append('offset', offset);

                fetch('?action=process_batch', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend Emails';
                        return;
                    }

                    // Update table
                    data.users.forEach(user => {
                        const row = userTable.querySelector(`tr[data-user-id="${user.id}"]`);
                        if (row) {
                            row.querySelector('.status-pending, .status-sent, .status-failed, .status-skipped')
                                .className = `status-${user.status.toLowerCase()}`;
                            row.querySelector('.status-pending, .status-sent, .status-failed, .status-skipped')
                                .textContent = user.status;
                            row.querySelector('.error').textContent = user.error;
                        } else {
                            const newRow = document.createElement('tr');
                            newRow.setAttribute('data-user-id', user.id);
                            newRow.innerHTML = `
                                <td>${user.id}</td>
                                <td>${user.fullname}</td>
                                <td>${user.email}</td>
                                <td class="status-${user.status.toLowerCase()}">${user.status}</td>
                                <td class="error">${user.error}</td>
                            `;
                            userTable.appendChild(newRow);
                        }
                    });

                    // Update summary
                    totalSent += data.sent;
                    totalFailed += data.failed;
                    totalSkipped += data.skipped;
                    totalProcessed += data.users.length;
                    document.getElementById('sent-emails').textContent = totalSent;
                    document.getElementById('failed-emails').textContent = totalFailed;
                    document.getElementById('skipped-emails').textContent = totalSkipped;

                    if (data.errors.length > 0) {
                        const errorList = document.getElementById('error-list');
                        errorList.innerHTML = '<p><strong>Errors:</strong></p><ul>' +
                            data.errors.map(error => `<li class="error">${error}</li>`).join('') +
                            '</ul>';
                    }

                    // Update progress
                    const percent = totalUsers > 0 ? Math.min((totalProcessed / totalUsers) * 100, 100) : 0;
                    progressBar.style.width = `${percent}%`;
                    progressText.textContent = `${Math.round(percent)}%`;

                    // Process next batch if more users remain
                    if (data.offset < totalUsers) {
                        processBatch(data.offset);
                    } else {
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend Emails';
                        alert('Email resending completed. Sent: ' + totalSent + ', Failed: ' + totalFailed + ', Skipped: ' + totalSkipped);
                    }
                })
                .catch(error => {
                    alert('Request failed: ' + error.message);
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Resend Emails';
                });
            }
        });
    </script>
</body>
</html>