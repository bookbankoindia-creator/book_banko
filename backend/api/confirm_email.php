<?php
/**
 * Email Confirmation Link Handler
 * Book Banko Backend & API
 * 
 * When user clicks "Confirm Email" link in the PHPMailer verification email:
 * 1. Verifies token and email in email_verifications table
 * 2. Marks email as verified
 * 3. Shows sleek confirmation page with redirect to Book Banko Register Screen
 */

define('IS_API_REQUEST', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

$email = strtolower(trim($_GET['email'] ?? ''));
$token = trim($_GET['token'] ?? '');
$isVerified = false;
$errorMessage = '';

if (!empty($email) && !empty($token)) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM email_verifications 
            WHERE LOWER(email) = LOWER(?) AND token = ? AND expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([$email, $token]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            $up = $db->prepare("UPDATE email_verifications SET is_verified = 1 WHERE id = ?");
            $up->execute([$record['id']]);
            $isVerified = true;
        } else {
            // Check if already verified
            $check = $db->prepare("
                SELECT * FROM email_verifications 
                WHERE LOWER(email) = LOWER(?) AND token = ? AND is_verified = 1 
                LIMIT 1
            ");
            $check->execute([$email, $token]);
            if ($check->fetch()) {
                $isVerified = true;
            } else {
                $errorMessage = "This confirmation link is invalid or has expired (valid for 15 minutes). Please request a new verification email in the app.";
            }
        }
    } catch (Exception $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
} else {
    $errorMessage = "Invalid verification request parameters.";
}

$deepLink = 'bookbanko://register?email=' . urlencode($email) . '&verified=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isVerified ? 'Email Verified Successfully - Book Banko' : 'Verification Issue - Book Banko'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 50%, #0F172A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 480px;
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            text-align: center;
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header {
            background: #F8FAFC;
            padding: 28px 24px;
            border-bottom: 1px solid #E2E8F0;
        }
        .logo-title {
            font-size: 22px;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .content {
            padding: 36px 28px;
        }
        .icon-badge {
            width: 76px;
            height: 76px;
            margin: 0 auto 20px auto;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
        }
        .badge-success {
            background: #DCFCE7;
            color: #16A34A;
            border: 3px solid #86EFAC;
        }
        .badge-error {
            background: #FEE2E2;
            color: #DC2626;
            border: 3px solid #FCA5A5;
        }
        h1 {
            font-size: 22px;
            font-weight: 800;
            color: #0F172A;
            margin-bottom: 10px;
        }
        p {
            font-size: 14.5px;
            color: #64748B;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .email-pill {
            display: inline-block;
            background: #EFF6FF;
            color: #1D4ED8;
            font-weight: 700;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 24px;
            border: 1px solid #BFDBFE;
        }
        .btn-primary {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: #FFFFFF !important;
            text-decoration: none;
            padding: 16px 24px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15.5px;
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
            transition: all 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -5px rgba(37, 99, 235, 0.5);
        }
        .footer-note {
            margin-top: 24px;
            font-size: 12.5px;
            color: #94A3B8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-title">
                <span>📚</span> Book Banko
            </div>
        </div>

        <div class="content">
            <?php if ($isVerified): ?>
                <div class="icon-badge badge-success">✓</div>
                <h1>Email Confirmed!</h1>
                <p>Your email address has been verified successfully. You can now complete your registration in Book Banko.</p>
                <div class="email-pill"><?php echo htmlspecialchars($email); ?></div>

                <a href="<?php echo htmlspecialchars($deepLink); ?>" class="btn-primary" id="openAppBtn">
                    🚀 Open Book Banko & Complete Register
                </a>

                <p class="footer-note">
                    You can switch back to the Book Banko app now. Your screen will automatically advance to registration.
                </p>

                <script>
                    // Auto-attempt opening the app via deep link
                    setTimeout(function() {
                        window.location.href = <?php echo json_encode($deepLink); ?>;
                    }, 500);
                </script>
            <?php else: ?>
                <div class="icon-badge badge-error">✕</div>
                <h1>Verification Link Issue</h1>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
                <a href="https://bookbanko-neon.vercel.app" class="btn-primary" style="background:#475569;">
                    Return to Book Banko
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
