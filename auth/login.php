<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? ''; $password = $_POST['password'] ?? '';
    if (login($email, $password)) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
    else { $error = 'Invalid email or password.'; }
}
$pageTitle = "Candidate Login";
$extraCss = ['auth.css'];
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
    <div class="container">
        <div class="auth-card glass">
            <div class="auth-header">
                <h1>Candidate Login</h1>
                <p class="text-secondary">Enter your credentials to access the trials.</p>
            </div>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="name@example.com" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary w-full">Login to Dashboard</button>
            </form>
            <div class="auth-footer text-center mt-6">
                <p class="text-secondary text-sm">New candidate? <a href="<?= BASE_URL ?>/auth/register.php">Register for the Gauntlet</a></p>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
