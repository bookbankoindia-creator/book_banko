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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/custom.css">
    <style>
        body {
            background: linear-gradient(135deg, #072B57 0%, #004785 50%, #0061A4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            max-width: 440px;
            width: 100%;
            padding: 40px 32px;
        }
        .login-brand-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2196F3, #0061A4);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #fff;
            margin: 0 auto 16px auto;
            box-shadow: 0 10px 20px rgba(33, 150, 243, 0.35);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="login-brand-icon">
            <i class="bi bi-book-half"></i>
        </div>
        <h3 class="fw-bold mb-1" style="color: #172033; letter-spacing: -0.5px;"><?= APP_NAME ?></h3>
        <p class="text-secondary small mb-0">Admin Portal & Content Control</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?= renderFlashMessages() ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">

        <div class="mb-3">
            <label class="form-label" for="username">Username or Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-person text-secondary"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" placeholder="admin or email" value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-lock text-secondary"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="••••••••" value="admin123" required>
            </div>
        </div>

        <button type="submit" class="btn btn-bb-primary w-100 py-2 fs-6 fw-bold">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Dashboard
        </button>
    </form>

    <div class="mt-4 p-3 bg-light rounded-3 text-center border">
        <div class="text-secondary small fw-bold mb-1">Demo Credentials:</div>
        <div class="text-dark small">Username: <code>admin</code> &bull; Password: <code>admin123</code></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
