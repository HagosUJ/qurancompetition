<?php
// filepath: /home/nquzqevo/public_html/admin/download.php

// Session is assumed to be started by an included file (e.g., config.php via db.php)
// Ensure that include happens before any $_SESSION access.

// Include database connection (which should handle session start via config.php)
try {
    // Use __DIR__ for reliable relative path to includes folder
    if (!file_exists(__DIR__ . '/includes/db.php')) { // Corrected path if db.php is in admin/includes
        throw new Exception("Database file not found at 'admin/includes/db.php'");
    }
    require_once __DIR__ . '/includes/db.php'; // This should bring in $pdo and start the session
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection not established. Check db.php file.");
    }
    // It's also good practice to check if session has been started if you rely on an include for it.
    if (session_status() == PHP_SESSION_NONE) {
        // If session is not started by includes, and you absolutely need it here, start it.
        // However, this might indicate an issue with your include's session management.
        // For now, let's assume the include *should* have started it.
        error_log("download.php - WARNING: Session was not started by included files. This might lead to issues.");
        // session_start(); // Uncomment cautiously if you are sure it's not started elsewhere
    }

} catch (Exception $e) {
    error_log("download.php - Database connection error: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    die("Internal server error. Please contact the administrator.");
}

// Check if admin is logged in (session should be active now)
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("download.php - Admin not logged in. Access denied. Session ID: " . session_id() . ". Admin Logged In Var: " . (isset($_SESSION['admin_logged_in']) ? $_SESSION['admin_logged_in'] : "Not Set"));
    header("HTTP/1.1 403 Forbidden");
    die("Access Denied. Admin login required.");
}

// Validate inputs
$application_id = isset($_GET['application_id']) ? filter_var($_GET['application_id'], FILTER_VALIDATE_INT) : 0;
$file_path_from_get = isset($_GET['file']) ? trim(urldecode($_GET['file'])) : '';

if ($application_id <= 0 || empty($file_path_from_get)) {
    error_log("download.php - Invalid request: application_id=$application_id, file_path_from_get=$file_path_from_get");
    header("HTTP/1.1 400 Bad Request");
    die("Invalid request: Missing application ID or file identifier.");
}

// Determine the correct base path and relative path based on $file_path_from_get
$determined_base_upload_path = '';
$normalized_relative_path = '';

// Check for "Uploads/" (uppercase U) prefix - case-sensitive for directory name
if (strpos($file_path_from_get, 'Uploads/') === 0) {
    $determined_base_upload_path = '/home/nquzqevo/public_html/portal/Uploads/';
    $normalized_relative_path = substr($file_path_from_get, strlen('Uploads/'));
} 
// Check for "uploads/" (lowercase u) prefix - case-sensitive
elseif (strpos($file_path_from_get, 'uploads/') === 0) {
    $determined_base_upload_path = '/home/nquzqevo/public_html/portal/uploads/';
    $normalized_relative_path = substr($file_path_from_get, strlen('uploads/'));
}
// Optional: Handle cases where the path might include 'portal/uploads/' or 'portal/Uploads/'
// This is if your DB sometimes stores a more complete path segment.
elseif (strpos($file_path_from_get, 'portal/Uploads/') === 0) {
    $determined_base_upload_path = '/home/nquzqevo/public_html/portal/Uploads/'; // Base is still the root of Uploads
    $normalized_relative_path = substr($file_path_from_get, strlen('portal/Uploads/'));
}
elseif (strpos($file_path_from_get, 'portal/uploads/') === 0) {
    $determined_base_upload_path = '/home/nquzqevo/public_html/portal/uploads/'; // Base is still the root of uploads
    $normalized_relative_path = substr($file_path_from_get, strlen('portal/uploads/'));
}
else {
    // If no known prefix is found, this indicates an unexpected path format from the database.
    error_log("download.php - Unrecognized path structure from DB: '$file_path_from_get'. Cannot determine base upload directory.");
    header("HTTP/1.1 500 Internal Server Error");
    die("Server configuration error: Unrecognized file path structure.");
}

$constructed_full_path = rtrim($determined_base_upload_path, '/') . '/' . ltrim($normalized_relative_path, '/');

error_log("download.php - Debug Paths: GET[file]='$file_path_from_get', determined_base_upload_path='$determined_base_upload_path', normalized_relative_path='$normalized_relative_path', constructed_full_path='$constructed_full_path'");

