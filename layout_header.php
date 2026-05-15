<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Textile QMS'; ?> - Textile QMS</title>
    
    <!-- Prevent Dark Mode Flash -->
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <!-- Font Awesome for Sidebar Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .app-top-bar {
            background: var(--card-bg);
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            gap: 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .notification-bell { position: relative; cursor: pointer; color: var(--text-light); font-size: 1.2rem; }
        .notif-badge { position: absolute; top: -5px; right: -5px; background: var(--danger-color); color: white; font-size: 0.6rem; padding: 1px 4px; border-radius: 10px; border: 2px solid white; }
    </style>
    
    <?php if(isset($extraHead)) echo $extraHead; ?>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode' : ''; ?>">
    <script>
        if (localStorage.getItem('theme') === 'dark' && !document.body.classList.contains('dark-mode')) {
            document.body.classList.add('dark-mode');
        }
    </script>

    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3 class="flex flex-center gap-1" style="margin-bottom: 2rem;">
                <i class="fa-solid fa-layer-group" style="color: var(--primary-color);"></i>
                <span>Textile QMS</span>
            </h3>
            <div class="menu">
                <?php if(hasPermission('view_dashboard')): ?>
                <a href="dashboard.php" class="<?php echo (isset($activePage) && $activePage == 'dashboard') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
                <?php endif; ?>
                
                <?php if(hasPermission('add_inspection')): ?>
                <a href="add_inspection.php" class="<?php echo (isset($activePage) && $activePage == 'add_inspection') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-plus-circle"></i> New Inspection
                </a>
                <?php endif; ?>

                <?php if(hasPermission('view_inspections')): ?>
                <a href="inspections.php" class="<?php echo (isset($activePage) && $activePage == 'inspections') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-list-check"></i> All Inspections
                </a>
                <?php endif; ?>

                <?php if(hasPermission('view_reports')): ?>
                <a href="reports.php" class="<?php echo (isset($activePage) && $activePage == 'reports') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-bar"></i> Quality Reports
                </a>
                <?php endif; ?>

                <?php if(hasPermission('manage_team')): ?>
                <a href="users.php" class="<?php echo (isset($activePage) && $activePage == 'users') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> Team Control
                </a>
                <?php endif; ?>

                <?php if(hasPermission('access_personnel')): ?>
                <a href="personnel.php" class="<?php echo (isset($activePage) && $activePage == 'personnel') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-id-card"></i> HR Directory
                </a>
                <?php endif; ?>

                <?php if(hasPermission('view_shifts')): ?>
                <a href="shifts.php" class="<?php echo (isset($activePage) && $activePage == 'shifts') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i> Shift Management
                </a>
                <?php endif; ?>

                <?php if(hasPermission('manage_roles')): ?>
                <a href="roles.php" class="<?php echo (isset($activePage) && $activePage == 'roles') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-shield-halved"></i> Structure & Access
                </a>
                <?php endif; ?>
            </div>
            <div style="margin-top: auto;">
                <div class="user-profile-badge">
                    <div class="avatar-circle">
                        <?php 
                        $username = $_SESSION['username'] ?? 'User';
                        echo strtoupper(substr($username, 0, 1)); 
                        ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['role_name'] ?? $_SESSION['role'] ?? 'Role'); ?></span>
                    </div>
                </div>
                <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content" style="padding: 0; display: flex; flex-direction: column;">
            <!-- Top Bar -->
            <div class="app-top-bar">
                <div class="notification-bell" onclick="toggleNotifDrawer()">
                    <i class="fa-solid fa-bell"></i>
                    <?php 
                    $u_id = $_SESSION['user_id'] ?? 0;
                    $notif_res = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = " . intval($u_id) . " AND is_read = 0");
                    $notif_count = ($notif_res) ? $notif_res->fetch_assoc()['total'] : 0;
                    if($notif_count > 0) echo "<span class='notif-badge'>$notif_count</span>";
                    ?>
                </div>

                <!-- Notification Drawer -->
                <div id="notifDrawer" style="display:none; position: absolute; top: 50px; right: 2rem; width: 320px; background: white; border-radius: 12px; box-shadow: var(--shadow-2xl); z-index: 2000; border: 1px solid var(--border-color); overflow: hidden;">
                    <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                        <strong style="font-size: 0.9rem;">Alert Center</strong>
                        <button onclick="markAllRead()" style="font-size: 0.75rem; background: none; border: none; color: var(--primary-color); cursor: pointer;">Mark all as read</button>
                    </div>
                    <div id="notifList" style="max-height: 400px; overflow-y: auto;">
                        <?php 
                        $notifs = $conn->query("SELECT * FROM notifications WHERE user_id = " . intval($u_id) . " ORDER BY created_at DESC LIMIT 10");
                        if($notifs && $notifs->num_rows > 0):
                            while($n = $notifs->fetch_assoc()):
                        ?>
                            <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); background: <?php echo $n['is_read'] ? 'white' : '#f0f9ff'; ?>;">
                                <p style="margin: 0; font-size: 0.85rem; color: var(--text-color);"><?php echo htmlspecialchars($n['message']); ?></p>
                                <small style="color: var(--text-light); font-size: 0.7rem;"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></small>
                            </div>
                        <?php endwhile; else: ?>
                            <p style="padding: 2rem; text-align: center; color: var(--text-light); font-size: 0.85rem;">No new notifications</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="width: 1px; height: 20px; background: var(--border-color);"></div>
                
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="font-size: 0.85rem; text-align: right;">
                        <div style="font-weight: 700; color: var(--text-color);"><?php echo htmlspecialchars($username); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-light);"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'User'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Scrollable Page Content -->
            <div class="page-content" style="padding: 2rem; flex: 1; overflow-y: auto;">

    <script>
        function toggleNotifDrawer() {
            const drawer = document.getElementById('notifDrawer');
            drawer.style.display = drawer.style.display === 'none' ? 'block' : 'none';
        }
        function markAllRead() {
            fetch('api/mark_read.php').then(() => {
                location.reload();
            });
        }
        // Close drawer on click outside
        window.addEventListener('click', function(e) {
            const drawer = document.getElementById('notifDrawer');
            const bell = document.querySelector('.notification-bell');
            if (drawer.style.display === 'block' && !drawer.contains(e.target) && !bell.contains(e.target)) {
                drawer.style.display = 'none';
            }
        });
    </script>
