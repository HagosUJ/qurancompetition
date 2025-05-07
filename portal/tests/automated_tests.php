<?php
// Set error reporting for comprehensive test output
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/test_errors.log');

// Include configuration files
define('APP_PATH', realpath(dirname(__FILE__) . '/..'));
require_once APP_PATH . '/includes/config.php';

// Validate required configuration constants
$requiredConstants = ['DB_HOST', 'DB_USER', 'DB_NAME', 'APP_URL'];
foreach ($requiredConstants as $const) {
    if (!defined($const)) {
        error_log("Missing required constant: $const");
        die("Configuration error: Missing required constant $const");
    }
}

// Define constants
define('BASE_URL', APP_URL . '/portal/');
define('TEST_PASSWORD', 'StrongP@ssw0rd123!');
define('TEST_FULLNAME', 'Test User');
define('TEST_PHONE', '+2348012345678');
define('TEST_LOG_FILE', __DIR__ . '/test_log_' . date('Ymd_His') . '.log');

// Generate unique test email
$testEmail = 'test_user_' . uniqid() . '@example.com';

// Class for color-coded output
class TestOutput {
    public static function success(string $message): void {
        echo "\033[32m✓ SUCCESS: $message\033[0m\n";
        self::log("SUCCESS: $message");
    }
    
    public static function failure(string $message): void {
        echo "\033[31m✗ FAILURE: $message\033[0m\n";
        self::log("FAILURE: $message");
    }
    
    public static function info(string $message): void {
        echo "\033[36mℹ INFO: $message\033[0m\n";
        self::log("INFO: $message");
    }
    
    public static function section(string $title): void {
        echo "\n\033[1;33m=== $title ===\033[0m\n";
        self::log("SECTION: $title");
    }
    
    private static function log(string $message): void {
        file_put_contents(TEST_LOG_FILE, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
    }
}

// Class for the automated tests
class MusabaqaAutomatedTests {
    private string $cookieFile;
    private ?string $csrfToken = null;
    private ?int $userId = null;
    private bool $isLoggedIn = false;
    private array $testResults = [
        'passed' => 0,
        'failed' => 0,
        'total' => 0
    ];

