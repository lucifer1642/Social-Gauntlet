<?php
// ==============================================
// api/analyze.php — Generate vulnerability report (POST endpoint)
// ==============================================
// Bundles full transcript + behavioral metrics, sends to Gemini for analysis

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-manager.php';
require_once __DIR__ . '/../includes/personality-prompts.php';
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

$session = getSession($sessionId, $_SESSION['user_id']);
if (!$session || $session['status'] !== 'completed') {
    echo json_encode(['error' => 'Session not found or not completed']);
    exit;
}

// Check if report already exists
$existingReport = getReport($sessionId);
if ($existingReport) {
    echo json_encode(['success' => true, 'report_exists' => true]);
    exit;
}

// Get full transcript
$transcript = getFullTranscript($sessionId);
$topicDisplay = $session['custom_topic'] ?: $session['topic'];

// Build formatted transcript for analysis
$formattedTranscript = "TOPIC: {$topicDisplay}\n\n";
$currentRound = 0;
$personalityNames = [
    1 => 'The Micromanager Boss',
    2 => 'The Conspiracy Theorist Uncle', 
    3 => 'The Aggressive Investor',
    4 => 'The Passive-Aggressive Coworker',
    5 => 'The Emotional Guilt-Tripper'
];

foreach ($transcript as $msg) {
    if ($msg['round_number'] !== $currentRound) {
        $currentRound = $msg['round_number'];
        $pName = $personalityNames[$msg['personality_id']] ?? 'Unknown';
        $formattedTranscript .= "\n--- ROUND {$currentRound}: {$pName} ---\n\n";
    }
    $speaker = ($msg['role'] === 'user') ? 'USER' : 'AI_PERSONA';
    $formattedTranscript .= "[{$speaker}]: {$msg['content']}\n";
    if ($msg['role'] === 'user') {
        $formattedTranscript .= "(Response time: {$msg['response_time_ms']}ms, Length: {$msg['char_count']} chars)\n";
    }
    $formattedTranscript .= "\n";
}

// Calculate behavioral metrics
$metrics = calculateBehavioralMetrics($transcript);
$formattedTranscript .= "\n--- BEHAVIORAL DATA ---\n";
$formattedTranscript .= json_encode($metrics, JSON_PRETTY_PRINT);

// Analysis prompt — concise to avoid token truncation while requesting all chart data
$analysisPrompt = "You are a communication psychologist. Analyze the transcript and respond with ONLY valid JSON (no markdown, no explanation). Keep all text fields under 100 words each. Be specific but concise. Reference actual quotes. Every audit must be unique — no two analyses should read the same.

Topic: {$topicDisplay}

