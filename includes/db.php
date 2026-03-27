<?php
$host = "localhost";
$user = "root";   // default XAMPP username
$pass = "";       // default XAMPP password (leave empty unless you set one)
$db   = "hospital_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
