<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/db_setup.php
require_once 'includes/config.php'; // Ensure DB constants are defined here

// --- Database Connection ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    // --- Create Database ---
    $dbName = DB_NAME;
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === FALSE) {
        throw new Exception("Error creating database '$dbName': " . $conn->error);
    }
    echo "Database '$dbName' checked/created successfully.<br>";

    // --- Select Database ---
    $conn->select_db($dbName);
    echo "Database '$dbName' selected.<br>";

    // --- Drop Existing Tables (Optional - Uncomment ONLY if you want to delete ALL data) ---
    /*
    echo "Dropping existing tables (if they exist)...<br>";
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("DROP TABLE IF EXISTS `login_attempts`, `remember_tokens`, `users`");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "Existing tables dropped.<br>";
    */

    // --- Create users Table (if it doesn't exist) ---
    echo "Checking/Creating 'users' table structure...<br>";
    $sql_users_create = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `fullname` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Password hash (nullable for flexibility)',
        `role` VARCHAR(50) NOT NULL DEFAULT 'user' COMMENT 'User role (e.g., user, admin, judge)',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_users_create) === FALSE) {
        throw new Exception("Error creating 'users' table base: " . $conn->error);
    }
    echo "'users' table base structure checked/created.<br>";

    // --- Add/Modify Columns in 'users' Table (using ALTER TABLE) ---
    echo "Ensuring all required columns exist in 'users' table...<br>";

    // Function to check if a column exists
    function columnExists($conn, $tableName, $columnName) {
        $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
        return $result && $result->num_rows > 0;
    }

    $tableName = 'users';

    // Add status
    if (!columnExists($conn, $tableName, 'status')) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'User status: pending, active, suspended' AFTER `role`");
        echo "- Added 'status' column.<br>";
    } else { // Ensure it's VARCHAR if it exists but might be ENUM
        $conn->query("ALTER TABLE `$tableName` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'User status: pending, active, suspended'");
         echo "- Checked/Modified 'status' column.<br>";
    }

    // Add activation_hash
    if (!columnExists($conn, $tableName, 'activation_hash')) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `activation_hash` VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA256 hash of activation token' AFTER `status`");
        echo "- Added 'activation_hash' column.<br>";
    }

    // Add activation_expiry
    if (!columnExists($conn, $tableName, 'activation_expiry')) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `activation_expiry` DATETIME NULL DEFAULT NULL COMMENT 'Expiry time for activation token' AFTER `activation_hash`");
        echo "- Added 'activation_expiry' column.<br>";
    }

    // Add reset_token_hash
    if (!columnExists($conn, $tableName, 'reset_token_hash')) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `reset_token_hash` VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA256 hash of password reset token' AFTER `activation_expiry`");
        echo "- Added 'reset_token_hash' column.<br>";
    }

    // Add reset_token_expiry
    if (!columnExists($conn, $tableName, 'reset_token_expiry')) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `reset_token_expiry` DATETIME NULL DEFAULT NULL COMMENT 'Expiry time for reset token' AFTER `reset_token_hash`");
        echo "- Added 'reset_token_expiry' column.<br>";
    }

    // Add last_login
    if (!columnExists($conn, $tableName, 'last_login')) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `last_login` DATETIME NULL DEFAULT NULL COMMENT 'Timestamp of last successful login' AFTER `reset_token_expiry`");
        echo "- Added 'last_login' column.<br>";
    }

    // Add profile_image
    if (!columnExists($conn, $tableName, 'profile_image')) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `profile_image` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path to profile image' AFTER `last_login`");
        echo "- Added 'profile_image' column.<br>";
    }

     // Ensure password column is nullable
    $conn->query("ALTER TABLE `$tableName` MODIFY COLUMN `password` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Password hash (nullable for flexibility)'");
    echo "- Ensured 'password' column is nullable.<br>";

    // Ensure role column is VARCHAR
    $conn->query("ALTER TABLE `$tableName` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'user' COMMENT 'User role (e.g., user, admin, judge)'");
    echo "- Ensured 'role' column is VARCHAR.<br>";

    // Ensure updated_at exists
    if (!columnExists($conn, $tableName, 'updated_at')) {
         $conn->query("ALTER TABLE `$tableName` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
         echo "- Added 'updated_at' column.<br>";
    } else {
         $conn->query("ALTER TABLE `$tableName` MODIFY COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
         echo "- Ensured 'updated_at' column updates on change.<br>";
    }


    echo "'users' table columns checked/added.<br>";


    // --- Create remember_tokens Table ---
    echo "Creating 'remember_tokens' table...<br>";
    $sql_remember = "CREATE TABLE IF NOT EXISTS `remember_tokens` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `selector` VARCHAR(32) NOT NULL COMMENT 'Unique selector for cookie lookup',
      `hashed_validator` VARCHAR(64) NOT NULL COMMENT 'SHA256 hash of the validator token',
      `expires` DATETIME NOT NULL COMMENT 'Expiry timestamp for the token',
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
      UNIQUE KEY `idx_selector` (`selector`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_remember) === FALSE) {
        throw new Exception("Error creating 'remember_tokens' table: " . $conn->error);
    }
    echo "'remember_tokens' table created successfully.<br>";

    // --- Create login_attempts Table ---
    echo "Creating 'login_attempts' table...<br>";
    $sql_attempts = "CREATE TABLE IF NOT EXISTS `login_attempts` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP address of the user',
      `email_identifier` VARCHAR(255) NOT NULL COMMENT 'Email used for login attempt',
      `attempts_count` INT NOT NULL DEFAULT 1 COMMENT 'Number of failed attempts',
      `attempt_time` DATETIME NOT NULL COMMENT 'Timestamp of the last attempt',
      `lockout_until` DATETIME NULL DEFAULT NULL COMMENT 'Timestamp until lockout expires',
      UNIQUE KEY `idx_ip_email` (`ip_address`, `email_identifier`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_attempts) === FALSE) {
        throw new Exception("Error creating 'login_attempts' table: " . $conn->error);
    }
    echo "'login_attempts' table created successfully.<br>";

    echo "<hr><strong>Database setup completed successfully!</strong>";

} catch (Exception $e) {
    die("Database setup failed: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>