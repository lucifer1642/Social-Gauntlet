<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
requireAuth();

$user = getCurrentUser();
$sessionId = intval($_GET['session_id'] ?? 0);

if (!$sessionId) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
$session = getSession($sessionId, $user['id']);
if (!$session) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }

$report = getReport($sessionId);
if (!$report) {
    echo '<!DOCTYPE html><html><head><title>Report</title><link rel="stylesheet" href="../assets/css/global.css"></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;"><div class="text-center"><h2>Report not ready</h2><p class="text-secondary">Analysis pending.</p><a href="' . BASE_URL . '/app/dashboard.php" class="btn btn-primary mt-5">Back to Dashboard</a></div></body></html>';
    exit;
}

$topicDisplay = $session['custom_topic'] ?: $session['topic'];
$analysis = json_decode($report['analysis_json'], true);
$recommendations = json_decode($report['recommendations_json'], true);
$transcript = getFullTranscript($sessionId);
$date = date('F j, Y', strtotime($session['started_at']));

function getDrawing($type) {
    if ($type === 'strong') return '<svg viewBox="0 0 24 24" class="audit-svg-draw"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z" fill="currentColor"/></svg>';
    return '<svg viewBox="0 0 24 24" class="audit-svg-draw"><circle cx="12" cy="12" r="10" stroke="currentColor" fill="none" stroke-width="1.5" stroke-dasharray="2 4"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
}

$roundTranscripts = [];
foreach ($transcript as $msg) {
    $rn = $msg['round_number'];
    if (!isset($roundTranscripts[$rn])) $roundTranscripts[$rn] = [];
    $roundTranscripts[$rn][] = $msg;
}

$msgLengthsByRound = [];
$responseTimesByRound = [];
foreach ($transcript as $msg) {
    if ($msg['role'] === 'user') {
        $rn = $msg['round_number'];
        if (!isset($msgLengthsByRound[$rn])) $msgLengthsByRound[$rn] = [];
        $msgLengthsByRound[$rn][] = $msg['char_count'];
        if ($msg['response_time_ms']) {
            if (!isset($responseTimesByRound[$rn])) $responseTimesByRound[$rn] = [];
            $responseTimesByRound[$rn][] = $msg['response_time_ms'];
        }
    }
}
$avgLengths = [];
$avgTimes = [];
for ($i = 1; $i <= 5; $i++) {
    $avgLengths[$i] = isset($msgLengthsByRound[$i]) ? round(array_sum($msgLengthsByRound[$i]) / count($msgLengthsByRound[$i])) : 0;
    $avgTimes[$i] = isset($responseTimesByRound[$i]) ? round(array_sum($responseTimesByRound[$i]) / count($responseTimesByRound[$i]) / 1000, 1) : 0;
}

// Calculate mock Stress Resistance Score (0-100) based on performance Consistency
$scoreBase = array_sum($avgLengths) + (60 - array_sum($avgTimes)); // Mock algorithm
$stressResistanceScore = min(98, max(42, round(($scoreBase / 500) * 100)));

// Radar Chart Data (Mapping performance across 5 personalities)
// In a real app, this would be based on LLM's 'score' for each round
$radarData = [
    $avgLengths[1] > 20 ? 85 : 45, // Boss
    $avgLengths[2] > 20 ? 70 : 35, // Uncle
    $avgLengths[3] > 20 ? 92 : 55, // Investor
    $avgLengths[4] > 20 ? 65 : 40, // Coworker
    $avgLengths[5] > 20 ? 78 : 50  // Guilt-Tripper
];

