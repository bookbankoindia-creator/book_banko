<?php
/**
 * Delete AI Course Material PDF
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/ai_course_schema.php';

requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf_token'] ?? '';

if (!verifyCSRFToken($csrf)) {
    flash('error', 'Invalid security token.');
    header('Location: ' . url('modules/ai_courses/index.php'));
    exit;
}

$db = Database::getConnection();
ensureAiCourseTableExists($db);

$stmt = $db->prepare("SELECT * FROM ai_course_materials WHERE id = :id");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if ($item) {
    // Delete physical local file if present
    if (!empty($item['pdf_file_path'])) {
        $localFile = PDF_UPLOADS_PATH . '/' . $item['pdf_file_path'];
        if (file_exists($localFile) && is_file($localFile)) {
            @unlink($localFile);
        }
    }

    $delStmt = $db->prepare("DELETE FROM ai_course_materials WHERE id = :id");
    $delStmt->execute([':id' => $id]);
    flash('success', 'AI Course Material "' . htmlspecialchars($item['title']) . '" deleted successfully.');
} else {
    flash('error', 'Material not found or already deleted.');
}

header('Location: ' . url('modules/ai_courses/index.php'));
exit;
