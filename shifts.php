<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
include 'db.php';
include 'auth.php';
if (!hasPermission('view_shifts')) { header("Location: dashboard.php"); exit(); }

// Get selected week
$selected_date = $_GET['week'] ?? date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($selected_date)));
$prev_week = date('Y-m-d', strtotime('-7 days', strtotime($week_start)));
$next_week = date('Y-m-d', strtotime('+7 days', strtotime($week_start)));

// Fetch all active shifts
$shifts_sql = "SELECT s.*, u.username as supervisor_name,
               COALESCE(pd.full_name, u.username) as supervisor_display
               FROM shifts s
               LEFT JOIN users u ON s.supervisor_id = u.id
               LEFT JOIN personnel_details pd ON u.id = pd.user_id
               ORDER BY s.start_time ASC";
$shifts_res = $conn->query($shifts_sql);
$shifts = [];
while ($row = $shifts_res->fetch_assoc()) {
    // Count assignments for this week
    $cnt = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM shift_assignments WHERE shift_id = {$row['id']} AND shift_date BETWEEN '$week_start' AND '$week_end'");
    $row['worker_count'] = ($cnt) ? $cnt->fetch_assoc()['c'] : 0;
    $shifts[] = $row;
}

// Stats
$active_today = $conn->query("SELECT COUNT(*) as c FROM shifts WHERE status='Active'")->fetch_assoc()['c'];
$workers_today_res = $conn->query("SELECT COUNT(DISTINCT sa.user_id) as c FROM shift_assignments sa JOIN shifts s ON sa.shift_id = s.id WHERE sa.shift_date = CURDATE() AND s.status='Active'");
$workers_today = $workers_today_res ? $workers_today_res->fetch_assoc()['c'] : 0;
$handovers_week_res = $conn->query("SELECT COUNT(*) as c FROM shift_handovers WHERE handover_date BETWEEN '$week_start' AND '$week_end'");
$handovers_week = $handovers_week_res ? $handovers_week_res->fetch_assoc()['c'] : 0;

// Days of the week
$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days", strtotime($week_start)));
    $days[] = ['date' => $d, 'label' => date('D', strtotime($d)), 'day' => date('d', strtotime($d)), 'is_today' => ($d == date('Y-m-d'))];
}
?>

