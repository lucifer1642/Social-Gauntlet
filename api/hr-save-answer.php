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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = intval($input['session_id'] ?? 0);
$answer = trim($input['answer'] ?? '');

if (!$sessionId || $answer === '') {
    echo json_encode(['error' => 'Missing session_id or answer']);
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

$messages = getRoundMessages($round['id']);
$responseTimeMs = null;
if (!empty($messages)) {
    $lastMsg = end($messages);
    $lastTime = strtotime($lastMsg['created_at']);
    if ($lastTime !== false) {
        $responseTimeMs = (time() - $lastTime) * 1000;
    }
}

saveMessage($round['id'], 'user', $answer, $responseTimeMs);

$followUp = '';
$currentRoundMsgs = getRoundMessages($round['id']);
$questionText = '';
foreach ($currentRoundMsgs as $msg) {
    if ($msg['role'] === 'assistant') {
        $questionText = $msg['content'];
        break;
    }
}

$followUpPrompt = "You are a professional HR interviewer. Based on the candidate answer, provide one concise follow-up response (max 1 sentence) that acknowledges specifics and lightly probes depth. Do not ask the next main question yet.";
$followUpInput = [[
    'role' => 'user',
    'content' => "Question asked: {$questionText}\nCandidate answer: {$answer}\nReturn one short follow-up response only."
]];
$aiFollowUp = sendToGemini($followUpPrompt, $followUpInput);
if ($aiFollowUp !== false) {
    $followUp = trim(preg_replace('/\s+/', ' ', $aiFollowUp));
    if ($followUp !== '') {
        saveMessage($round['id'], 'assistant', $followUp);
    }
}

$result = completeRound($sessionId, $round['id']);

echo json_encode([
    'success' => true,
    'follow_up' => $followUp,
    'session_completed' => isset($result['completed']),
    'next_round' => $result['next_round'] ?? null
]);
