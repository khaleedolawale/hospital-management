<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

if (!isset($_GET['id'])) {
    header("Location: all_messages.php");
    exit();
}

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM contact_messages WHERE id = $id");

if ($result->num_rows == 0) {
    echo "Message not found.";
    exit();
}

$message = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Message</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a class="link" href="all_messages.php">⬅ Back to Messages</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <h1>Message Details</h1>
            </header>

            <section class="content">
                <div class="message-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($message['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($message['email']); ?></p>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($message['subject']); ?></p>
                    <p><strong>Date:</strong> <?php echo htmlspecialchars($message['created_at']); ?></p>
                    <hr>
                    <p><strong>Message:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
