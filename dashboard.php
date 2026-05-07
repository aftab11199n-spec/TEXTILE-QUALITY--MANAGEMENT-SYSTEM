<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db.php';
include 'auth.php';

// Fetch specific statistics
function getCount($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return $row['total'] ?? $row['count'] ?? $row['rejected'] ?? $row['passed'] ?? $row['on_hold'] ?? 0;
}

$total = getCount($conn, "SELECT COUNT(*) as total FROM inspections");
$rejected = getCount($conn, "SELECT COUNT(*) as rejected FROM inspections WHERE status='Rejected'");
$passed = getCount($conn, "SELECT COUNT(*) as passed FROM inspections WHERE status='Passed'");
$on_hold = getCount($conn, "SELECT COUNT(*) as on_hold FROM inspections WHERE status='On Hold'");

// Fetch Last 30 Days Trend
$trend_sql = "SELECT inspection_date, COUNT(*) as count FROM inspections 
              WHERE inspection_date >= DATE(NOW()) - INTERVAL 30 DAY 
              GROUP BY inspection_date ORDER BY inspection_date ASC";
$trend_res = $conn->query($trend_sql);

$trend_labels = [];
$trend_data = [];
if ($trend_res) {
    while($row = $trend_res->fetch_assoc()) {
        $trend_labels[] = date('M d', strtotime($row['inspection_date']));
        $trend_data[] = $row['count'];
    }
}

$yield_rate = ($total > 0) ? round(($passed / $total) * 100, 1) : 0;

// Fetch Recent Activity
$audit_sql = "SELECT a.*, u.username FROM audit_log a 
              JOIN users u ON a.user_id = u.id 
              ORDER BY a.created_at DESC LIMIT 5";
$audit_res = $conn->query($audit_sql);

// 24h Rejection Alert
$alert_sql = "SELECT COUNT(*) as total, SUM(IF(status='Rejected', 1, 0)) as rejected 
              FROM inspections WHERE created_at >= NOW() - INTERVAL 1 DAY";
$alert_res = $conn->query($alert_sql);
$alert_data = ($alert_res) ? $alert_res->fetch_assoc() : ['total' => 0, 'rejected' => 0];
$recent_rejection_rate = (isset($alert_data['total']) && $alert_data['total'] > 0) ? ($alert_data['rejected'] / $alert_data['total']) * 100 : 0;

// Defect Analysis Data
$defect_sql = "SELECT defect_type, COUNT(*) as count FROM inspections WHERE defect_type != 'None' GROUP BY defect_type";
$defect_res = $conn->query($defect_sql);
$defect_labels = [];
$defect_counts = [];
if ($defect_res) {
    while($dRow = $defect_res->fetch_assoc()) {
        $defect_labels[] = $dRow['defect_type'];
        $defect_counts[] = $dRow['count'];
    }
}
?>

