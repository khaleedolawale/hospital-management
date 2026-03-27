<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

$result = $conn->query("SELECT * FROM doctors ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Doctors</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a class="link" href="add_doctor.php">➕ Add Doctor</a></li>
                <li><a class="link" href="../dashboard/admin.php">⬅ Back to Dashboard</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <h1>Manage Doctors</h1>
            </header>

            <section class="content">
                <table class="messages-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Specialization</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Schedule</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['specialization']; ?></td>
                                    <td><?php echo $row['email']; ?></td>
                                    <td><?php echo $row['phone']; ?></td>
                                    <td><?php echo $row['schedule']; ?></td>
                                    <td>
                                        <a class="btn-view" href="edit_doctor.php?id=<?php echo $row['id']; ?>">Edit</a> | 
                                        <a class="btn-delete" href="delete_doctor.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this doctor?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No doctors added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
