<?php
/**
 * Global Utility Functions & Helpers
 * Book Banko Backend & Admin Panel
 */

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/supabase.php';

// Authentication & Session
function isLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        flash('error', 'Please login to access the admin panel.');
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
    enforceRoutePermission();
}

function currentAdmin(): array {
    return $_SESSION['admin_user'] ?? [
        'id' => 0,
        'username' => 'Guest',
        'email' => '',
        'full_name' => 'Guest User',
        'role' => 'viewer',
        'permissions' => []
    ];
}

// ==========================================
// Role-Based Access Control (RBAC) & Privileges
// ==========================================

function getAllAdminModules(): array {
    return [
        'dashboard' => [
            'name' => 'Dashboard & Analytics',
            'icon' => 'bi-grid-1x2-fill',
            'url' => 'modules/dashboard/index.php',
            'group' => 'Core',
            'actions' => ['view']
        ],
        // Education Hierarchy
        'boards' => [
            'name' => 'Educational Boards',
            'icon' => 'bi-buildings',
            'url' => 'modules/boards/index.php',
            'group' => 'Education Hierarchy',
            'actions' => ['view', 'add', 'edit']
        ],
        'mediums' => [
            'name' => 'Mediums of Study',
            'icon' => 'bi-translate',
            'url' => 'modules/mediums/index.php',
            'group' => 'Education Hierarchy',
            'actions' => ['view', 'add', 'edit']
        ],
        'standards' => [
            'name' => 'Standards / Classes',
            'icon' => 'bi-mortarboard-fill',
            'url' => 'modules/standards/index.php',
            'group' => 'Education Hierarchy',
            'actions' => ['view', 'add', 'edit']
        ],
        'streams' => [
            'name' => 'Streams (11th & 12th)',
            'icon' => 'bi-diagram-3-fill',
            'url' => 'modules/streams/index.php',
            'group' => 'Education Hierarchy',
            'actions' => ['view', 'add', 'edit']
        ],
        'subjects' => [
            'name' => 'Subjects',
            'icon' => 'bi-journal-bookmark-fill',
            'url' => 'modules/subjects/index.php',
            'group' => 'Education Hierarchy',
            'actions' => ['view', 'add', 'edit']
        ],
        // Standard Study Modules (PDFs)
        'textbooks' => [
            'name' => 'Textbooks',
            'icon' => 'bi-book-half',
            'url' => 'modules/textbooks/index.php',
            'group' => 'Standard Study Modules (PDFs)',
            'actions' => ['view', 'add', 'edit']
        ],
        'pyqs' => [
            'name' => 'Old PYQs',
            'icon' => 'bi-journal-text',
            'url' => 'modules/pyqs/index.php',
            'group' => 'Standard Study Modules (PDFs)',
            'actions' => ['view', 'add', 'edit']
        ],
        'paper_sets' => [
            'name' => 'Paper Sets',
            'icon' => 'bi-clipboard-check-fill',
            'url' => 'modules/paper_sets/index.php',
            'group' => 'Standard Study Modules (PDFs)',
            'actions' => ['view', 'add', 'edit']
        ],
        'blueprints' => [
            'name' => 'Blueprint',
            'icon' => 'bi-compass-fill',
            'url' => 'modules/blueprints/index.php',
            'group' => 'Standard Study Modules (PDFs)',
            'actions' => ['view', 'add', 'edit']
        ],
        'mimp' => [
            'name' => 'M.IMP Questions',
            'icon' => 'bi-star-fill',
            'url' => 'modules/mimp/index.php',
            'group' => 'Standard Study Modules (PDFs)',
            'actions' => ['view', 'add', 'edit']
        ],
        'chapters' => [
            'name' => 'All Chapters & Master PDFs',
            'icon' => 'bi-file-earmark-pdf-fill',
            'url' => 'modules/chapters/index.php',
            'group' => 'Standard Study Modules (PDFs)',
            'actions' => ['view', 'add', 'edit']
        ],
        // Home Categories & Store
        'extra_materials' => [
            'name' => 'Extra Material PDFs',
            'icon' => 'bi-bookmark-star-fill',
            'url' => 'modules/extra_materials/index.php',
            'group' => 'Home Categories & Store',
            'actions' => ['view', 'add', 'edit']
        ],
        'competitive_exams' => [
            'name' => 'Competitive Exams',
            'icon' => 'bi-award-fill',
            'url' => 'modules/competitive_exams/index.php',
            'group' => 'Home Categories & Store',
            'actions' => ['view', 'add', 'edit']
        ],
        'higher_education' => [
            'name' => 'Higher Education',
            'icon' => 'bi-mortarboard-fill',
            'url' => 'modules/higher_education/index.php',
            'group' => 'Home Categories & Store',
            'actions' => ['view', 'add', 'edit']
        ],
        'ai_courses' => [
            'name' => 'AI Course Materials',
            'icon' => 'bi-cpu-fill',
            'url' => 'modules/ai_courses/index.php',
            'group' => 'Home Categories & Store',
            'actions' => ['view', 'add', 'edit']
        ],
        'study_products' => [
            'name' => 'Study Products (Store)',
            'icon' => 'bi-cart4',
            'url' => 'modules/study_products/index.php',
            'group' => 'Home Categories & Store',
            'actions' => ['view', 'add', 'edit']
        ],
        // App Management
        'students' => [
            'name' => 'Registered Students',
            'icon' => 'bi-people-fill',
            'url' => 'modules/students/index.php',
            'group' => 'App Management',
            'actions' => ['view', 'edit']
        ],
        'banners' => [
            'name' => 'Banners & Notices',
            'icon' => 'bi-megaphone-fill',
            'url' => 'modules/banners/index.php',
            'group' => 'App Management',
            'actions' => ['view', 'add', 'edit']
        ],
        'settings' => [
            'name' => 'App Settings & In-App Ads',
            'icon' => 'bi-sliders',
            'url' => 'modules/settings/index.php',
            'group' => 'App Management',
            'actions' => ['view', 'edit']
        ],
        'email_settings' => [
            'name' => 'Email & SMTP Credentials',
            'icon' => 'bi-envelope-at-fill',
            'url' => 'modules/email_settings/index.php',
            'group' => 'App Management',
            'actions' => ['view', 'edit']
        ],
    ];
}

