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
  const ring = document.getElementById('ring');
  const statusEl = document.getElementById('status');
  const progressEl = document.getElementById('progress');
  const aPanel = document.getElementById('assistantPanel');
  const aText = document.getElementById('assistantText');
  const uPanel = document.getElementById('userPanel');
  const uText = document.getElementById('userText');
  const debugEl = document.getElementById('debug');
  const overlay = document.getElementById('overlay');

  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

  const TOTAL = 9;
  const MAX_ANSWER_MS = 30000;
  const SILENCE_MS = 900;
  const POLL_MS = 70;

  let state = 'IDLE';
  let running = false;
  let recognizer = null;
  let listening = false;

  let liveFinal = '';
  let liveInterim = '';
  let speechStartedAt = 0;
  let lastSpeechAt = 0;

  let userName = '';
  let qIndex = 0;
  let qa = [];

  function log(msg){
    console.log(msg);
    debugEl.textContent += '\n' + msg;
    debugEl.scrollTop = debugEl.scrollHeight;
  }
  function setStatus(s){ statusEl.textContent = s; }
  function setProgress(s){ progressEl.textContent = s; }
  function showAssistant(t){ aPanel.style.display='block'; aText.textContent=t; }
  function showUser(t){ uPanel.style.display='block'; uText.textContent=t || '(no speech detected)'; }
  function setMic(active){
    micBtn.classList.toggle('listening', active);
    ring.classList.toggle('active', active);
    micIcon.className = active ? 'fa-solid fa-stop' : 'fa-solid fa-microphone';
  }

  function speak(text){
    return new Promise((resolve)=>{
      showAssistant(text);
      setStatus('AUDITOR SPEAKING...');
      if (!('speechSynthesis' in window)) return resolve();
      window.speechSynthesis.cancel();
      const u = new SpeechSynthesisUtterance(text);
      u.rate = 0.99; u.pitch = 1.0;
      u.onend = resolve; u.onerror = resolve;
      window.speechSynthesis.speak(u);
    });
  }

  function resetBuffer(){
    liveFinal=''; liveInterim=''; speechStartedAt=0; lastSpeechAt=0;
  }

  function initRecognizer(){
    if (!SR) return false;
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
      if(!speechStartedAt) speechStartedAt = now;
      lastSpeechAt = now;
      const txt = [liveFinal, liveInterim].filter(Boolean).join(' ').trim();
      if(txt) showUser(txt);
    };

    recognizer.onerror = (e) => {
      log('Recognizer error: ' + (e.error || 'unknown'));
      if (listening && running) setTimeout(() => { try{ recognizer.start(); }catch{} }, 120);
    };
    recognizer.onend = () => {
      if (listening && running) setTimeout(() => { try{ recognizer.start(); }catch{} }, 120);
    };
    return true;
  }

  function startListening(){
    if(!recognizer && !initRecognizer()) return false;
    if(listening) return true;
    listening = true;
    try{ recognizer.start(); } catch(e){ log('startListening failed: ' + e.message); }
    return true;
  }
  function stopListening(){
    listening = false;
    try{ recognizer && recognizer.stop(); } catch{}
  }

  async function ensureMicPermission(){
    try{
      const s = await navigator.mediaDevices.getUserMedia({audio:true});
      s.getTracks().forEach(t => t.stop());
      return true;
    }catch(e){
      log('Mic permission denied: ' + e.message);
      return false;
    }
  }

  async function captureAnswer(){
    setStatus('LISTENING...');
    const started = Date.now();
    const baseline = liveFinal;

    return new Promise((resolve) => {
      const timer = setInterval(() => {
        const now = Date.now();
        const elapsed = now - started;
        const newFinal = liveFinal.slice(baseline.length).trim();
        const combined = [newFinal, liveInterim].filter(Boolean).join(' ').trim();

        const hasSpeech = combined.length > 0;
        const silenceFor = lastSpeechAt ? (now - lastSpeechAt) : Infinity;

        if(hasSpeech && silenceFor >= SILENCE_MS){
          clearInterval(timer);
          return resolve(combined);
        }
        if(elapsed >= MAX_ANSWER_MS){
          clearInterval(timer);
          return resolve(combined);
        }
      }, POLL_MS);
    });
  }

  function nextQuestion(idx, history){
    const base = [
      "Tell me about a significant challenge at work and how you handled it.",
      "How do you handle disagreements with colleagues or managers?",
      "Describe how you worked under a tight deadline.",
      "Tell me about a time you received critical feedback.",
      "How do you prioritize when everything is urgent?",
      "Describe a time you led people through ambiguity.",
      "Tell me about a mistake and what you learned.",
      "How do you stay effective under sustained pressure?",
      "What professional achievement are you most proud of and why?"
    ];
    if(idx===0) return base[0];
    const prev = (history[history.length-1]?.answer || '').toLowerCase();
    if(prev.includes('team')) return "You mentioned teams. How do you handle underperforming team members?";
    if(prev.includes('mistake') || prev.includes('fail')) return "How did you rebuild trust after that situation?";
    if(prev.includes('stress') || prev.includes('pressure')) return "What concrete technique helps you regulate under stress?";
    return base[Math.min(idx, base.length-1)];
  }

  async function pushMessage(role, content){
    try{
      await fetch(BASE + '/api/hr-push-message.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({session_id: SESSION_ID, role, content})
      });
    }catch(e){ log('pushMessage failed: ' + e.message); }
  }

  function cleanName(t){
    return (t || '').replace(/[^\p{L}\p{N}\s'-]/gu,'').trim().split(/\s+/).slice(0,3).join(' ');
  }

  async function runInterview(){
    setProgress('IDENTITY');
    const intro = "Good day. I am your executive auditor. Please tell me your full name.";
    await speak(intro); await pushMessage('assistant', intro);

    resetBuffer();
    let nameAns = await captureAnswer();
    if(!nameAns){
      nameAns = prompt('Could not hear clearly. Please type your full name:') || '';
    }
    userName = cleanName(nameAns) || CANDIDATE_NAME || 'Candidate';
    showUser(userName);
    await pushMessage('user', userName);

    setProgress('BRIEFING');
    const brief = `Welcome, ${userName}. We will do ${TOTAL} adaptive questions. Please answer naturally.`;
    await speak(brief); await pushMessage('assistant', brief);

    qIndex = 0; qa = [];
    while(running && qIndex < TOTAL){
      setProgress(`QUESTION ${qIndex+1} OF ${TOTAL}`);
      const q = nextQuestion(qIndex, qa);

      await speak(`Question ${qIndex+1}. ${q}`);
      await pushMessage('assistant', `Question ${qIndex+1}. ${q}`);

      resetBuffer();
      let ans = await captureAnswer();
      if(!ans){
        ans = prompt(`I could not catch Question ${qIndex+1}. Type your answer (optional):`) || '';
      }
      showUser(ans || '(no response)');
      await pushMessage('user', ans || '(no response)');
      qa.push({question:q, answer:ans || ''});

      const ack = ans ? "Noted. Thank you." : "No clear response captured. Moving forward.";
      await speak(ack);
      await pushMessage('assistant', ack);

      qIndex++;
    }

    setProgress('COMPLETED');
    await speak(`Thank you, ${userName}. Interview completed. Generating report now.`);
    overlay.style.display = 'flex';
    setStatus('GENERATING REPORT...');

    try{
      await fetch(BASE + '/api/analyze.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({session_id: SESSION_ID, force_complete: true})
      });
      window.location.href = BASE + '/app/hr-report-rich.php?session_id=' + SESSION_ID;
    }catch(e){
      log('Analyze failed: ' + e.message);
      window.location.href = BASE + '/app/dashboard.php';
    }
  }

  async function startFlow(){
    if(running) return;
    running = true;
    state = 'RUNNING';
    setMic(true);
    setStatus('INITIALIZING...');

    const okPerm = await ensureMicPermission();
    if(!okPerm){
      alert('Microphone permission is blocked. Please allow mic access and reload.');
      stopFlow();
      return;
    }

    if(!initRecognizer()){
      alert('SpeechRecognition API not supported in this browser. Use latest Chrome/Edge.');
      stopFlow();
      return;
    }

    startListening();
    try{
      await runInterview();
    }catch(e){
      log('runInterview crash: ' + e.message);
      alert('Interview failed due to runtime error. Check debug panel.');
      stopFlow();
    }
  }

  function stopFlow(){
    running = false;
    state = 'IDLE';
    stopListening();
    window.speechSynthesis?.cancel();
    setMic(false);
    setStatus('TAP TO BEGIN AUDIT');
    if (!String(progressEl.textContent).includes('COMPLETED')) setProgress('READY');
  }

  micBtn.addEventListener('click', async () => {
    log('Mic button clicked. running=' + running);
    if(running) stopFlow();
    else await startFlow();
  });

  log('Page ready. SpeechRecognition=' + (!!SR));
})();
</script>
</body>
</html>