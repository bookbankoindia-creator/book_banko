<?php
/**
 * Top Navbar Include
 * Book Banko Admin Panel
 */
?>
<header class="top-navbar">
    <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0 flex-grow-1 me-2">
        <!-- Sidebar Toggle (Mobile Drawer & Desktop Collapse) -->
        <button class="btn btn-light p-2 rounded-3 shadow-none flex-shrink-0" id="sidebarToggle" type="button" aria-label="Toggle navigation" title="Toggle Sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>

        <div class="page-title min-w-0">
            <h4 class="text-truncate"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h4>
            <p class="text-truncate d-none d-sm-block"><?= htmlspecialchars($pageSubtitle ?? 'Manage educational resources, PDFs, and application configuration') ?></p>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3 flex-shrink-0">

        <!-- Admin Profile Pill Dropdown -->
        <div class="dropdown">
            <div class="admin-profile-pill" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                <div class="admin-avatar flex-shrink-0">
                    <?= strtoupper(substr($currentAdmin['full_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="d-none d-md-block text-start pe-1 admin-name-wrapper">
                    <div class="fw-bold text-dark admin-name-text" style="font-size: 13px; line-height: 1.2;">
                        <?= htmlspecialchars($currentAdmin['full_name'] ?? 'Administrator') ?>
                    </div>
                    <div class="text-secondary" style="font-size: 11px;">
                        <?= ucfirst($currentAdmin['role'] ?? 'Admin') ?>
                    </div>
                </div>
                <i class="bi bi-chevron-down text-muted flex-shrink-0" style="font-size: 12px;"></i>
            </div>

            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 rounded-3">
                <li><a class="dropdown-item py-2" href="<?= url('auth/profile.php') ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a class="dropdown-item py-2" href="<?= url('modules/settings/index.php') ?>"><i class="bi bi-gear me-2"></i>System Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= url('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>
