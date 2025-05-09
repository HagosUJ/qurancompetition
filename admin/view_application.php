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

// Include PHPMailer autoloader
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once 'vendor/autoload.php';
}

// Check if admin is logged in
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Validate application ID
$application_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
if ($application_id <= 0) {
    $_SESSION['error'] = "Invalid application ID.";
    header("Location: manage_applications.php");
    exit;
}

// Fetch application
$query = "SELECT a.id, a.user_id, a.contestant_type, a.status, a.current_step, a.submitted_at, a.created_at, a.last_updated, a.rejection_reason,
                 u.fullname, u.email,
                 COALESCE(nd.category, id.category) AS category,
                 COALESCE(nd.narration, id.narration) AS narration
          FROM applications a
          JOIN users u ON a.user_id = u.id
          LEFT JOIN application_details_nigerian nd ON a.id = nd.application_id
          LEFT JOIN application_details_international id ON a.id = id.application_id
          WHERE a.id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$application_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    $_SESSION['error'] = "Application not found.";
    header("Location: manage_applications.php");
    exit;
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;

    if (in_array($action, ['approve', 'reject']) && $application_id > 0) {
        $new_status = $action === 'approve' ? 'Approved' : 'Rejected';
        try {
            $update_query = "UPDATE applications SET status = ?, rejection_reason = ?, last_updated = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([$new_status, $new_status === 'Rejected' ? $rejection_reason : null, $application_id]);

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.example.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'your_email@example.com';
                $mail->Password = 'your_password';
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('no-reply@musabaqa.com', 'Musabaqa Admin');
                $mail->addAddress($application['email'], $application['fullname']);
                $mail->isHTML(true);
                $mail->Subject = 'Application Status Update';
                $body = "<h3>Dear {$application['fullname']},</h3>
                         <p>Your application for the " . ($application['contestant_type'] === 'nigerian' ? 'Nigerian' : 'International') . " competition has been <strong>" . ucfirst($new_status) . "</strong>.</p>";
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
                    'user_id' => $application['user_id'],
                    'message' => $notification_message
                ]);
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                $_SESSION['error'] = "Application updated but email failed: " . $mail->ErrorInfo;
            }

            $_SESSION['success'] = "Application $new_status successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating application: " . $e->getMessage();
        }

        header("Location: view_application.php?id=$application_id");
        exit;
    }
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment']);
    $admin_id = $_SESSION['admin_id'] ?? 1; // Replace with actual admin ID from session

    if (!empty($comment)) {
        try {
            $query = "INSERT INTO application_comments (application_id, admin_id, comment, created_at) 
                      VALUES (:application_id, :admin_id, :comment, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'application_id' => $application_id,
                'admin_id' => $admin_id,
                'comment' => $comment
            ]);
            $_SESSION['success'] = "Comment added successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding comment: " . $e->getMessage();
        }
        header("Location: view_application.php?id=$application_id");
        exit;
    } else {
        $_SESSION['error'] = "Comment cannot be empty.";
    }
}

// Fetch detailed information
$details_query = $application['contestant_type'] === 'nigerian' ?
    "SELECT full_name_nid, dob, age, address, district, state, lga, phone_number, email, health_status, languages_spoken, photo_path, category, narration, created_at, updated_at 
     FROM application_details_nigerian WHERE application_id = ?" :
    "SELECT full_name_passport, dob, age, address, city, country_residence, nationality, passport_number, phone_number, email, health_status, languages_spoken, photo_path, category, narration, created_at, updated_at 
     FROM application_details_international WHERE application_id = ?";
