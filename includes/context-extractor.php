<?php
// ==============================================
// context-extractor.php — Cross-round context injection
// ==============================================
// Extracts key user positions from prior rounds to inject into new personality prompts

require_once __DIR__ . '/session-manager.php';

/**
 * Extract a summary of user positions from previous rounds
 * Returns a formatted string to inject into the system prompt
 */
function extractUserPositions($sessionId, $currentRoundNumber) {
    if ($currentRoundNumber <= 1) {
        return ''; // No prior context for round 1
    }
    
    $priorMessages = getPriorUserMessages($sessionId, $currentRoundNumber);
    
    if (empty($priorMessages)) {
        return '';
    }
    
    // Build a summary of what the user said in each prior round
    $contextLines = [];
    $currentRound = 0;
    
    foreach ($priorMessages as $msg) {
        if ($msg['round_number'] !== $currentRound) {
            $currentRound = $msg['round_number'];
        }
        // Include user messages (trimmed to keep context manageable)
        $content = trim($msg['content']);
        if (mb_strlen($content) > 200) {
            $content = mb_substr($content, 0, 200) . '...';
        }
        $contextLines[] = "- (From earlier conversation) \"{$content}\"";
    }
    
    // Limit to last 10 key statements to avoid token overflow
    if (count($contextLines) > 10) {
        $contextLines = array_slice($contextLines, -10);
    }
    
    return implode("\n", $contextLines);
}
