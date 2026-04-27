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
    <title>HR Voice Interview - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <style>
        * { box-sizing: border-box; }
        .hr-wrap { min-height: 100vh; background-image: url('../img/backdrop.png'); background-size: cover; background-position: center; color: #e2e8f0; display: flex; align-items: center; justify-content: center; padding: 24px; position: relative; }
        .hr-wrap::before { content: ''; position: absolute; top:0; left:0; right:0; bottom:0; background: rgba(2,6,23,0.75); z-index: 0; pointer-events: none; }
        .hr-card { width: 100%; max-width: 1000px; background: rgba(15,23,42,0.65); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(148,163,184,0.2); border-radius: 24px; padding: 36px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5), inset 0 1px 1px rgba(255,255,255,0.05); z-index: 1; position: relative; }
        .hr-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; border-bottom: 1px solid rgba(148,163,184,0.1); padding-bottom: 20px; }
        .hr-question { font-size: 1.25rem; line-height: 1.6; margin: 0 0 20px 0; color: #f8fafc; min-height: 80px; padding: 24px; border-radius: 16px; background: linear-gradient(145deg, rgba(30,58,138,0.3), rgba(15,23,42,0.5)); border: 1px solid rgba(56,189,248,0.4); box-shadow: 0 0 20px rgba(56,189,248,0.1); position: relative; }
        .hr-question::before { content: 'AI AGENT:'; position: absolute; top: -10px; left: 20px; background: #0f172a; padding: 0 8px; font-size: 10px; font-family: monospace; color: #38bdf8; letter-spacing: 1px; border: 1px solid rgba(56,189,248,0.4); border-radius: 10px; }
        .hr-status { color: #38bdf8; font-family: monospace; font-size: 13px; letter-spacing: 0.1em; text-transform: uppercase; text-shadow: 0 0 10px rgba(56,189,248,0.4); }
        .hr-answer { width: 100%; min-height: 140px; resize: vertical; background: rgba(2,6,23,0.6); border: 1px solid rgba(148,163,184,0.3); border-radius: 12px; padding: 16px; color: #f8fafc; font-size: 1.05rem; transition: all 0.3s; margin-bottom: 20px; box-shadow: inset 0 2px 10px rgba(0,0,0,0.5); }
        .hr-answer:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 0 3px rgba(56,189,248,0.2), inset 0 2px 10px rgba(0,0,0,0.5); }
        .hr-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: center; background: rgba(15,23,42,0.4); padding: 16px; border-radius: 16px; border: 1px solid rgba(148,163,184,0.1); margin-bottom: 24px; }
        
        .btn-modern { padding: 12px 24px; border-radius: 12px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-modern:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
        .btn-primary-m { background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; box-shadow: 0 4px 15px rgba(37,99,235,0.4); }
        .btn-primary-m:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37,99,235,0.6); }
        .btn-success-m { background: linear-gradient(135deg, #059669, #10b981); color: white; box-shadow: 0 4px 15px rgba(16,185,129,0.4); }
        .btn-success-m:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.6); }
        .btn-outline-m { background: transparent; border: 1px solid rgba(148,163,184,0.4); color: #cbd5e1; }
        .btn-outline-m:not(:disabled):hover { background: rgba(248,250,252,0.05); border-color: #f8fafc; color: white; }
        .btn-danger-m { background: transparent; border: 1px solid rgba(239,68,68,0.5); color: #fca5a5; }
        .btn-danger-m:not(:disabled):hover { background: rgba(239,68,68,0.1); color: white; border-color: #ef4444; }

        .listening-pulse { animation: pulse 1.5s infinite; box-shadow: 0 0 0 0 rgba(239,68,68,0.7) !important; background: rgba(239,68,68,0.2) !important; color: #fca5a5 !important; border-color: #ef4444 !important; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.7); } 70% { box-shadow: 0 0 0 10px rgba(239,68,68,0); } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); } }

        .hr-transcript { max-height: 200px; overflow-y: auto; border: 1px solid rgba(148,163,184,0.15); border-radius: 12px; padding: 16px; background: rgba(2,6,23,0.5); scrollbar-width: thin; scrollbar-color: rgba(148,163,184,0.3) transparent; }
        .hr-transcript::-webkit-scrollbar { width: 6px; }
        .hr-transcript::-webkit-scrollbar-thumb { background-color: rgba(148,163,184,0.3); border-radius: 3px; }
        .hr-entry { padding: 10px 12px; border-bottom: 1px solid rgba(148,163,184,0.1); font-size: 14px; line-height: 1.5; color: #cbd5e1; display: flex; flex-direction: column; gap: 4px; }
        .hr-entry:last-child { border-bottom: none; }
        .hr-entry strong { color: #38bdf8; font-family: monospace; font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; display: inline-block; }
        .hr-entry.candidate strong { color: #10b981; }
    </style>
</head>
<body>
    <div class="hr-wrap">
        <div class="hr-card">
            <div class="hr-top">
                <div>
                    <div class="badge-label mb-3">HR VOICE INTERVIEW</div>
                    <h1 class="text-2xl mb-1">Candidate: <span class="text-accent"><?= htmlspecialchars($candidateName) ?></span></h1>
                    <div id="progress" class="text-secondary">Question -- / --</div>
                </div>
                <div id="status" class="hr-status">Idle</div>
            </div>



            <div id="mic-error" style="display: none; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #fca5a5; padding: 16px; border-radius: 12px; margin-bottom: 20px; text-align: center;">
                <strong>MICROPHONE BLOCKED</strong><br>
                Please click the lock icon (🔒) in your browser's URL bar, allow Microphone access, and refresh the page.
            </div>

            <!-- ====== PRIMARY: ELEVENLABS SDK ====== -->
            <div id="ai-container" style="margin: 0 0 24px 0; padding: 40px; background: rgba(2,6,23,0.7); border: 1px solid rgba(34,211,238,0.5); border-radius: 16px; text-align: center; box-shadow: 0 0 30px rgba(34,211,238,0.1); position: relative; overflow: hidden;">
                <div id="ai-idle-state">
                    <h2 style="color: #22d3ee; margin-bottom: 10px; font-size: 1.5rem;">Ready to Begin</h2>
                    <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 30px;">Click the button below to establish a neural link with the HR Agent.</p>
                    <button id="start-ai-btn" class="btn-modern btn-primary-m" style="padding: 16px 32px; font-size: 18px; border-radius: 50px;">
                        INITIATE AUDIT
                    </button>
                </div>
                
                <div id="ai-active-state" style="display: none; flex-direction: column; align-items: center; justify-content: center; min-height: 250px;">
                    <div id="status-text" style="color: #38bdf8; font-family: monospace; font-size: 14px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 40px; text-shadow: 0 0 10px rgba(56, 189, 248, 0.5);">Connecting...</div>
                    
                    <!-- Dynamic Visualizer -->
                    <div class="orb-container" style="position: relative; width: 120px; height: 120px; margin: 0 auto 50px auto;">
                        <div class="orb-ring ring-1" style="position: absolute; inset: -20px; border: 2px solid rgba(56,189,248,0.2); border-radius: 50%; animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;"></div>
                        <div class="orb-ring ring-2" style="position: absolute; inset: -10px; border: 2px solid rgba(56,189,248,0.4); border-radius: 50%; animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite 0.5s;"></div>
                        <div class="orb-core" id="orb-core" style="position: absolute; inset: 0; background: radial-gradient(circle at 30% 30%, #7dd3fc, #0284c7); border-radius: 50%; box-shadow: 0 0 30px #0284c7; transition: all 0.3s ease; animation: float 3s ease-in-out infinite;"></div>
                    </div>
                    
                    <button id="end-ai-btn" class="btn-modern btn-danger-m" style="padding: 10px 24px; border-radius: 30px;">
                        TERMINATE LINK & GENERATE AUDIT
                    </button>
                </div>

                <!-- Analysis Overlay -->
                <div id="ai-analysis-state" style="display: none; flex-direction: column; align-items: center; justify-content: center; min-height: 250px;">
                    <div class="pulse-loader mb-6" style="width: 60px; height: 60px; border: 4px solid rgba(34,211,238,0.2); border-top-color: #22d3ee; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <h2 style="color: #22d3ee; margin-bottom: 10px;">Synthesizing Audit...</h2>
                    <p style="color: #94a3b8; font-size: 0.95rem;">Our behavioral engine is analyzing your vocal metrics and response patterns.</p>
                </div>
            </div>

            <style>
                @keyframes pulse-ring {
                    0% { transform: scale(0.8); opacity: 0; }
                    50% { opacity: 1; }
                    100% { transform: scale(1.5); opacity: 0; }
                }
                @keyframes float {
                    0% { transform: translateY(0px); }
                    50% { transform: translateY(-10px); }
                    100% { transform: translateY(0px); }
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                .orb-listening { transform: scale(1.1); box-shadow: 0 0 50px #38bdf8 !important; }
                .orb-speaking { transform: scale(1.2); box-shadow: 0 0 60px #818cf8 !important; background: radial-gradient(circle at 30% 30%, #a5b4fc, #4f46e5) !important; }
            </style>
        </div>
    </div>

<script type="module">
import { Conversation } from "https://cdn.jsdelivr.net/npm/@11labs/client/+esm";

const BASE = '<?= BASE_URL ?>';
const SESSION_ID = <?= $sessionId ?>;
const CANDIDATE_NAME = '<?= addslashes($candidateName) ?>';

function getGreeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
}

const micBtn = document.getElementById('start-ai-btn'); // Link to your existing UI
const endAiBtn = document.getElementById('end-ai-btn');
const aiIdleState = document.getElementById('ai-idle-state');
const aiActiveState = document.getElementById('ai-active-state');
const aiAnalysisState = document.getElementById('ai-analysis-state');
const statusText = document.getElementById('status-text');
const orbCore = document.getElementById('orb-core');
const micError = document.getElementById('mic-error');
const elStatus = document.getElementById('status');

let conversation = null;
let isEnding = false;

async function pushMessage(role, content) {
    try {
        await fetch(BASE + '/api/hr-push-message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: SESSION_ID,
                role: role === 'user' ? 'user' : 'assistant',
                content: content
            })
        });
    } catch (e) {
        console.error("Failed to save message:", e);
    }
}

async function finalizeAudit() {
    if (isEnding) return;
    isEnding = true;

    aiActiveState.style.display = 'none';
    aiAnalysisState.style.display = 'flex';
    statusText.textContent = 'FINALIZING...';

    try {
        const res = await fetch(BASE + '/api/analyze.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: SESSION_ID, force_complete: true })
        });
        const data = await res.json();
        
        if (data.success) {
            window.location.href = BASE + '/app/hr-report-rich.php?session_id=' + SESSION_ID;
        } else {
            alert("Analysis failed: " + (data.error || "Unknown error"));
            window.location.href = BASE + '/app/dashboard.php';
        }
    } catch (e) {
        console.error("Finalization failed:", e);
        window.location.href = BASE + '/app/dashboard.php';
    }
}

micBtn.addEventListener('click', async function() {
    micError.style.display = 'none';
    aiIdleState.style.display = 'none';
    aiActiveState.style.display = 'flex';
    statusText.textContent = 'Connecting...';
    
    try {
        // Permissions check
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stream.getTracks().forEach(track => track.stop());
    } catch (err) {
        console.error("Mic permission failed:", err);
        resetAiUi();
        micError.innerHTML = '<strong>MICROPHONE BLOCKED</strong><br>Please allow Microphone access and refresh.';
        micError.style.display = 'block';
        return;
    }

    try {
        const tokenRes = await fetch(BASE + '/api/elevenlabs-token.php');
        const tokenData = await tokenRes.json();
        if (tokenData.error) throw new Error(tokenData.error);
        
        conversation = await Conversation.startSession({
            signedUrl: tokenData.signed_url,
            onConnect: () => {
                statusText.textContent = 'Neural Link Established. Synchronizing...';
                elStatus.textContent = 'Active';
                orbCore.className = 'orb-core orb-listening';
            },
            onDisconnect: () => {
                elStatus.textContent = 'Idle';
                if (!isEnding) finalizeAudit();
            },
            onMessage: (message) => {
                if (message.message && message.role) {
                    pushMessage(message.role, message.message);
                }
            },
            onError: (error) => {
                console.error('Error:', error);
                statusText.textContent = 'Connection failed';
                if (!isEnding) resetAiUi();
            },
            onModeChange: (mode) => {
                if (mode.mode === 'speaking') {
                    statusText.textContent = 'Agent is speaking...';
                    orbCore.className = 'orb-core orb-speaking';
                } else {
                    statusText.textContent = 'Listening...';
                    orbCore.className = 'orb-core orb-listening';
                }
            }
        });

        // Small delay to ensure the session is fully initialized and ready to receive messages
        setTimeout(() => {
            if (conversation) {
                const greeting = getGreeting();
                conversation.sendUserMessage(`Hello, I am ${CANDIDATE_NAME}. I am ready to begin the HR Behavioral Audit. Please greet me as the Senior Executive Auditor and ask the first behavioral question.`);
            }
        }, 1000);

    } catch (err) {
        console.error(err);
        statusText.textContent = 'Connection failed';
        resetAiUi();
    }
});

endAiBtn.addEventListener('click', async () => {
    if (conversation) {
        await conversation.endSession();
    }
    finalizeAudit();
});

function resetAiUi() {
    conversation = null;
    aiActiveState.style.display = 'none';
    aiIdleState.style.display = 'block';
    orbCore.className = 'orb-core';
    elStatus.textContent = 'Idle';
}
</script>
</body>
</html>
