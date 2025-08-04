<?php
session_start();
require 'config.php';

// Departments list
$departments = ['CAS', 'CBAA', 'CCS', 'CCJE', 'CFND', 'CHMT', 'COF', 'CTE'];

// Initialize arrays to hold counts for each category
$onTime = array_fill(0, count($departments), 0);
$late = array_fill(0, count($departments), 0);
$noSubmission = array_fill(0, count($departments), 0);

// Fetch submission deadline from settings table
$deadlineQuery = $con->query("SELECT submission_deadline FROM settings WHERE id = 1");
$deadlineRow = $deadlineQuery->fetch_assoc();
$submissionDeadline = $deadlineRow['submission_deadline'] ?? null;

if ($submissionDeadline) {
    // Query to get submission status for each user by department
    $sql = "
        SELECT 
            u.department,
            CASE 
                WHEN a.id IS NULL THEN 'No Submission'
                WHEN a.created_at <= ? THEN 'On Time'
                ELSE 'Late'
            END AS submission_status,
            COUNT(DISTINCT u.id) AS count
        FROM 
            users u
        LEFT JOIN 
            assessments a ON u.id = a.user_id
        GROUP BY 
            u.department, submission_status
    ";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $submissionDeadline);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $deptIndex = array_search($row['department'], $departments);
            if ($deptIndex !== false) {
                switch ($row['submission_status']) {
                    case 'On Time':
                        $onTime[$deptIndex] = (int)$row['count'];
                        break;
                    case 'Late':
                        $late[$deptIndex] = (int)$row['count'];
                        break;
                    case 'No Submission':
                        $noSubmission[$deptIndex] = (int)$row['count'];
                        break;
                }
            }
        }
    }
    $stmt->close();

    // Total summary counts
    $onTimeCount = array_sum($onTime);
    $lateCount = array_sum($late);
    $noSubmissionCount = array_sum($noSubmission);
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: homepage.php");
    exit();
}

