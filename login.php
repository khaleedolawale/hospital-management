<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();
require __DIR__ . "/includes/db.php";

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $emailOrPhone = $_POST['email_or_phone'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role']    = $row['role'];

            // Use role from DB
            $role = $row['role'];

            // Redirect based on role
            if ($role === "admin") {
                header("Location: dashboard/admin.php");
            } elseif ($role === "doctor") {
                header("Location: dashboard/doctor.php");
            } elseif ($role === "patient") {
                header("Location: dashboard/patient.php");
            } else {
                header("Location: index.php");
            }
            exit();

        } else {
            $msg = "❌ Invalid password!";
        }
    } else {
        $msg = "❌ Email/Phone not found!";
    }
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Login - Hospital System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> 
</head>
<body>
    <form method="POST">
        <h2>Login</h2>
        <p style="color:red;"><?php echo $msg; ?></p>

        <input type="text" name="email_or_phone" placeholder="Email or Phone" required>

        <div class="password-container">
            <input type="password" name="password" id="password" placeholder="Password" required>
            <i class="fas fa-eye" id="togglePassword"></i>
        </div>

        <button type="submit">Login</button>

        <p>No account? <a href="register.php">Register here</a></p>
    </form>

    <script>
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");

    togglePassword.addEventListener("click", function () {
        // Toggle the type attribute
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);

        // Toggle the eye icon style
        this.classList.toggle("fa-eye-slash");
        this.classList.toggle("active");
    });
</script>

</body>
</html>
