<?php
require_once 'includes/db.php'; // Ensure this path is correct

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['status']) ? $_GET['status'] : ''; // 'activate' or 'suspend'

if ($user_id === 0 || !in_array($action, ['activate', 'suspend'])) {
    $_SESSION['error_message'] = "Invalid action or user ID.";
    header("Location: users.php");
    exit;
}

// Optional: Add a check to prevent suspending the currently logged-in admin
// if ($user_id === $_SESSION['admin_id'] && $action === 'suspend') {
//     $_SESSION['error_message'] = "You cannot suspend your own account.";
//     header("Location: user-view.php?id=" . $user_id);
//     exit;
// }

$new_status = ($action === 'activate') ? 'active' : 'suspended';

try {
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = "User status updated to " . htmlspecialchars($new_status) . ".";
    } else {
        $_SESSION['error_message'] = "User not found or status already " . htmlspecialchars($new_status) . ".";
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    // Log the error: error_log("User status update failed for ID $user_id: " . $e->getMessage());
}

header("Location: user-view.php?id=" . $user_id);
exit;
?>