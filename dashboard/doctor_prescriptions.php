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

// Handle adding new prescription
if (isset($_POST['add_prescription'])) {
    $patient_id = $_POST['patient_id'];
    $medication_name = trim($_POST['medication_name']);
    $dosage = trim($_POST['dosage']);
    $frequency = trim($_POST['frequency']);
    $duration = trim($_POST['duration']);
    $instructions = trim($_POST['instructions']);
    $refills = intval($_POST['refills']);
    $diagnosis = trim($_POST['diagnosis']);
    
    $errors = [];
    
    if (empty($patient_id)) {
        $errors[] = "Please select a patient.";
    }
    if (empty($medication_name)) {
        $errors[] = "Medication name is required.";
    }
    if (empty($dosage)) {
        $errors[] = "Dosage is required.";
    }
    if (empty($frequency)) {
        $errors[] = "Frequency is required.";
    }
    if (empty($duration)) {
        $errors[] = "Duration is required.";
    }
    
    if (empty($errors)) {
        $insert_sql = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions, refills, diagnosis, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())
        ");
        $insert_sql->bind_param("iisssssiss", $patient_id, $doctor['id'], $medication_name, $dosage, $frequency, $duration, $instructions, $refills, $diagnosis);
        
        if ($insert_sql->execute()) {
            $prescription_id = $conn->insert_id;
            $success_message = "Prescription added successfully! Prescription ID: #" . $prescription_id;
            
            // Log activity
            $activity_sql = $conn->prepare("INSERT INTO activities (user, role, action, created_at) VALUES (?, 'doctor', ?, NOW())");
            $activity_text = "Prescribed " . $medication_name . " to patient ID: " . $patient_id;
            $activity_sql->bind_param("is", $user_id, $activity_text);
            $activity_sql->execute();
        } else {
            $errors[] = "Error adding prescription. Please try again.";
        }
    }
}

// Handle prescription status update
if (isset($_POST['update_status'])) {
    $prescription_id = $_POST['prescription_id'];
    $new_status = $_POST['status'];
    
    $update_sql = $conn->prepare("UPDATE prescriptions SET status = ? WHERE id = ? AND doctor_id = ?");
    $update_sql->bind_param("sii", $new_status, $prescription_id, $doctor['id']);
    
    if ($update_sql->execute()) {
        $success_message = "Prescription status updated successfully!";
    } else {
        $error_message = "Error updating prescription status.";
    }
}

// Get search and filter parameters
$search_patient = isset($_GET['search_patient']) ? trim($_GET['search_patient']) : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_medication = isset($_GET['filter_medication']) ? trim($_GET['filter_medication']) : '';

// Fetch prescriptions with filters
$prescriptions_query = "SELECT pr.*, p.name as patient_name, p.phone as patient_phone, p.email as patient_email,
           p.age, p.gender
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    WHERE pr.doctor_id = ?
";

$params = [$doctor['id']];
$param_types = "i";

if ($search_patient) {
    $prescriptions_query .= " AND (p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_term = "%$search_patient%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= "sss";
}

if ($filter_status) {
    $prescriptions_query .= " AND pr.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_medication) {
    $prescriptions_query .= " AND pr.medication_name LIKE ?";
    $params[] = "%$filter_medication%";
    $param_types .= "s";
}

$prescriptions_query .= " ORDER BY pr.created_at DESC";

$prescriptions_sql = $conn->prepare($prescriptions_query);
$prescriptions_sql->bind_param($param_types, ...$params);
$prescriptions_sql->execute();
$prescriptions_result = $prescriptions_sql->get_result();

