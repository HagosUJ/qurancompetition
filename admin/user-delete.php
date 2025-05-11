<?php
require_once 'includes/db.php'; // Ensure this path is correct

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: users.php");
    exit;
}

// Optional: Add a check to prevent deleting the currently logged-in admin or super admin
// if ($user_id === $_SESSION['admin_id']) {
//     $_SESSION['error_message'] = "You cannot delete your own account.";
//     header("Location: user-view.php?id=" . $user_id);
//     exit;
// }

try {
    // Begin transaction
    $pdo->beginTransaction();

    // It's good practice to delete related records or handle them appropriately.
    // For example, delete from applications, login_attempts, remember_tokens, etc.
    // This depends on your database schema and foreign key constraints (ON DELETE CASCADE etc.)

    // Example: Delete related applications (if you have an applications table with user_id)
    // $stmt_app = $pdo->prepare("DELETE FROM applications WHERE user_id = ?");
    // $stmt_app->execute([$user_id]);

    // Example: Delete login attempts
    // $stmt_logins = $pdo->prepare("DELETE FROM login_attempts WHERE email_identifier = (SELECT email FROM users WHERE id = ?)");
    // $stmt_logins->execute([$user_id]);
    
    // Example: Delete remember tokens
    // $stmt_tokens = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    // $stmt_tokens->execute([$user_id]);

    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        $_SESSION['success_message'] = "User deleted successfully.";
    } else {
        $pdo->rollBack();
        $_SESSION['error_message'] = "User not found or could not be deleted.";
    }
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    // Log the error: error_log("User deletion failed for ID $user_id: " . $e->getMessage());
}

header("Location: users.php");
exit;
?>