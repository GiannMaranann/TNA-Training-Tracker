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
$events = [];

// Get CCS department statistics
try {
    // Total CCS employees (users with role 'user')
    $stats['total_employees'] = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND role = 'user'")->fetch_row()[0];
    
    // Teaching staff
    $stats['teaching'] = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND teaching_status = 'Teaching' AND role = 'user'")->fetch_row()[0];
    
    // Non-teaching staff
    $stats['non_teaching'] = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND teaching_status = 'Non-Teaching' AND role = 'user'")->fetch_row()[0];
    
    // Evaluated this year (users with evaluation_id not null)
    $stats['evaluated_this_year'] = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND evaluation_id IS NOT NULL AND role = 'user'")->fetch_row()[0];
    
    // Pending evaluations
    $stats['pending_evaluations'] = $stats['total_employees'] - $stats['evaluated_this_year'];

    // Get unevaluated employees (CCS users with evaluation_id IS NULL)
    $unevaluated_result = $con->query("SELECT id, name, teaching_status 
                                     FROM users 
                                     WHERE department = 'CCS' 
                                     AND evaluation_id IS NULL 
                                     AND role = 'user'
                                     AND teaching_status IS NOT NULL
                                     AND teaching_status != ''");
    if ($unevaluated_result) {
        while ($row = $unevaluated_result->fetch_assoc()) {
            $unevaluated_employees[] = $row;
        }
    }

    // Get recent evaluations (users with evaluation_id not null)
    $recent_result = $con->query("SELECT u.id, u.name, u.evaluation_id, u.teaching_status
                                 FROM users u
                                 WHERE u.department = 'CCS' 
                                 AND u.evaluation_id IS NOT NULL
                                 AND u.role = 'user'
                                 ORDER BY u.evaluation_id DESC LIMIT 5");
    if ($recent_result) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_evaluations[] = $row;
        }
    }

    // Get events for calendar
    $events_result = $con->query("SELECT * FROM events ORDER BY event_date ASC");
    if ($events_result) {
        while ($row = $events_result->fetch_assoc()) {
            $events[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Handle sending evaluation to admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_evaluation'])) {
    $user_id = $_POST['user_id'];
    
    try {
        // For now, we'll just set a flag in the users table
        // In a real system, you might want to create a separate evaluations table
        $stmt = $con->prepare("UPDATE users SET evaluation_status = 'sent_to_hr' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            header("Location: CCS.php?success=1");
            exit();
        } else {
            $error_message = "Failed to update evaluation status";
        }
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .sidebar-link.active {
            background-color: rgb(30 64 175);
        }
        .evaluation-status {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-sent {
            background-color: #ddd6fe;
            color: #5b21b6;
        }
        .send-btn {
            background-color: #3b82f6;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .send-btn:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        .send-btn:hover:not(:disabled) {
            background-color: #2563eb;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 1.5rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .card {
            border-radius: 0.5rem;
        }
        .pagination button {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        /* Calendar styling */
        #calendar {
            font-size: 0.8rem;
        }
        .fc .fc-toolbar-title {
            font-size: 1rem;
        }
        .fc .fc-button {
            padding: 0.2rem 0.5rem;
            font-size: 0.8rem;
        }
        .fc .fc-daygrid-day-number {
            padding: 2px;
            font-size: 0.8rem;
        }
        .fc .fc-daygrid-day-frame {
            min-height: 50px;
        }
        .fc .fc-col-header-cell-cushion {
            padding: 2px 4px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-blue-900 text-white shadow-sm flex-shrink-0">
            <div class="h-full flex flex-col">
                <div class="p-6 flex items-center">
                    <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2" />
                    <span class="text-lg font-semibold text-white">CCS Admin</span>
                </div>
                <nav class="flex-1 px-4">
                    <div class="space-y-1">
                        <a href="CCS.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 sidebar-link active">
                            <i class="ri-dashboard-line mr-3 w-5 h-5 flex-shrink-0"></i>
                            <span class="whitespace-nowrap">Dashboard</span>
                        </a>
                        <a href="ccs_eval.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 sidebar-link">
                            <i class="ri-file-list-3-line w-5 h-5 mr-3 flex-shrink-0"></i>
                            <span class="whitespace-nowrap">Evaluation</span>
                        </a>
                    </div>
                </nav>
                <div class="p-4">
                    <a href="?logout=true" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-red-500 text-white sidebar-link">
                        <i class="ri-logout-box-line mr-3 w-5 h-5 flex-shrink-0"></i>
                        <span class="whitespace-nowrap">Sign Out</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="flex items-center">
                        <button id="mobileSidebarToggle" class="mr-4 text-gray-500 lg:hidden">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h2 class="text-xl font-semibold text-gray-800">CCS Admin Dashboard</h2>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <img src="images/ccs.png" alt="User" class="rounded-full h-8 w-8 border-2 border-blue-500">
                            <span class="ml-2 text-sm font-medium"><?= htmlspecialchars($user['name']) ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="card bg-white shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Total Employees</p>
                                <h3 class="text-2xl font-bold text-gray-800 mt-1"><?= $stats['total_employees'] ?></h3>
                                <div class="mt-3 flex space-x-2">
                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">CCS Department</span>
                                </div>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-white shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Teaching Staff</p>
                                <h3 class="text-2xl font-bold text-gray-800 mt-1"><?= $stats['teaching'] ?></h3>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-green-600 h-1.5 rounded-full" style="width: <?= $stats['total_employees'] > 0 ? round(($stats['teaching']/$stats['total_employees'])*100) : 0 ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1"><?= $stats['total_employees'] > 0 ? round(($stats['teaching']/$stats['total_employees'])*100) : 0 ?>% of total</p>
                                </div>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-white shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Non-Teaching Staff</p>
                                <h3 class="text-2xl font-bold text-gray-800 mt-1"><?= $stats['non_teaching'] ?></h3>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-purple-600 h-1.5 rounded-full" style="width: <?= $stats['total_employees'] > 0 ? round(($stats['non_teaching']/$stats['total_employees'])*100) : 0 ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1"><?= $stats['total_employees'] > 0 ? round(($stats['non_teaching']/$stats['total_employees'])*100) : 0 ?>% of total</p>
                                </div>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-user-tie text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evaluation Progress and Calendar -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Evaluation Progress - Now takes 2/3 width -->
                    <div class="card bg-white shadow lg:col-span-2">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Evaluation Progress (<?= $current_year ?>)</h3>
                                <div class="flex space-x-2">
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full"><?= $stats['evaluated_this_year'] ?> Evaluated</span>
                                    <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full"><?= $stats['pending_evaluations'] ?> Pending</span>
                                </div>
                            </div>
                            
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-600">Completed Evaluations</p>
                                            <p class="text-2xl font-bold text-gray-800"><?= $stats['evaluated_this_year'] ?></p>
                                        </div>
                                        <div class="bg-green-100 p-2 rounded-full">
                                            <i class="fas fa-check-circle text-green-600"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" style="width: <?= $stats['total_employees'] > 0 ? round(($stats['evaluated_this_year']/$stats['total_employees'])*100) : 0 ?>%"></div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1"><?= $stats['total_employees'] > 0 ? round(($stats['evaluated_this_year']/$stats['total_employees'])*100) : 0 ?>% completion rate</p>
                                    </div>
                                </div>
                                <div class="bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-600">Pending Evaluations</p>
                                            <p class="text-2xl font-bold text-gray-800"><?= $stats['pending_evaluations'] ?></p>
                                        </div>
                                        <div class="bg-yellow-100 p-2 rounded-full">
                                            <i class="fas fa-clock text-yellow-600"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-yellow-500 h-2 rounded-full" style="width: <?= $stats['total_employees'] > 0 ? round(($stats['pending_evaluations']/$stats['total_employees'])*100) : 0 ?>%"></div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1"><?= $stats['total_employees'] > 0 ? round(($stats['pending_evaluations']/$stats['total_employees'])*100) : 0 ?>% remaining</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar - Now takes 1/3 width and is on the right -->
                    <div class="card bg-white shadow">
                        <div class="p-4">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-md font-semibold text-gray-800">Events Calendar</h3>
                                <button id="addEventBtn" class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-plus mr-1"></i> Add Event
                                </button>
                            </div>
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>

                <!-- Unevaluated Employees and Recent Evaluations -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Unevaluated Employees -->
                    <div class="card bg-white shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Pending Evaluations</h3>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-sm font-medium">
                                    <?= count($unevaluated_employees) ?> Employees
                                </span>
                            </div>
                            
                            <?php if (!empty($unevaluated_employees)): ?>
                                <div class="space-y-3" id="pendingEvaluationsList">
                                    <?php foreach (array_slice($unevaluated_employees, 0, 5) as $employee): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-600"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($employee['name']) ?></p>
                                                    <p class="text-xs text-gray-500">
                                                        <?= $employee['teaching_status'] === 'Teaching' ? 
                                                            '<span class="text-green-600"><i class="fas fa-chalkboard-teacher mr-1"></i>Teaching</span>' : 
                                                            '<span class="text-purple-600"><i class="fas fa-user-tie mr-1"></i>Non-Teaching</span>' ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <a href="ccs_eval.php?user_id=<?= $employee['id'] ?>" class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition-colors">
                                                Evaluate
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($unevaluated_employees) > 5): ?>
                                <div class="pagination mt-4">
                                    <button id="prevPending" disabled class="bg-gray-300 text-gray-500">Previous</button>
                                    <span class="mx-2">Page 1</span>
                                    <button id="nextPending" class="bg-blue-600 text-white">Next</button>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="bg-green-100 text-green-800 p-3 rounded-full inline-block mb-3">
                                        <i class="fas fa-check-circle text-xl"></i>
                                    </div>
                                    <h4 class="text-lg font-medium text-gray-800">All Evaluations Completed!</h4>
                                    <p class="text-gray-500 mt-1">All CCS employees have been evaluated for <?= $current_year ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Evaluations -->
                    <div class="card bg-white shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Recent Evaluations</h3>
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-sm font-medium">
                                    Last 5 Evaluations
                                </span>
                            </div>
                            
                            <?php if (!empty($recent_evaluations)): ?>
                                <div class="space-y-3" id="recentEvaluationsList">
                                    <?php foreach (array_slice($recent_evaluations, 0, 5) as $evaluation): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user-check text-green-600"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($evaluation['name']) ?></p>
                                                    <p class="text-xs text-gray-500">
                                                        <?= $evaluation['teaching_status'] === 'Teaching' ? 
                                                            '<span class="text-green-600"><i class="fas fa-chalkboard-teacher mr-1"></i>Teaching</span>' : 
                                                            '<span class="text-purple-600"><i class="fas fa-user-tie mr-1"></i>Non-Teaching</span>' ?>
                                                    </p>
                                                    <span class="evaluation-status status-sent mt-1">
                                                        Evaluation Completed
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <a href="evaluation_details.php?user_id=<?= $evaluation['id'] ?>" class="text-sm text-blue-600 hover:underline">
                                                    View
                                                </a>
                                                <form method="POST" action="" class="m-0">
                                                    <input type="hidden" name="user_id" value="<?= $evaluation['id'] ?>">
                                                    <button type="submit" name="send_evaluation" class="send-btn" onclick="return confirm('Send this evaluation to HR for review?')">
                                                        Send to HR
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($recent_evaluations) > 5): ?>
                                <div class="pagination mt-4">
                                    <button id="prevRecent" disabled class="bg-gray-300 text-gray-500">Previous</button>
                                    <span class="mx-2">Page 1</span>
                                    <button id="nextRecent" class="bg-blue-600 text-white">Next</button>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="far fa-file-alt text-2xl mb-2"></i>
                                    <p>No evaluations recorded yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Event</h2>
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2" for="event_title">Event Title*</label>
                    <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           id="event_title" name="event_title" type="text" placeholder="Enter event title" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2" for="event_date">Date*</label>
                    <input class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           id="event_date" name="event_date" type="date" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2" for="event_type">Event Type*</label>
                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           id="event_type" name="event_type" required>
                        <option value="general">General Event</option>
                        <option value="meeting">Staff Meeting</option>
                        <option value="deadline">Evaluation Deadline</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2" for="event_description">Description</label>
                    <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              id="event_description" name="event_description" rows="3" placeholder="Enter event description"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500" id="cancelEvent">
                        Cancel
                    </button>
                    <button type="submit" name="add_event" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Add Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebar = document.querySelector('aside');
        
        mobileSidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('hidden');
        });

        // Calendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: ''
                },
                height: 'auto',
                events: [
                    <?php foreach ($events as $event): ?>
                        {
                            title: '<?= addslashes($event['title']) ?>',
                            start: '<?= $event['event_date'] ?>',
                            description: '<?= addslashes($event['description']) ?>',
                            color: '<?= 
                                ($event['type'] ?? 'general') === 'deadline' ? '#ef4444' : '#3b82f6'
                            ?>',
                            extendedProps: {
                                type: '<?= $event['type'] ?? 'general' ?>'
                            }
                        },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    const eventType = info.event.extendedProps.type === 'deadline' ? 'Evaluation Deadline' : 'Event';
                    
                    const description = info.event.extendedProps.description ? 
                        `<p class="mt-2 text-gray-600">${info.event.extendedProps.description}</p>` : 
                        '<p class="mt-2 text-gray-400">No description provided</p>';
                    
                    Swal.fire({
                        title: info.event.title,
                        html: `
                            <div class="text-left">
                                <p class="text-sm text-gray-500"><strong>Type:</strong> ${eventType}</p>
                                <p class="text-sm text-gray-500"><strong>Date:</strong> ${info.event.start.toLocaleDateString()}</p>
                                ${description}
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonColor: '#3b82f6',
                        confirmButtonText: 'Close'
                    });
                    info.jsEvent.preventDefault();
                },
                eventClassNames: function(arg) {
                    return ['cursor-pointer', 'hover:shadow-md'];
                },
                dayCellClassNames: function(arg) {
                    return ['hover:bg-gray-50'];
                }
            });
            calendar.render();

            // Event Modal
            const eventModal = document.getElementById('eventModal');
            const eventBtn = document.getElementById('addEventBtn');
            const eventSpan = document.querySelector("#eventModal .close");
            const cancelEventBtn = document.getElementById("cancelEvent");

            eventBtn.onclick = function() {
                eventModal.style.display = "block";
                // Set today's date as default
                document.getElementById('event_date').valueAsDate = new Date();
            }

            eventSpan.onclick = function() {
                eventModal.style.display = "none";
            }

            cancelEventBtn.onclick = function() {
                eventModal.style.display = "none";
            }

            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target == eventModal) {
                    eventModal.style.display = "none";
                }
            }
        });

        // Show success/error messages if they exist
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Evaluation sent to HR successfully!',
                confirmButtonColor: '#3b82f6'
            });
        <?php elseif (isset($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= $error_message ?>',
                confirmButtonColor: '#3b82f6'
            });
        <?php endif; ?>
    </script>
</body>
</html>