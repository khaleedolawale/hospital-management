<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

// Fetch all appointments with patient + doctor info
$sql = "SELECT a.*, p.name AS patient_name, d.name AS doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        ORDER BY a.appointment_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointments</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a class="link" href="admin.php">⬅ Back to Dashboard</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <h1>Appointments</h1>
            <a href="add_appointment.php" class="btn-view">+ Add Appointment</a>
        </header>

        <section class="content">
            <table class="messages-table">
                <thead>
                <tr>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date & Time</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                            <td><?php echo $row['appointment_date']; ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td>
                                <a class="btn-view" href="edit_appointment.php?id=<?php echo $row['id']; ?>">Edit</a> | 
                                <a class="btn-delete" href="delete_appointment.php?id=<?php echo $row['id']; ?>" 
                                   onclick="return confirm('Delete this appointment?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">No appointments yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
