<?php
session_start();
header('Content-Type: application/json');

// DB connection
$conn = new mysqli("localhost", "root", "", "user_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

// Check session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Query user profile data from users table
    $stmt = $conn->prepare("SELECT name, educationalAttainment, specialization, designation, department, yearsInLSPU, teaching_status FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        // User not found in users table (should not happen if logged in)
        echo json_encode(["success" => false, "error" => "User not found"]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $stmt->bind_result($name, $educ, $spec, $desig, $dept, $years, $teach);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    // Check if profile exists by checking if critical fields are non-empty
    $hasProfile = (!empty(trim($name ?? ''))) && (!empty(trim($educ ?? '')));
    
    echo json_encode([
        'success' => true,
        'hasProfile' => $hasProfile,
        'profileData' => [
            'name' => $name,
            'educationalAttainment' => $educ,
            'specialization' => $spec,
            'designation' => $desig,
            'department' => $dept,
            'yearsInLSPU' => $years,
            'teaching_status' => $teach
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
    if (isset($conn) && $conn) {
        $conn->close();
    }
    exit;
}
?>