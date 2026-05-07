<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db.php';
include 'auth.php';

// Strict Access Control
if (!hasPermission('access_personnel')) {
    header("Location: dashboard.php");
    exit();
}

$message = "";

// Handle Update/Add Details via AJAX-friendly POST or normal POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = intval($_POST['user_id']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $joining_date = $conn->real_escape_string($_POST['joining_date']);
    $salary = floatval($_POST['salary']);

    // Check if record exists
    $check = $conn->query("SELECT id FROM personnel_details WHERE user_id = $user_id");
    
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE personnel_details SET full_name=?, phone=?, address=?, joining_date=?, salary=? WHERE user_id=?");
        $stmt->bind_param("ssssdi", $full_name, $phone, $address, $joining_date, $salary, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO personnel_details (full_name, phone, address, joining_date, salary, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdi", $full_name, $phone, $address, $joining_date, $salary, $user_id);
    }

    if ($stmt->execute()) {
        $message = "Record for " . $full_name . " updated successfully.";
    } else {
        $message = "Error updating record: " . $stmt->error;
    }
}

// Fetch all users and their details
$sql = "SELECT u.id, u.username, r.role_name, p.full_name, p.phone, p.address, p.joining_date, p.salary 
        FROM users u 
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN personnel_details p ON u.id = p.user_id 
        ORDER BY r.level ASC, u.username ASC";
$result = $conn->query($sql);
$personnel = [];
while ($row = $result->fetch_assoc()) {
    $personnel[] = $row;
}
?>

<?php
$pageTitle = "HR Directory";
$activePage = "personnel";
$extraHead = '
<style>
    .search-container {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--bg-color);
        padding: 1rem 0;
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--border-color);
    }
    .hr-card {
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
        border-left: 4px solid var(--primary-color);
        position: relative;
        overflow: hidden;
    }
    .hr-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }
    .hr-card .role-tag {
        font-size: 0.7rem;
        text-transform: uppercase;
        font-weight: 800;
        color: var(--primary-color);
        letter-spacing: 0.05em;
    }
    .hr-card .avatar-placeholder {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: var(--bg-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary-color);
        border: 1px solid var(--border-color);
    }
    .confidential-mask {
        filter: blur(4px);
        transition: filter 0.3s;
    }
    .confidential-mask:hover {
        filter: blur(0);
    }
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    .hr-modal {
        background: var(--card-bg);
        width: 100%;
        max-width: 700px;
        border-radius: 1rem;
        box-shadow: var(--shadow-2xl);
        max-height: 90vh;
        overflow-y: auto;
    }
</style>
';
include 'layout_header.php';
?>

<div class="search-container">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 2rem;">
        <div style="position: relative; flex: 1;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-light);"></i>
            <input type="text" id="hrSearch" placeholder="Search by name, role, or username..." style="padding-left: 3rem; margin: 0; width: 100%;">
        </div>
        <div class="flex gap-1">
            <button class="btn-secondary" onclick="exportDirectory()" style="width: auto;"><i class="fa-solid fa-file-export"></i> Export</button>
        </div>
    </div>
</div>

<?php if ($message) echo "<div class='alert-badge success' style='margin-bottom: 2rem;'>$message <span onclick='this.parentNode.remove()'>✕</span></div>"; ?>

