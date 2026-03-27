<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $specialization = $conn->real_escape_string($_POST['specialization']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $schedule = $conn->real_escape_string($_POST['schedule']);

    $sql = "INSERT INTO doctors (name, specialization, email, phone, schedule) 
            VALUES ('$name', '$specialization', '$email', '$phone', '$schedule')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Doctor added successfully!'); window.location='manage_doctors.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Doctor</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a href="manage_doctors.php">⬅ Back to Manage Doctors</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <h1>Add Doctor</h1>
            </header>

            <section class="content">
                <form method="POST">
                    <label>Name</label>
                    <input type="text" name="name" required>

                    <label>Specialization</label>
                    <input type="text" name="specialization" required>

                    <label>Email</label>
                    <input type="email" name="email" required>

                    <label>Phone</label>
                    <input type="text" name="phone" required>

                    <label>Schedule</label>
                    <textarea name="schedule" rows="3"></textarea>

                    <button type="submit">Add Doctor</button>
                </form>
            </section>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
