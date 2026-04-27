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
$analysis = $report ? json_decode($report['analysis_json'], true) : null;
$mockPreviewUsernames = ['test user2'];
$mockPreviewEmails = ['xyz@xyz.com'];
$currentUsername = strtolower(trim($user['username'] ?? ''));
$currentEmail = strtolower(trim($user['email'] ?? ''));
$isMockPreviewUser = in_array($currentUsername, $mockPreviewUsernames, true) || in_array($currentEmail, $mockPreviewEmails, true);
$forceMock = $isMockPreviewUser && isset($_GET['mock']) && $_GET['mock'] === '1';
$useMock = $isMockPreviewUser && ($forceMock || !$report || !$analysis || !isset($analysis['chart_data']));

if ($useMock) {
    $analysis = [
        'chart_data' => [
            'stress_resistance_index' => 78.4,
            'psych_profile' => [
                'logic' => 82.6,
                'resilience' => 74.9,
                'professionalism' => 80.3,
                'empathy' => 68.7,
                'adaptability' => 76.5
            ],
            'vocal_stability' => [
                'control' => 71.8,
                'cadence' => 66.4,
                'hesitation_ratio' => 39.2
            ],
            'stability_timeline' => [74.2, 76.1, 72.8, 79.5, 81.4, 77.9, 80.2, 78.7],
            'latency_ms' => [1280, 1425, 1190, 1360, 1480, 1310, 1265, 1405]
        ],
        'reaction_consistency' => [78.5, 84.2, 69.6, 72.4]
    ];

    if (!$report) {
        $report = [
            'strongest_under' => 'Structured reasoning and calm response framing under pressure.',
            'biggest_vulnerability' => 'Answer precision drops when asked for measurable impact.',
            'pattern_summary' => 'Candidate demonstrates consistent executive tone with strong composure across escalating prompts. Performance is strongest when framing decisions logically, but examples become less concrete under follow-up challenge. Vocal control remains stable, with moderate latency spikes during metric-driven questions.',
            'analysis_json' => json_encode($analysis)
        ];
    }

    if (empty($transcript)) {
        $transcript = [
            ['role' => 'assistant', 'content' => 'Good afternoon. Tell me about yourself in a professional context.', 'created_at' => date('Y-m-d H:i:s')],
            ['role' => 'user', 'content' => 'I am a software engineer with 3 years of experience in full-stack development, mostly in PHP and JavaScript.', 'created_at' => date('Y-m-d H:i:s')],
            ['role' => 'assistant', 'content' => 'Describe a high-pressure project and your role in resolving it.', 'created_at' => date('Y-m-d H:i:s')],
            ['role' => 'user', 'content' => 'We had a release rollback issue. I coordinated triage, aligned stakeholders, and delivered a safe patch within two hours.', 'created_at' => date('Y-m-d H:i:s')]
        ];
    }
}

