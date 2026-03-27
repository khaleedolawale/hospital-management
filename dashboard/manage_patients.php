<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

// Fetch all patients
$result = $conn->query("SELECT * FROM patients ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Patients</title>
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
        <h1>Manage Patients</h1>
        <a href="add_patient.php" class="btn-view">+ Add Patient</a>
      </header>

      <section class="content">
        <table class="messages-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Gender</th>
              <th>Age</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= $row['name'] ?></td>
                  <td><?= $row['email'] ?></td>
                  <td><?= $row['phone'] ?></td>
                  <td><?= $row['gender'] ?></td>
                  <td><?= $row['age'] ?></td>
                  <td><?= $row['created_at'] ?></td>
                  <td>
                    <a class="btn-view" href="edit_patient.php?id=<?= $row['id'] ?>">Edit</a> |
                    <a class="btn-delete" href="delete_patient.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this patient?')">Delete</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7">No patients yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
