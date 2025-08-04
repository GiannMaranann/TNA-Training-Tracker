<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Manila');

// Initialize variables with default values
$submissionStatus = '';
$showNotification = false;
$formattedDeadline = '';
$rawDeadline = null;
$hasProfile = false;
$user = null;
$show_assessment = false;
$welcomeMessage = '';
$hasSubmitted = false;
$userLastSubmitted = null;
$currentDeadlinePassed = false;
$hasNotification = false;
$unreadNotifications = [];
$error = null;

try {
    // Get latest submission deadline
    $result = $con->query("SELECT submission_deadline FROM settings WHERE id = 1");
    if ($result && $row = $result->fetch_assoc()) {
        $rawDeadline = $row['submission_deadline'] ?? null;
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

        // Load user profile with better profile checking
        $profileQuery = $con->prepare("SELECT *, 
                                    (CASE WHEN name IS NOT NULL AND name != '' AND 
                                          educationalAttainment IS NOT NULL AND educationalAttainment != '' 
                                    THEN TRUE ELSE FALSE END) as has_complete_profile 
                                    FROM users WHERE id = ?");
        if (!$profileQuery) {
            throw new Exception("Prepare failed: " . $con->error);
        }
        
        $profileQuery->bind_param("i", $userId);
        if (!$profileQuery->execute()) {
            throw new Exception("Execute failed: " . $profileQuery->error);
        }
        
        $profileResult = $profileQuery->get_result();

        if ($profileResult && $profileResult->num_rows > 0) {
            $user = $profileResult->fetch_assoc();
            // Use the computed has_complete_profile from SQL
            $hasProfile = (bool)$user['has_complete_profile'];
            $welcomeMessage = "Welcome, " . htmlspecialchars($user['name'] ?? 'User') . "!";
            $hasNotification = (bool)($user['has_notification'] ?? false);
        } else {
            $hasProfile = false;
        }

        // Check for unread notifications
        $notifQuery = $con->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
        $notifQuery->bind_param("i", $userId);
        $notifQuery->execute();
        $notifResult = $notifQuery->get_result();
        $unreadNotifications = $notifResult->fetch_all(MYSQLI_ASSOC);

        // Mark notifications as read when page loads
        if (count($unreadNotifications) > 0) {
            $markRead = $con->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
            $markRead->bind_param("i", $userId);
            $markRead->execute();
            $markRead->close();
            
            // Update notification flag if no more unread notifications
            $checkUnread = $con->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
            $checkUnread->bind_param("i", $userId);
            $checkUnread->execute();
            $checkResult = $checkUnread->get_result();
            $row = $checkResult->fetch_row();
            if ($row[0] == 0) {
                $updateFlag = $con->prepare("UPDATE users SET has_notification = FALSE WHERE id = ?");
                $updateFlag->bind_param("i", $userId);
                $updateFlag->execute();
                $updateFlag->close();
            }
            $checkUnread->close();
        }

        // Check assessment submission status for current deadline
        if ($hasProfile && $hasDeadline) {
            $submissionStmt = $con->prepare("
                SELECT created_at FROM assessments 
                WHERE user_id = ? AND created_at <= ?
                ORDER BY created_at DESC LIMIT 1
            ");
            $submissionStmt->bind_param("is", $userId, $rawDeadline);
            $submissionStmt->execute();
            $submissionResult = $submissionStmt->get_result();

            if ($submissionResult && $submissionResult->num_rows > 0) {
                $row = $submissionResult->fetch_assoc();
                $userLastSubmitted = new DateTime($row['created_at']);
                $hasSubmitted = true;
            }

            // Determine if assessment form should be shown
            if (!$hasSubmitted && $now <= $deadlineDT && $hasNotification) {
                $showNotification = true;
                $show_assessment = true;
            }
        }
    }

    // Fallback image if none
    if ($user && empty($user['image_data'])) {
        $user['image_data'] = 'default.png';
    }

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Error in user_page.php: " . $error);
}

// Close database connection if it exists
if (isset($con) && $con) {
    $con->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>

  <!-- FullCalendar CSS -->
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />

  <!-- Tailwind Config -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#6366f1',
            secondary: '#818cf8',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444'
          },
          fontFamily: {
            sans: ['Poppins', 'sans-serif'],
          },
          borderRadius: {
            DEFAULT: '8px',
            'button': '8px'
          }
        }
      }
    };
  </script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />

  <style>
    * {
      font-family: 'Poppins', sans-serif !important;
    }
    body {
      background-color: #f3f4f6;
    }
    .fc-daygrid-event {
      cursor: pointer;
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
    .transition-all {
      transition-property: all;
      transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
      transition-duration: 150ms;
    }
    
    /* Notification styles */
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
    
    /* Smaller calendar styles */
    #calendar {
      height: 300px;
      width: 100%;
    }
    .fc .fc-toolbar-title {
      font-size: 1rem;
    }
    .fc .fc-col-header-cell-cushion {
      font-size: 0.75rem;
      padding: 2px 4px;
    }
    .fc .fc-daygrid-day-number {
      font-size: 0.75rem;
    }
    .fc .fc-daygrid-day-frame {
      min-height: auto;
    }
    .fc .fc-button {
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
    }
    /* Modal styles */
    .modal {
      transition: opacity 0.3s ease;
    }
    .modal-content {
      transform: translateY(0);
      transition: transform 0.3s ease;
    }
    .modal.hidden {
      opacity: 0;
      pointer-events: none;
    }
    .modal.hidden .modal-content {
      transform: translateY(-20px);
    }
  </style>
