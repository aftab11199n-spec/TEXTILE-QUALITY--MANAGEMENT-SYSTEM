<?php
session_start();
include 'db.php';
include 'auth.php';

if (!hasPermission('access_personnel')) {
    die("Access Denied");
}

$filename = "Personnel_Directory_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, array('ID', 'Username', 'Role', 'Full Name', 'Phone', 'Address', 'Joining Date', 'Salary'));

$sql = "SELECT u.id, u.username, r.role_name, p.full_name, p.phone, p.address, p.joining_date, p.salary 
        FROM users u 
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN personnel_details p ON u.id = p.user_id 
        ORDER BY r.level ASC, u.username ASC";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
