<?php
// ==============================================
// api/chat.php — Handle chat messages (POST endpoint)
// ==============================================
// Receives user message, sends full conversation to Gemini, returns AI response

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
require_once __DIR__ . '/../includes/context-extractor.php';
require_once __DIR__ . '/../includes/gemini-client.php';

// Must be logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = intval($input['session_id'] ?? 0);
$message = trim($input['message'] ?? '');
$isOpener = !empty($input['get_opener']); // If true, we want the AI to open the round

// Validate
if (!$sessionId) {
    echo json_encode(['error' => 'Missing session_id']);
    exit;
}

// Verify session ownership
$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session) {
    echo json_encode(['error' => 'Session not found']);
    exit;
}
if (($session['mode'] ?? 'standard') === 'hr') {
    echo json_encode(['error' => 'HR module uses dedicated voice APIs only']);
    exit;
}

if ($session['status'] !== 'active') {
    echo json_encode(['error' => 'Session is not active']);
    exit;
}

// Get current round
$round = getCurrentRound($sessionId);
if (!$round) {
    echo json_encode(['error' => 'No active round found']);
    exit;
}

// Start round if pending
if ($round['status'] === 'pending') {
    startRound($round['id']);
}

// Get topic context for the AI prompt
$topicContext = getTopicContextForAI($session['topic'], $session['custom_topic']);

// Get personality info
$personalityId = $round['personality_id'];
$roundNumber = $round['round_number'];

// Build cross-round context (for rounds 2-5)
$priorContext = extractUserPositions($sessionId, $roundNumber);

// Build system prompt
$systemPrompt = getPersonalityPrompt($personalityId, $topicContext, $priorContext);

// Handle AI opener (first message of a round)
if ($isOpener) {
    $existingMessages = getRoundMessages($round['id']);
    if (!empty($existingMessages)) {
        // Round already has messages, return them
        echo json_encode([
            'already_started' => true,
            'messages' => $existingMessages,
            'round' => $roundNumber,
            'personality_id' => $personalityId,
            'personality_name' => getPersonalityName($personalityId),
            'personality_emoji' => getPersonalityEmoji($personalityId),
            'personality_avatar' => getPersonalityAvatar($personalityId),
            'personality_class' => getPersonalityClass($personalityId),
            'exchange_count' => $round['exchange_count']
        ]);
        exit;
    }
    
    // Generate AI opener
    $openerPrompt = getOpeningPrompt($personalityId, $topicContext);
    $openerHistory = [
        ['role' => 'user', 'content' => $openerPrompt]
    ];
    
    $aiResponse = sendToGemini($systemPrompt, $openerHistory);
    
    if ($aiResponse === false) {
        echo json_encode(['error' => 'AI service unavailable. Please try again.']);
        exit;
    }
    
    // Save the AI opener (not the meta-prompt)
    saveMessage($round['id'], 'assistant', $aiResponse);
    
    echo json_encode([
        'success' => true,
        'ai_message' => $aiResponse,
        'round' => $roundNumber,
        'personality_id' => $personalityId,
        'personality_name' => getPersonalityName($personalityId),
        'personality_emoji' => getPersonalityEmoji($personalityId),
        'personality_avatar' => getPersonalityAvatar($personalityId),
        'personality_class' => getPersonalityClass($personalityId),
        'exchange_count' => 0,
        'is_opener' => true
    ]);
    exit;
}

// Regular message flow
if (mb_strlen($message) < 10) {
    echo json_encode(['error' => 'Message must be at least 10 characters.']);
    exit;
}

// Calculate response time (time since last message)
$existingMessages = getRoundMessages($round['id']);
$responseTimeMs = null;
if (!empty($existingMessages)) {
    $lastMsg = end($existingMessages);
    $lastTime = strtotime($lastMsg['created_at']);
    $responseTimeMs = (time() - $lastTime) * 1000;
}

// Save user message
saveMessage($round['id'], 'user', $message, $responseTimeMs);

// Build full conversation history for this round (the memory layer)
$allMessages = getRoundMessages($round['id']);
$conversationHistory = [];
foreach ($allMessages as $msg) {
    $conversationHistory[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

// Send to Gemini with FULL conversation history
$aiResponse = sendToGemini($systemPrompt, $conversationHistory);

    if ($aiResponse === false) {
        $errorMsg = 'AI service unavailable.';
        if (ini_get('display_errors')) {
            $errorMsg .= ' Check PHP error logs for Gemini API connectivity issues.';
        }
        echo json_encode(['error' => $errorMsg . ' Please try again.']);
        exit;
    }

// Save AI response
saveMessage($round['id'], 'assistant', $aiResponse);

// Refresh round data to get updated exchange count
$round = getRound($round['id']);
$exchangeCount = $round['exchange_count'];

// Check if round should end (4-6 exchanges)
$canEndRound = ($exchangeCount >= MIN_EXCHANGES_PER_ROUND);
$mustEndRound = ($exchangeCount >= MAX_EXCHANGES_PER_ROUND);
$isLastRound = ($roundNumber >= TOTAL_ROUNDS);

echo json_encode([
    'success' => true,
    'ai_message' => $aiResponse,
    'round' => $roundNumber,
    'exchange_count' => $exchangeCount,
    'can_end_round' => $canEndRound,
    'must_end_round' => $mustEndRound,
    'is_last_round' => $isLastRound,
    'personality_id' => $personalityId,
    'personality_name' => getPersonalityName($personalityId),
    'char_count' => mb_strlen($message),
    'response_time_ms' => $responseTimeMs
]);
