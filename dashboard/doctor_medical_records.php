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

// Handle adding new medical record
if (isset($_POST['add_record'])) {
    $patient_id = $_POST['patient_id'];
    $diagnosis = trim($_POST['diagnosis']);
    $symptoms = trim($_POST['symptoms']);
    $treatment = trim($_POST['treatment']);
    $medications = trim($_POST['medications']);
    $notes = trim($_POST['notes']);
    $follow_up_date = $_POST['follow_up_date'] ?: null;
    
    $errors = [];
    
    if (empty($patient_id)) {
        $errors[] = "Please select a patient.";
    }
    if (empty($diagnosis)) {
        $errors[] = "Diagnosis is required.";
    }
    if (empty($treatment)) {
        $errors[] = "Treatment is required.";
    }
    
    if (empty($errors)) {
        $insert_sql = $conn->prepare("
            INSERT INTO medical_records (patient_id, doctor_id, diagnosis, symptoms, treatment, medications, notes, follow_up_date, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert_sql->bind_param("iissssss", $patient_id, $doctor['id'], $diagnosis, $symptoms, $treatment, $medications, $notes, $follow_up_date);
        
        if ($insert_sql->execute()) {
            $success_message = "Medical record added successfully!";
            
            // Log activity
            $activity_sql = $conn->prepare("INSERT INTO activities (user, role, action, created_at) VALUES (?, 'doctor', ?, NOW())");
            $activity_text = "Added medical record for patient ID: " . $patient_id;
            $activity_sql->bind_param("is", $user_id, $activity_text);
            $activity_sql->execute();
        } else {
            $errors[] = "Error adding medical record. Please try again.";
        }
    }
}

// Get search and filter parameters
$search_patient = isset($_GET['search_patient']) ? trim($_GET['search_patient']) : '';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

// Fetch medical records with filters
$records_query = "SELECT mr.*, p.name as patient_name, p.phone as patient_phone, p.email as patient_email,
           p.age, p.gender
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    WHERE mr.doctor_id = ?
";

$params = [$doctor['id']];
$param_types = "i";

if ($search_patient) {
    $records_query .= " AND (p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_term = "%$search_patient%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= "sss";
}

if ($filter_date) {
    $records_query .= " AND DATE(mr.created_at) = ?";
    $params[] = $filter_date;
    $param_types .= "s";
}

$records_query .= " ORDER BY mr.created_at DESC";

$records_sql = $conn->prepare($records_query);
$records_sql->bind_param($param_types, ...$params);
$records_sql->execute();
$records_result = $records_sql->get_result();

// Get patients for dropdown
$patients_sql = $conn->prepare("SELECT DISTINCT p.id, p.name, p.phone 
    FROM patients p
    JOIN appointments a ON p.id = a.patient_id
    WHERE a.doctor_id = ?
    ORDER BY p.name ASC
");
$patients_sql->bind_param("i", $doctor['id']);
$patients_sql->execute();
$patients_result = $patients_sql->get_result();

// Get statistics
$stats_sql = $conn->prepare("SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT patient_id) as total_patients,
        SUM(CASE WHEN follow_up_date IS NOT NULL AND follow_up_date >= CURDATE() THEN 1 ELSE 0 END) as pending_followups,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_records
    FROM medical_records 
    WHERE doctor_id = ?
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
    <title>Medical Records - Doctor Dashboard</title>
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

        .stats-overview {
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

        .action-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .card-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 18px;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
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

        .records-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .search-filters {
            padding: 20px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 15px;
            align-items: end;
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

        .records-grid {
            display: grid;
            gap: 20px;
            padding: 25px;
        }

        .record-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .record-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #4facfe;
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 18px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .patient-details {
            font-size: 14px;
            color: #718096;
        }

        .record-date {
            background: #4facfe;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .record-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .record-field {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4facfe;
        }

        .field-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .field-content {
            color: #2d3748;
            font-size: 14px;
            line-height: 1.5;
        }

        .follow-up-badge {
            background: #ed8936;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
            display: inline-block;
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

        @media (max-width: 968px) {
            .action-section {
                grid-template-columns: 1fr;
            }
            
            .record-content {
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
            <li><a href="doctor_medical_records.php" class="active"><i class="fas fa-file-medical-alt"></i> Medical Records</a></li>
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
            <h1>Medical Records</h1>
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

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number" style="color: #4facfe;"><?php echo $stats['total_records']; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #48bb78;"><?php echo $stats['total_patients']; ?></div>
                    <div class="stat-label">Patients with Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #ed8936;"><?php echo $stats['pending_followups']; ?></div>
                    <div class="stat-label">Pending Follow-ups</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #38b2ac;"><?php echo $stats['today_records']; ?></div>
                    <div class="stat-label">Records Today</div>
                </div>
            </div>

            <!-- Action Section -->
            <div class="action-section">
                <!-- Add New Record Form -->
                <div class="form-card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Add New Medical Record</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="recordForm">
                            <div class="form-group">
                                <label for="patient_id">Select Patient</label>
                                <select name="patient_id" id="patient_id" required>
                                    <option value="">Choose a patient...</option>
                                    <?php while ($patient = $patients_result->fetch_assoc()): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo htmlspecialchars($patient['name']) . ' - ' . htmlspecialchars($patient['phone']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="diagnosis">Diagnosis</label>
                                <input type="text" name="diagnosis" id="diagnosis" placeholder="Primary diagnosis" required>
                            </div>

                            <div class="form-group">
                                <label for="symptoms">Symptoms</label>
                                <textarea name="symptoms" id="symptoms" placeholder="Patient symptoms and observations"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="treatment">Treatment</label>
                                <textarea name="treatment" id="treatment" placeholder="Treatment provided" required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="medications">Medications</label>
                                <textarea name="medications" id="medications" placeholder="Prescribed medications and dosages"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="follow_up_date">Follow-up Date (Optional)</label>
                                <input type="date" name="follow_up_date" id="follow_up_date" min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="form-group">
                                <label for="notes">Additional Notes</label>
                                <textarea name="notes" id="notes" placeholder="Additional observations or notes"></textarea>
                            </div>

                            <button type="submit" name="add_record" class="btn btn-success">
                                <i class="fas fa-save"></i> Add Medical Record
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="form-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; gap: 15px;">
                            <a href="follow_ups.php" class="btn btn-warning">
                                <i class="fas fa-calendar-plus"></i> Manage Follow-ups
                            </a>
                            <a href="export_records.php" class="btn btn-info">
                                <i class="fas fa-download"></i> Export Records
                            </a>
                            <a href="record_templates.php" class="btn btn-primary">
                                <i class="fas fa-file-alt"></i> Record Templates
                            </a>
                        </div>

                        <!-- Quick Stats -->
                        <div style="margin-top: 25px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                            <h4 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px;">
                                <i class="fas fa-chart-pie"></i> Quick Stats
                            </h4>
                            <div style="font-size: 14px; color: #4a5568; line-height: 1.6;">
                                <div>• Records this month: <strong><?php 
                                    $month_records_sql = $conn->prepare("SELECT COUNT(*) as count FROM medical_records WHERE doctor_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                                    $month_records_sql->bind_param("i", $doctor['id']);
                                    $month_records_sql->execute();
                                    echo $month_records_sql->get_result()->fetch_assoc()['count'];
                                ?></strong></div>
                                <div>• Avg records per patient: <strong><?php 
                                    echo $stats['total_patients'] > 0 ? round($stats['total_records'] / $stats['total_patients'], 1) : 0;
                                ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medical Records List -->
            <div class="records-section">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fas fa-file-medical"></i> Medical Records</h3>
                    <span style="color: #718096; font-size: 14px; font-weight: normal;">
                        <?php echo $records_result->num_rows; ?> records found
                    </span>
                </div>

                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="GET" action="" style="display: contents;">
                        <div class="filter-group">
                            <label for="search_patient">Search Patient</label>
                            <input type="text" name="search_patient" id="search_patient" placeholder="Patient name, phone, email..." value="<?php echo htmlspecialchars($search_patient); ?>" style="width: 250px;">
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_date">Filter by Date</label>
                            <input type="date" name="filter_date" id="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <a href="medical_records.php" class="btn" style="background: #e2e8f0; color: #4a5568;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Records Grid -->
                <div class="records-grid">
                    <?php if ($records_result->num_rows > 0): ?>
                        <?php while ($record = $records_result->fetch_assoc()): ?>
                        <div class="record-card">
                            <div class="record-header">
                                <div class="patient-info">
                                    <h4 class="patient-name"><?php echo htmlspecialchars($record['patient_name']); ?></h4>
                                    <div class="patient-details">
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($record['patient_phone']); ?></span>
                                        <?php if ($record['age']): ?>
                                            <span style="margin-left: 15px;"><i class="fas fa-birthday-cake"></i> <?php echo $record['age']; ?> years</span>
                                        <?php endif; ?>
                                        <?php if ($record['gender']): ?>
                                            <span style="margin-left: 15px;"><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($record['gender']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="record-date">
                                    <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                </div>
                            </div>

                            <div class="record-content">
                                <div class="record-field">
                                    <div class="field-label">Diagnosis</div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['diagnosis']); ?></div>
                                </div>
                                
                                <?php if ($record['symptoms']): ?>
                                <div class="record-field">
                                    <div class="field-label">Symptoms</div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['symptoms']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="record-field">
                                    <div class="field-label">Treatment</div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['treatment']); ?></div>
                                </div>
                                
                                <?php if ($record['medications']): ?>
                                <div class="record-field">
                                    <div class="field-label">Medications</div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['medications']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($record['notes']): ?>
                                <div class="record-field">
                                    <div class="field-label">Notes</div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['notes']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($record['follow_up_date'] && $record['follow_up_date'] >= date('Y-m-d')): ?>
                                <div class="follow-up-badge">
                                    <i class="fas fa-calendar-check"></i> Follow-up: <?php echo date('M j, Y', strtotime($record['follow_up_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px; color: #718096;">
                            <i class="fas fa-file-medical" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                            <h3>No Medical Records Found</h3>
                            <p>
                                <?php if ($search_patient || $filter_date): ?>
                                    No records match your current filters.
                                <?php else: ?>
                                    Start by adding your first medical record above.
                                <?php endif; ?>
                            </p>
                            <a href="medical_records.php" class="btn btn-primary">View All Records</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });

            // Form validation
            document.getElementById('recordForm').addEventListener('submit', function(e) {
                const patientId = document.getElementById('patient_id').value;
                const diagnosis = document.getElementById('diagnosis').value.trim();
                const treatment = document.getElementById('treatment').value.trim();
                
                if (!patientId) {
                    alert('Please select a patient.');
                    e.preventDefault();
                    return false;
                }
                
                if (!diagnosis) {
                    alert('Diagnosis is required.');
                    e.preventDefault();
                    return false;
                }
                
                if (!treatment) {
                    alert('Treatment is required.');
                    e.preventDefault();
                    return false;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Record...';
                submitBtn.disabled = true;
            });

            // Auto-submit search form on input
            const searchInput = document.getElementById('search_patient');
            const dateFilter = document.getElementById('filter_date');
            
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });

            dateFilter.addEventListener('change', function() {
                this.form.submit();
            });

            // Animate record cards on load
            const recordCards = document.querySelectorAll('.record-card');
            recordCards.forEach((card, index) => {
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

            // Enhanced patient selection
            const patientSelect = document.getElementById('patient_id');
            patientSelect.addEventListener('change', function() {
                if (this.value) {
                    // You could add AJAX here to fetch patient details
                    console.log('Selected patient:', this.value);
                }
            });
        });

        // Print record function
        function printRecord(recordId) {
            window.open(`print_record.php?id=${recordId}`, '_blank', 'width=800,height=600');
        }

        // Quick copy diagnosis function
        function copyDiagnosis(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary success message
                const tooltip = document.createElement('div');
                tooltip.textContent = 'Copied!';
                tooltip.style.cssText = 'position:fixed;top:20px;right:20px;background:#48bb78;color:white;padding:10px;border-radius:5px;z-index:9999;';
                document.body.appendChild(tooltip);
                setTimeout(() => tooltip.remove(), 2000);
            });
        }
    </script>
</body>
</html>