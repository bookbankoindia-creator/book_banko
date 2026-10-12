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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf)) {
        flash('error', 'Invalid security token.');
    } else {
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
            'admob_enabled' => isset($_POST['admob_enabled']) ? '1' : '0',
            'admob_app_id' => trim($_POST['admob_app_id'] ?? 'ca-app-pub-3940256099942544~3347511713'),
            'admob_interstitial_ad_unit_id' => trim($_POST['admob_interstitial_ad_unit_id'] ?? 'ca-app-pub-3940256099942544/1033173712')
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

            flash('success', 'Application settings saved successfully.');
            header('Location: ' . url('modules/settings/index.php'));
            exit;
        } catch (PDOException $e) {
            flash('error', 'Error saving settings: ' . $e->getMessage());
        }
    }
}

// Fetch all current settings
$rows = $db->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
$current = [];
foreach ($rows as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <form method="POST" action="">
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

            <!-- Google Mobile Ads (AdMob) Settings -->
            <div class="bb-card mb-4 border-primary">
                <div class="bb-card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-badge-ad-fill text-primary me-2"></i>Google Mobile Ads (AdMob) Monetization</h5>
                    <span class="badge bg-primary text-white">Full-Screen Ads</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="form-check form-switch p-3 bg-light rounded-3 border">
                            <input class="form-check-input" type="checkbox" role="switch" id="admob_enabled" name="admob_enabled" value="1" <?= ($current['admob_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold ms-2 text-dark" for="admob_enabled">
                                Enable AdMob Interstitial Ads (When clicking JEE & NEET button)
                            </label>
                            <div class="text-muted small ms-2">Displays a full-screen Interstitial Ad when a student clicks "Click here for the best JEE & NEET Questions", then opens the questions link once the ad is closed.</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="admob_app_id">AdMob App ID</label>
                        <input type="text" class="form-control font-monospace" id="admob_app_id" name="admob_app_id" value="<?= htmlspecialchars($current['admob_app_id'] ?? 'ca-app-pub-3940256099942544~3347511713') ?>" placeholder="ca-app-pub-3940256099942544~3347511713">
                        <small class="text-muted">Your Google AdMob Application ID. Default is Google Test App ID.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="admob_interstitial_ad_unit_id">Interstitial Ad Unit ID</label>
                        <input type="text" class="form-control font-monospace" id="admob_interstitial_ad_unit_id" name="admob_interstitial_ad_unit_id" value="<?= htmlspecialchars($current['admob_interstitial_ad_unit_id'] ?? 'ca-app-pub-3940256099942544/1033173712') ?>" placeholder="ca-app-pub-3940256099942544/1033173712">
                        <small class="text-muted">Your Interstitial Ad Unit ID. Default is Google Test Interstitial ID (<code>ca-app-pub-3940256099942544/1033173712</code>).</small>
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
