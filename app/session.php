<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
requireAuth();

$user = getCurrentUser();
$sessionId = intval($_GET['session_id'] ?? 0);

if (!$sessionId) { header('Location: ' . BASE_URL . '/app/select-topic.php'); exit; }
$session = getSession($sessionId, $user['id']);
if (!$session) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
if ($session['status'] === 'completed') {
    if ($session['mode'] === 'hr') {
        header('Location: ' . BASE_URL . '/app/hr-report-rich.php?session_id=' . $sessionId);
    } else {
        header('Location: ' . BASE_URL . '/app/report.php?session_id=' . $sessionId);
    }
    exit;
}

// Get actual rounds for this session
$stmt = getDB()->prepare('SELECT COUNT(*) as total FROM rounds WHERE session_id = ?');
$stmt->execute([$sessionId]);
$totalRounds = $stmt->fetch()['total'];

$currentRound = $session['current_round'];
$currentRoundRow = getCurrentRound($sessionId);
if (!$currentRoundRow) { header('Location: ' . BASE_URL . '/app/dashboard.php'); exit; }
$personality = getPersonalityForRound($currentRoundRow['personality_id']);
$topicDisplay = getTopicTitle($session['topic'], $session['custom_topic']);

// For HR mode: fetch all assigned questions for the voice engine's system prompt
$hrQuestionsList = [];
if ($session['mode'] === 'hr') {
    $db = getDB();
    $stmt = $db->prepare('SELECT hq.question, hq.guideline FROM rounds r JOIN hr_questions hq ON r.hr_question_id = hq.id WHERE r.session_id = ? ORDER BY r.round_number ASC');
    $stmt->execute([$sessionId]);
    $hrQuestionsList = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $session['mode'] === 'hr' ? 'HR Behavioral Audit' : "Round $currentRound" ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/chat.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/skins.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <?php if ($session['mode'] === 'hr'): ?>
    <script src="<?= BASE_URL ?>/assets/js/hr-live-engine.js"></script>
    <?php endif; ?>
</head>
<body>

<!-- ====== TRANSITION SCREEN ====== -->
<div class="transition-screen" id="transitionScreen">
    <div class="transition-sequence" id="transSeq">
        <?php if ($session['mode'] === 'hr'): ?>
            <div class="trans-step visible" style="font-size: 2rem; font-weight: 300; letter-spacing: 0.5rem;">INITIATING NEURAL LINK</div>
        <?php else: ?>
            <div class="trans-step" id="transStep1">Round <?= $currentRound ?> of <?= $totalRounds ?></div>
            <div class="trans-step" id="transStep2">Entering next room...</div>
            <div class="trans-step trans-persona" id="transStep3"><?= htmlspecialchars($personality['name']) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- ====== MAIN CHAT WRAPPER ====== -->
<div class="chat-wrapper <?= $session['mode'] === 'hr' ? 'skin-hr-live hr-continuous-mode' : '' ?>" id="chatWrapper" style="opacity: 0;">
    <div class="chat-container">

        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <div class="round-dots <?= $session['mode'] === 'hr' ? 'hr-hide' : '' ?>">
                    <?php for ($i = 1; $i <= $totalRounds; $i++): ?>
                        <div class="round-dot <?= $i < $currentRound ? 'done' : ($i == $currentRound ? 'active' : '') ?>" id="dot-<?= $i ?>"></div>
                    <?php endfor; ?>
                </div>
                <span class="round-label" id="roundLabel"><?= $session['mode'] === 'hr' ? '<span class="pulse text-accent">●</span> LIVE BEHAVIORAL AUDIT' : "Round $currentRound of $totalRounds" ?></span>
            </div>
            <div class="topbar-right">
                <span class="pressure-label" id="pressureLabel">PRESSURE</span>
                <div class="pressure-track">
                    <div class="pressure-fill" id="pressureFill" style="width: 10%;"></div>
                </div>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages"></div>

        <!-- End Round Bar -->
        <div class="end-round-bar" id="endRoundBar" style="display:none;">
            <button class="btn btn-ghost end-round-btn" id="endRoundBtn" onclick="endRound()">End Round & Continue →</button>
        </div>

        <!-- Input Area (hidden in HR voice mode) -->
        <div class="chat-input-area" id="inputArea" style="<?= $session['mode'] === 'hr' ? 'display:none;' : '' ?>">
            <div class="input-wrap">
                <textarea class="chat-input" id="userInput" placeholder="Respond carefully..." rows="1"></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()" disabled>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </div>

        <!-- ====== GOOGLE LIVE HR VOICE UI ====== -->
        <?php if ($session['mode'] === 'hr'): ?>
        <div class="hr-voice-area" id="voiceArea">
            <div class="google-live-visualizer">
                <div class="visualizer-ring"></div>
                <div class="visualizer-ring"></div>
                <div class="visualizer-ring"></div>
                <div class="visualizer-pulse"></div>
                <div class="visualizer-core"></div>
            </div>
            
            <div id="voiceStatus" class="mt-8 text-accent font-mono text-xs uppercase tracking-widest opacity-50">Syncing Behavioral Data...</div>

            <div id="liveTelemetry" class="mt-9 grid grid-cols-2 gap-4 w-full px-8" style="display:none;">
                <div class="glass p-4 bg-black/20 border-white/5">
                    <div class="text-tertiary text-[10px] uppercase mb-1 tracking-tighter">Question</div>
                    <div class="text-white font-mono text-sm" id="valLatency">-- / <?= count($hrQuestionsList) ?></div>
                </div>
                <div class="glass p-4 bg-black/20 border-white/5">
                    <div class="text-tertiary text-[10px] uppercase mb-1 tracking-tighter">Vocal Stability</div>
                    <div class="text-white font-mono text-sm" id="valPauses">Calculating...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ====== LIVE HUD OVERLAY ====== -->
        <div class="live-hud" id="liveHud">
            <div class="hud-header">
                <span class="hud-dot"></span>
                <span>REAL-TIME AUDIT FEED</span>
            </div>
            <div class="hud-body">
                <div style="height: 60px; width: 140px;"><canvas id="hudChart"></canvas></div>
                <div class="hud-stats mt-2">
                    <div class="stat-row"><span class="s-label">VELOCITY</span><span class="s-val" id="hudVelocity">--</span></div>
                    <div class="stat-row"><span class="s-label">PRESSURE</span><span class="s-val" id="hudPressure">--</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== ANALYSIS OVERLAY ====== -->
<div class="analysis-overlay" id="analysisOverlay" style="display:none;">
    <div class="analysis-content">
        <div class="loader-circle"></div>
        <h2 class="mt-5" id="analysisHeading">Generating Professional Audit</h2>
        <p class="text-secondary mt-2" id="analysisStatus">Initializing behavioral baseline...</p>
        <div class="loader-track mt-6"><div class="loader-bar" id="loaderBar" style="width: 0%;"></div></div>
        <div class="loader-meta mt-3">
            <span class="text-xs uppercase tracking-widest text-tertiary">Real-Time Synthesis Active</span>
        </div>
    </div>
</div>

<!-- ====== ROUND TRANSITION OVERLAY (between rounds — text mode only) ====== -->
<div class="transition-screen" id="roundTransition" style="display:none;">
    <div class="transition-sequence">
        <div class="trans-step" id="rtStep1"></div>
        <div class="trans-step" id="rtStep2">Entering next room...</div>
        <div class="trans-step trans-persona" id="rtStep3"></div>
    </div>
</div>

<script>
const SESSION_ID = <?= $sessionId ?>;
const SESSION_MODE = '<?= $session['mode'] ?>';
const CANDIDATE_NAME = '<?= addslashes($session['candidate_name'] ?? '') ?>';
const BASE = '<?= BASE_URL ?>';
const TOTAL = <?= $totalRounds ?>;
const API_CHAT = BASE + '/api/chat.php';
const API_END = BASE + '/api/end-round.php';
const API_ANALYZE = BASE + '/api/analyze.php';

let round = <?= $currentRound ?>;
let exchanges = 0;
let waiting = false;
let basePadding = 24;

const NAMES = {1:'The Micromanager Boss',2:'The Conspiracy Theorist Uncle',3:'The Aggressive Investor',4:'The Passive-Aggressive Coworker', 6:'The Stern HR Recruiter'};
const SKINS = {1:'skin-micromanager',2:'skin-conspiracy',3:'skin-investor',4:'skin-passive-aggressive', 6:'skin-hr-recruiter'};
let hudChart = null;
let hrEngine = null;

// ---- INITIAL HUD INITIALIZATION ----
function initHUD() {
    const ctx = document.getElementById('hudChart').getContext('2d');
    hudChart = new Chart(ctx, {
        type: 'line',
        data: { labels: [], datasets: [{ data: [], borderColor: '#3B82F6', borderWidth: 2, pointRadius: 0, tension: 0.4, fill: true, backgroundColor: 'rgba(59, 130, 246, 0.1)' }] },
        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
    });
}
function updateHUD(val, pressure) {
    if (!hudChart) return;
    hudChart.data.labels.push('');
    hudChart.data.datasets[0].data.push(val);
    if (hudChart.data.datasets[0].data.length > 20) { hudChart.data.labels.shift(); hudChart.data.datasets[0].data.shift(); }
    hudChart.update();
    document.getElementById('hudVelocity').textContent = val + '%';
    document.getElementById('hudPressure').textContent = pressure + '%';
}

// ---- INITIAL TRANSITION (timed text sequence) ----
document.addEventListener('DOMContentLoaded', () => {
    initHUD();
    const screen = document.getElementById('transitionScreen');
    const s1 = document.getElementById('transStep1');
    const s2 = document.getElementById('transStep2');
    const s3 = document.getElementById('transStep3');

    if (s1) s1.classList.add('visible');
    
    setTimeout(() => {
        if (s1) s1.classList.remove('visible');
        if (s2) setTimeout(() => s2.classList.add('visible'), 300);
    }, 1800);

    setTimeout(() => {
        if (s2) s2.classList.remove('visible');
        if (s3) setTimeout(() => s3.classList.add('visible'), 400);
    }, 3200);

    setTimeout(() => {
        screen.style.opacity = '0';
        setTimeout(() => {
            screen.style.display = 'none';
            const wrapper = document.getElementById('chatWrapper');
            wrapper.style.display = 'flex';
            setTimeout(() => {
                wrapper.style.opacity = '1';
                applySkin(<?= $currentRoundRow['personality_id'] ?>);
                
                if (SESSION_MODE === 'hr') {
                    // HR Voice mode — no loadRound, just init voice engine
                    setTimeout(() => initHRVoice(), 1000);
                } else {
                    loadRound();
                }
            }, 100);
        }, 1200);
    }, 6500);

    // Auto-resize textarea (text mode only)
    const input = document.getElementById('userInput');
    if (input) {
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            updateSendBtn();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
    }
});

// ==== HR LIVE VOICE INITIALIZATION (no rounds, predefined questions in system prompt) ====
function initHRVoice() {
    const apiKey = '<?= GEMINI_VOICE_API_KEY ?>';
    
    // Override the system instruction to include predefined questions
    hrEngine = new HRLiveEngine(apiKey, SESSION_ID, CANDIDATE_NAME);
    
    // Inject predefined questions into the engine's system instruction
    hrEngine._buildSystemInstruction = function() {
        const now = new Date();
        const hour = now.getHours();
        let greeting;
        if (hour < 12) greeting = 'Good Morning';
        else if (hour < 17) greeting = 'Good Afternoon';
        else greeting = 'Good Evening';

        const questions = <?= json_encode(array_map(function($q) {
            return $q['question'];
        }, $hrQuestionsList)) ?>;

        let questionBlock = '';
        questions.forEach((q, i) => {
            questionBlock += `\nQuestion ${i + 1}: ${q}`;
        });

        return `You are a highly professional and stern HR Recruiter conducting a formal behavioral interview.

IMPORTANT — YOU MUST START THE CONVERSATION. When the session begins, you do the following IN ORDER:
1. Greet the candidate by name using "${greeting}, ${this.candidateName}."
2. Introduce yourself briefly as the interviewer for today's session.
3. Explain the interview rules clearly:
   - This is a structured behavioral interview with ${questions.length} questions.
   - Answer each question thoroughly and honestly.
   - Responses are evaluated on clarity, depth, professionalism, and logical consistency.
   - Take a moment to think before answering if needed.
   - There are no right or wrong answers, but vague or evasive responses will be challenged.
4. Then ask the FIRST question immediately.

YOUR PREDEFINED QUESTIONS (ask them in this exact order):
${questionBlock}

INTERVIEW CONDUCT RULES:
- Ask ONE question at a time from the list above, then wait for the candidate to answer.
- After they answer, you may ask a brief follow-up, then move to the next question in the list.
- Do NOT skip questions. Do NOT make up new questions. Only use the ${questions.length} questions listed above.
- After all ${questions.length} questions, formally conclude the interview.
- Address the candidate by name (${this.candidateName}) periodically.
- Be professional, composed, and slightly cold. No warm encouragement or filler praise.
- If vague answers are given, challenge them: "Could you be more specific?" or "Give a concrete example."
- When concluding, thank the candidate formally and state that the audit is complete.

YOUR VOICE: Speak clearly and at a measured pace. You are a senior HR professional. Sound authoritative but not hostile.`;
    };
    
    hrEngine.onStatus = (msg) => {
        const statusEl = document.getElementById('voiceStatus');
        const visualizer = document.querySelector('.google-live-visualizer');
        if (statusEl) statusEl.textContent = msg;
        
        if (msg.includes('Established')) {
            const telemetry = document.getElementById('liveTelemetry');
            if (telemetry) telemetry.style.display = 'grid';
            if (statusEl) { statusEl.classList.remove('text-accent'); statusEl.classList.add('text-primary'); }
            if (visualizer) visualizer.style.filter = 'none';
        }
        if (msg.includes('Speaking')) {
            if (visualizer) visualizer.classList.add('ai-speaking');
        }
        if (msg.includes('Listening')) {
            if (visualizer) visualizer.classList.remove('ai-speaking');
        }
        if (msg.includes('Interrupted')) {
            if (visualizer) {
                visualizer.style.filter = 'hue-rotate(120deg) saturate(1.5)';
                setTimeout(() => visualizer.style.filter = 'none', 1000);
            }
        }
    };
    
    hrEngine.onMessage = (role, text) => {
        if (role === 'assistant') {
            typewriterBubble('ai', text);
            if (text.toLowerCase().includes('conclude') || text.toLowerCase().includes('complete') || text.toLowerCase().includes('thank you for your time')) {
                // Mark session completed and redirect to report
                setTimeout(() => {
                    fetch(BASE + '/api/end-round.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ session_id: SESSION_ID, force_complete: true })
                    }).then(() => {
                        window.location.href = BASE + '/app/hr-report-rich.php?session_id=' + SESSION_ID;
                    });
                }, 3000);
            }
        }
    };
    
    hrEngine.onTurnComplete = () => {
        const latencyEl = document.getElementById('valLatency');
        const pauseEl = document.getElementById('valPauses');
        if (latencyEl && hrEngine.metrics.questionCount > 0) {
            latencyEl.textContent = 'Q' + hrEngine.metrics.questionCount + ' / <?= count($hrQuestionsList) ?>';
        }
        if (pauseEl) pauseEl.textContent = 'Active';
    };
    
    hrEngine.init();
}

