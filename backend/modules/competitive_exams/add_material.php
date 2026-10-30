<?php
/**
 * Upload & Add New Competitive Exam Material PDF
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'Upload Competitive Exam PDF';
$pageSubtitle = 'Upload question papers, formula handbooks, notes or mock tests for any competitive exam';
$db = Database::getConnection();

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

            $displayOrder = (int)($_POST['display_order'] ?? 1);
            $status = $_POST['status'] ?? 'active';

            $pdfFileName = '';
            $fileSizeMB = 0.00;

            // Handle PDF File Upload (direct client-side upload or standard)
            if (!empty($_POST['direct_uploaded_file'])) {
                $pdfFileName = trim($_POST['direct_uploaded_file']);
                $fileSizeMB = (float)($_POST['direct_file_size_mb'] ?? 0.0);
            } elseif (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = uploadFile($_FILES['pdf_file'], PDF_UPLOADS_PATH, ['pdf'], 500);
                if ($uploadResult['status']) {
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
                    $examId = null;
                    if (!empty($examName)) {
                        $findExam = $db->prepare("SELECT id FROM competitive_exams WHERE LOWER(title) = LOWER(:name) OR LOWER(slug) = LOWER(:slug) OR LOWER(exam_code) = LOWER(:code) LIMIT 1");
                        $findExam->execute([':name' => $examName, ':slug' => $examName, ':code' => $examName]);
                        $matched = $findExam->fetch();
                        if ($matched) {
                            $examId = (int)$matched['id'];
                        }
                    }

                    $stmt = $db->prepare("
                        INSERT INTO competitive_exam_materials
                        (exam_id, exam_name, subject_name, title, material_type, description, pdf_file_path, pdf_external_url, page_count, file_size_mb, page_links, is_free, display_order, status)
                        VALUES
                        (:exam_id, :exam_name, :sub_name, :title, :mat_type, :description, :pdf_file, :ext_url, :page_count, :file_size, :links, true, :display_order, :status)
                    ");
                    $stmt->execute([
                        ':exam_id' => $examId,
                        ':exam_name' => $examName,
                        ':sub_name' => $examName ?: 'General',
                        ':title' => $title,
                        ':mat_type' => $materialType,
                        ':description' => $description,
                        ':pdf_file' => $pdfFileName,
                        ':ext_url' => $externalUrl,
                        ':page_count' => $pageCount,
                        ':file_size' => $fileSizeMB,
                        ':links' => $pageLinks,
                        ':display_order' => $displayOrder,
                        ':status' => $status,
                    ]);

                    flash('success', 'Competitive Exam PDF "' . htmlspecialchars($title) . '" uploaded successfully!');
                    header('Location: ' . url('modules/competitive_exams/index.php'));
                    exit;
                } catch (PDOException $e) {
                    flash('error', 'Database Error: ' . $e->getMessage());
                }
            }
        }
    }
}

$savedStartPage = (int)($_POST['start_page_num'] ?? 4);
$defaultEndPage = (int)($_POST['page_count'] ?? 0) > 0 ? (int)$_POST['page_count'] : $savedStartPage;
$savedFirstUrl = '';

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-cloud-arrow-up text-primary me-2"></i>Upload Competitive Exam PDF
                </h5>
                <small class="text-muted">Fill in the material title and select a PDF to make it available to students</small>
            </div>
            <a href="<?= url('modules/competitive_exams/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to List
            </a>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation">
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
                            <input type="text" name="title" class="form-control" required placeholder="e.g. UPSC Prelims Solved Papers, SSC CGL Formula Book, Grammar..." value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                            <div class="form-text small">This is the title students will see in the app card.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Exam / Category Name</label>
                            <input type="text" name="exam_name" class="form-control" placeholder="e.g. UPSC, SSC, Banking, GPSC, General..." value="<?= htmlspecialchars($_POST['exam_name'] ?? '') ?>" list="examSuggestions">
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
                            <div class="form-text small">Tag or exam name (optional).</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-dark">Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief summary of contents..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
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
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-dark">
                                Choose PDF File <span class="text-danger">*</span>
                            </label>
                            <div class="p-3 border border-2 border-dashed rounded text-center bg-light">
                                <i class="bi bi-file-earmark-pdf fs-2 text-primary d-block mb-1"></i>
                                <input type="file" name="pdf_file" class="form-control" accept=".pdf,application/pdf">
                                <div class="form-text mt-2 small text-muted">Supports PDF files up to 500 MB.</div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="text-center text-muted small my-1">── OR PROVIDE DIRECT PDF URL ──</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-dark">External PDF URL (Optional)</label>
                            <input type="url" name="pdf_external_url" class="form-control" placeholder="https://example.com/file.pdf" value="<?= htmlspecialchars($_POST['pdf_external_url'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Estimated Page Count</label>
                            <input type="number" name="page_count" class="form-control" min="1" value="<?= htmlspecialchars($_POST['page_count'] ?? '1') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Display Order</label>
                            <input type="number" name="display_order" class="form-control" min="1" value="<?= htmlspecialchars($_POST['display_order'] ?? '1') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-dark">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (Visible)</option>
                                <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
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
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" id="page_rows_badge">0 Pages</span>
                                        </div>
                                        <small class="text-muted" id="quick_fill_desc">
                                            Set specific question doc/quiz URLs for Page <span id="dyn_desc_start_page" class="fw-bold text-dark"><?= (int)($_POST['start_page_num'] ?? 4) ?></span> and all pages after (<span id="dyn_desc_examples" class="text-muted"><?= (int)($_POST['start_page_num'] ?? 4) ?>, <?= (int)($_POST['start_page_num'] ?? 4) + 1 ?>, <?= (int)($_POST['start_page_num'] ?? 4) + 2 ?>...</span>).
                                        </small>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div class="input-group input-group-sm" style="width: 135px;" title="Set starting page number">
                                            <span class="input-group-text bg-white text-muted fw-bold">Start Pg</span>
                                            <input type="number" min="1" max="5000" class="form-control form-control-sm fw-bold text-primary text-center" id="start_page_num" name="start_page_num" value="<?= (int)($_POST['start_page_num'] ?? 4) ?>" placeholder="e.g. 5" oninput="handleStartPageChange()">
                                        </div>
                                        <div class="input-group input-group-sm" style="width: 135px;" title="Set ending page number (defaults to Total Pages)">
                                            <span class="input-group-text bg-white text-muted fw-bold">End Pg</span>
                                            <input type="number" min="1" max="5000" class="form-control form-control-sm fw-bold text-primary text-center" id="end_page_num" value="<?= (int)($_POST['page_count'] ?? 0) > 0 ? (int)$_POST['page_count'] : '' ?>" placeholder="e.g. 322" oninput="handleStartPageChange()">
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPageRow()" title="Add single page row">
                                            <i class="bi bi-plus-circle me-1"></i>Add Link
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary text-white shadow-sm" id="btn_quick_fill" onclick="quickFillFromStartPage()" title="Generate page link rows for the exact page range">
                                            <i class="bi bi-magic me-1"></i><span id="quick_fill_btn_text">Quick Fill Page <?= (int)($_POST['start_page_num'] ?? 4) ?>+</span>
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
                                            <!-- Dynamic Rows -->
                                        </tbody>
                                    </table>
                                </div>
                                <div id="no_custom_links_msg" class="text-center py-3 text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>No page links added yet. Click <strong>+ Add Page Link</strong> above to add a link for a specific page.
                                </div>

                                <!-- Hidden field that stores the JSON string for backend processing -->
                                <input type="hidden" id="page_links" name="page_links" value="<?= htmlspecialchars($_POST['page_links'] ?? '') ?>">

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
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="<?= url('modules/competitive_exams/index.php') ?>" class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-bb-primary px-4">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i>Upload Material
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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
    const startPage = (!isNaN(startVal) && startVal >= 1) ? startPageVal : 1;

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

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
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
