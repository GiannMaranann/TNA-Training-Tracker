<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ['success' => false, 'message' => ''];

try {
    // 1. Verify session and CSRF token
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Session expired. Please login again.");
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Security verification failed. Please refresh the page.");
    }

    // 2. Validate form data
    if (empty($_POST['print_training_data'])) {
        throw new Exception("No training data submitted.");
    }

    require_once 'config.php';
    
    // Get current active deadline
    $deadline_query = $con->query("SELECT id, submission_deadline, allow_submissions FROM settings WHERE is_active = 1 ORDER BY submission_deadline DESC LIMIT 1");
    if (!$deadline_query || $deadline_query->num_rows === 0) {
        throw new Exception("No active assessment period found.");
    }
    
    $deadline_data = $deadline_query->fetch_assoc();
    $deadline_id = $deadline_data['id'];
    $allow_submissions = (bool)$deadline_data['allow_submissions'];
    
    // Check if submissions are allowed
    if (!$allow_submissions) {
        throw new Exception("Submissions are currently closed for this assessment period.");
    }

    // 3. Process training data
    $training_data = json_decode($_POST['print_training_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($training_data)) {
        throw new Exception("Invalid training data format.");
    }

    $desired_skills = isset($_POST['desired_skills']) ? trim($_POST['desired_skills']) : '';
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    $user_id = (int)$_SESSION['user_id'];

    // 4. Validate training entries
    $has_valid_training = false;
    foreach ($training_data as $index => $entry) {
        if (!empty($entry['date']) && !empty($entry['training'])) {
            // Validate date format
            if (!DateTime::createFromFormat('Y-m-d', $entry['date'])) {
                throw new Exception("Invalid date format in training entry #" . ($index + 1));
            }

            // Validate date is not in future
            $training_date = new DateTime($entry['date']);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if ($training_date > $today) {
                throw new Exception("Training date cannot be in the future in entry #" . ($index + 1));
            }

            // Validate required fields
            if (empty($entry['start_time']) || empty($entry['end_time']) || empty($entry['venue'])) {
                throw new Exception("Please complete all fields for training entry #" . ($index + 1));
            }

            $has_valid_training = true;
        }
    }

    if (!$has_valid_training && empty($desired_skills) && empty($comments)) {
        throw new Exception('Please provide at least one complete training entry or desired skills/comments.');
    }

    // Check for existing submission for this deadline
    $check_sql = "SELECT id FROM assessments WHERE user_id = ? AND deadline_id = ? LIMIT 1";
    $check_stmt = $con->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception("Database error: " . $con->error);
    }

    $check_stmt->bind_param("ii", $user_id, $deadline_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        throw new Exception("You have already submitted an assessment for this period.");
    }
    $check_stmt->close();

    // Determine submission status (on-time or late)
    $now = new DateTime();
    $deadline = new DateTime($deadline_data['submission_deadline']);
    $status = ($now <= $deadline) ? 'submitted' : 'late';

    // Insert new assessment
    $training_json = json_encode($training_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $insert_sql = "INSERT INTO assessments 
                  (user_id, deadline_id, training_history, desired_skills, comments, created_at, submission_date, status) 
                  VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)";
    
    $insert_stmt = $con->prepare($insert_sql);
    
    if (!$insert_stmt) {
        throw new Exception("Database error: " . $con->error);
    }

    $insert_stmt->bind_param("iissss", $user_id, $deadline_id, $training_json, $desired_skills, $comments, $status);

    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to save assessment: " . $insert_stmt->error);
    }

    $assessment_id = $insert_stmt->insert_id;
    $insert_stmt->close();

    // Create admin notification
    $notif_message = "New assessment submitted by user ID: $user_id";
    $notif_sql = "INSERT INTO notifications 
                 (user_id, message, related_id, related_type, is_read, created_at, type) 
                 VALUES (?, ?, ?, 'assessment', 0, NOW(), 'new_submission')";
    
    $notif_stmt = $con->prepare($notif_sql);
    $admin_user_id = 1; // Default admin ID
    
    if ($notif_stmt) {
        $notif_stmt->bind_param("isi", $admin_user_id, $notif_message, $assessment_id);
        $notif_stmt->execute();
        $notif_stmt->close();
    }

    // Mark all assessment notifications as read for this user
    $mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND related_type = 'deadline'";
    $mark_read_stmt = $con->prepare($mark_read_sql);
    if ($mark_read_stmt) {
        $mark_read_stmt->bind_param("i", $user_id);
        $mark_read_stmt->execute();
        $mark_read_stmt->close();
    }

    // 6. Success response
    $response = [
        'success' => true,
        'message' => 'Assessment submitted successfully!',
        'assessment_id' => $assessment_id,
        'status' => $status,
        'redirect' => 'user_page.php?success=1'
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response['error'] = $e->getMessage();
    $response['message'] = $e->getMessage();
    error_log("Assessment Submission Error [" . date('Y-m-d H:i:s') . "]: " . $e->getMessage());
}

// Close database connection if it exists
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}

// Return JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>