<?php
require_once __DIR__ . '/auth.php';
$loggedIn = isLoggedIn();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' — ' . APP_NAME : APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <?php if (isset($extraCss)): foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $css ?>">
    <?php endforeach; endif; ?>
</head>
<body>
    <nav class="nav">
        <div class="container nav-container">
            <a href="<?= BASE_URL ?>/" class="nav-logo">The<span>Social</span>Gauntlet</a>
            <div class="nav-links">
                <?php if ($loggedIn): ?>
                    <a href="<?= BASE_URL ?>/app/dashboard.php">Dashboard</a>
                    <a href="<?= BASE_URL ?>/app/select-topic.php" class="btn btn-neon btn-sm">Take Test</a>
                    <div class="user-menu">
                        <span class="user-name"><?= htmlspecialchars($user['username']) ?></span>
                        <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-link">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/login.php">Login</a>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-sm">Join The Gauntlet</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main>
