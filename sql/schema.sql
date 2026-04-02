-- Personality Stress Tester Database Schema
-- Run this in phpMyAdmin after creating database 'personality_stress_tester'

CREATE DATABASE IF NOT EXISTS personality_stress_tester;
USE personality_stress_tester;

-- Users table (simple auth)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions (each gauntlet run)
CREATE TABLE sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    topic VARCHAR(100) NOT NULL,
    custom_topic TEXT NULL,
    status ENUM('active','completed','abandoned') NOT NULL DEFAULT 'active',
    current_round TINYINT UNSIGNED NOT NULL DEFAULT 1,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rounds (5 per session)
CREATE TABLE rounds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    personality_id TINYINT UNSIGNED NOT NULL,
    status ENUM('pending','active','completed') NOT NULL DEFAULT 'pending',
    exchange_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    UNIQUE KEY unique_session_round (session_id, round_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messages (every exchange)
CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_id INT UNSIGNED NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content TEXT NOT NULL,
    char_count INT UNSIGNED NOT NULL DEFAULT 0,
    response_time_ms INT UNSIGNED NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
    INDEX idx_round_id (round_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reports (vulnerability analysis)
CREATE TABLE reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL UNIQUE,
    analysis_json JSON NOT NULL,
    strongest_under TEXT NOT NULL,
    biggest_vulnerability TEXT NOT NULL,
    blind_spot TEXT NOT NULL,
    pattern_summary TEXT NOT NULL,
    emotional_tripwire TEXT NOT NULL,
    recommendations_json JSON NOT NULL,
    share_token VARCHAR(64) NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