function isSuperAdmin(?array $user = null): bool {
    $user = $user ?? ($_SESSION['admin_user'] ?? []);
    $role = strtolower((string)($user['role'] ?? ''));
    return $role === 'super_admin' || $role === 'admin';
}

function getLoggedInAdminPermissions(): array {
    if (isSuperAdmin()) {
        $all = [];
        foreach (getAllAdminModules() as $modKey => $modInfo) {
            $all[$modKey] = $modInfo['actions'];
        }
        $all['team'] = ['view', 'add', 'edit', 'delete'];
        return $all;
    }
    return $_SESSION['admin_user']['permissions'] ?? [];
}

function hasPermission(string $module, string $action = 'view'): bool {
    if (isSuperAdmin()) {
        return true;
    }

    // Team management is exclusively for super_admin
    if ($module === 'team') {
        return false;
    }

    $perms = $_SESSION['admin_user']['permissions'] ?? [];
    if (!isset($perms[$module]) || !is_array($perms[$module])) {
        return false;
    }

    $action = strtolower($action);
    $userActions = array_map('strtolower', $perms[$module]);

    if ($action === 'update') {
        $action = 'edit';
    }

    if (in_array($action, $userActions, true)) {
        return true;
    }

    // Also support 'update' if user has 'edit'
    if ($action === 'edit' && in_array('update', $userActions, true)) {
        return true;
    }

    return false;
}

function getFirstPermittedModuleUrl(): string {
    if (hasPermission('dashboard', 'view')) {
        return url('modules/dashboard/index.php');
    }

    foreach (getAllAdminModules() as $key => $info) {
        if (hasPermission($key, 'view')) {
            return url($info['url']);
        }
    }

    return url('auth/profile.php');
}

function requirePermission(string $module, string $action = 'view'): void {
    if (!isLoggedIn()) {
        flash('error', 'Please login to access the admin panel.');
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }

    if (!hasPermission($module, $action)) {
        $actionLabel = match($action) {
            'add' => 'create/add data in',
            'edit', 'update' => 'update/edit data in',
            'delete' => 'delete data in',
            default => 'access/view'
        };

        $modInfo = getAllAdminModules()[$module] ?? ['name' => ucfirst($module)];
        flash('error', "Access Denied: You do not have permission to {$actionLabel} the " . htmlspecialchars($modInfo['name']) . " screen.");

        // If AJAX request, send 403 JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => "Access Denied: You do not have permission to {$actionLabel} this section."
            ]);
            exit;
        }

        // Redirect to first permitted module
        header('Location: ' . getFirstPermittedModuleUrl());
        exit;
    }
}

