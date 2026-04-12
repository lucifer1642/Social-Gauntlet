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
            <p class="hero-subtitle">Most people think they communicate well — until they're tested.<br>5 AI personalities. 5 back-to-back conversations. One brutally honest vulnerability report.</p>
            <div class="hero-ctas">
                <?php if (isLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/app/select-topic.php" class="btn btn-primary btn-lg">Enter the Gauntlet →</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-lg" id="heroStart">Enter the Gauntlet →</a>
                    <a href="#how-it-works" class="btn btn-ghost btn-lg">See How It Works</a>
                <?php endif; ?>
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
                    <h4>Pick a Scenario</h4>
                    <p class="text-secondary">Choose what you want to defend — a career change, business idea, personal boundary, or your own custom topic.</p>
                </div>
                <div class="step-card glass reveal-up stagger-2">
                    <div class="step-number">2</div>
                    <h4>Face the Gauntlet</h4>
                    <p class="text-secondary">5 back-to-back AI conversations. Each personality probes a different vulnerability. 4-6 exchanges each. No breaks.</p>
                </div>
                <div class="step-card glass reveal-up stagger-3">
                    <div class="step-number">3</div>
                    <h4>Get Your Report</h4>
                    <p class="text-secondary">A personalized vulnerability map with annotated transcripts, behavioral metrics, and brutally honest insights.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ====== THE 5 PERSONALITIES ====== -->
    <section class="personalities-section" id="personalities" style="padding: 120px 0; background: var(--bg-secondary);">
        <div class="container">
            <div class="text-center mb-7">
                <div class="badge-label mb-3">YOUR OPPONENTS</div>
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

    <!-- Scenarios section moved to dedicated Gauntlet entry screen -->

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

    <!-- Scenario script removed -->

    // Start Gauntlet Sequence (replaces the blank fade-out)
    document.querySelectorAll('.hero-ctas .btn-primary').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.tagName === 'A' && this.href) {
                e.preventDefault();
                const dest = this.href;
                
                // 1. Blur the hero background instead of blacking out
                const hero = document.querySelector('.hero');
                hero.style.transition = 'filter 1.5s ease-in-out';
                hero.style.filter = 'blur(15px) brightness(0.25) grayscale(0.5)';
                
                // 2. Inject loading UI
                const overlay = document.createElement('div');
                overlay.style.position = 'fixed';
                overlay.style.inset = '0';
                overlay.style.zIndex = '9999';
                overlay.style.display = 'flex';
                overlay.style.flexDirection = 'column';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.style.opacity = '0';
                overlay.style.transition = 'opacity 1s ease';
                
                // Keep the styles inline to avoid adding new CSS chunks
                const spinnerCSS = `
                    @keyframes spinFast { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                    @keyframes pulseGlow { 0% { box-shadow: 0 0 10px rgba(14, 165, 233, 0.2); } 100% { box-shadow: 0 0 30px rgba(14, 165, 233, 0.8); } }
                `;
                const styleOpt = document.createElement('style');
                styleOpt.innerHTML = spinnerCSS;
                document.head.appendChild(styleOpt);

                overlay.innerHTML = `
                    <div style="width: 70px; height: 70px; border: 3px solid rgba(14, 165, 233, 0.1); border-top-color: #0ea5e9; border-radius: 50%; margin-bottom: 30px; animation: spinFast 0.8s linear infinite, pulseGlow 1.5s ease-in-out infinite alternate;"></div>
                    <div id="loadingStatus" style="font-family: var(--font-mono); color: #0ea5e9; font-size: 14px; letter-spacing: 0.1em; text-transform: uppercase; transition: opacity 0.4s ease; text-shadow: 0 0 10px rgba(14, 165, 233, 0.5);">Initiating Neural Handshake...</div>
                `;
                document.body.appendChild(overlay);
                
                // 3. Play sequence
                setTimeout(() => { overlay.style.opacity = '1'; }, 100);
                
                const msg = document.getElementById('loadingStatus');
                setTimeout(() => { msg.style.opacity = '0'; }, 1800);
                setTimeout(() => { msg.textContent = 'Calibrating psychological parameters...'; msg.style.opacity = '1'; }, 2200);
                setTimeout(() => { msg.style.opacity = '0'; }, 3600);
                setTimeout(() => { 
                    msg.style.color = '#fff'; 
                    msg.style.textShadow = '0 0 15px rgba(255, 255, 255, 0.8)';
                    msg.textContent = 'Let\'s test your resilience.'; 
                    msg.style.opacity = '1'; 
                }, 4000);
                
                // 4. Final fade to black and redirect
                setTimeout(() => {
                    document.body.style.transition = 'opacity 0.6s ease';
                    document.body.style.opacity = '0';
                    setTimeout(() => window.location.href = dest, 600);
                }, 5500);
            }
        });
    });

    // Fallback for other primary buttons
    document.querySelectorAll('.btn-primary:not(.hero-ctas .btn-primary)').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.tagName === 'A' && this.href) {
                e.preventDefault();
                const href = this.href;
                document.body.style.transition = 'opacity 0.8s ease';
                document.body.style.opacity = '0';
                setTimeout(() => window.location.href = href, 800);
            }
        });
    });
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>
