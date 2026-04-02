<?php
// ==============================================
// shared-report.php — Public shared report view
// ==============================================
// Anyone with the share token link can view the report (no login required)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
require_once __DIR__ . '/../includes/db.php';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Look up report by share token
$db = getDB();
$stmt = $db->prepare('SELECT r.*, s.topic, s.custom_topic, s.started_at FROM reports r JOIN sessions s ON r.session_id = s.id WHERE r.share_token = ?');
$stmt->execute([$token]);
$report = $stmt->fetch();

if (!$report) {
    header('Location: ' . BASE_URL . '/error.php');
    exit;
}

$sessionId = $report['session_id'];
$topicDisplay = $report['custom_topic'] ?: $report['topic'];
$analysis = json_decode($report['analysis_json'], true);
$recommendations = json_decode($report['recommendations_json'], true);
$date = date('F j, Y · g:i A', strtotime($report['started_at']));

// Get transcript for charts
$transcript = getFullTranscript($sessionId);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Report — Personality Stress Tester</title>
    <meta name="description" content="Communication Vulnerability Report from the Personality Stress Tester">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/report.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="report-wrapper">
        <!-- Shared banner -->
        <div class="shared-banner">
            <span>🔗 Shared Report</span>
            <a href="<?= BASE_URL ?>/" class="btn btn-sm btn-secondary">Take Your Own Test →</a>
        </div>

        <!-- Header -->
        <div class="report-header">
            <h1>Communication Vulnerability Report</h1>
            <div class="report-meta">
                <span>📅 <?= $date ?></span>
                <span>🎯 <?= htmlspecialchars($topicDisplay) ?></span>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">💪 Strongest Under</div>
                <div class="summary-value"><?= htmlspecialchars($report['strongest_under']) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">⚠️ Biggest Vulnerability</div>
                <div class="summary-value"><?= htmlspecialchars($report['biggest_vulnerability']) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">👁️ Blind Spot</div>
                <div class="summary-value"><?= htmlspecialchars($report['blind_spot']) ?></div>
            </div>
        </div>

        <!-- Pattern Summary -->
        <div class="report-section">
            <h2>Recurring Patterns</h2>
            <div class="report-text"><?= nl2br(htmlspecialchars($report['pattern_summary'])) ?></div>
        </div>

        <!-- Emotional Tripwire -->
        <div class="report-section">
            <h2>Emotional Tripwire</h2>
            <div class="report-text"><?= nl2br(htmlspecialchars($report['emotional_tripwire'])) ?></div>
        </div>

        <!-- Charts -->
        <div class="report-section">
            <h2>Behavioral Metrics</h2>
            <div class="charts-grid">
                <div class="chart-container">
                    <h4>Average Message Length by Round</h4>
                    <canvas id="lengthChart"></canvas>
                </div>
                <div class="chart-container">
                    <h4>Average Response Time by Round (seconds)</h4>
                    <canvas id="timeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Language Patterns -->
        <?php if (!empty($analysis['language_patterns'])): ?>
        <div class="report-section">
            <h2>Language Patterns Detected</h2>
            <table class="patterns-table">
                <thead>
                    <tr>
                        <th>Phrase / Pattern</th>
                        <th>Count</th>
                        <th>What It Reveals</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analysis['language_patterns'] as $pattern): ?>
                    <tr>
                        <td>"<?= htmlspecialchars($pattern['phrase'] ?? '') ?>"</td>
                        <td><?= intval($pattern['count'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($pattern['context'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Round-by-Round -->
        <div class="report-section">
            <h2>Round-by-Round Breakdown</h2>
            <div class="accordion">
                <?php
                $personalityNames = [1=>'The Micromanager Boss',2=>'The Conspiracy Theorist Uncle',3=>'The Aggressive Investor',4=>'The Passive-Aggressive Coworker',5=>'The Emotional Guilt-Tripper'];
                $avatarFiles = [1=>'micromanager.png',2=>'conspiracy.png',3=>'investor.png',4=>'passive-aggressive.png',5=>'guilt-tripper.png'];

                for ($r = 1; $r <= 5; $r++):
                    $roundAnalysis = '';
                    $keyMoment = '';
                    if (!empty($analysis['round_analyses'])) {
                        foreach ($analysis['round_analyses'] as $ra) {
                            if (($ra['round'] ?? 0) == $r) {
                                $roundAnalysis = $ra['performance'] ?? '';
                                $keyMoment = $ra['key_moment'] ?? '';
                                break;
                            }
                        }
                    }
                ?>
                <div class="accordion-item">
                    <button class="accordion-header" onclick="toggleAccordion(this)">
                        <span class="accordion-header-content"><img src="../assets/img/avatars/<?= $avatarFiles[$r] ?>" alt="" class="accordion-avatar"> Round <?= $r ?>: <?= $personalityNames[$r] ?></span>
                        <span class="accordion-chevron">▼</span>
                    </button>
                    <div class="accordion-body">
                        <div class="accordion-content">
                            <?php if ($roundAnalysis): ?>
                                <div class="report-text"><?= nl2br(htmlspecialchars($roundAnalysis)) ?></div>
                            <?php endif; ?>
                            <?php if ($keyMoment): ?>
                                <div class="transcript-excerpt">
                                    <div class="excerpt-quote p<?= $r ?>">
                                        <?= nl2br(htmlspecialchars($keyMoment)) ?>
                                    </div>
                                    <div class="excerpt-annotation p<?= $r ?>">📌 Key moment from this round</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Recommendations -->
        <?php if (!empty($recommendations)): ?>
        <div class="report-section">
            <h2>Recommendations</h2>
            <ul class="recommendations-list">
                <?php foreach ($recommendations as $i => $rec): ?>
                <li class="recommendation-item">
                    <span class="rec-number"><?= $i + 1 ?></span>
                    <span class="rec-text"><?= htmlspecialchars($rec) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- CTA -->
        <div class="report-actions">
            <a href="<?= BASE_URL ?>/" class="btn btn-primary">Take Your Own Stress Test →</a>
        </div>
    </div>

    <script>
    const roundLabels = ['R1: Boss', 'R2: Uncle', 'R3: Investor', 'R4: Coworker', 'R5: Family'];
    const personalityColors = ['#FF3B3B', '#FF8A00', '#FFD600', '#3B82F6', '#A855F7'];
    const chartDefaults = {
        color: '#8A8A9A',
        borderColor: '#2A2A35',
        font: { family: "'JetBrains Mono', monospace", size: 11 }
    };

    new Chart(document.getElementById('lengthChart'), {
        type: 'bar',
        data: {
            labels: roundLabels,
            datasets: [{
                data: [<?= implode(',', array_values($avgLengths)) ?>],
                backgroundColor: personalityColors.map(c => c + '88'),
                borderColor: personalityColors,
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#2A2A35' }, ticks: chartDefaults },
                x: { grid: { display: false }, ticks: chartDefaults }
            }
        }
    });

    new Chart(document.getElementById('timeChart'), {
        type: 'bar',
        data: {
            labels: roundLabels,
            datasets: [{
                data: [<?= implode(',', array_values($avgTimes)) ?>],
                backgroundColor: personalityColors.map(c => c + '88'),
                borderColor: personalityColors,
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#2A2A35' }, ticks: chartDefaults },
                x: { grid: { display: false }, ticks: chartDefaults }
            }
        }
    });

    function toggleAccordion(header) {
        const item = header.parentElement;
        item.classList.toggle('open');
    }
    </script>
</body>
</html>
