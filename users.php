<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db.php';
include 'auth.php';

// Access Control
if (!hasPermission('manage_team')) {
    header("Location: dashboard.php");
    exit();
}

$message = "";

// Handle Actions (Add, Update, Reset Password, Delete)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ADD NEW USER
    if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $role_id = $_POST['role_id'];

        if (!empty($username) && !empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, role_id) VALUES ('$username', '$hashed_password', '$role_id')";
            if ($conn->query($sql) === TRUE) {
                $message = "Team member '$username' onboarded successfully.";
            } else {
                $message = "Error creating user: " . $conn->error;
            }
        }
    }

    // UPDATE USER (Role and/or Password)
    if (isset($_POST['action']) && $_POST['action'] == 'update_user') {
        $user_id = intval($_POST['user_id']);
        $new_role_id = $_POST['role_id'];
        $new_password = $_POST['new_password'];
        
        $updates = ["role_id = $new_role_id"];
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updates[] = "password = '$hashed_password'";
        }
        
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = $user_id";
        if ($conn->query($sql)) {
            $message = "Profile updated successfully.";
        } else {
            $message = "Error updating profile: " . $conn->error;
        }
    }
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id=$id");
        header("Location: users.php?msg=Member removed");
        exit();
    } else {
        $message = "Critical: You cannot remove your own administrative access.";
    }
}

