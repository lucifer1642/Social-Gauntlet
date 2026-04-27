<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
requireAuth();

$user = getCurrentUser();
$sessionId = intval($_GET['session_id'] ?? 0);
$session = getSession($sessionId, $user['id']);

if (!$session || ($session['mode'] ?? 'standard') !== 'hr') {
    header('Location: ' . BASE_URL . '/app/dashboard.php');
    exit;
}

if ($session['status'] === 'completed') {
    header('Location: ' . BASE_URL . '/app/hr-report-rich.php?session_id=' . $sessionId);
    exit;
}

$candidateName = $session['candidate_name'] ?: $user['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Voice Interview — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #020617; color: #e2e8f0; font-family: 'Inter', system-ui, sans-serif; }

        .hr-wrap {
            min-height: 100vh;
            background-image: url('../img/backdrop.png');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
        }
        .hr-wrap::before {
            content: '';
            position: absolute; inset: 0;
            background: rgba(2,6,23,0.82);
            z-index: 0;
        }

        .hr-card {
            width: 100%; max-width: 720px;
            background: rgba(15,23,42,0.7);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(148,163,184,0.15);
            border-radius: 28px;
            padding: 40px 36px;
            box-shadow: 0 30px 60px -12px rgba(0,0,0,0.6), inset 0 1px 1px rgba(255,255,255,0.04);
            z-index: 1;
            position: relative;
        }

        .hr-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(148,163,184,0.1);
        }
        .hr-header .badge-label {
            display: inline-block;
            background: rgba(34,211,238,0.1);
            color: #22d3ee;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 10px;
            font-family: monospace;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .hr-header h1 { font-size: 1.6rem; color: #f8fafc; margin-bottom: 6px; }
        .hr-header h1 span { color: #22d3ee; }
        .hr-header .sub { color: #94a3b8; font-size: 0.85rem; }
        #questionProgress {
            color: #38bdf8;
            font-family: monospace;
            font-size: 12px;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        /* ---- Mic Button ---- */
        .mic-area { text-align: center; margin-bottom: 28px; position: relative; }
        .mic-btn {
            width: 100px; height: 100px;
            border-radius: 50%;
            border: 2px solid rgba(56,189,248,0.4);
            background: rgba(15,23,42,0.8);
            color: #38bdf8;
            font-size: 32px;
            cursor: pointer;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .mic-btn:hover { border-color: #38bdf8; box-shadow: 0 0 30px rgba(56,189,248,0.3); }
        .mic-btn.listening {
            border-color: #ef4444;
            color: #fca5a5;
            animation: mic-pulse 1.5s infinite;
        }
        @keyframes mic-pulse {
            0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
            70% { box-shadow: 0 0 0 15px rgba(239,68,68,0); }
            100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        }

        .breathe-ring {
            position: absolute;
            top: 50%; left: 50%;
            width: 130px; height: 130px;
            margin: -65px 0 0 -65px;
            border: 2px solid rgba(56,189,248,0.15);
            border-radius: 50%;
            z-index: 1;
            transition: all 0.5s;
        }
        .breathe-ring.active {
            animation: ring-expand 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
            border-color: rgba(239,68,68,0.3);
        }
        @keyframes ring-expand {
            0% { transform: scale(0.9); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: scale(1.6); opacity: 0; }
        }

        #micStatus {
            margin-top: 14px;
            color: #94a3b8;
            font-family: monospace;
            font-size: 12px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* ---- Transcript Boxes ---- */
        .voice-box {
            display: none;
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 14px;
            font-size: 0.95rem;
            line-height: 1.6;
            position: relative;
        }
        .voice-box .label {
            font-size: 9px;
            font-family: monospace;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        .voice-response {
            background: linear-gradient(145deg, rgba(30,58,138,0.25), rgba(15,23,42,0.5));
            border: 1px solid rgba(56,189,248,0.3);
            color: #e0f2fe;
        }
        .voice-response .label { color: #38bdf8; }
        .voice-transcript {
            background: rgba(16,185,129,0.08);
            border: 1px solid rgba(16,185,129,0.25);
            color: #d1fae5;
        }
        .voice-transcript .label { color: #34d399; }

        .replay-btn {
            position: absolute;
            top: 12px; right: 14px;
            background: none;
            border: 1px solid rgba(56,189,248,0.3);
            color: #38bdf8;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .replay-btn:hover { background: rgba(56,189,248,0.1); }

        /* ---- Analysis Overlay ---- */
        .analysis-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(2,6,23,0.95);
            z-index: 100;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        .analysis-overlay h2 { color: #22d3ee; font-size: 1.4rem; }
        .analysis-overlay p { color: #94a3b8; font-size: 0.9rem; }
        .spin-loader {
            width: 56px; height: 56px;
            border: 4px solid rgba(34,211,238,0.15);
            border-top-color: #22d3ee;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="hr-wrap">
        <div class="hr-card">
            <div class="hr-header">
                <div class="badge-label">EXECUTIVE BEHAVIORAL AUDIT</div>
                <h1>Candidate: <span><?= htmlspecialchars($candidateName) ?></span></h1>
                <div class="sub">Voice-driven structured interview · 9 questions</div>
                <div id="questionProgress">READY</div>
            </div>

            <!-- Agent Response -->
            <div class="voice-box voice-response" id="voiceResponse">
                <span class="label">AUDITOR</span>
                <button class="replay-btn" id="replayBtn"><i class="fa-solid fa-rotate-right"></i> Replay</button>
                <div id="responseText"></div>
            </div>

            <!-- User Transcript -->
            <div class="voice-box voice-transcript" id="voiceTranscript">
                <span class="label">CANDIDATE</span>
                <div id="transcriptText"></div>
            </div>

            <!-- Mic Button -->
            <div class="mic-area">
                <div class="breathe-ring" id="breatheRing"></div>
                <button class="mic-btn" id="micBtn">
                    <i class="fa-solid fa-microphone" id="micIcon"></i>
                </button>
                <div id="micStatus">TAP TO BEGIN AUDIT</div>
            </div>
        </div>
    </div>

    <!-- Analysis Overlay -->
    <div class="analysis-overlay" id="analysisOverlay">
        <div class="spin-loader"></div>
        <h2>Synthesizing Audit Report...</h2>
        <p>Analyzing behavioral patterns and vocal response metrics.</p>
    </div>

<script type="module">
document.addEventListener('DOMContentLoaded', function () {
    const BASE = '<?= BASE_URL ?>';
    const SESSION_ID = <?= $sessionId ?>;
    const CANDIDATE_NAME = '<?= addslashes($candidateName) ?>';

    const micBtn = document.getElementById('micBtn');
    const micIcon = document.getElementById('micIcon');
    const micStatus = document.getElementById('micStatus');
    const breatheRing = document.getElementById('breatheRing');
    const progressEl = document.getElementById('questionProgress');

    const transcriptWrap = document.getElementById('voiceTranscript');
    const transcriptText = document.getElementById('transcriptText');
    const responseWrap = document.getElementById('voiceResponse');
    const responseText = document.getElementById('responseText');
    const replayBtn = document.getElementById('replayBtn');
    const analysisOverlay = document.getElementById('analysisOverlay');

    // ---- Flow State Machine ----
    const FLOW = {
        IDLE: 'IDLE',
        ASK_NAME: 'ASK_NAME',
        GREET: 'GREET',
        ASKING: 'ASKING',
        FINISHED: 'FINISHED'
    };

    let flowState = FLOW.IDLE;
    let running = false;
    let lastAssistantText = '';
    let userName = '';
    let currentQuestionIndex = -1;
    let answers = [];

    // ---- HR Behavioral Interview Questions ----
    const QUESTIONS = [
        "Tell me about a time you faced a significant challenge at work. How did you handle it?",
        "How do you handle disagreements with colleagues or supervisors?",
        "Describe a situation where you had to meet a tight deadline. What was your approach?",
        "Tell me about a time you received critical feedback. How did you respond?",
        "How do you prioritize tasks when you have multiple urgent deadlines?",
        "Describe a situation where you had to lead a team through a difficult project.",
        "Tell me about a time you made a mistake at work. What did you learn from it?",
        "How do you handle working under pressure or in a high-stress environment?",
        "What is one professional accomplishment you are most proud of, and why?"
    ];

    // ---- Speech Recognition Setup ----
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognizer = null;
    if (SR) {
        recognizer = new SR();
        recognizer.lang = 'en-US';
        recognizer.interimResults = false;
        recognizer.maxAlternatives = 1;
        recognizer.continuous = false;
    }

    function setStatus(text) {
        micStatus.textContent = text;
    }

    function setMicUI(active) {
        if (active) {
            micBtn.classList.add('listening');
            breatheRing.classList.add('active');
            micIcon.className = 'fa-solid fa-stop';
        } else {
            micBtn.classList.remove('listening');
            breatheRing.classList.remove('active');
            micIcon.className = 'fa-solid fa-microphone';
        }
    }

    function showUserText(text) {
        transcriptText.textContent = text || '(no speech detected)';
        transcriptWrap.style.display = 'block';
    }

    function showAssistantText(text) {
        lastAssistantText = text;
        responseText.textContent = text;
        responseWrap.style.display = 'block';
    }

    function speak(text) {
        return new Promise((resolve) => {
            if (!('speechSynthesis' in window)) return resolve();
            window.speechSynthesis.cancel();
            const u = new SpeechSynthesisUtterance(text);
            u.rate = 0.93;
            u.pitch = 1.0;
            u.onend = resolve;
            u.onerror = resolve;
            window.speechSynthesis.speak(u);
        });
    }

    async function speakAndShow(text) {
        showAssistantText(text);
        setStatus('AUDITOR SPEAKING...');
        await speak(text);
    }

    function listenOnce(timeoutMs = 15000) {
        return new Promise((resolve) => {
            if (!recognizer) return resolve('');

            let done = false;
            const finish = (val) => {
                if (done) return;
                done = true;
                resolve((val || '').trim());
            };

            const timer = setTimeout(() => {
                try { recognizer.stop(); } catch (e) {}
                finish('');
            }, timeoutMs);

            recognizer.onresult = (event) => {
                clearTimeout(timer);
                const text = event.results?.[0]?.[0]?.transcript || '';
                finish(text);
            };

            recognizer.onerror = () => {
                clearTimeout(timer);
                finish('');
            };

            recognizer.onend = () => {
                // fallback handled by timer
            };

            try {
                setStatus('LISTENING...');
                recognizer.start();
            } catch (e) {
                clearTimeout(timer);
                finish('');
            }
        });
    }

    function cleanName(v) {
        return (v || '')
            .replace(/[^\p{L}\p{N}\s'-]/gu, '')
            .trim()
            .split(/\s+/)
            .slice(0, 3)
            .join(' ');
    }

    function ackForAnswer(i, ans) {
        if (!ans) return "I didn't catch that clearly, but let's continue to the next question.";
        const short = ans.split(' ').slice(0, 12).join(' ');
        return `Noted. I heard: "${short}..." — Thank you. Moving on.`;
    }

    async function pushMessage(role, content) {
        try {
            await fetch(BASE + '/api/hr-push-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: SESSION_ID, role: role, content: content })
            });
        } catch (e) {
            console.error('Push error:', e);
        }
    }

    // ---- FLOW STEPS ----

    function getTimeGreeting() {
        const h = new Date().getHours();
        if (h < 12) return 'Good morning';
        if (h < 17) return 'Good afternoon';
        return 'Good evening';
    }

    async function askNameStep() {
        flowState = FLOW.ASK_NAME;
        progressEl.textContent = 'IDENTITY CALIBRATION';
        const greeting = getTimeGreeting();
        await speakAndShow(`${greeting}. I am your Senior Executive Auditor. Before we begin the behavioral audit, may I know your full name for the record?`);
        await pushMessage('assistant', lastAssistantText);

        const nameHeard = await listenOnce(10000);
        showUserText(nameHeard || '(no speech detected)');
        userName = cleanName(nameHeard) || CANDIDATE_NAME;
        await pushMessage('user', userName);
    }

    async function greetStep() {
        flowState = FLOW.GREET;
        progressEl.textContent = 'BRIEFING';
        await speakAndShow(`Welcome, ${userName}. This is a structured behavioral interview consisting of ${QUESTIONS.length} questions. Answer each question thoroughly and honestly. Your responses will be evaluated on clarity, depth, and professionalism. Let us begin.`);
        await pushMessage('assistant', lastAssistantText);
    }

    async function questionnaireStep() {
        flowState = FLOW.ASKING;
        answers = [];

        for (let i = 0; i < QUESTIONS.length; i++) {
            if (!running) return;
            currentQuestionIndex = i;
            progressEl.textContent = `QUESTION ${i + 1} OF ${QUESTIONS.length}`;

            const qText = `Question ${i + 1}. ${QUESTIONS[i]}`;
            await speakAndShow(qText);
            await pushMessage('assistant', qText);

            const ans = await listenOnce(20000);
            showUserText(ans || '(no speech detected)');
            answers.push({
                question_no: i + 1,
                question: QUESTIONS[i],
                answer: ans || '(no response)'
            });
            await pushMessage('user', ans || '(no response)');

            const ack = ackForAnswer(i, ans);
            await speakAndShow(ack);
            await pushMessage('assistant', ack);
        }
    }

    async function finishStep() {
        flowState = FLOW.FINISHED;
        progressEl.textContent = 'AUDIT COMPLETE';
        await speakAndShow(`Thank you, ${userName}. I have recorded all ${QUESTIONS.length} responses. The behavioral audit is now complete. Your Executive Performance Report is being generated.`);
        await pushMessage('assistant', lastAssistantText);
        setStatus('GENERATING REPORT...');

        // Show analysis overlay
        analysisOverlay.style.display = 'flex';

        try {
            await fetch(BASE + '/api/analyze.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: SESSION_ID, force_complete: true })
            });
            window.location.href = BASE + '/app/hr-report-rich.php?session_id=' + SESSION_ID;
        } catch (e) {
            console.error('Analysis error:', e);
            window.location.href = BASE + '/app/dashboard.php';
        }
    }

    async function startFlow() {
        if (running) return;
        running = true;
        setMicUI(true);

        if (!recognizer) {
            await speakAndShow('Voice input is not supported in this browser. Please use Chrome or Edge.');
            stopFlow();
            return;
        }

        try {
            flowState = FLOW.IDLE;
            userName = '';
            currentQuestionIndex = -1;
            answers = [];
            transcriptWrap.style.display = 'none';
            responseWrap.style.display = 'none';

            await askNameStep();
            if (!running) return;

            await greetStep();
            if (!running) return;

            await questionnaireStep();
            if (!running) return;

            await finishStep();
        } catch (e) {
            console.error(e);
            await speakAndShow('An error occurred during the audit. Please refresh and try again.');
        }
    }

    function stopFlow() {
        running = false;
        flowState = FLOW.IDLE;
        setMicUI(false);
        setStatus('TAP TO BEGIN AUDIT');
        progressEl.textContent = 'READY';
        try { recognizer?.stop(); } catch (e) {}
        window.speechSynthesis?.cancel();
    }

    micBtn.addEventListener('click', async function () {
        if (running) {
            stopFlow();
        } else {
            await startFlow();
        }
    });

    replayBtn?.addEventListener('click', function () {
        if (lastAssistantText) speak(lastAssistantText);
    });
});
</script>
</body>
</html>
