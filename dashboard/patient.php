<?php
session_start();
include("../includes/db.php");

// Check if logged in and role = patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch patient details
$patient_sql = $conn->prepare("SELECT * FROM patients WHERE id = ?");
$patient_sql->bind_param("i", $user_id);
$patient_sql->execute();
$patient = $patient_sql->get_result()->fetch_assoc();

// Count patient's appointments by status
$appt_stats_sql = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE patient_id = ?
");
$appt_stats_sql->bind_param("i", $patient['id']);
$appt_stats_sql->execute();
$appt_stats = $appt_stats_sql->get_result()->fetch_assoc();

// Fetch upcoming appointments with more details
$upcoming = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization, d.phone AS doctor_phone
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC LIMIT 5
");
$upcoming->bind_param("i", $patient['id']);
$upcoming->execute();
$upcoming_result = $upcoming->get_result();

// Fetch recent completed appointments (medical history)
$history_sql = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = ? AND a.status = 'completed'
    ORDER BY a.appointment_date DESC
    LIMIT 3
");
$history_sql->bind_param("i", $patient['id']);
$history_sql->execute();
$history_result = $history_sql->get_result();

// Get next appointment
$next_appt_sql = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
    ORDER BY a.appointment_date ASC
    LIMIT 1
");
$next_appt_sql->bind_param("i", $patient['id']);
$next_appt_sql->execute();
$next_appointment = $next_appt_sql->get_result()->fetch_assoc();

