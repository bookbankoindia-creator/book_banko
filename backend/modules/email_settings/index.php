<?php
/**
 * Email & SMTP Credentials Management
 * Book Banko Admin Panel
 * 
 * Allows Administrators to:
 * - Configure PHPMailer SMTP Settings (Host, Port, Username, Password, Encryption, Sender)
 * - Save credentials into app_settings table
 * - Send a Live Test OTP Email with full SMTP debug diagnostics
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/mailer.php';

requireLogin();

$pageTitle = 'Email & SMTP Credentials';
$pageSubtitle = 'Configure PHPMailer SMTP credentials for delivering student OTP codes and notifications';
$db = Database::getConnection();

// Default SMTP Settings
$defaults = [
    'smtp_enabled'    => '1',
    'smtp_host'       => 'smtp.gmail.com',
    'smtp_port'       => '587',
    'smtp_encryption' => 'tls',
    'smtp_username'   => 'bookbankoindia@gmail.com',
    'smtp_password'   => '',
    'smtp_from_email' => 'bookbankoindia@gmail.com',
    'smtp_from_name'  => 'Book Banko',
    'smtp_reply_to'   => 'bookbankoindia@gmail.com',
    'smtp_debug'      => '0',
];

// Fetch current settings from database
$rows = $db->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'smtp_%'")->fetchAll(PDO::FETCH_ASSOC);
$current = [];
foreach ($rows as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}
$current = array_merge($defaults, $current);

$testResult = null;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf)) {
        flash('error', 'Invalid security token. Please refresh and try again.');
    } else {
        $action = $_POST['action'] ?? 'save_settings';

        if ($action === 'save_settings') {
            $settingsToSave = [
                'smtp_enabled'    => isset($_POST['smtp_enabled']) ? '1' : '0',
                'smtp_host'       => trim($_POST['smtp_host'] ?? 'smtp.gmail.com'),
                'smtp_port'       => (string)max(1, (int)($_POST['smtp_port'] ?? 587)),
                'smtp_encryption' => strtolower(trim($_POST['smtp_encryption'] ?? 'tls')),
                'smtp_username'   => trim($_POST['smtp_username'] ?? ''),
                'smtp_password'   => trim($_POST['smtp_password'] ?? ''),
                'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
                'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? 'Book Banko'),
                'smtp_reply_to'   => trim($_POST['smtp_reply_to'] ?? ''),
                'smtp_debug'      => isset($_POST['smtp_debug']) ? '1' : '0',
            ];

            try {
                $isPgsql = (Database::getDriver() === 'pgsql');
                $sql = $isPgsql
                    ? "INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :val) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
                    : "INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val2";

                $stmt = $db->prepare($sql);
                foreach ($settingsToSave as $k => $v) {
                    $params = [':key' => $k, ':val' => $v];
                    if (!$isPgsql) {
                        $params[':val2'] = $v;
                    }
                    $stmt->execute($params);
                }

                flash('success', 'SMTP credentials and email configuration saved successfully!');
                header('Location: ' . url('modules/email_settings/index.php'));
                exit;
            } catch (PDOException $e) {
                flash('error', 'Database error saving SMTP credentials: ' . $e->getMessage());
            }
        } elseif ($action === 'test_smtp') {
            $testRecipient = strtolower(trim($_POST['test_recipient_email'] ?? ''));
            if (empty($testRecipient) || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Please enter a valid recipient email address to test SMTP delivery.');
            } else {
                $testConfig = [
                    'host'       => trim($_POST['smtp_host'] ?? $current['smtp_host']),
                    'port'       => (int)($_POST['smtp_port'] ?? $current['smtp_port']),
                    'encryption' => trim($_POST['smtp_encryption'] ?? $current['smtp_encryption']),
                    'username'   => trim($_POST['smtp_username'] ?? $current['smtp_username']),
                    'password'   => trim($_POST['smtp_password'] ?? $current['smtp_password']),
                    'from_email' => trim($_POST['smtp_from_email'] ?? $current['smtp_from_email']),
                    'from_name'  => trim($_POST['smtp_from_name'] ?? $current['smtp_from_name']),
                    'reply_to'   => trim($_POST['smtp_reply_to'] ?? $current['smtp_reply_to']),
                ];

                $debugLog = '';
                $testResult = testSmtpDiagnostics($testConfig, $testRecipient, $debugLog);
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <!-- Breadcrumb & Header -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('modules/dashboard/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('modules/settings/index.php') ?>" class="text-decoration-none">Settings</a></li>
                <li class="breadcrumb-item active" aria-current="page">Email & SMTP Credentials</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-envelope-at-fill text-warning me-2"></i>Email & SMTP Configuration</h3>
                <p class="text-muted mb-0">Configure PHPMailer SMTP credentials to deliver 6-digit OTP verification codes directly to official Gmail and student inboxes.</p>
            </div>
            <span class="badge <?= ($current['smtp_enabled'] === '1' && !empty($current['smtp_password'])) ? 'bg-success' : 'bg-warning' ?> px-3 py-2 fs-6">
                <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>
                <?= ($current['smtp_enabled'] === '1' && !empty($current['smtp_password'])) ? 'SMTP Ready' : 'Setup Required' ?>
            </span>
        </div>

        <?php if ($testResult !== null): ?>
            <div class="card mb-4 border-0 shadow-sm <?= $testResult['success'] ? 'border-start border-success border-4' : 'border-start border-danger border-4' ?>">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start">
                        <div class="fs-2 me-3 <?= $testResult['success'] ? 'text-success' : 'text-danger' ?>">
                            <i class="bi <?= $testResult['success'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1 <?= $testResult['success'] ? 'text-success' : 'text-danger' ?>">
                                <?= $testResult['success'] ? 'SMTP Test Email Sent Successfully!' : 'SMTP Connection Failed' ?>
                            </h5>
                            <p class="mb-2"><?= htmlspecialchars($testResult['message']) ?></p>
                            
                            <?php if (!empty($testResult['log'])): ?>
                                <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#smtpLogCollapse">
                                    <i class="bi bi-terminal me-1"></i> View SMTP Connection Log
                                </button>
                                <div class="collapse mt-2" id="smtpLogCollapse">
                                    <pre class="bg-dark text-light p-3 rounded font-monospace small mb-0" style="max-height: 250px; overflow-y: auto;"><?= htmlspecialchars($testResult['log']) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Settings Form -->
        <form method="POST" action="" id="smtpSettingsForm">
            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
            <input type="hidden" name="action" value="save_settings">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-shield-lock-fill me-2"></i>PHPMailer SMTP Server Credentials
                    </h5>
                    <!-- Quick Presets -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-magic me-1"></i> Load Provider Preset
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><h6 class="dropdown-header">Quick Configurations</h6></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="applyPreset('gmail')"><i class="bi bi-google me-2 text-danger"></i>Gmail SMTP (smtp.gmail.com)</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="applyPreset('hostinger')"><i class="bi bi-hdd-network me-2 text-primary"></i>Hostinger (smtp.hostinger.com)</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="applyPreset('brevo')"><i class="bi bi-send me-2 text-info"></i>Brevo / Sendinblue</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="applyPreset('outlook')"><i class="bi bi-microsoft me-2 text-primary"></i>Outlook / Office 365</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- Enable SMTP Switch -->
                    <div class="form-check form-switch mb-4 p-3 bg-light rounded border">
                        <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="smtp_enabled" name="smtp_enabled" value="1" <?= $current['smtp_enabled'] === '1' ? 'checked' : '' ?> style="width: 2.5em; height: 1.3em;">
                        <label class="form-check-label fw-bold" for="smtp_enabled">
                            Enable PHPMailer SMTP Email Sending
                            <div class="text-muted fw-normal small mt-1">When enabled, student signup OTPs and password reset emails will be sent directly via your SMTP server.</div>
                        </label>
                    </div>

                    <div class="row g-3">
                        <!-- SMTP Host -->
                        <div class="col-md-7">
                            <label class="form-label fw-semibold" for="smtp_host">SMTP Server Host <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-server"></i></span>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($current['smtp_host']) ?>" placeholder="e.g. smtp.gmail.com" required>
                            </div>
                            <div class="form-text">For Gmail use <code>smtp.gmail.com</code></div>
                        </div>

                        <!-- Port -->
                        <div class="col-md-2 col-6">
                            <label class="form-label fw-semibold" for="smtp_port">Port <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($current['smtp_port']) ?>" placeholder="587" required>
                            <div class="form-text">587 or 465</div>
                        </div>

                        <!-- Encryption -->
                        <div class="col-md-3 col-6">
                            <label class="form-label fw-semibold" for="smtp_encryption">Encryption <span class="text-danger">*</span></label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption" required>
                                <option value="tls" <?= $current['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                                <option value="ssl" <?= $current['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                                <option value="none" <?= $current['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None (Plain Port 25)</option>
                            </select>
                            <div class="form-text">TLS recommended</div>
                        </div>

                        <!-- Username / Email -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="smtp_username">SMTP Username / Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person-fill"></i></span>
                                <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?= htmlspecialchars($current['smtp_username']) ?>" placeholder="e.g. bookbankoindia@gmail.com" required>
                            </div>
                            <div class="form-text">Your full Gmail address or SMTP login account.</div>
                        </div>

                        <!-- Password / App Password -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold d-flex justify-content-between" for="smtp_password">
                                <span>SMTP Password / App Password <span class="text-danger">*</span></span>
                                <a href="#gmailHelpAccordion" class="text-decoration-none small text-primary" data-bs-toggle="collapse">How to get App Password?</a>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key-fill"></i></span>
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?= htmlspecialchars($current['smtp_password']) ?>" placeholder="16-character App Password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" onclick="togglePasswordVisibility()">
                                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">For Gmail, use a <strong>16-character App Password</strong> (not your normal Gmail login password).</div>
                        </div>

                        <hr class="my-3 text-muted">

                        <!-- From Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="smtp_from_name">Sender Name ("From" Name)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                                <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" value="<?= htmlspecialchars($current['smtp_from_name']) ?>" placeholder="Book Banko">
                            </div>
                            <div class="form-text">Displayed in student's inbox as the sender name.</div>
                        </div>

                        <!-- From Email -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="smtp_from_email">Sender Email ("From" Email) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" value="<?= htmlspecialchars($current['smtp_from_email']) ?>" placeholder="bookbankoindia@gmail.com" required>
                            </div>
                            <div class="form-text">Must match your authenticated SMTP account email.</div>
                        </div>

                        <!-- Reply-To Email -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="smtp_reply_to">Reply-To Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-reply-fill"></i></span>
                                <input type="email" class="form-control" id="smtp_reply_to" name="smtp_reply_to" value="<?= htmlspecialchars($current['smtp_reply_to']) ?>" placeholder="bookbankoindia@gmail.com">
                            </div>
                            <div class="form-text">Where student replies will be delivered.</div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save SMTP Configuration
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Test Email Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold mb-0 text-success">
                    <i class="bi bi-send-check-fill me-2"></i>Send Test OTP Email
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Verify that your SMTP credentials connect successfully to Gmail/host and can deliver OTP verification emails. Enter your personal email address below to send a live test message.
                </p>

                <form method="POST" action="" id="smtpTestForm">
                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                    <input type="hidden" name="action" value="test_smtp">

                    <!-- Pass active form values so test works even before saving -->
                    <input type="hidden" name="smtp_host" id="test_smtp_host">
                    <input type="hidden" name="smtp_port" id="test_smtp_port">
                    <input type="hidden" name="smtp_encryption" id="test_smtp_encryption">
                    <input type="hidden" name="smtp_username" id="test_smtp_username">
                    <input type="hidden" name="smtp_password" id="test_smtp_password">
                    <input type="hidden" name="smtp_from_email" id="test_smtp_from_email">
                    <input type="hidden" name="smtp_from_name" id="test_smtp_from_name">
                    <input type="hidden" name="smtp_reply_to" id="test_smtp_reply_to">

                    <div class="row g-2 align-items-center">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope-at"></i></span>
                                <input type="email" class="form-control" name="test_recipient_email" id="test_recipient_email" placeholder="Enter recipient email (e.g. your_email@gmail.com)" value="<?= htmlspecialchars($current['smtp_username']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success w-100 fw-semibold" onclick="prepareTestData()">
                                <i class="bi bi-send-fill me-1"></i> Send Test OTP Email
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gmail App Password Guide Accordion -->
        <div class="accordion mb-4" id="gmailHelpAccordion">
            <div class="accordion-item border-0 shadow-sm rounded overflow-hidden">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-bold bg-white text-dark py-3" type="button" data-bs-toggle="collapse" data-bs-target="#gmailGuide">
                        <i class="bi bi-question-circle-fill text-primary me-2 fs-5"></i>
                        How to Generate a Google App Password for Official Gmail (Quick 2-Minute Guide)
                    </button>
                </h2>
                <div id="gmailGuide" class="accordion-collapse collapse" data-bs-parent="#gmailHelpAccordion">
                    <div class="accordion-body p-4 bg-light">
                        <div class="alert alert-info border-0 mb-3">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Google does not allow third-party scripts to use your standard account password directly. You must generate an <strong>App Password</strong> with 2-Step Verification enabled.
                        </div>

                        <ol class="mb-0 ps-3">
                            <li class="mb-2">Go to your Google Account settings: <a href="https://myaccount.google.com/security" target="_blank" class="fw-semibold text-primary">myaccount.google.com/security</a></li>
                            <li class="mb-2">Ensure <strong>2-Step Verification</strong> is turned <strong>ON</strong>.</li>
                            <li class="mb-2">In the search bar at the top of Google Account, search for <strong>"App passwords"</strong> (or go to <a href="https://myaccount.google.com/apppasswords" target="_blank" class="fw-semibold text-primary">myaccount.google.com/apppasswords</a>).</li>
                            <li class="mb-2">Enter an app name: <code>Book Banko</code>, then click <strong>Create</strong>.</li>
                            <li class="mb-2">Google will generate a <strong>16-character code</strong> (e.g., <code>abcd efgh ijkl mnop</code>).</li>
                            <li class="mb-0">Copy that 16-character code and paste it into the <strong>SMTP Password</strong> field above, and click <strong>Save SMTP Configuration</strong>.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('smtp_password');
    const icon = document.getElementById('togglePasswordIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function applyPreset(provider) {
    if (provider === 'gmail') {
        document.getElementById('smtp_host').value = 'smtp.gmail.com';
        document.getElementById('smtp_port').value = '587';
        document.getElementById('smtp_encryption').value = 'tls';
    } else if (provider === 'hostinger') {
        document.getElementById('smtp_host').value = 'smtp.hostinger.com';
        document.getElementById('smtp_port').value = '465';
        document.getElementById('smtp_encryption').value = 'ssl';
    } else if (provider === 'brevo') {
        document.getElementById('smtp_host').value = 'smtp-relay.brevo.com';
        document.getElementById('smtp_port').value = '587';
        document.getElementById('smtp_encryption').value = 'tls';
    } else if (provider === 'outlook') {
        document.getElementById('smtp_host').value = 'smtp.office365.com';
        document.getElementById('smtp_port').value = '587';
        document.getElementById('smtp_encryption').value = 'tls';
    }
}

function prepareTestData() {
    document.getElementById('test_smtp_host').value = document.getElementById('smtp_host').value;
    document.getElementById('test_smtp_port').value = document.getElementById('smtp_port').value;
    document.getElementById('test_smtp_encryption').value = document.getElementById('smtp_encryption').value;
    document.getElementById('test_smtp_username').value = document.getElementById('smtp_username').value;
    document.getElementById('test_smtp_password').value = document.getElementById('smtp_password').value;
    document.getElementById('test_smtp_from_email').value = document.getElementById('smtp_from_email').value;
    document.getElementById('test_smtp_from_name').value = document.getElementById('smtp_from_name').value;
    document.getElementById('test_smtp_reply_to').value = document.getElementById('smtp_reply_to').value;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
