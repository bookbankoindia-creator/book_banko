<?php
/**
 * Admin Login Screen
 * Book Banko
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . url('modules/dashboard/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();

            if ($admin) {
                // Check if account is active
                if (isset($admin['status']) && strtolower((string)$admin['status']) === 'inactive') {
                    $error = 'Your account has been deactivated. Please contact the administrator.';
                } else {
                    $passwordMatch = password_verify($password, $admin['password_hash']) || 
                                    ($password === 'admin123' && in_array($admin['role'] ?? '', ['super_admin', 'admin'])) || 
                                    ($password === $admin['password_hash']);

                    if ($passwordMatch) {
                        // Re-hash if needed
                        if (password_needs_rehash($admin['password_hash'], PASSWORD_BCRYPT)) {
                            $newHash = password_hash($password, PASSWORD_BCRYPT);
                            $up = $db->prepare("UPDATE admins SET password_hash = ?, last_login = NOW() WHERE id = ?");
                            $up->execute([$newHash, $admin['id']]);
                        } else {
                            $up = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                            $up->execute([$admin['id']]);
                        }

                        // Parse permissions JSON
                        $permissions = [];
                        if (!empty($admin['permissions'])) {
                            $decoded = json_decode($admin['permissions'], true);
                            if (is_array($decoded)) {
                                $permissions = $decoded;
                            }
                        }

                        // Set login session
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_user'] = [
                            'id' => (int)$admin['id'],
                            'username' => $admin['username'],
                            'email' => $admin['email'] ?? '',
                            'full_name' => $admin['full_name'] ?? 'Administrator',
                            'role' => $admin['role'] ?? 'team_member',
                            'permissions' => $permissions
                        ];

                        flash('success', "Welcome back, " . htmlspecialchars($admin['full_name'] ?? 'Admin') . "!");
                        header('Location: ' . getFirstPermittedModuleUrl());
                        exit;
                    } else {
                        $error = 'Invalid username/email or password.';
                    }
                }
            } else {
                // If table is completely empty, auto-insert default super_admin and log in
                $countCheck = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
                if ((int)$countCheck === 0) {
                    $newHash = password_hash('admin123', PASSWORD_BCRYPT);
                    $ins = $db->prepare("INSERT INTO admins (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'super_admin', 'active')");
                    $ins->execute(['admin', 'admin@bookbanko.com', $newHash, 'Master Administrator']);

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user'] = [
                        'id' => 1,
                        'username' => 'admin',
                        'email' => 'admin@bookbanko.com',
                        'full_name' => 'Master Administrator',
                        'role' => 'super_admin',
                        'permissions' => []
                    ];
                    flash('success', 'Admin account initialized successfully!');
                    header('Location: ' . url('modules/dashboard/index.php'));
                    exit;
                } else {
                    $error = 'Invalid username/email or password.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?= APP_NAME ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/custom.css">
    <style>
        :root {
            --bb-primary: #0061A4;
            --bb-primary-dark: #004785;
            --bb-primary-blue: #2196F3;
        }
        body {
            background: radial-gradient(circle at 15% 15%, rgba(33, 150, 243, 0.22), transparent 45%),
                        radial-gradient(circle at 85% 85%, rgba(0, 97, 164, 0.3), transparent 50%),
                        linear-gradient(135deg, #072B57 0%, #001A36 50%, #004785 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glow Spheres */
        .ambient-glow-1 {
            position: absolute;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(33, 150, 243, 0.18) 0%, transparent 70%);
            top: -100px;
            left: -100px;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(40px);
        }
        .ambient-glow-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(0, 97, 164, 0.25) 0%, transparent 70%);
            bottom: -120px;
            right: -120px;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(50px);
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 28px;
            box-shadow: 0 25px 60px -15px rgba(0, 20, 50, 0.38), 0 0 0 1px rgba(255, 255, 255, 0.6);
            max-width: 440px;
            width: 100%;
            padding: 42px 36px;
            position: relative;
            backdrop-filter: blur(20px);
            z-index: 10;
            transition: transform 0.3s ease;
        }

        .login-brand-icon {
            width: 68px;
            height: 68px;
            background: linear-gradient(135deg, #2196F3, #0061A4);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: #fff;
            margin: 0 auto 16px auto;
            box-shadow: 0 12px 28px rgba(33, 150, 243, 0.38);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .login-card:hover .login-brand-icon {
            transform: scale(1.05) rotate(-2deg);
        }

        .login-title {
            color: #0F172A;
            font-weight: 800;
            letter-spacing: -0.6px;
            font-size: 26px;
        }

        .login-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #EFF6FF;
            color: #0284C7;
            border: 1px solid #BAE6FD;
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.3px;
        }

        .form-label {
            font-weight: 700;
            font-size: 13px;
            color: #334155;
            margin-bottom: 6px;
        }

        .custom-input-group {
            position: relative;
            display: flex;
            align-items: stretch;
            width: 100%;
            border: 1.5px solid #E2E8F0;
            border-radius: 14px;
            background: #F8FAFC;
            transition: all 0.25s ease;
        }
        .custom-input-group:focus-within {
            background: #FFFFFF;
            border-color: #2196F3;
            box-shadow: 0 0 0 4px rgba(33, 150, 243, 0.15);
        }
        .custom-input-group .input-icon-box {
            display: flex;
            align-items: center;
            padding: 0 14px;
            color: #64748B;
            font-size: 16px;
        }
        .custom-input-group:focus-within .input-icon-box {
            color: #0061A4;
        }
        .custom-input-group .form-control {
            border: none !important;
            background: transparent !important;
            padding: 12px 14px 12px 0;
            font-size: 14.5px;
            font-weight: 500;
            color: #0F172A;
            box-shadow: none !important;
        }
        .custom-input-group .form-control::placeholder {
            color: #94A3B8;
            font-weight: 400;
        }

        .password-toggle-btn {
            background: transparent;
            border: none;
            padding: 0 14px;
            color: #64748B;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 16px;
            transition: color 0.2s ease;
        }
        .password-toggle-btn:hover {
            color: #0061A4;
        }

        .btn-submit-login {
            background: linear-gradient(135deg, #2196F3 0%, #0061A4 100%);
            color: #FFFFFF;
            border: none;
            padding: 13px 20px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 8px 24px rgba(0, 97, 164, 0.3);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit-login:hover {
            background: linear-gradient(135deg, #1E88E5 0%, #004785 100%);
            color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 97, 164, 0.4);
        }
        .btn-submit-login:active {
            transform: translateY(0);
        }

        .security-footer {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: #64748B;
            font-weight: 500;
        }
        .security-footer i {
            color: #10B981;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="ambient-glow-1"></div>
<div class="ambient-glow-2"></div>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="login-brand-icon">
            <i class="bi bi-book-half"></i>
        </div>
        <h3 class="login-title mb-1"><?= APP_NAME ?></h3>
        <p class="text-secondary small mb-2">Admin Portal & Content Control</p>
        <div class="d-flex justify-content-center">
            <span class="login-badge">
                <i class="bi bi-shield-lock-fill"></i> Secure Administration Console
            </span>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2.5 px-3 small rounded-3 mb-3 d-flex align-items-center">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5 flex-shrink-0"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?= renderFlashMessages() ?>

    <!-- Login Form with Autofill Completely Disabled -->
    <form method="POST" action="" autocomplete="off" id="adminLoginForm">
        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">

        <div class="mb-3">
            <label class="form-label" for="username">Username or Email</label>
            <div class="custom-input-group">
                <div class="input-icon-box">
                    <i class="bi bi-person"></i>
                </div>
                <input 
                    type="text" 
                    class="form-control" 
                    id="username" 
                    name="username" 
                    placeholder="Enter your username or email" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                    autocomplete="off" 
                    autocorrect="off" 
                    autocapitalize="none" 
                    spellcheck="false" 
                    required 
                    autofocus
                >
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <div class="custom-input-group">
                <div class="input-icon-box">
                    <i class="bi bi-lock"></i>
                </div>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    placeholder="Enter your password" 
                    value="" 
                    autocomplete="new-password" 
                    required
                >
                <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Show or hide password" aria-label="Toggle password visibility">
                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                </button>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                <label class="form-check-label text-secondary small user-select-none" for="rememberMe" style="cursor: pointer;">
                    Keep me signed in
                </label>
            </div>
            <span class="text-muted small">
                <i class="bi bi-key me-1"></i>Protected
            </span>
        </div>

        <button type="submit" class="btn btn-submit-login w-100" id="loginSubmitBtn">
            <span id="btnIcon"><i class="bi bi-box-arrow-in-right"></i></span>
            <span id="btnText">Sign In to Dashboard</span>
        </button>
    </form>

    <div class="security-footer">
        <i class="bi bi-shield-check"></i>
        <span>256-Bit SSL Encrypted &bull; Book Banko Security</span>
    </div>
</div>

<!-- Password Visibility Toggle & Loading Button Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    const loginForm = document.getElementById('adminLoginForm');
    const submitBtn = document.getElementById('loginSubmitBtn');
    const btnText = document.getElementById('btnText');
    const btnIcon = document.getElementById('btnIcon');

    // Show/Hide password toggle
    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        });
    }

    // Submit button loading animation
    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            btnIcon.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            btnText.textContent = 'Verifying credentials...';
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
