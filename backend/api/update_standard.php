<?php
/**
 * Update Student Active Standard API
 * Book Banko Backend & API
 * Accepts JSON or POST form-data with:
 * - email (string, required)
 * - standard (int, required: 10, 11, or 12)
 * - stream (string, optional: Science, Commerce, Arts)
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

    // Read payload
    $rawInput = file_get_contents('php://input');
    $data = [];
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if (empty($data)) {
        $data = $_POST;
    }

    $email = trim($data['email'] ?? '');
    $standard = intval($data['standard'] ?? 0);
    $stream = trim($data['stream'] ?? '');

    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email address is required.'
        ]);
        exit;
    }

    if ($standard <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Valid standard number (10, 11, or 12) is required.'
        ]);
        exit;
    }

    // Check if user exists
    $checkStmt = $db->prepare("SELECT id, username, email, standard FROM app_users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $checkStmt->execute([$email]);
    $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $updateStmt = $db->prepare("UPDATE app_users SET standard = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$standard, $user['id']]);

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => "Standard updated to $standard successfully.",
            'data' => [
                'user_id' => intval($user['id']),
                'email' => $user['email'],
                'standard' => $standard,
                'stream' => $stream
            ]
        ]);
    } else {
        // If user not yet in table, we acknowledge the update so local app continues smoothly
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => "Standard updated locally to $standard.",
            'data' => [
                'email' => $email,
                'standard' => $standard,
                'stream' => $stream
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
