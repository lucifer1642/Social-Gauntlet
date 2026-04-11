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

// Analysis prompt
$analysisPrompt = "You are a communication psychologist performing a high-fidelity behavioral audit. You have been provided with a transcript of 5 high-pressure rounds where the subject (USER) interacted with distinct difficult personalities.

Your goal is to provide a uniquely tailored analysis. DO NOT give generic advice. You MUST:
1. Reference specific quotes from the subject in each round.
2. Analyze how their language evolved between Round 1 (Boss) and Round 5 (Guilt-Tripper).
3. Identify a 'Linguistic Fingerprint' — unique phrases or verbal tics they used only when stressed.
4. Calculate and mention specific percentage shifts in response volume or velocity.
5. Provide actionable strategic directives that are specific to the topic discussed ({$topicDisplay}).

You MUST respond in EXACTLY this JSON format and nothing else:
{
    \"strongest_under\": \"One detailed paragraph describing the exact persona or type of pressure the user handled best. Quote a specific exchange where they showed strength.\",
    \"biggest_vulnerability\": \"One detailed paragraph identifying their primary failure point. Use metrics (e.g., 'Response length increased by 40% when challenged') and quote their weakest moment.\",
    \"blind_spot\": \"One paragraph about a subtext or emotional cue they completely missed in the AI's dialogue. Be specific about which round and which quote they misread.\",
    \"pattern_summary\": \"Analyze recurring linguistic patterns. List 3 specific phrases they used repeatedly and explain the psychological underlying cause for each.\",
    \"emotional_tripwire\": \"Identify the exact moment/sentence from an AI persona that caused the subject to lose composure or clarity. Explain why that specific trigger worked.\",
    \"recommendations\": [
        \"A strategic directive specific to the topic ({$topicDisplay})\",
        \"A linguistic adjustment based on their detected patterns\",
        \"A psychological technique to counter their biggest detected vulnerability\"
    ],
    \"round_analyses\": [
        {\"round\": 1, \"personality\": \"Micromanager\", \"performance\": \"Analysis with a quote\", \"key_moment\": \"Revealing quote\"},
        {\"round\": 2, \"personality\": \"Conspiracy Uncle\", \"performance\": \"Analysis with a quote\", \"key_moment\": \"Revealing quote\"},
        {\"round\": 3, \"personality\": \"Investor\", \"performance\": \"Analysis with a quote\", \"key_moment\": \"Revealing quote\"},
        {\"round\": 4, \"personality\": \"Passive-Aggressive\", \"performance\": \"Analysis with a quote\", \"key_moment\": \"Revealing quote\"},
        {\"round\": 5, \"personality\": \"Guilt-Tripper\", \"performance\": \"Analysis with a quote\", \"key_moment\": \"Revealing quote\"}
    ],
    \"language_patterns\": [
        {\"phrase\": \"exact phrase used\", \"count\": 0, \"context\": \"what this specific repetition reveals about their state\"}
    ]
}";

// Send to Gemini Pro for analysis
$aiResponse = sendAnalysisToGemini($analysisPrompt, $formattedTranscript);

if ($aiResponse === false) {
    echo json_encode(['error' => 'Analysis service unavailable. Please try again.']);
    exit;
}

// Parse the JSON response (strip markdown code fences if present)
$cleanResponse = $aiResponse;
$cleanResponse = preg_replace('/^```json\s*/i', '', $cleanResponse);
$cleanResponse = preg_replace('/\s*```$/', '', $cleanResponse);
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
