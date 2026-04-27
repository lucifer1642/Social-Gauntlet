<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? ''; 
    $email = $_POST['email'] ?? ''; 
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $res = registerUser($username, $email, $password, $confirm);
    if (isset($res['success']) && $res['success']) { 
        header('Location: ' . BASE_URL . '/auth/login.php?registered=1'); 
        exit; 
    } else { 
        $error = $res['error'] ?? 'Registration failed.'; 
    }
}
$pageTitle = "Candidate Registration";
$extraCss = ['auth.css'];
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Join The Gauntlet</h1>
            <p>Create your account to begin the stress test.</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 12px; border-radius: 8px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
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
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
            </div>
            <button type="submit" class="btn w-full">Begin Assessment →</button>
        </form>
        
        <div class="auth-footer text-center">
            <p class="text-secondary text-sm" style="color: #cbd5e1;">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Login</a></p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
