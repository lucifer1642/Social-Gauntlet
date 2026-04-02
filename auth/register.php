<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? ''; $email = $_POST['email'] ?? ''; $password = $_POST['password'] ?? '';
    if (register($username, $email, $password)) { header('Location: ' . BASE_URL . '/auth/login.php?registered=1'); exit; }
    else { $error = 'Email already exists or registration failed.'; }
}
$pageTitle = "Candidate Registration";
$extraCss = ['auth.css'];
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
    <div class="container">
        <div class="auth-card glass">
            <div class="auth-header">
                <h1>Join The Gauntlet</h1>
                <p class="text-secondary">Create your account to begin the stress test.</p>
            </div>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="username" class="form-input" placeholder="Your name" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="name@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Create a strong password" required>
                </div>
                <button type="submit" class="btn btn-primary w-full">Begin Assessment →</button>
            </form>
            <div class="auth-footer text-center mt-6">
                <p class="text-secondary text-sm">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Login</a></p>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
