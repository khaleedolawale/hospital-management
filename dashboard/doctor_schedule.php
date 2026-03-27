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

// Handle schedule update
if (isset($_POST['update_schedule'])) {
    $new_schedule = $_POST['schedule'];
    $update_sql = $conn->prepare("UPDATE doctors SET schedule = ? WHERE id = ?");
    $update_sql->bind_param("si", $new_schedule, $doctor['id']);
    if ($update_sql->execute()) {
        $success_message = "Schedule updated successfully!";
        $doctor['schedule'] = $new_schedule; // Update the current data
    } else {
        $error_message = "Error updating schedule.";
    }
}

// Get current week dates
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime($current_week_start . " +$i days"));
}

// Fetch appointments for current week
$weekly_appointments = [];
foreach ($week_dates as $date) {
    $day_appointments_sql = $conn->prepare("SELECT a.*, p.name AS patient_name, p.phone AS patient_phone
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND a.appointment_date = ?
        -- ORDER BY a.appointment_time ASC
    ");
    $day_appointments_sql->bind_param("is", $doctor['id'], $date);
    $day_appointments_sql->execute();
    $weekly_appointments[$date] = $day_appointments_sql->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get next month's appointments count
$next_month_sql = $conn->prepare("SELECT COUNT(*) as count
    FROM appointments 
    WHERE doctor_id = ? 
    AND appointment_date >= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
    AND appointment_date < DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
    AND status != 'Cancelled'
");
$next_month_sql->bind_param("i", $doctor['id']);
$next_month_sql->execute();
$next_month_count = $next_month_sql->get_result()->fetch_assoc()['count'];

// Get this week's stats
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$week_stats_sql = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
    FROM appointments 
    WHERE doctor_id = ? 
    AND appointment_date BETWEEN ? AND ?
");
$week_stats_sql->bind_param("iss", $doctor['id'], $week_start, $week_end);
$week_stats_sql->execute();
$week_stats = $week_stats_sql->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same sidebar styles */
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

        .schedule-settings {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .schedule-form {
            display: grid;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            color: #4a5568;
            font-weight: 600;
        }

        .form-group textarea {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .stats-row {
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
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .weekly-calendar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .calendar-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }

        .calendar-day {
            border-right: 1px solid #e2e8f0;
            min-height: 400px;
        }

        .calendar-day:last-child {
            border-right: none;
        }

        .day-header {
            padding: 15px 10px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            font-weight: 600;
        }

        .day-date {
            font-size: 18px;
            color: #2d3748;
        }

        .day-name {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
        }

        .day-content {
            padding: 10px;
        }

        .appointment-slot {
            background: #4facfe;
            color: white;
            padding: 8px;
            margin: 5px 0;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .appointment-slot:hover {
            background: #3d8bfe;
            transform: translateY(-1px);
        }

        .appointment-slot.pending {
            background: #ed8936;
        }

        .appointment-slot.completed {
            background: #48bb78;
        }

        .appointment-slot.cancelled {
            background: #f56565;
            opacity: 0.7;
        }

        .appointment-time {
            font-weight: bold;
            display: block;
        }

        .appointment-patient {
            font-size: 10px;
            opacity: 0.9;
        }

        .empty-day {
            color: #a0aec0;
            text-align: center;
            padding: 20px 10px;
            font-style: italic;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-primary { 
            background: #4facfe; 
            color: white; 
        }

        .btn-success { 
            background: #48bb78; 
            color: white; 
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
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

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .current-schedule {
            background: #e6fffa;
            border: 1px solid #81e6d9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .schedule-display {
            font-family: monospace;
            white-space: pre-line;
            color: #2d3748;
            margin: 10px 0;
        }

        .current-day .day-header {
            background: #4facfe !important;
            color: white !important;
        }

        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
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
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
            <li><a href="doctor_patients.php"><i class="fas fa-users"></i> My Patients</a></li>
            <li><a href="doctor_schedule.php" class="active"><i class="fas fa-clock"></i> Schedule</a></li>
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
            <h1>Schedule Management</h1>
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

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Weekly Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number" style="color: #4facfe;"><?php echo $week_stats['total']; ?></div>
                    <div class="stat-label">This Week's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #48bb78;"><?php echo $week_stats['confirmed']; ?></div>
                    <div class="stat-label">Confirmed This Week</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #ed8936;"><?php echo $week_stats['pending']; ?></div>
                    <div class="stat-label">Pending This Week</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #38b2ac;"><?php echo $next_month_count; ?></div>
                    <div class="stat-label">Next Month Bookings</div>
                </div>
            </div>

            <!-- Schedule Settings -->
            <div class="schedule-settings">
                <h3 style="margin-bottom: 20px; color: #2d3748;">
                    <i class="fas fa-cogs"></i> Working Hours & Availability
                </h3>
                
                <?php if ($doctor['schedule']): ?>
                <div class="current-schedule">
                    <strong>Current Schedule:</strong>
                    <div class="schedule-display"><?php echo htmlspecialchars($doctor['schedule']); ?></div>
                </div>
                <?php endif; ?>

                <form method="POST" class="schedule-form">
                    <div class="form-group">
                        <label for="schedule">Update Your Working Schedule</label>
                        <textarea name="schedule" id="schedule" placeholder="Example:
Monday: 9:00 AM - 5:00 PM
Tuesday: 9:00 AM - 5:00 PM
Wednesday: 9:00 AM - 1:00 PM
Thursday: 9:00 AM - 5:00 PM
Friday: 9:00 AM - 5:00 PM
Saturday: 9:00 AM - 1:00 PM
Sunday: Closed

Break: 1:00 PM - 2:00 PM (Mon-Fri)
Emergency: Available 24/7 for urgent cases"><?php echo htmlspecialchars($doctor['schedule'] ?? ''); ?></textarea>
                        <small style="color: #718096; font-size: 12px;">
                            Specify your working days, hours, breaks, and any special notes. This will be visible to patients when booking appointments.
                        </small>
                    </div>
                    
                    <div>
                        <button type="submit" name="update_schedule" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Schedule
                        </button>
                    </div>
                </form>
            </div>

            <!-- Weekly Calendar -->
            <div class="weekly-calendar">
                <div class="calendar-header">
                    <h3 style="margin: 0; color: #2d3748;">
                        <i class="fas fa-calendar-week"></i> This Week's Schedule
                    </h3>
                    <div style="color: #718096; font-size: 14px;">
                        <?php echo date('M j', strtotime($week_dates[0])) . ' - ' . date('M j, Y', strtotime($week_dates[6])); ?>
                    </div>
                </div>
                
                <div class="calendar-grid">
                    <?php 
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($week_dates as $index => $date): 
                        $is_today = date('Y-m-d') == $date;
                    ?>
                    <div class="calendar-day">
                        <div class="day-header <?php echo $is_today ? 'current-day' : ''; ?>">
                            <div class="day-date"><?php echo date('j', strtotime($date)); ?></div>
                            <div class="day-name"><?php echo $days[$index]; ?></div>
                        </div>
                        
                        <div class="day-content">
                            <?php if (!empty($weekly_appointments[$date])): ?>
                                <?php foreach ($weekly_appointments[$date] as $appointment): ?>
                                <div class="appointment-slot <?php echo strtolower($appointment['status']); ?>" 
                                     onclick="viewAppointment(<?php echo $appointment['id']; ?>)"
                                     title="<?php echo htmlspecialchars($appointment['patient_name']) . ' - ' . $appointment['status']; ?>">
                                    <span class="appointment-time">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </span>
                                    <span class="appointment-patient">
                                        <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-day">
                                    <i class="fas fa-calendar-times"></i><br>
                                    No appointments
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Schedule Management Tips -->
            <div class="schedule-settings" style="margin-top: 30px;">
                <h3 style="margin-bottom: 20px; color: #2d3748;">
                    <i class="fas fa-lightbulb"></i> Schedule Management Tips
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div style="padding: 20px; background: #f7fafc; border-radius: 8px; border-left: 4px solid #4facfe;">
                        <h4 style="margin: 0 0 10px 0; color: #2d3748;">
                            <i class="fas fa-clock"></i> Time Management
                        </h4>
                        <ul style="margin: 0; padding-left: 20px; color: #4a5568;">
                            <li>Block time for documentation between patients</li>
                            <li>Schedule breaks to avoid burnout</li>
                            <li>Leave buffer time for emergency cases</li>
                            <li>Consider travel time between locations</li>
                        </ul>
                    </div>
                    
                    <div style="padding: 20px; background: #f7fafc; border-radius: 8px; border-left: 4px solid #48bb78;">
                        <h4 style="margin: 0 0 10px 0; color: #2d3748;">
                            <i class="fas fa-users"></i> Patient Communication
                        </h4>
                        <ul style="margin: 0; padding-left: 20px; color: #4a5568;">
                            <li>Clearly communicate your availability</li>
                            <li>Set expectations for response times</li>
                            <li>Provide alternative contact methods</li>
                            <li>Update patients about schedule changes</li>
                        </ul>
                    </div>
                    
                    <div style="padding: 20px; background: #f7fafc; border-radius: 8px; border-left: 4px solid #ed8936;">
                        <h4 style="margin: 0 0 10px 0; color: #2d3748;">
                            <i class="fas fa-exclamation-triangle"></i> Emergency Preparedness
                        </h4>
                        <ul style="margin: 0; padding-left: 20px; color: #4a5568;">
                            <li>Have a system for urgent appointments</li>
                            <li>Designate emergency contact hours</li>
                            <li>Prepare backup coverage arrangements</li>
                            <li>Keep emergency supplies readily available</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div style="margin-top: 30px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h4 style="margin: 0 0 15px 0; color: #2d3748;">Appointment Status Legend</h4>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #4facfe; border-radius: 4px;"></div>
                        <span style="font-size: 14px; color: #4a5568;">Confirmed</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #ed8936; border-radius: 4px;"></div>
                        <span style="font-size: 14px; color: #4a5568;">Pending</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #48bb78; border-radius: 4px;"></div>
                        <span style="font-size: 14px; color: #4a5568;">Completed</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 20px; height: 20px; background: #f56565; border-radius: 4px; opacity: 0.7;"></div>
                        <span style="font-size: 14px; color: #4a5568;">Cancelled</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-resize textarea
            const textarea = document.getElementById('schedule');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });

            // Trigger resize on load if there's content
            if (textarea.value) {
                textarea.dispatchEvent(new Event('input'));
            }

            // Add interactive features to appointment slots
            const appointmentSlots = document.querySelectorAll('.appointment-slot');
            appointmentSlots.forEach(slot => {
                slot.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                    this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
                });
                
                slot.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            });
        });

        function viewAppointment(appointmentId) {
            // You can redirect to appointment details or show a modal
            // For now, let's just show an alert with appointment info
            if (confirm('View appointment details?')) {
                // Redirect to appointment details page
                window.location.href = `appointment_details.php?id=${appointmentId}`;
            }
        }

        // Add some visual feedback for form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>