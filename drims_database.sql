-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS drims_database;
USE drims_database;

-- Drop old admin table if exists
DROP TABLE IF EXISTS admin;

-- Create admin table
CREATE TABLE admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(50) NOT NULL
);

-- Insert default admin (plain password)
INSERT INTO admin (username, password)
VALUES ('admin', 'admin123');

-- Create teams table
CREATE TABLE IF NOT EXISTS teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert teams
INSERT INTO teams (team_name, description) VALUES
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

-- Create employees table
CREATE TABLE IF NOT EXISTS employees (
    employee_id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(50) NOT NULL,
    team VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create messages table
CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id VARCHAR(20) NOT NULL,
    sender_type ENUM('admin', 'employee') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    recipient_id VARCHAR(20) NOT NULL,
    recipient_type ENUM('admin', 'employee') NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_id, recipient_type),
    INDEX idx_sender (sender_id, sender_type),
    INDEX idx_created (created_at)
);

-- Create documents table (for routing)
CREATE TABLE IF NOT EXISTS documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50) NOT NULL DEFAULT 'letter',
    originating_team VARCHAR(100) NOT NULL,
    remarks TEXT,
    route_before DATE NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_by_id VARCHAR(20) NOT NULL,
    uploaded_by_type ENUM('admin', 'employee') NOT NULL,
    uploaded_by_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_route_before (route_before),
    INDEX idx_uploaded_by (uploaded_by_id, uploaded_by_type)
);
