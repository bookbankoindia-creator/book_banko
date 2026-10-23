<?php
/**
 * Sidebar Navigation Include
 * Book Banko Admin Panel
 */
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
function isActiveNav(string $keyword): string {
    global $currentUri;
    return (strpos($currentUri, $keyword) !== false) ? 'active' : '';
}
?>
<aside class="sidebar" id="appSidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <div class="sidebar-brand-icon">
            <i class="bi bi-book-half"></i>
        </div>
        <div class="sidebar-brand-text">
            <h5>Book Banko</h5>
            <span>Admin Portal</span>
        </div>
        <button type="button" class="btn btn-sm text-white d-lg-none p-1 ms-auto" id="sidebarCloseBtn" aria-label="Close sidebar">
            <i class="bi bi-x-lg fs-5"></i>
        </button>
    </div>

    <!-- Navigation Links -->
    <div class="sidebar-menu">
        <?php if (hasPermission('dashboard', 'view')): ?>
        <a href="<?= url('modules/dashboard/index.php') ?>" class="nav-link-bb <?= isActiveNav('dashboard') ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        <?php endif; ?>

        <?php 
        $hasEduGroup = hasPermission('boards', 'view') || 
                       hasPermission('mediums', 'view') || 
                       hasPermission('standards', 'view') || 
                       hasPermission('streams', 'view') || 
                       hasPermission('subjects', 'view');
        if ($hasEduGroup):
        ?>
        <div class="sidebar-heading">Education Hierarchy</div>
        
        <?php if (hasPermission('boards', 'view')): ?>
        <a href="<?= url('modules/boards/index.php') ?>" class="nav-link-bb <?= isActiveNav('/boards/') ?>">
            <i class="bi bi-buildings"></i>
            <span>Boards</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('mediums', 'view')): ?>
        <a href="<?= url('modules/mediums/index.php') ?>" class="nav-link-bb <?= isActiveNav('/mediums/') ?>">
            <i class="bi bi-translate"></i>
            <span>Mediums of Study</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('standards', 'view')): ?>
        <a href="<?= url('modules/standards/index.php') ?>" class="nav-link-bb <?= isActiveNav('/standards/') ?>">
            <i class="bi bi-mortarboard-fill"></i>
            <span>Standards / Classes</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('streams', 'view')): ?>
        <a href="<?= url('modules/streams/index.php') ?>" class="nav-link-bb <?= isActiveNav('/streams/') ?>">
            <i class="bi bi-diagram-3-fill"></i>
            <span>Streams (11th & 12th)</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('subjects', 'view')): ?>
        <a href="<?= url('modules/subjects/index.php') ?>" class="nav-link-bb <?= isActiveNav('/subjects/') ?>">
            <i class="bi bi-journal-bookmark-fill"></i>
            <span>Subjects</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php 
        $hasStudyGroup = hasPermission('textbooks', 'view') || 
                        hasPermission('pyqs', 'view') || 
                        hasPermission('paper_sets', 'view') || 
                        hasPermission('blueprints', 'view') || 
                        hasPermission('mimp', 'view') || 
                        hasPermission('chapters', 'view');
        if ($hasStudyGroup):
        ?>
        <div class="sidebar-heading">Standard Study Modules (PDFs)</div>

        <?php if (hasPermission('textbooks', 'view')): ?>
        <a href="<?= url('modules/textbooks/index.php') ?>" class="nav-link-bb <?= isActiveNav('/textbooks/') ?>">
            <i class="bi bi-book-half text-primary"></i>
            <span>Textbooks</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('pyqs', 'view')): ?>
        <a href="<?= url('modules/pyqs/index.php') ?>" class="nav-link-bb <?= isActiveNav('/pyqs/') ?>">
            <i class="bi bi-journal-text text-info"></i>
            <span>Old PYQs</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('paper_sets', 'view')): ?>
        <a href="<?= url('modules/paper_sets/index.php') ?>" class="nav-link-bb <?= isActiveNav('/paper_sets/') ?>">
            <i class="bi bi-clipboard-check-fill text-primary"></i>
            <span>Paper Sets</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('blueprints', 'view')): ?>
        <a href="<?= url('modules/blueprints/index.php') ?>" class="nav-link-bb <?= isActiveNav('/blueprints/') ?>">
            <i class="bi bi-compass-fill text-primary"></i>
            <span>Blueprint</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('mimp', 'view')): ?>
        <a href="<?= url('modules/mimp/index.php') ?>" class="nav-link-bb <?= isActiveNav('/mimp/') ?>">
            <i class="bi bi-star-fill text-warning"></i>
            <span>M.IMP Questions</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('chapters', 'view')): ?>
        <a href="<?= url('modules/chapters/index.php') ?>" class="nav-link-bb <?= isActiveNav('/chapters/') ?>">
            <i class="bi bi-file-earmark-pdf-fill"></i>
            <span>All Chapters & Master PDFs</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php 
        $hasHomeGroup = hasPermission('extra_materials', 'view') || 
                       hasPermission('competitive_exams', 'view') || 
                       hasPermission('higher_education', 'view') || 
                       hasPermission('ai_courses', 'view') || 
                       hasPermission('study_products', 'view');
        if ($hasHomeGroup):
        ?>
        <div class="sidebar-heading">Home Categories & Store</div>

        <?php if (hasPermission('extra_materials', 'view')): ?>
        <a href="<?= url('modules/extra_materials/index.php') ?>" class="nav-link-bb <?= isActiveNav('/extra_materials/') ?>">
            <i class="bi bi-bookmark-star-fill text-warning"></i>
            <span>Extra Material PDFs</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('competitive_exams', 'view')): ?>
        <a href="<?= url('modules/competitive_exams/index.php') ?>" class="nav-link-bb <?= isActiveNav('/competitive_exams/') ?>">
            <i class="bi bi-award-fill text-warning"></i>
            <span>Competitive Exams</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('higher_education', 'view')): ?>
        <a href="<?= url('modules/higher_education/index.php') ?>" class="nav-link-bb <?= isActiveNav('/higher_education/') ?>">
            <i class="bi bi-mortarboard-fill text-success"></i>
            <span>Higher Education</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('ai_courses', 'view')): ?>
        <a href="<?= url('modules/ai_courses/index.php') ?>" class="nav-link-bb <?= isActiveNav('/ai_courses/') ?>">
            <i class="bi bi-cpu-fill text-primary"></i>
            <span>AI Course Materials</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('study_products', 'view')): ?>
        <a href="<?= url('modules/study_products/index.php') ?>" class="nav-link-bb <?= isActiveNav('/study_products/') ?>">
            <i class="bi bi-cart4 text-success"></i>
            <span>Study Products (Store)</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php 
        $hasAppGroup = hasPermission('students', 'view') || 
                      hasPermission('banners', 'view') || 
                      hasPermission('settings', 'view');
        if ($hasAppGroup):
        ?>
        <div class="sidebar-heading">App Management</div>

        <?php if (hasPermission('students', 'view')): ?>
        <a href="<?= url('modules/students/index.php') ?>" class="nav-link-bb <?= isActiveNav('/students/') ?>">
            <i class="bi bi-people-fill text-primary"></i>
            <span>Registered Students</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('banners', 'view')): ?>
        <a href="<?= url('modules/banners/index.php') ?>" class="nav-link-bb <?= isActiveNav('/banners/') ?>">
            <i class="bi bi-megaphone-fill"></i>
            <span>Banners & Notices</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('settings', 'view')): ?>
        <a href="<?= url('modules/settings/index.php#in_house_ads_section') ?>" class="nav-link-bb">
            <i class="bi bi-badge-ad-fill text-warning"></i>
            <span>In-App Custom Ads</span>
        </a>

        <a href="<?= url('modules/settings/index.php') ?>" class="nav-link-bb <?= isActiveNav('/settings/') ?>">
            <i class="bi bi-sliders"></i>
            <span>App Settings</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <div class="sidebar-heading">Security & Access</div>

        <?php if (isSuperAdmin()): ?>
        <a href="<?= url('modules/team/index.php') ?>" class="nav-link-bb <?= isActiveNav('/team/') ?>">
            <i class="bi bi-person-fill-gear text-info"></i>
            <span>Team & Privileges</span>
        </a>
        <?php endif; ?>

        <a href="<?= url('auth/profile.php') ?>" class="nav-link-bb <?= isActiveNav('profile') ?>">
            <i class="bi bi-person-badge-fill"></i>
            <span>Admin Profile</span>
        </a>

        <a href="<?= url('auth/logout.php') ?>" class="nav-link-bb text-danger" onclick="return confirm('Are you sure you want to log out?');">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
