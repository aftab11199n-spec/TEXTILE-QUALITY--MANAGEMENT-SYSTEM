<?php
include 'db.php';

// 1. Alter Enum to include Manager/Inspector
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'supervisor', 'Manager', 'Inspector') DEFAULT 'Manager'";
if ($conn->query($sql) === TRUE) {
    echo "Table altered successfully.<br>";
} else {
    echo "Error altering table: " . $conn->error . "<br>";
}

// 2. Update existing admin to Manager
$sql = "UPDATE users SET role='Manager' WHERE role IN ('admin')";
if ($conn->query($sql) === TRUE) {
    echo "Admin user updated to Manager.<br>";
} else {
    echo "Error updating admin: " . $conn->error . "<br>";
}

// 3. Clean up Enum (Optional but good for strictness)
// Note: MySQL may not support dropping enum values easily in one go without raw query, 
// so we will just set the final simplified enum.
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('Manager', 'Inspector') DEFAULT 'Manager'";
if ($conn->query($sql) === TRUE) {
    echo "Enum cleaned up successfully.<br>";
} else {
    echo "Error cleaning up enum: " . $conn->error . "<br>";
}

echo "Database update complete.";
?>