// ---- TEXT MODE FUNCTIONS ----
function loadRound() {
    fetch(API_CHAT, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ session_id: SESSION_ID, get_opener: true }) })
    .then(r => r.json())
    .then(data => {
        if (data.error) { addSystem(data.error); return; }
        updateTopbar(data);
        if (data.already_started) {
            data.messages.forEach(m => {
                addBubble(m.role === 'assistant' ? 'ai' : 'user', m.content, false);
                if (m.role === 'user') updateHUD(Math.min(100, Math.round(m.char_count / 5)), Math.min(100, Math.round(m.response_time_ms / 100)));
            });
            exchanges = data.exchange_count;
            checkEnd(exchanges);
        } else {
            showTyping();
            setTimeout(() => { hideTyping(); typewriterBubble('ai', data.ai_message); }, 800 + Math.random() * 1200);
        }
        enableInput(); updatePressure();
    })
    .catch(() => addSystem('Connection error. Please refresh.'));
}

function sendMessage() {
    const input = document.getElementById('userInput');
    const msg = input.value.trim();
    if (msg.length < 10 || waiting) return;
    waiting = true; disableInput();
    input.value = ''; input.style.height = 'auto';
    addBubble('user', msg, true);
    showTyping();

    fetch(API_CHAT, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ session_id: SESSION_ID, message: msg }) })
    .then(r => r.json())
    .then(data => {
        const delay = 1000 + Math.random() * 2000;
        setTimeout(() => {
            hideTyping();
            if (data.error) { addSystem(data.error); enableInput(); waiting = false; return; }
            typewriterBubble('ai', data.ai_message);
            exchanges = data.exchange_count;
            updatePressure(); tightenUI();
            updateHUD(Math.min(100, Math.round(data.char_count / 5)), Math.min(100, Math.round(data.response_time_ms / 100)));
            if (data.must_end_round) showEndBar(true);
            else if (data.can_end_round) { showEndBar(false); enableInput(); }
            else enableInput();
            waiting = false;
        }, delay);
    })
    .catch(() => { hideTyping(); addSystem('Connection error.'); enableInput(); waiting = false; });
}

