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
$savedFirstUrl = !empty($savedRows) ? reset($savedRows) : '';
$defaultEndPage = (int)($chapter['page_count'] ?? 0) > 0 ? (int)$chapter['page_count'] : $savedStartPage;

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
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="fw-bold mb-0 text-primary">
                                            <i class="bi bi-link-45deg me-1"></i>Page-Wise JEE & NEET Question Links
                                        </h6>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" id="page_rows_badge"><?= count($savedRows) ?> Pages</span>
                                    </div>
                                    <small class="text-muted" id="quick_fill_desc">
                                        Set specific question doc/quiz URLs for Page <span id="dyn_desc_start_page" class="fw-bold text-dark"><?= $savedStartPage ?></span> and all pages after (<span id="dyn_desc_examples" class="text-muted"><?= $savedStartPage ?>, <?= $savedStartPage + 1 ?>, <?= $savedStartPage + 2 ?>...</span>).
                                    </small>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="input-group input-group-sm" style="width: 135px;" title="Set starting page number">
                                        <span class="input-group-text bg-white text-muted fw-bold">Start Pg</span>
                                        <input type="number" min="1" max="5000" class="form-control form-control-sm fw-bold text-primary text-center" id="start_page_num" name="start_page_num" value="<?= $savedStartPage ?>" placeholder="e.g. 5" oninput="handleStartPageChange()">
                                    </div>
                                    <div class="input-group input-group-sm" style="width: 135px;" title="Set ending page number (defaults to Total Pages)">
                                        <span class="input-group-text bg-white text-muted fw-bold">End Pg</span>
                                        <input type="number" min="1" max="5000" class="form-control form-control-sm fw-bold text-primary text-center" id="end_page_num" value="<?= (int)($chapter['page_count'] ?? 0) > 0 ? (int)$chapter['page_count'] : '' ?>" placeholder="e.g. 322" oninput="handleStartPageChange()">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPageRow()" title="Add single page row">
                                        <i class="bi bi-plus-circle me-1"></i>Add Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary text-white shadow-sm" id="btn_quick_fill" onclick="quickFillFromStartPage()" title="Generate page link rows for the exact page range">
                                        <i class="bi bi-magic me-1"></i><span id="quick_fill_btn_text">Quick Fill Page <?= $savedStartPage ?>+</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyTemplateUrlToAll()" title="Copy first URL to all pages">
                                        <i class="bi bi-copy me-1"></i>Fill All URLs
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllPageRows()" title="Remove all page link rows">
                                        <i class="bi bi-trash3 me-1"></i>Clear
                                    </button>
                                </div>
                            </div>

                            <!-- Master Google Doc Quick-Setter & Access Verifier -->
                            <div class="card border-primary border-opacity-25 bg-white p-3 rounded-3 mb-3 shadow-sm">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold mb-0 text-dark small" for="master_doc_url">
                                        <i class="bi bi-google text-danger me-1"></i>Master Google Doc / Question URL 
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">Auto-Assign to All Pages</span>
                                    </label>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle small" id="doc_clean_preview_badge" style="display:none;">
                                        <i class="bi bi-eye me-1"></i>Auto-converted to preview mode
                                    </span>
                                </div>

                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-link-45deg"></i></span>
                                    <input type="url" class="form-control font-monospace small" id="master_doc_url" placeholder="Paste Google Doc link: e.g. https://docs.google.com/document/d/.../edit" value="<?= htmlspecialchars($savedFirstUrl ?? '') ?>" oninput="onMasterDocInput()">
                                    <button type="button" class="btn btn-outline-info" id="btn_verify_access" onclick="verifyMasterDocAccess()" title="Check if Google Doc is public or sends email access requests">
                                        <i class="bi bi-shield-check me-1"></i>Check Permission
                                    </button>
                                    <button type="button" class="btn btn-success text-white fw-bold px-3 shadow-sm" id="btn_apply_master_doc" onclick="applyMasterDocToRange()" title="Auto-fill this link to all pages in range">
                                        <i class="bi bi-magic me-1"></i>Auto-Set to Pages (<span id="btn_apply_range_label">Pg <?= $savedStartPage ?> to <?= !empty($defaultEndPage) ? $defaultEndPage : $savedStartPage ?></span>)
                                    </button>
                                </div>

                                <!-- Live Permission Status Feedback Box -->
                                <div id="master_doc_status_box" style="display: none;" class="mb-2"></div>

                                <!-- Fix 'Request Access' emails guidance note -->
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top mt-1">
                                    <div class="small text-muted">
                                        <i class="bi bi-info-circle text-primary me-1"></i><strong>Stop "Request Access" emails:</strong> In Google Docs, click <strong>Share</strong> &rarr; change General access to <strong>"Anyone with the link"</strong> (Viewer). Links are auto-converted to <code>/preview</code> so mobile students view directly without Google sign-in.
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#bulkPasteModal">
                                            <i class="bi bi-card-text me-1"></i>Bulk Paste List
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Table of Custom Page Links with Scrollable Container & Sticky Header -->
                            <div class="table-responsive bg-white rounded border" style="max-height: 480px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="table-light sticky-top" style="position: sticky; top: 0; z-index: 2; box-shadow: 0 1px 2px rgba(0,0,0,0.06);">
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

                            <!-- Bulk Paste Modal -->
                            <div class="modal fade" id="bulkPasteModal" tabindex="-1" aria-labelledby="bulkPasteModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold" id="bulkPasteModalLabel">
                                                <i class="bi bi-card-text text-primary me-2"></i>Bulk Paste Page Links
                                            </h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-muted mb-2">
                                                Paste links below. You can paste:
                                                <br>&bull; <strong>Line-by-line URLs:</strong> sequentially assigned starting from Start Page.
                                                <br>&bull; <strong>Page-specific lines:</strong> e.g. <code>5: https://...</code>, <code>Pg 6 - https://...</code>, or <code>Page 7: https://...</code>
                                            </p>
                                            <textarea id="bulk_paste_content" class="form-control font-monospace small" rows="8" placeholder="5: https://docs.google.com/document/d/...&#10;6: https://docs.google.com/document/d/...&#10;7: https://docs.google.com/document/d/..."></textarea>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-primary" onclick="applyBulkPaste()">Apply Links</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

