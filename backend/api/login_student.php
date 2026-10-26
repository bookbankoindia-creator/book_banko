<?php
/**
 * Student Login API
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

    $login = trim($data['email'] ?? ($data['username'] ?? ($data['login'] ?? '')));
    $password = trim($data['password'] ?? '');

    if (empty($login)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email ID or Username is required.'
        ]);
        exit;
    }

    if (empty($password)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Password is required.'
        ]);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM app_users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'No student account found with this email or username. Please sign up first.'
        ]);
        exit;
    }

    if (!empty($user['is_deleted']) && (int)$user['is_deleted'] === 1) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'This account was deleted in the app. Please register a new account to continue.'
        ]);
        exit;
    }

    // Verify password if user has password set
    if (!empty($user['password'])) {
        $storedPass = $user['password'];
        $match = ($storedPass === $password) || password_verify($password, $storedPass);
        if (!$match) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Incorrect password. Please try again or tap Forgot Password.'
            ]);
            exit;
        }
    } else {
        // If user didn't have password previously, set it now
        $up = $db->prepare("UPDATE app_users SET password = ? WHERE id = ?");
        $up->execute([$password, $user['id']]);
        $user['password'] = $password;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Welcome back, ' . htmlspecialchars($user['username']) . '!',
        'data' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'password' => $user['password'],
            'school_name' => $user['school_name'],
            'city' => $user['city'],
            'district' => $user['district'],
            'state' => $user['state'],
            'acknowledged' => (bool)$user['acknowledged'],
            'board' => $user['board'],
            'medium' => $user['medium'],
            'standard' => $user['standard'] ? (int)$user['standard'] : null,
            'created_at' => $user['created_at'],
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Login error: ' . $e->getMessage()
    ]);
}
