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

    // Ensure is_deleted and deleted_at columns exist
    $driver = strtolower(DB_DRIVER);
    if ($driver === 'pgsql') {
        try {
            $db->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS is_deleted SMALLINT DEFAULT 0;");
            $db->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL;");
        } catch (Exception $e) {}
    } else {
        try {
            $cols = $db->query("SHOW COLUMNS FROM `app_users` LIKE 'is_deleted'")->fetchAll();
            if (empty($cols)) {
                $db->exec("ALTER TABLE `app_users` ADD COLUMN `is_deleted` TINYINT DEFAULT 0");
            }
            $cols2 = $db->query("SHOW COLUMNS FROM `app_users` LIKE 'deleted_at'")->fetchAll();
            if (empty($cols2)) {
                $db->exec("ALTER TABLE `app_users` ADD COLUMN `deleted_at` TIMESTAMP NULL");
            }
        } catch (Exception $e) {}
    }

    $affectedRows = 0;
    if ($userId > 0 && !empty($email)) {
        $stmt = $db->prepare("DELETE FROM app_users WHERE id = ? OR LOWER(TRIM(email)) = LOWER(TRIM(?))");
        $stmt->execute([$userId, $email]);
        $affectedRows = $stmt->rowCount();
    } elseif ($userId > 0) {
        $stmt = $db->prepare("DELETE FROM app_users WHERE id = ?");
        $stmt->execute([$userId]);
        $affectedRows = $stmt->rowCount();
    } else {
        $stmt = $db->prepare("DELETE FROM app_users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))");
        $stmt->execute([$email]);
        $affectedRows = $stmt->rowCount();
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Student account and all data have been permanently deleted successfully.',
        'affected_rows' => $affectedRows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to delete account: ' . $e->getMessage()
    ]);
}
