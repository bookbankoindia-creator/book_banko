<?php
/**
 * Edit Chapter PDF
 * Book Banko
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$db = Database::getConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM chapters_content WHERE id = :id");
$stmt->execute([':id' => $id]);
$chapter = $stmt->fetch();

if (!$chapter) {
    flash('error', 'Chapter not found.');
    header('Location: ' . url('modules/chapters/index.php'));
    exit;
}

$pageTitle = 'Edit Material: ' . $chapter['title'];
$pageSubtitle = 'Update document details, page count, and PDF file';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf)) {
        flash('error', 'Invalid security token.');
    } else {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $moduleId = (int)($_POST['module_id'] ?? 1);
        $materialType = $_POST['material_type'] ?? 'full_book';
        
        $chapterNumber = isset($_POST['chapter_number']) && trim($_POST['chapter_number']) !== '' 
            ? max(0, (int)$_POST['chapter_number']) 
            : 1;
        if ($chapterNumber === 0 && $materialType === 'full_book') {
            $chapterNumber = 1;
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $pageCount = (int)($_POST['page_count'] ?? 1);
        $externalUrl = trim($_POST['pdf_external_url'] ?? '');
        $pageLinks = trim($_POST['page_links'] ?? '');
        $startPageNum = isset($_POST['start_page_num']) && trim($_POST['start_page_num']) !== '' ? max(1, (int)$_POST['start_page_num']) : null;

        if (!empty($pageLinks) && str_starts_with($pageLinks, '{') && str_ends_with($pageLinks, '}')) {
            $linksArr = json_decode($pageLinks, true);
            if (is_array($linksArr)) {
                $cleanLinks = [];
                if ($startPageNum !== null) {
                    $cleanLinks['start_page'] = $startPageNum;
                } elseif (!empty($linksArr['start_page'])) {
                    $cleanLinks['start_page'] = (int)$linksArr['start_page'];
                }
                foreach ($linksArr as $pNum => $pUrl) {
                    if (is_numeric($pNum) && !empty(trim((string)$pUrl))) {
                        $cleanLinks[(int)$pNum] = trim((string)$pUrl);
                    }
                }
                $pageLinks = !empty($cleanLinks) ? json_encode($cleanLinks, JSON_UNESCAPED_SLASHES) : '';
            } else {
                $pageLinks = '';
            }
        } elseif ($startPageNum !== null) {
            $pageLinks = json_encode(['start_page' => $startPageNum], JSON_UNESCAPED_SLASHES);
        } else {
            $pageLinks = '';
        }

        $displayOrder = (int)($_POST['display_order'] ?? ($chapterNumber > 0 ? $chapterNumber : 1));
        $status = $_POST['status'] ?? 'active';

        $pdfFileName = $chapter['pdf_file_path'];
        $fileSizeMB = $chapter['file_size_mb'];
        $uploadError = false;

        // Check if new PDF file was uploaded (direct or standard)
        if (!empty($_POST['direct_uploaded_file'])) {
            $pdfFileName = trim($_POST['direct_uploaded_file']);
            $fileSizeMB = (float)($_POST['direct_file_size_mb'] ?? 0.0);
        } elseif (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadFile($_FILES['pdf_file'], PDF_UPLOADS_PATH, ['pdf'], 500);
            if ($uploadResult['status']) {
                // Delete old file if exists
                if (!empty($chapter['pdf_file_path']) && file_exists(PDF_UPLOADS_PATH . $chapter['pdf_file_path'])) {
                    @unlink(PDF_UPLOADS_PATH . $chapter['pdf_file_path']);
                }
                $pdfFileName = $uploadResult['filename'];
                $fileSizeMB = $uploadResult['size_mb'];
            } else {
                $uploadError = true;
                flash('error', $uploadResult['message']);
            }
        }

        if (!$uploadError) {
            if (empty($subjectId) || empty($title)) {
                flash('error', 'Subject selection and document title are required.');
            } else {
            try {
                $update = $db->prepare("
                    UPDATE chapters_content 
                    SET subject_id = :sub, module_id = :mod, chapter_number = :chap_num, 
                        title = :title, description = :desc, pdf_file_path = :file, 
                        pdf_external_url = :url, page_count = :pages, file_size_mb = :size, 
                        page_links = :links, display_order = :order, status = :status
                    WHERE id = :id
                ");
                $update->execute([
                    ':sub' => $subjectId,
                    ':mod' => $moduleId,
                    ':chap_num' => $chapterNumber,
                    ':title' => $title,
                    ':desc' => $description,
                    ':file' => $pdfFileName,
                    ':url' => $externalUrl,
                    ':pages' => $pageCount,
                    ':size' => $fileSizeMB,
                    ':links' => $pageLinks,
                    ':order' => $displayOrder,
                    ':status' => $status,
                    ':id' => $id
                ]);

                $moduleInfo = $db->query("SELECT title, slug FROM dashboard_modules WHERE id = {$moduleId} LIMIT 1")->fetch();
                $moduleLabel = $moduleInfo ? rtrim($moduleInfo['title'], 's') : 'Material';
                flash('success', "{$moduleLabel} '{$title}' updated successfully.");
                
                if ($moduleInfo && $moduleInfo['slug'] === 'textbooks') {
                    header('Location: ' . url('modules/textbooks/index.php?subject_id=' . $subjectId));
                } else {
                    header('Location: ' . url('modules/chapters/index.php?subject_id=' . $subjectId . '&module_id=' . $moduleId));
                }
                exit;
            } catch (PDOException $e) {
                flash('error', 'Update error: ' . $e->getMessage());
            }
        }
        }
    }
}

$subjects = $db->query("
    SELECT s.id, s.name, b.code as board_code, st.name as standard_name 
    FROM subjects s
    JOIN boards b ON s.board_id = b.id
    JOIN standards st ON s.standard_id = st.id
    WHERE s.status = 'active'
    ORDER BY b.display_order ASC, st.display_order ASC, s.name ASC
")->fetchAll();

$modules = $db->query("SELECT id, title, slug FROM dashboard_modules WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();

// Determine if existing chapter is a full book
$titleLower = strtolower($chapter['title']);
$isExistingFullBook = ($chapter['module_id'] == 1 || $chapter['module_id'] === null) && 
    (stripos($titleLower, 'book') !== false || stripos($titleLower, 'textbook') !== false || (int)$chapter['chapter_number'] <= 1 && stripos($titleLower, 'chapter') === false);

// Parse existing page_links for server-side pre-rendering
$savedStartPage = 4;
$savedRows = [];
if (!empty($chapter['page_links'])) {
    $rawLinks = trim($chapter['page_links']);
    if (str_starts_with($rawLinks, '{') && str_ends_with($rawLinks, '}')) {
        $decoded = json_decode($rawLinks, true);
        if (is_array($decoded)) {
            if (!empty($decoded['start_page']) && is_numeric($decoded['start_page'])) {
                $savedStartPage = max(1, (int)$decoded['start_page']);
            }
            foreach ($decoded as $k => $v) {
                if (is_numeric($k) && !empty($v)) {
                    $savedRows[(int)$k] = (string)$v;
                }
            }
            if (empty($decoded['start_page']) && !empty($savedRows)) {
                $savedStartPage = (int)min(array_keys($savedRows));
            }
            ksort($savedRows);
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="bb-card">
            <div class="bb-card-header">
                <h5><i class="bi bi-pencil-square text-primary me-2"></i><span id="card_header_title">Edit Material: <?= htmlspecialchars($chapter['title']) ?></span></h5>
                <a href="<?= url('modules/chapters/index.php') ?>" class="btn btn-sm btn-bb-light">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="subject_id">Select Subject <span class="text-danger">*</span></label>
                        <select class="form-select" id="subject_id" name="subject_id" required>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $chapter['subject_id'] == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="module_id">Dashboard Section / Module <span class="text-danger">*</span></label>
                        <select class="form-select" id="module_id" name="module_id" required onchange="handleModuleChanged()">
                            <?php foreach ($modules as $m): ?>
                                <option value="<?= $m['id'] ?>" 
                                        data-slug="<?= htmlspecialchars($m['slug']) ?>" 
                                        data-title="<?= htmlspecialchars($m['title']) ?>"
                                        <?= $chapter['module_id'] == $m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Dynamic Material Format / Requirement Selector for Textbooks -->
                    <div id="material_type_wrapper" class="col-12" style="display: none;">
                        <div class="d-flex flex-wrap align-items-center justify-content-between p-2 px-3 bg-light rounded-3 border">
                            <div class="d-flex align-items-center gap-3">
                                <span class="small fw-bold text-dark"><i class="bi bi-sliders2 me-1 text-primary"></i>Upload Type:</span>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="material_type" id="type_full_book" value="full_book" <?= $isExistingFullBook ? 'checked' : '' ?> onchange="updateDynamicFields()">
                                    <label class="form-check-label fw-bold text-primary small" for="type_full_book" id="label_type_full_book">
                                        <i class="bi bi-book me-1"></i>Full Textbook / Complete Book
                                    </label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="material_type" id="type_chapter" value="chapter" <?= !$isExistingFullBook ? 'checked' : '' ?> onchange="updateDynamicFields()">
                                    <label class="form-check-label text-dark small" for="type_chapter" id="label_type_chapter">
                                        <i class="bi bi-file-earmark-text me-1"></i>Chapter-Wise PDF
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted d-none d-md-block" id="material_type_hint">Complete book PDF without chapter restrictions</small>
                        </div>
                    </div>

                    <div class="col-md-3" id="chapter_number_col">
                        <label class="form-label" for="chapter_number" id="label_chapter_number">
                            Chapter Number <span class="text-danger" id="req_chapter_number">*</span>
                        </label>
                        <input type="number" class="form-control" id="chapter_number" name="chapter_number" min="0" value="<?= (int)$chapter['chapter_number'] ?>">
                        <small class="text-muted d-block" id="hint_chapter_number" style="font-size: 11px;">e.g. 1, 2, 3...</small>
                    </div>

                    <div class="col-md-9" id="title_col">
                        <label class="form-label d-flex justify-content-between align-items-center mb-1" for="title">
                            <span id="label_title">Document Title <span class="text-danger">*</span></span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal" id="dynamic_type_badge" style="font-size: 11px;">Textbook</span>
                        </label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($chapter['title']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description" id="label_description">Summary / Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= htmlspecialchars($chapter['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Current File Status -->
                    <?php if (!empty($chapter['pdf_file_path'])): ?>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small d-block">Current PDF File:</span>
                                    <strong class="text-dark"><i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i><?= htmlspecialchars($chapter['pdf_file_path']) ?> (<?= $chapter['file_size_mb'] ?> MB)</strong>
                                </div>
                                <?php $previewUrl = resolveMediaUrl($chapter['pdf_file_path'], 'pdfs'); ?>
                                <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>Preview
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Replace PDF Upload -->
                    <div class="col-12">
                        <label class="form-label">Replace PDF Document (Leave blank to keep existing)</label>
                        <div class="upload-drop-zone" onclick="document.getElementById('pdf_file').click();">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <h6 class="fw-bold text-dark mb-1">Click to browse new PDF to replace current file</h6>
                            <p class="text-muted small mb-0" id="file_status_label">Supported format: .pdf</p>
                            <input type="file" id="pdf_file" name="pdf_file" accept=".pdf" style="display: none;" onchange="handleFileSelected(this)">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="pdf_external_url">External PDF URL</label>
                        <input type="url" class="form-control" id="pdf_external_url" name="pdf_external_url" value="<?= htmlspecialchars($chapter['pdf_external_url'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="page_count">Total Pages</label>
                        <input type="number" class="form-control" id="page_count" name="page_count" value="<?= (int)$chapter['page_count'] ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="display_order">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="<?= (int)$chapter['display_order'] ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= $chapter['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $chapter['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="card border rounded-3 p-3 bg-light">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-1 text-primary">
                                        <i class="bi bi-link-45deg me-1"></i>Page-Wise JEE & NEET Question Links
                                    </h6>
                                    <small class="text-muted" id="quick_fill_desc">
                                        Set specific question doc/quiz URLs for Page <span id="dyn_desc_start_page" class="fw-bold text-dark"><?= $savedStartPage ?></span> and all pages after (<span id="dyn_desc_examples" class="text-muted"><?= $savedStartPage ?>, <?= $savedStartPage + 1 ?>, <?= $savedStartPage + 2 ?>...</span>).
                                    </small>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="input-group input-group-sm" style="width: 150px;" title="Set starting page number">
                                        <span class="input-group-text bg-white text-muted fw-bold">Start Pg</span>
                                        <input type="number" min="1" max="1000" class="form-control form-control-sm fw-bold text-primary text-center" id="start_page_num" name="start_page_num" value="<?= $savedStartPage ?>" placeholder="e.g. 4" oninput="handleStartPageChange()">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPageRow()">
                                        <i class="bi bi-plus-circle me-1"></i>Add Page Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_quick_fill" onclick="quickFillFromStartPage()">
                                        <i class="bi bi-magic me-1"></i><span id="quick_fill_btn_text">Quick Fill Page <?= $savedStartPage ?>+</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Table of Custom Page Links -->
                            <div class="table-responsive bg-white rounded border">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 140px;">Page Number</th>
                                            <th>JEE & NEET Question URL / Google Doc</th>
                                            <th style="width: 60px;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="page_links_rows">
                                        <?php if (!empty($savedRows)): ?>
                                            <?php foreach ($savedRows as $pNum => $pUrl): ?>
                                                <tr class="page-link-row">
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-light text-muted">Pg</span>
                                                            <input type="number" min="1" class="form-control form-control-sm row-page-num fw-bold text-primary" value="<?= (int)$pNum ?>" placeholder="e.g. <?= (int)$pNum ?>" oninput="syncPageLinksJson()">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="url" class="form-control form-control-sm row-page-url font-monospace small" value="<?= htmlspecialchars($pUrl) ?>" placeholder="https://docs.google.com/document/d/..." oninput="syncPageLinksJson()">
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="removePageRow(this)" title="Delete Row">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="no_custom_links_msg" class="text-center py-3 text-muted small" style="<?= !empty($savedRows) ? 'display:none;' : '' ?>">
                                <i class="bi bi-info-circle me-1"></i>No page links added yet. Click <strong>+ Add Page Link</strong> above to add a link for a specific page.
                            </div>

                            <!-- Hidden field that stores the JSON string for backend processing -->
                            <input type="hidden" id="page_links" name="page_links" value="<?= htmlspecialchars($chapter['page_links'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <a href="<?= url('modules/chapters/index.php') ?>" class="btn btn-light border me-2">Cancel</a>
                        <button type="submit" class="btn btn-bb-primary">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function handleFileSelected(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        document.getElementById('file_status_label').innerHTML = 
            `<strong class="text-primary"><i class="bi bi-check2-circle me-1"></i>New File: ${file.name} (${sizeMB} MB)</strong>`;
    }
}

// Page Links Manager Script
function addPageRow(pageNum = '', url = '') {
    const tbody = document.getElementById('page_links_rows');
    const msg = document.getElementById('no_custom_links_msg');
    if (msg) msg.style.display = 'none';

    const tr = document.createElement('tr');
    tr.className = 'page-link-row';
    tr.innerHTML = `
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted">Pg</span>
                <input type="number" min="1" class="form-control form-control-sm row-page-num fw-bold text-primary" value="${pageNum}" placeholder="e.g. 4" oninput="syncPageLinksJson()">
            </div>
        </td>
        <td>
            <input type="url" class="form-control form-control-sm row-page-url font-monospace small" value="${url}" placeholder="https://docs.google.com/document/d/..." oninput="syncPageLinksJson()">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="removePageRow(this)" title="Delete Row">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    syncPageLinksJson();
}

function removePageRow(btn) {
    btn.closest('tr').remove();
    const rows = document.querySelectorAll('.page-link-row');
    if (rows.length === 0) {
        const msg = document.getElementById('no_custom_links_msg');
        if (msg) msg.style.display = 'block';
    }
    syncPageLinksJson();
}

function handleStartPageChange() {
    const input = document.getElementById('start_page_num');
    const val = parseInt(input?.value || '4', 10);
    const startPage = (!isNaN(val) && val >= 1) ? val : 4;

    const btnText = document.getElementById('quick_fill_btn_text');
    if (btnText) {
        btnText.textContent = `Quick Fill Page ${startPage}+`;
    }
    const descStart = document.getElementById('dyn_desc_start_page');
    if (descStart) {
        descStart.textContent = startPage;
    }
    const descExamples = document.getElementById('dyn_desc_examples');
    if (descExamples) {
        descExamples.textContent = `${startPage}, ${startPage + 1}, ${startPage + 2}...`;
    }
    syncPageLinksJson();
}

function quickFillFromStartPage() {
    const totalPagesInput = document.getElementById('page_count') || document.querySelector('input[name="page_count"]');
    const totalPages = parseInt(totalPagesInput?.value || '10', 10);
    const startPageInput = document.getElementById('start_page_num');
    const startPage = Math.max(1, parseInt(startPageInput?.value || '4', 10));

    // If an existing row has a URL, use it as template URL
    let templateDailyUrl = '';
    const existingUrlInputs = document.querySelectorAll('.row-page-url');
    for (const inp of existingUrlInputs) {
        if (inp.value && inp.value.trim().length > 0) {
            templateDailyUrl = inp.value.trim();
            break;
        }
    }

    const endPage = Math.min(Math.max(totalPages, startPage), startPage + 25);
    for (let p = startPage; p <= endPage; p++) {
        const existing = Array.from(document.querySelectorAll('.row-page-num')).some(input => parseInt(input.value, 10) === p);
        if (!existing) {
            addPageRow(p, templateDailyUrl);
        }
    }
}

function syncPageLinksJson() {
    const startPageVal = parseInt(document.getElementById('start_page_num')?.value || '4', 10);
    const startPage = (!isNaN(startPageVal) && startPageVal >= 1) ? startPageVal : 4;
    const rows = document.querySelectorAll('.page-link-row');
    const mapping = {};

    mapping['start_page'] = startPage;

    rows.forEach(tr => {
        const pageNum = tr.querySelector('.row-page-num').value.trim();
        const url = tr.querySelector('.row-page-url').value.trim();
        if (pageNum && url) {
            mapping[pageNum] = url;
        }
    });

    const hiddenInput = document.getElementById('page_links');
    if (Object.keys(mapping).length > 0) {
        hiddenInput.value = JSON.stringify(mapping);
    } else {
        hiddenInput.value = '';
    }
}

// Dynamic module & requirement adapter
function updateDynamicFields() {
    const moduleSelect = document.getElementById('module_id');
    if (!moduleSelect) return;
    const selectedOption = moduleSelect.options[moduleSelect.selectedIndex];
    const slug = (selectedOption ? selectedOption.getAttribute('data-slug') : '') || 'textbooks';
    const moduleTitle = (selectedOption ? selectedOption.getAttribute('data-title') : '') || 'Textbook';

    const fullBookRadio = document.getElementById('type_full_book');
    const isFullBook = fullBookRadio ? fullBookRadio.checked : true;
    const materialTypeWrapper = document.getElementById('material_type_wrapper');
    const chapterNumCol = document.getElementById('chapter_number_col');
    const titleCol = document.getElementById('title_col');
    const labelChapNum = document.getElementById('label_chapter_number');
    const reqChapNum = document.getElementById('req_chapter_number');
    const inputChapNum = document.getElementById('chapter_number');
    const hintChapNum = document.getElementById('hint_chapter_number');
    const labelTitle = document.getElementById('label_title');
    const inputTitle = document.getElementById('title');
    const labelDesc = document.getElementById('label_description');
    const inputDesc = document.getElementById('description');
    const dynamicBadge = document.getElementById('dynamic_type_badge');
    const typeHint = document.getElementById('material_type_hint');

    if (slug === 'textbooks' || slug === 'textbook') {
        if (materialTypeWrapper) materialTypeWrapper.style.display = 'block';
        
        if (isFullBook) {
            // Full Textbook Mode - Chapter Number is NOT required and NOT presented as a chapter!
            if (labelTitle) labelTitle.innerHTML = 'Textbook / Book Title <span class="text-danger">*</span>';
            if (inputTitle) inputTitle.placeholder = 'e.g. Mathematics Textbook - GSEB Class 10 (Full Book)';
            if (labelDesc) labelDesc.textContent = 'Textbook Summary / Description';
            if (inputDesc) inputDesc.placeholder = 'Brief outline of concepts or syllabus covered in this textbook';
            if (dynamicBadge) {
                dynamicBadge.textContent = 'Complete Textbook';
                dynamicBadge.className = 'badge bg-primary text-white fw-normal';
            }
            if (typeHint) typeHint.textContent = 'Complete textbook PDF without chapter restrictions';

            // Hide chapter number column so user is NOT forced to enter a chapter
            if (chapterNumCol) chapterNumCol.style.display = 'none';
            if (titleCol) titleCol.className = 'col-md-12';
            if (inputChapNum) {
                inputChapNum.required = false;
                if (!inputChapNum.value || inputChapNum.value === '0') {
                    inputChapNum.value = '1';
                }
            }
        } else {
            // Chapter-wise PDF Mode
            if (chapterNumCol) {
                chapterNumCol.style.display = 'block';
                chapterNumCol.className = 'col-md-3';
            }
            if (titleCol) titleCol.className = 'col-md-9';
            if (labelChapNum) labelChapNum.innerHTML = 'Chapter Number <span class="text-danger">*</span>';
            if (inputChapNum) inputChapNum.required = true;
            if (hintChapNum) hintChapNum.textContent = 'e.g. 1, 2, 3...';
            if (labelTitle) labelTitle.innerHTML = 'Chapter Title <span class="text-danger">*</span>';
            if (inputTitle) inputTitle.placeholder = 'e.g. Chapter 1: Real Numbers';
            if (labelDesc) labelDesc.textContent = 'Chapter Summary / Description';
            if (inputDesc) inputDesc.placeholder = 'Brief outline of concepts covered in this chapter';
            if (dynamicBadge) {
                dynamicBadge.textContent = 'Chapter PDF';
                dynamicBadge.className = 'badge bg-info-subtle text-info border border-info-subtle fw-normal';
            }
            if (typeHint) typeHint.textContent = 'Individual chapter PDF for this subject';
        }
    } else if (slug === 'blueprint') {
        if (materialTypeWrapper) materialTypeWrapper.style.display = 'none';
        if (chapterNumCol) {
            chapterNumCol.style.display = 'block';
            chapterNumCol.className = 'col-md-3';
        }
        if (titleCol) titleCol.className = 'col-md-9';
        if (labelChapNum) labelChapNum.innerHTML = 'Set / Year # <small class="text-muted fw-normal">(Optional)</small>';
        if (inputChapNum) inputChapNum.required = false;
        if (hintChapNum) hintChapNum.textContent = 'Default is 1';
        if (labelTitle) labelTitle.innerHTML = 'Blueprint Title <span class="text-danger">*</span>';
        if (inputTitle) inputTitle.placeholder = 'e.g. GSEB Board Exam Annual Blueprint 2026';
        if (labelDesc) labelDesc.textContent = 'Blueprint Details / Weightage';
        if (inputDesc) inputDesc.placeholder = 'Exam format, question weightage and marks distribution';
        if (dynamicBadge) {
            dynamicBadge.textContent = 'Blueprint';
            dynamicBadge.className = 'badge bg-success-subtle text-success border border-success-subtle fw-normal';
        }
    } else if (slug === 'pyqs') {
        if (materialTypeWrapper) materialTypeWrapper.style.display = 'none';
        if (chapterNumCol) {
            chapterNumCol.style.display = 'block';
            chapterNumCol.className = 'col-md-3';
        }
        if (titleCol) titleCol.className = 'col-md-9';
        if (labelChapNum) labelChapNum.innerHTML = 'Paper Order / Year # <small class="text-muted fw-normal">(Optional)</small>';
        if (inputChapNum) inputChapNum.required = false;
        if (hintChapNum) hintChapNum.textContent = 'Default is 1';
        if (labelTitle) labelTitle.innerHTML = 'Question Paper Title <span class="text-danger">*</span>';
        if (inputTitle) inputTitle.placeholder = 'e.g. March 2024 Board Exam Question Paper & Solutions';
        if (labelDesc) labelDesc.textContent = 'Paper Summary / Instructions';
        if (inputDesc) inputDesc.placeholder = 'Exam session, passing marks and general instructions';
        if (dynamicBadge) {
            dynamicBadge.textContent = 'PYQ Paper';
            dynamicBadge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle fw-normal';
        }
    } else if (slug === 'paper_sets') {
        if (materialTypeWrapper) materialTypeWrapper.style.display = 'none';
        if (chapterNumCol) {
            chapterNumCol.style.display = 'block';
            chapterNumCol.className = 'col-md-3';
        }
        if (titleCol) titleCol.className = 'col-md-9';
        if (labelChapNum) labelChapNum.innerHTML = 'Set Number <small class="text-muted fw-normal">(Optional)</small>';
        if (inputChapNum) inputChapNum.required = false;
        if (hintChapNum) hintChapNum.textContent = 'e.g. 1 for Set 1';
        if (labelTitle) labelTitle.innerHTML = 'Paper Set Title <span class="text-danger">*</span>';
        if (inputTitle) inputTitle.placeholder = 'e.g. Model Practice Paper Set 1 (March 2026)';
        if (labelDesc) labelDesc.textContent = 'Paper Set Description';
        if (inputDesc) inputDesc.placeholder = 'Sample question paper with answer keys';
        if (dynamicBadge) {
            dynamicBadge.textContent = 'Paper Set';
            dynamicBadge.className = 'badge bg-purple-subtle text-primary border border-primary-subtle fw-normal';
        }
    } else if (slug === 'mimp') {
        if (materialTypeWrapper) materialTypeWrapper.style.display = 'none';
        if (chapterNumCol) {
            chapterNumCol.style.display = 'block';
            chapterNumCol.className = 'col-md-3';
        }
        if (titleCol) titleCol.className = 'col-md-9';
        if (labelChapNum) labelChapNum.innerHTML = 'Part / Section # <small class="text-muted fw-normal">(Optional)</small>';
        if (inputChapNum) inputChapNum.required = false;
        if (hintChapNum) hintChapNum.textContent = 'Default is 1';
        if (labelTitle) labelTitle.innerHTML = 'Material Title <span class="text-danger">*</span>';
        if (inputTitle) inputTitle.placeholder = 'e.g. Top 50 Most Important Board Questions & Solutions';
        if (labelDesc) labelDesc.textContent = 'Material Summary / Description';
        if (inputDesc) inputDesc.placeholder = 'High scoring formulas, key theorems, and expected questions';
        if (dynamicBadge) {
            dynamicBadge.textContent = 'M.IMP Material';
            dynamicBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle fw-normal';
        }
    } else {
        if (materialTypeWrapper) materialTypeWrapper.style.display = 'none';
        if (chapterNumCol) {
            chapterNumCol.style.display = 'block';
            chapterNumCol.className = 'col-md-3';
        }
        if (titleCol) titleCol.className = 'col-md-9';
        if (labelChapNum) labelChapNum.innerHTML = 'Item / Part # <small class="text-muted fw-normal">(Optional)</small>';
        if (inputChapNum) inputChapNum.required = false;
        if (labelTitle) labelTitle.innerHTML = moduleTitle + ' Title <span class="text-danger">*</span>';
        if (inputTitle) inputTitle.placeholder = 'e.g. ' + moduleTitle + ' Title / Document Name';
        if (labelDesc) labelDesc.textContent = moduleTitle + ' Summary / Description';
        if (dynamicBadge) {
            dynamicBadge.textContent = moduleTitle;
            dynamicBadge.className = 'badge bg-secondary-subtle text-secondary fw-normal';
        }
    }
}

function handleModuleChanged() {
    updateDynamicFields();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    updateDynamicFields();

    const existingRows = document.querySelectorAll('.page-link-row');
    const initialVal = document.getElementById('page_links').value.trim();
    if (initialVal && initialVal.startsWith('{') && initialVal.endsWith('}')) {
        try {
            const parsed = JSON.parse(initialVal);
            if (parsed.start_page) {
                const sVal = parseInt(parsed.start_page, 10);
                if (!isNaN(sVal) && sVal >= 1) {
                    const input = document.getElementById('start_page_num');
                    if (input) input.value = sVal;
                    handleStartPageChange();
                }
            }
            if (existingRows.length === 0) {
                for (const [key, val] of Object.entries(parsed)) {
                    if (key && val && !isNaN(parseInt(key, 10))) {
                        addPageRow(key, val);
                    }
                }
            }
        } catch (e) {
            console.error('Error parsing initial page_links:', e);
        }
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
