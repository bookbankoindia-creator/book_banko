<?php
/**
 * Team Management & Access Privileges (RBAC)
 * Book Banko Admin Panel
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

// Only Super Admins can access Team & Privileges management
if (!isSuperAdmin()) {
    flash('error', 'Access Denied: Only Super Administrators can manage team members and privileges.');
    header('Location: ' . getFirstPermittedModuleUrl());
    exit;
}

$pageTitle = 'Team & Screen Privileges';
$pageSubtitle = 'Add team members, assign specific screen access, and control Add / Update privileges';
$db = Database::getConnection();
$allModules = getAllAdminModules();
$currentAdminUser = currentAdmin();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrf)) {
        flash('error', 'Security token expired or invalid. Please try again.');
        header('Location: ' . url('modules/team/index.php'));
        exit;
    }

    // 1. ADD NEW TEAM MEMBER
    if ($action === 'add_member') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? 'team_member') === 'super_admin' ? 'super_admin' : 'team_member';
        $permsInput = $_POST['perms'] ?? []; // format: perms[module_key][] = 'view'|'add'|'edit'

        if (empty($fullName) || empty($email) || empty($username) || empty($password)) {
            flash('error', 'Please fill in all required fields (Name, Email, Username, Password).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please provide a valid email address.');
        } elseif (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
        } else {
            try {
                // Check if username or email already exists
                $stmt = $db->prepare("SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    flash('error', 'A team member with this email address or username already exists.');
                } else {
                    // Structure permissions
                    $structuredPerms = [];
                    if ($role === 'team_member' && is_array($permsInput)) {
                        foreach ($permsInput as $modKey => $actList) {
                            if (isset($allModules[$modKey]) && is_array($actList)) {
                                $validActs = array_values(array_intersect($actList, ['view', 'add', 'edit']));
                                if (!empty($validActs)) {
                                    // If add or edit is selected, ensure view is also included
                                    if ((in_array('add', $validActs) || in_array('edit', $validActs)) && !in_array('view', $validActs)) {
                                        $validActs[] = 'view';
                                    }
                                    $structuredPerms[$modKey] = $validActs;
                                }
                            }
                        }
                    }

                    $permsJson = ($role === 'super_admin') ? null : json_encode($structuredPerms);
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                    $insertStmt = $db->prepare("
                        INSERT INTO admins (username, email, password_hash, full_name, role, status, permissions, plain_password, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW(), NOW())
                    ");
                    $insertStmt->execute([$username, $email, $passwordHash, $fullName, $role, $permsJson, $password]);

                    flash('success', "Team member '{$fullName}' ({$email}) added successfully with customized privileges!");
                }
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        }
        header('Location: ' . url('modules/team/index.php'));
        exit;
    }

    // 2. UPDATE EXISTING TEAM MEMBER PRIVILEGES & DETAILS
    if ($action === 'update_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $username = strtolower(trim($_POST['username'] ?? ''));
        $newPassword = $_POST['new_password'] ?? '';
        $role = ($_POST['role'] ?? 'team_member') === 'super_admin' ? 'super_admin' : 'team_member';
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $permsInput = $_POST['perms'] ?? [];

        if ($memberId <= 0 || empty($fullName) || empty($email) || empty($username)) {
            flash('error', 'Full Name, Email Address, and Username are all required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
        } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
            flash('error', 'New password must be at least 6 characters long.');
        } else {
            try {
                // Ensure email and username uniqueness excluding self
                $stmt = $db->prepare("SELECT id FROM admins WHERE (email = ? OR username = ?) AND id != ? LIMIT 1");
                $stmt->execute([$email, $username, $memberId]);
                if ($stmt->fetch()) {
                    flash('error', 'Another team member already uses this email address or username.');
                } else {
                    // Prevent self-demotion or self-deactivation
                    if ($memberId === (int)$currentAdminUser['id']) {
                        $role = 'super_admin';
                        $status = 'active';
                    }

                    $structuredPerms = [];
                    if ($role === 'team_member' && is_array($permsInput)) {
                        foreach ($permsInput as $modKey => $actList) {
                            if (isset($allModules[$modKey]) && is_array($actList)) {
                                $validActs = array_values(array_intersect($actList, ['view', 'add', 'edit']));
                                if (!empty($validActs)) {
                                    if ((in_array('add', $validActs) || in_array('edit', $validActs)) && !in_array('view', $validActs)) {
                                        $validActs[] = 'view';
                                    }
                                    $structuredPerms[$modKey] = $validActs;
                                }
                            }
                        }
                    }

                    $permsJson = ($role === 'super_admin') ? null : json_encode($structuredPerms);

                    if (!empty($newPassword)) {
                        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $updateStmt = $db->prepare("
                            UPDATE admins 
                            SET full_name = ?, email = ?, username = ?, password_hash = ?, role = ?, status = ?, permissions = ?, plain_password = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$fullName, $email, $username, $newHash, $role, $status, $permsJson, $newPassword, $memberId]);
                    } else {
                        $updateStmt = $db->prepare("
                            UPDATE admins 
                            SET full_name = ?, email = ?, username = ?, role = ?, status = ?, permissions = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$fullName, $email, $username, $role, $status, $permsJson, $memberId]);
                    }

                    // If editing current logged in user, refresh session
                    if ($memberId === (int)$currentAdminUser['id']) {
                        $_SESSION['admin_user']['full_name'] = $fullName;
                        $_SESSION['admin_user']['email'] = $email;
                        $_SESSION['admin_user']['username'] = $username;
                        $_SESSION['admin_user']['role'] = $role;
                        if ($role !== 'super_admin') {
                            $_SESSION['admin_user']['permissions'] = $structuredPerms;
                        }
                    }

                    flash('success', "Team member '{$fullName}' ({$username}) updated successfully.");
                }
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        }
        header('Location: ' . url('modules/team/index.php'));
        exit;
    }

    // 3. CHANGE PASSWORD
    if ($action === 'change_password') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if ($memberId <= 0 || strlen($newPassword) < 6) {
            flash('error', 'Password must be at least 6 characters long.');
        } else {
            try {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE admins SET password_hash = ?, plain_password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hash, $newPassword, $memberId]);
                flash('success', 'Password reset successfully for this team member.');
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        }
        header('Location: ' . url('modules/team/index.php'));
        exit;
    }

    // 4. TOGGLE ACTIVE / INACTIVE STATUS
    if ($action === 'toggle_status') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId === (int)$currentAdminUser['id']) {
            flash('error', 'You cannot deactivate your own active account.');
        } else {
            try {
                $stmt = $db->prepare("SELECT status, full_name FROM admins WHERE id = ?");
                $stmt->execute([$memberId]);
                $mem = $stmt->fetch();
                if ($mem) {
                    $newStatus = ($mem['status'] === 'active') ? 'inactive' : 'active';
                    $up = $db->prepare("UPDATE admins SET status = ?, updated_at = NOW() WHERE id = ?");
                    $up->execute([$newStatus, $memberId]);
                    flash('success', "Status of " . htmlspecialchars($mem['full_name']) . " changed to " . ucfirst($newStatus) . ".");
                }
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        }
        header('Location: ' . url('modules/team/index.php'));
        exit;
    }

    // 5. DELETE TEAM MEMBER
    if ($action === 'delete_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId === (int)$currentAdminUser['id']) {
            flash('error', 'You cannot delete your own logged-in account.');
        } else {
            try {
                $del = $db->prepare("DELETE FROM admins WHERE id = ?");
                $del->execute([$memberId]);
                flash('success', 'Team member removed successfully.');
            } catch (PDOException $e) {
                flash('error', 'Database error: ' . $e->getMessage());
            }
        }
        header('Location: ' . url('modules/team/index.php'));
        exit;
    }
}

// Fetch all team members
$stmt = $db->query("SELECT * FROM admins ORDER BY (role = 'super_admin') DESC, id ASC");
$teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate team statistics for metric cards
$totalMembers = count($teamMembers);
$activeMembers = 0;
$superAdminCount = 0;
foreach ($teamMembers as $m) {
    if (($m['status'] ?? '') === 'active') {
        $activeMembers++;
    }
    if (($m['role'] ?? '') === 'super_admin') {
        $superAdminCount++;
    }
}

// Group modules by category
$groupedModules = [];
foreach ($allModules as $modKey => $modInfo) {
    $grp = $modInfo['group'] ?? 'General';
    if (!isset($groupedModules[$grp])) {
        $groupedModules[$grp] = [];
    }
    $groupedModules[$grp][$modKey] = $modInfo;
}

/**
 * Render the Modern Screen Privileges Grid UI
 */
