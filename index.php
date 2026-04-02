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
                    <a href="<?= BASE_URL ?>/app/dashboard.php" class="btn btn-primary btn-lg">Enter Dashboard →</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-lg" id="heroStart">Enter the Stress Test</a>
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
                <div class="step-card glass">
                    <div class="step-number">1</div>
                    <h4>Pick a Scenario</h4>
                    <p class="text-secondary">Choose what you want to defend — a career change, business idea, personal boundary, or your own custom topic.</p>
                </div>
                <div class="step-card glass">
                    <div class="step-number">2</div>
                    <h4>Face the Gauntlet</h4>
                    <p class="text-secondary">5 back-to-back AI conversations. Each personality probes a different vulnerability. 4-6 exchanges each. No breaks.</p>
                </div>
                <div class="step-card glass">
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
                <div class="persona-card glass" style="padding: 32px; border-left: 3px solid #a0a0a0;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/micromanager.png" alt="Micromanager" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #a0a0a0;">
                    <h4 style="margin-bottom: 8px;">The Micromanager Boss</h4>
                    <p class="text-secondary text-sm">Cold, sterile, and demanding. Tests your ability to handle nitpicking, authority, and extreme detail without losing your cool.</p>
                </div>
                <div class="persona-card glass" style="padding: 32px; border-left: 3px solid #d4a017;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/conspiracy.png" alt="Conspiracy Uncle" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #d4a017;">
                    <h4 style="margin-bottom: 8px;">The Conspiracy Uncle</h4>
                    <p class="text-secondary text-sm">Chaotic and wildly dismissive of facts. Tests your patience and ability to de-escalate absurd situations.</p>
                </div>
                <div class="persona-card glass" style="padding: 32px; border-left: 3px solid #EF4444;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/investor.png" alt="Aggressive Investor" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #EF4444;">
                    <h4 style="margin-bottom: 8px;">The Aggressive Investor</h4>
                    <p class="text-secondary text-sm">High-pressure, dominant, and impatient. Tests your confidence when challenged and interrupted aggressively.</p>
                </div>
                <div class="persona-card glass" style="padding: 32px; border-left: 3px solid #3B82F6;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/passive-aggressive.png" alt="Passive-Aggressive Coworker" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #3B82F6;">
                    <h4 style="margin-bottom: 8px;">The Passive-Aggressive Coworker</h4>
                    <p class="text-secondary text-sm">Friendly on the surface, but deeply undermining. Tests your ability to address hidden resentment.</p>
                </div>
                <div class="persona-card glass" style="padding: 32px; border-left: 3px solid #A855F7;">
                    <img src="<?= BASE_URL ?>/assets/img/avatars/guilt-tripper.png" alt="Emotional Guilt-Tripper" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px; object-fit: cover; border: 2px solid #A855F7;">
                    <h4 style="margin-bottom: 8px;">The Emotional Guilt-Tripper</h4>
                    <p class="text-secondary text-sm">Manipulative and highly emotional. Tests your boundaries against guilt and pressure.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ====== SCENARIO SELECTION ====== -->
    <section class="scenarios-section">
        <div class="container">
            <div class="text-center mb-7">
                <div class="badge-label mb-3">CHOOSE YOUR TRIAL</div>
                <h2>Pick Your Scenario</h2>
            </div>
            <div class="scenarios-grid">
                <?php foreach ($presetTopics as $t): ?>
                <div class="scenario-card glass" onclick="selectScenario('<?= htmlspecialchars($t['title']) ?>', this)">
                    <span class="scenario-icon"><?= $t['icon'] ?></span>
                    <h4><?= $t['title'] ?></h4>
                    <p class="text-secondary text-sm"><?= $t['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Start button that appears when selected -->
            <div id="quickStartBtn" class="text-center mt-5" style="display:none; transition: opacity 0.3s;">
                <?php if (isLoggedIn()): ?>
                    <button class="btn btn-primary btn-lg" onclick="startCustom()">Proceed to Trial ➔</button>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-lg">Proceed to Trial ➔</a>
                <?php endif; ?>
            </div>

            <div class="custom-scenario mt-7">
                <div class="custom-input-wrap glass">
                    <input type="text" id="customTopic" class="custom-input" placeholder="Or define your own scenario... (e.g., 'I want to explain why I'm switching careers to AI')">
                    <?php if (isLoggedIn()): ?>
                        <button class="btn btn-primary" onclick="startCustom()">Start Simulation</button>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary">Start Simulation</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script>
    function selectScenario(title, el) {
        document.getElementById('customTopic').value = title;
        
        // Highlight selection
        document.querySelectorAll('.scenario-card').forEach(c => c.style.borderColor = 'var(--border-subtle)');
        if(el) el.style.borderColor = 'var(--accent)';
        
        const btn = document.getElementById('quickStartBtn');
        btn.style.display = 'block';
        setTimeout(() => { btn.style.opacity = '1'; }, 50);
        
        btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function startCustom() {
        const topic = document.getElementById('customTopic').value.trim();
        if (!topic) return;
        window.location.href = '<?= BASE_URL ?>/app/select-topic.php?prefill=' + encodeURIComponent(topic);
    }

    document.querySelectorAll('.btn-primary').forEach(btn => {
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
