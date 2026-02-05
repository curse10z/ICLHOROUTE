-- Add teams to existing DRIMS database
USE drims_database;

-- Create teams table if it doesn't exist
CREATE TABLE IF NOT EXISTS teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert teams (ignore if they already exist)
INSERT IGNORE INTO teams (team_name, description) VALUES
('Admin', 'Administrative Team'),
('Frontdesk', 'Front Desk Operations'),
('Technical', 'Technical Support Team'),
('Survey', 'Survey and Assessment Team'),
('TXZ', 'TXZ Department'),
('Atty. Peter', 'Legal - Atty. Peter'),
('OV', 'Office of the Vice'),
('Eviction and Dismantling', 'Eviction and Dismantling Operations'),
('Legal Team', 'Legal Department'),
('HHRO', 'Human Resources and Housing Operations');
