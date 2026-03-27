<?php
// Add error reporting to see what's wrong
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

try {
    include("../includes/db.php");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Check if logged in and role = patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch patient details
    $patient_sql = $conn->prepare("SELECT * FROM patients WHERE id = ?");
    if (!$patient_sql) {
        die("Prepare failed: " . $conn->error);
    }
    
    $patient_sql->bind_param("i", $user_id);
    $patient_sql->execute();
    $patient_result = $patient_sql->get_result();
    $patient = $patient_result->fetch_assoc();
    
    if (!$patient) {
        die("Patient not found with ID: " . $user_id);
    }

    // Fetch medical history (simplified query first)
    $history_sql = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization
        FROM appointments a 
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ? AND a.status = 'Completed'
        ORDER BY a.appointment_date DESC
    ");
    
    if (!$history_sql) {
        die("History query prepare failed: " . $conn->error);
    }
    
    $history_sql->bind_param("i", $patient['id']);
    $history_sql->execute();
    $history_result = $history_sql->get_result();

    // Process appointments
    $appointments = array();
    while ($row = $history_result->fetch_assoc()) {
        $appointment_id = $row['id'];
        
        if (!isset($appointments[$appointment_id])) {
            $appointments[$appointment_id] = array(
                'appointment' => $row,
                'prescriptions' => array(),
                'diagnoses' => array()
            );
        }
        
        // Fetch prescriptions separately
        try {
            $pres_sql = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ?");
            if ($pres_sql) {
                $pres_sql->bind_param("i", $appointment_id);
                $pres_sql->execute();
                $pres_result = $pres_sql->get_result();
                while ($pres = $pres_result->fetch_assoc()) {
                    $appointments[$appointment_id]['prescriptions'][] = array(
                        'medication' => $pres['medication'],
                        'dosage' => $pres['dosage'],
                        'instructions' => $pres['instructions'],
                        'date' => $pres['created_at']
                    );
                }
                $pres_sql->close();
            }
        } catch (Exception $e) {
            // Continue if prescriptions table doesn't exist or has issues
        }
        
        // Fetch diagnoses separately
        try {
            $diag_sql = $conn->prepare("SELECT * FROM diagnoses WHERE appointment_id = ?");
            if ($diag_sql) {
                $diag_sql->bind_param("i", $appointment_id);
                $diag_sql->execute();
                $diag_result = $diag_sql->get_result();
                while ($diag = $diag_result->fetch_assoc()) {
                    $appointments[$appointment_id]['diagnoses'][] = array(
                        'diagnosis' => $diag['diagnosis'],
                        'notes' => $diag['notes'],
                        'date' => $diag['created_at']
                    );
                }
                $diag_sql->close();
            }
        } catch (Exception $e) {
            // Continue if diagnoses table doesn't exist or has issues
        }
    }

    // Get health statistics
    $total_appointments = count($appointments);
    $total_doctors = count(array_unique(array_column(array_column($appointments, 'appointment'), 'doctor_id')));

    // Simplified vital signs for now
    $recent_vitals = array(
        array('type' => 'Blood Pressure', 'value' => '120/80 mmHg', 'date' => date('Y-m-d'), 'status' => 'Normal'),
        array('type' => 'Heart Rate', 'value' => '72 bpm', 'date' => date('Y-m-d'), 'status' => 'Normal'),
        array('type' => 'Temperature', 'value' => '98.6°F', 'date' => date('Y-m-d'), 'status' => 'Normal'),
        array('type' => 'Weight', 'value' => '70 kg', 'date' => date('Y-m-d'), 'status' => 'Normal')
    );

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        /* Sidebar Styles */
        .patient-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .patient-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .patient-avatar {
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

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin: 5px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }

        .sidebar-menu i {
            margin-right: 15px;
            width: 20px;
        }

        /* Main Content */
        .patient-main-content {
            margin-left: 280px;
            min-height: 100vh;
        }

        .content-header {
            background: white;
            padding: 30px;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        .medical-container {
            padding: 30px;
        }

        /* Health Overview Cards */
        .health-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .health-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .health-card:hover {
            transform: translateY(-2px);
        }

        .health-card.appointments { border-left-color: #4299e1; }
        .health-card.doctors { border-left-color: #48bb78; }
        .health-card.prescriptions { border-left-color: #ed8936; }
        .health-card.years { border-left-color: #9f7aea; }

        .card-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .health-card.appointments .card-icon { color: #4299e1; }
        .health-card.doctors .card-icon { color: #48bb78; }
        .health-card.prescriptions .card-icon { color: #ed8936; }
        .health-card.years .card-icon { color: #9f7aea; }

        .card-number {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .card-label {
            color: #718096;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Medical Sections */
        .medical-sections {
            display: grid;
            gap: 30px;
        }

        .medical-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .section-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 18px;
        }

        .section-content {
            padding: 0;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 25px;
        }

        .vital-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #48bb78;
        }

        .vital-type {
            color: #718096;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 8px 0;
        }

        .vital-value {
            font-size: 24px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .vital-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: #c6f6d5;
            color: #2f855a;
        }

        .vital-date {
            color: #718096;
            font-size: 12px;
            margin-top: 8px;
        }

        /* Appointment History */
        .appointment-history-item {
            padding: 25px;
            border-bottom: 1px solid #e2e8f0;
        }

        .appointment-history-item:last-child {
            border-bottom: none;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .appointment-basic-info {
            flex: 1;
        }

        .appointment-date {
            font-size: 18px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .appointment-doctor {
            color: #667eea;
            font-weight: 600;
            margin: 0 0 5px 0;
        }

        .appointment-specialty {
            color: #718096;
            font-size: 14px;
        }

        .appointment-actions {
            display: flex;
            gap: 10px;
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

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .medical-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 15px;
        }

        .detail-section {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .detail-section h4 {
            margin: 0 0 15px 0;
            color: #2d3748;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .diagnosis-item, .prescription-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #e2e8f0;
        }

        .diagnosis-item:last-child, .prescription-item:last-child {
            margin-bottom: 0;
        }

        .diagnosis-name, .medication-name {
            font-weight: 600;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .diagnosis-notes, .medication-details {
            color: #718096;
            font-size: 14px;
            line-height: 1.5;
        }

        .medication-dosage {
            color: #667eea;
            font-weight: 600;
            font-size: 14px;
        }

        .no-data {
            padding: 60px 20px;
            text-align: center;
            color: #718096;
        }

        .no-data i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -38px;
            top: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .export-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            text-align: center;
        }

        .export-section h3 {
            margin: 0 0 15px 0;
            color: #2d3748;
        }

        .export-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-export {
            background: #48bb78;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-export:hover {
            background: #38a169;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .patient-sidebar {
                transform: translateX(-100%);
            }
            
            .patient-main-content {
                margin-left: 0;
            }
            
            .medical-details {
                grid-template-columns: 1fr;
            }
            
            .appointment-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .vitals-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Debug Info -->
    <div style="background: #d4edda; padding: 10px; margin: 10px; border: 1px solid #c3e6cb; border-radius: 5px;">
        <strong>Debug Info:</strong><br>
        Patient ID: <?php echo $user_id; ?><br>
        Patient Name: <?php echo isset($patient['name']) ? htmlspecialchars($patient['name']) : 'Not found'; ?><br>
        Total Appointments: <?php echo $total_appointments; ?><br>
        PHP Version: <?php echo PHP_VERSION; ?><br>
        <?php if (isset($conn)) echo "Database Connected: Yes"; else echo "Database Connected: No"; ?>
    </div>

    <!-- Sidebar -->
    <div class="patient-sidebar">
        <div class="sidebar-logo">
            🏥 HMS Patient
        </div>
        
        <div class="patient-info">
            <div class="patient-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h4><?php echo htmlspecialchars($patient['name'] ?? 'Unknown'); ?></h4>
            <p style="opacity: 0.8; font-size: 14px;">Patient ID: #<?php echo $patient['id'] ?? 'N/A'; ?></p>
        </div>

        <ul class="sidebar-menu">
            <li><a href="patient.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="patient_appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="patient_book_appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="patient_medical_history.php" class="active"><i class="fas fa-file-medical"></i> Medical History</a></li>
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
            <h1><i class="fas fa-file-medical"></i> Medical History</h1>
            <p>Complete record of your medical appointments and treatments</p>
        </div>

        <div class="medical-container">
            <!-- Health Overview -->
            <div class="health-overview">
                <div class="health-card appointments">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="card-number"><?php echo $total_appointments; ?></div>
                    <div class="card-label">Total Visits</div>
                </div>
                
                <div class="health-card doctors">
                    <div class="card-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="card-number"><?php echo $total_doctors; ?></div>
                    <div class="card-label">Doctors Consulted</div>
                </div>
                
                <div class="health-card prescriptions">
                    <div class="card-icon">
                        <i class="fas fa-prescription-bottle"></i>
                    </div>
                    <div class="card-number"><?php echo array_sum(array_map(function($a) { return count($a['prescriptions']); }, $appointments)); ?></div>
                    <div class="card-label">Prescriptions</div>
                </div>
                
                <div class="health-card years">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-number"><?php echo max(1, (int)((time() - strtotime($patient['created_at'] ?? '2020-01-01')) / (365.25 * 24 * 3600))); ?></div>
                    <div class="card-label">Years as Patient</div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="export-section">
                <h3><i class="fas fa-download"></i> Export Medical Records</h3>
                <p style="color: #718096; margin: 0 0 20px 0;">Download your complete medical history for your records</p>
                <div class="export-buttons">
                    <a href="#" class="btn-export" onclick="alert('Export functionality coming soon!')">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </a>
                    <a href="#" class="btn-export" onclick="alert('Export functionality coming soon!')">
                        <i class="fas fa-file-excel"></i> Export as Excel
                    </a>
                </div>
            </div>

            <div class="medical-sections">
                <!-- Recent Vital Signs -->
                <div class="medical-section">
                    <div class="section-header">
                        <i class="fas fa-heartbeat"></i>
                        <h3>Recent Vital Signs</h3>
                    </div>
                    <div class="vitals-grid">
                        <?php foreach ($recent_vitals as $vital): ?>
                            <div class="vital-card">
                                <div class="vital-type"><?php echo $vital['type']; ?></div>
                                <div class="vital-value"><?php echo $vital['value']; ?></div>
                                <div class="vital-status"><?php echo $vital['status']; ?></div>
                                <div class="vital-date"><?php echo date('M j, Y', strtotime($vital['date'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Appointment History -->
                <div class="medical-section">
                    <div class="section-header">
                        <i class="fas fa-history"></i>
                        <h3>Appointment History</h3>
                    </div>
                    
                    <div class="section-content">
                        <?php if (empty($appointments)): ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Medical History Available</h3>
                                <p>You haven't completed any appointments yet. Your medical history will appear here after your first completed consultation.</p>
                                <a href="patient_book_appointment.php" class="btn btn-primary" style="margin-top: 15px;">
                                    <i class="fas fa-plus"></i> Book Your First Appointment
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($appointments as $appointment_id => $data): ?>
                                    <div class="timeline-item">
                                        <div class="appointment-history-item">
                                            <div class="appointment-header">
                                                <div class="appointment-basic-info">
                                                    <h3 class="appointment-date">
                                                        <?php echo date('F j, Y', strtotime($data['appointment']['appointment_date'])); ?>
                                                    </h3>
                                                    <p class="appointment-doctor">
                                                        Dr. <?php echo htmlspecialchars($data['appointment']['doctor_name']); ?>
                                                    </p>
                                                    <p class="appointment-specialty">
                                                        <?php echo htmlspecialchars($data['appointment']['specialization']); ?>
                                                    </p>
                                                </div>
                                                
                                                <div class="appointment-actions">
                                                    <button class="btn btn-primary" onclick="toggleDetails('details-<?php echo $appointment_id; ?>')">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div id="details-<?php echo $appointment_id; ?>" class="medical-details" style="display: none;">
                                                <!-- Diagnoses -->
                                                <div class="detail-section">
                                                    <h4><i class="fas fa-stethoscope"></i> Diagnoses</h4>
                                                    <?php if (!empty($data['diagnoses'])): ?>
                                                        <?php foreach (array_unique($data['diagnoses'], SORT_REGULAR) as $diagnosis): ?>
                                                            <div class="diagnosis-item">
                                                                <h5 class="diagnosis-name"><?php echo htmlspecialchars($diagnosis['diagnosis']); ?></h5>
                                                                <?php if ($diagnosis['notes']): ?>
                                                                    <p class="diagnosis-notes"><?php echo htmlspecialchars($diagnosis['notes']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <p style="color: #718096; font-style: italic;">No diagnoses recorded for this visit.</p>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Prescriptions -->
                                                <div class="detail-section">
                                                    <h4><i class="fas fa-prescription-bottle"></i> Prescriptions</h4>
                                                    <?php if (!empty($data['prescriptions'])): ?>
                                                        <?php foreach (array_unique($data['prescriptions'], SORT_REGULAR) as $prescription): ?>
                                                            <div class="prescription-item">
                                                                <h5 class="medication-name"><?php echo htmlspecialchars($prescription['medication']); ?></h5>
                                                                <p class="medication-dosage"><?php echo htmlspecialchars($prescription['dosage']); ?></p>
                                                                <?php if ($prescription['instructions']): ?>
                                                                    <p class="medication-details"><?php echo htmlspecialchars($prescription['instructions']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <p style="color: #718096; font-style: italic;">No prescriptions issued for this visit.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDetails(detailsId) {
            const details = document.getElementById(detailsId);
            const button = event.target.closest('button');
            
            if (details.style.display === 'none' || !details.style.display) {
                details.style.display = 'grid';
                button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Details';
                
                // Animate the expansion
                details.style.opacity = '0';
                details.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    details.style.transition = 'all 0.3s ease';
                    details.style.opacity = '1';
                    details.style.transform = 'translateY(0)';
                }, 10);
            } else {
                details.style.display = 'none';
                button.innerHTML = '<i class="fas fa-eye"></i> View Details';
            }
        }

        // Animate elements on page load
        document.addEventListener('DOMContentLoaded', function() {
            const timelineItems = document.querySelectorAll('.timeline-item');
            const healthCards = document.querySelectorAll('.health-card');
            
            // Animate health cards
            healthCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animate timeline items
            timelineItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-30px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, (index * 150) + 500);
            });
        });
    </script>
</body>
</html>