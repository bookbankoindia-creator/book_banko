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
}

function currentAdmin(): array {
    return $_SESSION['admin_user'] ?? [
        'id' => 0,
        'username' => 'Guest',
        'full_name' => 'Guest User',
        'role' => 'viewer'
    ];
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
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'mp4', 'webm'])) {
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
    } elseif ($ext === 'mp4') {
        $mimeType = 'video/mp4';
    } elseif ($ext === 'webm') {
        $mimeType = 'video/webm';
    } elseif (function_exists('mime_content_type') && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $detected = @mime_content_type($file['tmp_name']);
        if ($detected) $mimeType = $detected;
    }

    // 1. Upload to Supabase Storage (if configured and file size <= 50MB)
    if ($supabase->isConfigured() && $file['size'] <= 52428800) {
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
