<?php
/**
 * Get Registered Student Users API
 * Book Banko Backend & API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('IS_API_REQUEST', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $db = Database::getConnection();

    // Auto-create app_users table if not exists
    $driver = strtolower(DB_DRIVER);
    if ($driver === 'pgsql') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS app_users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL,
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
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `app_users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(150) NOT NULL,
                `email` VARCHAR(150) NOT NULL,
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
    }

    $search = trim($_GET['search'] ?? '');
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $sql = "SELECT id, username, email, phone, school_name, city, district, state, board, medium, standard, created_at FROM app_users";
    $params = [];

    if (!empty($search)) {
        $sql .= " WHERE LOWER(username) LIKE ? OR LOWER(email) LIKE ? OR phone LIKE ? OR LOWER(school_name) LIKE ? OR LOWER(city) LIKE ? OR LOWER(district) LIKE ? OR LOWER(state) LIKE ?";
        $searchTerm = '%' . strtolower($search) . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    $sql .= " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'count' => count($users),
        'data' => $users
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch users',
        'error' => $e->getMessage()
    ]);
}
