<?php
/**
 * PHPMailer SMTP Email Service
 * Book Banko Backend & Admin Panel
 * 
 * Supports:
 * - SMTP Authentication (Gmail, Hostinger, Brevo, AWS SES, Custom SMTP)
 * - TLS & SSL Encryption
 * - In-Database Configuration via app_settings
 * - Environment Variable Fallback
 * - 6-Digit Verification OTP Emails with HTML Templates
 * - Live SMTP Diagnostic Testing with Debug Logging
 */

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Retrieve active SMTP configuration from database or environment
 */
function getSmtpConfig(): array {
    $dbConfig = [];
    try {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'smtp_%'");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $dbConfig[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $e) {
        // Fallback to env if database table not ready
    }

    return [
        'enabled'    => $dbConfig['smtp_enabled'] ?? getEnvValue('SMTP_ENABLED', '1'),
        'host'       => $dbConfig['smtp_host'] ?? getEnvValue('SMTP_HOST', 'smtp.gmail.com'),
        'port'       => (int)($dbConfig['smtp_port'] ?? getEnvValue('SMTP_PORT', '587')),
        'encryption' => strtolower($dbConfig['smtp_encryption'] ?? getEnvValue('SMTP_ENCRYPTION', 'tls')),
        'username'   => $dbConfig['smtp_username'] ?? getEnvValue('SMTP_USERNAME', 'bookbankoindia@gmail.com'),
        'password'   => $dbConfig['smtp_password'] ?? getEnvValue('SMTP_PASSWORD', ''),
        'from_email' => $dbConfig['smtp_from_email'] ?? getEnvValue('SMTP_FROM_EMAIL', 'bookbankoindia@gmail.com'),
        'from_name'  => $dbConfig['smtp_from_name'] ?? getEnvValue('SMTP_FROM_NAME', 'Book Banko'),
        'reply_to'   => $dbConfig['smtp_reply_to'] ?? getEnvValue('SMTP_REPLY_TO', 'bookbankoindia@gmail.com'),
        'debug'      => (int)($dbConfig['smtp_debug'] ?? getEnvValue('SMTP_DEBUG', '0')),
    ];
}

/**
 * Send 6-Digit OTP Verification Email to Student via PHPMailer
 * 
 * @param string $recipientEmail Target student email address
 * @param string $otp 6-digit verification code
 * @param string $token Secure confirmation link token
 * @param string|null &$errorOutput Output parameter for error message
 * @return bool True if sent successfully, False otherwise
 */
function sendOtpViaPHPMailer(string $recipientEmail, string $otp, string $token, ?string &$errorOutput = null): bool {
    $config = getSmtpConfig();

    if (empty($config['enabled']) || $config['enabled'] === '0') {
        $errorOutput = 'SMTP email sending is disabled in Admin Panel settings.';
        return false;
    }

    if (empty($config['username']) || empty($config['password'])) {
        $errorOutput = 'SMTP credentials not configured. Please set SMTP username & password in the Admin Panel.';
        return false;
    }

    // Determine public URL for confirmation endpoint
    $serverBaseUrl = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'vercel.app') !== false)
        ? 'https://' . $_SERVER['HTTP_HOST']
        : 'https://bookbanko-neon.vercel.app';

    $confirmationUrl = $serverBaseUrl . '/api/confirm_email.php?email=' . urlencode($recipientEmail) . '&token=' . $token;

    // Build Premium Branded HTML Template
    $htmlContent = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Book Banko Verification Code</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1E293B;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #F8FAFC; padding: 30px 15px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 520px; background-color: #FFFFFF; border-radius: 20px; overflow: hidden; border: 1px solid #E2E8F0; box-shadow: 0 10px 25px rgba(0,0,0,0.06);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%); background-color: #0F172A; padding: 32px 24px; text-align: center;">
                                <div style="font-size: 34px; margin-bottom: 6px;">📚</div>
                                <h1 style="margin: 0; color: #FFFFFF; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Book Banko</h1>
                                <p style="margin: 6px 0 0 0; color: #93C5FD; font-size: 13px; font-weight: 500;">Educational Resources & Student Portal</p>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding: 32px 28px;">
                                <h2 style="margin: 0 0 12px 0; color: #0F172A; font-size: 20px; font-weight: 700;">Verify Your Email Address</h2>
                                <p style="margin: 0 0 20px 0; color: #475569; font-size: 14px; line-height: 1.6;">
                                    Hello,<br>
                                    Use the 6-digit OTP code below to verify your student account for <strong>' . htmlspecialchars($recipientEmail) . '</strong>:
                                </p>

                                <!-- 6-Digit OTP Box -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
                                    <tr>
                                        <td align="center" style="background-color: #EFF6FF; border: 2px dashed #3B82F6; border-radius: 14px; padding: 22px 16px;">
                                            <div style="font-size: 11px; color: #1E40AF; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px;">Your 6-Digit Verification Code</div>
                                            <div style="font-family: \'SFMono-Regular\', Menlo, Monaco, Consolas, monospace; font-size: 36px; font-weight: 800; color: #1D4ED8; letter-spacing: 10px; margin-left: 10px;">' . htmlspecialchars($otp) . '</div>
                                            <div style="font-size: 12px; color: #64748B; margin-top: 8px;">⏱ Valid for <strong>15 minutes</strong></div>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Direct Confirmation Link Button -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0 10px 0;">
                                    <tr>
                                        <td align="center">
                                            <a href="' . htmlspecialchars($confirmationUrl) . '" target="_blank" style="display: block; width: 100%; box-sizing: border-box; text-align: center; background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); background-color: #2563EB; color: #FFFFFF !important; text-decoration: none; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 12px rgba(37,99,235,0.3);">
                                                ✓ Or Tap Here to Confirm Instantly
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin: 16px 0 0 0; color: #94A3B8; font-size: 12px; line-height: 1.5; text-align: center;">
                                    Never share this OTP with anyone. Book Banko staff will never ask for your verification code.
                                </p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="padding: 18px 24px; text-align: center; font-size: 11.5px; color: #64748B; background-color: #F1F5F9; border-top: 1px solid #E2E8F0;">
                                Book Banko India • <a href="mailto:' . htmlspecialchars($config['reply_to']) . '" style="color: #2563EB; text-decoration: none;">' . htmlspecialchars($config['reply_to']) . '</a><br>
                                If you did not request this OTP, you can safely ignore this email.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $textBody = "Your Book Banko verification code is: $otp\n\nValid for 15 minutes.\n\nOr verify instantly by visiting: $confirmationUrl\n\nIf you did not request this code, please ignore this email.";

    return sendMailViaPHPMailer(
        $recipientEmail,
        "Your Book Banko Verification OTP: $otp",
        $htmlContent,
        $textBody,
        $errorOutput
    );
}

