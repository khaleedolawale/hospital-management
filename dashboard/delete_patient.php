<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

$id = $_GET['id'];
$conn->query("DELETE FROM patients WHERE id=$id");

header("Location: manage_patients.php");
exit();
?>