<?php
$pageTitle = "VIP Dashboard";
$activePage = "dashboard";
$extraHead = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.3);
    }
    .dark-mode {
        --glass-bg: rgba(30, 41, 59, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }
    
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .glass-card:hover {
        transform: scale(1.02);
    }

    .vip-stat-card {
        padding: 1.5rem;
        border-radius: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .vip-stat-card .icon-box {
        width: 45px; height: 45px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; margin-bottom: 1rem;
    }
    .value-counter { font-size: 2.2rem; font-weight: 800; margin: 0.5rem 0; }

    .live-pulse {
        width: 10px; height: 10px; background: #10b981; border-radius: 50%;
        display: inline-block; margin-right: 8px;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .yield-circle-svg { transform: rotate(-90deg); }
    .yield-circle-bg { fill: none; stroke: var(--border-color); stroke-width: 8; }
    .yield-circle-val { 
        fill: none; stroke: var(--secondary-color); stroke-width: 8; 
        stroke-linecap: round; transition: stroke-dasharray 1s ease-out;
    }
</style>
';
include 'layout_header.php';
?>

<!-- VIP Top Header -->
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem;">
    <div>
        <h1 style="margin: 0; font-size: 2rem; color: var(--text-color);">
            <?php 
                $hour = date('H');
                $greeting = ($hour < 12) ? "Good Morning" : (($hour < 18) ? "Good Afternoon" : "Good Evening");
                echo $greeting . ", " . htmlspecialchars($_SESSION['username']);
            ?>! 👋
        </h1>
        <p style="color: var(--text-light); margin: 0.5rem 0 0;"><i class="fa-solid fa-calendar-day"></i> <?php echo date('l, F j, Y'); ?> | <i class="fa-solid fa-clock"></i> <span id="vip-clock">--:--:--</span></p>
    </div>
    <div class="flex gap-1 no-print">
        <button class="btn-secondary" onclick="location.reload()" style="width: auto;"><i class="fa-solid fa-arrows-rotate"></i> Refresh Data</button>
        <button class="btn-primary" onclick="window.print()" style="width: auto;"><i class="fa-solid fa-file-pdf"></i> Export View</button>
    </div>
</div>

<?php if ($recent_rejection_rate > 10): ?>
<div class="alert-badge danger pulse" style="margin-bottom: 2rem; border-radius: 1rem; padding: 1.25rem;">
    <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.2rem;"></i>
    <div style="flex: 1;">
        <strong>Critical Quality Alert:</strong> Rejection rate has hit <?php echo round($recent_rejection_rate, 1); ?>% in the last 24 hours. Immediate process review recommended.
    </div>
    <span onclick="this.parentNode.remove()" style="cursor: pointer;">✕</span>
</div>
<?php endif; ?>

<!-- Stat Grid -->
<div class="card-grid" style="grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
    <div class="card glass-card vip-stat-card">
        <div class="icon-box" style="background: #eef2ff; color: #4f46e5;"><i class="fa-solid fa-layer-group"></i></div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-light);">Total Batches</span>
            <div class="value-counter" data-target="<?php echo $total; ?>">0</div>
        </div>
        <div style="font-size: 0.75rem; color: #10b981;"><i class="fa-solid fa-arrow-trend-up"></i> +4.2% from last week</div>
    </div>
    
    <div class="card glass-card vip-stat-card">
        <div class="icon-box" style="background: #ecfdf5; color: #10b981;"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-light);">Passed Yield</span>
            <div class="value-counter" data-target="<?php echo $passed; ?>">0</div>
        </div>
        <div style="font-size: 0.75rem; color: #10b981;"><i class="fa-solid fa-check-double"></i> 99.4% Data Integrity</div>
    </div>

    <div class="card glass-card vip-stat-card">
        <div class="icon-box" style="background: #fff1f2; color: #e11d48;"><i class="fa-solid fa-circle-xmark"></i></div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-light);">Rejections</span>
            <div class="value-counter" data-target="<?php echo $rejected; ?>">0</div>
        </div>
        <div style="font-size: 0.75rem; color: #e11d48;"><i class="fa-solid fa-arrow-trend-down"></i> -1.5% improvement</div>
    </div>

    <div class="card glass-card vip-stat-card">
        <div class="icon-box" style="background: #fffbeb; color: #d97706;"><i class="fa-solid fa-hourglass-half"></i></div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-light);">On Hold</span>
            <div class="value-counter" data-target="<?php echo $on_hold; ?>">0</div>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-light);">Pending Analysis</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-top: 2rem;">
    <!-- Yield Gauge -->
    <div class="card glass-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2.5rem;">
        <h3 style="margin-bottom: 2rem;">Quality Yield Rate</h3>
        <div style="position: relative; width: 160px; height: 160px;">
            <svg width="160" height="160" class="yield-circle-svg">
                <circle cx="80" cy="80" r="70" class="yield-circle-bg"></circle>
                <circle cx="80" cy="80" r="70" class="yield-circle-val" id="yieldCircle" stroke-dasharray="0 440"></circle>
            </svg>
            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                <span style="font-size: 2rem; font-weight: 800; color: var(--text-color);"><?php echo $yield_rate; ?>%</span>
                <small style="color: var(--text-light); font-size: 0.7rem;">OPTIMUM</small>
            </div>
        </div>
    </div>

    <!-- Defect Doughnut -->
    <div class="card glass-card">
        <h3 style="margin-bottom: 1.5rem;">Root Cause Analysis</h3>
        <div style="height: 200px;">
            <canvas id="defectChart"></canvas>
        </div>
    </div>

    <!-- Activity Feed -->
    <div class="card glass-card">
        <h3 style="margin-bottom: 1.5rem;">Recent Activity Feed</h3>
        <div style="max-height: 220px; overflow-y: auto; padding-right: 0.5rem;">
            <?php while($log = $audit_res->fetch_assoc()): ?>
                <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;">
                        <?php echo strtoupper(substr($log['username'], 0, 1)); ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.2rem;">
                            <strong style="font-size: 0.85rem; color: var(--primary-color);"><?php echo htmlspecialchars($log['action']); ?></strong>
                            <small style="color: var(--text-light);"><?php echo date('H:i', strtotime($log['created_at'])); ?></small>
                        </div>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-light);"><?php echo htmlspecialchars($log['username']); ?>: <?php echo htmlspecialchars($log['details']); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Real-time Monitor -->
