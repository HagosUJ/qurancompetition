<?php
// process_email_queue.php

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

// --- Configuration ---
$emails_to_process_per_run = 20; // Process N emails at a time to avoid script timeout
$max_attempts = 3; // Max attempts to send an email before marking as permanently failed
// --- End Configuration ---

echo "Starting email queue processing...\n";

try {
    // Fetch pending emails
    $query_fetch = "SELECT * FROM email_queue WHERE status = 'pending' OR (status = 'failed' AND attempts < :max_attempts) ORDER BY created_at ASC LIMIT :limit";
    $stmt_fetch = $pdo->prepare($query_fetch);
    $stmt_fetch->bindValue(':max_attempts', $max_attempts, PDO::PARAM_INT);
    $stmt_fetch->bindValue(':limit', $emails_to_process_per_run, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $emails = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($emails)) {
        echo "No emails in queue to process.\n";
        exit;
    }

    echo "Found " . count($emails) . " email(s) to process.\n";

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'mail.majlisuahlilquran.org'; 
    $mail->SMTPAuth = true;
    $mail->Username = 'admin@majlisuahlilquran.org'; 
    $mail->Password = '%bDbxex4n%Mn'; 
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->SMTPKeepAlive = true; 
    $mail->CharSet = 'UTF-8';

    // DKIM Settings (Optional - ensure paths are correct if uncommented)
    // $mail->DKIM_domain = 'majlisuahlilquran.org';
    // $mail->DKIM_private = '/path/to/your/dkim_private.key'; 
    // $mail->DKIM_selector = 'default';
    // $mail->DKIM_passphrase = '';
    // $mail->DKIM_identity = $mail->From;

    $update_status_query = "UPDATE email_queue SET status = :status, attempts = :attempts, last_attempt_at = NOW(), error_message = :error_message WHERE id = :id";
    $stmt_update = $pdo->prepare($update_status_query);

    foreach ($emails as $email_item) {
        echo "Processing email ID: {$email_item['id']} for {$email_item['recipient_email']}...\n";
        try {
            // Mark as processing (optional, good for long-running tasks or multiple workers)
            // $stmt_update->execute([':status' => 'processing', ':attempts' => $email_item['attempts'], ':error_message' => null, ':id' => $email_item['id']]);

            $mail->setFrom('admin@majlisuahlilquran.org', 'Majlis Ahlil Quran Musabaqa Team');
            $mail->addReplyTo('admin@majlisuahlilquran.org', 'Majlis Ahlil Quran Musabaqa Team');
            $mail->addAddress($email_item['recipient_email'], $email_item['recipient_name']);
            $mail->Subject = $email_item['subject'];
            $mail->Body = $email_item['html_body'];
            $mail->AltBody = $email_item['text_body'];
            
            $mail->send();
            
            $stmt_update->execute([
                ':status' => 'sent', 
                ':attempts' => $email_item['attempts'] + 1, 
                ':error_message' => null, 
                ':id' => $email_item['id']
            ]);
            echo "Email ID: {$email_item['id']} sent successfully.\n";

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $stmt_update->execute([
                ':status' => 'failed', 
                ':attempts' => $email_item['attempts'] + 1, 
                ':error_message' => $e->getMessage() . " | PHPMailer: " . $mail->ErrorInfo, 
                ':id' => $email_item['id']
            ]);
            echo "Email ID: {$email_item['id']} failed. Error: " . $e->getMessage() . " | PHPMailer: " . $mail->ErrorInfo . "\n";
        } catch (Exception $e) { // General exceptions
             $stmt_update->execute([
                ':status' => 'failed',
                ':attempts' => $email_item['attempts'] + 1,
                ':error_message' => "General Exception: " . $e->getMessage(),
                ':id' => $email_item['id']
            ]);
            echo "Email ID: {$email_item['id']} failed. General Error: " . $e->getMessage() . "\n";
        } finally {
            $mail->clearAddresses();
            $mail->clearAttachments(); // Though not used here, good practice
        }
    }
    $mail->smtpClose();
    echo "Email queue processing finished for this run.\n";

} catch (PDOException $e) {
    echo "Database error during queue processing: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General error during queue processing: " . $e->getMessage() . "\n";
}
?>