</head>

<body class="flex h-screen bg-gray-50">
<!-- Sidebar -->
<aside class="w-64 bg-blue-900 text-white shadow-sm flex flex-col justify-between">
  <div class="h-full flex flex-col">
    <!-- Logo & Title -->
    <div class="p-6 flex items-center">
      <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2" />
      <a href="user_page.php" class="text-lg font-semibold text-white">Training Needs Assessment</a>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-6">
      <div class="space-y-2">
        <a href="user_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-dashboard-line"></i></div>
          TNA
        </a>
        <a href="idp_form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-text-line"></i></div>
          IDP Form
        </a>
        <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-user-line"></i></div>
          Profile
        </a>
      </div>
    </nav>

    <!-- Sign Out -->
    <div class="p-4">
      <a href="homepage.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md hover:bg-red-600 text-white">
        <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-logout-box-line"></i></div>
        Sign Out
      </a>
    </div>
  </div>
</aside>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto relative">
  <div class="p-8">
    <!-- Welcome Section with Notification Bell -->
    <div id="dashboardSection" class="transition-transform duration-700">
      <div class="flex justify-between items-start mb-6">
        <h1 class="text-3xl font-bold">
          <?= $welcomeMessage ?>
        </h1>
        <div class="flex items-center gap-4">
          <div class="text-sm text-gray-500">
            <?= date('l, F j, Y') ?>
          </div>
          <?php if (count($unreadNotifications) > 0): ?>
            <div class="relative">
              <button id="notificationBtn" class="text-gray-600 hover:text-blue-600 focus:outline-none">
                <i class="ri-notification-3-fill text-xl"></i>
                <span class="notification-badge">
                  <?= count($unreadNotifications) ?>
                </span>
              </button>
              <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg z-50 border border-gray-200 notification-dropdown">
                <div class="p-3 border-b bg-blue-50">
                  <h3 class="text-sm font-medium text-gray-700">Notifications</h3>
                </div>
                <div class="divide-y divide-gray-100">
                  <?php foreach ($unreadNotifications as $notif): ?>
                    <div class="p-3 notification-item">
                      <p class="text-sm"><?= htmlspecialchars($notif['message']) ?></p>
                      <p class="text-xs text-gray-500 mt-1">
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

    <!-- Notification Banner -->
    <div id="notificationBanner" class="hidden p-4 rounded mb-6 font-semibold text-center"></div>

    <?php if ($showNotification): ?>
      <div class="bg-yellow-100 text-yellow-800 p-3 rounded font-semibold text-center mb-6 flex items-center justify-center">
        <i class="ri-alarm-warning-line mr-2"></i>
        Please complete the assessment form before the submission deadline: <?= htmlspecialchars($formattedDeadline); ?>
      </div>
    <?php endif; ?>

    <!-- Dashboard Layout -->
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Left Column -->
      <div class="flex-1 space-y-8">
        <!-- Assessment Summary -->
        <div class="bg-white shadow-custom rounded-lg p-6 hover:shadow-custom-hover transition-all">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-700">Assessment Forms</h2>
            <?php if ($hasDeadline): ?>
              <div class="flex items-center bg-blue-50 px-3 py-1 rounded-full">
                <i class="ri-time-line text-blue-500 mr-2"></i>
                <span class="text-sm font-medium text-blue-600">Deadline: <?= htmlspecialchars($formattedDeadline) ?></span>
              </div>
            <?php endif; ?>
          </div>
          <?php include 'assessment_summary_table.php'; ?>
        </div>

        <!-- Conditional Assessment Form Display -->
        <?php if (!$hasProfile): ?>
          <div class="text-center text-red-500 mt-8 font-medium flex items-center justify-center">
            <i class="ri-error-warning-line mr-2"></i>
            Please complete your profile first to access the assessment form.
          </div>
        <?php else: ?>
          <?php if ($show_assessment): ?>
            <!-- Show the button for new submissions -->
            <div class="text-center mt-4">
              <button id="showFormBtn" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all font-medium flex items-center mx-auto">
                <i class="ri-edit-box-line mr-2"></i>
                Fill Out Training Needs Assessment
              </button>
            </div>

            <!-- Hidden Assessment Form Section -->
            <div id="assessmentFormWrapper" class="hidden bg-white shadow-custom rounded-lg p-6 mt-4 transition-all duration-300 ease-in-out hover:shadow-custom-hover">
              <?php include 'assessment_form_partial.php'; ?>
            </div>
          <?php elseif ($hasSubmitted): ?>
            <div class="text-center mt-8">
              <div class="inline-flex items-center bg-green-100 text-green-800 px-4 py-2 rounded-full">
                <i class="ri-checkbox-circle-fill mr-2"></i>
                You have already submitted your assessment for this deadline.
              </div>
            </div>
          <?php elseif ($currentDeadlinePassed): ?>
            <div class="text-center mt-8">
              <div class="inline-flex items-center bg-red-100 text-red-800 px-4 py-2 rounded-full">
                <i class="ri-time-line mr-2"></i>
                The submission deadline has passed.
              </div>
              <?php if ($hasDeadline): ?>
                <div class="mt-4 text-gray-600">
                  The deadline was <?= htmlspecialchars($formattedDeadline) ?>
                </div>
              <?php endif; ?>
            </div>
          <?php elseif (!$hasNotification): ?>
            <div class="text-center mt-8">
              <div class="inline-flex items-center bg-blue-100 text-blue-800 px-4 py-2 rounded-full">
                <i class="ri-information-line mr-2"></i>
                Please wait for a new assessment period to be announced.
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Right Column: Profile Information and Calendar -->
      <div class="w-full lg:w-1/3 space-y-8">
        <?php if ($hasProfile && $user): ?>
          <div class="bg-white rounded-xl shadow-custom overflow-hidden hover:shadow-custom-hover transition-all">
            <div class="p-6 text-center">
              <?php
                $imageSrc = !empty($user['image_data']) 
                  ? (str_starts_with($user['image_data'], 'data:image') 
                      ? $user['image_data'] 
                      : 'data:image/jpeg;base64,' . $user['image_data'])
                  : 'https://via.placeholder.com/150';
              ?>
              <img class="w-24 h-24 rounded-full mx-auto mb-4 border-4 border-blue-100"
                   src="<?= htmlspecialchars($imageSrc); ?>"
                   alt="Profile Picture">

              <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['name']); ?></h2>

              <div class="mt-4 text-left text-gray-700 text-sm space-y-2">
                <p class="flex items-center">
                  <i class="ri-graduation-cap-line text-blue-500 mr-2"></i>
                  <span class="font-semibold">Educational Attainment:</span> <?= htmlspecialchars($user['educationalAttainment']); ?>
                </p>
                <p class="flex items-center">
                  <i class="ri-medal-line text-blue-500 mr-2"></i>
                  <span class="font-semibold">Specialization:</span> <?= htmlspecialchars($user['specialization']); ?>
                </p>
                <p class="flex items-center">
                  <i class="ri-briefcase-line text-blue-500 mr-2"></i>
                  <span class="font-semibold">Designation:</span> <?= htmlspecialchars($user['designation']); ?>
                </p>
                <p class="flex items-center">
                  <i class="ri-building-line text-blue-500 mr-2"></i>
                  <span class="font-semibold">Department:</span> <?= htmlspecialchars($user['department']); ?>
                </p>
                <p class="flex items-center">
                  <i class="ri-history-line text-blue-500 mr-2"></i>
                  <span class="font-semibold">Years in LSPU:</span> <?= htmlspecialchars($user['yearsInLSPU']); ?>
                </p>
                <p class="flex items-center">
                  <i class="ri-user-settings-line text-blue-500 mr-2"></i>
                  <span class="font-semibold">Type of Employment:</span> <?= htmlspecialchars($user['teaching_status']); ?>
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Calendar Section -->
        <div class="bg-white rounded-xl shadow-custom p-4 hover:shadow-custom-hover transition-all">
          <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
            <i class="ri-calendar-line text-blue-500 mr-2"></i>
            Calendar
          </h3>
          <div id="calendar"></div>
          <?php if ($hasDeadline): ?>
            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
              <div class="flex items-center">
                <i class="ri-time-line text-blue-500 mr-2"></i>
                <div>
                  <p class="text-sm font-medium text-blue-600">Current Deadline</p>
                  <p class="text-sm text-gray-700"><?= htmlspecialchars($formattedDeadline) ?></p>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for No Profile -->
  <div id="noProfileModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 <?= $hasProfile ? 'hidden' : '' ?>">
      <div class="modal-content bg-white p-6 rounded-lg shadow-lg text-center max-w-sm w-full">
          <div class="mb-4">
              <i class="ri-user-3-line text-4xl text-blue-500"></i>
          </div>
          <h2 class="text-xl font-semibold mb-2">Profile Required</h2>
          <p class="text-gray-600 mb-4">You need to complete your profile before accessing the assessment form.</p>
          <div class="flex justify-center space-x-3">
              <button id="cancelProfileBtn" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300 transition font-medium">
                  Later
              </button>
              <button id="createProfileBtn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition font-medium">
                  Create Profile Now
              </button>
          </div>
      </div>
  </div>
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
  
  // Calendar events
  const calendarEvents = [];
  
  if (hasDeadline) {
    calendarEvents.push({
      title: 'Submission Deadline',
      start: deadline,
      color: '#ef4444', // red
      allDay: false,
      extendedProps: {
        type: 'deadline'
      }
    });
  }
  
  if (hasSubmitted) {
    calendarEvents.push({
      title: 'Your Submission',
      start: userLastSubmitted,
      color: '#10b981', // green
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
      left: 'prev,next',
      center: 'title',
      right: ''
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
      if (info.event.extendedProps.type === 'deadline') {
        description = 'Final deadline for submission';
      } else if (info.event.extendedProps.type === 'submission') {
        description = 'Your last submission date';
      }
      
      Swal.fire({
        title: info.event.title,
        html: `<div class="text-left">
                <p class="text-gray-700 mb-2">${eventDate}</p>
                ${description ? `<p class="text-gray-500">${description}</p>` : ''}
              </div>`,
        icon: 'info',
        confirmButtonColor: '#6366f1',
      });
    },
    height: 300,
    contentHeight: 250,
    aspectRatio: 1,
    eventDisplay: 'block'
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

  // Form handling
  const assessmentFormWrapper = document.getElementById('assessmentFormWrapper');
  const showFormBtn = document.getElementById('showFormBtn');

  // Toggle Assessment Form Show/Hide
  if (showFormBtn && assessmentFormWrapper) {
    showFormBtn.addEventListener('click', () => {
      assessmentFormWrapper.classList.toggle('hidden');
      if (!assessmentFormWrapper.classList.contains('hidden')) {
        assessmentFormWrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  }

  // Enhanced Modal handling
  const noProfileModal = document.getElementById('noProfileModal');
  const createProfileBtn = document.getElementById('createProfileBtn');
  const cancelProfileBtn = document.getElementById('cancelProfileBtn');

  if (createProfileBtn) {
    createProfileBtn.addEventListener('click', () => {
      // Store current scroll position
      sessionStorage.setItem('scrollPosition', window.scrollY);
      
      // Redirect to profile.php with return parameter
      window.location.href = 'profile.php?source=modal';
    });
  }

  if (cancelProfileBtn) {
    cancelProfileBtn.addEventListener('click', () => {
      noProfileModal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
      
      // Set cookie to remember "Later" for 24 hours
      document.cookie = "profileRemindLater=true; max-age=86400; path=/";
    });
  }

  // Show modal if user doesn't have a profile and hasn't clicked "Later"
  <?php if (!$hasProfile && !isset($_COOKIE['profileRemindLater'])): ?>
    document.body.classList.add('overflow-hidden');
    setTimeout(() => {
      noProfileModal.classList.remove('hidden');
    }, 300); // Slight delay for better UX
  <?php endif; ?>

  // Handle form submission status messages
  <?php if (isset($_SESSION['form_submission_status'])): ?>
    const status = '<?= $_SESSION['form_submission_status'] ?>';
    const message = '<?= $_SESSION['form_submission_message'] ?? '' ?>';
    
    if (status === 'success') {
      Swal.fire({
        title: 'Success!',
        text: message,
        icon: 'success',
        confirmButtonColor: '#6366f1',
        willClose: () => {
          window.location.reload();
        }
      });
    } else if (status === 'error') {
      Swal.fire({
        title: 'Error!',
        text: message,
        icon: 'error',
        confirmButtonColor: '#6366f1',
      });
    }
    
    <?php
    // Clear the session variables
    unset($_SESSION['form_submission_status']);
    unset($_SESSION['form_submission_message']);
    ?>
  <?php endif; ?>

  // Handle profile update return
  <?php if (isset($_GET['profile_updated']) && $_GET['profile_updated'] === '1'): ?>
    Swal.fire({
      title: 'Profile Updated!',
      text: 'Your profile has been successfully updated.',
      icon: 'success',
      confirmButtonColor: '#6366f1',
    }).then(() => {
      // Remove the query parameter without reload
      history.replaceState(null, null, window.location.pathname);
      
      // Restore scroll position if coming from modal
      const scrollPosition = sessionStorage.getItem('scrollPosition');
      if (scrollPosition) {
        window.scrollTo(0, parseInt(scrollPosition));
        sessionStorage.removeItem('scrollPosition');
      }
    });
  <?php endif; ?>
});
</script>
</body>
</html>