<?php
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

// Check if admin is logged in
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
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
            ? [$_POST['application_id']] 
            : [];
    }

    if (!empty($application_ids) && in_array($action, ['approve', 'reject', 'bulk_approve', 'bulk_reject'])) {
        $new_status = (in_array($action, ['approve', 'bulk_approve'])) ? 'Approved' : 'Rejected';
        try {
            $placeholders = implode(',', array_fill(0, count($application_ids), '?'));
            $update_query = "UPDATE applications SET status = ?, rejection_reason = ?, last_updated = NOW() WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute(array_merge([$new_status, $new_status === 'Rejected' ? $rejection_reason : null], $application_ids));

            $query = "SELECT u.id AS user_id, u.fullname, u.email, a.contestant_type 
                     FROM applications a 
                     JOIN users u ON a.user_id = u.id 
                     WHERE a.id IN ($placeholders)";
            $stmt = $pdo->prepare($query);
            $stmt->execute($application_ids);
            $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $email_errors = [];
            foreach ($applicants as $applicant) {
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.example.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'your_email@example.com';
                    $mail->Password = 'your_password';
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('no-reply@majlisahlilquran.org', 'Majlis Ahlil Quran Musabaqa Team');
                    $mail->addAddress($applicant['email'], $applicant['fullname']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Application Status Update';
                    $body = "<h3>Dear {$applicant['fullname']},</h3>
                             <p>Your application for the " . ($applicant['contestant_type'] === 'nigerian' ? 'Nigerian' : 'International') . " competition has been <strong>" . ucfirst($new_status) . "</strong>.</p>";
                    if ($new_status === 'Rejected' && $rejection_reason) {
                        $body .= "<p><strong>Reason for Rejection:</strong> " . htmlspecialchars($rejection_reason) . "</p>";
                    }
                    $body .= "<p>Please check your dashboard for further details.</p>
                              <p>Best regards,<br>Musabaqa Team</p>";
                    $mail->Body = $body;

                    $mail->send();

                    $notification_message = "Your application has been $new_status.";
                    if ($new_status === 'Rejected' && $rejection_reason) {
                        $notification_message .= " Reason: $rejection_reason";
                    }
                    $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) 
                                        VALUES (:user_id, :message, 'application_status', NOW())";
                    $stmt = $pdo->prepare($notification_query);
                    $stmt->execute([
                        'user_id' => $applicant['user_id'],
                        'message' => $notification_message
                    ]);

                    $mail->clearAddresses();
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    $email_errors[] = "Failed to send email to {$applicant['email']}: " . $mail->ErrorInfo;
                }
            }

            if (empty($email_errors)) {
                $_SESSION['success'] = count($application_ids) . " application(s) $new_status successfully.";
            } else {
                $_SESSION['error'] = "Application(s) updated but some emails failed: " . implode(', ', $email_errors);
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating application(s): " . $e->getMessage();
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
$query = "SELECT a.id, a.user_id, a.contestant_type, a.status, a.current_step, a.submitted_at, a.created_at, a.last_updated, a.rejection_reason,
                 u.fullname, u.email,
                 COALESCE(nd.category, id.category) AS category,
                 COALESCE(nd.narration, id.narration) AS narration
          FROM applications a
          JOIN users u ON a.user_id = u.id
          LEFT JOIN application_details_nigerian nd ON a.id = nd.application_id
          LEFT JOIN application_details_international id ON a.id = id.application_id
          $where_sql
          ORDER BY a.submitted_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                                <option value="Submitted" <?php echo $status_filter === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
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
                                                    <?php foreach ($applications as $index => $app): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" name="application_ids[]" value="<?php echo $app['id']; ?>" class="application-checkbox">
                                                        </td>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($app['fullname']); ?></td>
                                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                                        <td><?php echo ucfirst($app['contestant_type']); ?></td>
                                                        <td><?php echo htmlspecialchars($app['category'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($app['narration'] ?? 'N/A'); ?></td>
                                                        <td><?php echo $app['submitted_at'] ? date('M d, Y', strtotime($app['submitted_at'])) : 'N/A'; ?></td>
                                                        <td>
                                                            <?php 
                                                            switch (strtolower($app['status'])) {
                                                                case 'approved':
                                                                    echo '<span class="badge bg-success">Approved</span>';
                                                                    break;
                                                                case 'rejected':
                                                                    echo '<span class="badge bg-danger">Rejected</span>';
                                                                    break;
                                                                case 'submitted':
                                                                    echo '<span class="badge bg-warning">Submitted</span>';
                                                                    break;
                                                                case 'not started':
                                                                    echo '<span class="badge bg-info">Not Started</span>';
                                                                    break;
                                                                default:
                                                                    echo '<span class="badge bg-secondary">' . ucfirst(htmlspecialchars($app['status'])) . '</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $app['id']; ?>">Quick View</button>
                                                            <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">Full Details</a>
                                                            <?php if ($app['status'] !== 'Approved'): ?>
                                                                <form method="POST" style="display:inline;" class="action-form">
                                                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                                    <input type="hidden" name="action" value="approve">
                                                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php if ($app['status'] !== 'Rejected'): ?>
                                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $app['id']; ?>">Reject</button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>

                                                    <!-- Quick View Modal -->
                                                    <div class="modal fade" id="viewModal<?php echo $app['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $app['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="viewModalLabel<?php echo $app['id']; ?>">Application Preview</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <h6>Application Information</h6>
                                                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($app['id']); ?></p>
                                                                    <p><strong>Contestant Type:</strong> <?php echo ucfirst($app['contestant_type']); ?></p>
                                                                    <p><strong>Category:</strong> <?php echo htmlspecialchars($app['category'] ?? 'N/A'); ?></p>
                                                                    <p><strong>Narration:</strong> <?php echo htmlspecialchars($app['narration'] ?? 'N/A'); ?></p>
                                                                    <p><strong>Status:</strong> <?php echo htmlspecialchars($app['status']); ?></p>
                                                                    <p><strong>Submitted At:</strong> <?php echo $app['submitted_at'] ? date('M d, Y H:i', strtotime($app['submitted_at'])) : 'N/A'; ?></p>

                                                                    <h6>Applicant Information</h6>
                                                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($app['fullname']); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></p>

                                                                    <?php if ($app['status'] === 'Rejected' && $app['rejection_reason']): ?>
                                                                        <h6>Rejection Reason</h6>
                                                                        <p><?php echo htmlspecialchars($app['rejection_reason']); ?></p>
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
                                                                    <h5 class="modal-title" id="rejectModalLabel<?php echo $app['id']; ?>">Reject Application</h5>
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
                                                <form method="POST" id="bulk-reject-form">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="bulk_reject">
                                                        <div class="mb-3">
                                                            <label for="bulk_rejection_reason" class="form-label">Reason for Rejection (Optional)</label>
                                                            <textarea class="form-control" id="bulk_rejection_reason" name="rejection_reason" rows="4" placeholder="Enter reason for rejection"></textarea>
                                                        </div>
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
    <script src="assets/js/app.min.js"></script>
    <script>
        // Select all checkboxes
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.application-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkRejectForm();
        });

        // Update bulk reject form hidden inputs
        function updateBulkRejectForm() {
            const checkedIds = Array.from(document.querySelectorAll('.application-checkbox:checked')).map(cb => cb.value);
            const bulkForm = document.getElementById('bulk-reject-form');
            const existingInputs = bulkForm.querySelectorAll('.bulk-application-id');
            existingInputs.forEach(input => input.remove());
            checkedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'application_ids[]';
                input.value = id;
                input.className = 'bulk-application-id';
                bulkForm.appendChild(input);
            });
        }

        // Update bulk form on checkbox change
        document.querySelectorAll('.application-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkRejectForm);
        });

        // Submit bulk action
        function submitBulkAction(action) {
            if (document.querySelectorAll('.application-checkbox:checked').length === 0) {
                alert('Please select at least one application.');
                return;
            }
            document.getElementById('bulk-action').value = action;
            document.getElementById('bulk-actions-form').submit();
        }

        // Validate bulk reject modal submission
        document.getElementById('bulk-reject-form').addEventListener('submit', function(e) {
            if (document.querySelectorAll('.application-checkbox:checked').length === 0) {
                e.preventDefault();
                alert('Please select at least one application.');
            }
        });
    </script>
</body>
</html>