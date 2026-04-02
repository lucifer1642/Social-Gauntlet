<?php
// generate_pending_reports.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session-manager.php';
require_once __DIR__ . '/includes/gemini-client.php';

// This script mimics the logic in api/analyze.php to "back-fill" missing reports

function runAnalysisForSession($sessionId) {
    echo "--- Analyzing Session ID: $sessionId ---\n";
    
    $session = getSession($sessionId, 1); // Assuming user_id 1 for this test, or adjust as needed
    if (!$session) {
        // Try without user_id check if it's the only user
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
    }

    if (!$session || $session['status'] !== 'completed') {
        echo "Error: Session not found or not completed.\n";
        return;
    }

    // Check if report already exists
    $existingReport = getReport($sessionId);
    if ($existingReport) {
        echo "Report already exists. Skipping.\n";
        return;
    }

    // Get full transcript
    $transcript = getFullTranscript($sessionId);
    $topicDisplay = $session['custom_topic'] ?: $session['topic'];

    // Build formatted transcript (Logic from api/analyze.php)
    $formattedTranscript = "TOPIC: {$topicDisplay}\n\n";
    $currentRound = 0;
    foreach ($transcript as $msg) {
        if ($msg['round_number'] !== $currentRound) {
            $currentRound = $msg['round_number'];
            $formattedTranscript .= "\n--- ROUND {$currentRound} ---\n\n";
        }
        $speaker = ($msg['role'] === 'user') ? 'USER' : 'AI_PERSONA';
        $formattedTranscript .= "[{$speaker}]: {$msg['content']}\n\n";
    }

    $analysisPrompt = "You are a communication psychologist analyzing a stress test. Respond in JSON format with fields: strongest_under, biggest_vulnerability, blind_spot, pattern_summary, emotional_tripwire, recommendations.";

    echo "Sending to AI for analysis...\n";
    $aiResponse = sendAnalysisToGemini($analysisPrompt, $formattedTranscript);

    if ($aiResponse === false) {
        echo "FAILED: AI returned false for session $sessionId\n";
        return;
    }

    $cleanResponse = $aiResponse;
    $cleanResponse = preg_replace('/^```json\s*/i', '', $cleanResponse);
    $cleanResponse = preg_replace('/\s*```$/', '', $cleanResponse);
    $analysis = json_decode($cleanResponse, true);

    if (!$analysis) {
        echo "Error: JSON parsing failed.\n";
        return;
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

    echo "SUCCESS: Report generated and saved for Session $sessionId!\n";
}

// Find all completed sessions without reports
$db = getDB();
$sessions = $db->query("SELECT s.id FROM sessions s LEFT JOIN reports r ON s.id = r.session_id WHERE s.status='completed' AND r.id IS NULL")->fetchAll();

if (empty($sessions)) {
    echo "No pending reports found.\n";
} else {
    foreach ($sessions as $s) {
        runAnalysisForSession($s['id']);
    }
}
