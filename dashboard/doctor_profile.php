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

if (!$doctor) {
    header("Location: ../login.php");
    exit();
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $specialization = trim($_POST['specialization']);
    $schedule = trim($_POST['schedule']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    if (empty($specialization)) {
        $errors[] = "Specialization is required.";
    }
    
    // Check if email already exists (for other doctors)
    $email_check_sql = $conn->prepare("SELECT id FROM doctors WHERE email = ? AND id != ?");
    $email_check_sql->bind_param("si", $email, $doctor['id']);
    $email_check_sql->execute();
    if ($email_check_sql->get_result()->num_rows > 0) {
        $errors[] = "Email already exists for another doctor.";
    }
    
    if (empty($errors)) {
        $update_sql = $conn->prepare("UPDATE doctors SET name = ?, email = ?, phone = ?, specialization = ?, schedule = ? WHERE id = ?");
        $update_sql->bind_param("sssssi", $name, $email, $phone, $specialization, $schedule, $doctor['id']);
        
        if ($update_sql->execute()) {
            $success_message = "Profile updated successfully!";
            // Update the current data
            $doctor['name'] = $name;
            $doctor['email'] = $email;
            $doctor['phone'] = $phone;
            $doctor['specialization'] = $specialization;
            $doctor['schedule'] = $schedule;
            
            // Log activity
            $activity_sql = $conn->prepare("INSERT INTO activities (user, role, action, created_at) VALUES (?, 'doctor', 'Updated profile information', NOW())");
            $activity_sql->bind_param("i", $user_id);
            $activity_sql->execute();
        } else {
            $errors[] = "Error updating profile. Please try again.";
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $password_errors = [];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_errors[] = "All password fields are required.";
    }
    
    if ($new_password !== $confirm_password) {
        $password_errors[] = "New passwords do not match.";
    }
    
    if (strlen($new_password) < 6) {
        $password_errors[] = "New password must be at least 6 characters long.";
    }
    
    if (empty($password_errors)) {
        // Check current password from users table
        $password_check_sql = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'doctor'");
        $password_check_sql->bind_param("i", $user_id);
        $password_check_sql->execute();
        $password_result = $password_check_sql->get_result()->fetch_assoc();
        
        if ($password_result && password_verify($current_password, $password_result['password'])) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password_sql = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'doctor'");
            $update_password_sql->bind_param("si", $hashed_new_password, $user_id);
            
            if ($update_password_sql->execute()) {
                $password_success = "Password changed successfully!";
                
                // Log activity
                $activity_sql = $conn->prepare("INSERT INTO activities (user, role, action, created_at) VALUES (?, 'doctor', 'Changed password', NOW())");
                $activity_sql->bind_param("i", $user_id);
                $activity_sql->execute();
            } else {
                $password_errors[] = "Error changing password. Please try again.";
            }
        } else {
            $password_errors[] = "Current password is incorrect.";
        }
    }
}

// Get profile statistics
$profile_stats_sql = $conn->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        COUNT(DISTINCT patient_id) as total_patients,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_appointments,
        MIN(appointment_date) as first_appointment,
        MAX(appointment_date) as last_appointment
    FROM appointments 
    WHERE doctor_id = ?
");
$profile_stats_sql->bind_param("i", $doctor['id']);
$profile_stats_sql->execute();
$profile_stats = $profile_stats_sql->get_result()->fetch_assoc();

// Get recent activities
$recent_activities_sql = $conn->prepare("
    SELECT action, created_at 
    FROM activities 
    WHERE user = ? AND role = 'doctor'
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_activities_sql->bind_param("i", $user_id);
$recent_activities_sql->execute();
$recent_activities = $recent_activities_sql->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Doctor Dashboard</title>
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

        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            text-align: center;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
            color: white;
            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);
        }

        .profile-name {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 10px 0;
        }

        .profile-specialization {
            font-size: 18px;
            color: #4facfe;
            margin: 0 0 5px 0;
        }

        .profile-member-since {
            font-size: 14px;
            color: #718096;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #4facfe;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .profile-card {
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

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary { 
            background: #4facfe; 
            color: white; 
        }

        .btn-success { 
            background: #48bb78; 
            color: white; 
        }

        .btn-danger {
            background: #f56565;
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

        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }

        .strength-weak { color: #f56565; }
        .strength-medium { color: #ed8936; }
        .strength-strong { color: #48bb78; }

        .recent-activities {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #4a5568;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 14px;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 12px;
            color: #718096;
        }

        .additional-settings {
            margin-top: 30px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .settings-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #4facfe;
        }

        .settings-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin: 0 0 10px 0;
        }

        .settings-description {
            color: #718096;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        @media (max-width: 968px) {
            .profile-sections {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
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
            <li><a href="doctor_profile.php" class="active"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
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
            <h1>Profile Settings</h1>
            <div class="topbar-actions">
                <a href="doctor.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="content-section">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <i class="fas fa-user-md"></i>
                </div>
                <h1 class="profile-name"><?php echo htmlspecialchars($doctor['name']); ?></h1>
                <p class="profile-specialization"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                <p class="profile-member-since">Member since <?php echo date('F j, Y', strtotime($doctor['created_at'])); ?></p>
                
                <!-- Statistics Overview -->
                <div class="stats-overview">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $profile_stats['total_appointments'] ?? 0; ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $profile_stats['total_patients'] ?? 0; ?></div>
                        <div class="stat-label">Patients Treated</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $profile_stats['completed_appointments'] ?? 0; ?></div>
                        <div class="stat-label">Completed Sessions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php 
                            $experience_years = $profile_stats['first_appointment'] 
                                ? max(1, date('Y') - date('Y', strtotime($profile_stats['first_appointment'])))
                                : date('Y') - date('Y', strtotime($doctor['created_at']));
                            echo $experience_years;
                            ?>
                        </div>
                        <div class="stat-label">Years Experience</div>
                    </div>
                </div>
            </div>

            <!-- Profile Sections -->
            <div class="profile-sections">
                <!-- Personal Information -->
                <div class="profile-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                    </div>
                    <div class="card-body">
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

                        <form method="POST">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="specialization">Medical Specialization</label>
                                <select id="specialization" name="specialization" required>
                                    <option value="">Select Specialization</option>
                                    <option value="General Practice" <?php echo $doctor['specialization'] == 'General Practice' ? 'selected' : ''; ?>>General Practice</option>
                                    <option value="Internal Medicine" <?php echo $doctor['specialization'] == 'Internal Medicine' ? 'selected' : ''; ?>>Internal Medicine</option>
                                    <option value="Family Medicine" <?php echo $doctor['specialization'] == 'Family Medicine' ? 'selected' : ''; ?>>Family Medicine</option>
                                    <option value="Cardiology" <?php echo $doctor['specialization'] == 'Cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                                    <option value="Dermatology" <?php echo $doctor['specialization'] == 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                                    <option value="Emergency Medicine" <?php echo $doctor['specialization'] == 'Emergency Medicine' ? 'selected' : ''; ?>>Emergency Medicine</option>
                                    <option value="Endocrinology" <?php echo $doctor['specialization'] == 'Endocrinology' ? 'selected' : ''; ?>>Endocrinology</option>
                                    <option value="Gastroenterology" <?php echo $doctor['specialization'] == 'Gastroenterology' ? 'selected' : ''; ?>>Gastroenterology</option>
                                    <option value="Neurology" <?php echo $doctor['specialization'] == 'Neurology' ? 'selected' : ''; ?>>Neurology</option>
                                    <option value="Oncology" <?php echo $doctor['specialization'] == 'Oncology' ? 'selected' : ''; ?>>Oncology</option>
                                    <option value="Orthopedics" <?php echo $doctor['specialization'] == 'Orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                                    <option value="Pediatrics" <?php echo $doctor['specialization'] == 'Pediatrics' ? 'selected' : ''; ?>>Pediatrics</option>
                                    <option value="Psychiatry" <?php echo $doctor['specialization'] == 'Psychiatry' ? 'selected' : ''; ?>>Psychiatry</option>
                                    <option value="Radiology" <?php echo $doctor['specialization'] == 'Radiology' ? 'selected' : ''; ?>>Radiology</option>
                                    <option value="Surgery" <?php echo $doctor['specialization'] == 'Surgery' ? 'selected' : ''; ?>>Surgery</option>
                                    <option value="Urology" <?php echo $doctor['specialization'] == 'Urology' ? 'selected' : ''; ?>>Urology</option>
                                    <option value="Obstetrics & Gynecology" <?php echo $doctor['specialization'] == 'Obstetrics & Gynecology' ? 'selected' : ''; ?>>Obstetrics & Gynecology</option>
                                    <option value="Ophthalmology" <?php echo $doctor['specialization'] == 'Ophthalmology' ? 'selected' : ''; ?>>Ophthalmology</option>
                                    <option value="Otolaryngology" <?php echo $doctor['specialization'] == 'Otolaryngology' ? 'selected' : ''; ?>>Otolaryngology (ENT)</option>
                                    <option value="Other" <?php echo $doctor['specialization'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="schedule">Working Schedule & Availability</label>
                                <textarea id="schedule" name="schedule" placeholder="e.g., Monday-Friday: 9:00 AM - 5:00 PM, Saturday: 9:00 AM - 1:00 PM, Lunch: 1:00 PM - 2:00 PM"><?php echo htmlspecialchars($doctor['schedule'] ?? ''); ?></textarea>
                                <small style="color: #718096; font-size: 12px;">
                                    Specify your working hours, days off, and availability for patient reference
                                </small>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="profile-card">
                    <div class="card-header">
                        <h3><i class="fas fa-lock"></i> Security & Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($password_success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $password_success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($password_errors)): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <ul style="margin: 10px 0 0 20px;">
                                    <?php foreach ($password_errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="passwordForm">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                <div style="font-size: 12px; margin-top: 5px; color: #718096;" id="passwordMatch"></div>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-danger">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>

                        <!-- Security Tips -->
                        <div style="margin-top: 25px; padding: 15px; background: #f7fafc; border-radius: 8px; border-left: 4px solid #4facfe;">
                            <h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">
                                <i class="fas fa-shield-alt"></i> Password Security Tips
                            </h4>
                            <ul style="margin: 0; padding-left: 20px; color: #4a5568; font-size: 13px; line-height: 1.5;">
                                <li>Use at least 8 characters with mixed case letters</li>
                                <li>Include numbers and special characters</li>
                                <li>Avoid using personal information</li>
                                <li>Change password regularly (every 3-6 months)</li>
                                <li>Never share your credentials with anyone</li>
                                <li>Log out from shared computers</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="profile-card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Account Activities</h3>
                </div>
                <div class="card-body">
                    <ul class="recent-activities">
                        <?php if ($recent_activities->num_rows > 0): ?>
                            <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text"><?php echo htmlspecialchars($activity['action']); ?></div>
                                    <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-info"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">No recent activities recorded</div>
                                    <div class="activity-time">Your activities will appear here</div>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Additional Settings -->
            <div class="additional-settings">
                <h3 style="margin: 0 0 20px 0; color: #2d3748;">
                    <i class="fas fa-cogs"></i> Account Management
                </h3>
                
                <div class="settings-grid">
                    <div class="settings-item">
                        <h4 class="settings-title">
                            <i class="fas fa-user-check"></i> Account Information
                        </h4>
                        <div class="settings-description">
                            Your account is active and verified. You have full access to all doctor dashboard features.
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                            <div>
                                <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Doctor ID</strong>
                                <div style="color: #2d3748; font-weight: 600;">#<?php echo $doctor['id']; ?></div>
                            </div>
                            <div>
                                <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Status</strong>
                                <div style="color: #48bb78; font-weight: 600;">
                                    <i class="fas fa-check-circle"></i> Active
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-item">
                        <h4 class="settings-title">
                            <i class="fas fa-download"></i> Data Export
                        </h4>
                        <div class="settings-description">
                            Export your profile data, appointment history, and medical records for your records.
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="export_profile.php" class="btn btn-info" style="margin-right: 10px;">
                                <i class="fas fa-file-export"></i> Export Data
                            </a>
                        </div>
                    </div>

                    <div class="settings-item">
                        <h4 class="settings-title">
                            <i class="fas fa-print"></i> Professional Documents
                        </h4>
                        <div class="settings-description">
                            Generate professional documents like your profile summary and credentials.
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="print_profile.php" class="btn btn-primary" style="margin-right: 10px;">
                                <i class="fas fa-print"></i> Print Profile
                            </a>
                            <a href="generate_certificate.php" class="btn btn-success">
                                <i class="fas fa-certificate"></i> Certificate
                            </a>
                        </div>
                    </div>

                    <div class="settings-item">
                        <h4 class="settings-title">
                            <i class="fas fa-bell"></i> Notifications
                        </h4>
                        <div class="settings-description">
                            Manage your notification preferences for appointments, reminders, and updates.
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="notification_settings.php" class="btn btn-warning">
                                <i class="fas fa-cog"></i> Manage Notifications
                            </a>
                        </div>
                    </div>

                    <div class="settings-item">
                        <h4 class="settings-title">
                            <i class="fas fa-question-circle"></i> Help & Support
                        </h4>
                        <div class="settings-description">
                            Access help documentation, contact support, or report issues with your account.
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="help_center.php" class="btn btn-info" style="margin-right: 10px;">
                                <i class="fas fa-life-ring"></i> Help Center
                            </a>
                            <a href="contact_support.php" class="btn btn-primary">
                                <i class="fas fa-envelope"></i> Support
                            </a>
                        </div>
                    </div>

                    <div class="settings-item" style="border-left-color: #f56565;">
                        <h4 class="settings-title" style="color: #c53030;">
                            <i class="fas fa-exclamation-triangle"></i> Account Deactivation
                        </h4>
                        <div class="settings-description">
                            Temporarily deactivate your account. This action can be reversed by contacting support.
                        </div>
                        <div style="margin-top: 15px;">
                            <button onclick="confirmDeactivation()" class="btn btn-danger">
                                <i class="fas fa-user-times"></i> Deactivate Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professional Information Summary -->
            <div class="profile-card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-id-card"></i> Professional Summary</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px;">Full Name</label>
                            <div style="padding: 12px; background: #f7fafc; border-radius: 8px; color: #2d3748; font-weight: 600;">
                                <?php echo htmlspecialchars($doctor['name']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px;">Medical Specialization</label>
                            <div style="padding: 12px; background: #f7fafc; border-radius: 8px; color: #2d3748; font-weight: 600;">
                                <?php echo htmlspecialchars($doctor['specialization']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px;">Contact Email</label>
                            <div style="padding: 12px; background: #f7fafc; border-radius: 8px; color: #2d3748; font-weight: 600;">
                                <?php echo htmlspecialchars($doctor['email']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px;">Phone Number</label>
                            <div style="padding: 12px; background: #f7fafc; border-radius: 8px; color: #2d3748; font-weight: 600;">
                                <?php echo htmlspecialchars($doctor['phone']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px;">Member Since</label>
                            <div style="padding: 12px; background: #f7fafc; border-radius: 8px; color: #2d3748; font-weight: 600;">
                                <?php echo date('F j, Y', strtotime($doctor['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px;">Profile Status</label>
                            <div style="padding: 12px; background: #c6f6d5; color: #2f855a; border-radius: 8px; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Complete & Active
                            </div>
                        </div>
                    </div>

                    <?php if ($doctor['schedule']): ?>
                    <div style="margin-top: 20px;">
                        <label style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 600; font-size: 14px;">Working Schedule</label>
                        <div style="padding: 15px; background: #f7fafc; border-radius: 8px; color: #2d3748; white-space: pre-line; font-family: monospace; line-height: 1.6;">
                            <?php echo htmlspecialchars($doctor['schedule']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength checker
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordStrengthDiv = document.getElementById('passwordStrength');
            const passwordMatchDiv = document.getElementById('passwordMatch');

            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strength = checkPasswordStrength(password);
                    
                    passwordStrengthDiv.innerHTML = `Password strength: <span class="strength-${strength.level}">${strength.text}</span>`;
                    
                    // Check if passwords match
                    checkPasswordMatch();
                });
            }

            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            }

            function checkPasswordStrength(password) {
                let score = 0;
                
                // Length check
                if (password.length >= 8) score++;
                if (password.length >= 12) score++;
                
                // Character variety checks
                if (/[a-z]/.test(password)) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                
                if (score < 3) {
                    return { level: 'weak', text: 'Weak' };
                } else if (score < 5) {
                    return { level: 'medium', text: 'Medium' };
                } else {
                    return { level: 'strong', text: 'Strong' };
                }
            }

            function checkPasswordMatch() {
                if (!newPasswordInput || !confirmPasswordInput || !passwordMatchDiv) return;
                
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword.length > 0) {
                    if (newPassword === confirmPassword) {
                        passwordMatchDiv.innerHTML = '<span style="color: #48bb78;"><i class="fas fa-check"></i> Passwords match</span>';
                    } else {
                        passwordMatchDiv.innerHTML = '<span style="color: #f56565;"><i class="fas fa-times"></i> Passwords do not match</span>';
                    }
                } else {
                    passwordMatchDiv.innerHTML = '';
                }
            }

            // Form validation
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = newPasswordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        return false;
                    }

                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
                    submitBtn.disabled = true;
                });
            }

            // Auto-resize textarea
            const textarea = document.getElementById('schedule');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
                
                // Initial resize
                textarea.dispatchEvent(new Event('input'));
            }

            // Show loading state for profile update
            const profileForm = document.querySelector('form:not(#passwordForm)');
            if (profileForm) {
                profileForm.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Profile...';
                    submitBtn.disabled = true;
                });
            }

            // Animate profile cards on load
            const cards = document.querySelectorAll('.profile-card, .settings-item');
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

        // Account deactivation confirmation
        function confirmDeactivation() {
            const confirmed = confirm(
                'Are you sure you want to deactivate your account?\n\n' +
                'This will:\n' +
                '- Temporarily disable your access\n' +
                '- Cancel pending appointments\n' +
                '- Hide your profile from patients\n\n' +
                'You can reactivate by contacting support.'
            );
            
            if (confirmed) {
                const doubleConfirm = confirm('This is your final confirmation. Deactivate account?');
                if (doubleConfirm) {
                    window.location.href = 'deactivate_account.php';
                }
            }
        }

        // Print profile function
        function printProfile() {
            window.print();
        }

        // Copy contact information
        function copyToClipboard(text, element) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary success message
                const originalContent = element.innerHTML;
                element.innerHTML = '<i class="fas fa-check"></i> Copied!';
                element.style.color = '#48bb78';
                
                setTimeout(() => {
                    element.innerHTML = originalContent;
                    element.style.color = '';
                }, 2000);
            }).catch(function() {
                alert('Unable to copy to clipboard');
            });
        }
    </script>
</body>
</html>