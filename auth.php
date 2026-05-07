<?php
// auth.php - Include this after session_start() and db.php

if (!isset($_SESSION['permissions'])) {
    $_SESSION['permissions'] = [];
}

// Function to refresh permissions (call on login)
function reloadUserPermissions($conn, $user_id) {
    $_SESSION['permissions'] = []; // Reset permissions
    if (!$user_id) return;

    // Get Role ID
    $user_id = intval($user_id);
    $sql = "SELECT r.id, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = $user_id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['role_name'] = $row['role_name'];
        $role_id = $row['id'];
        
        // Get Permissions
        $permSql = "SELECT p.slug FROM permissions p 
                    JOIN role_permissions rp ON p.id = rp.permission_id 
                    WHERE rp.role_id = $role_id";
        $permResult = $conn->query($permSql);
        
        if ($permResult) {
            while($pRow = $permResult->fetch_assoc()) {
                $_SESSION['permissions'][] = $pRow['slug'];
            }
        }
    } else {
        $_SESSION['role_name'] = "No Role Assigned";
    }
}

// Helper to check permission
function notifyUser($conn, $user_id, $message, $type = 'info') {
    $message = $conn->real_escape_string($message);
    $conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($user_id, '$message', '$type')");
}

function hasPermission($slug) {
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions']) && in_array($slug, $_SESSION['permissions'])) {
        return true;
    }
    return false;
}

function logAction($conn, $user_id, $action, $details = "") {
    $action = $conn->real_escape_string($action);
    $details = $conn->real_escape_string($details);
    $conn->query("INSERT INTO audit_log (user_id, action, details) VALUES ($user_id, '$action', '$details')");
}
?>
