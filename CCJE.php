<?php
session_start();

// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

// Database connection
require_once 'config.php';

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $con->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: homepage.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: homepage.php");
    exit();
}

// Get current year for evaluation tracking
$current_year = date('Y');

// Initialize arrays to prevent undefined variable warnings
$stats = [];
$unevaluated_employees = [];
$recent_evaluations = [];
$evaluation_details = null;
$evaluation_ratings = [];
$workflow_history = [];
$evaluation_stats = [];
$error_message = '';
$show_evaluation_modal = false;

// Default values for stats
$stats = [
    'total_employees' => 0,
    'teaching' => 0,
    'non_teaching' => 0,
    'evaluated_this_year' => 0,
    'pending_evaluations' => 0,
    'progress_percentage' => 0
];

// Get CCJE department statistics
try {
    // Total CCJE employees (users with role 'user' AND teaching_status is not null)
    $total_result = $con->query("SELECT COUNT(*) as count FROM users WHERE department = 'CCJE' AND role = 'user' AND teaching_status IS NOT NULL AND teaching_status != ''");
    if ($total_result) {
        $stats['total_employees'] = $total_result->fetch_assoc()['count'];
    }
    
    // Teaching staff - Using flexible query to handle different cases
    $teaching_result = $con->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE department = 'CCJE' 
        AND role = 'user' 
        AND (teaching_status = 'Teaching' OR teaching_status = 'teaching' OR LOWER(teaching_status) LIKE '%teaching%' AND LOWER(teaching_status) NOT LIKE '%non%')
    ");
    if ($teaching_result) {
        $stats['teaching'] = $teaching_result->fetch_assoc()['count'];
    }
    
    // Non-teaching staff - Using flexible query
    $non_teaching_result = $con->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE department = 'CCJE' 
        AND role = 'user' 
        AND (teaching_status = 'Non-Teaching' OR teaching_status = 'Non Teaching' OR teaching_status = 'non-teaching' OR LOWER(teaching_status) LIKE '%non%teaching%')
    ");
    if ($non_teaching_result) {
        $stats['non_teaching'] = $non_teaching_result->fetch_assoc()['count'];
    }
    
    // Alternative approach: If still zero, get all and categorize
    if ($stats['teaching'] == 0 && $stats['non_teaching'] == 0) {
        $all_result = $con->query("
            SELECT teaching_status, COUNT(*) as count 
            FROM users 
            WHERE department = 'CCJE' 
            AND role = 'user' 
            AND teaching_status IS NOT NULL 
            AND teaching_status != '' 
            GROUP BY teaching_status
        ");
        
        if ($all_result) {
            while ($row = $all_result->fetch_assoc()) {
                $status = strtolower($row['teaching_status']);
                if (strpos($status, 'non') !== false || strpos($status, 'non-teaching') !== false || strpos($status, 'non teaching') !== false) {
                    $stats['non_teaching'] += $row['count'];
                } else {
                    $stats['teaching'] += $row['count'];
                }
            }
        }
    }
    
    // Get evaluation statistics
    $evaluation_stats_result = $con->query("
        SELECT 
            COUNT(*) as total_evaluations,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM evaluations
    ");
    
    if ($evaluation_stats_result) {
        $evaluation_stats = $evaluation_stats_result->fetch_assoc();
    } else {
        $evaluation_stats = [
            'total_evaluations' => 0, 
            'approved' => 0, 
            'submitted' => 0, 
            'draft' => 0, 
            'rejected' => 0
        ];
    }
    
    // Evaluated employees (have at least one evaluation record)
    $evaluated_result = $con->query("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM evaluations 
        WHERE YEAR(created_at) = '$current_year'
    ");
    if ($evaluated_result) {
        $stats['evaluated_this_year'] = $evaluated_result->fetch_assoc()['count'];
    }
    
    // Pending evaluations (employees without any evaluation record)
    $pending_result = $con->query("
        SELECT COUNT(*) as count 
        FROM users u 
        WHERE u.department = 'CCJE' 
        AND u.role = 'user'
        AND u.teaching_status IS NOT NULL 
        AND u.teaching_status != ''
        AND NOT EXISTS (
            SELECT 1 FROM evaluations e WHERE e.user_id = u.id
        )
    ");
    if ($pending_result) {
        $stats['pending_evaluations'] = $pending_result->fetch_assoc()['count'];
    }

    // Calculate progress percentage
    $stats['progress_percentage'] = $stats['total_employees'] > 0 ? 
        round(($stats['evaluated_this_year'] / $stats['total_employees']) * 100) : 0;

    // Get unevaluated employees (CCJE users without any evaluation record)
    $unevaluated_result = $con->query("
        SELECT id, name, teaching_status, department 
        FROM users 
        WHERE department = 'CCJE' 
        AND role = 'user'
        AND teaching_status IS NOT NULL 
        AND teaching_status != ''
        AND NOT EXISTS (
            SELECT 1 FROM evaluations WHERE user_id = users.id
        )
        ORDER BY name ASC
    ");
    
    if ($unevaluated_result && $unevaluated_result->num_rows > 0) {
        while ($row = $unevaluated_result->fetch_assoc()) {
            $unevaluated_employees[] = $row;
        }
    }

    // Get recent evaluations with status information including department
    $recent_result = $con->query("
        SELECT 
            u.id,
            u.name,
            u.department,
            u.teaching_status,
            e.id as evaluation_id,
            e.status as evaluation_status,
            e.created_at
        FROM evaluations e
        JOIN users u ON e.user_id = u.id
        WHERE u.department = 'CCJE'
        ORDER BY e.created_at DESC 
        LIMIT 5
    ");
    
    if ($recent_result && $recent_result->num_rows > 0) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_evaluations[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Database error in CCJE.php: " . $e->getMessage());
    $error_message = "An error occurred while loading dashboard data. Please try again later.";
}

// Handle sending evaluation to HR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_evaluation'])) {
    $evaluation_id = $_POST['evaluation_id'];
    
    try {
        // Update evaluation status to submitted
        $stmt = $con->prepare("UPDATE evaluations SET status = 'submitted', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $evaluation_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Add to workflow history
            $workflow_sql = "INSERT INTO evaluation_workflow (evaluation_id, from_status, to_status, changed_by) 
                             VALUES (?, 'draft', 'submitted', ?)";
            $workflow_stmt = $con->prepare($workflow_sql);
            $workflow_stmt->bind_param("ii", $evaluation_id, $user_id);
            $workflow_stmt->execute();
            
            $_SESSION['success_message'] = "Evaluation submitted to HR successfully!";
            header("Location: CCJE.php?success=1");
            exit();
        } else {
            $_SESSION['error_message'] = "Failed to update evaluation status. The evaluation may have already been submitted.";
            header("Location: CCJE.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Error sending evaluation: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error: Unable to submit evaluation. Please try again.";
        header("Location: CCJE.php");
        exit();
    }
}

// Handle viewing evaluation form in modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_evaluation'])) {
    $evaluation_id = $_POST['evaluation_id'];
    
    try {
        // Get evaluation data
        $evaluation_sql = "SELECT 
            e.*,
            u.name as employee_name,
            u.department as employee_department,
            evaluator.name as evaluator_name
        FROM evaluations e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN users evaluator ON e.evaluator_id = evaluator.id
        WHERE e.id = ?";

        $stmt = $con->prepare($evaluation_sql);
        $stmt->bind_param("i", $evaluation_id);
        $stmt->execute();
        $evaluation_result = $stmt->get_result();

        if ($evaluation_result->num_rows > 0) {
            $evaluation_details = $evaluation_result->fetch_assoc();

            // Get evaluation ratings
            $ratings_sql = "SELECT * FROM evaluation_ratings WHERE evaluation_id = ? ORDER BY question_number";
            $ratings_stmt = $con->prepare($ratings_sql);
            $ratings_stmt->bind_param("i", $evaluation_id);
            $ratings_stmt->execute();
            $ratings_result = $ratings_stmt->get_result();

            while ($rating = $ratings_result->fetch_assoc()) {
                $evaluation_ratings[$rating['question_number']] = $rating;
            }

            // Get workflow history
            $workflow_sql = "SELECT 
                wf.*,
                u.name as changed_by_name
            FROM evaluation_workflow wf
            JOIN users u ON wf.changed_by = u.id
            WHERE wf.evaluation_id = ?
            ORDER BY wf.created_at ASC";

            $workflow_stmt = $con->prepare($workflow_sql);
            $workflow_stmt->bind_param("i", $evaluation_id);
            $workflow_stmt->execute();
            $workflow_result = $workflow_stmt->get_result();

            while ($history = $workflow_result->fetch_assoc()) {
                $workflow_history[] = $history;
            }

            // Set flag to show modal
            $show_evaluation_modal = true;
        } else {
            $error_message = "Evaluation not found or you don't have permission to view it.";
        }
    } catch (Exception $e) {
        error_log("Error viewing evaluation: " . $e->getMessage());
        $error_message = "Database error: Unable to load evaluation details.";
    }
}

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get real-time notifications for CCJE
$notifications = [];
try {
    // Recent evaluations for CCJE
    $notification_result = $con->query("
        SELECT 
            'evaluation' as type,
            CONCAT('New evaluation submitted for ', u.name) as message,
            e.created_at as timestamp
        FROM evaluations e
        JOIN users u ON e.user_id = u.id
        WHERE u.department = 'CCJE'
        ORDER BY e.created_at DESC
        LIMIT 3
    ");
    
    if ($notification_result && $notification_result->num_rows > 0) {
        while ($row = $notification_result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    
    // Pending evaluations count for CCJE
    $pending_count = $con->query("
        SELECT COUNT(*) as count 
        FROM users u 
        WHERE u.department = 'CCJE' 
        AND u.role = 'user'
        AND u.teaching_status IS NOT NULL 
        AND u.teaching_status != ''
        AND NOT EXISTS (
            SELECT 1 FROM evaluations e WHERE e.user_id = u.id
        )
    ");
    
    if ($pending_count) {
        $pending_row = $pending_count->fetch_assoc();
        if ($pending_row['count'] > 0) {
            $notifications[] = [
                'type' => 'pending',
                'message' => "You have {$pending_row['count']} pending evaluations in CCJE",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Recent system activities
    $activities_result = $con->query("
        SELECT 
            'system' as type,
            CONCAT('System update: ', description) as message,
            created_at as timestamp
        FROM system_activities
        ORDER BY created_at DESC
        LIMIT 2
    ");
    
    if ($activities_result && $activities_result->num_rows > 0) {
        while ($row = $activities_result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCJE Admin Dashboard - LSPU</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        /* Enhanced Card Animations */
        .card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            box-shadow: 0 10px 30px rgba(75, 85, 99, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            transform-style: preserve-3d;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(135deg, rgba(75, 85, 99, 0.05) 0%, rgba(107, 114, 128, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            border-radius: 20px;
            z-index: 1;
        }

        .card:hover {
            transform: translateY(-12px) scale(1.02) rotateX(2deg) rotateY(1deg);
            box-shadow: 0 25px 50px rgba(75, 85, 99, 0.25);
        }

        .card:hover::before {
            opacity: 1;
        }

        .card-content {
            position: relative;
            z-index: 2;
        }
        
        /* Enhanced Sidebar Link Animations - GRAY AND BLACK THEME */
        .sidebar-link {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 15px;
            margin: 4px 0;
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .sidebar-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .sidebar-link:hover::before {
            left: 100%;
        }

        .sidebar-link:hover {
            background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%);
            transform: translateX(8px) scale(1.02);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(75, 85, 99, 0.3);
        }

        .sidebar-link.active {
            background: linear-gradient(135deg, #000000 0%, #374151 100%);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateX(5px);
        }
        
        /* Enhanced Stat Card Animations */
        .stat-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%);
            color: white;
            transform-style: preserve-3d;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #000000, #1f2937);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.8s ease;
        }

        .stat-card:hover::after {
            left: 100%;
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #000000 0%, #1f2937 100%);
        }
        
        /* Enhanced Floating Animation */
        .floating {
            animation: floatingEnhanced 4s ease-in-out infinite;
        }

        @keyframes floatingEnhanced {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg) scale(1);
            }
            50% { 
                transform: translateY(-15px) rotate(2deg) scale(1.02);
            }
        }
        
        /* Enhanced Pulse Animation */
        .pulse {
            animation: pulseEnhanced 3s infinite;
        }

        @keyframes pulseEnhanced {
            0% { 
                transform: scale(1); 
                box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.7);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 15px rgba(0, 0, 0, 0.3);
            }
            100% { 
                transform: scale(1); 
                box-shadow: 0 0 0 0 rgba(0, 0, 0, 0);
            }
        }
        
        .lspu-header {
            background: linear-gradient(135deg, 
                rgba(75, 85, 99, 0.95) 0%, 
                rgba(55, 65, 81, 0.95) 50%, 
                rgba(31, 41, 55, 0.95) 100%);
            backdrop-filter: blur(20px);
            border-bottom: 4px solid #000000;
            position: relative;
            overflow: hidden;
        }
        
        .lspu-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23000000' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        
        /* Enhanced Logo Animations */
        .logo-container {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .logo-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .logo-container:hover::before {
            transform: translateX(100%);
        }

        .logo-container:hover {
            transform: scale(1.1) rotate(2deg);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }
        
        .accent-black {
            color: #000000;
        }
        
        .bg-accent-black {
            background-color: #000000;
        }
        
        .text-accent-black {
            color: #000000;
        }
        
        .border-accent-black {
            border-color: #000000;
        }

        /* Compact Sidebar Styles */
        .sidebar-container {
            width: 260px;
            min-width: 260px;
            max-width: 260px;
            flex-shrink: 0;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
        }

        .sidebar-container::-webkit-scrollbar {
            display: none;
        }

        .sidebar-container {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            overflow-x: hidden;
        }

        .sidebar-content {
            padding: 1rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .navigation-section {
            flex: 1;
        }

        .user-section {
            flex-shrink: 0;
        }

        /* Prevent horizontal scroll */
        .no-horizontal-scroll {
            max-width: 100vw;
            overflow-x: hidden;
        }
        
        /* Campus-wide theme colors */
        .campus-primary {
            background-color: #4b5563;
        }
        
        .campus-secondary {
            background-color: #6b7280;
        }
        
        .campus-accent {
            background-color: #000000;
        }
        
        .text-campus-primary {
            color: #4b5563;
        }
        
        .text-campus-secondary {
            color: #6b7280;
        }
        
        .text-campus-accent {
            color: #000000;
        }
        
        /* CCJE-specific styling */
        .ccje-gradient {
            background: linear-gradient(135deg, #4b5563 0%, #6b7280 50%, #9ca3af 100%);
        }
        
        .ccje-card {
            border-left: 4px solid #4b5563;
        }
        
        .ccje-highlight {
            background: linear-gradient(135deg, #000000 0%, #374151 100%);
            color: white;
        }

        /* Enhanced Notification Bell Animation */
        .notification-bell {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .notification-bell:hover {
            transform: scale(1.1) rotate(15deg);
            color: #9ca3af;
        }

        .notification-bell::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(156, 163, 175, 0.1);
            transform: scale(0);
            transition: transform 0.4s ease;
        }

        .notification-bell:hover::after {
            transform: scale(1.5);
        }

        /* Enhanced Evaluation Item Animations */
        .evaluation-item {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .evaluation-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #000000, #374151);
            transform: scaleY(0);
            transform-origin: top;
            transition: transform 0.4s ease;
        }

        .evaluation-item:hover::before {
            transform: scaleY(1);
        }

        .evaluation-item:hover {
            transform: translateX(8px) scale(1.01);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .animate-fade-in {
            animation: fadeIn 0.6s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced Status Badge Animations */
        .status-badge {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .status-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .status-badge:hover::before {
            left: 100%;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced Button Animations */
        .btn-animated {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-animated::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .btn-animated:hover::before {
            width: 300px;
            height: 300px;
        }

        /* Enhanced Modal Animations */
        .modal-backdrop {
            backdrop-filter: blur(10px);
            background: rgba(0, 0, 0, 0.7);
            animation: backdropFadeIn 0.3s ease forwards;
            opacity: 0;
        }

        @keyframes backdropFadeIn {
            to {
                opacity: 1;
            }
        }

        .modal-container {
            max-height: 90vh;
            overflow-y: auto;
            width: 95%;
            max-width: 1200px;
            animation: modalSlideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            transform: translateY(-50px) scale(0.9);
            opacity: 0;
        }

        @keyframes modalSlideIn {
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .status-draft {
            background-color: #f3f4f6;
            color: #374151;
        }

        .status-submitted {
            background-color: #e5e7eb;
            color: #374151;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .workflow-timeline {
            border-left: 2px solid #e5e7eb;
            margin-left: 10px;
        }

        .workflow-step {
            position: relative;
            padding-left: 20px;
            margin-bottom: 1rem;
        }

        .workflow-step::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 6px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #6b7280;
        }

        .signature-preview {
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signature-image {
            max-width: 100%;
            max-height: 70px;
        }

        /* Progress Bar Styles */
        .progress-bar-container {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            height: 8px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #000000 0%, #374151 100%);
            border-radius: 50px;
            transition: width 1s ease-in-out;
        }

        /* Evaluation Grid Layout */
        .evaluation-grid {
            display: grid;
            gap: 0.75rem;
        }

        .evaluation-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .evaluation-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .evaluation-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .evaluation-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.95rem;
        }

        .evaluation-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: #6b7280;
        }

        .evaluation-meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .evaluation-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        /* Sliding Line Animation - BLACK */
        .sliding-line {
            position: relative;
            overflow: hidden;
        }

        .sliding-line::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: -100%;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #000000, transparent);
            transition: left 0.8s ease;
        }

        .sliding-line:hover::after {
            left: 100%;
        }

        .sliding-line.active::after {
            left: 0;
            background: linear-gradient(90deg, #000000, #374151, #000000);
            animation: slideLine 2s infinite;
        }

        @keyframes slideLine {
            0% {
                left: -100%;
            }
            50% {
                left: 0;
            }
            100% {
                left: 100%;
            }
        }

        /* Real-time notification styles */
        .notification-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .notification-item:hover {
            border-left-color: #000000;
            background-color: #f9fafb;
        }

        .notification-new {
            background-color: #f9fafb;
            border-left-color: #000000;
        }
    </style>
</head>
<body class="min-h-screen bg-white no-horizontal-scroll <?php echo $show_evaluation_modal ? 'overflow-hidden' : ''; ?>">
    <div class="flex h-screen">
        <!-- Sidebar - GRAY AND BLACK THEME -->
        <aside class="sidebar-container ccje-gradient border-r border-gray-600">
            <div class="sidebar-content">
                <!-- LSPU Header -->
                <div class="lspu-header p-3 text-white mb-3 rounded-xl">
                    <div class="text-center relative z-10">
                        <!-- Logo Container -->
                        <div class="flex items-center justify-center space-x-3 mb-3">
                            <!-- LSPU Logo -->
                            <div class="logo-container">
                                <img src="images/lspu-logo.png" alt="LSPU Logo" class="w-12 h-12 rounded-lg bg-white p-1" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm" style="display: none;">
                                    <i class="ri-government-line text-white text-lg"></i>
                                </div>
                            </div>
                            <!-- CCJE Logo -->
                            <div class="logo-container">
                                <img src="images/ccje-logo.png" alt="CCJE Logo" class="w-12 h-12 rounded-lg bg-white p-1"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm" style="display: none;">
                                    <i class="ri-shield-user-line text-white text-lg"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- University Header -->
                        <div class="border-t border-white/30 pt-2">
                            <h2 class="text-xs font-semibold uppercase tracking-wider">Republic of the Philippines</h2>
                            <h1 class="text-sm font-bold mt-1 tracking-tight">LAGUNA STATE POLYTECHNIC UNIVERSITY</h1>
                            
                            <!-- College of Criminal Justice Education -->
                            <div class="mt-2 pt-2 border-t border-white/30">
                                <h3 class="text-sm font-bold uppercase tracking-wide">COLLEGE OF CRIMINAL JUSTICE EDUCATION</h3>
                                <p class="text-xs opacity-80 mt-1 font-semibold text-accent-black">A.Y. 2024-2025</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="navigation-section mb-3">
                    <nav class="mb-3">
                        <div class="space-y-1">
                            <a href="CCJE.php" class="flex items-center px-3 py-2 text-white font-semibold rounded-xl sidebar-link active sliding-line active">
                                <i class="ri-dashboard-line mr-2 text-base"></i>
                                <span class="text-sm">Dashboard</span>
                                <i class="ri-arrow-right-s-line ml-auto text-base"></i>
                            </a>
                            <a href="ccje_eval.php" class="flex items-center px-3 py-2 text-white/90 font-semibold rounded-xl sidebar-link sliding-line">
                                <i class="ri-file-list-3-line mr-2 text-base"></i>
                                <span class="text-sm">Evaluation</span>
                            </a>
                        </div>
                    </nav>

                    <!-- Stats Overview in Sidebar -->
                    <div class="p-3 bg-white/10 rounded-xl backdrop-blur-sm border border-white/20 sliding-line">
                        <h3 class="text-white font-bold mb-2 flex items-center text-sm">
                            <i class="ri-bar-chart-line mr-1"></i>Quick Stats
                        </h3>
                        <div class="space-y-1">
                            <div class="flex justify-between items-center text-white/90 text-xs font-medium">
                                <span>Total Faculty</span>
                                <span class="font-bold text-white text-sm"><?= $stats['total_employees'] ?></span>
                            </div>
                            <div class="flex justify-between items-center text-white/90 text-xs font-medium">
                                <span>Evaluated</span>
                                <span class="font-bold text-green-300 text-sm"><?= $stats['evaluated_this_year'] ?></span>
                            </div>
                            <div class="flex justify-between items-center text-white/90 text-xs font-medium">
                                <span>Pending</span>
                                <span class="font-bold text-yellow-300 text-sm"><?= $stats['pending_evaluations'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logout Only -->
                <div class="user-section">
                    <a href="?logout=true" class="flex items-center justify-center px-3 py-2 text-white/90 font-semibold rounded-xl sidebar-link hover:bg-red-500/30 border border-red-500/30 bg-red-500/20 sliding-line">
                        <i class="ri-logout-box-line mr-2 text-base"></i>
                        <span class="text-sm">Sign Out</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="bg-gradient-to-r from-gray-800 to-gray-700 border-b border-gray-600">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold text-white truncate">Welcome back, <?= htmlspecialchars($user['name'] ?? 'CCJE Admin') ?>! 👋</h1>
                        <p class="text-white/70 text-sm mt-1 truncate">College of Criminal Justice Education - Faculty Evaluation Dashboard</p>
                    </div>
                    <div class="flex items-center space-x-4 flex-shrink-0">
                        <!-- Notification Bell -->
                        <div class="relative">
                            <button class="notification-bell text-white hover:text-gray-200 transition-colors" onclick="showNotifications()">
                                <i class="ri-notification-3-line text-2xl"></i>
                                <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                            </button>
                        </div>
                        
                        <div class="text-right">
                            <p class="text-white/80 text-xs font-semibold">Academic Year</p>
                            <p class="text-white font-bold text-lg">2024-2025</p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm floating border border-white/30">
                            <i class="ri-calendar-2-line text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 bg-gray-50 min-h-full">
                <!-- Success/Error Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="mb-6 glass-card p-4 border-l-4 border-green-500 animate-fade-in">
                        <div class="flex items-center">
                            <i class="ri-checkbox-circle-line text-green-500 text-xl mr-3"></i>
                            <span class="text-gray-800 text-base font-medium"><?= $success_message ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="mb-6 glass-card p-4 border-l-4 border-red-500 animate-fade-in">
                        <div class="flex items-center">
                            <i class="ri-error-warning-line text-red-500 text-xl mr-3"></i>
                            <span class="text-gray-800 text-base font-medium"><?= $error_message ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Total Employees -->
                    <div class="card stat-card pulse sliding-line">
                        <div class="p-4 card-content">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-white/80 text-xs font-medium">Total Faculty</p>
                                    <h3 class="text-2xl font-bold text-white mt-1"><?= $stats['total_employees'] ?></h3>
                                    <p class="text-white/70 text-xs mt-1 truncate">College of Criminal Justice Education</p>
                                </div>
                                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm shadow-lg floating flex-shrink-0 ml-3">
                                    <i class="fas fa-users text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Teaching Staff -->
                    <div class="card stat-card sliding-line">
                        <div class="p-4 card-content">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-white/80 text-xs font-medium">Teaching Staff</p>
                                    <h3 class="text-2xl font-bold text-white mt-1"><?= $stats['teaching'] ?></h3>
                                    <p class="text-white/90 text-xs mt-1 font-semibold">
                                        <i class="ri-arrow-up-line"></i>
                                        <?= $stats['total_employees'] > 0 ? round(($stats['teaching'] / $stats['total_employees']) * 100) : 0 ?>%
                                    </p>
                                </div>
                                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm shadow-lg floating flex-shrink-0 ml-3">
                                    <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Non-Teaching Staff -->
                    <div class="card stat-card sliding-line">
                        <div class="p-4 card-content">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-white/80 text-xs font-medium">Non-Teaching Staff</p>
                                    <h3 class="text-2xl font-bold text-white mt-1"><?= $stats['non_teaching'] ?></h3>
                                    <p class="text-white/90 text-xs mt-1 font-semibold">
                                        <i class="ri-arrow-up-line"></i>
                                        <?= $stats['total_employees'] > 0 ? round(($stats['non_teaching'] / $stats['total_employees']) * 100) : 0 ?>%
                                    </p>
                                </div>
                                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm shadow-lg floating flex-shrink-0 ml-3">
                                    <i class="fas fa-user-tie text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Evaluation Progress -->
                    <div class="card stat-card sliding-line">
                        <div class="p-4 card-content">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-white/80 text-xs font-medium">Evaluation Progress</p>
                                    <h3 class="text-2xl font-bold text-white mt-1"><?= $stats['progress_percentage'] ?>%</h3>
                                    <p class="text-white/70 text-xs mt-1">
                                        <?= $stats['evaluated_this_year'] ?> of <?= $stats['total_employees'] ?> completed
                                    </p>
                                    <!-- Progress Bar -->
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $stats['progress_percentage'] ?>%"></div>
                                    </div>
                                </div>
                                <div class="relative flex-shrink-0 ml-3">
                                    <svg class="w-16 h-16" viewBox="0 0 36 36">
                                        <path class="text-white/30"
                                            d="M18 2.0845
                                            a 15.9155 15.9155 0 0 1 0 31.831
                                            a 15.9155 15.9155 0 0 1 0 -31.831"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        />
                                        <path class="text-white"
                                            d="M18 2.0845
                                            a 15.9155 15.9155 0 0 1 0 31.831
                                            a 15.9155 15.9155 0 0 1 0 -31.831"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="3"
                                            stroke-dasharray="<?= $stats['progress_percentage'] ?>, 100"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i class="fas fa-tasks text-white text-lg"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Detailed Stats -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Evaluation Progress Chart -->
                    <div class="card sliding-line">
                        <div class="p-4 card-content">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="ri-progress-4-line mr-2 text-gray-700 text-xl"></i>
                                Evaluation Progress Overview
                            </h3>
                            <div class="h-64">
                                <canvas id="progressChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Faculty Distribution -->
                    <div class="card sliding-line">
                        <div class="p-4 card-content">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="ri-pie-chart-line mr-2 text-gray-700 text-xl"></i>
                                Faculty Distribution
                            </h3>
                            <div class="h-64">
                                <canvas id="distributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Pending Evaluations -->
                    <div class="card sliding-line">
                        <div class="p-4 card-content">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                    <i class="ri-time-line mr-2 text-gray-600 text-xl"></i>
                                    Pending Evaluations
                                </h3>
                                <span class="bg-gray-100 text-gray-800 text-xs font-bold px-2 py-1 rounded-full border border-gray-300 status-badge">
                                    <?= count($unevaluated_employees) ?> pending
                                </span>
                            </div>
                            
                            <?php if (!empty($unevaluated_employees)): ?>
                                <div class="evaluation-grid">
                                    <?php foreach (array_slice($unevaluated_employees, 0, 5) as $employee): ?>
                                        <div class="evaluation-row sliding-line">
                                            <div class="evaluation-info">
                                                <div class="evaluation-name"><?= htmlspecialchars($employee['name']) ?></div>
                                                <div class="evaluation-meta">
                                                    <span class="evaluation-meta-item">
                                                        <i class="fas fa-building text-gray-600"></i>
                                                        <?= htmlspecialchars($employee['department']) ?>
                                                    </span>
                                                    <span class="evaluation-meta-item">
                                                        <?php if ($employee['teaching_status'] === 'Teaching'): ?>
                                                            <i class="fas fa-chalkboard-teacher text-green-500"></i>
                                                            Teaching
                                                        <?php else: ?>
                                                            <i class="fas fa-user-tie text-gray-500"></i>
                                                            Non-Teaching
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="evaluation-actions">
                                                <a href="ccje_eval.php?user_id=<?= $employee['id'] ?>" 
                                                   class="btn-animated bg-gradient-to-r from-gray-600 to-gray-700 text-white px-4 py-2 rounded-lg text-xs font-bold hover:shadow-lg transition-all duration-300 transform hover:scale-105 shadow-md">
                                                    Evaluate Now
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($unevaluated_employees) > 5): ?>
                                    <div class="mt-4 text-center">
                                        <a href="ccje_eval.php" class="btn-animated text-gray-700 hover:text-gray-900 text-sm font-bold inline-flex items-center border-2 border-gray-700 px-4 py-2 rounded-lg hover:bg-gray-700 hover:text-white transition-all duration-300">
                                            View all <?= count($unevaluated_employees) ?> pending evaluations
                                            <i class="ri-arrow-right-line ml-1 text-lg"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <i class="fas fa-check text-white text-xl"></i>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-800 mb-2">All Caught Up! 🎉</h4>
                                    <p class="text-gray-500 text-sm">All evaluations are completed for A.Y. <?= $current_year ?>-<?= $current_year + 1 ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Evaluations -->
                    <div class="card sliding-line">
                        <div class="p-4 card-content">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                    <i class="ri-history-line mr-2 text-gray-700 text-xl"></i>
                                    Recent Evaluations
                                </h3>
                                <span class="bg-gray-100 text-gray-800 text-xs font-bold px-2 py-1 rounded-full border border-gray-300 status-badge">
                                    <?= count($recent_evaluations) ?> recent
                                </span>
                            </div>
                            
                            <?php if (!empty($recent_evaluations)): ?>
                                <div class="evaluation-grid">
                                    <?php foreach ($recent_evaluations as $evaluation): 
                                        $status_config = [
                                            'approved' => ['class' => 'status-approved', 'icon' => 'fa-check-circle'],
                                            'submitted' => ['class' => 'status-submitted', 'icon' => 'fa-paper-plane'],
                                            'draft' => ['class' => 'status-draft', 'icon' => 'fa-edit'],
                                            'rejected' => ['class' => 'status-rejected', 'icon' => 'fa-times-circle']
                                        ];
                                        $status = $status_config[$evaluation['evaluation_status']] ?? $status_config['draft'];
                                    ?>
                                        <div class="evaluation-row sliding-line">
                                            <div class="evaluation-info">
                                                <div class="evaluation-name"><?= htmlspecialchars($evaluation['name']) ?></div>
                                                <div class="evaluation-meta">
                                                    <span class="evaluation-meta-item">
                                                        <i class="fas fa-building text-gray-600"></i>
                                                        <?= htmlspecialchars($evaluation['department']) ?>
                                                    </span>
                                                    <span class="evaluation-meta-item">
                                                        <?php if ($evaluation['teaching_status'] === 'Teaching'): ?>
                                                            <i class="fas fa-chalkboard-teacher text-green-500"></i>
                                                            Teaching
                                                        <?php else: ?>
                                                            <i class="fas fa-user-tie text-gray-500"></i>
                                                            Non-Teaching
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="evaluation-meta-item">
                                                        <i class="fas fa-calendar text-gray-400"></i>
                                                        <?= date('M d, Y', strtotime($evaluation['created_at'])) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="evaluation-actions">
                                                <span class="status-badge <?= $status['class'] ?>">
                                                    <i class="fas <?= $status['icon'] ?> mr-1"></i>
                                                    <?= ucfirst($evaluation['evaluation_status']) ?>
                                                </span>
                                                <div class="flex gap-2">
                                                    <?php if ($evaluation['evaluation_status'] === 'draft'): ?>
                                                    <form method="POST" action="" class="m-0">
                                                        <input type="hidden" name="evaluation_id" value="<?= $evaluation['evaluation_id'] ?>">
                                                        <button type="submit" name="send_evaluation" 
                                                                class="btn-animated bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-1.5 rounded text-xs font-bold hover:shadow-md transition-all duration-300"
                                                                onclick="return confirm('Submit this evaluation to HR for review?')">
                                                            Submit
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" action="" class="m-0">
                                                        <input type="hidden" name="evaluation_id" value="<?= $evaluation['evaluation_id'] ?>">
                                                        <button type="submit" name="view_evaluation" 
                                                                class="btn-animated bg-gradient-to-r from-gray-700 to-gray-800 text-white px-3 py-1.5 rounded text-xs font-bold hover:shadow-md transition-all duration-300">
                                                            View
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-gradient-to-br from-gray-400 to-gray-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                                        <i class="fas fa-file-alt text-white text-xl"></i>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-800 mb-2">No Evaluations Yet</h4>
                                    <p class="text-gray-500 text-sm">Completed evaluations will appear here</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notificationModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop" onclick="hideNotifications()"></div>
        <div class="modal-container bg-white rounded-2xl shadow-2xl absolute top-4 right-4 w-96 max-w-sm">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800">Notifications</h3>
                    <button onclick="hideNotifications()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="max-h-96 overflow-y-auto" id="notificationList">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                            <div class="flex items-start space-x-3">
                                <?php if ($notification['type'] === 'evaluation'): ?>
                                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="ri-file-list-3-line text-gray-600 text-sm"></i>
                                    </div>
                                <?php elseif ($notification['type'] === 'pending'): ?>
                                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="ri-time-line text-amber-600 text-sm"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="ri-information-line text-gray-600 text-sm"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($notification['message']) ?></p>
                                    <p class="text-xs text-gray-400 mt-1"><?= date('M d, Y h:i A', strtotime($notification['timestamp'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="ri-notification-off-line text-gray-400 text-xl"></i>
                        </div>
                        <p class="text-gray-500 text-sm">No notifications</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="p-4 border-t border-gray-200">
                <button class="w-full py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium" onclick="markAllAsRead()">
                    Mark all as read
                </button>
            </div>
        </div>
    </div>

    <!-- Evaluation View Modal -->
    <?php if ($show_evaluation_modal && $evaluation_details): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-2xl shadow-2xl modal-container w-full max-w-6xl">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-gray-800 to-gray-700 text-white p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold">TRAINING PROGRAM IMPACT ASSESSMENT FORM</h2>
                        <div class="flex items-center space-x-4 mt-2">
                            <span class="status-badge status-<?= $evaluation_details['status'] ?>">
                                <?= ucfirst($evaluation_details['status']) ?>
                            </span>
                            <span class="text-sm text-gray-100">
                                Created: <?= date('M d, Y h:i A', strtotime($evaluation_details['created_at'])) ?>
                            </span>
                            <?php if ($evaluation_details['updated_at'] != $evaluation_details['created_at']): ?>
                            <span class="text-sm text-gray-100">
                                Updated: <?= date('M d, Y h:i A', strtotime($evaluation_details['updated_at'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="printForm()" class="btn-animated px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors">
                            <i class="ri-printer-line mr-2"></i>Print
                        </button>
                        <button onclick="closeModal()" class="btn-animated px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                            <i class="ri-close-line mr-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name of Employee:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= htmlspecialchars($evaluation_details['employee_name']) ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department/Unit:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= htmlspecialchars($evaluation_details['employee_department']) ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title of Training/Seminar Attended:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= htmlspecialchars($evaluation_details['training_title']) ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Conducted:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= date('M d, Y', strtotime($evaluation_details['date_conducted'])) ?></div>
                    </div>
                </div>

                <!-- Objectives -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Objective/s:</label>
                    <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200 min-h-[80px]"><?= nl2br(htmlspecialchars($evaluation_details['objectives'])) ?></div>
                </div>

                <!-- Ratings Table -->
                <div class="mb-6">
                    <div class="bg-gray-100 p-3 rounded mb-4">
                        <p class="text-sm text-gray-700"><span class="font-medium">INSTRUCTION:</span> Please check (✓) in the appropriate column the impact/benefits gained by the employee in attending the training program in a scale of 1-5 (where 5 – Strongly Agree; 4 – Agree; 3 – Neither agree nor disagree; 2 – Disagree; 1 – Strongly Disagree)</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left py-3 px-4 font-medium text-gray-700 border border-gray-300 w-1/2">IMPACT/BENEFITS GAINED</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">1</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">2</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">3</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">4</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">5</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-700 border border-gray-300">REMARKS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $questions = [
                                    "1. The employee's performance became more efficient as shown with no/less commitment of mistakes on work.",
                                    "2. The employee enhanced his/her ability to generate ideas and recommendations.",
                                    "3. He/She has developed new system or improved the present system through contributing new ideas.",
                                    "4. The employee's morale has been upgraded.",
                                    "5. The employee has applied new skills in the performance of his/her work.",
                                    "6. The employee became more proud and confident in his/her tasks.",
                                    "7. The employee can now be entrusted higher/greater responsibility.",
                                    "8. He/She transferred the knowledge and skills gained through conduct of workshop or demonstration to co-employee."
                                ];
                                
                                for ($i = 1; $i <= 8; $i++): 
                                    $rating = $evaluation_ratings[$i] ?? null;
                                ?>
                                <tr class="<?= $i % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?>">
                                    <td class="py-3 px-4 border border-gray-300 text-gray-700 text-sm">
                                        <?= $questions[$i-1] ?>
                                    </td>
                                    <?php for ($j = 1; $j <= 5; $j++): ?>
                                    <td class="text-center py-2 px-1 border border-gray-300">
                                        <?php if ($rating && $rating['rating'] == $j): ?>
                                        <div class="w-8 h-8 flex items-center justify-center mx-auto bg-gray-600 text-white rounded">
                                            <i class="ri-check-line text-sm"></i>
                                        </div>
                                        <?php else: ?>
                                        <div class="w-8 h-8 flex items-center justify-center mx-auto text-gray-400">
                                            <?= $j ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endfor; ?>
                                    <td class="py-2 px-4 border border-gray-300">
                                        <div class="text-sm text-gray-700"><?= htmlspecialchars($rating['remark'] ?? '') ?></div>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Comments -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Comments:</label>
                    <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200 min-h-[80px]"><?= nl2br(htmlspecialchars($evaluation_details['comments'])) ?></div>
                </div>

                <!-- Future Training Needs -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Please list down other training programs he/she might need in the future:</label>
                    <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200 min-h-[100px]"><?= nl2br(htmlspecialchars($evaluation_details['future_training_needs'])) ?></div>
                </div>

                <!-- Signature Section -->
                <div class="border-t border-gray-200 pt-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rated by:</label>
                            <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= htmlspecialchars($evaluation_details['rated_by']) ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Signature:</label>
                            <div class="signature-preview rounded">
                                <?php if (!empty($evaluation_details['signature_date'])): ?>
                                    <img src="<?= htmlspecialchars($evaluation_details['signature_date']) ?>" alt="Signature" class="signature-image">
                                <?php else: ?>
                                    <span class="text-gray-400">No signature</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date:</label>
                            <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <?= $evaluation_details['created_at'] ? date('M d, Y', strtotime($evaluation_details['created_at'])) : 'Not set' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workflow History -->
                <?php if (!empty($workflow_history)): ?>
                <div class="mt-8 border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Evaluation History</h3>
                    <div class="workflow-timeline">
                        <?php foreach ($workflow_history as $history): ?>
                        <div class="workflow-step">
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            Status changed from 
                                            <span class="text-gray-600"><?= $history['from_status'] ? ucfirst($history['from_status']) : 'None' ?></span> 
                                            to 
                                            <span class="text-green-600"><?= ucfirst($history['to_status']) ?></span>
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            By: <?= htmlspecialchars($history['changed_by_name']) ?>
                                        </p>
                                        <?php if (!empty($history['comments'])): ?>
                                        <p class="text-sm text-gray-600 mt-2">
                                            <strong>Comment:</strong> <?= htmlspecialchars($history['comments']) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-400">
                                        <?= date('M d, Y h:i A', strtotime($history['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="mt-8 flex justify-end space-x-4 pt-6 border-t">
                    <?php if ($evaluation_details['status'] === 'draft'): ?>
                    <form method="POST" action="" class="inline">
                        <input type="hidden" name="evaluation_id" value="<?= $evaluation_details['id'] ?>">
                        <button type="submit" name="send_evaluation" class="btn-animated px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors" onclick="return confirm('Submit this evaluation to HR?')">
                            <i class="ri-send-plane-line mr-2"></i>Submit to HR
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <a href="ccje_eval.php?evaluation_id=<?= $evaluation_details['id'] ?>&user_id=<?= $evaluation_details['user_id'] ?>" class="btn-animated px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
                        <i class="ri-edit-line mr-2"></i>Edit Evaluation
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Progress Chart
            const progressCtx = document.getElementById('progressChart').getContext('2d');
            const progressChart = new Chart(progressCtx, {
                type: 'pie',
                data: {
                    labels: ['Completed', 'Pending', 'In Progress'],
                    datasets: [{
                        data: [
                            <?= $stats['evaluated_this_year'] ?>,
                            <?= $stats['pending_evaluations'] ?>,
                            <?= $evaluation_stats['draft'] ?? 0 ?>
                        ],
                        backgroundColor: [
                            '#10b981',
                            '#f59e0b',
                            '#6b7280'
                        ],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        borderRadius: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Distribution Chart
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            const distributionChart = new Chart(distributionCtx, {
                type: 'bar',
                data: {
                    labels: ['Teaching', 'Non-Teaching'],
                    datasets: [{
                        label: 'Faculty Count',
                        data: [<?= $stats['teaching'] ?>, <?= $stats['non_teaching'] ?>],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.9)',
                            'rgba(107, 114, 128, 0.9)'
                        ],
                        borderColor: [
                            'rgb(16, 185, 129)',
                            'rgb(107, 114, 128)'
                        ],
                        borderWidth: 3,
                        borderRadius: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                }
            });
        });

        // Notification Modal Functions
        function showNotifications() {
            const modal = document.getElementById('notificationModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function hideNotifications() {
            const modal = document.getElementById('notificationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Evaluation Modal Functions
        function closeModal() {
            document.body.classList.remove('overflow-hidden');
            window.location.href = 'CCJE.php';
        }

        function printForm() {
            window.print();
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const notificationModal = document.getElementById('notificationModal');
            const evaluationModal = document.querySelector('.modal-backdrop');
            
            if (notificationModal && !notificationModal.classList.contains('hidden') && 
                event.target === notificationModal.querySelector('.modal-backdrop')) {
                hideNotifications();
            }
            
            if (evaluationModal && event.target === evaluationModal) {
                closeModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideNotifications();
                closeModal();
            }
        });

        // Enhanced hover effects with JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('.btn-animated, button, a[href*="eval"]');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.classList.contains('btn-animated') || this.tagName === 'BUTTON') {
                        const ripple = document.createElement('span');
                        const rect = this.getBoundingClientRect();
                        const size = Math.max(rect.width, rect.height);
                        const x = e.clientX - rect.left - size / 2;
                        const y = e.clientY - rect.top - size / 2;
                        
                        ripple.style.cssText = `
                            position: absolute;
                            width: ${size}px;
                            height: ${size}px;
                            background: rgba(255, 255, 255, 0.5);
                            border-radius: 50%;
                            top: ${y}px;
                            left: ${x}px;
                            transform: scale(0);
                            animation: ripple 0.6s ease-out;
                            pointer-events: none;
                            z-index: 1;
                        `;
                        
                        this.style.position = 'relative';
                        this.style.overflow = 'hidden';
                        this.appendChild(ripple);
                        
                        setTimeout(() => {
                            ripple.remove();
                        }, 600);
                    }
                });
            });

            // Add CSS for ripple animation
            if (!document.querySelector('#ripple-styles')) {
                const style = document.createElement('style');
                style.id = 'ripple-styles';
                style.textContent = `
                    @keyframes ripple {
                        to {
                            transform: scale(4);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }

            // Enhanced notification bell functionality
            document.querySelector('.notification-bell').addEventListener('click', function() {
                this.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.style.transform = 'scale(1.1) rotate(15deg)';
                }, 100);
                setTimeout(() => {
                    this.style.transform = '';
                }, 300);
            });
        });

        // Show success/error messages if they exist
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Evaluation submitted to HR successfully!',
                confirmButtonColor: '#6b7280',
                background: '#fff',
                color: '#374151',
                confirmButtonText: 'Continue',
                timer: 3000,
                timerProgressBar: true
            });
        <?php elseif (isset($error_message) && !empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($error_message) ?>',
                confirmButtonColor: '#6b7280',
                background: '#fff',
                color: '#374151'
            });
        <?php endif; ?>

        // Real-time notification updates
        function updateNotifications() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const notificationList = document.getElementById('notificationList');
                    if (data.length > 0) {
                        notificationList.innerHTML = '';
                        data.forEach(notification => {
                            const notificationItem = document.createElement('div');
                            notificationItem.className = 'notification-item p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors';
                            
                            let iconClass = 'ri-information-line';
                            let bgColor = 'bg-gray-100';
                            let textColor = 'text-gray-600';
                            
                            if (notification.type === 'evaluation') {
                                iconClass = 'ri-file-list-3-line';
                                bgColor = 'bg-gray-100';
                                textColor = 'text-gray-600';
                            } else if (notification.type === 'pending') {
                                iconClass = 'ri-time-line';
                                bgColor = 'bg-amber-100';
                                textColor = 'text-amber-600';
                            }
                            
                            notificationItem.innerHTML = `
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 ${bgColor} rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="${iconClass} ${textColor} text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">${notification.message}</p>
                                        <p class="text-xs text-gray-400 mt-1">${new Date(notification.timestamp).toLocaleString()}</p>
                                    </div>
                                </div>
                            `;
                            
                            notificationList.appendChild(notificationItem);
                        });
                    } else {
                        notificationList.innerHTML = `
                            <div class="p-4 text-center">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="ri-notification-off-line text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500 text-sm">No notifications</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }

        // Update notifications every 30 seconds
        setInterval(updateNotifications, 30000);

        // Mark all notifications as read
        function markAllAsRead() {
            fetch('mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update notification count
                    const notificationCount = document.querySelector('.notification-bell span');
                    if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }
                    hideNotifications();
                }
            })
            .catch(error => {
                console.error('Error marking notifications as read:', error);
            });
        }
    </script>
</body>
</html>