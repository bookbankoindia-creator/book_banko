<?php
/**
 * Edit AI Course Material PDF
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/ai_course_schema.php';

requireLogin();

$db = Database::getConnection();
ensureAiCourseTableExists($db);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM ai_course_materials WHERE id = :id");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'AI Course material not found.');
    header('Location: ' . url('modules/ai_courses/index.php'));
    exit;
}

$pageTitle = 'Edit AI Course PDF: ' . htmlspecialchars($item['title']);
$pageSubtitle = 'Update course title, standard, module details or replace PDF';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf)) {
        flash('error', 'Invalid security token. Please refresh the page and try again.');
    } else {
        $standardNumber = (int)($_POST['standard_number'] ?? $item['standard_number']);
        $title = trim($_POST['title'] ?? '');
        $moduleName = trim($_POST['module_name'] ?? '');
        $materialType = trim($_POST['material_type'] ?? 'Course Notes & PDF');
        $description = trim($_POST['description'] ?? '');
        $pageCount = (int)($_POST['page_count'] ?? $item['page_count']);
        $externalUrl = trim($_POST['pdf_external_url'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 1);
        $status = $_POST['status'] ?? 'active';

        $pdfFileName = $item['pdf_file_path'];
        $fileSizeMB = (float)$item['file_size_mb'];

        // If direct upload pushed to Supabase
        if (!empty($_POST['direct_uploaded_file'])) {
            $pdfFileName = trim($_POST['direct_uploaded_file']);
            $fileSizeMB = (float)($_POST['direct_file_size_mb'] ?? $fileSizeMB);
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
            flash('error', 'Material / Chapter title is required.');
        } else {
            try {
                $updateStmt = $db->prepare("
                    UPDATE ai_course_materials
                    SET standard_number = :std_num,
                        title = :title,
                        module_name = :mod_name,
                        material_type = :mat_type,
                        description = :description,
                        pdf_file_path = :pdf_file,
                        pdf_external_url = :ext_url,
                        video_url = :video_url,
                        page_count = :page_count,
                        file_size_mb = :file_size,
                        display_order = :display_order,
                        status = :status,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    ':std_num' => $standardNumber,
                    ':title' => $title,
                    ':mod_name' => $moduleName,
                    ':mat_type' => $materialType,
                    ':description' => $description,
                    ':pdf_file' => $pdfFileName,
                    ':ext_url' => $externalUrl,
                    ':video_url' => $videoUrl,
                    ':page_count' => $pageCount,
                    ':file_size' => $fileSizeMB,
                    ':display_order' => $displayOrder,
                    ':status' => $status,
                    ':id' => $id,
                ]);

                flash('success', 'AI Course Material updated successfully!');
                header('Location: ' . url('modules/ai_courses/index.php?standard_number=' . $standardNumber));
                exit;
            } catch (PDOException $e) {
                flash('error', 'Database Error: ' . $e->getMessage());
            }
        }
    }
}

// Standards list
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

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit AI Course PDF
                </h5>
                <small class="text-muted">Modify standard, module title or upload replacement PDF</small>
            </div>
            <a href="<?= url('modules/ai_courses/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to List
            </a>
        </div>

        <div class="bb-card p-4">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                <input type="hidden" name="direct_uploaded_file" id="direct_uploaded_file" value="">
                <input type="hidden" name="direct_file_size_mb" id="direct_file_size_mb" value="">

                <div class="row g-3">
                    <!-- Standard Selection -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">
                            Standard / Class <span class="text-danger">*</span>
                        </label>
                        <select name="standard_number" class="form-select" required>
                            <?php foreach ($standards as $std): ?>
                                <option value="<?= (int)$std['standard_number'] ?>" <?= (int)$item['standard_number'] === (int)$std['standard_number'] ? 'selected' : '' ?>>
                                    Standard <?= (int)$std['standard_number'] ?> (<?= htmlspecialchars($std['name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Module / Unit Tag -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">Module / Unit Tag</label>
                        <input type="text" name="module_name" class="form-control" value="<?= htmlspecialchars($item['module_name'] ?? '') ?>" placeholder="e.g. Module 1 • Python Essentials">
                    </div>

                    <!-- Material Title -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">
                            Material / Chapter Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($item['title']) ?>" required>
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">Description / Topics Summary</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Current PDF -->
                    <?php if (!empty($item['pdf_file_path']) || !empty($item['pdf_external_url'])): 
                        $curPdf = !empty($item['pdf_file_path']) ? resolveMediaUrl($item['pdf_file_path'], 'pdfs') : $item['pdf_external_url'];
                    ?>
                        <div class="col-12">
                            <div class="p-2.5 bg-light rounded border d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>
                                    <strong>Current Attached PDF:</strong> 
                                    <span class="text-muted small"><?= htmlspecialchars($item['pdf_file_path'] ?: $item['pdf_external_url']) ?></span>
                                </div>
                                <a href="<?= htmlspecialchars($curPdf) ?>" target="_blank" class="btn btn-xs btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Open File
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Replace PDF -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark mb-1">
                            Replace PDF Document (Optional)
                        </label>
                        <input type="file" name="pdf_file" class="form-control" accept=".pdf,application/pdf">
                        <small class="text-muted">Leave empty to keep existing PDF.</small>
                    </div>

                    <!-- External URL -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">External PDF URL</label>
                        <input type="url" name="pdf_external_url" class="form-control" value="<?= htmlspecialchars($item['pdf_external_url'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <!-- Video Tutorial URL -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">Video Tutorial Link</label>
                        <input type="url" name="video_url" class="form-control" value="<?= htmlspecialchars($item['video_url'] ?? '') ?>" placeholder="https://youtube.com/...">
                    </div>

                    <!-- Page Count -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">Page Count</label>
                        <input type="number" name="page_count" class="form-control" min="1" value="<?= (int)$item['page_count'] ?>">
                    </div>

                    <!-- Display Order -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">Display Order</label>
                        <input type="number" name="display_order" class="form-control" min="1" value="<?= (int)$item['display_order'] ?>">
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">Publish Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $item['status'] === 'active' ? 'selected' : '' ?>>Active (Visible in App)</option>
                            <option value="inactive" <?= $item['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
                        </select>
                    </div>

                    <!-- Submit -->
                    <div class="col-12 mt-4 pt-2 border-top d-flex justify-content-end gap-2">
                        <a href="<?= url('modules/ai_courses/index.php') ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-bb-primary px-4">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
