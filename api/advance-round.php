<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = intval($input['session_id'] ?? 0);
$roundId = intval($input['round_id'] ?? 0);

$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session) { echo json_encode(['error' => 'Invalid session']); exit; }

$result = completeRound($sessionId, $roundId);

echo json_encode($result);
