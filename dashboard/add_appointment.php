<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

// Fetch patients and doctors for dropdowns
$patients = $conn->query("SELECT id, name FROM patients ORDER BY name");
$doctors  = $conn->query("SELECT id, name FROM doctors ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $status = $_POST['status'];

    $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) 
            VALUES ('$patient_id', '$doctor_id', '$date', '$status')";

    if ($conn->query($sql)) {
        header("Location: appointments.php?success=1");
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
    <title>Add Appointment</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a class="link" href="appointments.php">⬅ Back to Appointments</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <h1>Add Appointment</h1>
        </header>

        <section class="content">
            <form method="POST">
                <label>Patient:</label>
                <select name="patient_id" required>
                    <option value="">-- Select Patient --</option>
                    <?php while ($p = $patients->fetch_assoc()): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Doctor:</label>
                <select name="doctor_id" required>
                    <option value="">-- Select Doctor --</option>
                    <?php while ($d = $doctors->fetch_assoc()): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo $d['name']; ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Date & Time:</label>
                <input type="datetime-local" name="appointment_date" required>

                <label>Status:</label>
                <select name="status">
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <button type="submit">Save Appointment</button>
            </form>
        </section>
    </main>
</div>

<script src="../assets/js/script.js"></script>

</body>
</html>
