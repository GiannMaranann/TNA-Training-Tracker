<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([]);
    exit();
}

// Get user ID from query parameter
if (!isset($_GET['user_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([]);
    exit();
}

$user_id = intval($_GET['user_id']);

// Get IDP forms for the specified user
$stmt = $con->prepare("SELECT id, form_data, submitted_at FROM idp_forms WHERE user_id = ? AND status = 'submitted' ORDER BY submitted_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$forms = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json');
echo json_encode($forms);