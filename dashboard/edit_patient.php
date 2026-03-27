<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

$id = $_GET['id'];
$patient = $conn->query("SELECT * FROM patients WHERE id=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];

    $stmt = $conn->prepare("UPDATE patients SET name=?, email=?, phone=?, gender=?, age=? WHERE id=?");
    $stmt->bind_param("ssssii", $name, $email, $phone, $gender, $age, $id);
    $stmt->execute();

    header("Location: manage_patients.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Patient</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
  <div class="form-container">
    <h2>Edit Patient</h2>
    <form method="POST">
      <input type="text" name="name" value="<?= $patient['name'] ?>" required>
      <input type="email" name="email" value="<?= $patient['email'] ?>" required>
      <input type="text" name="phone" value="<?= $patient['phone'] ?>" required>
      <select name="gender" required>
        <option value="Male" <?= $patient['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= $patient['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
      </select>
      <input type="number" name="age" value="<?= $patient['age'] ?>" required>
      <button type="submit">Update</button>
    </form>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
