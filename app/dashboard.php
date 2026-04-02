<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
requireAuth();

$user = getCurrentUser();
$sessions = getUserSessions($user['id']);
$completed = count(array_filter($sessions, fn($s) => $s['status'] === 'completed'));

$pageTitle = "Candidate Dashboard";
$extraCss = ['dashboard.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="audit-pane">
        <!-- Minimal Dashboard Header (Clean, Zero Redundancy) -->
        <header class="dash-audit-header mb-6">
            <div class="badge-label mb-2">AUDIT SUMMARY</div>
            <h1 class="welcome-header">Welcome, <span class="text-accent"><?= htmlspecialchars($user['username']) ?></span></h1>
            <p class="text-secondary mt-1">Status: <span class="text-white font-mono uppercase tracking-tighter" style="font-size: 0.8rem;">Active Candidate ID-<?= $user['id'] ?></span></p>
        </header>

        <!-- Technical Stats Bar (Horizontal & Minimal) -->
        <div class="data-audit-bar mb-7">
            <div class="audit-cell">
                <span class="cell-label">Trials Engaged</span>
                <span class="cell-value"><?= count($sessions) ?></span>
            </div>
            <div class="audit-cell">
                <span class="cell-label">Resolution Rate</span>
                <span class="cell-value"><?= count($sessions) > 0 ? round(($completed / count($sessions)) * 100) : 0 ?>%</span>
            </div>
            <div class="audit-cell">
                <span class="cell-label">Psych Standing</span>
                <span class="cell-value text-accent" style="font-size: 1.2rem;">STABLE</span>
            </div>
        </div>

        <section class="sessions-section">
            <div class="section-header mb-6">
                <h2 style="font-size: 1.5rem; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">Session History</h2>
            </div>

            <?php if (empty($sessions)): ?>
                <div class="empty-state text-center mt-9">
                    <p class="text-secondary">No trials recorded. Your history is a clean slate.</p>
                    <a href="<?= BASE_URL ?>/app/select-topic.php" class="text-accent mt-4 d-inline-block">Start First Session →</a>
                </div>
            <?php else: ?>
                <div class="sessions-list">
                    <?php foreach ($sessions as $s): ?>
                        <div class="session-item">
                            <div class="session-main">
                                <div class="session-topic"><?= htmlspecialchars($s['custom_topic'] ?: $s['topic']) ?></div>
                                <div class="session-meta text-tertiary"><?= date('M j, Y — g:i A', strtotime($s['started_at'])) ?></div>
                            </div>
                            <div class="session-actions">
                                <span class="badge badge-<?= $s['status'] ?> mr-4"><?= strtoupper($s['status']) ?></span>
                                <?php if ($s['status'] === 'completed'): ?>
                                    <a href="<?= BASE_URL ?>/app/report.php?session_id=<?= $s['id'] ?>" class="btn-text">Audit Report ➔</a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/app/session.php?session_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Resume</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
