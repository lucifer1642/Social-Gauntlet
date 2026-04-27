<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/gemini-client.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'GET required']);
    exit;
}

$sessionId = intval($_GET['session_id'] ?? 0);
if (!$sessionId) {
    echo json_encode(['error' => 'Missing session_id']);
    exit;
}

$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session || ($session['mode'] ?? 'standard') !== 'hr') {
    echo json_encode(['error' => 'HR session not found']);
    exit;
}

if ($session['status'] !== 'active') {
    echo json_encode(['completed' => true]);
    exit;
}

$round = getCurrentRound($sessionId);
if (!$round) {
    echo json_encode(['completed' => true]);
    exit;
}

if ($round['status'] === 'pending') {
    startRound($round['id']);
}

$db = getDB();
$stmt = $db->prepare('SELECT question FROM hr_questions WHERE id = ?');
$stmt->execute([intval($round['hr_question_id'])]);
$row = $stmt->fetch();
if (!$row) {
    echo json_encode(['error' => 'Question not found']);
    exit;
}

$question = $row['question'];
$contextualQuestion = $question;

// Build context-aware phrasing using prior answers.
$priorAnswers = getPriorUserMessages($sessionId, intval($round['round_number']));
if (!empty($priorAnswers)) {
    $answersText = '';
    foreach ($priorAnswers as $a) {
        $answersText .= "Q{$a['round_number']} answer: " . $a['content'] . "\n";
    }

    $systemPrompt = "You are a strict HR interviewer. Rewrite the upcoming interview question so it is context-aware to earlier candidate answers while preserving the original intent. Output exactly one interview question. Keep it concise and professional.";
    $conversation = [[
        'role' => 'user',
        'content' => "Candidate name: " . ($session['candidate_name'] ?: 'Candidate') . "\n\nPrior answers:\n{$answersText}\nBase question:\n{$question}\n\nReturn only the adapted next question."
    ]];
    $aiQuestion = sendToGemini($systemPrompt, $conversation);
    if ($aiQuestion !== false) {
        $clean = trim(preg_replace('/\s+/', ' ', $aiQuestion));
        if ($clean !== '') {
            $contextualQuestion = $clean;
        }
    }
}

$existing = getRoundMessages($round['id']);
if (empty($existing)) {
    saveMessage($round['id'], 'assistant', $contextualQuestion);
}

$stmt = $db->prepare('SELECT COUNT(*) AS total FROM rounds WHERE session_id = ?');
$stmt->execute([$sessionId]);
$total = intval($stmt->fetch()['total'] ?? 0);

echo json_encode([
    'success' => true,
    'question_no' => intval($round['round_number']),
    'total_questions' => $total,
    'question' => $contextualQuestion
]);
