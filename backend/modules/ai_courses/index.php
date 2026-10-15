<?php
/**
 * AI Course Materials Management
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/ai_course_schema.php';

requireLogin();

$pageTitle = 'AI Course Materials';
$pageSubtitle = 'Upload and manage curriculum PDFs, notes, and lessons for AI Course organized by standard';
$db = Database::getConnection();
ensureAiCourseTableExists($db);

// Filters
$standardFilter = isset($_GET['standard_number']) && $_GET['standard_number'] !== '' ? (int)$_GET['standard_number'] : null;
$statusFilter = trim($_GET['status'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');

$whereClauses = [];
$params = [];

if ($standardFilter !== null && $standardFilter > 0) {
    $whereClauses[] = "standard_number = :std_num";
    $params[':std_num'] = $standardFilter;
}
if (!empty($statusFilter)) {
    $whereClauses[] = "status = :status";
    $params[':status'] = $statusFilter;
}
if (!empty($searchFilter)) {
    $whereClauses[] = "(title LIKE :search1 OR description LIKE :search2 OR module_name LIKE :search3)";
    $params[':search1'] = '%' . $searchFilter . '%';
    $params[':search2'] = '%' . $searchFilter . '%';
    $params[':search3'] = '%' . $searchFilter . '%';
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$query = "
    SELECT *
    FROM ai_course_materials
    {$whereSql}
    ORDER BY standard_number ASC, display_order ASC, id ASC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$materials = $stmt->fetchAll();

// Standards for dropdown filter
$standards = $db->query("SELECT id, standard_number, name FROM standards WHERE status = 'active' ORDER BY standard_number ASC")->fetchAll();
if (empty($standards)) {
    $standards = [
        ['id' => 1, 'standard_number' => 6, 'name' => 'Standard 6'],
        ['id' => 2, 'standard_number' => 7, 'name' => 'Standard 7'],
        ['id' => 3, 'standard_number' => 8, 'name' => 'Standard 8'],
        ['id' => 4, 'standard_number' => 9, 'name' => 'Standard 9'],
        ['id' => 5, 'standard_number' => 10, 'name' => 'Standard 10'],
        ['id' => 6, 'standard_number' => 11, 'name' => 'Standard 11'],
        ['id' => 7, 'standard_number' => 12, 'name' => 'Standard 12'],
    ];
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Header Action Buttons -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-cpu-fill text-primary me-2"></i>AI Course Materials (<?= count($materials) ?>)
        </h5>
        <small class="text-muted">Upload and manage AI, Coding & Machine Learning materials for Standards 6 to 12</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('modules/ai_courses/add.php') ?>" class="btn btn-bb-primary btn-sm">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i>Upload AI Course PDF
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="bb-card p-3 mb-3">
    <form method="GET" action="" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-bold text-secondary mb-1">Search Materials</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by title, module, or topic..." value="<?= htmlspecialchars($searchFilter) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold text-secondary mb-1">Standard / Class</label>
            <select name="standard_number" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- All Standards --</option>
                <?php foreach ($standards as $std): ?>
                    <option value="<?= (int)$std['standard_number'] ?>" <?= $standardFilter === (int)$std['standard_number'] ? 'selected' : '' ?>>
                        Standard <?= (int)$std['standard_number'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-secondary mb-1">Status</label>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- All Statuses --</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <div class="flex-grow-1">
                <label class="form-label d-block small mb-1">&nbsp;</label>
                <button type="submit" class="btn btn-sm btn-bb-primary w-100">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
            <?php if (!empty($statusFilter) || !empty($searchFilter) || $standardFilter !== null): ?>
                <div>
                    <label class="form-label d-block small mb-1">&nbsp;</label>
                    <a href="<?= url('modules/ai_courses/index.php') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="bb-card">
    <div class="table-responsive">
        <table class="table bb-table data-table align-middle">
            <thead>
                <tr>
                    <th width="100">Standard</th>
                    <th width="50">Order</th>
                    <th>Material / Chapter Title</th>
                    <th>Module / Unit</th>
                    <th>PDF File</th>
                    <th>Pages & Size</th>
                    <th>Status</th>
                    <th width="100" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materials)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-cpu fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-bold mb-1">No AI Course Materials Found</h6>
                            <p class="small mb-3">Upload your first AI Course material PDF for any standard to make it available in the app.</p>
                            <a href="<?= url('modules/ai_courses/add.php') ?>" class="btn btn-sm btn-bb-primary">
                                <i class="bi bi-cloud-arrow-up-fill me-1"></i>Upload AI Course PDF
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materials as $item): 
                        $pdfUrl = !empty($item['pdf_file_path']) ? resolveMediaUrl($item['pdf_file_path'], 'pdfs') : ($item['pdf_external_url'] ?? '');
                    ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary px-2.5 py-1.5 fs-7 fw-bold">
                                    Std <?= (int)$item['standard_number'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= (int)$item['display_order'] ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary-subtle text-primary p-2 rounded">
                                        <i class="bi bi-robot fs-5"></i>
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($item['title']) ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                            <small class="text-muted text-truncate d-block" style="max-width: 300px;">
                                                <?= htmlspecialchars($item['description']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($item['module_name'])): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                                        <?= htmlspecialchars($item['module_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($pdfUrl)): ?>
                                    <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="btn btn-xs btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>View PDF
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Notes Only</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="d-block text-secondary">
                                    <i class="bi bi-file-text me-1"></i><?= (int)$item['page_count'] ?> Pages
                                </small>
                                <small class="text-muted">
                                    <?= number_format((float)$item['file_size_mb'], 2) ?> MB
                                </small>
                            </td>
                            <td>
                                <?= statusBadge($item['status']) ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('modules/ai_courses/edit.php?id=' . $item['id']) ?>" class="btn btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="<?= url('modules/ai_courses/delete.php?id=' . $item['id'] . '&csrf_token=' . getCSRFToken()) ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this AI Course material?');"
                                       title="Delete">
                                        <i class="bi bi-trash3-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
