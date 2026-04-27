<?php
// ==============================================
// api/hr-push-message.php — Save voice transcript message
// ==============================================
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';

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
$role = $input['role'] ?? ''; // 'user' or 'assistant'
$content = trim($input['content'] ?? '');

if (!$sessionId || !$role || $content === '') {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session || ($session['mode'] ?? 'standard') !== 'hr') {
    echo json_encode(['error' => 'HR session not found']);
    exit;
}

// Get the current round (active or pending)
$round = getCurrentRound($sessionId);
if (!$round) {
    // If no current round, find the first pending round
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM rounds WHERE session_id = ? AND status = "pending" ORDER BY round_number ASC LIMIT 1');
    $stmt->execute([$sessionId]);
    $round = $stmt->fetch();
}

if (!$round) {
    echo json_encode(['error' => 'No active round available']);
    exit;
}

// Mark round as active if it was pending
if ($round['status'] === 'pending') {
    startRound($round['id']);
}

// Save the message
saveMessage($round['id'], $role, $content);

// For HR mode, we want to advance the round if the assistant asks a NEW question.
// However, since ElevenLabs is a continuous conversation, we can't easily detect "next question".
// A simple heuristic: if it's an assistant message and it contains a question mark or is long, 
// and we already have user messages in this round, maybe advance?
// Actually, let's keep it simple for now and just pile messages into rounds.
// The analyzer will handle the full transcript regardless.

echo json_encode(['success' => true, 'round_id' => $round['id']]);
