<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

if (!isset($_GET['id'])) {
    header("Location: manage_doctors.php");
    exit();
}

$id = intval($_GET['id']);
$doctor = $conn->query("SELECT * FROM doctors WHERE id=$id")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $specialization = $conn->real_escape_string($_POST['specialization']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $schedule = $conn->real_escape_string($_POST['schedule']);

    $sql = "UPDATE doctors 
            SET name='$name', specialization='$specialization', email='$email', phone='$phone', schedule='$schedule' 
            WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Doctor updated successfully!'); window.location='manage_doctors.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Doctor</title>
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
                <h1>Edit Doctor</h1>
            </header>

            <section class="content">
                <form method="POST">
                    <label>Name</label>
                    <input type="text" name="name" value="<?php echo $doctor['name']; ?>" required>

                    <label>Specialization</label>
                    <input type="text" name="specialization" value="<?php echo $doctor['specialization']; ?>" required>

                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo $doctor['email']; ?>" required>

                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo $doctor['phone']; ?>" required>

                    <label>Schedule</label>
                    <textarea name="schedule" rows="3"><?php echo $doctor['schedule']; ?></textarea>

                    <button type="submit">Update Doctor</button>
                </form>
            </section>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
