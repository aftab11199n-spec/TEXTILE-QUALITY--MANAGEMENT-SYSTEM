-- Database Updates for Advanced Features

USE textile_qms;

-- AI Defect Detection - Storage for image analysis results
CREATE TABLE IF NOT EXISTS inspection_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    ai_status ENUM('Pending', 'Analyzed', 'Error') DEFAULT 'Pending',
    ai_findings TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
);

-- Audit Management System
CREATE TABLE IF NOT EXISTS audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_title VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    auditor_id INT,
    summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS audit_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    checkpoint VARCHAR(255) NOT NULL,
    observation TEXT,
    compliance_status ENUM('Compliant', 'Non-Compliant', 'Observation') DEFAULT 'Compliant',
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

-- Notifications for Real-time Monitoring
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Personnel Details for HR Directory
CREATE TABLE IF NOT EXISTS personnel_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    joining_date DATE,
    salary DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add missing fields to inspections if they don't exist
ALTER TABLE inspections ADD COLUMN IF NOT EXISTS barcode VARCHAR(100) AFTER batch_number;
ALTER TABLE inspections ADD COLUMN IF NOT EXISTS machine_id VARCHAR(50) AFTER material_type;
ALTER TABLE inspections ADD COLUMN IF NOT EXISTS operator_name VARCHAR(100) AFTER machine_id;
ALTER TABLE inspections ADD COLUMN IF NOT EXISTS defect_type VARCHAR(100) AFTER defect_count;
ALTER TABLE inspections ADD COLUMN IF NOT EXISTS defect_photo VARCHAR(255) AFTER comments;
ALTER TABLE inspections ADD COLUMN IF NOT EXISTS qr_id VARCHAR(50) AFTER defect_photo;
ALTER TABLE inspections ADD COLUMN IF NOT EXISTS grade VARCHAR(10) AFTER status;

-- Notifications Hub
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    type VARCHAR(20) DEFAULT 'info', -- info, warning, danger
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
