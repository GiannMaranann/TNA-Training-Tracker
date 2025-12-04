<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Manila');

// Initialize variables
$submissionStatus = '';
$formattedDeadline = '';
$rawDeadline = null;
$hasProfile = false;
$user = null;
$show_assessment = false;
$welcomeMessage = '';
$hasSubmitted = false;
$userLastSubmitted = null;
$currentDeadlinePassed = false;
$unreadNotifications = [];
$error = null;
$showAssessmentButton = false;
$hasUnreadAssessmentNotification = false;
$profileCompletionPercentage = 0;
$missingProfileFields = [];
$currentDeadlineId = null;
$allowSubmissions = false;

// Set upload directory path (same as profile.php)
$upload_dir = 'uploads/profile_images/';

try {
    // Get latest active deadline
    $result = $con->query("SELECT id, submission_deadline, allow_submissions FROM settings WHERE is_active = 1 ORDER BY submission_deadline DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $rawDeadline = $row['submission_deadline'] ?? null;
        $currentDeadlineId = $row['id'] ?? null;
        $allowSubmissions = (bool)$row['allow_submissions'];
        if (!empty($rawDeadline)) {
            $formattedDeadline = date("F j, Y, g:i a", strtotime($rawDeadline));
        }
    }

    $hasDeadline = !empty($rawDeadline);
    $now = new DateTime();
    $deadlineDT = $hasDeadline ? new DateTime($rawDeadline) : null;
    $currentDeadlinePassed = $hasDeadline && ($now > $deadlineDT);

    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];

        // Enhanced profile completeness check - Get profile_image from database
        $profileQuery = $con->prepare("SELECT *, profile_image,
                                    (CASE WHEN name IS NOT NULL AND name != '' AND 
                                          educationalAttainment IS NOT NULL AND educationalAttainment != '' AND
                                          specialization IS NOT NULL AND specialization != '' AND
                                          designation IS NOT NULL AND designation != '' AND
                                          department IS NOT NULL AND department != '' AND
                                          yearsInLSPU IS NOT NULL AND yearsInLSPU != '' AND
                                          teaching_status IS NOT NULL AND teaching_status != ''
                                    THEN TRUE ELSE FALSE END) as has_complete_profile 
                                    FROM users WHERE id = ?");
        $profileQuery->bind_param("i", $userId);
        $profileQuery->execute();
        $profileResult = $profileQuery->get_result();

        if ($profileResult && $profileResult->num_rows > 0) {
            $user = $profileResult->fetch_assoc();
            $hasProfile = (bool)$user['has_complete_profile'];
            $welcomeMessage = "Welcome, " . htmlspecialchars($user['name'] ?? 'User') . "!";
            
            // Save profile image to session
            if (!empty($user['profile_image'])) {
                $_SESSION['profile_image'] = $user['profile_image'];
            }
            
            // Save other profile data to session
            $_SESSION['profile_name'] = $user['name'] ?? '';
            $_SESSION['profile_designation'] = $user['designation'] ?? '';
            $_SESSION['profile_educationalAttainment'] = $user['educationalAttainment'] ?? '';
            $_SESSION['profile_specialization'] = $user['specialization'] ?? '';
            $_SESSION['profile_department'] = $user['department'] ?? '';
            $_SESSION['profile_yearsInLSPU'] = $user['yearsInLSPU'] ?? '';
            $_SESSION['profile_teaching_status'] = $user['teaching_status'] ?? '';
            
            // Calculate profile completion
            $requiredFields = [
                'name' => 'Full Name',
                'educationalAttainment' => 'Educational Attainment',
                'specialization' => 'Specialization',
                'designation' => 'Designation',
                'department' => 'Department',
                'yearsInLSPU' => 'Years in LSPU',
                'teaching_status' => 'Employment Type'
            ];
            
            $completedFields = 0;
            $missingProfileFields = [];
            
            foreach ($requiredFields as $field => $label) {
                if (!empty(trim($user[$field] ?? ''))) {
                    $completedFields++;
                } else {
                    $missingProfileFields[$field] = $label;
                }
            }
            
            $profileCompletionPercentage = round(($completedFields / count($requiredFields)) * 100);
        }

        // Check for unread assessment notifications
        $notifQuery = $con->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 AND related_type = 'deadline' ORDER BY created_at DESC");
        $notifQuery->bind_param("i", $userId);
        $notifQuery->execute();
        $notifResult = $notifQuery->get_result();
        $unreadNotifications = $notifResult->fetch_all(MYSQLI_ASSOC);
        $hasUnreadAssessmentNotification = count($unreadNotifications) > 0;

        // Check assessment submission status for the CURRENT deadline only
        if ($hasProfile && $hasDeadline && $currentDeadlineId) {
            $submissionStmt = $con->prepare("
                SELECT created_at, submission_date, status FROM assessments 
                WHERE user_id = ? AND deadline_id = ?
                ORDER BY created_at DESC LIMIT 1
            ");
            $submissionStmt->bind_param("ii", $userId, $currentDeadlineId);
            $submissionStmt->execute();
            $submissionResult = $submissionStmt->get_result();

            if ($submissionResult && $submissionResult->num_rows > 0) {
                $row = $submissionResult->fetch_assoc();
                $userLastSubmitted = new DateTime($row['submission_date'] ?? $row['created_at']);
                $hasSubmitted = true;
                $submissionStatus = $row['status'] ?? 'submitted';
                
                if ($hasSubmitted) {
                    $markRead = $con->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND related_type = 'deadline'");
                    $markRead->bind_param("i", $userId);
                    $markRead->execute();
                    $markRead->close();
                }
            }
        }

        // Show assessment button if:
        // 1. User has complete profile
        // 2. There's an active deadline (regardless of deadline passed or not)
        // 3. User hasn't submitted for THIS deadline
        $showAssessmentButton = $hasProfile && $hasDeadline && !$hasSubmitted;
        
        // Automatically show assessment form if there are unread notifications
        if ($showAssessmentButton && $hasUnreadAssessmentNotification) {
            $show_assessment = true;
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Error in user_page.php: " . $error);
}

if (isset($con) && $con) {
    $con->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard | Training Needs Assessment</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#1e40af',
            secondary: '#3b82f6',
            accent: '#f59e0b',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
          },
          fontFamily: {
            'poppins': ['Poppins', 'sans-serif'],
          },
          boxShadow: {
            'custom': '0 4px 20px rgba(0, 0, 0, 0.08)',
            'custom-hover': '0 8px 30px rgba(0, 0, 0, 0.12)',
            'neumorphic': '20px 20px 60px #d9d9d9, -20px -20px 60px #ffffff',
          },
          borderRadius: {
            'xl': '1rem',
            '2xl': '1.5rem',
          },
          animation: {
            'fade-in': 'fadeIn 0.5s ease-in-out',
            'slide-up': 'slideUp 0.3s ease-out',
            'pulse-gentle': 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
          }
        }
      }
    }
  </script>
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <style>
    * {
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
      min-height: 100vh;
    }

    .sidebar-gradient {
      background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
    }

    .card-gradient {
      background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    }

    .profile-gradient {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .assessment-btn {
      background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
    }

    .assessment-btn:hover {
      background: linear-gradient(135deg, #2563eb 0%, #1e3a8a 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
    }

    .late-submission-btn {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
    }

    .late-submission-btn:hover {
      background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
    }

    .notification-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: pulse-gentle 2s infinite;
    }

    .submission-status {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      border-radius: 1rem;
      padding: 1.5rem;
      margin-top: 1.5rem;
      box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
    }

    .submission-status-late {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(5px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      animation: fadeIn 0.3s ease-out;
    }

    .modal-container {
      background: white;
      border-radius: 1.5rem;
      padding: 2rem;
      max-width: 500px;
      width: 90%;
      animation: slideUp 0.3s ease-out;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes slideUp {
      from { 
        opacity: 0;
        transform: translateY(20px);
      }
      to { 
        opacity: 1;
        transform: translateY(0);
      }
    }

    .glass-effect {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .hover-lift {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .hover-lift:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .calendar-card {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border-radius: 1rem;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .deadline-badge {
      background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 2rem;
      font-size: 0.875rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .welcome-text {
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .nav-item {
      transition: all 0.3s ease;
      border-radius: 0.75rem;
      margin: 0.25rem 0;
    }

    .nav-item:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateX(5px);
    }

    .nav-item.active {
      background: rgba(255, 255, 255, 0.15);
      position: relative;
    }

    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 60%;
      background: white;
      border-radius: 0 2px 2px 0;
    }

    .progress-ring {
      position: relative;
      width: 120px;
      height: 120px;
    }

    .progress-ring-circle {
      transform: rotate(-90deg);
      transform-origin: 50% 50%;
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 8px;
    }

    .status-submitted { background-color: #10b981; }
    .status-pending { background-color: #f59e0b; }
    .status-overdue { background-color: #ef4444; }
    
    /* Smooth scrolling */
    html {
      scroll-behavior: smooth;
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }
    
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
      border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, #2563eb 0%, #1e3a8a 100%);
    }
  </style>
</head>

<body class="flex h-screen bg-gray-50 font-poppins">
<!-- Sidebar with Navigation First, Quick Status at Bottom -->
<aside class="w-64 sidebar-gradient text-white shadow-xl flex flex-col justify-between relative z-10">
  <div class="h-full flex flex-col">
    <!-- Logo & Title -->
    <div class="p-6 flex items-center border-b border-blue-800/30">
      <div class="relative">
        <img src="images/lspu-logo.png" alt="LSPU Logo" class="w-16 h-16 mr-4 drop-shadow-lg" />
      </div>
      <div>
        <a href="user_page.php" class="text-xl font-bold text-white tracking-tight">TNA System</a>
        <p class="text-blue-200 text-xs mt-1">Training Needs Assessment</p>
      </div>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-6 space-y-1">
      <a href="user_page.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg bg-blue-700/50 hover:bg-blue-700/70 transition-all active">
        <div class="w-6 h-6 flex items-center justify-center mr-3">
          <i class="ri-dashboard-line text-lg"></i>
        </div>
        Dashboard
        <i class="ri-arrow-right-s-line ml-auto"></i>
      </a>
      
      <!-- IDP Forms Dropdown -->
      <div class="group">
        <button id="idp-dropdown-btn" class="nav-item flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg hover:bg-blue-700/50 transition-all">
          <div class="flex items-center">
            <div class="w-6 h-6 flex items-center justify-center mr-3">
              <i class="ri-file-text-line text-lg"></i>
            </div>
            IDP Forms
          </div>
          <i class="ri-arrow-down-s-line transition-transform duration-300 group-[.open]:rotate-180"></i>
        </button>
        
        <div id="idp-dropdown-menu" class="hidden pl-10 mt-1 space-y-1 group-[.open]:block">
          <a href="Individual_Development_Plan.php" class="nav-item flex items-center px-4 py-2.5 text-sm rounded-lg hover:bg-blue-700/30 transition-all">
            <div class="w-5 h-5 flex items-center justify-center mr-3">
              <i class="ri-file-add-line"></i>
            </div>
            Create New
          </a>
          <a href="save_idp_forms.php" class="nav-item flex items-center px-4 py-2.5 text-sm rounded-lg hover:bg-blue-700/30 transition-all">
            <div class="w-5 h-5 flex items-center justify-center mr-3">
              <i class="ri-file-list-line"></i>
            </div>
            My Submitted Forms
          </a>
        </div>
      </div>
      
      <a href="profile.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg hover:bg-blue-700/50 transition-all">
        <div class="w-6 h-6 flex items-center justify-center mr-3">
          <i class="ri-user-line text-lg"></i>
        </div>
        Profile
        <?php if (!$hasProfile): ?>
          <span class="ml-auto text-xs bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center">
            <i class="ri-alert-line text-xs"></i>
          </span>
        <?php endif; ?>
      </a>
      
      <!-- Assessment Page Link -->
      <?php if ($hasProfile && $hasDeadline && !$hasSubmitted): ?>
        <a href="#assessmentFormWrapper" id="assessment-sidebar-link" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900 transition-all mt-4">
          <div class="w-6 h-6 flex items-center justify-center mr-3">
            <i class="ri-file-edit-line text-lg"></i>
          </div>
          Take Assessment
          <span class="ml-auto animate-pulse">
            <i class="ri-arrow-right-up-line"></i>
          </span>
        </a>
      <?php endif; ?>
    </nav>

    <!-- Quick Stats Section at Bottom -->
    <div class="p-4 border-t border-blue-800/30">
      <div class="bg-blue-800/30 backdrop-blur-sm rounded-xl p-4 border border-blue-700/30 mb-4">
        <h3 class="text-sm font-semibold text-blue-200 mb-3 flex items-center">
          <i class="ri-dashboard-3-line mr-2"></i>
          Quick Status
        </h3>
        
        <div class="space-y-3">
          <!-- Profile Completion -->
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="w-8 h-8 rounded-full bg-blue-500/20 flex items-center justify-center mr-3">
                <i class="ri-user-line text-blue-300 text-sm"></i>
              </div>
              <span class="text-sm text-blue-100">Profile</span>
            </div>
            <div class="flex items-center">
              <span class="text-sm font-semibold text-white mr-2"><?= $profileCompletionPercentage ?>%</span>
              <div class="w-16 bg-blue-900/50 rounded-full h-1.5">
                <div class="bg-gradient-to-r from-green-400 to-green-500 h-1.5 rounded-full" 
                     style="width: <?= $profileCompletionPercentage ?>%"></div>
              </div>
            </div>
          </div>

          <!-- Assessment Status -->
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="w-8 h-8 rounded-full <?= $hasSubmitted ? 'bg-green-500/20' : ($hasDeadline ? 'bg-yellow-500/20' : 'bg-gray-500/20') ?> flex items-center justify-center mr-3">
                <i class="ri-file-text-line <?= $hasSubmitted ? 'text-green-300' : ($hasDeadline ? 'text-yellow-300' : 'text-gray-300') ?> text-sm"></i>
              </div>
              <span class="text-sm text-blue-100">TNA Status</span>
            </div>
            <span class="text-xs font-medium px-2 py-1 rounded-full <?= $hasSubmitted ? 'bg-green-500/20 text-green-300' : ($hasDeadline ? 'bg-yellow-500/20 text-yellow-300' : 'bg-gray-500/20 text-gray-300') ?>">
              <?= $hasSubmitted ? 'Submitted' : ($hasDeadline ? 'Pending' : 'No Deadline') ?>
            </span>
          </div>

          <!-- Deadline Counter -->
          <?php if ($hasDeadline): ?>
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <div class="w-8 h-8 rounded-full <?= $currentDeadlinePassed ? 'bg-red-500/20' : 'bg-blue-500/20' ?> flex items-center justify-center mr-3">
                  <i class="ri-time-line <?= $currentDeadlinePassed ? 'text-red-300' : 'text-blue-300' ?> text-sm"></i>
                </div>
                <span class="text-sm text-blue-100">Deadline</span>
              </div>
              <span class="text-xs font-medium <?= $currentDeadlinePassed ? 'text-red-300' : 'text-blue-300' ?>">
                <?= date('M j', strtotime($rawDeadline)) ?>
              </span>
            </div>
          <?php endif; ?>

          <!-- Notifications -->
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="w-8 h-8 rounded-full bg-orange-500/20 flex items-center justify-center mr-3 relative">
                <i class="ri-notification-3-line text-orange-300 text-sm"></i>
                <?php if ($hasUnreadAssessmentNotification): ?>
                  <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center">
                    <span class="text-xs text-white"><?= count($unreadNotifications) ?></span>
                  </span>
                <?php endif; ?>
              </div>
              <span class="text-sm text-blue-100">Alerts</span>
            </div>
            <span class="text-sm font-semibold <?= $hasUnreadAssessmentNotification ? 'text-red-300' : 'text-blue-300' ?>">
              <?= count($unreadNotifications) ?>
            </span>
          </div>
        </div>

        <!-- Overall Status -->
        <div class="mt-4 pt-3 border-t border-blue-700/30">
          <div class="flex items-center justify-between">
            <span class="text-xs text-blue-300">System Status:</span>
            <?php if ($hasProfile && $hasSubmitted): ?>
              <span class="flex items-center text-xs text-green-300 font-medium">
                <i class="ri-checkbox-circle-line mr-1"></i>
                Complete
              </span>
            <?php elseif (!$hasProfile): ?>
              <span class="flex items-center text-xs text-red-300 font-medium">
                <i class="ri-alert-line mr-1"></i>
                Profile Required
              </span>
            <?php elseif ($hasDeadline && !$hasSubmitted): ?>
              <span class="flex items-center text-xs text-yellow-300 font-medium animate-pulse">
                <i class="ri-timer-flash-line mr-1"></i>
                Assessment Due
              </span>
            <?php else: ?>
              <span class="flex items-center text-xs text-blue-300 font-medium">
                <i class="ri-information-line mr-1"></i>
                Active
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- User Info & Logout -->
      <div class="flex items-center justify-between">
      <div class="flex items-center">
        <?php
          $defaultImage = 'images/noprofile.jpg'; 
          
          // Get profile image from user data or session
          $imageSrc = $defaultImage;
          if (!empty($user['profile_image'])) {
              $full_path = $upload_dir . $user['profile_image'];
              if (file_exists($full_path)) {
                  $imageSrc = $full_path;
              }
          }
        ?>
        
        <img class="w-10 h-10 rounded-full border-2 border-blue-500/30 mr-3 object-cover" 
            src="<?= htmlspecialchars($imageSrc); ?>"
            alt="Profile Picture"
            onerror="this.onerror=null;this.src='<?= $defaultImage; ?>';">
        <div>
          <p class="text-sm font-medium text-white">
            <?= htmlspecialchars($user['name'] ?? 'User') ?>
          </p>
          <p class="text-xs text-blue-300">
            <?= htmlspecialchars($user['designation'] ?? 'Staff') ?>
          </p>
        </div>
      </div>
      <a href="homepage.php" class="p-2 rounded-lg hover:bg-red-600/20 text-red-100 border border-red-500/20 transition-all">
        <i class="ri-logout-box-line"></i>
      </a>
    </div>
    </div>
  </div>
</aside>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto relative">
  <?php if (!$hasProfile && isset($user)): ?>
    <!-- Modal Overlay -->
    <div id="profileModal" class="modal-overlay">
      <div class="modal-container">
        <div class="flex items-center justify-between mb-4">
          <h2 class="modal-title text-2xl font-bold text-gray-800">
            <i class="ri-user-settings-line text-blue-500 mr-3 text-3xl"></i>
            Complete Your Profile
          </h2>
          <button id="modalCloseBtn" class="modal-close p-2 rounded-full hover:bg-gray-100 transition-colors">
            <i class="ri-close-line text-xl text-gray-500"></i>
          </button>
        </div>
        
        <div class="modal-content">
          <p class="text-gray-600 mb-6">You need to complete your profile before you can access the assessment features.</p>
          
          <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-semibold text-gray-700">
                Profile Completion
              </span>
              <span class="text-lg font-bold text-blue-600">
                <?= $profileCompletionPercentage ?>%
              </span>
            </div>
            
            <div class="w-full bg-gray-200 rounded-full h-3">
              <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-500" 
                   style="width: <?= $profileCompletionPercentage ?>%"></div>
            </div>
          </div>
          
          <?php if (!empty($missingProfileFields)): ?>
            <div class="bg-blue-50 rounded-xl p-4 mb-6">
              <p class="text-sm font-semibold text-blue-800 mb-3 flex items-center">
                <i class="ri-information-line mr-2"></i>
                Missing Information
              </p>
              <ul class="space-y-2">
                <?php foreach ($missingProfileFields as $field => $label): ?>
                  <li class="flex items-center text-sm text-blue-700">
                    <i class="ri-arrow-right-s-line mr-2 text-blue-500"></i>
                    <?= htmlspecialchars($label) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="modal-actions">
          <a href="profile.php" class="w-full inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg font-medium hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-xl">
            <i class="ri-user-settings-line mr-3"></i>
            Complete Profile Now
          </a>
        </div>
      </div>
    </div>
    
    <!-- Blurred Content -->
    <div class="blur-sm filter backdrop-blur-sm">
  <?php endif; ?>

  <div class="p-8">
    <!-- Welcome Section -->
    <div id="dashboardSection" class="transition-transform duration-700">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
        <div>
          <h1 class="text-4xl font-bold welcome-text mb-2">
            <?= $welcomeMessage ?>
          </h1>
          <p class="text-gray-600 flex items-center">
            <i class="ri-calendar-line mr-2"></i>
            <?= date('l, F j, Y') ?>
          </p>
        </div>
        
        <div class="flex items-center gap-6">
          <?php if ($hasDeadline): ?>
            <div class="deadline-badge">
              <i class="ri-time-line"></i>
              Deadline: <?= htmlspecialchars($formattedDeadline) ?>
            </div>
          <?php endif; ?>
          
          <?php if ($hasUnreadAssessmentNotification): ?>
            <div class="relative">
              <button id="notificationBtn" class="relative p-3 bg-white rounded-full shadow-custom hover:shadow-custom-hover transition-all hover-lift">
                <i class="ri-notification-3-fill text-2xl text-blue-600"></i>
                <span class="notification-badge">
                  <?= count($unreadNotifications) ?>
                </span>
              </button>
              <div id="notificationDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-2xl z-50 border border-gray-100 overflow-hidden notification-dropdown">
                <div class="p-4 bg-gradient-to-r from-blue-500 to-blue-600">
                  <h3 class="text-sm font-semibold text-white flex items-center">
                    <i class="ri-notification-3-line mr-2"></i>
                    Assessment Notifications
                  </h3>
                </div>
                <div class="max-h-96 overflow-y-auto">
                  <?php foreach ($unreadNotifications as $notif): ?>
                    <div class="p-4 border-b border-gray-100 hover:bg-blue-50/50 transition-colors notification-item">
                      <p class="text-sm text-gray-700 mb-2"><?= htmlspecialchars($notif['message']) ?></p>
                      <p class="text-xs text-gray-500 flex items-center">
                        <i class="ri-time-line mr-1"></i>
                        <?= date('M j, g:i a', strtotime($notif['created_at'])) ?>
                      </p>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Dashboard Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Left Column -->
      <div class="flex-1 space-y-8">
        <!-- Assessment Summary -->
        <div class="card-gradient rounded-2xl shadow-custom p-8 hover:shadow-custom-hover transition-all duration-300 hover-lift">
          <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
            <div>
              <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="ri-file-list-3-line text-blue-500 mr-3 text-2xl"></i>
                Assessment Forms
              </h2>
              <p class="text-gray-600">View and manage your training needs assessments</p>
            </div>
            
            <?php if ($hasDeadline && $hasProfile): ?>
              <div class="flex items-center gap-4">
                <?php if ($hasSubmitted): ?>
                  <span class="px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-medium flex items-center">
                    <i class="ri-checkbox-circle-line mr-2"></i>
                    Submitted
                  </span>
                <?php elseif ($currentDeadlinePassed): ?>
                  <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium flex items-center">
                    <i class="ri-time-line mr-2"></i>
                    Deadline Passed
                  </span>
                <?php else: ?>
                  <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-medium flex items-center">
                    <i class="ri-time-line mr-2"></i>
                    Active
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          
          <?php include 'assessment_summary_table.php'; ?>
        </div>

        <?php if ($hasProfile): ?>
          <!-- Assessment Button Section -->
          <?php if ($showAssessmentButton): ?>
            <div class="text-center mt-8">
              <button id="showFormBtn" class="px-8 py-4 text-white rounded-xl font-semibold flex items-center mx-auto shadow-lg hover:shadow-xl transition-all duration-300 hover-lift <?= $currentDeadlinePassed ? 'late-submission-btn' : 'assessment-btn' ?>">
                <i class="ri-edit-box-line mr-3 text-xl"></i>
                <?= $currentDeadlinePassed ? 'Fill Out Training Needs Assessment (Late Submission)' : 'Fill Out Training Needs Assessment' ?>
                <i class="ri-arrow-right-line ml-3"></i>
              </button>
              <?php if ($currentDeadlinePassed): ?>
                <p class="text-sm text-yellow-700 mt-3 flex items-center justify-center">
                  <i class="ri-alert-line mr-2"></i> 
                  The deadline has passed, but you can still submit your assessment.
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Assessment Form Section -->
          <div id="assessmentFormWrapper" class="<?= $show_assessment ? '' : 'hidden' ?> bg-white rounded-2xl shadow-custom mt-8 transition-all duration-500 ease-in-out hover:shadow-custom-hover overflow-hidden">
            <div class="form-header bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6">
              <h3 class="text-xl font-bold flex items-center">
                <i class="ri-file-text-line mr-3 text-2xl"></i>
                Training Needs Assessment Form
              </h3>
              <p class="text-blue-100 mt-2">Complete the form below to submit your training needs assessment</p>
            </div>
            <div class="p-6">
              <?php include 'assessment_form_partial.php'; ?>
            </div>
          </div>

          <!-- Submission Status Display -->
          <?php if ($hasSubmitted): ?>
            <div class="submission-status <?= $submissionStatus === 'late' ? 'submission-status-late' : '' ?> mt-8 rounded-2xl p-8">
              <div class="submission-status-header flex items-center justify-center mb-4">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mr-4">
                  <i class="ri-checkbox-circle-fill text-3xl <?= $submissionStatus === 'late' ? 'text-yellow-300' : 'text-green-300' ?>"></i>
                </div>
                <div>
                  <h3 class="submission-status-title text-2xl font-bold">
                    <?= $submissionStatus === 'late' ? 'Late Submission Received' : 'Assessment Successfully Submitted' ?>
                  </h3>
                  <p class="text-white/80 mt-1">
                    <?= $submissionStatus === 'late' ? 'Submitted after the deadline' : 'Thank you for your submission' ?>
                  </p>
                </div>
              </div>
              
              <div class="submission-details bg-white/10 backdrop-blur-sm rounded-xl p-6 mb-4">
                <div class="flex flex-col items-center">
                  <div class="flex items-center mb-3">
                    <i class="ri-check-line <?= $submissionStatus === 'late' ? 'text-yellow-300' : 'text-green-300' ?> mr-2 text-2xl"></i>
                    <span class="submission-message text-xl font-medium">
                      <?= $submissionStatus === 'late' ? 'Your late submission was recorded' : 'Your assessment has been recorded' ?>
                    </span>
                  </div>
                  <div class="flex items-center text-white/90">
                    <i class="ri-time-line mr-2"></i>
                    <span class="submission-date">
                      Submitted on <?= $userLastSubmitted ? $userLastSubmitted->format('F j, Y \a\t g:i a') : '' ?>
                    </span>
                  </div>
                </div>
              </div>
              
              <?php if ($submissionStatus === 'late'): ?>
                <p class="text-center text-yellow-200 font-medium">
                  <i class="ri-information-line mr-2"></i>
                  Note: Your submission was received after the deadline.
                </p>
              <?php else: ?>
                <p class="text-center text-white/90">
                  <i class="ri-check-double-line mr-2"></i>
                  Your assessment is now complete. You will be notified about the next steps.
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- No Active Deadline Message -->
          <?php if (!$hasDeadline): ?>
            <div class="mt-8 p-8 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl border border-blue-100 text-center">
              <div class="flex flex-col items-center">
                <div class="w-20 h-20 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                  <i class="ri-time-line text-blue-500 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-blue-800 mb-3">No Active Assessment Period</h3>
                <p class="text-gray-700 max-w-md mb-4">There is currently no active assessment period. Please wait for the administrator to announce the next assessment cycle.</p>
                <div class="flex items-center text-blue-600">
                  <i class="ri-information-line mr-2"></i>
                  <span class="text-sm">You will be notified when the next assessment opens</span>
                </div>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      
      <!-- Right Column: Profile Information and Calendar --> 
      <div class="w-full lg:w-1/3 space-y-8">
        <!-- Profile Card -->
        <?php if (isset($user)): ?>
        <div class="bg-white rounded-2xl shadow-custom overflow-hidden hover:shadow-custom-hover transition-all duration-300 hover-lift">
          <!-- Profile Header -->
          <div class="profile-gradient p-6 text-center relative">
            <div class="absolute top-4 right-4">
              <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs text-white font-medium">
                <?= htmlspecialchars($user['designation'] ?? 'Staff') ?>
              </span>
            </div>
            
            <?php
              $defaultImage = 'images/noprofile.jpg'; 
              $mainImageSrc = $defaultImage;
              
              if (!empty($user['profile_image'])) {
                  $full_path = $upload_dir . $user['profile_image'];
                  if (file_exists($full_path)) {
                      $mainImageSrc = $full_path;
                  }
              }
            ?>
            
            <div class="relative inline-block">
              <img class="w-32 h-32 rounded-full mx-auto mb-4 border-4 border-white/20 shadow-xl"
                  src="<?= htmlspecialchars($mainImageSrc) ?>" 
                  alt="Profile Picture"
                  onerror="this.onerror=null;this.src='<?= $defaultImage; ?>';">
              <div class="absolute bottom-4 right-4 w-8 h-8 bg-green-500 rounded-full border-2 border-white flex items-center justify-center">
                <i class="ri-check-line text-white text-xs"></i>
              </div>
            </div>
            
            <h2 class="text-2xl font-bold text-white mb-2">
              <?= htmlspecialchars($user['name']); ?>
            </h2>
            <p class="text-blue-100"><?= htmlspecialchars($user['department'] ?? 'Department'); ?></p>
          </div>
            
            <!-- Profile Details -->
            <div class="p-6">
              <div class="space-y-4">
                <?php if (!empty($user['educationalAttainment'])): ?>
                  <div class="flex items-start p-3 rounded-lg bg-blue-50/50 hover:bg-blue-50 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                      <i class="ri-graduation-cap-line text-blue-600"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-600">Educational Attainment</p>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['educationalAttainment']); ?></p>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($user['specialization'])): ?>
                  <div class="flex items-start p-3 rounded-lg bg-blue-50/50 hover:bg-blue-50 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                      <i class="ri-medal-line text-blue-600"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-600">Specialization</p>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['specialization']); ?></p>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($user['designation'])): ?>
                  <div class="flex items-start p-3 rounded-lg bg-blue-50/50 hover:bg-blue-50 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                      <i class="ri-briefcase-line text-blue-600"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-600">Designation</p>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['designation']); ?></p>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($user['department'])): ?>
                  <div class="flex items-start p-3 rounded-lg bg-blue-50/50 hover:bg-blue-50 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                      <i class="ri-building-line text-blue-600"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-600">Department</p>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['department']); ?></p>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($user['yearsInLSPU'])): ?>
                  <div class="flex items-start p-3 rounded-lg bg-blue-50/50 hover:bg-blue-50 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                      <i class="ri-history-line text-blue-600"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-600">Years in LSPU</p>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['yearsInLSPU']); ?> years</p>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($user['teaching_status'])): ?>
                  <div class="flex items-start p-3 rounded-lg bg-blue-50/50 hover:bg-blue-50 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                      <i class="ri-user-settings-line text-blue-600"></i>
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-600">Employment Type</p>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['teaching_status']); ?></p>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
              
              <div class="mt-6 pt-6 border-t border-gray-100">
                <a href="profile.php" 
                  class="w-full inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-medium hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-xl">
                  <i class="ri-edit-line mr-3"></i>
                  Edit Profile
                </a>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Calendar Section -->
        <div class="calendar-card hover:shadow-custom-hover transition-all duration-300 hover-lift">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
              <i class="ri-calendar-line text-blue-500 mr-3 text-2xl"></i>
              Calendar
            </h3>
            <span class="text-sm text-gray-500">
              <?= date('F Y') ?>
            </span>
          </div>
          
          <div id="calendar" class="mb-6"></div>

          <?php if ($hasDeadline): ?>
            <div class="mt-6 p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl border border-blue-200">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center mr-3">
                  <i class="ri-time-line text-white text-xl"></i>
                </div>
                <div>
                  <p class="text-sm font-semibold text-blue-800">Current Deadline</p>
                  <p class="text-sm text-gray-700 font-medium"><?= htmlspecialchars($formattedDeadline) ?></p>
                  <?php if ($currentDeadlinePassed): ?>
                    <span class="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-medium mt-1">
                      <i class="ri-alert-line mr-1"></i> Deadline Passed
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  <?php if (!$hasProfile && isset($user)): ?>
    </div>
  <?php endif; ?>
