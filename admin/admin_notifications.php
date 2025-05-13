<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/admin/admin_notifications.php
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);
session_start();

try {
    if (!file_exists('includes/db.php')) {
        throw new Exception("Database file not found at 'includes/db.php'");
    }
    require_once 'includes/db.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection not established.");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_fullname = $_SESSION['user_fullname'] ?? 'Admin';
$user_role = $_SESSION['user_role'] ?? 'admin';

if (!$user_id || $user_role !== 'admin') {
    $_SESSION['error'] = 'Unauthorized access.';
    header("Location: index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$action_message = '';
$action_success = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $action_message = 'Invalid CSRF token.';
        $action_success = false;
    } else {
        try {
            if ($_POST['action'] === 'create_notification') {
                $recipient = trim($_POST['recipient'] ?? '');
                $message = trim($_POST['message'] ?? '');

                if (empty($message)) {
                    $action_message = 'Message is required.';
                    $action_success = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'admin_message', NOW())");
                    if ($recipient === 'all') {
                        $user_query = "SELECT id FROM users WHERE role = 'user'";
                        $user_result = $pdo->query($user_query);
                        foreach ($user_result as $user) {
                            $stmt->execute([$user['id'], $message]);
                        }
                        $action_message = 'Notifications sent to all users.';
                    } else {
                        $stmt->execute([(int)$recipient, $message]);
                        $action_message = 'Notification sent successfully.';
                    }
                }
            } elseif ($_POST['action'] === 'delete_notification') {
                $notification_id = (int)($_POST['notification_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                $stmt->execute([$notification_id]);
                $action_message = 'Notification deleted successfully.';
            }
        } catch (PDOException $e) {
            $action_message = 'Database error: ' . $e->getMessage();
            $action_success = false;
            error_log("Database error: " . $e->getMessage());
        }
    }
}

$notifications = [];
$fetch_error = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$filter_user_id = (int)($_GET['filter_user_id'] ?? 0);
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_date = $_GET['filter_date'] ?? '';

$sql = "SELECT n.id, n.user_id, n.message, n.type, n.created_at, u.fullname AS user_name
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        WHERE 1=1";
$params = [];
if ($filter_user_id > 0) {
    $sql .= " AND n.user_id = ?";
    $params[] = $filter_user_id;
}
if ($filter_type !== 'all') {
    $sql .= " AND n.type = ?";
    $params[] = $filter_type;
}
if ($filter_date) {
    $sql .= " AND DATE(n.created_at) = ?";
    $params[] = $filter_date;
}
$sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";

try {
    $count_sql = str_replace("SELECT n.id, n.user_id, n.message, n.type, n.created_at, u.fullname AS user_name", "SELECT COUNT(*) AS total", $sql);
    $count_sql = preg_replace("/LIMIT \? OFFSET \?/", "", $count_sql);
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(array_slice($params, 0, -2));
    $total_notifications = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_notifications / $per_page);

    $stmt = $pdo->prepare($sql);
    $param_index = 1;
    if ($filter_user_id > 0) {
        $stmt->bindValue($param_index++, $filter_user_id, PDO::PARAM_INT);
    }
    if ($filter_type !== 'all') {
        $stmt->bindValue($param_index++, $filter_type, PDO::PARAM_STR);
    }
    if ($filter_date) {
        $stmt->bindValue($param_index++, $filter_date, PDO::PARAM_STR);
    }
    $stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $fetch_error = "Error fetching notifications: " . $e->getMessage();
    error_log($fetch_error);
}

$users = $pdo->query("SELECT id, fullname FROM users WHERE role = 'user' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Notifications | JCDA Admin Portal</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .notification-item { border-bottom: 1px solid #dee2e6; padding: 1rem 0; }
        .notification-item:last-child { border-bottom: none; }
        .notification-message { color: #495057; margin-bottom: 0.25rem; }
        .notification-time { font-size: 0.8em; color: #6c757d; }
        .action-buttons form { display: inline; }
    </style>
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
                                <h4 class="page-title">Admin Notifications</h4>
                            </div>
                        </div>
                    </div>
                    <?php if ($action_message): ?>
                        <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>">
                            <?php echo htmlspecialchars($action_message); ?>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Send New Notification</h5>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="create_notification">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="mb-3">
                                            <label for="recipient" class="form-label">Recipient</label>
                                            <select class="form-select" id="recipient" name="recipient" required>
                                                <option value="all">All Users</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['fullname']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message</label>
                                            <textarea class="form-control" id="message" name="message" rows="4" required maxlength="1000"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Send Notification</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Filter Notifications</h5>
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-4">
                                            <label for="filter_user_id" class="form-label">User</label>
                                            <select class="form-select" id="filter_user_id" name="filter_user_id">
                                                <option value="0">All Users</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user_id == $user['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($user['fullname']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="filter_type" class="form-label">Type</label>
                                            <select class="form-select" id="filter_type" name="filter_type">
                                                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All</option>
                                                <option value="application_status" <?php echo $filter_type === 'application_status' ? 'selected' : ''; ?>>Application Status</option>
                                                <option value="application_reminder" <?php echo $filter_type === 'application_reminder' ? 'selected' : ''; ?>>Application Reminder</option>
                                                <option value="admin_message" <?php echo $filter_type === 'admin_message' ? 'selected' : ''; ?>>Admin Message</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="filter_date" class="form-label">Date</label>
                                            <input type="date" class="form-control" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                                        </div>
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-secondary mt-2">Apply Filters</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Notifications</h5>
                                    <?php if ($fetch_error): ?>
                                        <div class="alert alert-danger"><?php echo htmlspecialchars($fetch_error); ?></div>
                                    <?php elseif (empty($notifications)): ?>
                                        <div class="text-center py-4">No notifications found.</div>
                                    <?php else: ?>
                                        <div class="notification-list">
                                            <?php foreach ($notifications as $notification): ?>
                                                <div class="notification-item">
                                                    <div class="notification-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></div>
                                                    <div class="notification-time">
                                                        <?php echo date('M j, Y \a\t g:i A', strtotime($notification['created_at'])); ?>
                                                        | To: <?php echo htmlspecialchars($notification['user_name']); ?>
                                                        | Type: <?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?>
                                                    </div>
                                                    <div class="action-buttons mt-2">
                                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                                            <input type="hidden" name="action" value="delete_notification">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <button type="submit" class="btn btn-link text-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if ($total_pages > 1): ?>
                                            <nav class="mt-3">
                                                <ul class="pagination">
                                                    <?php if ($page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter_user_id=<?php echo $filter_user_id; ?>&filter_type=<?php echo $filter_type; ?>&filter_date=<?php echo $filter_date; ?>">Previous</a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $i; ?>&filter_user_id=<?php echo $filter_user_id; ?>&filter_type=<?php echo $filter_type; ?>&filter_date=<?php echo $filter_date; ?>"><?php echo $i; ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    <?php if ($page < $total_pages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter_user_id=<?php echo $filter_user_id; ?>&filter_type=<?php echo $filter_type; ?>&filter_date=<?php echo $filter_date; ?>">Next</a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                        <?php endif; ?>
                                    <?php endif; ?>
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
</body>
</html>