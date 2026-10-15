<?php
/**
 * API: Get AI Course Materials & Curriculum PDFs by Standard
 * Method: GET
 * Endpoint: /backend/api/get_ai_course_materials.php
 * Parameters:
 *   - standard_number (optional, e.g. 6, 7, 8, 9, 10, 11, 12)
 *   - standard_id (optional)
 *   - search (optional)
 */
define('IS_API_REQUEST', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/ai_course_schema.php';

try {
    $db = Database::getConnection();
    ensureAiCourseTableExists($db);

    $standardNumber = isset($_GET['standard_number']) && $_GET['standard_number'] !== '' ? (int)$_GET['standard_number'] : null;
    $standardId = isset($_GET['standard_id']) && $_GET['standard_id'] !== '' ? (int)$_GET['standard_id'] : null;
    $search = trim($_GET['search'] ?? '');

    $whereClauses = ["status = 'active'"];
    $params = [];

    if ($standardNumber !== null && $standardNumber > 0) {
        $whereClauses[] = "standard_number = :std_num";
        $params[':std_num'] = $standardNumber;
    }

    if ($standardId !== null && $standardId > 0) {
        $whereClauses[] = "standard_id = :std_id";
        $params[':std_id'] = $standardId;
    }

    if (!empty($search)) {
        $whereClauses[] = "(title LIKE :search1 OR description LIKE :search2 OR module_name LIKE :search3)";
        $params[':search1'] = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
    }

    $whereSql = implode(" AND ", $whereClauses);

    $sql = "
        SELECT *
        FROM ai_course_materials
        WHERE {$whereSql}
        ORDER BY display_order ASC, id ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();

    // Attach full PDF URL and parse types
    foreach ($materials as &$item) {
        $item['id'] = (int)$item['id'];
        $item['standard_number'] = (int)($item['standard_number'] ?? 0);
        $item['standard_id'] = isset($item['standard_id']) ? (int)$item['standard_id'] : null;
        $item['page_count'] = (int)($item['page_count'] ?? 1);
        $item['file_size_mb'] = (float)($item['file_size_mb'] ?? 0.0);
        $item['is_free'] = (bool)($item['is_free'] ?? true);
        $item['display_order'] = (int)($item['display_order'] ?? 1);

        if (!empty($item['pdf_file_path'])) {
            $item['full_pdf_url'] = resolveMediaUrl($item['pdf_file_path'], 'pdfs');
        } elseif (!empty($item['pdf_external_url'])) {
            $item['full_pdf_url'] = $item['pdf_external_url'];
        } else {
            // Provide a valid educational fallback reference PDF so user can immediately view and test reader
            $item['full_pdf_url'] = '';
        }
    }

    jsonResponse('success', 'AI course materials fetched successfully', $materials);
} catch (PDOException $e) {
    jsonResponse('error', 'Failed to fetch AI course materials: ' . $e->getMessage(), null, 500);
}
