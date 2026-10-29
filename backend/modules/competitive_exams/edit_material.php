<?php
/**
 * Edit Competitive Exam Material PDF
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid material ID.');
    header('Location: ' . url('modules/competitive_exams/index.php'));
    exit;
}

$pageTitle = 'Edit Competitive Exam Material';
$pageSubtitle = 'Update exam question paper, formula handbook or PDF notes';
$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM competitive_exam_materials WHERE id = :id");
$stmt->execute([':id' => $id]);
$material = $stmt->fetch();

if (!$material) {
    flash('error', 'Exam material not found.');
    header('Location: ' . url('modules/competitive_exams/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $sizeMB = round((int)$_SERVER['CONTENT_LENGTH'] / (1024 * 1024), 1);
        flash('error', "The uploaded file ({$sizeMB} MB) exceeds PHP's post_max_size limit.");
    } else {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrf)) {
            flash('error', 'Invalid security token. Please refresh the page and try again.');
        } else {
            $title = trim($_POST['title'] ?? '');
            $examName = trim($_POST['exam_name'] ?? '');
            $materialType = trim($_POST['material_type'] ?? 'PDF Material');
            $description = trim($_POST['description'] ?? '');
            $pageCount = (int)($_POST['page_count'] ?? 1);
            $externalUrl = trim($_POST['pdf_external_url'] ?? '');
            $displayOrder = (int)($_POST['display_order'] ?? 1);
            $status = $_POST['status'] ?? 'active';
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

            $pdfFileName = $material['pdf_file_path'];
            $fileSizeMB = $material['file_size_mb'];

            // Handle New PDF File Upload (direct client-side upload or standard)
            if (!empty($_POST['direct_uploaded_file'])) {
                $pdfFileName = trim($_POST['direct_uploaded_file']);
                $fileSizeMB = (float)($_POST['direct_file_size_mb'] ?? 0.0);
            } elseif (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = uploadFile($_FILES['pdf_file'], PDF_UPLOADS_PATH, ['pdf'], 500);
                if ($uploadResult['status']) {
                    // Remove old local file if replaced
                    if (!empty($pdfFileName) && file_exists(PDF_UPLOADS_PATH . $pdfFileName)) {
                        @unlink(PDF_UPLOADS_PATH . $pdfFileName);
                    }
                    $pdfFileName = $uploadResult['filename'];
                    $fileSizeMB = $uploadResult['size_mb'];
                } else {
                    flash('error', $uploadResult['message']);
                }
            }

            if (empty($title)) {
                flash('error', 'Material / PDF title is required.');
            } elseif (empty($pdfFileName) && empty($externalUrl)) {
                flash('error', 'Please upload a PDF file or provide an external PDF URL.');
            } else {
                try {
                    // Match existing competitive exam ID if available
                    $examId = $material['exam_id'] ?? null;
                    if (!empty($examName)) {
                        $findExam = $db->prepare("SELECT id FROM competitive_exams WHERE LOWER(title) = LOWER(:name) OR LOWER(slug) = LOWER(:slug) OR LOWER(exam_code) = LOWER(:code) LIMIT 1");
                        $findExam->execute([':name' => $examName, ':slug' => $examName, ':code' => $examName]);
                        $matched = $findExam->fetch();
                        if ($matched) {
                            $examId = (int)$matched['id'];
                        }
                    }

                    $stmt = $db->prepare("
                        UPDATE competitive_exam_materials
                        SET title = :title,
                            exam_id = :exam_id,
                            exam_name = :exam_name,
                            subject_name = :sub_name,
                            material_type = :mat_type,
                            description = :description,
                            pdf_file_path = :pdf_file,
                            pdf_external_url = :ext_url,
                            page_count = :page_count,
                            file_size_mb = :file_size,
                            page_links = :page_links,
                            display_order = :display_order,
                            status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':title' => $title,
                        ':exam_id' => $examId,
                        ':exam_name' => $examName,
                        ':sub_name' => $examName ?: 'General',
                        ':mat_type' => $materialType,
                        ':description' => $description,
                        ':pdf_file' => $pdfFileName,
                        ':ext_url' => $externalUrl,
                        ':page_count' => $pageCount,
                        ':file_size' => $fileSizeMB,
                        ':page_links' => $pageLinks,
                        ':display_order' => $displayOrder,
                        ':status' => $status,
                        ':id' => $id,
                    ]);

                    flash('success', 'Competitive Exam material updated successfully!');
                    header('Location: ' . url('modules/competitive_exams/index.php'));
                    exit;
                } catch (PDOException $e) {
                    flash('error', 'Database Error: ' . $e->getMessage());
                }
            }
        }
    }
}

// Parse existing page_links for server-side pre-rendering
$savedStartPage = 4;
$savedRows = [];
if (!empty($material['page_links'])) {
    $rawLinks = trim($material['page_links']);
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
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Competitive Exam Material
                </h5>
                <small class="text-muted">Update details and attached PDF file</small>
            </div>
            <a href="<?= url('modules/competitive_exams/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to List
            </a>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">

            <!-- Material Details Card -->
            <div class="bb-card mb-3">
                <div class="bb-card-header">
                    <h6 class="fw-bold mb-0 text-dark">1. Material Information</h6>
                </div>
                <div class="bb-card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-dark">
                                Material / PDF Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($_POST['title'] ?? $material['title']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Exam / Category Name</label>
                            <input type="text" name="exam_name" class="form-control" placeholder="e.g. UPSC, SSC, Banking, GPSC, General..." value="<?= htmlspecialchars($_POST['exam_name'] ?? $material['exam_name'] ?? '') ?>" list="examSuggestions">
                            <datalist id="examSuggestions">
                                <option value="UPSC">
                                <option value="SSC">
                                <option value="Banking / IBPS">
                                <option value="Railways (RRB)">
                                <option value="GPSC / State PSC">
                                <option value="Defence / NDA / CDS">
                                <option value="JEE">
                                <option value="NEET">
                                <option value="GATE">
                                <option value="General Aptitude">
                            </datalist>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-dark">Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? $material['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PDF Upload Card -->
            <div class="bb-card mb-3">
                <div class="bb-card-header">
                    <h6 class="fw-bold mb-0 text-dark">2. PDF Attachment</h6>
                </div>
                <div class="bb-card-body p-4">
                    <div class="row g-3">
                        <?php if (!empty($material['pdf_file_path'])): ?>
                            <div class="col-12">
                                <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i>
                                        <strong>Current File:</strong> <?= htmlspecialchars($material['pdf_file_path']) ?>
                                        <small class="text-muted ms-2">(<?= $material['file_size_mb'] ?> MB)</small>
                                    </div>
                                    <?php $pdfUrl = resolveMediaUrl($material['pdf_file_path'], 'pdfs'); ?>
                                    <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>View Current PDF
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-dark">Replace PDF File (Optional)</label>
                            <input type="file" name="pdf_file" class="form-control" accept=".pdf,application/pdf">
                            <div class="form-text small text-muted">Leave empty to keep current PDF file.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-dark">External PDF URL</label>
                            <input type="url" name="pdf_external_url" class="form-control" placeholder="https://example.com/file.pdf" value="<?= htmlspecialchars($_POST['pdf_external_url'] ?? $material['pdf_external_url'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Page Count</label>
                            <input type="number" name="page_count" class="form-control" min="1" value="<?= htmlspecialchars($_POST['page_count'] ?? $material['page_count']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Display Order</label>
                            <input type="number" name="display_order" class="form-control" min="1" value="<?= htmlspecialchars($_POST['display_order'] ?? $material['display_order']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= ($_POST['status'] ?? $material['status']) === 'active' ? 'selected' : '' ?>>Active (Visible)</option>
                                <option value="inactive" <?= ($_POST['status'] ?? $material['status']) === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
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
                                <input type="hidden" id="page_links" name="page_links" value="<?= htmlspecialchars($material['page_links'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="<?= url('modules/competitive_exams/index.php') ?>" class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-bb-primary px-4">
                    <i class="bi bi-check-circle me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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
    const totalPagesInput = document.querySelector('input[name="page_count"]');
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
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
