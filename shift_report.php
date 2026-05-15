<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
include 'db.php';
include 'auth.php';
if (!hasPermission('view_shift_reports')) { header("Location: shifts.php"); exit(); }

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 1. Attendance Summary
$att_sql = "SELECT att.status, COUNT(*) as count 
            FROM shift_attendance att 
            JOIN shift_assignments sa ON att.assignment_id = sa.id 
            WHERE sa.shift_date BETWEEN '$start_date' AND '$end_date'
            GROUP BY att.status";
$att_res = $conn->query($att_sql);
$att_stats = [];
while($row = $att_res->fetch_assoc()) $att_stats[$row['status']] = $row['count'];

// 2. Productivity by Shift
$prod_sql = "SELECT s.shift_name, COUNT(i.id) as inspection_count 
             FROM shifts s 
             LEFT JOIN inspections i ON i.inspection_date BETWEEN '$start_date' AND '$end_date' 
             -- Note: This is a simple join, in a real app you'd join by time/machine
             GROUP BY s.id";
// Since we don't have a direct shift_id in inspections yet, we'll mock this for now
// or use a correlated subquery if we had the logic.
$prod_res = $conn->query("SELECT shift_name, id FROM shifts");
$productivity = [];
while($row = $prod_res->fetch_assoc()) {
    $productivity[] = ['label' => $row['shift_name'], 'value' => rand(20, 100)]; // Mocking productivity for demo
}

// 3. Handover History
$hand_sql = "SELECT h.*, s1.shift_name as out_name, s2.shift_name as in_name, u.username as supervisor 
             FROM shift_handovers h 
             JOIN shifts s1 ON h.outgoing_shift_id = s1.id 
             JOIN shifts s2 ON h.incoming_shift_id = s2.id 
             JOIN users u ON h.handover_by = u.id 
             WHERE h.handover_date BETWEEN '$start_date' AND '$end_date' 
             ORDER BY h.handover_date DESC";
$hand_res = $conn->query($hand_sql);
?>

<?php
$pageTitle = "Shift Analytics & Reports";
$activePage = "shifts";
$extraHead = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
';
include 'layout_header.php';
?>

<div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem; font-size: 0.85rem;">
    <a href="shifts.php" style="color: var(--text-light); text-decoration: none;"><i class="fa-solid fa-clock-rotate-left"></i> Shift Management</a>
    <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem; color: var(--text-light);"></i>
    <span style="font-weight: 600;">Reports</span>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h2><i class="fa-solid fa-chart-line" style="color: var(--primary-color);"></i> Shift Analytics & Productivity</h2>
    <form method="GET" class="flex gap-1" style="background:none; box-shadow:none; padding:0; margin:0; max-width:none;">
        <input type="date" name="start_date" value="<?php echo $start_date; ?>" style="margin:0; width:auto;">
        <input type="date" name="end_date" value="<?php echo $end_date; ?>" style="margin:0; width:auto;">
        <button type="submit" class="btn-secondary" style="width:auto;">Filter</button>
    </form>
</div>

<div class="card-grid" style="grid-template-columns: 1.5fr 1fr; gap: 1.5rem;">
    <!-- Attendance Distribution -->
    <div class="card">
        <h3>Attendance Distribution</h3>
        <div style="height: 300px;">
            <canvas id="attendanceChart"></canvas>
        </div>
    </div>

    <!-- Productivity Comparison -->
    <div class="card">
        <h3>Productivity (Mock Data)</h3>
        <div style="height: 300px;">
            <canvas id="productivityChart"></canvas>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <h3 style="margin-bottom: 1.5rem;">Handover Log History</h3>
    <div class="table-container" style="margin-top: 0;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>From → To</th>
                    <th>Supervisor</th>
                    <th>Summary</th>
                    <th>Quality Alerts</th>
                </tr>
            </thead>
            <tbody>
                <?php while($h = $hand_res->fetch_assoc()): ?>
                <tr>
                    <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($h['handover_date'])); ?></td>
                    <td><span class="status status-on-hold" style="font-size: 0.75rem;"><?php echo htmlspecialchars($h['out_name']); ?></span> <i class="fa-solid fa-arrow-right" style="font-size: 0.7rem;"></i> <span class="status status-passed" style="font-size: 0.75rem;"><?php echo htmlspecialchars($h['in_name']); ?></span></td>
                    <td><strong><?php echo htmlspecialchars($h['supervisor']); ?></strong></td>
                    <td><div style="max-width: 300px; font-size: 0.85rem; color: var(--text-light);" class="text-truncate"><?php echo htmlspecialchars($h['summary']); ?></div></td>
                    <td>
                        <?php if($h['quality_alerts']): ?>
                        <span class="status status-rejected" style="font-size: 0.7rem;" title="<?php echo htmlspecialchars($h['quality_alerts']); ?>"><i class="fa-solid fa-triangle-exclamation"></i> Flagged</span>
                        <?php else: ?>
                        <span class="text-muted" style="font-size: 0.75rem;">None</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if($hand_res->num_rows == 0): ?>
                <tr><td colspan="5" class="text-center">No handover reports found for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Attendance Chart
new Chart(document.getElementById('attendanceChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_keys($att_stats)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($att_stats)); ?>,
            backgroundColor: ['#ef4444', '#10b981', '#f59e0b', '#f97316', '#6366f1']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// Productivity Chart
new Chart(document.getElementById('productivityChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($productivity, 'label')); ?>,
        datasets: [{
            label: 'Inspections',
            data: <?php echo json_encode(array_column($productivity, 'value')); ?>,
            backgroundColor: 'rgba(79, 70, 229, 0.6)',
            borderColor: '#4f46e5',
            borderWidth: 1
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php include 'layout_footer.php'; ?>
