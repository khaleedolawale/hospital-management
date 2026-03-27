<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
include("../includes/db.php");
// Total Users
$totalUsers = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users) + 
    (SELECT COUNT(*) FROM patients) + 
    (SELECT COUNT(*) FROM doctors) AS total")->fetch_assoc()['total'];
// Total Doctors
$totalDoctors = $conn->query("SELECT COUNT(*) AS total FROM doctors")->fetch_assoc()['total'];
// Total Patients
$totalPatients = $conn->query("SELECT COUNT(*) AS total FROM patients")->fetch_assoc()['total'];
// Total Appointments
$totalAppointments = $conn->query("SELECT COUNT(*) AS total FROM appointments")->fetch_assoc()['total'];

// Recent Activities
$recentActivities = $conn->query("SELECT * FROM activities ORDER BY created_at DESC LIMIT 5");

// Example query to fetch recent activities with user names
// $result = $conn->query("
//     SELECT activities.action, activities.created_at, users.name, users.role 
//     FROM activities 
//     JOIN users ON activities.user = user
//     ORDER BY activities.created_at DESC 
//     LIMIT 5
// ");

// if ($result->num_rows > 0) {
//     while ($row = $result->fetch_assoc()) {
//         echo "<p><strong>{$row['name']}</strong> - {$row['action']} <em>({$row['created_at']})</em></p>";
//     }
// } else {
//     echo "<p>No recent activities yet.</p>";
// }


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - HMS</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-logo">
      🏥 HMS
    </div>
    <ul class="sidebar-menu">
    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
    <li><a href="manage_doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
    <li><a href="manage_patients.php"><i class="fas fa-procedures"></i> Patients</a></li>
    <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
    <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>

      <li>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to log out?')">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
      <h2>Admin Dashboard</h2>
      <div class="topbar-user">
        <i class="fas fa-user-shield"></i>
        <span>Admin</span>
      </div>
    </div>

    <!-- Dashboard Widgets -->
    <div class="dashboard-cards">
      <div class="card">
        <i class="fas fa-users"></i>
        <h3>Total Users</h3>
        <p><?php echo $totalUsers; ?></p>
      </div>
      <div class="card">
        <i class="fas fa-user-md"></i>
        <h3>Doctors</h3>
        <p><?php echo $totalDoctors; ?></p>
      </div>
      <div class="card">
        <i class="fas fa-procedures"></i>
        <h3>Patients</h3>
        <p><?php echo $totalPatients; ?></p>
      </div>
      <div class="card">
        <i class="fas fa-calendar-check"></i>
        <h3>Appointments</h3>
        <p><?php echo $totalAppointments; ?></p>
      </div>
    </div>

    <!-- Recent Activities -->
    <?php
$recentActivities = $conn->query("SELECT * FROM activities ORDER BY created_at DESC LIMIT 5");
?>

<h3>Recent Activities</h3>
<table>
  <thead>
    <tr>
      <th>User</th>
      <th>Role</th>
      <th>Action</th>
      <th>Date</th>
    </tr>
  </thead>
  <tbody>
    <?php while($row = $recentActivities->fetch_assoc()): ?>
      <tr>
        <td><?php echo $row['user']; ?></td>
        <td><?php echo $row['role']; ?></td>
        <td><?php echo $row['action']; ?></td>
        <td><?php echo $row['created_at']; ?></td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>


    <!-- Contact Messages -->
    <div class="data-section">
      <h3>Contact Messages</h3>
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Subject</th>
            <th>Message</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php
          include("../includes/db.php");
          $result = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
          if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                  echo "<tr>
                          <td>".$row['name']."</td>
                          <td>".$row['email']."</td>
                          <td>".$row['subject']."</td>
                          <td>".$row['message']."</td>
                          <td>".$row['created_at']."</td>
                        </tr>";
              }
          } else {
              echo "<tr><td colspan='4'>No messages yet</td></tr>";
          }
          ?>
        </tbody>
      </table>
      <a href="../includes/all_messages.php" class="view-all">View All Messages</a>
    </div>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
