<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
include 'db.php';
include 'auth.php';
if (!hasPermission('manage_shifts')) { header("Location: shifts.php"); exit(); }

$message = "";
$edit_mode = false;
$shift = ['shift_name'=>'','shift_type'=>'Morning','start_time'=>'06:00','end_time'=>'14:00','department'=>'','machine_ids'=>'','supervisor_id'=>'','status'=>'Active'];

// Load shift for editing
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM shifts WHERE id = $edit_id");
    if ($res && $res->num_rows > 0) {
        $shift = $res->fetch_assoc();
        $edit_mode = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shift_name = $conn->real_escape_string($_POST['shift_name']);
    $shift_type = $conn->real_escape_string($_POST['shift_type']);
    $start_time = $conn->real_escape_string($_POST['start_time']);
    $end_time = $conn->real_escape_string($_POST['end_time']);
    $department = $conn->real_escape_string($_POST['department']);
    $machine_ids = $conn->real_escape_string($_POST['machine_ids']);
    $supervisor_id = intval($_POST['supervisor_id']) ?: null;
    $status = $conn->real_escape_string($_POST['status']);

    if (isset($_POST['shift_id']) && $_POST['shift_id']) {
        // UPDATE
        $id = intval($_POST['shift_id']);
        $stmt = $conn->prepare("UPDATE shifts SET shift_name=?, shift_type=?, start_time=?, end_time=?, department=?, machine_ids=?, supervisor_id=?, status=? WHERE id=?");
        $stmt->bind_param("ssssssisi", $shift_name, $shift_type, $start_time, $end_time, $department, $machine_ids, $supervisor_id, $status, $id);
        if ($stmt->execute()) {
            logAction($conn, $_SESSION['user_id'], 'Shift Updated', "Updated shift: $shift_name");
            header("Location: shifts.php?msg=Shift updated successfully");
            exit();
        } else {
            $message = "Error: " . $stmt->error;
        }
    } else {
        // INSERT
        $created_by = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO shifts (shift_name, shift_type, start_time, end_time, department, machine_ids, supervisor_id, status, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssisi", $shift_name, $shift_type, $start_time, $end_time, $department, $machine_ids, $supervisor_id, $status, $created_by);
        if ($stmt->execute()) {
            logAction($conn, $_SESSION['user_id'], 'Shift Created', "Created shift: $shift_name");
            header("Location: shifts.php?msg=Shift created successfully");
            exit();
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}

// Fetch supervisors for dropdown
$supervisors = $conn->query("SELECT u.id, u.username, COALESCE(pd.full_name, u.username) as display_name, r.role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    LEFT JOIN personnel_details pd ON u.id = pd.user_id
    ORDER BY r.level ASC, u.username ASC");
?>

<?php
$pageTitle = $edit_mode ? "Edit Shift" : "Create New Shift";
$activePage = "shifts";
$extraHead = '
<style>
    .shift-form { max-width: 750px; margin: 0; padding: 2rem; }
    .time-preview { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 0.75rem; background: rgba(79,70,229,0.04); border: 1px solid rgba(79,70,229,0.1); margin-bottom: 1.5rem; }
    .time-preview .duration { font-size: 1.5rem; font-weight: 800; color: var(--primary-color); }
    .type-selector { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; }
    .type-option { flex: 1; padding: 1rem; border-radius: 0.75rem; border: 2px solid var(--border-color); text-align: center; cursor: pointer; transition: all 0.2s; }
    .type-option:hover { border-color: var(--primary-color); }
    .type-option.selected { border-color: var(--primary-color); background: rgba(79,70,229,0.05); }
    .type-option i { display: block; font-size: 1.3rem; margin-bottom: 0.5rem; }
    .type-option span { font-size: 0.8rem; font-weight: 700; }
</style>';
include 'layout_header.php';
?>

<!-- Breadcrumb -->
<div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem; font-size: 0.85rem;">
    <a href="shifts.php" style="color: var(--text-light); text-decoration: none;"><i class="fa-solid fa-clock-rotate-left"></i> Shift Management</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; color: var(--text-light);"></i>
    <span style="font-weight: 600;"><?php echo $edit_mode ? 'Edit Shift' : 'New Shift'; ?></span>
</div>

<div style="display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 2rem;">
    <h2 style="margin: 0;"><i class="fa-solid fa-<?php echo $edit_mode ? 'pen' : 'plus'; ?>" style="color: var(--primary-color);"></i> <?php echo $edit_mode ? 'Edit Shift Definition' : 'Create New Shift'; ?></h2>
</div>

<?php if ($message) echo "<div class='alert-badge danger' style='margin-bottom: 1.5rem;'>$message <span onclick='this.parentNode.remove()'>✕</span></div>"; ?>

<form method="POST" class="shift-form">
    <?php if ($edit_mode): ?>
    <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
    <?php endif; ?>

    <!-- Shift Type Selector -->
    <label style="margin-bottom: 0.75rem;">Shift Type</label>
    <div class="type-selector">
        <div class="type-option <?php echo $shift['shift_type']=='Morning'?'selected':''; ?>" onclick="selectType(this, 'Morning')">
            <i class="fa-solid fa-sun" style="color: #f59e0b;"></i>
            <span>Morning</span>
        </div>
        <div class="type-option <?php echo $shift['shift_type']=='Afternoon'?'selected':''; ?>" onclick="selectType(this, 'Afternoon')">
            <i class="fa-solid fa-cloud-sun" style="color: #f97316;"></i>
            <span>Afternoon</span>
        </div>
        <div class="type-option <?php echo $shift['shift_type']=='Night'?'selected':''; ?>" onclick="selectType(this, 'Night')">
            <i class="fa-solid fa-moon" style="color: #6366f1;"></i>
            <span>Night</span>
        </div>
        <div class="type-option <?php echo $shift['shift_type']=='Custom'?'selected':''; ?>" onclick="selectType(this, 'Custom')">
            <i class="fa-solid fa-sliders" style="color: #8b5cf6;"></i>
            <span>Custom</span>
        </div>
    </div>
    <input type="hidden" name="shift_type" id="shiftType" value="<?php echo htmlspecialchars($shift['shift_type']); ?>">

    <!-- Shift Name -->
    <label><i class="fa-solid fa-tag"></i> Shift Name</label>
    <input type="text" name="shift_name" value="<?php echo htmlspecialchars($shift['shift_name']); ?>" placeholder="e.g. Morning A, Night Shift B" required>

    <!-- Time Range -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div>
            <label><i class="fa-regular fa-clock"></i> Start Time</label>
            <input type="time" name="start_time" id="startTime" value="<?php echo htmlspecialchars($shift['start_time']); ?>" required onchange="updateDuration()">
        </div>
        <div>
            <label><i class="fa-regular fa-clock"></i> End Time</label>
            <input type="time" name="end_time" id="endTime" value="<?php echo htmlspecialchars($shift['end_time']); ?>" required onchange="updateDuration()">
        </div>
    </div>

    <!-- Duration Preview -->
    <div class="time-preview">
        <i class="fa-solid fa-hourglass-half" style="color: var(--primary-color); font-size: 1.2rem;"></i>
        <div>
            <div style="font-size: 0.7rem; color: var(--text-light); text-transform: uppercase; font-weight: 700;">Shift Duration</div>
            <div class="duration" id="durationDisplay">8h 00m</div>
        </div>
    </div>

    <!-- Department & Supervisor -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div>
            <label><i class="fa-solid fa-building"></i> Department</label>
            <input type="text" name="department" value="<?php echo htmlspecialchars($shift['department']); ?>" placeholder="e.g. Quality Control, Weaving">
        </div>
        <div>
            <label><i class="fa-solid fa-user-tie"></i> Supervisor</label>
            <select name="supervisor_id">
                <option value="">— Select Supervisor —</option>
                <?php while($sup = $supervisors->fetch_assoc()): ?>
                <option value="<?php echo $sup['id']; ?>" <?php echo ($shift['supervisor_id'] == $sup['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sup['display_name']); ?> (<?php echo $sup['role_name']; ?>)
                </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- Machine IDs -->
    <label><i class="fa-solid fa-gear"></i> Machine IDs <span style="font-weight: 400; color: var(--text-light); font-size: 0.8rem;">(comma-separated)</span></label>
    <input type="text" name="machine_ids" value="<?php echo htmlspecialchars($shift['machine_ids']); ?>" placeholder="e.g. MC-01, MC-02, MC-03">

    <!-- Status -->
    <label><i class="fa-solid fa-toggle-on"></i> Status</label>
    <select name="status">
        <option value="Active" <?php echo $shift['status']=='Active'?'selected':''; ?>>Active</option>
        <option value="Inactive" <?php echo $shift['status']=='Inactive'?'selected':''; ?>>Inactive</option>
    </select>

    <!-- Submit -->
    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
        <button type="submit" style="flex: 2;"><i class="fa-solid fa-check"></i> <?php echo $edit_mode ? 'Update Shift' : 'Create Shift'; ?></button>
        <a href="shifts.php" style="flex: 1; text-align: center; padding: 0.85rem; border: 1px solid var(--border-color); border-radius: 0.5rem; text-decoration: none; color: var(--text-color); font-weight: 600;">Cancel</a>
    </div>
</form>

<script>
function selectType(el, type) {
    document.querySelectorAll('.type-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('shiftType').value = type;
    // Auto-set times
    const times = { Morning: ['06:00','14:00'], Afternoon: ['14:00','22:00'], Night: ['22:00','06:00'], Custom: ['',''] };
    if (times[type][0]) {
        document.getElementById('startTime').value = times[type][0];
        document.getElementById('endTime').value = times[type][1];
        updateDuration();
    }
}

function updateDuration() {
    const start = document.getElementById('startTime').value;
    const end = document.getElementById('endTime').value;
    if (!start || !end) return;
    let [sh, sm] = start.split(':').map(Number);
    let [eh, em] = end.split(':').map(Number);
    let diff = (eh * 60 + em) - (sh * 60 + sm);
    if (diff < 0) diff += 24 * 60; // overnight
    const hours = Math.floor(diff / 60);
    const mins = diff % 60;
    document.getElementById('durationDisplay').innerText = hours + 'h ' + String(mins).padStart(2,'0') + 'm';
}
updateDuration();
</script>

<?php include 'layout_footer.php'; ?>
