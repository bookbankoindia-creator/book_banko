<?php
/**
 * Blueprint Management Module
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'Blueprint Material';
$pageSubtitle = 'Upload and manage exam blueprints, paper patterns, and chapter-wise weightage PDFs';
$db = Database::getConnection();

// Get the Blueprint module ID
$blueprintModule = $db->query("SELECT id, title, slug FROM dashboard_modules WHERE slug = 'blueprint' LIMIT 1")->fetch();
$blueprintModuleId = $blueprintModule ? (int)$blueprintModule['id'] : 4;

// Filters
$subjectFilter = isset($_GET['subject_id']) && $_GET['subject_id'] !== '' ? (int)$_GET['subject_id'] : null;
$standardFilter = isset($_GET['standard_id']) && $_GET['standard_id'] !== '' ? (int)$_GET['standard_id'] : null;

// Build query
$whereClauses = ["(c.module_id = :module_id OR m.slug = 'blueprint')"];
$params = [':module_id' => $blueprintModuleId];

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
$blueprints = $stmt->fetchAll();

// Dropdown lists for filter
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

<!-- Filter Card -->
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
                <a href="<?= url('modules/blueprints/index.php') ?>" class="btn btn-sm btn-light border px-3">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-compass-fill text-primary me-2"></i>Blueprint PDFs (<?= count($blueprints) ?>)
        </h5>
        <p class="text-muted small mb-0">Showing only Blueprint documents and examination paper schemes</p>
    </div>
    <a href="<?= url('modules/blueprints/add.php' . ($subjectFilter ? '?subject_id=' . $subjectFilter : '')) ?>" class="btn btn-bb-primary btn-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-cloud-arrow-up-fill"></i>
        <span>Upload New Blueprint</span>
    </a>
</div>

<div class="bb-card p-3 p-md-4">
    <div class="table-responsive">
        <table class="table bb-table data-table align-middle w-100">
            <thead>
                <tr>
                    <th width="60">#</th>
                    <th>Blueprint Title</th>
                    <th>Subject</th>
                    <th>Pages / Size</th>
                    <th>PDF File Source</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th width="120" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blueprints)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-compass fs-2 d-block mb-2 text-muted"></i>
                            No Blueprint materials uploaded yet. Click <strong>Upload New Blueprint</strong> to add one.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($blueprints as $bp): ?>
                    <tr>
                        <td>
                            <span class="badge bg-light text-secondary border"><?= $bp['chapter_number'] ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($bp['title']) ?></div>
                            <?php if (!empty($bp['description'])): ?>
                                <div class="text-muted small text-truncate" style="max-width: 300px;">
                                    <?= htmlspecialchars($bp['description']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold text-primary"><?= htmlspecialchars($bp['subject_name']) ?></div>
                        </td>
                        <td>
                            <div class="small">
                                <span><i class="bi bi-file-earmark-text me-1"></i><?= $bp['page_count'] ?> Pages</span>
                                <br>
                                <span class="text-muted"><i class="bi bi-hdd me-1"></i><?= number_format($bp['file_size_mb'], 2) ?> MB</span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($bp['pdf_file_path'])): ?>
                                <a href="<?= getPdfUrl($bp['pdf_file_path']) ?>" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle text-decoration-none">
                                    <i class="bi bi-filetype-pdf me-1"></i>View PDF
                                </a>
                            <?php elseif (!empty($bp['pdf_external_url'])): ?>
                                <a href="<?= htmlspecialchars($bp['pdf_external_url']) ?>" target="_blank" class="badge bg-info-subtle text-info border border-info-subtle text-decoration-none">
                                    <i class="bi bi-link-45deg me-1"></i>External Link
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">No File</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-muted small"><?= $bp['display_order'] ?></span>
                        </td>
                        <td>
                            <?php if ($bp['status'] === 'active'): ?>
                                <span class="badge-bb badge-bb-active"><i class="bi bi-check-circle-fill"></i> Active</span>
                            <?php else: ?>
                                <span class="badge-bb badge-bb-inactive"><i class="bi bi-x-circle-fill"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url('modules/blueprints/edit.php?id=' . $bp['id']) ?>" class="btn btn-sm btn-light border p-1 px-2" title="Edit Blueprint">
                                <i class="bi bi-pencil-fill text-primary"></i>
                            </a>
                            <a href="<?= url('modules/blueprints/delete.php?id=' . $bp['id'] . '&csrf=' . getCSRFToken()) ?>" class="btn btn-sm btn-light border p-1 px-2 text-danger" title="Delete Blueprint" onclick="return confirm('Are you sure you want to delete this blueprint?');">
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
