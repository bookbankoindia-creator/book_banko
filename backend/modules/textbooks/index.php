<?php
/**
 * Textbooks Management Module
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'Textbooks & Study Books';
$pageSubtitle = 'Upload and manage full textbooks, chapters, PDFs, and page-wise JEE/NEET question links';
$db = Database::getConnection();

$textbookModule = $db->query("SELECT id, title, slug FROM dashboard_modules WHERE slug = 'textbooks' LIMIT 1")->fetch();
$textbookModuleId = $textbookModule ? (int)$textbookModule['id'] : 1;

$subjectFilter = isset($_GET['subject_id']) && $_GET['subject_id'] !== '' ? (int)$_GET['subject_id'] : null;
$standardFilter = isset($_GET['standard_id']) && $_GET['standard_id'] !== '' ? (int)$_GET['standard_id'] : null;

$whereClauses = ["(c.module_id = :module_id OR m.slug = 'textbooks' OR c.module_id IS NULL)"];
$params = [':module_id' => $textbookModuleId];

if ($subjectFilter) {
    $whereClauses[] = "c.subject_id = :subject_id";
    $params[':subject_id'] = $subjectFilter;
}
if ($standardFilter) {
    $whereClauses[] = "s.standard_id = :standard_id";
    $params[':standard_id'] = $standardFilter;
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

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
    ORDER BY st.display_order ASC, s.name ASC, c.display_order ASC, c.chapter_number ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$chapters = $stmt->fetchAll();

$subjects = $db->query("
    SELECT s.id, s.name, b.code as board_code, st.name as standard_name 
    FROM subjects s
    JOIN boards b ON s.board_id = b.id
    JOIN standards st ON s.standard_id = st.id
    WHERE s.status = 'active'
    ORDER BY b.display_order ASC, st.display_order ASC, s.name ASC
")->fetchAll();

$standards = $db->query("SELECT id, name FROM standards WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="bb-card mb-4 p-3">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-lg-5">
            <label class="form-label small text-muted mb-1">Filter by Standard / Class</label>
            <select class="form-select form-select-sm" name="standard_id" onchange="this.form.submit()">
                <option value="">All Standards</option>
                <?php foreach ($standards as $std): ?>
                    <option value="<?= $std['id'] ?>" <?= $standardFilter == $std['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($std['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-lg-5">
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

        <div class="col-12 col-sm-12 col-lg-2">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-bb-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="<?= url('modules/textbooks/index.php') ?>" class="btn btn-sm btn-light border px-3">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-book-half text-primary me-2"></i>Textbooks & Materials (<?= count($chapters) ?>)
        </h5>
        <p class="text-muted small mb-0">Contains full textbook PDFs and chapters with page-by-page JEE & NEET question links</p>
    </div>
    <?php if (hasPermission('textbooks', 'add')): ?>
    <a href="<?= url('modules/chapters/add.php?module_id=' . $textbookModuleId . ($subjectFilter ? '&subject_id=' . $subjectFilter : '')) ?>" class="btn btn-bb-primary btn-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-cloud-arrow-up-fill"></i>
        <span>Upload Textbook / Book PDF</span>
    </a>
    <?php endif; ?>
</div>

<div class="bb-card p-3 p-md-4">
    <div class="table-responsive">
        <table class="table bb-table data-table align-middle w-100">
            <thead>
                <tr>
                    <th width="70">Type / #</th>
                    <th>Textbook / Document Title</th>
                    <th>Subject</th>
                    <th>Pages / Size</th>
                    <th>JEE & NEET Links</th>
                    <th>PDF Source</th>
                    <th>Status</th>
                    <th width="120" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($chapters)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-book fs-2 d-block mb-2 text-muted"></i>
                            No textbooks found. Click <strong>Upload Textbook / Book PDF</strong> to add one.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($chapters as $ch): ?>
                    <tr>
                        <td>
                            <?php 
                            $tLower = strtolower($ch['title']);
                            $isBook = ($ch['chapter_number'] <= 1 && (stripos($tLower, 'book') !== false || stripos($tLower, 'textbook') !== false || stripos($tLower, 'chapter') === false));
                            if ($isBook): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-book me-1"></i>Book</span>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border">Ch <?= $ch['chapter_number'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($ch['title']) ?></div>
                            <?php if (!empty($ch['description'])): ?>
                                <div class="text-muted small text-truncate" style="max-width: 250px;">
                                    <?= htmlspecialchars($ch['description']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold text-primary"><?= htmlspecialchars($ch['subject_name']) ?></div>
                        </td>
                        <td>
                            <div class="small">
                                <span><i class="bi bi-file-earmark-text me-1"></i><?= $ch['page_count'] ?> Pages</span>
                                <br>
                                <span class="text-muted"><i class="bi bi-hdd me-1"></i><?= number_format($ch['file_size_mb'], 2) ?> MB</span>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $hasPageLinks = !empty($ch['page_links']) && trim($ch['page_links']) !== '{}' && trim($ch['page_links']) !== '[]';
                            if ($hasPageLinks): 
                                $linksArray = json_decode($ch['page_links'], true);
                                $customPages = is_array($linksArray) ? count(array_filter(array_keys($linksArray), fn($k) => is_numeric($k))) : 0;
                                $startPage = (is_array($linksArray) && !empty($linksArray['start_page'])) ? (int)$linksArray['start_page'] : 4;
                            ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" title="<?= htmlspecialchars($ch['page_links']) ?>">
                                    <i class="bi bi-link-45deg me-1"></i>From Pg <?= $startPage ?>+ (<?= $customPages > 0 ? $customPages . ' custom' : 'Default' ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bi bi-stars me-1"></i>Default Settings
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($ch['pdf_file_path'])): ?>
                                <a href="<?= getPdfUrl($ch['pdf_file_path']) ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle text-decoration-none">
                                    <i class="bi bi-filetype-pdf me-1"></i>View PDF
                                </a>
                            <?php elseif (!empty($ch['pdf_external_url'])): ?>
                                <a href="<?= htmlspecialchars($ch['pdf_external_url']) ?>" target="_blank" class="badge bg-info-subtle text-info border border-info-subtle text-decoration-none">
                                    <i class="bi bi-link-45deg me-1"></i>External Link
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">No File</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ch['status'] === 'active'): ?>
                                <span class="badge-bb badge-bb-active"><i class="bi bi-check-circle-fill"></i> Active</span>
                            <?php else: ?>
                                <span class="badge-bb badge-bb-inactive"><i class="bi bi-x-circle-fill"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (hasPermission('textbooks', 'edit')): ?>
                                <a href="<?= url('modules/chapters/edit.php?id=' . $ch['id']) ?>" class="btn btn-sm btn-light border p-1 px-2" title="Edit">
                                    <i class="bi bi-pencil-fill text-primary"></i>
                                </a>
                                <a href="<?= url('modules/chapters/delete.php?id=' . $ch['id'] . '&csrf=' . getCSRFToken()) ?>" class="btn btn-sm btn-light border p-1 px-2 text-danger" title="Delete" onclick="return confirm('Delete this chapter?');">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border">View Only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
