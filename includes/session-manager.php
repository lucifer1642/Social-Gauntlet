<?php
// ==============================================
// session-manager.php — Session & Round CRUD
// ==============================================
// Handles creating sessions, rounds, messages, and retrieving data

require_once __DIR__ . '/db.php';

/**
 * Create a new stress test session with 5 rounds
 */
function createSession($userId, $topic, $customTopic = null) {
    $db = getDB();
    
    // Insert session
    $stmt = $db->prepare('INSERT INTO sessions (user_id, topic, custom_topic) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $topic, $customTopic]);
    $sessionId = $db->lastInsertId();
    
    // Create all 5 rounds (personalities 1-5)
    $stmt = $db->prepare('INSERT INTO rounds (session_id, round_number, personality_id) VALUES (?, ?, ?)');
    for ($i = 1; $i <= TOTAL_ROUNDS; $i++) {
        $stmt->execute([$sessionId, $i, $i]);
    }
    return $sessionId;
}

/**
 * Get a session by ID (with ownership check)
 */
function getSession($sessionId, $userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $userId]);
    return $stmt->fetch();
}

/**
 * Get all sessions for a user
 */
function getUserSessions($userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT s.*, r.strongest_under, r.biggest_vulnerability 
                          FROM sessions s 
                          LEFT JOIN reports r ON r.session_id = s.id 
                          WHERE s.user_id = ? 
                          ORDER BY s.started_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get the current active round for a session
 */
function getCurrentRound($sessionId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM rounds WHERE session_id = ? AND round_number = (SELECT current_round FROM sessions WHERE id = ?)');
    $stmt->execute([$sessionId, $sessionId]);
    return $stmt->fetch();
}

/**
 * Get a specific round
 */
function getRound($roundId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM rounds WHERE id = ?');
    $stmt->execute([$roundId]);
    return $stmt->fetch();
}

/**
 * Get all messages for a round (ordered by time)
 */
function getRoundMessages($roundId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM messages WHERE round_id = ? ORDER BY created_at ASC');
    $stmt->execute([$roundId]);
    return $stmt->fetchAll();
}

/**
 * Save a message to the database
 */
function saveMessage($roundId, $role, $content, $responseTimeMs = null) {
    $db = getDB();
    $charCount = mb_strlen($content);
    $stmt = $db->prepare('INSERT INTO messages (round_id, role, content, char_count, response_time_ms) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$roundId, $role, $content, $charCount, $responseTimeMs]);
    
    // Update exchange count if this is an assistant message (completes an exchange)
    if ($role === 'assistant') {
        $stmt = $db->prepare('UPDATE rounds SET exchange_count = exchange_count + 1 WHERE id = ?');
        $stmt->execute([$roundId]);
    }
    
    return $db->lastInsertId();
}

/**
 * Start a round (set status to active)
 */
function startRound($roundId) {
    $db = getDB();
    $stmt = $db->prepare('UPDATE rounds SET status = "active", started_at = NOW() WHERE id = ?');
    $stmt->execute([$roundId]);
}

/**
 * Complete a round and advance session
 */
function completeRound($sessionId, $roundId) {
    $db = getDB();
    
    // Mark round complete
    $stmt = $db->prepare('UPDATE rounds SET status = "completed", completed_at = NOW() WHERE id = ?');
    $stmt->execute([$roundId]);
    
    // Get current round number
    $stmt = $db->prepare('SELECT current_round FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if ($session['current_round'] < TOTAL_ROUNDS) {
        // Advance to next round
        $stmt = $db->prepare('UPDATE sessions SET current_round = current_round + 1 WHERE id = ?');
        $stmt->execute([$sessionId]);
        return ['next_round' => $session['current_round'] + 1];
    } else {
        // All rounds done
        $stmt = $db->prepare('UPDATE sessions SET status = "completed", completed_at = NOW() WHERE id = ?');
        $stmt->execute([$sessionId]);
        return ['completed' => true];
    }
}

/**
 * Get all messages across all rounds for a session (for analysis)
 */
function getFullTranscript($sessionId) {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT r.round_number, r.personality_id, m.role, m.content, m.char_count, m.response_time_ms, m.created_at
        FROM messages m
        JOIN rounds r ON m.round_id = r.id
        WHERE r.session_id = ?
        ORDER BY r.round_number ASC, m.created_at ASC
    ');
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

/**
 * Save a report
 */
function saveReport($sessionId, $analysisJson, $strongestUnder, $biggestVulnerability, $blindSpot, $patternSummary, $emotionalTripwire, $recommendationsJson) {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO reports (session_id, analysis_json, strongest_under, biggest_vulnerability, blind_spot, pattern_summary, emotional_tripwire, recommendations_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$sessionId, $analysisJson, $strongestUnder, $biggestVulnerability, $blindSpot, $patternSummary, $emotionalTripwire, $recommendationsJson]);
    return $db->lastInsertId();
}

/**
 * Get report for a session
 */
function getReport($sessionId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM reports WHERE session_id = ?');
    $stmt->execute([$sessionId]);
    return $stmt->fetch();
}

/**
 * Get all user messages from previous rounds (for cross-round context)
 */
function getPriorUserMessages($sessionId, $currentRoundNumber) {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT m.content, r.round_number
        FROM messages m
        JOIN rounds r ON m.round_id = r.id
        WHERE r.session_id = ? AND r.round_number < ? AND m.role = "user"
        ORDER BY r.round_number ASC, m.created_at ASC
    ');
    $stmt->execute([$sessionId, $currentRoundNumber]);
    return $stmt->fetchAll();
}

/**
 * Delete a session (and cascade delete rounds/messages if database doesn't auto cascade)
 */
function deleteSession($sessionId, $userId) {
    $db = getDB();
    
    // Delete messages in all rounds of this session
    $stmt = $db->prepare('DELETE FROM messages WHERE round_id IN (SELECT id FROM rounds WHERE session_id = ?)');
    $stmt->execute([$sessionId]);
    
    // Delete rounds
    $stmt = $db->prepare('DELETE FROM rounds WHERE session_id = ?');
    $stmt->execute([$sessionId]);
    
    // Delete reports
    $stmt = $db->prepare('DELETE FROM reports WHERE session_id = ?');
    $stmt->execute([$sessionId]);

    // Finally, delete the session itself
    $stmt = $db->prepare('DELETE FROM sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $userId]);
    
    return $stmt->rowCount() > 0;
}
