<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';
include 'auth.php';

// Access Control
if (!hasPermission('edit_inspection')) {
    header("Location: dashboard.php");
    exit();
}

$message = "";
$id = 0;
$row = [];

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM inspections WHERE id = $id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        header("Location: inspections.php");
        exit();
    }
} else {
    header("Location: inspections.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $batch_number = $_POST['batch_number'];
    $material_type = $_POST['material_type'];
    $machine_id = $_POST['machine_id'];
    $operator_name = $_POST['operator_name'];
    $length = $_POST['length'];
    $defect_count = $_POST['defect_count'];
    
    // Handle Multiple Defect Types
    $defect_types_arr = $_POST['defect_types'] ?? [];
    $defect_type = empty($defect_types_arr) ? 'None' : implode(', ', $defect_types_arr);
    
    $status = $_POST['status'];
    $inspection_date = $_POST['inspection_date'];
    $comments = $_POST['comments'];

    // Auto-Grading Logic
    $grade = "A";
    if ($defect_count > 0 && $defect_count <= 3) {
        $grade = "B";
    } elseif ($defect_count > 3) {
        $grade = "Rejected";
    }

    $sql = "UPDATE inspections SET 
            batch_number=?, material_type=?, machine_id=?, operator_name=?, length_meters=?, defect_count=?, defect_type=?, status=?, grade=?, inspection_date=?, comments=? 
            WHERE id=?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssissssssi", $batch_number, $material_type, $machine_id, $operator_name, $length, $defect_count, $defect_type, $status, $grade, $inspection_date, $comments, $id);

    if ($stmt->execute()) {
        logAction($conn, $_SESSION['user_id'], "Edit Inspection", "Record ID: $id");
        header("Location: inspections.php?msg=Inspection record updated successfully");
        exit();
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Inspection - Textile QMS</title>
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
                <a href="inspections.php" class="active">All Inspections</a>
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
                <h2>Edit Inspection Record</h2>
            </header>

            <div style="display: flex; justify-content: center;">
                <form method="POST" style="width: 100%; max-width: 600px;">
                    <?php if ($message) echo "<div style='background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem;'>$message</div>"; ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label>Batch Number</label>
                            <input type="text" name="batch_number" value="<?php echo htmlspecialchars($row['batch_number']); ?>" required>
                        </div>
                        <div>
                            <label>Material Type</label>
                            <input type="text" name="material_type" value="<?php echo htmlspecialchars($row['material_type']); ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label>Machine ID</label>
                            <input type="text" name="machine_id" value="<?php echo htmlspecialchars($row['machine_id'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>Operator Name</label>
                            <input type="text" name="operator_name" value="<?php echo htmlspecialchars($row['operator_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label>Length (Meters)</label>
                            <input type="number" step="0.01" name="length" value="<?php echo htmlspecialchars($row['length_meters']); ?>" required>
                        </div>
                        <div>
                            <label>Defect Count</label>
                            <input type="number" name="defect_count" value="<?php echo htmlspecialchars($row['defect_count']); ?>" required>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                        <label style="margin-bottom: 0.75rem; display: block; font-weight: 700;">Select Defect Types (Multiple Allowed)</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; background: var(--bg-color); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                            <?php 
                            $existing_defects = explode(', ', $row['defect_type'] ?? '');
                            $defect_options = ['Stains', 'Holes', 'Uneven Dyeing', 'Slubs', 'Shading', 'Thick/Thin Place', 'Broken Picks', 'Oil Stains', 'Knots', 'Water Marks', 'Crease Marks'];
                            foreach($defect_options as $option): 
                            ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem;">
                                <input type="checkbox" name="defect_types[]" value="<?php echo $option; ?>" 
                                    <?php if(in_array($option, $existing_defects)) echo 'checked'; ?>
                                    style="width: auto; margin: 0;"> <?php echo $option; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label>Status</label>
                    <select name="status">
                        <option value="Passed" <?php if($row['status'] == 'Passed') echo 'selected'; ?>>Passed</option>
                        <option value="Rejected" <?php if($row['status'] == 'Rejected') echo 'selected'; ?>>Rejected</option>
                        <option value="On Hold" <?php if($row['status'] == 'On Hold') echo 'selected'; ?>>On Hold</option>
                    </select>

                    <label>Inspection Date</label>
                    <input type="date" name="inspection_date" required value="<?php echo htmlspecialchars($row['inspection_date']); ?>">

                    <label>Comments</label>
                    <textarea name="comments" rows="3"><?php echo htmlspecialchars($row['comments']); ?></textarea>

                    <button type="submit">Update Inspection Record</button>
                    <a href="inspections.php" style="display: block; text-align: center; margin-top: 1rem; color: var(--text-light); text-decoration: none;">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <?php include 'layout_footer.php'; ?>
</body>
</html>