</main>

<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Initialize calendar
  const calendarEl = document.getElementById('calendar');
  
  // Get deadline from PHP
  const deadline = '<?= $rawDeadline ?>';
  const hasDeadline = '<?= $hasDeadline ?>' === '1';
  const hasSubmitted = '<?= $hasSubmitted ?>' === '1';
  const userLastSubmitted = '<?= $userLastSubmitted ? $userLastSubmitted->format('Y-m-d H:i:s') : '' ?>';
  const showAssessment = '<?= $show_assessment ?>' === '1';
  const hasProfile = '<?= $hasProfile ?>' === '1';
  const submissionStatus = '<?= $submissionStatus ?>';
  const currentDeadlinePassed = '<?= $currentDeadlinePassed ?>' === '1';
  
  // Calendar events
  const calendarEvents = [];
  
  if (hasDeadline) {
    calendarEvents.push({
      title: '📅 Submission Deadline',
      start: deadline,
      color: '#ef4444',
      allDay: false,
      extendedProps: {
        type: 'deadline'
      }
    });
  }
  
  if (hasSubmitted) {
    calendarEvents.push({
      title: submissionStatus === 'late' ? '⏰ Late Submission' : '✅ Your Submission',
      start: userLastSubmitted,
      color: submissionStatus === 'late' ? '#f59e0b' : '#10b981',
      allDay: false,
      extendedProps: {
        type: 'submission'
      }
    });
  }
  
  // Initialize calendar with compact view
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev',
      center: 'title',
      right: 'next'
    },
    events: calendarEvents,
    eventClick: function(info) {
      const eventDate = new Date(info.event.start).toLocaleString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      
      let description = '';
      let icon = 'info';
      if (info.event.extendedProps.type === 'deadline') {
        description = 'Final deadline for assessment submission';
        icon = 'warning';
      } else if (info.event.extendedProps.type === 'submission') {
        description = info.event.title.includes('Late') 
          ? 'Your submission was received after the deadline' 
          : 'Your assessment was successfully submitted';
        icon = 'success';
      }
      
      Swal.fire({
        title: info.event.title.replace(/[📅✅⏰]/g, ''),
        html: `<div class="text-left">
                <p class="text-gray-700 mb-2"><strong>Date:</strong> ${eventDate}</p>
                ${description ? `<p class="text-gray-500">${description}</p>` : ''}
              </div>`,
        icon: icon,
        confirmButtonColor: '#3b82f6',
        confirmButtonText: 'OK',
        customClass: {
          popup: 'rounded-2xl'
        }
      });
    },
    height: 320,
    contentHeight: 280,
    aspectRatio: 1,
    eventDisplay: 'block',
    dayMaxEvents: 2,
    dayCellContent: function(e) {
      e.dayNumberText = e.dayNumberText.replace('日', '');
    }
  });
  calendar.render();

  // Notification dropdown toggle
  const notificationBtn = document.getElementById('notificationBtn');
  const notificationDropdown = document.getElementById('notificationDropdown');

  if (notificationBtn && notificationDropdown) {
    notificationBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notificationDropdown.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
        notificationDropdown.classList.add('hidden');
      }
    });
  }

  // Form handling elements
  const assessmentFormWrapper = document.getElementById('assessmentFormWrapper');
  const showFormBtn = document.getElementById('showFormBtn');

  // Toggle Assessment Form Show/Hide with conditions
  if (showFormBtn) {
    showFormBtn.addEventListener('click', () => {
      if (assessmentFormWrapper) {
        assessmentFormWrapper.classList.remove('hidden');
        assessmentFormWrapper.scrollIntoView({ 
          behavior: 'smooth', 
          block: 'center'
        });
        
        // Mark notification as read when form is shown
        fetch('mark_notification_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ mark_read: true })
        }).then(response => {
          if (!response.ok) {
            console.error('Failed to mark notification as read');
          }
        });

        // Show warning if deadline has passed
        if (currentDeadlinePassed) {
          Swal.fire({
            title: '⚠️ Late Submission',
            html: `<div class="text-left">
                    <p class="mb-3">The submission deadline has passed, but you can still submit your assessment.</p>
                    <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                      <p class="text-sm text-yellow-700"><strong>Note:</strong> Your submission will be marked as late.</p>
                    </div>
                  </div>`,
            icon: 'warning',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Continue',
            customClass: {
              popup: 'rounded-2xl'
            }
          });
        }
      }
    });
  }

  // If there's a notification and not submitted, automatically show the form
  <?php if ($show_assessment): ?>
    if (assessmentFormWrapper) {
      assessmentFormWrapper.classList.remove('hidden');
      assessmentFormWrapper.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center'
      });
      
      // Show warning if deadline has passed
      if (currentDeadlinePassed) {
        setTimeout(() => {
          Swal.fire({
            title: '⚠️ Late Submission',
            html: `<div class="text-left">
                    <p class="mb-3">The submission deadline has passed, but you can still submit your assessment.</p>
                    <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                      <p class="text-sm text-yellow-700"><strong>Note:</strong> Your submission will be marked as late.</p>
                    </div>
                  </div>`,
            icon: 'warning',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Continue',
            customClass: {
              popup: 'rounded-2xl'
            }
          });
        }, 500);
      }
    }
  <?php endif; ?>

  // Handle form submission status messages
  <?php if (isset($_SESSION['form_submission_status'])): ?>
    const status = '<?= $_SESSION['form_submission_status'] ?>';
    const message = '<?= $_SESSION['form_submission_message'] ?? '' ?>';
    
    if (status === 'success') {
      Swal.fire({
        title: '🎉 Success!',
        text: message,
        icon: 'success',
        confirmButtonColor: '#10b981',
        customClass: {
          popup: 'rounded-2xl'
        },
        willClose: () => {
          window.location.reload();
        }
      });
    } else if (status === 'error') {
      Swal.fire({
        title: '❌ Error!',
        text: message,
        icon: 'error',
        confirmButtonColor: '#ef4444',
        customClass: {
          popup: 'rounded-2xl'
        }
      });
    }
    
    <?php
    unset($_SESSION['form_submission_status']);
    unset($_SESSION['form_submission_message']);
    ?>
  <?php endif; ?>

  // Handle profile update return
  <?php if (isset($_GET['profile_updated']) && $_GET['profile_updated'] === '1') : ?>
    Swal.fire({
      title: '✅ Profile Updated!',
      text: 'Your profile has been successfully updated.',
      icon: 'success',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'rounded-2xl'
      },
      timer: 2000,
      timerProgressBar: true
    }).then(() => {
      history.replaceState(null, null, window.location.pathname);
    });
  <?php endif; ?>
  
  // Profile modal handling
  const profileModal = document.getElementById('profileModal');
  const modalCloseBtn = document.getElementById('modalCloseBtn');
  
  if (!hasProfile && profileModal) {
    // Prevent closing the modal if profile is incomplete
    modalCloseBtn.addEventListener('click', (e) => {
      e.preventDefault();
      Swal.fire({
        title: '⚠️ Profile Required',
        html: `<div class="text-left">
                <p class="mb-3">You must complete your profile to access the assessment features.</p>
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                  <p class="text-sm text-blue-700"><strong>Required:</strong> Complete all profile fields to proceed.</p>
                </div>
              </div>`,
        icon: 'warning',
        confirmButtonColor: '#3b82f6',
        confirmButtonText: 'Go to Profile',
        showCancelButton: true,
        cancelButtonText: 'Stay Here',
        customClass: {
          popup: 'rounded-2xl'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'profile.php';
        }
      });
    });
  }
});