/**
 * Low-level email sender using PHPMailer
 */
function sendMailViaPHPMailer(
    string $toEmail,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    ?string &$errorOutput = null
): bool {
    $config = getSmtpConfig();

    $mail = new PHPMailer(true);

    try {
        // Server Settings
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->Port       = $config['port'];

        // Encryption
        if ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        // Timeout & Charset
        $mail->Timeout = 12;
        $mail->CharSet = 'UTF-8';

        // Sender & Recipient
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail);
        if (!empty($config['reply_to'])) {
            $mail->addReplyTo($config['reply_to']);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = !empty($textBody) ? $textBody : strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorOutput = $mail->ErrorInfo ?: $e->getMessage();
        return false;
    }
}

/**
 * Diagnostic Tool to Test SMTP Credentials from Admin Panel
 */
function testSmtpDiagnostics(array $customConfig, string $testRecipient, ?string &$debugLog = null): array {
    $mail = new PHPMailer(true);
    $output = '';

    try {
        // Capture live debug output
        $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
        $mail->Debugoutput = function($str, $level) use (&$output) {
            $output .= "[$level] " . trim($str) . "\n";
        };

        $mail->isSMTP();
        $mail->Host       = trim($customConfig['host'] ?? 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = trim($customConfig['username'] ?? '');
        $mail->Password   = trim($customConfig['password'] ?? '');
        $mail->Port       = (int)($customConfig['port'] ?? 587);

        $enc = strtolower(trim($customConfig['encryption'] ?? 'tls'));
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';

        $fromEmail = trim($customConfig['from_email'] ?? $customConfig['username'] ?? 'bookbankoindia@gmail.com');
        $fromName  = trim($customConfig['from_name'] ?? 'Book Banko Admin');
        $replyTo   = trim($customConfig['reply_to'] ?? $fromEmail);

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($testRecipient);
        if (!empty($replyTo)) {
            $mail->addReplyTo($replyTo);
        }

        $testOtp = (string)random_int(100000, 999999);
        $mail->isHTML(true);
        $mail->Subject = "Book Banko SMTP Test - Verification Code: $testOtp";
        $mail->Body    = "<h2>Book Banko SMTP Verification Test</h2><p>Your SMTP credentials configured in the Admin Panel are working properly!</p><p><strong>Test OTP: $testOtp</strong></p><p>Time: " . date('Y-m-d H:i:s') . "</p>";
        $mail->AltBody = "Book Banko SMTP Test. Test OTP: $testOtp. Sent at " . date('Y-m-d H:i:s');

        $mail->send();
        $debugLog = $output;

        return [
            'success' => true,
            'message' => "Test email with OTP ($testOtp) sent successfully to $testRecipient via {$mail->Host}!",
            'log' => $output
        ];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        $debugLog = $output . "\nERROR: " . $error;

        return [
            'success' => false,
            'message' => "SMTP Error: " . $error,
            'log' => $debugLog
        ];
    }
}
