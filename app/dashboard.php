<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
requireAuth();

$user = getCurrentUser();
$sessions = getUserSessions($user['id']);
$hrSessions = array_filter($sessions, fn($s) => ($s['mode'] ?? '') === 'hr');
$standardSessions = array_filter($sessions, fn($s) => ($s['mode'] ?? '') !== 'hr');
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
                <span class="cell-value" id="stat-total-trials"><?= count($sessions) ?></span>
            </div>
            <div class="audit-cell">
                <span class="cell-label">Resolution Rate</span>
                <span class="cell-value" id="stat-resolution-rate"><?= count($sessions) > 0 ? round(($completed / count($sessions)) * 100) : 0 ?>%</span>
            </div>
            <div class="audit-cell">
                <span class="cell-label">Psych Standing</span>
                <span class="cell-value text-accent" style="font-size: 1.2rem;">STABLE</span>
            </div>
        </div>

        <section class="sessions-section">
            <div class="section-header mb-6">
                <h2 style="font-size: 1.5rem; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">Session History</h2>
                <div class="header-actions">
                    <span class="text-tertiary text-xs uppercase" id="syncStatus">Syncing active...</span>
                </div>
            </div>

            <div id="sessionsContainer">
                <?php if (empty($sessions)): ?>
                    <div class="empty-state text-center mt-9">
                        <p class="text-secondary">No trials recorded. Your history is a clean slate.</p>
                        <a href="<?= BASE_URL ?>/app/select-topic.php" class="text-accent mt-4 d-inline-block">Start First Session →</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($hrSessions)): ?>
                    <div class="section-subheader mb-4 mt-2"><h3 class="text-accent uppercase text-sm tracking-widest font-mono border-b border-white/5 pb-2">Professional HR Audits</h3></div>
                    <div class="sessions-list mb-8">
                        <?php foreach ($hrSessions as $s): ?>
                            <div class="session-item" data-session-id="<?= $s['id'] ?>" data-status="<?= $s['status'] ?>">
                                <div class="session-main">
                                    <div class="session-topic"><?= htmlspecialchars(getTopicTitle($s['topic'], $s['custom_topic'])) ?></div>
                                    <div class="session-meta text-tertiary"><?= date('M j, Y — g:i A', strtotime($s['started_at'])) ?></div>
                                </div>
                                <div class="session-actions" style="display:flex; align-items:center;">
                                    <span class="badge badge-<?= $s['status'] ?> status-badge mr-4"><?= strtoupper($s['status']) ?></span>
                                    <?php if ($s['status'] === 'completed'): ?>
                                        <a href="<?= BASE_URL ?>/app/hr-report-rich.php?session_id=<?= $s['id'] ?>" class="btn-text">HR Audit ➔</a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/app/hr-session.php?session_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Resume HR</a>
                                    <?php endif; ?>
                                    <button class="btn-text ml-4 text-secondary hover-text-danger" style="background:transparent;border:none;cursor:pointer;font-size:0.8rem;transition:color 0.2s;" onclick="deleteSession(<?= $s['id'] ?>, this)" title="Delete Session">✕</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($standardSessions)): ?>
                    <div class="section-subheader mb-4"><h3 class="text-secondary uppercase text-sm tracking-widest font-mono border-b border-white/5 pb-2">Standard Behavioral Trials</h3></div>
                    <div class="sessions-list">
                        <?php foreach ($standardSessions as $s): ?>
                            <div class="session-item" data-session-id="<?= $s['id'] ?>" data-status="<?= $s['status'] ?>">
                                <div class="session-main">
                                    <div class="session-topic"><?= htmlspecialchars(getTopicTitle($s['topic'], $s['custom_topic'])) ?></div>
                                    <div class="session-meta text-tertiary"><?= date('M j, Y — g:i A', strtotime($s['started_at'])) ?></div>
                                </div>
                                <div class="session-actions" style="display:flex; align-items:center;">
                                    <span class="badge badge-<?= $s['status'] ?> status-badge mr-4"><?= strtoupper($s['status']) ?></span>
                                    <?php if ($s['status'] === 'completed'): ?>
                                        <a href="<?= BASE_URL ?>/app/report.php?session_id=<?= $s['id'] ?>" class="btn-text">Audit Report ➔</a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/app/session.php?session_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Resume</a>
                                    <?php endif; ?>
                                    <button class="btn-text ml-4 text-secondary hover-text-danger" style="background:transparent;border:none;cursor:pointer;font-size:0.8rem;transition:color 0.2s;" onclick="deleteSession(<?= $s['id'] ?>, this)" title="Delete Session">✕</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
    // Delete Session capability
    function deleteSession(id, btnElement) {
        if (!confirm('Are you sure you want to permanently delete this trial? This action cannot be undone.')) return;
        
        btnElement.disabled = true;
        btnElement.style.opacity = '0.5';
        
        fetch('<?= BASE_URL ?>/api/delete-session.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({session_id: id})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = btnElement.closest('.session-item');
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    row.remove();
                    document.getElementById('stat-total-trials').textContent = data.total;
                    document.getElementById('stat-resolution-rate').textContent = data.rate + '%';
                    if (data.total === 0) {
                        location.reload(); // Simple reload to show empty state if 0
                    }
                }, 300);
            } else {
                alert(data.error || 'Failed to delete');
                btnElement.disabled = false;
                btnElement.style.opacity = '1';
            }
        })
        .catch(err => {
            alert('Network error occurred.');
            btnElement.disabled = false;
            btnElement.style.opacity = '1';
        });
    }

    // Real-time Dashboard Sync
    function syncDashboard() {
        const syncLabel = document.getElementById('syncStatus');
        syncLabel.textContent = 'Syncing...';
        syncLabel.style.opacity = '1';

        fetch(window.location.href)
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newSessions = doc.getElementById('sessionsContainer');
                if (newSessions) {
                    document.getElementById('sessionsContainer').innerHTML = newSessions.innerHTML;
                }
                
                const newStats = doc.querySelector('.data-audit-bar');
                if (newStats) {
                    document.querySelector('.data-audit-bar').innerHTML = newStats.innerHTML;
                }

                syncLabel.textContent = 'Synced';
                setTimeout(() => {
                    syncLabel.textContent = 'Syncing active...';
                    syncLabel.style.opacity = '0.5';
                }, 1000);
            })
            .catch(err => {
                syncLabel.textContent = 'Sync failed';
                console.error(err);
            });
    }

    setInterval(syncDashboard, 5000);
    syncDashboard();
</script>

<style>
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
}
.pulse { animation: pulse 2s infinite ease-in-out; }
#syncStatus { transition: opacity 0.5s ease; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
