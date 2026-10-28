<?php
/**
 * API: Get Active Standards / Classes with Board & Medium filtering
 * Method: GET
 * Endpoint: /backend/api/get_standards.php
 * Parameters:
 *   - board_code (string, optional, e.g. "GSEB", "CBSE")
 *   - board_id (int, optional)
 *   - medium_code (string, optional, e.g. "english", "gujrati", "hindi")
 *   - medium_id (int, optional)
 */
define('IS_API_REQUEST', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $db = Database::getConnection();

    $boardCode = isset($_GET['board_code']) ? trim($_GET['board_code']) : null;
    $boardId = isset($_GET['board_id']) && (int)$_GET['board_id'] > 0 ? (int)$_GET['board_id'] : null;
    $mediumCode = isset($_GET['medium_code']) ? trim($_GET['medium_code']) : null;
    $mediumId = isset($_GET['medium_id']) && (int)$_GET['medium_id'] > 0 ? (int)$_GET['medium_id'] : null;

    if ($mediumCode) {
        $lower = strtolower($mediumCode);
        if ($lower === 'gujrati' || $lower === 'guj') {
            $mediumCode = 'gujarati';
        }
    }

    $whereClauses = ["st.status = 'active'"];
    $params = [];

    if ($boardId) {
        $whereClauses[] = "(st.board_id = :board_id OR st.board_id IS NULL)";
        $params[':board_id'] = $boardId;
    } elseif ($boardCode) {
        $whereClauses[] = "(LOWER(TRIM(b.code)) = LOWER(TRIM(:board_code)) OR st.board_id IS NULL)";
        $params[':board_code'] = $boardCode;
    }

    if ($mediumId) {
        $whereClauses[] = "(st.medium_id = :medium_id OR st.medium_id IS NULL)";
        $params[':medium_id'] = $mediumId;
    } elseif ($mediumCode) {
        $whereClauses[] = "(LOWER(TRIM(m.code)) = LOWER(TRIM(:medium_code)) OR st.medium_id IS NULL)";
        $params[':medium_code'] = $mediumCode;
    }

    $whereSql = implode(" AND ", $whereClauses);

    $stmt = $db->prepare("
        SELECT st.id, st.board_id, st.medium_id, st.standard_number, st.name, st.requires_stream, st.display_order,
               b.code as board_code, b.name as board_name,
               m.code as medium_code, m.name as medium_name
        FROM standards st
        LEFT JOIN boards b ON st.board_id = b.id
        LEFT JOIN mediums m ON st.medium_id = m.id
        WHERE {$whereSql}
        ORDER BY st.display_order ASC, st.standard_number ASC
    ");
    $stmt->execute($params);
    $standards = $stmt->fetchAll();

    // Cast boolean and integer
    foreach ($standards as &$std) {
        $std['requires_stream'] = (bool)($std['requires_stream'] ?? false);
        $std['standard_number'] = (int)($std['standard_number'] ?? 0);
    }

    jsonResponse('success', 'Standards fetched successfully', $standards);
} catch (PDOException $e) {
    jsonResponse('error', 'Failed to fetch standards: ' . $e->getMessage(), null, 500);
}
