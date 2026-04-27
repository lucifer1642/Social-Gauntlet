<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }

$error = '';
$step = 1;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'check_email') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db = getDB();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $step = 2; // Email exists, proceed to reset
            } else {
                $error = 'No account found with that email address.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step = 2;
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
            $step = 2;
        } else {
            $db = getDB();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
            $stmt->execute([$hash, $email]);
            
            // Auto login then redirect to dashboard
            loginUser($email, $password);
            header('Location: ' . BASE_URL . '/app/dashboard.php');
            exit;
        }
    }
}

$pageTitle = "Reset Password";
$extraCss = ['auth.css'];
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Reset Password</h1>
            <?php if ($step === 1): ?>
                <p>Enter your email address to reset your password directly.</p>
            <?php else: ?>
                <p>Enter a new password for <br><strong style="color: #38bdf8;"><?= htmlspecialchars($email) ?></strong>.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 12px; border-radius: 8px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="check_email">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>" required autofocus>
                </div>
                <button type="submit" class="btn w-full">Verify Account →</button>
            </form>
        <?php else: ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Create a strong password" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
                </div>
                <button type="submit" class="btn w-full">Save & Login →</button>
            </form>
        <?php endif; ?>
        
        <div class="auth-footer text-center">
            <p class="text-secondary text-sm" style="color: #cbd5e1;">Remember your password? <a href="<?= BASE_URL ?>/auth/login.php">Login here</a></p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
