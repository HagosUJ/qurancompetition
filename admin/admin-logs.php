<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/admin/admin-logs.php
require_once __DIR__ . '/../portal/includes/auth.php'; // Adjust path if your auth.php is elsewhere

// --- Authentication & Session Management ---
if (!is_logged_in()) {
    redirect('login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_role = $_SESSION['user_role'] ?? 'participant';

if ($current_user_role !== 'admin') {
    $_SESSION['error'] = 'Unauthorized access to admin logs.';
    redirect('index.php'); // Or wherever non-admins should go
    exit;
}

// --- Database Object Check ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $fetch_error = "Database connection error. Cannot display logs.";
    $admin_logs = [];
    $total_logs = 0;
    $total_pages = 0;
    // Fall through to display error in HTML
} else {
    // --- Fetch Admin Logs (with Filters and Pagination) ---
    $fetch_error = '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 20; // Logs per page
    $offset = ($page - 1) * $per_page;

    // Filters
    $filter_admin_id = (int)($_GET['filter_admin_id'] ?? 0);
    $filter_action = trim($_GET['filter_action'] ?? '');
    $filter_target_type = trim($_GET['filter_target_type'] ?? '');
    $filter_date_from = trim($_GET['filter_date_from'] ?? '');
    $filter_date_to = trim($_GET['filter_date_to'] ?? '');

    $base_sql = "FROM admin_logs WHERE 1=1";
    $params = [];

    if ($filter_admin_id > 0) {
        $base_sql .= " AND admin_user_id = :filter_admin_id";
        $params[':filter_admin_id'] = $filter_admin_id;
    }
    if (!empty($filter_action)) {
        $base_sql .= " AND action LIKE :filter_action";
        $params[':filter_action'] = "%" . $filter_action . "%";
    }
    if (!empty($filter_target_type)) {
        $base_sql .= " AND target_type = :filter_target_type";
        $params[':filter_target_type'] = $filter_target_type;
    }
    if (!empty($filter_date_from)) {
        $base_sql .= " AND DATE(created_at) >= :filter_date_from";
        $params[':filter_date_from'] = $filter_date_from;
    }
    if (!empty($filter_date_to)) {
        $base_sql .= " AND DATE(created_at) <= :filter_date_to";
        $params[':filter_date_to'] = $filter_date_to;
    }

    try {
        $count_sql = "SELECT COUNT(*) AS total " . $base_sql;
        $stmt_count = $pdo->prepare($count_sql);
        $stmt_count->execute($params);
        $total_logs = (int)$stmt_count->fetchColumn();
        $total_pages = ceil($total_logs / $per_page);

        $data_sql = "SELECT id, admin_username, action, target_type, target_id, details, ip_address, created_at "
                  . $base_sql
                  . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt_data = $pdo->prepare($data_sql);

        // Bind filter parameters
        foreach ($params as $key => $value) {
            $stmt_data->bindValue($key, $value); // PDO determines type automatically for most cases here
        }
        // Bind LIMIT and OFFSET as integers
        $stmt_data->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt_data->execute();
        $admin_logs = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $fetch_error = "Error fetching admin logs: " . $e->getMessage();
        error_log($fetch_error);
        $admin_logs = [];
        $total_logs = 0;
        $total_pages = 0;
    }

    // Fetch admin users for filter dropdown
    $admin_users_for_filter = [];
    try {
        $stmt_admins = $pdo->query("SELECT id, fullname FROM users WHERE role = 'admin' ORDER BY fullname");
        $admin_users_for_filter = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin users for log filter: " . $e->getMessage());
    }
}

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
// ... (other headers as in your other admin files)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Logs | Musabaqa Admin</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        .details-column { max-width: 300px; overflow-wrap: break-word; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'layouts/menu.php'; // Or left-sidebar.php ?>
        <div class="content-page">
            <div class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box">
                                <h4 class="page-title">Admin Activity Logs</h4>
                            </div>
                        </div>
                    </div>

                    <?php if ($fetch_error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($fetch_error); ?></div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Filter Logs</h5>
                                    <form method="GET" class="row g-3 align-items-end">
                                        <div class="col-md-3">
                                            <label for="filter_admin_id" class="form-label">Admin User</label>
                                            <select class="form-select" id="filter_admin_id" name="filter_admin_id">
                                                <option value="0">All Admins</option>
                                                <?php foreach ($admin_users_for_filter as $admin_user): ?>
                                                    <option value="<?php echo $admin_user['id']; ?>" <?php echo $filter_admin_id == $admin_user['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($admin_user['fullname']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="filter_action" class="form-label">Action</label>
                                            <input type="text" class="form-control" id="filter_action" name="filter_action" value="<?php echo htmlspecialchars($filter_action); ?>" placeholder="e.g., login, update">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="filter_target_type" class="form-label">Target Type</label>
                                            <input type="text" class="form-control" id="filter_target_type" name="filter_target_type" value="<?php echo htmlspecialchars($filter_target_type); ?>" placeholder="e.g., user, application">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="filter_date_from" class="form-label">Date From</label>
                                            <input type="date" class="form-control" id="filter_date_from" name="filter_date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="filter_date_to" class="form-label">Date To</label>
                                            <input type="date" class="form-control" id="filter_date_to" name="filter_date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <?php if (empty($admin_logs) && !$fetch_error): ?>
                                        <div class="text-center py-3">
                                            <p class="text-muted">No admin logs found matching your criteria.</p>
                                        </div>
                                    <?php elseif (!empty($admin_logs)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Timestamp</th>
                                                        <th>Admin</th>
                                                        <th>Action</th>
                                                        <th>Target</th>
                                                        <th class="details-column">Details</th>
                                                        <th>IP Address</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($admin_logs as $log): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars(date('M j, Y H:i:s', strtotime($log['created_at']))); ?></td>
                                                            <td><?php echo htmlspecialchars($log['admin_username'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                            <td>
                                                                <?php if ($log['target_type']): ?>
                                                                    <?php echo htmlspecialchars(ucfirst($log['target_type'])); ?>
                                                                    <?php if ($log['target_id']): ?>
                                                                        (ID: <?php echo htmlspecialchars($log['target_id']); ?>)
                                                                    <?php endif; ?>
                                                                <?php else: echo 'N/A'; endif; ?>
                                                            </td>
                                                            <td class="details-column"><?php echo nl2br(htmlspecialchars($log['details'] ?? '')); ?></td>
                                                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <?php if ($total_pages > 1): ?>
                                            <nav class="mt-3">
                                                <ul class="pagination justify-content-center">
                                                    <?php
                                                    $query_params = http_build_query([
                                                        'filter_admin_id' => $filter_admin_id,
                                                        'filter_action' => $filter_action,
                                                        'filter_target_type' => $filter_target_type,
                                                        'filter_date_from' => $filter_date_from,
                                                        'filter_date_to' => $filter_date_to,
                                                    ]);
                                                    ?>
                                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_params; ?>">Previous</a>
                                                    </li>
                                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $query_params; ?>"><?php echo $i; ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_params; ?>">Next</a>
                                                    </li>
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