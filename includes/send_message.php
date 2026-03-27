<?php
session_start();
require __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['msg'] = "All fields are required.";
        $_SESSION['msg_type'] = "error";
        
        header("Location: ../contact.php?msg=Message sent successfully");

        exit;
    }

    // Insert into DB
    $sql = "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $subject, $message);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "Your message has been sent successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "Error: Could not send your message.";
        $_SESSION['msg_type'] = "error";
    }

    $stmt->close();
    $conn->close();

    header("Location: ../contact.php?msg=Message sent successfully");

    exit;
}
?>
