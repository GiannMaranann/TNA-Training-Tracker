<?php
session_start();
require 'config.php'; // Make sure this defines $con as the MySQLi connection

// Ensure user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $_SESSION['flash_message'] = "User not logged in.";
    $_SESSION['flash_type'] = "error";
    header("Location: user_page.php");
    exit;
}

// Collect and sanitize POST data
$dates        = $_POST['date'] ?? [];
$start_times  = $_POST['start_time'] ?? [];
$end_times    = $_POST['end_time'] ?? [];
$durations    = $_POST['duration'] ?? [];
$trainings    = $_POST['training'] ?? [];
$venues       = $_POST['venue'] ?? [];
$desired_skills = trim($_POST['desired_skills'] ?? '');
$comments       = trim($_POST['comments'] ?? '');

// Validate row count consistency
$rowCount = count($dates);
if (
    $rowCount === 0 ||
    $rowCount !== count($trainings) ||
    $rowCount !== count($venues) ||
    $rowCount !== count($start_times) ||
    $rowCount !== count($end_times)
) {
    $_SESSION['flash_message'] = "Error: Incomplete or mismatched training data.";
    $_SESSION['flash_type'] = "error";
    header("Location: user_page.php");
    exit;
}

// Build training history array
$training_history = [];
for ($i = 0; $i < $rowCount; $i++) {
    $training_history[] = [
        'date'       => $dates[$i],
        'start_time' => $start_times[$i] ?? '',
        'end_time'   => $end_times[$i] ?? '',
        'duration'   => $durations[$i] ?? '',
        'training'   => $trainings[$i],
        'venue'      => $venues[$i],
    ];
}

// Convert to JSON
$training_history_json = json_encode($training_history, JSON_UNESCAPED_UNICODE);

// Save to database
try {
    $stmt = $con->prepare("INSERT INTO assessments (user_id, training_history, desired_skills, comments) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error);
    }

    $stmt->bind_param("isss", $user_id, $training_history_json, $desired_skills, $comments);

    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Training Needs Assessment submitted successfully.";
        $_SESSION['flash_type'] = "success";
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }

    $stmt->close();
    $con->close();
} catch (Exception $e) {
    $_SESSION['flash_message'] = "Submission failed: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
}

// Redirect back
header("Location: user_page.php");
exit;
?>