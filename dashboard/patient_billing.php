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

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $bill_id = $_POST['bill_id'];
    $payment_method = $_POST['payment_method'];
    $payment_amount = $_POST['payment_amount'];
    
    // In a real application, you would process the payment through a payment gateway
    // For demo purposes, we'll just update the bill status
    $payment_sql = $conn->prepare("INSERT INTO payments (bill_id, patient_id, amount, payment_method, payment_date, status) 
        VALUES (?, ?, ?, ?, NOW(), 'Completed')
    ");
    $payment_sql->bind_param("iids", $bill_id, $patient['id'], $payment_amount, $payment_method);
    
    if ($payment_sql->execute()) {
        // Update bill status to paid
        $update_bill_sql = $conn->prepare("UPDATE bills SET status = 'Paid', paid_date = NOW() WHERE id = ?");
        $update_bill_sql->bind_param("i", $bill_id);
        $update_bill_sql->execute();
        
        $success_message = "Payment processed successfully!";
        
        // Log activity
        $activity_sql = $conn->prepare("INSERT INTO activities (user, action, created_at) VALUES (?, ?, NOW())");
        $activity_action = "Made payment for bill #" . $bill_id;
        $activity_sql->bind_param("is", $user_id, $activity_action);
        $activity_sql->execute();
    } else {
        $error_message = "Error processing payment. Please try again.";
    }
}

