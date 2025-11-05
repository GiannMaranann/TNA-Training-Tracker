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

// Check if we should auto-open modal for specific user
$auto_open_modal = false;
$auto_open_user_id = null;
$auto_open_user_name = null;
$auto_open_user_department = null;

if (isset($_GET['user_id'])) {
    $auto_open_user_id = $_GET['user_id'];
    $auto_open_modal = true;
    
    // Get user details for the modal
    $stmt = $con->prepare("SELECT name, department FROM users WHERE id = ?");
    $stmt->bind_param("i", $auto_open_user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $auto_open_user_name = $user_data['name'];
        $auto_open_user_department = $user_data['department'];
    }
}

// Initialize variables
$result = null;
$error_message = null;

try {
    // UPDATED query - Only show users with non-null teaching_status for COE department
    $sql = "SELECT 
                u.id AS user_id,
                u.name,
                u.department,
                u.teaching_status,
                e.id AS evaluation_id,
                e.status AS evaluation_status,
                e.created_at AS evaluation_created
            FROM users u
            LEFT JOIN evaluations e ON u.id = e.user_id 
            WHERE u.department = 'COE' 
            AND u.teaching_status IS NOT NULL 
            AND u.teaching_status != ''
            ORDER BY u.name ASC";
    
    // Debug: Check if query works
    error_log("SQL Query: " . $sql);
    
    // Prepare and execute
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Debug: Check number of results
    error_log("Number of rows: " . $result->num_rows);

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while fetching data. Please try again later. Error: " . $e->getMessage();
}

