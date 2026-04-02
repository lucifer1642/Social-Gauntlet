<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
requireAuth();

$user = getCurrentUser();
$presetTopics = getPresetTopics();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = trim($_POST['topic'] ?? '');
    $customTopic = trim($_POST['custom_topic'] ?? '');
    if (empty($topic) && empty($customTopic)) {
        $error = 'Please select a topic or enter a custom one.';
    } else {
        $topicSlug = !empty($customTopic) ? 'custom' : $topic;
        $sessionId = createSession($user['id'], $topicSlug, $customTopic ?: null);
        header('Location: ' . BASE_URL . '/app/session.php?session_id=' . $sessionId);
        exit;
    }
}

$pageTitle = "Choose Your Scenario";
$extraCss = ['select-topic.css'];
include __DIR__ . '/../includes/header.php';
?>

<div class="topic-wrapper">
    <div class="container">
        <div class="text-center mb-7">
            <div class="badge-label mb-3">SCENARIO SELECTION</div>
            <h1>Pick Your <span class="text-accent">Scenario</span></h1>
            <p class="text-secondary mt-2">Choose a preset or describe your own high-stakes conversation.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="topicForm">
            <input type="hidden" name="topic" id="selectedTopic" value="">

            <div class="topics-grid">
                <?php foreach ($presetTopics as $t): ?>
                    <div class="topic-card glass" onclick="selectTopic('<?= $t['slug'] ?>', this)">
                        <span class="topic-icon"><?= $t['icon'] ?></span>
                        <h4><?= $t['title'] ?></h4>
                        <p class="text-secondary text-sm"><?= $t['desc'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="custom-section">
                <h3 class="mb-4">Or describe your own scenario</h3>
                <textarea name="custom_topic" class="form-input" placeholder="e.g., Explaining to my parents why I'm dropping out of college to start a business..." oninput="clearSelection()"><?= htmlspecialchars($_POST['custom_topic'] ?? ($_GET['prefill'] ?? '')) ?></textarea>
            </div>

            <div class="text-center mt-6">
                <button type="submit" class="btn btn-primary btn-lg" id="startBtn">Enter the Gauntlet →</button>
            </div>
        </form>
    </div>
</div>

<script>
    function selectTopic(slug, el) {
        document.querySelector('textarea[name="custom_topic"]').value = '';
        document.querySelectorAll('.topic-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('selectedTopic').value = slug;
        
        const btn = document.getElementById('startBtn');
        btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
function clearSelection() {
    document.querySelectorAll('.topic-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('selectedTopic').value = '';
}
document.getElementById('topicForm').addEventListener('submit', function() {
    const btn = document.getElementById('startBtn');
    btn.disabled = true; btn.textContent = 'Starting session...';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
