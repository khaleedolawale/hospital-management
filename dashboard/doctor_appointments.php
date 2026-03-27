<?php
session_start();
include("../includes/db.php");

// Check if logged in and role = doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch doctor details
$doctor_sql = $conn->prepare("SELECT id, name, specialization, email, phone, schedule, created_at FROM doctors WHERE id = ?");
$doctor_sql->bind_param("i", $user_id);
$doctor_sql->execute();
$doctor = $doctor_sql->get_result()->fetch_assoc();

// Handle appointment actions
if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    
    if ($action == 'confirm') {
        $update_sql = $conn->prepare("UPDATE appointments SET status = 'Confirmed' WHERE id = ? AND doctor_id = ?");
        $update_sql->bind_param("ii", $appointment_id, $doctor['id']);
        if ($update_sql->execute()) {
            $success_message = "Appointment confirmed successfully!";
        }
    } elseif ($action == 'cancel') {
        $update_sql = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND doctor_id = ?");
        $update_sql->bind_param("ii", $appointment_id, $doctor['id']);
        if ($update_sql->execute()) {
            $success_message = "Appointment cancelled successfully!";
        }
    } elseif ($action == 'complete') {
        $update_sql = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ? AND doctor_id = ?");
        $update_sql->bind_param("ii", $appointment_id, $doctor['id']);
        if ($update_sql->execute()) {
            $success_message = "Appointment marked as completed!";
        }
    }
}

// Get filter parameters
$filter_status = isset($_GET['filter']) ? $_GET['filter'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query based on filters
$where_conditions = ["a.doctor_id = ?"];
$params = [$doctor['id']];
$param_types = "i";

if ($filter_status && $filter_status !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = ucfirst($filter_status);
    $param_types .= "s";
}

if ($filter_date) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $filter_date;
    $param_types .= "s";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Fetch appointments with filters
$appointments_sql = $conn->prepare("SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.email AS patient_email
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id
    $where_clause
    ORDER BY a.appointment_date DESC
");

$appointments_sql->bind_param($param_types, ...$params);
$appointments_sql->execute();
$appointments_result = $appointments_sql->get_result();

// Get appointment statistics
$stats_sql = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE doctor_id = ?
");
$stats_sql->bind_param("i", $doctor['id']);
$stats_sql->execute();
$stats = $stats_sql->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same styles as doctor dashboard */
        .doctor-sidebar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .doctor-sidebar .sidebar-logo {
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .doctor-sidebar .doctor-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .doctor-sidebar .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
        }

        .doctor-sidebar .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .doctor-sidebar .sidebar-menu li {
            margin: 5px 0;
        }

        .doctor-sidebar .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .doctor-sidebar .sidebar-menu a:hover,
        .doctor-sidebar .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }

        .doctor-sidebar .sidebar-menu i {
            margin-right: 15px;
            width: 20px;
        }

        .doctor-main-content {
            margin-left: 280px;
            padding: 0;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .doctor-topbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .content-section {
            padding: 30px;
        }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .appointments-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th,
        .modern-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .modern-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modern-table tbody tr:hover {
            background: #f7fafc;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-confirmed { background: #c6f6d5; color: #2f855a; }
        .status-pending { background: #feebc8; color: #c05621; }
        .status-completed { background: #bee3f8; color: #2c5aa0; }
        .status-cancelled { background: #fed7d7; color: #c53030; }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary { background: #4facfe; color: white; }
        .btn-success { background: #48bb78; color: white; }
        .btn-danger { background: #f56565; color: white; }
        .btn-warning { background: #ed8936; color: white; }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="doctor-sidebar">
        <div class="sidebar-logo">
            🩺 HMS Doctor
        </div>
        
        <div class="doctor-info">
            <div class="doctor-avatar">
                <i class="fas fa-user-md"></i>
            </div>
            <h4><?php echo htmlspecialchars($doctor['name']); ?></h4>
            <p style="opacity: 0.8; font-size: 14px;"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
        </div>

        <ul class="sidebar-menu">
            <li><a href="doctor.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="doctor_appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
            <li><a href="doctor_patients.php"><i class="fas fa-users"></i> My Patients</a></li>
            <li><a href="doctor_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
            <li><a href="doctor_medical_records.php"><i class="fas fa-file-medical-alt"></i> Medical Records</a></li>
            <li><a href="doctor_prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
            <li><a href="doctor_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
            <li><a href="doctor_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to log out?')">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="doctor-main-content">
        <div class="doctor-topbar">
            <h1>My Appointments</h1>
            <div class="topbar-actions">
                <a href="doctor.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="content-section">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number" style="color: #4299e1;"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #48bb78;"><?php echo $stats['confirmed']; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #ed8936;"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #38b2ac;"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #f56565;"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="filter">Filter by Status</label>
                            <select name="filter" id="filter">
                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date">Filter by Date</label>
                            <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <a href="appointments.php" class="btn" style="background: #e2e8f0; color: #4a5568;">Clear Filters</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Appointments Table -->
            <div class="appointments-table">
                <div class="table-header">
                    <h3>All Appointments</h3>
                </div>
                
                <?php if ($appointments_result->num_rows > 0): ?>
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient Details</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($appointment = $appointments_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                    <div style="font-size: 12px; color: #718096;"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                    <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                    <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($appointment['patient_email']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo $appointment['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($appointment['status'] == 'Pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" name="action" value="confirm" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" name="action" value="cancel" class="btn btn-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($appointment['status'] == 'Confirmed'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" name="action" value="complete" class="btn btn-primary">
                                                    <i class="fas fa-check-circle"></i> Complete
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" name="action" value="cancel" class="btn btn-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #718096; font-size: 12px;">No actions available</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 60px; text-align: center; color: #718096;">
                        <i class="fas fa-calendar" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                        <h3>No appointments found</h3>
                        <p>No appointments match your current filters.</p>
                        <a href="appointments.php" class="btn btn-primary">View All Appointments</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when filter changes
            const filterSelect = document.getElementById('filter');
            const dateInput = document.getElementById('date');
            
            filterSelect.addEventListener('change', function() {
                this.form.submit();
            });
            
            dateInput.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>