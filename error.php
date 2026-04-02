<?php require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found — Personality Stress Tester</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <style>
        .error-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-5);
        }
        .error-container {
            text-align: center;
            max-width: 500px;
            animation: fadeIn 0.4s ease-out;
        }
        .error-code {
            font-size: 120px;
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1;
            background: linear-gradient(135deg, var(--accent-primary), #A855F7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--space-4);
        }
        .error-container h2 {
            font-size: 24px;
            margin-bottom: var(--space-3);
        }
        .error-container p {
            color: var(--text-secondary);
            margin-bottom: var(--space-6);
            font-size: 16px;
        }
        .error-actions {
            display: flex;
            gap: var(--space-4);
            justify-content: center;
        }
        @media (max-width: 480px) {
            .error-code { font-size: 80px; }
            .error-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="error-wrapper">
        <div class="error-container">
            <div class="error-code">404</div>
            <h2>Lost in the Gauntlet</h2>
            <p>The page you're looking for doesn't exist or has been moved. Maybe the Conspiracy Uncle hid it.</p>
            <div class="error-actions">
                <a href="<?= BASE_URL ?>/" class="btn btn-primary">← Back to Home</a>
                <a href="<?= BASE_URL ?>/app/dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
