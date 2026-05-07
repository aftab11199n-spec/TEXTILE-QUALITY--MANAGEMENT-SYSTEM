<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';
include 'auth.php';

// Access Control
if (!hasPermission('view_reports')) { // Reusing report permission for simplicity or add 'manage_audits'
    header("Location: dashboard.php");
    exit();
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_audit'])) {
    $title = $_POST['title'];
    $dept = $_POST['department'];
    $date = $_POST['date'];
    $auditor = $_SESSION['user_id'];

    $sql = "INSERT INTO audits (audit_title, department, scheduled_date, auditor_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $title, $dept, $date, $auditor);
    if ($stmt->execute()) {
        $message = "Audit scheduled successfully.";
    }
}

// Fetch audits
$audits = $conn->query("SELECT a.*, u.username as auditor_name FROM audits a LEFT JOIN users u ON a.auditor_id = u.id ORDER BY scheduled_date DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Management - Textile QMS</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3>Textile QMS</h3>
            <div class="menu">
                <?php if(hasPermission('view_dashboard')): ?>
                <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>

                <?php if(hasPermission('add_inspection')): ?>
                <a href="add_inspection.php">New Inspection</a>
                <?php endif; ?>

                <?php if(hasPermission('view_inspections')): ?>
                <a href="inspections.php">All Inspections</a>
                <?php endif; ?>

                <?php if(hasPermission('view_reports')): ?>
                <a href="reports.php">Quality Reports</a>
                <?php endif; ?>

                <?php if(hasPermission('view_reports')): ?>
                <a href="audits.php" class="active">Audit System</a>
                <?php endif; ?>

                <?php if(hasPermission('manage_team')): ?>
                <a href="users.php">Team Control</a>
                <?php endif; ?>

                <?php if(hasPermission('access_personnel')): ?>
                <a href="personnel.php">HR Directory</a>
                <?php endif; ?>

                <?php if(hasPermission('manage_roles')): ?>
                <a href="roles.php">Structure & Access</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-content">
            <header>
                <h2>Audit Management</h2>
            </header>

            <?php if ($message) echo "<div class='card' style='background: #d1fae5; color: #065f46; margin-bottom: 1.5rem;'>$message</div>"; ?>

            <div class="card" style="margin-bottom: 2rem;">
                <h3>Schedule New Audit</h3>
                <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end; max-width: none;">
                    <div style="flex: 2;">
                        <label>Audit Title</label>
                        <input type="text" name="title" required placeholder="e.g. Q1 Fabric Quality Audit">
                    </div>
                    <div style="flex: 1;">
                        <label>Department</label>
                        <input type="text" name="department" required placeholder="Spinning / Weaving">
                    </div>
                    <div style="flex: 1;">
                        <label>Scheduled Date</label>
                        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" name="add_audit">Schedule Audit</button>
                </form>
            </div>

            <div class="card">
                <h3>Existing Audits</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Dept</th>
                                <th>Date</th>
                                <th>Auditor</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $audits->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['audit_title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo $row['scheduled_date']; ?></td>
                                <td><?php echo htmlspecialchars($row['auditor_name']); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower($row['status']) == 'completed' ? 'status-passed' : 'status-on-hold'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td><a href="#" style="color: var(--primary-color);">Manage Items</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
