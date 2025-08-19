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
    $stats['total_employees'] = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS'")->fetch_row()[0];
    $stats['teaching'] = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND teaching_status = 'Teaching'")->fetch_row()[0];
    $stats['non_teaching'] = $con->query("SELECT COUNT(*) FROM users WHERE department = 'CCS' AND teaching_status = 'Non-Teaching'")->fetch_row()[0];
    $stats['evaluated_this_year'] = $con->query("SELECT COUNT(DISTINCT a.user_id) FROM assessments a JOIN users u ON a.user_id = u.id WHERE u.department = 'CCS' AND YEAR(a.submission_date) = $current_year")->fetch_row()[0];
    $stats['pending_evaluations'] = $stats['total_employees'] - $stats['evaluated_this_year'];

    // Get unevaluated employees
    $unevaluated_result = $con->query("SELECT u.id, u.name, u.teaching_status 
                                     FROM users u 
                                     LEFT JOIN assessments a ON u.id = a.user_id AND YEAR(a.submission_date) = $current_year
                                     WHERE u.department = 'CCS' AND a.id IS NULL");
    if ($unevaluated_result) {
        while ($row = $unevaluated_result->fetch_assoc()) {
            $unevaluated_employees[] = $row;
        }
    }

    // Get recent evaluations with status
    $recent_result = $con->query("SELECT a.id, u.name, a.submission_date, a.status 
                                 FROM assessments a 
                                 JOIN users u ON a.user_id = u.id 
                                 WHERE u.department = 'CCS' 
                                 ORDER BY a.submission_date DESC LIMIT 5");
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
    // You might want to handle this error more gracefully
}

// Handle sending evaluation to admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_evaluation'])) {
    $eval_id = $_POST['eval_id'];
    
    try {
        $stmt = $con->prepare("UPDATE assessments SET status = 'sent' WHERE id = ?");
        $stmt->bind_param("i", $eval_id);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/ccs_style.css">

</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar - Simplified with only 2 items -->
        <div class="sidebar text-white sidebar-expanded" id="sidebar">
            <div class="p-5 flex items-center justify-between border-b border-blue-700">
                <div class="flex items-center space-x-3">
                    <img src="images/lspubg2.png" alt="CCS Logo" class="h-10">
                    <h1 class="text-xl font-bold hidden md:block" id="sidebar-logo-text">CCS Admin</h1>
                </div>
                <button id="sidebarToggle" class="text-blue-300 hover:text-white hidden md:block">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            <nav class="mt-4 flex flex-col h-[calc(100%-8rem)]">
                <div class="flex-1">
                    <a href="CCS.php" class="sidebar-link flex items-center px-5 py-3">
                        <i class="fas fa-tachometer-alt mr-3 text-blue-300 text-lg w-6 text-center"></i>
                        <span class="hidden md:block">Dashboard</span>
                        <span class="sidebar-tooltip">Dashboard</span>
                    </a>
                    <a href="ccs_eval.php" class="sidebar-link flex items-center px-5 py-3 active">
                        <i class="fas fa-clipboard-check mr-3 text-blue-300 text-lg w-6 text-center"></i>
                        <span class="hidden md:block">Evaluation</span>
                        <span class="sidebar-tooltip">Evaluation</span>
                    </a>
                </div>
                <div class="px-5 py-3 border-t border-blue-700">
                    <a href="?logout" class="sign-out-btn flex items-center px-3 py-2 rounded text-sm transition-colors">
                        <i class="fas fa-sign-out-alt mr-2 text-center w-5"></i>
                        <span class="hidden md:block">Sign Out</span>
                        <span class="sidebar-tooltip">Sign Out</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content flex-1 overflow-auto" id="mainContent">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="flex items-center">
                        <button id="mobileSidebarToggle" class="mr-4 text-gray-500 lg:hidden">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h2 class="text-xl font-semibold text-gray-800">Evaluation Dashboard</h2>
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
                                        <div class="bg-green-600 h-1.5 rounded-full" style="width: <?= round(($stats['teaching']/$stats['total_employees'])*100) ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1"><?= round(($stats['teaching']/$stats['total_employees'])*100) ?>% of total</p>
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
                                        <div class="bg-purple-600 h-1.5 rounded-full" style="width: <?= round(($stats['non_teaching']/$stats['total_employees'])*100) ?>%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1"><?= round(($stats['non_teaching']/$stats['total_employees'])*100) ?>% of total</p>
                                </div>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-user-tie text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar and Evaluation Progress -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Calendar -->
                    <div class="card bg-white shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Events Calendar</h3>
                                <button id="addEventBtn" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-plus mr-1"></i> Add Event
                                </button>
                            </div>
                            <div id="calendar"></div>
                        </div>
                    </div>

                    <!-- Evaluation Progress - Moved to the right side -->
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
                                            <div class="bg-green-600 h-2 rounded-full" style="width: <?= round(($stats['evaluated_this_year']/$stats['total_employees'])*100) ?>%"></div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1"><?= round(($stats['evaluated_this_year']/$stats['total_employees'])*100) ?>% completion rate</p>
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
                                            <div class="bg-yellow-500 h-2 rounded-full" style="width: <?= round(($stats['pending_evaluations']/$stats['total_employees'])*100) ?>%"></div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1"><?= round(($stats['pending_evaluations']/$stats['total_employees'])*100) ?>% remaining</p>
                                    </div>
                                </div>
                            </div>
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
                                            <a href="evaluate.php?user_id=<?= $employee['id'] ?>" class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition-colors">
                                                Evaluate
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="pagination mt-4">
                                    <button id="prevPending" disabled class="bg-gray-300 text-gray-500">Previous</button>
                                    <span class="mx-2">Page 1</span>
                                    <button id="nextPending" class="bg-blue-600 text-white">Next</button>
                                </div>
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

                    <!-- Recent Evaluations (Updated with status and send functionality) -->
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
                                                        Evaluated on <?= date('M d, Y', strtotime($evaluation['submission_date'])) ?>
                                                    </p>
                                                    <span class="evaluation-status <?= $evaluation['status'] === 'sent' ? 'status-sent' : 'status-pending' ?> mt-1">
                                                        <?= $evaluation['status'] === 'sent' ? 'Sent to Admin' : 'Pending Review' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <a href="evaluation_details.php?eval_id=<?= $evaluation['id'] ?>" class="text-sm text-blue-600 hover:underline">
                                                    View
                                                </a>
                                                <?php if ($evaluation['status'] !== 'sent'): ?>
                                                    <form method="POST" action="" class="m-0">
                                                        <input type="hidden" name="eval_id" value="<?= $evaluation['id'] ?>">
                                                        <button type="submit" name="send_evaluation" class="send-btn">
                                                            Send
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="send-btn" disabled>
                                                        Sent
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="pagination mt-4">
                                    <button id="prevRecent" disabled class="bg-gray-300 text-gray-500">Previous</button>
                                    <span class="mx-2">Page 1</span>
                                    <button id="nextRecent" class="bg-blue-600 text-white">Next</button>
                                </div>
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
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        
        // Desktop toggle
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            sidebar.classList.toggle('sidebar-expanded');
            mainContent.classList.toggle('main-content-collapsed');
            mainContent.classList.toggle('main-content');
            
            // Change icon
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('sidebar-collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            }
        });
        
        // Mobile toggle
        mobileSidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('hidden');
        });

        // Calendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'title',
                    right: 'prev,next today'
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
                    const eventType = info.event.extendedProps.type === 'assignment' ? 'Evaluation Assignment' : 
                                    info.event.extendedProps.type === 'deadline' ? 'Evaluation Deadline' : 'Event';
                    
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

            // Pagination for Pending Evaluations
            let pendingPage = 1;
            const pendingPerPage = 5;
            const totalPending = <?= count($unevaluated_employees) ?>;
            const pendingPages = Math.ceil(totalPending / pendingPerPage);

            document.getElementById('nextPending').addEventListener('click', function() {
                if (pendingPage < pendingPages) {
                    pendingPage++;
                    updatePendingList();
                }
            });

            document.getElementById('prevPending').addEventListener('click', function() {
                if (pendingPage > 1) {
                    pendingPage--;
                    updatePendingList();
                }
            });

            function updatePendingList() {
                // In a real application, you would fetch new data from the server here
                // For this example, we'll just simulate it by showing/hiding items
                const startIndex = (pendingPage - 1) * pendingPerPage;
                const endIndex = startIndex + pendingPerPage;
                
                // Update pagination controls
                document.querySelector('#pendingEvaluationsList + .pagination span').textContent = `Page ${pendingPage}`;
                document.getElementById('prevPending').disabled = pendingPage === 1;
                document.getElementById('nextPending').disabled = pendingPage === pendingPages;
            }

            // Pagination for Recent Evaluations
            let recentPage = 1;
            const recentPerPage = 5;
            const totalRecent = <?= count($recent_evaluations) ?>;
            const recentPages = Math.ceil(totalRecent / recentPerPage);

            document.getElementById('nextRecent').addEventListener('click', function() {
                if (recentPage < recentPages) {
                    recentPage++;
                    updateRecentList();
                }
            });

            document.getElementById('prevRecent').addEventListener('click', function() {
                if (recentPage > 1) {
                    recentPage--;
                    updateRecentList();
                }
            });

            function updateRecentList() {
                // In a real application, you would fetch new data from the server here
                // For this example, we'll just simulate it by showing/hiding items
                const startIndex = (recentPage - 1) * recentPerPage;
                const endIndex = startIndex + recentPerPage;
                
                // Update pagination controls
                document.querySelector('#recentEvaluationsList + .pagination span').textContent = `Page ${recentPage}`;
                document.getElementById('prevRecent').disabled = recentPage === 1;
                document.getElementById('nextRecent').disabled = recentPage === recentPages;
            }
        });

        // Show success/error messages if they exist
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Operation completed successfully!',
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