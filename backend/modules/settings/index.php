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
                $customAdImage = $uploadResult['filename'];
            } else {
                flash('warning', 'Ad image upload notice: ' . ($uploadResult['message'] ?? 'Failed to upload'));
            }
        } elseif (isset($_POST['custom_ad_image_url'])) {
            $customAdImage = trim($_POST['custom_ad_image_url']);
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
            'custom_ad_enabled' => isset($_POST['custom_ad_enabled']) ? '1' : '0',
            'custom_ad_title' => trim($_POST['custom_ad_title'] ?? 'Best JEE & NEET Preparation Materials'),
            'custom_ad_sub_title' => trim($_POST['custom_ad_sub_title'] ?? 'Top question bank, chapter-wise test series & expert video solutions!'),
            'custom_ad_image' => $customAdImage,
            'custom_ad_target_url' => trim($_POST['custom_ad_target_url'] ?? ''),
            'custom_ad_button_text' => trim($_POST['custom_ad_button_text'] ?? 'Explore Questions Now 🚀'),
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
                        <label class="form-label" for="default_jee_neet_text">Default Container Text (Page 4+ in Textbooks)</label>
                        <input type="text" class="form-control" id="default_jee_neet_text" name="default_jee_neet_text" value="<?= htmlspecialchars($current['default_jee_neet_text'] ?? '👉 Click here for the best JEE & NEET Questions ↗') ?>" placeholder="👉 Click here for the best JEE & NEET Questions ↗">
                        <small class="text-muted">Global default text shown on the clickable bottom container for textbook PDFs from Page 4 onwards. Can also be overridden per individual chapter in the Chapter Editor.</small>
                    </div>
                </div>
            </div>

            <!-- Dynamic In-House Custom Ads (Self-Hosted Ads) -->
            <div class="bb-card mb-4 border-success shadow-sm">
                <div class="bb-card-header d-flex justify-content-between align-items-center bg-light">
                    <h5 class="mb-0 text-success"><i class="bi bi-badge-ad-fill me-2"></i>In-House Custom Ad Management (Self-Hosted)</h5>
                    <span class="badge bg-success text-white"><i class="bi bi-shield-check me-1"></i>100% Dynamic • No AdMob</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="form-check form-switch p-3 bg-light rounded-3 border">
                            <input class="form-check-input" type="checkbox" role="switch" id="custom_ad_enabled" name="custom_ad_enabled" value="1" <?= ($current['custom_ad_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold ms-2 text-dark" for="custom_ad_enabled">
                                Enable Custom In-House Ad Popup (When students click JEE & NEET button in PDF)
                            </label>
                            <div class="text-muted small ms-2">Displays your own custom full-screen promotional ad popup whenever a student clicks "Click here for the best JEE & NEET Questions" in the textbook reader.</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="custom_ad_title">Ad Title / Headline</label>
                        <input type="text" class="form-control" id="custom_ad_title" name="custom_ad_title" value="<?= htmlspecialchars($current['custom_ad_title'] ?? 'Best JEE & NEET Preparation Materials') ?>" placeholder="e.g. Special JEE & NEET Rank Booster Course!">
                        <small class="text-muted">Primary headline shown prominently at the top of the ad popup.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="custom_ad_button_text">Call to Action (CTA) Button Text</label>
                        <input type="text" class="form-control" id="custom_ad_button_text" name="custom_ad_button_text" value="<?= htmlspecialchars($current['custom_ad_button_text'] ?? 'Explore Questions Now 🚀') ?>" placeholder="e.g. Explore Now 🚀, Join Telegram, Get Free Notes">
                        <small class="text-muted">The text displayed on the main action button.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold" for="custom_ad_sub_title">Ad Description / Subtitle</label>
                        <textarea class="form-control" id="custom_ad_sub_title" name="custom_ad_sub_title" rows="2" placeholder="e.g. Access 5000+ solved previous years questions, revision notes, chapter mock tests, and top faculty guidance."><?= htmlspecialchars($current['custom_ad_sub_title'] ?? 'Top question bank, chapter-wise test series & expert video solutions!') ?></textarea>
                        <small class="text-muted">Short promotional description explaining the benefits or offer.</small>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label fw-bold" for="custom_ad_image_file">Upload Ad Banner Image</label>
                        <input type="file" class="form-control" id="custom_ad_image_file" name="custom_ad_image_file" accept="image/png,image/jpeg,image/webp,image/gif">
                        <small class="text-muted">Upload high-quality banner (recommended 16:9 or 4:3, e.g. 800x450 or 600x600). Allowed: JPG, PNG, WEBP.</small>

                        <div class="mt-2">
                            <label class="form-label small text-muted mb-1" for="custom_ad_image_url">Or Direct Image URL / Filename</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="custom_ad_image_url" name="custom_ad_image_url" value="<?= htmlspecialchars($current['custom_ad_image'] ?? '') ?>" placeholder="https://example.com/banner.png or uploads/banners/image.png">
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-bold">Current Ad Banner Preview</label>
                        <div class="p-2 border rounded bg-white text-center" style="min-height: 120px; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($current['custom_ad_image'])): ?>
                                <img src="<?= htmlspecialchars(resolveMediaUrl($current['custom_ad_image'], 'banners')) ?>" alt="Ad Preview" class="img-fluid rounded shadow-sm" style="max-height: 140px; object-fit: contain;">
                            <?php else: ?>
                                <div class="text-muted small py-3">
                                    <i class="bi bi-image display-6 d-block mb-1 text-secondary"></i>
                                    No ad banner uploaded yet.<br>(A default gradient illustration will be shown in-app)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold" for="custom_ad_target_url">Target Redirect URL (Website, Telegram, WhatsApp, Course, or Form)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                            <input type="url" class="form-control font-monospace" id="custom_ad_target_url" name="custom_ad_target_url" value="<?= htmlspecialchars($current['custom_ad_target_url'] ?? '') ?>" placeholder="https://t.me/your_channel or https://yourwebsite.com/course">
                        </div>
                        <small class="text-muted">Where students are redirected when tapping the CTA button. <strong>Leave blank</strong> if you want the button to automatically redirect to the chapter's configured JEE & NEET link!</small>
                    </div>
                </div>
            </div>

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
