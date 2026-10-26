<?php
/**
 * Delete Student User Account API
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

    $userId = isset($data['user_id']) ? (int)$data['user_id'] : (isset($data['id']) ? (int)$data['id'] : 0);
    $email = trim($data['email'] ?? '');

    if ($userId <= 0 && empty($email)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'User ID or Email is required to delete account.'
        ]);
        exit;
    }

    $deletedRows = 0;
    if ($userId > 0 && !empty($email)) {
        $stmt = $db->prepare("DELETE FROM app_users WHERE id = ? OR LOWER(TRIM(email)) = LOWER(TRIM(?))");
        $stmt->execute([$userId, $email]);
        $deletedRows = $stmt->rowCount();
    } elseif ($userId > 0) {
        $stmt = $db->prepare("DELETE FROM app_users WHERE id = ?");
        $stmt->execute([$userId]);
        $deletedRows = $stmt->rowCount();
    } else {
        $stmt = $db->prepare("DELETE FROM app_users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))");
        $stmt->execute([$email]);
        $deletedRows = $stmt->rowCount();
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Account and all associated learning data have been permanently deleted.',
        'deleted_rows' => $deletedRows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to delete account: ' . $e->getMessage()
    ]);
}
