<?php
session_start();
header('Content-Type: application/json');

// DB connection
$conn = new mysqli("localhost", "root", "", "user_db");
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Check session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "User not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Query user profile data from users table
$stmt = $conn->prepare("SELECT name, educationalAttainment, specialization, designation, department, yearsInLSPU, teaching_status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    // User not found in users table (should not happen if logged in)
    echo json_encode(["error" => "User not found"]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->bind_result($name, $educ, $spec, $desig, $dept, $years, $teach);
$stmt->fetch();
$stmt->close();
$conn->close();

// Check if profile exists by checking if critical fields are non-empty
// You can customize which fields define a "profile"
// Here I assume "name" and "educationalAttainment" must not be empty
$hasProfile = false;
if (!empty(trim($name)) && !empty(trim($educ))) {
    $hasProfile = true;
}

echo json_encode(['hasProfile' => $hasProfile]);
?>
