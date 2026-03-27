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
$doctor_sql = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
$doctor_sql->bind_param("i", $user_id);
$doctor_sql->execute();
$doctor = $doctor_sql->get_result()->fetch_assoc();

// Count doctor's appointments by status
$appt_stats_sql = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE doctor_id = ?
");
$appt_stats_sql->bind_param("i", $doctor['id']);
$appt_stats_sql->execute();
$appt_stats = $appt_stats_sql->get_result()->fetch_assoc();

// Count today's appointments
$today_appt_sql = $conn->prepare("SELECT COUNT(*) as today_total,
           SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as today_confirmed
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date = CURDATE()
");
$today_appt_sql->bind_param("i", $doctor['id']);
$today_appt_sql->execute();
$today_stats = $today_appt_sql->get_result()->fetch_assoc();

// Fetch today's appointments schedule
$today_schedule = $conn->prepare("SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.email AS patient_email
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
");
$today_schedule->bind_param("i", $doctor['id']);
$today_schedule->execute();
$today_result = $today_schedule->get_result();

// Fetch upcoming appointments (next 7 days)
$upcoming = $conn->prepare("SELECT a.*, p.name AS patient_name, p.phone AS patient_phone
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND a.appointment_date > CURDATE() AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.appointment_date ASC LIMIT 5
");
$upcoming->bind_param("i", $doctor['id']);
$upcoming->execute();
$upcoming_result = $upcoming->get_result();

// Get next appointment
$next_appt_sql = $conn->prepare("SELECT a.*, p.name AS patient_name, p.phone AS patient_phone
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'Cancelled'
    ORDER BY a.appointment_date ASC LIMIT 1
");
$next_appt_sql->bind_param("i", $doctor['id']);
$next_appt_sql->execute();
$next_appointment = $next_appt_sql->get_result()->fetch_assoc();

// Count total patients treated
$patients_count_sql = $conn->prepare("SELECT COUNT(DISTINCT patient_id) as total_patients
    FROM appointments 
    WHERE doctor_id = ? AND status = 'Completed'
");
$patients_count_sql->bind_param("i", $doctor['id']);
$patients_count_sql->execute();
$total_patients = $patients_count_sql->get_result()->fetch_assoc()['total_patients'];

// Recent activities
$activity_sql = $conn->prepare("SELECT action, created_at FROM activities 
    WHERE user = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$activity_sql->bind_param("i", $user_id);
$activity_sql->execute();
$activities = $activity_sql->get_result();

// Get monthly appointment trends (last 6 months)
$monthly_trends = $conn->prepare("SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m') as month,
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_appointments
    FROM appointments 
    WHERE doctor_id = ? 
    AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$monthly_trends->bind_param("i", $doctor['id']);
$monthly_trends->execute();
$trends_result = $monthly_trends->get_result();

// Get pending appointment requests
$pending_requests = $conn->prepare("SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.email AS patient_email
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND a.status = 'Pending'
    ORDER BY a.created_at DESC
    LIMIT 5
");
$pending_requests->bind_param("i", $doctor['id']);
$pending_requests->execute();
$pending_result = $pending_requests->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - HMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced styles for doctor dashboard */
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

        .doctor-topbar h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
        }

        .doctor-topbar .topbar-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .doctor-topbar .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #4facfe;
            color: white;
        }

        .btn-primary:hover {
            background: #3d8bfe;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
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
        .enhanced-card.today { border-left-color: #38b2ac; }
        .enhanced-card.patients { border-left-color: #48bb78; }
        .enhanced-card.pending { border-left-color: #ed8936; }

        .enhanced-card .card-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .enhanced-card.total .card-icon { color: #4299e1; }
        .enhanced-card.today .card-icon { color: #38b2ac; }
        .enhanced-card.patients .card-icon { color: #48bb78; }
        .enhanced-card.pending .card-icon { color: #ed8936; }

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
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);
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
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-action-btn {
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
            border-color: #4facfe;
            color: #4facfe;
            transform: translateY(-1px);
        }

        .quick-action-btn i {
            display: block;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .schedule-item {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .schedule-item:last-child {
            border-bottom: none;
        }

        .schedule-time {
            font-weight: 600;
            color: #4facfe;
            min-width: 80px;
        }

        .schedule-patient {
            flex: 1;
            margin-left: 15px;
        }

        .schedule-actions {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .trends-chart {
            padding: 20px;
        }

        .trend-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .trend-item:last-child {
            border-bottom: none;
        }

        .trend-month {
            font-weight: 600;
            color: #2d3748;
        }

        .trend-stats {
            display: flex;
            gap: 15px;
            font-size: 14px;
        }

        .trend-total {
            color: #4facfe;
            font-weight: 600;
        }

        .trend-completed {
            color: #48bb78;
            font-weight: 600;
        }

        .empty-state {
            padding: 40px;
            text-align: center;
            color: #718096;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .data-sections {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .doctor-sidebar{
                width: 50%;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Sidebar -->
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
            <p style="opacity: 0.8; font-size: 12px;">Doctor ID: #<?php echo $doctor['id']; ?></p>
        </div>

        <ul class="sidebar-menu">
            <li><a href="doctor.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
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
        <!-- Enhanced Topbar -->
        <div class="doctor-topbar">
            <h1>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, Dr. <?php echo htmlspecialchars($doctor['name']); ?> 👋</h1>
            <div class="topbar-actions">
                <a href="add_prescription.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Prescription
                </a>
                <a href="schedule.php" class="btn btn-primary">
                    <i class="fas fa-calendar"></i> View Schedule
                </a>
                <div class="topbar-user">
                    <i class="fas fa-user-circle" style="font-size: 24px; color: #4facfe;"></i>
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
                
                <div class="enhanced-card today">
                    <div class="card-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <h3>Today's Appointments</h3>
                    <p class="card-value"><?php echo $today_stats['today_total']; ?></p>
                </div>
                
                <div class="enhanced-card patients">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Total Patients</h3>
                    <p class="card-value"><?php echo $total_patients; ?></p>
                </div>
                
                <div class="enhanced-card pending">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Pending Requests</h3>
                    <p class="card-value"><?php echo $appt_stats['pending']; ?></p>
                </div>
            </div>

            <!-- Next Appointment Highlight -->
            <?php if ($next_appointment): ?>
            <div class="next-appointment-card">
                <h3><i class="fas fa-calendar-alt"></i> Next Patient</h3>
                <div class="appointment-details">
                    <div class="appointment-detail">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($next_appointment['patient_name']); ?></span>
                    </div>
                    <div class="appointment-detail">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('D, M j, Y', strtotime($next_appointment['appointment_date'])); ?></span>
                    </div>
                    <div class="appointment-detail">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?></span>
                    </div>
                    <div class="appointment-detail">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars($next_appointment['patient_phone']); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="add_prescription.php" class="quick-action-btn">
                    <i class="fas fa-prescription"></i>
                    Write Prescription
                </a>
                <a href="appointments.php" class="quick-action-btn">
                    <i class="fas fa-list"></i>
                    View All Appointments
                </a>
                <a href="patients.php" class="quick-action-btn">
                    <i class="fas fa-user-friends"></i>
                    Patient Records
                </a>
                <a href="schedule.php" class="quick-action-btn">
                    <i class="fas fa-calendar-alt"></i>
                    Manage Schedule
                </a>
                <a href="reports.php" class="quick-action-btn">
                    <i class="fas fa-chart-line"></i>
                    View Reports
                </a>
            </div>

            <!-- Data Sections -->
            <div class="data-sections">
                <!-- Today's Schedule -->
                <div class="data-section">
                    <h3>
                        Today's Schedule
                        <span style="font-size: 14px; font-weight: normal; color: #718096;">
                            <?php echo date('M j, Y'); ?>
                        </span>
                    </h3>
                    <?php if ($today_result->num_rows > 0): ?>
                        <?php while ($schedule = $today_result->fetch_assoc()): ?>
                        <div class="schedule-item">
                            <div class="schedule-time">
                                <?php echo date('g:i A', strtotime($schedule['appointment_time'])); ?>
                            </div>
                            <div class="schedule-patient">
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($schedule['patient_name']); ?></div>
                                <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($schedule['patient_phone']); ?></div>
                            </div>
                            <div class="schedule-actions">
                                <span class="status-badge status-<?php echo strtolower($schedule['status']); ?>">
                                    <?php echo $schedule['status']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <p>No appointments scheduled for today</p>
                            <p style="font-size: 14px; margin-top: 5px;">Enjoy your free day!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Appointment Requests -->
                <div class="data-section">
                    <h3>
                        Pending Requests
                        <?php if ($pending_result->num_rows > 0): ?>
                            <a href="appointments.php?filter=pending" style="font-size: 14px; color: #4facfe; text-decoration: none;">View All</a>
                        <?php endif; ?>
                    </h3>
                    <?php if ($pending_result->num_rows > 0): ?>
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date & Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($pending = $pending_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($pending['patient_name']); ?></div>
                                        <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($pending['patient_phone']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($pending['appointment_date'])); ?></div>
                                        <div style="font-size: 12px; color: #718096;"><?php echo date('g:i A', strtotime($pending['appointment_time'])); ?></div>
                                    </td>
                                    <td>
                                        <a href="confirm_appointment.php?id=<?php echo $pending['id']; ?>" class="btn-sm btn-success">Confirm</a>
                                        <a href="cancel_appointment.php?id=<?php echo $pending['id']; ?>" class="btn-sm" style="background: #fed7d7; color: #c53030;">Cancel</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending requests</p>
                            <p style="font-size: 14px; margin-top: 5px;">All appointments are up to date</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Additional Data Sections -->
            <div class="data-sections" style="margin-top: 30px;">
                <!-- Upcoming Appointments -->
                <div class="data-section">
                    <h3>Upcoming Appointments (Next 7 Days)</h3>
                    <?php if ($upcoming_result->num_rows > 0): ?>
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Patient</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($upcoming_appt = $upcoming_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($upcoming_appt['appointment_date'])); ?></div>
                                        <div style="font-size: 12px; color: #718096;"><?php echo date('g:i A', strtotime($upcoming_appt['appointment_time'])); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($upcoming_appt['patient_name']); ?></div>
                                        <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($upcoming_appt['patient_phone']); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($upcoming_appt['status']); ?>">
                                            <?php echo $upcoming_appt['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-plus"></i>
                            <p>No upcoming appointments</p>
                            <p style="font-size: 14px; margin-top: 5px;">Your schedule is clear for the next 7 days</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Monthly Trends -->
                <div class="data-section">
                    <h3>Monthly Appointment Trends</h3>
                    <div class="trends-chart">
                        <?php if ($trends_result->num_rows > 0): ?>
                            <?php while ($trend = $trends_result->fetch_assoc()): ?>
                            <div class="trend-item">
                                <div class="trend-month"><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></div>
                                <div class="trend-stats">
                                    <span class="trend-total"><?php echo $trend['total_appointments']; ?> Total</span>
                                    <span class="trend-completed"><?php echo $trend['completed_appointments']; ?> Completed</span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <p>No data available</p>
                                <p style="font-size: 14px; margin-top: 5px;">Trends will appear as you see more patients</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="data-section" style="margin-top: 30px;">
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
                                <div class="activity-time">Activity log will appear here</div>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Quick Statistics Overview -->
            <div class="data-section" style="margin-top: 30px;">
                <h3>Quick Statistics Overview & Doctor Info</h3>
                <div style="padding: 20px;">
                    <!-- Doctor Information Card -->
                    <div style="background: #f7fafc; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0; color: #2d3748;">Doctor Information</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <label style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Full Name</label>
                                <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($doctor['name']); ?></div>
                            </div>
                            <div>
                                <label style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Specialization</label>
                                <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
                            </div>
                            <div>
                                <label style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Email</label>
                                <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($doctor['email']); ?></div>
                            </div>
                            <div>
                                <label style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Phone</label>
                                <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($doctor['phone']); ?></div>
                            </div>
                            <?php if ($doctor['schedule']): ?>
                            <div>
                                <label style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Schedule</label>
                                <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($doctor['schedule']); ?></div>
                            </div>
                            <?php endif; ?>
                            <div>
                                <label style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Joined</label>
                                <div style="font-weight: 600; color: #2d3748;"><?php echo date('M j, Y', strtotime($doctor['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Statistics -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                        <div style="text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #48bb78;"><?php echo $appt_stats['completed']; ?></div>
                            <div style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Completed</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #4facfe;"><?php echo $appt_stats['confirmed']; ?></div>
                            <div style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Confirmed</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #c53030;"><?php echo $appt_stats['cancelled']; ?></div>
                            <div style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Cancelled</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #38b2ac;">
                                <?php echo $appt_stats['total'] > 0 ? round(($appt_stats['completed'] / $appt_stats['total']) * 100) : 0; ?>%
                            </div>
                            <div style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px;">Success Rate</div>
                        </div>
                    </div>
                </div>
            </div>
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

            // Add real-time clock
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', {
                    hour12: true,
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                // You can add a clock element if needed
                const clockElement = document.getElementById('current-time');
                if (clockElement) {
                    clockElement.textContent = timeString;
                }
            }

            // Update clock every second
            updateClock();
            setInterval(updateClock, 1000);

            // Add notification for pending requests
            const pendingCount = <?php echo $appt_stats['pending']; ?>;
            if (pendingCount > 0) {
                setTimeout(() => {
                    // You can add a notification system here
                    console.log(`You have ${pendingCount} pending appointment requests`);
                }, 2000);
            }

            // Add hover effects to schedule items
            const scheduleItems = document.querySelectorAll('.schedule-item');
            scheduleItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f7fafc';
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                    this.style.transform = 'translateX(0)';
                });
            });

            // Auto-refresh appointment data every 5 minutes
            setInterval(() => {
                // You can implement AJAX refresh here
                console.log('Refreshing appointment data...');
            }, 5 * 60 * 1000);
        });

        // Function to confirm appointment
        function confirmAppointment(appointmentId) {
            if (confirm('Are you sure you want to confirm this appointment?')) {
                window.location.href = `confirm_appointment.php?id=${appointmentId}`;
            }
        }

        // Function to cancel appointment
        function cancelAppointment(appointmentId) {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                window.location.href = `cancel_appointment.php?id=${appointmentId}`;
            }
        }
    </script>
</body>
</html>