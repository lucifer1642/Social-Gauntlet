-- Updated HR Interview Module - Questions Table with Guidelines
USE personality_stress_tester;

-- Add mode to sessions table for identification
ALTER TABLE sessions ADD COLUMN IF NOT EXISTS mode ENUM('standard', 'hr') DEFAULT 'standard' AFTER custom_topic;

-- Create/Recreate HR Questions Table
DROP TABLE IF EXISTS hr_questions;
CREATE TABLE hr_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    guideline TEXT NULL,
    category VARCHAR(50) DEFAULT 'general',
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Questions with Guidelines
INSERT INTO hr_questions (question, guideline, category, difficulty) VALUES
('Tell me about yourself.', 'Limit to work-related items. Talk about achievements and career progression chronologically. Avoid sounding rehearsed.', 'intro', 'easy'),
('Why did you leave your last job?', 'Stay positive. Never speak ill of previous employers. Focus on growth opportunities and special chances.', 'background', 'medium'),
('What experience do you have in this field?', 'Speak about specifics relating to the position. If lacking experience, find the closest transferable skills.', 'background', 'medium'),
('Do you consider yourself successful?', 'Always answer yes. Explain by mentioning specific goals met and future targets on track.', 'self-reflection', 'medium'),
('What do co-workers say about you?', 'Provide a quote or paraphrase from a specific colleague illustrating a strength like "hardest worker."', 'peer-perception', 'medium'),
('What do you know about this organization?', 'Demonstrate research. Mention current issues, major players, and the organization''s direction.', 'research', 'medium'),
('What have you done to improve your knowledge in the last year?', 'Mention positive self-improvement linked to the job role.', 'growth', 'medium'),
('Are you applying for other jobs?', 'Be honest but keep focus on this specific role and organization.', 'status', 'medium'),
('Why do you want to work for this organization?', 'Relate to long-term career goals and sincerity. Based on research of the company.', 'motivation', 'medium'),
('Do you know anyone who works for us?', 'Be careful with friends/relatives. Only mention friends well-thought-of in the company.', 'networking', 'easy'),
('What kind of salary do you need?', 'Try to ask for the range first. If forced, give a wide range based on job details.', 'salary', 'hard'),
('Are you a team player?', 'Give specific examples showing performance for the good of the team over self.', 'teamwork', 'easy'),
('How long would you expect to work for us if hired?', 'Answer with "a long time" or "as long as we both feel I''m doing a good job."', 'commitment', 'hard'),
('Have you ever had to fire anyone? How did you feel about that?', 'Show seriousness. Protect the organization while showing empathy. Differentiate from layoffs.', 'leadership', 'hard'),
('What is your philosophy towards work?', 'Short and positive. Focus on getting the job done for the benefit of the company.', 'ethic', 'medium'),
('If you had enough money to retire right now, would you?', 'If yes, state that this work is what you prefer regardless. Be honest but professional.', 'ethics', 'hard'),
('Have you ever been asked to leave a position?', 'Be honest, brief, and avoid negative comments about the former employer.', 'background', 'hard'),
('Explain how you would be an asset to this organization.', 'Highlight best points as they relate specifically to this position.', 'value', 'hard'),
('Why should we hire you?', 'Match assets to organizational needs. Avoid comparing self to other candidates.', 'value', 'hard'),
('Tell me about a suggestion you have made.', 'Use a suggestion that was accepted and successful. Ideally job-related.', 'initiative', 'medium'),
('What irritates you about co-workers?', 'Trap question. Try to say you get along with everyone. Avoid citing minor irritations.', 'teamwork', 'hard'),
('What is your greatest strength?', 'Prioritizing, problem-solving, working under pressure, or leadership qualities.', 'strengths', 'easy'),
('Tell me about your dream job.', 'Stay generic. A job where you can contribute, like the people, and love the work.', 'goals', 'medium'),
('Why do you think you would do well at this job?', 'Summarize skills, experience, and specific interests in the role.', 'confidence', 'medium'),
('What are you looking for in a job?', 'A place to contribute, enjoy the work, and find professional fulfillment.', 'vision', 'medium'),
('What kind of person would you refuse to work with?', 'Only object to lawbreaking, violence, or disloyalty to the organization. Don''t be trivial.', 'teamwork', 'hard'),
('What is more important to you: the money or the work?', 'State that work is most important, though money is always a factor.', 'values', 'hard'),
('What would your previous supervisor say your strongest point is?', 'Loyalty, energy, initiative, problem-solving, or hard work.', 'strengths', 'medium'),
('Tell me about a problem you had with a supervisor.', 'Trap question. Stay positive. Have a "poor memory" for any real conflicts.', 'conflict', 'hard'),
('What has disappointed you about a job?', 'Avoid trivialities. Mention lack of challenge or company-wide setbacks (like lost contracts).', 'background', 'hard'),
('Tell me about your ability to work under pressure.', 'Thrive under pressure. Give an example related to the position.', 'resilience', 'medium'),
('Do your skills match this job or another job more closely?', 'Focus on this job. Don''t suggest you want another role more.', 'fit', 'hard'),
('What motivates you to do your best on the job?', 'Personal traits like challenge, achievement, or professional recognition.', 'motivation', 'medium'),
('How would you know you were successful on this job?', 'Setting high standards and meeting them. Positive outcomes and boss feedback.', 'vision', 'medium'),
('Would you be willing to relocate if required?', 'Be honest. Better to be clear now than create issues later.', 'commitment', 'medium'),
('Are you willing to put the interests of the organization ahead of your own?', 'Straight loyalty question. Answer yes.', 'ethics', 'hard'),
('Describe your management style.', 'Use "situational style" — managing according to the specific situation.', 'leadership', 'medium'),
('What have you learned from mistakes on the job?', 'Small, well-intentioned mistake with a positive lesson learned. Show coordination improvements.', 'growth', 'medium'),
('Do you have any blind spots?', 'Trick question. If you know them, they are concerns, not blind spots. Don''t reveal much.', 'self-reflection', 'hard'),
('If you were hiring a person for this job, what would you look for?', 'Traits that are needed for the role and that you personally possess.', 'vision', 'medium'),
('Do you think you are overqualified for this position?', 'State that you are "very well qualified" regardless of high experience.', 'fit', 'hard'),
('How do you propose to compensate for your lack of experience?', 'Mention experience the interviewer might not know. Emphasize being a fast learner.', 'growth', 'hard'),
('What qualities do you look for in a boss?', 'Knowledgeable, fair, loyal, holder of high standards, and a sense of humor.', 'leadership', 'medium'),
('Tell me about a time when you helped resolve a dispute between others.', 'Concentrate on problem-solving techniques, not the details of the dispute itself.', 'conflict', 'hard'),
('What position do you prefer on a team working on a project?', 'Show comfort in different roles (flexible) or specific preferred strengths.', 'teamwork', 'medium'),
('Describe your work ethic.', 'Emphasize benefits to the organization like determination and hard work.', 'ethic', 'medium'),
('What has been your biggest professional disappointment?', 'Refer to something beyond your control. Show acceptance and no negativity.', 'self-reflection', 'medium'),
('Tell me about the most fun you have had on the job.', 'Fun derived from accomplishing something significant for the organization.', 'culture', 'easy'),
('Do you have any questions for me?', 'Always have questions prepared. Ask about productivity and upcoming projects.', 'initiative', 'medium'),

-- Distinct / "Modern" HR Questions added by AI
('How do you stay updated with AI and emerging technologies in your field?', 'Show proactive learning and specific tools or resources you use daily.', 'modern', 'medium'),
('How do you handle disagreements in a remote or hybrid work environment?', 'Focus on clear communication channels and proactive resolution without face-to-face cues.', 'modern', 'hard'),
('Tell me about a time you had to adapt to a major change in company strategy?', 'Emphasize flexibility, understanding the why, and supporting the team through transition.', 'adaptability', 'hard');