function endRound() {
    disableInput(); document.getElementById('endRoundBar').style.display = 'none';
    fetch(API_END, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ session_id: SESSION_ID }) })
    .then(r => r.json())
    .then(data => {
        if (data.error) { addSystem(data.error); return; }
        if (data.session_completed) triggerAnalysis();
        else { round = data.next_round; showRoundTransition(round); }
    })
    .catch(() => addSystem('Connection error.'));
}

function showRoundTransition(nextRound) {
    const overlay = document.getElementById('roundTransition');
    document.getElementById('rtStep1').textContent = `Round ${nextRound - 1} Complete`;
    document.getElementById('rtStep3').textContent = NAMES[nextRound] || '';
    overlay.style.display = 'flex'; overlay.style.opacity = '1';

    const s1 = document.getElementById('rtStep1');
    const s2 = document.getElementById('rtStep2');
    const s3 = document.getElementById('rtStep3');
    [s1,s2,s3].forEach(s => s.classList.remove('visible'));

    s1.classList.add('visible');
    setTimeout(() => { s1.classList.remove('visible'); s2.classList.add('visible'); }, 1500);
    setTimeout(() => { s2.classList.remove('visible'); s3.classList.add('visible'); }, 2500);
    setTimeout(() => {
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.style.display = 'none';
            document.getElementById('chatMessages').innerHTML = '';
            exchanges = 0; basePadding = 24;
            document.getElementById('endRoundBar').style.display = 'none';
            applySkin(nextRound);
            loadRound();
        }, 500);
    }, 3500);
}

