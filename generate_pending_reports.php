<?php
// generate_pending_reports.php — Regenerate reports from stored conversations
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session-manager.php';
require_once __DIR__ . '/includes/personality-prompts.php';
require_once __DIR__ . '/includes/gemini-client.php';

function runAnalysisForSession($sessionId) {
    echo "--- Analyzing Session ID: $sessionId ---\n";
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session || $session['status'] !== 'completed') {
        echo "Error: Session not found or not completed.\n";
        return;
    }

    $existingReport = getReport($sessionId);
    if ($existingReport) {
        echo "Report already exists. Skipping.\n";
        return;
    }

    $transcript = getFullTranscript($sessionId);
    $topicDisplay = $session['custom_topic'] ?: $session['topic'];

    // Build formatted transcript with behavioral data
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

    // Full analysis prompt (same as api/analyze.php)
    $analysisPrompt = "You are a communication psychologist performing a high-fidelity behavioral audit. You have been provided with a transcript of 5 high-pressure rounds where the subject (USER) interacted with distinct difficult personalities on the topic: {$topicDisplay}.

CRITICAL RULES:
- Every single data point, score, and insight MUST be directly derived from the actual conversation transcript provided.
- DO NOT use generic or template responses. Reference specific quotes and moments.
- Every audit must be UNIQUE. Vary your language, structure, and focus areas.
- All percentage scores must reflect the actual performance observed in the transcript.

You MUST respond in EXACTLY this JSON format and nothing else:
{
    \"strongest_under\": \"One detailed paragraph describing which persona the user handled best. Quote a specific exchange showing their strength. Reference the exact round number.\",
    \"biggest_vulnerability\": \"One detailed paragraph identifying their primary failure point. Use metrics from the behavioral data. Quote their weakest moment verbatim.\",
    \"blind_spot\": \"One paragraph about a subtext or emotional cue they completely missed. Be specific about which round, which AI quote, and what the user should have caught.\",
    \"pattern_summary\": \"Analyze 3 specific recurring linguistic patterns. For each, quote the exact phrase, state how many times it appeared, and explain the psychological cause.\",
    \"emotional_tripwire\": \"Identify the exact AI sentence that caused the user to lose composure. Quote both the AI trigger and the user's derailed response. Explain why it worked.\",
    \"recommendations\": [
        \"A strategic directive specific to the topic ({$topicDisplay}) based on observed weaknesses\",
        \"A linguistic adjustment referencing their actual detected speech patterns\",
        \"A psychological counter-technique for their specific vulnerability\",
        \"A practice exercise targeting their weakest round performance\"
    ],
    \"round_analyses\": [
        {\"round\": 1, \"personality\": \"Micromanager\", \"performance\": \"Detailed analysis quoting actual user responses\", \"key_moment\": \"Most revealing quote from this round\"},
        {\"round\": 2, \"personality\": \"Conspiracy Uncle\", \"performance\": \"Detailed analysis quoting actual user responses\", \"key_moment\": \"Most revealing quote from this round\"},
        {\"round\": 3, \"personality\": \"Investor\", \"performance\": \"Detailed analysis quoting actual user responses\", \"key_moment\": \"Most revealing quote from this round\"},
        {\"round\": 4, \"personality\": \"Passive-Aggressive\", \"performance\": \"Detailed analysis quoting actual user responses\", \"key_moment\": \"Most revealing quote from this round\"},
        {\"round\": 5, \"personality\": \"Guilt-Tripper\", \"performance\": \"Detailed analysis quoting actual user responses\", \"key_moment\": \"Most revealing quote from this round\"}
    ],
    \"language_patterns\": [
        {\"phrase\": \"exact phrase the user actually said\", \"count\": 0, \"context\": \"what this repetition reveals about their psychological state\"}
    ],
    \"chart_data\": {
        \"persona_resistance\": {
            \"boss\": 0, \"uncle\": 0, \"investor\": 0, \"coworker\": 0, \"guilt_tripper\": 0
        },
        \"stress_resistance_index\": 0,
        \"psych_profile\": {
            \"defensiveness\": 0, \"adaptability\": 0, \"anxiety\": 0, \"logic_focus\": 0, \"empathy\": 0
        },
        \"linguistic_profile\": {
            \"tone_control\": 0, \"complexity\": 0, \"assertiveness\": 0, \"empathy\": 0, \"formality\": 0
        },
        \"reaction_consistency\": {
            \"boss\": 0, \"uncle\": 0, \"investor\": 0, \"coworker\": 0, \"guilt_tripper\": 0
        },
        \"blind_spot_radar\": {
            \"awareness\": 0, \"impact\": 0, \"recurrence\": 0
        },
        \"trigger_radar\": {
            \"volatility\": 0, \"frequency\": 0, \"severity\": 0
        }
    }
}

SCORING GUIDELINES (all scores 0-100 based on actual transcript):
- persona_resistance: How well did the user resist each personality's pressure tactics?
- stress_resistance_index: Overall score (0-100) reflecting composure across all rounds.
- psych_profile: Rate each dimension based on observed behavior.
- linguistic_profile: Rate based on actual word choices and rhetorical techniques.
- reaction_consistency: How consistent was the user's response quality within each round?
- blind_spot_radar: awareness, impact, recurrence — all based on observed blind spots.
- trigger_radar: volatility, frequency, severity — all based on observed emotional reactions.";

    echo "Sending to AI for analysis (this may take ~15s)...\n";
    $aiResponse = sendAnalysisToGemini($analysisPrompt, $formattedTranscript);

    if ($aiResponse === false) {
        echo "FAILED: AI returned false for session $sessionId\n";
        return;
    }

    // Robust JSON extraction
    $cleanResponse = $aiResponse;
    $cleanResponse = preg_replace('/```(?:json)?\s*/i', '', $cleanResponse);
    $firstBrace = strpos($cleanResponse, '{');
    $lastBrace = strrpos($cleanResponse, '}');
    if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
        $cleanResponse = substr($cleanResponse, $firstBrace, $lastBrace - $firstBrace + 1);
    }
    $analysis = json_decode($cleanResponse, true);

    if (!$analysis) {
        echo "Error: JSON parsing failed. Raw response:\n" . substr($aiResponse, 0, 500) . "\n";
        return;
    }

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

    echo "SUCCESS: Report generated for Session $sessionId!\n";
}

// Find all completed sessions without reports
$db = getDB();
$sessions = $db->query("SELECT s.id FROM sessions s LEFT JOIN reports r ON s.id = r.session_id WHERE s.status='completed' AND r.id IS NULL")->fetchAll();

if (empty($sessions)) {
    echo "No pending reports found.\n";
} else {
    echo "Found " . count($sessions) . " sessions needing reports.\n\n";
    foreach ($sessions as $s) {
        runAnalysisForSession($s['id']);
        echo "\n";
    }
}
