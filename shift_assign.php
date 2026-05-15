<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
include 'db.php';
include 'auth.php';
if (!hasPermission('assign_shifts')) { header("Location: shifts.php"); exit(); }

$message = "";

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shift_id = intval($_POST['shift_id']);
    $shift_date = $conn->real_escape_string($_POST['shift_date']);
    $user_ids = $_POST['user_ids'] ?? [];
    $roles = $_POST['roles'] ?? [];
    $notes = $_POST['notes'] ?? [];
    $assigned_by = $_SESSION['user_id'];

    $success = 0;
    $errors = 0;
    foreach ($user_ids as $idx => $uid) {
        $uid = intval($uid);
        $role = $conn->real_escape_string($roles[$idx] ?? '');
        $note = $conn->real_escape_string($notes[$idx] ?? '');

        $stmt = $conn->prepare("INSERT INTO shift_assignments (shift_id, user_id, shift_date, role_in_shift, notes, assigned_by) 
                                VALUES (?, ?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE role_in_shift=VALUES(role_in_shift), notes=VALUES(notes), assigned_by=VALUES(assigned_by)");
        $stmt->bind_param("iisssi", $shift_id, $uid, $shift_date, $role, $note, $assigned_by);
        if ($stmt->execute()) {
            $success++;
            // Notify worker
            notifyUser($conn, $uid, "You have been assigned to a shift on $shift_date.", 'info');
        } else {
            $errors++;
        }
    }

    logAction($conn, $_SESSION['user_id'], 'Workers Assigned', "$success workers assigned to shift #$shift_id on $shift_date");
    $message = "$success worker(s) assigned successfully." . ($errors ? " $errors failed." : "");
}

// Get selected shift
$selected_shift_id = intval($_GET['shift_id'] ?? $_POST['shift_id'] ?? 0);
$selected_date = $_GET['date'] ?? $_POST['shift_date'] ?? date('Y-m-d');

// Fetch all shifts for dropdown
$all_shifts = $conn->query("SELECT id, shift_name, shift_type, start_time, end_time, department FROM shifts WHERE status='Active' ORDER BY start_time ASC");
$shift_list = [];
while ($r = $all_shifts->fetch_assoc()) $shift_list[] = $r;

// Fetch available workers (all users)
$workers_sql = "SELECT u.id, u.username, COALESCE(pd.full_name, u.username) as full_name, r.role_name
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN personnel_details pd ON u.id = pd.user_id
                ORDER BY r.level ASC, u.username ASC";
$workers_res = $conn->query($workers_sql);
$all_workers = [];
while ($w = $workers_res->fetch_assoc()) $all_workers[] = $w;

// Fetch already assigned for selected shift+date
$assigned_ids = [];
if ($selected_shift_id) {
    $a_res = $conn->query("SELECT user_id FROM shift_assignments WHERE shift_id = $selected_shift_id AND shift_date = '$selected_date'");
    while ($a = $a_res->fetch_assoc()) $assigned_ids[] = $a['user_id'];
}
?>

<?php
$pageTitle = "Assign Workers to Shift";
$activePage = "shifts";
$extraHead = '
<style>
    .worker-row { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 1px solid var(--border-color); border-radius: 0.75rem; margin-bottom: 0.75rem; transition: all 0.2s; background: var(--card-bg); }
    .worker-row:hover { border-color: var(--primary-color); }
    .worker-row.already-assigned { background: rgba(16,185,129,0.04); border-color: rgba(16,185,129,0.3); }
    .worker-check { width: 20px; height: 20px; accent-color: var(--primary-color); cursor: pointer; margin: 0; }
    .shift-selector { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .shift-pill { padding: 0.6rem 1.2rem; border-radius: 9999px; border: 2px solid var(--border-color); cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: all 0.2s; text-decoration: none; color: var(--text-color); }
    .shift-pill:hover { border-color: var(--primary-color); }
    .shift-pill.active { border-color: var(--primary-color); background: var(--primary-color); color: white; }
</style>';
include 'layout_header.php';
?>

<div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem; font-size: 0.85rem;">
    <a href="shifts.php" style="color: var(--text-light); text-decoration: none;"><i class="fa-solid fa-clock-rotate-left"></i> Shift Management</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; color: var(--text-light);"></i>
    <span style="font-weight: 600;">Assign Workers</span>
</div>

<h2 style="margin: 0 0 0.5rem;"><i class="fa-solid fa-user-plus" style="color: var(--primary-color);"></i> Assign Workers to Shift</h2>
<p style="color: var(--text-light); margin-bottom: 2rem;">Select a shift, pick a date, and check the workers you want to assign.</p>

<?php if ($message) echo "<div class='alert-badge success' style='margin-bottom: 1.5rem;'><i class='fa-solid fa-check-circle'></i> $message <span onclick='this.parentNode.remove()'>✕</span></div>"; ?>

<!-- Shift + Date Selection -->
<div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: end;">
        <div>
            <label><i class="fa-solid fa-clock"></i> Select Shift</label>
            <select id="shiftSelect" onchange="updateSelection()" style="margin-bottom: 0;">
                <option value="">— Choose a shift —</option>
                <?php foreach ($shift_list as $sl): ?>
                <option value="<?php echo $sl['id']; ?>" <?php echo ($selected_shift_id == $sl['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sl['shift_name']); ?> (<?php echo $sl['shift_type']; ?> · <?php echo date('h:i A', strtotime($sl['start_time'])); ?>–<?php echo date('h:i A', strtotime($sl['end_time'])); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><i class="fa-solid fa-calendar"></i> Date</label>
            <input type="date" id="dateSelect" value="<?php echo $selected_date; ?>" onchange="updateSelection()" style="margin-bottom: 0;">
        </div>
    </div>
</div>

<!-- Worker Assignment Form -->
<form method="POST" id="assignForm">
    <input type="hidden" name="shift_id" id="formShiftId" value="<?php echo $selected_shift_id; ?>">
    <input type="hidden" name="shift_date" id="formDate" value="<?php echo $selected_date; ?>">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="margin: 0; font-size: 1rem;"><i class="fa-solid fa-users"></i> Available Workers (<?php echo count($all_workers); ?>)</h3>
        <div class="flex gap-1" style="font-size: 0.8rem;">
            <button type="button" onclick="selectAll()" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">Select All</button>
            <button type="button" onclick="deselectAll()" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: var(--bg-color); color: var(--text-color); border: 1px solid var(--border-color);">Deselect All</button>
        </div>
    </div>

    <!-- Search -->
    <div style="position: relative; margin-bottom: 1.5rem;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-light);"></i>
        <input type="text" id="workerSearch" placeholder="Search workers..." style="padding-left: 3rem; margin: 0;">
    </div>

    <div id="workerList">
    <?php foreach ($all_workers as $idx => $w): 
        $is_assigned = in_array($w['id'], $assigned_ids);
    ?>
    <div class="worker-row <?php echo $is_assigned ? 'already-assigned' : ''; ?>" data-search="<?php echo strtolower($w['full_name'] . ' ' . $w['username'] . ' ' . $w['role_name']); ?>">
        <input type="checkbox" class="worker-check" name="user_ids[]" value="<?php echo $w['id']; ?>" <?php echo $is_assigned ? 'checked' : ''; ?>>
        <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--bg-color); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; color: var(--primary-color); border: 1px solid var(--border-color);">
            <?php echo strtoupper(substr($w['username'], 0, 1)); ?>
        </div>
        <div style="flex: 1;">
            <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($w['full_name']); ?></div>
            <div style="font-size: 0.7rem; color: var(--text-light);">@<?php echo htmlspecialchars($w['username']); ?> · <?php echo htmlspecialchars($w['role_name']); ?></div>
        </div>
        <input type="text" name="roles[]" placeholder="Role in shift" value="<?php echo $is_assigned ? 'Assigned' : ''; ?>" style="width: 140px; margin: 0; padding: 0.4rem 0.6rem; font-size: 0.8rem;">
        <input type="hidden" name="notes[]" value="">
        <?php if ($is_assigned): ?>
        <span style="font-size: 0.65rem; font-weight: 700; color: #10b981; background: #d1fae5; padding: 0.2rem 0.5rem; border-radius: 4px;">ASSIGNED</span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
        <button type="submit" style="flex: 2;"><i class="fa-solid fa-check"></i> Save Assignments</button>
        <a href="shifts.php" style="flex: 1; text-align: center; padding: 0.85rem; border: 1px solid var(--border-color); border-radius: 0.5rem; text-decoration: none; color: var(--text-color); font-weight: 600;">Cancel</a>
    </div>
</form>

<script>
function updateSelection() {
    const shiftId = document.getElementById('shiftSelect').value;
    const date = document.getElementById('dateSelect').value;
    if (shiftId && date) {
        window.location.href = 'shift_assign.php?shift_id=' + shiftId + '&date=' + date;
    }
}

function selectAll() {
    document.querySelectorAll('.worker-check').forEach(cb => cb.checked = true);
}
function deselectAll() {
    document.querySelectorAll('.worker-check').forEach(cb => cb.checked = false);
}

// Search
document.getElementById('workerSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.worker-row').forEach(row => {
        row.style.display = row.getAttribute('data-search').includes(term) ? 'flex' : 'none';
    });
});
</script>

<?php include 'layout_footer.php'; ?>
