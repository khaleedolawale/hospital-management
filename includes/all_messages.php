<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

// --- Pagination setup ---
$limit = 10; // messages per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Search setup ---
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";

// --- Query ---
$where = "";
if (!empty($search)) {
    $where = "WHERE name LIKE '%$search%' 
              OR email LIKE '%$search%' 
              OR subject LIKE '%$search%' 
              OR message LIKE '%$search%'";
}

$result = $conn->query("SELECT * FROM contact_messages $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// For pagination count
$countRes = $conn->query("SELECT COUNT(*) AS total FROM contact_messages $where");
$totalRows = $countRes->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Messages</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a class="link" href="../dashboard/admin.php">⬅ Back to Dashboard</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <h1>All Contact Messages</h1>
            </header>

            <!-- Search Bar -->
            <form method="GET" class="search-bar">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search messages...">
                <button type="submit">Search</button>
            </form>

            <section class="content">
                <table class="messages-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                        <td>{$row['name']}</td>
                                        <td>{$row['email']}</td>
                                        <td>{$row['subject']}</td>
                                        <td>{$row['message']}</td>
                                        <td>{$row['created_at']}</td>
                                        <td>
                                            <a href='view_message.php?id={$row['id']}' class='btn-view'>View</a>
                                            <a href='delete_message.php?id={$row['id']}' class='btn-delete'
                                                  onclick=\"return confirm('Are you sure you want to delete this message?');\">Delete</a>
                                        </td>

                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No messages found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Prev</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                            <?php if ($i == $page) echo "class='active'"; ?>>
                            <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                </div>
            </section>
        </main>
    </div>

</body>
</html>
