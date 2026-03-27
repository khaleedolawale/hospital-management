<?php
session_start();
include("../includes/db.php");

// Check if logged in and role = patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$appointment_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch appointment details
$appointment_sql = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization, d.phone AS doctor_phone, p.name AS patient_name
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id
    JOIN patients p ON a.patient_id = p.id
    WHERE a.id = ? AND a.patient_id = ? AND a.status = 'Completed'
");
$appointment_sql->bind_param("ii", $appointment_id, $user_id);
$appointment_sql->execute();
$appointment = $appointment_sql->get_result()->fetch_assoc();

if (!$appointment) {
    die("Appointment not found or access denied.");
}

// Fetch prescriptions
$prescription_sql = $conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ?");
$prescription_sql->bind_param("i", $appointment_id);
$prescription_sql->execute();
$prescriptions = $prescription_sql->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch diagnoses
$diagnosis_sql = $conn->prepare("SELECT * FROM diagnoses WHERE appointment_id = ?");
$diagnosis_sql->bind_param("i", $appointment_id);
$diagnosis_sql->execute();
$diagnoses = $diagnosis_sql->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appointment Report - <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .header { text-align: center; border-bottom: 3px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .info-section { background: #f9f9f9; padding: 15px; border-left: 4px solid #007bff; }
        .info-section h4 { margin: 0 0 10px 0; color: #333; }
        .section { margin: 25px 0; }
        .section h3 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        .diagnosis-item, .prescription-item { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>Medical Appointment Report</h1>
        <h3><?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
        <p>Appointment Date: <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
    </div>
    
    <div class="info-grid">
        <div class="info-section">
            <h4>Doctor Information</h4>
            <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
            <p><strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['doctor_phone']); ?></p>
        </div>
        
        <div class="info-section">
            <h4>Appointment Details</h4>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($appointment['status']); ?></p>
            <p><strong>Type:</strong> <?php echo htmlspecialchars($appointment['appointment_type'] ?? 'Consultation'); ?></p>
            <?php if ($appointment['notes']): ?>
                <p><strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($diagnoses)): ?>
        <div class="section">
            <h3>Diagnoses</h3>
            <?php foreach ($diagnoses as $diagnosis): ?>
                <div class="diagnosis-item">
                    <h4><?php echo htmlspecialchars($diagnosis['diagnosis']); ?></h4>
                    <?php if ($diagnosis['notes']): ?>
                        <p><?php echo nl2br(htmlspecialchars($diagnosis['notes'])); ?></p>
                    <?php endif; ?>
                    <small>Date: <?php echo date('M j, Y g:i A', strtotime($diagnosis['created_at'])); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($prescriptions)): ?>
        <div class="section">
            <h3>Prescriptions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Instructions</th>
                        <th>Date Prescribed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prescription['medication']); ?></td>
                            <td><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                            <td><?php echo htmlspecialchars($prescription['instructions']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($prescription['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div class="section" style="margin-top: 50px; text-align: center; color: #666; font-size: 12px;">
        <p>This is an official medical record generated on <?php echo date('F j, Y g:i A'); ?></p>
        <p>Report ID: #<?php echo $appointment_id; ?></p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Report</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Close Window</button>
    </div>
</body>
</html>