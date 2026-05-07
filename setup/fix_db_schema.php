<?php
// setup/fix_db_schema.php
include dirname(__FILE__) . '/../db.php';

echo "<h2>Fixing Database Schema</h2>";

function tableExists($table) {
    global $conn;
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res->num_rows > 0;
}

function columnExists($table, $column) {
    global $conn;
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res->num_rows > 0;
}

// 1. Audit Log Table
if (!tableExists('audit_log')) {
    $sql = "CREATE TABLE audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    if ($conn->query($sql)) echo "✅ Table 'audit_log' created.<br>";
    else echo "❌ Error creating 'audit_log': " . $conn->error . "<br>";
} else {
    echo "ℹ️ Table 'audit_log' already exists.<br>";
}

// 2. Notifications Table
if (!tableExists('notifications')) {
    $sql = "CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        message TEXT NOT NULL,
        type VARCHAR(20) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    if ($conn->query($sql)) echo "✅ Table 'notifications' created.<br>";
    else echo "❌ Error creating 'notifications': " . $conn->error . "<br>";
} else {
    echo "ℹ️ Table 'notifications' already exists.<br>";
    // Ensure 'type' column exists
    if (!columnExists('notifications', 'type')) {
        $conn->query("ALTER TABLE notifications ADD COLUMN type VARCHAR(20) DEFAULT 'info' AFTER message");
        echo "✅ Column 'type' added to 'notifications'.<br>";
    }
}

// 3. Inspections Table Columns
$inspections_cols = [
    'barcode' => "VARCHAR(100) AFTER batch_number",
    'machine_id' => "VARCHAR(50) AFTER material_type",
    'operator_name' => "VARCHAR(100) AFTER machine_id",
    'defect_type' => "VARCHAR(100) AFTER defect_count",
    'defect_photo' => "VARCHAR(255) AFTER comments",
    'qr_id' => "VARCHAR(50) AFTER defect_photo",
    'grade' => "VARCHAR(10) AFTER status"
];

foreach ($inspections_cols as $col => $def) {
    if (!columnExists('inspections', $col)) {
        if ($conn->query("ALTER TABLE inspections ADD COLUMN $col $def")) {
            echo "✅ Column '$col' added to 'inspections'.<br>";
        } else {
            echo "❌ Error adding '$col' to 'inspections': " . $conn->error . "<br>";
        }
    }
}

// 4. Other tables from update_schema.sql
$extra_tables = [
    'inspection_images' => "CREATE TABLE inspection_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inspection_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        ai_status ENUM('Pending', 'Analyzed', 'Error') DEFAULT 'Pending',
        ai_findings TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
    )",
    'audits' => "CREATE TABLE audits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        audit_title VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        scheduled_date DATE NOT NULL,
        status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
        auditor_id INT,
        summary TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    'audit_items' => "CREATE TABLE audit_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        audit_id INT NOT NULL,
        checkpoint VARCHAR(255) NOT NULL,
        observation TEXT,
        compliance_status ENUM('Compliant', 'Non-Compliant', 'Observation') DEFAULT 'Compliant',
        FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
    )",
    'personnel_details' => "CREATE TABLE personnel_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        full_name VARCHAR(255),
        phone VARCHAR(20),
        address TEXT,
        joining_date DATE,
        salary DECIMAL(15, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
];

foreach ($extra_tables as $table => $sql) {
    if (!tableExists($table)) {
        if ($conn->query($sql)) echo "✅ Table '$table' created.<br>";
        else echo "❌ Error creating '$table': " . $conn->error . "<br>";
    } else {
        echo "ℹ️ Table '$table' already exists.<br>";
    }
}

echo "<br><strong>Database Fix Complete!</strong><br>";
echo "<a href='../dashboard.php'>Back to Dashboard</a>";
?>
