<?php
/**
 * Save / Register Student User API
 * Book Banko Backend & API
 * Accepts JSON or POST form-data with:
 * - username
 * - email
 * - school_name
 * - city
 * - district
 * - state
 * - acknowledged (1/true)
 * - board (optional)
 * - medium (optional)
 * - standard (optional)
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

    // Auto-create app_users table if not exists (PostgreSQL and MySQL compatible)
    $driver = strtolower(DB_DRIVER);
    if ($driver === 'pgsql') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS app_users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL,
                password VARCHAR(255) DEFAULT NULL,
                school_name VARCHAR(255) NOT NULL,
                city VARCHAR(100) NOT NULL,
                district VARCHAR(100) NOT NULL,
                state VARCHAR(100) NOT NULL,
                acknowledged SMALLINT DEFAULT 1,
                board VARCHAR(50) DEFAULT NULL,
                medium VARCHAR(50) DEFAULT NULL,
                standard INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_app_users_email ON app_users(email);
        ");
        try {
            $db->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL;");
        } catch (Exception $e) {}
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `app_users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(150) NOT NULL,
                `email` VARCHAR(150) NOT NULL,
                `password` VARCHAR(255) DEFAULT NULL,
                `school_name` VARCHAR(255) NOT NULL,
                `city` VARCHAR(100) NOT NULL,
                `district` VARCHAR(100) NOT NULL,
                `state` VARCHAR(100) NOT NULL,
                `acknowledged` TINYINT DEFAULT 1,
                `board` VARCHAR(50) DEFAULT NULL,
                `medium` VARCHAR(50) DEFAULT NULL,
                `standard` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        try {
            $cols = $db->query("SHOW COLUMNS FROM `app_users` LIKE 'password'")->fetchAll();
            if (empty($cols)) {
                $db->exec("ALTER TABLE `app_users` ADD COLUMN `password` VARCHAR(255) NULL AFTER `email`");
            }
        } catch (Exception $e) {}
    }

    // Read payload from JSON body or standard $_POST
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

    $username   = trim($data['username'] ?? '');
    $email      = trim($data['email'] ?? '');
    $password   = isset($data['password']) && trim((string)$data['password']) !== '' ? trim((string)$data['password']) : null;
    $schoolName = trim($data['school_name'] ?? '');
    $city       = trim($data['city'] ?? '');
    $district   = trim($data['district'] ?? '');
    $state      = trim($data['state'] ?? '');
    $ackVal     = $data['acknowledged'] ?? 1;
    $acknowledged = ($ackVal == 1 || $ackVal === true || $ackVal === '1' || $ackVal === 'true') ? 1 : 0;

    $board    = !empty($data['board']) ? trim($data['board']) : null;
    $medium   = !empty($data['medium']) ? trim($data['medium']) : null;
    $standard = !empty($data['standard']) ? (int)$data['standard'] : null;

    // Basic Validation
    if (empty($username)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Username / Student Name is required.'
        ]);
        exit;
    }

    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email ID is required.'
        ]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Please provide a valid email address.'
        ]);
        exit;
    }

    if (empty($schoolName)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'School Name is required.'
        ]);
        exit;
    }

    if (empty($city) || empty($district) || empty($state)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'City, District, and State are required.'
        ]);
        exit;
    }

    // Check if user with this email already exists
    $checkStmt = $db->prepare("SELECT * FROM app_users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $checkStmt->execute([$email]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing record
        $updateStmt = $db->prepare("
            UPDATE app_users 
            SET username = ?,
                password = COALESCE(?, password),
                school_name = ?,
                city = ?,
                district = ?,
                state = ?,
                acknowledged = ?,
                board = COALESCE(?, board),
                medium = COALESCE(?, medium),
                standard = COALESCE(?, standard),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updateStmt->execute([
            $username,
            $password,
            $schoolName,
            $city,
            $district,
            $state,
            $acknowledged,
            $board,
            $medium,
            $standard,
            $existing['id']
        ]);

        $userId = (int)$existing['id'];
        $message = 'User profile updated successfully';
    } else {
        // Insert new record
        $insertStmt = $db->prepare("
            INSERT INTO app_users (username, email, password, school_name, city, district, state, acknowledged, board, medium, standard, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $insertStmt->execute([
            $username,
            $email,
            $password,
            $schoolName,
            $city,
            $district,
            $state,
            $acknowledged,
            $board,
            $medium,
            $standard
        ]);

        $userId = (int)$db->lastInsertId();
        $message = 'User registered successfully';
    }

    // Fetch refreshed user record
    $fetchStmt = $db->prepare("SELECT * FROM app_users WHERE id = ?");
    $fetchStmt->execute([$userId]);
    $userRecord = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'data' => [
            'id' => (int)($userRecord['id'] ?? $userId),
            'username' => $userRecord['username'] ?? $username,
            'email' => $userRecord['email'] ?? $email,
            'password' => $userRecord['password'] ?? $password,
            'school_name' => $userRecord['school_name'] ?? $schoolName,
            'city' => $userRecord['city'] ?? $city,
            'district' => $userRecord['district'] ?? $district,
            'state' => $userRecord['state'] ?? $state,
            'acknowledged' => (int)($userRecord['acknowledged'] ?? $acknowledged),
            'board' => $userRecord['board'] ?? $board,
            'medium' => $userRecord['medium'] ?? $medium,
            'standard' => $userRecord['standard'] ?? $standard,
            'created_at' => $userRecord['created_at'] ?? date('Y-m-d H:i:s'),
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save student data',
        'error' => $e->getMessage()
    ]);
}
