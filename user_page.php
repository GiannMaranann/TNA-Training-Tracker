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

        // Enhanced profile completeness check
        $profileQuery = $con->prepare("SELECT *, 
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

    // Fallback image
    if ($user && empty($user['image_data'])) {
        $user['image_data'] = 'default.png';
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
  <title>Dashboard</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <link rel="stylesheet" href="css/user_style.css">

</head>

<body class="flex h-screen bg-gray-50">
<!-- Sidebar -->
<aside class="w-64 bg-blue-900 text-white shadow-sm flex flex-col justify-between">
  <div class="h-full flex flex-col">
    <div class="p-6 flex items-center">
      <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2" />
      <a href="user_page.php" class="text-lg font-semibold text-white">Training Needs Assessment</a>
    </div>

    <nav class="flex-1 px-4 py-4">
      <div class="space-y-2">
        <a href="user_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-dashboard-line"></i></div>
          TNA
        </a>
        <div class="group">
          <button id="idp-dropdown-btn" class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <div class="flex items-center">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-text-line"></i></div>
              IDP Forms
            </div>
            <i class="ri-arrow-down-s-line transition-transform duration-300 group-[.open]:rotate-180"></i>
          </button>
          
          <div id="idp-dropdown-menu" class="hidden pl-8 mt-1 space-y-1 group-[.open]:block">
            <a href="Individual Development Plan.php" class="flex items-center px-4 py-2 text-sm rounded-md hover:bg-blue-700 transition-all">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-add-line"></i></div>
              Create New
            </a>
            <a href="save_idp_forms.php" class="flex items-center px-4 py-2 text-sm rounded-md hover:bg-blue-700 transition-all">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-list-line"></i></div>
              My Submitted Forms
            </a>
          </div>
        </div>
        <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-user-line"></i></div>
          Profile
        </a>
      </div>
    </nav>

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
  <?php if (!$hasProfile && isset($user)): ?>
    <!-- Modal Overlay -->
    <div id="profileModal" class="modal-overlay">
      <div class="modal-container">
        <button id="modalCloseBtn" class="modal-close">
          <i class="ri-close-line"></i>
        </button>
        <h2 class="modal-title">
          <i class="ri-user-settings-line text-blue-500 mr-2"></i>
          Complete Your Profile
        </h2>
        <div class="modal-content">
          <p>You need to complete your profile before you can access this page.</p>
          
          <div class="flex items-center justify-between mt-4">
            <span class="text-sm font-medium text-gray-600">
              Profile Completion: <?= $profileCompletionPercentage ?>%
            </span>
          </div>
          
          <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $profileCompletionPercentage ?>%"></div>
          </div>
          
          <?php if (!empty($missingProfileFields)): ?>
            <div class="mt-4">
              <p class="text-sm font-medium text-gray-700 mb-2">Missing Information:</p>
              <ul class="list-disc pl-5 text-sm text-gray-600">
                <?php foreach ($missingProfileFields as $field => $label): ?>
                  <li><?= htmlspecialchars($label) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-actions">
          <a href="profile.php" class="modal-btn-primary">
            <i class="ri-user-settings-line mr-2"></i>
            Complete Profile
          </a>
        </div>
      </div>
    </div>
    
    <!-- Blurred Content -->
    <div class="blur-sm">
  <?php endif; ?>

  <div class="p-6">
    <!-- Welcome Section -->
    <div id="dashboardSection" class="transition-transform duration-700">
      <div class="flex justify-between items-start mb-6">
        <h1 class="text-3xl font-bold">
          <?= $welcomeMessage ?>
        </h1>
        <div class="flex items-center gap-4">
          <div class="text-sm text-gray-500">
            <?= date('l, F j, Y') ?>
          </div>
          <?php if ($hasUnreadAssessmentNotification): ?>
            <div class="relative">
              <button id="notificationBtn" class="text-gray-600 hover:text-blue-600 focus:outline-none">
                <i class="ri-notification-3-fill text-xl"></i>
                <span class="notification-badge">
                  <?= count($unreadNotifications) ?>
                </span>
              </button>
              <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg z-50 border border-gray-200 notification-dropdown">
                <div class="p-3 border-b bg-blue-50">
                  <h3 class="text-sm font-medium text-gray-700">Assessment Notification</h3>
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

        <?php if ($hasProfile): ?>
          <!-- Show the assessment button when there's an active deadline and user hasn't submitted -->
          <?php if ($showAssessmentButton): ?>
            <div class="text-center mt-4">
              <button id="showFormBtn" class="px-6 py-3 text-white rounded-lg font-medium flex items-center mx-auto <?= $currentDeadlinePassed ? 'late-submission-btn' : 'assessment-btn' ?>">
                <i class="ri-edit-box-line mr-2"></i>
                <?= $currentDeadlinePassed ? 'Fill Out Training Needs Assessment (Late Submission)' : 'Fill Out Training Needs Assessment' ?>
              </button>
              <?php if ($currentDeadlinePassed): ?>
                <p class="text-sm text-yellow-600 mt-2">
                  <i class="ri-alert-line mr-1"></i> The deadline has passed, but you can still submit your assessment.
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Assessment Form Section -->
          <div id="assessmentFormWrapper" class="<?= $show_assessment ? '' : 'hidden' ?> bg-white shadow-custom rounded-lg mt-4 transition-all duration-300 ease-in-out hover:shadow-custom-hover assessment-form-container">
            <div class="form-header">
              <h3 class="text-lg">Training Needs Assessment Form</h3>
            </div>
            <?php include 'assessment_form_partial.php'; ?>
          </div>

          <!-- Submission Status Display -->
          <?php if ($hasSubmitted): ?>
            <div class="submission-status <?= $submissionStatus === 'late' ? 'submission-status-late' : '' ?>">
              <div class="submission-status-header">
                <i class="ri-checkbox-circle-fill submission-status-icon"></i>
                <h3 class="submission-status-title">
                  <?= $submissionStatus === 'late' ? 'Late Submission' : 'Assessment Submitted' ?>
                </h3>
              </div>
              
              <div class="submission-details">
                <div class="flex items-center justify-center">
                  <i class="ri-check-line <?= $submissionStatus === 'late' ? 'text-yellow-500' : 'text-green-500' ?> mr-2"></i>
                  <span class="submission-message">
                    <?= $submissionStatus === 'late' ? 'Your late submission was received' : 'Your submission was received' ?>
                  </span>
                </div>
                <p class="submission-date text-center">
                  <?= $userLastSubmitted ? $userLastSubmitted->format('F j, Y \a\t g:i a') : '' ?>
                </p>
              </div>
              
              <?php if ($submissionStatus === 'late'): ?>
                <p class="text-center text-yellow-700">Your submission was received after the deadline.</p>
              <?php else: ?>
                <p class="text-center text-gray-700">Thank you for completing your assessment.</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- No Active Deadline Message -->
          <?php if (!$hasDeadline): ?>
            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200 text-center">
              <div class="flex items-center justify-center mb-2">
                <i class="ri-time-line text-blue-500 text-2xl mr-2"></i>
                <h3 class="text-lg font-semibold text-blue-800">No Active Assessment</h3>
              </div>
              <p class="text-gray-700">There is currently no active assessment period.</p>
              <p class="text-sm text-gray-500 mt-1">Please wait for the administrator to announce the next assessment deadline.</p>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <!-- Right Column: Profile Information and Calendar --> 
      <div class="w-full lg:w-1/3 space-y-8">
        <?php if (isset($user)): ?>
          <div class="bg-white rounded-xl shadow-custom overflow-hidden hover:shadow-custom-hover transition-all">
            <div class="p-6 text-center">
              <?php
                // Default profile image (siguraduhin meron ka sa "images/no_prof.jpg")
                $defaultImage = 'images/noprofile.jpg'; 

                // Check kung may image sa DB
                $imageSrc = !empty($user['image_data']) 
                  ? (str_starts_with($user['image_data'], 'data:image') 
                      ? $user['image_data'] 
                      : 'data:image/jpeg;base64,' . $user['image_data'])
                  : $defaultImage;
              ?>
              <img class="w-24 h-24 rounded-full mx-auto mb-4 border-4 border-blue-100"
                  src="<?= htmlspecialchars($imageSrc); ?>"
                  alt="Profile Picture"
                  onerror="this.onerror=null;this.src='<?= $defaultImage; ?>';">

              <h2 class="text-xl font-bold text-gray-800">
                <?= htmlspecialchars($user['name']); ?>
              </h2>

              <div class="mt-4 text-left text-gray-700 text-sm space-y-2">
                <?php if (!empty($user['educationalAttainment'])): ?>
                  <p class="flex items-center">
                    <i class="ri-graduation-cap-line text-blue-500 mr-2"></i>
                    <span class="font-semibold">Educational Attainment:</span> 
                    <?= htmlspecialchars($user['educationalAttainment']); ?>
                  </p>
                <?php endif; ?>

                <?php if (!empty($user['specialization'])): ?>
                  <p class="flex items-center">
                    <i class="ri-medal-line text-blue-500 mr-2"></i>
                    <span class="font-semibold">Specialization:</span> 
                    <?= htmlspecialchars($user['specialization']); ?>
                  </p>
                <?php endif; ?>

                <?php if (!empty($user['designation'])): ?>
                  <p class="flex items-center">
                    <i class="ri-briefcase-line text-blue-500 mr-2"></i>
                    <span class="font-semibold">Designation:</span> 
                    <?= htmlspecialchars($user['designation']); ?>
                  </p>
                <?php endif; ?>

                <?php if (!empty($user['department'])): ?>
                  <p class="flex items-center">
                    <i class="ri-building-line text-blue-500 mr-2"></i>
                    <span class="font-semibold">Department:</span> 
                    <?= htmlspecialchars($user['department']); ?>
                  </p>
                <?php endif; ?>

                <?php if (!empty($user['yearsInLSPU'])): ?>
                  <p class="flex items-center">
                    <i class="ri-history-line text-blue-500 mr-2"></i>
                    <span class="font-semibold">Years in LSPU:</span> 
                    <?= htmlspecialchars($user['yearsInLSPU']); ?>
                  </p>
                <?php endif; ?>

                <?php if (!empty($user['teaching_status'])): ?>
                  <p class="flex items-center">
                    <i class="ri-user-settings-line text-blue-500 mr-2"></i>
                    <span class="font-semibold">Type of Employment:</span> 
                    <?= htmlspecialchars($user['teaching_status']); ?>
                  </p>
                <?php endif; ?>
              </div>
              
              <div class="mt-4">
                <a href="profile.php" 
                  class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                  <i class="ri-edit-line mr-2"></i>
                  Edit Profile
                </a>
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
  
  <?php if (!$hasProfile && isset($user)): ?>
    </div> <!-- Close the blurred content div -->
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
      title: 'Submission Deadline',
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
      title: submissionStatus === 'late' ? 'Late Submission' : 'Your Submission',
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
        description = info.event.title === 'Late Submission' 
          ? 'Your submission was received after the deadline' 
          : 'Your submission was received';
      }
      
      Swal.fire({
        title: info.event.title,
        html: `<div class="text-center">
                <p class="text-gray-700 mb-2">${eventDate}</p>
                ${description ? `<p class="text-gray-500">${description}</p>` : ''}
              </div>`,
        icon: 'info',
        confirmButtonColor: '#6366f6',
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

  // Form handling elements
  const assessmentFormWrapper = document.getElementById('assessmentFormWrapper');
  const showFormBtn = document.getElementById('showFormBtn');

  // Toggle Assessment Form Show/Hide with conditions
  if (showFormBtn) {
    showFormBtn.addEventListener('click', () => {
      if (assessmentFormWrapper) {
        assessmentFormWrapper.classList.remove('hidden');
        assessmentFormWrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
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
            title: 'Late Submission',
            text: 'The submission deadline has passed, but you can still submit your assessment.',
            icon: 'warning',
            confirmButtonColor: '#6366f6',
          });
        }
      }
    });
  }

  // If there's a notification and not submitted, automatically show the form
  <?php if ($show_assessment): ?>
    if (assessmentFormWrapper) {
      assessmentFormWrapper.classList.remove('hidden');
      assessmentFormWrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      
      // Show warning if deadline has passed
      if (currentDeadlinePassed) {
        Swal.fire({
          title: 'Late Submission',
          text: 'The submission deadline has passed, but you can still submit your assessment.',
          icon: 'warning',
          confirmButtonColor: '#6366f6',
        });
      }
    }
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
        confirmButtonColor: '#6366f6',
        willClose: () => {
          window.location.reload();
        }
      });
    } else if (status === 'error') {
      Swal.fire({
        title: 'Error!',
        text: message,
        icon: 'error',
        confirmButtonColor: '#6366f6',
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
      title: 'Profile Updated!',
      text: 'Your profile has been successfully updated.',
      icon: 'success',
      confirmButtonColor: '#6366f6',
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
        title: 'Profile Required',
        text: 'You must complete your profile to access this page.',
        icon: 'warning',
        confirmButtonColor: '#6366f6',
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
  }
});
</script>
</body>
</html>