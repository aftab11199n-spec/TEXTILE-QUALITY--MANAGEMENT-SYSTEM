<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';
include 'auth.php'; // Include Auth Helper

// Debug: Check if session works
if (isset($_GET['debug_session'])) {
    die("Session ID: " . session_id() . "<br>User ID: " . ($_SESSION['user_id'] ?? 'Not set'));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, password, role, role_id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $error = "Database Error: " . $conn->error . ". Have you run update_rbac.php?";
    } else {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $row['role']; // Keep for legacy display if needed
            
            // LOAD RBAC PERMISSIONS
            reloadUserPermissions($conn, $row['id']);

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Textile QMS</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="login-body">
    <div class="login-container">
        <h2 style="font-weight: 700; letter-spacing: -0.025em;">Textile QMS</h2>
        <?php if (isset($_GET['error'])) echo "<div class='error'>" . htmlspecialchars($_GET['error']) . "</div>"; ?>
        <?php if (isset($error)) echo "<div class='error' style='background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem;'>" . htmlspecialchars($error) . "</div>"; ?>
        
        <div style="font-size: 0.8rem; color: #666; margin-bottom: 1rem; text-align: center;">
            Default login: <strong>admin</strong> / <strong>admin123</strong>
        </div>
        
        <form method="POST" style="box-shadow: none; padding: 0;">
            <label>Username</label>
            <input type="text" name="username" required>
            
            <label>Password</label>
            <input type="password" name="password" required>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
