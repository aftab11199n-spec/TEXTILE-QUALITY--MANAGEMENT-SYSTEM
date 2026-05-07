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

$id = $_GET['id'] ?? 0;
$sql = "SELECT * FROM inspections WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Inspection record not found.");
}

// Logic to determine "Major Problem" if not explicitly set
$major_problem = $data['defect_type'] ?? 'None Detected';
if ($data['defect_count'] > 5 && $major_problem == 'None') {
    $major_problem = "Multiple Minor Defects";
}

$pageTitle = "Inspection Summary - " . $data['batch_number'];
include 'layout_header.php';
?>

<div class="no-print" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <a href="inspections.php" style="text-decoration: none; color: var(--text-light);">&larr; Back to Records</a>
    <button onclick="window.print()" class="btn-primary" style="background: var(--secondary-color); width: auto;">Download/Print PDF</button>
</div>

<div class="card" id="summary-report" style="max-width: 900px; margin: 0 auto; padding: 3rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg);">
    <!-- Report Header -->
    <div style="display: flex; justify-content: space-between; border-bottom: 2px solid var(--primary-color); padding-bottom: 1.5rem; margin-bottom: 2rem;">
        <div>
            <h1 style="color: var(--primary-color); margin: 0; font-size: 1.8rem;">QUALITY INSPECTION SUMMARY</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem;">Official Fabric Quality Certificate</p>
        </div>
        <div style="text-align: right;">
            <div style="font-weight: 700; font-size: 1.2rem;"><?php echo htmlspecialchars($data['batch_number']); ?></div>
            <p style="color: var(--text-light); margin: 0;">Ref: #QMS-<?php echo str_pad($data['id'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
    </div>

    <!-- Top Alert: Major Problem -->
    <div style="background: <?php echo $data['status'] == 'Passed' ? '#f0fdf4' : '#fef2f2'; ?>; border: 1px solid <?php echo $data['status'] == 'Passed' ? '#bbf7d0' : '#fecaca'; ?>; padding: 1.5rem; border-radius: 0.5rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 1.5rem;">
        <div style="font-size: 2.5rem;">
            <?php echo $data['status'] == 'Passed' ? '✅' : '⚠️'; ?>
        </div>
        <div>
            <h3 style="margin: 0; color: <?php echo $data['status'] == 'Passed' ? '#166534' : '#991b1b'; ?>;">
                Analysis: <?php echo $data['status'] == 'Passed' ? 'Batch Quality Confirmed' : 'Critical Defects Identified'; ?>
            </h3>
            <p style="margin: 0.5rem 0 0; font-size: 1rem; color: var(--text-color);">
                <strong>Major Issue:</strong> <?php echo htmlspecialchars($major_problem); ?> 
                <span style="color: var(--text-light); margin-left: 1rem;">|</span> 
                <span style="margin-left: 1rem;"><strong>Grade:</strong> <?php echo htmlspecialchars($data['grade'] ?? 'A'); ?></span>
            </p>
        </div>
    </div>

    <!-- Data Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
        <!-- Fabric & Batch Data -->
        <div>
            <h4 style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; color: var(--primary-color);">Fabric Information</h4>
            <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 0; color: var(--text-light);">Material Type</td>
                    <td style="padding: 0.75rem 0; font-weight: 600; text-align: right;"><?php echo htmlspecialchars($data['material_type']); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 0; color: var(--text-light);">Total Length</td>
                    <td style="padding: 0.75rem 0; font-weight: 600; text-align: right;"><?php echo number_format($data['length_meters'], 2); ?> Meters</td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 0; color: var(--text-light);">Inspection Date</td>
                    <td style="padding: 0.75rem 0; font-weight: 600; text-align: right;"><?php echo date('F d, Y', strtotime($data['inspection_date'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- Production Data -->
        <div>
            <h4 style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; color: var(--primary-color);">Production Details</h4>
            <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 0; color: var(--text-light);">Machine ID</td>
                    <td style="padding: 0.75rem 0; font-weight: 600; text-align: right;"><?php echo htmlspecialchars($data['machine_id'] ?? 'MC-Unknown'); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 0; color: var(--text-light);">Operator</td>
                    <td style="padding: 0.75rem 0; font-weight: 600; text-align: right;"><?php echo htmlspecialchars($data['operator_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 0; color: var(--text-light);">Defect Count</td>
                    <td style="padding: 0.75rem 0; font-weight: 600; text-align: right; color: <?php echo $data['defect_count'] > 0 ? 'var(--danger-color)' : 'var(--secondary-color)'; ?>;">
                        <?php echo $data['defect_count']; ?> Points
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Comments & Remarks -->
    <div style="margin-top: 2rem; background: var(--bg-color); padding: 1.5rem; border-radius: 0.5rem;">
        <h4 style="margin: 0 0 0.5rem; font-size: 0.9rem; text-transform: uppercase; color: var(--text-light);">Auditor Remarks</h4>
        <p style="margin: 0; line-height: 1.6; color: var(--text-color);">
            <?php echo !empty($data['comments']) ? nl2br(htmlspecialchars($data['comments'])) : "No additional comments provided for this inspection."; ?>
        </p>
    </div>

    <!-- Signature Area (for print) -->
    <div style="margin-top: 4rem; display: flex; justify-content: space-between; border-top: 1px dashed var(--border-color); padding-top: 2rem;">
        <div style="text-align: center; width: 200px;">
            <div style="height: 50px;"></div>
            <p style="border-top: 1px solid #333; margin-top: 0.5rem; font-size: 0.8rem;">Quality Inspector</p>
        </div>
        <div style="text-align: center; width: 200px;">
            <div style="height: 50px;"></div>
            <p style="border-top: 1px solid #333; margin-top: 0.5rem; font-size: 0.8rem;">Department Head</p>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .no-print, header { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    body { background: white; }
    .card { box-shadow: none !important; border: none !important; }
    .app-container { display: block !important; }
}
</style>

<?php include 'layout_footer.php'; ?>
