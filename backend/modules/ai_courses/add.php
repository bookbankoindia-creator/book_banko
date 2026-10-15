<?php
/**
 * Upload & Add New AI Course Material PDF
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/ai_course_schema.php';

requireLogin();

$pageTitle = 'Upload AI Course PDF';
$pageSubtitle = 'Upload AI, Machine Learning, Python, or Robotics curriculum material for a specific standard';
$db = Database::getConnection();
ensureAiCourseTableExists($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $sizeMB = round((int)$_SERVER['CONTENT_LENGTH'] / (1024 * 1024), 1);
        flash('error', "The uploaded file ({$sizeMB} MB) exceeds PHP's post_max_size limit.");
    } else {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrf)) {
            flash('error', 'Invalid security token. Please refresh the page and try again.');
        } else {
            $standardNumber = (int)($_POST['standard_number'] ?? 0);
            $standardId = isset($_POST['standard_id']) && $_POST['standard_id'] !== '' ? (int)$_POST['standard_id'] : null;
            $title = trim($_POST['title'] ?? '');
            $moduleName = trim($_POST['module_name'] ?? '');
            $materialType = trim($_POST['material_type'] ?? 'Course Notes & PDF');
            $description = trim($_POST['description'] ?? '');
            $pageCount = (int)($_POST['page_count'] ?? 1);
            $externalUrl = trim($_POST['pdf_external_url'] ?? '');
            $videoUrl = trim($_POST['video_url'] ?? '');
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

            if ($standardNumber <= 0) {
                flash('error', 'Please select a valid Standard (e.g. Standard 6 to 12).');
            } elseif (empty($title)) {
                flash('error', 'Material / Chapter title is required.');
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO ai_course_materials
                        (standard_id, standard_number, title, module_name, material_type, description, pdf_file_path, pdf_external_url, video_url, page_count, file_size_mb, display_order, status)
                        VALUES
                        (:std_id, :std_num, :title, :mod_name, :mat_type, :description, :pdf_file, :ext_url, :video_url, :page_count, :file_size, :display_order, :status)
                    ");
                    $stmt->execute([
                        ':std_id' => $standardId,
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
                    ]);

                    flash('success', 'AI Course Material "' . htmlspecialchars($title) . '" for Standard ' . $standardNumber . ' uploaded successfully!');
                    header('Location: ' . url('modules/ai_courses/index.php?standard_number=' . $standardNumber));
                    exit;
                } catch (PDOException $e) {
                    flash('error', 'Database Error: ' . $e->getMessage());
                }
            }
        }
    }
}

// Fetch available standards
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
                    <i class="bi bi-cloud-arrow-up text-primary me-2"></i>Upload AI Course PDF
                </h5>
                <small class="text-muted">Select standard, fill in title & upload material PDF for AI Course</small>
            </div>
            <a href="<?= url('modules/ai_courses/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to List
            </a>
        </div>

        <div class="bb-card p-4">
            <form action="" method="POST" enctype="multipart/form-data" id="aiMaterialForm">
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
                            <option value="">-- Choose Standard --</option>
                            <?php foreach ($standards as $std): ?>
                                <option value="<?= (int)$std['standard_number'] ?>" <?= (isset($_GET['std']) && (int)$_GET['std'] === (int)$std['standard_number']) ? 'selected' : '' ?>>
                                    Standard <?= (int)$std['standard_number'] ?> (<?= htmlspecialchars($std['name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Choose the target standard for this AI curriculum item.</div>
                    </div>

                    <!-- Module / Unit Tag -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">
                            Module / Unit Tag
                        </label>
                        <input type="text" name="module_name" class="form-control" placeholder="e.g. Module 1 • Python Essentials" value="<?= htmlspecialchars($_POST['module_name'] ?? '') ?>">
                        <div class="form-text">Displayed as an organizing badge in the app.</div>
                    </div>

                    <!-- Material Title -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">
                            Material / Chapter Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Introduction to Machine Learning Algorithms" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">Description / Topics Summary</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief outline of concepts covered, coding projects, or revision notes..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Upload PDF File -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>Select PDF Document
                            </label>
                            <input type="file" name="pdf_file" id="pdfFileInput" class="form-control" accept=".pdf,application/pdf">
                            <small class="text-muted d-block mt-1">Accepts standard PDF files up to 500MB.</small>
                            
                            <!-- Direct upload progress bar -->
                            <div id="uploadProgressContainer" class="progress mt-2 d-none" style="height: 12px;">
                                <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="uploadStatusText" class="text-muted d-none"></small>
                        </div>
                    </div>

                    <!-- External URL Fallback -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">External PDF URL (Optional)</label>
                        <input type="url" name="pdf_external_url" class="form-control" placeholder="https://example.com/ai_course.pdf" value="<?= htmlspecialchars($_POST['pdf_external_url'] ?? '') ?>">
                        <div class="form-text">Used if hosting PDF on Google Drive, Supabase or CDN.</div>
                    </div>

                    <!-- Video Tutorial URL -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">Video Tutorial Link (Optional)</label>
                        <input type="url" name="video_url" class="form-control" placeholder="https://youtube.com/watch?v=..." value="<?= htmlspecialchars($_POST['video_url'] ?? '') ?>">
                        <div class="form-text">Optional video tutorial for students to watch.</div>
                    </div>

                    <!-- Page Count -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">Estimated Pages</label>
                        <input type="number" name="page_count" class="form-control" min="1" value="<?= (int)($_POST['page_count'] ?? 10) ?>">
                    </div>

                    <!-- Display Order -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">Display Order</label>
                        <input type="number" name="display_order" class="form-control" min="1" value="<?= (int)($_POST['display_order'] ?? 1) ?>">
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-dark">Publish Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active (Visible in App)</option>
                            <option value="inactive">Inactive (Hidden)</option>
                        </select>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="col-12 mt-4 pt-2 border-top d-flex justify-content-end gap-2">
                        <a href="<?= url('modules/ai_courses/index.php') ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-bb-primary px-4" id="submitBtn">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i>Save AI Course Material
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
