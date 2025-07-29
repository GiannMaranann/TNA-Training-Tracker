<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "user_db");
if ($mysqli->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get the latest deadline
$deadlineRes = $mysqli->query("SELECT deadline FROM submission_deadline ORDER BY id DESC LIMIT 1");
$latestDeadline = $deadlineRes->fetch_assoc()['deadline'] ?? null;

$hasSubmitted = false;

if ($latestDeadline) {
    // Check if user has a submission AFTER the latest deadline
    $stmt = $mysqli->prepare("SELECT created_at FROM assessments WHERE user_id = ? AND created_at >= ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("is", $user_id, $latestDeadline);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentSubmission = $result->fetch_assoc();

    // If there is such a submission, user has already submitted
    $hasSubmitted = $recentSubmission ? true : false;
}

echo json_encode([
    'hasSubmitted' => $hasSubmitted,
    'latestDeadline' => $latestDeadline
]);
?>