function enforceRoutePermission(): void {
    static $checking = false;
    if ($checking) {
        return;
    }
    $checking = true;

    if (!isLoggedIn() || isSuperAdmin()) {
        $checking = false;
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '');
    $path = parse_url($uri, PHP_URL_PATH) ?? '';

    if (preg_match('#/modules/([a-zA-Z0-9_]+)(?:/([a-zA-Z0-9_]+)\.php)?#i', $path, $m)) {
        $module = strtolower($m[1]);
        $actionFile = isset($m[2]) ? strtolower($m[2]) : 'index';

        if ($module === 'team') {
            flash('error', 'Access Denied: Only Super Administrators can manage team members and privileges.');
            header('Location: ' . getFirstPermittedModuleUrl());
            exit;
        }

        $allModules = getAllAdminModules();
        if (isset($allModules[$module])) {
            $action = match($actionFile) {
                'add', 'create', 'new', 'upload' => 'add',
                'edit', 'update', 'status', 'toggle', 'delete' => 'edit',
                default => 'view'
            };
            requirePermission($module, $action);
        }
    }

    $checking = false;
}

// Flash Messages
function flash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

function setFlashMessage(string $type, string $message): void {
    flash($type, $message);
}

function renderFlashMessages(): string {
    if (!isset($_SESSION['flash_messages']) || empty($_SESSION['flash_messages'])) {
        return '';
    }

    $output = '';
    foreach ($_SESSION['flash_messages'] as $flash) {
        $alertClass = match($flash['type']) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };

        $icon = match($flash['type']) {
            'success' => '<i class="bi bi-check-circle-fill me-2"></i>',
            'error' => '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
            'warning' => '<i class="bi bi-exclamation-circle-fill me-2"></i>',
            default => '<i class="bi bi-info-circle-fill me-2"></i>'
        };

        $output .= '
        <div class="alert ' . $alertClass . ' alert-dismissible fade show custom-alert shadow-sm" role="alert">
            ' . $icon . htmlspecialchars($flash['message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }

    unset($_SESSION['flash_messages']);
    return $output;
}