<?php
$pageTitle = "Shift Management";
$activePage = "shifts";
$extraHead = '
<style>
    .shift-type-morning { border-left: 4px solid #f59e0b; }
    .shift-type-afternoon { border-left: 4px solid #f97316; }
    .shift-type-night { border-left: 4px solid #6366f1; }
    .shift-type-custom { border-left: 4px solid #8b5cf6; }
    .shift-card { transition: all 0.3s; cursor: pointer; position: relative; overflow: hidden; }
    .shift-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
    .shift-card .shift-badge { font-size: 0.65rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.08em; padding: 0.25rem 0.6rem; border-radius: 6px; }
    .badge-morning { background: #fef3c7; color: #92400e; }
    .badge-afternoon { background: #ffedd5; color: #9a3412; }
    .badge-night { background: #e0e7ff; color: #3730a3; }
    .badge-custom { background: #ede9fe; color: #5b21b6; }
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }
    .week-nav { display: flex; align-items: center; gap: 0.5rem; }
    .week-nav a { text-decoration: none; color: var(--text-light); padding: 0.5rem; border-radius: 0.5rem; transition: all 0.2s; }
    .week-nav a:hover { background: var(--bg-color); color: var(--primary-color); }
    .day-tab { padding: 0.75rem 1rem; border-radius: 0.75rem; text-align: center; cursor: pointer; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--card-bg); min-width: 70px; }
    .day-tab:hover { border-color: var(--primary-color); }
    .day-tab.today { border-color: var(--primary-color); background: rgba(79,70,229,0.05); }
    .day-tab .day-name { font-size: 0.7rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.05em; }
    .day-tab .day-num { font-size: 1.2rem; font-weight: 800; color: var(--text-color); }
    .stat-mini { display: flex; align-items: center; gap: 0.75rem; padding: 1.25rem; border-radius: 1rem; background: var(--card-bg); border: 1px solid var(--border-color); }
    .stat-mini .stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .stat-mini .stat-val { font-size: 1.5rem; font-weight: 800; }
    .shift-drawer { display: none; position: fixed; top: 0; right: 0; width: 420px; height: 100vh; background: var(--card-bg); box-shadow: -8px 0 30px rgba(0,0,0,0.15); z-index: 1001; overflow-y: auto; animation: slideDrawer 0.3s ease; }
    @keyframes slideDrawer { from { transform: translateX(100%); } to { transform: translateX(0); } }
    .drawer-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 1000; }
</style>';
include 'layout_header.php';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
    <div>
        <h2 style="margin: 0;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary-color);"></i> Shift Management</h2>
        <p style="color: var(--text-light); margin-top: 0.5rem;">Plan, assign, and monitor production shifts across departments.</p>
    </div>
    <div class="flex gap-1">
        <?php if(hasPermission('log_handover')): ?>
        <a href="shift_handover.php" class="btn-secondary" style="width: auto; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; background: var(--card-bg); color: var(--text-color); font-weight: 600; font-size: 0.85rem;">
            <i class="fa-solid fa-arrow-right-arrow-left"></i> Handover Log
        </a>
        <?php endif; ?>
        <?php if(hasPermission('manage_shifts')): ?>
        <a href="shift_add.php" style="width: auto; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 0.5rem; background: var(--primary-color); color: white; font-weight: 700; font-size: 0.85rem;">
            <i class="fa-solid fa-plus"></i> New Shift
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Stats -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
    <div class="stat-mini">
        <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;"><i class="fa-solid fa-clock"></i></div>
        <div><div class="stat-val"><?php echo count($shifts); ?></div><div class="text-xs text-muted">Total Shifts</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fa-solid fa-check-circle"></i></div>
        <div><div class="stat-val"><?php echo $active_today; ?></div><div class="text-xs text-muted">Active Today</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon" style="background: #fff7ed; color: #f97316;"><i class="fa-solid fa-users"></i></div>
        <div><div class="stat-val"><?php echo $workers_today; ?></div><div class="text-xs text-muted">Workers On Shift</div></div>
    </div>
    <div class="stat-mini">
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
        <div><div class="stat-val"><?php echo $handovers_week; ?></div><div class="text-xs text-muted">Handovers This Week</div></div>
    </div>
</div>

<!-- Week Navigation -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div class="week-nav">
        <a href="?week=<?php echo $prev_week; ?>"><i class="fa-solid fa-chevron-left"></i></a>
        <span style="font-weight: 700; font-size: 1.1rem;"><?php echo date('M d', strtotime($week_start)); ?> — <?php echo date('M d, Y', strtotime($week_end)); ?></span>
        <a href="?week=<?php echo $next_week; ?>"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
    <a href="?week=<?php echo date('Y-m-d'); ?>" style="font-size: 0.8rem; color: var(--primary-color); text-decoration: none; font-weight: 600;">
        <i class="fa-solid fa-calendar-day"></i> Jump to Today
    </a>
</div>

<!-- Day Tabs -->
<div style="display: flex; gap: 0.75rem; margin-bottom: 2rem;">
    <?php foreach ($days as $day): ?>
    <div class="day-tab <?php echo $day['is_today'] ? 'today' : ''; ?>">
        <div class="day-name"><?php echo $day['label']; ?></div>
        <div class="day-num"><?php echo $day['day']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Shifts Grid -->
<?php if (empty($shifts)): ?>
<div class="empty-state" style="padding: 4rem 2rem;">
    <i class="fa-solid fa-clock" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
    <h3 style="color: var(--text-light); margin-bottom: 0.5rem;">No Shifts Defined Yet</h3>
    <p style="color: var(--text-light); font-size: 0.9rem;">Create your first shift to start managing your production schedule.</p>
    <?php if(hasPermission('manage_shifts')): ?>
    <a href="shift_add.php" style="display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1rem; padding: 0.75rem 1.5rem; background: var(--primary-color); color: white; border-radius: 0.5rem; text-decoration: none; font-weight: 700;">
        <i class="fa-solid fa-plus"></i> Create First Shift
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
    <?php foreach ($shifts as $s): 
        $type_lower = strtolower($s['shift_type']);
    ?>
    <div class="card shift-card shift-type-<?php echo $type_lower; ?>" onclick="openShiftDrawer(<?php echo htmlspecialchars(json_encode($s)); ?>)">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div>
                <span class="shift-badge badge-<?php echo $type_lower; ?>"><?php echo htmlspecialchars($s['shift_type']); ?></span>
                <h3 style="margin: 0.5rem 0 0; font-size: 1.15rem; font-weight: 700; text-transform: none; letter-spacing: 0; color: var(--text-color);">
                    <?php echo htmlspecialchars($s['shift_name']); ?>
                </h3>
            </div>
            <span class="shift-badge <?php echo $s['status'] == 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                <?php echo $s['status']; ?>
            </span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.8rem; margin-bottom: 1rem;">
            <div>
                <span style="color: var(--text-light); display: block; margin-bottom: 0.15rem;">Schedule</span>
                <span style="font-weight: 700;"><i class="fa-regular fa-clock" style="color: var(--primary-color); font-size: 0.7rem;"></i> <?php echo date('h:i A', strtotime($s['start_time'])); ?> — <?php echo date('h:i A', strtotime($s['end_time'])); ?></span>
            </div>
            <div>
                <span style="color: var(--text-light); display: block; margin-bottom: 0.15rem;">Department</span>
                <span style="font-weight: 600;"><?php echo htmlspecialchars($s['department'] ?: 'General'); ?></span>
            </div>
        </div>

        <div style="padding-top: 0.75rem; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem;">
                <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--bg-color); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; color: var(--primary-color); border: 1px solid var(--border-color);">
                    <?php echo strtoupper(substr($s['supervisor_display'] ?? 'N', 0, 1)); ?>
                </div>
                <span style="color: var(--text-light);"><?php echo htmlspecialchars($s['supervisor_display'] ?? 'Unassigned'); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--primary-color);">
                <i class="fa-solid fa-users" style="font-size: 0.7rem;"></i>
                <?php echo $s['worker_count']; ?> assigned
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Shift Detail Drawer -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeShiftDrawer()"></div>
<div class="shift-drawer" id="shiftDrawer">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-color);">
        <h3 id="drawerTitle" style="margin: 0;">Shift Details</h3>
        <button onclick="closeShiftDrawer()" style="background: none; border: none; font-size: 1.3rem; cursor: pointer; color: var(--text-light);">✕</button>
    </div>
    <div style="padding: 1.5rem;" id="drawerBody">
        <!-- Populated by JS -->
    </div>
</div>

<script>
function openShiftDrawer(s) {
    document.getElementById('drawerTitle').innerText = s.shift_name;
    const typeColors = { Morning: '#f59e0b', Afternoon: '#f97316', Night: '#6366f1', Custom: '#8b5cf6' };
    const color = typeColors[s.shift_type] || '#6366f1';

    let actionsHtml = '';
    <?php if(hasPermission('assign_shifts')): ?>
    actionsHtml += `<a href="shift_assign.php?shift_id=${s.id}" style="flex:1;text-align:center;padding:0.6rem;background:var(--primary-color);color:white;border-radius:0.5rem;text-decoration:none;font-weight:600;font-size:0.8rem;"><i class="fa-solid fa-user-plus"></i> Assign Workers</a>`;
    <?php endif; ?>
    <?php if(hasPermission('mark_attendance')): ?>
    actionsHtml += `<a href="shift_attendance.php?shift_id=${s.id}" style="flex:1;text-align:center;padding:0.6rem;background:var(--secondary-color);color:white;border-radius:0.5rem;text-decoration:none;font-weight:600;font-size:0.8rem;"><i class="fa-solid fa-clipboard-check"></i> Attendance</a>`;
    <?php endif; ?>
    <?php if(hasPermission('manage_shifts')): ?>
    actionsHtml += `<a href="shift_add.php?edit=${s.id}" style="flex:1;text-align:center;padding:0.6rem;border:1px solid var(--border-color);border-radius:0.5rem;text-decoration:none;font-weight:600;font-size:0.8rem;color:var(--text-color);"><i class="fa-solid fa-pen"></i> Edit</a>`;
    <?php endif; ?>

    document.getElementById('drawerBody').innerHTML = `
        <div style="padding:1.25rem;border-radius:0.75rem;background:${color}10;border:1px solid ${color}30;margin-bottom:1.5rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-weight:800;color:${color};text-transform:uppercase;font-size:0.75rem;letter-spacing:0.05em;">${s.shift_type} Shift</span>
                <span style="font-weight:700;font-size:0.85rem;">${formatTime(s.start_time)} — ${formatTime(s.end_time)}</span>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
            <div><span class="text-xs text-muted" style="display:block;margin-bottom:0.2rem;">Department</span><span style="font-weight:600;">${s.department || 'General'}</span></div>
            <div><span class="text-xs text-muted" style="display:block;margin-bottom:0.2rem;">Supervisor</span><span style="font-weight:600;">${s.supervisor_display || 'Unassigned'}</span></div>
            <div><span class="text-xs text-muted" style="display:block;margin-bottom:0.2rem;">Machines</span><span style="font-weight:600;">${s.machine_ids || 'N/A'}</span></div>
            <div><span class="text-xs text-muted" style="display:block;margin-bottom:0.2rem;">Workers This Week</span><span style="font-weight:600;">${s.worker_count} assigned</span></div>
        </div>
        <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;">${actionsHtml}</div>
        <h4 style="margin:0 0 1rem;font-size:0.9rem;color:var(--text-light);">Assigned Workers (Today)</h4>
        <div id="drawerWorkerList"><div class="skeleton" style="width:100%;height:40px;margin-bottom:0.5rem;"></div><div class="skeleton" style="width:100%;height:40px;"></div></div>
    `;

    // Load workers for today
    fetch('api/get_shift_workers.php?shift_id=' + s.id + '&date=' + '<?php echo date("Y-m-d"); ?>')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('drawerWorkerList');
            if (!data.workers || data.workers.length === 0) {
                list.innerHTML = '<div class="empty-state" style="padding:1.5rem;font-size:0.85rem;">No workers assigned for today</div>';
                return;
            }
            list.innerHTML = data.workers.map(w => {
                const statusColors = { Present: '#10b981', Absent: '#ef4444', Late: '#f59e0b', 'Half-Day': '#f97316', Leave: '#6366f1' };
                const sc = statusColors[w.attendance_status] || '#94a3b8';
                return `<div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;border-bottom:1px solid var(--border-color);">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--bg-color);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:var(--primary-color);border:1px solid var(--border-color);">${w.full_name.charAt(0).toUpperCase()}</div>
                    <div style="flex:1;"><div style="font-weight:600;font-size:0.85rem;">${w.full_name}</div><div style="font-size:0.7rem;color:var(--text-light);">${w.role_in_shift || w.role_name}</div></div>
                    <span style="font-size:0.7rem;font-weight:700;color:${sc};padding:0.2rem 0.5rem;border-radius:4px;background:${sc}15;">${w.attendance_status || 'Unmarked'}</span>
                </div>`;
            }).join('');
        });

    document.getElementById('shiftDrawer').style.display = 'block';
    document.getElementById('drawerBackdrop').style.display = 'block';
}

function closeShiftDrawer() {
    document.getElementById('shiftDrawer').style.display = 'none';
    document.getElementById('drawerBackdrop').style.display = 'none';
}

function formatTime(t) {
    if (!t) return '--:--';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return (hr > 12 ? hr - 12 : hr || 12) + ':' + m + ' ' + (hr >= 12 ? 'PM' : 'AM');
}

window.addEventListener('keydown', e => { if (e.key === 'Escape') closeShiftDrawer(); });
</script>

<?php include 'layout_footer.php'; ?>
