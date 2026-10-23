<?php
/**
 * Reset / Update Password API
 * Book Banko Backend & API
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
    $newPassword = trim($data['password'] ?? ($data['new_password'] ?? ''));

    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email ID is required.'
        ]);
        exit;
    }

    if (strlen($newPassword) !== 8) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Password must be exactly 8 characters long.'
        ]);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM app_users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'No student account found with this email address.'
        ]);
        exit;
    }

    $up = $db->prepare("UPDATE app_users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $up->execute([$newPassword, $user['id']]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Password updated successfully! You can now sign in with your new password.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to reset password: ' . $e->getMessage()
    ]);
}