$pageTitle = "Executive HR Audit Result";
$extraCss = ['report.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="report-wrapper glass-magical">
    <div class="container py-9">
        <header class="audit-landing mb-9">
            <div class="header-card glass-magical hover-float">
                <span class="index-label">EXECUTIVE PERFORMANCE AUDIT</span>
                <h1 class="text-accent">Audit Result: <span class="text-white"><?= htmlspecialchars($session['candidate_name'] ?: $user['username']) ?></span></h1>
                <p class="text-secondary mt-2">Verified Professional Identity | Session HR-<?= $sessionId ?></p>
            </div>
            
            <div class="index-score-box glass-magical p-6 text-center" style="min-width:200px;">
                <span class="index-label">OVERALL STABILITY</span>
                <div class="index-value text-accent"><?= $analysis['chart_data']['stress_resistance_index'] ?? 0 ?>%</div>
                <div class="index-meter mx-auto"><div class="index-progress" style="width: <?= $analysis['chart_data']['stress_resistance_index'] ?? 0 ?>%"></div></div>
            </div>
        </header>


        <?php if (!$useMock && !$report): ?>
            <div class="glass p-9 text-center">
                <h3 class="mb-4">Synthesizing Executive Audit...</h3>
                <p class="text-secondary">Our behavioral engine is crunching your vocal metrics and response patterns. This takes about 15-20 seconds.</p>
                <div class="mt-6"><span class="pulse text-accent">Processing Neural Transcript...</span></div>
                <script>setTimeout(() => window.location.reload(), 5000);</script>
            </div>
        <?php else: ?>
            <div class="visual-audit-grid mb-9">
                <!-- Competency Radar -->
                <div class="radar-container glass-magical hover-float-accent">
                    <h3 class="mb-6 text-center text-sm font-mono tracking-widest uppercase">Competency Matrix</h3>
                    <canvas id="competencyRadar"></canvas>
                </div>

                <!-- Secondary Charts -->
                <div class="flex flex-col gap-6">
                    <div id="volume-chart-box" class="chart-box-sm glass-magical hover-float">
                        <h4 class="text-xs font-mono uppercase text-tertiary mb-4">Vocal Stability Timeline</h4>
                        <canvas id="stabilityChart"></canvas>
                    </div>
                    <div id="consistency-chart-box" class="chart-box-sm glass-magical hover-float">
                        <h4 class="text-xs font-mono uppercase text-tertiary mb-4">Response Latency (ms)</h4>
                        <canvas id="latencyChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="diagnostics-grid mb-9">
                <div class="diag-card glass-magical hover-float" style="border-left-color: var(--accent);">
                    <div class="diag-icon">💼</div>
                    <div class="diag-details">
                        <span class="diag-label">Core Professional Strength</span>
                        <p class="diag-text"><?= htmlspecialchars($report['strongest_under']) ?></p>
                    </div>
                </div>
                <div class="diag-card glass-magical hover-float-danger" style="border-left-color: var(--text-danger);">
                    <div class="diag-icon">⚠️</div>
                    <div class="diag-details">
                        <span class="diag-label">Primary Behavioral Risk</span>
                        <p class="diag-text text-danger"><?= htmlspecialchars($report['biggest_vulnerability']) ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-magical p-7 mb-9">
                <h3 class="mb-5 text-accent font-mono uppercase tracking-widest text-sm">Executive Summary</h3>
                <p class="line-height-relaxed text-secondary"><?= htmlspecialchars($report['pattern_summary']) ?></p>
            </div>

            <div class="transcript-section">
                <h3 class="mb-6 font-mono uppercase tracking-tighter opacity-50">Verified Interview Transcript</h3>
                <div class="transcript-list glass-magical p-4">
                    <?php foreach ($transcript as $m): ?>
                        <div class="transcript-item mb-4 pb-4 border-b border-white/5">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-[10px] font-mono uppercase <?= $m['role'] === 'user' ? 'text-primary' : 'text-accent' ?>"><?= $m['role'] === 'user' ? (htmlspecialchars($session['candidate_name'] ?: 'CANDIDATE')) : 'RECRUITER' ?></span>
                                <span class="text-[10px] text-tertiary"><?= date('H:i:s', strtotime($m['created_at'])) ?></span>
                            </div>
                            <div class="transcript-content text-sm"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if ($analysis): ?>
    const auditData = <?= json_encode($analysis['chart_data']) ?>;

    // 1. Competency Radar
    new Chart(document.getElementById('competencyRadar'), {
        type: 'radar',
        data: {
            labels: ['Logic', 'Resilience', 'Presence', 'Empathy', 'Adaptability'],
            datasets: [{
                label: 'Candidate Profile',
                data: [
                    auditData.psych_profile.logic,
                    auditData.psych_profile.resilience,
                    auditData.psych_profile.professionalism,
                    auditData.psych_profile.empathy,
                    auditData.psych_profile.adaptability
                ],
                backgroundColor: 'rgba(20, 184, 166, 0.2)',
                borderColor: '#14b8a6',
                borderWidth: 2,
                pointBackgroundColor: '#14b8a6'
            }]
        },
        options: {
            scales: {
                r: {
                    angleLines: { color: 'rgba(255,255,255,0.05)' },
                    grid: { color: 'rgba(255,255,255,0.1)' },
                    pointLabels: { color: '#94a3b8', font: { size: 10 } },
                    ticks: { display: false, max: 100, min: 0 }
                }
            },
            plugins: { legend: { display: false } }
        }
    });

    // 2. Stability Timeline
    const stabilityPoints = auditData.stability_timeline || [74, 76, 73, 79, 81, 78, 80, 79];
    new Chart(document.getElementById('stabilityChart'), {
        type: 'line',
        data: {
            labels: ['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8'],
            datasets: [{
                data: stabilityPoints,
                borderColor: '#38bdf8',
                tension: 0.4,
                pointRadius: 0,
                fill: true,
                backgroundColor: 'rgba(56, 189, 248, 0.05)'
            }]
        },
        options: {
            scales: {
                x: { display: false },
                y: { display: false, min: 0, max: 100 }
            },
            plugins: { legend: { display: false } }
        }
    });

    // 3. Latency Chart
    const latencyData = auditData.latency_ms || [1280, 1425, 1190, 1360, 1480, 1310, 1265, 1405];
    new Chart(document.getElementById('latencyChart'), {
        type: 'bar',
        data: {
            labels: ['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8'],
            datasets: [{
                data: latencyData,
                backgroundColor: ['#c084fc', '#a855f7', '#d8b4fe', '#8b5cf6', '#c084fc', '#a855f7', '#d8b4fe', '#8b5cf6'],
                borderRadius: 4
            }]
        },
        options: {
            scales: {
                x: { display: false },
                y: { display: false, min: 0, max: 2500 }
            },
            plugins: { legend: { display: false } }
        }
    });
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
