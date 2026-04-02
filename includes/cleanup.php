<?php
// ==============================================
// cleanup.php — Session timeout & abandonment cleanup
// ==============================================
// Marks stale "active" sessions as "abandoned" after a timeout period.
// Can be called on login/dashboard load or via a cron job.

require_once __DIR__ . '/db.php';

/**
 * Abandon sessions that have been active for too long without activity.
 * Default timeout: 2 hours since last message or session start.
 * 
 * @param int $timeoutMinutes Minutes of inactivity before abandoning
 * @return int Number of sessions abandoned
 */
function cleanupStaleSessions($timeoutMinutes = 120) {
    $db = getDB();
    
    // Find sessions that are 'active' but have no message activity
    // within the timeout window
    $stmt = $db->prepare('
        UPDATE sessions s
        SET s.status = "abandoned", s.completed_at = NOW()
        WHERE s.status = "active"
        AND (
            -- No messages at all and session started > timeout ago
            (NOT EXISTS (
                SELECT 1 FROM messages m
                JOIN rounds r ON m.round_id = r.id
                WHERE r.session_id = s.id
            ) AND s.started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE))
            OR
            -- Has messages but last message > timeout ago
            (EXISTS (
                SELECT 1 FROM messages m
                JOIN rounds r ON m.round_id = r.id
                WHERE r.session_id = s.id
            ) AND (
                SELECT MAX(m2.created_at) FROM messages m2
                JOIN rounds r2 ON m2.round_id = r2.id
                WHERE r2.session_id = s.id
            ) < DATE_SUB(NOW(), INTERVAL ? MINUTE))
        )
    ');
    
    $stmt->execute([$timeoutMinutes, $timeoutMinutes]);
    return $stmt->rowCount();
}

/**
 * Get count of stale sessions (for dashboard display)
 */
function countStaleSessions($userId, $timeoutMinutes = 120) {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT COUNT(*) as cnt FROM sessions
        WHERE user_id = ? AND status = "active"
        AND started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ');
    $stmt->execute([$userId, $timeoutMinutes]);
    $row = $stmt->fetch();
    return $row['cnt'] ?? 0;
}
