<?php
include '../db.php';

echo "<h2>Shift Module Setup</h2>";

$sql = file_get_contents('shift_schema.sql');

// Split SQL into individual queries
$queries = explode(';', $sql);
$success = 0;
$errors = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) continue;
    
    if ($conn->query($query)) {
        $success++;
    } else {
        echo "<p style='color:red;'>Error executing: " . substr($query, 0, 50) . "... <br>Error: " . $conn->error . "</p>";
        $errors++;
    }
}

echo "<p>Setup Complete: $success queries successful, $errors errors.</p>";
echo "<a href='../shifts.php'>Go to Shift Management</a>";
?>
