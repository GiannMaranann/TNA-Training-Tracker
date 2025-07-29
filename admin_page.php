<?php
session_start();
require 'config.php';

// Departments list (same order as in your chart xAxis)
$departments = ['CAS', 'CBAA', 'CCS', 'CCJE', 'CFND', 'CHMT', 'COF', 'CTE'];

// Initialize arrays to hold counts for each category
$onTime = array_fill(0, count($departments), 0);
$late = array_fill(0, count($departments), 0);
$noSubmission = array_fill(0, count($departments), 0);

// ✅ Fetch submission deadline from settings table
$deadlineQuery = $con->query("SELECT submission_deadline FROM settings WHERE id = 1");
$deadlineRow = $deadlineQuery->fetch_assoc();
$submissionDeadline = $deadlineRow['submission_deadline'] ?? null;

if ($submissionDeadline) {
    // Query using created_at instead of submission_date
    $sql = "
        SELECT u.department, 
               CASE 
                 WHEN s.created_at IS NULL THEN 'No Submission'
                 WHEN DATE(s.created_at) <= ? THEN 'On Time'
                 ELSE 'Late'
               END AS submission_status,
               COUNT(DISTINCT u.id) AS count
        FROM users u
        LEFT JOIN assessments s ON u.id = s.user_id
        GROUP BY u.department, submission_status
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

    // Total summary counts (for your dashboard display)
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

// Fetch the submission deadline from settings table
$deadlineResult = $con->query("SELECT submission_deadline FROM settings LIMIT 1");
if ($deadlineResult && $deadlineResult->num_rows > 0) {
    $deadlineRow = $deadlineResult->fetch_assoc();
    $deadline = $deadlineRow['submission_deadline'];
} else {
    $deadline = date('Y-m-d H:i:s');  // fallback to current date/time
}
$formattedDeadline = date('Y-m-d\TH:i', strtotime($deadline));

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

        // Send notification using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gianmaranan81@gmail.com'; // replace with your Gmail
            $mail->Password   = 'hlzg jxay twxn iaem';    // replace with your Gmail App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('gianmaranan81@gmail.com', 'LSPU Admin');
            $mail->addAddress($user['email'], $user['name']);

            $mail->isHTML(true);
            $mail->Subject = "LSPU Registration Approved";
            $mail->Body    = "Hello " . htmlspecialchars($user['name']) . ",<br><br>We are pleased to inform you that your registration has been successfully reviewed and approved by the administrator. You now have full access to the LSPU Training Needs Assessment System, the official platform of Laguna State Polytechnic University – Los Baños Campus. Through this system, you are encouraged to identify, evaluate, and submit your professional development needs to help foster a stronger and smarter LSPU. Should you require any assistance, please do not hesitate to contact the system support team. Welcome aboard, and thank you for taking an active role in your continued growth and development.<br><br>Thank you!";
            $mail->AltBody = "Hello " . $user['name'] . ",\n\nWe are pleased to inform you that your registration has been successfully reviewed and approved by the administrator. You now have full access to the LSPU Training Needs Assessment System, the official platform of Laguna State Polytechnic University – Los Baños Campus. Through this system, you are encouraged to identify, evaluate, and submit your professional development needs to help foster a stronger and smarter LSPU. Should you require any assistance, please do not hesitate to contact the system support team. Welcome aboard, and thank you for taking an active role in your continued growth and development.\n\nThank you!";

            $mail->send();
            $_SESSION['userActionMessage'] = "User accepted and email sent to {$user['email']}.";
        } catch (Exception $e) {
            $_SESSION['userActionMessage'] = "User accepted, but email sending failed. Error: {$mail->ErrorInfo}";
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

// Handle deadline update - IMPORTANT CHANGE HERE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_deadline'])) {
    $newDeadline = $_POST['deadline'];
    
    // Validate the deadline
    if (empty($newDeadline)) {
        $_SESSION['deadline_message'] = "Please enter a valid deadline.";
        $_SESSION['message_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }
    
    // Convert to MySQL datetime format
    $formattedDeadline = date('Y-m-d H:i:s', strtotime($newDeadline));
    
    // Update the deadline in the database
    $stmt = $con->prepare("UPDATE settings SET submission_deadline = ? WHERE id = 1");
    $stmt->bind_param("s", $formattedDeadline);
    
    if ($stmt->execute()) {
        // DON'T clear existing assessments - CHANGED FROM TRUNCATE
        $_SESSION['deadline_message'] = "Deadline updated successfully. Submission counts will be recalculated.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['deadline_message'] = "Failed to update deadline: " . $con->error;
        $_SESSION['message_type'] = "error";
    }
    
    $stmt->close();
    header("Location: admin_page.php");
    exit();
}

// Get pending users
$result = $con->query("SELECT * FROM users WHERE status='pending'");

// Initialize counters for teaching and non-teaching
$teachingTotal = 0;
$teachingOnTime = 0;
$teachingLate = 0;
$teachingNoSubmission = 0;

$nonTeachingTotal = 0;
$nonTeachingOnTime = 0;
$nonTeachingLate = 0;
$nonTeachingNoSubmission = 0;

// Convert deadline to string for query parameter
$deadlineDatetime = strtotime($deadline);
$deadlineStr = date('Y-m-d H:i:s', $deadlineDatetime);

// --- Teaching Employees ---//

// Total teaching users
$resultTeaching = $con->query("SELECT COUNT(*) AS total FROM users WHERE teaching_status = 'teaching'");
if ($resultTeaching) {
    $row = $resultTeaching->fetch_assoc();
    $teachingTotal = (int)($row['total'] ?? 0);
}

// On Time submissions (teaching)
$stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                       FROM assessments s 
                       JOIN users u ON s.user_id = u.id 
                       WHERE u.teaching_status = 'teaching' 
                       AND DATE(s.created_at) <= ?");
$stmt->bind_param("s", $deadlineStr);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    $row = $res->fetch_assoc();
    $teachingOnTime = (int)($row['count'] ?? 0);
}
$stmt->close();

// Late submissions (teaching)
$stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                       FROM assessments s 
                       JOIN users u ON s.user_id = u.id 
                       WHERE u.teaching_status = 'teaching' 
                       AND DATE(s.created_at) > ?");
$stmt->bind_param("s", $deadlineStr);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    $row = $res->fetch_assoc();
    $teachingLate = (int)($row['count'] ?? 0);
}
$stmt->close();

