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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $emergency_contact = trim($_POST['emergency_contact']);
    $emergency_phone = trim($_POST['emergency_phone']);
    $blood_group = $_POST['blood_group'];
    $allergies = trim($_POST['allergies']);
    $medical_conditions = trim($_POST['medical_conditions']);
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($phone)) {
        $error_message = "Please fill in all required fields (Name, Email, Phone).";
    } else {
        // Check if email is already taken by another user
        $email_check_sql = $conn->prepare("SELECT id FROM patients WHERE email = ? AND id != ?");
        $email_check_sql->bind_param("si", $email, $user_id);
        $email_check_sql->execute();
        $email_exists = $email_check_sql->get_result();
        
        if ($email_exists->num_rows > 0) {
            $error_message = "This email address is already registered to another account.";
        } else {
            // Update patient profile
            $update_sql = $conn->prepare("UPDATE patients SET 
                name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, 
                gender = ?, emergency_contact = ?, emergency_phone = ?, blood_group = ?,
                allergies = ?, medical_conditions = ?, updated_at = NOW()
                WHERE id = ?");
            
            $update_sql->bind_param("sssssssssssi", 
                $name, $email, $phone, $address, $date_of_birth, 
                $gender, $emergency_contact, $emergency_phone, $blood_group,
                $allergies, $medical_conditions, $user_id
            );
            
            if ($update_sql->execute()) {
                $success_message = "Profile updated successfully!";
                
                // Log activity
                $activity_sql = $conn->prepare("INSERT INTO activities (user, action, created_at) VALUES (?, ?, NOW())");
                $activity_action = "Updated profile information";
                $activity_sql->bind_param("is", $user_id, $activity_action);
                $activity_sql->execute();
                
                // Refresh patient data
                $patient_sql->execute();
                $patient = $patient_sql->get_result()->fetch_assoc();
            } else {
                $error_message = "Error updating profile. Please try again.";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $password_error = "Password must be at least 6 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $patient['password'])) {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $password_sql = $conn->prepare("UPDATE patients SET password = ?, updated_at = NOW() WHERE id = ?");
            $password_sql->bind_param("si", $new_password_hash, $user_id);
            
            if ($password_sql->execute()) {
                $password_success = "Password changed successfully!";
                
                // Log activity
                $activity_sql = $conn->prepare("INSERT INTO activities (user, action, created_at) VALUES (?, ?, NOW())");
                $activity_action = "Changed password";
                $activity_sql->bind_param("is", $user_id, $activity_action);
                $activity_sql->execute();
            } else {
                $password_error = "Error changing password. Please try again.";
            }
        } else {
            $password_error = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - HMS</title>
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

        .profile-container {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .profile-forms {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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

        .required {
            color: #e53e3e;
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
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1
);
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            text-align: center;
        }

        .btn:hover {
            background: #5a67d8;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #f0fff4;
            color: #38a169;
            border: 1px solid #c6f6d5;
        }

        .alert-error {
            background: #fff5f5;
            color: #e53e3e;
            border: 1px solid #fed7d7;
        }

        .profile-summary {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .summary-item {
            margin-bottom: 15px;
        }

        .summary-item strong {
            color: #2d3748;
        }

        @media (max-width: 992px) {
            .patient-sidebar {
                width: 220px;
            }

            .patient-main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            .patient-sidebar {
                position: relative;
                width: 100%;
                height: auto;
                display: flex;
                flex-direction: row;
                overflow-x: auto;
            }

            .patient-sidebar .sidebar-menu {
                display: flex;
                flex-direction: row;
                padding: 0;
            }

            .patient-sidebar .sidebar-menu li {
                margin: 0;
            }

            .patient-sidebar .sidebar-menu a {
                padding: 15px 10px;
                white-space: nowrap;
            }

            .patient-main-content {
                margin-left: 0;
            }

            .profile-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="patient-sidebar">
        <div class="sidebar-logo">
            HMS
        </div>
        <div class="patient-info">
            <div class="patient-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></div>
            <div class="patient-email"><?php echo htmlspecialchars($patient['email']); ?></div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="patient.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="patient_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
            <li><a href="patient_medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
            <li><a href="patient_prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
            <li><a href="patient_profile_settings.php" class="active"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
            <li><a href="patient_billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
            <li>
        <a href="../logout.php" onclick="return confirm('Are you sure you want to log out?')">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
        </ul>
    </div>
    <div class="patient-main-content">
        <div class="content-header">
            <h1>Profile Settings</h1>
            <p>Manage your personal information and account settings.</p>
        </div>
        <div class="profile-container">
            <div class="profile-forms">
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-user-edit"></i>
                        <h3>Edit Profile Information</h3>
                    </div>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-error"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <?php if (isset($success_message)): ?>
                        <div class="alert
    alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                        <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Phone <span class="required">*</span></label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="">Select</option>
                                    <option value="Male" <?php if ($patient['gender']=="Male") echo "selected"; ?>>Male</option>
                                    <option value="Female" <?php if ($patient['gender']=="Female") echo "selected"; ?>>Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($patient['emergency_contact'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Emergency Phone</label>
                                <input type="text" name="emergency_phone" class="form-control" value="<?php echo htmlspecialchars($patient['emergency_phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Blood Group</label>
                                <input type="text" name="blood_group" class="form-control" value="<?php echo htmlspecialchars($patient['blood_group'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Allergies</label>
                                <input type="text" name="allergies" class="form-control" value="<?php echo htmlspecialchars($patient['allergies'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Medical Conditions</label>
                            <textarea name="medical_conditions" class="form-control"><?php echo htmlspecialchars($patient['medical_conditions'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn">Save Changes</button>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-lock"></i>
                        <h3>Change Password</h3>
                    </div>
                    <?php if (isset($password_error)): ?>
                        <div class="alert alert-error"><?php echo $password_error; ?></div>
                    <?php endif; ?>
                    <?php if (isset($password_success)): ?>
                        <div class="alert alert-success"><?php echo $password_success; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Profile Summary -->
            <div class="profile-summary">
                <h3>Profile Summary</h3>
                <div class="summary-item"><strong>Name:</strong> <?php echo htmlspecialchars($patient['name']); ?></div>
                <div class="summary-item"><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></div>
                <div class="summary-item"><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></div>
                <div class="summary-item"><strong>Address:</strong> <?php echo htmlspecialchars($patient['address'] ?? ''); ?></div>
                <div class="summary-item"><strong>Date of Birth:</strong> <?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?></div>
                <div class="summary-item"><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender'] ?? ''); ?></div>
                <div class="summary-item"><strong>Blood Group:</strong> <?php echo htmlspecialchars($patient['blood_group'] ?? ''); ?></div>
                <div class="summary-item"><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></div>
                <div class="summary-item"><strong>Medical Conditions:</strong> <?php echo htmlspecialchars($patient['medical_conditions'] ?? ''); ?></div>
                <div class="summary-item"><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($patient['emergency_contact'] ?? ''); ?> (<?php echo htmlspecialchars($patient['emergency_phone'] ?? ''); ?>)</div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 -->
<!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Handle PHP success/error messages via JS
    <?php if (isset($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?php echo $success_message; ?>',
            confirmButtonColor: '#667eea'
        });
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo $error_message; ?>',
            confirmButtonColor: '#e53e3e'
        });
    <?php endif; ?>

    <?php if (isset($password_success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Password Changed',
            text: '<?php echo $password_success; ?>',
            confirmButtonColor: '#667eea'
        });
    <?php endif; ?>

    <?php if (isset($password_error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Password Error',
            text: '<?php echo $password_error; ?>',
            confirmButtonColor: '#e53e3e'
        });
    <?php endif; ?>

    // Confirm before submitting password change
    const passwordForm = document.querySelector("form[action=''][method='POST'] button[name='change_password']");
    if (passwordForm) {
        passwordForm.addEventListener("click", function (e) {
            e.preventDefault();
            Swal.fire({
                title: "Are you sure?",
                text: "You are about to change your password.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#667eea",
                cancelButtonColor: "#e53e3e",
                confirmButtonText: "Yes, change it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    e.target.closest("form").submit();
                }
            });
        });
    }
});
</script>

</body>
</html>
