<?php
include '../db.php';

// 1. Create Roles Table
$conn->query("CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    level INT DEFAULT 0,
    description TEXT
)");

// 2. Create Permissions Table
$conn->query("CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE, -- e.g. 'view_dashboard'
    description TEXT
)");

// 3. Create Role_Permissions Table (Many-to-Many)
$conn->query("CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
)");

// 4. Alter Users Table to link to Roles
// Check if role_id column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'role_id'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE users ADD CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL");
}

// 5. Seed Permissions (The "Designations")
$permissions = [
    'view_dashboard' => 'View the main dashboard',
    'view_inspections' => 'View all inspection records',
    'add_inspection' => 'Create new inspection records',
    'edit_inspection' => 'Edit existing inspection records',
    'delete_inspection' => 'Delete inspection records',
    'manage_team' => 'Access user management',
    'manage_roles' => 'Create and edit roles/permissions',
    'access_personnel' => 'View confidential personnel info',
    'export_data' => 'Export data to CSV',
    'reset_passwords' => 'Reset user passwords',
    'view_reports' => 'Access quality reports and analytics'
];

foreach ($permissions as $slug => $desc) {
    $conn->query("INSERT IGNORE INTO permissions (slug, description) VALUES ('$slug', '$desc')");
}

// 6. Seed Roles (The Hierarchy)
$roles = [
    'General Manager' => [1, 'Top level management access'],
    'Quality Manager' => [2, 'Head of Quality Department'],
    'Supervisor' => [3, 'Oversees inspectors'],
    'Senior Inspector' => [4, 'Experienced inspector'],
    'Junior Inspector' => [5, 'Entry level inspector']
];

foreach ($roles as $name => $data) {
    $level = $data[0];
    $desc = $data[1];
    $conn->query("INSERT INTO roles (role_name, level, description) VALUES ('$name', $level, '$desc') ON DUPLICATE KEY UPDATE level=$level, description='$desc'");
}

// 7. Assign Permissions to Roles (The "Designation Logic")
function assignPerms($conn, $roleName, $perms) {
    $roleRes = $conn->query("SELECT id FROM roles WHERE role_name='$roleName'");
    if ($roleRes->num_rows > 0) {
        $roleId = $roleRes->fetch_assoc()['id'];
        foreach ($perms as $permSlug) {
            $permRes = $conn->query("SELECT id FROM permissions WHERE slug='$permSlug'");
            if ($permRes->num_rows > 0) {
                $permId = $permRes->fetch_assoc()['id'];
                $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES ($roleId, $permId)");
            }
        }
    }
}

// General Manager (Everything)
assignPerms($conn, 'General Manager', array_keys($permissions));

// Quality Manager (Everything)
assignPerms($conn, 'Quality Manager', array_keys($permissions));

// Supervisor
assignPerms($conn, 'Supervisor', ['view_dashboard', 'view_inspections', 'add_inspection', 'edit_inspection', 'export_data', 'view_reports']);

// Senior Inspector
assignPerms($conn, 'Senior Inspector', ['view_dashboard', 'view_inspections', 'add_inspection', 'edit_inspection']);

// Junior Inspector
assignPerms($conn, 'Junior Inspector', ['view_dashboard', 'view_inspections', 'add_inspection']);

// 8. DATA MIGRATION: Map old ENUM roles to new IDs
$qm_res = $conn->query("SELECT id FROM roles WHERE role_name='Quality Manager'");
$insp_res = $conn->query("SELECT id FROM roles WHERE role_name='Senior Inspector'");

if ($qm_res->num_rows > 0 && $insp_res->num_rows > 0) {
    $qm_id = $qm_res->fetch_assoc()['id'];
    $insp_id = $insp_res->fetch_assoc()['id'];

    $conn->query("UPDATE users SET role_id = $qm_id WHERE role = 'Quality Manager' OR role = 'Manager' OR role = 'admin'");
    $conn->query("UPDATE users SET role_id = $insp_id WHERE role = 'Inspection Team' OR role = 'Inspector' OR role = 'supervisor'");
}

echo "<div style='font-family: sans-serif; padding: 2rem; text-align: center;'>
    <h2 style='color: #059669;'>✅ RBAC Migration Complete</h2>
    <p>Tables created, roles seeded, and users migrated successfully.</p>
    <a href='index.php' style='display: inline-block; margin-top: 1rem; padding: 0.5rem 1.5rem; background: #4f46e5; color: white; text-decoration: none; border-radius: 0.375rem;'>Go to Login Page</a>
</div>";
?>
