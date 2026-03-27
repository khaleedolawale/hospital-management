<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];

    $stmt = $conn->prepare("INSERT INTO patients (name, email, phone, gender, age) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $email, $phone, $gender, $age);
    $stmt->execute();

    header("Location: manage_patients.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Patient</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<aside class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a class="link" href="manage_patients.php">⬅ Back to Manage Patients</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <h1>Add Patient</h1>
            </header>
  <div class="form-container">
    <h2>Add New Patient</h2>
    <form method="POST">
      <input type="text" name="name" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="text" name="phone" placeholder="Phone" required>
      <select name="gender" required>
        <option value="">-- Select Gender --</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
      </select>
      <input type="number" name="age" placeholder="Age" required>
      <button type="submit">Add Patient</button>
    </form>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
