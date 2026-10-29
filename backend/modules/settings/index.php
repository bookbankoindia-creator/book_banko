<?php
/**
 * App Settings & Configuration
 * Book Banko
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'App Settings & Config';
$pageSubtitle = 'Configure global application parameters, versioning, notices, and maintenance mode';
$db = Database::getConnection();

// Fetch current settings
$rows = $db->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
$current = [];
foreach ($rows as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}

$defaults = [
    'custom_ad_enabled' => '1',
    'custom_ad_layout' => 'fullscreen',
    'custom_ad_media_type' => 'video',
    'custom_ad_title' => 'Best JEE & NEET Preparation Materials',
    'custom_ad_sub_title' => 'Top question bank, chapter-wise test series & expert video solutions!',
    'custom_ad_badge_text' => 'SPECIAL PROMOTION',
    'custom_ad_pills' => '',
    'custom_ad_image' => '',
    'custom_ad_video' => '',
    'custom_ad_target_url' => 'https://bookbanko.com',
    'custom_ad_button_text' => 'Explore Questions Now 🚀',
    'custom_ad_skip_seconds' => '3',
    'custom_ad_require_complete' => '0',
    'custom_ad_auto_close' => '0',
];
$current = array_merge($defaults, $current);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf)) {
        flash('error', 'Invalid security token.');
    } else {
        // Handle Custom Ad Image (File upload or direct URL)
        $customAdImage = $current['custom_ad_image'] ?? '';
        if (!empty($_FILES['custom_ad_image_file']['name'])) {
            $uploadResult = uploadFile($_FILES['custom_ad_image_file'], BANNER_UPLOADS_PATH, ['jpg', 'jpeg', 'png', 'webp', 'gif'], 20);
            if ($uploadResult['status']) {
                $customAdImage = !empty($uploadResult['public_url']) ? $uploadResult['public_url'] : $uploadResult['filename'];
            } else {
                flash('warning', 'Ad image upload notice: ' . ($uploadResult['message'] ?? 'Failed to upload'));
            }
        } elseif (isset($_POST['custom_ad_image_url'])) {
            $customAdImage = trim($_POST['custom_ad_image_url']);
        }

        // Handle Custom Ad Video (File upload or direct URL)
        $customAdVideo = $current['custom_ad_video'] ?? '';
        if (!empty($_FILES['custom_ad_video_file']['name'])) {
            $videoResult = uploadFile($_FILES['custom_ad_video_file'], BANNER_UPLOADS_PATH, ['mp4', 'webm', 'mov'], 100);
            if ($videoResult['status']) {
                $customAdVideo = !empty($videoResult['public_url']) ? $videoResult['public_url'] : $videoResult['filename'];
            } else {
                flash('warning', 'Ad video upload notice: ' . ($videoResult['message'] ?? 'Failed to upload'));
            }
        } elseif (isset($_POST['custom_ad_video_url'])) {
            $customAdVideo = trim($_POST['custom_ad_video_url']);
        }

        // Handle direct upload if performed via client-side Supabase Storage Uploader
        if (!empty($_POST['direct_uploaded_file'])) {
            $directFile = clean($_POST['direct_uploaded_file']);
            $directUrl = resolveMediaUrl($directFile, 'banners');
            $targetField = $_POST['direct_upload_target_input'] ?? '';
            $directExt = strtolower(pathinfo($directFile, PATHINFO_EXTENSION));

            if ($targetField === 'custom_ad_video_file' || in_array($directExt, ['mp4', 'webm', 'mov'])) {
                $customAdVideo = $directUrl;
            } elseif ($targetField === 'custom_ad_image_file' || in_array($directExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $customAdImage = $directUrl;
            }
        }

        $settings = [
            'app_name' => trim($_POST['app_name'] ?? 'Book Banko'),
            'app_version' => trim($_POST['app_version'] ?? '1.0.0'),
            'force_update' => isset($_POST['force_update']) ? '1' : '0',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'maintenance_message' => trim($_POST['maintenance_message'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'privacy_policy_url' => trim($_POST['privacy_policy_url'] ?? ''),
            'terms_conditions_url' => trim($_POST['terms_conditions_url'] ?? ''),
            'notice_title' => trim($_POST['notice_title'] ?? ''),
            'notice_message' => trim($_POST['notice_message'] ?? ''),
            'default_jee_neet_text' => trim($_POST['default_jee_neet_text'] ?? '👉 Click here for the best JEE & NEET Questions ↗'),
            // Dynamic In-House Custom Ad Configurations
            'custom_ad_enabled' => isset($_POST['custom_ad_enabled']) ? '1' : '0',
            'custom_ad_layout' => trim($_POST['custom_ad_layout'] ?? 'modal'),
            'custom_ad_media_type' => trim($_POST['custom_ad_media_type'] ?? 'image'),
            'custom_ad_title' => trim($_POST['custom_ad_title'] ?? 'Best JEE & NEET Preparation Materials'),
            'custom_ad_sub_title' => trim($_POST['custom_ad_sub_title'] ?? 'Top question bank, chapter-wise test series & expert video solutions!'),
            'custom_ad_badge_text' => trim($_POST['custom_ad_badge_text'] ?? 'SPECIAL PROMOTION'),
            'custom_ad_pills' => trim($_POST['custom_ad_pills'] ?? 'Verified, Top Ranks, High Yield'),
            'custom_ad_image' => $customAdImage,
            'custom_ad_video' => $customAdVideo,
            'custom_ad_target_url' => trim($_POST['custom_ad_target_url'] ?? ''),
            'custom_ad_button_text' => trim($_POST['custom_ad_button_text'] ?? 'Explore Questions Now 🚀'),
            'custom_ad_skip_seconds' => max(0, (int)($_POST['custom_ad_skip_seconds'] ?? 3)),
            'custom_ad_require_complete' => isset($_POST['custom_ad_require_complete']) ? '1' : '0',
            'custom_ad_auto_close' => isset($_POST['custom_ad_auto_close']) ? '1' : '0',
            'admob_enabled' => '0'
        ];

        try {
            $isPgsql = (Database::getDriver() === 'pgsql');
            $sql = $isPgsql
                ? "INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :val) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
                : "INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val2";

            $stmt = $db->prepare($sql);

            foreach ($settings as $k => $v) {
                $params = [':key' => $k, ':val' => $v];
                if (!$isPgsql) {
                    $params[':val2'] = $v;
                }
                $stmt->execute($params);
            }

            flash('success', 'Application settings & Custom Ad configuration saved successfully.');
            header('Location: ' . url('modules/settings/index.php'));
            exit;
        } catch (PDOException $e) {
            flash('error', 'Error saving settings: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">

            <!-- General App Config -->
            <div class="bb-card mb-4">
                <div class="bb-card-header">
                    <h5><i class="bi bi-app-indicator text-primary me-2"></i>General Application Info</h5>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="app_name">App Display Name</label>
                        <input type="text" class="form-control" id="app_name" name="app_name" value="<?= htmlspecialchars($current['app_name'] ?? 'Book Banko') ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="app_version">Current App Version</label>
                        <input type="text" class="form-control" id="app_version" name="app_version" value="<?= htmlspecialchars($current['app_version'] ?? '1.0.0') ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="contact_email">Support Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= htmlspecialchars($current['contact_email'] ?? 'support@bookbanko.com') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch p-3 bg-light rounded-3 border">
                            <input class="form-check-input" type="checkbox" role="switch" id="force_update" name="force_update" value="1" <?= ($current['force_update'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold ms-2 text-dark" for="force_update">
                                Force Update Required
                            </label>
                            <div class="text-muted small ms-2">Forces students with older versions to update before accessing textbooks.</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch p-3 bg-light rounded-3 border">
                            <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" <?= ($current['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold ms-2 text-danger" for="maintenance_mode">
                                Maintenance Mode
                            </label>
                            <div class="text-muted small ms-2">Temporarily disable app requests and display maintenance dialog.</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="maintenance_message">Maintenance Message</label>
                        <input type="text" class="form-control" id="maintenance_message" name="maintenance_message" value="<?= htmlspecialchars($current['maintenance_message'] ?? '') ?>" placeholder="We are updating our content library. Please check back shortly.">
                    </div>
                </div>
            </div>

            <!-- Home Screen Announcement Notice -->
            <div class="bb-card mb-4">
                <div class="bb-card-header">
                    <h5><i class="bi bi-megaphone-fill text-warning me-2"></i>Global In-App Announcement Banner</h5>
                </div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label" for="notice_title">Notice Headline</label>
                        <input type="text" class="form-control" id="notice_title" name="notice_title" value="<?= htmlspecialchars($current['notice_title'] ?? '') ?>" placeholder="e.g. Welcome to Book Banko!">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label" for="notice_message">Notice Message Body</label>
                        <textarea class="form-control" id="notice_message" name="notice_message" rows="2" placeholder="Announce new study materials, updates, or board news"><?= htmlspecialchars($current['notice_message'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Textbook & PDF Reader Container Settings -->
            <div class="bb-card mb-4">
                <div class="bb-card-header">
                    <h5><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Textbook & PDF Reader Options</h5>
                </div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label" for="default_jee_neet_text">Default JEE & NEET Container Text (Textbooks)</label>
                        <input type="text" class="form-control" id="default_jee_neet_text" name="default_jee_neet_text" value="<?= htmlspecialchars($current['default_jee_neet_text'] ?? '👉 Click here for the best JEE & NEET Questions ↗') ?>" placeholder="👉 Click here for the best JEE & NEET Questions ↗">
                        <small class="text-muted">Global default text shown on the clickable bottom container for textbook PDFs. Can also be overridden per individual chapter in the Chapter Editor.</small>
                    </div>
                </div>
            </div>

            <!-- Dynamic In-House Custom Ads (Self-Hosted Ads with Live Mobile Simulator) -->
            <div class="bb-card mb-4 border-success shadow-sm">
                <div class="bb-card-header d-flex justify-content-between align-items-center bg-light flex-wrap gap-2">
                    <h5 class="mb-0 text-success"><i class="bi bi-badge-ad-fill me-2"></i>In-House Custom Ad Engine & Simulator</h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success text-white"><i class="bi bi-shield-check me-1"></i>100% Dynamic • No AdMob</span>
                        <span class="badge bg-primary text-white"><i class="bi bi-play-circle me-1"></i>Video & Image Supported</span>
                    </div>
                </div>

                <div class="p-3 bg-light border-bottom">
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" id="custom_ad_enabled" name="custom_ad_enabled" value="1" <?= ($current['custom_ad_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold ms-2 text-dark" for="custom_ad_enabled">
                            Enable In-House Custom Ad on JEE & NEET Button
                        </label>
                    </div>
                    <small class="text-muted ms-1">When students tap "Click here for the best JEE & NEET Questions" in textbook PDFs, this customized promotional ad appears.</small>
                </div>

                <div class="row g-4 p-3">
                    <!-- Left: Ad Controls & Inputs -->
                    <div class="col-lg-7">
                        <div class="row g-3">
                            <!-- Display Layout & Media Format -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold"><i class="bi bi-aspect-ratio me-1 text-primary"></i>Ad Display Size</label>
                                <select class="form-select" id="custom_ad_layout" name="custom_ad_layout">
                                    <option value="modal" <?= ($current['custom_ad_layout'] ?? 'modal') === 'modal' ? 'selected' : '' ?>>📱 Popup Dialog Card (Centered)</option>
                                    <option value="fullscreen" <?= ($current['custom_ad_layout'] ?? '') === 'fullscreen' ? 'selected' : '' ?>>📺 Full-Screen Interstitial (100% Screen)</option>
                                </select>
                                <small class="text-muted">Choose whether ad pops up as a dialog card or takes full screen.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold"><i class="bi bi-film me-1 text-primary"></i>Media Format</label>
                                <select class="form-select" id="custom_ad_media_type" name="custom_ad_media_type">
                                    <option value="image" <?= ($current['custom_ad_media_type'] ?? 'image') === 'image' ? 'selected' : '' ?>>🖼️ Image Banner Ad</option>
                                    <option value="video" <?= ($current['custom_ad_media_type'] ?? '') === 'video' ? 'selected' : '' ?>>🎬 Video Ad (MP4 / WebM)</option>
                                </select>
                                <small class="text-muted">Display a graphic banner or a playable promo video.</small>
                            </div>

                            <!-- Skip Timer & Close Button Rules -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="custom_ad_skip_seconds">
                                    <i class="bi bi-stopwatch text-warning me-1"></i>Close Button Delay (Seconds)
                                </label>
                                <div class="input-group">
                                    <input type="number" min="0" max="60" class="form-control" id="custom_ad_skip_seconds" name="custom_ad_skip_seconds" value="<?= htmlspecialchars($current['custom_ad_skip_seconds'] ?? '3') ?>" placeholder="3">
                                    <span class="input-group-text">sec</span>
                                </div>
                                <div class="btn-group btn-group-sm mt-1 w-100" role="group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setTimer(0)">0s (Instant)</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setTimer(3)">3s</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setTimer(5)">5s</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setTimer(10)">10s</button>
                                </div>
                                <small class="text-muted d-block mt-1">Countdown time before the Close (X) button appears/unlocks.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold"><i class="bi bi-shield-lock text-danger me-1"></i>Viewing Rules</label>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="custom_ad_require_complete" name="custom_ad_require_complete" value="1" <?= ($current['custom_ad_require_complete'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold" for="custom_ad_require_complete">
                                        Must finish video before close button appears
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="custom_ad_auto_close" name="custom_ad_auto_close" value="1" <?= ($current['custom_ad_auto_close'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold" for="custom_ad_auto_close">
                                        Auto-redirect to target link when timer ends
                                    </label>
                                </div>
                            </div>

                            <!-- Banner Image Settings -->
                            <div class="col-12 p-3 bg-light rounded-3 border" id="image_settings_box">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-image me-1"></i>Ad Banner Image</h6>
                                    <span class="badge bg-white text-secondary border">JPG, PNG, WEBP, GIF (Max 20MB)</span>
                                </div>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-semibold" for="custom_ad_image_file">Select Banner Image</label>
                                        <input type="file" class="form-control" id="custom_ad_image_file" name="custom_ad_image_file" accept="image/png,image/jpeg,image/webp,image/gif">
                                        <div class="mt-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <label class="form-label small text-muted mb-0" for="custom_ad_image_url">Or Image URL / Cloud Path</label>
                                                <button type="button" class="btn btn-link btn-xs text-danger p-0 text-decoration-none" onclick="clearImageMedia()">
                                                    <i class="bi bi-trash3 me-1"></i>Clear Image
                                                </button>
                                            </div>
                                            <input type="text" class="form-control form-control-sm font-monospace mt-1" id="custom_ad_image_url" name="custom_ad_image_url" value="<?= htmlspecialchars($current['custom_ad_image'] ?? '') ?>" placeholder="https://.../banner.png or bb_123.jpg">
                                        </div>
                                    </div>
                                    <div class="col-md-5 text-center">
                                        <label class="form-label small fw-semibold">Image Preview</label>
                                        <div class="border rounded bg-white p-1 position-relative" style="height: 100px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                            <?php $hasImg = !empty($current['custom_ad_image']); ?>
                                            <img id="current_image_thumb" src="<?= $hasImg ? htmlspecialchars(resolveMediaUrl($current['custom_ad_image'], 'banners')) : '' ?>" alt="Banner" class="img-fluid rounded" style="max-height: 90px; object-fit: contain; <?= $hasImg ? '' : 'display: none;' ?>">
                                            <div id="no_image_placeholder" class="text-muted small" style="<?= $hasImg ? 'display: none;' : '' ?>">
                                                <i class="bi bi-image text-muted fs-3 d-block"></i>
                                                <span>No image uploaded</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Video Settings -->
                            <div class="col-12 p-3 bg-light rounded-3 border" id="video_settings_box">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-camera-video me-1"></i>Ad Video File / Stream URL</h6>
                                    <span class="badge bg-white text-secondary border">MP4, WebM, MOV (Max 100MB)</span>
                                </div>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-semibold" for="custom_ad_video_file">Select Promo Video</label>
                                        <input type="file" class="form-control" id="custom_ad_video_file" name="custom_ad_video_file" accept="video/mp4,video/webm,video/quicktime">
                                        <div class="mt-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <label class="form-label small text-muted mb-0" for="custom_ad_video_url">Or Direct Video URL</label>
                                                <button type="button" class="btn btn-link btn-xs text-danger p-0 text-decoration-none" onclick="clearVideoMedia()">
                                                    <i class="bi bi-trash3 me-1"></i>Clear Video
                                                </button>
                                            </div>
                                            <input type="text" class="form-control form-control-sm font-monospace mt-1" id="custom_ad_video_url" name="custom_ad_video_url" value="<?= htmlspecialchars($current['custom_ad_video'] ?? '') ?>" placeholder="https://example.com/promo.mp4">
                                        </div>
                                    </div>
                                    <div class="col-md-5 text-center">
                                        <label class="form-label small fw-semibold">Video Preview Player</label>
                                        <div class="border rounded bg-white p-2 position-relative" style="min-height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                            <?php $hasVideo = !empty($current['custom_ad_video']); ?>
                                            <video id="current_video_player" src="<?= $hasVideo ? htmlspecialchars(resolveMediaUrl($current['custom_ad_video'], 'banners')) : '' ?>" controls playsinline style="max-width: 100%; max-height: 80px; border-radius: 6px; <?= $hasVideo ? '' : 'display: none;' ?>"></video>
                                            <div id="no_video_placeholder" class="text-muted small" style="<?= $hasVideo ? 'display: none;' : '' ?>">
                                                <i class="bi bi-film text-muted fs-3 d-block"></i>
                                                <span>No video uploaded</span>
                                            </div>
                                            <small id="video_filename_badge" class="text-success fw-bold text-truncate w-100 mt-1" style="<?= $hasVideo ? '' : 'display: none;' ?>"><?= $hasVideo ? htmlspecialchars(basename($current['custom_ad_video'])) : '' ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ad Content Text Inputs -->
                            <div class="col-md-6" id="badge_text_group">
                                <label class="form-label fw-bold" for="custom_ad_badge_text">Promo Top Badge Text</label>
                                <input type="text" class="form-control" id="custom_ad_badge_text" name="custom_ad_badge_text" value="<?= htmlspecialchars($current['custom_ad_badge_text'] ?? 'SPECIAL PROMOTION') ?>" placeholder="SPECIAL PROMOTION">
                                <small class="text-muted">Top corner tag in Dialog popup</small>
                            </div>

                            <div class="col-md-6" id="pills_group">
                                <label class="form-label fw-bold" for="custom_ad_pills">Feature Badges (Optional)</label>
                                <input type="text" class="form-control" id="custom_ad_pills" name="custom_ad_pills" value="<?= htmlspecialchars($current['custom_ad_pills'] ?? '') ?>" placeholder="Verified, Top Ranks, High Yield">
                                <small class="text-muted">Used in popup dialog (hidden in clean full-screen mode)</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold text-primary" for="custom_ad_title">
                                    <i class="bi bi-type-h1 me-1"></i>Line 1: Ad Headline / Title (Bold)
                                </label>
                                <input type="text" class="form-control fw-semibold" id="custom_ad_title" name="custom_ad_title" value="<?= htmlspecialchars($current['custom_ad_title'] ?? 'Best JEE & NEET Preparation Materials') ?>" placeholder="e.g. Best JEE & NEET Preparation Materials">
                                <small class="text-muted">First text line displayed prominently over video.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold text-primary" for="custom_ad_sub_title">
                                    <i class="bi bi-card-text me-1"></i>Line 2: Ad Subtitle / Description (Concise)
                                </label>
                                <textarea class="form-control" id="custom_ad_sub_title" name="custom_ad_sub_title" rows="2" placeholder="Top question bank, chapter-wise test series & expert video solutions!"><?= htmlspecialchars($current['custom_ad_sub_title'] ?? 'Top question bank, chapter-wise test series & expert video solutions!') ?></textarea>
                                <small class="text-muted">Second text line displayed cleanly below title.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="custom_ad_button_text">Call to Action (CTA) Button Label</label>
                                <input type="text" class="form-control" id="custom_ad_button_text" name="custom_ad_button_text" value="<?= htmlspecialchars($current['custom_ad_button_text'] ?? 'Explore Questions Now 🚀') ?>" placeholder="Explore Questions Now 🚀">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="custom_ad_target_url">Target Redirect URL</label>
                                <input type="url" class="form-control font-monospace" id="custom_ad_target_url" name="custom_ad_target_url" value="<?= htmlspecialchars($current['custom_ad_target_url'] ?? '') ?>" placeholder="https://t.me/your_channel or https://website.com">
                                <small class="text-muted">Leave blank to open chapter's JEE/NEET link.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Interactive Mobile Phone Mockup -->
                    <div class="col-lg-5">
                        <div class="sticky-top" style="top: 20px;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark"><i class="bi bi-phone me-1"></i>Live Mobile App Simulator</span>
                                <button type="button" class="btn btn-xs btn-outline-primary" id="btn_test_timer" onclick="testCountdown()">
                                    <i class="bi bi-play-fill me-1"></i>Test Timer Animation
                                </button>
                            </div>

                            <!-- Phone Bezel Frame -->
                            <div style="background: #111827; border: 10px solid #1f2937; border-radius: 36px; padding: 10px; max-width: 320px; margin: 0 auto; box-shadow: 0 20px 45px rgba(0,0,0,0.35);">
                                <!-- Status Bar -->
                                <div class="d-flex justify-content-between align-items-center text-white px-2 mb-2" style="font-size: 11px;">
                                    <span class="fw-bold">14:22</span>
                                    <div class="d-flex gap-1 align-items-center">
                                        <i class="bi bi-wifi"></i>
                                        <i class="bi bi-reception-4"></i>
                                        <span class="badge bg-secondary p-1" style="font-size: 9px;">28</span>
                                    </div>
                                </div>

                                <!-- Screen Content Area -->
                                <div id="phone_screen_container" style="background: #000; min-height: 480px; border-radius: 24px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 0;">
                                    <!-- Blurred background document hint -->
                                    <div id="phone_blur_backdrop" style="position: absolute; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); display: none;"></div>

                                    <!-- Ad Modal Card / Fullscreen Container -->
                                    <div id="mockup_ad_card" style="position: relative; z-index: 10; width: 100%; height: 100%; min-height: 480px; background: #000; border-radius: 24px; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-end; transition: all 0.3s ease;">
                                        <!-- Header Image / Video (Fills entire screen in fullscreen) -->
                                        <div id="mockup_header" style="position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; background: #000; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                            <!-- Image layer -->
                                            <img id="mockup_header_img" src="<?= !empty($current['custom_ad_image']) ? htmlspecialchars(resolveMediaUrl($current['custom_ad_image'], 'banners')) : '' ?>" alt="Ad Banner" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; <?= empty($current['custom_ad_image']) ? 'display: none;' : '' ?>">

                                            <!-- Video layer -->
                                            <video id="mockup_header_video" src="<?= !empty($current['custom_ad_video']) ? htmlspecialchars(resolveMediaUrl($current['custom_ad_video'], 'banners')) : '' ?>" autoplay muted loop playsinline style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: none;"></video>

                                            <!-- Fallback Icon / Video Play Overlay -->
                                            <div id="mockup_header_icon" style="text-align: center; color: #fff; z-index: 2;">
                                                <div id="mockup_play_badge" style="width: 54px; height: 54px; background: rgba(0,0,0,0.45); border: 2px solid rgba(255,255,255,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                                                    <i id="mockup_center_icon" class="bi bi-mortarboard-fill fs-4 text-white"></i>
                                                </div>
                                                <div id="mockup_header_text" class="small fw-bold mt-1 text-white-50" style="font-size: 10px;">JEE & NEET Prep Exclusive</div>
                                            </div>

                                            <!-- Mute Toggle Icon (Fullscreen Video) -->
                                            <div id="mockup_sound_btn" style="position: absolute; top: 10px; left: 10px; z-index: 5;">
                                                <div style="background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.25); width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                                    <i class="bi bi-volume-up-fill text-white" style="font-size: 12px;"></i>
                                                </div>
                                            </div>

                                            <!-- Promo Pill Badge (Modal mode) -->
                                            <div id="mockup_badge_wrapper" style="position: absolute; top: 8px; left: 8px; z-index: 4; display: none;">
                                                <span class="badge" style="background: rgba(0,0,0,0.65); border: 1px solid rgba(255,255,255,0.3); font-size: 8.5px; border-radius: 12px; padding: 4px 7px;">
                                                    <i class="bi bi-star-fill text-warning me-1"></i><span id="preview_badge_text"><?= htmlspecialchars($current['custom_ad_badge_text'] ?? 'SPECIAL PROMOTION') ?></span>
                                                </span>
                                            </div>

                                            <!-- Close / Skip Button -->
                                            <div style="position: absolute; top: 10px; right: 10px; z-index: 5;">
                                                <div id="mockup_close_btn" style="background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.3); width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.25);">
                                                    <i class="bi bi-x-lg text-white" style="font-size: 12px;"></i>
                                                </div>
                                                <span id="mockup_countdown_badge" class="badge bg-dark text-white shadow-sm border border-secondary" style="font-size: 9px; display: none; padding: 4px 8px; border-radius: 12px;">Skip in 3s</span>
                                            </div>
                                        </div>

                                        <!-- Card Body Content: Clean 2 Lines + CTA Button -->
                                        <div id="mockup_card_body" style="position: absolute; bottom: 0; left: 0; right: 0; z-index: 10; padding: 35px 14px 16px; background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.72) 40%, rgba(0,0,0,0.96) 100%);">
                                            <!-- Line 1: Headline / Title -->
                                            <div id="preview_title_text" class="fw-bold text-white lh-sm" style="font-size: 14.5px; margin-bottom: 4px; text-shadow: 0 1px 3px rgba(0,0,0,0.6);">
                                                <?= htmlspecialchars($current['custom_ad_title'] ?? 'Best JEE & NEET Preparation Materials') ?>
                                            </div>
                                            <!-- Line 2: Subtitle / Description -->
                                            <div id="preview_subtitle_text" class="lh-sm" style="font-size: 11px; margin-bottom: 14px; color: rgba(255,255,255,0.85); text-shadow: 0 1px 2px rgba(0,0,0,0.6);">
                                                <?= htmlspecialchars($current['custom_ad_sub_title'] ?? 'Top question bank, chapter-wise test series & expert video solutions!') ?>
                                            </div>

                                            <!-- Feature Tags (Shown only in modal mode) -->
                                            <div id="preview_pills_container" class="d-flex flex-wrap gap-1 mb-3" style="display: none !important;">
                                                <!-- Populated by JS -->
                                            </div>

                                            <!-- CTA Action Button -->
                                            <button type="button" class="btn btn-primary w-100 rounded-3 py-2 fw-bold text-white shadow border-0 d-flex align-items-center justify-content-center" style="background: linear-gradient(90deg, #0061A4, #0284C7); font-size: 12.5px;">
                                                <span id="preview_button_label" class="text-truncate me-1"><?= htmlspecialchars($current['custom_ad_button_text'] ?? 'Explore Questions Now 🚀') ?></span>
                                                <i class="bi bi-arrow-right"></i>
                                            </button>

                                            <!-- Skip Text -->
                                            <div class="text-center mt-2">
                                                <small id="preview_skip_text" style="font-size: 10px; color: rgba(255,255,255,0.65);">Continue Reading PDF</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- JavaScript for Live Simulator Real-time Synchronization -->
            <script>
            function setTimer(sec) {
                document.getElementById('custom_ad_skip_seconds').value = sec;
                updateSimulator();
                testCountdown();
            }

            function updateSimulator() {
                // Title & Subtitle
                const title = document.getElementById('custom_ad_title').value || 'Best JEE & NEET Preparation Materials';
                const subtitle = document.getElementById('custom_ad_sub_title').value || 'Top question bank, chapter-wise test series & expert video solutions!';
                const badge = document.getElementById('custom_ad_badge_text').value || 'SPECIAL PROMOTION';
                const buttonText = document.getElementById('custom_ad_button_text').value || 'Explore Questions Now 🚀';
                const layout = document.getElementById('custom_ad_layout').value;
                const mediaType = document.getElementById('custom_ad_media_type').value;
                const pillsText = document.getElementById('custom_ad_pills').value || '';
                const imageUrl = document.getElementById('custom_ad_image_url').value;

                document.getElementById('preview_title_text').innerText = title;
                document.getElementById('preview_subtitle_text').innerText = subtitle;
                document.getElementById('preview_badge_text').innerText = badge;
                document.getElementById('preview_button_label').innerText = buttonText;

                // Feature Pills
                const container = document.getElementById('preview_pills_container');
                container.innerHTML = '';
                if (pillsText) {
                    pillsText.split(',').forEach(pill => {
                        const clean = pill.trim();
                        if (clean) {
                            const span = document.createElement('span');
                            span.className = 'badge border text-primary';
                            span.style.background = '#F0F6FF';
                            span.style.fontSize = '9px';
                            span.style.padding = '3px 6px';
                            span.innerHTML = '✔ ' + clean;
                            container.appendChild(span);
                        }
                    });
                }

                // Layout (Modal vs Fullscreen)
                const card = document.getElementById('mockup_ad_card');
                const screen = document.getElementById('phone_screen_container');
                const header = document.getElementById('mockup_header');
                const cardBody = document.getElementById('mockup_card_body');
                const soundBtn = document.getElementById('mockup_sound_btn');
                const badgeWrapper = document.getElementById('mockup_badge_wrapper');
                const titleEl = document.getElementById('preview_title_text');
                const subTitleEl = document.getElementById('preview_subtitle_text');
                const skipTextEl = document.getElementById('preview_skip_text');
                const blurBackdrop = document.getElementById('phone_blur_backdrop');
                const closeBtn = document.getElementById('mockup_close_btn');

                if (layout === 'fullscreen') {
                    screen.style.padding = '0';
                    if (blurBackdrop) blurBackdrop.style.display = 'none';

                    card.style.height = '100%';
                    card.style.minHeight = '480px';
                    card.style.borderRadius = '24px';
                    card.style.background = '#000000';
                    card.style.display = 'flex';
                    card.style.flexDirection = 'column';
                    card.style.justifyContent = 'flex-end';

                    header.style.position = 'absolute';
                    header.style.inset = '0';
                    header.style.width = '100%';
                    header.style.height = '100%';
                    header.style.zIndex = '1';

                    cardBody.style.position = 'absolute';
                    cardBody.style.bottom = '0';
                    cardBody.style.left = '0';
                    cardBody.style.right = '0';
                    cardBody.style.zIndex = '10';
                    cardBody.style.background = 'linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.72) 40%, rgba(0,0,0,0.96) 100%)';
                    cardBody.style.padding = '35px 14px 16px';

                    titleEl.style.color = '#ffffff';
                    titleEl.style.fontSize = '14.5px';
                    titleEl.style.fontWeight = '700';
                    titleEl.style.marginBottom = '4px';

                    subTitleEl.style.color = 'rgba(255,255,255,0.85)';
                    subTitleEl.style.fontSize = '11px';
                    subTitleEl.style.marginBottom = '14px';

                    container.style.setProperty('display', 'none', 'important');

                    if (soundBtn) soundBtn.style.display = mediaType === 'video' ? 'block' : 'none';
                    if (badgeWrapper) badgeWrapper.style.display = 'none';
                    if (skipTextEl) skipTextEl.style.color = 'rgba(255,255,255,0.65)';
                    if (closeBtn) {
                        closeBtn.style.background = 'rgba(0,0,0,0.6)';
                        closeBtn.querySelector('i').className = 'bi bi-x-lg text-white';
                    }
                } else {
                    screen.style.padding = '12px';
                    if (blurBackdrop) blurBackdrop.style.display = 'block';

                    card.style.height = 'auto';
                    card.style.minHeight = 'auto';
                    card.style.borderRadius = '20px';
                    card.style.background = '#ffffff';
                    card.style.display = 'block';

                    header.style.position = 'relative';
                    header.style.inset = 'auto';
                    header.style.width = '100%';
                    header.style.height = '140px';
                    header.style.zIndex = 'auto';

                    cardBody.style.position = 'static';
                    cardBody.style.background = 'transparent';
                    cardBody.style.padding = '14px 12px 10px';

                    titleEl.style.color = '#111827';
                    titleEl.style.fontSize = '13.5px';
                    titleEl.style.fontWeight = 'bold';
                    titleEl.style.marginBottom = '5px';

                    subTitleEl.style.color = '#6b7280';
                    subTitleEl.style.fontSize = '10.5px';
                    subTitleEl.style.marginBottom = '9px';

                    if (pillsText) {
                        container.style.removeProperty('display');
                        container.style.display = 'flex';
                    }

                    if (soundBtn) soundBtn.style.display = 'none';
                    if (badgeWrapper) badgeWrapper.style.display = 'block';
                    if (skipTextEl) skipTextEl.style.color = '#6c757d';
                    if (closeBtn) {
                        closeBtn.style.background = 'rgba(255,255,255,0.92)';
                        closeBtn.querySelector('i').className = 'bi bi-x-lg text-dark';
                    }
                }

                // Media Format (Image vs Video)
                const playBadge = document.getElementById('mockup_play_badge');
                const centerIcon = document.getElementById('mockup_center_icon');
                const headerImg = document.getElementById('mockup_header_img');
                const headerVideo = document.getElementById('mockup_header_video');
                const videoUrl = document.getElementById('custom_ad_video_url').value;

                // Visual highlight on relevant settings box
                const imgBox = document.getElementById('image_settings_box');
                const vidBox = document.getElementById('video_settings_box');
                if (mediaType === 'video') {
                    if (imgBox) imgBox.classList.remove('border-primary', 'shadow-sm');
                    if (vidBox) vidBox.classList.add('border-primary', 'shadow-sm');

                    if (headerImg) headerImg.style.display = 'none';

                    if (headerVideo && (headerVideo.src || videoUrl)) {
                        if (videoUrl && (!headerVideo.src || !headerVideo.src.includes(videoUrl))) {
                            headerVideo.src = videoUrl;
                        }
                        headerVideo.style.display = 'block';
                        playBadge.style.display = 'none';
                        headerVideo.play().catch(() => {});
                    } else {
                        if (headerVideo) headerVideo.style.display = 'none';
                        centerIcon.className = 'bi bi-play-fill fs-3 text-white ps-1';
                        playBadge.style.display = 'flex';
                        playBadge.style.background = 'rgba(0,0,0,0.65)';
                    }
                    document.getElementById('mockup_header_text').innerText = 'Video Ad Preview';
                } else {
                    if (vidBox) vidBox.classList.remove('border-primary', 'shadow-sm');
                    if (imgBox) imgBox.classList.add('border-primary', 'shadow-sm');

                    if (headerVideo) {
                        headerVideo.pause();
                        headerVideo.style.display = 'none';
                    }

                    const currentImgSrc = (headerImg && headerImg.getAttribute('src')) || imageUrl;
                    if (currentImgSrc && currentImgSrc.trim() !== '') {
                        if (headerImg) {
                            if (imageUrl) headerImg.src = imageUrl;
                            headerImg.style.display = 'block';
                        }
                        playBadge.style.display = 'none';
                    } else {
                        if (headerImg) headerImg.style.display = 'none';
                        centerIcon.className = 'bi bi-mortarboard-fill fs-4 text-white';
                        playBadge.style.display = 'flex';
                        playBadge.style.background = 'rgba(0,0,0,0.45)';
                    }
                    document.getElementById('mockup_header_text').innerText = 'JEE & NEET Prep Exclusive';
                }
            }

            // Clear Media Handlers
            function clearImageMedia() {
                document.getElementById('custom_ad_image_file').value = '';
                document.getElementById('custom_ad_image_url').value = '';
                const thumb = document.getElementById('current_image_thumb');
                if (thumb) {
                    thumb.src = '';
                    thumb.style.display = 'none';
                }
                const placeholder = document.getElementById('no_image_placeholder');
                if (placeholder) placeholder.style.display = 'block';
                const mockupImg = document.getElementById('mockup_header_img');
                if (mockupImg) {
                    mockupImg.src = '';
                    mockupImg.style.display = 'none';
                }
                updateSimulator();
            }

            function clearVideoMedia() {
                document.getElementById('custom_ad_video_file').value = '';
                document.getElementById('custom_ad_video_url').value = '';
                const player = document.getElementById('current_video_player');
                if (player) {
                    player.pause();
                    player.src = '';
                    player.style.display = 'none';
                }
                const placeholder = document.getElementById('no_video_placeholder');
                if (placeholder) placeholder.style.display = 'block';
                const badge = document.getElementById('video_filename_badge');
                if (badge) badge.style.display = 'none';
                const mockupVideo = document.getElementById('mockup_header_video');
                if (mockupVideo) {
                    mockupVideo.pause();
                    mockupVideo.src = '';
                    mockupVideo.style.display = 'none';
                }
                updateSimulator();
            }

            // Client-side Instant Previews when choosing files
            const imgFileInput = document.getElementById('custom_ad_image_file');
            if (imgFileInput) {
                imgFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            const thumb = document.getElementById('current_image_thumb');
                            if (thumb) {
                                thumb.src = evt.target.result;
                                thumb.style.display = 'block';
                            }
                            const placeholder = document.getElementById('no_image_placeholder');
                            if (placeholder) placeholder.style.display = 'none';
                            const mockupImg = document.getElementById('mockup_header_img');
                            if (mockupImg) {
                                mockupImg.src = evt.target.result;
                                mockupImg.style.display = 'block';
                            }
                            updateSimulator();
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            const vidFileInput = document.getElementById('custom_ad_video_file');
            if (vidFileInput) {
                vidFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const objectUrl = URL.createObjectURL(file);
                        const player = document.getElementById('current_video_player');
                        if (player) {
                            player.src = objectUrl;
                            player.style.display = 'block';
                            player.play().catch(() => {});
                        }
                        const placeholder = document.getElementById('no_video_placeholder');
                        if (placeholder) placeholder.style.display = 'none';
                        const badge = document.getElementById('video_filename_badge');
                        if (badge) {
                            badge.innerText = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB)';
                            badge.style.display = 'block';
                        }
                        const mockupVideo = document.getElementById('mockup_header_video');
                        if (mockupVideo) {
                            mockupVideo.src = objectUrl;
                            mockupVideo.play().catch(() => {});
                        }
                        // Also auto-select Video media type for convenience
                        document.getElementById('custom_ad_media_type').value = 'video';
                        updateSimulator();
                    }
                });
            }

            let countdownTimerInterval = null;
            function testCountdown() {
                clearInterval(countdownTimerInterval);
                const delay = parseInt(document.getElementById('custom_ad_skip_seconds').value) || 0;
                const closeBtn = document.getElementById('mockup_close_btn');
                const timerBadge = document.getElementById('mockup_countdown_badge');

                if (delay <= 0) {
                    closeBtn.style.display = 'flex';
                    timerBadge.style.display = 'none';
                    return;
                }

                let remaining = delay;
                closeBtn.style.display = 'none';
                timerBadge.style.display = 'inline-block';
                timerBadge.innerText = 'Skip in ' + remaining + 's';

                countdownTimerInterval = setInterval(() => {
                    remaining--;
                    if (remaining > 0) {
                        timerBadge.innerText = 'Skip in ' + remaining + 's';
                    } else {
                        clearInterval(countdownTimerInterval);
                        timerBadge.style.display = 'none';
                        closeBtn.style.display = 'flex';
                    }
                }, 1000);
            }

            // Real-time event listeners on all inputs
            [
                'custom_ad_title', 'custom_ad_sub_title', 'custom_ad_badge_text',
                'custom_ad_button_text', 'custom_ad_layout', 'custom_ad_media_type',
                'custom_ad_pills', 'custom_ad_image_url', 'custom_ad_video_url'
            ].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', updateSimulator);
                    el.addEventListener('change', updateSimulator);
                }
            });

            document.addEventListener('DOMContentLoaded', updateSimulator);
            updateSimulator();
            </script>

            <!-- Legal & URLs -->
            <div class="bb-card mb-4">
                <div class="bb-card-header">
                    <h5><i class="bi bi-shield-check text-success me-2"></i>Legal & Policies</h5>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="privacy_policy_url">Privacy Policy URL</label>
                        <input type="url" class="form-control" id="privacy_policy_url" name="privacy_policy_url" value="<?= htmlspecialchars($current['privacy_policy_url'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="terms_conditions_url">Terms & Conditions URL</label>
                        <input type="url" class="form-control" id="terms_conditions_url" name="terms_conditions_url" value="<?= htmlspecialchars($current['terms_conditions_url'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="text-end mb-4">
                <button type="submit" class="btn btn-bb-primary btn-lg px-4">
                    <i class="bi bi-save me-2"></i>Save All Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
