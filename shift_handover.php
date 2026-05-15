<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
include 'db.php';
include 'auth.php';
if (!hasPermission('log_handover')) { header("Location: shifts.php"); exit(); }

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $outgoing_shift_id = intval($_POST['outgoing_shift_id']);
    $incoming_shift_id = intval($_POST['incoming_shift_id']);
    $handover_date = $conn->real_escape_string($_POST['handover_date']);
    $handover_by = $_SESSION['user_id'];
    $summary = $conn->real_escape_string($_POST['summary']);
    $pending_tasks = $conn->real_escape_string($_POST['pending_tasks']);
    $machine_status = $conn->real_escape_string($_POST['machine_status']);
    $quality_alerts = $conn->real_escape_string($_POST['quality_alerts']);

    $stmt = $conn->prepare("INSERT INTO shift_handovers (outgoing_shift_id, incoming_shift_id, handover_date, handover_by, summary, pending_tasks, machine_status, quality_alerts) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("iissssss", $outgoing_shift_id, $incoming_shift_id, $handover_date, $handover_by, $summary, $pending_tasks, $machine_status, $quality_alerts);

    if ($stmt->execute()) {
        logAction($conn, $_SESSION['user_id'], 'Shift Handover', "Handover logged from shift #$outgoing_shift_id to #$incoming_shift_id");
        
        // Notify incoming supervisor
        $res = $conn->query("SELECT supervisor_id FROM shifts WHERE id = $incoming_shift_id");
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['supervisor_id']) {
                notifyUser($conn, $row['supervisor_id'], "New shift handover report submitted for your upcoming shift.", 'warning');
            }
        }
        
        header("Location: shifts.php?msg=Handover report logged successfully");
        exit();
    } else {
        $message = "Error logging handover: " . $stmt->error;
    }
}

$shifts = $conn->query("SELECT id, shift_name FROM shifts WHERE status='Active' ORDER BY start_time ASC");
$shift_list = [];
while($s = $shifts->fetch_assoc()) $shift_list[] = $s;
?>

<?php
$pageTitle = "Shift Handover Log";
$activePage = "shifts";
include 'layout_header.php';
?>

<div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem; font-size: 0.85rem;">
    <a href="shifts.php" style="color: var(--text-light); text-decoration: none;"><i class="fa-solid fa-clock-rotate-left"></i> Shift Management</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; color: var(--text-light);"></i>
    <span style="font-weight: 600;">Shift Handover</span>
</div>

<h2><i class="fa-solid fa-arrow-right-arrow-left" style="color: var(--primary-color);"></i> Shift Handover Report</h2>

<?php if ($message) echo "<div class='alert-badge danger' style='margin-bottom: 1.5rem;'>$message</div>"; ?>

<form method="POST" style="max-width: 800px; margin: 0; padding: 2.5rem;">
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
        <div>
            <label>Outgoing Shift</label>
            <select name="outgoing_shift_id" required>
                <option value="">— Select —</option>
                <?php foreach($shift_list as $s): ?>
                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['shift_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Incoming Shift</label>
            <select name="incoming_shift_id" required>
                <option value="">— Select —</option>
                <?php foreach($shift_list as $s): ?>
                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['shift_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Handover Date</label>
            <input type="date" name="handover_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
    </div>

    <div style="margin-top: 1.5rem;">
        <label>Work Summary</label>
        <textarea name="summary" rows="4" placeholder="Briefly describe what was accomplished during your shift..." required></textarea>
    </div>

    <div style="margin-top: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div>
            <label>Pending Tasks</label>
            <textarea name="pending_tasks" rows="3" placeholder="Tasks that need to be finished..."></textarea>
        </div>
        <div>
            <label>Machine Status</label>
            <textarea name="machine_status" rows="3" placeholder="Notes on machine health or issues..."></textarea>
        </div>
    </div>

    <div style="margin-top: 1.5rem;">
        <label style="color: var(--danger-color);"><i class="fa-solid fa-triangle-exclamation"></i> Quality Alerts</label>
        <textarea name="quality_alerts" rows="2" placeholder="Any specific quality concerns or defect trends to watch out for..."></textarea>
    </div>

    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
        <button type="submit" style="flex: 2;">Submit Handover Report</button>
        <a href="shifts.php" style="flex: 1; text-align: center; padding: 0.85rem; border: 1px solid var(--border-color); border-radius: 0.5rem; text-decoration: none; color: var(--text-color); font-weight: 600;">Cancel</a>
    </div>
</form>

<?php include 'layout_footer.php'; ?>
