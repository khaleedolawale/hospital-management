<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

// Fetch user by ID
if (isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    $result = $conn->query("SELECT * FROM users WHERE id = $user_id");

    if ($result->num_rows == 0) {
        die("User not found!");
    }

    $user = $result->fetch_assoc();
} else {
    die("Invalid request.");
}

// Update user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $role  = $conn->real_escape_string($_POST['role']);

    $sql = "UPDATE users SET 
                name='$name', 
                email='$email', 
                role='$role', 
                updated_at=NOW() 
            WHERE id=$user_id";

    if ($conn->query($sql)) {
        header("Location: manage_users.php?success=1");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a class="link" href="manage_users.php">⬅ Back to Manage Users</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <h1>Edit User</h1>
            </header>

            <section class="content">
                <form method="POST" class="edit-form">
                    <label>Name:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                    <label>Role:</label>
                    <select name="role" required>
                        <option value="user" <?php if($user['role']=='user') echo "selected"; ?>>Doctor</option>
                        <option value="user" <?php if($user['role']=='user') echo "selected"; ?>>Patient</option>
                        <option value="admin" <?php if($user['role']=='admin') echo "selected"; ?>>Admin</option>
                    </select>

                    <button type="submit">Update User</button>
                </form>
            </section>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