function triggerAnalysis() {
    const overlay = document.getElementById('analysisOverlay');
    const status = document.getElementById('analysisStatus');
    const bar = document.getElementById('loaderBar');
    overlay.style.display = 'flex';
    
    const stages = [
        "Calibrating behavioral baselines...",
        "Profiling response consistency (Round 3)...",
        "Mapping psychological blind spots...",
        "Finalizing cinematic audit report..."
    ];
    
    let stageIdx = 0;
    const stageInterval = setInterval(() => {
        if (stageIdx < stages.length - 1) {
            stageIdx++;
            status.textContent = stages[stageIdx];
        }
    }, 2200);

    let pct = 0;
    const barInterval = setInterval(() => {
        if (pct < 95) {
            pct += (Math.random() * 2);
            bar.style.width = pct + '%';
        }
    }, 150);

    fetch(API_ANALYZE, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ session_id: SESSION_ID }) })
    .then(r => r.json())
    .then(data => {
        clearInterval(stageInterval);
        clearInterval(barInterval);
        if (data.error) { document.getElementById('analysisHeading').textContent = 'Analysis Failed'; status.textContent = data.error; return; }
        
        bar.style.width = '100%';
        status.textContent = "Audit Complete. Redirecting...";
        setTimeout(() => {
            window.location.href = BASE + '/app/report.php?session_id=' + SESSION_ID;
        }, 800);
    })
    .catch(() => { 
        clearInterval(stageInterval); clearInterval(barInterval);
        document.getElementById('analysisHeading').textContent = 'Error'; status.textContent = 'Analysis connection failed.'; 
    });
}