// HTML Escape helper
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Convert any Google Doc URL to clean /preview mode to stop editor UI & account barriers
function normalizeGoogleDocUrl(url) {
    if (!url) return '';
    const trimmed = url.trim();
    const match = trimmed.match(/docs\.google\.com\/document\/d\/([a-zA-Z0-9_-]+)/i);
    if (match && match[1]) {
        return `https://docs.google.com/document/d/${match[1]}/preview`;
    }
    return trimmed;
}

function onMasterDocInput() {
    const input = document.getElementById('master_doc_url');
    if (!input) return;
    const rawVal = input.value.trim();
    const clean = normalizeGoogleDocUrl(rawVal);
    const badge = document.getElementById('doc_clean_preview_badge');
    if (clean !== rawVal && clean.includes('/preview') && badge) {
        badge.style.display = 'inline-block';
    } else if (badge) {
        badge.style.display = 'none';
    }
}

function verifyMasterDocAccess(urlToTest = null) {
    const input = document.getElementById('master_doc_url');
    const url = (urlToTest || input?.value || '').trim();
    const statusBox = document.getElementById('master_doc_status_box');
    
    if (!url) {
        alert('Please enter or paste a Google Doc URL first.');
        input?.focus();
        return;
    }

    if (statusBox) {
        statusBox.style.display = 'block';
        statusBox.innerHTML = `
            <div class="alert alert-info py-2 px-3 mb-0 small d-flex align-items-center">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                <span>Testing link access and permissions...</span>
            </div>
        `;
    }

    fetch('../../api/verify_google_doc.php?url=' + encodeURIComponent(url))
        .then(res => res.json())
        .then(data => {
            if (!statusBox) return;
            if (data.status === 'success') {
                if (data.is_google_doc) {
                    if (data.is_public) {
                        statusBox.innerHTML = `
                            <div class="alert alert-success py-2 px-3 mb-0 small d-flex align-items-center justify-content-between">
                                <div>
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Access OK (Public):</strong> All students can view this document directly! No email access requests will be sent to your Gmail.
                                </div>
                                <span class="badge bg-success">Public / Preview</span>
                            </div>
                        `;
                    } else {
                        const editUrl = data.clean_preview_url.replace('/preview', '/edit');
                        statusBox.innerHTML = `
                            <div class="alert alert-danger py-2 px-3 mb-0 small">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-exclamation-triangle-fill text-danger fs-6 mt-1"></i>
                                    <div>
                                        <strong>Restricted Permission Detected!</strong>
                                        <div class="mt-1">Students opening this will see <em>"You need access &rarr; Request access"</em>, sending emails to your Gmail inbox.</div>
                                        <div class="mt-2">
                                            <strong>Easy 3-Step Fix:</strong>
                                            <ol class="mb-1 ps-3">
                                                <li>Open in Google Docs: <a href="${editUrl}" target="_blank" class="fw-bold text-decoration-underline text-danger">Click here to open Document</a></li>
                                                <li>Click the blue <strong>Share</strong> button (top-right).</li>
                                                <li>Under <em>General access</em>, change <strong>Restricted</strong> to <strong>"Anyone with the link"</strong> (Role: Viewer), then click <strong>Done</strong>.</li>
                                            </ol>
                                            <div class="mt-1 text-muted">After changing, click <strong>Check Permission</strong> again to verify!</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    statusBox.innerHTML = `
                        <div class="alert alert-secondary py-2 px-3 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>Standard link configured: <code>${escapeHtml(url)}</code>
                        </div>
                    `;
                }
            } else {
                statusBox.innerHTML = `
                    <div class="alert alert-warning py-2 px-3 mb-0 small">
                        <i class="bi bi-exclamation-circle me-1"></i>${escapeHtml(data.message || 'Could not verify link.')}
                    </div>
                `;
            }
        })
        .catch(err => {
            if (statusBox) {
                statusBox.innerHTML = `
                    <div class="alert alert-secondary py-2 px-3 mb-0 small">
                        <i class="bi bi-check2 me-1"></i>Link configured.
                    </div>
                `;
            }
        });
}

function updateRangeLabels() {
    const startInput = document.getElementById('start_page_num');
    const startVal = parseInt(startInput?.value || '1', 10);
    const startPage = (!isNaN(startVal) && startVal >= 1) ? startVal : 1;

    const totalPagesInput = document.getElementById('page_count') || document.querySelector('input[name="page_count"]');
    const totalPages = parseInt(totalPagesInput?.value || '0', 10);

    const endInput = document.getElementById('end_page_num');
    let endVal = parseInt(endInput?.value || '0', 10);
    if ((isNaN(endVal) || endVal <= 0) && totalPages > 0) {
        endVal = totalPages;
        if (endInput) endInput.value = totalPages;
    }

    const endPage = (endVal >= startPage) ? endVal : (totalPages >= startPage ? totalPages : startPage);
    const count = Math.max(1, endPage - startPage + 1);

    const rangeLabel = document.getElementById('btn_apply_range_label');
    if (rangeLabel) {
        rangeLabel.textContent = `Pg ${startPage} to ${endPage} (${count} pgs)`;
    }

    const btnText = document.getElementById('quick_fill_btn_text');
    if (btnText) {
        if (endPage > startPage) {
            btnText.textContent = `Quick Fill Pages ${startPage} to ${endPage} (${count} pgs)`;
        } else {
            btnText.textContent = `Quick Fill Page ${startPage}+`;
        }
    }

    const descStart = document.getElementById('dyn_desc_start_page');
    if (descStart) descStart.textContent = startPage;

    const descExamples = document.getElementById('dyn_desc_examples');
    if (descExamples) descExamples.textContent = `${startPage}, ${startPage + 1}, ${startPage + 2}...`;
}

function applyMasterDocToRange() {
    const masterInput = document.getElementById('master_doc_url');
    let url = masterInput?.value?.trim() || '';
    if (!url) {
        alert('Please paste a Google Doc or Question link first.');
        masterInput?.focus();
        return;
    }

    url = normalizeGoogleDocUrl(url);
    masterInput.value = url;
    onMasterDocInput();

    const startPageVal = parseInt(document.getElementById('start_page_num')?.value || '1', 10);
    const startPage = (!isNaN(startPageVal) && startPageVal >= 1) ? startPageVal : 1;

    const totalPagesInput = document.getElementById('page_count') || document.querySelector('input[name="page_count"]');
    const totalPages = parseInt(totalPagesInput?.value || '0', 10);

    const endInput = document.getElementById('end_page_num');
    let customEndPage = parseInt(endInput?.value || '0', 10);

    let endPage = 0;
    if (!isNaN(customEndPage) && customEndPage >= startPage) {
        endPage = customEndPage;
    } else if (!isNaN(totalPages) && totalPages >= startPage) {
        endPage = totalPages;
    } else if (!isNaN(totalPages) && totalPages > 0) {
        endPage = totalPages;
    } else {
        endPage = startPage + 25;
    }

    if (endPage < startPage) {
        alert(`End Page (${endPage}) cannot be less than Start Page (${startPage}).`);
        return;
    }

    if (endPage - startPage > 2000) {
        if (!confirm(`You are about to assign this link to ${endPage - startPage + 1} pages. Continue?`)) {
            return;
        }
    }

    // Map existing rows by page number
    const rowMap = new Map();
    document.querySelectorAll('.page-link-row').forEach(tr => {
        const pNum = parseInt(tr.querySelector('.row-page-num')?.value || '0', 10);
        if (pNum > 0) rowMap.set(pNum, tr);
    });

    const tbody = document.getElementById('page_links_rows');
    const fragment = document.createDocumentFragment();
    let updatedCount = 0;
    let addedCount = 0;

    for (let p = startPage; p <= endPage; p++) {
        if (rowMap.has(p)) {
            const tr = rowMap.get(p);
            const urlInp = tr.querySelector('.row-page-url');
            if (urlInp) {
                urlInp.value = url;
                updatedCount++;
            }
        } else {
            fragment.appendChild(createPageRowElement(p, url));
            addedCount++;
        }
    }

    if (addedCount > 0) {
        tbody.appendChild(fragment);
        const msg = document.getElementById('no_custom_links_msg');
        if (msg) msg.style.display = 'none';
    }

    // Sort table rows numerically
    const rows = Array.from(tbody.querySelectorAll('.page-link-row'));
    rows.sort((a, b) => {
        const valA = parseInt(a.querySelector('.row-page-num')?.value || '0', 10);
        const valB = parseInt(b.querySelector('.row-page-num')?.value || '0', 10);
        return valA - valB;
    });
    rows.forEach(r => tbody.appendChild(r));

    syncPageLinksJson();
    verifyMasterDocAccess(url);
}

function applyBulkPaste() {
    const textarea = document.getElementById('bulk_paste_content');
    const content = textarea?.value?.trim() || '';
    if (!content) {
        alert('Please paste some links first.');
        return;
    }

    const lines = content.split('\n').map(l => l.trim()).filter(l => l.length > 0);
    const startPageVal = parseInt(document.getElementById('start_page_num')?.value || '1', 10);
    let currentAutoPage = (!isNaN(startPageVal) && startPageVal >= 1) ? startPageVal : 1;

    const rowMap = new Map();
    document.querySelectorAll('.page-link-row').forEach(tr => {
        const pNum = parseInt(tr.querySelector('.row-page-num')?.value || '0', 10);
        if (pNum > 0) rowMap.set(pNum, tr);
    });

    const tbody = document.getElementById('page_links_rows');
    const fragment = document.createDocumentFragment();

    lines.forEach(line => {
        let pageNum = null;
        let url = line;

        const prefixMatch = line.match(/^(?:page|pg)?\s*(\d+)\s*[:=\-–]\s*(https?:\/\/.+)$/i);
        if (prefixMatch) {
            pageNum = parseInt(prefixMatch[1], 10);
            url = prefixMatch[2].trim();
        } else if (/^https?:\/\//i.test(line)) {
            pageNum = currentAutoPage++;
            url = line.trim();
        }

        if (pageNum && url) {
            url = normalizeGoogleDocUrl(url);
            if (rowMap.has(pageNum)) {
                const tr = rowMap.get(pageNum);
                const uInp = tr.querySelector('.row-page-url');
                if (uInp) uInp.value = url;
            } else {
                fragment.appendChild(createPageRowElement(pageNum, url));
            }
        }
    });

    if (fragment.children.length > 0) {
        tbody.appendChild(fragment);
        const msg = document.getElementById('no_custom_links_msg');
        if (msg) msg.style.display = 'none';
    }

    const rows = Array.from(tbody.querySelectorAll('.page-link-row'));
    rows.sort((a, b) => {
        const valA = parseInt(a.querySelector('.row-page-num')?.value || '0', 10);
        const valB = parseInt(b.querySelector('.row-page-num')?.value || '0', 10);
        return valA - valB;
    });
    rows.forEach(r => tbody.appendChild(r));

    syncPageLinksJson();

    const modalEl = document.getElementById('bulkPasteModal');
    if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
}

// Page Links Manager Script
function createPageRowElement(pageNum = '', url = '') {
    const cleanUrl = normalizeGoogleDocUrl(url);
    const tr = document.createElement('tr');
    tr.className = 'page-link-row';
    tr.innerHTML = `
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted">Pg</span>
                <input type="number" min="1" class="form-control form-control-sm row-page-num fw-bold text-primary" value="${pageNum}" placeholder="e.g. 5" oninput="syncPageLinksJson()">
            </div>
        </td>
        <td>
            <input type="url" class="form-control form-control-sm row-page-url font-monospace small" value="${escapeHtml(cleanUrl)}" placeholder="https://docs.google.com/document/d/..." oninput="syncPageLinksJson()" onblur="this.value = normalizeGoogleDocUrl(this.value); syncPageLinksJson();">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="removePageRow(this)" title="Delete Row">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    return tr;
}

function addPageRow(pageNum = '', url = '', shouldSync = true) {
    const tbody = document.getElementById('page_links_rows');
    const msg = document.getElementById('no_custom_links_msg');
    if (msg) msg.style.display = 'none';

    // Auto-detect next page number if pageNum is not provided
    if (pageNum === '') {
        const existingNums = Array.from(document.querySelectorAll('.row-page-num'))
            .map(inp => parseInt(inp.value, 10))
            .filter(n => !isNaN(n));
        if (existingNums.length > 0) {
            pageNum = Math.max(...existingNums) + 1;
        } else {
            const startPageVal = parseInt(document.getElementById('start_page_num')?.value || '1', 10);
            pageNum = (!isNaN(startPageVal) && startPageVal >= 1) ? startPageVal : 1;
        }
    }

    const tr = createPageRowElement(pageNum, url);
    tbody.appendChild(tr);

    if (shouldSync) {
        syncPageLinksJson();
    }
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

function clearAllPageRows() {
    const rows = document.querySelectorAll('.page-link-row');
    if (rows.length === 0) return;
    if (confirm(`Are you sure you want to delete all ${rows.length} page link rows?`)) {
        document.getElementById('page_links_rows').innerHTML = '';
        const msg = document.getElementById('no_custom_links_msg');
        if (msg) msg.style.display = 'block';
        syncPageLinksJson();
    }
}

function applyTemplateUrlToAll() {
    let templateDailyUrl = '';
    const masterInput = document.getElementById('master_doc_url');
    if (masterInput && masterInput.value.trim().length > 0) {
        templateDailyUrl = normalizeGoogleDocUrl(masterInput.value.trim());
    }

    if (!templateDailyUrl) {
        const existingUrlInputs = document.querySelectorAll('.row-page-url');
        for (const inp of existingUrlInputs) {
            if (inp.value && inp.value.trim().length > 0) {
                templateDailyUrl = normalizeGoogleDocUrl(inp.value.trim());
                break;
            }
        }
    }

    if (!templateDailyUrl) {
        const prompted = prompt('Enter the question doc/quiz URL to apply to all pages:');
        if (prompted && prompted.trim().length > 0) {
            templateDailyUrl = normalizeGoogleDocUrl(prompted.trim());
        } else {
            return;
        }
    }

    const existingUrlInputs = document.querySelectorAll('.row-page-url');
    existingUrlInputs.forEach(inp => {
        inp.value = templateDailyUrl;
    });

    if (masterInput && !masterInput.value) {
        masterInput.value = templateDailyUrl;
    }

    syncPageLinksJson();
}

function handleStartPageChange() {
    updateRangeLabels();
    syncPageLinksJson();
}

function quickFillFromStartPage() {
    const totalPagesInput = document.getElementById('page_count') || document.querySelector('input[name="page_count"]');
    const totalPages = parseInt(totalPagesInput?.value || '0', 10);
    
    const startPageInput = document.getElementById('start_page_num');
    const startPage = Math.max(1, parseInt(startPageInput?.value || '1', 10));

    const endPageInput = document.getElementById('end_page_num');
    let customEndPage = parseInt(endPageInput?.value || '0', 10);

    // Determine actual endPage: prioritize customEndPage if valid, else totalPages, else fallback
    let endPage = 0;
    if (!isNaN(customEndPage) && customEndPage >= startPage) {
        endPage = customEndPage;
    } else if (!isNaN(totalPages) && totalPages >= startPage) {
        endPage = totalPages;
    } else if (!isNaN(totalPages) && totalPages > 0) {
        endPage = totalPages;
    } else {
        endPage = startPage + 25;
    }

    if (endPage < startPage) {
        alert(`End Page (${endPage}) must be greater than or equal to Start Page (${startPage}).`);
        return;
    }

    if (endPage - startPage > 2000) {
        if (!confirm(`You are about to generate ${endPage - startPage + 1} page rows. Continue?`)) {
            return;
        }
    }

    // If an existing row has a URL, use it as template URL
    let templateDailyUrl = '';
    const existingUrlInputs = document.querySelectorAll('.row-page-url');
    for (const inp of existingUrlInputs) {
        if (inp.value && inp.value.trim().length > 0) {
            templateDailyUrl = inp.value.trim();
            break;
        }
    }

    // Collect all existing page numbers into a Set for O(1) fast lookup
    const existingPages = new Set();
    document.querySelectorAll('.row-page-num').forEach(inp => {
        const val = parseInt(inp.value, 10);
        if (!isNaN(val)) existingPages.add(val);
    });

    const tbody = document.getElementById('page_links_rows');
    const fragment = document.createDocumentFragment();
    let rowsAdded = 0;

    for (let p = startPage; p <= endPage; p++) {
        if (!existingPages.has(p)) {
            fragment.appendChild(createPageRowElement(p, templateDailyUrl));
            rowsAdded++;
        }
    }

    if (rowsAdded > 0) {
        tbody.appendChild(fragment);
        const msg = document.getElementById('no_custom_links_msg');
        if (msg) msg.style.display = 'none';
    }

    // Sort table rows numerically by page number
    const rows = Array.from(tbody.querySelectorAll('.page-link-row'));
    rows.sort((a, b) => {
        const valA = parseInt(a.querySelector('.row-page-num')?.value || '0', 10);
        const valB = parseInt(b.querySelector('.row-page-num')?.value || '0', 10);
        return valA - valB;
    });
    rows.forEach(r => tbody.appendChild(r));

    syncPageLinksJson();
}

function syncPageLinksJson() {
    const startPageVal = parseInt(document.getElementById('start_page_num')?.value || '1', 10);
    const startPage = (!isNaN(startPageVal) && startPageVal >= 1) ? startPageVal : 1;
    const rows = document.querySelectorAll('.page-link-row');
    const mapping = {};

    mapping['start_page'] = startPage;

    let validLinksCount = 0;
    rows.forEach(tr => {
        const pageNum = tr.querySelector('.row-page-num')?.value.trim();
        const url = tr.querySelector('.row-page-url')?.value.trim();
        if (pageNum && url) {
            mapping[pageNum] = url;
            validLinksCount++;
        }
    });

    const hiddenInput = document.getElementById('page_links');
    if (Object.keys(mapping).length > 1 || (Object.keys(mapping).length === 1 && mapping['start_page'])) {
        hiddenInput.value = JSON.stringify(mapping);
    } else {
        hiddenInput.value = '';
    }

    // Update row count badge
    const badge = document.getElementById('page_rows_badge');
    if (badge) {
        badge.textContent = `${rows.length} Pages (${validLinksCount} with URLs)`;
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

    // Sync page_count change to End Pg
    const totalPagesInput = document.getElementById('page_count') || document.querySelector('input[name="page_count"]');
    if (totalPagesInput) {
        totalPagesInput.addEventListener('input', () => {
            const endInput = document.getElementById('end_page_num');
            const totalVal = parseInt(totalPagesInput.value || '0', 10);
            if (endInput && (!endInput.value || parseInt(endInput.value, 10) === 0 || endInput.dataset.userEdited !== 'true')) {
                endInput.value = totalVal > 0 ? totalVal : '';
            }
            handleStartPageChange();
        });
    }

    const endPageInput = document.getElementById('end_page_num');
    if (endPageInput) {
        endPageInput.addEventListener('input', () => {
            endPageInput.dataset.userEdited = 'true';
            handleStartPageChange();
        });
    }

    // Initialize End Pg from Total Pages if empty
    const totalVal = parseInt(totalPagesInput?.value || '0', 10);
    if (endPageInput && (!endPageInput.value || endPageInput.value === '0') && totalVal > 0) {
        endPageInput.value = totalVal;
    }

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
                }
            }
            if (existingRows.length === 0) {
                const tbody = document.getElementById('page_links_rows');
                const fragment = document.createDocumentFragment();
                for (const [key, val] of Object.entries(parsed)) {
                    if (key && val && !isNaN(parseInt(key, 10))) {
                        fragment.appendChild(createPageRowElement(key, val));
                    }
                }
                tbody.appendChild(fragment);
                const msg = document.getElementById('no_custom_links_msg');
                if (msg && tbody.children.length > 0) msg.style.display = 'none';
            }
        } catch (e) {
            console.error('Error parsing initial page_links:', e);
        }
    }

    handleStartPageChange();
    onMasterDocInput();
    syncPageLinksJson();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
