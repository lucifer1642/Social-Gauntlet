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
                <div class="sub">Voice-driven structured interview · 9 adaptive questions</div>
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

    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

    // ---------- Config ----------
    const TOTAL_QUESTIONS = 9;
    const SILENCE_STOP_MS = 1800;
    const NEXT_QUESTION_DELAY_MS = 8000;

    // ---------- State ----------
    let running = false;
    let flowState = 'IDLE';
    let lastAssistantText = '';
    let userName = 'Friend';
    let qaHistory = [];
    let qIndex = 0;

    // realtime mic buffer
    let recognizer = null;
    let bgListening = false;
    let liveInterim = '';
    let liveFinal = '';
    let lastSpeechAt = 0;

    // ---------- UI Helpers ----------
    function setStatus(text) { micStatus.textContent = text; }

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

    function sleep(ms) { return new Promise(res => setTimeout(res, ms)); }

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

    replayBtn?.addEventListener('click', () => {
        if (lastAssistantText) speak(lastAssistantText);
    });

    // ---------- Recognition ----------
    function initRecognizer() {
        if (!SR) return false;

        recognizer = new SR();
        recognizer.lang = 'en-US';
        recognizer.interimResults = true;
        recognizer.continuous = true;
        recognizer.maxAlternatives = 1;

        recognizer.onresult = (event) => {
            let interimChunk = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const t = (event.results[i][0]?.transcript || '').trim();
                if (!t) continue;
                if (event.results[i].isFinal) {
                    liveFinal += (liveFinal ? ' ' : '') + t;
                } else {
                    interimChunk += (interimChunk ? ' ' : '') + t;
                }
            }
            liveInterim = interimChunk.trim();
            lastSpeechAt = Date.now();

            // live preview of what user is saying
            const liveText = [liveFinal, liveInterim].filter(Boolean).join(' ').trim();
            if (liveText) showUserText(liveText);
        };

        recognizer.onerror = () => {
            if (bgListening && running) {
                setTimeout(() => { try { recognizer.start(); } catch (_) {} }, 200);
            }
        };

        recognizer.onend = () => {
            if (bgListening && running) {
                setTimeout(() => { try { recognizer.start(); } catch (_) {} }, 150);
            }
        };

        return true;
    }

    function startBackgroundListening() {
        if (!recognizer && !initRecognizer()) return false;
        if (bgListening) return true;
        bgListening = true;
        try { recognizer.start(); } catch (_) {}
        return true;
    }

    function stopBackgroundListening() {
        bgListening = false;
        try { recognizer?.stop(); } catch (_) {}
    }

    function clearBuffer() {
        liveInterim = '';
        liveFinal = '';
        lastSpeechAt = 0;
    }

    async function captureUntilSilence(maxWaitMs = 60000) {
        const started = Date.now();
        const baselineFinal = liveFinal;

        setStatus('LISTENING...');
        return new Promise((resolve) => {
            const t = setInterval(() => {
                const now = Date.now();
                const elapsed = now - started;

                const newFinal = liveFinal.slice(baselineFinal.length).trim();
                const combined = [newFinal, liveInterim].filter(Boolean).join(' ').trim();

                const hasSpeech = combined.length > 0;
                const silenceFor = lastSpeechAt ? (now - lastSpeechAt) : Infinity;

                if (hasSpeech && silenceFor >= SILENCE_STOP_MS) {
                    clearInterval(t);
                    resolve(combined);
                    return;
                }

                if (elapsed >= maxWaitMs) {
                    clearInterval(t);
                    resolve(combined);
                }
            }, 120);
        });
    }

    // ---------- Context-Aware Question Generator ----------
    function cleanName(v) {
        return (v || '')
            .replace(/[^\p{L}\p{N}\s'-]/gu, '')
            .trim()
            .split(/\s+/)
            .slice(0, 3)
            .join(' ');
    }

    function pickNextQuestion(index, history) {
        const base = [
            "Tell me about a time you faced a significant challenge at work. How did you handle it?",
            "How do you typically handle disagreements with colleagues or supervisors?",
            "Describe a situation where you had to meet a very tight deadline. What was your approach?",
            "Tell me about a time you received critical feedback. How did you respond to it?",
            "How do you prioritize tasks when you have multiple urgent deadlines competing for attention?",
            "Describe a situation where you had to lead a team through a difficult or ambiguous project.",
            "Tell me about a time you made a mistake at work. What did you learn from it?",
            "How do you handle working under sustained pressure or in a high-stress environment?",
            "What is one professional accomplishment you are most proud of, and why does it matter to you?"
        ];

        if (index === 0) return base[0];

        const prev = history[history.length - 1] || {};
        const a = (prev.answer || '').toLowerCase();

        // Context-adaptive follow-ups based on previous answer
        if (a.includes('team') || a.includes('collaborat') || a.includes('group')) {
            return "You mentioned team dynamics. How do you handle situations where a team member is underperforming?";
        }
        if (a.includes('fail') || a.includes('mistake') || a.includes('wrong')) {
            return "You mentioned a setback. How do you ensure you recover and maintain credibility after a failure?";
        }
        if (a.includes('lead') || a.includes('manag') || a.includes('delegat')) {
            return "You mentioned leadership. What is your approach to delegating tasks while maintaining accountability?";
        }
        if (a.includes('stress') || a.includes('pressure') || a.includes('overwhelm')) {
            return "You mentioned high pressure. What specific techniques do you use to stay composed under stress?";
        }
        if (a.includes('conflict') || a.includes('disagree') || a.includes('argument')) {
            return "You brought up conflict. Can you describe how you turned a professional disagreement into a productive outcome?";
        }
        if (a.includes('deadline') || a.includes('time') || a.includes('urgent')) {
            return "Time pressure came up. How do you decide what to sacrifice when you genuinely cannot meet every deadline?";
        }
        if (a.includes('feedback') || a.includes('criticism') || a.includes('review')) {
            return "Feedback is important. How do you give constructive criticism to someone senior to you?";
        }

        return base[Math.min(index, base.length - 1)];
    }

    function acknowledgment(answer) {
        if (!answer) return "I didn't catch that clearly. Let's move on to the next question.";
        const short = answer.split(/\s+/).slice(0, 12).join(' ');
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

    // ---------- Time Greeting ----------
    function getTimeGreeting() {
        const h = new Date().getHours();
        if (h < 12) return 'Good morning';
        if (h < 17) return 'Good afternoon';
        return 'Good evening';
    }

    // ---------- Main Flow ----------
    async function runFlow() {
        // Step 1: Ask Name
        flowState = 'ASK_NAME';
        progressEl.textContent = 'IDENTITY CALIBRATION';
        const greeting = getTimeGreeting();
        await speakAndShow(`${greeting}. I am your Senior Executive Auditor for today's session. Before we begin the behavioral audit, may I know your full name for the record?`);
        await pushMessage('assistant', lastAssistantText);

        clearBuffer();
        const nameAnswer = await captureUntilSilence(30000);
        showUserText(nameAnswer || '(no speech detected)');
        userName = cleanName(nameAnswer) || CANDIDATE_NAME;
        await pushMessage('user', userName);

        // Step 2: Greet & Brief
        flowState = 'GREET';
        progressEl.textContent = 'BRIEFING';
        await speakAndShow(`Welcome, ${userName}. This is a structured behavioral interview consisting of ${TOTAL_QUESTIONS} adaptive questions. Answer each question thoroughly and honestly. Your responses will be evaluated on clarity, depth, and professionalism. Let us begin.`);
        await pushMessage('assistant', lastAssistantText);

        // Step 3: Question Loop
        flowState = 'QUESTIONS';
        qIndex = 0;
        qaHistory = [];

        while (running && qIndex < TOTAL_QUESTIONS) {
            const question = pickNextQuestion(qIndex, qaHistory);
            progressEl.textContent = `QUESTION ${qIndex + 1} OF ${TOTAL_QUESTIONS}`;

            const qText = `Question ${qIndex + 1}. ${question}`;
            await speakAndShow(qText);
            await pushMessage('assistant', qText);

            clearBuffer();
            const answer = await captureUntilSilence(60000);
            showUserText(answer || '(no speech detected)');

            qaHistory.push({ question, answer: answer || '' });
            await pushMessage('user', answer || '(no response)');

            const ack = acknowledgment(answer);
            await speakAndShow(ack);
            await pushMessage('assistant', ack);

            // Wait before next question
            if (qIndex < TOTAL_QUESTIONS - 1) {
                setStatus('NEXT QUESTION IN 8 SECONDS...');
                await sleep(NEXT_QUESTION_DELAY_MS);
            }

            qIndex++;
        }

        // Step 4: Finish & Generate Report
        flowState = 'DONE';
        stopBackgroundListening();
        progressEl.textContent = 'AUDIT COMPLETE';
        await speakAndShow(`Thank you, ${userName}. I have completed all ${TOTAL_QUESTIONS} questions and captured your responses. The behavioral audit is now complete. Your Executive Performance Report is being generated.`);
        await pushMessage('assistant', lastAssistantText);
        setStatus('GENERATING REPORT...');

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

        if (!initRecognizer()) {
            await speakAndShow('Voice input is not supported in your browser. Please use Chrome or Edge.');
            stopFlow();
            return;
        }

        startBackgroundListening();

        try {
            transcriptWrap.style.display = 'none';
            responseWrap.style.display = 'none';
            await runFlow();
        } catch (e) {
            console.error(e);
            await speakAndShow('An error occurred during the audit. Please refresh and try again.');
        }
    }

    function stopFlow() {
        running = false;
        flowState = 'IDLE';
        stopBackgroundListening();
        window.speechSynthesis?.cancel();
        setMicUI(false);
        setStatus('TAP TO BEGIN AUDIT');
        progressEl.textContent = 'READY';
    }

    micBtn.addEventListener('click', async () => {
        if (running) stopFlow();
        else await startFlow();
    });
});
</script>
</body>
</html>
