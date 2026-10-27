<?php
/**
 * Send Verification OTP Email via PHPMailer & Database Store
 * Book Banko Backend & API
 * 
 * Supports:
 * - PHPMailer SMTP with TLS/SSL Authentication (Gmail, Hostinger, Brevo, custom SMTP)
 * - 6-Digit OTP code for in-app verification (15-min validity)
 * - Confirmation Link: https://bookbanko-neon.vercel.app/api/confirm_email.php?token=...&email=...
 * - Configurable via Admin Panel -> Email & SMTP Credentials
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('IS_API_REQUEST', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/mailer.php';

try {
    $db = Database::getConnection();

    // 1. Ensure email_verifications table exists
    $driver = strtolower(DB_DRIVER);
    if ($driver === 'pgsql') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_verifications (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                otp VARCHAR(10) NOT NULL,
                token VARCHAR(64) NOT NULL,
                is_verified SMALLINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_email_verif_email ON email_verifications(email);
            CREATE INDEX IF NOT EXISTS idx_email_verif_token ON email_verifications(token);
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `email_verifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL,
                `otp` VARCHAR(10) NOT NULL,
                `token` VARCHAR(64) NOT NULL,
                `is_verified` TINYINT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` DATETIME NOT NULL,
                INDEX (`email`),
                INDEX (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    // 2. Parse request input
    $rawInput = file_get_contents('php://input');
    $data = [];
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if (empty($data)) {
        $data = $_REQUEST;
    }

    $email = strtolower(trim($data['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Please provide a valid email address.'
        ]);
        exit;
    }

    // 3. Generate 6-digit OTP code & confirmation token
    $otp = (string)random_int(100000, 999999);
    $token = bin2hex(random_bytes(24));

    // Expiry: 15 minutes from now
    $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));

    // Invalidate old unverified requests for this email
    $del = $db->prepare("DELETE FROM email_verifications WHERE LOWER(email) = LOWER(?) AND is_verified = 0");
    $del->execute([$email]);

    // Insert new verification record
    $ins = $db->prepare("INSERT INTO email_verifications (email, otp, token, expires_at) VALUES (?, ?, ?, ?)");
    $ins->execute([$email, $otp, $token, $expiresAt]);

    // 4. Construct Confirmation Link
    $serverBaseUrl = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'vercel.app') !== false)
        ? 'https://' . $_SERVER['HTTP_HOST']
        : 'https://bookbanko-neon.vercel.app';

    $confirmationUrl = $serverBaseUrl . '/api/confirm_email.php?email=' . urlencode($email) . '&token=' . $token;
    $deepLinkUrl = 'bookbanko://register?email=' . urlencode($email) . '&token=' . $token . '&verified=1';

    // 5. Send OTP via PHPMailer SMTP
    $smtpError = null;
    $mailSent = sendOtpViaPHPMailer($email, $otp, $token, $smtpError);

    $smtpConfig = getSmtpConfig();
    $isConfigured = !empty($smtpConfig['username']) && !empty($smtpConfig['password']);

    echo json_encode([
        'status' => 'success',
        'message' => $mailSent 
            ? "Verification code sent to $email. Please check your inbox." 
            : ($isConfigured 
                ? "Verification code generated. (SMTP Notice: $smtpError)" 
                : "Verification code generated. Please configure SMTP in Admin Panel to send live emails."),
        'email' => $email,
        'otp' => $otp, // Available for development/testing and app auto-paste
        'token' => $token,
        'confirmation_url' => $confirmationUrl,
        'deep_link_url' => $deepLinkUrl,
        'smtp_sent' => $mailSent,
        'smtp_configured' => $isConfigured,
        'smtp_error' => $smtpError
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
