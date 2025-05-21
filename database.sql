-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS legal_ai_db;
USE legal_ai_db;

-- Create sentences table
CREATE TABLE IF NOT EXISTS sentences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    court VARCHAR(100) NOT NULL,
    case_number VARCHAR(50) NOT NULL,
    date_issued DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create classifications table
CREATE TABLE IF NOT EXISTS classifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sentence_id INT NOT NULL,
    case_type VARCHAR(100) NOT NULL,
    legal_norms TEXT,
    is_precedent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sentence_id) REFERENCES sentences(id) ON DELETE CASCADE
);

-- Create analysis table
CREATE TABLE IF NOT EXISTS analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sentence_id INT NOT NULL,
    summary TEXT,
    proven_facts TEXT,
    applied_norms TEXT,
    court_criteria TEXT,
    final_resolution TEXT,
    dissenting_opinion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sentence_id) REFERENCES sentences(id) ON DELETE CASCADE
);

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic VARCHAR(255) NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create comparisons table
CREATE TABLE IF NOT EXISTS comparisons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sentence_id_1 INT NOT NULL,
    sentence_id_2 INT NOT NULL,
    similarities TEXT,
    differences TEXT,
    evolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sentence_id_1) REFERENCES sentences(id) ON DELETE CASCADE,
    FOREIGN KEY (sentence_id_2) REFERENCES sentences(id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX idx_sentences_court ON sentences(court);
CREATE INDEX idx_sentences_date ON sentences(date_issued);
CREATE INDEX idx_classifications_type ON classifications(case_type);
CREATE INDEX idx_alerts_topic ON alerts(topic);