function renderPermissionsMatrix(string $containerId, array $allModules, array $groupedModules, array $memberPerms = []): string {
    ob_start();
    $totalScreens = count($allModules);
    $activeCount = count($memberPerms);
    $grpIcons = [
        'Education Hierarchy' => 'bi-mortarboard',
        'Standard Study Modules (PDFs)' => 'bi-journal-bookmark',
        'Home Categories & Store' => 'bi-shop',
        'App Management' => 'bi-sliders',
        'Core' => 'bi-grid-1x2',
    ];
    ?>
    <div class="perm-matrix-wrapper" id="<?= $containerId ?>">
        <!-- 1. Header Toolbar: Presets + Search + Category Filter Navigation -->
        <div class="bg-white border rounded-3 p-3 mb-3 shadow-xs">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock-fill text-primary"></i>Screen Privileges & Operations
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill py-1 px-2 fw-semibold perm-summary-counter" style="font-size: 11px;">
                        <span class="perm-active-count"><?= $activeCount ?></span> of <?= $totalScreens ?> screens authorized
                    </span>
                </div>
                <!-- Quick Preset Buttons -->
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 btn-preset-all" data-matrix="#<?= $containerId ?>" title="Grant full access to all screens">
                        <i class="bi bi-check-all me-1"></i>Full Access (All)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info py-1 px-2 btn-preset-view" data-matrix="#<?= $containerId ?>" title="View-only access for all screens">
                        <i class="bi bi-eye me-1"></i>View Only All
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success py-1 px-2 btn-preset-study" data-matrix="#<?= $containerId ?>" title="Full access to Study PDFs & Education">
                        <i class="bi bi-journal-check me-1"></i>Study Content Only
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 btn-preset-clear" data-matrix="#<?= $containerId ?>" title="Clear all screen permissions">
                        <i class="bi bi-x-circle me-1"></i>Clear All
                    </button>
                </div>
            </div>

            <!-- Search + Category Filter Navigation Pills -->
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 perm-search-input" placeholder="Search screen name (e.g. Textbooks, PYQs, Subjects)..." data-matrix="#<?= $containerId ?>">
                        <button class="btn btn-outline-secondary btn-clear-search" type="button" data-matrix="#<?= $containerId ?>" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12 col-md-7">
                    <div class="d-flex flex-wrap gap-1 justify-content-md-end perm-filter-tabs" data-matrix="#<?= $containerId ?>">
                        <button type="button" class="btn btn-sm btn-primary py-1 px-2 filter-pill active" data-filter="all">
                            All Screens (<?= $totalScreens ?>)
                        </button>
                        <?php foreach ($groupedModules as $grpName => $grpMods): 
                            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $grpName), '-'));
                            $icon = $grpIcons[$grpName] ?? 'bi-folder';
                            $count = count($grpMods);
                        ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 filter-pill" data-filter="<?= $slug ?>">
                                <i class="bi <?= $icon ?> me-1"></i><?= htmlspecialchars($grpName) ?> (<?= $count ?>)
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Screen Cards Grouped by Category -->
        <div class="perm-groups-container">
            <?php foreach ($groupedModules as $grpName => $grpMods): 
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $grpName), '-'));
                $icon = $grpIcons[$grpName] ?? 'bi-folder';
            ?>
            <div class="perm-category-section mb-4" data-category="<?= $slug ?>">
                <!-- Category Section Banner -->
                <div class="d-flex flex-wrap justify-content-between align-items-center p-2 px-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= $icon ?> text-primary fs-5"></i>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($grpName) ?></span>
                        <span class="badge bg-secondary-subtle text-secondary border category-active-counter" style="font-size: 11px;">
                            <span class="cat-count">0</span> / <?= count($grpMods) ?> active
                        </span>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 btn-cat-all" data-category="<?= $slug ?>" data-matrix="#<?= $containerId ?>" style="font-size: 11px;">
                            <i class="bi bi-check2 me-1"></i>Grant Category
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-info py-0 px-2 btn-cat-view" data-category="<?= $slug ?>" data-matrix="#<?= $containerId ?>" style="font-size: 11px;">
                            View Only
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 btn-cat-clear" data-category="<?= $slug ?>" data-matrix="#<?= $containerId ?>" style="font-size: 11px;">
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div class="row g-3">
                    <?php foreach ($grpMods as $modKey => $modInfo): 
                        $acts = $memberPerms[$modKey] ?? [];
                        $canView = in_array('view', $acts);
                        $canAdd = in_array('add', $acts);
                        $canEdit = in_array('edit', $acts) || in_array('update', $acts);
                        $supportsAdd = in_array('add', $modInfo['actions']);
                        $supportsEdit = in_array('edit', $modInfo['actions']);

                        // Status badge text
                        $statusText = 'No Access';
                        $statusClass = 'bg-secondary-subtle text-secondary';
                        if ($canView && (!$supportsAdd || $canAdd) && (!$supportsEdit || $canEdit)) {
                            $statusText = 'Full Access';
                            $statusClass = 'bg-success text-white';
                        } elseif ($canView && ($canAdd || $canEdit)) {
                            $statusText = 'Custom Access';
                            $statusClass = 'bg-warning text-dark';
                        } elseif ($canView) {
                            $statusText = 'View Only';
                            $statusClass = 'bg-info text-dark';
                        }
                    ?>
                    <div class="col-12 col-md-6 col-xl-4 screen-card-item" data-search="<?= strtolower(htmlspecialchars($modInfo['name'] . ' ' . $grpName . ' ' . $modKey)) ?>" data-category="<?= $slug ?>">
                        <div class="card h-100 border rounded-3 p-3 shadow-xs perm-screen-card <?= $canView ? 'border-primary-subtle bg-white shadow-sm' : 'bg-light-subtle' ?>">
                            <!-- Card Header -->
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2 text-truncate">
                                    <div class="p-2 rounded bg-primary-subtle text-primary flex-shrink-0" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi <?= $modInfo['icon'] ?> fs-5"></i>
                                    </div>
                                    <div class="text-truncate">
                                        <div class="fw-bold text-dark small text-truncate" title="<?= htmlspecialchars($modInfo['name']) ?>">
                                            <?= htmlspecialchars($modInfo['name']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 10px;"><?= htmlspecialchars($modInfo['url']) ?></div>
                                    </div>
                                </div>
                                <span class="badge <?= $statusClass ?> perm-card-badge flex-shrink-0" style="font-size: 10px;">
                                    <?= $statusText ?>
                                </span>
                            </div>

                            <!-- Interactive Toggles -->
                            <div class="d-flex flex-column gap-2 mt-auto pt-1">
                                <!-- View Toggle -->
                                <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded border">
                                    <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                                        <input class="form-check-input chk-view" type="checkbox" name="perms[<?= $modKey ?>][]" value="view" id="<?= $containerId ?>_<?= $modKey ?>_view" <?= $canView ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-bold text-dark" for="<?= $containerId ?>_<?= $modKey ?>_view">
                                            <i class="bi bi-eye text-primary me-1"></i>Show in Menu (View)
                                        </label>
                                    </div>
                                    <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none btn-card-quick-toggle" title="Toggle full access on this screen" style="font-size: 11px;">
                                        <?= ($canView && (!$supportsAdd || $canAdd) && (!$supportsEdit || $canEdit)) ? 'Revoke' : 'All' ?>
                                    </button>
                                </div>

                                <!-- Add & Edit Toggles -->
                                <div class="d-flex align-items-center justify-content-between gap-2 px-1">
                                    <!-- Add -->
                                    <?php if ($supportsAdd): ?>
                                        <div class="form-check form-switch m-0 d-flex align-items-center gap-1">
                                            <input class="form-check-input chk-add" type="checkbox" name="perms[<?= $modKey ?>][]" value="add" id="<?= $containerId ?>_<?= $modKey ?>_add" <?= $canAdd ? 'checked' : '' ?>>
                                            <label class="form-check-label small text-secondary" for="<?= $containerId ?>_<?= $modKey ?>_add" style="font-size: 11px;">
                                                <i class="bi bi-plus-circle text-info me-1"></i>Add New
                                            </label>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic" style="font-size: 10px;">(View-only module)</span>
                                    <?php endif; ?>

                                    <!-- Edit -->
                                    <?php if ($supportsEdit): ?>
                                        <div class="form-check form-switch m-0 d-flex align-items-center gap-1">
                                            <input class="form-check-input chk-edit" type="checkbox" name="perms[<?= $modKey ?>][]" value="edit" id="<?= $containerId ?>_<?= $modKey ?>_edit" <?= $canEdit ? 'checked' : '' ?>>
                                            <label class="form-check-label small text-secondary" for="<?= $containerId ?>_<?= $modKey ?>_edit" style="font-size: 11px;">
                                                <i class="bi bi-pencil text-warning me-1"></i>Update / Edit
                                            </label>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic" style="font-size: 10px;">&nbsp;</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card h-100 hover-shadow border-0 shadow-sm" style="background: linear-gradient(145deg, #ffffff 0%, #f4f8fe 100%); border: 1px solid rgba(0, 97, 164, 0.12) !important;">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0 flex-grow-1">
                    <span class="text-uppercase fw-bold text-secondary" style="font-size: 11px; letter-spacing: 0.6px;">Total Team</span>
                    <div class="stat-value text-dark mt-1"><?= (int)$totalMembers ?></div>
                    <div class="text-muted small mt-2 d-flex align-items-center gap-1" style="font-size: 11.5px;">
                        <i class="bi bi-people-fill text-primary"></i> Registered Staff
                    </div>
                </div>
                <div class="stat-icon-wrapper stat-icon-blue flex-shrink-0 shadow-sm mb-0">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card h-100 hover-shadow border-0 shadow-sm" style="background: linear-gradient(145deg, #ffffff 0%, #f0fdf6 100%); border: 1px solid rgba(16, 185, 129, 0.16) !important;">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0 flex-grow-1">
                    <span class="text-uppercase fw-bold text-secondary" style="font-size: 11px; letter-spacing: 0.6px;">Active Accounts</span>
                    <div class="stat-value text-dark mt-1"><?= (int)$activeMembers ?></div>
                    <div class="text-muted small mt-2 d-flex align-items-center gap-1" style="font-size: 11.5px;">
                        <i class="bi bi-check-circle-fill text-success"></i> Authorized to login
                    </div>
                </div>
                <div class="stat-icon-wrapper stat-icon-emerald flex-shrink-0 shadow-sm mb-0">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card h-100 hover-shadow border-0 shadow-sm" style="background: linear-gradient(145deg, #ffffff 0%, #fffcf0 100%); border: 1px solid rgba(245, 158, 11, 0.16) !important;">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0 flex-grow-1">
                    <span class="text-uppercase fw-bold text-secondary" style="font-size: 11px; letter-spacing: 0.6px;">Super Admins</span>
                    <div class="stat-value text-dark mt-1"><?= (int)$superAdminCount ?></div>
                    <div class="text-muted small mt-2 d-flex align-items-center gap-1" style="font-size: 11.5px;">
                        <i class="bi bi-shield-lock-fill text-warning"></i> Full Master Access
                    </div>
                </div>
                <div class="stat-icon-wrapper stat-icon-amber flex-shrink-0 shadow-sm mb-0">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card h-100 hover-shadow border-0 shadow-sm" style="background: linear-gradient(145deg, #ffffff 0%, #f6f3ff 100%); border: 1px solid rgba(139, 92, 246, 0.16) !important;">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0 flex-grow-1">
                    <span class="text-uppercase fw-bold text-secondary" style="font-size: 11px; letter-spacing: 0.6px;">Available Screens</span>
                    <div class="stat-value text-dark mt-1"><?= count($allModules) ?></div>
                    <div class="text-muted small mt-2 d-flex align-items-center gap-1" style="font-size: 11.5px;">
                        <i class="bi bi-ui-checks-grid" style="color: #7C3AED;"></i> RBAC Controlled
                    </div>
                </div>
                <div class="stat-icon-wrapper flex-shrink-0 shadow-sm mb-0" style="background: #F3E8FF; color: #7C3AED;">
                    <i class="bi bi-ui-checks-grid"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Header Action Row -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-shield-shaded text-primary me-2"></i>Admin Team Members & Screen Privileges
        </h5>
        <div class="text-secondary small">Define customized access, allowed screens, and Add/Update privileges per user.</div>
    </div>
    <button type="button" class="btn btn-bb-primary btn-sm px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">
        <i class="bi bi-person-plus-fill fs-6"></i>
        <span class="fw-semibold">Add New Team Member</span>
    </button>
</div>

<!-- Team Members Table Card -->
<div class="bb-card p-3 p-md-4 mb-4">
    <div class="table-responsive">
        <table class="table bb-table data-table align-middle w-100">
            <thead>
                <tr>
                    <th style="width: 50px; min-width: 45px;">#</th>
                    <th style="min-width: 170px;">Member Profile</th>
                    <th style="min-width: 250px;">Account Credentials (User, Email & Pass)</th>
                    <th style="min-width: 130px;">Role</th>
                    <th style="min-width: 200px;">Screen Access & Privileges</th>
                    <th style="min-width: 100px;">Status</th>
                    <th style="min-width: 140px;">Last Active</th>
                    <th style="width: 150px; min-width: 140px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teamMembers as $idx => $member): 
                    $isSelf = ((int)$member['id'] === (int)$currentAdminUser['id']);
                    $isSuper = (($member['role'] ?? '') === 'super_admin');
                    $memberPerms = [];
                    if (!empty($member['permissions'])) {
                        $pDec = json_decode($member['permissions'], true);
                        if (is_array($pDec)) {
                            $memberPerms = $pDec;
                        }
                    }
                    $permittedCount = count($memberPerms);
                ?>
                <tr>
                    <td>
                        <span class="text-secondary small fw-semibold"><?= $idx + 1 ?></span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="admin-avatar flex-shrink-0" style="width: 38px; height: 38px; font-size: 15px; font-weight: bold;">
                                <?= strtoupper(substr($member['full_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">
                                    <?= htmlspecialchars($member['full_name']) ?>
                                    <?php if ($isSelf): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-1" style="font-size: 10px;">You</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-secondary small" style="font-size: 11px;">ID: #<?= $member['id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1" style="min-width: 240px; max-width: 320px;">
                            <!-- Username -->
                            <div class="d-flex align-items-center justify-content-between bg-light px-2 py-1 rounded border">
                                <div class="d-flex align-items-center gap-1 text-truncate">
                                    <i class="bi bi-person-fill text-primary" style="font-size: 13px;"></i>
                                    <span class="text-secondary small" style="font-size: 11px;">User:</span>
                                    <strong class="text-dark small font-monospace user-select-all"><?= htmlspecialchars($member['username']) ?></strong>
                                </div>
                                <button type="button" class="btn btn-sm btn-link p-0 text-secondary copy-credential-btn" data-copy="<?= htmlspecialchars($member['username']) ?>" data-label="Username" title="Copy Username">
                                    <i class="bi bi-clipboard" style="font-size: 12px;"></i>
                                </button>
                            </div>

                            <!-- Email -->
                            <div class="d-flex align-items-center justify-content-between bg-light px-2 py-1 rounded border">
                                <div class="d-flex align-items-center gap-1 text-truncate">
                                    <i class="bi bi-envelope-fill text-info" style="font-size: 13px;"></i>
                                    <span class="text-secondary small" style="font-size: 11px;">Email:</span>
                                    <a href="mailto:<?= htmlspecialchars($member['email']) ?>" class="text-dark small text-truncate text-decoration-none" style="max-width: 140px;" title="<?= htmlspecialchars($member['email']) ?>">
                                        <?= htmlspecialchars($member['email']) ?>
                                    </a>
                                </div>
                                <button type="button" class="btn btn-sm btn-link p-0 text-secondary copy-credential-btn" data-copy="<?= htmlspecialchars($member['email']) ?>" data-label="Email" title="Copy Email">
                                    <i class="bi bi-clipboard" style="font-size: 12px;"></i>
                                </button>
                            </div>

                            <!-- Password -->
                            <div class="d-flex align-items-center justify-content-between bg-light px-2 py-1 rounded border">
                                <div class="d-flex align-items-center gap-1">
                                    <i class="bi bi-key-fill text-warning" style="font-size: 13px;"></i>
                                    <span class="text-secondary small" style="font-size: 11px;">Pass:</span>
                                    <?php if (!empty($member['plain_password'])): ?>
                                        <span class="font-monospace fw-bold text-dark small cred-password-text" data-plain="<?= htmlspecialchars($member['plain_password']) ?>">••••••••</span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic small" style="font-size: 11px;">Encrypted</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($member['plain_password'])): ?>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-secondary toggle-cred-pass-btn" title="Show / Hide Password">
                                            <i class="bi bi-eye" style="font-size: 13px;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-secondary copy-credential-btn" data-copy="<?= htmlspecialchars($member['plain_password']) ?>" data-label="Password" title="Copy Password">
                                            <i class="bi bi-clipboard" style="font-size: 12px;"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-primary small fw-semibold" style="font-size: 11px;" data-bs-toggle="modal" data-bs-target="#passwordModal<?= $member['id'] ?>" title="Reset to view password">
                                            Reset
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 1-Click Share All Credentials -->
                            <div class="mt-1 d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-muted border" style="font-size: 10px;">
                                    <i class="bi bi-shield-lock me-1"></i>Auth Card
                                </span>
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 share-all-creds-btn d-inline-flex align-items-center gap-1"
                                        style="font-size: 11px; font-weight: 500;"
                                        data-name="<?= htmlspecialchars($member['full_name']) ?>"
                                        data-username="<?= htmlspecialchars($member['username']) ?>"
                                        data-email="<?= htmlspecialchars($member['email']) ?>"
                                        data-role="<?= $isSuper ? 'Super Administrator' : 'Team Member' ?>"
                                        data-password="<?= htmlspecialchars($member['plain_password'] ?? '(Reset needed to view password)') ?>"
                                        data-login-url="<?= url('auth/login.php') ?>"
                                        title="Copy formatted login message to clipboard">
                                    <i class="bi bi-share-fill" style="font-size: 10px;"></i>
                                    <span>Share Creds</span>
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($isSuper): ?>
                            <span class="badge bg-danger text-white rounded-pill px-2 py-1">
                                <i class="bi bi-shield-fill-check me-1"></i>Super Admin
                            </span>
                        <?php else: ?>
                            <span class="badge bg-info text-dark rounded-pill px-2 py-1">
                                <i class="bi bi-person-gear me-1"></i>Team Member
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isSuper): ?>
                            <div class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="bi bi-infinity me-1"></i>All <?= count($allModules) ?> Screens (Full Access)
                            </div>
                        <?php elseif ($permittedCount === 0): ?>
                            <span class="badge bg-secondary-subtle text-secondary border">No screens assigned</span>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                    <i class="bi bi-check2-square me-1"></i><?= $permittedCount ?> Screen<?= $permittedCount > 1 ? 's' : '' ?> Active
                                </span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-primary fw-semibold" style="font-size: 12px;" data-bs-toggle="modal" data-bs-target="#viewPermsModal<?= $member['id'] ?>">
                                    View Breakdown
                                </button>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($member['status'] ?? 'active') === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="text-secondary small">
                            <?= !empty($member['last_login']) ? date('M d, Y h:i A', strtotime($member['last_login'])) : '<span class="text-muted">Never</span>' ?>
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <!-- Edit Button -->
                            <button type="button" class="btn btn-outline-primary btn-sm p-1 px-2" title="Edit Privileges" data-bs-toggle="modal" data-bs-target="#editMemberModal<?= $member['id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Change Password -->
                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 px-2" title="Reset Password" data-bs-toggle="modal" data-bs-target="#passwordModal<?= $member['id'] ?>">
                                <i class="bi bi-key-fill"></i>
                            </button>

                            <!-- Toggle Status (Disabled for self) -->
                            <?php if (!$isSelf): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Change status for <?= htmlspecialchars(addslashes($member['full_name'])) ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                    <button type="submit" class="btn <?= ($member['status'] === 'active') ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-sm p-1 px-2" title="<?= ($member['status'] === 'active') ? 'Deactivate User' : 'Activate User' ?>">
                                        <i class="bi <?= ($member['status'] === 'active') ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($member['full_name'])) ?>? This action cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                    <input type="hidden" name="action" value="delete_member">
                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm p-1 px-2" title="Delete Member">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <!-- View Privileges Modal for this Member -->
                <?php if (!$isSuper && $permittedCount > 0): ?>
                <div class="modal fade" id="viewPermsModal<?= $member['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable modal-lg">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <div class="modal-header border-bottom py-3">
                                <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check text-primary fs-5"></i>
                                    Screen Privileges for <?= htmlspecialchars($member['full_name']) ?>
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="text-secondary small">
                                        Currently authorized screen access for <strong><?= htmlspecialchars($member['full_name']) ?></strong> (<?= htmlspecialchars($member['email']) ?>):
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-bold"><?= $permittedCount ?> Active Screens</span>
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($allModules as $modKey => $modInfo): 
                                        $userActions = $memberPerms[$modKey] ?? [];
                                        $hasView = in_array('view', $userActions);
                                        $hasAdd = in_array('add', $userActions);
                                        $hasEdit = in_array('edit', $userActions) || in_array('update', $userActions);
                                        if (!$hasView && !$hasAdd && !$hasEdit) continue;
                                    ?>
                                    <div class="col-12 col-md-6">
                                        <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2 h-100">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="p-2 rounded bg-primary-subtle text-primary">
                                                    <i class="bi <?= $modInfo['icon'] ?> fs-5"></i>
                                                </div>
                                                <div class="text-truncate">
                                                    <div class="fw-bold text-dark small text-truncate"><?= htmlspecialchars($modInfo['name']) ?></div>
                                                    <div class="text-muted" style="font-size: 11px;"><?= $modInfo['group'] ?></div>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 pt-2 border-top mt-auto">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 11px;">
                                                    <i class="bi bi-eye me-1"></i>View / Show
                                                </span>
                                                <?php if ($hasAdd): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle py-1 px-2" style="font-size: 11px;">
                                                        <i class="bi bi-plus-circle me-1"></i>Add New
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($hasEdit): ?>
                                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-1 px-2" style="font-size: 11px;">
                                                        <i class="bi bi-pencil me-1"></i>Update / Edit
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="modal-footer border-top py-2">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-bb-primary btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editMemberModal<?= $member['id'] ?>">
                                    <i class="bi bi-pencil-square me-1"></i>Edit These Privileges
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Edit Member Modal -->
                <div class="modal fade" id="editMemberModal<?= $member['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable modal-xl">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_member">
                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">

                                <div class="modal-header border-bottom py-3">
                                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-person-gear text-primary fs-5"></i>
                                        Edit Member & Screen Privileges &mdash; <?= htmlspecialchars($member['full_name']) ?>
                                    </h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <!-- 1. Account Details, Username & Password Card -->
                                    <div class="card border border-primary-subtle bg-light-subtle rounded-3 mb-4 shadow-sm">
                                        <div class="card-header bg-primary text-white py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="fw-bold fs-6">
                                                <i class="bi bi-person-badge-fill me-2"></i>1. Account Details, Username & Password
                                            </div>
                                            <span class="badge bg-white text-primary fw-semibold">Login Credentials</span>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="row g-3">
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label fw-bold text-dark small mb-1">Full Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($member['full_name']) ?>" required>
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label fw-bold text-dark small mb-1">Email Address <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($member['email']) ?>" required>
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label fw-bold text-dark small mb-1">Login Username <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($member['username']) ?>" required>
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label fw-bold text-dark small mb-1">Change Password</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="new_password" id="editPwInput<?= $member['id'] ?>" placeholder="Leave blank to keep" minlength="6">
                                                        <button type="button" class="btn btn-outline-secondary btn-toggle-pw" data-target="#editPwInput<?= $member['id'] ?>" title="Show/Hide">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-primary btn-generate-pw" data-target="#editPwInput<?= $member['id'] ?>" title="Generate">
                                                            <i class="bi bi-shuffle"></i>
                                                        </button>
                                                    </div>
                                                    <?php if (!empty($member['plain_password'])): ?>
                                                        <div class="form-text small d-flex align-items-center justify-content-between mt-1">
                                                            <span>Current: <strong class="font-monospace text-primary"><?= htmlspecialchars($member['plain_password']) ?></strong></span>
                                                            <button type="button" class="btn btn-link btn-sm p-0 text-secondary copy-credential-btn" data-copy="<?= htmlspecialchars($member['plain_password']) ?>" data-label="Password" title="Copy Current Password">
                                                                <i class="bi bi-clipboard" style="font-size: 11px;"></i>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label fw-bold text-dark small mb-1">Role & Access Type <span class="text-danger">*</span></label>
                                                    <select class="form-select role-selector" name="role" data-target="#editMatrix<?= $member['id'] ?>" <?= $isSelf ? 'disabled' : '' ?>>
                                                        <option value="team_member" <?= ($member['role'] === 'team_member') ? 'selected' : '' ?>>Team Member (Custom Screen Privileges)</option>
                                                        <option value="super_admin" <?= ($member['role'] === 'super_admin') ? 'selected' : '' ?>>Super Administrator (Full Unrestricted Access)</option>
                                                    </select>
                                                    <?php if ($isSelf): ?>
                                                        <input type="hidden" name="role" value="super_admin">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label fw-bold text-dark small mb-1">Account Status <span class="text-danger">*</span></label>
                                                    <select class="form-select" name="status" <?= $isSelf ? 'disabled' : '' ?>>
                                                        <option value="active" <?= ($member['status'] === 'active') ? 'selected' : '' ?>>Active (Can Log In)</option>
                                                        <option value="inactive" <?= ($member['status'] === 'inactive') ? 'selected' : '' ?>>Inactive (Account Blocked)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modern Privileges Matrix -->
                                    <div class="privileges-section" id="editMatrix<?= $member['id'] ?>" style="<?= ($member['role'] === 'super_admin') ? 'display: none;' : '' ?>">
                                        <?= renderPermissionsMatrix('editMatrixContainer_' . $member['id'], $allModules, $groupedModules, $memberPerms) ?>
                                    </div>
                                    <div class="alert alert-info py-2 px-3 small rounded-3 mt-3 super-admin-note" style="<?= ($member['role'] === 'super_admin') ? '' : 'display: none;' ?>">
                                        <i class="bi bi-info-circle-fill me-1"></i>
                                        Super Administrators have unrestricted access to all screens and operations by default.
                                    </div>
                                </div>
                                <div class="modal-footer border-top py-2">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-bb-primary btn-sm">
                                        <i class="bi bi-check2-circle me-1"></i>Save Member Privileges
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Reset Password Modal -->
                <div class="modal fade" id="passwordModal<?= $member['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">

                                <div class="modal-header border-bottom py-3">
                                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                        <i class="bi bi-key-fill text-warning fs-5"></i>
                                        Reset Password for <?= htmlspecialchars($member['full_name']) ?>
                                    </h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <?php if (!empty($member['plain_password'])): ?>
                                        <div class="mb-3 p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="text-secondary small fw-semibold" style="font-size: 11px;">
                                                    <i class="bi bi-shield-check text-success me-1"></i>CURRENT ACTIVE PASSWORD
                                                </div>
                                                <div class="fw-bold font-monospace text-dark fs-6 mt-1 user-select-all"><?= htmlspecialchars($member['plain_password']) ?></div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary copy-credential-btn" data-copy="<?= htmlspecialchars($member['plain_password']) ?>" data-label="Password" title="Copy Current Password">
                                                <i class="bi bi-clipboard me-1"></i>Copy
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="new_password" id="pwInput<?= $member['id'] ?>" placeholder="Minimum 6 characters" minlength="6" required>
                                            <button type="button" class="btn btn-outline-secondary btn-toggle-pw" data-target="#pwInput<?= $member['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-generate-pw" data-target="#pwInput<?= $member['id'] ?>">
                                            <i class="bi bi-shuffle me-1"></i>Generate Random Password
                                        </button>
                                    </div>
                                </div>
                                <div class="modal-footer border-top py-2">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-warning btn-sm text-dark fw-bold">
                                        <i class="bi bi-lock-fill me-1"></i>Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD NEW TEAM MEMBER MODAL -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" action="" id="addMemberForm">
                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                <input type="hidden" name="action" value="add_member">

                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-person-plus-fill text-primary"></i>
                        Add New Team Member & Assign Screen Privileges
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border d-flex align-items-center gap-2 py-2 px-3 small rounded-3 mb-3">
                        <i class="bi bi-shield-check text-primary fs-5"></i>
                        <div>
                            When this user logs in with their username/email, <strong>only the screens checked below will show in their navigation</strong>, and they can only perform <strong>Add / Update operations</strong> on screens you specifically authorize.
                        </div>
                    </div>

                    <!-- 1. Set Login Username, Password & Account Details Card -->
                    <div class="card border border-primary-subtle bg-light-subtle rounded-3 mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white py-2 px-3 d-flex align-items-center justify-content-between">
                            <div class="fw-bold fs-6">
                                <i class="bi bi-key-fill me-2"></i>1. Set Login Username, Password & Account Details
                            </div>
                            <span class="badge bg-white text-primary fw-semibold">Required for Login</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-bold text-dark small mb-1">
                                        <i class="bi bi-person-fill text-primary me-1"></i>Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="full_name" placeholder="e.g. Rahul Sharma" required id="newFullName">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-bold text-dark small mb-1">
                                        <i class="bi bi-envelope-fill text-primary me-1"></i>Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control" name="email" placeholder="e.g. rahul@bookbanko.com" required id="newEmail">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-bold text-dark small mb-1">
                                        <i class="bi bi-at text-primary me-1"></i>Login Username <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="username" placeholder="e.g. rahul_editor" required id="newUsername">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-bold text-dark small mb-1">
                                        <i class="bi bi-lock-fill text-primary me-1"></i>Login Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="password" id="newPasswordInput" placeholder="Min 6 characters" minlength="6" required>
                                        <button type="button" class="btn btn-outline-secondary btn-toggle-pw" data-target="#newPasswordInput" title="Show/Hide">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-generate-pw" data-target="#newPasswordInput" title="Generate">
                                            <i class="bi bi-shuffle"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark small mb-1">
                                        <i class="bi bi-shield-lock-fill text-primary me-1"></i>Role & Access Level <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select role-selector" name="role" data-target="#newMatrixContainer">
                                        <option value="team_member" selected>Team Member (Custom Screen Privileges)</option>
                                        <option value="super_admin">Super Administrator (Full Unrestricted Access)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modern Privileges Selection Grid -->
                    <div class="privileges-section" id="newMatrixContainer">
                        <?= renderPermissionsMatrix('newMatrixContainer', $allModules, $groupedModules, []) ?>
                    </div>

                    <div class="alert alert-info py-2 px-3 small rounded-3 mt-3 super-admin-note" style="display: none;">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Super Administrators automatically receive full unrestricted access across all screens and features.
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-bb-primary btn-sm px-3">
                        <i class="bi bi-person-check-fill me-1"></i>Create Member & Assign Privileges
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Role selector toggle
    document.querySelectorAll('.role-selector').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var target = document.querySelector(this.getAttribute('data-target'));
            var modal = this.closest('.modal-content');
            var note = modal ? modal.querySelector('.super-admin-note') : null;
            if (this.value === 'super_admin') {
                if (target) target.style.display = 'none';
                if (note) note.style.display = 'block';
            } else {
                if (target) target.style.display = 'block';
                if (note) note.style.display = 'none';
            }
        });
    });

    // Update visual appearance and badge of a single screen card
    function updateCardVisuals(cardEl) {
        if (!cardEl) return;
        var viewChk = cardEl.querySelector('.chk-view');
        var addChk = cardEl.querySelector('.chk-add');
        var editChk = cardEl.querySelector('.chk-edit');
        var badge = cardEl.querySelector('.perm-card-badge');
        var quickBtn = cardEl.querySelector('.btn-card-quick-toggle');

        var canView = viewChk ? viewChk.checked : false;
        var canAdd = addChk ? addChk.checked : false;
        var canEdit = editChk ? editChk.checked : false;

        var hasAdd = !!addChk;
        var hasEdit = !!editChk;

        var isFull = canView && (!hasAdd || canAdd) && (!hasEdit || canEdit);

        if (badge) {
            if (!canView) {
                badge.className = 'badge bg-secondary-subtle text-secondary perm-card-badge flex-shrink-0';
                badge.textContent = 'No Access';
            } else if (isFull) {
                badge.className = 'badge bg-success text-white perm-card-badge flex-shrink-0';
                badge.textContent = 'Full Access';
            } else if (canAdd || canEdit) {
                badge.className = 'badge bg-warning text-dark perm-card-badge flex-shrink-0';
                badge.textContent = 'Custom Access';
            } else {
                badge.className = 'badge bg-info text-dark perm-card-badge flex-shrink-0';
                badge.textContent = 'View Only';
            }
        }

        if (quickBtn) {
            quickBtn.textContent = isFull ? 'Revoke' : 'All';
        }

        if (canView) {
            cardEl.classList.add('border-primary-subtle', 'bg-white', 'shadow-sm');
            cardEl.classList.remove('bg-light-subtle');
        } else {
            cardEl.classList.remove('border-primary-subtle', 'bg-white', 'shadow-sm');
            cardEl.classList.add('bg-light-subtle');
        }
    }

    // Update Category Counters and Overall Active Count in a matrix
    function updateMatrixCounters(matrixEl) {
        if (!matrixEl) return;
        var totalActive = 0;

        matrixEl.querySelectorAll('.perm-category-section').forEach(function(sec) {
            var catActive = 0;
            var cards = sec.querySelectorAll('.perm-screen-card');
            cards.forEach(function(card) {
                var viewChk = card.querySelector('.chk-view');
                if (viewChk && viewChk.checked) {
                    catActive++;
                }
            });

            var countSpan = sec.querySelector('.cat-count');
            if (countSpan) countSpan.textContent = catActive;
            totalActive += catActive;
        });

        var summaryCounter = matrixEl.querySelector('.perm-active-count');
        if (summaryCounter) {
            summaryCounter.textContent = totalActive;
        }
    }

    // Auto check/uncheck dependencies
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('chk-add') || e.target.classList.contains('chk-edit')) {
            var card = e.target.closest('.perm-screen-card');
            if (card) {
                if (e.target.checked) {
                    var viewChk = card.querySelector('.chk-view');
                    if (viewChk) viewChk.checked = true;
                }
                updateCardVisuals(card);
                updateMatrixCounters(card.closest('.perm-matrix-wrapper'));
            }
        }

        if (e.target.classList.contains('chk-view')) {
            var card = e.target.closest('.perm-screen-card');
            if (card) {
                if (!e.target.checked) {
                    var addChk = card.querySelector('.chk-add');
                    var editChk = card.querySelector('.chk-edit');
                    if (addChk) addChk.checked = false;
                    if (editChk) editChk.checked = false;
                }
                updateCardVisuals(card);
                updateMatrixCounters(card.closest('.perm-matrix-wrapper'));
            }
        }
    });

    // Card Quick All / Revoke Toggle
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-card-quick-toggle');
        if (!btn) return;
        e.preventDefault();
        var card = btn.closest('.perm-screen-card');
        if (!card) return;

        var viewChk = card.querySelector('.chk-view');
        var addChk = card.querySelector('.chk-add');
        var editChk = card.querySelector('.chk-edit');

        var isFull = viewChk && viewChk.checked && (!addChk || addChk.checked) && (!editChk || editChk.checked);

        if (isFull) {
            if (viewChk) viewChk.checked = false;
            if (addChk) addChk.checked = false;
            if (editChk) editChk.checked = false;
        } else {
            if (viewChk) viewChk.checked = true;
            if (addChk) addChk.checked = true;
            if (editChk) editChk.checked = true;
        }

        updateCardVisuals(card);
        updateMatrixCounters(card.closest('.perm-matrix-wrapper'));
    });

    // Preset: Full Access (All)
    document.querySelectorAll('.btn-preset-all').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            matrix.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = true;
            });
            matrix.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
            updateMatrixCounters(matrix);
        });
    });

    // Preset: View Only All
    document.querySelectorAll('.btn-preset-view').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            matrix.querySelectorAll('.chk-view').forEach(function(chk) {
                chk.checked = true;
            });
            matrix.querySelectorAll('.chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = false;
            });
            matrix.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
            updateMatrixCounters(matrix);
        });
    });

    // Preset: Study Content Only (Education + Study Modules PDFs)
    document.querySelectorAll('.btn-preset-study').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            matrix.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = false;
            });
            var studyCategories = ['education-hierarchy', 'standard-study-modules-pdfs'];
            studyCategories.forEach(function(slug) {
                var sec = matrix.querySelector('.perm-category-section[data-category="' + slug + '"]');
                if (sec) {
                    sec.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                        chk.checked = true;
                    });
                }
            });
            matrix.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
            updateMatrixCounters(matrix);
        });
    });

    // Preset: Clear All
    document.querySelectorAll('.btn-preset-clear').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            matrix.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = false;
            });
            matrix.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
            updateMatrixCounters(matrix);
        });
    });

    // Category Level Actions: Grant Category
    document.querySelectorAll('.btn-cat-all').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            var slug = this.getAttribute('data-category');
            if (!matrix || !slug) return;
            var sec = matrix.querySelector('.perm-category-section[data-category="' + slug + '"]');
            if (!sec) return;
            sec.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = true;
            });
            sec.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
            updateMatrixCounters(matrix);
        });
    });

    // Category Level Actions: View Only
    document.querySelectorAll('.btn-cat-view').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            var slug = this.getAttribute('data-category');
            if (!matrix || !slug) return;
            var sec = matrix.querySelector('.perm-category-section[data-category="' + slug + '"]');
            if (!sec) return;
            sec.querySelectorAll('.chk-view').forEach(function(chk) { chk.checked = true; });
            sec.querySelectorAll('.chk-add, .chk-edit').forEach(function(chk) { chk.checked = false; });
            sec.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
            updateMatrixCounters(matrix);
        });
    });

    // Category Level Actions: Clear
    document.querySelectorAll('.btn-cat-clear').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            var slug = this.getAttribute('data-category');
            if (!matrix || !slug) return;
            var sec = matrix.querySelector('.perm-category-section[data-category="' + slug + '"]');
            if (!sec) return;
            sec.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = false;
            });
            sec.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
            updateMatrixCounters(matrix);
        });
    });

    // Category Filter Pills Navigation
    document.querySelectorAll('.filter-pill').forEach(function(pill) {
        pill.addEventListener('click', function() {
            var filterGroup = this.closest('.perm-filter-tabs');
            var matrix = document.querySelector(filterGroup ? filterGroup.getAttribute('data-matrix') : '');
            if (!matrix) return;

            filterGroup.querySelectorAll('.filter-pill').forEach(function(p) {
                p.classList.remove('active', 'btn-primary');
                p.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('active', 'btn-primary');

            var filter = this.getAttribute('data-filter');
            var searchInput = matrix.querySelector('.perm-search-input');
            if (searchInput) searchInput.value = '';

            matrix.querySelectorAll('.screen-card-item').forEach(function(item) {
                item.style.display = '';
            });

            if (filter === 'all') {
                matrix.querySelectorAll('.perm-category-section').forEach(function(sec) {
                    sec.style.display = '';
                });
            } else {
                matrix.querySelectorAll('.perm-category-section').forEach(function(sec) {
                    if (sec.getAttribute('data-category') === filter) {
                        sec.style.display = '';
                    } else {
                        sec.style.display = 'none';
                    }
                });
            }
        });
    });

    // Real-time Search Filter
    document.querySelectorAll('.perm-search-input').forEach(function(input) {
        input.addEventListener('input', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            var query = this.value.trim().toLowerCase();

            if (!query) {
                // If search is cleared, restore filter pill state
                var activePill = matrix.querySelector('.filter-pill.active');
                if (activePill) activePill.click();
                return;
            }

            // In search mode, search across all cards
            matrix.querySelectorAll('.perm-category-section').forEach(function(sec) {
                sec.style.display = '';
                var visibleInSec = 0;
                sec.querySelectorAll('.screen-card-item').forEach(function(item) {
                    var s = item.getAttribute('data-search') || '';
                    if (s.indexOf(query) !== -1) {
                        item.style.display = '';
                        visibleInSec++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                if (visibleInSec === 0) {
                    sec.style.display = 'none';
                }
            });
        });
    });

    // Clear Search Button
    document.querySelectorAll('.btn-clear-search').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            var input = matrix.querySelector('.perm-search-input');
            if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input'));
            }
        });
    });

    // Initialize all matrix visuals on load
    document.querySelectorAll('.perm-matrix-wrapper').forEach(function(matrix) {
        matrix.querySelectorAll('.perm-screen-card').forEach(updateCardVisuals);
        updateMatrixCounters(matrix);
    });

    // Toggle Password Visibility
    document.querySelectorAll('.btn-toggle-pw').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.querySelector(this.getAttribute('data-target'));
            if (!target) return;
            var isPass = target.getAttribute('type') === 'password';
            target.setAttribute('type', isPass ? 'text' : 'password');
            var icon = this.querySelector('i');
            if (icon) {
                icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });

    // Generate Random Password
    document.querySelectorAll('.btn-generate-pw').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.querySelector(this.getAttribute('data-target'));
            if (!target) return;
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
            var generated = '';
            for (var i = 0; i < 10; i++) {
                generated += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            target.value = generated;
            target.setAttribute('type', 'text');
            var toggleBtn = this.closest('.input-group')?.querySelector('.btn-toggle-pw');
            if (toggleBtn) {
                var icon = toggleBtn.querySelector('i');
                if (icon) icon.className = 'bi bi-eye-slash';
            }
        });
    });

    // Auto-generate username from email
    var emailInput = document.getElementById('newEmail');
    var usernameInput = document.getElementById('newUsername');
    if (emailInput && usernameInput) {
        emailInput.addEventListener('blur', function() {
            if (!usernameInput.value.trim() && this.value.includes('@')) {
                var prefix = this.value.split('@')[0].replace(/[^a-zA-Z0-9_]/g, '_');
                usernameInput.value = prefix;
            }
        });
    }

    // Ensure modals always open scrolled to top so username and password are fully visible
    document.querySelectorAll('.modal').forEach(function(modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            var modalBody = this.querySelector('.modal-body');
            if (modalBody) modalBody.scrollTop = 0;
            var firstInput = this.querySelector('input[type="text"]:not([disabled])');
            if (firstInput) firstInput.focus();
        });
    });

    // Toggle Table Credential Password Visibility
    document.querySelectorAll('.toggle-cred-pass-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var row = this.closest('td');
            var textEl = row ? row.querySelector('.cred-password-text') : null;
            if (!textEl) return;
            var plain = textEl.getAttribute('data-plain');
            var icon = this.querySelector('i');
            if (textEl.textContent.trim() === '••••••••') {
                textEl.textContent = plain;
                textEl.classList.add('text-primary');
                if (icon) icon.className = 'bi bi-eye-slash';
            } else {
                textEl.textContent = '••••••••';
                textEl.classList.remove('text-primary');
                if (icon) icon.className = 'bi bi-eye';
            }
        });
    });

    // Copy Individual Credential (Username / Email / Password)
    document.querySelectorAll('.copy-credential-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var text = this.getAttribute('data-copy');
            var label = this.getAttribute('data-label') || 'Credential';
            if (!text) return;
            var self = this;
            navigator.clipboard.writeText(text).then(function() {
                var icon = self.querySelector('i');
                var originalClass = icon ? icon.className : '';
                if (icon) icon.className = 'bi bi-check-lg text-success';
                var originalTitle = self.getAttribute('title');
                self.setAttribute('title', 'Copied ' + label + '!');
                setTimeout(function() {
                    if (icon) icon.className = originalClass;
                    if (originalTitle) self.setAttribute('title', originalTitle);
                }, 1500);
            }).catch(function(err) {
                prompt('Copy ' + label + ':', text);
            });
        });
    });

    // Share / Copy All Credentials Card
    document.querySelectorAll('.share-all-creds-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var name = this.getAttribute('data-name') || '';
            var username = this.getAttribute('data-username') || '';
            var email = this.getAttribute('data-email') || '';
            var password = this.getAttribute('data-password') || '';
            var role = this.getAttribute('data-role') || '';
            var loginPath = this.getAttribute('data-login-url') || '/auth/login.php';
            var loginUrl = window.location.origin + (loginPath.startsWith('/') ? loginPath : '/' + loginPath);

            var cardText = "🔐 Book Banko Admin Access Credentials\n" +
                           "━━━━━━━━━━━━━━━━━━━━━━\n" +
                           "👤 Name: " + name + "\n" +
                           "🏷️ Username: " + username + "\n" +
                           "✉️ Email: " + email + "\n" +
                           "🔑 Password: " + password + "\n" +
                           "🛡️ Role: " + role + "\n" +
                           "🌐 Login Portal: " + loginUrl + "\n" +
                           "━━━━━━━━━━━━━━━━━━━━━━\n" +
                           "⚠️ Note: Please keep your login credentials secure.";

            var self = this;
            navigator.clipboard.writeText(cardText).then(function() {
                var originalHtml = self.innerHTML;
                self.innerHTML = '<i class="bi bi-check2-circle text-success" style="font-size: 11px;"></i> <span class="text-success fw-bold">Copied!</span>';
                setTimeout(function() {
                    self.innerHTML = originalHtml;
                }, 2000);
            }).catch(function() {
                prompt('Copy Login Details:', cardText);
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