// Handle deadline update with notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_deadline'])) {
    $newDeadline = $_POST['deadline'];
    
    if (empty($newDeadline)) {
        $_SESSION['deadline_message'] = "Please enter a valid deadline.";
        $_SESSION['message_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }
    
    $formattedDeadline = date('Y-m-d H:i:s', strtotime($newDeadline));
    
    // Check if this is a new deadline or updating existing one
    $checkStmt = $con->prepare("SELECT COUNT(*) FROM settings WHERE id = 1");
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_row();
    $exists = $row[0] > 0;
    $checkStmt->close();
    
    if ($exists) {
        $stmt = $con->prepare("UPDATE settings SET submission_deadline = ? WHERE id = 1");
    } else {
        $stmt = $con->prepare("INSERT INTO settings (id, submission_deadline) VALUES (1, ?)");
    }
    
    $stmt->bind_param("s", $formattedDeadline);
    
    if ($stmt->execute()) {
        $_SESSION['deadline_message'] = "Deadline " . ($exists ? "updated" : "created") . " successfully!";
        $_SESSION['message_type'] = "success";
        
        // Prepare notification message
        $notificationMessage = "A new submission deadline has been set for the Training Needs Assessment form. Please complete your assessment before " . 
                             date('F j, Y g:i A', strtotime($formattedDeadline)) . ".";
        
        // Get all active users
        $usersQuery = $con->query("SELECT id, email, name FROM users WHERE status = 'accepted'");
        
        // Initialize counters
        $emailsSent = 0;
        $dbNotifications = 0;
        $errors = [];
        
        // Send email notifications and create database notifications
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gianmaranan81@gmail.com';
            $mail->Password   = 'hlzg jxay twxn iaem';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            
            $mail->setFrom('gianmaranan81@gmail.com', 'LSPU Admin');
            $mail->Subject = "New Training Needs Assessment Deadline";
            $mail->isHTML(true);
            $mail->Body    = $notificationMessage;
            $mail->AltBody = strip_tags($notificationMessage);
            
            while ($user = $usersQuery->fetch_assoc()) {
                try {
                    // Add recipient
                    $mail->clearAddresses();
                    $mail->addAddress($user['email'], $user['name']);
                    
                    // Send email
                    if ($mail->send()) {
                        $emailsSent++;
                    }
                    
                    // Create database notification
                    $notifStmt = $con->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $notifStmt->bind_param("is", $user['id'], $notificationMessage);
                    if ($notifStmt->execute()) {
                        $dbNotifications++;
                        
                        // Update user's notification flag
                        $updateStmt = $con->prepare("UPDATE users SET has_notification = TRUE WHERE id = ?");
                        $updateStmt->bind_param("i", $user['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                    $notifStmt->close();
                    
                } catch (Exception $e) {
                    $errors[] = "Error sending to {$user['email']}: " . $e->getMessage();
                }
            }
            
            $_SESSION['deadline_message'] .= " Notifications sent to $dbNotifications users ($emailsSent emails).";
            if (!empty($errors)) {
                $_SESSION['deadline_message'] .= " Some errors occurred: " . implode("; ", $errors);
            }
        } catch (Exception $e) {
            $_SESSION['deadline_message'] .= " Error setting up mailer: " . $e->getMessage();
            $_SESSION['message_type'] = "warning";
        }
    } else {
        $_SESSION['deadline_message'] = "Failed to update deadline.";
        $_SESSION['message_type'] = "error";
    }
    
    $stmt->close();
    header("Location: admin_page.php");
    exit();
}

// Fetch the current deadline for display
$deadlineResult = $con->query("SELECT submission_deadline FROM settings LIMIT 1");
if ($deadlineResult && $deadlineResult->num_rows > 0) {
    $deadlineRow = $deadlineResult->fetch_assoc();
    $deadline = $deadlineRow['submission_deadline'];
    $formattedDeadline = date('Y-m-d\TH:i', strtotime($deadline));
} else {
    $deadline = null;
    $formattedDeadline = '';
}

// Handle Accept/Decline actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];

    // Fetch user info
    $stmt = $con->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $_SESSION['userActionMessage'] = "User not found.";
        header("Location: admin_page.php");
        exit();
    }

    if ($action === 'accept') {
        // Update status to 'accepted'
        $stmt = $con->prepare("UPDATE users SET status = 'accepted' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Send notification email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gianmaranan81@gmail.com';
            $mail->Password   = 'hlzg jxay twxn iaem';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('gianmaranan81@gmail.com', 'LSPU Admin');
            $mail->addAddress($user['email'], $user['name']);

            $mail->isHTML(true);
            $mail->Subject = "LSPU Registration Approved";
            $mail->Body    = "Hello " . htmlspecialchars($user['name']) . ",<br><br>Your registration has been approved. You can now access the Training Needs Assessment system.";
            $mail->AltBody = "Hello " . $user['name'] . ",\n\nYour registration has been approved. You can now access the Training Needs Assessment system.";

            $mail->send();
            $_SESSION['userActionMessage'] = "User accepted and email sent to {$user['email']}.";
        } catch (Exception $e) {
            $_SESSION['userActionMessage'] = "User accepted, but email sending failed: " . $e->getMessage();
        }

    } elseif ($action === 'decline') {
        // Update status to 'declined'
        $stmt = $con->prepare("UPDATE users SET status = 'declined' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['userActionMessage'] = "User has been declined.";
    }

    header("Location: admin_page.php");
    exit();
}

// Get pending users
$result = $con->query("SELECT * FROM users WHERE status='pending'");

// Initialize counters
$teachingTotal = 0;
$teachingOnTime = 0;
$teachingLate = 0;
$teachingNoSubmission = 0;

$nonTeachingTotal = 0;
$nonTeachingOnTime = 0;
$nonTeachingLate = 0;
$nonTeachingNoSubmission = 0;

if ($submissionDeadline) {
    // Teaching employees stats
    $resultTeaching = $con->query("SELECT COUNT(*) AS total FROM users WHERE teaching_status = 'teaching'");
    if ($resultTeaching) {
        $row = $resultTeaching->fetch_assoc();
        $teachingTotal = (int)($row['total'] ?? 0);
    }

    $stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                           FROM assessments s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE u.teaching_status = 'teaching' 
                           AND s.created_at <= ?");
    $stmt->bind_param("s", $submissionDeadline);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $teachingOnTime = (int)($row['count'] ?? 0);
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                           FROM assessments s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE u.teaching_status = 'teaching' 
                           AND s.created_at > ?");
    $stmt->bind_param("s", $submissionDeadline);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $teachingLate = (int)($row['count'] ?? 0);
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(*) AS count 
                           FROM users u 
                           WHERE u.teaching_status = 'teaching' 
                           AND NOT EXISTS (
                               SELECT 1 FROM assessments s WHERE s.user_id = u.id
                           )");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $teachingNoSubmission = (int)($row['count'] ?? 0);
    }
    $stmt->close();

    // Non-teaching employees stats
    $resultNonTeaching = $con->query("SELECT COUNT(*) AS total FROM users WHERE teaching_status = 'non teaching'");
    if ($resultNonTeaching) {
        $row = $resultNonTeaching->fetch_assoc();
        $nonTeachingTotal = (int)($row['total'] ?? 0);
    }

    $stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                           FROM assessments s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE u.teaching_status = 'non teaching' 
                           AND s.created_at <= ?");
    $stmt->bind_param("s", $submissionDeadline);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $nonTeachingOnTime = (int)($row['count'] ?? 0);
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                           FROM assessments s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE u.teaching_status = 'non teaching' 
                           AND s.created_at > ?");
    $stmt->bind_param("s", $submissionDeadline);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $nonTeachingLate = (int)($row['count'] ?? 0);
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(*) AS count 
                           FROM users u 
                           WHERE u.teaching_status = 'non teaching' 
                           AND NOT EXISTS (
                               SELECT 1 FROM assessments s WHERE s.user_id = u.id
                           )");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $nonTeachingNoSubmission = (int)($row['count'] ?? 0);
    }
    $stmt->close();
}