<div class="card-grid" id="personnelGrid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
    <?php foreach ($personnel as $p): ?>
    <div class="card hr-card" onclick='openHRModal(<?php echo json_encode($p); ?>)' data-search="<?php echo strtolower($p['username'] . ' ' . $p['full_name'] . ' ' . $p['role_name']); ?>">
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="avatar-placeholder">
                <?php echo strtoupper(substr($p['username'], 0, 1)); ?>
            </div>
            <div style="flex: 1;">
                <div class="role-tag"><?php echo htmlspecialchars($p['role_name']); ?></div>
                <h3 style="margin: 0.2rem 0;"><?php echo htmlspecialchars($p['full_name'] ?: $p['username']); ?></h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-light);"><i class="fa-solid fa-user" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($p['username']); ?></p>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.8rem;">
            <div>
                <span style="color: var(--text-light); display: block; margin-bottom: 0.2rem;">Phone</span>
                <span style="font-weight: 600;"><i class="fa-solid fa-phone" style="font-size: 0.7rem; color: var(--secondary-color);"></i> <?php echo htmlspecialchars($p['phone'] ?: 'N/A'); ?></span>
            </div>
            <div>
                <span style="color: var(--text-light); display: block; margin-bottom: 0.2rem;">Joined</span>
                <span style="font-weight: 600;"><i class="fa-solid fa-calendar" style="font-size: 0.7rem; color: var(--primary-color);"></i> <?php echo $p['joining_date'] ? date('M Y', strtotime($p['joining_date'])) : 'N/A'; ?></span>
            </div>
        </div>

        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; color: var(--text-light);">Salary Rate</span>
            <span class="confidential-mask" style="font-weight: 800; color: var(--danger-color); font-size: 0.9rem;">
                $<?php echo number_format($p['salary'] ?: 0, 2); ?>
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Employee Profile & Edit Modal -->
<div id="hrModal" class="modal-overlay">
    <div class="hr-modal">
        <div style="padding: 2rem; border-bottom: 1px solid var(--border-color); background: var(--bg-color); border-radius: 1rem 1rem 0 0; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                <div id="modalAvatar" class="avatar-placeholder" style="width: 60px; height: 60px; font-size: 1.5rem;"></div>
                <div>
                    <h2 id="modalTitle" style="margin: 0;">Employee Profile</h2>
                    <span id="modalRole" class="status status-on-hold" style="margin-top: 0.5rem; display: inline-block;"></span>
                </div>
            </div>
            <button onclick="closeHRModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">✕</button>
        </div>
        
        <form method="POST" id="hrForm" style="padding: 2rem; margin: 0; box-shadow: none; background: none;">
            <input type="hidden" name="user_id" id="modalUserId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label><i class="fa-solid fa-id-card-clip"></i> Full Legal Name</label>
                    <input type="text" name="full_name" id="modalFullName" required>
                </div>
                <div>
                    <label><i class="fa-solid fa-phone"></i> Contact Phone</label>
                    <input type="text" name="phone" id="modalPhone" placeholder="+00 000 0000000">
                </div>
                <div>
                    <label><i class="fa-solid fa-calendar-day"></i> Joining Date</label>
                    <input type="date" name="joining_date" id="modalJoiningDate">
                </div>
                <div>
                    <label><i class="fa-solid fa-money-bill-trend-up"></i> Base Salary (USD) <i class="fa-solid fa-lock" style="font-size: 0.7rem; color: var(--danger-color);"></i></label>
                    <input type="number" step="0.01" name="salary" id="modalSalary">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label><i class="fa-solid fa-location-dot"></i> Residential Address</label>
                    <textarea name="address" id="modalAddress" rows="2" style="width: 100%; border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 0.75rem;"></textarea>
                </div>
            </div>

            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn-primary" style="flex: 2;">Save Employee Profile</button>
                <button type="button" onclick="closeHRModal()" class="btn-secondary" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Real-time Search Logic
    const searchInput = document.getElementById('hrSearch');
    const cards = document.querySelectorAll('.hr-card');

    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        cards.forEach(card => {
            const content = card.getAttribute('data-search');
            if (content.includes(term)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Modal Logic
    function openHRModal(p) {
        document.getElementById('modalUserId').value = p.id;
        document.getElementById('modalFullName').value = p.full_name || '';
        document.getElementById('modalPhone').value = p.phone || '';
        document.getElementById('modalJoiningDate').value = p.joining_date || '';
        document.getElementById('modalSalary').value = p.salary || '';
        document.getElementById('modalAddress').value = p.address || '';
        
        document.getElementById('modalTitle').innerText = p.full_name || p.username;
        document.getElementById('modalRole').innerText = p.role_name;
        document.getElementById('modalAvatar').innerText = p.username.charAt(0).toUpperCase();
        
        document.getElementById('hrModal').style.display = 'flex';
    }

    function closeHRModal() {
        document.getElementById('hrModal').style.display = 'none';
    }

    // Close modal on escape
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeHRModal();
    });

    function exportDirectory() {
        alert('Exporting HR Directory to CSV... (This feature is being processed)');
        window.location.href = 'export_personnel.php';
    }
</script>

<?php include 'layout_footer.php'; ?>
