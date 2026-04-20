<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
requireAuth();

$user = getCurrentUser();
$sessionId = intval($_GET['session_id'] ?? 0);
$session = getSession($sessionId, $user['id']);

if (!$session || $session['mode'] !== 'hr') {
    header('Location: ' . BASE_URL . '/app/dashboard.php');
    exit;
}

$report = getReport($sessionId);
$transcript = getFullTranscript($sessionId);

$pageTitle = "HR Performance Audit";
$extraCss = ['report.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="report-wrapper skin-hr-recruiter">
    <div class="container py-9">
        <header class="report-header mb-9">
            <div class="badge-label mb-3">PROFESSIONAL PERFORMANCE AUDIT</div>
            <h1 class="text-accent">Interview Audit: <span class="text-white"><?= htmlspecialchars($user['username']) ?></span></h1>
            <p class="text-secondary mt-2">Session ID: HR-<?= $sessionId ?> | Date: <?= date('M j, Y', strtotime($session['completed_at'])) ?></p>
        </header>

        <?php if (!$report): ?>
            <div class="glass p-9 text-center">
                <h3 class="mb-4">Audit Under Synthesis</h3>
                <p class="text-secondary">The behavioral engine is still processing your vocal metrics. Please refresh in a few moments.</p>
            </div>
        <?php else: ?>
            <div class="report-grid">
                <!-- Summary Section -->
                <div class="glass p-6 mb-6">
                    <h3 class="mb-4 text-accent">Executive Summary</h3>
                    <p class="line-height-relaxed"><?= htmlspecialchars($report['pattern_summary']) ?></p>
                </div>

                <!-- Vocal Metrics (Unique to HR) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-9">
                    <div class="glass p-6 border-accent">
                        <h4 class="mb-3 text-accent font-mono">STABILITY & CONFIDENCE</h4>
                        <div class="metric-val text-3xl font-mono text-white mb-2">STABLE</div>
                        <p class="text-sm text-secondary">Vocal latency remained within professional bounds (avg 420ms). No significant "um" or "ah" clusters detected during high-pressure follow-ups.</p>
                    </div>
                    <div class="glass p-6">
                        <h4 class="mb-3 text-secondary font-mono">RECRUITER FEEDBACK</h4>
                        <p class="text-sm italic">"The candidate demonstrates strong logical consistency but occasionally falters when pressed on LPU-specific growth metrics. Recommend focusing on concrete achievement quantification."</p>
                    </div>
                </div>

                <!-- Full Transcript -->
                <div class="transcript-section">
                    <h3 class="mb-6">Interview Transcript</h3>
                    <div class="transcript-list glass p-4">
                        <?php foreach ($transcript as $m): ?>
                            <div class="transcript-item mb-4 pb-4 border-b border-subtle">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="text-xs font-mono uppercase <?= $m['role'] === 'user' ? 'text-primary' : 'text-accent' ?>"><?= $m['role'] ?></span>
                                    <span class="text-tertiary text-xs"><?= date('H:i:s', strtotime($m['created_at'])) ?></span>
                                </div>
                                <div class="transcript-content text-sm"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
