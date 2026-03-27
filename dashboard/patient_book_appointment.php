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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $purpose = $_POST['purpose'];
    
    // Check if the appointment slot is available
    $check_sql = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'");
    $check_sql->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
    $check_sql->execute();
    $existing = $check_sql->get_result();
    
    if ($existing->num_rows > 0) {
        $error_message = "This time slot is already booked. Please choose another time.";
    } else {
        // Insert the appointment
        $insert_sql = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, purpose, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
        $insert_sql->bind_param("iisss", $patient['id'], $doctor_id, $appointment_date, $appointment_time, $purpose);
        
        if ($insert_sql->execute()) {
            $appointment_id = $conn->insert_id;
            $success_message = "Appointment booked successfully! Your appointment ID is #" . $appointment_id;
            
            // Log activity
            $activity_sql = $conn->prepare("INSERT INTO activities (user, action, created_at) VALUES (?, ?, NOW())");
            $activity_action = "Booked new appointment #" . $appointment_id;
            $activity_sql->bind_param("is", $user_id, $activity_action);
            $activity_sql->execute();
            
            // Clear form data
            $_POST = array();
        } else {
            $error_message = "Error booking appointment. Please try again.";
        }
    }
}

// Fetch all doctors
$doctors_sql = $conn->prepare("SELECT * FROM doctors ORDER BY name ASC");
$doctors_sql->execute();
$doctors_result = $doctors_sql->get_result();

