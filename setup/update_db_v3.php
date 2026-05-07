<?php
include 'db.php';

// 1. Alter users table to strictly use new Roles
// We first modify it to a broad VARCHAR or larger ENUM to allow transition
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'supervisor', 'Manager', 'Inspector', 'Quality Manager', 'Inspection Team') DEFAULT 'Quality Manager'";
if ($conn->query($sql) === TRUE) {
    echo "Enum expanded successfully.<br>";
} else {
    echo "Error expanding enum: " . $conn->error . "<br>";
}

// 2. Migrate existing data
$sql_manager = "UPDATE users SET role='Quality Manager' WHERE role IN ('admin', 'Manager')";
if ($conn->query($sql_manager) === TRUE) {
    echo "Managers updated to 'Quality Manager'.<br>";
}

$sql_inspector = "UPDATE users SET role='Inspection Team' WHERE role IN ('supervisor', 'Inspector')";
if ($conn->query($sql_inspector) === TRUE) {
    echo "Inspectors updated to 'Inspection Team'.<br>";
}

// 3. Finalize Enum
$sql_final = "ALTER TABLE users MODIFY COLUMN role ENUM('Quality Manager', 'Inspection Team') DEFAULT 'Quality Manager'";
if ($conn->query($sql_final) === TRUE) {
    echo "Enum finalized to 'Quality Manager' / 'Inspection Team'.<br>";
} else {
    echo "Error finalizing enum: " . $conn->error . "<br>";
}

// 4. Create Personnel Details Table
$sql_personnel = "CREATE TABLE IF NOT EXISTS personnel_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    joining_date DATE,
    salary DECIMAL(10, 2), -- Confidential
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql_personnel) === TRUE) {
    echo "Personnel details table created successfully.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

echo "Database update v3 complete.";
?>
