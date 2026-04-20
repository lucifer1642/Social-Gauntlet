-- Update rounds table for HR mode
USE personality_stress_tester;

ALTER TABLE rounds ADD COLUMN IF NOT EXISTS hr_question_id INT UNSIGNED NULL AFTER personality_id;
ALTER TABLE rounds ADD FOREIGN KEY IF NOT EXISTS (hr_question_id) REFERENCES hr_questions(id) ON DELETE SET NULL;
