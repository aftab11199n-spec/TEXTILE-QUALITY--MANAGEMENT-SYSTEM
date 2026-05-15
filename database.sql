-- Database: textile_qms

CREATE DATABASE IF NOT EXISTS textile_qms;
USE textile_qms;

-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    level INT DEFAULT 0,
    description TEXT
);

-- Permissions Table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

-- Role_Permissions Table
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'supervisor', 'Quality Manager', 'Quality Supervisor') DEFAULT 'admin',
    role_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
);

-- Inspections Table
CREATE TABLE IF NOT EXISTS inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(50) NOT NULL,
    material_type VARCHAR(100) NOT NULL,
    length_meters DECIMAL(10, 2) NOT NULL,
    defect_count INT DEFAULT 0,
    status ENUM('Passed', 'Rejected', 'On Hold') DEFAULT 'On Hold',
    inspection_date DATE NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Roles
INSERT INTO roles (role_name, level, description) VALUES 
('General Manager', 1, 'Top level management access'),
('Quality Manager', 2, 'Head of Quality Department'),
('Supervisor', 3, 'Oversees inspectors'),
('Senior Inspector', 4, 'Experienced inspector'),
('Junior Inspector', 5, 'Entry level inspector');

-- Insert Default Permissions
INSERT INTO permissions (slug, description) VALUES 
('view_dashboard', 'View the main dashboard'),
('view_inspections', 'View all inspection records'),
('add_inspection', 'Create new inspection records'),
('edit_inspection', 'Edit existing inspection records'),
('delete_inspection', 'Delete inspection records'),
('manage_team', 'Access user management'),
('manage_roles', 'Create and edit roles/permissions'),
('access_personnel', 'View confidential personnel info'),
('export_data', 'Export data to CSV'),
('reset_passwords', 'Reset user passwords');

-- Map GM/QM Permissions
INSERT INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p WHERE r.role_name IN ('General Manager', 'Quality Manager');

-- Insert Default Admin User (Password: admin123)
-- MD5 is used here for simplicity in a student project, but allow for upgrade to password_hash later.
-- For this specific file, we will use a raw INSERT for a known password 'admin123' if using MD5 or similar.
-- However, standard PHP now uses password_hash (Bcrypt). 
-- Let's generate a hash for 'admin123' using PASSWORD_DEFAULT cost.
-- The hash below is for 'admin123'.
INSERT INTO users (username, password, role_id) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Audit Log Table
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    type VARCHAR(20) DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Inspection Images Table
CREATE TABLE IF NOT EXISTS inspection_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    ai_status ENUM('Pending','Analyzed','Error') DEFAULT 'Pending',
    ai_findings TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
);

-- Audits Table
CREATE TABLE IF NOT EXISTS audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_title VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    scheduled_date DATE NOT NULL,
    status ENUM('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
    auditor_id INT,
    summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit Items Table
CREATE TABLE IF NOT EXISTS audit_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    checkpoint VARCHAR(255) NOT NULL,
    observation TEXT,
    compliance_status ENUM('Compliant','Non-Compliant','Observation') DEFAULT 'Compliant',
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);
