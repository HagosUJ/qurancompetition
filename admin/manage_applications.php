<?php
// Ensure session configurations are set before starting the session
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);

// Include database connection
try {
    if (!file_exists('includes/db.php')) {
        throw new Exception("Database file not found at 'includes/db.php'");
    }
    require_once 'includes/db.php';
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection not established. Check db.php file.");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Include PHPMailer autoloader only if not already loaded
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once 'vendor/autoload.php';
}

// Start session after configurations
session_start();

// Check if admin is logged in
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Define APP_URL if not already defined
if (!defined('APP_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); 
    $app_root_path = dirname($script_dir);
    if ($app_root_path === '/' || $app_root_path === '\\') {
        $app_root_path = '';
    }
    define('APP_URL', $protocol . "://" . $host . $app_root_path . '/');
}

// Handle individual and bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $application_ids = [];
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;

    if ($action === 'bulk_approve' || $action === 'bulk_reject') {
        $application_ids = isset($_POST['application_ids']) && is_array($_POST['application_ids']) 
            ? array_map('intval', $_POST['application_ids']) 
            : [];
    } elseif ($action === 'approve' || $action === 'reject') {
        $application_ids = isset($_POST['application_id']) && filter_var($_POST['application_id'], FILTER_VALIDATE_INT) 
            ? [intval($_POST['application_id'])] 
            : [];
    }

    if (!empty($application_ids) && in_array($action, ['approve', 'reject', 'bulk_approve', 'bulk_reject'])) {
        $new_status = (in_array($action, ['approve', 'bulk_approve'])) ? 'Approved' : 'Rejected';
        try {
            $placeholders = implode(',', array_fill(0, count($application_ids), '?'));
            $update_query = "UPDATE applications SET status = ?, rejection_reason = ?, last_updated = NOW() WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute(array_merge([$new_status, $new_status === 'Rejected' ? $rejection_reason : null], $application_ids));
        
            $query_users = "SELECT u.id AS user_id, u.fullname, u.email, a.contestant_type 
                     FROM applications a 
                     JOIN users u ON a.user_id = u.id 
                     WHERE a.id IN ($placeholders)";
            $stmt_users = $pdo->prepare($query_users);
            $stmt_users->execute($application_ids);
            $applicants = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
        
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $email_errors = [];
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = 'mail.majlisuahlilquran.org'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'admin@majlisuahlilquran.org'; 
            $mail->Password = '%bDbxex4n%Mn'; 
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->SMTPKeepAlive = true; 

            foreach ($applicants as $applicant) {
                try {
                    $mail->setFrom('admin@majlisuahlilquran.org', 'Majlis Ahlil Quran Musabaqa Team');
                    $mail->addReplyTo('admin@majlisuahlilquran.org', 'Majlis Ahlil Quran Musabaqa Team');
                    $mail->addAddress($applicant['email'], $applicant['fullname']);
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = 'Application Status Update';
                    
                    $htmlBody = "<h3 style='color: #1a3c34; font-family: Arial, sans-serif;'>Dear {$applicant['fullname']},</h3>
                             <p style='font-family: Arial, sans-serif; font-size: 14px;'>Your application for the " . ($applicant['contestant_type'] === 'nigerian' ? 'Nigerian' : 'International') . " competition has been <strong>" . ucfirst($new_status) . "</strong>.</p>";
                    if ($new_status === 'Rejected' && $rejection_reason) {
                        $htmlBody .= "<p style='font-family: Arial, sans-serif; font-size: 14px;'><strong>Reason for Rejection:</strong> " . htmlspecialchars($rejection_reason) . "</p>";
                    }
                    $htmlBody .= "<p style='font-family: Arial, sans-serif; font-size: 14px;'>Please check your dashboard for further details: <a href='" . APP_URL . "login.php' style='color: #007bff; text-decoration: none;'>Login to Dashboard</a></p>
                              <p style='font-family: Arial, sans-serif; font-size: 14px;'>Best regards,<br>Musabaqa Team</p>";
                    $mail->Body = $htmlBody;

                    $plainTextBody = "Dear {$applicant['fullname']},\n\n" .
                                     "Your application for the " . ($applicant['contestant_type'] === 'nigerian' ? 'Nigerian' : 'International') . " competition has been " . ucfirst($new_status) . ".\n";
                    if ($new_status === 'Rejected' && $rejection_reason) {
                        $plainTextBody .= "Reason for Rejection: " . htmlspecialchars($rejection_reason) . "\n";
                    }
                    $plainTextBody .= "Please check your dashboard for further details: " . APP_URL . "login.php\n\n" .
                                      "Best regards,\nMusabaqa Team";
                    $mail->AltBody = $plainTextBody;
        
                    $mail->send();
        
                    $notification_message = "Your application has been $new_status.";
                    if ($new_status === 'Rejected' && $rejection_reason) {
                        $notification_message .= " Reason: $rejection_reason";
                    }
                    $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) 
                                        VALUES (:user_id, :message, 'application_status', NOW())";
                    $stmt_notif = $pdo->prepare($notification_query);
                    $stmt_notif->execute([
                        'user_id' => $applicant['user_id'],
                        'message' => $notification_message
                    ]);
        
                    $mail->clearAddresses(); 
                    $mail->clearAttachments(); 
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    $email_errors[] = "Failed to send email to {$applicant['email']}: " . $mail->ErrorInfo;
                }
            }
            $mail->smtpClose(); 
        
            if (empty($email_errors)) {
                $_SESSION['success'] = count($application_ids) . " application(s) $new_status successfully.";
            } else {
                $_SESSION['error'] = "Application(s) updated but some emails failed: " . implode(', ', $email_errors);
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating application(s): " . $e->getMessage();
        } catch (\PHPMailer\PHPMailer\Exception $e) { 
            $_SESSION['error'] = "Mailer Error: " . $e->getMessage();
        }
        
        header("Location: manage_applications.php" . (isset($_GET['status']) ? "?status={$_GET['status']}" : ""));
        exit;
    } elseif ($action === 'send_reminders') {
        // Include all statuses except Approved and Rejected
        $reminder_statuses = [
            'Not Started',
            'Personal Info Complete',
            'Sponsor Info Complete',
            'Documents Uploaded',
            'Information Requested',
            'Submitted',
            'Under Review'
        ];
        $placeholders_statuses = implode(',', array_fill(0, count($reminder_statuses), '?'));

        try {
            // Only select applications where last_reminder_at is NULL or older than 3 days
            $query_remind = "SELECT u.id AS user_id, u.fullname, u.email, a.status AS application_status, a.contestant_type
                      FROM applications a
                      JOIN users u ON a.user_id = u.id
                      WHERE a.status IN ($placeholders_statuses)
                      AND (a.last_reminder_at IS NULL OR a.last_reminder_at < NOW() - INTERVAL 3 DAY)";
            $stmt_remind = $pdo->prepare($query_remind); 
            $stmt_remind->execute($reminder_statuses);
            $applicants_to_remind = $stmt_remind->fetchAll(PDO::FETCH_ASSOC);

            if (empty($applicants_to_remind)) {
                $_SESSION['info'] = "No applications found requiring a reminder at this time.";
                header("Location: manage_applications.php" . (isset($_GET['status']) ? "?status={$_GET['status']}" : ""));
                exit;
            }

            $queued_count = 0;
            $queue_errors = [];

            $insert_queue_query = "INSERT INTO email_queue (recipient_email, recipient_name, subject, html_body, text_body, status, created_at) 
                                   VALUES (:recipient_email, :recipient_name, :subject, :html_body, :text_body, 'pending', NOW())";
            $stmt_queue = $pdo->prepare($insert_queue_query);

            // Update last_reminder_at query
            $update_reminder_query = "UPDATE applications SET last_reminder_at = NOW() WHERE user_id = :user_id";
            $stmt_update_reminder = $pdo->prepare($update_reminder_query);

            foreach ($applicants_to_remind as $applicant) {
                $subject_remind = 'Reminder: Majlis Ahlil Quran Musabaqa Application Update';
                
                // Tailor message based on application status
                $status_message = '';
                $action_prompt = '';
                switch ($applicant['application_status']) {
                    case 'Not Started':
                        $status_message = "Your application has not yet been started.";
                        $action_prompt = "Please begin your application by logging into your dashboard.";
                        break;
                    case 'Personal Info Complete':
                        $status_message = "Your personal information is complete, but additional details are needed.";
                        $action_prompt = "Please proceed to enter your sponsor/nominator details.";
                        break;
                    case 'Sponsor Info Complete':
                        $status_message = "Your sponsor/nominator details are complete.";
                        $action_prompt = "Please upload the required documents to continue.";
                        break;
                    case 'Documents Uploaded':
                        $status_message = "Your documents have been uploaded.";
                        $action_prompt = "Please review and submit your application.";
                        break;
                    case 'Information Requested':
                        $status_message = "Additional information has been requested for your application.";
                        $action_prompt = "Please check your dashboard for details and provide the requested information.";
                        break;
                    case 'Submitted':
                        $status_message = "Your application has been submitted and is awaiting review.";
                        $action_prompt = "Please check your dashboard for any updates or additional requirements.";
                        break;
                    case 'Under Review':
                        $status_message = "Your application is currently under review.";
                        $action_prompt = "Please stay tuned for updates on your application status.";
                        break;
                    default:
                        $status_message = "Your application status is: " . htmlspecialchars($applicant['application_status']) . ".";
                        $action_prompt = "Please check your dashboard for next steps.";
                }

                $htmlBody_remind = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                        <div style='background-color: #1a3c34; padding: 10px; text-align: center;'>
                            <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>Majlis Ahlil Quran Musabaqa</h2>
                        </div>
                        <div style='background-color: #ffffff; padding: 20px; border: 1px solid #e0e0e0;'>
                            <h3 style='color: #1a3c34; font-size: 18px;'>Dear {$applicant['fullname']},</h3>
                            <p style='font-size: 14px; color: #333333;'>This is a reminder regarding your application for the Majlis Ahlil Quran Musabaqa.</p>
                            <p style='font-size: 14px; color: #333333;'>$status_message Your current application status is: <strong>" . htmlspecialchars($applicant['application_status']) . "</strong>.</p>
                            <p style='font-size: 14px; color: #333333;'>$action_prompt</p>
                            <p style='font-size: 14px; color: #333333;'>To complete or update your application, please follow these steps:</p>
                            <ol style='font-size: 14px; color: #333333; line-height: 1.6;'>
                                <li><strong>Login to Your Dashboard:</strong> Visit <a href='https://majlisuahlilquran.org/portal/sign-in.php' style='color: #007bff; text-decoration: none;'>the portal</a> and sign in with your credentials.</li>
                                <li><strong>Proceed to Start Application:</strong> Navigate to the 'Applications' section and click 'Start Application' or continue where you left off.</li>
                                <li><strong>Fill in Your Personal Information:</strong> Provide all required personal details accurately.</li>
                                <li><strong>Enter Sponsor/Nominators Details:</strong> Include information about your sponsor or nominators as required.</li>
                                <li><strong>Upload Your Documents:</strong> Upload all necessary documents in the specified formats.</li>
                                <li><strong>Submit Your Application:</strong> Review your application and submit it when complete.</li>
                            </ol>
                            <p style='font-size: 14px; color: #333333;'>Access your dashboard here: <a href='https://majlisuahlilquran.org/portal/sign-in.php' style='color: #007bff; text-decoration: none;'>Update Your Application</a></p>
                            <p style='font-size: 14px; color: #333333;'>If you have any questions, please contact us at <a href='mailto:admin@majlisuahlilquran.org' style='color: #007bff; text-decoration: none;'>admin@majlisuahlilquran.org</a>.</p>
                            <p style='font-size: 14px; color: #333333;'>Best regards,<br>The Musabaqa Team</p>
                        </div>
                        <div style='text-align: center; padding: 10px; font-size: 12px; color: #777777;'>
                            <p>© " . date('Y') . " Majlis Ahlil Quran Musabaqa. All rights reserved.</p>
                        </div>
                    </div>";

                $plainTextBody_remind = "Dear {$applicant['fullname']},\n\n" .
                                 "This is a reminder regarding your application for the Majlis Ahlil Quran Musabaqa.\n" .
                                 "$status_message Your current application status is: " . htmlspecialchars($applicant['application_status']) . ".\n" .
                                 "$action_prompt\n\n" .
                                 "To complete or update your application, please follow these steps:\n" .
                                 "1. Login to Your Dashboard: Visit https://majlisuahlilquran.org/portal/sign-in.php and sign in with your credentials.\n" .
                                 "2. Proceed to Start Application: Navigate to the 'Applications' section and click 'Start Application' or continue where you left off.\n" .
                                 "3. Fill in Your Personal Information: Provide all required personal details accurately.\n" .
                                 "4. Enter Sponsor/Nominators Details: Include information about your sponsor or nominators as required.\n" .
                                 "5. Upload Your Documents: Upload all necessary documents in the specified formats.\n" .
                                 "6. Submit Your Application: Review your application and submit it when complete.\n\n" .
                                 "Access your dashboard here: https://majlisuahlilquran.org/portal/sign-in.php\n\n" .
                                 "If you have any questions, please contact us at admin@majlisuahlilquran.org.\n\n" .
                                 "Best regards,\nThe Musabaqa Team";

                try {
                    // Queue the email
                    $stmt_queue->execute([
                        ':recipient_email' => $applicant['email'],
                        ':recipient_name' => $applicant['fullname'],
                        ':subject' => $subject_remind,
                        ':html_body' => $htmlBody_remind,
                        ':text_body' => $plainTextBody_remind
                    ]);
                    $queued_count++;

                    // Update last_reminder_at
                    $stmt_update_reminder->execute([':user_id' => $applicant['user_id']]);

                    // Create an in-app notification
                    $notification_message_remind = "Reminder: Please update your Musabaqa application. Current status: " . htmlspecialchars($applicant['application_status']);
                    $notification_query_remind = "INSERT INTO notifications (user_id, message, type, created_at) 
                                        VALUES (:user_id, :message, 'application_reminder', NOW())";
                    $stmt_notif_remind = $pdo->prepare($notification_query_remind);
                    $stmt_notif_remind->execute([
                        'user_id' => $applicant['user_id'],
                        'message' => $notification_message_remind
                    ]);

                } catch (PDOException $e) {
                    $queue_errors[] = "Failed to queue email for {$applicant['email']}: " . $e->getMessage();
                }
            }

            if ($queued_count > 0 && empty($queue_errors)) {
                $_SESSION['success'] = "$queued_count reminder email(s) have been queued for sending. Please run the email processing script.";
            } elseif ($queued_count > 0 && !empty($queue_errors)) {
                $_SESSION['warning'] = "$queued_count reminder(s) queued, but some failed to queue: " . implode(', ', $queue_errors);
            } elseif (empty($queue_errors)) { 
                 $_SESSION['info'] = "No applications found requiring a reminder at this time.";
            } else { 
                $_SESSION['error'] = "Failed to queue reminder emails. Errors: " . implode(', ', $queue_errors);
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error while preparing to queue reminders: " . $e->getMessage();
        }
        header("Location: manage_applications.php" . (isset($_GET['status']) ? "?status={$_GET['status']}" : ""));
        exit;

    } else {
        $_SESSION['error'] = "Invalid action or no applications selected.";
        header("Location: manage_applications.php" . (isset($_GET['status']) ? "?status={$_GET['status']}" : ""));
        exit;
    }
}

// Handle filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = [];
$params = [];
if ($status_filter !== 'all') {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filter;
}
if ($search_query) {
    $where_clauses[] = "(u.fullname LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch applications
$query_main = "SELECT a.id, a.user_id, a.contestant_type, a.status, a.current_step, a.submitted_at, a.created_at, a.last_updated, a.rejection_reason,
                 u.fullname, u.email,
                 COALESCE(nd.category, id.category) AS category,
                 COALESCE(nd.narration, id.narration) AS narration
          FROM applications a
          JOIN users u ON a.user_id = u.id
          LEFT JOIN application_details_nigerian nd ON a.id = nd.application_id
          LEFT JOIN application_details_international id ON a.id = id.application_id
          $where_sql
          ORDER BY a.submitted_at DESC, a.created_at DESC";
$stmt_main = $pdo->prepare($query_main); 
$stmt_main->execute($params);
$applications = $stmt_main->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Applications | JCDA Admin Portal</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
</head>
<body>
    <div class="wrapper">
        <?php include 'layouts/menu.php'; ?>
        
        <div class="content-page">
            <div class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box">
                                <div class="page-title-right">
                                    <a href="manage_applications.php" class="btn btn-success ms-2 flex-shrink-0">
                                        <i class="ri-refresh-line"></i> Refresh
                                    </a>
                                </div>
                                <h4 class="page-title">Manage Applications</h4>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['warning'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['warning']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['warning']); ?>
                    <?php endif; ?>
                     <?php if (isset($_SESSION['info'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['info']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['info']); ?>
                    <?php endif; ?>

                    <!-- Filter Form -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-4">
                                            <label for="status_filter" class="form-label">Filter by Status</label>
                                            <select name="status" id="status_filter" class="form-select">
                                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                                <option value="Not Started" <?php echo $status_filter === 'Not Started' ? 'selected' : ''; ?>>Not Started</option>
                                                <option value="Personal Info Complete" <?php echo $status_filter === 'Personal Info Complete' ? 'selected' : ''; ?>>Personal Info Complete</option>
                                                <option value="Sponsor Info Complete" <?php echo $status_filter === 'Sponsor Info Complete' ? 'selected' : ''; ?>>Sponsor Info Complete</option>
                                                <option value="Documents Uploaded" <?php echo $status_filter === 'Documents Uploaded' ? 'selected' : ''; ?>>Documents Uploaded</option>
                                                <option value="Information Requested" <?php echo $status_filter === 'Information Requested' ? 'selected' : ''; ?>>Information Requested</option>
                                                <option value="Submitted" <?php echo $status_filter === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                                <option value="Under Review" <?php echo $status_filter === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                                <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="search_query" class="form-label">Search Participant</label>
                                            <input type="text" name="search" id="search_query" class="form-control" placeholder="Name or Email" value="<?php echo htmlspecialchars($search_query); ?>">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="header-title">Applications</h4>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-success" onclick="submitBulkAction('bulk_approve')">Bulk Approve</button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#bulkRejectModal">Bulk Reject</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to send reminder emails to users with incomplete or pending applications?');">
                                            <input type="hidden" name="action" value="send_reminders">
                                            <button type="submit" class="btn btn-sm btn-warning ms-2">Send Reminders</button>
                                        </form>
                                        <a href="reports.php?export=applications" class="btn btn-sm btn-info ms-2">Export Data</a>
                                    </div>
                                </div>
                                <div class="card-body pt-0">
                                    <form method="POST" id="bulk-actions-form">
                                        <input type="hidden" name="action" id="bulk-action">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm table-centered mb-0">
                                                <thead>
                                                    <tr>
                                                        <th><input type="checkbox" id="select-all"></th>
                                                        <th>S/N</th>
                                                        <th>Applicant</th>
                                                        <th>Email</th>
                                                        <th>Type</th>
                                                        <th>Category</th>
                                                        <th>Narration</th>
                                                        <th>Submitted</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($applications)): ?>
                                                        <tr>
                                                            <td colspan="10" class="text-center">No applications found matching your criteria.</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($applications as $index => $app): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="checkbox" name="application_ids[]" value="<?php echo $app['id']; ?>" class="application-checkbox">
                                                            </td>
                                                            <td><?php echo $index + 1; ?></td>
                                                            <td><?php echo htmlspecialchars($app['fullname']); ?></td>
                                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                                            <td><?php echo ucfirst(htmlspecialchars($app['contestant_type'])); ?></td>
                                                            <td><?php echo htmlspecialchars($app['category'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($app['narration'] ?? 'N/A'); ?></td>
                                                            <td><?php echo $app['submitted_at'] ? date('M d, Y H:i', strtotime($app['submitted_at'])) : ($app['created_at'] ? date('M d, Y H:i', strtotime($app['created_at'])) . ' (Started)' : 'N/A'); ?></td>
                                                            <td>
                                                                <?php 
                                                                $status_lower = strtolower($app['status']);
                                                                if ($status_lower === 'approved') {
                                                                    echo '<span class="badge bg-success">Approved</span>';
                                                                } elseif ($status_lower === 'rejected') {
                                                                    echo '<span class="badge bg-danger">Rejected</span>';
                                                                } elseif ($status_lower === 'submitted' || $status_lower === 'under review') {
                                                                    echo '<span class="badge bg-warning">' . ucfirst(htmlspecialchars($app['status'])) . '</span>';
                                                                } elseif (in_array($status_lower, ['not started', 'personal info complete', 'sponsor info complete', 'documents uploaded', 'information requested'])) {
                                                                    echo '<span class="badge bg-info">' . ucfirst(htmlspecialchars($app['status'])) . '</span>';
                                                                } else {
                                                                    echo '<span class="badge bg-secondary">' . ucfirst(htmlspecialchars($app['status'])) . '</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-info mb-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $app['id']; ?>">Quick View</button>
                                                                <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary mb-1">Full Details</a>
                                                                <?php if ($app['status'] !== 'Approved'): ?>
                                                                    <form method="POST" style="display:inline;" class="action-form">
                                                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                                        <input type="hidden" name="action" value="approve">
                                                                        <button type="submit" class="btn btn-sm btn-success mb-1">Approve</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                <?php if ($app['status'] !== 'Rejected'): ?>
                                                                    <button type="button" class="btn btn-sm btn-danger mb-1" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $app['id']; ?>">Reject</button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>

                                                        <!-- Quick View Modal -->
                                                        <div class="modal fade" id="viewModal<?php echo $app['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $app['id']; ?>" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="viewModalLabel<?php echo $app['id']; ?>">Application Preview: <?php echo htmlspecialchars($app['fullname']); ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <h6>Application Information</h6>
                                                                        <p><strong>ID:</strong> <?php echo htmlspecialchars($app['id']); ?></p>
                                                                        <p><strong>Contestant Type:</strong> <?php echo ucfirst(htmlspecialchars($app['contestant_type'])); ?></p>
                                                                        <p><strong>Category:</strong> <?php echo htmlspecialchars($app['category'] ?? 'N/A'); ?></p>
                                                                        <p><strong>Narration:</strong> <?php echo htmlspecialchars($app['narration'] ?? 'N/A'); ?></p>
                                                                        <p><strong>Status:</strong> <?php echo htmlspecialchars($app['status']); ?></p>
                                                                        <p><strong>Submitted At:</strong> <?php echo $app['submitted_at'] ? date('M d, Y H:i', strtotime($app['submitted_at'])) : 'Not Yet Submitted'; ?></p>
                                                                        <p><strong>Last Updated:</strong> <?php echo $app['last_updated'] ? date('M d, Y H:i', strtotime($app['last_updated'])) : 'N/A'; ?></p>

                                                                        <h6>Applicant Information</h6>
                                                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($app['fullname']); ?></p>
                                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></p>

                                                                        <?php if ($app['status'] === 'Rejected' && !empty($app['rejection_reason'])): ?>
                                                                            <h6>Rejection Reason</h6>
                                                                            <p><?php echo nl2br(htmlspecialchars($app['rejection_reason'])); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn btn-primary">View Full Details</a>
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Individual Reject Modal -->
                                                        <?php if ($app['status'] !== 'Rejected'): ?>
                                                        <div class="modal fade" id="rejectModal<?php echo $app['id']; ?>" tabindex="-1" aria-labelledby="rejectModalLabel<?php echo $app['id']; ?>" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="rejectModalLabel<?php echo $app['id']; ?>">Reject Application for <?php echo htmlspecialchars($app['fullname']); ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <form method="POST">
                                                                        <div class="modal-body">
                                                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                                            <input type="hidden" name="action" value="reject">
                                                                            <div class="mb-3">
                                                                                <label for="rejection_reason_<?php echo $app['id']; ?>" class="form-label">Reason for Rejection (Optional)</label>
                                                                                <textarea class="form-control" id="rejection_reason_<?php echo $app['id']; ?>" name="rejection_reason" rows="4" placeholder="Enter reason for rejection"></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </form>
                                    <!-- Bulk Reject Modal -->
                                    <div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-labelledby="bulkRejectModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="bulkRejectModalLabel">Bulk Reject Applications</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" id="bulk-reject-form-modal"> 
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="bulk_reject">
                                                        <div class="mb-3">
                                                            <label for="bulk_rejection_reason" class="form-label">Reason for Rejection (Optional)</label>
                                                            <textarea class="form-control" id="bulk_rejection_reason" name="rejection_reason" rows="4" placeholder="Enter reason for rejection. This will apply to all selected applications."></textarea>
                                                        </div>
                                                        <p><small>Note: This action will apply to all selected applications.</small></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Confirm Bulk Rejection</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include 'layouts/footer.php'; ?>
        </div>
    </div>
    
    <?php include 'layouts/right-sidebar.php'; ?>
    <?php include 'layouts/footer-scripts.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    document.querySelectorAll('.application-checkbox').forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            window.submitBulkAction = function(action) {
                const bulkActionsForm = document.getElementById('bulk-actions-form');
                const checkedCheckboxes = bulkActionsForm.querySelectorAll('.application-checkbox:checked');
                if (checkedCheckboxes.length === 0) {
                    alert('Please select at least one application.');
                    return;
                }
                document.getElementById('bulk-action').value = action; 
                bulkActionsForm.submit();
            }

            const bulkRejectModal = document.getElementById('bulkRejectModal');
            if (bulkRejectModal) {
                const bulkRejectFormModal = document.getElementById('bulk-reject-form-modal');
                
                bulkRejectModal.addEventListener('show.bs.modal', function () {
                    const existingInputs = bulkRejectFormModal.querySelectorAll('input[name="application_ids[]"]');
                    existingInputs.forEach(input => input.remove());

                    const checkedIds = Array.from(document.querySelectorAll('#bulk-actions-form .application-checkbox:checked')).map(cb => cb.value);
                    
                    if (checkedIds.length === 0) {
                        alert('Please select at least one application to reject.');
                        var modal = bootstrap.Modal.getInstance(bulkRejectModal);
                        modal.hide();
                        return false; 
                    }
                    checkedIds.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'application_ids[]';
                        input.value = id;
                        bulkRejectFormModal.appendChild(input);
                    });
                });

                if (bulkRejectFormModal) {
                    bulkRejectFormModal.addEventListener('submit', function(e) {
                        const checkedCheckboxesInModalForm = this.querySelectorAll('input[name="application_ids[]"]');
                        if (checkedCheckboxesInModalForm.length === 0) {
                            e.preventDefault();
                            alert('No applications selected for bulk rejection. Please select applications from the table.');
                            var modal = bootstrap.Modal.getInstance(bulkRejectModal);
                            modal.hide();
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>