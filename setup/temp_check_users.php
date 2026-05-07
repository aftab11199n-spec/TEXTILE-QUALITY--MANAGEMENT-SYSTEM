<?php
include 'db.php';
$res = $conn->query("SELECT * FROM users");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