// Verify file belongs to application (using $file_path_from_get as it's stored in DB)
$valid_file = false;
$queries = [
    "SELECT photo_path AS path_column FROM application_details_nigerian WHERE application_id = :app_id AND photo_path = :file_uri",
    "SELECT file_path AS path_column FROM application_documents WHERE application_id = :app_id AND file_path = :file_uri",
    "SELECT photo_path AS path_column FROM application_details_international WHERE application_id = :app_id AND photo_path = :file_uri",
    "SELECT passport_scan_path AS path_column FROM application_documents_international WHERE application_id = :app_id AND passport_scan_path = :file_uri",
    "SELECT birth_certificate_path AS path_column FROM application_documents_international WHERE application_id = :app_id AND birth_certificate_path = :file_uri",
    "SELECT nomination_letter_path AS path_column FROM application_nominators_international WHERE application_id = :app_id AND nomination_letter_path = :file_uri"
];

foreach ($queries as $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['app_id' => $application_id, 'file_uri' => $file_path_from_get]); // Use original path for DB check
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $valid_file = true;
        break;
    }
}

if (!$valid_file) {
    error_log("download.php - File not associated with application or DB path mismatch: app_id=$application_id, file_uri_checked_in_db='$file_path_from_get'");
    header("HTTP/1.1 403 Forbidden");
    die("File not found for this application or access denied (DB check).");
}

// Security: Ensure the file is within the determined uploads directory
$resolved_determined_base_upload_path = realpath($determined_base_upload_path);
$resolved_constructed_full_path = realpath($constructed_full_path);

error_log("download.php - Realpath Debug: resolved_determined_base_upload_path='$resolved_determined_base_upload_path', resolved_constructed_full_path='$resolved_constructed_full_path'");
error_log("download.php - File Exists Check for constructed_full_path ('$constructed_full_path'): " . (file_exists($constructed_full_path) ? 'Exists' : 'Does NOT Exist'));
error_log("download.php - Is Readable Check for constructed_full_path ('$constructed_full_path'): " . (is_readable($constructed_full_path) ? 'Readable' : 'NOT Readable'));

if ($resolved_determined_base_upload_path === false) {
    error_log("download.php - CRITICAL CONFIGURATION ERROR: realpath() failed for determined base path: '$determined_base_upload_path'. Check if this base directory exists and is accessible on your LIVE SERVER.");
    header("HTTP/1.1 500 Internal Server Error");
    die("Server configuration error regarding upload directory.");
}

if ($resolved_constructed_full_path === false) {
    error_log("download.php - Security Error: realpath(\$constructed_full_path) failed. Path: '$constructed_full_path'. This means the path does not exist or permissions are incorrect on your LIVE SERVER.");
    header("HTTP/1.1 404 Not Found");
    die("File path could not be resolved on server.");
}

// Path traversal check using the dynamically determined base path
if (strpos($resolved_constructed_full_path, $resolved_determined_base_upload_path) !== 0) {
    error_log("download.php - Security violation: Attempted access to file outside designated uploads directory. Requested file resolves to '$resolved_constructed_full_path', which is not within determined base uploads path '$resolved_determined_base_upload_path'. Original GET[file]: '$file_path_from_get', Constructed Full Path: '$constructed_full_path'");
    header("HTTP/1.1 403 Forbidden");
    die("Access denied due to security policy.");
}

// ... (Serve the file logic - remains the same as before) ...
// Serve the file
if (file_exists($resolved_constructed_full_path) && is_readable($resolved_constructed_full_path)) {
    $mime_type = mime_content_type($resolved_constructed_full_path);
    if (!$mime_type) { // Fallback
        $file_extension = strtolower(pathinfo($resolved_constructed_full_path, PATHINFO_EXTENSION));
        $mime_map = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $mime_type = $mime_map[$file_extension] ?? 'application/octet-stream';
    }
    
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($resolved_constructed_full_path));

    $filename_for_disposition = basename($resolved_constructed_full_path);
    if (isset($_GET['preview']) && $_GET['preview'] == 'true' && in_array($mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'text/plain'])) {
        header('Content-Disposition: inline; filename="' . $filename_for_disposition . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename_for_disposition . '"');
    }
    
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    ob_clean(); 
    flush();
    readfile($resolved_constructed_full_path);
    exit;
} else {
    error_log("download.php - Final Check Failed: File does not exist or is not readable at resolved path: '$resolved_constructed_full_path'");
    header("HTTP/1.1 404 Not Found");
    die("File does not exist or is not readable on the server (final check).");
}
?>