// Handle sending evaluation to HR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_hr'])) {
    $evaluation_id = $_POST['evaluation_id'];
    
    try {
        // Update evaluation status to submitted
        $update_sql = "UPDATE evaluations SET status = 'submitted', updated_at = NOW() WHERE id = ?";
        $update_stmt = $con->prepare($update_sql);
        $update_stmt->bind_param("i", $evaluation_id);
        $update_stmt->execute();
        
        // Add to workflow history
        $workflow_sql = "INSERT INTO evaluation_workflow (evaluation_id, from_status, to_status, changed_by) 
                         VALUES (?, 'draft', 'submitted', ?)";
        $workflow_stmt = $con->prepare($workflow_sql);
        $workflow_stmt->bind_param("ii", $evaluation_id, $user_id);
        $workflow_stmt->execute();
        
        $_SESSION['success_message'] = "Evaluation sent to HR successfully!";
        header("Location: coe_eval.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Send to HR error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to send evaluation to HR.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COE Evaluation - LSPU</title>
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

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(4, 120, 87, 0.15);
            background: white;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem;
            border-bottom: 2px solid #6b7280;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        tbody tr:hover {
            background-color: #f0fdf4;
            transform: translateY(-2px);
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.1);
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid #e2e8f0;
            background: white;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.15);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: white;
            border-color: #065f46;
        }

        .action-btn {
            transition: all 0.3s ease;
            border-radius: 12px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .action-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .modal-content {
            background-color: white;
            border-radius: 20px;
            width: 95%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            border: 2px solid #065f46;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: white;
            border-radius: 18px 18px 0 0;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            transform: scale(1.1);
            color: #6b7280;
        }

        .modal-iframe {
            width: 100%;
            height: 70vh;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .search-input {
            transition: all 0.3s ease;
            border-radius: 12px;
            padding-left: 2.5rem;
            border: 2px solid #e2e8f0;
        }

        .search-input:focus {
            border-color: #065f46;
            box-shadow: 0 0 0 3px rgba(6, 95, 70, 0.2);
            transform: translateY(-2px);
        }

        /* Department Badge Style - GINAMIT DIN SA EMPLOYMENT TYPE AT EVALUATION STATUS */
        .department-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: white;
        }

        .employment-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .evaluation-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-approved {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: white;
        }

        .status-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .status-submitted {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            color: white;
        }

        .status-draft {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }

        .status-rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
    </style>
</head>
<body class="min-h-screen bg-white no-horizontal-scroll">
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
                            <a href="COE.php" class="flex items-center px-3 py-2 text-white/90 font-semibold rounded-xl sidebar-link sliding-line">
                                <i class="ri-dashboard-line mr-2 text-base"></i>
                                <span class="text-sm">Dashboard</span>
                            </a>
                            <a href="coe_eval.php" class="flex items-center px-3 py-2 text-white font-semibold rounded-xl sidebar-link active sliding-line active">
                                <i class="ri-file-list-3-line mr-2 text-base"></i>
                                <span class="text-sm">Evaluation</span>
                                <i class="ri-arrow-right-s-line ml-auto text-base"></i>
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
                                <span class="font-bold text-white text-sm">
                                    <?php 
                                        $total_faculty = $result ? $result->num_rows : 0;
                                        echo $total_faculty;
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-white/90 text-xs font-medium">
                                <span>Evaluated</span>
                                <span class="font-bold text-green-300 text-sm">
                                    <?php
                                        $evaluated_count = 0;
                                        if ($result) {
                                            $result->data_seek(0); // Reset pointer
                                            while ($row = $result->fetch_assoc()) {
                                                if (!empty($row['evaluation_status']) && $row['evaluation_status'] !== 'pending') {
                                                    $evaluated_count++;
                                                }
                                            }
                                            $result->data_seek(0); // Reset pointer again for main display
                                        }
                                        echo $evaluated_count;
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-white/90 text-xs font-medium">
                                <span>Pending</span>
                                <span class="font-bold text-yellow-300 text-sm">
                                    <?= $total_faculty - $evaluated_count ?>
                                </span>
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
                        <h1 class="text-2xl font-bold text-white truncate">Welcome back, <?= htmlspecialchars($user['name'] ?? 'COE Admin') ?>! 👨‍🏫</h1>
                        <p class="text-white/70 text-sm mt-1 truncate">College of Engineering - Faculty Evaluation Management</p>
                    </div>
                    <div class="flex items-center space-x-4 flex-shrink-0">
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
                    <div class="mb-6 glass-card p-4 border-l-4 border-green-500 animate-fade-in">
                        <div class="flex items-center">
                            <i class="ri-checkbox-circle-line text-green-500 text-xl mr-3"></i>
                            <span class="text-gray-800 text-base font-medium"><?= $_SESSION['success_message'] ?></span>
                        </div>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="mb-6 glass-card p-4 border-l-4 border-red-500 animate-fade-in">
                        <div class="flex items-center">
                            <i class="ri-error-warning-line text-red-500 text-xl mr-3"></i>
                            <span class="text-gray-800 text-base font-medium"><?= $_SESSION['error_message'] ?></span>
                        </div>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Title and Search -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">COE Faculty Evaluations</h2>
                        <p class="text-sm text-gray-500 mt-1">View and manage COE faculty training evaluations</p>
                        <p class="text-xs text-gray-900 mt-1 sliding-line">
                            <i class="ri-information-line"></i>
                            Only showing users with assigned teaching status
                        </p>
                    </div>
                    <div class="relative w-full md:w-96">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                            <i class="ri-search-line"></i>
                        </div>
                        <input type="search" id="search-input" class="search-input w-full pl-10 pr-4 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 focus:border-green-900 focus:ring-1 focus:ring-green-900" placeholder="Search by name...">
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Faculty Type Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Faculty Type:</label>
                        <div class="flex gap-2">
                            <button type="button" class="filter-btn type-filter active sliding-line" data-type="all">All Faculty</button>
                            <button type="button" class="filter-btn type-filter sliding-line" data-type="Teaching">Teaching</button>
                            <button type="button" class="filter-btn type-filter sliding-line" data-type="Non-Teaching">Non-Teaching</button>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status:</label>
                        <div class="flex gap-2 flex-wrap">
                            <button type="button" class="filter-btn status-filter active sliding-line" data-status="all">All Status</button>
                            <button type="button" class="filter-btn status-filter sliding-line" data-status="draft">Draft</button>
                            <button type="button" class="filter-btn status-filter sliding-line" data-status="submitted">Submitted</button>
                            <button type="button" class="filter-btn status-filter sliding-line" data-status="approved">Approved</button>
                            <button type="button" class="filter-btn status-filter sliding-line" data-status="rejected">Rejected</button>
                            <button type="button" class="filter-btn status-filter sliding-line" data-status="pending">Pending</button>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <?php if ($error_message): ?>
                    <div class="mb-6 glass-card p-4 border-l-4 border-red-500 animate-fade-in">
                        <div class="flex items-center">
                            <i class="ri-error-warning-line text-red-500 text-xl mr-3"></i>
                            <span class="text-gray-800 text-base font-medium"><?= $error_message ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Table -->
                <div class="card sliding-line">
                    <div class="p-6 card-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Employment Type</th>
                                        <th>Evaluation Status</th>
                                        <th>Last Evaluation</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="evaluation-table-body">
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php 
                                        while ($row = $result->fetch_assoc()): 
                                            // Determine status based on evaluation data
                                            $status = $row['evaluation_status'] ?? 'pending';
                                            $type = $row['teaching_status'] === 'Teaching' ? 'Teaching' : 'Non-Teaching';
                                            $department = $row['department'] ?? 'COE';
                                            // Gamitin ang created_at para sa last evaluation date
                                            $evaluation_date = $row['evaluation_created'] ? date('M d, Y', strtotime($row['evaluation_created'])) : 'Never evaluated';
                                            
                                            // Check if teaching status is valid
                                            $has_teaching_status = !empty($row['teaching_status']) && $row['teaching_status'] !== 'NULL';
                                        ?>
                                            <tr data-name="<?= htmlspecialchars($row['name']) ?>" 
                                                data-department="<?= htmlspecialchars($department) ?>"
                                                data-status="<?= $status ?>"
                                                data-type="<?= $type ?>"
                                                data-id="<?= $row['user_id'] ?>"
                                                class="hover:bg-gray-50 transition-colors sliding-line">
                                                <td class="font-medium text-gray-800">
                                                    <?= htmlspecialchars($row['name']) ?>
                                                </td>
                                                <td>
                                                    <span class="department-badge">
                                                        <?= htmlspecialchars($department) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($has_teaching_status): ?>
                                                    <span class="employment-type-badge">
                                                        <?= $type ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="department-badge bg-red-500">
                                                        Not Set
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="evaluation-status-badge <?= 
                                                        $status === 'submitted' ? 'status-submitted' : 
                                                        ($status === 'approved' ? 'status-approved' : 
                                                        ($status === 'rejected' ? 'status-rejected' : 
                                                        ($status === 'draft' ? 'status-draft' : 'status-pending'))) 
                                                    ?>">
                                                        <?= ucfirst($status) ?>
                                                    </span>
                                                </td>
                                                <td class="text-gray-600"><?= $evaluation_date ?></td>
                                                <td class="text-right">
                                                    <div class="flex justify-end space-x-2">
                                                        <?php if ($has_teaching_status && ($status === 'pending' || $status === 'rejected' || $status === 'draft')): ?>
                                                        <button 
                                                            type="button"
                                                            class="evaluate-btn action-btn btn-animated bg-gradient-to-r from-green-900 to-green-800 text-white hover:from-green-800 hover:to-green-700"
                                                            data-name="<?= htmlspecialchars($row['name']) ?>"
                                                            data-evaluation-id="<?= htmlspecialchars($row['evaluation_id'] ?? '') ?>"
                                                            data-user-id="<?= htmlspecialchars($row['user_id']) ?>"
                                                            data-department="<?= htmlspecialchars($department) ?>"
                                                        >
                                                            <i class="ri-star-line"></i>
                                                            <?= $status === 'draft' ? 'Continue' : 'Evaluate' ?>
                                                        </button>
                                                        <?php elseif (!$has_teaching_status): ?>
                                                        <span class="text-sm text-red-500">Set Teaching Status First</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($has_teaching_status && $status === 'draft'): ?>
                                                        <form method="POST" class="inline" onsubmit="return confirm('Send this evaluation to HR for review?')">
                                                            <input type="hidden" name="evaluation_id" value="<?= $row['evaluation_id'] ?>">
                                                            <button type="submit" name="send_to_hr" class="action-btn btn-animated bg-gradient-to-r from-green-600 to-green-700 text-white hover:from-green-700 hover:to-green-800">
                                                                <i class="ri-send-plane-line"></i>
                                                                Send to HR
                                                            </button>
                                                        </form>
                                                        <?php elseif ($has_teaching_status && $status === 'submitted'): ?>
                                                        <span class="text-sm text-gray-500">Pending Review</span>
                                                        <?php elseif ($has_teaching_status && $status === 'approved'): ?>
                                                        <span class="text-sm text-green-600">Completed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center">
                                                <div class="flex flex-col items-center justify-center py-8">
                                                    <i class="ri-file-search-line text-4xl text-gray-400 mb-4"></i>
                                                    <h3 class="text-lg font-medium text-gray-900">
                                                        <?= $error_message ? 'Database Error' : 'No COE faculty found' ?>
                                                    </h3>
                                                    <p class="mt-1 text-sm text-gray-500">
                                                        <?= $error_message ? 'Please check your database connection' : 'No COE faculty found with teaching status.' ?>
                                                    </p>
                                                    <?php if (!$error_message): ?>
                                                    <p class="mt-2 text-xs text-gray-400">
                                                        Users need to have teaching status set to appear in this list.
                                                    </p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal for Evaluation Form -->
    <div id="evaluation-modal" class="modal">
        <div class="modal-container bg-white rounded-2xl shadow-2xl">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-white">Training Program Impact Assessment</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <iframe id="evaluation-iframe" class="modal-iframe" src="about:blank"></iframe>
            </div>
        </div>
    </div>

    <script>
        // Auto-open modal if user_id is provided in URL
        <?php if ($auto_open_modal && $auto_open_user_id): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Construct the URL with parameters
            const url = `training_program_impact_assessment_form.php?name=<?= urlencode($auto_open_user_name) ?>&user_id=<?= $auto_open_user_id ?>&department=<?= urlencode($auto_open_user_department) ?>&auto_open=1`;
            
            // Set the iframe source
            document.getElementById('evaluation-iframe').src = url;
            
            // Show the modal
            document.getElementById('evaluation-modal').style.display = 'flex';
            
            // Remove the user_id from URL to prevent reopening on refresh
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        });
        <?php endif; ?>

        // Search Functionality
        document.getElementById('search-input').addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#evaluation-table-body tr');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name').toLowerCase();
                const department = row.getAttribute('data-department').toLowerCase();
                const type = row.getAttribute('data-type').toLowerCase();
                
                if (name.includes(query) || department.includes(query) || type.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Filter by Type Buttons
        document.querySelectorAll('.type-filter').forEach(button => {
            button.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('.type-filter').forEach(btn => {
                    btn.classList.remove('active', 'bg-green-900', 'text-white');
                });
                this.classList.add('active', 'bg-green-900', 'text-white');
                
                const filter = this.getAttribute('data-type');
                const rows = document.querySelectorAll('#evaluation-table-body tr');
                
                rows.forEach(row => {
                    const rowType = row.getAttribute('data-type');
                    
                    if (filter === 'all' || rowType === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // Filter by Status Buttons
        document.querySelectorAll('.status-filter').forEach(button => {
            button.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('.status-filter').forEach(btn => {
                    btn.classList.remove('active', 'bg-green-900', 'text-white');
                });
                this.classList.add('active', 'bg-green-900', 'text-white');
                
                const status = this.getAttribute('data-status');
                const rows = document.querySelectorAll('#evaluation-table-body tr');
                
                rows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    
                    if (status === 'all' || 
                        (status === 'pending' && (!rowStatus || rowStatus === 'pending')) ||
                        rowStatus === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // Modal functionality
        const modal = document.getElementById('evaluation-modal');
        const modalIframe = document.getElementById('evaluation-iframe');
        const modalClose = document.querySelector('.modal-close');
        
        // Open modal when evaluate button is clicked
        document.querySelectorAll('.evaluate-btn').forEach(button => {
            button.addEventListener('click', function() {
                const facultyName = this.getAttribute('data-name');
                const evaluationId = this.getAttribute('data-evaluation-id');
                const userId = this.getAttribute('data-user-id');
                const department = this.getAttribute('data-department');
                
                // Construct the URL with parameters
                const url = `training_program_impact_assessment_form.php?name=${encodeURIComponent(facultyName)}&evaluation_id=${encodeURIComponent(evaluationId)}&user_id=${encodeURIComponent(userId)}&department=${encodeURIComponent(department)}`;
                
                // Set the iframe source
                modalIframe.src = url;
                
                // Show the modal
                modal.style.display = 'flex';
            });
        });
        
        // Close modal when X is clicked
        modalClose.addEventListener('click', function() {
            modal.style.display = 'none';
            modalIframe.src = 'about:blank';
        });
        
        // Close modal when clicking outside the modal content
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                modalIframe.src = 'about:blank';
            }
        });

        // Function to handle messages from the iframe
        window.addEventListener('message', function(e) {
            if (e.data === 'closeModal') {
                modal.style.display = 'none';
                modalIframe.src = 'about:blank';
                // Refresh the page to update evaluation status
                window.location.reload();
            }
        });

        // Enhanced hover effects with JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('.btn-animated, button, .action-btn');
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
        });
    </script>
</body>
</html>