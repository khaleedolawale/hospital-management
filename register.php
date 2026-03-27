<?php
// include("includes/db.php");
require __DIR__ . "/includes/db.php";


$msg = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role  = $_POST['role']; // admin, doctor, patient

    if ($password !== $confirm_password) {
        $msg = "⚠️ Passwords do not match!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if email OR phone already exists
        $check = $conn->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $check->bind_param("ss", $email, $phone);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $msg = "⚠️ Email or Phone already exists!";

        } else {
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?,?)");
                $stmt->bind_param("sssss", $name, $email, $phone, $hashed_password, $role);
    
                if ($stmt->execute()) {
                    $msg = "✅ Registration successful! <a href='login.php'>Login here</a>";
                } else {
                    $msg = "❌ Something went wrong!";
                }
        // } else {
        //     // Insert into users table
        //     $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?,?)");
        //     $stmt->bind_param("sssss", $name, $email, $phone, $hashed_password, $role);

        //     if ($stmt->execute()) {
        //         $user_id = $stmt->insert_id; // get inserted user ID

                // if ($role == 'doctor') {
                //     $specialization = $_POST['specialization'] ?? '';
                //     $schedule = $_POST['schedule'] ?? '';

                //     $stmt2 = $conn->prepare("INSERT INTO doctors (user_id, name, specialization, email, phone, schedule) VALUES (?,?,?,?,?,?)");
                //     $stmt2->bind_param("isssss", $user_id, $name, $specialization, $email, $phone, $schedule);
                //     $stmt2->execute();

                // } elseif ($role == 'patient') {
                //     $dob = $_POST['dob'] ?? '';
                //     $address = $_POST['address'] ?? '';

                //     $stmt3 = $conn->prepare("INSERT INTO patients (user_id, name, email, phone, dob, address) VALUES (?,?,?,?,?,?)");
                //     $stmt3->bind_param("isssss", $user_id, $name, $email, $phone, $dob, $address);
                //     $stmt3->execute();
                // }

            //     $msg = "✅ Registration successful! <a href='login.php'>Login here</a>";
            // } else {
            //     $msg = "❌ Something went wrong!";
            // }

            // Redirect based on role
            // if ($role === "admin") {
            //     header("Location: /dashboard.admin.php");
            // } elseif ($role === "doctor") {
            //     header("Location: doctor.php");
            // } elseif ($role === "patient") {
            //     header("Location: patient.php");
            // } else {
            //     $msg = "❌ Something went wrong!";
            // }
            // exit();
             
        }
    }

    // Log activity
        $desc = "Registered as " . ucfirst($role);
        $activity = $conn->prepare("INSERT INTO activities (user, role, action) VALUES (?, ?, ?)");
        $activity->bind_param("iss", $user_id, $role, $desc);
        $activity->execute();


}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Hospital System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> 
</head>
<body>
<form method="POST">
    <h2>Register</h2>
    <p style="color:red;"><?php echo $msg; ?></p>

    <input type="text" name="name" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="phone" placeholder="Phone Number" required>

    <!-- Password with toggle -->
    <div class="password-container">
        <input type="password" name="password" id="password" placeholder="Password" required>
        <i class="fas fa-eye" id="togglePassword"></i>
    </div>

    <!-- Confirm password with toggle -->
    <div class="password-container">
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
        <i class="fas fa-eye" id="toggleConfirmPassword"></i>
    </div>

    <!-- Role -->
    <select name="role" id="roleSelect" required>
        <option value="">-- Select Role --</option>
        <!-- <option value="admin">Admin</option> -->
        <option value="doctor">Doctor</option>
        <option value="patient">Patient</option>
    </select>

    <!-- Doctor fields -->
    <!-- <div id="doctorFields" style="display:none;">
        <input type="text" name="specialization" placeholder="Specialization">
        <!-- <input type="text" name="schedule" placeholder="Schedule"> -->
    <!-- </div> --> 

    <!-- Patient fields -->
    <!-- <div id="patientFields" style="display:none;">
        <input type="date" name="dob" placeholder="Date of Birth">
        <!-- <input type="text" name="address" placeholder="Address"> -->
    <!-- </div> --> 

    <button type="submit">Register</button>

    <p>Already have an account? <a href="login.php">Login here</a></p>
</form>

<script>
    // Password toggle
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");
    togglePassword.addEventListener("click", function () {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        this.classList.toggle("fa-eye-slash");
    });

    const toggleConfirmPassword = document.querySelector("#toggleConfirmPassword");
    const confirmPassword = document.querySelector("#confirm_password");
    toggleConfirmPassword.addEventListener("click", function () {
        const type = confirmPassword.getAttribute("type") === "password" ? "text" : "password";
        confirmPassword.setAttribute("type", type);
        this.classList.toggle("fa-eye-slash");
    });

    // Show/hide fields based on role
    //const roleSelect = document.getElementById("roleSelect");
    //const doctorFields = document.getElementById("doctorFields");
    //const patientFields = document.getElementById("patientFields");

    // roleSelect.addEventListener("change", function () {
        // doctorFields.style.display = "none";
        // patientFields.style.display = "none";

        // if (this.value === "doctor") {
            // doctorFields.style.display = "block";
        // } else if (this.value === "patient") {
            // patientFields.style.display = "block";
        // }
    // });
</script>

</body>
</html>
