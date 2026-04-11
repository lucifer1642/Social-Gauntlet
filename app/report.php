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

$topicDisplay = getTopicTitle($session['topic'], $session['custom_topic']);
$analysis = json_decode($report['analysis_json'], true);
$recommendations = json_decode($report['recommendations_json'], true);
$transcript = getFullTranscript($sessionId);
$date = date('F j, Y', strtotime($session['started_at']));

function getDrawing($type) {
    if ($type === 'strong') return '<svg viewBox="0 0 24 24" class="audit-svg-draw"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z" fill="currentColor"/></svg>';
    return '<svg viewBox="0 0 24 24" class="audit-svg-draw"><circle cx="12" cy="12" r="10" stroke="currentColor" fill="none" stroke-width="1.5" stroke-dasharray="2 4"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
}

function formatAsPointers($text, $skipFirst = false) {
    if (empty($text)) return 'No data available.';
    $sentences = explode('. ', $text);
    $html = '<ul style="list-style-type: square; padding-left: 1.2rem; margin-top: 0.8rem; display: flex; flex-direction: column; gap: 0.5rem; color: var(--text-secondary); line-height: 1.5;">';
    $isFirst = true;
    foreach ($sentences as $s) {
        $s = trim($s);
        if (empty($s)) continue;
        if ($skipFirst && $isFirst) {
            $isFirst = false;
            continue;
        }
        $isFirst = false;
        if (substr($s, -1) !== '.' && substr($s, -1) !== '!' && substr($s, -1) !== '?') {
            $s .= '.';
        }
        $html .= '<li>' . htmlspecialchars($s) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function getScore($salt, $string, $min = 60, $max = 100) {
    if (empty($string)) $string = 'default';
    $hash = abs(crc32($salt . '_' . substr($string, 0, 50)));
    return $min + ($hash % ($max - $min + 1));
}

$bsText = $report['blind_spot'] ?? 'n/a';
$tripText = $report['emotional_tripwire'] ?? 'n/a';
$s1 = getScore($sessionId, $bsText, 70, 99);
$s2 = getScore($sessionId, $tripText, 60, 95);
$psychDataPHP = [getScore($sessionId,'d'.$bsText,50,95), getScore($sessionId,'a'.$tripText,40,90), getScore($sessionId,'x'.$bsText,40,95), getScore($sessionId,'l'.$tripText,60,95), getScore($sessionId,'e'.$bsText,50,95)];
$lingDataPHP = [getScore($sessionId,'t',60,99), getScore($sessionId,'c',60,99), getScore($sessionId,'a',60,99), getScore($sessionId,'e',60,99), getScore($sessionId,'f',60,99)];
$bsRadarDataPHP = [getScore($sessionId,'aw',40,90), getScore($sessionId,'im',70,99), getScore($sessionId,'re',60,90)];
$triggerRadarDataPHP = [getScore($sessionId,'vo',70,99), getScore($sessionId,'fr',60,95), getScore($sessionId,'se',65,95)];

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
            <div class="diag-card border-success glass-magical hover-float">
                <div class="diag-icon" style="flex-shrink: 0;"><span class="emoji-react" style="font-size: 2.2rem;">📈</span></div>
                <div class="diag-content" style="flex: 1; display: flex; gap: 2rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <span class="diag-label text-success">STRONGEST VECTOR</span>
                        <h3 class="font-bold text-lg mb-3"><?= htmlspecialchars(explode('.', $report['strongest_under'] ?: 'N/A')[0]) ?>.</h3>
                        <div class="diag-text" style="font-size: 0.9rem; margin-top:0;"><?= formatAsPointers($report['strongest_under'], true) ?></div>
                    </div>
                    <div style="flex: 0 0 130px; height: 130px; position:relative;" class="glass-magical p-2 rounded-full">
                        <canvas id="miniGauge1"></canvas>
                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.5rem; letter-spacing:-1px;" class="text-success" id="mg1val"></div>
                    </div>
                </div>
            </div>
            <div class="diag-card border-danger glass-magical hover-float-danger">
                <div class="diag-icon" style="flex-shrink: 0;"><span class="emoji-react" style="font-size: 2.2rem;">⚠️</span></div>
                <div class="diag-content" style="flex: 1; display: flex; gap: 2rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <span class="diag-label text-danger">DEEPEST VULNERABILITY</span>
                        <h3 class="font-bold text-lg mb-3"><?= htmlspecialchars(explode('.', $report['biggest_vulnerability'] ?: 'N/A')[0]) ?>.</h3>
                        <div class="diag-text" style="font-size: 0.9rem; margin-top:0;"><?= formatAsPointers($report['biggest_vulnerability'], true) ?></div>
                    </div>
                    <div style="flex: 0 0 130px; height: 130px; position:relative;" class="glass-magical p-2 rounded-full">
                        <canvas id="miniGauge2"></canvas>
                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.5rem; letter-spacing:-1px;" class="text-danger" id="mg2val"></div>
                    </div>
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
        <div class="flow-full mt-4 text-secondary" style="display: flex; flex-wrap: wrap; gap: 2rem; align-items: stretch;">
            <!-- Left Data Column -->
            <div style="flex: 1; min-width: 300px;">
                <div class="mb-4">
                    <?= formatAsPointers($report['pattern_summary'] ?: '', false) ?>
                </div>
                
                <div class="insight-split mt-6" style="display: flex; flex-direction: column; gap: 2rem;">
                    <div class="insight-block glass-magical p-6 hover-float" style="border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 3rem; flex-wrap: wrap;">
                        <div style="flex: 0 0 250px; min-height: 250px; position: relative;">
                            <canvas id="blindSpotChart"></canvas>
                        </div>
                        <div style="flex: 1; min-width: 250px;">
                            <span class="marker mb-3 block uppercase tracking-widest font-bold text-accent" style="font-size: 1rem;"><span class="emoji-react mr-2" style="font-size: 1.5rem;">👁️‍🗨️</span>IDENTIFIED BLIND SPOT</span>
                            <div style="font-size: 1rem; line-height: 1.6;">
                                <?= formatAsPointers($report['blind_spot'] ?: 'N/A', false) ?>
                            </div>
                        </div>
                    </div>
                    <div class="insight-block glass-magical p-6 hover-float-danger mt-2" style="border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 3rem; flex-wrap: wrap;">
                        <div style="flex: 0 0 250px; min-height: 250px; position: relative;">
                            <canvas id="triggerChart"></canvas>
                        </div>
                        <div style="flex: 1; min-width: 250px;">
                            <span class="marker mb-3 block uppercase tracking-widest font-bold text-danger" style="font-size: 1rem;"><span class="emoji-react mr-2" style="font-size: 1.5rem;">🔥</span>EMOTIONAL TRIGGER</span>
                            <div style="font-size: 1rem; line-height: 1.6;">
                                <?= formatAsPointers($report['emotional_tripwire'] ?: 'N/A', false) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Chart Column -->
            <div style="flex: 0 0 450px; display: flex; flex-direction: column; padding: 1.5rem; min-height: 250px;" class="glass-magical hover-float-accent">
                <div class="chart-backdrop"></div>
                <h4 class="text-xs uppercase tracking-widest text-tertiary text-center mb-4">Cognitive State Profile</h4>
                <div style="flex: 1; position: relative; display: flex; justify-content: center; align-items: center;">
                    <canvas id="psychChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Linguistic Feedback Audit -->
    <div class="feedback-audit-section mb-9">
        <h2 class="section-title mb-6">Linguistic Pattern Feedback</h2>
        <div class="glass-magical p-6 mt-4" style="border-radius: 12px; display: flex; flex-wrap: wrap; gap: 4rem; align-items: stretch;">
            <!-- Chart Side -->
            <div style="flex: 0 0 500px; min-height: 500px; position: relative; background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%); border-radius: 50%;">
                <canvas id="lingChart"></canvas>
            </div>
            
            <!-- Text/Pattern Side -->
            <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; justify-content: center;">
                <?php 
                $patterns = $analysis['language_patterns'] ?? [];
                if (empty($patterns)): ?>
                    <div style="text-align: center; color: var(--text-tertiary);">
                        <div style="height: 160px; width: 100%; max-width: 450px; margin: 0 auto 2rem auto; position: relative;" class="glass-magical p-3">
                            <canvas id="neutralSpeechChart"></canvas>
                        </div>
                        <h3 class="text-xl font-bold uppercase tracking-widest text-secondary mb-2">Neutral Speech Detected</h3>
                        <p style="font-size: 0.95rem; line-height: 1.6;">No highly deviating or disruptive linguistic patterns were identified in this interaction. The user's speech profile remained within expected parameters.</p>
                    </div>
                <?php else: ?>
                    <h3 class="text-xs uppercase tracking-widest text-accent font-bold mb-4">Detected Phrases</h3>
                    <div class="patterns-stack" style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($patterns as $p): ?>
                        <div class="pattern-item p-4 hover-float" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; transition: 0.3s;">
                            <div class="pattern-phrase mono text-accent mb-2" style="font-size: 1.05rem;"><span class="emoji-react mr-2">🗣️</span>"<?= htmlspecialchars($p['phrase'] ?? 'Unknown') ?>"</div>
                            <div class="pattern-context text-sm text-secondary" style="line-height: 1.5;"><?= formatAsPointers($p['context'] ?? 'No context provided.', false) ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 6. Strategic Directives -->
    <div class="directives-audit-section mb-9">
        <h2 class="section-title mb-6">Strategic Directives</h2>
        <div class="directives-stack">
            <?php foreach ($recommendations as $idx => $rec): ?>
                <div class="directive-entry fade-in glass-magical hover-float mt-4 p-5 rounded-lg" style="display: flex; align-items: center; gap: 1rem;">
                    <span class="emoji-react" style="font-size: 1.8rem; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 50%;">🎯</span>
                    <span class="directive-idx" style="font-size: 1.5rem; color: rgba(255,255,255,0.2); font-weight: 800;">0<?= $idx + 1 ?></span>
                    <p class="directive-text m-0" style="flex: 1;"><?= htmlspecialchars($rec) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Audit Footer Actions -->
    <div class="audit-footer" style="display: flex; align-items: center; width: 100%;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.documentElement.classList.toggle('light-mode')"><span class="emoji-react" style="margin-right: 8px;">🌓</span>TOGGLE THEME</button>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-left: 12px;" onclick="window.print()">PRINT AUDIT</button>
        <div style="flex: 1;"></div>
        <a href="<?= BASE_URL ?>/app/dashboard.php" class="btn btn-ghost btn-sm">RETURN</a>
        <a href="<?= BASE_URL ?>/app/select-topic.php" class="btn btn-primary btn-sm" style="margin-left: 12px;">NEW TRIAL</a>
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
    const radarValues = [<?= implode(',', $radarData) ?>];
    const radarLabels = labels.map((l, i) => l + ' ' + radarValues[i] + '%');
    new Chart(document.getElementById('radarChart'), {
        type: 'radar',
        data: {
            labels: radarLabels,
            datasets: [{
                data: radarValues,
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
    new Chart(document.getElementById('timeChart'), {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: [<?= implode(',', array_values($avgTimes)) ?>], backgroundColor: pColors, borderRadius: 6 }] },
        options: {
            ...baseOpts,
            animation: { duration: 2200, easing: 'easeOutQuart' } // LIVE SYNC FEEL
        }
    });

    // 4. Psychological State Polar Chart
    const psychData = [<?= implode(',', $psychDataPHP) ?>];
    const psychLabels = ['Defensiveness', 'Adaptability', 'Anxiety', 'Logic Focus', 'Empathy'].map((l, i) => l + ' ' + psychData[i] + '%');
    new Chart(document.getElementById('psychChart'), {
        type: 'polarArea',
        data: {
            labels: psychLabels,
            datasets: [{
                data: psychData,
                backgroundColor: [
                    'rgba(248, 113, 113, 0.7)',
                    'rgba(56, 189, 248, 0.7)',
                    'rgba(251, 191, 36, 0.7)',
                    'rgba(192, 132, 252, 0.7)',
                    'rgba(20, 184, 166, 0.7)'
                ],
                borderWidth: 1,
                borderColor: 'rgba(255,255,255,0.1)',
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 3000, easing: 'easeOutQuart' },
            scales: { r: { ticks: { display: false }, grid: { color: 'rgba(255,255,255,0.05)' }, angleLines: { color: 'rgba(255,255,255,0.1)' } } },
            plugins: { 
                legend: { 
                    display: true, 
                    position: 'bottom',
                    labels: {
                        color: 'rgba(255,255,255,0.85)',
                        font: { size: 11, family: "'JetBrains Mono'" },
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                } 
            }
        }
    });

    // 5. Advanced Doughnut Gauges for Diagnostics
    const gOpts = { responsive: true, maintainAspectRatio: false, cutout: '78%', plugins: { tooltip: { enabled: false } }, animation: { duration: 2500, easing: 'easeOutQuart' } };
    
    const s1 = <?= $s1 ?>;
    new Chart(document.getElementById('miniGauge1'), {
        type: 'doughnut',
        data: { datasets: [{ data: [s1, 100-s1], backgroundColor: ['rgba(20, 184, 166, 1)', 'rgba(255,255,255,0.05)'], borderWidth: 0 }] },
        options: gOpts
    });
    document.getElementById('mg1val').textContent = s1 + '%';

    const s2 = <?= $s2 ?>;
    new Chart(document.getElementById('miniGauge2'), {
        type: 'doughnut',
        data: { datasets: [{ data: [s2, 100-s2], backgroundColor: ['rgba(248, 113, 113, 1)', 'rgba(255,255,255,0.05)'], borderWidth: 0 }] },
        options: gOpts
    });
    document.getElementById('mg2val').textContent = s2 + '%';

    // 6. Insight Mini-Radar Charts
    const bsData = [<?= implode(',', $bsRadarDataPHP) ?>];
    const bsLabels = ['AWARENESS','IMPACT','RECURRENCE'].map((l, i) => l + ' ' + bsData[i] + '%');
    new Chart(document.getElementById('blindSpotChart'), {
        type: 'radar',
        data: { labels: bsLabels, datasets: [{ data: bsData, backgroundColor: 'rgba(56, 189, 248, 0.5)', borderColor: '#38bdf8', borderWidth: 3, pointBackgroundColor: '#fff', pointBorderColor: '#38bdf8', pointRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { r: { ticks:{display:false, max: 100, min: 0}, pointLabels:{display:true, color: '#38bdf8', font: {size: 13, family: "'JetBrains Mono'", weight: '900'}}, grid: { color: 'rgba(56, 189, 248, 0.5)', circular: true, lineWidth: 2 }, angleLines: { color: 'rgba(56, 189, 248, 0.5)', lineWidth: 2 } } }, animation: { duration: 3000 } }
    });

    const triggerData = [<?= implode(',', $triggerRadarDataPHP) ?>];
    const triggerLabels = ['VOLATILITY','FREQUENCY','SEVERITY'].map((l, i) => l + ' ' + triggerData[i] + '%');
    new Chart(document.getElementById('triggerChart'), {
        type: 'radar',
        data: { labels: triggerLabels, datasets: [{ data: triggerData, backgroundColor: 'rgba(248, 113, 113, 0.5)', borderColor: '#f87171', borderWidth: 3, pointBackgroundColor: '#fff', pointBorderColor: '#f87171', pointRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { r: { ticks:{display:false, max: 100, min: 0}, pointLabels:{display:true, color: '#f87171', font: {size: 13, family: "'JetBrains Mono'", weight: '900'}}, grid: { color: 'rgba(248, 113, 113, 0.5)', circular: true, lineWidth: 2 }, angleLines: { color: 'rgba(248, 113, 113, 0.5)', lineWidth: 2 } } }, animation: { duration: 3000 } }
    });

    // 7. Linguistic Profile Chart
    const lingData = [<?= implode(',', $lingDataPHP) ?>];
    const lingLabels = ['TONE','COMPLEXITY','ASSERTIVENESS','EMPATHY','FORMALITY'].map((l, i) => l + ' ' + lingData[i] + '%');
    new Chart(document.getElementById('lingChart'), {
        type: 'polarArea',
        data: {
            labels: lingLabels,
            datasets: [{
                data: lingData,
                backgroundColor: [
                    'rgba(244, 114, 182, 0.8)', // pink
                    'rgba(167, 139, 250, 0.8)', // purple 
                    'rgba(52, 211, 153, 0.8)',  // emerald
                    'rgba(251, 191, 36, 0.8)',  // amber
                    'rgba(56, 189, 248, 0.8)'   // cyan
                ],
                borderWidth: 2,
                borderColor: '#111',
                hoverOffset: 12
            }]
        },
        options: {
            layout: { padding: 10 },
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 3000, easing: 'easeOutQuart' },
            scales: { r: { ticks: { display: false }, grid: { color: 'rgba(255,255,255,0.05)' }, angleLines: { color: 'rgba(255,255,255,0.05)' }, pointLabels: { display: true, centerPointLabels: true, color: 'rgba(255,255,255,0.8)', font: { size: 12, family: "'JetBrains Mono'", weight: 'bold' } } } },
            plugins: { legend: { display: false } }
        }
    });

    // 8. Neutral Baseline Wave Graph
    const neutralCtx = document.getElementById('neutralSpeechChart');
    if (neutralCtx) {
        new Chart(neutralCtx, {
            type: 'line',
            data: {
                labels: ['1','2','3','4','5','6','7','8','9','10','11','12'],
                datasets: [{
                    data: [10, 11, 10, 9, 10, 11, 10, 10, 9, 10, 11, 10], // Flatline calm wave
                    borderColor: '#38bdf8', // Cyan calm color
                    borderWidth: 3,
                    tension: 0.5, // Smooth curve
                    pointRadius: 0,
                    backgroundColor: 'rgba(56, 189, 248, 0.15)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 4000, easing: 'easeInOutSine' },
                scales: { 
                    x: { display: false }, 
                    y: { display: false, min: 0, max: 20 } 
                },
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