// Get patients for dropdown
$patients_sql = $conn->prepare("SELECT DISTINCT p.id, p.name, p.phone, p.age, p.gender
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
        COUNT(*) as total_prescriptions,
        COUNT(DISTINCT patient_id) as total_patients,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_prescriptions,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_prescriptions,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_prescriptions
    FROM prescriptions 
    WHERE doctor_id = ?
");
$stats_sql->bind_param("i", $doctor['id']);
$stats_sql->execute();
$stats = $stats_sql->get_result()->fetch_assoc();

// Get common medications
$common_meds_sql = $conn->prepare("SELECT medication_name, COUNT(*) as count
    FROM prescriptions
    WHERE doctor_id = ?
    GROUP BY medication_name
    ORDER BY count DESC
    LIMIT 5
");
$common_meds_sql->bind_param("i", $doctor['id']);
$common_meds_sql->execute();
$common_meds_result = $common_meds_sql->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - Doctor Dashboard</title>
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
            grid-template-columns: 2fr 1fr;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        .medication-suggestions {
            display: grid;
            gap: 8px;
            margin-top: 8px;
        }

        .med-suggestion {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .med-suggestion:hover {
            background: #4facfe;
            color: white;
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
        .btn-warning { background: #ed8936; color: white; }
        .btn-danger { background: #f56565; color: white; }
        .btn-info { background: #38b2ac; color: white; }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .prescriptions-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .search-filters {
            padding: 20px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
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

        .prescriptions-grid {
            display: grid;
            gap: 20px;
            padding: 25px;
        }

        .prescription-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .prescription-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #4facfe;
        }

        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .prescription-info {
            flex: 1;
        }

        .prescription-id {
            font-size: 14px;
            font-weight: bold;
            color: #4facfe;
            margin-bottom: 5px;
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

        .prescription-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { background: #c6f6d5; color: #2f855a; }
        .status-completed { background: #bee3f8; color: #2c5aa0; }
        .status-cancelled { background: #fed7d7; color: #c53030; }
        .status-expired { background: #e2e8f0; color: #4a5568; }

        .prescription-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .prescription-field {
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
            font-weight: 600;
        }

        .medication-name {
            font-size: 20px;
            font-weight: bold;
            color: #4facfe;
            margin-bottom: 10px;
        }

        .prescription-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .prescription-content {
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
            <li><a href="doctor_prescriptions.php" class="active"><i class="fas fa-prescription"></i> Prescriptions</a></li>
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
            <h1>Prescriptions Management</h1>
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
                    <div class="stat-number" style="color: #4facfe;"><?php echo $stats['total_prescriptions']; ?></div>
                    <div class="stat-label">Total Prescriptions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #48bb78;"><?php echo $stats['active_prescriptions']; ?></div>
                    <div class="stat-label">Active Prescriptions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #38b2ac;"><?php echo $stats['completed_prescriptions']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #ed8936;"><?php echo $stats['today_prescriptions']; ?></div>
                    <div class="stat-label">Prescribed Today</div>
                </div>
            </div>

            <!-- Action Section -->
            <div class="action-section">
                <!-- Add New Prescription Form -->
                <div class="form-card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Write New Prescription</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="prescriptionForm">
                            <div class="form-group">
                                <label for="patient_id">Select Patient</label>
                                <select name="patient_id" id="patient_id" required>
                                    <option value="">Choose a patient...</option>
                                    <?php while ($patient = $patients_result->fetch_assoc()): ?>
                                        <option value="Every 8 hours">Every 8 hours</option>
                                        <option value="Every 12 hours">Every 12 hours</option>
                                        <option value="As needed">As needed (PRN)</option>
                                        <option value="Before meals">Before meals</option>
                                        <option value="After meals">After meals</option>
                                        <option value="At bedtime">At bedtime</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="duration">Duration</label>
                                    <input type="text" name="duration" id="duration" placeholder="e.g., 7 days, 2 weeks" required>
                                </div>

                                <div class="form-group">
                                    <label for="refills">Number of Refills</label>
                                    <select name="refills" id="refills">
                                        <option value="0">No refills</option>
                                        <option value="1">1 refill</option>
                                        <option value="2">2 refills</option>
                                        <option value="3">3 refills</option>
                                        <option value="4">4 refills</option>
                                        <option value="5">5 refills</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label for="instructions">Special Instructions</label>
                                <textarea name="instructions" id="instructions" placeholder="Take with food, avoid alcohol, etc."></textarea>
                            </div>

                            <button type="submit" name="add_prescription" class="btn btn-success">
                                <i class="fas fa-prescription-bottle"></i> Write Prescription
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Reference & Common Medications -->
                <div class="form-card">
                    <div class="card-header">
                        <h3><i class="fas fa-pills"></i> Quick Reference</h3>
                    </div>
                    <div class="card-body">
                        <!-- Common Medications -->
                        <h4 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px;">
                            <i class="fas fa-star"></i> Most Prescribed
                        </h4>
                        <div style="display: grid; gap: 8px; margin-bottom: 25px;">
                            <?php while ($med = $common_meds_result->fetch_assoc()): ?>
                                <div class="med-suggestion" onclick="fillMedication('<?php echo htmlspecialchars($med['medication_name']); ?>')">
                                    <strong><?php echo htmlspecialchars($med['medication_name']); ?></strong>
                                    <span style="color: #718096; font-size: 11px;">(<?php echo $med['count']; ?> times)</span>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <!-- Quick Actions -->
                        <h4 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px;">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h4>
                        <div style="display: grid; gap: 10px;">
                            <a href="prescription_templates.php" class="btn btn-info btn-sm">
                                <i class="fas fa-file-alt"></i> Prescription Templates
                            </a>
                            <a href="drug_interactions.php" class="btn btn-warning btn-sm">
                                <i class="fas fa-exclamation-triangle"></i> Drug Interactions
                            </a>
                            <a href="dosage_calculator.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-calculator"></i> Dosage Calculator
                            </a>
                            <button onclick="printBlankPrescription()" class="btn btn-sm" style="background: #e2e8f0; color: #4a5568;">
                                <i class="fas fa-print"></i> Print Blank Form
                            </button>
                        </div>

                        <!-- Drug Safety Reminders -->
                        <div style="margin-top: 25px; padding: 15px; background: #fef5e7; border: 1px solid #f6e05e; border-radius: 8px;">
                            <h5 style="margin: 0 0 10px 0; color: #c05621;">
                                <i class="fas fa-shield-alt"></i> Safety Reminders
                            </h5>
                            <ul style="margin: 0; padding-left: 15px; color: #744210; font-size: 13px; line-height: 1.5;">
                                <li>Always check for drug allergies</li>
                                <li>Verify patient weight for pediatric dosing</li>
                                <li>Consider drug interactions</li>
                                <li>Include clear administration instructions</li>
                                <li>Specify duration to prevent overuse</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prescriptions List -->
            <div class="prescriptions-section">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fas fa-prescription"></i> All Prescriptions</h3>
                    <span style="color: #718096; font-size: 14px; font-weight: normal;">
                        <?php echo $prescriptions_result->num_rows; ?> prescriptions found
                    </span>
                </div>

                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="GET" action="" style="display: contents;">
                        <div class="filter-group">
                            <label for="search_patient">Search Patient</label>
                            <input type="text" name="search_patient" id="search_patient" placeholder="Patient name, phone..." value="<?php echo htmlspecialchars($search_patient); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_status">Status</label>
                            <select name="filter_status" id="filter_status">
                                <option value="">All Status</option>
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Completed" <?php echo $filter_status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $filter_status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="Expired" <?php echo $filter_status == 'Expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_medication">Medication</label>
                            <input type="text" name="filter_medication" id="filter_medication" placeholder="Medication name..." value="<?php echo htmlspecialchars($filter_medication); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <a href="prescriptions.php" class="btn" style="background: #e2e8f0; color: #4a5568;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Prescriptions Grid -->
                <div class="prescriptions-grid">
                    <?php if ($prescriptions_result->num_rows > 0): ?>
                        <?php while ($prescription = $prescriptions_result->fetch_assoc()): ?>
                        <div class="prescription-card">
                            <div class="prescription-header">
                                <div class="prescription-info">
                                    <div class="prescription-id">Rx #<?php echo $prescription['id']; ?></div>
                                    <h4 class="patient-name"><?php echo htmlspecialchars($prescription['patient_name']); ?></h4>
                                    <div class="patient-details">
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($prescription['patient_phone']); ?></span>
                                        <?php if ($prescription['age']): ?>
                                            <span style="margin-left: 15px;"><i class="fas fa-birthday-cake"></i> <?php echo $prescription['age']; ?> years</span>
                                        <?php endif; ?>
                                        <?php if ($prescription['gender']): ?>
                                            <span style="margin-left: 15px;"><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($prescription['gender']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="prescription-status status-<?php echo strtolower($prescription['status']); ?>">
                                    <?php echo $prescription['status']; ?>
                                </div>
                            </div>

                            <!-- Medication Name (Prominent) -->
                            <div class="medication-name">
                                <i class="fas fa-pills"></i> <?php echo htmlspecialchars($prescription['medication_name']); ?>
                            </div>

                            <div class="prescription-content">
                                <div class="prescription-field">
                                    <div class="field-label">Dosage</div>
                                    <div class="field-content"><?php echo htmlspecialchars($prescription['dosage']); ?></div>
                                </div>
                                
                                <div class="prescription-field">
                                    <div class="field-label">Frequency</div>
                                    <div class="field-content"><?php echo htmlspecialchars($prescription['frequency']); ?></div>
                                </div>
                                
                                <div class="prescription-field">
                                    <div class="field-label">Duration</div>
                                    <div class="field-content"><?php echo htmlspecialchars($prescription['duration']); ?></div>
                                </div>
                                
                                <div class="prescription-field">
                                    <div class="field-label">Refills</div>
                                    <div class="field-content"><?php echo $prescription['refills']; ?> remaining</div>
                                </div>
                                
                                <?php if ($prescription['diagnosis']): ?>
                                <div class="prescription-field">
                                    <div class="field-label">Diagnosis</div>
                                    <div class="field-content"><?php echo htmlspecialchars($prescription['diagnosis']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($prescription['instructions']): ?>
                                <div class="prescription-field" style="grid-column: 1 / -1;">
                                    <div class="field-label">Instructions</div>
                                    <div class="field-content"><?php echo htmlspecialchars($prescription['instructions']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                                <div style="font-size: 12px; color: #718096;">
                                    <i class="fas fa-calendar"></i> Prescribed: <?php echo date('M j, Y', strtotime($prescription['created_at'])); ?>
                                </div>
                                
                                <div class="prescription-actions">
                                    <button onclick="printPrescription(<?php echo $prescription['id']; ?>)" class="btn btn-primary btn-sm">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    
                                    <?php if ($prescription['status'] == 'Active'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                            <input type="hidden" name="status" value="Completed">
                                            <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                            <input type="hidden" name="status" value="Cancelled">
                                            <button type="submit" name="update_status" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this prescription?')">
                                                <i class="fas fa-ban"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px; color: #718096;">
                            <i class="fas fa-prescription-bottle" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                            <h3>No Prescriptions Found</h3>
                            <p>
                                <?php if ($search_patient || $filter_status || $filter_medication): ?>
                                    No prescriptions match your current filters.
                                <?php else: ?>
                                    Start by writing your first prescription above.
                                <?php endif; ?>
                            </p>
                            <a href="prescriptions.php" class="btn btn-primary">View All Prescriptions</a>
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
            document.getElementById('prescriptionForm').addEventListener('submit', function(e) {
                const patientId = document.getElementById('patient_id').value;
                const medicationName = document.getElementById('medication_name').value.trim();
                const dosage = document.getElementById('dosage').value.trim();
                const frequency = document.getElementById('frequency').value;
                const duration = document.getElementById('duration').value.trim();
                
                if (!patientId) {
                    alert('Please select a patient.');
                    e.preventDefault();
                    return false;
                }
                
                if (!medicationName) {
                    alert('Medication name is required.');
                    e.preventDefault();
                    return false;
                }
                
                if (!dosage) {
                    alert('Dosage is required.');
                    e.preventDefault();
                    return false;
                }
                
                if (!frequency) {
                    alert('Frequency is required.');
                    e.preventDefault();
                    return false;
                }
                
                if (!duration) {
                    alert('Duration is required.');
                    e.preventDefault();
                    return false;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Writing Prescription...';
                submitBtn.disabled = true;
            });

            // Auto-submit search form on input changes
            const searchInput = document.getElementById('search_patient');
            const statusFilter = document.getElementById('filter_status');
            const medicationFilter = document.getElementById('filter_medication');
            
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });

            statusFilter.addEventListener('change', function() {
                this.form.submit();
            });

            let medTimeout;
            medicationFilter.addEventListener('input', function() {
                clearTimeout(medTimeout);
                medTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });

            // Animate prescription cards on load
            const prescriptionCards = document.querySelectorAll('.prescription-card');
            prescriptionCards.forEach((card, index) => {
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

            // Medication name autocomplete suggestions
            const medNameInput = document.getElementById('medication_name');
            const suggestionsDiv = document.getElementById('medSuggestions');
            
            medNameInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                if (value.length >= 2) {
                    // You can implement API call here for medication suggestions
                    showMedicationSuggestions(value);
                } else {
                    suggestionsDiv.style.display = 'none';
                }
            });
        });

        // Fill medication from suggestion
        function fillMedication(medicationName) {
            document.getElementById('medication_name').value = medicationName;
            document.getElementById('medication_name').focus();
        }

        // Show medication suggestions (you can enhance this with API calls)
        function showMedicationSuggestions(query) {
            const commonMeds = [
                'Amoxicillin', 'Ibuprofen', 'Acetaminophen', 'Lisinopril', 'Metformin',
                'Aspirin', 'Omeprazole', 'Simvastatin', 'Levothyroxine', 'Amlodipine',
                'Hydrochlorothiazide', 'Prednisone', 'Azithromycin', 'Albuterol', 'Gabapentin'
            ];
            
            const suggestions = commonMeds.filter(med => 
                med.toLowerCase().includes(query)
            );
            
            const suggestionsDiv = document.getElementById('medSuggestions');
            
            if (suggestions.length > 0) {
                suggestionsDiv.innerHTML = suggestions.slice(0, 5).map(med => 
                    `<div class="med-suggestion" onclick="fillMedication('${med}')">${med}</div>`
                ).join('');
                suggestionsDiv.style.display = 'grid';
            } else {
                suggestionsDiv.style.display = 'none';
            }
        }

        // Print prescription
        function printPrescription(prescriptionId) {
            window.open(`print_prescription.php?id=${prescriptionId}`, '_blank', 'width=800,height=600');
        }

        // Print blank prescription form
        function printBlankPrescription() {
            window.open(`print_blank_prescription.php`, '_blank', 'width=800,height=600');
        }

        // Quick dosage suggestions based on medication
        function suggestDosage(medication) {
            const dosageGuide = {
                'amoxicillin': '500mg',
                'ibuprofen': '400mg',
                'acetaminophen': '500mg',
                'aspirin': '81mg',
                'prednisone': '20mg'
            };
            
            const suggested = dosageGuide[medication.toLowerCase()];
            if (suggested) {
                document.getElementById('dosage').placeholder = `Suggested: ${suggested}`;
            }
        }

        // Enhanced patient selection
        document.getElementById('patient_id').addEventListener('change', function() {
            if (this.value) {
                // You could add AJAX here to fetch patient's medication history, allergies, etc.
                console.log('ed patient:', this.value);
            }
        });
    </script>
</body>
</html>