<?php
require_once __DIR__ . '/includes/db.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS candidate_name VARCHAR(100) NULL AFTER custom_topic");
    echo "Success: candidate_name column ensured.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