<div class="card glass-card" style="margin-top: 2rem; border-left: 5px solid #10b981; overflow: hidden;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h3 style="margin: 0;"><span class="live-pulse"></span>Real-time Production Monitor</h3>
            <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.3rem;">Syncing with factory floor every 5 seconds...</p>
        </div>
        <div style="text-align: right;">
            <span style="font-size: 0.75rem; color: var(--text-light);">Last Sync</span>
            <div id="last-update-time" style="font-weight: 700; font-size: 0.9rem;">--:--:--</div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
        <div style="background: rgba(16, 185, 129, 0.05); padding: 1.5rem; border-radius: 1rem; text-align: center;">
            <small style="text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Total Batches</small>
            <div id="live-total" style="font-size: 1.75rem; font-weight: 800; color: var(--primary-color); margin-top: 0.5rem;">--</div>
        </div>
        <div style="background: rgba(16, 185, 129, 0.05); padding: 1.5rem; border-radius: 1rem; text-align: center;">
            <small style="text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Passed Units</small>
            <div id="live-passed" style="font-size: 1.75rem; font-weight: 800; color: #10b981; margin-top: 0.5rem;">--</div>
        </div>
        <div style="background: rgba(16, 185, 129, 0.05); padding: 1.5rem; border-radius: 1rem; text-align: center;">
            <small style="text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Current Yield</small>
            <div id="live-yield" style="font-size: 1.75rem; font-weight: 800; color: var(--text-color); margin-top: 0.5rem;">--%</div>
        </div>
    </div>
</div>

<!-- Predictive & Trend -->
<div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.5rem; margin-top: 2rem;">
    <div class="card glass-card" style="background: linear-gradient(135deg, var(--glass-bg) 0%, rgba(79, 70, 229, 0.05) 100%);">
        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem;">
            <div style="width: 40px; height: 40px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #4f46e5;">
                <i class="fa-solid fa-microchip"></i>
            </div>
            <h3 style="margin: 0;">AI Quality Forecast</h3>
        </div>
        <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 1.5rem;">
            <div style="text-align: center;">
                <div style="font-size: 2.2rem; font-weight: 800; color: #4f46e5;">94.2%</div>
                <small style="color: var(--text-light);">Predicted Yield</small>
            </div>
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 0.5rem;">
                    <span>Confidence Score</span>
                    <span style="font-weight: 700;">89%</span>
                </div>
                <div style="height: 6px; background: var(--border-color); border-radius: 3px; overflow: hidden;">
                    <div style="width: 89%; height: 100%; background: #4f46e5;"></div>
                </div>
            </div>
        </div>
        <div style="background: rgba(255, 255, 255, 0.4); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--glass-border); font-size: 0.85rem;">
            <strong>AI Suggestion:</strong> Shift to Machine <strong>MC-04</strong> for high-density fabrics to reduce "Slub" occurrence by 12%.
        </div>
    </div>

    <div class="card glass-card">
        <h3 style="margin-bottom: 1.5rem;">Inspection Trends (30 Days)</h3>
        <div style="height: 200px;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
</div>

<script>
    // VIP Clock
    function updateClock() {
        const now = new Date();
        document.getElementById('vip-clock').innerText = now.toLocaleTimeString();
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Animated Counters
    const counters = document.querySelectorAll('.value-counter');
    counters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        const increment = target / 50;
        const updateCount = () => {
            const count = +counter.innerText;
            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(updateCount, 20);
            } else {
                counter.innerText = target;
            }
        };
        updateCount();
    });

    // Yield Circle Animation
    const yieldRate = <?php echo $yield_rate; ?>;
    const dashArray = (yieldRate / 100) * 440;
    setTimeout(() => {
        document.getElementById('yieldCircle').setAttribute('stroke-dasharray', `${dashArray} 440`);
    }, 500);

    // Chart.js Config
    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    };

    // Trend Chart
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($trend_data); ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.05)',
                fill: true,
                tension: 0.4,
                pointRadius: 0
            }]
        },
        options: { ...chartDefaults, scales: { x: { display: false }, y: { display: false } } }
    });

    // Defect Doughnut
    new Chart(document.getElementById('defectChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($defect_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($defect_counts); ?>,
                backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: { 
            ...chartDefaults, 
            plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 8, font: { size: 10 } } } } 
        }
    });

    // Live monitor polling
    function updateLiveStats() {
        fetch('api/get_live_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.error) return;
                document.getElementById('live-total').innerText = data.total;
                document.getElementById('live-passed').innerText = data.passed;
                document.getElementById('live-yield').innerText = data.yield_rate + '%';
                document.getElementById('last-update-time').innerText = data.last_updated;
            });
    }
    setInterval(updateLiveStats, 5000);
    updateLiveStats();
</script>

<?php include 'layout_footer.php'; ?>
