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

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Overall Statistics
$stats_sql = $conn->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT a.patient_id) as total_patients,
        COUNT(DISTINCT CASE WHEN a.status = 'Completed' THEN a.id END) as completed_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'Cancelled' THEN a.id END) as cancelled_appointments,
        COUNT(DISTINCT CASE WHEN a.appointment_date BETWEEN ? AND ? THEN a.id END) as period_appointments,
        COUNT(DISTINCT CASE WHEN DATE(a.created_at) = CURDATE() THEN a.id END) as today_appointments
    FROM appointments a
    WHERE a.doctor_id = ?
");
$stats_sql->bind_param("ssi", $start_date, $end_date, $doctor['id']);
$stats_sql->execute();
$stats = $stats_sql->get_result()->fetch_assoc();

// Monthly Trends (Last 12 months)
$monthly_trends_sql = $conn->prepare("
    SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m') as month,
        DATE_FORMAT(appointment_date, '%M %Y') as month_name,
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled,
        COUNT(DISTINCT patient_id) as unique_patients
    FROM appointments
    WHERE doctor_id = ? 
    AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month DESC
");
$monthly_trends_sql->bind_param("i", $doctor['id']);
$monthly_trends_sql->execute();
$monthly_trends = $monthly_trends_sql->get_result()->fetch_all(MYSQLI_ASSOC);

// Daily Performance for selected period
$daily_performance_sql = $conn->prepare("
    SELECT 
        appointment_date,
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled,
        COUNT(DISTINCT patient_id) as unique_patients
    FROM appointments
    WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date
    ORDER BY appointment_date ASC
");
$daily_performance_sql->bind_param("iss", $doctor['id'], $start_date, $end_date);
$daily_performance_sql->execute();
$daily_performance = $daily_performance_sql->get_result()->fetch_all(MYSQLI_ASSOC);

// Patient Demographics
$demographics_sql = $conn->prepare("
    SELECT 
        p.gender,
        CASE 
            WHEN p.age BETWEEN 0 AND 17 THEN 'Child (0-17)'
            WHEN p.age BETWEEN 18 AND 35 THEN 'Young Adult (18-35)'
            WHEN p.age BETWEEN 36 AND 55 THEN 'Adult (36-55)'
            WHEN p.age > 55 THEN 'Senior (55+)'
            ELSE 'Unknown'
        END as age_group,
        COUNT(*) as count
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND a.status = 'Completed'
    GROUP BY p.gender, age_group
    ORDER BY count DESC
");
$demographics_sql->bind_param("i", $doctor['id']);
$demographics_sql->execute();
$demographics = $demographics_sql->get_result()->fetch_all(MYSQLI_ASSOC);

// Top Conditions/Diagnoses (from medical records)
$diagnoses_sql = $conn->prepare("
    SELECT 
        diagnosis,
        COUNT(*) as frequency,
        COUNT(DISTINCT patient_id) as patients_affected
    FROM medical_records
    WHERE doctor_id = ? AND created_at BETWEEN ? AND ?
    GROUP BY diagnosis
    ORDER BY frequency DESC
    LIMIT 10
");
$diagnoses_sql->bind_param("iss", $doctor['id'], $start_date, $end_date);
$diagnoses_sql->execute();
$diagnoses = $diagnoses_sql->get_result()->fetch_all(MYSQLI_ASSOC);

// Prescription Analytics
$prescriptions_sql = $conn->prepare("
    SELECT 
        COUNT(*) as total_prescriptions,
        COUNT(DISTINCT patient_id) as patients_with_prescriptions,
        COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_prescriptions,
        medication_name,
        COUNT(medication_name) as medication_count
    FROM prescriptions
    WHERE doctor_id = ? AND created_at BETWEEN ? AND ?
    GROUP BY medication_name
    ORDER BY medication_count DESC
    LIMIT 10
");
$prescriptions_sql->bind_param("iss", $doctor['id'], $start_date, $end_date);
$prescriptions_sql->execute();
$prescriptions_data = $prescriptions_sql->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate success rate and other KPIs
$success_rate = $stats['total_appointments'] > 0 ? round(($stats['completed_appointments'] / $stats['total_appointments']) * 100, 1) : 0;
$cancellation_rate = $stats['total_appointments'] > 0 ? round(($stats['cancelled_appointments'] / $stats['total_appointments']) * 100, 1) : 0;

// Average patients per day
$days_in_period = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
$avg_patients_per_day = $days_in_period > 0 ? round($stats['period_appointments'] / $days_in_period, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .report-controls {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .controls-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .control-group label {
            font-size: 14px;
            color: #4a5568;
            font-weight: 600;
        }

        .control-group input,
        .control-group select {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 4px solid;
        }

        .kpi-card.primary { border-left-color: #4facfe; }
        .kpi-card.success { border-left-color: #48bb78; }
        .kpi-card.warning { border-left-color: #ed8936; }
        .kpi-card.info { border-left-color: #38b2ac; }

        .kpi-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .kpi-card.primary .kpi-number { color: #4facfe; }
        .kpi-card.success .kpi-number { color: #48bb78; }
        .kpi-card.warning .kpi-number { color: #ed8936; }
        .kpi-card.info .kpi-number { color: #38b2ac; }

        .kpi-label {
            font-size: 14px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .kpi-trend {
            font-size: 12px;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .trend-up { background: #c6f6d5; color: #2f855a; }
        .trend-down { background: #fed7d7; color: #c53030; }
        .trend-stable { background: #e2e8f0; color: #4a5568; }

        .reports-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .chart-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .chart-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 18px;
        }

        .chart-body {
            padding: 25px;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tbody tr:hover {
            background: #f7fafc;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary { background: #4facfe; color: white; }
        .btn-success { background: #48bb78; color: white; }
        .btn-info { background: #38b2ac; color: white; }
        .btn-warning { background: #ed8936; color: white; }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .insights-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .insight-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .insight-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
        }

        .insight-body {
            padding: 20px;
        }

        .insight-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .insight-item:last-child {
            border-bottom: none;
        }

        .insight-label {
            color: #4a5568;
            font-size: 14px;
        }

        .insight-value {
            font-weight: bold;
            color: #2d3748;
        }

        @media (max-width: 968px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .controls-row {
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
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
            <li><a href="doctor_patients.php"><i class="fas fa-users"></i> My Patients</a></li>
            <li><a href="doctor_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
            <li><a href="doctor_medical_records.php"><i class="fas fa-file-medical-alt"></i> Medical Records</a></li>
            <li><a href="doctor_prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
            <li><a href="doctor_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
            <li><a href="doctor_reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
            <h1>Reports & Analytics</h1>
            <div class="topbar-actions">
                <a href="export_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success">
                    <i class="fas fa-download"></i> Export Report
                </a>
                <a href="doctor.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="content-section">
            <!-- Report Controls -->
            <div class="report-controls">
                <h3 style="margin: 0 0 20px 0; color: #2d3748;">
                    <i class="fas fa-filter"></i> Report Filters
                </h3>
                <form method="GET" action="">
                    <div class="controls-row">
                        <div class="control-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div class="control-group">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="control-group">
                            <label for="report_type">Report Type</label>
                            <select name="report_type" id="report_type">
                                <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                                <option value="detailed" <?php echo $report_type == 'detailed' ? 'selected' : ''; ?>>Detailed Analysis</option>
                                <option value="trends" <?php echo $report_type == 'trends' ? 'selected' : ''; ?>>Trend Analysis</option>
                            </select>
                        </div>
                        
                        <div class="control-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-line"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-cards">
                <div class="kpi-card primary">
                    <div class="kpi-number"><?php echo $stats['period_appointments']; ?></div>
                    <div class="kpi-label">Appointments (Period)</div>
                    <div class="kpi-trend trend-stable">
                        <i class="fas fa-calendar"></i> <?php echo date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)); ?>
                    </div>
                </div>
                
                <div class="kpi-card success">
                    <div class="kpi-number"><?php echo $success_rate; ?>%</div>
                    <div class="kpi-label">Success Rate</div>
                    <div class="kpi-trend <?php echo $success_rate >= 80 ? 'trend-up' : ($success_rate >= 60 ? 'trend-stable' : 'trend-down'); ?>">
                        <i class="fas fa-chart-line"></i> 
                        <?php echo $success_rate >= 80 ? 'Excellent' : ($success_rate >= 60 ? 'Good' : 'Needs Improvement'); ?>
                    </div>
                </div>
                
                <div class="kpi-card info">
                    <div class="kpi-number"><?php echo $avg_patients_per_day; ?></div>
                    <div class="kpi-label">Avg Patients/Day</div>
                    <div class="kpi-trend trend-stable">
                        <i class="fas fa-users"></i> Based on <?php echo round($days_in_period); ?> days
                    </div>
                </div>
                
                <div class="kpi-card warning">
                    <div class="kpi-number"><?php echo $cancellation_rate; ?>%</div>
                    <div class="kpi-label">Cancellation Rate</div>
                    <div class="kpi-trend <?php echo $cancellation_rate <= 10 ? 'trend-up' : ($cancellation_rate <= 20 ? 'trend-stable' : 'trend-down'); ?>">
                        <i class="fas fa-ban"></i> 
                        <?php echo $cancellation_rate <= 10 ? 'Low' : ($cancellation_rate <= 20 ? 'Moderate' : 'High'); ?>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics -->
            <div class="reports-grid">
                <!-- Monthly Trends Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-line-chart"></i> Monthly Trends (Last 12 Months)</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                        <p style="color: #718096; font-size: 14px; text-align: center;">
                            Track your monthly appointment patterns and patient engagement
                        </p>
                    </div>
                </div>

                <!-- Patient Demographics -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-users"></i> Patient Demographics</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container" style="height: 250px;">
                            <canvas id="demographicsChart"></canvas>
                        </div>
                        <div style="margin-top: 20px;">
                            <h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">Gender Distribution</h4>
                            <?php 
                            $gender_stats = [];
                            foreach ($demographics as $demo) {
                                if (!isset($gender_stats[$demo['gender']])) {
                                    $gender_stats[$demo['gender']] = 0;
                                }
                                $gender_stats[$demo['gender']] += $demo['count'];
                            }
                            foreach ($gender_stats as $gender => $count):
                            ?>
                            <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #e2e8f0;">
                                <span style="color: #4a5568;"><?php echo ucfirst($gender); ?></span>
                                <strong><?php echo $count; ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Insights Section -->
            <div class="insights-section">
                <!-- Top Diagnoses -->
                <div class="insight-card">
                    <div class="insight-header">
                        <h3 style="margin: 0;"><i class="fas fa-diagnoses"></i> Top Conditions Treated</h3>
                    </div>
                    <div class="insight-body">
                        <?php if (count($diagnoses) > 0): ?>
                            <?php foreach (array_slice($diagnoses, 0, 8) as $diagnosis): ?>
                            <div class="insight-item">
                                <span class="insight-label"><?php echo htmlspecialchars($diagnosis['diagnosis']); ?></span>
                                <span class="insight-value">
                                    <?php echo $diagnosis['frequency']; ?> cases 
                                    <small style="color: #718096;">(<?php echo $diagnosis['patients_affected']; ?> patients)</small>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #718096; text-align: center; padding: 20px;">No medical records data available for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Prescribed Medications -->
                <div class="insight-card">
                    <div class="insight-header">
                        <h3 style="margin: 0;"><i class="fas fa-pills"></i> Most Prescribed Medications</h3>
                    </div>
                    <div class="insight-body">
                        <?php if (count($prescriptions_data) > 0): ?>
                            <?php foreach (array_slice($prescriptions_data, 0, 8) as $prescription): ?>
                            <div class="insight-item">
                                <span class="insight-label"><?php echo htmlspecialchars($prescription['medication_name']); ?></span>
                                <span class="insight-value"><?php echo $prescription['medication_count']; ?> times</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #718096; text-align: center; padding: 20px;">No prescription data available for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance Summary -->
                <div class="insight-card">
                    <div class="insight-header">
                        <h3 style="margin: 0;"><i class="fas fa-trophy"></i> Performance Summary</h3>
                    </div>
                    <div class="insight-body">
                        <div class="insight-item">
                            <span class="insight-label">Total Career Appointments</span>
                            <span class="insight-value"><?php echo $stats['total_appointments']; ?></span>
                        </div>
                        <div class="insight-item">
                            <span class="insight-label">Total Patients Treated</span>
                            <span class="insight-value"><?php echo $stats['total_patients']; ?></span>
                        </div>
                        <div class="insight-item">
                            <span class="insight-label">Completion Rate</span>
                            <span class="insight-value"><?php echo $success_rate; ?>%</span>
                        </div>
                        <div class="insight-item">
                            <span class="insight-label">Years of Service</span>
                            <span class="insight-value">
                                <?php echo date('Y') - date('Y', strtotime($doctor['created_at'])); ?> years
                            </span>
                        </div>
                        <div class="insight-item">
                            <span class="insight-label">Today's Appointments</span>
                            <span class="insight-value"><?php echo $stats['today_appointments']; ?></span>
                        </div>
                        <div class="insight-item">
                            <span class="insight-label">Specialization</span>
                            <span class="insight-value"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Performance Table -->
            <?php if (count($daily_performance) > 0): ?>
            <div class="chart-card" style="margin-top: 30px;">
                <div class="chart-header">
                    <h3><i class="fas fa-calendar-alt"></i> Daily Performance Breakdown</h3>
                </div>
                <div class="chart-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Appointments</th>
                                <th>Completed</th>
                                <th>Cancelled</th>
                                <th>Success Rate</th>
                                <th>Unique Patients</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_performance as $day): ?>
                            <tr>
                                <td><?php echo date('M j, Y (D)', strtotime($day['appointment_date'])); ?></td>
                                <td><?php echo $day['total_appointments']; ?></td>
                                <td style="color: #48bb78; font-weight: 600;"><?php echo $day['completed']; ?></td>
                                <td style="color: #f56565; font-weight: 600;"><?php echo $day['cancelled']; ?></td>
                                <td>
                                    <?php 
                                    $daily_success = $day['total_appointments'] > 0 ? round(($day['completed'] / $day['total_appointments']) * 100, 1) : 0;
                                    $success_class = $daily_success >= 80 ? '#48bb78' : ($daily_success >= 60 ? '#ed8936' : '#f56565');
                                    ?>
                                    <span style="color: <?php echo $success_class; ?>; font-weight: 600;"><?php echo $daily_success; ?>%</span>
                                </td>
                                <td><?php echo $day['unique_patients']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Export and Print Options -->
            <div style="margin-top: 30px; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h3 style="margin: 0 0 20px 0; color: #2d3748;">
                    <i class="fas fa-download"></i> Export Options
                </h3>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="export_pdf.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </a>
                    <a href="export_excel.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </a>
                    <button onclick="window.print()" class="btn btn-info">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <a href="email_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-warning">
                        <i class="fas fa-envelope"></i> Email Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Trends Chart
            const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
            const monthlyData = <?php echo json_encode(array_reverse($monthly_trends)); ?>;
            
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(item => item.month_name),
                    datasets: [{
                        label: 'Total Appointments',
                        data: monthlyData.map(item => item.total_appointments),
                        borderColor: '#4facfe',
                        backgroundColor: 'rgba(79, 172, 254, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Completed',
                        data: monthlyData.map(item => item.completed),
                        borderColor: '#48bb78',
                        backgroundColor: 'rgba(72, 187, 120, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }, {
                        label: 'Unique Patients',
                        data: monthlyData.map(item => item.unique_patients),
                        borderColor: '#ed8936',
                        backgroundColor: 'rgba(237, 137, 54, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Count'
                            },
                            beginAtZero: true
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });

            // Demographics Pie Chart
            const demoCtx = document.getElementById('demographicsChart').getContext('2d');
            const demographicsData = <?php echo json_encode($demographics); ?>;
            
            // Process demographics data for chart
            const ageGroups = {};
            demographicsData.forEach(item => {
                if (!ageGroups[item.age_group]) {
                    ageGroups[item.age_group] = 0;
                }
                ageGroups[item.age_group] += parseInt(item.count);
            });

            new Chart(demoCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(ageGroups),
                    datasets: [{
                        data: Object.values(ageGroups),
                        backgroundColor: [
                            '#4facfe',
                            '#48bb78',
                            '#ed8936',
                            '#f56565',
                            '#9f7aea'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Auto-submit form when date changes
            document.getElementById('start_date').addEventListener('change', function() {
                this.form.submit();
            });

            document.getElementById('end_date').addEventListener('change', function() {
                this.form.submit();
            });

            document.getElementById('report_type').addEventListener('change', function() {
                this.form.submit();
            });

            // Animate KPI cards
            const kpiCards = document.querySelectorAll('.kpi-card');
            kpiCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });

            // Print specific sections
            window.printSection = function(sectionId) {
                const section = document.getElementById(sectionId);
                if (section) {
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <html>
                            <head>
                                <title>Report Section</title>
                                <style>
                                    body { font-family: Arial, sans-serif; margin: 20px; }
                                    table { width: 100%; border-collapse: collapse; }
                                    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                                    th { background-color: #f5f5f5; }
                                </style>
                            </head>
                            <body>${section.innerHTML}</body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                }
            };
        });

        // Generate insights based on data
        function generateInsights() {
            const successRate = <?php echo $success_rate; ?>;
            const cancellationRate = <?php echo $cancellation_rate; ?>;
            const avgPatientsPerDay = <?php echo $avg_patients_per_day; ?>;
            
            let insights = [];
            
            if (successRate >= 90) {
                insights.push("Excellent success rate! Your patients are highly satisfied.");
            } else if (successRate < 70) {
                insights.push("Consider reviewing appointment processes to improve success rate.");
            }
            
            if (cancellationRate > 20) {
                insights.push("High cancellation rate detected. Consider implementing reminder systems.");
            }
            
            if (avgPatientsPerDay > 15) {
                insights.push("High patient volume. Ensure adequate time allocation per patient.");
            }
            
            return insights;
        }

        // Add tooltips to KPI cards
        const kpiCards = document.querySelectorAll('.kpi-card');
        kpiCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>