// Fetch specializations for filter
$specialties_sql = $conn->prepare("SELECT DISTINCT specialization FROM doctors ORDER BY specialization ASC");
$specialties_sql->execute();
$specialties_result = $specialties_sql->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - HMS</title>
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

        .booking-container {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .booking-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .booking-sidebar {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            height: fit-content;
        }

        .form-group {
            margin-bottom: 25px;
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

        .form-control:invalid {
            border-color: #e53e3e;
        }

        .doctor-selection {
            display: grid;
            gap: 15px;
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }

        .doctor-card {
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .doctor-card:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .doctor-card.selected {
            border-color: #667eea;
            background: #edf2f7;
        }

        .doctor-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .doctor-info h4 {
            margin: 0 0 5px 0;
            color: #2d3748;
            font-size: 16px;
        }

        .doctor-specialty {
            color: #667eea;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .doctor-details {
            font-size: 12px;
            color: #718096;
            display: flex;
            gap: 15px;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
        }

        .time-slot {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .time-slot:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .time-slot.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }

        .time-slot input[type="radio"] {
            display: none;
        }

        .specialty-filter {
            margin-bottom: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            background: #a0aec0;
            cursor: not-allowed;
            transform: none;
        }

        .btn-large {
            width: 100%;
            padding: 16px 24px;
            font-size: 16px;
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

        .booking-summary {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .booking-summary h3 {
            margin: 0 0 15px 0;
            color: #2d3748;
            font-size: 18px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            color: #2d3748;
        }

        .summary-label {
            color: #718096;
            font-size: 14px;
        }

        .summary-value {
            color: #2d3748;
            font-weight: 500;
        }

        .booking-tips {
            background: #e6fffa;
            border: 1px solid #81e6d9;
            border-radius: 8px;
            padding: 20px;
        }

        .booking-tips h3 {
            margin: 0 0 15px 0;
            color: #234e52;
            font-size: 16px;
        }

        .booking-tips ul {
            margin: 0;
            padding-left: 20px;
            color: #2d3748;
            font-size: 14px;
            line-height: 1.6;
        }

        .booking-tips li {
            margin-bottom: 8px;
        }

        @media (max-width: 1024px) {
            .booking-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .time-slots {
                grid-template-columns: repeat(2, 1fr);
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
            <li><a href="patient_book_appointment.php" class="active"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
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
            <h1><i class="fas fa-calendar-plus"></i> Book New Appointment</h1>
            <p>Schedule your appointment with our qualified medical professionals</p>
        </div>

        <div class="booking-container">
            <!-- Booking Form -->
            <div class="booking-form">
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

                <form method="POST" id="bookingForm">
                    <!-- Doctor Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-md"></i> Select Doctor *
                        </label>
                        
                        <!-- Specialty Filter -->
                        <div class="specialty-filter">
                            <select id="specialtyFilter" class="form-control">
                                <option value="">All Specializations</option>
                                <?php $specialties_result->data_seek(0); ?>
                                <?php while ($specialty = $specialties_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($specialty['specialization']); ?>">
                                        <?php echo htmlspecialchars($specialty['specialization']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="doctor-selection" id="doctorSelection">
                            <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                                <label class="doctor-card" data-specialty="<?php echo htmlspecialchars($doctor['specialization']); ?>">
                                    <input type="radio" name="doctor_id" value="<?php echo $doctor['id']; ?>" required>
                                    <div class="doctor-info">
                                        <h4>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h4>
                                        <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
                                        <div class="doctor-details">
                                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone']); ?></span>
                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></span>
                                        </div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Date Selection -->
                    <div class="form-group">
                        <label class="form-label" for="appointment_date">
                            <i class="fas fa-calendar"></i> Appointment Date *
                        </label>
                        <input type="date" id="appointment_date" name="appointment_date" 
                               class="form-control" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               max="<?php echo date('Y-m-d', strtotime('+3 months')); ?>"
                               required>
                    </div>

                    <!-- Time Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clock"></i> Preferred Time *
                        </label>
                        <div class="time-slots">
                            <?php 
                            $time_slots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
                            foreach ($time_slots as $time): 
                            ?>
                                <label class="time-slot">
                                    <input type="radio" name="appointment_time" value="<?php echo $time; ?>" required>
                                    <?php echo date('g:i A', strtotime($time)); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Purpose -->
                    <div class="form-group">
                        <label class="form-label" for="purpose">
                            <i class="fas fa-notes-medical"></i> Purpose of Visit *
                        </label>
                        <textarea id="purpose" name="purpose" class="form-control" rows="4" 
                                  placeholder="Please describe your symptoms or reason for the visit..."
                                  required></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="book_appointment" class="btn btn-primary btn-large" id="submitBtn" disabled>
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </button>
                </form>
            </div>

            <!-- Booking Sidebar -->
            <div class="booking-sidebar">
                <!-- Booking Summary -->
                <div class="booking-summary" id="bookingSummary" style="display: none;">
                    <h3><i class="fas fa-clipboard-list"></i> Appointment Summary</h3>
                    <div class="summary-item">
                        <span class="summary-label">Doctor:</span>
                        <span class="summary-value" id="selectedDoctor">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Specialty:</span>
                        <span class="summary-value" id="selectedSpecialty">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Date:</span>
                        <span class="summary-value" id="selectedDate">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Time:</span>
                        <span class="summary-value" id="selectedTime">-</span>
                    </div>
                </div>

                <!-- Booking Tips -->
                <div class="booking-tips">
                    <h3><i class="fas fa-lightbulb"></i> Booking Tips</h3>
                    <ul>
                        <li>Arrive 15 minutes early for your appointment</li>
                        <li>Bring your ID and insurance card</li>
                        <li>Prepare a list of current medications</li>
                        <li>Write down your symptoms and questions</li>
                        <li>You can cancel up to 2 hours before your appointment</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('bookingForm');
            const submitBtn = document.getElementById('submitBtn');
            const bookingSummary = document.getElementById('bookingSummary');
            const specialtyFilter = document.getElementById('specialtyFilter');
            const doctorCards = document.querySelectorAll('.doctor-card');
            
            // Specialty filter functionality
            specialtyFilter.addEventListener('change', function() {
                const selectedSpecialty = this.value.toLowerCase();
                
                doctorCards.forEach(card => {
                    const cardSpecialty = card.dataset.specialty.toLowerCase();
                    if (selectedSpecialty === '' || cardSpecialty.includes(selectedSpecialty)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });

            // Doctor card selection
            doctorCards.forEach(card => {
                card.addEventListener('click', function() {
                    doctorCards.forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                    updateSummary();
                    checkFormCompletion();
                });
            });

            // Time slot selection
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                    updateSummary();
                    checkFormCompletion();
                });
            });

            // Date change handler
            document.getElementById('appointment_date').addEventListener('change', function() {
                updateSummary();
                checkFormCompletion();
            });

            // Purpose change handler
            document.getElementById('purpose').addEventListener('input', function() {
                checkFormCompletion();
            });

            function updateSummary() {
                const selectedDoctor = document.querySelector('input[name="doctor_id"]:checked');
                const selectedDate = document.getElementById('appointment_date').value;
                const selectedTime = document.querySelector('input[name="appointment_time"]:checked');

                if (selectedDoctor || selectedDate || selectedTime) {
                    bookingSummary.style.display = 'block';

                    if (selectedDoctor) {
                        const doctorCard = selectedDoctor.closest('.doctor-card');
                        const doctorName = doctorCard.querySelector('h4').textContent;
                        const specialty = doctorCard.querySelector('.doctor-specialty').textContent;
                        document.getElementById('selectedDoctor').textContent = doctorName;
                        document.getElementById('selectedSpecialty').textContent = specialty;
                    }

                    if (selectedDate) {
                        const dateObj = new Date(selectedDate);
                        const formattedDate = dateObj.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        document.getElementById('selectedDate').textContent = formattedDate;
                    }

                    if (selectedTime) {
                        const timeSlot = selectedTime.closest('.time-slot');
                        document.getElementById('selectedTime').textContent = timeSlot.textContent.trim();
                    }
                }
            }

            function checkFormCompletion() {
                const doctor = document.querySelector('input[name="doctor_id"]:checked');
                const date = document.getElementById('appointment_date').value;
                const time = document.querySelector('input[name="appointment_time"]:checked');
                const purpose = document.getElementById('purpose').value.trim();

                if (doctor && date && time && purpose) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }

            // Form animation
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                group.style.opacity = '0';
                group.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    group.style.transition = 'all 0.5s ease';
                    group.style.opacity = '1';
                    group.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>