// Sanitization & Security
function clean(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function getCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// File Upload Handlers (Supports Direct Client-Side Uploads, Local Storage & Supabase Storage)
function uploadFile(array $file, string $targetDir, array $allowedExtensions = ['pdf'], int $maxSizeMB = 500): array {
    // If client-side direct upload already pushed to Supabase
    if (!empty($_POST['direct_uploaded_file'])) {
        $filename = clean($_POST['direct_uploaded_file']);
        $fileSizeMB = isset($_POST['direct_file_size_mb']) ? (float)$_POST['direct_file_size_mb'] : 0.0;
        $bucket = 'pdfs';
        if (str_contains($targetDir, 'banners')) {
            $bucket = 'banners';
        } elseif (str_contains($targetDir, 'products')) {
            $bucket = 'products';
        } elseif (str_contains($targetDir, 'icons')) {
            $bucket = 'icons';
        }
        return [
            'status' => true,
            'filename' => $filename,
            'path' => $targetDir . '/' . $filename,
            'public_url' => resolveMediaUrl($filename, $bucket),
            'size_mb' => $fileSizeMB,
            'original_name' => $filename
        ];
    }

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['status' => false, 'message' => 'Invalid file parameter.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Upload error code: ' . $file['error']];
    }

    $maxBytes = $maxSizeMB * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['status' => false, 'message' => "File exceeds the {$maxSizeMB}MB maximum size limit."];
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['status' => false, 'message' => 'Invalid file extension. Allowed: ' . implode(', ', $allowedExtensions)];
    }

    $filename = uniqid('bb_', true) . '.' . $ext;
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;
    $fileSizeMB = round($file['size'] / (1024 * 1024), 2);

    // Determine bucket name
    $bucket = 'pdfs';
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'mp4', 'webm', 'mov'])) {
        if (str_contains($targetDir, 'banners')) {
            $bucket = 'banners';
        } elseif (str_contains($targetDir, 'products')) {
            $bucket = 'products';
        } elseif (str_contains($targetDir, 'icons')) {
            $bucket = 'icons';
        }
    }

    $supabase = SupabaseService::getInstance();
    $publicUrl = '';
    $supabaseSuccess = false;

    // Detect MIME type safely
    $mimeType = 'application/octet-stream';
    if ($ext === 'pdf') {
        $mimeType = 'application/pdf';
    } elseif (in_array($ext, ['jpg', 'jpeg'])) {
        $mimeType = 'image/jpeg';
    } elseif ($ext === 'png') {
        $mimeType = 'image/png';
    } elseif ($ext === 'webp') {
        $mimeType = 'image/webp';
    } elseif ($ext === 'svg') {
        $mimeType = 'image/svg+xml';
    } elseif ($ext === 'gif') {
        $mimeType = 'image/gif';
    } elseif ($ext === 'mp4') {
        $mimeType = 'video/mp4';
    } elseif ($ext === 'webm') {
        $mimeType = 'video/webm';
    } elseif ($ext === 'mov') {
        $mimeType = 'video/quicktime';
    } elseif (function_exists('mime_content_type') && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $detected = @mime_content_type($file['tmp_name']);
        if ($detected) $mimeType = $detected;
    }

    // 1. Upload to Supabase Storage (if configured and file size <= maxSizeMB)
    $maxBytes = $maxSizeMB * 1024 * 1024;
    if ($supabase->isConfigured() && $file['size'] <= $maxBytes) {
        $uploadResult = $supabase->uploadToStorage($bucket, $filename, $file['tmp_name'], $mimeType);
        if ($uploadResult['status']) {
            $supabaseSuccess = true;
            $publicUrl = $uploadResult['public_url'];
        }
    }

    // 2. Try saving to local disk if directory is writable (e.g. on XAMPP/VPS)
    $localSaved = false;
    if (is_dir($targetDir) && is_writable($targetDir)) {
        if (@move_uploaded_file($file['tmp_name'], $targetPath) || @copy($file['tmp_name'], $targetPath)) {
            $localSaved = true;
        }
    }

    // If neither Supabase nor local disk succeeded
    if (!$supabaseSuccess && !$localSaved) {
        // If Supabase wasn't configured or failed and local disk write failed
        return [
            'status' => false,
            'message' => 'Failed to save file: Supabase Storage upload ' . ($supabase->isConfigured() ? 'failed' : 'not configured') . ' and local directory is not writable.'
        ];
    }

    return [
        'status' => true,
        'filename' => $filename,
        'path' => $targetPath,
        'public_url' => $publicUrl,
        'size_mb' => $fileSizeMB,
        'original_name' => $file['name']
    ];
}

// Media URL Resolver (Handles both Supabase Storage Public URLs and Local Paths for large files)
function resolveMediaUrl(?string $fileName, string $bucketType = 'pdfs'): string {
    if (empty($fileName)) {
        return '';
    }
    // If it's already a full HTTP(S) URL
    if (str_starts_with($fileName, 'http://') || str_starts_with($fileName, 'https://')) {
        return $fileName;
    }

    $targetFolder = defined('PDF_UPLOADS_PATH') && $bucketType === 'pdfs' ? PDF_UPLOADS_PATH : (UPLOADS_PATH . $bucketType . '/');
    $localFilePath = $targetFolder . $fileName;
    $isLargeFile = is_file($localFilePath) && filesize($localFilePath) > 52428800;

    $supabase = SupabaseService::getInstance();
    if ($supabase->isConfigured() && !$isLargeFile) {
        return $supabase->getStoragePublicUrl($bucketType, $fileName);
    }

    return UPLOADS_URL . "{$bucketType}/" . rawurlencode($fileName);
}

// URL Helper
function url(string $path = ''): string {
    return BASE_URL . ltrim($path, '/');
}

// Format Numbers / Bytes
function formatBytes(int|float $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = (float)max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min((int)$pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Status Badge Helper
function statusBadge(string $status): string {
    if (strtolower($status) === 'active') {
        return '<span class="badge bg-success">Active</span>';
    }
    return '<span class="badge bg-secondary">Inactive</span>';
}

// API JSON Response Formatter
function jsonResponse(string $status, string $message, mixed $data = null, int $httpCode = 200): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    http_response_code($httpCode);

    $response = [
        'status' => $status,
        'message' => $message,
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
