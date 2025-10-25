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

// Initialize variables
$evaluations = [];
$error_message = null;
$evaluation_details = null;
$evaluation_ratings = [];
$workflow_history = [];

// Build the query to get ALL evaluations (not just submitted)
$sql = "
    SELECT 
        e.id as evaluation_id,
        e.user_id,
        e.status,
        e.created_at,
        e.updated_at,
        e.sent_to_user_at,
        u.name as employee_name,
        u.department,
        u.teaching_status,
        evaluator.name as evaluator_name,
        sent_by.name as sent_by_name
    FROM evaluations e
    JOIN users u ON e.user_id = u.id
    JOIN users evaluator ON e.evaluator_id = evaluator.id
    LEFT JOIN users sent_by ON e.sent_by = sent_by.id
    WHERE u.role != 'admin'
    AND u.department IS NOT NULL
    AND u.department != 'admin'
    AND e.status IN ('submitted', 'sent_to_user', 'approved')
";

$params = [];
$types = "";

// Apply filters
$filters = [];
if (isset($_GET['department']) && !empty($_GET['department'])) {
    $sql .= " AND u.department = ?";
    $params[] = $_GET['department'];
    $types .= "s";
    $filters['department'] = $_GET['department'];
}

if (isset($_GET['employment_type']) && !empty($_GET['employment_type'])) {
    $sql .= " AND u.teaching_status = ?";
    $params[] = $_GET['employment_type'];
    $types .= "s";
    $filters['employment_type'] = $_GET['employment_type'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $sql .= " AND (u.name LIKE ? OR u.department LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
    $filters['search'] = $_GET['search'];
}

// Add ordering - show newest first
$sql .= " ORDER BY e.created_at DESC, u.name ASC";

try {
    if (!empty($params)) {
        $stmt = $con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $con->query($sql);
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $evaluations[] = $row;
        }
    } else {
        throw new Exception("Query failed: " . $con->error);
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while fetching evaluation data: " . $e->getMessage();
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
            evaluator.name as evaluator_name,
            sent_by.name as sent_by_name
        FROM evaluations e
        JOIN users u ON e.user_id = u.id
        JOIN users evaluator ON e.evaluator_id = evaluator.id
        LEFT JOIN users sent_by ON e.sent_by = sent_by.id
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
            $error_message = "Evaluation not found";
        }
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle sending evaluation to user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_user'])) {
    $evaluation_id = $_POST['evaluation_id'];
    $target_user_id = $_POST['user_id'];
    
    try {
        // Get user first name
        $user_sql = "SELECT name FROM users WHERE id = ?";
        $user_stmt = $con->prepare($user_sql);
        $user_stmt->bind_param("i", $target_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $target_user = $user_result->fetch_assoc();
        
        if ($target_user) {
            // Extract first name
            $first_name = explode(' ', $target_user['name'])[0];
            
            // Update evaluation status to sent_to_user and record who sent it
            $update_sql = "UPDATE evaluations SET status = 'sent_to_user', sent_by = ?, sent_to_user_at = NOW(), updated_at = NOW() WHERE id = ?";
            $update_stmt = $con->prepare($update_sql);
            $update_stmt->bind_param("ii", $_SESSION['user_id'], $evaluation_id);
            $update_stmt->execute();
            
            // Add to workflow history
            $workflow_sql = "INSERT INTO evaluation_workflow (evaluation_id, from_status, to_status, changed_by, comments) 
                             VALUES (?, 'submitted', 'sent_to_user', ?, ?)";
            $workflow_stmt = $con->prepare($workflow_sql);
            $comments = "Evaluation sent to " . $first_name;
            $workflow_stmt->bind_param("iis", $evaluation_id, $_SESSION['user_id'], $comments);
            $workflow_stmt->execute();

            // Create notification for the user
            $notification_sql = "INSERT INTO notifications (user_id, message, related_type, related_id, is_read) 
                                VALUES (?, ?, 'evaluation', ?, 0)";
            $notification_stmt = $con->prepare($notification_sql);
            $notification_message = "You have been evaluated! View your evaluation results.";
            $notification_stmt->bind_param("isi", $target_user_id, $notification_message, $evaluation_id);
            $notification_stmt->execute();
            
            $_SESSION['success_message'] = "Evaluation successfully sent to " . $first_name . "!";
            header("Location: Evaluation_Form.php?success=1");
            exit();
        } else {
            $error_message = "User not found";
        }
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get unique departments and teaching statuses for filters (exclude admin)
$departments = $con->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' AND department != 'admin' AND role != 'admin' ORDER BY department")->fetch_all(MYSQLI_ASSOC);
$employment_types = $con->query("SELECT DISTINCT teaching_status FROM users WHERE teaching_status IS NOT NULL AND teaching_status != '' AND role != 'admin' ORDER BY teaching_status")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Forms - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e3a8a',
                        secondary: '#1e40af',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                        info: '#3b82f6',
                        dark: '#1e293b',
                        light: '#f8fafc'
                    },
                    borderRadius: {
                        DEFAULT: '12px',
                        'button': '10px'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    },
                    boxShadow: {
                        'card': '0 8px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 10px -2px rgba(0, 0, 0, 0.05)',
                        'button': '0 4px 12px 0 rgba(30, 58, 138, 0.2)'
                    }
                }
            }
        }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
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
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(30, 58, 138, 0.25);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
        }
        
        .sidebar-link {
            transition: all 0.3s ease;
            border-radius: 12px;
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
        
        .header {
            background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        }
        
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.15);
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        
        .data-table thead th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: none;
        }
        
        .data-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .data-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .data-table tbody tr:hover {
            background-color: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .data-table tbody td {
            padding: 1.25rem;
            vertical-align: top;
            color: #374151;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-teaching {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .status-nonteaching {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .status-submitted {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .status-sent {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .status-approved {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            color: white;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #ef4444, #f87171);
            color: white;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
        .view-btn {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
        }
        
        .view-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.4);
        }
        
        .send-btn {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            margin-left: 0.5rem;
        }
        
        .send-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .sent-btn {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(107, 114, 128, 0.3);
            margin-left: 0.5rem;
            cursor: not-allowed;
        }
        
        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        
        .filter-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .search-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M18.031 16.617l4.283 4.282-1.415 1.415-4.282-4.283A8.96 8.96 0 0 1 11 20c-4.968 0-9-4.032-9-9s4.032-9 9-9 9 4.032 9 9a8.96 8.96 0 0 1-1.969 5.617zm-2.006-.742A6.977 6.977 0 0 0 18 11c0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132 7 7 7a6.977 6.977 0 0 0 4.875-1.975l.15-.15z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 1rem center;
            background-size: 16px;
            padding-left: 2.75rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .search-input:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        .filter-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M12 15l-4.243-4.243 1.415-1.414L12 12.172l2.828-2.829 1.415 1.414z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .filter-select:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 20px;
            width: 100%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 130px);
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
        }
        
        .department-badge {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
        }
        
        .auto-submit {
            cursor: pointer;
        }
        
        .timestamp {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .evaluation-detail {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .evaluation-detail:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            color: #6b7280;
        }

        /* Evaluation form styles */
        .form-field {
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            width: 100%;
        }

        .rating-cell {
            text-align: center;
            padding: 8px 4px;
        }

        .rating-selected {
            background-color: #3b82f6;
            color: white;
            border-radius: 4px;
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
            background-color: #3b82f6;
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

        .sent-info {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .sent-info p {
            margin: 0.25rem 0;
            color: #0c4a6e;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="min-h-screen <?php echo isset($show_evaluation_modal) && $show_evaluation_modal ? 'overflow-hidden' : ''; ?>">
<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-80 sidebar border-r border-white/20 flex-shrink-0 relative z-10">
        <div class="h-full flex flex-col">
            <!-- LSPU Header -->
            <div class="p-6 border-b border-white/20">
                <div class="flex items-center space-x-4">
                    <div class="logo-container">
                        <img src="images/lspu-logo.png" alt="LSPU Logo" class="w-12 h-12 rounded-xl bg-white p-1" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm" style="display: none;">
                            <i class="ri-government-line text-white text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">LSPU Admin</h1>
                        <p class="text-white/60 text-sm">Dashboard</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6">
                <div class="space-y-2">
                    <a href="admin_page.php" 
                       class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
                        <i class="ri-dashboard-line mr-3 text-lg"></i>
                        <span class="text-base">Dashboard</span>
                    </a>

                    <a href="Assessment Form.php" 
                       class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
                        <i class="ri-survey-line mr-3 text-lg"></i>
                        <span class="text-base">Assessment Forms</span>
                    </a>

                    <a href="Individual_Development_Plan_Form.php" 
                       class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
                        <i class="ri-contacts-book-2-line mr-3 text-lg"></i>
                        <span class="text-base">IDP Forms</span>
                    </a>

                    <a href="Evaluation_Form.php" 
                       class="flex items-center px-4 py-3 text-white font-semibold rounded-xl sidebar-link active">
                        <i class="ri-file-search-line mr-3 text-lg"></i>
                        <span class="text-base">Evaluation Forms</span>
                        <i class="ri-arrow-right-s-line ml-auto text-lg"></i>
                    </a>
                </div>
            </nav>

            <!-- Sign Out -->
            <div class="p-4 border-t border-white/20">
                <a href="homepage.php" 
                   class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link hover:bg-red-500/30 border border-red-500/30">
                    <i class="ri-logout-box-line mr-3 text-lg"></i>
                    <span class="text-base">Sign Out</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-auto">
        <!-- Header -->
        <header class="header border-b border-white/20">
            <div class="flex justify-between items-center px-8 py-6">
                <div>
                    <h1 class="text-3xl font-bold text-white">Evaluation Forms</h1>
                    <p class="text-white/70 text-lg mt-2">View and manage evaluation forms</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-white/80 text-sm font-semibold">Today is</p>
                        <p class="text-white font-bold text-lg"><?php echo date('F j, Y'); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm border border-white/30">
                        <i class="ri-calendar-2-line text-white text-xl"></i>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Success Message -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="ri-checkbox-circle-line text-green-500 mr-2"></i>
                            <span class="text-green-700"><?= $_SESSION['success_message'] ?></span>
                        </div>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <!-- Stats Card -->
                <div class="card mb-8">
                    <div class="p-6 bg-gradient-to-r from-primary to-secondary text-white rounded-2xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white/80 text-sm font-medium">Total Evaluations</p>
                                <h3 class="text-4xl font-bold text-white mt-2"><?= count($evaluations) ?></h3>
                                <p class="text-white/90 text-xs mt-1 font-semibold">
                                    All evaluation forms
                                </p>
                            </div>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm shadow-lg">
                                <i class="fas fa-file-alt text-white text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <form method="GET" action="Evaluation_Form.php" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Department Filter -->
                    <div class="filter-card">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Department</label>
                        <select name="department" class="filter-select auto-submit appearance-none w-full px-4 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-primary text-gray-700">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                    <?= isset($_GET['department']) && $_GET['department'] === $dept['department'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Teaching Status Filter -->
                    <div class="filter-card">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Teaching Status</label>
                        <select name="employment_type" class="filter-select auto-submit appearance-none w-full px-4 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-primary text-gray-700">
                            <option value="">All Status</option>
                            <?php foreach ($employment_types as $status): ?>
                                <option value="<?= htmlspecialchars($status['teaching_status']) ?>" 
                                    <?= isset($_GET['employment_type']) && $_GET['employment_type'] === $status['teaching_status'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status['teaching_status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="filter-card">
                        <label for="search-input" class="block text-sm font-medium text-gray-700 mb-3">Search Employees</label>
                        <div class="relative">
                            <input type="text" name="search" id="search-input"
                                   class="w-full search-input py-2.5 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-primary transition"
                                   placeholder="Search by name or department..."
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                            <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-primary text-white p-2 rounded-lg hover:bg-secondary transition-colors">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Clear Filters Button -->
                <?php if (!empty($filters)): ?>
                <div class="flex justify-end mb-6">
                    <a href="Evaluation_Form.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="ri-close-line mr-2"></i> Clear All Filters
                    </a>
                </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if ($error_message): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="ri-error-warning-line text-red-500 mr-2"></i>
                            <span class="text-red-700"><?= $error_message ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Results Count -->
                <div class="mb-4">
                    <p class="text-sm text-gray-600">
                        Showing <?= count($evaluations) ?> evaluation<?= count($evaluations) !== 1 ? 's' : '' ?>
                        <?php if (!empty($filters)): ?>
                            with applied filters
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Table -->
                <div class="table-container mb-8">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="pl-6">Employee Name</th>
                                    <th>Department</th>
                                    <th>Teaching Status</th>
                                    <th>Status</th>
                                    <th>Submitted Date</th>
                                    <th class="pr-6">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($evaluations)): ?>
                                    <?php foreach ($evaluations as $eval): ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="pl-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="bg-blue-100 text-blue-600 rounded-full w-10 h-10 flex items-center justify-center mr-3">
                                                        <i class="ri-user-line"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($eval['employee_name']) ?></div>
                                                        <div class="text-xs text-gray-500">Evaluated by: <?= htmlspecialchars($eval['evaluator_name']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4">
                                                <span class="department-badge"><?= htmlspecialchars($eval['department']) ?></span>
                                            </td>
                                            <td class="py-4">
                                                <?php if (strtolower($eval['teaching_status']) == 'teaching'): ?>
                                                    <span class="status-badge status-teaching">
                                                        <i class="ri-user-star-fill mr-1"></i> Teaching
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge status-nonteaching">
                                                        <i class="ri-user-fill mr-1"></i> Non-Teaching
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4">
                                                <?php if ($eval['status'] === 'submitted'): ?>
                                                    <span class="status-badge status-submitted">
                                                        <i class="ri-send-plane-fill mr-1"></i> Submitted
                                                    </span>
                                                <?php elseif ($eval['status'] === 'sent_to_user'): ?>
                                                    <span class="status-badge status-sent">
                                                        <i class="ri-check-double-fill mr-1"></i> Sent to User
                                                    </span>
                                                    <?php if (!empty($eval['sent_by_name'])): ?>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            By: <?= htmlspecialchars($eval['sent_by_name']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ($eval['status'] === 'approved'): ?>
                                                    <span class="status-badge status-approved">
                                                        <i class="ri-checkbox-circle-fill mr-1"></i> Approved
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4">
                                                <div class="text-sm text-gray-600">
                                                    <?= date('M j, Y', strtotime($eval['created_at'])) ?>
                                                </div>
                                                <div class="timestamp">
                                                    <?= date('g:i A', strtotime($eval['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td class="pr-6 py-4">
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="evaluation_id" value="<?= $eval['evaluation_id'] ?>">
                                                    <button type="submit" name="view_evaluation" class="view-btn">
                                                        <i class="ri-eye-line mr-1"></i> View
                                                    </button>
                                                </form>
                                                <?php if ($eval['status'] === 'submitted'): ?>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="evaluation_id" value="<?= $eval['evaluation_id'] ?>">
                                                        <input type="hidden" name="user_id" value="<?= $eval['user_id'] ?>">
                                                        <button type="submit" name="send_to_user" class="send-btn" onclick="return confirm('Send this evaluation to <?= htmlspecialchars(explode(' ', $eval['employee_name'])[0]) ?>?')">
                                                            <i class="ri-send-plane-line mr-1"></i> Send
                                                        </button>
                                                    </form>
                                                <?php elseif ($eval['status'] === 'sent_to_user'): ?>
                                                    <button class="sent-btn" disabled>
                                                        <i class="ri-check-double-line mr-1"></i> Sent
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center">
                                            <div class="flex flex-col items-center justify-center text-gray-400">
                                                <i class="ri-file-search-line text-4xl mb-3"></i>
                                                <p class="text-lg font-medium">No evaluations found</p>
                                                <p class="text-sm mt-1">
                                                    <?php if (!empty($filters)): ?>
                                                        Try adjusting your filters or search terms
                                                    <?php else: ?>
                                                        No evaluation forms have been submitted yet
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Evaluation View Modal -->
<?php if (isset($show_evaluation_modal) && $show_evaluation_modal && $evaluation_details): ?>
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay active">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-xl font-bold text-gray-800">TRAINING PROGRAM IMPACT ASSESSMENT FORM</h3>
            <button type="button" class="text-gray-400 hover:text-gray-500 text-2xl close-modal-btn">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <!-- Header Information -->
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <div>
                    <?php if ($evaluation_details['status'] === 'submitted'): ?>
                        <span class="status-badge status-submitted">
                            <i class="ri-send-plane-fill mr-1"></i> Submitted
                        </span>
                    <?php elseif ($evaluation_details['status'] === 'sent_to_user'): ?>
                        <span class="status-badge status-sent">
                            <i class="ri-check-double-fill mr-1"></i> Sent to User
                        </span>
                    <?php elseif ($evaluation_details['status'] === 'approved'): ?>
                        <span class="status-badge status-approved">
                            <i class="ri-checkbox-circle-fill mr-1"></i> Approved
                        </span>
                    <?php endif; ?>
                    <span class="text-sm text-gray-500 ml-4">
                        Created: <?= date('M d, Y h:i A', strtotime($evaluation_details['created_at'])) ?>
                    </span>
                    <?php if ($evaluation_details['updated_at'] != $evaluation_details['created_at']): ?>
                    <span class="text-sm text-gray-500 ml-4">
                        Updated: <?= date('M d, Y h:i A', strtotime($evaluation_details['updated_at'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="flex space-x-2">
                    <button onclick="printForm()" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors">
                        <i class="ri-printer-line mr-2"></i>Print
                    </button>
                </div>
            </div>

            <?php if ($evaluation_details['status'] === 'sent_to_user' && !empty($evaluation_details['sent_by_name'])): ?>
                <div class="sent-info">
                    <p><strong>Sent to User:</strong> <?= date('M d, Y \a\t g:i A', strtotime($evaluation_details['sent_to_user_at'])) ?></p>
                    <p><strong>Sent by:</strong> <?= htmlspecialchars($evaluation_details['sent_by_name']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name of Employee:</label>
                    <div class="form-field"><?= htmlspecialchars($evaluation_details['employee_name']) ?></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department/Unit:</label>
                    <div class="form-field"><?= htmlspecialchars($evaluation_details['employee_department']) ?></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title of Training/Seminar Attended:</label>
                    <div class="form-field"><?= htmlspecialchars($evaluation_details['training_title']) ?></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Conducted:</label>
                    <div class="form-field"><?= date('M d, Y', strtotime($evaluation_details['date_conducted'])) ?></div>
                </div>
            </div>

            <!-- Objectives -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Objective/s:</label>
                <div class="form-field min-h-[80px]"><?= nl2br(htmlspecialchars($evaluation_details['objectives'])) ?></div>
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
                                <td class="rating-cell border border-gray-300">
                                    <?php if ($rating && $rating['rating'] == $j): ?>
                                    <div class="rating-selected w-8 h-8 flex items-center justify-center mx-auto">
                                        <i class="ri-check-line"></i>
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
                <div class="form-field min-h-[80px]"><?= nl2br(htmlspecialchars($evaluation_details['comments'])) ?></div>
            </div>

            <!-- Future Training Needs -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Please list down other training programs he/she might need in the future:</label>
                <div class="form-field min-h-[100px]"><?= nl2br(htmlspecialchars($evaluation_details['future_training_needs'])) ?></div>
            </div>

            <!-- Signature Section -->
            <div class="border-t border-gray-200 pt-6">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rated by:</label>
                        <div class="form-field"><?= htmlspecialchars($evaluation_details['rated_by']) ?></div>
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
                        <div class="form-field">
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
                                        <span class="text-blue-600"><?= $history['from_status'] ? ucfirst($history['from_status']) : 'None' ?></span> 
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
        </div>
        <div class="modal-footer">
            <?php if ($evaluation_details['status'] === 'submitted'): ?>
            <form method="POST" action="" class="inline">
                <input type="hidden" name="evaluation_id" value="<?= $evaluation_details['id'] ?>">
                <input type="hidden" name="user_id" value="<?= $evaluation_details['user_id'] ?>">
                <button type="submit" name="send_to_user" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors" onclick="return confirm('Send this evaluation to <?= htmlspecialchars(explode(' ', $evaluation_details['employee_name'])[0]) ?>?')">
                    <i class="ri-send-plane-line mr-2"></i> Send to <?= htmlspecialchars(explode(' ', $evaluation_details['employee_name'])[0]) ?>
                </button>
            </form>
            <?php endif; ?>
            <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors close-modal-btn">
                Close
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Auto-submit functionality for filters
  const autoSubmitElements = document.querySelectorAll('.auto-submit');
  
  autoSubmitElements.forEach(element => {
      element.addEventListener('change', function() {
          this.closest('form').submit();
      });
  });

  // Modal functionality
  const modal = document.querySelector('.modal-overlay');
  const closeButtons = document.querySelectorAll('.close-modal-btn');

  // Close modal functionality
  closeButtons.forEach(button => {
      button.addEventListener('click', function() {
          if (modal) {
              modal.classList.remove('active');
              // Redirect to clear the modal state
              window.location.href = 'Evaluation_Form.php';
          }
      });
  });

  // Close modal when clicking outside
  if (modal) {
      modal.addEventListener('click', function(e) {
          if (e.target === this) {
              modal.classList.remove('active');
              // Redirect to clear the modal state
              window.location.href = 'Evaluation_Form.php';
          }
      });
  }

  // Close modal with Escape key
  document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
          modal.classList.remove('active');
          // Redirect to clear the modal state
          window.location.href = 'Evaluation_Form.php';
      }
  });
});

function printForm() {
    window.print();
}
</script>
</body>
</html>