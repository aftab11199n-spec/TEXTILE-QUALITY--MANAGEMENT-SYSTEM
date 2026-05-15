-- Database Update for Shift Automation
USE textile_qms;

-- Add automation tracking columns to shift_attendance
ALTER TABLE shift_attendance 
ADD COLUMN IF NOT EXISTS total_hours DECIMAL(5,2) DEFAULT 0.00 AFTER status,
ADD COLUMN IF NOT EXISTS is_overtime TINYINT(1) DEFAULT 0 AFTER total_hours,
ADD COLUMN IF NOT EXISTS is_automatic TINYINT(1) DEFAULT 1 AFTER is_overtime;

-- Optional: Indexing for performance
CREATE INDEX IF NOT EXISTS idx_assignment ON shift_attendance(assignment_id);
