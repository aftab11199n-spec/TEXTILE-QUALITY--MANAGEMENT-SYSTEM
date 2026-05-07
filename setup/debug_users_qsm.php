<?php
$conn = new mysqli("localhost", "root", "", "textile_qsm");
$res = $conn->query("SELECT id, username, role_id FROM users");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} | User: {$row['username']} | Role ID: " . ($row['role_id'] ?? 'NULL') . "\n";
    }
}
?>
