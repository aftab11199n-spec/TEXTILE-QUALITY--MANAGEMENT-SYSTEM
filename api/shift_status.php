<?php
// API: Update shift status or handle quick actions
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include '../db.php';
include '../auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'toggle_shift_status':
        if (!hasPermission('manage_shifts')) {
            echo json_encode(['error' => 'No permission']);
            exit();
        }
        $shift_id = intval($_POST['shift_id']);
        $new_status = $conn->real_escape_string($_POST['status']);
        $stmt = $conn->prepare("UPDATE shifts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $shift_id);
        if ($stmt->execute()) {
            logAction($conn, $_SESSION['user_id'], 'Shift Status Change', "Shift #$shift_id set to $new_status");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => $stmt->error]);
        }
        break;

    case 'delete_shift':
        if (!hasPermission('manage_shifts')) {
            echo json_encode(['error' => 'No permission']);
            exit();
        }
        $shift_id = intval($_POST['shift_id']);
        $stmt = $conn->prepare("DELETE FROM shifts WHERE id = ?");
        $stmt->bind_param("i", $shift_id);
        if ($stmt->execute()) {
            logAction($conn, $_SESSION['user_id'], 'Shift Deleted', "Shift #$shift_id removed");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => $stmt->error]);
        }
        break;

    case 'bulk_attendance':
        if (!hasPermission('mark_attendance')) {
            echo json_encode(['error' => 'No permission']);
            exit();
        }
        $assignments = json_decode($_POST['assignments'], true);
        $success = 0;
        foreach ($assignments as $a) {
            $assignment_id = intval($a['assignment_id']);
            $status = $conn->real_escape_string($a['status']);
            $check_in = $conn->real_escape_string($a['check_in'] ?? '');
            $check_out = $conn->real_escape_string($a['check_out'] ?? '');
            $remarks = $conn->real_escape_string($a['remarks'] ?? '');
            $marked_by = $_SESSION['user_id'];

            // Upsert attendance
            $check = $conn->query("SELECT id FROM shift_attendance WHERE assignment_id = $assignment_id");
            if ($check && $check->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE shift_attendance SET status=?, check_in=NULLIF(?,''), check_out=NULLIF(?,''), remarks=?, marked_by=? WHERE assignment_id=?");
                $stmt->bind_param("ssssii", $status, $check_in, $check_out, $remarks, $marked_by, $assignment_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO shift_attendance (assignment_id, status, check_in, check_out, remarks, marked_by) VALUES (?, ?, NULLIF(?,''), NULLIF(?,''), ?, ?)");
                $stmt->bind_param("issssi", $assignment_id, $status, $check_in, $check_out, $remarks, $marked_by);
            }
            if ($stmt->execute()) $success++;
        }
        logAction($conn, $_SESSION['user_id'], 'Attendance Marked', "$success records updated");
        echo json_encode(['success' => true, 'updated' => $success]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
