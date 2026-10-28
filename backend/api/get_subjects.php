<?php
/**
 * API: Get Subjects (Filtered by Board, Standard, Stream)
 * Method: GET
 * Endpoint: /backend/api/get_subjects.php
 * Parameters:
 *   - board_id (int) OR board_code (string, e.g. "GSEB")
 *   - standard_id (int) OR standard_number (int, e.g. 9)
 *   - stream_id (int) OR stream_slug (string, e.g. "science") [Optional]
 */
define('IS_API_REQUEST', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $db = Database::getConnection();

    $boardId = isset($_GET['board_id']) ? (int)$_GET['board_id'] : null;
    $boardCode = isset($_GET['board_code']) ? trim($_GET['board_code']) : null;
    $mediumId = isset($_GET['medium_id']) ? (int)$_GET['medium_id'] : null;
    $mediumCode = isset($_GET['medium_code']) ? trim($_GET['medium_code']) : null;
    $standardId = isset($_GET['standard_id']) ? (int)$_GET['standard_id'] : null;
    $standardNumber = isset($_GET['standard_number']) ? (int)$_GET['standard_number'] : null;
    $streamId = isset($_GET['stream_id']) && $_GET['stream_id'] !== '' ? (int)$_GET['stream_id'] : null;
    $streamSlug = isset($_GET['stream_slug']) ? trim($_GET['stream_slug']) : null;

    // Normalize medium code
    if ($mediumCode && strtolower($mediumCode) === 'gujrati') {
        $mediumCode = 'gujarati';
    }

    $whereClauses = ["s.status = 'active'"];
    $params = [];

    // Board filter
    if ($boardId) {
        $whereClauses[] = "(s.board_id = :board_id OR s.board_id IS NULL)";
        $params[':board_id'] = $boardId;
    } elseif ($boardCode) {
        $whereClauses[] = "(LOWER(TRIM(b.code)) = LOWER(TRIM(:board_code)) OR s.board_id IS NULL)";
        $params[':board_code'] = $boardCode;
    }

    // Medium filter
    if ($mediumId) {
        $whereClauses[] = "(s.medium_id = :medium_id OR s.medium_id IS NULL)";
        $params[':medium_id'] = $mediumId;
    } elseif ($mediumCode) {
        $whereClauses[] = "(LOWER(TRIM(m.code)) = LOWER(TRIM(:medium_code)) OR s.medium_id IS NULL)";
        $params[':medium_code'] = $mediumCode;
    }

    // Standard filter
    if ($standardId) {
        $whereClauses[] = "s.standard_id = :standard_id";
        $params[':standard_id'] = $standardId;
    } elseif ($standardNumber) {
        $whereClauses[] = "st.standard_number = :standard_num";
        $params[':standard_num'] = $standardNumber;
    }

    // Stream filter (if standard is 11 or 12)
    if ($streamId) {
        $whereClauses[] = "(s.stream_id = :stream_id OR s.stream_id IS NULL)";
        $params[':stream_id'] = $streamId;
    } elseif ($streamSlug) {
        $whereClauses[] = "(LOWER(TRIM(str.slug)) = LOWER(TRIM(:stream_slug)) OR s.stream_id IS NULL)";
        $params[':stream_slug'] = $streamSlug;
    }

    $whereSql = implode(" AND ", $whereClauses);

    $query = "
        SELECT s.id, s.name, s.code, s.icon, s.color_hex, s.display_order,
               b.id as board_id, b.code as board_code, b.name as board_name,
               m.id as medium_id, m.code as medium_code, m.name as medium_name,
               st.id as standard_id, st.standard_number, st.name as standard_name,
               str.id as stream_id, str.slug as stream_slug, str.name as stream_name,
               (SELECT COUNT(*) FROM chapters_content c WHERE c.subject_id = s.id AND c.status = 'active') as chapter_count
        FROM subjects s
        LEFT JOIN boards b ON s.board_id = b.id
        LEFT JOIN mediums m ON s.medium_id = m.id
        JOIN standards st ON s.standard_id = st.id
        LEFT JOIN streams str ON s.stream_id = str.id
        WHERE {$whereSql}
        ORDER BY s.display_order ASC, s.name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $subjects = $stmt->fetchAll();



    foreach ($subjects as &$sub) {
        $sub['chapter_count'] = (int)$sub['chapter_count'];
        $sub['standard_number'] = (int)$sub['standard_number'];
    }

    jsonResponse('success', 'Subjects fetched successfully', $subjects);
} catch (PDOException $e) {
    jsonResponse('error', 'Failed to fetch subjects: ' . $e->getMessage(), null, 500);
}
