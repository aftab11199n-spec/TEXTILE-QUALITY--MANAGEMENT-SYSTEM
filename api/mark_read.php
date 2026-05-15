<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");

http_response_code(200);
?>
