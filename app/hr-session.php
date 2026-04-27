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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HR Voice Interview — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body{background:#020617;color:#e2e8f0;font-family:Inter,system-ui,sans-serif;margin:0}
    .wrap{min-height:100vh;display:flex;justify-content:center;align-items:center;padding:20px}
    .card{width:100%;max-width:920px;background:#0b1224;border:1px solid #1e293b;border-radius:22px;padding:24px}
    .badge{display:inline-block;padding:8px 14px;border:1px solid #155e75;border-radius:999px;color:#22d3ee;font-size:12px}
    h1{margin:14px 0 6px;font-size:42px}
    h1 span{color:#22d3ee}
    .sub{color:#94a3b8;font-size:30px}
    .meta{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0}
    .box{border:1px solid #1e293b;border-radius:14px;padding:14px}
    .k{font-size:12px;color:#94a3b8;display:block}
    .v{font-size:18px;font-weight:700;margin-top:4px;display:block}
    .micZone{text-align:center;margin-top:16px}
    .ring{width:160px;height:160px;border-radius:999px;border:2px solid #0c4a6e;margin:0 auto;display:flex;align-items:center;justify-content:center}
    .ring.active{animation:pulse 1.4s infinite;border-color:#ef4444}
    @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.35)}70%{box-shadow:0 0 0 20px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
    .micBtn{width:118px;height:118px;border-radius:999px;border:2px solid #0ea5e9;background:#0f172a;color:#38bdf8;font-size:36px;cursor:pointer}
    .micBtn.listening{border-color:#ef4444;color:#fca5a5}
    #status{margin-top:12px;font-family:monospace;color:#94a3b8}
    .panel{margin-top:14px;border:1px solid #1e293b;border-radius:12px;padding:12px;display:none}
    .panel .lbl{font-size:11px;color:#38bdf8;margin-bottom:6px;display:block}
    .panel.user .lbl{color:#34d399}
    .debug{margin-top:12px;background:#020617;border:1px dashed #334155;border-radius:10px;padding:10px;color:#93c5fd;font-size:12px;white-space:pre-wrap;max-height:180px;overflow:auto}
    .overlay{position:fixed;inset:0;background:rgba(2,6,23,.9);display:none;align-items:center;justify-content:center;flex-direction:column;gap:12px;z-index:9999}
    .loader{width:50px;height:50px;border:4px solid #164e63;border-top-color:#22d3ee;border-radius:999px;animation:spin .8s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    @media(max-width:900px){h1{font-size:30px}.sub{font-size:22px}.meta{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="badge">● EXECUTIVE BEHAVIORAL AUDIT</div>
    <h1>Candidate: <span><?= htmlspecialchars($candidateName) ?></span></h1>
    <div class="sub">Voice-driven structured interview · 9 adaptive questions · live turn-taking</div>

    <div class="meta">
      <div class="box"><span class="k">SESSION ID</span><span class="v">#<?= (int)$sessionId ?></span></div>
      <div class="box"><span class="k">MODE</span><span class="v">HR Voice Interview</span></div>
      <div class="box"><span class="k">PROGRESS</span><span class="v" id="progress">READY</span></div>
    </div>

    <div id="assistantPanel" class="panel">
      <span class="lbl">AUDITOR</span>
      <div id="assistantText"></div>
    </div>

    <div id="userPanel" class="panel user">
      <span class="lbl">CANDIDATE</span>
      <div id="userText"></div>
    </div>

    <div class="micZone">
      <div id="ring" class="ring">
        <button id="micBtn" class="micBtn" aria-label="Start/Stop">
          <i id="micIcon" class="fa-solid fa-microphone"></i>
        </button>
      </div>
      <div id="status">TAP TO BEGIN AUDIT</div>
    </div>

    <div id="debug" class="debug">Debug initialized.</div>
  </div>
</div>

<div id="overlay" class="overlay">
  <div class="loader"></div>
  <div style="color:#22d3ee;font-weight:700">Generating report...</div>
</div>

<script>
(() => {
  const BASE = '<?= BASE_URL ?>';
  const SESSION_ID = <?= (int)$sessionId ?>;
  const CANDIDATE_NAME = <?= json_encode($candidateName) ?>;

  const micBtn = document.getElementById('micBtn');
  const micIcon = document.getElementById('micIcon');
  const ring = document.getElementById('ring') || document.getElementById('breatheRing');
  const statusEl = document.getElementById('status') || document.getElementById('micStatus');
  const progressEl = document.getElementById('progress') || document.getElementById('questionProgress');
  const aPanel = document.getElementById('assistantPanel') || document.getElementById('voiceResponse');
  const aText = document.getElementById('assistantText') || document.getElementById('responseText');
  const uPanel = document.getElementById('userPanel') || document.getElementById('voiceTranscript');
  const uText = document.getElementById('userText') || document.getElementById('transcriptText');
  const overlay = document.getElementById('overlay') || document.getElementById('analysisOverlay');

  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

  const TOTAL = 9;
  const MAX_ANSWER_MS = 30000;
  const END_SILENCE_MS = 1000;
  const POLL_MS = 80;

  let running = false;
  let recognizer = null;
  let micHot = false;
  let speakingNow = false;

  let liveFinal = '';
  let liveInterim = '';
  let lastSpeechAt = 0;
  let speechStartAt = 0;

  let userName = '';
  let qa = [];

  function setStatus(t){ if(statusEl) statusEl.textContent = t; }
  function setProgress(t){ if(progressEl) progressEl.textContent = t; }
  function showAssistant(t){ if(aPanel) aPanel.style.display='block'; if(aText) aText.textContent=t; }
  function showUser(t){ if(uPanel) uPanel.style.display='block'; if(uText) uText.textContent=t || '(no response)'; }

  function setMicUI(active){
    micBtn?.classList.toggle('listening', active);
    ring?.classList.toggle('active', active);
    if(micIcon) micIcon.className = active ? 'fa-solid fa-stop' : 'fa-solid fa-microphone';
  }

  function sleep(ms){ return new Promise(r => setTimeout(r, ms)); }

  function speak(text){
    return new Promise((resolve) => {
      showAssistant(text);
      setStatus('AUDITOR SPEAKING...');
      if (!('speechSynthesis' in window)) return resolve();
      speakingNow = true;
      window.speechSynthesis.cancel();
      const u = new SpeechSynthesisUtterance(text);
      u.rate = 0.98;
      u.pitch = 1.0;
      u.onend = () => { speakingNow = false; resolve(); };
      u.onerror = () => { speakingNow = false; resolve(); };
      window.speechSynthesis.speak(u);
    });
  }

  function resetBuffers(){
    liveFinal = '';
    liveInterim = '';
    lastSpeechAt = 0;
    speechStartAt = 0;
  }

  function initRecognizer(){
    if(!SR) return false;
    recognizer = new SR();
    recognizer.lang = 'en-US';
    recognizer.interimResults = true;
    recognizer.continuous = true;
    recognizer.maxAlternatives = 1;

    recognizer.onresult = (e) => {
      let interim = '';
      for(let i=e.resultIndex;i<e.results.length;i++){
        const t = (e.results[i][0]?.transcript || '').trim();
        if(!t) continue;
        if(e.results[i].isFinal) liveFinal += (liveFinal ? ' ' : '') + t;
        else interim += (interim ? ' ' : '') + t;
      }
      liveInterim = interim.trim();

      const now = Date.now();
      if(!speechStartAt) speechStartAt = now;
      lastSpeechAt = now;

      if(!speakingNow){
        const preview = [liveFinal, liveInterim].filter(Boolean).join(' ').trim();
        if(preview) showUser(preview);
      }
    };

    recognizer.onerror = () => {
      if(micHot && running){
        setTimeout(() => { try{ recognizer.start(); }catch(_){} }, 120);
      }
    };

    recognizer.onend = () => {
      if(micHot && running){
        setTimeout(() => { try{ recognizer.start(); }catch(_){} }, 120);
      }
    };

    return true;
  }

  async function ensureMicPermission(){
    try{
      const s = await navigator.mediaDevices.getUserMedia({audio:true});
      s.getTracks().forEach(t => t.stop());
      return true;
    }catch{
      return false;
    }
  }

  function startMic(){
    if(!recognizer && !initRecognizer()) return false;
    if(micHot) return true;
    micHot = true;
    try{ recognizer.start(); }catch{}
    return true;
  }

  function stopMic(){
    micHot = false;
    try{ recognizer?.stop(); }catch{}
  }

  async function captureOne({maxMs = MAX_ANSWER_MS, silenceMs = END_SILENCE_MS} = {}){
    resetBuffers();
    setStatus('LISTENING...');
    const started = Date.now();

    return new Promise((resolve) => {
      const timer = setInterval(() => {
        const now = Date.now();
        const elapsed = now - started;
        const txt = [liveFinal, liveInterim].filter(Boolean).join(' ').trim();

        // finalize on silence after speech
        const silenceFor = lastSpeechAt ? (now - lastSpeechAt) : Infinity;
        if(txt && silenceFor >= silenceMs){
          clearInterval(timer);
          resolve(txt);
          return;
        }

        // hard timeout
        if(elapsed >= maxMs){
          clearInterval(timer);
          resolve(txt);
        }
      }, POLL_MS);
    });
  }

  function cleanName(s){
    return (s || '').replace(/[^\p{L}\p{N}\s'-]/gu,'').trim().split(/\s+/).slice(0,3).join(' ');
  }

  function getQuestion(i, history){
    const base = [
      "Tell me about a significant challenge at work and how you handled it.",
      "How do you handle disagreements with colleagues or managers?",
      "Describe how you managed a very tight deadline.",
      "Tell me about a time you received difficult feedback.",
      "How do you prioritize when multiple urgent tasks arrive together?",
      "Describe a time you led people through uncertainty.",
      "Tell me about a mistake and what you learned from it.",
      "How do you stay effective during prolonged pressure?",
      "What professional achievement are you most proud of and why?"
    ];
    if(i === 0) return base[0];
    const prev = (history[history.length - 1]?.answer || '').toLowerCase();
    if(prev.includes('team')) return "You mentioned team context. How do you handle an underperforming team member?";
    if(prev.includes('mistake') || prev.includes('fail')) return "How did you rebuild confidence after that incident?";
    if(prev.includes('stress') || prev.includes('pressure')) return "What concrete method helps you regulate stress in the moment?";
    return base[i] || base[base.length - 1];
  }

  async function pushMessage(role, content){
    try{
      await fetch(BASE + '/api/hr-push-message.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({session_id: SESSION_ID, role, content})
      });
    }catch{}
  }

  async function getAnswerWithFallback(questionNo){
    let ans = await captureOne({maxMs: 30000, silenceMs: 1000});
    if(ans) return ans;

    // retry once quickly
    await speak("I did not catch that clearly. Please repeat your answer.");
    ans = await captureOne({maxMs: 20000, silenceMs: 1000});
    if(ans) return ans;

    // final fallback typing
    return prompt(`Question ${questionNo}: I couldn't hear you. Please type your answer:`) || '';
  }

  async function completeInterview(){
    setProgress('GENERATING REPORT');
    setStatus('GENERATING REPORT...');
    if(overlay) overlay.style.display = 'flex';

    try{
      await fetch(BASE + '/api/analyze.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ session_id: SESSION_ID, force_complete: true })
      });
      window.location.href = BASE + '/app/hr-report-rich.php?session_id=' + SESSION_ID;
    }catch{
      window.location.href = BASE + '/app/dashboard.php';
    }
  }

  async function runFlow(){
    setProgress('IDENTITY');
    await speak("Welcome. Please say your full name for the record.");
    await pushMessage('assistant', 'Welcome. Please say your full name for the record.');

    const nameRaw = await getAnswerWithFallback('Name');
    userName = cleanName(nameRaw) || CANDIDATE_NAME || 'Candidate';
    showUser(userName);
    await pushMessage('user', userName);

    await speak(`Thank you, ${userName}. We will now begin a nine question behavioral interview.`);
    await pushMessage('assistant', `Thank you, ${userName}. We will now begin a nine question behavioral interview.`);

    qa = [];
    for(let i=0; i<TOTAL && running; i++){
      setProgress(`QUESTION ${i+1} OF ${TOTAL}`);
      const q = getQuestion(i, qa);

      await speak(`Question ${i+1}. ${q}`);
      await pushMessage('assistant', `Question ${i+1}. ${q}`);

      const ans = await getAnswerWithFallback(i+1);
      showUser(ans || '(no response)');
      await pushMessage('user', ans || '(no response)');

      qa.push({question:q, answer:ans || ''});

      await speak(ans ? "Noted. Thank you." : "No clear response captured. Moving to next question.");
      await pushMessage('assistant', ans ? "Noted. Thank you." : "No clear response captured. Moving to next question.");

      await sleep(250);
    }

    if(!running) return;
    await speak(`Thank you ${userName}. Interview is complete.`);
    await pushMessage('assistant', `Thank you ${userName}. Interview is complete.`);
    await completeInterview();
  }

  async function startFlow(){
    if(running) return;
    running = true;
    setMicUI(true);
    setStatus('INITIALIZING...');

    const perm = await ensureMicPermission();
    if(!perm){
      alert('Microphone permission is blocked. Allow mic access and reload.');
      stopFlow();
      return;
    }

    if(!initRecognizer()){
      alert('SpeechRecognition not supported. Use latest Chrome/Edge.');
      stopFlow();
      return;
    }

    startMic();

    try{
      await runFlow();
    }catch(e){
      console.error(e);
      alert('Interview flow error. Please refresh and retry.');
      stopFlow();
    }
  }

  function stopFlow(){
    running = false;
    stopMic();
    window.speechSynthesis?.cancel();
    setMicUI(false);
    setStatus('TAP TO BEGIN AUDIT');
    setProgress('READY');
  }

  micBtn?.addEventListener('click', async () => {
    if(running) stopFlow();
    else await startFlow();
  });
})();
</script>
</body>
</html>