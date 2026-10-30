<?php
/**
 * Verify Google Doc / Question Link Access
 * Book Banko Admin Panel API
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Allow logged in admins
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$rawUrl = trim($_POST['url'] ?? $_GET['url'] ?? '');
if (empty($rawUrl)) {
    echo json_encode(['status' => 'error', 'message' => 'URL is required']);
    exit;
}

$isGoogleDoc = (bool)preg_match('/docs\.google\.com\/document\/d\/([a-zA-Z0-9_-]+)/', $rawUrl, $matches);
$cleanPreviewUrl = $rawUrl;
$docId = $matches[1] ?? null;

if ($isGoogleDoc && $docId) {
    // Generate clean preview URL that avoids editor UI and account prompt
    $cleanPreviewUrl = "https://docs.google.com/document/d/{$docId}/preview";

    // Test public accessibility
    $testUrl = "https://docs.google.com/document/d/{$docId}/export?format=txt";
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo json_encode([
            'status' => 'success',
            'is_google_doc' => true,
            'doc_id' => $docId,
            'clean_preview_url' => $cleanPreviewUrl,
            'is_public' => true,
            'http_code' => 200,
            'message' => 'Verified Public: All students can open and view directly without needing Google sign-in or email access requests!'
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'is_google_doc' => true,
            'doc_id' => $docId,
            'clean_preview_url' => $cleanPreviewUrl,
            'is_public' => false,
            'http_code' => $httpCode,
            'message' => 'Restricted Permission: This Google Doc is set to Restricted. Please open Google Docs > click Share > change General access to "Anyone with the link" (Viewer) so you don\'t receive access request emails.'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'success',
        'is_google_doc' => false,
        'clean_preview_url' => $rawUrl,
        'is_public' => true,
        'message' => 'Standard link configured.'
    ]);
}
