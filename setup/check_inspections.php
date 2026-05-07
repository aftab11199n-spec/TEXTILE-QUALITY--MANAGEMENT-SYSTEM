<?php
include 'db.php';
$result = $conn->query("SELECT * FROM inspections");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
?>
