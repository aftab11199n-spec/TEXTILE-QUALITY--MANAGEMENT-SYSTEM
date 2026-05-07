<?php
session_start();
include 'db.php';
include 'auth.php';

// Force reload permissions if not set
if (!isset($_SESSION['permissions']) || empty($_SESSION['permissions'])) {
    if (isset($_SESSION['user_id'])) reloadUserPermissions($conn, $_SESSION['user_id']);
}

// Access Control
if (!hasPermission('manage_roles')) {
    header("Location: dashboard.php");
    exit();
}

$message = "";

// Handle Delete
if (isset($_GET['delete'])) {
    $role_id = intval($_GET['delete']);
    // Check if role is in use
    $check = $conn->query("SELECT id FROM users WHERE role_id = $role_id");
    if ($check->num_rows > 0) {
        $message = "Error: This designation is currently assigned to " . $check->num_rows . " user(s) and cannot be deleted.";
    } else {
        $conn->query("DELETE FROM roles WHERE id = $role_id");
        $conn->query("DELETE FROM role_permissions WHERE role_id = $role_id");
        $message = "Designation deleted successfully.";
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role_name = $conn->real_escape_string($_POST['role_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $level = intval($_POST['level'] ?? 0);
    $perms = $_POST['permissions'] ?? [];

    if (isset($_POST['action']) && $_POST['action'] == 'add_role') {
        $conn->query("INSERT INTO roles (role_name, description, level) VALUES ('$role_name', '$description', $level)");
        $role_id = $conn->insert_id;
        foreach ($perms as $perm_id) {
            $conn->query("INSERT INTO role_permissions (role_id, permission_id) VALUES ($role_id, $perm_id)");
        }
        $message = "New designation '$role_name' created successfully.";
    }

    if (isset($_POST['action']) && $_POST['action'] == 'edit_role') {
        $role_id = intval($_POST['role_id']);
        $conn->query("UPDATE roles SET role_name='$role_name', description='$description', level=$level WHERE id=$role_id");
        $conn->query("DELETE FROM role_permissions WHERE role_id=$role_id");
        foreach ($perms as $perm_id) {
            $conn->query("INSERT INTO role_permissions (role_id, permission_id) VALUES ($role_id, $perm_id)");
        }
        $message = "Designation updated successfully.";
    }
}

// Fetch Data
$roles = $conn->query("SELECT * FROM roles ORDER BY level ASC");
$all_perms = $conn->query("SELECT * FROM permissions");
$permissions_array = [];
while ($p = $all_perms->fetch_assoc()) $permissions_array[] = $p;

$role_perms_map = [];
$rp_res = $conn->query("SELECT * FROM role_permissions");
while ($row = $rp_res->fetch_assoc()) {
    $role_perms_map[$row['role_id']][] = $row['permission_id'];
}

function prettifySlug($slug) {
    $wording = [
        'view_dashboard' => ['label' => 'Dashboard Access', 'icon' => 'fa-gauge-high', 'group' => 'Core'],
        'view_inspections' => ['label' => 'View Records', 'icon' => 'fa-list-check', 'group' => 'Inspections'],
        'add_inspection' => ['label' => 'Create Records', 'icon' => 'fa-plus-circle', 'group' => 'Inspections'],
        'edit_inspection' => ['label' => 'Modify Records', 'icon' => 'fa-pen-to-square', 'group' => 'Inspections'],
        'delete_inspection' => ['label' => 'Delete Records', 'icon' => 'fa-trash-can', 'group' => 'Inspections'],
        'manage_team' => ['label' => 'User Management', 'icon' => 'fa-users-gear', 'group' => 'Team'],
        'manage_roles' => ['label' => 'System Hierarchy', 'icon' => 'fa-shield-halved', 'group' => 'Team'],
        'access_personnel' => ['label' => 'HR Directory', 'icon' => 'fa-id-card', 'group' => 'Team'],
        'export_data' => ['label' => 'Data Export', 'icon' => 'fa-file-export', 'group' => 'System'],
        'reset_passwords' => ['label' => 'Security Reset', 'icon' => 'fa-key', 'group' => 'System']
    ];
    return $wording[$slug] ?? ['label' => ucwords(str_replace('_', ' ', $slug)), 'icon' => 'fa-circle-dot', 'group' => 'Other'];
}
?>

<?php
$pageTitle = "Structure & Access";
$activePage = "roles";
$extraHead = '
<style>
    .role-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
    .role-card { position: relative; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-top: 5px solid var(--primary-color); }
    .role-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-2xl); }
    .level-badge { position: absolute; top: -12px; right: 20px; background: var(--primary-color); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; }
    
    .perm-chip { display: inline-flex; align-items: center; gap: 0.4rem; padding: 4px 10px; background: #f1f5f9; border-radius: 6px; font-size: 0.75rem; color: #475569; border: 1px solid #e2e8f0; }
    .perm-chip i { font-size: 0.7rem; color: var(--primary-color); }
    
    .group-header { font-size: 0.8rem; font-weight: 800; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.05em; margin: 1.5rem 0 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
    .group-header::after { content: ""; flex: 1; height: 1px; background: var(--border-color); }
</style>
';
include 'layout_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem;">
    <div>
        <h2 style="margin: 0;">System Hierarchy & Access</h2>
        <p style="color: var(--text-light); margin: 0.5rem 0 0;">Configure designations. <strong>Level 1 (CEO/GM)</strong> is top management.</p>
    </div>
    <button onclick="openAddModal()" class="btn-primary" style="width: auto;"><i class="fa-solid fa-plus-circle"></i> Create New Designation</button>
</div>

<?php if ($message) echo "<div class='alert-badge success' style='margin-bottom: 2rem;'>$message <span onclick='this.parentNode.remove()'>✕</span></div>"; ?>

<div class="role-grid">
    <?php while($role = $roles->fetch_assoc()): ?>
    <div class="card role-card">
        <div class="level-badge">L-<?php echo $role['level']; ?></div>
        
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-color);"><?php echo htmlspecialchars($role['role_name']); ?></h3>
            <div class="flex gap-0-5">
                <button class="btn-secondary" style="padding: 4px 12px; width: auto; font-size: 0.8rem;" onclick='openEdit(<?php echo json_encode($role); ?>)'>Configure</button>
                <button class="btn-secondary" style="padding: 4px 12px; width: auto; font-size: 0.8rem; color: var(--danger-color); border-color: #fecaca;" onclick="confirmDelete(<?php echo $role['id']; ?>, '<?php echo $role['role_name']; ?>')">Delete</button>
            </div>
        </div>
        
        <p style="color: var(--text-light); font-size: 0.9rem; margin: 1rem 0 1.5rem; line-height: 1.5; height: 3rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
            <?php echo htmlspecialchars($role['description'] ?: 'No description provided for this role.'); ?>
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <?php 
            if (isset($role_perms_map[$role['id']])) {
                foreach ($role_perms_map[$role['id']] as $pid) {
                    foreach ($permissions_array as $pa) {
                        if ($pa['id'] == $pid) {
                            $info = prettifySlug($pa['slug']);
                            echo "<span class='perm-chip'><i class='fa-solid {$info['icon']}'></i> {$info['label']}</span>";
                        }
                    }
                }
            } else {
                echo "<div style='background: #fff7ed; color: #9a3412; padding: 0.75rem; width: 100%; border-radius: 0.5rem; font-size: 0.85rem; border: 1px solid #ffedd5;'>No active privileges assigned.</div>";
            }
            ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<!-- Add/Edit Modal -->
<div id="roleModal" class="modal-overlay" style="display:none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal-content" style="background: var(--card-bg); padding: 2.5rem; width: 100%; max-width: 650px; border-radius: 1.25rem; box-shadow: var(--shadow-2xl); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 id="modalTitle" style="margin: 0;">Designation Configuration</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">✕</button>
        </div>
        
        <form method="POST" id="roleForm" style="box-shadow: none; padding: 0; background: none; margin: 0;">
            <input type="hidden" name="action" value="add_role" id="formAction">
            <input type="hidden" name="role_id" id="roleId">
            
            <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label>Designation Name</label>
                    <input type="text" name="role_name" id="roleName" required placeholder="e.g. Quality Supervisor">
                </div>
                <div>
                    <label>Hierarchy Level</label>
                    <input type="number" name="level" id="roleLevel" value="0" min="0" max="99">
                </div>
            </div>
            
            <label>Purpose / Description</label>
            <input type="text" name="description" id="roleDesc" placeholder="Briefly describe the scope of this role..." style="margin-bottom: 1.5rem;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <label style="margin: 0;">Access Privileges</label>
                <div class="flex gap-1">
                    <button type="button" onclick="toggleAllPerms(true)" style="font-size: 0.7rem; background: none; border: 1px solid var(--border-color); padding: 2px 8px; border-radius: 4px; cursor: pointer;">Select All</button>
                    <button type="button" onclick="toggleAllPerms(false)" style="font-size: 0.7rem; background: none; border: 1px solid var(--border-color); padding: 2px 8px; border-radius: 4px; cursor: pointer;">Clear</button>
                </div>
            </div>

            <div id="permissionSelector">
                <?php 
                $groups = ['Core', 'Inspections', 'Team', 'System', 'Other'];
                foreach ($groups as $group): 
                    $hasGroupItems = false;
                    foreach ($permissions_array as $p) { if (prettifySlug($p['slug'])['group'] == $group) $hasGroupItems = true; }
                    if (!$hasGroupItems) continue;
                ?>
                    <div class="group-header"><?php echo $group; ?> Privileges</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <?php foreach ($permissions_array as $p): 
                            $info = prettifySlug($p['slug']);
                            if ($info['group'] != $group) continue;
                        ?>
                        <label style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; cursor: pointer; transition: background 0.2s;">
                            <input type="checkbox" name="permissions[]" value="<?php echo $p['id']; ?>" id="perm_<?php echo $p['id']; ?>" class="perm-checkbox" style="width: auto;">
                            <span style="font-size: 0.85rem;"><i class="fa-solid <?php echo $info['icon']; ?>" style="color: var(--primary-color); width: 20px;"></i> <?php echo $info['label']; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 3rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn-primary" style="flex: 2;">Save Configuration</button>
                <button type="button" onclick="closeModal()" class="btn-secondary" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    var rolePermsMap = <?php echo json_encode($role_perms_map); ?>;

    function openAddModal() {
        document.getElementById('modalTitle').innerText = "New Designation";
        document.getElementById('formAction').value = "add_role";
        document.getElementById('roleId').value = "";
        document.getElementById('roleName').value = "";
        document.getElementById('roleLevel').value = "10"; // Default low priority
        document.getElementById('roleDesc').value = "";
        toggleAllPerms(false);
        document.getElementById('roleModal').style.display = 'flex';
    }

    // Automatic Level Detection
    document.getElementById('roleName').addEventListener('input', function(e) {
        if (document.getElementById('formAction').value !== 'add_role') return; // Only auto-level for NEW roles
        
        const name = e.target.value.toLowerCase();
        let level = 10; // Default

        const mapping = [
            { keywords: ['ceo', 'owner', 'president', 'chairman', 'director', 'gm', 'general manager'], level: 1 },
            { keywords: ['manager', 'head of', 'lead', 'chief'], level: 2 },
            { keywords: ['supervisor', 'sr', 'senior', 'executive'], level: 3 },
            { keywords: ['assistant', 'jr', 'junior', 'officer'], level: 4 },
            { keywords: ['inspector', 'operator', 'staff', 'worker', 'helper'], level: 5 }
        ];

        for (const map of mapping) {
            if (map.keywords.some(k => name.includes(k))) {
                level = map.level;
                break;
            }
        }
        document.getElementById('roleLevel').value = level;
    });

    function openEdit(role) {
        document.getElementById('modalTitle').innerText = "Configure " + role.role_name;
        document.getElementById('formAction').value = "edit_role";
        document.getElementById('roleId').value = role.id;
        document.getElementById('roleName').value = role.role_name;
        document.getElementById('roleLevel').value = role.level;
        document.getElementById('roleDesc').value = role.description;
        
        toggleAllPerms(false);
        if (rolePermsMap[role.id]) {
            rolePermsMap[role.id].forEach(pid => {
                var cb = document.getElementById('perm_' + pid);
                if (cb) cb.checked = true;
            });
        }
        document.getElementById('roleModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('roleModal').style.display = 'none';
    }

    function toggleAllPerms(check) {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = check);
    }

    function confirmDelete(id, name) {
        if (confirm("Are you sure you want to delete the designation '" + name + "'? This action cannot be undone.")) {
            window.location.href = "roles.php?delete=" + id;
        }
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('roleModal')) closeModal();
    }
</script>

<?php include 'layout_footer.php'; ?>
