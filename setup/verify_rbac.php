<?php
session_start();
include 'db.php';
include 'auth.php';

echo "<h2>RBAC Verification Report</h2>";

// Test 1: Session check
echo "<h3>Test 1: Session Data</h3>";
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>FAIL: No user session found. Please login first.</p>";
} else {
    echo "<p style='color:green;'>PASS: User ID " . $_SESSION['user_id'] . " found in session.</p>";
    echo "Role: " . ($_SESSION['role_name'] ?? 'Not set') . "<br>";
    echo "Permissions Loaded: " . count($_SESSION['permissions']) . "<br>";
    echo "List: " . implode(', ', $_SESSION['permissions']) . "<br>";
}

// Test 2: Permission check helper
echo "<h3>Test 2: hasPermission Function</h3>";
$test_perms = ['view_dashboard', 'add_inspection', 'manage_team', 'non_existent_perm'];
foreach ($test_perms as $p) {
    echo "Check '$p': " . (hasPermission($p) ? "<span style='color:green;'>GRANTED</span>" : "<span style='color:red;'>DENIED</span>") . "<br>";
}

// Test 3: Database Roles Check
echo "<h3>Test 3: Database Role Integrity</h3>";
$res = $conn->query("SELECT r.role_name, COUNT(rp.permission_id) as perm_count 
                     FROM roles r 
                     LEFT JOIN role_permissions rp ON r.id = rp.role_id 
                     GROUP BY r.id");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "Role: " . $row['role_name'] . " - Permissions: " . $row['perm_count'] . "<br>";
    }
} else {
    echo "Error: " . $conn->error;
}
?>
