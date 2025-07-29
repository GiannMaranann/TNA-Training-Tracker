<?php
require_once 'config.php';
session_start();

// Check if admin user or authorized here (optional)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDeadline = $_POST['deadline'] ?? '';

    if ($newDeadline) {
        // Save new deadline to settings table
        $stmt = $con->prepare("UPDATE settings SET submission_deadline = ? WHERE id = 1");
        $stmt->bind_param("s", $newDeadline);
        $stmt->execute();

        // Set has_notification = 1 for all users (notify everyone)
        $con->query("UPDATE users SET has_notification = 1");

        echo json_encode(['status' => 'success', 'message' => 'Deadline updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid deadline value']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
