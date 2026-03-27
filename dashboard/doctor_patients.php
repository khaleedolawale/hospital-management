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

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch patients who have appointments with this doctor
$patients_query = "SELECT DISTINCT p.*, 
           COUNT(a.id) as total_appointments,
           MAX(a.appointment_date) as last_visit,
           SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) as completed_appointments,
           SUM(CASE WHEN a.status = 'Pending' THEN 1 ELSE 0 END) as pending_appointments
    FROM patients p
    JOIN appointments a ON p.id = a.patient_id
    WHERE a.doctor_id = ?
";

$params = [$doctor['id']];
$param_types = "i";

if ($search) {
    $patients_query .= " AND (p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= "sss";
}

$patients_query .= " GROUP BY p.id ORDER BY last_visit DESC";

$patients_sql = $conn->prepare($patients_query);
$patients_sql->bind_param($param_types, ...$params);
$patients_sql->execute();
$patients_result = $patients_sql->get_result();

// Get total patient statistics
$stats_sql = $conn->prepare("SELECT 
        COUNT(DISTINCT a.patient_id) as total_patients,
        AVG(patient_age.age) as avg_age
    FROM appointments a
    LEFT JOIN (
        SELECT id, 
               CASE 
                   WHEN age IS NOT NULL THEN age
                   WHEN age IS NOT NULL THEN YEAR(CURDATE()) - YEAR(age)
                   ELSE NULL
               END as age
        FROM patients
    ) patient_age ON a.patient_id = patient_age.id
    WHERE a.doctor_id = ? AND a.status = 'Completed'
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
    <title>My Patients - Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same sidebar styles as previous pages */
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

        .search-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .search-row {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .search-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .search-group label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .search-group input {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #4facfe;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .patient-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .patient-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .patient-header {
            padding: 20px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }

        .patient-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .patient-contact {
            opacity: 0.9;
            font-size: 14px;
        }

        .patient-body {
            padding: 20px;
        }

        .patient-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .patient-stat {
            text-align: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .patient-stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #2d3748;
        }

        .patient-stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .patient-info {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-weight: 600;
            color: #2d3748;
        }

        .patient-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            text-align: center;
            flex: 1;
        }

        .btn-primary { 
            background: #4facfe; 
            color: white; 
        }

        .btn-success { 
            background: #48bb78; 
            color: white; 
        }

        .btn-info { 
            background: #38b2ac; 
            color: white; 
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .patients-grid {
                grid-template-columns: 1fr;
            }
            
            .search-row {
                flex-direction: column;
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
            <li><a href="doctor_patients.php" class="active"><i class="fas fa-users"></i> My Patients</a></li>
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
            <h1>My Patients</h1>
            <div class="topbar-actions">
                <a href="doctor.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="content-section">
            <!-- Search Section -->
            <div class="search-section">
                <form method="GET" action="">
                    <div class="search-row">
                        <div class="search-group">
                            <label for="search">Search Patients</label>
                            <input type="text" name="search" id="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        <div>
                            <a href="patients.php" class="btn" style="background: #e2e8f0; color: #4a5568;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_patients'] ?? 0; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['avg_age'] ? round($stats['avg_age']) : 'N/A'; ?></div>
                    <div class="stat-label">Average Age</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $patients_result->num_rows; ?></div>
                    <div class="stat-label">Search Results</div>
                </div>
            </div>

            <!-- Patients Grid -->
            <?php if ($patients_result->num_rows > 0): ?>
                <div class="patients-grid">
                    <?php while ($patient = $patients_result->fetch_assoc()): ?>
                    <div class="patient-card">
                        <div class="patient-header">
                            <div class="patient-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></h3>
                            <div class="patient-contact">
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></div>
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></div>
                            </div>
                        </div>
                        
                        <div class="patient-body">
                            <!-- Patient Statistics -->
                            <div class="patient-stats">
                                <div class="patient-stat">
                                    <div class="patient-stat-number"><?php echo $patient['total_appointments']; ?></div>
                                    <div class="patient-stat-label">Total Visits</div>
                                </div>
                                <div class="patient-stat">
                                    <div class="patient-stat-number"><?php echo $patient['completed_appointments']; ?></div>
                                    <div class="patient-stat-label">Completed</div>
                                </div>
                            </div>

                            <!-- Patient Info -->
                            <div class="patient-info">
                                <?php if ($patient['age']): ?>
                                <div class="info-row">
                                    <span class="info-label">Age</span>
                                    <span class="info-value"><?php echo $patient['age']; ?> years</span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($patient['gender']): ?>
                                <div class="info-row">
                                    <span class="info-label">Gender</span>
                                    <span class="info-value"><?php echo htmlspecialchars($patient['gender']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($patient['address']): ?>
                                <div class="info-row">
                                    <span class="info-label">Address</span>
                                    <span class="info-value"><?php echo htmlspecialchars(substr($patient['address'], 0, 30)) . (strlen($patient['address']) > 30 ? '...' : ''); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-row">
                                    <span class="info-label">Last Visit</span>
                                    <span class="info-value">
                                        <?php echo $patient['last_visit'] ? date('M j, Y', strtotime($patient['last_visit'])) : 'Never'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($patient['pending_appointments'] > 0): ?>
                                <div class="info-row">
                                    <span class="info-label">Pending</span>
                                    <span class="info-value" style="color: #ed8936;"><?php echo $patient['pending_appointments']; ?> appointments</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="patient-actions">
                                <a href="patient_history.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-history"></i> History
                                </a>
                                <a href="add_prescription.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-prescription"></i> Prescribe
                                </a>
                                <a href="patient_details.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Patients Found</h3>
                    <p>
                        <?php if ($search): ?>
                            No patients found matching "<?php echo htmlspecialchars($search); ?>".
                        <?php else: ?>
                            You haven't seen any patients yet.
                        <?php endif; ?>
                    </p>
                    <a href="patients.php" class="btn btn-primary">View All Patients</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add search functionality with enter key
            const searchInput = document.getElementById('search');
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });

            // Animate patient cards on load
            const cards = document.querySelectorAll('.patient-card');
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
        });
    </script>
</body>
</html>