// ---- TYPEWRITER EFFECT ---- 
function typewriterBubble(type, text) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = `message message-${type} fade-in-up`;
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    const time = document.createElement('div');
    time.className = 'message-time';
    time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    div.appendChild(bubble); div.appendChild(time);
    container.appendChild(div);

    let i = 0;
    const speed = Math.max(8, Math.min(25, 2000 / text.length));
    function type_() {
        if (i < text.length) {
            bubble.textContent += text.charAt(i); i++;
            container.scrollTop = container.scrollHeight;
            setTimeout(type_, speed);
        }
    }
    type_();
}

function addBubble(type, text, animate) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = `message message-${type}` + (animate ? ' fade-in-up' : '');
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    bubble.textContent = text;
    const time = document.createElement('div');
    time.className = 'message-time';
    time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    div.appendChild(bubble); div.appendChild(time);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function addSystem(text) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'alert alert-error';
    div.style.maxWidth = '80%'; div.style.margin = '8px auto';
    div.textContent = text;
    container.appendChild(div);
}

function showTyping() {
    if (document.getElementById('typingDots')) return;
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.id = 'typingDots'; div.className = 'typing-indicator';
    div.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}
function hideTyping() { const el = document.getElementById('typingDots'); if (el) el.remove(); }

function showEndBar(must) {
    const bar = document.getElementById('endRoundBar');
    bar.style.display = 'flex';
    if (must) { disableInput(); document.getElementById('endRoundBtn').textContent = round >= TOTAL ? 'Complete & Get Report →' : 'End Round & Continue →'; }
}
function checkEnd(c) { if (c >= 6) showEndBar(true); else if (c >= 4) showEndBar(false); }

