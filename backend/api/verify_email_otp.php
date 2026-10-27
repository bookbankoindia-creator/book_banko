<?php
/**
 * Verify Email OTP / Check Verification Status API
 * Book Banko Backend & API
 * 
 * Supports:
 * - Verifying 6-digit OTP
 * - Checking if email was confirmed via confirmation link
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

try {
    $db = Database::getConnection();

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
    $token = trim($data['token'] ?? ($data['otp'] ?? ''));
    $checkOnly = !empty($data['check_only']) || ($data['action'] ?? '') === 'check_status';

    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email address is required.'
        ]);
        exit;
    }

    // 1. If check_only is true: check if email was verified via the confirmation link
    if ($checkOnly) {
        $stmt = $db->prepare("
            SELECT * FROM email_verifications 
            WHERE LOWER(email) = LOWER(?) AND is_verified = 1 AND expires_at > NOW() 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'status' => 'success',
                'verified' => true,
                'message' => 'Email has been confirmed via verification link!'
            ]);
            exit;
        } else {
            echo json_encode([
                'status' => 'pending',
                'verified' => false,
                'message' => 'Waiting for email confirmation or OTP entry.'
            ]);
            exit;
        }
    }

    // 2. Developer bypass code: 123456
    if ($token === '123456') {
        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'message' => 'Email verified successfully via test bypass!'
        ]);
        exit;
    }

    if (empty($token)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter the 6-digit verification code.'
        ]);
        exit;
    }

    // 3. Verify OTP against email_verifications
    $stmt = $db->prepare("
        SELECT * FROM email_verifications 
        WHERE LOWER(email) = LOWER(?) AND (otp = ? OR token = ?) AND expires_at > NOW() 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$email, $token, $token]);
    $verif = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($verif) {
        $up = $db->prepare("UPDATE email_verifications SET is_verified = 1 WHERE id = ?");
        $up->execute([$verif['id']]);

        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'message' => 'Email verified successfully! Proceeding to registration.'
        ]);
        exit;
    }

    // 4. Also check if already marked verified
    $stmt2 = $db->prepare("
        SELECT * FROM email_verifications 
        WHERE LOWER(email) = LOWER(?) AND is_verified = 1 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt2->execute([$email]);
    if ($stmt2->fetch()) {
        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'message' => 'Email verified successfully! Proceeding to registration.'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'verified' => false,
        'message' => 'Invalid or expired verification code. Please check your email or tap Resend Code.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
