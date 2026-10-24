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
                        INSERT INTO admins (username, email, password_hash, full_name, role, status, permissions, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
                    ");
                    $insertStmt->execute([$username, $email, $passwordHash, $fullName, $role, $permsJson]);

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
                            SET full_name = ?, email = ?, username = ?, password_hash = ?, role = ?, status = ?, permissions = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$fullName, $email, $username, $newHash, $role, $status, $permsJson, $memberId]);
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
                $stmt = $db->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hash, $memberId]);
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

// Count active and super admin
$totalMembers = count($teamMembers);
$activeMembers = count(array_filter($teamMembers, fn($m) => ($m['status'] ?? 'active') === 'active'));
$superAdminCount = count(array_filter($teamMembers, fn($m) => ($m['role'] ?? '') === 'super_admin'));

include __DIR__ . '/../../includes/header.php';
?>

<!-- Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="bb-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #0061A4, #2196F3);">
                <i class="bi bi-people-fill fs-3"></i>
            </div>
            <div>
                <div class="text-secondary small fw-bold">TOTAL TEAM MEMBERS</div>
                <div class="fs-4 fw-bold text-dark"><?= $totalMembers ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="bb-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #10B981, #059669);">
                <i class="bi bi-check-circle-fill fs-3"></i>
            </div>
            <div>
                <div class="text-secondary small fw-bold">ACTIVE ACCOUNTS</div>
                <div class="fs-4 fw-bold text-dark"><?= $activeMembers ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="bb-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                <i class="bi bi-shield-lock-fill fs-3"></i>
            </div>
            <div>
                <div class="text-secondary small fw-bold">SUPER ADMINISTRATORS</div>
                <div class="fs-4 fw-bold text-dark"><?= $superAdminCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="bb-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #8B5CF6, #6D28D9);">
                <i class="bi bi-ui-checks-grid fs-3"></i>
            </div>
            <div>
                <div class="text-secondary small fw-bold">AVAILABLE SCREENS</div>
                <div class="fs-4 fw-bold text-dark"><?= count($allModules) ?></div>
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
    <button type="button" class="btn btn-bb-primary btn-sm px-3 py-2 d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addMemberModal">
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
                    <th width="50">#</th>
                    <th>Member Profile</th>
                    <th>Login Username & Email</th>
                    <th>Role</th>
                    <th>Screen Access & Privileges</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th width="150" class="text-end">Actions</th>
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
                        <div class="fw-semibold text-dark">
                            <i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($member['username']) ?>
                        </div>
                        <div class="text-secondary small d-flex align-items-center gap-1">
                            <i class="bi bi-envelope text-muted"></i>
                            <a href="mailto:<?= htmlspecialchars($member['email']) ?>" class="text-decoration-none text-secondary">
                                <?= htmlspecialchars($member['email']) ?>
                            </a>
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
                                <div class="mb-3 text-secondary small">
                                    The following screens and operations are currently enabled for <strong><?= htmlspecialchars($member['email']) ?></strong>:
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Screen / Module</th>
                                                <th>Group</th>
                                                <th class="text-center" width="90">Display (View)</th>
                                                <th class="text-center" width="90">Add New</th>
                                                <th class="text-center" width="90">Update / Edit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allModules as $modKey => $modInfo): 
                                                $userActions = $memberPerms[$modKey] ?? [];
                                                $hasView = in_array('view', $userActions);
                                                $hasAdd = in_array('add', $userActions);
                                                $hasEdit = in_array('edit', $userActions) || in_array('update', $userActions);
                                                if (!$hasView && !$hasAdd && !$hasEdit) continue;
                                            ?>
                                            <tr>
                                                <td>
                                                    <i class="bi <?= $modInfo['icon'] ?> text-primary me-2"></i>
                                                    <strong><?= htmlspecialchars($modInfo['name']) ?></strong>
                                                </td>
                                                <td><span class="badge bg-light text-secondary border"><?= $modInfo['group'] ?></span></td>
                                                <td class="text-center">
                                                    <?= $hasView ? '<span class="badge bg-success"><i class="bi bi-check-lg"></i> Yes</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $hasAdd ? '<span class="badge bg-info text-dark"><i class="bi bi-check-lg"></i> Yes</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $hasEdit ? '<span class="badge bg-warning text-dark"><i class="bi bi-check-lg"></i> Yes</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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

                                    <!-- Privileges Matrix -->
                                    <div class="privileges-section" id="editMatrix<?= $member['id'] ?>" style="<?= ($member['role'] === 'super_admin') ? 'display: none;' : '' ?>">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 p-2 bg-light rounded-3 border">
                                            <div class="fw-bold small text-dark">
                                                <i class="bi bi-ui-checks me-1 text-primary"></i>Assign Permitted Screens & Operations:
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary py-0 btn-select-all" data-matrix="#editMatrix<?= $member['id'] ?>">Select All (Full)</button>
                                                <button type="button" class="btn btn-sm btn-outline-info py-0 btn-select-view" data-matrix="#editMatrix<?= $member['id'] ?>">View Only</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 btn-clear-all" data-matrix="#editMatrix<?= $member['id'] ?>">Clear All</button>
                                            </div>
                                        </div>

                                        <div class="table-responsive border rounded-3" style="max-height: 400px; overflow-y: auto;">
                                            <table class="table table-hover table-sm align-middle mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Screen / Module Name</th>
                                                        <th>Category</th>
                                                        <th class="text-center" width="110">Show / Display</th>
                                                        <th class="text-center" width="100">Add New</th>
                                                        <th class="text-center" width="110">Update / Edit</th>
                                                        <th class="text-center" width="90">Row Quick</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($allModules as $modKey => $modInfo): 
                                                        $acts = $memberPerms[$modKey] ?? [];
                                                        $canView = in_array('view', $acts);
                                                        $canAdd = in_array('add', $acts);
                                                        $canEdit = in_array('edit', $acts) || in_array('update', $acts);
                                                        $supportsAdd = in_array('add', $modInfo['actions']);
                                                        $supportsEdit = in_array('edit', $modInfo['actions']);
                                                    ?>
                                                    <tr class="perm-row">
                                                        <td>
                                                            <i class="bi <?= $modInfo['icon'] ?> text-primary me-2"></i>
                                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($modInfo['name']) ?></span>
                                                        </td>
                                                        <td><span class="badge bg-light text-secondary border"><?= $modInfo['group'] ?></span></td>
                                                        <td class="text-center">
                                                            <div class="form-check form-check-inline m-0">
                                                                <input class="form-check-input chk-view" type="checkbox" name="perms[<?= $modKey ?>][]" value="view" <?= $canView ? 'checked' : '' ?> id="edit_<?= $member['id'] ?>_<?= $modKey ?>_view">
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($supportsAdd): ?>
                                                                <div class="form-check form-check-inline m-0">
                                                                    <input class="form-check-input chk-add" type="checkbox" name="perms[<?= $modKey ?>][]" value="add" <?= $canAdd ? 'checked' : '' ?> id="edit_<?= $member['id'] ?>_<?= $modKey ?>_add">
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted small">&mdash;</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($supportsEdit): ?>
                                                                <div class="form-check form-check-inline m-0">
                                                                    <input class="form-check-input chk-edit" type="checkbox" name="perms[<?= $modKey ?>][]" value="edit" <?= $canEdit ? 'checked' : '' ?> id="edit_<?= $member['id'] ?>_<?= $modKey ?>_edit">
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted small">&mdash;</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 btn-toggle-row" title="Toggle row full access" style="font-size: 11px;">
                                                                All
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
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

                    <!-- Privileges Selection Grid -->
                    <div id="newMatrixContainer">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 p-2 bg-light rounded-3 border">
                            <div class="fw-bold small text-dark">
                                <i class="bi bi-grid-3x3-gap-fill me-1 text-primary"></i>Choose Particular Screens & Operations to Authorize:
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 btn-select-all" data-matrix="#newMatrixContainer">
                                    <i class="bi bi-check-all me-1"></i>Select All (Full)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info py-0 btn-select-view" data-matrix="#newMatrixContainer">
                                    <i class="bi bi-eye me-1"></i>View Only All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 btn-clear-all" data-matrix="#newMatrixContainer">
                                    <i class="bi bi-x me-1"></i>Clear All
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive border rounded-3" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Screen / Module Name</th>
                                        <th>Category</th>
                                        <th class="text-center" width="120">Show / Display</th>
                                        <th class="text-center" width="110">Add New</th>
                                        <th class="text-center" width="120">Update / Edit</th>
                                        <th class="text-center" width="90">Row Quick</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allModules as $modKey => $modInfo): 
                                        $supportsAdd = in_array('add', $modInfo['actions']);
                                        $supportsEdit = in_array('edit', $modInfo['actions']);
                                    ?>
                                    <tr class="perm-row">
                                        <td>
                                            <i class="bi <?= $modInfo['icon'] ?> text-primary me-2"></i>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($modInfo['name']) ?></span>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?= $modInfo['group'] ?></span></td>
                                        <td class="text-center">
                                            <div class="form-check form-check-inline m-0">
                                                <input class="form-check-input chk-view" type="checkbox" name="perms[<?= $modKey ?>][]" value="view" id="new_<?= $modKey ?>_view">
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($supportsAdd): ?>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input chk-add" type="checkbox" name="perms[<?= $modKey ?>][]" value="add" id="new_<?= $modKey ?>_add">
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($supportsEdit): ?>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input chk-edit" type="checkbox" name="perms[<?= $modKey ?>][]" value="edit" id="new_<?= $modKey ?>_edit">
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 btn-toggle-row" title="Toggle row full access" style="font-size: 11px;">
                                                All
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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

    // Auto check 'view' when 'add' or 'edit' is checked
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('chk-add') || e.target.classList.contains('chk-edit')) {
            if (e.target.checked) {
                var row = e.target.closest('tr');
                if (row) {
                    var viewChk = row.querySelector('.chk-view');
                    if (viewChk) viewChk.checked = true;
                }
            }
        }
        // If 'view' unchecked, uncheck 'add' and 'edit'
        if (e.target.classList.contains('chk-view')) {
            if (!e.target.checked) {
                var row = e.target.closest('tr');
                if (row) {
                    var addChk = row.querySelector('.chk-add');
                    var editChk = row.querySelector('.chk-edit');
                    if (addChk) addChk.checked = false;
                    if (editChk) editChk.checked = false;
                }
            }
        }
    });

    // Row toggle button
    document.querySelectorAll('.btn-toggle-row').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = this.closest('tr');
            var viewChk = row.querySelector('.chk-view');
            var addChk = row.querySelector('.chk-add');
            var editChk = row.querySelector('.chk-edit');

            var allChecked = (!viewChk || viewChk.checked) && 
                             (!addChk || addChk.checked) && 
                             (!editChk || editChk.checked);

            if (allChecked) {
                if (viewChk) viewChk.checked = false;
                if (addChk) addChk.checked = false;
                if (editChk) editChk.checked = false;
            } else {
                if (viewChk) viewChk.checked = true;
                if (addChk) addChk.checked = true;
                if (editChk) editChk.checked = true;
            }
        });
    });

    // Select All (Full access)
    document.querySelectorAll('.btn-select-all').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            matrix.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = true;
            });
        });
    });

    // View Only All
    document.querySelectorAll('.btn-select-view').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            matrix.querySelectorAll('.chk-view').forEach(function(chk) {
                chk.checked = true;
            });
            matrix.querySelectorAll('.chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = false;
            });
        });
    });

    // Clear All
    document.querySelectorAll('.btn-clear-all').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var matrix = document.querySelector(this.getAttribute('data-matrix'));
            if (!matrix) return;
            matrix.querySelectorAll('.chk-view, .chk-add, .chk-edit').forEach(function(chk) {
                chk.checked = false;
            });
        });
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
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
