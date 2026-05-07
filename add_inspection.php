<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';
include 'auth.php';

// Access Control
if (!hasPermission('add_inspection')) {
    header("Location: dashboard.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Correctly extract all POST variables
    $batch_number = $_POST['batch_number'] ?? '';
    $barcode = $_POST['barcode'] ?? '';
    $material_type = $_POST['material_type'] ?? '';
    $machine_id = $_POST['machine_id'] ?? '';
    $operator_name = $_POST['operator_name'] ?? '';
    $length = $_POST['length'] ?? 0;
    $defect_count = $_POST['defect_count'] ?? 0;
    
    // Handle Multiple Defect Types
    $defect_types_arr = $_POST['defect_types'] ?? [];
    $defect_type = empty($defect_types_arr) ? 'None' : implode(', ', $defect_types_arr);
    $status = $_POST['status'] ?? 'On Hold';
    $inspection_date = $_POST['inspection_date'] ?? date('Y-m-d');
    $comments = $_POST['comments'] ?? '';

    // Auto-Grading Logic (Mirroring edit_inspection.php for consistency)
    $grade = "A";
    if ($defect_count > 0 && $defect_count <= 3) {
        $grade = "B";
    } elseif ($defect_count > 3) {
        $grade = "Rejected";
    }

    $sql = "INSERT INTO inspections (batch_number, barcode, material_type, machine_id, operator_name, length_meters, defect_count, defect_type, status, grade, inspection_date, comments) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $message = "Database Error: " . $conn->error;
    } else {
        $stmt->bind_param("sssssdisssss", $batch_number, $barcode, $material_type, $machine_id, $operator_name, $length, $defect_count, $defect_type, $status, $grade, $inspection_date, $comments);

        if ($stmt->execute()) {
            logAction($conn, $_SESSION['user_id'], "Add Inspection", "Batch: $batch_number");
            header("Location: inspections.php?msg=Inspection record added successfully");
            exit();
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

?>
<?php
$pageTitle = "New Inspection";
$activePage = "add_inspection";
include 'layout_header.php';
?>
            <header>
                <h2>Record New Inspection</h2>
            </header>

            <div style="display: flex; justify-content: center;">
                <form method="POST" enctype="multipart/form-data" style="width: 100%; max-width: 600px;">
                    <?php if ($message) echo "<div style='background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem;'>$message</div>"; ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label>Batch Number</label>
                            <input type="text" name="batch_number" id="batch_number" required>
                        </div>
                        <div>
                            <label>Fabric Barcode / QR (Scan)</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="text" name="barcode" id="barcode" placeholder="Scan or type...">
                                <button type="button" id="start-scan" style="padding: 0.5rem; width: auto; background: var(--secondary);">Scan</button>
                            </div>
                            <div id="reader" style="width: 100%; display: none; margin-top: 1rem;"></div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label>Material Type</label>
                            <input type="text" name="material_type" placeholder="e.g. Cotton, Polyester" required>
                        </div>
                        <div>
                            <label>Machine ID</label>
                            <input type="text" name="machine_id" placeholder="e.g. MC-01" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                        <div>
                            <label>Operator Name</label>
                            <input type="text" name="operator_name" required>
                        </div>
                    </div>


                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label>Length (Meters)</label>
                            <input type="number" step="0.01" name="length" required>
                        </div>
                        <div>
                            <label>Defect Count</label>
                            <input type="number" name="defect_count" required>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                        <label style="margin-bottom: 0.75rem; display: block; font-weight: 700;">Select Defect Types (Multiple Allowed)</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; background: var(--bg-color); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                            <?php 
                            $defect_options = ['Stains', 'Holes', 'Uneven Dyeing', 'Slubs', 'Shading', 'Thick/Thin Place', 'Broken Picks', 'Oil Stains', 'Knots', 'Water Marks', 'Crease Marks'];
                            foreach($defect_options as $option): 
                            ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem;">
                                <input type="checkbox" name="defect_types[]" value="<?php echo $option; ?>" style="width: auto; margin: 0;"> <?php echo $option; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label>Status</label>
                    <select name="status">
                        <option value="Passed">Passed</option>
                        <option value="Rejected">Rejected</option>
                        <option value="On Hold">On Hold</option>
                    </select>

                    <label>Inspection Date</label>
                    <input type="date" name="inspection_date" required value="<?php echo date('Y-m-d'); ?>">

                    <label>Comments</label>
                    <textarea name="comments" rows="3"></textarea>

                    <button type="submit">Save Inspection Record</button>
                    <a href="dashboard.php" style="display: block; text-align: center; margin-top: 1rem; color: var(--text-light); text-decoration: none;">Cancel</a>
                </form>

                <!-- QR Scanner Script -->
                <script src="https://unpkg.com/html5-qrcode"></script>
                <script>
                    const scanBtn = document.getElementById('start-scan');
                    const reader = document.getElementById('reader');
                    const barcodeInput = document.getElementById('barcode');

                    scanBtn.addEventListener('click', () => {
                        reader.style.display = 'block';
                        const html5QrCode = new Html5Qrcode("reader");
                        html5QrCode.start(
                            { facingMode: "environment" }, 
                            { fps: 10, qrbox: { width: 250, height: 250 } },
                            (decodedText) => {
                                barcodeInput.value = decodedText;
                                html5QrCode.stop();
                                reader.style.display = 'none';
                                alert("Scan Successful: " + decodedText);
                            }
                        ).catch(err => {
                            console.error("Camera access failed", err);
                            alert("Unable to access camera");
                        });
                    });
                </script>
            </div>
        </div>
    </div>
    <?php include 'layout_footer.php'; ?>
</body>
</html>
