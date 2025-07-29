<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Manila');

// Initialize variables
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

// Get latest submission deadline
$result = $con->query("SELECT submission_deadline FROM settings WHERE id = 1");
if ($result && $row = $result->fetch_assoc()) {
    $rawDeadline = $row['submission_deadline'];
    if (!empty($rawDeadline)) {
        $formattedDeadline = date("F j, Y, g:i a", strtotime($rawDeadline));
    }
}

$hasDeadline = !empty($rawDeadline);
$now = new DateTime();
$deadlineDT = $hasDeadline ? new DateTime($rawDeadline) : null;

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Load user profile
    $profileQuery = $con->prepare("SELECT * FROM users WHERE id = ?");
    $profileQuery->bind_param("i", $userId);
    $profileQuery->execute();
    $profileResult = $profileQuery->get_result();

    if ($profileResult && $profileResult->num_rows > 0) {
        $user = $profileResult->fetch_assoc();
        $hasProfile = true;
        $welcomeMessage = "Welcome, " . htmlspecialchars($_SESSION['name'] ?? 'User') . "!";
    }

    // Check assessment submission status
    if ($hasProfile && $hasDeadline) {
        $submissionStmt = $con->prepare("
            SELECT created_at FROM assessments 
            WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $submissionStmt->bind_param("i", $userId);
        $submissionStmt->execute();
        $submissionResult = $submissionStmt->get_result();

        if ($submissionResult && $submissionResult->num_rows > 0) {
            $row = $submissionResult->fetch_assoc();
            $userLastSubmitted = new DateTime($row['created_at']);
            $hasSubmitted = true;
        }

        // Determine submission status
        if ($hasSubmitted) {
            if ($userLastSubmitted <= $deadlineDT) {
                $submissionStatus = 'submitted';
            } else {
                $submissionStatus = 'submitted (late)';
            }
            $show_assessment = false;
            $showNotification = false;
        } else {
            if ($now <= $deadlineDT) {
                $submissionStatus = 'on time';
                $show_assessment = true;
                $showNotification = true;
            } else {
                $submissionStatus = 'late';
                $show_assessment = false;
                $showNotification = true;
            }
        }

        // Update notification flag
        $stmt = $con->prepare("UPDATE users SET has_notification = ? WHERE id = ?");
        $notifFlag = $showNotification ? 1 : 0;
        $stmt->bind_param("ii", $notifFlag, $userId);
        $stmt->execute();
    }
}

