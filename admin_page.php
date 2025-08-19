<?php
session_start();
require 'config.php';

// Departments list
$departments = ['CAS', 'CBAA', 'CCS', 'CCJE', 'CFND', 'CHMT', 'COF', 'CTE'];

// Check if admin is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

// Check if user is admin (any admin role)
$adminRoles = ['admin', 'admin_cas', 'admin_car', 'admin_chas', 'admin_cdp', 'admin_chrd', 'admin_chmt', 'admin_cof'];
if (!in_array($_SESSION['role'], $adminRoles)) {
    header("Location: homepage.php");
    exit();
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_deadline'])) {
        $newDeadline = $_POST['deadline'];
        $title = $_POST['title'] ?? 'Training Needs Assessment Deadline';
        $description = $_POST['description'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($newDeadline)) {
            $_SESSION['deadline_message'] = "Please enter a valid deadline.";
            $_SESSION['message_type'] = "error";
            header("Location: admin_page.php");
            exit();
        }
        
        $formattedDeadline = date('Y-m-d H:i:s', strtotime($newDeadline));
        
        // If marking as active, deactivate all other deadlines first
        if ($isActive) {
            $con->query("UPDATE settings SET is_active = 0");
        }
        
        // Insert new deadline
        $stmt = $con->prepare("INSERT INTO settings (submission_deadline, title, description, is_active, created_at, updated_at, allow_submissions) 
                              VALUES (?, ?, ?, ?, NOW(), NOW(), 1)");
        $stmt->bind_param("sssi", $formattedDeadline, $title, $description, $isActive);
        
        if ($stmt->execute()) {
            $deadlineId = $stmt->insert_id;
            $_SESSION['deadline_message'] = "New deadline added successfully!";
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
                        $notifStmt = $con->prepare("INSERT INTO notifications (user_id, message, related_id, related_type) 
                                                    VALUES (?, ?, ?, 'deadline')");
                        $notifStmt->bind_param("isi", $user['id'], $notificationMessage, $deadlineId);
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
            $_SESSION['deadline_message'] = "Failed to add new deadline.";
            $_SESSION['message_type'] = "error";
        }
        
        $stmt->close();
        header("Location: admin_page.php?updated=" . time());
        exit();
    }
    elseif (isset($_POST['set_active'])) {
        $deadlineId = intval($_POST['deadline_id']);
        
        // Deactivate all deadlines first
        $con->query("UPDATE settings SET is_active = 0");
        
        // Activate the selected deadline
        $stmt = $con->prepare("UPDATE settings SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $deadlineId);
        
        if ($stmt->execute()) {
            $_SESSION['deadline_message'] = "Active deadline updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['deadline_message'] = "Failed to update active deadline.";
            $_SESSION['message_type'] = "error";
        }
        
        $stmt->close();
        header("Location: admin_page.php");
        exit();
    }
    elseif (isset($_POST['toggle_submissions'])) {
        $deadlineId = intval($_POST['deadline_id']);
        $currentStatus = intval($_POST['current_status']);
        $newStatus = $currentStatus ? 0 : 1;
        
        $stmt = $con->prepare("UPDATE settings SET allow_submissions = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $deadlineId);
        
        if ($stmt->execute()) {
            $_SESSION['deadline_message'] = "Submissions are now " . ($newStatus ? "OPEN" : "CLOSED") . " for this deadline.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['deadline_message'] = "Failed to update submission status.";
            $_SESSION['message_type'] = "error";
        }
        
        $stmt->close();
        header("Location: admin_page.php");
        exit();
    }
    elseif (isset($_POST['user_id'], $_POST['action'])) {
        // Handle Accept/Decline actions
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
}

// Get active deadline
$activeDeadline = $con->query("SELECT * FROM settings WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$submissionDeadline = $activeDeadline['submission_deadline'] ?? null;
$deadlineTitle = $activeDeadline['title'] ?? 'Training Needs Assessment Deadline';
$deadlineDescription = $activeDeadline['description'] ?? '';
$allowSubmissions = (int)($activeDeadline['allow_submissions'] ?? 0);
$submissionStatus = $allowSubmissions ? 'OPEN' : 'CLOSED';

// Get all deadlines
$deadlines = $con->query("SELECT * FROM settings ORDER BY submission_deadline DESC");

// Get pending users
$pendingUsers = $con->query("SELECT * FROM users WHERE status='pending'");

// Initialize arrays to hold counts for each category
$onTime = array_fill(0, count($departments), 0);
$late = array_fill(0, count($departments), 0);
$noSubmission = array_fill(0, count($departments), 0);

if ($submissionDeadline) {
    // Query to get submission status for each user by department
    $sql = "
        SELECT 
            u.department,
            CASE 
                WHEN a.id IS NULL THEN 'No Submission'
                WHEN a.submission_date <= ? THEN 'On Time'
                ELSE 'Late'
            END AS submission_status,
            COUNT(DISTINCT u.id) AS count
        FROM 
            users u
        LEFT JOIN 
            assessments a ON u.id = a.user_id AND a.deadline_id = ?
        GROUP BY 
            u.department, submission_status
    ";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("si", $submissionDeadline, $activeDeadline['id']);
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
                           FROM assessments a 
                           JOIN users u ON a.user_id = u.id 
                           WHERE u.teaching_status = 'teaching' 
                           AND a.submission_date <= ?
                           AND a.deadline_id = ?");
    $stmt->bind_param("si", $submissionDeadline, $activeDeadline['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $teachingOnTime = (int)($row['count'] ?? 0);
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                           FROM assessments a 
                           JOIN users u ON a.user_id = u.id 
                           WHERE u.teaching_status = 'teaching' 
                           AND a.submission_date > ?
                           AND a.deadline_id = ?");
    $stmt->bind_param("si", $submissionDeadline, $activeDeadline['id']);
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
                               SELECT 1 FROM assessments a WHERE a.user_id = u.id AND a.deadline_id = ?
                           )");
    $stmt->bind_param("i", $activeDeadline['id']);
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
                           FROM assessments a 
                           JOIN users u ON a.user_id = u.id 
                           WHERE u.teaching_status = 'non teaching' 
                           AND a.submission_date <= ?
                           AND a.deadline_id = ?");
    $stmt->bind_param("si", $submissionDeadline, $activeDeadline['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $nonTeachingOnTime = (int)($row['count'] ?? 0);
    }
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(DISTINCT u.id) AS count 
                           FROM assessments a 
                           JOIN users u ON a.user_id = u.id 
                           WHERE u.teaching_status = 'non teaching' 
                           AND a.submission_date > ?
                           AND a.deadline_id = ?");
    $stmt->bind_param("si", $submissionDeadline, $activeDeadline['id']);
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
                               SELECT 1 FROM assessments a WHERE a.user_id = u.id AND a.deadline_id = ?
                           )");
    $stmt->bind_param("i", $activeDeadline['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $nonTeachingNoSubmission = (int)($row['count'] ?? 0);
    }
    $stmt->close();
}

// Get total users count
$totalUsersQuery = $con->query("SELECT COUNT(*) AS total FROM users WHERE status = 'accepted'");
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
          },
          fontFamily: {
            'poppins': ['Poppins', 'sans-serif']
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
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
    .deadline-item {
      transition: all 0.2s;
    }
    .deadline-item:hover {
      transform: translateY(-2px);
    }
    .active-deadline {
      border-left: 4px solid #6366f1;
    }
    .submission-item {
      transition: all 0.2s;
    }
    .submission-item:hover {
      background-color: #f8fafc;
    }
    .pagination {
      display: flex;
      justify-content: center;
      margin-top: 1rem;
    }
    .pagination button {
      margin: 0 0.25rem;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
    }
    .pagination button.active {
      background-color: #6366f1;
      color: white;
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
        <div class="space-y-2">
          <a href="admin_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 text-white hover:bg-blue-700 transition-all">
            <i class="ri-dashboard-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">Dashboard</span>
          </a>
          <a href="Assessment Form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">Assessment Forms</span>
          </a>
          <a href="Individual Development Plan Form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">IDP Forms</span>
          </a>
          <a href="Assessment Form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">Evaluation Forms</span>
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
      <div class="flex justify-between items-center mb-8 border-b pb-3">
        <h1 class="text-3xl font-bold text-gray-900">Welcome, Admin</h1>
        <div class="text-sm text-gray-500 font-medium">
          <?php echo date('l, F j, Y'); ?>
        </div>
      </div>

      <!-- Show deadline update message -->
      <?php if (isset($_SESSION['deadline_message'])): ?>
        <div class="mb-6 p-4 rounded-lg border <?php echo $_SESSION['message_type'] === 'success' ? 'bg-green-50 text-green-700 border-green-300' : 'bg-red-50 text-red-700 border-red-300'; ?>">
          <i class="ri-information-line mr-2"></i>
          <?php echo $_SESSION['deadline_message']; ?>
          <?php unset($_SESSION['deadline_message']); unset($_SESSION['message_type']); ?>
        </div>
      <?php endif; ?>

      <!-- Toggle Pending Users Button -->
      <button id="togglePendingBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg mb-6 shadow-md flex items-center gap-2 transition-all">
        <i class="ri-user-add-line text-lg"></i>
        <span>Show Pending User Registrations</span>
      </button>

      <!-- Pending Users Section -->
      <div id="pendingUsersSection" class="hidden bg-white shadow-lg border rounded-xl p-5 mb-8 transition-all">
        <h2 class="text-lg font-bold mb-4 text-gray-800 flex items-center gap-2">
          <i class="ri-user-add-line"></i> Pending User Registrations
        </h2>

        <?php if (isset($_SESSION['userActionMessage'])): ?>
          <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 p-3 rounded flex items-center gap-2">
            <i class="ri-checkbox-circle-fill"></i>
            <?= $_SESSION['userActionMessage'] ?>
          </div>
          <?php unset($_SESSION['userActionMessage']); ?>
        <?php endif; ?>

        <?php if ($pendingUsers && $pendingUsers->num_rows > 0): ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-gray-700 border border-gray-200 rounded-lg overflow-hidden">
              <thead class="bg-gray-100 text-xs uppercase font-semibold text-gray-600">
                <tr>
                  <th class="px-4 py-3 border">Name</th>
                  <th class="px-4 py-3 border">Email</th>
                  <th class="px-4 py-3 border text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($user = $pendingUsers->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 border"><?= htmlspecialchars($user['name']) ?></td>
                    <td class="px-4 py-3 border"><?= htmlspecialchars($user['email']) ?></td>
                    <td class="px-4 py-3 border text-center">
                      <form method="POST" action="admin_page.php" class="flex gap-2 justify-center">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" name="action" value="accept" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-md text-xs flex items-center gap-1">
                          <i class="ri-check-line"></i> Accept
                        </button>
                        <button type="submit" name="action" value="decline" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-xs flex items-center gap-1">
                          <i class="ri-close-line"></i> Decline
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

      <!-- Dashboard Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Users -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-500">Total Users</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-full text-indigo-600">
              <i class="ri-user-line"></i>
            </div>
          </div>
          <div class="mt-4 flex justify-center">
            <div class="text-4xl font-bold text-indigo-600 bg-indigo-50 px-6 py-3 rounded-full shadow-inner">
              <?= $totalUsers ?>
            </div>
          </div>
        </div>

        <!-- Teaching -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-500">Teaching Employees</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-blue-100 rounded-full text-blue-600">
              <i class="ri-user-star-line"></i>
            </div>
          </div>
          <div class="mt-4 flex items-center gap-4">
            <div class="text-3xl font-semibold"><?= $teachingTotal ?></div>
            <canvas id="teachingOnlyChart" width="64" height="64"></canvas>
          </div>
          <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
            <div>
              <span class="badge-on-time status-badge">On Time: <?= $teachingOnTime ?></span>
            </div>
            <div>
              <span class="badge-late status-badge">Late: <?= $teachingLate ?></span>
            </div>
            <div>
              <span class="badge-no-submission status-badge">No Submission: <?= $teachingNoSubmission ?></span>
            </div>
          </div>
        </div>

        <!-- Non-Teaching -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-500">Non-Teaching Employees</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-purple-100 rounded-full text-purple-600">
              <i class="ri-user-settings-line"></i>
            </div>
          </div>
          <div class="mt-4 flex items-center gap-4">
            <div class="text-3xl font-semibold"><?= $nonTeachingTotal ?></div>
            <canvas id="nonTeachingChart" width="64" height="64"></canvas>
          </div>
          <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
            <div>
              <span class="badge-on-time status-badge">On Time: <?= $nonTeachingOnTime ?></span>
            </div>
            <div>
              <span class="badge-late status-badge">Late: <?= $nonTeachingLate ?></span>
            </div>
            <div>
              <span class="badge-no-submission status-badge">No Submission: <?= $nonTeachingNoSubmission ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Lower Section -->
      <div class="flex flex-col md:flex-row gap-6">
        <!-- Left Column -->
        <div class="md:w-1/3 flex flex-col gap-6">
          <!-- Deadline Card -->
          <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-medium text-gray-500">Current Deadline</h3>
              <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-full text-indigo-600">
                <i class="ri-timer-line"></i>
              </div>
            </div>
            
            <div class="mb-4">
              <h4 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($deadlineTitle) ?></h4>
              <?php if ($submissionDeadline): ?>
                <p class="text-sm text-gray-600 mt-1">
                  Deadline: <?= date('F j, Y g:i A', strtotime($submissionDeadline)) ?>
                </p>
                <?php if (!empty($deadlineDescription)): ?>
                  <p class="text-sm text-gray-600 mt-2">
                    <?= htmlspecialchars($deadlineDescription) ?>
                  </p>
                <?php endif; ?>
              <?php else: ?>
                <p class="text-sm text-gray-600 mt-1">No active deadline set</p>
              <?php endif; ?>
            </div>
            
            <button onclick="document.getElementById('deadlineModal').classList.remove('hidden')" 
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg transition flex items-center justify-center gap-2">
              <i class="ri-edit-line"></i>
              <span>Set New Deadline</span>
            </button>
          </div>

          <!-- Submission Status Card -->
          <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-medium text-gray-500">Overall Submission Status</h3>
              <div class="w-8 h-8 flex items-center justify-center bg-green-100 rounded-full text-green-600">
                <i class="ri-checkbox-circle-line"></i>
              </div>
            </div>
            
            <div class="space-y-4">
              <div>
                <div class="flex justify-between text-sm mb-1">
                  <span class="font-medium">On Time Submissions</span>
                  <span><?= $onTimeCount ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                  <div class="bg-green-500 h-2.5 rounded-full" 
                       style="width: <?= $totalUsers > 0 ? ($onTimeCount / $totalUsers * 100) : 0 ?>%"></div>
                </div>
              </div>
              
              <div>
                <div class="flex justify-between text-sm mb-1">
                  <span class="font-medium">Late Submissions</span>
                  <span><?= $lateCount ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                  <div class="bg-yellow-500 h-2.5 rounded-full" 
                       style="width: <?= $totalUsers > 0 ? ($lateCount / $totalUsers * 100) : 0 ?>%"></div>
                </div>
              </div>
              
              <div>
                <div class="flex justify-between text-sm mb-1">
                  <span class="font-medium">No Submission</span>
                  <span><?= $noSubmissionCount ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                  <div class="bg-red-500 h-2.5 rounded-full" 
                       style="width: <?= $totalUsers > 0 ? ($noSubmissionCount / $totalUsers * 100) : 0 ?>%"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition w-full md:w-2/3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-500">TNA Accomplished Forms Submission</h3>
            <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-full text-indigo-600">
              <i class="ri-bar-chart-grouped-line"></i>
            </div>
          </div>
          <div class="h-[300px]">
            <div id="trainingChart" class="w-full h-full"></div>
          </div>
        </div>
      </div>

      <!-- Deadline History Section -->
      <div class="mt-8 bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-medium text-gray-500">Deadline History</h3>
          <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-full text-indigo-600">
            <i class="ri-history-line"></i>
          </div>
        </div>
        
        <div class="space-y-3">
          <?php if ($deadlines && $deadlines->num_rows > 0): ?>
            <?php while ($deadline = $deadlines->fetch_assoc()): 
              // Get submissions count for this deadline
              $submissionsQuery = $con->prepare("SELECT COUNT(*) AS count FROM assessments WHERE deadline_id = ?");
              $submissionsQuery->bind_param("i", $deadline['id']);
              $submissionsQuery->execute();
              $submissionsResult = $submissionsQuery->get_result();
              $submissionsCount = $submissionsResult->fetch_assoc()['count'];
              $submissionsQuery->close();
            ?>
              <div class="deadline-item p-4 border rounded-lg <?= $deadline['is_active'] ? 'active-deadline bg-indigo-50' : '' ?>">
                <div class="flex justify-between items-center">
                  <div>
                    <h4 class="font-medium text-gray-800"><?= htmlspecialchars($deadline['title']) ?></h4>
                    <p class="text-sm text-gray-600">
                      <?= date('F j, Y g:i A', strtotime($deadline['submission_deadline'])) ?>
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                      Submissions: <?= $submissionsCount ?>
                      <button onclick="showSubmissions(<?= $deadline['id'] ?>)" class="text-indigo-600 hover:text-indigo-800 text-xs ml-2">
                        <i class="ri-eye-line"></i> View Submissions
                      </button>
                    </p>
                    <?php if (!empty($deadline['description'])): ?>
                      <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($deadline['description']) ?></p>
                    <?php endif; ?>
                  </div>
                  <div class="flex gap-2">
                    <?php if (!$deadline['is_active']): ?>
                      <form method="POST" action="admin_page.php">
                        <input type="hidden" name="deadline_id" value="<?= $deadline['id'] ?>">
                        <button type="submit" name="set_active" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded flex items-center gap-1">
                          <i class="ri-check-line"></i> Set Active
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-xs bg-green-600 text-white px-3 py-1 rounded flex items-center gap-1">
                        <i class="ri-checkbox-circle-line"></i> Active
                      </span>
                    <?php endif; ?>
                    <form method="POST" action="admin_page.php">
                      <input type="hidden" name="deadline_id" value="<?= $deadline['id'] ?>">
                      <input type="hidden" name="current_status" value="<?= $deadline['allow_submissions'] ?>">
                      <button type="submit" name="toggle_submissions" class="text-xs <?= $deadline['allow_submissions'] ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-3 py-1 rounded flex items-center gap-1">
                        <i class="ri-refresh-line"></i> <?= $deadline['allow_submissions'] ? 'Close' : 'Open'; ?>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="text-center py-4 text-gray-500">
              <i class="ri-inbox-line text-2xl mb-2"></i>
              <p>No deadlines set yet</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Deadline Modal -->
  <div id="deadlineModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-md shadow-lg rounded-md bg-white">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Set New Deadline</h3>
        <button onclick="document.getElementById('deadlineModal').classList.add('hidden')" 
                class="text-gray-400 hover:text-gray-500">
          <i class="ri-close-line text-xl"></i>
        </button>
      </div>
      
      <form id="deadlineForm" method="POST" action="admin_page.php">
        <div class="mb-4">
          <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
          <input type="text" id="title" name="title" value="<?= htmlspecialchars($deadlineTitle) ?>" 
                 class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        
        <div class="mb-4">
          <label for="newDeadline" class="block text-sm font-medium text-gray-700 mb-1">Deadline Date & Time</label>
          <input type="datetime-local" id="newDeadline" name="deadline" 
                 class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
          <p id="deadlineError" class="mt-1 text-sm text-red-600 hidden"></p>
        </div>
        
        <div class="mb-4">
          <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
          <textarea id="description" name="description" rows="3" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
        </div>
        
        <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-md">
          <div class="flex items-center justify-between">
            <label for="is_active" class="flex items-center text-sm font-medium text-gray-700">
              <i class="ri-checkbox-circle-line mr-2"></i>
              Set as Active Deadline
            </label>
            <div class="relative inline-block w-10 mr-2 align-middle select-none">
              <input type="checkbox" id="is_active" name="is_active" 
                     class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                     checked>
              <label for="is_active" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-1">When checked, this will be the current active deadline for submissions.</p>
        </div>
        
        <div id="deadlinePreview" class="hidden mb-4 p-3 bg-gray-50 border border-gray-200 rounded-md">
          <h4 class="text-sm font-medium text-gray-700 mb-1">Preview:</h4>
          <p class="text-sm text-gray-600">
            New deadline will be set to: <span id="previewDate" class="font-medium"></span>
          </p>
        </div>
        
        <div class="flex justify-end gap-3">
          <button type="button" onclick="document.getElementById('deadlineModal').classList.add('hidden')" 
                  class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Cancel
          </button>
          <button type="submit" name="update_deadline" id="submitDeadlineBtn"
                  class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Set Deadline
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Submissions Modal -->
  <div id="submissionsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Submissions for Deadline: <span id="modalDeadlineTitle"></span></h3>
        <button onclick="document.getElementById('submissionsModal').classList.add('hidden')" 
                class="text-gray-400 hover:text-gray-500">
          <i class="ri-close-line text-xl"></i>
        </button>
      </div>
      
      <div class="mb-4">
        <div class="flex justify-between items-center">
          <div>
            <p class="text-sm text-gray-600">Deadline: <span id="modalDeadlineDate" class="font-medium"></span></p>
            <p class="text-sm text-gray-600">Total Submissions: <span id="modalTotalSubmissions" class="font-medium"></span></p>
          </div>
          <div class="flex gap-2">
            <button onclick="changePage(-1)" class="px-3 py-1 bg-gray-200 rounded text-sm">
              <i class="ri-arrow-left-line"></i> Previous
            </button>
            <span id="currentPage" class="px-3 py-1 text-sm">1</span>
            <button onclick="changePage(1)" class="px-3 py-1 bg-gray-200 rounded text-sm">
              Next <i class="ri-arrow-right-line"></i>
            </button>
          </div>
        </div>
      </div>
      
      <div class="overflow-y-auto max-h-96">
        <table class="w-full text-sm text-gray-700 border border-gray-200 rounded-lg">
          <thead class="bg-gray-100 text-xs uppercase font-semibold text-gray-600">
            <tr>
              <th class="px-4 py-3 border">Name</th>
              <th class="px-4 py-3 border">Department</th>
              <th class="px-4 py-3 border">Status</th>
              <th class="px-4 py-3 border">Submission Date</th>
            </tr>
          </thead>
          <tbody id="submissionsTableBody">
            <!-- Submissions will be loaded here -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Load Chart.js and ECharts -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Global variables for submissions modal
let currentDeadlineId = 0;
let currentPage = 1;
const itemsPerPage = 10;

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
  const isActiveToggle = document.getElementById('is_active');

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

  // Toggle switch styling
  if (isActiveToggle) {
    isActiveToggle.addEventListener('change', function() {
      const label = this.nextElementSibling;
      if (this.checked) {
        label.classList.remove('bg-gray-300');
        label.classList.add('bg-indigo-500');
      } else {
        label.classList.remove('bg-indigo-500');
        label.classList.add('bg-gray-300');
      }
    });
    
    // Initialize toggle state
    const label = isActiveToggle.nextElementSibling;
    if (isActiveToggle.checked) {
      label.classList.remove('bg-gray-300');
      label.classList.add('bg-indigo-500');
    }
  }

  // Initialize Charts
  const initCharts = () => {
    // Departments list from PHP
    const departments = <?php echo json_encode($departments); ?>;
    
    // Training Chart (Bar Chart)
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
      
      // Responsive handling with debounce
      let resizeTimer;
      window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
          trainingChart.resize();
        }, 200);
      });
    }

    // Pie Charts
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

    initPieCharts();
  };

  // Initialize all charts
  initCharts();

  // Add confirmation for user actions
  document.querySelectorAll('button[type="submit"][name="action"]').forEach(button => {
    button.addEventListener('click', function(e) {
      if (!confirm(`Are you sure you want to ${this.value} this user?`)) {
        e.preventDefault();
      }
    });
  });

  // Auto-focus on deadline input when shown
  if (deadlineInput && deadlineInput.value === '') {
    deadlineInput.focus();
  }
});

// Show submissions for a specific deadline
function showSubmissions(deadlineId) {
  currentDeadlineId = deadlineId;
  currentPage = 1;
  
  // Show loading state
  document.getElementById('submissionsTableBody').innerHTML = `
    <tr>
      <td colspan="4" class="text-center py-4">
        <i class="ri-loader-4-line animate-spin text-2xl text-indigo-600"></i>
        <p class="mt-2 text-sm text-gray-500">Loading submissions...</p>
      </td>
    </tr>
  `;
  
  // Show modal
  document.getElementById('submissionsModal').classList.remove('hidden');
  
  // Load deadline info
  fetch(`get_deadline_info.php?id=${deadlineId}`)
    .then(response => response.json())
    .then(data => {
      document.getElementById('modalDeadlineTitle').textContent = data.title;
      document.getElementById('modalDeadlineDate').textContent = data.formatted_date;
      document.getElementById('modalTotalSubmissions').textContent = data.total_submissions;
    });
  
  // Load submissions
  loadSubmissions();
}

// Load submissions for current page
function loadSubmissions() {
  fetch(`get_submissions.php?deadline_id=${currentDeadlineId}&page=${currentPage}&per_page=${itemsPerPage}`)
    .then(response => response.json())
    .then(data => {
      const tbody = document.getElementById('submissionsTableBody');
      tbody.innerHTML = '';
      
      if (data.submissions.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="text-center py-4 text-gray-500">
              <i class="ri-inbox-line text-2xl"></i>
              <p>No submissions found for this deadline</p>
            </td>
          </tr>
        `;
        return;
      }
      
      data.submissions.forEach(submission => {
        const row = document.createElement('tr');
        row.className = 'submission-item hover:bg-gray-50';
        row.innerHTML = `
          <td class="px-4 py-3 border">${submission.name}</td>
          <td class="px-4 py-3 border">${submission.department}</td>
          <td class="px-4 py-3 border">
            <span class="status-badge ${submission.status === 'On Time' ? 'badge-on-time' : 'badge-late'}">
              ${submission.status}
            </span>
          </td>
          <td class="px-4 py-3 border">${submission.formatted_date}</td>
        `;
        tbody.appendChild(row);
      });
      
      // Update page info
      document.getElementById('currentPage').textContent = currentPage;
    });
}

// Change page in submissions modal
function changePage(delta) {
  const newPage = currentPage + delta;
  if (newPage < 1) return;
  
  currentPage = newPage;
  loadSubmissions();
}
</script>

<style>
  /* Hide dropdown content before Alpine loads */
  [x-cloak] { display: none !important; }
  
  /* Toggle switch styles */
  .toggle-checkbox:checked {
    right: 0;
    border-color: #6366f1;
  }
  .toggle-checkbox:checked + .toggle-label {
    background-color: #6366f1;
  }
  .toggle-checkbox {
    transition: all 0.3s;
    top: 0;
    left: 0;
  }
  .toggle-label {
    transition: background-color 0.3s;
  }
</style>

</body>
</html>