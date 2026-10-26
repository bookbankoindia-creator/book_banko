<?php
/**
 * Registered Students / Users Admin Module
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'Registered Students';
$pageSubtitle = 'View and manage all students who have logged in and registered through Book Banko App';
$db = Database::getConnection();

// Ensure table exists
$driver = strtolower(DB_DRIVER);
if ($driver === 'pgsql') {
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            password VARCHAR(255) DEFAULT NULL,
            school_name VARCHAR(255) NOT NULL,
            city VARCHAR(100) NOT NULL,
            district VARCHAR(100) NOT NULL,
            state VARCHAR(100) NOT NULL,
            acknowledged SMALLINT DEFAULT 1,
            board VARCHAR(50) DEFAULT NULL,
            medium VARCHAR(50) DEFAULT NULL,
            standard INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    try {
        $db->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL;");
        $db->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS is_deleted SMALLINT DEFAULT 0;");
        $db->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL;");
    } catch (Exception $e) {}
} else {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `app_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(150) NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `password` VARCHAR(255) DEFAULT NULL,
            `school_name` VARCHAR(255) NOT NULL,
            `city` VARCHAR(100) NOT NULL,
            `district` VARCHAR(100) NOT NULL,
            `state` VARCHAR(100) NOT NULL,
            `acknowledged` TINYINT DEFAULT 1,
            `board` VARCHAR(50) DEFAULT NULL,
            `medium` VARCHAR(50) DEFAULT NULL,
            `standard` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    try {
        $cols = $db->query("SHOW COLUMNS FROM `app_users` LIKE 'password'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE `app_users` ADD COLUMN `password` VARCHAR(255) NULL AFTER `email`");
        }
    } catch (Exception $e) {}
}

// Handle Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=book_banko_students_' . date('Y_m_d_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Username / Student Name', 'Email ID', 'School Name', 'City', 'District', 'State', 'Board', 'Medium', 'Standard', 'Registered On']);

    $exportStmt = $db->query("SELECT id, username, email, school_name, city, district, state, board, medium, standard, created_at FROM app_users ORDER BY id DESC");
    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['username'],
            $row['email'],
            $row['school_name'],
            $row['city'],
            $row['district'],
            $row['state'],
            $row['board'] ?? 'N/A',
            $row['medium'] ?? 'N/A',
            $row['standard'] ? 'Std ' . $row['standard'] : 'N/A',
            $row['created_at'],
        ]);
    }
    fclose($output);
    exit;
}

// Handle Delete Student (support both GET and POST)
$deleteId = 0;
if (isset($_POST['action']) && $_POST['action'] === 'delete_student' && !empty($_POST['id'])) {
    $deleteId = (int)$_POST['id'];
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $deleteId = (int)$_GET['id'];
}

if ($deleteId > 0) {
    try {
        $delStmt = $db->prepare("DELETE FROM app_users WHERE id = ?");
        $delStmt->execute([$deleteId]);
        flash('success', "Student record #{$deleteId} and all associated data have been deleted successfully.");
    } catch (Exception $e) {
        flash('error', "Could not delete student record #{$deleteId}: " . $e->getMessage());
    }
    header('Location: ' . url('modules/students/index.php'));
    exit;
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$stateFilter = trim($_GET['state'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$query = "SELECT * FROM app_users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (LOWER(username) LIKE ? OR LOWER(email) LIKE ? OR LOWER(school_name) LIKE ? OR LOWER(city) LIKE ? OR LOWER(district) LIKE ?)";
    $st = '%' . strtolower($search) . '%';
    $params[] = $st;
    $params[] = $st;
    $params[] = $st;
    $params[] = $st;
    $params[] = $st;
}

if (!empty($stateFilter)) {
    $query .= " AND LOWER(state) = LOWER(?)";
    $params[] = $stateFilter;
}

if ($statusFilter === 'active') {
    $query .= " AND (is_deleted IS NULL OR is_deleted = 0)";
} elseif ($statusFilter === 'deleted') {
    $query .= " AND is_deleted = 1";
}

$query .= " ORDER BY id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total counts
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM app_users")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM app_users WHERE is_deleted IS NULL OR is_deleted = 0")->fetchColumn();
$deletedStudents = (int)$db->query("SELECT COUNT(*) FROM app_users WHERE is_deleted = 1")->fetchColumn();
$todayStudents = (int)$db->query("SELECT COUNT(*) FROM app_users WHERE DATE(created_at) = CURRENT_DATE")->fetchColumn();

// Fetch distinct states for filter
$statesStmt = $db->query("SELECT DISTINCT state FROM app_users WHERE state IS NOT NULL AND state != '' ORDER BY state ASC");
$availableStates = $statesStmt->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../../includes/header.php';
?>

<!-- Metric Stats Row -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value text-primary"><?= number_format($totalStudents) ?></div>
                    <div class="stat-label">Total Registered Students</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <div class="mt-2 text-muted small">
                All registered student accounts (<?= $totalStudents ?> total)
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value text-success"><?= number_format($activeStudents) ?></div>
                    <div class="stat-label">Active in App</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-green">
                    <i class="bi bi-person-check-fill"></i>
                </div>
            </div>
            <div class="mt-2 text-muted small">
                <?= $deletedStudents > 0 ? "{$deletedStudents} deactivated/deleted in app" : "Active student logins from Book Banko App" ?>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value text-warning"><?= count($availableStates) ?></div>
                    <div class="stat-label">States Covered</div>
                </div>
                <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
            </div>
            <div class="mt-2 text-muted small">
                Geographic diversity of students
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-mortarboard-fill text-primary me-2"></i>All Registered Students</h5>
            <small class="text-muted">Detailed list of student names, schools, emails, and locations. Preserved permanently in admin records.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('modules/students/index.php?export=csv') ?>" class="btn btn-outline-success btn-sm rounded-pill px-3">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to Excel/CSV
            </a>
        </div>
    </div>

    <div class="card-body px-4 pt-0">
        <!-- Search & Filter Form -->
        <form method="GET" class="row g-2 mb-4 mt-1">
            <div class="col-md-5 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search by name, email, school, city..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3 col-lg-3">
                <select name="state" class="form-select bg-light" onchange="this.form.submit()">
                    <option value="">All States</option>
                    <?php foreach ($availableStates as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= strtolower($stateFilter) === strtolower($st) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-lg-3">
                <select name="status" class="form-select bg-light" onchange="this.form.submit()">
                    <option value="">All App Statuses (<?= $totalStudents ?>)</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active in App Only (<?= $activeStudents ?>)</option>
                    <option value="deleted" <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>Deleted in App Only (<?= $deletedStudents ?>)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-3 px-3">Filter</button>
                <?php if (!empty($search) || !empty($stateFilter) || !empty($statusFilter)): ?>
                    <a href="<?= url('modules/students/index.php') ?>" class="btn btn-outline-secondary rounded-3">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Students Table -->
        <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-person-x text-muted" style="font-size: 3rem;"></i>
                </div>
                <h6 class="text-muted fw-bold">No registered students found</h6>
                <p class="text-secondary small">Students will appear here once they register on the Book Banko app.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light text-uppercase small fw-bold text-secondary">
                        <tr>
                            <th scope="col" style="width: 60px;">#</th>
                            <th scope="col">Student Name</th>
                            <th scope="col">Email ID</th>
                            <th scope="col">School Name</th>
                            <th scope="col">City & District</th>
                            <th scope="col">State</th>
                            <th scope="col">Academic Info</th>
                            <th scope="col">Registered On</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu): ?>
                            <tr>
                                <td class="fw-semibold text-muted">#<?= $stu['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center me-3" style="width: 38px; height: 38px; font-size: 14px;">
                                            <?= strtoupper(substr($stu['username'] ?: 'S', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($stu['username']) ?></div>
                                            <?php if (!empty($stu['is_deleted']) && (int)$stu['is_deleted'] === 1): ?>
                                                <div class="small mt-1">
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 10px;">
                                                        <i class="bi bi-person-x me-1"></i>Deleted in App
                                                    </span>
                                                    <?php if (!empty($stu['deleted_at'])): ?>
                                                        <span class="text-muted" style="font-size: 10px;">(<?= date('d M', strtotime($stu['deleted_at'])) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="small text-muted"><i class="bi bi-check2-circle text-success"></i> Acknowledged</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($stu['email']) ?>" class="text-decoration-none text-dark">
                                        <i class="bi bi-envelope text-secondary me-1"></i><?= htmlspecialchars($stu['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><i class="bi bi-building me-1 text-primary"></i><?= htmlspecialchars($stu['school_name']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($stu['city']) ?></div>
                                    <div class="small text-muted">Dist: <?= htmlspecialchars($stu['district']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($stu['state']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($stu['board']) || !empty($stu['standard'])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">
                                            <?= htmlspecialchars($stu['board'] ?? '') ?>
                                            <?= !empty($stu['standard']) ? ' • Std ' . $stu['standard'] : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Pending Selection</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d M Y, h:i A', strtotime($stu['created_at'])) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/students/index.php?action=delete&id=' . $stu['id']) ?>" 
                                       class="btn btn-sm btn-outline-danger rounded-pill px-2"
                                       onclick="return confirm('Are you sure you want to permanently delete student #<?= $stu['id'] ?> (<?= htmlspecialchars(addslashes($stu['username'] ?? 'Student')) ?>)?');"
                                       title="Delete Student and Data">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
