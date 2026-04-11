<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$sessionId = intval($data['session_id'] ?? 0);

if (!$sessionId) {
    echo json_encode(['error' => 'Invalid session ID']);
    exit;
}

$session = getSession($sessionId, $user['id']);
if (!$session) {
    echo json_encode(['error' => 'Session not found or unauthorized']);
    exit;
}

if (!function_exists('deleteSession')) {
    echo json_encode(['error' => 'Delete function not implemented yet']);
    exit;
}

$success = deleteSession($sessionId, $user['id']);

if ($success) {
    // Return updated counts
    $sessions = getUserSessions($user['id']);
    $completed = count(array_filter($sessions, fn($s) => $s['status'] === 'completed'));
    $total = count($sessions);
    $rate = $total > 0 ? round(($completed / $total) * 100) : 0;
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'rate' => $rate
    ]);
} else {
    echo json_encode(['error' => 'Failed to delete session']);
}
