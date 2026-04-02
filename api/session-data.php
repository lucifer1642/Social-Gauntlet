<?php
// ==============================================
// api/session-data.php — Get session state (GET endpoint)
// ==============================================

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$sessionId = intval($_GET['session_id'] ?? 0);
if (!$sessionId) {
    echo json_encode(['error' => 'Missing session_id']);
    exit;
}

$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session) {
    echo json_encode(['error' => 'Session not found']);
    exit;
}

$round = getCurrentRound($sessionId);
$messages = $round ? getRoundMessages($round['id']) : [];

$response = [
    'session' => $session,
    'current_round' => $round,
    'messages' => $messages
];

if ($round) {
    $response['personality_name'] = getPersonalityName($round['personality_id']);
    $response['personality_emoji'] = getPersonalityEmoji($round['personality_id']);
    $response['personality_class'] = getPersonalityClass($round['personality_id']);
}

echo json_encode($response);
