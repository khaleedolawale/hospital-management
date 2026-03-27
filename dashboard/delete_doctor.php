<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "DELETE FROM doctors WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Doctor deleted successfully!'); window.location='manage_doctors.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "'); window.location='manage_doctors.php';</script>";
    }
} else {
    header("Location: manage_doctors.php");
}
?>
