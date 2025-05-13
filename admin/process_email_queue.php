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

// Include PHPMailer autoloader only if not already loaded
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once 'vendor/autoload.php';
}

// --- Configuration ---
$emails_to_process_per_run = 20; // Process N emails at a time
$max_attempts = 3; // Max attempts to send an email
$delay_between_emails = 1; // Seconds to wait between sending emails
$base_retry_delay = 60; // Base delay in seconds for exponential backoff (doubles each attempt)
$log_file = __DIR__ . '/email_queue_errors.log'; // Path to error log file
// --- End Configuration ---

// Initialize logging
function logError($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

echo "Starting email queue processing at " . date('Y-m-d H:i:s') . "...\n";

try {
    // Fetch pending emails or failed emails with remaining attempts
    $query_fetch = "SELECT * FROM email_queue 
                    WHERE status = 'pending' 
                    OR (status = 'failed' AND attempts < :max_attempts 
                        AND (last_attempt_at IS NULL OR last_attempt_at < NOW() - INTERVAL (POW(2, attempts) * :base_retry_delay) SECOND))
                    ORDER BY created_at ASC LIMIT :limit";
    $stmt_fetch = $pdo->prepare($query_fetch);
    $stmt_fetch->bindValue(':max_attempts', $max_attempts, PDO::PARAM_INT);
    $stmt_fetch->bindValue(':base_retry_delay', $base_retry_delay, PDO::PARAM_INT);
    $stmt_fetch->bindValue(':limit', $emails_to_process_per_run, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $emails = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($emails)) {
        echo "No emails in queue to process.\n";
        exit;
    }

    echo "Found " . count($emails) . " email(s) to process.\n";

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $smtp_connected = false;

    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'mail.majlisuahlilquran.org';
    $mail->SMTPAuth = true;
    $mail->Username = 'admin@majlisuahlilquran.org';
    $mail->Password = '%bDbxex4n%Mn';
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';

    // Prepare update query
    $update_status_query = "UPDATE email_queue SET status = :status, attempts = :attempts, last_attempt_at = NOW(), error_message = :error_message WHERE id = :id";
    $stmt_update = $pdo->prepare($update_status_query);

    foreach ($emails as $index => $email_item) {
        echo "Processing email ID: {$email_item['id']} for {$email_item['recipient_email']} (Attempt " . ($email_item['attempts'] + 1) . ")...\n";

        try {
            // Ensure SMTP connection
            if (!$smtp_connected) {
                try {
                    $mail->smtpConnect();
                    $smtp_connected = true;
                    echo "SMTP connection established.\n";
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    throw new Exception("Failed to connect to SMTP server: " . $e->getMessage());
                }
            }

            $mail->setFrom('admin@majlisuahlilquran.org', 'Majlis Ahlil Quran Musabaqa Team');
            $mail->addReplyTo('admin@majlisuahlilquran.org', 'Majlis Ahlil Quran Musabaqa Team');
            $mail->addAddress($email_item['recipient_email'], $email_item['recipient_name']);
            $mail->isHTML(true);
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
            $error_message = "PHPMailer Error: " . $e->getMessage() . " | " . $mail->ErrorInfo;
            $status = ($email_item['attempts'] + 1 >= $max_attempts) ? 'permanently_failed' : 'failed';
            
            $stmt_update->execute([
                ':status' => $status,
                ':attempts' => $email_item['attempts'] + 1,
                ':error_message' => $error_message,
                ':id' => $email_item['id']
            ]);
            
            logError("Email ID: {$email_item['id']} failed for {$email_item['recipient_email']}. Error: $error_message");
            echo "Email ID: {$email_item['id']} failed. Error: $error_message\n";

            // If SMTP connection fails, mark as disconnected
            if (strpos($e->getMessage(), 'SMTP connect() failed') !== false) {
                $smtp_connected = false;
                $mail->smtpClose();
            }

        } catch (Exception $e) {
            $error_message = "General Error: " . $e->getMessage();
            $status = ($email_item['attempts'] + 1 >= $max_attempts) ? 'permanently_failed' : 'failed';
            
            $stmt_update->execute([
                ':status' => $status,
                ':attempts' => $email_item['attempts'] + 1,
                ':error_message' => $error_message,
                ':id' => $email_item['id']
            ]);
            
            logError("Email ID: {$email_item['id']} failed for {$email_item['recipient_email']}. Error: $error_message");
            echo "Email ID: {$email_item['id']} failed. Error: $error_message\n";

        } finally {
            $mail->clearAddresses();
            $mail->clearAttachments();
            
            // Add delay between emails (except for the last one)
            if ($index < count($emails) - 1) {
                sleep($delay_between_emails);
            }
        }
    }

    // Close SMTP connection
    if ($smtp_connected) {
        $mail->smtpClose();
        echo "SMTP connection closed.\n";
    }
    
    echo "Email queue processing finished for this run at " . date('Y-m-d H:i:s') . ".\n";

} catch (PDOException $e) {
    $error_message = "Database error during queue processing: " . $e->getMessage();
    logError($error_message);
    echo "$error_message\n";
} catch (Exception $e) {
    $error_message = "General error during queue processing: " . $e->getMessage();
    logError($error_message);
    echo "$error_message\n";
}
?>