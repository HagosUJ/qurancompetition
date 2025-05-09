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
    error_log("Database connection error: " . $e->getMessage());
    die("Internal server error. Please contact the administrator.");
}

// Check if admin is logged in
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Validate inputs
$application_id = isset($_GET['application_id']) ? filter_var($_GET['application_id'], FILTER_VALIDATE_INT) : 0;
$file_path = isset($_GET['file']) ? trim(urldecode($_GET['file'])) : '';

if ($application_id <= 0 || empty($file_path)) {
    error_log("Invalid request: application_id=$application_id, file_path=$file_path");
    die("Invalid request.");
}

// Define the base path for uploads
$base_upload_path = '/Applications/XAMPP/xamppfiles/htdocs/musabaqa/portal/uploads/';

// Construct the full file path
// Handle both 'uploads/filename.pdf' and 'portal/uploads/filename.pdf' cases
$relative_path = preg_replace('~^(portal/)?uploads/~', '', $file_path);
$full_path = $base_upload_path . $relative_path;

// Verify file belongs to application
$valid_file = false;
$query_nigerian = "SELECT file_path FROM application_documents WHERE application_id = ? AND file_path = ?";
$query_international = "SELECT passport_scan_path, birth_certificate_path FROM application_documents_international WHERE application_id = ?";
$nominator_query = "SELECT nomination_letter_path FROM application_nominators_international WHERE application_id = ?";

$stmt = $pdo->prepare($query_nigerian);
$stmt->execute([$application_id, $file_path]);
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    $valid_file = true;
}

$stmt = $pdo->prepare($query_international);
$stmt->execute([$application_id]);
$intl_doc = $stmt->fetch(PDO::FETCH_ASSOC);
if ($intl_doc && ($intl_doc['passport_scan_path'] === $file_path || $intl_doc['birth_certificate_path'] === $file_path)) {
    $valid_file = true;
}

$stmt = $pdo->prepare($nominator_query);
$stmt->execute([$application_id]);
$nominator = $stmt->fetch(PDO::FETCH_ASSOC);
if ($nominator && $nominator['nomination_letter_path'] === $file_path) {
    $valid_file = true;
}

if (!$valid_file) {
    error_log("File not found or access denied: application_id=$application_id, file_path=$file_path");
    die("File not found or access denied.");
}

// Security: Ensure the file is within the uploads directory
$real_path = realpath($full_path);
if ($real_path === false || strpos($real_path, realpath($base_upload_path)) !== 0) {
    error_log("Security violation: Attempted access to file outside uploads directory: $full_path");
    die("Access denied.");
}

// Serve the file
if (file_exists($real_path)) {
    $mime_type = mime_content_type($real_path);
    header('Content-Type: ' . ($mime_type ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($real_path) . '"');
    header('Content-Length: ' . filesize($real_path));
    readfile($real_path);
    exit;
} else {
    error_log("File does not exist on server: $real_path");
    die("File does not exist.");
}
?>