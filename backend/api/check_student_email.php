<?php
/**
 * Check Student Email Registration Status API
 * Book Banko Backend & API
 * Verifies if an email exists and is an active registered student in app_users
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

    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'exists' => false,
            'message' => 'Email address is required.'
        ]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'exists' => false,
            'message' => 'Please provide a valid email address.'
        ]);
        exit;
    }

    $stmt = $db->prepare("SELECT id, username, email, is_deleted, deleted_at FROM app_users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Not registered / fake email
        http_response_code(200);
        echo json_encode([
            'status' => 'not_found',
            'exists' => false,
            'registered' => false,
            'message' => 'This email address is not registered with Book Banko. Please check your email or register as a new student.'
        ]);
        exit;
    }

    if (!empty($user['is_deleted']) && (int)$user['is_deleted'] === 1) {
        // Account was deleted in app
        http_response_code(200);
        echo json_encode([
            'status' => 'deleted',
            'exists' => false,
            'registered' => true,
            'is_deleted' => true,
            'message' => 'This student account was deleted in the app. Please register a new account to continue.'
        ]);
        exit;
    }

    // Active registered student found
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'exists' => true,
        'registered' => true,
        'username' => $user['username'],
        'email' => $user['email'],
        'message' => 'Student account verified.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'exists' => false,
        'message' => 'Server error while checking student account: ' . $e->getMessage()
    ]);
}