// IDP dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
  const dropdownBtn = document.getElementById('idp-dropdown-btn');
  const dropdownMenu = document.getElementById('idp-dropdown-menu');
  
  if (dropdownBtn && dropdownMenu) {
    dropdownBtn.addEventListener('click', function() {
      dropdownBtn.parentElement.classList.toggle('open');
      dropdownMenu.classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      if (!dropdownBtn.contains(event.target) && !dropdownMenu.contains(event.target)) {
        dropdownBtn.parentElement.classList.remove('open');
        dropdownMenu.classList.add('hidden');
      }
    });
  }
  
  // Add active state to current page in navigation
  const currentPage = window.location.pathname.split('/').pop();
  const navLinks = document.querySelectorAll('.nav-item');
  navLinks.forEach(link => {
    if (link.getAttribute('href') === currentPage) {
      link.classList.add('active');
    }
  });
});

// Add hover effects to all cards
document.querySelectorAll('.hover-lift').forEach(card => {
  card.addEventListener('mouseenter', () => {
    card.style.transform = 'translateY(-5px)';
  });
  
  card.addEventListener('mouseleave', () => {
    card.style.transform = 'translateY(0)';
  });
});

// Smooth scrolling for assessment sidebar link
const assessmentSidebarLink = document.getElementById('assessment-sidebar-link');
if (assessmentSidebarLink) {
  assessmentSidebarLink.addEventListener('click', (e) => {
    e.preventDefault();
    const assessmentFormWrapper = document.getElementById('assessmentFormWrapper');
    if (assessmentFormWrapper) {
      assessmentFormWrapper.classList.remove('hidden');
      assessmentFormWrapper.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center'
      });
    }
  });
}
</script>
</body>
</html>