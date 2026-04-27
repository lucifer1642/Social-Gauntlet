<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? ''; $password = $_POST['password'] ?? '';
    $res = loginUser($email, $password);
    if (isset($res['success']) && $res['success']) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
    else { $error = $res['error'] ?? 'Invalid email or password.'; }
}
$pageTitle = "Candidate Login";
$extraCss = ['auth.css'];
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Candidate Login</h1>
            <p>Enter your credentials to access the trials.</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 12px; border-radius: 8px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (isset($_GET['registered'])): ?><div class="alert alert-success" style="margin-bottom: 20px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); color: #86efac; padding: 12px; border-radius: 8px;">Registration successful! Please login.</div><?php endif; ?>
        
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" placeholder="name@example.com" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">
                    <span>Password</span>
                    <a href="<?= BASE_URL ?>/auth/forgot.php" tabindex="-1">Forgot?</a>
                </label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn w-full">Access Dashboard →</button>
        </form>
        
        <div class="auth-footer text-center">
            <p class="text-secondary text-sm" style="color: #cbd5e1;">New candidate? <a href="<?= BASE_URL ?>/auth/register.php">Register for the Gauntlet</a></p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
