<?php
include 'db.php';

echo "<h2>Consolidated Database Fix</h2>";

// 1. Create personnel_details if it doesn't exist
$sql_personnel = "CREATE TABLE IF NOT EXISTS personnel_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    joining_date DATE,
    salary DECIMAL(10, 2),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql_personnel) === TRUE) {
    echo "✅ Table 'personnel_details' is ready.<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

// 2. Ensure all users have a valid role_id (Default to 2 for Senior Inspector if missing)
$sql_roles = "UPDATE users SET role_id = 2 WHERE role_id IS NULL OR role_id = 0";
if ($conn->query($sql_roles) === TRUE) {
    echo "✅ User role mappings updated.<br>";
}

// 3. Clean up any redundant files
$redundant_files = ['update_db_v3.php', 'update_rbac.php'];
foreach ($redundant_files as $file) {
    if (file_exists($file)) {
        // unlink($file); // Optionally delete, but better to just leave for now or warn
        echo "ℹ️ Note: '$file' is now obsolete.<br>";
    }
}

echo "<br><a href='personnel.php' style='padding: 0.5rem 1rem; background: #4f46e5; color: white; text-decoration: none; border-radius: 4px;'>Go to Personnel Page</a>";
?>
