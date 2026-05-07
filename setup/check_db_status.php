<?php
include 'db.php';

echo "Database: " . $dbname . "\n";
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "Tables in database:\n";
    while ($row = $result->fetch_array()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "Error showing tables: " . $conn->error . "\n";
}

$tables = ['users', 'roles', 'permissions', 'role_permissions', 'inspections'];
foreach ($tables as $table) {
    $res = $conn->query("SELECT COUNT(*) FROM $table");
    if ($res) {
        $count = $res->fetch_row()[0];
        echo "Table '$table' exists with $count rows.\n";
    } else {
        echo "Table '$table' MISSING or error: " . $conn->error . "\n";
    }
}
?>
