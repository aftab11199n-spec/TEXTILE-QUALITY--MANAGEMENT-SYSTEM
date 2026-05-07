<?php
include 'db.php';
$res = $conn->query("SELECT u.id, u.username, u.role_id, r.role_name 
                     FROM users u 
                     LEFT JOIN roles r ON u.role_id = r.id");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} | User: {$row['username']} | Role: " . ($row['role_name'] ?? 'NULL') . "\n";
    }
} else {
    echo "Query failed: " . $conn->error . "\n";
}
?>
