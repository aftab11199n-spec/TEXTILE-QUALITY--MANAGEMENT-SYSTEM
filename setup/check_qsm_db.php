<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "textile_qsm"; // The other one

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection to textile_qsm failed: " . $conn->connect_error);
}

$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "Tables in textile_qsm:\n";
    while ($row = $result->fetch_array()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "Error showing tables: " . $conn->error . "\n";
}

$tables = ['users', 'inspections'];
foreach ($tables as $table) {
    $res = $conn->query("SELECT COUNT(*) FROM $table");
    if ($res) {
        $count = $res->fetch_row()[0];
        echo "Table '$table' in textile_qsm has $count rows.\n";
    }
}
?>
