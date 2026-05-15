<?php
/**
 * Automation Runner (Daemon)
 * This script handles automated tasks like shift transitions, 
 * attendance calculations, and alert generation.
 * 
 * Recommended execution: Cron job every 5-15 minutes
 * Example: php automation_runner.php --secret=YOUR_TOKEN
 */

include '../db.php';

// Simple security check (optional)
// if (php_sapi_name() !== 'cli' && $_GET['token'] !== 'secure_token') die('Unauthorized');

echo "--- Automation Runner Started: " . date('Y-m-d H:i:s') . " ---\n";

/**
 * 1. AUTOMATE SHIFT TRANSITIONS
 * Update shift statuses based on current time
 */
function updateShiftStatuses($conn) {
    $now = date('H:i:s');
    
    // Activate shifts that should be running
    $activate_sql = "UPDATE shifts SET status = 'Active' 
                     WHERE status = 'Inactive' 
                     AND '$now' BETWEEN start_time AND end_time";
    $conn->query($activate_sql);
    $activated = $conn->affected_rows;

    // Deactivate shifts that have ended
    // Handle wrap-around shifts (night shifts) if necessary
    $deactivate_sql = "UPDATE shifts SET status = 'Inactive' 
                       WHERE status = 'Active' 
                       AND '$now' NOT BETWEEN start_time AND end_time";
    $conn->query($deactivate_sql);
    $deactivated = $conn->affected_rows;

    echo "[Shifts] Activated: $activated, Deactivated: $deactivated\n";
}

/**
 * 2. CALCULATE SHIFT HOURS & OVERTIME
 * For completed attendance records, calculate total hours
 */
function calculateShiftHours($conn) {
    // This requires the columns added in shift_automation_update.sql
    $sql = "SELECT sa.id, s.start_time, s.end_time 
            FROM shift_attendance sa
            JOIN shift_assignments sas ON sa.assignment_id = sas.id
            JOIN shifts s ON sas.shift_id = s.id
            WHERE sa.total_hours = 0 OR sa.total_hours IS NULL";
    
    $res = $conn->query($sql);
    $updated = 0;

    while ($row = $res->fetch_assoc()) {
        $start = strtotime($row['start_time']);
        $end = strtotime($row['end_time']);
        
        // Handle night shifts (cross-day)
        if ($end < $start) $end += 86400; 
        
        $hours = round(($end - $start) / 3600, 2);
        $is_overtime = ($hours > 8) ? 1 : 0; // Simple logic: > 8 hrs is OT

        $u_sql = "UPDATE shift_attendance SET total_hours = $hours, is_overtime = $is_overtime 
                  WHERE id = {$row['id']}";
        $conn->query($u_sql);
        $updated++;
    }

    echo "[Attendance] Updated hours for $updated records\n";
}

/**
 * 3. QUALITY THRESHOLD MONITORING
 * Check for high rejection rates and trigger notifications
 */
function monitorQuality($conn) {
    $today = date('Y-m-d');
    $sql = "SELECT machine_id, COUNT(*) as total, 
                   SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
            FROM inspections 
            WHERE DATE(created_at) = '$today'
            GROUP BY machine_id
            HAVING (rejected / total) > 0.05 AND total > 5";
    
    $res = $conn->query($sql);
    $alerts = 0;

    while ($row = $res->fetch_assoc()) {
        $machine = $row['machine_id'];
        $rate = round(($row['rejected'] / $row['total']) * 100, 1);
        $msg = "CRITICAL: Machine #$machine has a high rejection rate of $rate% ($row[rejected]/$row[total])";
        
        // Check if alert already exists to prevent spam
        $check = $conn->query("SELECT id FROM notifications WHERE message = '$msg' AND DATE(created_at) = '$today'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO notifications (message, type, status) VALUES ('$msg', 'Critical', 'Unread')");
            $alerts++;
        }
    }

    echo "[Quality] Triggered $alerts critical alerts\n";
}

// Run tasks
updateShiftStatuses($conn);
// Only run the following if the schema has been updated
// calculateShiftHours($conn); 
monitorQuality($conn);

echo "--- Automation Runner Finished ---\n";
