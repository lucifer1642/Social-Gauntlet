<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/personality-prompts.php';
$presetTopics = getPresetTopics();
$pageTitle = "Home";
$extraCss = ['landing.css'];
include __DIR__ . '/includes/header.php';
?>
    <!-- ====== HERO — Full viewport ====== -->
    <section class="hero" id="hero">
        <div class="hero-gradient"></div>
        <div class="hero-content">
            <div class="badge-label mb-5">⚡ AI-POWERED COMMUNICATION STRESS TEST</div>
            <h1 class="hero-title">How Do You Break<br>Under <span class="text-accent">Pressure?</span></h1>
            <p class="hero-subtitle">Most people think they communicate well — until they're tested.<br>Choose your arena: face 5 AI personalities in text, or survive a live voice HR interrogation.</p>
            <div class="hero-ctas">
                <a href="#choose-module" class="btn btn-primary btn-lg">Choose Your Arena →</a>
                <a href="#how-it-works" class="btn btn-ghost btn-lg">See How It Works</a>
            </div>
        </div>
    </section>

    <!-- ====== CHOOSE YOUR MODULE ====== -->
    <section class="modules-section" id="choose-module">
        <div class="container">
            <div class="text-center mb-7">
                <div class="badge-label mb-3">SELECT YOUR TRIAL</div>
                <h2>Two Arenas. One Goal: <span class="text-accent">Break You.</span></h2>
                <p class="text-secondary mt-2">Each module tests a different dimension of your communication ability.</p>
            </div>

            <div class="modules-grid">
                <!-- MODULE 1: Text Chat Gauntlet -->
                <div class="module-card module-chat glass reveal-scale stagger-1" id="moduleChat">
                    <div class="module-glow module-glow-chat"></div>
                    <div class="module-badge">TEXT</div>
                    <div class="module-icon">⌨️</div>
                    <h3 class="module-title">The Social Gauntlet</h3>
                    <p class="module-desc">5 back-to-back AI personalities probe your logic, patience, and emotional resilience through text. 4-6 exchanges each. No breaks. One brutally honest vulnerability report.</p>
                    
                    <div class="module-specs">
                        <div class="spec">
                            <span class="spec-val">5</span>
                            <span class="spec-lbl">Personalities</span>
                        </div>
                        <div class="spec">
                            <span class="spec-val">20+</span>
                            <span class="spec-lbl">Exchanges</span>
                        </div>
                        <div class="spec">
                            <span class="spec-val">TEXT</span>
                            <span class="spec-lbl">Interface</span>
                        </div>
                    </div>

                    <div class="module-features">
                        <div class="feature-tag">📝 Written Responses</div>
                        <div class="feature-tag">🧠 Psych Profiling</div>
                        <div class="feature-tag">📊 Vulnerability Map</div>
                    </div>

                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>/app/select-topic.php" class="btn btn-primary btn-lg module-cta">Enter the Gauntlet →</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-lg module-cta">Enter the Gauntlet →</a>
                    <?php endif; ?>
                </div>

                <!-- MODULE 2: HR Voice Interview -->
                <div class="module-card module-voice glass reveal-scale stagger-2" id="moduleVoice">
                    <div class="module-glow module-glow-voice"></div>
                    <div class="module-badge module-badge-voice">VOICE LIVE</div>
                    <div class="module-icon">🎙️</div>
                    <h3 class="module-title">HR Behavioral Audit</h3>
                    <p class="module-desc">A stern AI Recruiter conducts a real-time voice interview. 7-8 behavioral questions. Your mic is live. Every pause, hesitation, and answer is analyzed for an executive performance report.</p>
                    
                    <div class="module-specs">
                        <div class="spec">
                            <span class="spec-val spec-val-voice">8</span>
                            <span class="spec-lbl">Questions</span>
                        </div>
                        <div class="spec">
                            <span class="spec-val spec-val-voice">LIVE</span>
                            <span class="spec-lbl">Voice Engine</span>
                        </div>
                        <div class="spec">
                            <span class="spec-val spec-val-voice">AI</span>
                            <span class="spec-lbl">Recruiter</span>
                        </div>
                    </div>

                    <div class="module-features">
                        <div class="feature-tag feature-tag-voice">🎤 Real-Time Voice</div>
                        <div class="feature-tag feature-tag-voice">💼 HR Simulation</div>
                        <div class="feature-tag feature-tag-voice">📋 Executive Report</div>
                    </div>

                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>/app/hr-init.php" class="btn btn-accent btn-lg module-cta">Launch HR Audit →</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-accent btn-lg module-cta">Launch HR Audit →</a>
                    <?php endif; ?>

                    <!-- Live pulse indicator -->
                    <div class="voice-pulse-row">
                        <span class="voice-dot"></span>
                        <span class="voice-label">Gemini Live — Powered by Google</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====== HOW IT WORKS ====== -->
    <section class="how-section" id="how-it-works">
        <div class="container">
            <div class="text-center mb-7">
                <div class="badge-label mb-3">THE PROCESS</div>
                <h2>Three Steps. No Hiding.</h2>
            </div>
            <div class="steps-grid">
                <div class="step-card glass reveal-up stagger-1">
                    <div class="step-number">1</div>
                    <h4>Pick Your Arena</h4>
                    <p class="text-secondary">Choose the <strong>Text Gauntlet</strong> to defend your ideas against 5 personalities, or the <strong>HR Voice Audit</strong> for a live behavioral interview.</p>
                </div>
                <div class="step-card glass reveal-up stagger-2">
                    <div class="step-number">2</div>
                    <h4>Face the Pressure</h4>
                    <p class="text-secondary">In text mode: 5 back-to-back AI conversations, each probing a different vulnerability. In voice mode: a stern AI recruiter interrogates you live.</p>
                </div>
                <div class="step-card glass reveal-up stagger-3">
                    <div class="step-number">3</div>
                    <h4>Get Your Report</h4>
                    <p class="text-secondary">A personalized audit with behavioral metrics, vulnerability maps, competency radar charts, and brutally honest insights.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ====== THE 5 PERSONALITIES (Text Gauntlet) ====== -->
    <section class="personalities-section" id="personalities" style="padding: 120px 0; background: var(--bg-secondary);">
        <div class="container">
            <div class="text-center mb-7">
                <div class="badge-label mb-3">TEXT GAUNTLET — YOUR OPPONENTS</div>
                <h2>The 5 Personalities You Will Face</h2>
                <p class="text-secondary mt-2">Each one probes a different psychological vulnerability.</p>
            </div>
            <div class="personas-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                <div class="persona-card glass reveal-scale stagger-1" style="padding: 32px; border-left: 3px solid #a0a0a0;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/micromanager.png" alt="Micromanager" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #a0a0a0;">
                    <h4 style="margin-bottom: 8px;">The Micromanager Boss</h4>
                    <p class="text-secondary text-sm">Cold, sterile, and demanding. Tests your ability to handle nitpicking, authority, and extreme detail without losing your cool.</p>
                </div>
                <div class="persona-card glass reveal-scale stagger-2" style="padding: 32px; border-left: 3px solid #d4a017;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/conspiracy.png" alt="Conspiracy Uncle" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #d4a017;">
                    <h4 style="margin-bottom: 8px;">The Conspiracy Uncle</h4>
                    <p class="text-secondary text-sm">Chaotic and wildly dismissive of facts. Tests your patience and ability to de-escalate absurd situations.</p>
                </div>
                <div class="persona-card glass reveal-scale stagger-3" style="padding: 32px; border-left: 3px solid #EF4444;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/investor.png" alt="Aggressive Investor" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #EF4444;">
                    <h4 style="margin-bottom: 8px;">The Aggressive Investor</h4>
                    <p class="text-secondary text-sm">High-pressure, dominant, and impatient. Tests your confidence when challenged and interrupted aggressively.</p>
                </div>
                <div class="persona-card glass reveal-scale stagger-4" style="padding: 32px; border-left: 3px solid #3B82F6;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/passive-aggressive.png" alt="Passive-Aggressive Coworker" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #3B82F6;">
                    <h4 style="margin-bottom: 8px;">The Passive-Aggressive Coworker</h4>
                    <p class="text-secondary text-sm">Friendly on the surface, but deeply undermining. Tests your ability to address hidden resentment.</p>
                </div>
                <div class="persona-card glass reveal-scale stagger-5" style="padding: 32px; border-left: 3px solid #A855F7;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/guilt-tripper.png" alt="Emotional Guilt-Tripper" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #A855F7;">
                    <h4 style="margin-bottom: 8px;">The Emotional Guilt-Tripper</h4>
                    <p class="text-secondary text-sm">Manipulative and highly emotional. Tests your boundaries against guilt and pressure.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ====== HR VOICE MODULE SPOTLIGHT ====== -->
    <section class="hr-spotlight-section" id="hr-spotlight">
        <div class="container">
            <div class="text-center mb-7">
                <div class="badge-label badge-label-voice mb-3">🎙️ VOICE MODULE — POWERED BY GEMINI LIVE</div>
                <h2>The HR Behavioral Audit</h2>
                <p class="text-secondary mt-2">A standalone voice-first professional interview simulation.</p>
            </div>
            <div class="hr-features-grid">
                <div class="hr-feature-card glass reveal-up stagger-1">
                    <div class="hr-feat-icon">🗣️</div>
                    <h4>Real-Time Voice</h4>
                    <p class="text-secondary text-sm">Speak naturally. The AI listens, interrupts, and challenges — just like a real HR recruiter would.</p>
                </div>
                <div class="hr-feature-card glass reveal-up stagger-2">
                    <div class="hr-feat-icon">📋</div>
                    <h4>52 Interview Questions</h4>
                    <p class="text-secondary text-sm">Randomly selected from a bank of professional HR questions with evaluation guidelines for each.</p>
                </div>
                <div class="hr-feature-card glass reveal-up stagger-3">
                    <div class="hr-feat-icon">📊</div>
                    <h4>Executive Report</h4>
                    <p class="text-secondary text-sm">Competency radar, vocal stability timeline, professional strength analysis, and actionable recommendations.</p>
                </div>
                <div class="hr-feature-card glass reveal-up stagger-4">
                    <div class="hr-feat-icon">🧠</div>
                    <h4>Behavioral Profiling</h4>
                    <p class="text-secondary text-sm">Logic, resilience, professionalism, empathy, and adaptability scored on a 0-100 scale.</p>
                </div>
            </div>
            <div class="text-center mt-9">
                <?php if (isLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/app/hr-init.php" class="btn btn-accent btn-lg">Launch HR Voice Audit →</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-accent btn-lg">Sign Up to Begin →</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
    // Intersection Observer for scroll animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('reveal-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('.reveal-up, .reveal-scale').forEach(el => observer.observe(el));

    // Fix BFCache visibility bug & replay title animation
    window.addEventListener('pageshow', function(e) {
        if (e.persisted || document.body.style.opacity === '0') {
            document.body.style.opacity = '1';
            
            const title = document.querySelector('.hero-title');
            if (title) {
                title.style.animation = 'none';
                title.offsetHeight; /* trigger reflow */
                title.style.animation = null; 
            }
        }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Cinematic transition for module CTAs
    document.querySelectorAll('.module-cta, .hr-spotlight-section .btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.tagName === 'A' && this.href && !this.href.startsWith('#')) {
                e.preventDefault();
                const dest = this.href;
                const isVoice = this.closest('.module-voice') || this.closest('.hr-spotlight-section');
                
                // Blur background
                const sections = document.querySelectorAll('section');
                sections.forEach(s => {
                    s.style.transition = 'filter 1.5s ease-in-out';
                    s.style.filter = 'blur(15px) brightness(0.25) grayscale(0.5)';
                });
                
                // Inject loading overlay
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:0;transition:opacity 1s ease;';
                
                const style = document.createElement('style');
                style.innerHTML = `@keyframes spinFast{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}@keyframes pulseGlow{0%{box-shadow:0 0 10px rgba(14,165,233,0.2)}100%{box-shadow:0 0 30px rgba(14,165,233,0.8)}}`;
                document.head.appendChild(style);

                const accentColor = isVoice ? '20, 184, 166' : '14, 165, 233';
                const loadMsg = isVoice ? 'Initializing Neural Voice Link...' : 'Initiating Neural Handshake...';
                const midMsg = isVoice ? 'Calibrating vocal analysis engine...' : 'Calibrating psychological parameters...';
                const finalMsg = isVoice ? 'Prepare to speak.' : "Let's test your resilience.";

                overlay.innerHTML = `
                    <div style="width:70px;height:70px;border:3px solid rgba(${accentColor},0.1);border-top-color:rgb(${accentColor});border-radius:50%;margin-bottom:30px;animation:spinFast 0.8s linear infinite,pulseGlow 1.5s ease-in-out infinite alternate;"></div>
                    <div id="loadingStatus" style="font-family:var(--font-mono);color:rgb(${accentColor});font-size:14px;letter-spacing:0.1em;text-transform:uppercase;transition:opacity 0.4s ease;text-shadow:0 0 10px rgba(${accentColor},0.5);">${loadMsg}</div>
                `;
                document.body.appendChild(overlay);
                
                setTimeout(() => { overlay.style.opacity = '1'; }, 100);
                const msg = document.getElementById('loadingStatus');
                setTimeout(() => { msg.style.opacity = '0'; }, 1800);
                setTimeout(() => { msg.textContent = midMsg; msg.style.opacity = '1'; }, 2200);
                setTimeout(() => { msg.style.opacity = '0'; }, 3600);
                setTimeout(() => { 
                    msg.style.color = '#fff'; 
                    msg.style.textShadow = '0 0 15px rgba(255,255,255,0.8)';
                    msg.textContent = finalMsg; 
                    msg.style.opacity = '1'; 
                }, 4000);
                
                setTimeout(() => {
                    document.body.style.transition = 'opacity 0.6s ease';
                    document.body.style.opacity = '0';
                    setTimeout(() => window.location.href = dest, 600);
                }, 5500);
            }
        });
    });

    // Hero CTA fallback for logged-in users direct nav
    document.querySelectorAll('.hero-ctas .btn-primary').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#choose-module') return; // let smooth scroll handle
        });
    });
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>
