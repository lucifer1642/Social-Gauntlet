<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
requireAuth();

$user = getCurrentUser();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidateName = trim($_POST['candidate_name'] ?? '');
    
    if (empty($candidateName)) {
        $error = 'Please enter your professional name for the audit record.';
    } else {
        // Create session with mode='hr' and fixed topic
        $topicSlug = 'hr-professional-audit';
        $customTopic = 'Standalone Professional Behavioral Interview';
        $mode = 'hr';
        
        $sessionId = createSession($user['id'], $topicSlug, $customTopic, $mode, $candidateName);
        header('Location: ' . BASE_URL . '/app/hr-session.php?session_id=' . $sessionId);
        exit;
    }
}

$pageTitle = "Identity & Calibration";
include __DIR__ . '/../includes/header.php';
?>

<style>
.wave-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    height: 40px;
    margin-bottom: 2rem;
}
.wave-bar {
    width: 3px;
    height: 10px;
    background: var(--accent);
    border-radius: 10px;
    animation: wavePulse 1.2s infinite ease-in-out;
}
.wave-bar:nth-child(2) { animation-delay: 0.1s; height: 20px; }
.wave-bar:nth-child(3) { animation-delay: 0.2s; height: 30px; }
.wave-bar:nth-child(4) { animation-delay: 0.3s; height: 15px; }
.wave-bar:nth-child(5) { animation-delay: 0.4s; height: 25px; }

@keyframes wavePulse {
    0%, 100% { transform: scaleY(1); opacity: 0.5; }
    50% { transform: scaleY(2); opacity: 1; }
}

.hr-init-card {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(20, 184, 166, 0.2);
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    border-radius: 24px;
    overflow: hidden;
}
</style>

<div class="hr-init-wrapper" style="min-height: calc(100vh - 80px); display:flex; align-items:center; justify-content:center; padding: 2rem; background: radial-gradient(circle at 50% 50%, rgba(20, 184, 166, 0.05) 0%, transparent 70%);">
    <div class="container" style="max-width: 540px;">
        <div class="hr-init-card p-10">
            <div class="text-center mb-9">
                <div class="badge-label mb-4" style="background: rgba(20, 184, 166, 0.1); color: var(--accent);">EXECUTIVE PERFORMANCE AUDIT</div>
                <h1 class="text-3xl font-bold mb-3 tracking-tight">Begin Behavioral <span class="text-accent">Audit</span></h1>
                <p class="text-secondary text-sm leading-relaxed">Establish your professional record. This is a voice-only interview with 6 to 8 adaptive HR questions.</p>
            </div>

            <div class="wave-container">
                <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error mb-6"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="hrForm">
                <div class="mb-8">
                    <label class="text-[10px] font-mono uppercase text-tertiary mb-3 block tracking-[0.2em] text-center">Candidate Identity Record</label>
                    <input type="text" name="candidate_name" id="nameInput" class="form-input text-center" style="font-size: 1.4rem; padding: 1.25rem; border-color: rgba(20, 184, 166, 0.3); border-radius: 12px; background: rgba(0,0,0,0.2);" placeholder="Enter Full Name..." required autofocus>
                </div>

                <div class="audit-specs mb-10 grid grid-cols-2 gap-4">
                    <div class="spec-item p-4 glass-subtle rounded-xl text-center border-white/5 border">
                        <span class="text-accent font-mono text-lg block mb-1">6-8</span>
                        <span class="text-[9px] font-mono text-tertiary uppercase tracking-widest">Questions</span>
                    </div>
                    <div class="spec-item p-4 glass-subtle rounded-xl text-center border-white/5 border">
                        <span class="text-accent font-mono text-lg block mb-1">LIVE</span>
                        <span class="text-[9px] font-mono text-tertiary uppercase tracking-widest">Voice Engine</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-full py-5 rounded-xl shadow-xl shadow-accent/10" id="startBtn">Start HR Voice Interview →</button>
            </form>

            <div class="mt-8 text-center">
                <a href="dashboard.php" class="text-xs font-mono text-tertiary hover-text-accent transition-all uppercase tracking-widest">✕ Return to Terminal</a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('hrForm').addEventListener('submit', function() {
    const btn = document.getElementById('startBtn');
    btn.disabled = true; btn.textContent = 'Preparing Interview...';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
