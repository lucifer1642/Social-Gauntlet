<?php
// ==============================================
// api/end-round.php — End current round (POST endpoint)
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

$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session || $session['status'] !== 'active') {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

$round = getCurrentRound($sessionId);
if (!$round || $round['exchange_count'] < MIN_EXCHANGES_PER_ROUND) {
    echo json_encode(['error' => 'Round not ready to end']);
    exit;
}

$result = completeRound($sessionId, $round['id']);

echo json_encode([
    'success' => true,
    'completed_round' => $round['round_number'],
    'session_completed' => isset($result['completed']),
    'next_round' => $result['next_round'] ?? null
]);
