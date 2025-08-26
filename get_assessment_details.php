<?php
require_once 'config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if ID parameter is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID is required']);
    exit();
}

// Sanitize the ID parameter
$assessment_id = intval($_GET['id']);

// Query to get assessment details
$sql = "SELECT 
            u.name,
            u.email,
            u.educationalAttainment,
            u.specialization,
            u.designation,
            u.department,
            u.yearsInLSPU,
            u.teaching_status,
            a.training_history,
            a.desired_skills,
            a.comments,
            a.submission_date
        FROM users u
        JOIN assessments a ON u.id = a.user_id
        WHERE a.id = ?";

// Prepare and execute the query
$stmt = $con->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $con->error]);
    exit();
}

$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if assessment exists
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found']);
    exit();
}

// Fetch assessment data
$assessment = $result->fetch_assoc();

// Parse training_history JSON
$training_history = [];
if (!empty($assessment['training_history'])) {
    $training_history = json_decode($assessment['training_history'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $training_history = [];
    }
}

// Prepare response data
$response = [
    'success' => true,
    'assessment' => [
        'name' => $assessment['name'] ?? '',
        'email' => $assessment['email'] ?? '',
        'educationalAttainment' => $assessment['educationalAttainment'] ?? '',
        'specialization' => $assessment['specialization'] ?? '',
        'designation' => $assessment['designation'] ?? '',
        'department' => $assessment['department'] ?? '',
        'yearsInLSPU' => $assessment['yearsInLSPU'] ?? '',
        'teaching_status' => $assessment['teaching_status'] ?? '',
        'training_history' => $training_history,
        'desired_skills' => $assessment['desired_skills'] ?? '',
        'comments' => $assessment['comments'] ?? '',
        'submission_date' => $assessment['submission_date'] ?? ''
    ]
];

// Return JSON response
echo json_encode($response);

// Close connections
$stmt->close();
$con->close();
?>