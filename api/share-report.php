<?php
// ==============================================
// api/share-report.php — Generate a shareable report link
// ==============================================
// Creates a unique share token for a session report

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/db.php';

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

// Verify session ownership and completion
$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session || $session['status'] !== 'completed') {
    echo json_encode(['error' => 'Session not found or not completed']);
    exit;
}

// Check if share token already exists
$db = getDB();
$stmt = $db->prepare('SELECT share_token FROM reports WHERE session_id = ?');
$stmt->execute([$sessionId]);
$report = $stmt->fetch();

if (!$report) {
    echo json_encode(['error' => 'Report not found']);
    exit;
}

if (!empty($report['share_token'])) {
    // Already has a share token
    $shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
              . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/app/shared-report.php?token=' . $report['share_token'];
    echo json_encode(['success' => true, 'share_url' => $shareUrl]);
    exit;
}

// Generate a unique share token
$token = bin2hex(random_bytes(16)); // 32-char hex string

$stmt = $db->prepare('UPDATE reports SET share_token = ? WHERE session_id = ?');
$stmt->execute([$token, $sessionId]);

$shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
          . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/app/shared-report.php?token=' . $token;

echo json_encode(['success' => true, 'share_url' => $shareUrl]);
