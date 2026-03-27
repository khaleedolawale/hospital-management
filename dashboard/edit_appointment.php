<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

$id = (int)$_GET['id'];

// Fetch appointment
$appointment = $conn->query("SELECT * FROM appointments WHERE id=$id")->fetch_assoc();

// Fetch patients & doctors for dropdowns
$patients = $conn->query("SELECT id, name FROM patients ORDER BY name");
$doctors  = $conn->query("SELECT id, name FROM doctors ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $status = $_POST['status'];

    $sql = "UPDATE appointments 
            SET patient_id='$patient_id', doctor_id='$doctor_id', appointment_date='$date', status='$status' 
            WHERE id=$id";

    if ($conn->query($sql)) {
        header("Location: appointments.php?updated=1");
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
    <title>Edit Appointment</title>
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
            <h1>Edit Appointment</h1>
        </header>

        <section class="content">
            <form method="POST">
                <label>Patient:</label>
                <select name="patient_id" required>
                    <?php while ($p = $patients->fetch_assoc()): ?>
                        <option value="<?php echo $p['id']; ?>" 
                            <?php if ($appointment['patient_id'] == $p['id']) echo 'selected'; ?>>
                            <?php echo $p['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Doctor:</label>
                <select name="doctor_id" required>
                    <?php while ($d = $doctors->fetch_assoc()): ?>
                        <option value="<?php echo $d['id']; ?>" 
                            <?php if ($appointment['doctor_id'] == $d['id']) echo 'selected'; ?>>
                            <?php echo $d['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Date & Time:</label>
                <input type="datetime-local" name="appointment_date" 
                       value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['appointment_date'])); ?>" required>

                <label>Status:</label>
                <select name="status">
                    <?php 
                        $statuses = ['Pending','Confirmed','Completed','Cancelled'];
                        foreach ($statuses as $s) {
                            $selected = ($appointment['status'] == $s) ? 'selected' : '';
                            echo "<option value='$s' $selected>$s</option>";
                        }
                    ?>
                </select>

                <button type="submit">Update Appointment</button>
            </form>
        </section>
    </main>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
