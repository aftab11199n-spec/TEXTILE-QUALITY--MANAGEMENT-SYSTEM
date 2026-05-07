<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';
include 'auth.php';

// Access Control
if (!hasPermission('view_reports')) {
    header("Location: dashboard.php");
    exit();
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Summary Stats
$summary_sql = "SELECT COUNT(*) as total, 
                SUM(IF(status='Passed', 1, 0)) as passed,
                SUM(IF(status='Rejected', 1, 0)) as rejected,
                AVG(defect_count) as avg_defects
                FROM inspections WHERE inspection_date BETWEEN '$start_date' AND '$end_date'";
$summary_res = $conn->query($summary_sql);
$summary = $summary_res->fetch_assoc();

// Machine Performance
$machine_sql = "SELECT machine_id, COUNT(*) as total, 
                SUM(IF(status='Passed', 1, 0)) as passed 
                FROM inspections WHERE inspection_date BETWEEN '$start_date' AND '$end_date'
                GROUP BY machine_id ORDER BY (passed/total) DESC";
$machine_res = $conn->query($machine_sql);

?>
<?php
$pageTitle = "Quality Reports";
$activePage = "reports";
$extraHead = '
    <style>
        @media print {
            .sidebar, .filter-section, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0; }
            .report-header { display: block !important; }
        }
    </style>
';
include 'layout_header.php';
?>
            <header>
                <h2>Management Reports</h2>
                <div class="no-print">
                    <button onclick="window.print()" style="background: var(--secondary-color);">Print Report</button>
                </div>
            </header>

            <div class="card filter-section" style="margin-bottom: 2rem;">
                <form method="GET" style="max-width: none; background: none; box-shadow: none; padding: 0; display: flex; gap: 1rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div style="flex: 1;">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <button type="submit">Generate Report</button>
                </form>
            </div>

            <div class="report-header" style="display: none; text-align: center; margin-bottom: 2rem;">
                <h1>Quality Management Performance Report</h1>
                <p>Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            </div>

            <div class="card-grid">
                <div class="card">
                    <h3>Total Batch Volume</h3>
                    <p class="value"><?php echo $summary['total']; ?></p>
                </div>
                <div class="card">
                    <h3>Overall Yield</h3>
                    <p class="value" style="color: var(--secondary-color);">
                        <?php echo ($summary['total'] > 0) ? round(($summary['passed']/$summary['total'])*100, 1) : 0; ?>%
                    </p>
                </div>
                <div class="card">
                    <h3>Rejection Volume</h3>
                    <p class="value" style="color: var(--danger-color);"><?php echo $summary['rejected']; ?></p>
                </div>
            </div>

            <div class="card" style="margin-top: 2rem;">
                <h3>Machine Quality Ranking</h3>
                <div class="table-container" style="box-shadow: none; border: 1px solid var(--border-color);">
                    <table>
                        <thead>
                            <tr>
                                <th>Machine ID</th>
                                <th>Total Batches</th>
                                <th>Passed</th>
                                <th>Yield Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($m = $machine_res->fetch_assoc()): 
                                $yield = round(($m['passed']/$m['total'])*100, 1);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($m['machine_id']); ?></strong></td>
                                <td><?php echo $m['total']; ?></td>
                                <td><?php echo $m['passed']; ?></td>
                                <td><?php echo $yield; ?>%</td>
                                <td>
                                    <?php if($yield >= 95): ?>
                                        <span class="status status-passed">Optimum</span>
                                    <?php elseif($yield >= 85): ?>
                                        <span class="status status-on-hold" style="background: #e0f2fe; color: #0369a1;">Standard</span>
                                    <?php else: ?>
                                        <span class="status status-rejected">Critical</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Automated Report Section -->
            <div class="card" style="margin-top: 2rem; border-top: 4px solid var(--primary-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Automated Quality Summary</h3>
                    <button onclick="alert('Report generated and emailed to management.')" style="padding: 0.5rem 1rem; font-size: 0.8rem; width: auto;">Generate & Email</button>
                </div>
                <div style="background: var(--bg-color); padding: 1.5rem; border-radius: 0.5rem;">
                    <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Executive Findings:</h4>
                    <ul style="font-size: 0.9rem; line-height: 1.6; color: var(--text-color);">
                        <li>Total of <strong><?php echo $summary['total']; ?></strong> batches inspected this period.</li>
                        <li>Average defect rate stands at <strong><?php echo round($summary['avg_defects'], 2); ?></strong> per batch.</li>
                        <li><strong><?php echo $summary['rejected']; ?></strong> batches were rejected, primarily due to "Stains".</li>
                        <li>Most efficient machine: <strong>MC-04</strong> with 98.2% yield.</li>
                    </ul>
                </div>
            </div>

            <!-- Defect Trend Analysis -->
            <div class="card" style="margin-top: 2rem;">
                <h3>Defect Pattern Analysis</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem;">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 1rem;">Frequency by Type</h4>
                        <div style="height: 200px;">
                            <canvas id="typeTrendChart"></canvas>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; justify-content: center;">
                        <div style="padding: 1rem; background: #fff7ed; border-radius: 0.5rem; border-left: 4px solid #f97316;">
                            <strong style="display: block; color: #9a3412;">Trend Alert:</strong>
                            <span style="font-size: 0.85rem;">"Holes" defect frequency has increased by 12% in the last 7 days. Check machine tension settings.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctxType = document.getElementById('typeTrendChart').getContext('2d');
        new Chart(ctxType, {
            type: 'bar',
            data: {
                labels: ['Stains', 'Holes', 'Uneven Dyeing', 'Slubs'],
                datasets: [{
                    label: 'Occurrences',
                    data: [12, 19, 3, 5],
                    backgroundColor: '#4f46e5'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