{
    \"strongest_under\": \"Which persona they handled best — cite 1 specific quote (1 short paragraph)\",
    \"biggest_vulnerability\": \"Their primary weakness — cite their weakest moment (1 short paragraph)\",
    \"blind_spot\": \"What emotional cue or subtext they missed — specify round and AI quote\",
    \"pattern_summary\": \"3 recurring linguistic patterns with exact phrases quoted\",
    \"emotional_tripwire\": \"The exact AI sentence that triggered them and their response\",
    \"recommendations\": [\"directive 1\",\"directive 2\",\"directive 3\",\"directive 4\"],
    \"round_analyses\": [
        {\"round\":1,\"personality\":\"Boss\",\"performance\":\"brief analysis\",\"key_moment\":\"quote\"},
        {\"round\":2,\"personality\":\"Uncle\",\"performance\":\"brief analysis\",\"key_moment\":\"quote\"},
        {\"round\":3,\"personality\":\"Investor\",\"performance\":\"brief analysis\",\"key_moment\":\"quote\"},
        {\"round\":4,\"personality\":\"Coworker\",\"performance\":\"brief analysis\",\"key_moment\":\"quote\"},
        {\"round\":5,\"personality\":\"Guilt-Tripper\",\"performance\":\"brief analysis\",\"key_moment\":\"quote\"}
    ],
    \"language_patterns\": [{\"phrase\":\"exact phrase used\",\"count\":0,\"context\":\"what it reveals\"}],
    \"chart_data\": {
        \"persona_resistance\":{\"boss\":0,\"uncle\":0,\"investor\":0,\"coworker\":0,\"guilt_tripper\":0},
        \"stress_resistance_index\":0,
        \"psych_profile\":{\"defensiveness\":0,\"adaptability\":0,\"anxiety\":0,\"logic_focus\":0,\"empathy\":0},
        \"linguistic_profile\":{\"tone_control\":0,\"complexity\":0,\"assertiveness\":0,\"empathy\":0,\"formality\":0},
        \"reaction_consistency\":{\"boss\":0,\"uncle\":0,\"investor\":0,\"coworker\":0,\"guilt_tripper\":0},
        \"blind_spot_radar\":{\"awareness\":0,\"impact\":0,\"recurrence\":0},
        \"trigger_radar\":{\"volatility\":0,\"frequency\":0,\"severity\":0}
    }
}
All scores 0-100 based on actual conversation performance. Fill ALL numeric fields.";

// Send to Gemini Pro for analysis
$aiResponse = sendAnalysisToGemini($analysisPrompt, $formattedTranscript);

if ($aiResponse === false) {
    echo json_encode(['error' => 'Analysis service unavailable. Please try again.']);
    exit;
}

// Parse the JSON response — robust extraction
$cleanResponse = $aiResponse;
// Strip markdown code fences
$cleanResponse = preg_replace('/```(?:json)?\s*/i', '', $cleanResponse);

// Find the outermost JSON object: first '{' to last '}'
$firstBrace = strpos($cleanResponse, '{');
$lastBrace = strrpos($cleanResponse, '}');
if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
    $cleanResponse = substr($cleanResponse, $firstBrace, $lastBrace - $firstBrace + 1);
}

$analysis = json_decode($cleanResponse, true);

if (!$analysis) {
    // If JSON parsing fails, store raw response
    $analysis = [
        'strongest_under' => 'Analysis could not be parsed. Raw response saved.',
        'biggest_vulnerability' => $aiResponse,
        'blind_spot' => '',
        'pattern_summary' => '',
        'emotional_tripwire' => '',
        'recommendations' => ['Complete another session for better analysis.'],
        'round_analyses' => [],
        'language_patterns' => []
    ];
}

// Save report
saveReport(
    $sessionId,
    json_encode($analysis),
    $analysis['strongest_under'] ?? '',
    $analysis['biggest_vulnerability'] ?? '',
    $analysis['blind_spot'] ?? '',
    $analysis['pattern_summary'] ?? '',
    $analysis['emotional_tripwire'] ?? '',
    json_encode($analysis['recommendations'] ?? [])
);

echo json_encode(['success' => true]);

/**
 * Calculate behavioral metrics from transcript data
 */
function calculateBehavioralMetrics($transcript) {
    $metrics = [
        'total_user_messages' => 0,
        'total_ai_messages' => 0,
        'avg_user_msg_length' => 0,
        'avg_response_time_ms' => 0,
        'msg_length_by_round' => [],
        'response_time_by_round' => [],
        'length_increase_after_pushback' => 0
    ];
    
    $userLengths = [];
    $responseTimes = [];
    $roundLengths = [];
    
    foreach ($transcript as $msg) {
        if ($msg['role'] === 'user') {
            $metrics['total_user_messages']++;
            $userLengths[] = $msg['char_count'];
            if ($msg['response_time_ms']) {
                $responseTimes[] = $msg['response_time_ms'];
            }
            $round = $msg['round_number'];
            if (!isset($roundLengths[$round])) $roundLengths[$round] = [];
            $roundLengths[$round][] = $msg['char_count'];
        } else {
            $metrics['total_ai_messages']++;
        }
    }
    
    if (!empty($userLengths)) {
        $metrics['avg_user_msg_length'] = round(array_sum($userLengths) / count($userLengths));
    }
    if (!empty($responseTimes)) {
        $metrics['avg_response_time_ms'] = round(array_sum($responseTimes) / count($responseTimes));
    }
    
    foreach ($roundLengths as $round => $lengths) {
        $metrics['msg_length_by_round'][$round] = round(array_sum($lengths) / count($lengths));
    }
    
    // Detect defensiveness spiral (message length increase within rounds)
    foreach ($roundLengths as $round => $lengths) {
        if (count($lengths) >= 2) {
            $first = $lengths[0];
            $last = end($lengths);
            if ($first > 0) {
                $increase = (($last - $first) / $first) * 100;
                $metrics['response_time_by_round'][$round] = round($increase) . '% length change';
            }
        }
    }
    
    return $metrics;
}
