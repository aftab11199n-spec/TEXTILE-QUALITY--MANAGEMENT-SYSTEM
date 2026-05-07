<?php
include '../db.php';

// 1. Generate correct hash for 'admin123'
$new_hash = password_hash('admin123', PASSWORD_DEFAULT);

// 2. Update the admin user
$stmt = $conn->prepare("UPDATE users SET password = ?, role_id = 1 WHERE username = 'admin'");
$stmt->bind_param("s", $new_hash);

if ($stmt->execute()) {
    echo "<div style='font-family: sans-serif; padding: 2rem; text-align: center;'>
        <h2 style='color: #4f46e5;'>✅ Admin Account Fixed</h2>
        <p>Password has been reset to: <strong>admin123</strong></p>
        <p>Role ID has been set to 1 (General Manager).</p>
        <a href='login.php' style='display: inline-block; margin-top: 1rem; padding: 0.5rem 1.5rem; background: #4f46e5; color: white; text-decoration: none; border-radius: 0.375rem;'>Try Login Now</a>
    </div>";
} else {
    echo "Error updating admin user: " . $conn->error;
}
?>
