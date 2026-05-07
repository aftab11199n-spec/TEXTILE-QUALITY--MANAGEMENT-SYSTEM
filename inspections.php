<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';
include 'auth.php';

// Access Control
if (!hasPermission('view_inspections')) {
    header("Location: dashboard.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    if (!hasPermission('delete_inspection')) {
        die("Access Denied: You do not have permission to delete records.");
    }
    $id = intval($_GET['delete']);
    logAction($conn, $_SESSION['user_id'], "Delete Inspection", "Record ID: $id");
    $conn->query("DELETE FROM inspections WHERE id=$id");
    header("Location: inspections.php?msg=Record deleted successfully");
    exit();
}

// Handle Search & Filter
$search = $_GET['search'] ?? "";
$status_filter = $_GET['status'] ?? "";
$start_date = $_GET['start_date'] ?? "";
$end_date = $_GET['end_date'] ?? "";

$where_clauses = [];

if (!empty($search)) {
    $search_esc = $conn->real_escape_string($search);
    $where_clauses[] = "(batch_number LIKE '%$search_esc%' OR material_type LIKE '%$search_esc%' OR machine_id LIKE '%$search_esc%')";
}

if (!empty($status_filter)) {
    $status_esc = $conn->real_escape_string($status_filter);
    $where_clauses[] = "status = '$status_esc'";
}

if (!empty($start_date)) {
    $start_esc = $conn->real_escape_string($start_date);
    $where_clauses[] = "inspection_date >= '$start_esc'";
}

if (!empty($end_date)) {
    $end_esc = $conn->real_escape_string($end_date);
    $where_clauses[] = "inspection_date <= '$end_esc'";
}

$sql = "SELECT * FROM inspections";
if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Inspections - Textile QMS</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
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
                <a href="inspections.php" class="active">All Inspections</a>
                <?php endif; ?>

                <?php if(hasPermission('view_reports')): ?>
                <a href="reports.php">Quality Reports</a>
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
            <div style="margin-top: auto;">
                <div style="color: var(--sidebar-text); font-size: 0.75rem; margin-bottom: 0.75rem; padding: 0 1rem;">
                    Logged in as:<br>
                    <strong style="color: white; font-weight: 600;"><?php echo htmlspecialchars($_SESSION['role_name'] ?? $_SESSION['role']); ?></strong>
                </div>
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h2>Inspection Records</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <form method="GET" style="display: flex; gap: 0.5rem; background: none; box-shadow: none; padding: 0; max-width: none;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search Batch/Machine..." style="margin: 0; width: 200px;">
                        
                        <select name="status" style="margin: 0; width: auto;">
                            <option value="">All Status</option>
                            <option value="Passed" <?php if($status_filter=='Passed') echo 'selected'; ?>>Passed</option>
                            <option value="Rejected" <?php if($status_filter=='Rejected') echo 'selected'; ?>>Rejected</option>
                            <option value="On Hold" <?php if($status_filter=='On Hold') echo 'selected'; ?>>On Hold</option>
                        </select>

                        <button type="submit" style="padding: 0.5rem 1rem;">Filter</button>
                        <a href="inspections.php" style="padding: 0.5rem 1rem; text-decoration: none; color: var(--text-light); border: 1px solid var(--border-color); border-radius: 0.375rem; background: white; font-size: 0.9rem;">Reset</a>
                    </form>
                </div>
            </header>

            <div class="table-container">
                <table id="inspectionsTable">
                    <thead>
                        <tr>
                            <th>Batch No</th>
                            <th>Material</th>
                            <th>Machine</th>
                            <th>Defects</th>
                            <th>Root Cause</th>
                            <th>Status</th>
                            <th>Grade</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['batch_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['material_type']); ?></td>
                                <td><span style="font-size: 0.85rem; background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 0.25rem; border: 1px solid #e2e8f0; color: #475569;"><?php echo htmlspecialchars($row['machine_id'] ?? 'N/A'); ?></span></td>
                                <td><?php echo htmlspecialchars($row['defect_count']); ?></td>
                                <td style="font-size: 0.85rem; color: var(--text-light);"><?php echo htmlspecialchars($row['defect_type'] ?? 'None'); ?></td>
                                <td>
                                    <?php 
                                    $statusClass = 'status-on-hold';
                                    if ($row['status'] == 'Passed') $statusClass = 'status-passed';
                                    if ($row['status'] == 'Rejected') $statusClass = 'status-rejected';
                                    ?>
                                    <span class="status <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $gradeColor = 'var(--secondary-color)';
                                    if ($row['grade'] == 'B') $gradeColor = 'var(--warning-color)';
                                    if ($row['grade'] == 'Rejected') $gradeColor = 'var(--danger-color)';
                                    ?>
                                    <span style="font-weight: 700; color: <?php echo $gradeColor; ?>; font-size: 0.9rem;">
                                        Grade <?php echo htmlspecialchars($row['grade'] ?? 'A'); ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap; font-size: 0.9rem;"><?php echo date('M d, Y', strtotime($row['inspection_date'])); ?></td>
                                <td style="display: flex; gap: 0.75rem; align-items: center;">
                                    <button onclick="generateQR('<?php echo $row['batch_number']; ?>', 'Batch: <?php echo $row['batch_number']; ?>\nGrade: <?php echo $row['grade']; ?>\nStatus: <?php echo $row['status']; ?>')" class="no-style" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; padding: 0;" title="QR Code">📱</button>
                                    <a href="inspection_summary.php?id=<?php echo $row['id']; ?>" style="color: var(--secondary-color); text-decoration: none; font-weight: 600; font-size: 0.9rem;">Summary</a>
                                    <a href="edit_inspection.php?id=<?php echo $row['id']; ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 600; font-size: 0.9rem;">Edit</a>
                                    <?php if(hasPermission('delete_inspection')): ?>
                                        <button onclick="confirmAction('Are you sure you want to delete this record?', 'inspections.php?delete=<?php echo $row['id']; ?>')" style="background: none; border: none; color: var(--danger-color); padding: 0; font-size: 0.9rem; font-weight: 600; cursor: pointer;">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for QR -->
    <div id="qrModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Batch QR Code</h3>
            <div id="qrcode" style="display: flex; justify-content: center; margin: 1.5rem 0;"></div>
            <p id="qrText" style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 1.5rem;"></p>
            <button onclick="closeModal()" class="btn-cancel">Close</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/ushelp/EasyQRCodeJS@master/dist/easy.qrcode.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#inspectionsTable').DataTable({
                "order": [[ 7, "desc" ]],
                "pageLength": 10,
                "language": {
                    "search": "Search Table:",
                    "lengthMenu": "Show _MENU_ records"
                }
            });
        });

        function generateQR(batch, text) {
            document.getElementById('qrcode').innerHTML = "";
            new QRCode(document.getElementById("qrcode"), {
                text: text,
                width: 180,
                height: 180,
                colorDark : "#0f172a",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
            document.getElementById('qrText').innerText = "Data for Batch: " + batch;
            document.getElementById('qrModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('qrModal').style.display = 'none';
        }
    </script>
    <?php include 'layout_footer.php'; ?>
</body>
</html>