// Fetch bills with appointment details
$bills_sql = $conn->prepare("SELECT b.*, a.appointment_date, a.created_at, d.name as doctor_name, d.specialization,
           COALESCE(p.amount, 0) as paid_amount
    FROM bills b
    JOIN appointments a ON b.appointment_id = a.id
    JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN payments p ON b.id = p.bill_id AND p.status = 'Completed'
    WHERE b.patient_id = ?
    ORDER BY b.created_at DESC
");
$bills_sql->bind_param("i", $patient['id']);
$bills_sql->execute();
$bills_result = $bills_sql->get_result();

// Calculate billing statistics
$total_billed = 0;
$total_paid = 0;
$total_outstanding = 0;
$bills_data = array();

while ($bill = $bills_result->fetch_assoc()) {
    $bills_data[] = $bill;
    $total_billed += $bill['total_amount'];
    $total_paid += $bill['paid_amount'];
    if ($bill['status'] === 'Outstanding') {
        $total_outstanding += $bill['total_amount'];
    }
}

// For demo purposes, create some sample bills if none exist
if (empty($bills_data)) {
    $sample_bills = array(
        array(
            'id' => 1,
            'appointment_date' => '2024-01-15',
            'appointment_time' => '10:00:00',
            'doctor_name' => 'Dr. John Smith',
            'specialization' => 'General Medicine',
            'consultation_fee' => 150.00,
            'lab_tests' => 75.00,
            'medication' => 25.00,
            'total_amount' => 250.00,
            'status' => 'Outstanding',
            'created_at' => '2024-01-15 10:30:00',
            'due_date' => '2024-02-14',
            'paid_amount' => 0
        ),
        array(
            'id' => 2,
            'appointment_date' => '2024-01-10',
            'appointment_time' => '14:00:00',
            'doctor_name' => 'Dr. Sarah Wilson',
            'specialization' => 'Cardiology',
            'consultation_fee' => 200.00,
            'lab_tests' => 150.00,
            'medication' => 50.00,
            'total_amount' => 400.00,
            'status' => 'Paid',
            'created_at' => '2024-01-10 14:30:00',
            'due_date' => '2024-02-09',
            'paid_amount' => 400.00
        )
    );
    
    $bills_data = $sample_bills;
    $total_billed = 650.00;
    $total_paid = 400.00;
    $total_outstanding = 250.00;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Payments - HMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Include the same sidebar and base styles */
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

        .billing-container {
            padding: 30px;
        }

        .billing-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .billing-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid;
            text-align: center;
        }

        .billing-card.total { border-left-color: #4299e1; }
        .billing-card.paid { border-left-color: #48bb78; }
        .billing-card.outstanding { border-left-color: #ed8936; }

        .billing-card .card-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .billing-card.total .card-icon { color: #4299e1; }
        .billing-card.paid .card-icon { color: #48bb78; }
        .billing-card.outstanding .card-icon { color: #ed8936; }

        .billing-card .card-amount {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .billing-card .card-label {
            color: #718096;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bills-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .section-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .section-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bills-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bills-table th {
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

        .bills-table td {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .bills-table tbody tr:hover {
            background: #f7fafc;
        }

        .bill-id {
            font-weight: 600;
            color: #2d3748;
        }

        .bill-date {
            font-weight: 600;
            color: #2d3748;
        }

        .bill-time {
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

        .amount {
            font-weight: 600;
            color: #2d3748;
            font-size: 16px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-paid { background: #c6f6d5; color: #2f855a; }
        .status-outstanding { background: #feebc8; color: #c05621; }
        .status-overdue { background: #fed7d7; color: #c53030; }

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

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .payment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .payment-modal.active {
            display: flex;
        }

        .payment-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h3 {
            margin: 0;
            color: #2d3748;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #718096;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .payment-method {
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .payment-method:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .payment-method.selected {
            border-color: #667eea;
            background: #edf2f7;
        }

        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .payment-method i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .bill-breakdown {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .breakdown-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 16px;
            color: #2d3748;
        }

        .breakdown-label {
            color: #718096;
        }

        .breakdown-amount {
            color: #2d3748;
            font-weight: 500;
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

        .no-bills {
            padding: 60px 20px;
            text-align: center;
            color: #718096;
        }

        .no-bills i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .payment-history {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .billing-overview {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .payment-methods {
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
            <li><a href="patient_appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="patient_book_appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="patient_medical_history.php"><i class="fas fa-file-medical"></i> Medical History</a></li>
            <li><a href="patient_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
            <li><a href="patient_billing.php" class="active"><i class="fas fa-receipt"></i> Billing</a></li>
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
            <h1><i class="fas fa-receipt"></i> Billing & Payments</h1>
            <p>View your medical bills and make payments securely</p>
        </div>

        <div class="billing-container">
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

            <!-- Billing Overview -->
            <div class="billing-overview">
                <div class="billing-card total">
                    <div class="card-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="card-amount">$<?php echo number_format($total_billed, 2); ?></div>
                    <div class="card-label">Total Billed</div>
                </div>
                
                <div class="billing-card paid">
                    <div class="card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-amount">$<?php echo number_format($total_paid, 2); ?></div>
                    <div class="card-label">Total Paid</div>
                </div>
                
                <div class="billing-card outstanding">
                    <div class="card-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="card-amount">$<?php echo number_format($total_outstanding, 2); ?></div>
                    <div class="card-label">Outstanding Balance</div>
                </div>
            </div>

            <!-- Bills List -->
            <div class="bills-section">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> Your Bills</h3>
                </div>
                
                <?php if (empty($bills_data)): ?>
                    <div class="no-bills">
                        <i class="fas fa-receipt"></i>
                        <h3>No Bills Available</h3>
                        <p>You don't have any bills yet. Bills will appear here after your appointments.</p>
                        <a href="patient_book_appointment.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Book Your First Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <table class="bills-table">
                        <thead>
                            <tr>
                                <th>Bill ID</th>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Services</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills_data as $bill): ?>
                                <tr>
                                    <td>
                                        <div class="bill-id">#<?php echo $bill['id']; ?></div>
                                    </td>
                                    <td>
                                        <div class="bill-date">
                                            <?php echo date('M j, Y', strtotime($bill['appointment_date'])); ?>
                                        </div>
                                        <div class="bill-time">
                                            <?php echo date('g:i A', strtotime($bill['appointment_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="doctor-info"><?php echo htmlspecialchars($bill['doctor_name']); ?></div>
                                        <div class="doctor-specialty"><?php echo htmlspecialchars($bill['specialization']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; color: #718096;">
                                            Consultation: $<?php echo number_format($bill['consultation_fee'], 2); ?><br>
                                            <?php if (isset($bill['lab_tests']) && $bill['lab_tests'] > 0): ?>
                                                Lab Tests: $<?php echo number_format($bill['lab_tests'], 2); ?><br>
                                            <?php endif; ?>
                                            <?php if (isset($bill['medication']) && $bill['medication'] > 0): ?>
                                                Medication: $<?php echo number_format($bill['medication'], 2); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="amount">$<?php echo number_format($bill['total_amount'], 2); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($bill['status']); ?>">
                                            <?php echo $bill['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($bill['status'] === 'Outstanding'): ?>
                                                <button class="btn btn-success" onclick="openPaymentModal(<?php echo htmlspecialchars(json_encode($bill)); ?>)">
                                                    <i class="fas fa-credit-card"></i> Pay Now
                                                </button>
                                            <?php endif; ?>
                                            
                                            <a href="view_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-secondary" target="_blank">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <a href="download_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="payment-modal" id="paymentModal">
        <div class="payment-form">
            <div class="modal-header">
                <h3><i class="fas fa-credit-card"></i> Make Payment</h3>
                <button class="close-modal" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="paymentForm">
                <input type="hidden" name="bill_id" id="billId">
                <input type="hidden" name="payment_amount" id="paymentAmount">

                <!-- Bill Details -->
                <div class="bill-breakdown">
                    <h4 style="margin: 0 0 15px 0; color: #2d3748;">Bill Details</h4>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Bill ID:</span>
                        <span class="breakdown-amount" id="modalBillId">-</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Doctor:</span>
                        <span class="breakdown-amount" id="modalDoctor">-</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Date:</span>
                        <span class="breakdown-amount" id="modalDate">-</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Consultation Fee:</span>
                        <span class="breakdown-amount" id="modalConsultation">-</span>
                    </div>
                    <div class="breakdown-item" id="labTestsRow" style="display: none;">
                        <span class="breakdown-label">Lab Tests:</span>
                        <span class="breakdown-amount" id="modalLabTests">-</span>
                    </div>
                    <div class="breakdown-item" id="medicationRow" style="display: none;">
                        <span class="breakdown-label">Medication:</span>
                        <span class="breakdown-amount" id="modalMedication">-</span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Total Amount:</span>
                        <span class="breakdown-amount" id="modalTotal">-</span>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="form-group">
                    <label class="form-label">Select Payment Method</label>
                    <div class="payment-methods">
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="credit_card" required>
                            <i class="fas fa-credit-card"></i>
                            <span>Credit Card</span>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="debit_card" required>
                            <i class="fas fa-credit-card"></i>
                            <span>Debit Card</span>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="paypal" required>
                            <i class="fab fa-paypal"></i>
                            <span>PayPal</span>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="bank_transfer" required>
                            <i class="fas fa-university"></i>
                            <span>Bank Transfer</span>
                        </label>
                    </div>
                </div>

                <!-- Card Details (shown only for card payments) -->
                <div id="cardDetails" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="card_number">Card Number</label>
                        <input type="text" id="card_number" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19">
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label" for="expiry">Expiry Date</label>
                            <input type="text" id="expiry" class="form-control" placeholder="MM/YY" maxlength="5">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="cvv">CVV</label>
                            <input type="text" id="cvv" class="form-control" placeholder="123" maxlength="3">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="card_name">Cardholder Name</label>
                        <input type="text" id="card_name" class="form-control" placeholder="John Doe">
                    </div>
                </div>

                <button type="submit" name="make_payment" class="btn btn-success" style="width: 100%; padding: 16px; font-size: 16px;">
                    <i class="fas fa-lock"></i> Pay Securely
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentBill = null;

        function openPaymentModal(bill) {
            currentBill = bill;
            
            // Populate modal with bill data
            document.getElementById('billId').value = bill.id;
            document.getElementById('paymentAmount').value = bill.total_amount;
            document.getElementById('modalBillId').textContent = '#' + bill.id;
            document.getElementById('modalDoctor').textContent = bill.doctor_name;
            document.getElementById('modalDate').textContent = new Date(bill.appointment_date).toLocaleDateString();
            document.getElementById('modalConsultation').textContent = '$' + parseFloat(bill.consultation_fee).toFixed(2);
            width: "280px";
            height: "100vh";
            position: fixed;
            left: 0;
            top: 0; + parseFloat(bill.consultation_fee).toFixed(2);
            document.getElementById('modalTotal').textContent = 'eea 0%, #764ba2 100%)';
            width: "280px";
            height: "100vh";
            position: fixed;
            left: 0;
            top: 0; + parseFloat(bill.total_amount).toFixed(2);
            
            // Show/hide optional charges
            if (bill.lab_tests && bill.lab_tests > 0) {
                document.getElementById('labTestsRow').style.display = 'flex';
                document.getElementById('modalLabTests').textContent = 'eea 0%, #764ba2 100%)';
            width: "280px";
            height: "100vh";
            position: fixed;
            left: 0;
            top: 0; + parseFloat(bill.lab_tests).toFixed(2);
            }
            
            if (bill.medication && bill.medication > 0) {
                document.getElementById('medicationRow').style.display = 'flex';
                document.getElementById('modalMedication').textContent = 'eea 0%, #764ba2 100%)';
            width: "280px";
            height: "100vh";
            position: fixed;
            left: 0;
            top: 0; + parseFloat(bill.medication).toFixed(2);
            }
            
            document.getElementById('paymentModal').classList.add('active');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
            document.getElementById('paymentForm').reset();
            document.getElementById('cardDetails').style.display = 'none';
        }

        // Payment method selection
        document.querySelectorAll('input[name="payment_method"]').forEach(input => {
            input.addEventListener('change', function() {
                // Update visual selection
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.classList.remove('selected');
                });
                this.closest('.payment-method').classList.add('selected');
                
                // Show/hide card details
                if (this.value === 'credit_card' || this.value === 'debit_card') {
                    document.getElementById('cardDetails').style.display = 'block';
                } else {
                    document.getElementById('cardDetails').style.display = 'none';
                }
            });
        });

        // Card number formatting
        document.getElementById('card_number').addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '');
            let formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
            this.value = formattedValue;
        });

        // Expiry date formatting
        document.getElementById('expiry').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            this.value = value;
        });

        // CVV validation
        document.getElementById('cvv').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Close modal when clicking outside
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });

        // Animate elements on page load
        document.addEventListener('DOMContentLoaded', function() {
            const billingCards = document.querySelectorAll('.billing-card');
            
            billingCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>