// No submissions (teaching)
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

// --- Non-Teaching Employees ---

// Total non-teaching users
$resultNonTeaching = $con->query("SELECT COUNT(*) AS total FROM users WHERE teaching_status = 'non teaching'");
if ($resultNonTeaching) {
    $row = $resultNonTeaching->fetch_assoc();
    $nonTeachingTotal = (int)($row['total'] ?? 0);
}

// On Time submissions (non-teaching)
$stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                       FROM assessments s 
                       JOIN users u ON s.user_id = u.id 
                       WHERE u.teaching_status = 'non teaching' 
                       AND DATE(s.created_at) <= ?");
$stmt->bind_param("s", $deadlineStr);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    $row = $res->fetch_assoc();
    $nonTeachingOnTime = (int)($row['count'] ?? 0);
}
$stmt->close();

// Late submissions (non-teaching)
$stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                       FROM assessments s 
                       JOIN users u ON s.user_id = u.id 
                       WHERE u.teaching_status = 'non teaching' 
                       AND DATE(s.created_at) > ?");
$stmt->bind_param("s", $deadlineStr);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    $row = $res->fetch_assoc();
    $nonTeachingLate = (int)($row['count'] ?? 0);
}
$stmt->close();

// No submissions (non-teaching)
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
            'none': '0px',
            'sm': '4px',
            DEFAULT: '8px',
            'md': '12px',
            'lg': '16px',
            'xl': '20px',
            '2xl': '24px',
            '3xl': '32px',
            'full': '9999px',
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
    :where([class^="ri-"])::before { content: "\f3c2"; }
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
    .animate-pulse {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
    .shadow-custom {
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .shadow-custom-hover:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .transition-all {
      transition-property: all;
      transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
      transition-duration: 150ms;
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
            <div class="w-5 h-5 flex items-center justify-center mr-3">
              <i class="ri-dashboard-line"></i>
            </div>
            Dashboard
          </a>
          <a href="user_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <div class="w-5 h-5 flex items-center justify-center mr-3">
              <i class="ri-user-line"></i>
            </div>
            Assessment Forms
          </a>
        </div>
      </nav>
      <div class="p-4">
        <a href="homepage.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-red-500 text-white transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3">
            <i class="ri-logout-box-line"></i>
          </div>
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
              <tbody id="pendingUsersTableBody">
                <?php while ($user = $result->fetch_assoc()): ?>
                  <tr class="bg-white border-b hover:bg-gray-50" data-user-id="<?= $user['id'] ?>">
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

      <!-- Dashboard and Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Users -->
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
              
              <form method="POST" action="admin_page.php">
                  <div class="relative">
                      <input
                          type="datetime-local"
                          id="newDeadline"
                          name="deadline"
                          value="<?php echo $formattedDeadline; ?>"
                          class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 w-full bg-white"
                          required
                      />
                      <div id="deadlineError" class="text-red-500 text-xs mt-1 hidden"></div>
                  </div>
                  
                  <button
                      type="submit"
                      name="update_deadline"
                      class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm transition-all flex items-center justify-center w-full mt-2"
                  >
                      <i class="ri-save-line mr-2"></i>
                      Create Deadline
                  </button>
              </form>
              
              <div class="flex items-center justify-between bg-blue-50 p-3 rounded-lg">
                  <div class="flex items-center">
                      <i class="ri-time-line text-blue-500 mr-2"></i>
                      <span class="text-sm font-medium text-gray-700">Current Deadline:</span>
                  </div>
                  <div id="submissionDeadline" class="text-blue-600 font-semibold text-sm">
                      <?php echo date('F j, Y g:i A', strtotime($deadline)); ?>
                  </div>
              </div>
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
                <span class="text-sm text-gray-500"><?= isset($onTimeCount) ? $onTimeCount : 0 ?> submitted</span>
              </div>
              <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                <div class="flex items-center">
                  <span class="status-badge badge-late">
                    <i class="ri-time-line mr-1"></i> Late
                  </span>
                </div>
                <span class="text-sm text-gray-500"><?= isset($lateCount) ? $lateCount : 0 ?> submitted</span>
              </div>
              <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                <div class="flex items-center">
                  <span class="status-badge badge-no-submission">
                    <i class="ri-close-line mr-1"></i> No Submission
                  </span>
                </div>
                <span class="text-sm text-gray-500"><?= isset($noSubmissionCount) ? $noSubmissionCount : 0 ?> missing</span>
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

  // Departments list, same as PHP $departments array
  const departments = ['CAS', 'CBAA', 'CCS', 'CCJE', 'CFND', 'CHMT', 'COF', 'CTE'];

  // These variables will be filled with PHP echo (JSON encoded arrays)
  const noSubmissionData = <?php echo json_encode($noSubmission); ?>;
  const lateSubmissionData = <?php echo json_encode($late); ?>;
  const onTimeSubmissionData = <?php echo json_encode($onTime); ?>;

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
        animation: true,
        animationDuration: 800,
        animationEasing: 'cubicOut',
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          backgroundColor: 'rgba(255, 255, 255, 0.95)',
          borderColor: '#e5e7eb',
          borderWidth: 1,
          textStyle: { color: '#1f2937' },
          formatter: function(params) {
            let result = `<div class="text-sm font-semibold mb-1">${params[0].name}</div>`;
            params.forEach(param => {
              const icon = param.seriesName === 'On Time' ? '✓' : 
                          param.seriesName === 'Late' ? '⌛' : '✗';
              const color = param.seriesName === 'On Time' ? '#10b981' : 
                            param.seriesName === 'Late' ? '#f59e0b' : '#ef4444';
              result += `
                <div class="flex items-center justify-between">
                  <span class="flex items-center">
                    <span style="color:${color};margin-right:5px">${icon}</span>
                    ${param.seriesName}
                  </span>
                  <span class="font-medium ml-4">${param.value}</span>
                </div>
              `;
            });
            return result;
          }
        },
        legend: {
          top: 30,
          data: ['On Time', 'Late', 'No Submission'],
          textStyle: { color: '#1f2937' }
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
            data: noSubmissionData,
            itemStyle: { 
              color: '#ef4444', 
              borderRadius: [4, 4, 0, 0] 
            },
            emphasis: { 
              itemStyle: { 
                color: '#dc2626',
                shadowBlur: 10,
                shadowColor: 'rgba(0, 0, 0, 0.3)'
              } 
            }
          },
          {
            name: 'Late',
            type: 'bar',
            stack: 'total',
            data: lateSubmissionData,
            itemStyle: { 
              color: '#f59e0b', 
              borderRadius: [4, 4, 0, 0] 
            },
            emphasis: { 
              itemStyle: { 
                color: '#d97706',
                shadowBlur: 10,
                shadowColor: 'rgba(0, 0, 0, 0.3)'
              } 
            }
          },
          {
            name: 'On Time',
            type: 'bar',
            stack: 'total',
            data: onTimeSubmissionData,
            itemStyle: { 
              color: '#10b981', 
              borderRadius: [4, 4, 0, 0] 
            },
            emphasis: { 
              itemStyle: { 
                color: '#059669',
                shadowBlur: 10,
                shadowColor: 'rgba(0, 0, 0, 0.3)'
              } 
            }
          }
        ]
      };
      
      trainingChart.setOption(option);
      window.addEventListener('resize', () => trainingChart.resize());
    }
  };

  // Initialize Pie Charts with hover effects
  const initPieCharts = () => {
    // Teaching Chart
    const ctxTeaching = document.getElementById('teachingOnlyChart')?.getContext('2d');
    if (ctxTeaching && typeof Chart !== 'undefined') {
      const teachingChart = new Chart(ctxTeaching, {
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
          plugins: {
            legend: { display: false },
            tooltip: { 
              enabled: true,
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.raw || 0;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = Math.round((value / total) * 100);
                  return `${label}: ${value} (${percentage}%)`;
                }
              }
            }
          },
          onHover: (event, chartElement) => {
            if (chartElement.length > 0) {
              const index = chartElement[0].index;
              const chart = chartElement[0].chart;
              chart.setActiveElements([{datasetIndex: 0, index}]);
              chart.update();
            }
          },
          onClick: (event, chartElement) => {
            if (chartElement.length > 0) {
              const index = chartElement[0].index;
              const chart = chartElement[0].chart;
              chart.toggleDataVisibility(index);
              chart.update();
            }
          }
        }
      });
    }

    // Non-Teaching Chart
    const ctxNonTeaching = document.getElementById('nonTeachingChart')?.getContext('2d');
    if (ctxNonTeaching && typeof Chart !== 'undefined') {
      const nonTeachingChart = new Chart(ctxNonTeaching, {
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
          plugins: {
            legend: { display: false },
            tooltip: { 
              enabled: true,
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.raw || 0;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = Math.round((value / total) * 100);
                  return `${label}: ${value} (${percentage}%)`;
                }
              }
            }
          },
          onHover: (event, chartElement) => {
            if (chartElement.length > 0) {
              const index = chartElement[0].index;
              const chart = chartElement[0].chart;
              chart.setActiveElements([{datasetIndex: 0, index}]);
              chart.update();
            }
          },
          onClick: (event, chartElement) => {
            if (chartElement.length > 0) {
              const index = chartElement[0].index;
              const chart = chartElement[0].chart;
              chart.toggleDataVisibility(index);
              chart.update();
            }
          }
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