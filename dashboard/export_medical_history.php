<?php
session_start();
include("../includes/db.php");

// Check if logged in and role = patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$format = $_GET['format'] ?? 'pdf';

// Fetch patient details
$patient_sql = $conn->prepare("SELECT * FROM patients WHERE id = ?");
$patient_sql->bind_param("i", $user_id);
$patient_sql->execute();
$patient = $patient_sql->get_result()->fetch_assoc();

// Fetch all medical data
$history_sql = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization, d.phone AS doctor_phone,
           p.medication, p.dosage, p.instructions as prescription_instructions, p.created_at as prescription_date,
           diag.diagnosis, diag.notes as diagnosis_notes, diag.created_at as diagnosis_date
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN prescriptions p ON a.id = p.appointment_id
    LEFT JOIN diagnoses diag ON a.id = diag.appointment_id
    WHERE a.patient_id = ? AND a.status = 'Completed'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$history_sql->bind_param("i", $patient['id']);
$history_sql->execute();
$history_result = $history_sql->get_result();

// Group results by appointment
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
    
    if ($row['medication']) {
        $appointments[$appointment_id]['prescriptions'][] = array(
            'medication' => $row['medication'],
            'dosage' => $row['dosage'],
            'instructions' => $row['prescription_instructions'],
            'date' => $row['prescription_date']
        );
    }
    
    if ($row['diagnosis']) {
        $appointments[$appointment_id]['diagnoses'][] = array(
            'diagnosis' => $row['diagnosis'],
            'notes' => $row['diagnosis_notes'],
            'date' => $row['diagnosis_date']
        );
    }
}

if ($format === 'excel') {
    // Excel export
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="medical_history_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='6' style='background-color: #f0f0f0;'><h2>Medical History - " . htmlspecialchars($patient['name']) . "</h2></th></tr>";
    echo "<tr><th>Date</th><th>Doctor</th><th>Specialization</th><th>Diagnosis</th><th>Medication</th><th>Dosage</th></tr>";
    
    foreach ($appointments as $data) {
        $diagnoses = implode('; ', array_column($data['diagnoses'], 'diagnosis'));
        $medications = implode('; ', array_column($data['prescriptions'], 'medication'));
        $dosages = implode('; ', array_column($data['prescriptions'], 'dosage'));
        
        echo "<tr>";
        echo "<td>" . date('M j, Y', strtotime($data['appointment']['appointment_date'])) . "</td>";
        echo "<td>Dr. " . htmlspecialchars($data['appointment']['doctor_name']) . "</td>";
        echo "<td>" . htmlspecialchars($data['appointment']['specialization']) . "</td>";
        echo "<td>" . htmlspecialchars($diagnoses) . "</td>";
        echo "<td>" . htmlspecialchars($medications) . "</td>";
        echo "<td>" . htmlspecialchars($dosages) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    // PDF export (simple HTML to PDF)
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Medical History - <?php echo htmlspecialchars($patient['name']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .appointment { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; }
            .appointment-header { background: #f5f5f5; padding: 10px; margin: -15px -15px 15px; }
            .section { margin: 10px 0; }
            .section h4 { color: #333; margin: 10px 0 5px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f5f5f5; }
        </style>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </head>
    <body>
        <div class="header">
            <h1>Medical History Report</h1>
            <h3><?php echo htmlspecialchars($patient['name']); ?></h3>
            <p>Patient ID: #<?php echo $patient['id']; ?> | Generated: <?php echo date('F j, Y g:i A'); ?></p>
        </div>
        
        <?php if (empty($appointments)): ?>
            <p>No medical history available.</p>
        <?php else: ?>
            <?php foreach ($appointments as $data): ?>
                <div class="appointment">
                    <div class="appointment-header">
                        <h3><?php echo date('F j, Y', strtotime($data['appointment']['appointment_date'])); ?></h3>
                        <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($data['appointment']['doctor_name']); ?></p>
                        <p><strong>Specialization:</strong> <?php echo htmlspecialchars($data['appointment']['specialization']); ?></p>
                    </div>
                    
                    <?php if (!empty($data['diagnoses'])): ?>
                        <div class="section">
                            <h4>Diagnoses:</h4>
                            <?php foreach (array_unique($data['diagnoses'], SORT_REGULAR) as $diagnosis): ?>
                                <p><strong><?php echo htmlspecialchars($diagnosis['diagnosis']); ?></strong></p>
                                <?php if ($diagnosis['notes']): ?>
                                    <p><?php echo htmlspecialchars($diagnosis['notes']); ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($data['prescriptions'])): ?>
                        <div class="section">
                            <h4>Prescriptions:</h4>
                            <table>
                                <tr><th>Medication</th><th>Dosage</th><th>Instructions</th></tr>
                                <?php foreach (array_unique($data['prescriptions'], SORT_REGULAR) as $prescription): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prescription['medication']); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['instructions']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
}
?>