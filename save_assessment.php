<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$response = ['success' => false];

try {
    // Optional: Save raw POST for debugging
    file_put_contents('debug_post.txt', print_r($_POST, true));

    // Get user ID from session
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception("User not logged in.");
    }

    // Validate presence of POST data
    if (!isset($_POST['training_history'], $_POST['desired_skills'], $_POST['comments'])) {
        throw new Exception('Missing POST data.');
    }

    // Decode training history (still in JSON format)
    $training_history = json_decode($_POST['training_history'], true);
    $desired_skills = trim($_POST['desired_skills']); // plain text
    $comments = trim($_POST['comments']);

    // Validate training history
    if (!is_array($training_history)) {
        throw new Exception('Invalid training history data.');
    }

    foreach ($training_history as $entry) {
        if (
            !isset($entry['date'], $entry['training'], $entry['venue']) ||
            empty($entry['date']) || empty($entry['training']) || empty($entry['venue'])
        ) {
            throw new Exception('Malformed or incomplete training entry.');
        }
    }

    // Connect to user_db
    $con = new mysqli("localhost", "root", "", "user_db");
    if ($con->connect_error) {
        throw new Exception("Database connection failed: " . $con->connect_error);
    }

    // Prepare data
    $training_history_json = json_encode($training_history);

    // Insert into assessments table
    $stmt = $con->prepare("INSERT INTO assessments (user_id, training_history, desired_skills, comments) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error);
    }

    $stmt->bind_param("isss", $user_id, $training_history_json, $desired_skills, $comments);

    if (!$stmt->execute()) {
        throw new Exception("Insert failed: " . $stmt->error);
    }

    $response['success'] = true;
    $response['tables'] = [
        'training_history' => $training_history,
        'desired_skills' => $desired_skills,
        'assessment_comments' => ['comments' => htmlspecialchars($comments)]
    ];

    $stmt->close();
    $con->close();

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;