$pageTitle = "Audit Report";
$extraCss = ['report.css'];
include __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <!-- 1. Cinematic Index Top -->
    <header class="audit-landing mb-8">
        <div class="index-score-box header-card">
            <span class="index-label">Stress Resistance Index</span>
            <div class="index-value text-accent"><?= $stressResistanceScore ?>%</div>
            <div class="index-meter">
                <div class="index-progress" style="width: <?= $stressResistanceScore ?>%"></div>
            </div>
        </div>

        <div class="audit-meta header-card">
            <div class="text-xs uppercase tracking-widest text-tertiary mb-1">AUDIT NO: <?= strtoupper(substr(md5($sessionId), 0, 8)) ?></div>
            <h1 class="text-2xl font-extrabold tracking-tighter">Performance Analysis</h1>
            <div class="text-xs text-secondary mt-2">Communication Strategy Review • Stage Map: Baseline</div>
        </div>
    </header>

    <!-- 2. Dual Radar + Behavior Visuals -->
    <div class="visual-audit-grid mb-9">
        <div class="radar-container glass" id="radarChartContainer">
            <h4 class="text-xs uppercase tracking-widest text-tertiary mb-6">Persona Resistance Map</h4>
            <div style="flex:1; position:relative;"><canvas id="radarChart"></canvas></div>
        </div>
        <div class="metrics-audit-stack">
            <div class="chart-box-sm" id="volume-chart-box">
                <h4 class="text-xs uppercase tracking-widest text-tertiary mb-4">Volume Strategy</h4>
                <div style="height:140px;"><canvas id="lengthChart"></canvas></div>
            </div>
            <div class="chart-box-sm mt-6" id="consistency-chart-box" style="border:none">
                <h4 class="text-xs uppercase tracking-widest text-tertiary mb-4">Reaction Consistency</h4>
                <div style="height:140px;"><canvas id="timeChart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- 3. Key Findings -->
    <div class="findings-audit-section mb-9">
        <h2 class="section-title mb-6">Primary Diagnostics</h2>
        <div class="diagnostics-grid">
            <div class="diag-card border-success">
                <div class="diag-icon"><?= getDrawing('strong') ?></div>
                <div class="diag-content">
                    <span class="diag-label">Strongest Vector</span>
                    <h3 class="font-bold text-lg mb-2"><?= htmlspecialchars(explode('.', $report['strongest_under'] ?: 'N/A')[0]) ?>.</h3>
                    <details class="diag-details">
                        <summary>Audit Breakdown</summary>
                        <p class="diag-text"><?= htmlspecialchars($report['strongest_under'] ?: 'No data available.') ?></p>
                    </details>
                </div>
            </div>
            <div class="diag-card border-danger">
                <div class="diag-icon"><?= getDrawing('weak') ?></div>
                <div class="diag-content">
                    <span class="diag-label">Deepest Vulnerability</span>
                    <h3 class="font-bold text-lg mb-2"><?= htmlspecialchars(explode('.', $report['biggest_vulnerability'] ?: 'N/A')[0]) ?>.</h3>
                    <details class="diag-details">
                        <summary>Audit Breakdown</summary>
                        <p class="diag-text"><?= htmlspecialchars($report['biggest_vulnerability'] ?: 'No data available.') ?></p>
                    </details>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Deep Insights -->
    <div class="insights-audit-flow mb-9" onclick="this.classList.toggle('expanded')">
        <div class="flow-header">
            <h2 class="section-title">Psychological Findings</h2>
            <span class="text-accent uppercase tracking-tighter text-xs">Tap to Expand Detail</span>
        </div>
        <div class="flow-preview mt-4 text-secondary">
            <?= substr(htmlspecialchars($report['pattern_summary'] ?: ''), 0, 150) ?>...
        </div>
        <div class="flow-full mt-4 text-secondary">
            <p class="mb-6"><?= nl2br(htmlspecialchars($report['pattern_summary'] ?: '')) ?></p>
            <div class="insight-split mt-9">
                <div class="insight-block">
                    <span class="marker mb-2 block">Identified Blind Spot</span>
                    <p class="text-sm"><?= htmlspecialchars($report['blind_spot'] ?: 'N/A') ?></p>
                </div>
                <div class="insight-block">
                    <span class="marker mb-2 block">Emotional Trigger</span>
                    <p class="text-sm"><?= nl2br(htmlspecialchars($report['emotional_tripwire'] ?: 'N/A')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Linguistic Feedback Audit -->
    <div class="feedback-audit-section mb-9">
        <h2 class="section-title mb-6">Linguistic Pattern Feedback</h2>
        <div class="patterns-grid">
            <?php 
            $patterns = $analysis['language_patterns'] ?? [];
            if (empty($patterns)): ?>
                <p class="text-tertiary">No specific linguistic patterns detected in this trial.</p>
            <?php else: 
                foreach ($patterns as $p): ?>
                <div class="pattern-card">
                    <div class="pattern-phrase mono text-accent">"<?= htmlspecialchars($p['phrase'] ?? 'Unknown') ?>"</div>
                    <div class="pattern-context mt-3 text-sm text-secondary"><?= htmlspecialchars($p['context'] ?? 'No context provided.') ?></div>
                </div>
            <?php endforeach; 
            endif; ?>
        </div>
    </div>

    <!-- 6. Strategic Directives -->
    <div class="directives-audit-section mb-9">
        <h2 class="section-title mb-6">Strategic Directives</h2>
        <div class="directives-stack">
            <?php foreach ($recommendations as $idx => $rec): ?>
                <div class="directive-entry fade-in">
                    <span class="directive-idx">0<?= $idx + 1 ?></span>
                    <p class="directive-text"><?= htmlspecialchars($rec) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Audit Footer Actions -->
    <div class="audit-footer">
        <button class="btn btn-ghost btn-sm" onclick="window.print()">PRINT AUDIT</button>
        <div class="flex-1"></div>
        <a href="<?= BASE_URL ?>/app/dashboard.php" class="btn btn-ghost btn-sm">RETURN</a>
        <a href="<?= BASE_URL ?>/app/select-topic.php" class="btn btn-primary btn-sm ml-3">NEW TRIAL</a>
    </div>
</div>

<script>
    const labels = ['BOSS', 'UNCLE', 'INVESTOR', 'COWORKER', 'RELATIONSHIP'];
    
    // Patterned Data Themes
    const pColors = ['#94a3b8', '#fbbf24', '#f87171', '#38bdf8', '#c084fc'];
    
    const baseOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { display: false },
            x: { grid: { display: false }, ticks: { color: '#666', font: { family: "'JetBrains Mono'", size: 9 } } }
        }
    };

    // 1. Radar Chart (Teal Theme)
    new Chart(document.getElementById('radarChart'), {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [{
                data: [<?= implode(',', $radarData) ?>],
                backgroundColor: 'rgba(20, 184, 166, 0.2)',
                borderColor: '#14b8a6',
                borderWidth: 3,
                pointBackgroundColor: '#14b8a6',
                pointBorderColor: '#fff',
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 2500, easing: 'easeOutQuart' }, // LIVE SYNC FEEL
            plugins: { legend: { display: false } },
            scales: { r: { angleLines: { color: 'rgba(255,255,255,0.05)' }, grid: { color: 'rgba(255,255,255,0.05)' }, pointLabels: { color: '#88a', font: { family: "'JetBrains Mono'", size: 10 } }, ticks: { display: false } } }
        }
    });

    // 2. Length Chart (Blue Pattern)
    const ctxL = document.getElementById('lengthChart').getContext('2d');
    const gradL = ctxL.createLinearGradient(0, 0, 400, 0);
    gradL.addColorStop(0, '#0ea5e9'); gradL.addColorStop(1, '#3b82f6');

    new Chart(ctxL, {
        type: 'line',
        data: { labels: labels, datasets: [{ data: [<?= implode(',', array_values($avgLengths)) ?>], borderColor: gradL, borderWidth: 4, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#fff' }] },
        options: {
            ...baseOpts,
            animation: { duration: 2000, easing: 'easeOutQuart' } // LIVE SYNC FEEL
        }
    });

    // 3. Time Chart (Amethyst Pattern)
    new Chart(ctxL, {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: [<?= implode(',', array_values($avgTimes)) ?>], backgroundColor: pColors, borderRadius: 6 }] },
        options: {
            ...baseOpts,
            animation: { duration: 2200, easing: 'easeOutQuart' } // LIVE SYNC FEEL
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
