<?php
// Save as test_db.php
require_once('includes/db.php');

try {
    // Test query
    $stmt = $pdo->query("SELECT 1");
    echo "Database connection successful!";
    
    // Check if tables exist
    $tables = ['admins', 'admin_logs'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        echo "<br>Table '$table': " . ($result->rowCount() > 0 ? "Exists" : "Missing");
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>