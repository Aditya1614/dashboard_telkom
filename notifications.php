<?php
// Function to send email 
function send_email($email, $message, $subject) {
    $headers = "From: adityacandragumilang.9a@gmail.com"; // ubah sesuai kebutuhan
    
    return mail($email, $subject, $message, $headers);
}

// Load users from users.json
$users = json_decode(file_get_contents('users.json'), true);

// Load notifications from notifications.json
$notifications = json_decode(file_get_contents('notifications.json'), true);

// Handle form submission to send notifications
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $sent_emails = array();

    // Send email to all users except admin
    foreach ($users as $user) {
        if ($user['role'] != 'admin' && !empty($user['email'])) {
            if (send_email($user['email'], $message, $subject)) {
                $sent_emails[] = $user['email'];
            }
        }
    }

    // Store notification in notifications.json
    $new_notification = array(
        'subject' => $subject,
        'message' => $message,
        'sent_to' => $sent_emails,
        'timestamp' => date('Y-m-d H:i:s')
    );
    $notifications[] = $new_notification;
    file_put_contents('notifications.json', json_encode($notifications));

    echo "Notifications sent successfully!";
}

// Handle resending to specific email
if (isset($_GET['resend']) && isset($_GET['index'])) {
    $email = $_GET['resend'];
    $index = $_GET['index'];
    if (isset($notifications[$index])) {
        $notification = $notifications[$index];
        if (send_email($email, $notification['message'], $notification['subject'])) {
            echo "Resent notification to $email";
        } else {
            echo "Failed to resend notification to $email";
        }
    } else {
        echo "Notification not found";
    }
}

// Handle deleting a notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $index = $_GET['delete'];
    if (isset($notifications[$index])) {
        array_splice($notifications, $index, 1);
        file_put_contents('notifications.json', json_encode($notifications));
        echo "Notification deleted successfully!";
    } else {
        echo "Notification not found";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Notification Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 20px;
        background-color: #f4f4f4;
    }

    /* .container {
        max-width: 800px;
        margin: auto;
        background: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    } */

    h1,
    h2 {
        color: #333;
    }

    form {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 5px;
    }

    input[type="text"],
    textarea {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }

    textarea {
        height: 100px;
    }

    button {
        background-color: #4CAF50;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        background-color: #45a049;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .resend-btn,
    .delete-btn {
        padding: 5px 10px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.9em;
        width: 100%;
        text-align: left;
    }

    .resend-btn {
        background-color: #008CBA;
        color: white;

    }

    .resend-btn:hover {
        background-color: #007B9A;
    }

    .delete-btn {
        background-color: #f44336;
        color: white;
    }

    .delete-btn:hover {
        background-color: #d32f2f;
    }

    .send-btn,
    .home-btn {
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1em;
        text-decoration: none;
        color: white;
    }

    .send-btn {
        background-color: #4CAF50;
    }

    .send-btn:hover {
        background-color: #45a049;
    }

    .home-btn {
        background-color: #4CAF50;
    }

    .home-btn:hover {
        background-color: #45a049;
    }
    </style>
</head>

<body>
    <h1>Send Notification to Users</h1>
    <form method="POST" action="">
        <label for="subject">Subject:</label>
        <input type="text" id="subject" name="subject" placeholder="Enter subject" required><br><br>
        <label for="message">Message:</label>
        <textarea name="message" placeholder="Enter your notification message" required></textarea><br>
        <div class="button-container">
            <button type="submit" class="send-btn">Send Notification</button>
            <a href="index.php" class="home-btn">Back to Home</a>
        </div>
    </form>

    <h2>Sent Notifications</h2>
    <?php if (count($notifications) > 0): ?>
    <table>
        <tr>
            <th>Subject</th>
            <th>Message</th>
            <th>Sent To</th>
            <th>Timestamp</th>
            <th>Action</th>
            <th>Delete</th>
        </tr>
        <?php foreach ($notifications as $index => $notification):  ?>
        <tr>
            <td><?php echo $notification['subject']; ?></td>
            <td><?php echo $notification['message']; ?></td>
            <td><?php echo implode(', ', $notification['sent_to']); ?></td>
            <td><?php echo $notification['timestamp']; ?></td>
            <td>
                <div class="action-buttons">
                    <?php foreach ($notification['sent_to'] as $email): ?>
                    <form action="" method="get" style="display:inline;">
                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                        <button type="submit" name="resend" value="<?php echo $email; ?>">Resend to
                            <?php echo $email; ?></button>
                    </form>
                    <?php endforeach; ?>
            </td>
            <td>
                <form action="" method="get"
                    ><button type="button" class="delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal" data-bs-index="<?php echo $index; ?>">
                        Delete
                    </button>
                </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No notifications sent yet.</p>
    <?php endif; ?>

    

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var deleteModal = document.getElementById('deleteModal')
    deleteModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget
        var index = button.getAttribute('data-bs-index')
        var confirmDeleteButton = deleteModal.querySelector('#confirmDelete')
        confirmDeleteButton.onclick = function() {
            window.location.href = "?delete=" + index
        }
    })
    </script>

</body>

</html>