function applySkin(r) {
    const w = document.getElementById('chatWrapper');
    Object.values(SKINS).forEach(s => w.classList.remove(s));
    if (SKINS[r]) w.classList.add(SKINS[r]);
}

function updateTopbar(data) {
    document.getElementById('roundLabel').textContent = `Round ${data.round} of ${TOTAL}`;
    document.title = `Round ${data.round}: ${data.personality_name} — The Social Gauntlet`;
    for (let i = 1; i <= TOTAL; i++) {
        const dot = document.getElementById('dot-' + i);
        if (dot) { dot.classList.remove('done','active'); if (i < data.round) dot.classList.add('done'); else if (i == data.round) dot.classList.add('active'); }
    }
}

function updatePressure() {
    const base = (round - 1) * 20;
    const ex = exchanges * 3;
    const total = Math.min(base + ex + 10, 100);
    const fill = document.getElementById('pressureFill');
    fill.style.width = total + '%';
    if (total < 35) fill.style.background = 'linear-gradient(to right, #3B82F6, #60A5FA)';
    else if (total < 65) fill.style.background = 'linear-gradient(to right, #3B82F6, #FBBF24)';
    else fill.style.background = 'linear-gradient(to right, #FBBF24, #EF4444)';
}

function tightenUI() {
    const pct = Math.min((round - 1) * 20 + exchanges * 3 + 10, 100);
    const newPad = Math.max(12, basePadding - (pct * 0.12));
    document.querySelectorAll('.message-bubble').forEach(b => { b.style.padding = `${newPad}px ${newPad + 4}px`; });
}

function enableInput() { 
    if (SESSION_MODE === 'hr') return;
    document.getElementById('userInput').disabled = false; 
    document.getElementById('userInput').focus(); 
    updateSendBtn(); 
}
function disableInput() { document.getElementById('userInput').disabled = true; document.getElementById('sendBtn').disabled = true; }
function updateSendBtn() { document.getElementById('sendBtn').disabled = document.getElementById('userInput').value.trim().length < 10 || waiting; }
</script>
</body>
</html>
