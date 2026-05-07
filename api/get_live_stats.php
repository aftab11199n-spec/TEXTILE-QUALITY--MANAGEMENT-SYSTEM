<?php
session_start();
header('Content-Type: application/json');
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Fetch live statistics
$stats = [];

$res = $conn->query("SELECT COUNT(*) as total FROM inspections");
$stats['total'] = ($res) ? ($res->fetch_assoc()['total'] ?? 0) : 0;

$res = $conn->query("SELECT COUNT(*) as rejected FROM inspections WHERE status='Rejected'");
$stats['rejected'] = ($res) ? ($res->fetch_assoc()['rejected'] ?? 0) : 0;

$res = $conn->query("SELECT COUNT(*) as passed FROM inspections WHERE status='Passed'");
$stats['passed'] = ($res) ? ($res->fetch_assoc()['passed'] ?? 0) : 0;

$stats['yield_rate'] = ($stats['total'] > 0) ? round(($stats['passed'] / $stats['total']) * 100, 1) : 0;

// Fetch latest notification
$res = $conn->query("SELECT message FROM notifications ORDER BY created_at DESC LIMIT 1");
$stats['latest_alert'] = ($res && $res->num_rows > 0) ? ($res->fetch_assoc()['message'] ?? "System normal") : "System normal";

$stats['last_updated'] = date('H:i:s');

echo json_encode($stats);
