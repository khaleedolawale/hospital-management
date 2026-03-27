<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

// --- Change password logic ---
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $id = $_SESSION['user_id'];
    $res = $conn->query("SELECT password FROM users WHERE id='$id'");
    $row = $res->fetch_assoc();

    if (password_verify($current, $row['password'])) {
        if ($new === $confirm) {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $conn->query("UPDATE users SET password='$hashed' WHERE id='$id'");
            $msg = "✅ Password changed successfully!";
        } else {
            $msg = "❌ New passwords don’t match.";
        }
    } else {
        $msg = "❌ Current password is wrong.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</head>
<body class="settings-page">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a class="link" href="../dashboard/admin.php">⬅ Back to Dashboard</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <h1> Settings</h1>
                <!-- Dark Mode Toggle -->
                <button id="darkToggle" class="dark-toggle">
                    <i class="fas fa-moon"></i>
                </button>
            </header>

            <section class="content">
                <h2>Change Password</h2>
                <?php if ($msg): ?>
                    <p class="message"><?php echo $msg; ?></p>
                <?php endif; ?>
                <form method="POST">
                    <input type="password" name="current_password" placeholder="Current Password" required>
                    <input type="password" name="new_password" placeholder="New Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <button type="submit" name="change_password">Change Password</button>
                </form>
            </section>
        </main>
    </div>

    <!-- <script>
    // --- Dark Mode Toggle ---
    const toggleBtn = document.getElementById("darkToggle");
    const body = document.body;

    // Load from localStorage
    if (localStorage.getItem("darkMode") === "enabled") {
        body.classList.add("dark");
        toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
    }

    toggleBtn.addEventListener("click", () => {
        body.classList.toggle("dark");
        if (body.classList.contains("dark")) {
            localStorage.setItem("darkMode", "enabled");
            toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            localStorage.setItem("darkMode", "disabled");
            toggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
        }
    });
    </script> -->

    <script src="../assets/js/script.js"></script>
</body>
</html>