// Get total users count
$totalUsersQuery = $con->query("SELECT COUNT(*) AS total FROM users");
$totalUsersRow = $totalUsersQuery->fetch_assoc();
$totalUsers = $totalUsersRow['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#6366f1',
            secondary: '#818cf8',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6'
          },
          borderRadius: {
            DEFAULT: '8px',
            'button': '8px'
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f3f4f6;
    }
    .chart-container {
      position: relative;
      height: 320px;
      width: 100%;
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .badge-on-time {
      background-color: #d1fae5;
      color: #065f46;
    }
    .badge-late {
      background-color: #fef3c7;
      color: #92400e;
    }
    .badge-no-submission {
      background-color: #fee2e2;
      color: #991b1b;
    }
    .shadow-custom {
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .shadow-custom-hover:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .deadline-preview {
      transition: all 0.3s ease;
    }
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background-color: #ef4444;
      color: white;
      border-radius: 9999px;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .notification-dropdown {
      max-height: 300px;
      overflow-y: auto;
      width: 320px;
      right: 0;
      z-index: 50;
    }
    .notification-item {
      transition: background-color 0.2s;
    }
    .notification-item:hover {
      background-color: #f9fafb;
    }
  </style>
</head>

<body>
<div class="flex h-screen">
  <!-- Sidebar -->
  <aside class="w-64 bg-blue-900 text-white shadow-sm">
    <div class="h-full flex flex-col">
      <div class="p-6 flex items-center">
        <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2" />
        <a href="admin_page.php" class="text-lg font-semibold text-white">Admin Dashboard</a>
      </div>
      <nav class="flex-1 px-4">
        <div class="space-y-1">
          <a href="admin_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 text-white hover:bg-blue-700 transition-all">
            <i class="ri-dashboard-line mr-3"></i>
             Dashboard
          </a>
          <a href="user_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            Assessment Forms
          </a>
          <a href="user_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            IDP Forms
          </a>
        </div>
      </nav>
      <div class="p-4">
        <a href="homepage.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-red-500 text-white transition-all">
          <i class="ri-logout-box-line mr-3"></i>
          Sign Out
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 w-full overflow-y-auto p-8 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
      <!-- Welcome Message -->
      <div class="flex justify-between items-start mb-8">
        <h1 class="text-2xl font-semibold text-gray-900">Welcome Admin</h1>
        <div class="text-sm text-gray-500">
          <?php echo date('l, F j, Y'); ?>
        </div>
      </div>

      <!-- Show deadline update message if exists -->
      <?php if (isset($_SESSION['deadline_message'])): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $_SESSION['message_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
          <?php echo $_SESSION['deadline_message']; ?>
          <?php unset($_SESSION['deadline_message']); unset($_SESSION['message_type']); ?>
        </div>
      <?php endif; ?>

      <!-- Button to toggle Pending User Registrations -->
      <button id="togglePendingBtn" class="bg-indigo-600 text-white px-4 py-2 rounded mb-6 shadow-custom hover:shadow-custom-hover transition-all flex items-center">
        <i class="ri-user-add-line mr-2"></i>
        <span>Show Pending User Registrations</span>
      </button>

      <!-- Pending User Registrations (hidden by default) -->
      <div id="pendingUsersSection" class="hidden bg-white shadow-xl border rounded-xl p-4 max-h-[500px] overflow-y-auto mb-8 transition-all">
        <h2 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
          <i class="ri-user-add-line mr-2"></i>
          Pending User Registrations
        </h2>

        <?php if (isset($_SESSION['userActionMessage'])): ?>
          <div class="mb-4 text-sm text-green-700 bg-green-100 border border-green-300 p-2 rounded flex items-center">
            <i class="ri-checkbox-circle-fill mr-2"></i>
            <?= $_SESSION['userActionMessage'] ?>
          </div>
          <?php unset($_SESSION['userActionMessage']); ?>
        <?php endif; ?>

        <?php if ($result->num_rows > 0): ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700 border">
              <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                <tr>
                  <th class="px-4 py-3 border">Name</th>
                  <th class="px-4 py-3 border">Email</th>
                  <th class="px-4 py-3 border">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($user = $result->fetch_assoc()): ?>
                  <tr class="bg-white border-b hover:bg-gray-50">
                    <td class="px-4 py-3 border"><?= htmlspecialchars($user['name']) ?></td>
                    <td class="px-4 py-3 border"><?= htmlspecialchars($user['email']) ?></td>
                    <td class="px-4 py-3 border">
                      <form method="POST" action="admin_page.php" class="flex gap-2">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" name="action" value="accept" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs flex items-center transition-all">
                          <i class="ri-check-line mr-1"></i> Accept
                        </button>
                        <button type="submit" name="action" value="decline" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs flex items-center transition-all">
                          <i class="ri-close-line mr-1"></i> Decline
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-4 text-gray-500">
            <i class="ri-inbox-line text-2xl mb-2"></i>
            <p>No pending user registrations</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Dashboard Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Users Card -->
        <div class="bg-white p-6 rounded-2xl shadow-custom hover:shadow-custom-hover transition-all flex flex-col justify-between min-h-[180px]">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-500">Total Users</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-full text-indigo-600">
              <i class="ri-user-line"></i>
            </div>
          </div>
          <div class="flex justify-center items-center">
            <div id="totalUsers" class="text-4xl font-bold text-indigo-600 bg-indigo-50 px-6 py-3 rounded-full shadow-inner">
              <?= $totalUsers ?>
            </div>
          </div>
        </div>

        <!-- Teaching Employees Card -->
        <div class="bg-white p-6 rounded-2xl shadow-custom hover:shadow-custom-hover transition-all flex flex-col justify-between min-h-[180px]">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-500">Teaching Employees</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-blue-100 rounded-full text-blue-600">
              <i class="ri-user-star-line"></i>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <div class="text-3xl font-semibold text-gray-900"><?= $teachingTotal ?></div>
            <div class="flex items-center space-x-4">
              <div class="w-20 h-20 flex-shrink-0">
                <canvas id="teachingOnlyChart" width="64" height="64"></canvas>
              </div>
              <div class="text-sm text-gray-600 space-y-1">
                <div class="flex items-center space-x-2 cursor-pointer" id="onTimeLabel">
                  <span class="w-3 h-3 bg-indigo-600 rounded-full"></span>
                  <span>On Time (<?= $teachingOnTime ?>)</span>
                </div>
                <div class="flex items-center space-x-2 cursor-pointer" id="lateLabel">
                  <span class="w-3 h-3 bg-yellow-400 rounded-full"></span>
                  <span>Late (<?= $teachingLate ?>)</span>
                </div>
                <div class="flex items-center space-x-2 cursor-pointer" id="noSubmissionLabel">
                  <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                  <span>No Submission (<?= $teachingNoSubmission ?>)</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Non-Teaching Employees Card -->
        <div class="bg-white p-6 rounded-2xl shadow-custom hover:shadow-custom-hover transition-all flex flex-col justify-between min-h-[180px]">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-500">Non-Teaching Employees</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-purple-100 rounded-full text-purple-600">
              <i class="ri-user-settings-line"></i>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <div class="text-3xl font-semibold text-gray-900"><?= $nonTeachingTotal ?></div>
            <div class="flex items-center space-x-4">
              <div class="w-20 h-20 flex-shrink-0">
                <canvas id="nonTeachingChart" width="64" height="64"></canvas>
              </div>
              <div class="text-sm text-gray-600 space-y-1">
                <div class="flex items-center space-x-2 cursor-pointer" id="nonTeachingOnTimeLabel">
                  <span class="w-3 h-3 bg-indigo-600 rounded-full"></span>
                  <span>On Time (<?= $nonTeachingOnTime ?>)</span>
                </div>
                <div class="flex items-center space-x-2 cursor-pointer" id="nonTeachingLateLabel">
                  <span class="w-3 h-3 bg-yellow-400 rounded-full"></span>
                  <span>Late (<?= $nonTeachingLate ?>)</span>
                </div>
                <div class="flex items-center space-x-2 cursor-pointer" id="nonTeachingNoSubmissionLabel">
                  <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                  <span>No Submission (<?= $nonTeachingNoSubmission ?>)</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Wrapper: Left Section -->
      <div class="flex flex-col md:flex-row gap-6 mb-8">
        <!-- Left Column for Deadline + Submission Status -->
        <div class="md:w-1/3 flex flex-col gap-6">
          <!-- Update Deadline Form Section -->
          <div class="bg-white p-6 rounded-xl shadow-custom hover:shadow-custom-hover transition-all flex flex-col gap-4 min-h-[150px]">
              <div class="flex items-center gap-2">
                  <div class="w-8 h-8 flex items-center justify-center bg-blue-100 rounded-full text-blue-600">
                      <i class="ri-calendar-line"></i>
                  </div>
                  <h3 class="text-base font-semibold text-gray-700">Submission Deadline</h3>
              </div>
              
              <form method="POST" action="admin_page.php" id="deadlineForm">
                  <div class="relative">
                      <input
                          type="datetime-local"
                          id="newDeadline"
                          name="deadline"
                          value="<?php echo $formattedDeadline; ?>"
                          class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 w-full bg-white"
                          required
                          min="<?php echo date('Y-m-d\TH:i'); ?>"
                      />
                      <div id="deadlineError" class="text-red-500 text-xs mt-1 hidden"></div>
                  </div>
                  
                  <div id="deadlinePreview" class="mt-2 bg-gray-50 p-3 rounded-lg deadline-preview hidden">
                      <p class="text-sm text-gray-600">New deadline will be:</p>
                      <p id="previewDate" class="font-medium text-gray-800"></p>
                  </div>
                  
                  <button
                      type="submit"
                      name="update_deadline"
                      class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm transition-all flex items-center justify-center w-full mt-2"
                      id="submitDeadlineBtn"
                  >
                      <i class="ri-save-line mr-2"></i>
                      <?php echo $deadline ? 'Update Deadline' : 'Create Deadline'; ?>
                  </button>
              </form>
              
              <?php if ($submissionDeadline): ?>
              <div class="flex items-center justify-between bg-blue-50 p-3 rounded-lg">
                  <div class="flex items-center">
                      <i class="ri-time-line text-blue-500 mr-2"></i>
                      <span class="text-sm font-medium text-gray-700">Current Deadline:</span>
                  </div>
                  <div id="submissionDeadline" class="text-blue-600 font-semibold text-sm">
                      <?php echo date('F j, Y g:i A', strtotime($submissionDeadline)); ?>
                  </div>
              </div>
              <?php endif; ?>
          </div>

          <!-- Submission Status -->
          <div class="bg-white p-6 rounded-xl shadow-custom hover:shadow-custom-hover transition-all flex flex-col justify-between min-h-[220px]">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-medium text-gray-700">Submission Status</h3>
              <div class="w-8 h-8 flex items-center justify-center bg-green-100 rounded-full text-green-600">
                <i class="ri-pie-chart-line"></i>
              </div>
            </div>
            <div class="space-y-3">
              <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                <div class="flex items-center">
                  <span class="status-badge badge-on-time">
                    <i class="ri-check-line mr-1"></i> On Time
                  </span>
                </div>
                <span class="text-sm text-gray-500"><?= $onTimeCount ?? 0 ?> submitted</span>
              </div>
              <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                <div class="flex items-center">
                  <span class="status-badge badge-late">
                    <i class="ri-time-line mr-1"></i> Late
                  </span>
                </div>
                <span class="text-sm text-gray-500"><?= $lateCount ?? 0 ?> submitted</span>
              </div>
              <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                <div class="flex items-center">
                  <span class="status-badge badge-no-submission">
                    <i class="ri-close-line mr-1"></i> No Submission
                  </span>
                </div>
                <span class="text-sm text-gray-500"><?= $noSubmissionCount ?? 0 ?> missing</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Training Statistics per Department -->
        <div class="bg-white p-6 rounded-xl shadow-custom hover:shadow-custom-hover transition-all w-full md:w-2/3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-500">TNA Accomplished Forms Submission</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-full text-indigo-600">
              <i class="ri-bar-chart-grouped-line"></i>
            </div>
          </div>
          <div class="chart-container">
            <div id="trainingChart" class="w-full h-full"></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Load Chart.js and ECharts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  // Toggle Pending Users Section
  const toggleBtn = document.getElementById('togglePendingBtn');
  const pendingSection = document.getElementById('pendingUsersSection');

  if (toggleBtn && pendingSection) {
    toggleBtn.addEventListener('click', () => {
      const isHidden = pendingSection.classList.toggle('hidden');
      toggleBtn.innerHTML = isHidden 
        ? '<i class="ri-user-add-line mr-2"></i><span>Show Pending User Registrations</span>' 
        : '<i class="ri-user-unfollow-line mr-2"></i><span>Hide Pending User Registrations</span>';
    });
  }

  // Deadline Form Handling
  const deadlineForm = document.getElementById('deadlineForm');
  const deadlineInput = document.getElementById('newDeadline');
  const deadlineError = document.getElementById('deadlineError');
  const deadlinePreview = document.getElementById('deadlinePreview');
  const previewDate = document.getElementById('previewDate');
  const submitDeadlineBtn = document.getElementById('submitDeadlineBtn');

  if (deadlineInput) {
    deadlineInput.addEventListener('change', () => {
      const selectedDate = new Date(deadlineInput.value);
      const now = new Date();
      
      // Validate date is in the future
      if (selectedDate <= now) {
        deadlineError.textContent = "Deadline must be in the future";
        deadlineError.classList.remove('hidden');
        deadlinePreview.classList.add('hidden');
        submitDeadlineBtn.disabled = true;
        return;
      }
      
      deadlineError.classList.add('hidden');
      submitDeadlineBtn.disabled = false;
      
      // Show preview
      const formattedDate = selectedDate.toLocaleString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      
      previewDate.textContent = formattedDate;
      deadlinePreview.classList.remove('hidden');
    });
  }

  // Confirm before updating deadline
  if (deadlineForm) {
    deadlineForm.addEventListener('submit', (e) => {
      if (!confirm('Are you sure you want to set this deadline? This will notify all users.')) {
        e.preventDefault();
      }
    });
  }

  // Departments list
  const departments = <?php echo json_encode($departments); ?>;

  // Initialize Training Chart
  const initTrainingChart = () => {
    const trainingChartElem = document.getElementById('trainingChart');
    if (trainingChartElem && typeof echarts !== 'undefined') {
      const trainingChart = echarts.init(trainingChartElem);
      
      const option = {
        title: {
          text: 'Training Submissions per Department',
          left: 'center',
          top: 5,
          textStyle: { 
            fontSize: 14, 
            fontWeight: 'bold', 
            color: '#1f2937' 
          }
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' }
        },
        legend: {
          top: 30,
          data: ['On Time', 'Late', 'No Submission']
        },
        grid: { 
          top: 70, 
          right: 10, 
          bottom: 20, 
          left: 40, 
          containLabel: true 
        },
        xAxis: {
          type: 'category',
          data: departments,
          axisLine: { lineStyle: { color: '#e5e7eb' } },
          axisLabel: { 
            color: '#6b7280',
            rotate: 0,
            interval: 0
          }
        },
        yAxis: {
          type: 'value',
          axisLine: { show: false },
          splitLine: { lineStyle: { color: '#f1f5f9' } },
          axisLabel: { color: '#6b7280' }
        },
        series: [
          {
            name: 'No Submission',
            type: 'bar',
            stack: 'total',
            data: <?php echo json_encode($noSubmission); ?>,
            itemStyle: { 
              color: '#ef4444', 
              borderRadius: [4, 4, 0, 0] 
            }
          },
          {
            name: 'Late',
            type: 'bar',
            stack: 'total',
            data: <?php echo json_encode($late); ?>,
            itemStyle: { 
              color: '#f59e0b', 
              borderRadius: [4, 4, 0, 0] 
            }
          },
          {
            name: 'On Time',
            type: 'bar',
            stack: 'total',
            data: <?php echo json_encode($onTime); ?>,
            itemStyle: { 
              color: '#10b981', 
              borderRadius: [4, 4, 0, 0] 
            }
          }
        ]
      };
      
      trainingChart.setOption(option);
      window.addEventListener('resize', () => trainingChart.resize());
    }
  };

  // Initialize Pie Charts
  const initPieCharts = () => {
    // Teaching Chart
    const ctxTeaching = document.getElementById('teachingOnlyChart')?.getContext('2d');
    if (ctxTeaching && typeof Chart !== 'undefined') {
      new Chart(ctxTeaching, {
        type: 'pie',
        data: {
          labels: ['On Time', 'Late', 'No Submission'],
          datasets: [{
            data: [
              <?= $teachingOnTime ?? 0 ?>,
              <?= $teachingLate ?? 0 ?>,
              <?= $teachingNoSubmission ?? 0 ?>
            ],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
            borderWidth: 0,
            borderRadius: 4
          }]
        },
        options: {
          responsive: false,
          plugins: { legend: { display: false } }
        }
      });
    }

    // Non-Teaching Chart
    const ctxNonTeaching = document.getElementById('nonTeachingChart')?.getContext('2d');
    if (ctxNonTeaching && typeof Chart !== 'undefined') {
      new Chart(ctxNonTeaching, {
        type: 'pie',
        data: {
          labels: ['On Time', 'Late', 'No Submission'],
          datasets: [{
            data: [
              <?= $nonTeachingOnTime ?? 0 ?>,
              <?= $nonTeachingLate ?? 0 ?>,
              <?= $nonTeachingNoSubmission ?? 0 ?>
            ],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
            borderWidth: 0,
            borderRadius: 4
          }]
        },
        options: {
          responsive: false,
          plugins: { legend: { display: false } }
        }
      });
    }
  };

  // Initialize charts
  initTrainingChart();
  initPieCharts();
});
</script>

</body>
</html>