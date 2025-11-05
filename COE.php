<?php
session_start();

// Check if user is logged in
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
$unevaluated_faculty = [];
$recent_evaluations = [];
$evaluation_details = null;
$evaluation_ratings = [];
$workflow_history = [];
$evaluation_stats = [];
$error_message = '';
$show_evaluation_modal = false;

// Default values for stats
$stats = [
    'total_faculty' => 0,
    'teaching' => 0,
    'non_teaching' => 0,
    'evaluated_this_year' => 0,
    'pending_evaluations' => 0,
    'progress_percentage' => 0
];

// Get COE department statistics
try {
    // Total COE employees (users with role 'user' AND teaching_status is not null)
    $total_result = $con->query("SELECT COUNT(*) as count FROM users WHERE department = 'COE' AND role = 'user' AND teaching_status IS NOT NULL AND teaching_status != ''");
    if ($total_result) {
        $stats['total_faculty'] = $total_result->fetch_assoc()['count'];
    }
    
    // Teaching staff - Using flexible query to handle different cases
    $teaching_result = $con->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE department = 'COE' 
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
        WHERE department = 'COE' 
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
            WHERE department = 'COE' 
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
        WHERE EXISTS (
            SELECT 1 FROM users u WHERE u.id = evaluations.user_id AND u.department = 'COE'
        )
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
    
    // Evaluated faculty (have at least one evaluation record)
    $evaluated_result = $con->query("
        SELECT COUNT(DISTINCT e.user_id) as count 
        FROM evaluations e
        JOIN users u ON e.user_id = u.id
        WHERE u.department = 'COE'
        AND YEAR(e.created_at) = '$current_year'
    ");
    if ($evaluated_result) {
        $stats['evaluated_this_year'] = $evaluated_result->fetch_assoc()['count'];
    }
    
    // Pending evaluations (faculty without any evaluation record for current year)
    $pending_result = $con->query("
        SELECT COUNT(*) as count 
        FROM users u 
        WHERE u.department = 'COE' 
        AND u.role = 'user'
        AND u.teaching_status IS NOT NULL 
        AND u.teaching_status != ''
        AND NOT EXISTS (
            SELECT 1 FROM evaluations e WHERE e.user_id = u.id AND YEAR(e.created_at) = '$current_year'
        )
    ");
    if ($pending_result) {
        $stats['pending_evaluations'] = $pending_result->fetch_assoc()['count'];
    }

    // Calculate progress percentage
    $stats['progress_percentage'] = $stats['total_faculty'] > 0 ? 
        round(($stats['evaluated_this_year'] / $stats['total_faculty']) * 100) : 0;

    // Get unevaluated faculty (COE users without any evaluation record for current year)
    $unevaluated_result = $con->query("
        SELECT id, name, teaching_status 
        FROM users 
        WHERE department = 'COE' 
        AND role = 'user'
        AND teaching_status IS NOT NULL 
        AND teaching_status != ''
        AND NOT EXISTS (
            SELECT 1 FROM evaluations WHERE user_id = users.id AND YEAR(created_at) = '$current_year'
        )
        ORDER BY name ASC
    ");
    
    if ($unevaluated_result && $unevaluated_result->num_rows > 0) {
        while ($row = $unevaluated_result->fetch_assoc()) {
            $unevaluated_faculty[] = $row;
        }
    }

    // Get recent evaluations with status information
    $recent_result = $con->query("
        SELECT 
            u.id,
            u.name,
            u.teaching_status,
            e.id as evaluation_id,
            e.status as evaluation_status,
            e.created_at
        FROM evaluations e
        JOIN users u ON e.user_id = u.id
        WHERE u.department = 'COE'
        ORDER BY e.created_at DESC 
        LIMIT 5
    ");
    
    if ($recent_result && $recent_result->num_rows > 0) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_evaluations[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Database error in COE.php: " . $e->getMessage());
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
            header("Location: COE.php?success=1");
            exit();
        } else {
            $_SESSION['error_message'] = "Failed to update evaluation status. The evaluation may have already been submitted.";
            header("Location: COE.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Error sending evaluation: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error: Unable to submit evaluation. Please try again.";
        header("Location: COE.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COE Admin Dashboard - LSPU</title>
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
            box-shadow: 0 10px 30px rgba(4, 120, 87, 0.15);
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
            background: linear-gradient(135deg, rgba(4, 120, 87, 0.05) 0%, rgba(6, 95, 70, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            border-radius: 20px;
            z-index: 1;
        }

        .card:hover {
            transform: translateY(-12px) scale(1.02) rotateX(2deg) rotateY(1deg);
            box-shadow: 0 25px 50px rgba(4, 120, 87, 0.25);
        }

        .card:hover::before {
            opacity: 1;
        }

        .card-content {
            position: relative;
            z-index: 2;
        }
        
        /* Enhanced Sidebar Link Animations - DARK GREEN TO LIGHT GREEN AND GREY THEME */
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
            background: linear-gradient(135deg, #047857 0%, #059669 100%);
            transform: translateX(8px) scale(1.02);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(4, 120, 87, 0.3);
        }

        .sidebar-link.active {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            box-shadow: 0 8px 25px rgba(6, 95, 70, 0.4);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateX(5px);
        }
        
        /* Enhanced Stat Card Animations */
        .stat-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
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
            background: linear-gradient(90deg, #6b7280, #9ca3af);
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
            background: linear-gradient(135deg, #047857 0%, #059669 100%);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
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
                box-shadow: 0 0 0 0 rgba(4, 120, 87, 0.7);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 15px rgba(4, 120, 87, 0.3);
            }
            100% { 
                transform: scale(1); 
                box-shadow: 0 0 0 0 rgba(4, 120, 87, 0);
            }
        }
        
        .lspu-header {
            background: linear-gradient(135deg, 
                rgba(6, 95, 70, 0.95) 0%, 
                rgba(4, 120, 87, 0.95) 50%, 
                rgba(6, 78, 59, 0.95) 100%);
            backdrop-filter: blur(20px);
            border-bottom: 4px solid #6b7280;
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
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%236b7280' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
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
        
        .accent-grey {
            color: #6b7280;
        }
        
        .bg-accent-grey {
            background-color: #6b7280;
        }
        
        .text-accent-grey {
            color: #6b7280;
        }
        
        .border-accent-grey {
            border-color: #6b7280;
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
            background-color: #065f46;
        }
        
        .campus-secondary {
            background-color: #047857;
        }
        
        .campus-accent {
            background-color: #6b7280;
        }
        
        .text-campus-primary {
            color: #065f46;
        }
        
        .text-campus-secondary {
            color: #047857;
        }
        
        .text-campus-accent {
            color: #6b7280;
        }
        
        /* COE-specific styling */
        .coe-gradient {
            background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);
        }
        
        .coe-card {
            border-left: 4px solid #065f46;
        }
        
        .coe-highlight {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
            color: #1f2937;
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
            background: rgba(107, 114, 128, 0.1);
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
            background: linear-gradient(to bottom, #6b7280, #9ca3af);
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
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-approved {
            background-color: #bbf7d0;
            color: #166534;
        }

        .status-rejected {
            background-color: #fecaca;
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
            background-color: #047857;
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
            background: linear-gradient(90deg, #6b7280, #9ca3af);
            border-radius: 50px;
            transition: width 1s ease-in-out;
        }

        /* Sliding Line Animation - GREY */
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
            background: linear-gradient(90deg, transparent, #6b7280, transparent);
            transition: left 0.8s ease;
        }

        .sliding-line:hover::after {
            left: 100%;
        }

        .sliding-line.active::after {
            left: 0;
            background: linear-gradient(90deg, #6b7280, #9ca3af, #6b7280);
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
            border-left-color: #065f46;
            background-color: #f9fafb;
        }

        .notification-new {
            background-color: #f0fdf4;
            border-left-color: #065f46;
        }
    </style>
</head>
<body class="min-h-screen bg-white no-horizontal-scroll <?php echo $show_evaluation_modal ? 'overflow-hidden' : ''; ?>">
    <div class="flex h-screen">
        <!-- Sidebar - DARK GREEN TO LIGHT GREEN AND GREY THEME -->
        <aside class="sidebar-container coe-gradient border-r border-green-600">
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
                            <!-- COE Logo -->
                            <div class="logo-container">
                                <img src="images/coe.png" alt="COE Logo" class="w-12 h-12 rounded-lg bg-white p-1"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm" style="display: none;">
                                    <i class="ri-cpu-line text-white text-lg"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- University Header -->
                        <div class="border-t border-white/30 pt-2">
                            <h2 class="text-xs font-semibold uppercase tracking-wider">Republic of the Philippines</h2>
                            <h1 class="text-sm font-bold mt-1 tracking-tight">LAGUNA STATE POLYTECHNIC UNIVERSITY</h1>
                            
                            <!-- College of Engineering -->
                            <div class="mt-2 pt-2 border-t border-white/30">
                                <h3 class="text-sm font-bold uppercase tracking-wide">COLLEGE OF ENGINEERING</h3>
                                <p class="text-xs opacity-80 mt-1 font-semibold text-accent-grey">A.Y. 2024-2025</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="navigation-section mb-3">
                    <nav class="mb-3">
                        <div class="space-y-1">
                            <a href="COE.php" class="flex items-center px-3 py-2 text-white font-semibold rounded-xl sidebar-link active sliding-line active">
                                <i class="ri-dashboard-line mr-2 text-base"></i>
                                <span class="text-sm">Dashboard</span>
                                <i class="ri-arrow-right-s-line ml-auto text-base"></i>
                            </a>
                            <a href="coe_eval.php" class="flex items-center px-3 py-2 text-white/90 font-semibold rounded-xl sidebar-link sliding-line">
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
                                <span class="font-bold text-white text-sm"><?= $stats['total_faculty'] ?></span>
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
            <header class="bg-gradient-to-r from-green-800 to-green-700 border-b border-green-600">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold text-white truncate">Welcome back, <?= htmlspecialchars($user['name'] ?? 'COE Admin') ?>! ⚙️</h1>
                        <p class="text-white/70 text-sm mt-1 truncate">College of Engineering - Faculty Evaluation Dashboard</p>
                    </div>
                    <div class="flex items-center space-x-4 flex-shrink-0">
                        <!-- Notification Bell -->
                        <div class="relative">
                            <button class="notification-bell text-white hover:text-green-200 transition-colors" onclick="showNotifications()">
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
                    <!-- Total Faculty -->
                    <div class="card stat-card pulse sliding-line">
                        <div class="p-4 card-content">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-white/80 text-xs font-medium">Total Faculty</p>
                                    <h3 class="text-2xl font-bold text-white mt-1"><?= $stats['total_faculty'] ?></h3>
                                    <p class="text-white/70 text-xs mt-1 truncate">College of Engineering</p>
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
                                        <?= $stats['total_faculty'] > 0 ? round(($stats['teaching'] / $stats['total_faculty']) * 100) : 0 ?>%
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
                                        <?= $stats['total_faculty'] > 0 ? round(($stats['non_teaching'] / $stats['total_faculty']) * 100) : 0 ?>%
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
                                        <?= $stats['evaluated_this_year'] ?> of <?= $stats['total_faculty'] ?> completed
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
                                <i class="ri-progress-4-line mr-2 text-green-700 text-xl"></i>
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
                                <i class="ri-pie-chart-line mr-2 text-green-700 text-xl"></i>
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
                                    <i class="ri-time-line mr-2 text-yellow-600 text-xl"></i>
                                    Pending Evaluations
                                </h3>
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded-full border border-yellow-300 status-badge">
                                    <?= count($unevaluated_faculty) ?> pending
                                </span>
                            </div>
                            
                            <?php if (!empty($unevaluated_faculty)): ?>
                                <div class="space-y-3">
                                    <?php foreach (array_slice($unevaluated_faculty, 0, 5) as $faculty): ?>
                                        <div class="evaluation-item flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-xl border-2 border-yellow-200 hover:border-yellow-400 transition-all duration-300 sliding-line">
                                            <div class="flex items-center space-x-3 min-w-0">
                                                <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg flex items-center justify-center shadow-lg flex-shrink-0">
                                                    <i class="fas fa-user text-white text-sm"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-base font-bold text-gray-800 truncate"><?= htmlspecialchars($faculty['name']) ?></p>
                                                    <p class="text-xs text-gray-600">
                                                        <?= $faculty['teaching_status'] === 'Teaching' ? 
                                                            '<span class="text-green-600 font-semibold"><i class="fas fa-chalkboard-teacher mr-1"></i>Teaching</span>' : 
                                                            '<span class="text-blue-600 font-semibold"><i class="fas fa-user-tie mr-1"></i>Non-Teaching</span>' ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <a href="coe_eval.php?user_id=<?= $faculty['id'] ?>" 
                                               class="btn-animated bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-3 py-2 rounded-lg text-xs font-bold hover:shadow-lg transition-all duration-300 transform hover:scale-105 shadow-md flex-shrink-0 ml-2">
                                                Evaluate Now
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($unevaluated_faculty) > 5): ?>
                                    <div class="mt-4 text-center">
                                        <a href="coe_eval.php" class="btn-animated text-green-700 hover:text-green-900 text-sm font-bold inline-flex items-center border-2 border-green-700 px-4 py-2 rounded-lg hover:bg-green-700 hover:text-white transition-all duration-300">
                                            View all <?= count($unevaluated_faculty) ?> pending evaluations
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
                                    <i class="ri-history-line mr-2 text-green-700 text-xl"></i>
                                    Recent Evaluations
                                </h3>
                                <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded-full border border-green-300 status-badge">
                                    <?= count($recent_evaluations) ?> recent
                                </span>
                            </div>
                            
                            <?php if (!empty($recent_evaluations)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($recent_evaluations as $evaluation): 
                                        $status_config = [
                                            'approved' => ['class' => 'status-approved', 'icon' => 'fa-check-circle'],
                                            'submitted' => ['class' => 'status-submitted', 'icon' => 'fa-paper-plane'],
                                            'draft' => ['class' => 'status-draft', 'icon' => 'fa-edit'],
                                            'rejected' => ['class' => 'status-rejected', 'icon' => 'fa-times-circle']
                                        ];
                                        $status = $status_config[$evaluation['evaluation_status']] ?? $status_config['draft'];
                                    ?>
                                        <div class="evaluation-item flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-green-50 rounded-xl border-2 border-green-200 hover:border-green-400 transition-all duration-300 sliding-line">
                                            <div class="flex items-center space-x-3 min-w-0 flex-1">
                                                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center shadow-lg flex-shrink-0">
                                                    <i class="fas <?= $status['icon'] ?> text-white text-sm"></i>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center justify-between">
                                                        <p class="text-base font-bold text-gray-800 truncate"><?= htmlspecialchars($evaluation['name']) ?></p>
                                                        <span class="status-badge <?= $status['class'] ?> ml-2">
                                                            <?= ucfirst($evaluation['evaluation_status']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">
                                                        <?= $evaluation['teaching_status'] === 'Teaching' ? 
                                                            '<span class="text-green-600 font-semibold"><i class="fas fa-chalkboard-teacher mr-1"></i>Teaching</span>' : 
                                                            '<span class="text-blue-600 font-semibold"><i class="fas fa-user-tie mr-1"></i>Non-Teaching</span>' ?>
                                                        <span class="mx-2 text-gray-400">•</span>
                                                        <span class="text-gray-500 font-medium"><?= date('M d, Y', strtotime($evaluation['created_at'])) ?></span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2 flex-shrink-0 ml-2">
                                                <?php if ($evaluation['evaluation_status'] === 'draft'): ?>
                                                <form method="POST" action="" class="m-0">
                                                    <input type="hidden" name="evaluation_id" value="<?= $evaluation['evaluation_id'] ?>">
                                                    <button type="submit" name="send_evaluation" 
                                                            class="btn-animated bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:shadow-md transition-all duration-300 shadow-sm"
                                                            onclick="return confirm('Submit this evaluation to HR for review?')">
                                                        <i class="fas fa-paper-plane mr-1"></i>Submit
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" action="" class="m-0">
                                                    <input type="hidden" name="evaluation_id" value="<?= $evaluation['evaluation_id'] ?>">
                                                    <button type="submit" name="view_evaluation" 
                                                            class="btn-animated bg-gradient-to-r from-green-700 to-green-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:shadow-md transition-all duration-300 shadow-sm">
                                                        <i class="fas fa-eye mr-1"></i>View
                                                    </button>
                                                </form>
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
                <div class="p-4 text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="ri-notification-off-line text-green-600 text-xl"></i>
                    </div>
                    <p class="text-gray-500 text-sm">No notifications</p>
                </div>
            </div>
            <div class="p-4 border-t border-gray-200">
                <button class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium" onclick="hideNotifications()">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Evaluation View Modal -->
    <?php if ($show_evaluation_modal && $evaluation_details): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-2xl shadow-2xl modal-container w-full max-w-6xl">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-green-800 to-green-700 text-white p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold">FACULTY PERFORMANCE EVALUATION FORM</h2>
                        <div class="flex items-center space-x-4 mt-2">
                            <span class="status-badge status-<?= $evaluation_details['status'] ?>">
                                <?= ucfirst($evaluation_details['status']) ?>
                            </span>
                            <span class="text-sm text-green-100">
                                Created: <?= date('M d, Y h:i A', strtotime($evaluation_details['created_at'])) ?>
                            </span>
                            <?php if ($evaluation_details['updated_at'] != $evaluation_details['created_at']): ?>
                            <span class="text-sm text-green-100">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name of Faculty:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= htmlspecialchars($evaluation_details['employee_name']) ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= htmlspecialchars($evaluation_details['employee_department']) ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= $current_year ?>-<?= $current_year + 1 ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Evaluation Period:</label>
                        <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200"><?= date('M d, Y', strtotime($evaluation_details['created_at'])) ?></div>
                    </div>
                </div>

                <!-- Evaluation Ratings -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Performance Ratings</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left py-3 px-4 font-medium text-gray-700 border border-gray-300 w-2/3">EVALUATION CRITERIA</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">1</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">2</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">3</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">4</th>
                                    <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-300 w-8">5</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $criteria = [
                                    "1. Teaching Effectiveness and Classroom Management",
                                    "2. Subject Matter Knowledge and Expertise",
                                    "3. Student Engagement and Motivation",
                                    "4. Assessment and Evaluation Methods",
                                    "5. Professional Development and Growth",
                                    "6. Collegiality and Collaboration",
                                    "7. Adherence to Institutional Policies",
                                    "8. Contribution to Department Goals"
                                ];
                                
                                for ($i = 1; $i <= 8; $i++): 
                                    $rating = $evaluation_ratings[$i] ?? null;
                                ?>
                                <tr class="<?= $i % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?>">
                                    <td class="py-3 px-4 border border-gray-300 text-gray-700 text-sm">
                                        <?= $criteria[$i-1] ?>
                                    </td>
                                    <?php for ($j = 1; $j <= 5; $j++): ?>
                                    <td class="text-center py-2 px-1 border border-gray-300">
                                        <?php if ($rating && $rating['rating'] == $j): ?>
                                        <div class="w-8 h-8 flex items-center justify-center mx-auto bg-green-600 text-white rounded">
                                            <i class="ri-check-line text-sm"></i>
                                        </div>
                                        <?php else: ?>
                                        <div class="w-8 h-8 flex items-center justify-center mx-auto text-gray-400">
                                            <?= $j ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Comments and Recommendations -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Overall Comments:</label>
                    <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200 min-h-[80px]"><?= nl2br(htmlspecialchars($evaluation_details['comments'])) ?></div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recommendations for Improvement:</label>
                    <div class="w-full p-3 bg-gray-50 rounded-lg border border-gray-200 min-h-[100px]"><?= nl2br(htmlspecialchars($evaluation_details['future_training_needs'])) ?></div>
                </div>

                <!-- Signature Section -->
                <div class="border-t border-gray-200 pt-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evaluated by:</label>
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
                                            <span class="text-green-600"><?= $history['from_status'] ? ucfirst($history['from_status']) : 'None' ?></span> 
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
                    
                    <a href="coe_eval.php?evaluation_id=<?= $evaluation_details['id'] ?>&user_id=<?= $evaluation_details['user_id'] ?>" class="btn-animated px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
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
                            '#065f46'
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
                            'rgba(6, 95, 70, 0.9)'
                        ],
                        borderColor: [
                            'rgb(16, 185, 129)',
                            'rgb(6, 95, 70)'
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
            window.location.href = 'COE.php';
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
                confirmButtonColor: '#065f46',
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
                confirmButtonColor: '#065f46',
                background: '#fff',
                color: '#374151'
            });
        <?php endif; ?>
    </script>
</body>
</html>