<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
include 'db.php';
include 'auth.php';
if (!hasPermission('mark_attendance')) { header("Location: shifts.php"); exit(); }

$selected_shift_id = intval($_GET['shift_id'] ?? 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Fetch shift details
$shift = null;
if ($selected_shift_id) {
    $res = $conn->query("SELECT * FROM shifts WHERE id = $selected_shift_id");
    if ($res) $shift = $res->fetch_assoc();
}

// Fetch workers for this shift and date
$workers = [];
if ($selected_shift_id) {
    $sql = "SELECT sa.id as assignment_id, sa.user_id, sa.role_in_shift,
                   COALESCE(pd.full_name, u.username) as full_name,
                   att.status as attendance_status, att.check_in, att.check_out, att.remarks
            FROM shift_assignments sa
            JOIN users u ON sa.user_id = u.id
            LEFT JOIN personnel_details pd ON u.id = pd.user_id
            LEFT JOIN shift_attendance att ON sa.id = att.assignment_id
            WHERE sa.shift_id = ? AND sa.shift_date = ?
            ORDER BY u.username ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $selected_shift_id, $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $workers[] = $row;
}

// Fetch active shifts for dropdown
$shifts_res = $conn->query("SELECT id, shift_name FROM shifts WHERE status='Active'");
?>

<?php
$pageTitle = "Mark Shift Attendance";
$activePage = "shifts";
$extraHead = '
<style>
    .attendance-table td { vertical-align: middle; }
    .status-select { padding: 0.4rem; border-radius: 0.4rem; font-size: 0.85rem; width: 100%; }
</style>';
include 'layout_header.php';
?>

<div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem; font-size: 0.85rem;">
    <a href="shifts.php" style="color: var(--text-light); text-decoration: none;"><i class="fa-solid fa-clock-rotate-left"></i> Shift Management</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; color: var(--text-light);"></i>
    <span style="font-weight: 600;">Attendance</span>
</div>

<h2><i class="fa-solid fa-clipboard-check" style="color: var(--secondary-color);"></i> Shift Attendance Tracking</h2>

<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: end; background: none; box-shadow: none; padding: 0; max-width: none;">
        <div>
            <label>Select Shift</label>
            <select name="shift_id" onchange="this.form.submit()">
                <option value="">— Select Shift —</option>
                <?php while($s = $shifts_res->fetch_assoc()): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo ($selected_shift_id == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['shift_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label>Date</label>
            <input type="date" name="date" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
        </div>
        <button type="submit" class="btn-secondary" style="margin-bottom: 1.25rem;">Load Roster</button>
    </form>
</div>

<?php if ($shift): ?>
<div class="table-container">
    <table class="attendance-table">
        <thead>
            <tr>
                <th>Worker</th>
                <th>Shift Role</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody id="attendanceBody">
            <?php foreach ($workers as $w): ?>
            <tr data-assignment-id="<?php echo $w['assignment_id']; ?>">
                <td style="font-weight: 600;"><?php echo htmlspecialchars($w['full_name']); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($w['role_in_shift'] ?: 'Worker'); ?></td>
                <td>
                    <select class="status-select" onchange="markDirty(this)">
                        <option value="Absent" <?php echo $w['attendance_status']=='Absent'?'selected':''; ?>>Absent</option>
                        <option value="Present" <?php echo $w['attendance_status']=='Present'?'selected':''; ?>>Present</option>
                        <option value="Late" <?php echo $w['attendance_status']=='Late'?'selected':''; ?>>Late</option>
                        <option value="Half-Day" <?php echo $w['attendance_status']=='Half-Day'?'selected':''; ?>>Half-Day</option>
                        <option value="Leave" <?php echo $w['attendance_status']=='Leave'?'selected':''; ?>>Leave</option>
                    </select>
                </td>
                <td><input type="time" class="check-in" value="<?php echo $w['check_in']; ?>" onchange="markDirty(this)" style="margin:0; padding:0.3rem;"></td>
                <td><input type="time" class="check-out" value="<?php echo $w['check_out']; ?>" onchange="markDirty(this)" style="margin:0; padding:0.3rem;"></td>
                <td><input type="text" class="remarks" value="<?php echo htmlspecialchars($w['remarks']); ?>" onchange="markDirty(this)" style="margin:0; padding:0.3rem;" placeholder="Optional notes..."></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($workers)): ?>
            <tr><td colspan="6" class="text-center">No workers assigned to this shift for the selected date.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
    <button class="btn-primary" onclick="saveAttendance()"><i class="fa-solid fa-save"></i> Save Attendance Records</button>
</div>
<?php endif; ?>

<script>
function markDirty(el) {
    el.closest('tr').classList.add('is-dirty');
}

function saveAttendance() {
    const rows = document.querySelectorAll('tr.is-dirty');
    if (rows.length === 0) {
        showToast('No changes to save.', 'info');
        return;
    }

    const assignments = [];
    rows.forEach(row => {
        assignments.push({
            assignment_id: row.getAttribute('data-assignment-id'),
            status: row.querySelector('.status-select').value,
            check_in: row.querySelector('.check-in').value,
            check_out: row.querySelector('.check-out').value,
            remarks: row.querySelector('.remarks').value
        });
    });

    const formData = new FormData();
    formData.append('action', 'bulk_attendance');
    formData.append('assignments', JSON.stringify(assignments));

    fetch('api/shift_status.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Attendance saved successfully.');
            rows.forEach(r => r.classList.remove('is-dirty'));
        } else {
            showToast(data.error || 'Error saving attendance.', 'error');
        }
    });
}
</script>

<?php include 'layout_footer.php'; ?>