// Recent activities
$activity_sql = $conn->prepare("SELECT action, created_at FROM activities 
    WHERE user = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$activity_sql->bind_param("i", $user_id);
$activity_sql->execute();
$activities = $activity_sql->get_result();

// Count doctors patient has visited
$doctors_count_sql = $conn->prepare("SELECT COUNT(DISTINCT doctor_id) as total_doctors
    FROM appointments 
    WHERE patient_id = ? AND status = 'Completed'
");
$doctors_count_sql->bind_param("i", $patient['id']);
$doctors_count_sql->execute();
$doctors_visited = $doctors_count_sql->get_result()->fetch_assoc()['total_doctors'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - HMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced styles for patient dashboard */
        .patient-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            transition: all 0.3s ease;
        }

        .patient-sidebar .sidebar-logo {
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .patient-sidebar .patient-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .patient-sidebar .patient-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 32px;
        }

        .patient-sidebar .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .patient-sidebar .sidebar-menu li {
            margin: 5px 0;
        }

        .patient-sidebar .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .patient-sidebar .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }

        .patient-sidebar .sidebar-menu i {
            margin-right: 15px;
            width: 20px;
        }

        .patient-main-content {
            margin-left: 280px;
            padding: 0;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .patient-topbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .patient-topbar h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
        }

        .patient-topbar .topbar-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .patient-topbar .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .dashboard-content {
            padding: 30px;
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .enhanced-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .enhanced-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .enhanced-card.total { border-left-color: #4299e1; }
        .enhanced-card.confirmed { border-left-color: #48bb78; }
        .enhanced-card.pending { border-left-color: #ed8936; }
        .enhanced-card.doctors { border-left-color: #9f7aea; }

        .enhanced-card .card-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .enhanced-card.total .card-icon { color: #4299e1; }
        .enhanced-card.confirmed .card-icon { color: #48bb78; }
        .enhanced-card.pending .card-icon { color: #ed8936; }
        .enhanced-card.doctors .card-icon { color: #9f7aea; }

        .enhanced-card h3 {
            margin: 0 0 5px 0;
            color: #4a5568;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .enhanced-card .card-value {
            font-size: 32px;
            font-weight: bold;
            color: #2d3748;
            margin: 0;
        }

        .next-appointment-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .next-appointment-card h3 {
            margin: 0 0 15px 0;
            font-size: 18px;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .appointment-detail {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .appointment-detail i {
            width: 20px;
            opacity: 0.8;
        }

        .data-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .data-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .data-section h3 {
            background: #f7fafc;
            margin: 0;
            padding: 20px;
            color: #2d3748;
            border-bottom: 1px solid #e2e8f0;
            font-size: 18px;
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

        .activity-list {
            list-style: none;
            padding: 20px;
            margin: 0;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #4a5568;
        }

        .activity-content {
            flex: 1;
        }

        .activity-time {
            font-size: 12px;
            color: #718096;
        }

        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-action-btn {
            flex: 1;
            padding: 15px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #4a5568;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .quick-action-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-1px);
        }

        .quick-action-btn i {
            display: block;
            font-size: 24px;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .data-sections {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Sidebar -->
    <div class="patient-sidebar">
        <div class="sidebar-logo">
            🏥 HMS Patient
        </div>
        
        <div class="patient-info">
            <div class="patient-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h4><?php echo htmlspecialchars($patient['name']); ?></h4>
            <p style="opacity: 0.8; font-size: 14px;">Patient ID: #<?php echo $patient['id']; ?></p>
        </div>

        <ul class="sidebar-menu">
            <li><a href="patient.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="patient_appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="patient_book_appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="patient_medical_history.php"><i class="fas fa-file-medical"></i> Medical History</a></li>
            <li><a href="patient_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
            <li><a href="patient_billing.php"><i class="fas fa-receipt"></i> Billing</a></li>
            <li>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to log out?')">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="patient-main-content">
        <!-- Enhanced Topbar -->
        <div class="patient-topbar">
            <h1>Welcome back, <?php echo htmlspecialchars($patient['name']); ?> 👋</h1>
            <div class="topbar-actions">
                <a href="book_appointment.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Book Appointment
                </a>
                <div class="topbar-user">
                    <i class="fas fa-user-circle" style="font-size: 24px; color: #667eea;"></i>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Statistics Cards -->
            <div class="dashboard-cards">
                <div class="enhanced-card total">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Total Appointments</h3>
                    <p class="card-value"><?php echo $appt_stats['total']; ?></p>
                </div>
                
                <div class="enhanced-card confirmed">
                    <div class="card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Confirmed</h3>
                    <p class="card-value"><?php echo $appt_stats['confirmed']; ?></p>
                </div>
                
                <div class="enhanced-card pending">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Pending</h3>
                    <p class="card-value"><?php echo $appt_stats['pending']; ?></p>
                </div>
                
                <div class="enhanced-card doctors">
                    <div class="card-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3>Doctors Visited</h3>
                    <p class="card-value"><?php echo $doctors_visited; ?></p>
                </div>
            </div>

            <!-- Next Appointment Highlight -->
            <?php if ($next_appointment): ?>
            <div class="next-appointment-card">
                <h3><i class="fas fa-calendar-alt"></i> Next Appointment</h3>
                <div class="appointment-details">
                    <div class="appointment-detail">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('D, M j, Y', strtotime($next_appointment['appointment_date'])); ?></span>
                    </div>
                    <div class="appointment-detail">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?></span>
                    </div>
                    <div class="appointment-detail">
                        <i class="fas fa-user-md"></i>
                        <span>Dr. <?php echo htmlspecialchars($next_appointment['doctor_name']); ?></span>
                    </div>
                    <div class="appointment-detail">
                        <i class="fas fa-stethoscope"></i>
                        <span><?php echo htmlspecialchars($next_appointment['specialization']); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="patient_book_appointment.php" class="quick-action-btn">
                    <i class="fas fa-calendar-plus"></i>
                    Book New Appointment
                </a>
                <a href="patient_appointments.php" class="quick-action-btn">
                    <i class="fas fa-list"></i>
                    View All Appointments
                </a>
                <a href="patient_medical_history.php" class="quick-action-btn">
                    <i class="fas fa-file-medical"></i>
                    Medical History
                </a>
                <a href="patient_profile.php" class="quick-action-btn">
                    <i class="fas fa-user-edit"></i>
                    Update Profile
                </a>
            </div>

            <!-- Data Sections -->
            <div class="data-sections">
                <!-- Upcoming Appointments -->
                <div class="data-section">
                    <h3>Upcoming Appointments</h3>
                    <?php if ($upcoming_result->num_rows > 0): ?>
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $upcoming_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($row['appointment_date'])); ?></div>
                                        <div style="font-size: 12px; color: #718096;"><?php echo date('g:i A', strtotime($row['appointment_time'])); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;">Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></div>
                                        <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($row['specialization']); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $row['status']; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: #718096;">
                            <i class="fas fa-calendar" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                            <p>No upcoming appointments</p>
                            <a href="book_appointment.php" class="btn btn-primary">Book Your First Appointment</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="data-section">
                    <h3>Recent Activities</h3>
                    <ul class="activity-list">
                        <?php if ($activities->num_rows > 0): ?>
                            <?php while ($act = $activities->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <div><?php echo htmlspecialchars($act['action']); ?></div>
                                    <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($act['created_at'])); ?></div>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-info"></i>
                                </div>
                                <div class="activity-content">
                                    <div>No recent activities yet</div>
                                    <div class="activity-time">Start by booking an appointment</div>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Medical History Preview -->
            <?php if ($history_result->num_rows > 0): ?>
            <div class="data-section" style="margin-top: 30px;">
                <h3>Recent Medical History</h3>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($history = $history_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($history['appointment_date'])); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($history['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($history['specialization']); ?></td>
                            <td>
                                <a href="appointment_details.php?id=<?php echo $history['id']; ?>" class="btn btn-sm">
                                    View Details
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add some interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.enhanced-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });

            // Add current time
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', {
                    hour12: true,
                    hour: 'numeric',
                    minute: '2-digit'
                });
                // You can add a time display element if needed
            }

            updateTime();
            setInterval(updateTime, 60000); // Update every minute
        });
    </script>
</body>
</html>