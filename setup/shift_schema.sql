-- ============================================================
-- Shift Management Module - Database Migration
-- Run this file to add shift management tables & permissions
-- ============================================================

USE textile_qms;

-- 1. Shifts Definition Table
CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_name VARCHAR(100) NOT NULL,
    shift_type ENUM('Morning','Afternoon','Night','Custom') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    department VARCHAR(100),
    machine_ids VARCHAR(255),
    supervisor_id INT,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. Shift Assignments (Worker Roster)
CREATE TABLE IF NOT EXISTS shift_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    user_id INT NOT NULL,
    shift_date DATE NOT NULL,
    role_in_shift VARCHAR(100),
    notes TEXT,
    assigned_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_shift_user_date (shift_id, user_id, shift_date),
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Shift Attendance Tracking
CREATE TABLE IF NOT EXISTS shift_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    check_in TIME,
    check_out TIME,
    status ENUM('Present','Absent','Late','Half-Day','Leave') DEFAULT 'Absent',
    marked_by INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES shift_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. Shift Handover Log
CREATE TABLE IF NOT EXISTS shift_handovers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outgoing_shift_id INT NOT NULL,
    incoming_shift_id INT NOT NULL,
    handover_date DATE NOT NULL,
    handover_by INT NOT NULL,
    summary TEXT NOT NULL,
    pending_tasks TEXT,
    machine_status TEXT,
    quality_alerts TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (outgoing_shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (incoming_shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (handover_by) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. New Permissions for Shift Management
INSERT IGNORE INTO permissions (slug, description) VALUES
('manage_shifts', 'Create, edit, and delete shift definitions'),
('assign_shifts', 'Assign workers to shifts'),
('view_shifts', 'View the shift roster and schedule'),
('mark_attendance', 'Mark shift attendance for workers'),
('view_shift_reports', 'Access shift productivity and attendance reports'),
('log_handover', 'Submit shift handover notes');

-- 6. Grant shift permissions to General Manager & Quality Manager (full access)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('General Manager', 'Quality Manager') 
AND p.slug IN ('manage_shifts', 'assign_shifts', 'view_shifts', 'mark_attendance', 'view_shift_reports', 'log_handover');

-- 7. Grant operational shift permissions to Supervisor
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name = 'Supervisor' 
AND p.slug IN ('assign_shifts', 'view_shifts', 'mark_attendance', 'view_shift_reports', 'log_handover');

-- 8. Grant view-only to Senior & Junior Inspectors
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('Senior Inspector', 'Junior Inspector') 
AND p.slug = 'view_shifts';
