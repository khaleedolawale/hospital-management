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

// Handle appointment cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancel_sql = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND patient_id = ?");
    $cancel_sql->bind_param("ii", $appointment_id, $patient['id']);
    
    if ($cancel_sql->execute()) {
        $success_message = "Appointment cancelled successfully!";
        
        // Log activity
        $activity_sql = $conn->prepare("INSERT INTO activities (user, action, created_at) VALUES (?, ?, NOW())");
        $activity_action = "Cancelled appointment #" . $appointment_id;
        $activity_sql->bind_param("is", $user_id, $activity_action);
        $activity_sql->execute();
    } else {
        $error_message = "Error cancelling appointment. Please try again.";
    }
}

// Fetch all appointments with filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_clause = "WHERE a.patient_id = ?";
$params = array($patient['id']);
$param_types = "i";

if ($filter !== 'all') {
    $where_clause .= " AND a.status = ?";
    $params[] = ucfirst($filter);
    $param_types .= "s";
}

if (!empty($search)) {
    $where_clause .= " AND (d.name LIKE ? OR d.specialization LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

$appointments_sql = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization, d.phone AS doctor_phone, d.email AS doctor_email
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id
    $where_clause
    ORDER BY a.appointment_date DESC
");

$appointments_sql->bind_param($param_types, ...$params);
$appointments_sql->execute();
$appointments_result = $appointments_sql->get_result();

// Count appointments by status
$stats_sql = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE patient_id = ?
");
$stats_sql->bind_param("i", $patient['id']);
$stats_sql->execute();
$stats = $stats_sql->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - HMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Include the same sidebar and base styles from patient.php */
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

        .patient-sidebar .sidebar-menu a:hover,
        .patient-sidebar .sidebar-menu a.active {
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

        .content-header {
            background: white;
            padding: 30px;
            border-bottom: 1px solid #e2e8f0;
        }

        .content-header h1 {
            margin: 0 0 10px 0;
            color: #2d3748;
            font-size: 28px;
        }

        .content-header p {
            color: #718096;
            margin: 0;
        }

        .appointments-container {
            padding: 30px;
        }

        .appointments-filters {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-tabs {
            display: flex;
            gap: 5px;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #4a5568;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .filter-tab.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }

        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .filter-tab.active:hover {
            color: white;
        }

        .search-box {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            width: 300px;
        }

        .search-box:focus {
            outline: none;
            border-color: #667eea;
        }

        .appointments-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid;
            text-align: center;
        }

        .stat-card.total { border-left-color: #4299e1; }
        .stat-card.confirmed { border-left-color: #48bb78; }
        .stat-card.pending { border-left-color: #ed8936; }
        .stat-card.completed { border-left-color: #9f7aea; }
        .stat-card.cancelled { border-left-color: #e53e3e; }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .stat-label {
            color: #718096;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .appointments-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .appointments-table th {
            background: #f7fafc;
            padding: 20px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .appointments-table td {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .appointments-table tbody tr:hover {
            background: #f7fafc;
        }

        .appointment-date {
            font-weight: 600;
            color: #2d3748;
        }

        .appointment-time {
            color: #718096;
            font-size: 14px;
        }

        .doctor-info {
            font-weight: 600;
            color: #2d3748;
        }

        .doctor-specialty {
            color: #718096;
            font-size: 14px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-confirmed { background: #c6f6d5; color: #2f855a; }
        .status-pending { background: #feebc8; color: #c05621; }
        .status-completed { background: #bee3f8; color: #2c5aa0; }
        .status-cancelled { background: #fed7d7; color: #c53030; }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .no-appointments {
            padding: 60px 20px;
            text-align: center;
            color: #718096;
        }

        .no-appointments i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        .alert-danger {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .appointments-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <li><a href="patient_appointments.php" class="active"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
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
        <!-- Content Header -->
        <div class="content-header">
            <h1><i class="fas fa-calendar-check"></i> My Appointments</h1>
            <p>View and manage all your medical appointments</p>
        </div>

        <div class="appointments-container">
            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="appointments-stats">
                <div class="stat-card total">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card confirmed">
                    <div class="stat-number"><?php echo $stats['confirmed']; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card cancelled">
                    <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="appointments-filters">
                <form method="GET" class="filter-row">
                    <div class="filter-group">
                        <label><strong>Filter by Status:</strong></label>
                        <div class="filter-tabs">
                            <a href="?filter=all&search=<?php echo urlencode($search); ?>" 
                               class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                            <a href="?filter=pending&search=<?php echo urlencode($search); ?>" 
                               class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                            <a href="?filter=confirmed&search=<?php echo urlencode($search); ?>" 
                               class="filter-tab <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
                            <a href="?filter=completed&search=<?php echo urlencode($search); ?>" 
                               class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                            <a href="?filter=cancelled&search=<?php echo urlencode($search); ?>" 
                               class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label><strong>Search:</strong></label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by doctor name or specialty..." class="search-box">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Appointments List -->
            <div class="appointments-list">
                <?php if ($appointments_result->num_rows > 0): ?>
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($appointment = $appointments_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="appointment-date">
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div class="appointment-time">
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="doctor-info">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                        <div class="doctor-specialty"><?php echo htmlspecialchars($appointment['specialization']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['purpose'] ?? 'General Consultation'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                            <?php echo $appointment['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($appointment['status'] === 'Pending' || $appointment['status'] === 'Confirmed'): ?>
                                                <?php if (strtotime($appointment['appointment_date']) > time()): ?>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <button type="submit" name="cancel_appointment" class="btn btn-danger">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <a href="appointment_details.php?id=<?php echo $appointment['id']; ?>" class="btn btn-secondary">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-appointments">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No appointments found</h3>
                        <p>
                            <?php if (!empty($search) || $filter !== 'all'): ?>
                                No appointments match your current filters.
                            <?php else: ?>
                                You haven't booked any appointments yet.
                            <?php endif; ?>
                        </p>
                        <a href="patient_book_appointment.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Book Your First Appointment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit search form on enter
            const searchBox = document.querySelector('.search-box');
            if (searchBox) {
                searchBox.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.closest('form').submit();
                    }
                });
            }

            // Animate table rows
            const tableRows = document.querySelectorAll('.appointments-table tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>