    public function __construct() {
        $this->cookieFile = sys_get_temp_dir() . '/musabaqa_test_cookies_' . uniqid() . '.txt';
        touch($this->cookieFile);
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    /**
     * Execute all tests
     */
    public function runAllTests(): void {
        global $testEmail;
        try {
            TestOutput::info("Using database: " . DB_NAME . " on " . DB_HOST);
            TestOutput::info("Base URL: " . BASE_URL);
            TestOutput::info("Test email: " . $testEmail);
            
            $this->cleanupPreviousTestData();
            
            // Authentication Tests
            $this->testRegistration();
            $this->testLogin();
            if ($this->isLoggedIn) {
                $this->testPasswordReset();
                $this->testProfileUpdate();
                $this->testPasswordChange();
                $this->testGuidelinesAcknowledgment();
                $this->testContestantTypeSelection();
                $this->testApplicationSubmission();
                $this->testDocumentUpload();
                $this->testCSRFProtection();
                $this->testSessionTimeout();
            } else {
                TestOutput::failure("Skipping dependent tests due to login failure");
            }
            
            // These tests can run independently
            $this->testSecureAreaAccess();
            $this->testLogout();

            $this->printSummary();
        } catch (Exception $e) {
            TestOutput::failure("Test suite encountered an exception: " . $e->getMessage());
            error_log("Test suite exception: " . $e->getMessage());
        }
    }

    /**
     * Clean up any previous test data
     */
    private function cleanupPreviousTestData(): void {
        global $testEmail;
        TestOutput::section("Cleanup Previous Test Data");
        
        try {
            $conn = $this->getDatabaseConnection();
            
            // Delete test user
            $stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
            $stmt->bind_param("s", $testEmail);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete test user: " . $stmt->error);
            }
            
            // Delete associated applications
            $stmt = $conn->prepare("DELETE FROM applications WHERE user_id IN (SELECT id FROM users WHERE email = ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
            $stmt->bind_param("s", $testEmail);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete test applications: " . $stmt->error);
            }
            
            $conn->close();
            TestOutput::info("Previous test data cleaned up");
        } catch (Exception $e) {
            TestOutput::failure("Cleanup failed: " . $e->getMessage());
            error_log("Cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Get a database connection
     */
    private function getDatabaseConnection(): mysqli {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        return $conn;
    }

    /**
     * Test user registration process
     */
    private function testRegistration(): void {
        global $testEmail;
        TestOutput::section("Testing User Registration");
        
        // Test invalid email
        $response = $this->sendRequest('sign-up.php', [
            'fullname' => TEST_FULLNAME,
            'email' => 'invalid-email',
            'password' => TEST_PASSWORD,
            'confirm_password' => TEST_PASSWORD,
            'phone' => TEST_PHONE,
            'agree' => 'on',
            'register' => '1'
        ]);
        
        if (stripos($response, 'invalid email') !== false) {
            $this->recordSuccess("Registration validates email format");
        } else {
            $this->recordFailure("Registration should reject invalid email");
        }
        
        // Test password mismatch
        $response = $this->sendRequest('sign-up.php', [
            'fullname' => TEST_FULLNAME,
            'email' => $testEmail,
            'password' => TEST_PASSWORD,
            'confirm_password' => 'DifferentPassword123!',
            'phone' => TEST_PHONE,
            'agree' => 'on',
            'register' => '1'
        ]);
        
        if (stripos($response, 'password') !== false && stripos($response, 'match') !== false) {
            $this->recordSuccess("Registration validates password matching");
        } else {
            $this->recordFailure("Registration should reject mismatched passwords");
        }
        
        // Test valid registration
        $response = $this->sendRequest('sign-up.php', [
            'fullname' => TEST_FULLNAME,
            'email' => $testEmail,
            'password' => TEST_PASSWORD,
            'confirm_password' => TEST_PASSWORD,
            'phone' => TEST_PHONE,
            'agree' => 'on',
            'register' => '1'
        ]);
        
        if (stripos($response, 'success') !== false || stripos($response, 'verification') !== false) {
            $this->recordSuccess("Registration successful");
            $conn = $this->getDatabaseConnection();
            $stmt = $conn->prepare("UPDATE users SET email_verified = 1, status = 'active' WHERE email = ?");
            $stmt->bind_param("s", $testEmail);
            $stmt->execute();
            $conn->close();
            TestOutput::info("Email verification bypassed for testing");
        } else {
            $this->recordFailure("Registration failed with valid data");
        }
    }
    
    /**
     * Test login functionality
     */
    private function testLogin(): void {
        global $testEmail;
        TestOutput::section("Testing Login");
        
        // Test invalid credentials
        $response = $this->sendRequest('sign-in.php', [
            'email' => $testEmail,
            'password' => 'WrongPassword123!',
            'login' => '1'
        ]);
        
        if (stripos($response, 'invalid') !== false || stripos($response, 'incorrect') !== false) {
            $this->recordSuccess("Login rejects wrong password");
        } else {
            $this->recordFailure("Login should reject wrong password");
        }
        
        // Test valid credentials
        $response = $this->sendRequest('sign-in.php', [
            'email' => $testEmail,
            'password' => TEST_PASSWORD,
            'login' => '1'
        ], true);
        
        if (stripos($response, 'dashboard') !== false || stripos($response, 'index.php') !== false) {
            $this->recordSuccess("Login successful");
            $this->isLoggedIn = true;
            
            preg_match('/name="csrf_token"\s+value="([^"]+)"/', $response, $matches);
            if (!empty($matches[1])) {
                $this->csrfToken = $matches[1];
                TestOutput::info("CSRF token captured: " . substr($this->csrfToken, 0, 10) . "...");
            }
            
            $conn = $this->getDatabaseConnection();
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $testEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $this->userId = $row['id'];
                TestOutput::info("User ID captured: " . $this->userId);
            }
            $conn->close();
        } else {
            $this->recordFailure("Login failed with valid credentials");
        }
    }
    
    /**
     * Test password reset functionality
     */
    private function testPasswordReset(): void {
        global $testEmail;
        TestOutput::section("Testing Password Reset");
        
        $response = $this->sendRequest('enter-email.php', [
            'email' => $testEmail,
            'request_reset' => '1'
        ]);
        
        if (stripos($response, 'sent') !== false || stripos($response, 'email') !== false) {
            $this->recordSuccess("Password reset request accepted");
            
            $conn = $this->getDatabaseConnection();
            $stmt = $conn->prepare("SELECT reset_token FROM users WHERE email = ?");
            $stmt->bind_param("s", $testEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            $resetToken = '';
            if ($row = $result->fetch_assoc()) {
                $resetToken = $row['reset_token'];
                TestOutput::info("Reset token captured: " . substr($resetToken, 0, 10) . "...");
            }
            $conn->close();
            
            if ($resetToken) {
                $response = $this->sendRequest('reset-password.php', [
                    'token' => $resetToken,
                    'password' => 'NewStrongP@ss123!',
                    'confirm_password' => 'NewStrongP@ss123!',
                    'reset_password' => '1'
                ]);
                
                if (stripos($response, 'success') !== false || stripos($response, 'reset') !== false) {
                    $this->recordSuccess("Password reset successful");
                    $conn = $this->getDatabaseConnection();
                    $hashedPassword = password_hash(TEST_PASSWORD, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->bind_param("ss", $hashedPassword, $testEmail);
                    $stmt->execute();
                    $conn->close();
                    TestOutput::info("Original password restored");
                } else {
                    $this->recordFailure("Password reset failed");
                }
            } else {
                $this->recordFailure("Could not retrieve reset token");
            }
        } else {
            $this->recordFailure("Password reset request failed");
        }
    }
    
    /**
     * Test profile update functionality
     */
    private function testProfileUpdate(): void {
        global $testEmail;
        TestOutput::section("Testing Profile Update");
        
        if (!$this->csrfToken) {
            $this->recordFailure("Cannot test profile update - no CSRF token");
            return;
        }
        
        $response = $this->sendRequest('profile.php', [
            'csrf_token' => $this->csrfToken,
            'fullname' => TEST_FULLNAME . ' Updated',
            'phone' => '+2348099999999',
            'update_profile' => '1'
        ], true);
        
        if (stripos($response, 'success') !== false || stripos($response, 'updated') !== false) {
            $this->recordSuccess("Profile update successful");
            
            $conn = $this->getDatabaseConnection();
            $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
            $stmt->bind_param("i", $this->userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc() && $row['fullname'] == TEST_FULLNAME . ' Updated') {
                $this->recordSuccess("Profile update verified in database");
            } else {
                $this->recordFailure("Profile update not reflected in database");
            }
            $conn->close();
        } else {
            $this->recordFailure("Profile update failed");
        }
    }
    
    /**
     * Test password change functionality
     */
    private function testPasswordChange(): void {
        global $testEmail;
        TestOutput::section("Testing Password Change");
        
        if (!$this->csrfToken) {
            $this->recordFailure("Cannot test password change - no CSRF token");
            return;
        }
        
        $response = $this->sendRequest('profile.php', [
            'csrf_token' => $this->csrfToken,
            'current_password' => TEST_PASSWORD,
            'new_password' => TEST_PASSWORD . '!New',
            'confirm_new_password' => TEST_PASSWORD . '!New',
            'change_password' => '1'
        ], true);
        
        if (stripos($response, 'success') !== false || stripos($response, 'changed') !== false) {
            $this->recordSuccess("Password change successful");
            $conn = $this->getDatabaseConnection();
            $hashedPassword = password_hash(TEST_PASSWORD, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $this->userId);
            $stmt->execute();
            $conn->close();
            TestOutput::info("Original password restored");
        } else {
            $this->recordFailure("Password change failed");
        }
    }
    
    /**
     * Test guidelines acknowledgment
     */
    private function testGuidelinesAcknowledgment(): void {
        TestOutput::section("Testing Guidelines Acknowledgment");
        
        if (!$this->csrfToken || !$this->userId) {
            $this->recordFailure("Cannot test guidelines acknowledgment - not logged in");
            return;
        }
        
        $conn = $this->getDatabaseConnection();
        $stmt = $conn->prepare("UPDATE users SET guidelines_acknowledged = 0 WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $conn->close();
        
        $response = $this->sendRequest('application.php', [], true);
        
        if (stripos($response, 'guidelines') !== false && stripos($response, 'modal') !== false) {
            $this->recordSuccess("Guidelines modal appears");
            
            $response = $this->sendRequest('application.php', [
                'csrf_token' => $this->csrfToken,
                'action' => 'acknowledge_guidelines',
                'guidelines_acknowledged' => '1'
            ], true);
            
            if (stripos($response, 'modal') === false) {
                $this->recordSuccess("Guidelines acknowledgment processed");
            } else {
                $this->recordFailure("Guidelines acknowledgment failed");
            }
        } else {
            $this->recordFailure("Guidelines modal not found");
        }
    }
    
    /**
     * Test contestant type selection
     */
    private function testContestantTypeSelection(): void {
        TestOutput::section("Testing Contestant Type Selection");
        
        if (!$this->csrfToken || !$this->userId) {
            $this->recordFailure("Cannot test contestant type selection - not logged in");
            return;
        }
        
        $conn = $this->getDatabaseConnection();
        $stmt = $conn->prepare("DELETE FROM applications WHERE user_id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $conn->close();
        
        $response = $this->sendRequest('application.php', [
            'csrf_token' => $this->csrfToken,
            'contestant_type' => 'nigerian'
        ], true);
        
        if (stripos($response, 'step1') !== false || stripos($response, 'nigerian') !== false) {
            $this->recordSuccess("Contestant type selection successful");
            
            $conn = $this->getDatabaseConnection();
            $stmt = $conn->prepare("SELECT contestant_type FROM applications WHERE user_id = ?");
            $stmt->bind_param("i", $this->userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc() && $row['contestant_type'] == 'nigerian') {
                $this->recordSuccess("Contestant type saved in database");
            } else {
                $this->recordFailure("Contestant type not saved correctly");
            }
            $conn->close();
        } else {
            $this->recordFailure("Contestant type selection failed");
        }
    }
    
    /**
     * Test application submission process
     */
    private function testApplicationSubmission(): void {
        TestOutput::section("Testing Application Submission");
        
        if (!$this->csrfToken || !$this->userId) {
            $this->recordFailure("Cannot test application submission - not logged in");
            return;
        }
        
        $response = $this->sendRequest('application-step1-nigerian.php', [
            'csrf_token' => $this->csrfToken,
            'first_name' => 'Test',
            'last_name' => 'User',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'address' => '123 Test Street',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'next_step' => '1'
        ], true);
        
        if (stripos($response, 'step2') !== false) {
            $this->recordSuccess("Application step 1 completed");
            
            $conn = $this->getDatabaseConnection();
            $stmt = $conn->prepare("SELECT status FROM applications WHERE user_id = ?");
            $stmt->bind_param("i", $this->userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc() && $row['status'] == 'Personal Info Complete') {
                $this->recordSuccess("Application status updated in database");
            } else {
                $this->recordFailure("Application status not updated correctly");
            }
            $conn->close();
        } else {
            $this->recordFailure("Application step 1 submission failed");
        }
    }
    
    /**
     * Test document upload functionality
     */
    private function testDocumentUpload(): void {
        TestOutput::section("Testing Document Upload");
        
        if (!$this->csrfToken || !$this->userId) {
            $this->recordFailure("Cannot test document upload - not logged in");
            return;
        }
        
        $testFilePath = sys_get_temp_dir() . '/test_document_' . uniqid() . '.pdf';
        $testFileContent = "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n3 0 obj\n<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<<>>>>\nendobj\nxref\n0 4\n0000000000 65535 f\n0000000010 00000 n\n0000000053 00000 n\n0000000102 00000 n\ntrailer\n<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF";
        file_put_contents($testFilePath, $testFileContent);
        
        try {
            $ch = curl_init(BASE_URL . 'documents.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
            
            $postFields = [
                'csrf_token' => $this->csrfToken,
                'document_type' => 'identification',
                'upload_document' => '1',
                'document' => new CURLFile($testFilePath, 'application/pdf', 'test_document.pdf')
            ];
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode < 400 && (stripos($response, 'success') !== false || stripos($response, 'uploaded') !== false)) {
                $this->recordSuccess("Document upload successful");
                
                $conn = $this->getDatabaseConnection();
                $stmt = $conn->prepare("SELECT id FROM user_documents WHERE user_id = ? AND document_type = 'identification'");
                $stmt->bind_param("i", $this->userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $this->recordSuccess("Document record found in database");
                } else {
                    $this->recordFailure("Document record not found in database");
                }
                $conn->close();
            } else {
                $this->recordFailure("Document upload failed (HTTP $httpCode)");
            }
        } catch (Exception $e) {
            $this->recordFailure("Document upload error: " . $e->getMessage());
        } finally {
            if (file_exists($testFilePath)) {
                unlink($testFilePath);
            }
        }
    }
    
    /**
     * Test CSRF protection
     */
    private function testCSRFProtection(): void {
        TestOutput::section("Testing CSRF Protection");
        
        if (!$this->userId) {
            $this->recordFailure("Cannot test CSRF protection - not logged in");
            return;
        }
        
        $response = $this->sendRequest('profile.php', [
            'csrf_token' => 'invalid_token',
            'fullname' => 'CSRF Attack User',
            'update_profile' => '1'
        ], true);
        
        if (stripos($response, 'invalid') !== false || stripos($response, 'csrf') !== false || stripos($response, 'token') !== false) {
            $this->recordSuccess("CSRF protection working");
        } else {
            $conn = $this->getDatabaseConnection();
            $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
            $stmt->bind_param("i", $this->userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc() && $row['fullname'] != 'CSRF Attack User') {
                $this->recordSuccess("CSRF protection prevented data modification");
            } else {
                $this->recordFailure("CSRF protection failed - data modified");
            }
            $conn->close();
        }
    }
    
    /**
     * Test secure area access protection
     */
    private function testSecureAreaAccess(): void {
        TestOutput::section("Testing Secure Area Protection");
        
        $this->sendRequest('logout.php', [], true);
        $this->isLoggedIn = false;
        
        $response = $this->sendRequest('profile.php', [], true);
        
        if (stripos($response, 'login') !== false || stripos($response, 'sign-in') !== false) {
            $this->recordSuccess("Secure area redirects to login");
        } else {
            $this->recordFailure("Secure area access not protected");
        }
        
        // Log in again for subsequent tests
        $this->testLogin();
    }
    
    /**
     * Test session timeout
     */
    private function testSessionTimeout(): void {
        TestOutput::section("Testing Session Timeout");
        
        if (!$this->isLoggedIn) {
            $this->testLogin();
        }
        
        $conn = $this->getDatabaseConnection();
        $stmt = $conn->prepare("UPDATE users SET last_activity = ? WHERE id = ?");
        $pastTime = time() - (SESSION_TIMEOUT_DURATION + 60);
        $stmt->bind_param("ii", $pastTime, $this->userId);
        $stmt->execute();
        $conn->close();
        
        $response = $this->sendRequest('profile.php', [], true);
        
        if (stripos($response, 'timeout') !== false || stripos($response, 'expired') !== false || stripos($response, 'login') !== false) {
            $this->recordSuccess("Session timeout working");
        } else {
            $this->recordFailure("Session timeout not working");
        }
        
        // Log in again
        $this->testLogin();
    }
    
    /**
     * Test logout functionality
     */
    private function testLogout(): void {
        TestOutput::section("Testing Logout");
        
        if (!$this->isLoggedIn) {
            $this->testLogin();
        }
        
        $this->sendRequest('logout.php', [], true);
        $this->isLoggedIn = false;
        
        $response = $this->sendRequest('profile.php', [], true);
        
        if (stripos($response, 'login') !== false || stripos($response, 'sign-in') !== false) {
            $this->recordSuccess("Logout working");
        } else {
            $this->recordFailure("Logout not working");
        }
    }
    
    /**
     * Send an HTTP request
     */
    private function sendRequest(string $endpoint, array $data = [], bool $saveCookies = false): string {
        try {
            $ch = curl_init(BASE_URL . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
            
            if ($saveCookies) {
                curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
                curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            }
            
            $response = curl_exec($ch);
            if ($response === false) {
                throw new Exception("cURL error: " . curl_error($ch));
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 400) {
                TestOutput::info("HTTP request to $endpoint failed with status $httpCode");
            }
            
            return $response;
        } catch (Exception $e) {
            error_log("Request to $endpoint failed: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Record a test success
     */
    private function recordSuccess(string $message): void {
        $this->testResults['passed']++;
        $this->testResults['total']++;
        TestOutput::success($message);
    }
    
    /**
     * Record a test failure
     */
    private function recordFailure(string $message): void {
        $this->testResults['failed']++;
        $this->testResults['total']++;
        TestOutput::failure($message);
    }
    
    /**
     * Print test summary
     */
    private function printSummary(): void {
        TestOutput::section("Test Summary");
        echo "Total Tests: " . $this->testResults['total'] . "\n";
        echo "\033[32mPassed: " . $this->testResults['passed'] . "\033[0m\n";
        echo "\033[31mFailed: " . $this->testResults['failed'] . "\033[0m\n";
        
        $successRate = ($this->testResults['total'] > 0) 
            ? round(($this->testResults['passed'] / $this->testResults['total']) * 100, 2) 
            : 0;
        
        echo "Success Rate: " . $successRate . "%\n";
        
        if ($this->testResults['failed'] == 0) {
            echo "\n\033[1;32m✓ All tests passed successfully!\033[0m\n";
        } else {
            echo "\n\033[1;31m✗ Some tests failed. Please review the output and logs for details.\033[0m\n";
        }
    }
}

// Run the tests
echo "Starting Musabaqa Portal Automated Tests\n";
echo "=======================================\n\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$tests = new MusabaqaAutomatedTests();
$tests->runAllTests();

echo "\n\nTests completed at " . date('Y-m-d H:i:s') . "\n";
?>