$stmt = $pdo->prepare($details_query);
$stmt->execute([$application_id]);
$details = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch documents with file size
$documents = [];
if ($application['contestant_type'] === 'nigerian') {
    $docs_query = "SELECT document_type, original_filename, file_path, file_size FROM application_documents WHERE application_id = ?";
    $stmt = $pdo->prepare($docs_query);
    try {
        $stmt->execute([$application_id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Document fetch error: " . $e->getMessage());
    }
} else {
    $docs_query = "SELECT passport_scan_path, birth_certificate_path FROM application_documents_international WHERE application_id = ?";
    $stmt = $pdo->prepare($docs_query);
    try {
        $stmt->execute([$application_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($doc) {
            if ($doc['passport_scan_path']) {
                $file_path = $doc['passport_scan_path'];
                $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                $documents[] = [
                    'document_type' => 'Passport Scan',
                    'original_filename' => basename($file_path),
                    'file_path' => $file_path,
                    'file_size' => $file_size
                ];
            }
            if ($doc['birth_certificate_path']) {
                $file_path = $doc['birth_certificate_path'];
                $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                $documents[] = [
                    'document_type' => 'Birth Certificate',
                    'original_filename' => basename($file_path),
                    'file_path' => $file_path,
                    'file_size' => $file_size
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Document fetch error: " . $e->getMessage());
    }
}

// Fetch sponsor/nominator
$sponsor_query = $application['contestant_type'] === 'nigerian' ?
    "SELECT sponsor_name, sponsor_address, sponsor_phone, sponsor_email, sponsor_occupation, sponsor_relationship 
     FROM application_sponsor_details_nigerian WHERE application_id = ?" :
    "SELECT nominator_type, nominator_name, nominator_address, nominator_country, nominator_phone, nomination_letter_path 
     FROM application_nominators_international WHERE application_id = ?";
$stmt = $pdo->prepare($sponsor_query);
$stmt->execute([$application_id]);
$sponsor = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch comments
$comments_query = "SELECT ac.comment, ac.created_at, u.fullname AS admin_name 
                  FROM application_comments ac 
                  JOIN users u ON ac.admin_id = u.id 
                  WHERE ac.application_id = ? 
                  ORDER BY ac.created_at DESC";
$stmt = $pdo->prepare($comments_query);
$stmt->execute([$application_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Application | JCDA Admin Portal</title>
    <?php include 'layouts/title-meta.php'; ?>
    <?php include 'layouts/head-css.php'; ?>
    <style>
        /* Modal styling for preview */
        #previewModal .modal-dialog {
            max-width: 80%;
        }
        #previewModal .modal-body {
            text-align: center;
        }
        #previewModal img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
        }
        #previewModal iframe {
            width: 100%;
            height: 70vh;
            border: none;
        }
        .file-meta {
            font-size: 0.9em;
            color: #6c757d;
        }
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
                                <div class="page-title-right">
                                    <a href="manage_applications.php" class="btn btn-secondary">
                                        <i class="ri-arrow-left-line"></i> Back to Applications
                                    </a>
                                </div>
                                <h4 class="page-title">Application Details - ID: <?php echo htmlspecialchars($application['id']); ?></h4>
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

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <!-- Actions -->
                                    <div class="mb-3">
                                        <?php if ($application['status'] !== 'Approved'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success">Approve Application</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($application['status'] !== 'Rejected'): ?>
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject Application</button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Accordion for Details -->
                                    <div class="accordion" id="applicationDetails">
                                        <!-- Application Information -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingAppInfo">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAppInfo" aria-expanded="true" aria-controls="collapseAppInfo">
                                                    Application Information
                                                </button>
                                            </h2>
                                            <div id="collapseAppInfo" class="accordion-collapse collapse show" aria-labelledby="headingAppInfo">
                                                <div class="accordion-body">
                                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($application['id']); ?></p>
                                                    <p><strong>Contestant Type:</strong> <?php echo ucfirst($application['contestant_type']); ?></p>
                                                    <p><strong>Category:</strong> <?php echo htmlspecialchars($details['category'] ?? 'N/A'); ?></p>
                                                    <p><strong>Narration:</strong> <?php echo htmlspecialchars($details['narration'] ?? 'N/A'); ?></p>
                                                    <p><strong>Status:</strong> <?php echo htmlspecialchars($application['status']); ?></p>
                                                    <p><strong>Current Step:</strong> <?php echo htmlspecialchars($application['current_step'] ?? 'N/A'); ?></p>
                                                    <p><strong>Submitted At:</strong> <?php echo $application['submitted_at'] ? date('M d, Y H:i', strtotime($application['submitted_at'])) : 'N/A'; ?></p>
                                                    <p><strong>Created At:</strong> <?php echo $application['created_at'] ? date('M d, Y H:i', strtotime($application['created_at'])) : 'N/A'; ?></p>
                                                    <p><strong>Last Updated:</strong> <?php echo $application['last_updated'] ? date('M d, Y H:i', strtotime($application['last_updated'])) : 'N/A'; ?></p>
                                                    <?php if ($application['status'] === 'Rejected' && $application['rejection_reason']): ?>
                                                        <p><strong>Rejection Reason:</strong> <?php echo htmlspecialchars($application['rejection_reason']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Applicant Information -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingApplicant">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseApplicant" aria-expanded="false" aria-controls="collapseApplicant">
                                                    Applicant Information
                                                </button>
                                            </h2>
                                            <div id="collapseApplicant" class="accordion-collapse collapse" aria-labelledby="headingApplicant">
                                                <div class="accordion-body">
                                                    <p><strong>Name (User):</strong> <?php echo htmlspecialchars($application['fullname']); ?></p>
                                                    <p><strong>Email (User):</strong> <?php echo htmlspecialchars($application['email']); ?></p>
                                                    <?php if ($details): ?>
                                                        <p><strong>Name (Official):</strong> <?php echo htmlspecialchars($application['contestant_type'] === 'nigerian' ? $details['full_name_nid'] : ($details['full_name_passport'] ?? 'N/A')); ?></p>
                                                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($details['dob'] ?? 'N/A'); ?></p>
                                                        <p><strong>Age:</strong> <?php echo htmlspecialchars($details['age'] ?? 'N/A'); ?></p>
                                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($details['address'] ?? 'N/A'); ?></p>
                                                        <?php if ($application['contestant_type'] === 'nigerian'): ?>
                                                            <p><strong>District:</strong> <?php echo htmlspecialchars($details['district'] ?? 'N/A'); ?></p>
                                                            <p><strong>State:</strong> <?php echo htmlspecialchars($details['state'] ?? 'N/A'); ?></p>
                                                            <p><strong>LGA:</strong> <?php echo htmlspecialchars($details['lga'] ?? 'N/A'); ?></p>
                                                        <?php else: ?>
                                                            <p><strong>City:</strong> <?php echo htmlspecialchars($details['city'] ?? 'N/A'); ?></p>
                                                            <p><strong>Country of Residence:</strong> <?php echo htmlspecialchars($details['country_residence'] ?? 'N/A'); ?></p>
                                                            <p><strong>Nationality:</strong> <?php echo htmlspecialchars($details['nationality'] ?? 'N/A'); ?></p>
                                                            <p><strong>Passport Number:</strong> <?php echo htmlspecialchars($details['passport_number'] ?? 'N/A'); ?></p>
                                                        <?php endif; ?>
                                                        <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($details['phone_number'] ?? 'N/A'); ?></p>
                                                        <p><strong>Email (Details):</strong> <?php echo htmlspecialchars($details['email'] ?? 'N/A'); ?></p>
                                                        <p><strong>Health Status:</strong> <?php echo htmlspecialchars($details['health_status'] ?? 'N/A'); ?></p>
                                                        <p><strong>Languages Spoken:</strong> <?php echo htmlspecialchars($details['languages_spoken'] ?? 'N/A'); ?></p>
                                                        <p><strong>Photo:</strong> 
                                                            <?php if ($details['photo_path']): ?>
                                                                <?php echo htmlspecialchars(basename($details['photo_path'])); ?>
                                                                <button class="btn btn-sm btn-outline-info ms-2" data-bs-toggle="modal" data-bs-target="#previewModal" data-file="<?php echo urlencode($details['photo_path']); ?>" data-type="image">Preview</button>
                                                                <a href="download.php?application_id=<?php echo $application_id; ?>&file=<?php echo urlencode($details['photo_path']); ?>" class="btn btn-sm btn-outline-primary ms-2">Download</a>
                                                            <?php else: ?>
                                                                N/A
                                                            <?php endif; ?>
                                                        </p>
                                                        <p><strong>Created At (Details):</strong> <?php echo $details['created_at'] ? date('M d, Y H:i', strtotime($details['created_at'])) : 'N/A'; ?></p>
                                                        <p><strong>Updated At (Details):</strong> <?php echo $details['updated_at'] ? date('M d, Y H:i', strtotime($details['updated_at'])) : 'N/A'; ?></p>
                                                    <?php else: ?>
                                                        <p>No additional details provided.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Documents -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingDocuments">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDocuments" aria-expanded="false" aria-controls="collapseDocuments">
                                                    Documents
                                                </button>
                                            </h2>
                                            <div id="collapseDocuments" class="accordion-collapse collapse" aria-labelledby="headingDocuments">
                                                <div class="accordion-body">
                                                    <?php if ($documents): ?>
                                                        <ul>
                                                            <?php foreach ($documents as $doc): ?>
                                                                <li>
                                                                    <?php echo htmlspecialchars($doc['document_type'] . ': ' . $doc['original_filename']); ?>
                                                                    <div class="file-meta">
                                                                        Size: <?php echo $doc['file_size'] ? number_format($doc['file_size'] / 1024, 2) . ' KB' : 'N/A'; ?>
                                                                    </div>
                                                                    <?php
                                                                    $file_ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                                                    $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                                                                    $is_pdf = $file_ext === 'pdf';
                                                                    ?>
                                                                    <?php if ($is_image || $is_pdf): ?>
                                                                        <button class="btn btn-sm btn-outline-info ms-2" data-bs-toggle="modal" data-bs-target="#previewModal" data-file="<?php echo urlencode($doc['file_path']); ?>" data-type="<?php echo $is_image ? 'image' : 'pdf'; ?>">Preview</button>
                                                                    <?php endif; ?>
                                                                    <a href="download.php?application_id=<?php echo $application_id; ?>&file=<?php echo urlencode($doc['file_path']); ?>" class="btn btn-sm btn-outline-primary ms-2">Download</a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p>No documents uploaded.</p>
                                                    <?php endif; ?>
                                                    <?php if ($sponsor && $application['contestant_type'] === 'international' && $sponsor['nomination_letter_path']): ?>
                                                        <p><strong>Nomination Letter:</strong> 
                                                            <?php
                                                            $file_ext = strtolower(pathinfo($sponsor['nomination_letter_path'], PATHINFO_EXTENSION));
                                                            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                                                            $is_pdf = $file_ext === 'pdf';
                                                            $file_size = file_exists($sponsor['nomination_letter_path']) ? filesize($sponsor['nomination_letter_path']) : 0;
                                                            ?>
                                                            <?php echo htmlspecialchars(basename($sponsor['nomination_letter_path'])); ?>
                                                            <div class="file-meta">
                                                                Size: <?php echo $file_size ? number_format($file_size / 1024, 2) . ' KB' : 'N/A'; ?>
                                                            </div>
                                                            <?php if ($is_image || $is_pdf): ?>
                                                                <button class="btn btn-sm btn-outline-info ms-2" data-bs-toggle="modal" data-bs-target="#previewModal" data-file="<?php echo urlencode($sponsor['nomination_letter_path']); ?>" data-type="<?php echo $is_image ? 'image' : 'pdf'; ?>">Preview</button>
                                                            <?php endif; ?>
                                                            <a href="download.php?application_id=<?php echo $application_id; ?>&file=<?php echo urlencode($sponsor['nomination_letter_path']); ?>" class="btn btn-sm btn-outline-primary ms-2">Download</a>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sponsor/Nominator -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingSponsor">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSponsor" aria-expanded="false" aria-controls="collapseSponsor">
                                                    <?php echo $application['contestant_type'] === 'nigerian' ? 'Sponsor' : 'Nominator'; ?> Information
                                                </button>
                                            </h2>
                                            <div id="collapseSponsor" class="accordion-collapse collapse" aria-labelledby="headingSponsor">
                                                <div class="accordion-body">
                                                    <?php if ($sponsor): ?>
                                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($application['contestant_type'] === 'nigerian' ? $sponsor['sponsor_name'] : $sponsor['nominator_name'] ?? 'N/A'); ?></p>
                                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($application['contestant_type'] === 'nigerian' ? $sponsor['sponsor_address'] : $sponsor['nominator_address'] ?? 'N/A'); ?></p>
                                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($application['contestant_type'] === 'nigerian' ? $sponsor['sponsor_phone'] : $sponsor['nominator_phone'] ?? 'N/A'); ?></p>
                                                        <?php if ($application['contestant_type'] === 'nigerian'): ?>
                                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($sponsor['sponsor_email'] ?? 'N/A'); ?></p>
                                                            <p><strong>Occupation:</strong> <?php echo htmlspecialchars($sponsor['sponsor_occupation'] ?? 'N/A'); ?></p>
                                                            <p><strong>Relationship:</strong> <?php echo htmlspecialchars($sponsor['sponsor_relationship'] ?? 'N/A'); ?></p>
                                                        <?php else: ?>
                                                            <p><strong>Nominator Type:</strong> <?php echo htmlspecialchars($sponsor['nominator_type'] ?? 'N/A'); ?></p>
                                                            <p><strong>Country:</strong> <?php echo htmlspecialchars($sponsor['nominator_country'] ?? 'N/A'); ?></p>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <p>No sponsor/nominator information provided.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Comments -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingComments">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseComments" aria-expanded="false" aria-controls="collapseComments">
                                                    Comments
                                                </button>
                                            </h2>
                                            <div id="collapseComments" class="accordion-collapse collapse" aria-labelledby="headingComments">
                                                <div class="accordion-body">
                                                    <form method="POST" class="mb-4">
                                                        <input type="hidden" name="add_comment" value="1">
                                                        <div class="mb-3">
                                                            <label for="comment" class="form-label">Add Comment</label>
                                                            <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Enter your comment" required></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Submit Comment</button>
                                                    </form>

                                                    <?php if ($comments): ?>
                                                        <h6>Comment History</h6>
                                                        <?php foreach ($comments as $comment): ?>
                                                            <div class="card mb-2">
                                                                <div class="card-body">
                                                                    <p class="mb-1"><?php echo htmlspecialchars($comment['comment']); ?></p>
                                                                    <small class="text-muted">
                                                                        By <?php echo htmlspecialchars($comment['admin_name']); ?> on 
                                                                        <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <p>No comments yet.</p>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Reject Modal -->
    <?php if ($application['status'] !== 'Rejected'): ?>
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Reject Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection (Optional)</label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" placeholder="Enter reason for rejection"></textarea>
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

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">File Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="downloadLink" class="btn btn-primary">Download</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'layouts/right-sidebar.php'; ?>
    <?php include 'layouts/footer-scripts.php'; ?>
    <script src="assets/js/app.min.js"></script>
    <script>
        // Handle preview modal content
        document.addEventListener('DOMContentLoaded', function () {
            var previewModal = document.getElementById('previewModal');
            previewModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var file = button.getAttribute('data-file');
                var type = button.getAttribute('data-type');
                var previewContent = document.getElementById('previewContent');
                var downloadLink = document.getElementById('downloadLink');

                // Construct file URL using download.php for security
                var fileUrl = 'download.php?application_id=<?php echo $application_id; ?>&file=' + file;
                downloadLink.href = fileUrl;

                // Clear previous content
                previewContent.innerHTML = '';

                if (type === 'image') {
                    previewContent.innerHTML = '<img src="' + fileUrl + '" alt="Preview">';
                } else if (type === 'pdf') {
                    previewContent.innerHTML = '<iframe src="' + fileUrl + '" title="PDF Preview"></iframe>';
                }
            });
        });
    </script>
</body>
</html>