// Fallback image if none
if ($user && empty($user['image_data'])) {
    $user['image_data'] = 'default.png';
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
    #noProfileModal {
      transition: opacity 0.3s ease;
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

    <!-- Welcome Section -->
    <div id="dashboardSection" class="transition-transform duration-700">
      <div class="flex justify-between items-start mb-6">
        <h1 class="text-3xl font-bold">
          <?= $welcomeMessage ?>
        </h1>
        <div class="text-sm text-gray-500">
          <?= date('l, F j, Y') ?>
        </div>
      </div>
    </div>

    <!-- Notification Banner -->
    <div id="notificationBanner" class="hidden p-4 rounded mb-6 font-semibold text-center"></div>

    <?php if ($showNotification): ?>
      <div class="bg-yellow-100 text-yellow-800 p-3 rounded font-semibold text-center mb-6 flex items-center justify-center">
        <i class="ri-alarm-warning-line mr-2"></i>
        <?php if ($hasSubmitted): ?>
          You have already submitted your assessment.
        <?php else: ?>
          Please complete the assessment form before the submission deadline: <?= htmlspecialchars($formattedDeadline); ?>
        <?php endif; ?>
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
                You have already submitted your assessment.
              </div>
              <?php if ($submissionStatus === 'submitted (late)'): ?>
                <div class="text-sm text-yellow-700 mt-2 flex items-center justify-center">
                  <i class="ri-time-line mr-1"></i>
                  Note: Your submission was after the deadline.
                </div>
              <?php endif; ?>
            </div>

          <?php elseif ($hasDeadline && $now > $deadlineDT): ?>
            <div class="text-center mt-8">
              <div class="inline-flex items-center bg-red-100 text-red-800 px-4 py-2 rounded-full">
                <i class="ri-time-line mr-2"></i>
                The submission deadline has passed.
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
  <div id="noProfileModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 <?= $hasProfile ? 'hidden' : '' ?>">
    <div class="bg-white p-6 rounded-lg shadow-lg text-center max-w-sm w-full">
      <h2 class="text-lg font-semibold mb-4">Create your profile to continue</h2>
      <button id="createProfileBtn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition font-medium">
        Create Profile
      </button>
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
  const submissionStatus = '<?= $submissionStatus ?>';
  const userLastSubmitted = '<?= $userLastSubmitted ? $userLastSubmitted->format('Y-m-d H:i:s') : '' ?>';
  
  // Calendar events
  const calendarEvents = [];
  
  if (hasDeadline) {
    calendarEvents.push({
      title: 'Submission Deadline',
      start: deadline,
      color: '#ef4444', // red
      allDay: false
    });
  }
  
  if (hasSubmitted) {
    calendarEvents.push({
      title: submissionStatus === 'submitted (late)' ? 'Late Submission' : 'Submission',
      start: userLastSubmitted,
      color: submissionStatus === 'submitted (late)' ? '#f59e0b' : '#10b981', // yellow or green
      allDay: false
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
      Swal.fire({
        title: info.event.title,
        html: `<p>Date: ${info.event.start.toLocaleString()}</p>
               <p>Status: ${info.event.title.includes('Deadline') ? 'Deadline' : 
                  info.event.title.includes('Late') ? 'Late Submission' : 'On Time Submission'}</p>`,
        icon: info.event.title.includes('Deadline') ? 'warning' : 
              info.event.title.includes('Late') ? 'warning' : 'success',
        confirmButtonColor: '#6366f1',
      });
    },
    height: 300,
    contentHeight: 250,
    aspectRatio: 1
  });
  calendar.render();

  // Modal and form handling
  const dashboardSection = document.getElementById('dashboardSection');
  const assessmentSection = document.getElementById('assessmentSection');
  const assessmentFormWrapper = document.getElementById('assessmentFormWrapper');
  const showFormBtn = document.getElementById('showFormBtn');
  const submissionDeadline = document.getElementById('submissionDeadline');
  const notificationBanner = document.getElementById('notificationBanner');
  const noProfileModal = document.getElementById('noProfileModal');
  const createProfileBtn = document.getElementById('createProfileBtn');

  function toggleView(showAssessment) {
    if (dashboardSection) dashboardSection.classList.toggle('hidden', showAssessment);
    if (assessmentSection) assessmentSection.classList.toggle('hidden', !showAssessment);
  }

  function showNotification(message, statusType) {
    if (!notificationBanner) return;
    notificationBanner.textContent = message;
    notificationBanner.classList.remove('hidden');

    // Reset all status classes
    notificationBanner.classList.remove('bg-green-100', 'text-green-800', 'bg-yellow-100', 'text-yellow-800', 'bg-red-100', 'text-red-800');

    // Apply color based on status
    if (statusType === 'on-time') {
      notificationBanner.classList.add('bg-green-100', 'text-green-800');
    } else if (statusType === 'late') {
      notificationBanner.classList.add('bg-yellow-100', 'text-yellow-800');
    } else if (statusType === 'very-late') {
      notificationBanner.classList.add('bg-red-100', 'text-red-800');
    }
  }

  function clearNotification() {
    if (!notificationBanner) return;
    notificationBanner.textContent = '';
    notificationBanner.classList.add('hidden');
    notificationBanner.classList.remove('bg-green-100', 'text-green-800', 'bg-yellow-100', 'text-yellow-800', 'bg-red-100', 'text-red-800');
  }

  function showNoProfileModal() {
    if (noProfileModal) noProfileModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }

  if (createProfileBtn) {
    createProfileBtn.addEventListener('click', () => {
      window.location.href = 'profile.php';
    });
  }

  // Show modal if user doesn't have a profile
  <?php if (!$hasProfile): ?>
    showNoProfileModal();
  <?php endif; ?>

  // Initial state: show dashboard, hide assessment form
  toggleView(false);
  if (assessmentFormWrapper) assessmentFormWrapper.classList.add('hidden');

  // Toggle Assessment Form Show/Hide
  if (showFormBtn && assessmentFormWrapper) {
    showFormBtn.addEventListener('click', () => {
      assessmentFormWrapper.classList.toggle('hidden');
      assessmentFormWrapper.scrollIntoView({ behavior: 'smooth' });
    });
  }
});
</script>

</body>
</html>