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

// Default values for stats
$stats = [
    'total_employees' => 0,
    'teaching' => 0,
    'non_teaching' => 0,
    'evaluated_this_year' => 0,
    'pending_evaluations' => 0,
    'progress_percentage' => 0
];

// Get CCS department statistics
try {
    // Total CCS employees (users with role 'user' AND teaching_status is not null)
    $total_result = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND role = 'user' AND teaching_status IS NOT NULL AND teaching_status != ''");
    $stats['total_employees'] = $total_result ? $total_result->fetch_row()[0] : 0;
    
    // Teaching staff
    $teaching_result = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND teaching_status = 'Teaching' AND role = 'user'");
    $stats['teaching'] = $teaching_result ? $teaching_result->fetch_row()[0] : 0;
    
    // Non-teaching staff
    $non_teaching_result = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND teaching_status = 'Non-Teaching' AND role = 'user'");
    $stats['non_teaching'] = $non_teaching_result ? $non_teaching_result->fetch_row()[0] : 0;
    
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
        $evaluation_stats = ['total_evaluations' => 0, 'approved' => 0, 'submitted' => 0, 'draft' => 0, 'rejected' => 0];
    }
    
    // Evaluated employees (have at least one evaluation record)
    $evaluated_result = $con->query("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM evaluations 
        WHERE YEAR(created_at) = '$current_year'
    ");
    $stats['evaluated_this_year'] = $evaluated_result ? $evaluated_result->fetch_row()[0] : 0;
    
    // Pending evaluations (employees without any evaluation record)
    $pending_result = $con->query("
        SELECT COUNT(*) as count 
        FROM users u 
        WHERE u.department = 'CCS' 
        AND u.role = 'user'
        AND u.teaching_status IS NOT NULL 
        AND u.teaching_status != ''
        AND NOT EXISTS (
            SELECT 1 FROM evaluations e WHERE e.user_id = u.id
        )
    ");
    $stats['pending_evaluations'] = $pending_result ? $pending_result->fetch_row()[0] : 0;

    // Calculate progress percentage
    $stats['progress_percentage'] = $stats['total_employees'] > 0 ? 
        round(($stats['evaluated_this_year'] / $stats['total_employees']) * 100) : 0;

    // Get unevaluated employees (CCS users without any evaluation record)
    $unevaluated_result = $con->query("
        SELECT id, name, teaching_status 
        FROM users 
        WHERE department = 'CCS' 
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
        WHERE u.department = 'CCS'
        ORDER BY e.created_at DESC 
        LIMIT 5
    ");
    
    if ($recent_result && $recent_result->num_rows > 0) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_evaluations[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while loading dashboard data.";
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
            header("Location: CCS.php?success=1");
            exit();
        } else {
            $error_message = "Failed to update evaluation status";
        }
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle viewing evaluation form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_evaluation'])) {
    $evaluation_id = $_POST['evaluation_id'];
    // Store evaluation_id in session for the view page
    $_SESSION['view_evaluation_id'] = $evaluation_id;
    header("Location: view_evaluation.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Admin Dashboard - LSPU</title>
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
            background-color: #ffffff;
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
        
        .card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        
        .card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 25px 50px rgba(30, 58, 138, 0.25);
        }
        
        .sidebar-link {
            transition: all 0.3s ease;
            border-radius: 15px;
            margin: 4px 0;
            border: 1px solid transparent;
        }
        
        .sidebar-link:hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            transform: translateX(8px);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.3);
        }
        
        .sidebar-link.active {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.4);
            border-color: rgba(255, 255, 255, 0.4);
        }
        
        .stat-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
        }
        
        .floating {
            animation: floating 4s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(2deg); }
        }
        
        .pulse {
            animation: pulse 3s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 20px rgba(245, 158, 11, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        
        .lspu-header {
            background: linear-gradient(135deg, 
                rgba(30, 58, 138, 0.95) 0%, 
                rgba(30, 64, 175, 0.95) 50%, 
                rgba(37, 99, 235, 0.95) 100%);
            backdrop-filter: blur(20px);
            border-bottom: 4px solid #f59e0b;
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
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23f59e0b' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        
        .logo-container {
            transition: all 0.4s ease;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .logo-container:hover {
            transform: scale(1.1) rotate(2deg);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }
        
        .accent-gold {
            color: #f59e0b;
        }
        
        .bg-accent-gold {
            background-color: #f59e0b;
        }
        
        .text-accent-gold {
            color: #f59e0b;
        }
        
        .border-accent-gold {
            border-color: #f59e0b;
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
    </style>
</head>
<body class="min-h-screen bg-white no-horizontal-scroll">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="sidebar-container bg-gradient-to-b from-blue-900 to-blue-800 border-r border-blue-700">
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
                            <!-- CCS Logo -->
                            <div class="logo-container">
                                <img src="images/ccs-logo.png" alt="CCS Logo" class="w-12 h-12 rounded-lg bg-white p-1"
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
                            <p class="text-xs opacity-90 mt-1 font-medium">Los Baños Campus</p>
                            
                            <!-- College of Computer Studies -->
                            <div class="mt-2 pt-2 border-t border-white/30">
                                <h3 class="text-sm font-bold uppercase tracking-wide">COLLEGE OF COMPUTER STUDIES</h3>
                                <p class="text-xs opacity-90 mt-1 font-medium">Student Council</p>
                                <p class="text-xs opacity-80 mt-1 font-semibold text-accent-gold">A.Y. 2024-2025</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="navigation-section mb-3">
                    <nav class="mb-3">
                        <div class="space-y-1">
                            <a href="CCS.php" class="flex items-center px-3 py-2 text-white font-semibold rounded-xl sidebar-link active">
                                <i class="ri-dashboard-line mr-2 text-base"></i>
                                <span class="text-sm">Dashboard</span>
                                <i class="ri-arrow-right-s-line ml-auto text-base"></i>
                            </a>
                            <a href="ccs_eval.php" class="flex items-center px-3 py-2 text-white/90 font-semibold rounded-xl sidebar-link">
                                <i class="ri-file-list-3-line mr-2 text-base"></i>
                                <span class="text-sm">Evaluation</span>
                            </a>
                        </div>
                    </nav>

                    <!-- Stats Overview in Sidebar -->
                    <div class="p-3 bg-white/10 rounded-xl backdrop-blur-sm border border-white/20">
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
                    <a href="?logout=true" class="flex items-center justify-center px-3 py-2 text-white/90 font-semibold rounded-xl sidebar-link hover:bg-red-500/30 border border-red-500/30 bg-red-500/20">
                        <i class="ri-logout-box-line mr-2 text-base"></i>
                        <span class="text-sm">Sign Out</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="bg-gradient-to-r from-blue-900 to-blue-800 border-b border-blue-700">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold text-white truncate">Welcome back, CCS! 👋</h1>
                        <p class="text-white/70 text-sm mt-1 truncate">College of Computer Studies - Faculty Evaluation Dashboard</p>
                    </div>
                    <div class="flex items-center space-x-3 flex-shrink-0">
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
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-6 glass-card p-4 border-l-4 border-green-500">
                        <div class="flex items-center">
                            <i class="ri-checkbox-circle-line text-green-500 text-xl mr-3"></i>
                            <span class="text-gray-800 text-base font-medium"><?= $_SESSION['success_message'] ?></span>
                        </div>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="mb-6 glass-card p-4 border-l-4 border-red-500">
                        <div class="flex items-center">
                            <i class="ri-error-warning-line text-red-500 text-xl mr-3"></i>
                            <span class="text-gray-800 text-base font-medium"><?= $error_message ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Total Employees -->
                    <div class="card stat-card pulse">
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-white/80 text-xs font-medium">Total Faculty</p>
                                    <h3 class="text-2xl font-bold text-white mt-1"><?= $stats['total_employees'] ?></h3>
                                    <p class="text-white/70 text-xs mt-1 truncate">College of Computer Studies</p>
                                </div>
                                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm shadow-lg floating flex-shrink-0 ml-3">
                                    <i class="fas fa-users text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Teaching Staff -->
                    <div class="card stat-card">
                        <div class="p-4">
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
                    <div class="card stat-card">
                        <div class="p-4">
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
                    <div class="card stat-card">
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-white/80 text-xs font-medium">Evaluation Progress</p>
                                    <h3 class="text-2xl font-bold text-white mt-1"><?= $stats['progress_percentage'] ?>%</h3>
                                    <p class="text-white/70 text-xs mt-1">
                                        <?= $stats['evaluated_this_year'] ?> of <?= $stats['total_employees'] ?> completed
                                    </p>
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
                    <div class="card">
                        <div class="p-4">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="ri-progress-4-line mr-2 text-blue-900 text-xl"></i>
                                Evaluation Progress Overview
                            </h3>
                            <div class="h-64">
                                <canvas id="progressChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Faculty Distribution -->
                    <div class="card">
                        <div class="p-4">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="ri-pie-chart-line mr-2 text-blue-900 text-xl"></i>
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
                    <div class="card">
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                    <i class="ri-time-line mr-2 text-yellow-600 text-xl"></i>
                                    Pending Evaluations
                                </h3>
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded-full border border-yellow-300">
                                    <?= count($unevaluated_employees) ?> pending
                                </span>
                            </div>
                            
                            <?php if (!empty($unevaluated_employees)): ?>
                                <div class="space-y-3">
                                    <?php foreach (array_slice($unevaluated_employees, 0, 5) as $employee): ?>
                                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-xl border-2 border-yellow-200 hover:border-yellow-400 transition-all duration-300">
                                            <div class="flex items-center space-x-3 min-w-0">
                                                <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg flex items-center justify-center shadow-lg flex-shrink-0">
                                                    <i class="fas fa-user text-white text-sm"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-base font-bold text-gray-800 truncate"><?= htmlspecialchars($employee['name']) ?></p>
                                                    <p class="text-xs text-gray-600">
                                                        <?= $employee['teaching_status'] === 'Teaching' ? 
                                                            '<span class="text-green-600 font-semibold"><i class="fas fa-chalkboard-teacher mr-1"></i>Teaching</span>' : 
                                                            '<span class="text-purple-600 font-semibold"><i class="fas fa-user-tie mr-1"></i>Non-Teaching</span>' ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <a href="ccs_eval.php?user_id=<?= $employee['id'] ?>" 
                                               class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-3 py-2 rounded-lg text-xs font-bold hover:shadow-lg transition-all duration-300 transform hover:scale-105 shadow-md flex-shrink-0 ml-2">
                                                Evaluate Now
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($unevaluated_employees) > 5): ?>
                                    <div class="mt-4 text-center">
                                        <a href="ccs_eval.php" class="text-blue-900 hover:text-blue-700 text-sm font-bold inline-flex items-center border-2 border-blue-900 px-4 py-2 rounded-lg hover:bg-blue-900 hover:text-white transition-all duration-300">
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
                    <div class="card">
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                    <i class="ri-history-line mr-2 text-blue-900 text-xl"></i>
                                    Recent Evaluations
                                </h3>
                                <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded-full border border-blue-300">
                                    <?= count($recent_evaluations) ?> recent
                                </span>
                            </div>
                            
                            <?php if (!empty($recent_evaluations)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($recent_evaluations as $evaluation): 
                                        $status_colors = [
                                            'approved' => 'from-green-500 to-green-600',
                                            'submitted' => 'from-blue-500 to-blue-600',
                                            'draft' => 'from-gray-500 to-gray-600',
                                            'rejected' => 'from-red-500 to-red-600'
                                        ];
                                        $status_icons = [
                                            'approved' => 'fa-check-circle',
                                            'submitted' => 'fa-paper-plane',
                                            'draft' => 'fa-edit',
                                            'rejected' => 'fa-times-circle'
                                        ];
                                    ?>
                                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-blue-50 rounded-xl border-2 border-blue-200 hover:border-blue-400 transition-all duration-300">
                                            <div class="flex items-center space-x-3 min-w-0">
                                                <div class="w-10 h-10 bg-gradient-to-br <?= $status_colors[$evaluation['evaluation_status']] ?> rounded-lg flex items-center justify-center shadow-lg flex-shrink-0">
                                                    <i class="fas <?= $status_icons[$evaluation['evaluation_status']] ?> text-white text-sm"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-base font-bold text-gray-800 truncate"><?= htmlspecialchars($evaluation['name']) ?></p>
                                                    <p class="text-xs text-gray-600">
                                                        <?= $evaluation['teaching_status'] === 'Teaching' ? 
                                                            '<span class="text-green-600 font-semibold"><i class="fas fa-chalkboard-teacher mr-1"></i>Teaching</span>' : 
                                                            '<span class="text-purple-600 font-semibold"><i class="fas fa-user-tie mr-1"></i>Non-Teaching</span>' ?>
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
                                                            class="bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:shadow-md transition-all duration-300 shadow-sm"
                                                            onclick="return confirm('Submit this evaluation to HR for review?')">
                                                        <i class="fas fa-paper-plane mr-1"></i>Submit
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" action="" class="m-0">
                                                    <input type="hidden" name="evaluation_id" value="<?= $evaluation['evaluation_id'] ?>">
                                                    <button type="submit" name="view_evaluation" 
                                                            class="bg-gradient-to-r from-blue-900 to-blue-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:shadow-md transition-all duration-300 shadow-sm">
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

    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Progress Chart - Changed to Pie Chart
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
                            '#1e40af'
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
                            'rgba(139, 92, 246, 0.9)'
                        ],
                        borderColor: [
                            'rgb(16, 185, 129)',
                            'rgb(139, 92, 246)'
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

        // Show success/error messages if they exist
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Evaluation submitted to HR successfully!',
                confirmButtonColor: '#1e40af',
                background: '#fff',
                color: '#374151',
                confirmButtonText: 'Continue',
                timer: 3000,
                timerProgressBar: true
            });
        <?php elseif (isset($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= $error_message ?>',
                confirmButtonColor: '#1e40af',
                background: '#fff',
                color: '#374151'
            });
        <?php endif; ?>
    </script>
</body>
</html>