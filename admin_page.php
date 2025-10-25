<?php
session_start();
require 'config.php';

// Complete departments list for LSPU
$departments = [
    'CA' => 'College of Agriculture',
    'CBAA' => 'College of Business, Administration and Accountancy',
    'CAS' => 'College of Arts and Sciences',
    'CCJE' => 'College of Criminal Justice Education',
    'CCS' => 'College of Computer Studies',
    'CFND' => 'College of Food Nutrition and Dietetics',
    'CHMT' => 'College of Hospitality and Tourism Management',
    'CIT' => 'College of Industrial Technology',
    'COE' => 'College of Engineering',
    'COF' => 'College of Fisheries',
    'COL' => 'College of Law',
    'CONAH' => 'College of Nursing and Allied Health',
    'CTE' => 'College of Teacher Education'
];

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
                $mail->Password   = 'kicj ixmy jzjv kmds';
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
                $mail->Password   = 'kicj ixmy jzjv kmds';
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
$onTime = array_fill_keys(array_keys($departments), 0);
$late = array_fill_keys(array_keys($departments), 0);
$noSubmission = array_fill_keys(array_keys($departments), 0);

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
        WHERE u.department IN ('" . implode("','", array_keys($departments)) . "')
        GROUP BY 
            u.department, submission_status
    ";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("si", $submissionDeadline, $activeDeadline['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dept = $row['department'];
            if (array_key_exists($dept, $departments)) {
                switch ($row['submission_status']) {
                    case 'On Time':
                        $onTime[$dept] = (int)$row['count'];
                        break;
                    case 'Late':
                        $late[$dept] = (int)$row['count'];
                        break;
                    case 'No Submission':
                        $noSubmission[$dept] = (int)$row['count'];
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
  <title>Admin Dashboard - LSPU</title>
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
            light: '#f8fafc',
            agriculture: '#059669', // Green for agriculture
            fisheries: '#0ea5e9',   // Blue for fisheries
            technology: '#92400e'   // Brown for technology
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
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
    
    /* SIMPLE BUTTON STYLES - PINAPALITAN MO LANG ITO */
    .btn-primary {
      background: #3b82f6;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    .btn-primary:hover {
      background: #2563eb;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }
    
    .btn-success {
      background: #10b981;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    .btn-success:hover {
      background: #059669;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }
    
    .btn-danger {
      background: #ef4444;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .btn-danger:hover {
      background: #dc2626;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
    }
    
    .btn-warning {
      background: #f59e0b;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    .btn-warning:hover {
      background: #d97706;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
    }

    /* Pending Registrations Button */
    .lspu-gradient {
      background: #3b82f6;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    .lspu-gradient:hover {
      background: #2563eb;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
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
    .stat-card {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      color: #1e293b;
      border: 1px solid #e2e8f0;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #f59e0b, #fbbf24);
    }
    .stat-card:nth-child(2)::before {
      background: linear-gradient(90deg, #1e3a8a, #1e40af);
    }
    .stat-card:nth-child(3)::before {
      background: linear-gradient(90deg, #059669, #10b981);
    }
    .floating {
      animation: floating 3s ease-in-out infinite;
    }
    @keyframes floating {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
    .pulse {
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
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
    .badge-on-time {
      background: linear-gradient(135deg, #10b981, #34d399);
      color: white;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }
    .badge-late {
      background: linear-gradient(135deg, #f59e0b, #fbbf24);
      color: white;
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }
    .badge-no-submission {
      background: linear-gradient(135deg, #ef4444, #f87171);
      color: white;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    .progress-bar {
      height: 8px;
      border-radius: 10px;
      overflow: hidden;
      background: #e5e7eb;
    }
    .progress-fill {
      height: 100%;
      border-radius: 10px;
      transition: width 0.8s ease-in-out;
    }
    .toggle-checkbox:checked {
      right: 0;
      border-color: #1e40af;
    }
    .toggle-checkbox:checked + .toggle-label {
      background-color: #1e40af;
    }
    .chart-container {
      position: relative;
      height: 320px;
      width: 100%;
    }
    .deadline-item {
      transition: all 0.3s ease;
      border-radius: 15px;
      border: 1px solid #e5e7eb;
    }
    .deadline-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    .active-deadline {
      border-left: 4px solid #1e40af;
      background: linear-gradient(135deg, #f8fafc, #e0e7ff);
    }
    .modal-overlay {
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(8px);
    }
    .modal-content {
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
    .sidebar {
      background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
    }
    .header {
      background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
    }
    .lspu-gradient-text {
      background: linear-gradient(135deg, #059669 0%, #0ea5e9 50%, #92400e 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
  </style>
</head>

<body class="min-h-screen">
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
            <div class="w-12 h-12 lspu-gradient rounded-xl flex items-center justify-center backdrop-blur-sm" style="display: none;">
              <i class="ri-government-line text-white text-xl"></i>
            </div>
          </div>
          <div>
            <h1 class="text-lg font-bold text-white">LSPU Admin</h1>
            <p class="text-white/60 text-sm">Training Needs Assessment</p>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-4 py-6">
        <div class="space-y-2">
          <a href="admin_page.php" 
             class="flex items-center px-4 py-3 text-white font-semibold rounded-xl sidebar-link active">
            <i class="ri-dashboard-line mr-3 text-lg"></i>
            <span class="text-base">Dashboard</span>
            <i class="ri-arrow-right-s-line ml-auto text-lg"></i>
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
             class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
            <i class="ri-file-search-line mr-3 text-lg"></i>
            <span class="text-base">Evaluation Forms</span>
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
          <h1 class="text-3xl font-bold text-white">LSPU Admin Dashboard</h1>
        </div>
        <div class="flex items-center space-x-4">
          <div class="text-right">
            <p class="text-white/80 text-sm font-semibold">Today is</p>
            <p class="text-white font-bold text-lg"><?php echo date('F j, Y'); ?></p>
          </div>
          <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm floating border border-white/30">
            <i class="ri-calendar-2-line text-white text-xl"></i>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <div class="p-8">
      <div class="max-w-7xl mx-auto">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['deadline_message'])): ?>
          <div class="mb-6 glass-card p-6 border-l-4 <?php echo $_SESSION['message_type'] === 'success' ? 'border-green-500' : 'border-red-500'; ?>">
            <div class="flex items-center">
              <i class="<?php echo $_SESSION['message_type'] === 'success' ? 'ri-checkbox-circle-line text-green-500' : 'ri-error-warning-line text-red-500'; ?> text-2xl mr-4"></i>
              <span class="text-gray-800 text-lg font-medium"><?php echo $_SESSION['deadline_message']; ?></span>
            </div>
          </div>
          <?php unset($_SESSION['deadline_message']); unset($_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Pending Users Toggle -->
        <div class="mb-6">
          <button id="togglePendingBtn" class="lspu-gradient text-white px-6 py-3 rounded-xl shadow-button flex items-center gap-3 transition-all transform hover:scale-105">
            <i class="ri-user-add-line text-xl"></i>
            <span class="font-semibold">Show Pending Registrations</span>
          </button>
        </div>

        <!-- Pending Users Section -->
        <div id="pendingUsersSection" class="hidden glass-card p-6 mb-8 transition-all">
          <h2 class="text-xl font-bold mb-6 text-gray-800 flex items-center gap-3">
            <i class="ri-user-add-line text-indigo-600"></i> 
            Pending User Registrations
          </h2>

          <?php if (isset($_SESSION['userActionMessage'])): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-center gap-3">
              <i class="ri-checkbox-circle-fill text-green-500 text-xl"></i>
              <span class="text-green-700 font-medium"><?= $_SESSION['userActionMessage'] ?></span>
            </div>
            <?php unset($_SESSION['userActionMessage']); ?>
          <?php endif; ?>

          <?php if ($pendingUsers && $pendingUsers->num_rows > 0): ?>
            <div class="overflow-x-auto rounded-xl border border-gray-200">
              <table class="w-full text-sm text-gray-700">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-xs uppercase font-semibold text-gray-600">
                  <tr>
                    <th class="px-6 py-4 border-b">Name</th>
                    <th class="px-6 py-4 border-b">Email</th>
                    <th class="px-6 py-4 border-b">Department</th>
                    <th class="px-6 py-4 border-b text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($user = $pendingUsers->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition-all border-b last:border-b-0">
                      <td class="px-6 py-4 font-medium"><?= htmlspecialchars($user['name']) ?></td>
                      <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($user['email']) ?></td>
                      <td class="px-6 py-4 text-gray-600"><?= isset($departments[$user['department']]) ? $departments[$user['department']] : $user['department'] ?></td>
                      <td class="px-6 py-4 text-center">
                        <form method="POST" action="admin_page.php" class="flex gap-3 justify-center">
                          <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                          <button type="submit" name="action" value="accept" 
                                  class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all shadow-md hover:shadow-lg">
                            <i class="ri-check-line"></i> Accept
                          </button>
                          <button type="submit" name="action" value="decline" 
                                  class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all shadow-md hover:shadow-lg">
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
            <div class="text-center py-8 text-gray-500">
              <i class="ri-inbox-line text-4xl mb-3"></i>
              <p class="text-lg">No pending user registrations</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <!-- Total Users -->
          <div class="card stat-card pulse">
            <div class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-gray-600 text-sm font-medium">Total Users</p>
                  <h3 class="text-4xl font-bold text-gray-800 mt-2"><?= $totalUsers ?></h3>
                  <p class="text-gray-500 text-xs mt-1">Registered Users</p>
                </div>
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center shadow-lg floating">
                  <i class="fas fa-users text-indigo-600 text-2xl"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Teaching Staff -->
          <div class="card stat-card">
            <div class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-gray-600 text-sm font-medium">Teaching Staff</p>
                  <h3 class="text-4xl font-bold text-gray-800 mt-2"><?= $teachingTotal ?></h3>
                  <p class="text-green-600 text-xs mt-1 font-semibold">
                    <i class="ri-arrow-up-line"></i>
                    <?= $teachingTotal > 0 ? round(($teachingOnTime / $teachingTotal) * 100) : 0 ?>% On Time
                  </p>
                </div>
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center shadow-lg floating">
                  <i class="fas fa-chalkboard-teacher text-blue-600 text-2xl"></i>
                </div>
              </div>
              <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                <span class="badge-on-time">On Time: <?= $teachingOnTime ?></span>
                <span class="badge-late">Late: <?= $teachingLate ?></span>
                <span class="badge-no-submission">No Sub: <?= $teachingNoSubmission ?></span>
              </div>
            </div>
          </div>

          <!-- Non-Teaching Staff -->
          <div class="card stat-card">
            <div class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-gray-600 text-sm font-medium">Non-Teaching Staff</p>
                  <h3 class="text-4xl font-bold text-gray-800 mt-2"><?= $nonTeachingTotal ?></h3>
                  <p class="text-green-600 text-xs mt-1 font-semibold">
                    <i class="ri-arrow-up-line"></i>
                    <?= $nonTeachingTotal > 0 ? round(($nonTeachingOnTime / $nonTeachingTotal) * 100) : 0 ?>% On Time
                  </p>
                </div>
                <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center shadow-lg floating">
                  <i class="fas fa-user-tie text-purple-600 text-2xl"></i>
                </div>
              </div>
              <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                <span class="badge-on-time">On Time: <?= $nonTeachingOnTime ?></span>
                <span class="badge-late">Late: <?= $nonTeachingLate ?></span>
                <span class="badge-no-submission">No Sub: <?= $nonTeachingNoSubmission ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Lower Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
          <!-- Left Column -->
          <div class="lg:col-span-1 space-y-6">
            <!-- Current Deadline -->
            <div class="card">
              <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ri-timer-line text-indigo-600"></i>
                    Current Deadline
                  </h3>
                  <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="ri-alarm-warning-line text-indigo-600 text-lg"></i>
                  </div>
                </div>
                
                <div class="mb-6">
                  <h4 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($deadlineTitle) ?></h4>
                  <?php if ($submissionDeadline): ?>
                    <p class="text-lg text-indigo-600 font-semibold mb-3">
                      <i class="ri-calendar-event-line mr-2"></i>
                      <?= date('F j, Y g:i A', strtotime($submissionDeadline)) ?>
                    </p>
                    <?php if (!empty($deadlineDescription)): ?>
                      <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                        <?= htmlspecialchars($deadlineDescription) ?>
                      </p>
                    <?php endif; ?>
                  <?php else: ?>
                    <p class="text-gray-500 italic">No active deadline set</p>
                  <?php endif; ?>
                </div>
                
                <button onclick="document.getElementById('deadlineModal').classList.remove('hidden')" 
                        class="w-full lspu-gradient text-white py-3 px-4 rounded-xl transition-all transform hover:scale-105 shadow-button flex items-center justify-center gap-2 font-semibold">
                  <i class="ri-edit-line"></i>
                  <span>Set New Deadline</span>
                </button>
              </div>
            </div>

            <!-- Submission Status -->
            <div class="card">
              <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ri-progress-4-line text-green-600"></i>
                    Submission Status
                  </h3>
                  <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="ri-checkbox-circle-line text-green-600 text-lg"></i>
                  </div>
                </div>
                
                <div class="space-y-4">
                  <div>
                    <div class="flex justify-between text-sm mb-2">
                      <span class="font-semibold text-gray-700">On Time</span>
                      <span class="font-bold text-green-600"><?= $onTimeCount ?></span>
                    </div>
                    <div class="progress-bar">
                      <div class="progress-fill bg-green-500" 
                           style="width: <?= $totalUsers > 0 ? ($onTimeCount / $totalUsers * 100) : 0 ?>%"></div>
                    </div>
                  </div>
                  
                  <div>
                    <div class="flex justify-between text-sm mb-2">
                      <span class="font-semibold text-gray-700">Late</span>
                      <span class="font-bold text-yellow-600"><?= $lateCount ?></span>
                    </div>
                    <div class="progress-bar">
                      <div class="progress-fill bg-yellow-500" 
                           style="width: <?= $totalUsers > 0 ? ($lateCount / $totalUsers * 100) : 0 ?>%"></div>
                    </div>
                  </div>
                  
                  <div>
                    <div class="flex justify-between text-sm mb-2">
                      <span class="font-semibold text-gray-700">No Submission</span>
                      <span class="font-bold text-red-600"><?= $noSubmissionCount ?></span>
                    </div>
                    <div class="progress-bar">
                      <div class="progress-fill bg-red-500" 
                           style="width: <?= $totalUsers > 0 ? ($noSubmissionCount / $totalUsers * 100) : 0 ?>%"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="lg:col-span-2">
            <div class="card h-full">
              <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                  <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ri-bar-chart-grouped-line text-indigo-600"></i>
                    TNA Submissions by Department
                  </h3>
                  <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="ri-dashboard-line text-indigo-600 text-lg"></i>
                  </div>
                </div>
                <div class="h-80">
                  <div id="trainingChart" class="w-full h-full"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Deadline History -->
        <div class="card">
          <div class="p-6">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                <i class="ri-history-line text-indigo-600"></i>
                Deadline History
              </h3>
              <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                <i class="ri-time-line text-indigo-600 text-lg"></i>
              </div>
            </div>
            
            <div class="space-y-4">
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
                  <div class="deadline-item p-5 <?= $deadline['is_active'] ? 'active-deadline' : '' ?>">
                    <div class="flex justify-between items-start">
                      <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                          <h4 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($deadline['title']) ?></h4>
                          <?php if ($deadline['is_active']): ?>
                            <span class="badge-on-time text-xs">ACTIVE</span>
                          <?php endif; ?>
                        </div>
                        <p class="text-gray-600 mb-2">
                          <i class="ri-calendar-event-line mr-2"></i>
                          <?= date('F j, Y g:i A', strtotime($deadline['submission_deadline'])) ?>
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                          <span>
                            <i class="ri-file-list-line mr-1"></i>
                            <?= $submissionsCount ?> Submissions
                          </span>
                          <button onclick="showSubmissions(<?= $deadline['id'] ?>)" 
                                  class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1 transition-all">
                            <i class="ri-eye-line"></i> View Submissions
                          </button>
                        </div>
                        <?php if (!empty($deadline['description'])): ?>
                          <p class="text-sm text-gray-500 mt-2"><?= htmlspecialchars($deadline['description']) ?></p>
                        <?php endif; ?>
                      </div>
                      <div class="flex gap-2">
                        <?php if (!$deadline['is_active']): ?>
                          <form method="POST" action="admin_page.php">
                            <input type="hidden" name="deadline_id" value="<?= $deadline['id'] ?>">
                            <button type="submit" name="set_active" 
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all shadow-md hover:shadow-lg">
                              <i class="ri-check-line"></i> Set Active
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
                            <i class="ri-checkbox-circle-line"></i> Active
                          </span>
                        <?php endif; ?>
                        <form method="POST" action="admin_page.php">
                          <input type="hidden" name="deadline_id" value="<?= $deadline['id'] ?>">
                          <input type="hidden" name="current_status" value="<?= $deadline['allow_submissions'] ?>">
                          <button type="submit" name="toggle_submissions" 
                                  class="<?= $deadline['allow_submissions'] ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all shadow-md hover:shadow-lg">
                            <i class="ri-refresh-line"></i> 
                            <?= $deadline['allow_submissions'] ? 'Close' : 'Open'; ?>
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                  <i class="ri-inbox-line text-4xl mb-3"></i>
                  <p class="text-lg">No deadlines set yet</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Deadline Modal -->
<div id="deadlineModal" class="hidden fixed inset-0 modal-overlay overflow-y-auto h-full w-full z-50">
  <div class="relative top-20 mx-auto p-5 w-11/12 max-w-md modal-content">
    <div class="glass-card p-6 rounded-2xl">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-800">Set New Deadline</h3>
        <button onclick="document.getElementById('deadlineModal').classList.add('hidden')" 
                class="text-gray-400 hover:text-gray-500 transition-all">
          <i class="ri-close-line text-2xl"></i>
        </button>
      </div>
      
      <form id="deadlineForm" method="POST" action="admin_page.php">
        <div class="space-y-4">
          <div>
            <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Title</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($deadlineTitle) ?>" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
          </div>
          
          <div>
            <label for="newDeadline" class="block text-sm font-semibold text-gray-700 mb-2">Deadline Date & Time</label>
            <input type="datetime-local" id="newDeadline" name="deadline" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all" required>
            <p id="deadlineError" class="mt-2 text-sm text-red-600 hidden"></p>
          </div>
          
          <div>
            <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Description (Optional)</label>
            <textarea id="description" name="description" rows="3" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"></textarea>
          </div>
          
          <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
            <div class="flex items-center justify-between">
              <label for="is_active" class="flex items-center text-sm font-semibold text-gray-700 cursor-pointer">
                <i class="ri-checkbox-circle-line mr-2 text-indigo-600"></i>
                Set as Active Deadline
              </label>
              <div class="relative inline-block w-12 align-middle select-none">
                <input type="checkbox" id="is_active" name="is_active" 
                       class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-2 appearance-none cursor-pointer transition-all"
                       checked>
                <label for="is_active" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer transition-all"></label>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">When checked, this will be the current active deadline for submissions.</p>
          </div>
          
          <div id="deadlinePreview" class="hidden p-4 bg-indigo-50 border border-indigo-200 rounded-xl">
            <h4 class="text-sm font-semibold text-indigo-700 mb-1">Preview:</h4>
            <p class="text-sm text-indigo-600">
              New deadline will be set to: <span id="previewDate" class="font-bold"></span>
            </p>
          </div>
          
          <div class="flex justify-end gap-3 pt-4">
            <button type="button" onclick="document.getElementById('deadlineModal').classList.add('hidden')" 
                    class="px-6 py-3 border border-gray-300 rounded-xl shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all">
              Cancel
            </button>
            <button type="submit" name="update_deadline" id="submitDeadlineBtn"
                    class="px-6 py-3 border border-transparent rounded-xl shadow-button text-sm font-semibold text-white lspu-gradient focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:scale-105">
              Set Deadline
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Submissions Modal -->
<div id="submissionsModal" class="hidden fixed inset-0 modal-overlay overflow-y-auto h-full w-full z-50">
  <div class="relative top-20 mx-auto p-5 w-11/12 max-w-4xl modal-content">
    <div class="glass-card p-6 rounded-2xl">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-800">Submissions for: <span id="modalDeadlineTitle" class="text-indigo-600"></span></h3>
        <button onclick="document.getElementById('submissionsModal').classList.add('hidden')" 
                class="text-gray-400 hover:text-gray-500 transition-all">
          <i class="ri-close-line text-2xl"></i>
        </button>
      </div>
      
      <div class="mb-6">
        <div class="flex justify-between items-center">
          <div class="space-y-1">
            <p class="text-sm text-gray-600">
              <i class="ri-calendar-event-line mr-2"></i>
              Deadline: <span id="modalDeadlineDate" class="font-semibold"></span>
            </p>
            <p class="text-sm text-gray-600">
              <i class="ri-file-list-line mr-2"></i>
              Total Submissions: <span id="modalTotalSubmissions" class="font-semibold"></span>
            </p>
          </div>
          <div class="flex gap-2">
            <button onclick="changePage(-1)" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
              <i class="ri-arrow-left-line"></i> Previous
            </button>
            <span id="currentPage" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold">1</span>
            <button onclick="changePage(1)" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
              Next <i class="ri-arrow-right-line"></i>
            </button>
          </div>
        </div>
      </div>
      
      <div class="overflow-y-auto max-h-96 rounded-xl border border-gray-200">
        <table class="w-full text-sm text-gray-700">
          <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-xs uppercase font-semibold text-gray-600 sticky top-0">
            <tr>
              <th class="px-6 py-4 border-b">Name</th>
              <th class="px-6 py-4 border-b">Department</th>
              <th class="px-6 py-4 border-b">Status</th>
              <th class="px-6 py-4 border-b">Submission Date</th>
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
        ? '<i class="ri-user-add-line text-xl"></i><span class="font-semibold">Show Pending User Registrations</span>' 
        : '<i class="ri-user-unfollow-line text-xl"></i><span class="font-semibold">Hide Pending User Registrations</span>';
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
    const departments = <?php echo json_encode(array_keys($departments)); ?>;
    const departmentNames = <?php echo json_encode(array_values($departments)); ?>;
    
    // Training Chart (Bar Chart)
    const trainingChartElem = document.getElementById('trainingChart');
    if (trainingChartElem && typeof echarts !== 'undefined') {
      const trainingChart = echarts.init(trainingChartElem);
      
      const option = {
        title: {
          text: 'Training Submissions per Department',
          left: 'center',
          top: 10,
          textStyle: { 
            fontSize: 16, 
            fontWeight: 'bold', 
            color: '#1f2937' 
          }
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          formatter: function(params) {
            let result = params[0].name + '<br/>';
            params.forEach(param => {
              result += `${param.marker} ${param.seriesName}: ${param.value}<br/>`;
            });
            return result;
          }
        },
        legend: {
          top: 40,
          data: ['On Time', 'Late', 'No Submission'],
          textStyle: {
            fontWeight: 'bold'
          }
        },
        grid: { 
          top: 80, 
          right: 20, 
          bottom: 30, 
          left: 50, 
          containLabel: true 
        },
        xAxis: {
          type: 'category',
          data: departments,
          axisLine: { lineStyle: { color: '#e5e7eb' } },
          axisLabel: { 
            color: '#6b7280',
            interval: 0,
            fontWeight: 'bold',
            fontSize: 11,
            formatter: function(value) {
              // Return only the department code (CA, CBAA, etc.)
              return value;
            }
          }
        },
        yAxis: {
          type: 'value',
          axisLine: { show: false },
          splitLine: { lineStyle: { color: '#f1f5f9' } },
          axisLabel: { 
            color: '#6b7280',
            fontWeight: 'bold'
          }
        },
        series: [
          {
            name: 'No Submission',
            type: 'bar',
            stack: 'total',
            data: <?php echo json_encode(array_values($noSubmission)); ?>,
            itemStyle: { 
              color: '#ef4444', 
              borderRadius: [4, 4, 0, 0] 
            },
            emphasis: {
              itemStyle: {
                shadowBlur: 10,
                shadowColor: 'rgba(239, 68, 68, 0.5)'
              }
            }
          },
          {
            name: 'Late',
            type: 'bar',
            stack: 'total',
            data: <?php echo json_encode(array_values($late)); ?>,
            itemStyle: { 
              color: '#f59e0b', 
              borderRadius: [4, 4, 0, 0] 
            },
            emphasis: {
              itemStyle: {
                shadowBlur: 10,
                shadowColor: 'rgba(245, 158, 11, 0.5)'
              }
            }
          },
          {
            name: 'On Time',
            type: 'bar',
            stack: 'total',
            data: <?php echo json_encode(array_values($onTime)); ?>,
            itemStyle: { 
              color: '#10b981', 
              borderRadius: [4, 4, 0, 0] 
            },
            emphasis: {
              itemStyle: {
                shadowBlur: 10,
                shadowColor: 'rgba(16, 185, 129, 0.5)'
              }
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
      <td colspan="4" class="text-center py-8">
        <i class="ri-loader-4-line animate-spin text-3xl text-indigo-600"></i>
        <p class="mt-3 text-gray-500 font-medium">Loading submissions...</p>
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
            <td colspan="4" class="text-center py-8 text-gray-500">
              <i class="ri-inbox-line text-3xl mb-3"></i>
              <p class="text-lg font-medium">No submissions found for this deadline</p>
            </td>
          </tr>
        `;
        return;
      }
      
      data.submissions.forEach(submission => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 transition-all border-b last:border-b-0';
        row.innerHTML = `
          <td class="px-6 py-4 font-medium">${submission.name}</td>
          <td class="px-6 py-4 text-gray-600">${submission.department}</td>
          <td class="px-6 py-4">
            <span class="status-badge ${submission.status === 'On Time' ? 'badge-on-time' : 'badge-late'}">
              ${submission.status}
            </span>
          </td>
          <td class="px-6 py-4 text-gray-600">${submission.formatted_date}</td>
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

</body>
</html>