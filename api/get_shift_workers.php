<?php
// API: Get workers assigned to a shift on a specific date
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include '../db.php';
include '../auth.php';

header('Content-Type: application/json');

$shift_id = intval($_GET['shift_id'] ?? 0);
$shift_date = $conn->real_escape_string($_GET['date'] ?? date('Y-m-d'));

if (!$shift_id) {
    echo json_encode(['error' => 'Missing shift_id']);
    exit();
}

$sql = "SELECT sa.id as assignment_id, sa.user_id, sa.role_in_shift, sa.notes,
               u.username, r.role_name, 
               COALESCE(pd.full_name, u.username) as full_name,
               att.status as attendance_status, att.check_in, att.check_out, att.remarks
        FROM shift_assignments sa
        JOIN users u ON sa.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN personnel_details pd ON u.id = pd.user_id
        LEFT JOIN shift_attendance att ON sa.id = att.assignment_id
        WHERE sa.shift_id = ? AND sa.shift_date = ?
        ORDER BY sa.role_in_shift ASC, u.username ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $shift_id, $shift_date);
$stmt->execute();
$result = $stmt->get_result();

$workers = [];
while ($row = $result->fetch_assoc()) {
    $workers[] = $row;
}

echo json_encode(['workers' => $workers, 'count' => count($workers)]);
