<?php
/**
 * Main Admin Dashboard
 * Book Banko
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'Dashboard Overview';
$pageSubtitle = 'Welcome to Book Banko Administration & Educational Content Manager';
$db = Database::getConnection();

// Fetch statistics
$totalBoards = (int)$db->query("SELECT COUNT(*) FROM boards WHERE status = 'active'")->fetchColumn();
$totalStandards = (int)$db->query("SELECT COUNT(*) FROM standards WHERE status = 'active'")->fetchColumn();
$totalSubjects = (int)$db->query("SELECT COUNT(*) FROM subjects WHERE status = 'active'")->fetchColumn();
$totalChapters = (int)$db->query("SELECT COUNT(*) FROM chapters_content WHERE status = 'active'")->fetchColumn();
$totalBanners = (int)$db->query("SELECT COUNT(*) FROM banners WHERE status = 'active'")->fetchColumn();

// Fetch registered student count
$totalStudents = 0;
try {
    $totalStudents = (int)$db->query("SELECT COUNT(*) FROM app_users")->fetchColumn();
} catch (Exception $e) {
    $totalStudents = 0;
}

// Calculate total PDF storage size from active chapters
$storageBytes = 0;
$activeFiles = $db->query("SELECT pdf_file_path, file_size_mb FROM chapters_content WHERE status = 'active'")->fetchAll();
foreach ($activeFiles as $row) {
    $f = $row['pdf_file_path'] ?? '';
    if ($f && is_file(PDF_UPLOADS_PATH . $f)) {
        $storageBytes += filesize(PDF_UPLOADS_PATH . $f);
    } elseif (!empty($row['file_size_mb'])) {
        $storageBytes += (float)$row['file_size_mb'] * 1024 * 1024;
    }
}
$storageFormatted = formatBytes($storageBytes);

// Fetch recent uploaded chapters
$recentStmt = $db->query("
    SELECT c.*, s.name as subject_name, st.name as standard_name, b.code as board_code, m.title as module_title
    FROM chapters_content c
    JOIN subjects s ON c.subject_id = s.id
    JOIN standards st ON s.standard_id = st.id
    JOIN boards b ON s.board_id = b.id
    LEFT JOIN dashboard_modules m ON c.module_id = m.id
    ORDER BY c.id DESC
    LIMIT 6
");
$recentChapters = $recentStmt->fetchAll();

// Fetch App Settings for quick status
$settingsStmt = $db->query("SELECT setting_key, setting_value FROM app_settings");
$appSettings = [];
while ($row = $settingsStmt->fetch()) {
    $appSettings[$row['setting_key']] = $row['setting_value'];
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Metric Stats Row -->
<div class="row g-4 mb-4">
    <!-- Boards Metric -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?= $totalBoards ?></div>
                    <div class="stat-label">Active Boards</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="bi bi-buildings"></i>
                </div>
            </div>
            <div class="mt-3 d-flex align-items-center text-muted small">
                <a href="<?= url('modules/boards/index.php') ?>" class="text-decoration-none text-primary fw-semibold">
                    Manage Boards <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Standards Metric -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?= $totalStandards ?></div>
                    <div class="stat-label">Standards (6-12)</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-indigo">
                    <i class="bi bi-mortarboard"></i>
                </div>
            </div>
            <div class="mt-3 d-flex align-items-center text-muted small">
                <a href="<?= url('modules/standards/index.php') ?>" class="text-decoration-none text-primary fw-semibold">
                    View Standards <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Subjects Metric -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?= $totalSubjects ?></div>
                    <div class="stat-label">Total Subjects</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-emerald">
                    <i class="bi bi-journal-bookmark"></i>
                </div>
            </div>
            <div class="mt-3 d-flex align-items-center text-muted small">
                <a href="<?= url('modules/subjects/index.php') ?>" class="text-decoration-none text-primary fw-semibold">
                    View Subjects <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- PDF Chapters Metric -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?= $totalChapters ?></div>
                    <div class="stat-label">PDF Chapters</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="bi bi-file-earmark-pdf"></i>
                </div>
            </div>
            <div class="mt-3 d-flex align-items-center text-muted small">
                <a href="<?= url('modules/chapters/index.php') ?>" class="text-decoration-none text-primary fw-semibold">
                    Manage Chapters <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Registered Students Metric -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value text-primary"><?= number_format($totalStudents) ?></div>
                    <div class="stat-label">Registered Students</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <div class="mt-3 d-flex align-items-center text-muted small">
                <a href="<?= url('modules/students/index.php') ?>" class="text-decoration-none text-primary fw-semibold">
                    View All Students <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & App Status Row -->
<div class="row g-4 mb-4">
    <!-- Quick Shortcuts Card -->
    <div class="col-lg-8">
        <div class="bb-card mb-0 h-100">
            <div class="bb-card-header">
                <h5><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Management Shortcuts</h5>
            </div>
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <a href="<?= url('modules/chapters/index.php') ?>" class="text-decoration-none text-center d-block p-3 rounded-4 bg-light border border-primary-subtle hover-shadow">
                        <i class="bi bi-file-earmark-pdf fs-2 text-primary d-block mb-2"></i>
                        <span class="fw-bold text-dark small d-block">Manage Chapters</span>
                        <span class="text-muted" style="font-size: 11px;">View & edit PDFs</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= url('modules/subjects/add.php') ?>" class="text-decoration-none text-center d-block p-3 rounded-4 bg-light border border-info-subtle">
                        <i class="bi bi-journal-plus fs-2 text-info d-block mb-2"></i>
                        <span class="fw-bold text-dark small d-block">Add Subject</span>
                        <span class="text-muted" style="font-size: 11px;">Link to standard</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= url('modules/banners/add.php') ?>" class="text-decoration-none text-center d-block p-3 rounded-4 bg-light border border-success-subtle">
                        <i class="bi bi-card-image fs-2 text-success d-block mb-2"></i>
                        <span class="fw-bold text-dark small d-block">Add Banner</span>
                        <span class="text-muted" style="font-size: 11px;">App promo banner</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= url('modules/settings/index.php') ?>" class="text-decoration-none text-center d-block p-3 rounded-4 bg-light border border-secondary-subtle">
                        <i class="bi bi-sliders2 fs-2 text-secondary d-block mb-2"></i>
                        <span class="fw-bold text-dark small d-block">App Config</span>
                        <span class="text-muted" style="font-size: 11px;">Version & notice</span>
                    </a>
                </div>
            </div>

            <!-- API Status Links Preview -->
            <div class="mt-4 p-3 rounded-3 bg-white border">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold text-dark small"><i class="bi bi-hdd-network me-2 text-primary"></i>REST API Endpoints for Flutter:</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('api/get_boards.php') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_boards.php</code></a>
                    <a href="<?= url('api/get_standards.php') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_standards.php</code></a>
                    <a href="<?= url('api/get_subjects.php?board_id=1&standard_number=9') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_subjects.php</code></a>
                    <a href="<?= url('api/get_chapters.php?subject_id=2') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_chapters.php</code></a>
                    <a href="<?= url('api/get_extra_materials.php') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_extra_materials.php</code></a>
                    <a href="<?= url('api/get_competitive_exams.php') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_competitive_exams.php</code></a>
                    <a href="<?= url('api/get_competitive_exam_materials.php') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_competitive_exam_materials.php</code></a>
                    <a href="<?= url('api/get_app_config.php') ?>" target="_blank" class="btn btn-sm btn-light border" style="font-size: 11.5px;"><code>GET /api/get_app_config.php</code></a>
                </div>
            </div>
        </div>
    </div>

    <!-- App Info & System Status -->
    <div class="col-lg-4">
        <div class="bb-card mb-0 h-100">
            <div class="bb-card-header">
                <h5><i class="bi bi-phone text-primary me-2"></i>App Control Status</h5>
            </div>
            
            <div class="list-group list-group-flush border-0">
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                    <span class="text-secondary small">App Version:</span>
                    <span class="badge bg-primary-subtle text-primary fw-bold">v<?= htmlspecialchars($appSettings['app_version'] ?? '1.0.0') ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                    <span class="text-secondary small">Maintenance Mode:</span>
                    <?php if (($appSettings['maintenance_mode'] ?? '0') === '1'): ?>
                        <span class="badge bg-danger-subtle text-danger fw-bold">Enabled</span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success fw-bold">Live (Normal)</span>
                    <?php endif; ?>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                    <span class="text-secondary small">Active Promo Banners:</span>
                    <span class="fw-bold text-dark"><?= $totalBanners ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                    <span class="text-secondary small">Uploaded PDFs Size:</span>
                    <span class="fw-bold text-dark"><?= $storageFormatted ?></span>
                </div>
            </div>

            <div class="mt-3 pt-3 border-top">
                <a href="<?= url('modules/settings/index.php') ?>" class="btn btn-bb-light btn-sm w-100 text-center">
                    <i class="bi bi-gear-fill me-1"></i>Configure App Settings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Chapters & Content List -->
<div class="bb-card">
    <div class="bb-card-header">
        <h5><i class="bi bi-clock-history text-primary me-2"></i>Recently Uploaded Chapters</h5>
        <a href="<?= url('modules/chapters/index.php') ?>" class="btn btn-sm btn-bb-light">View All (<?= $totalChapters ?>)</a>
    </div>

    <div class="table-responsive">
        <table class="table bb-table align-middle">
            <thead>
                <tr>
                    <th>Ch #</th>
                    <th>Chapter Title</th>
                    <th>Subject & Standard</th>
                    <th>Module</th>
                    <th>Pages</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentChapters)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No chapters uploaded yet. Click "Upload PDF" to add one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentChapters as $chap): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-primary border fw-bold">Ch. <?= $chap['chapter_number'] ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($chap['title']) ?></div>
                                <div class="text-muted" style="font-size: 11.5px;">
                                    <?= !empty($chap['pdf_file_path']) ? htmlspecialchars($chap['pdf_file_path']) : 'External URL' ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($chap['subject_name']) ?></div>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 11px;">
                                    <?= htmlspecialchars($chap['board_code']) ?> &bull; <?= htmlspecialchars($chap['standard_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    <?= htmlspecialchars($chap['module_title'] ?? 'Textbook') ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= $chap['page_count'] ?> Pages</span>
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
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