// Fetch Users with Role Details
$result = $conn->query("SELECT u.id, u.username, u.role_id, r.role_name, r.level, p.full_name 
                        FROM users u 
                        LEFT JOIN roles r ON u.role_id = r.id 
                        LEFT JOIN personnel_details p ON u.id = p.user_id
                        ORDER BY r.level ASC, u.username ASC");

// Fetch Roles for Dropdown
$roles_res = $conn->query("SELECT id, role_name, level FROM roles ORDER BY level ASC");
$roles_list = [];
while($r = $roles_res->fetch_assoc()) $roles_list[] = $r;
?>

<?php
$pageTitle = "Team Control";
$activePage = "users";
$extraHead = '
<style>
    .user-card {
        transition: all 0.3s ease;
        border-top: 4px solid var(--border-color);
        position: relative;
    }
    .user-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .user-card.level-1 { border-top-color: #ef4444; }
    .user-card.level-2 { border-top-color: #f59e0b; }
    .user-card.level-3 { border-top-color: #3b82f6; }
    
    .avatar-lg {
        width: 64px; height: 64px; border-radius: 50%;
        background: var(--bg-color); color: var(--primary-color);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; font-weight: 800; border: 2px solid var(--border-color);
    }
    .status-indicator {
        width: 10px; height: 10px; border-radius: 50%; background: #10b981;
        position: absolute; bottom: 5px; right: 5px; border: 2px solid white;
    }
</style>
';
include 'layout_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2 style="margin: 0;">Team Control Center</h2>
        <p style="color: var(--text-light); margin-top: 0.5rem;">Manage identities, access levels, and security credentials.</p>
    </div>
    <button onclick="openAddModal()" class="btn-primary" style="width: auto;"><i class="fa-solid fa-user-plus"></i> Add Team Member</button>
</div>

<?php if ($message) echo "<div class='alert-badge info' style='margin-bottom: 2rem;'>$message <span onclick='this.parentNode.remove()'>✕</span></div>"; ?>

<div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
    <?php while($user = $result->fetch_assoc()): ?>
    <div class="card user-card level-<?php echo $user['level']; ?>">
        <div style="display: flex; gap: 1.25rem; align-items: center;">
            <div style="position: relative;">
                <div class="avatar-lg"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                <div class="status-indicator"></div>
            </div>
            <div style="flex: 1;">
                <h3 style="margin: 0;"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h3>
                <span style="font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">
                    <?php echo htmlspecialchars($user['role_name']); ?> (L-<?php echo $user['level']; ?>)
                </span>
            </div>
        </div>

        <div style="margin-top: 1.5rem; background: var(--bg-color); padding: 1rem; border-radius: 0.5rem; font-size: 0.85rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="color: var(--text-light);">Username</span>
                <span style="font-weight: 600;">@<?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-light);">Account Status</span>
                <span style="color: #10b981; font-weight: 600;">Active</span>
            </div>
        </div>

        <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
            <button onclick='openEditModal(<?php echo json_encode($user); ?>)' class="btn-secondary" style="flex: 1; font-size: 0.8rem; padding: 0.5rem;"><i class="fa-solid fa-user-gear"></i> Manage</button>
            <?php if($user['id'] != $_SESSION['user_id']): ?>
                <button onclick="confirmUserDelete(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>')" class="btn-secondary" style="color: var(--danger-color); border-color: #fecaca; width: auto;"><i class="fa-solid fa-trash-can"></i></button>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<!-- Unified User Modal -->
<div id="userModal" class="modal-overlay" style="display:none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal-content" style="background: var(--card-bg); padding: 2.5rem; width: 100%; max-width: 500px; border-radius: 1.25rem; box-shadow: var(--shadow-2xl);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 id="modalTitle" style="margin: 0;">Onboard New Member</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">✕</button>
        </div>
        
        <form method="POST" id="userForm" style="box-shadow: none; padding: 0; background: none; margin: 0;">
            <input type="hidden" name="action" value="add_user" id="formAction">
            <input type="hidden" name="user_id" id="modalUserId">
            
            <div id="usernameSection">
                <label>Username</label>
                <input type="text" name="username" id="modalUsername" required placeholder="e.g. john_doe">
            </div>

            <div style="margin-top: 1.5rem;">
                <label>Access Role / Designation</label>
                <select name="role_id" id="modalRoleId" required>
                    <?php foreach($roles_list as $role): ?>
                        <option value="<?php echo $role['id']; ?>">L-<?php echo $role['level']; ?>: <?php echo htmlspecialchars($role['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top: 1.5rem;">
                <label id="passLabel">Initial Password</label>
                <div style="position: relative;">
                    <input type="password" name="password" id="modalPassword" placeholder="Minimum 8 characters" style="padding-right: 2.5rem;">
                    <i class="fa-solid fa-eye" id="togglePassword" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-light);"></i>
                </div>
                <p id="passHint" style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.5rem;">Leave blank to keep existing password when editing.</p>
            </div>

            <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn-primary" style="flex: 2;">Confirm Member Data</button>
                <button type="button" onclick="closeModal()" class="btn-secondary" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerText = "Onboard New Member";
        document.getElementById('formAction').value = "add_user";
        document.getElementById('modalUserId').value = "";
        document.getElementById('modalUsername').value = "";
        document.getElementById('modalUsername').disabled = false;
        document.getElementById('passLabel').innerText = "Initial Password";
        document.getElementById('modalPassword').required = true;
        document.getElementById('passHint').style.display = "none";
        document.getElementById('userModal').style.display = 'flex';
    }

    function openEditModal(user) {
        document.getElementById('modalTitle').innerText = "Configure Identity: " + user.username;
        document.getElementById('formAction').value = "update_user";
        document.getElementById('modalUserId').value = user.id;
        document.getElementById('modalUsername').value = user.username;
        document.getElementById('modalUsername').disabled = true; // Username locked for safety
        document.getElementById('modalRoleId').value = user.role_id;
        document.getElementById('passLabel').innerText = "Reset Password (Optional)";
        document.getElementById('modalPassword').required = false;
        document.getElementById('passHint').style.display = "block";
        document.getElementById('userModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    function confirmUserDelete(id, name) {
        if (confirm("Are you sure you want to remove '" + name + "' from the system? Their access will be immediately revoked.")) {
            window.location.href = "users.php?delete=" + id;
        }
    }

    // Password Visibility Toggle
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#modalPassword');

    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        // toggle the eye icon
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    window.onclick = function(event) {
        if (event.target.className === 'modal-overlay') closeModal();
    }
</script>

<?php include 'layout_footer.php'; ?>
