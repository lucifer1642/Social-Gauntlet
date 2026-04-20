<?php
// migrate_hr.php — Database migration for the HR Module
require_once __DIR__ . '/../includes/config.php';

function run_migration() {
    $host = DB_HOST;
    $db   = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "Starting HR Module Migration...\n";

        // 1. Add mode to sessions if not exists
        echo "Updating 'sessions' table...\n";
        $pdo->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS mode ENUM('standard', 'hr') DEFAULT 'standard' AFTER custom_topic");

        // 2. Create/Seed hr_questions
        echo "Recreating 'hr_questions' table...\n";
        $pdo->exec("DROP TABLE IF EXISTS hr_questions");
        $pdo->exec("CREATE TABLE hr_questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question TEXT NOT NULL,
            guideline TEXT NULL,
            category VARCHAR(50) DEFAULT 'general',
            difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Use the SQL file for seeding (this part is safe as it doesn't have ALTER/CREATE)
        $sql = file_get_contents(__DIR__ . '/hr_questions.sql');
        $queries = explode(';', $sql);
        foreach ($queries as $query) {
            if (stripos($query, 'INSERT INTO hr_questions') !== false) {
                $pdo->exec($query);
            }
        }

        // 3. Update rounds table
        echo "Updating 'rounds' table...\n";
        $pdo->exec("ALTER TABLE rounds ADD COLUMN IF NOT EXISTS hr_question_id INT UNSIGNED NULL AFTER personality_id");
        
        // Try adding foreign key (will fail if exists, so we wrap in try-catch or check existence)
        try {
            $pdo->exec("ALTER TABLE rounds ADD CONSTRAINT fk_hr_question FOREIGN KEY (hr_question_id) REFERENCES hr_questions(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Likely already exists
        }

        echo "Migration successful.\n";

    } catch (PDOException $e) {
        die("Migration failed: " . $e->getMessage() . "\n");
    }
}

run_migration();
