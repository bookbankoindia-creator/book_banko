<?php
/**
 * Chapters & PDF Content Management
 * Book Banko
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'Chapters & PDF Material';
$pageSubtitle = 'Upload and manage textbook chapters, question papers, solutions, and blueprints';
$db = Database::getConnection();

// Filters
$subjectFilter = isset($_GET['subject_id']) && $_GET['subject_id'] !== '' ? (int)$_GET['subject_id'] : null;
$moduleFilter = isset($_GET['module_id']) && $_GET['module_id'] !== '' ? (int)$_GET['module_id'] : null;
$standardFilter = isset($_GET['standard_id']) && $_GET['standard_id'] !== '' ? (int)$_GET['standard_id'] : null;

// Build query
$whereClauses = [];
$params = [];

if ($subjectFilter) {
    $whereClauses[] = "c.subject_id = :subject_id";
    $params[':subject_id'] = $subjectFilter;
}
if ($moduleFilter) {
    $whereClauses[] = "c.module_id = :module_id";
    $params[':module_id'] = $moduleFilter;
}
if ($standardFilter) {
    $whereClauses[] = "s.standard_id = :standard_id";
    $params[':standard_id'] = $standardFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$query = "
    SELECT c.*, 
           s.name as subject_name, 
           b.code as board_code, 
           st.name as standard_name,
           m.title as module_title
    FROM chapters_content c
    JOIN subjects s ON c.subject_id = s.id
    JOIN boards b ON s.board_id = b.id
    JOIN standards st ON s.standard_id = st.id
    LEFT JOIN dashboard_modules m ON c.module_id = m.id
    {$whereSql}
    ORDER BY s.id ASC, c.display_order ASC, c.chapter_number ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$chapters = $stmt->fetchAll();

// Dropdown lists for filter
$subjects = $db->query("
    SELECT s.id, s.name, b.code as board_code, st.name as standard_name 
    FROM subjects s
    JOIN boards b ON s.board_id = b.id
    JOIN standards st ON s.standard_id = st.id
    WHERE s.status = 'active'
    ORDER BY b.display_order ASC, st.display_order ASC, s.name ASC
")->fetchAll();

$modules = $db->query("SELECT id, title FROM dashboard_modules WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();
$standards = $db->query("SELECT id, name FROM standards WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Filter Card -->
<div class="bb-card mb-4 p-3">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small text-muted mb-1">Filter by Subject</label>
            <select class="form-select form-select-sm" name="subject_id" onchange="this.form.submit()">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?= $sub['id'] ?>" <?= $subjectFilter == $sub['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sub['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small text-muted mb-1">Filter by Module</label>
            <select class="form-select form-select-sm" name="module_id" onchange="this.form.submit()">
                <option value="">All Modules</option>
                <?php foreach ($modules as $mod): ?>
                    <option value="<?= $mod['id'] ?>" <?= $moduleFilter == $mod['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mod['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small text-muted mb-1">Filter by Standard</label>
            <select class="form-select form-select-sm" name="standard_id" onchange="this.form.submit()">
                <option value="">All Standards</option>
                <?php foreach ($standards as $std): ?>
                    <option value="<?= $std['id'] ?>" <?= $standardFilter == $std['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($std['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-bb-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="<?= url('modules/chapters/index.php') ?>" class="btn btn-sm btn-light border px-3">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="fw-bold mb-0 text-dark">Chapters & PDF Content (<?= count($chapters) ?>)</h5>
    <a href="<?= url('modules/chapters/add.php' . ($subjectFilter ? '?subject_id=' . $subjectFilter : '')) ?>" class="btn btn-bb-primary btn-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-cloud-arrow-up-fill"></i>
        <span>Upload New Chapter PDF</span>
    </a>
</div>

<div class="bb-card p-3 p-md-4">
    <div class="table-responsive">
        <table class="table bb-table data-table align-middle w-100">
            <thead>
                <tr>
                    <th width="50">Ch #</th>
                    <th>Chapter Title</th>
                    <th>Subject & Standard</th>
                    <th>Module</th>
                    <th>Pages / Size</th>
                    <th>PDF File Source</th>
                    <th>Status</th>
                    <th width="140" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chapters as $chap): ?>
                    <tr>
                        <td>
                            <span class="badge bg-primary text-white fw-bold px-2 py-1 rounded-2">
                                <?= $chap['chapter_number'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($chap['title']) ?></div>
                            <?php if (!empty($chap['description'])): ?>
                                <small class="text-muted d-block text-truncate" style="max-width: 260px;"><?= htmlspecialchars($chap['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($chap['subject_name']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                <?= htmlspecialchars($chap['module_title'] ?? 'Textbook') ?>
                            </span>
                        </td>
                        <td>
                            <span class="fw-semibold text-dark"><?= $chap['page_count'] ?> Pages</span>
                            <div class="text-muted" style="font-size: 11.5px;"><?= $chap['file_size_mb'] ?> MB</div>
                        </td>
                        <td>
                            <?php if (!empty($chap['pdf_file_path'])): ?>
                                <?php $pdfViewUrl = resolveMediaUrl($chap['pdf_file_path'], 'pdfs'); ?>
                                <a href="<?= htmlspecialchars($pdfViewUrl) ?>" target="_blank" class="btn btn-sm btn-light border text-danger text-truncate" style="max-width: 140px;" title="View PDF">
                                    <i class="bi bi-file-pdf-fill me-1"></i><?= htmlspecialchars($chap['pdf_file_path']) ?>
                                </a>
                            <?php elseif (!empty($chap['pdf_external_url'])): ?>
                                <a href="<?= htmlspecialchars($chap['pdf_external_url']) ?>" target="_blank" class="btn btn-sm btn-light border text-primary" title="Open External URL">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>External Link
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">No File</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($chap['status'] === 'active'): ?>
                                <span class="badge-bb badge-bb-active"><i class="bi bi-check-circle-fill"></i> Active</span>
                            <?php else: ?>
                                <span class="badge-bb badge-bb-inactive"><i class="bi bi-x-circle-fill"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url('modules/chapters/edit.php?id=' . $chap['id']) ?>" class="btn btn-sm btn-light border p-1 px-2" title="Edit Chapter">
                                <i class="bi bi-pencil-fill text-primary"></i>
                            </a>
                            <a href="<?= url('modules/chapters/delete.php?id=' . $chap['id'] . '&csrf=' . getCSRFToken()) ?>" class="btn btn-sm btn-light border p-1 px-2 text-danger" title="Delete" onclick="return confirm('Delete chapter <?= htmlspecialchars($chap['title']) ?>? The PDF file will also be removed.');">
                                <i class="bi bi-trash-fill"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
