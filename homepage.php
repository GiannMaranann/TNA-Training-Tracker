<?php
session_start();

$errors = [
  'login' => $_SESSION['login_error'] ?? '',
  'register' => $_SESSION['register_error'] ?? '',
  'register_success' => $_SESSION['register_success'] ?? '',
  'account_status' => $_SESSION['account_status'] ?? '',
  'password_reset' => $_SESSION['password_reset'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset();

function showError($error) {
  return !empty($error) ? "<p class='error_message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
  return $formName === $activeForm ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
  <title>Homepage</title>

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Poppins', 'sans-serif'],
          },
          colors: {
            primary: '#4F46E5',
            secondary: '#10B981',
            danger: '#EF4444',
            success: '#10B981',
            warning: '#F59E0B',
            info: '#3B82F6',
          },
          borderRadius: {
            none: '0px',
            sm: '4px',
            DEFAULT: '8px',
            md: '12px',
            lg: '16px',
            xl: '20px',
            '2xl': '24px',
            '3xl': '32px',
            full: '9999px',
            button: '8px',
          },
        },
      },
    };
  </script>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />

  <!-- External CSS -->
  <link rel="stylesheet" href="test_style.css" />

  <!-- particles.js -->
  <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>

  <!-- Animate.css for smooth animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

  <style>
    html, body {
      max-width: 100%;
      overflow: hidden;
      margin: 0;
      padding: 0;
      height: 100%;
    }

    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      max-width: 350px;
      width: 100%;
    }

    .notification-item {
      animation: slideInRight 0.3s ease-out forwards;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 10px;
    }

    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }

    .notification-success {
      background-color: #ECFDF5;
      border-left: 4px solid #10B981;
      color: #065F46;
    }

    .notification-error {
      background-color: #FEF2F2;
      border-left: 4px solid #EF4444;
      color: #991B1B;
    }

    .notification-warning {
      background-color: #FFFBEB;
      border-left: 4px solid #F59E0B;
      color: #92400E;
    }

    .notification-info {
      background-color: #EFF6FF;
      border-left: 4px solid #3B82F6;
      color: #1E40AF;
    }

    footer {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      padding: 1rem 0;
      text-align: center;
      border-top: 1px solid #e5e7eb;
      color: #6b7280;
      font-size: 0.875rem;
      z-index: 10;
    }

    /* Side panel styles */
    .side-panel {
      height: 100vh;
      overflow: hidden;
      position: fixed;
      top: 0;
      width: 400px;
      max-width: 90%;
      background-color: white;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease-in-out;
      z-index: 100;
      padding-top: 60px;
    }

    .side-panel-content {
      height: calc(100% - 60px);
      overflow-y: auto;
      padding: 20px;
      position: relative;
    }

    /* Custom scrollbar */
    .side-panel-content::-webkit-scrollbar {
      width: 8px;
    }

    .side-panel-content::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .side-panel-content::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 10px;
    }

    .side-panel-content::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }

    /* Fade effect at bottom */
    .side-panel-content::after {
      content: '';
      position: sticky;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 40px;
      background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1));
      pointer-events: none;
      display: block;
    }

    /* Form styles */
    .form-transition {
      transition: all 0.3s ease-in-out;
    }

    .error-message {
      background-color: #FEF2F2;
      border: 1px solid #FECACA;
      color: #B91C1C;
      padding: 0.75rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
    }

    .error-message i {
      margin-right: 0.5rem;
    }

    .success-message {
      background-color: #ECFDF5;
      border: 1px solid #A7F3D0;
      color: #065F46;
      padding: 0.75rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
    }

    .success-message i {
      margin-right: 0.5rem;
    }

    /* Password reset modal */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      display: flex;
      justify-content: center;
      align-items: center;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .modal-content {
      background-color: white;
      padding: 2rem;
      border-radius: 0.5rem;
      width: 100%;
      max-width: 400px;
      transform: translateY(20px);
      transition: transform 0.3s ease;
    }

    .modal-overlay.active .modal-content {
      transform: translateY(0);
    }

    .modal-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      font-size: 1.5rem;
      cursor: pointer;
      color: #6b7280;
    }

    /* Loading spinner */
    .spinner {
      animation: spin 1s linear infinite;
      display: inline-block;
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
<div id="particles-js"></div>

<!-- Notification area -->
<div class="notification" id="notificationArea">
  <?php if (!empty($errors['login'])): ?>
    <div class="notification-item notification-error animate__animated animate__slideInRight">
      <div class="p-4 flex items-start">
        <i class="ri-error-warning-line text-xl mr-3"></i>
        <div>
          <h4 class="font-bold mb-1">Login Error</h4>
          <p><?php echo htmlspecialchars($errors['login']); ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($errors['register'])): ?>
    <div class="notification-item notification-error animate__animated animate__slideInRight">
      <div class="p-4 flex items-start">
        <i class="ri-error-warning-line text-xl mr-3"></i>
        <div>
          <h4 class="font-bold mb-1">Registration Error</h4>
          <p><?php echo htmlspecialchars($errors['register']); ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($errors['register_success'])): ?>
    <div class="notification-item notification-success animate__animated animate__slideInRight">
      <div class="p-4 flex items-start">
        <i class="ri-checkbox-circle-line text-xl mr-3"></i>
        <div>
          <h4 class="font-bold mb-1">Registration Successful</h4>
          <p><?php echo htmlspecialchars($errors['register_success']); ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($errors['account_status'])): ?>
    <div class="notification-item notification-<?php echo strpos($errors['account_status'], 'approved') !== false ? 'success' : 'warning'; ?> animate__animated animate__slideInRight">
      <div class="p-4 flex items-start">
        <i class="<?php echo strpos($errors['account_status'], 'approved') !== false ? 'ri-checkbox-circle-line' : 'ri-alert-line'; ?> text-xl mr-3"></i>
        <div>
          <h4 class="font-bold mb-1">Account Status</h4>
          <p><?php echo htmlspecialchars($errors['account_status']); ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors['password_reset'])): ?>
    <div class="notification-item notification-<?php echo strpos($errors['password_reset'], 'successfully') !== false ? 'success' : 'info'; ?> animate__animated animate__slideInRight">
      <div class="p-4 flex items-start">
        <i class="<?php echo strpos($errors['password_reset'], 'successfully') !== false ? 'ri-checkbox-circle-line' : 'ri-information-line'; ?> text-xl mr-3"></i>
        <div>
          <h4 class="font-bold mb-1">Password Reset</h4>
          <p><?php echo htmlspecialchars($errors['password_reset']); ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Click zones -->
<div class="click-zone left-zone" id="left-zone" title="System Info" role="button" aria-controls="left-panel" aria-expanded="false" tabindex="0"></div>
<div class="click-zone right-zone" id="right-zone" title="About Us" role="button" aria-controls="right-panel" aria-expanded="false" tabindex="0"></div>
<div class="main-zone" id="main-zone" title="Close Panel" role="button" tabindex="0"></div>

<!-- Left panel (About the System) -->
<aside class="side-panel left-panel" id="left-panel" aria-hidden="true" tabindex="-1">
  <div class="side-panel-content p-6">
    <h2 class="text-4xl font-extrabold mb-2 text-center text-blue-800">About the System</h2>
    <div class="w-20 h-1 mx-auto bg-gradient-to-r from-blue-400 to-sky-400 rounded mb-6"></div>
    
    <div class="prose prose-lg text-gray-700 max-w-none">
      <!-- Welcome Section -->
      <div class="text-center mb-8">
        <p class="text-xl font-semibold text-blue-700 mb-4">
          Welcome to the LSPU-LBC Training Tracker System – Your Gateway to Continuous Learning and Professional Growth
        </p>
        <div class="w-40 h-0.5 bg-gray-200 mx-auto mb-4"></div>
        <p class="text-gray-600">
          A comprehensive solution for managing professional development activities at Laguna State Polytechnic University
        </p>
      </div>

      <!-- System Overview -->
      <div class="bg-white p-6 rounded-xl shadow-sm mb-8 border border-gray-100">
        <p class="mb-4">
          The <strong class="text-blue-800">LSPU-LBC Training Tracker System</strong> is an innovative digital platform developed to enhance the documentation and management of training and professional development across the Los Baños campus. Whether you are a faculty member or administrative staff, this system ensures that every learning opportunity is efficiently recorded and accessible.
        </p>
        
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg mb-4">
          <p class="text-blue-700 italic">
            "Stay organized. Stay updated. Stay empowered with our centralized training management solution."
          </p>
        </div>
        
        <p>
          This system replaces traditional paper-based methods with a streamlined digital approach, enabling real-time tracking of training activities and compliance with university requirements.
        </p>
      </div>

      <!-- Key Features -->
      <div class="mb-8">
        <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">Key Features</h3>
        
        <div class="grid gap-5 sm:grid-cols-2">
          <!-- Feature 1 -->
          <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition">
            <div class="flex items-center mb-3">
              <div class="bg-blue-100 p-3 rounded-full mr-4">
                <i class="ri-calendar-todo-fill text-blue-600 text-xl"></i>
              </div>
              <h4 class="font-bold text-lg text-gray-800">Training Management</h4>
            </div>
            <p class="text-gray-600">
              Easily schedule, document, and track all professional development activities in one centralized platform.
            </p>
          </div>
          
          <!-- Feature 2 -->
          <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition">
            <div class="flex items-center mb-3">
              <div class="bg-green-100 p-3 rounded-full mr-4">
                <i class="ri-line-chart-fill text-green-600 text-xl"></i>
              </div>
              <h4 class="font-bold text-lg text-gray-800">Progress Tracking</h4>
            </div>
            <p class="text-gray-600">
              Visualize your training progress and compliance with university-mandated requirements.
            </p>
          </div>
          
          <!-- Feature 3 -->
          <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition">
            <div class="flex items-center mb-3">
              <div class="bg-purple-100 p-3 rounded-full mr-4">
                <i class="ri-file-chart-line text-purple-600 text-xl"></i>
              </div>
              <h4 class="font-bold text-lg text-gray-800">Automated Reporting</h4>
            </div>
            <p class="text-gray-600">
              Generate comprehensive reports for performance reviews, accreditation, and compliance purposes.
            </p>
          </div>
          
          <!-- Feature 4 -->
          <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition">
            <div class="flex items-center mb-3">
              <div class="bg-orange-100 p-3 rounded-full mr-4">
                <i class="ri-team-fill text-orange-600 text-xl"></i>
              </div>
              <h4 class="font-bold text-lg text-gray-800">Collaboration Tools</h4>
            </div>
            <p class="text-gray-600">
              Coordinate training initiatives across departments and share resources with colleagues.
            </p>
          </div>
          
          <!-- Feature 5 (New) -->
          <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition">
            <div class="flex items-center mb-3">
              <div class="bg-red-100 p-3 rounded-full mr-4">
                <i class="ri-upload-cloud-fill text-red-600 text-xl"></i>
              </div>
              <h4 class="font-bold text-lg text-gray-800">Document Storage</h4>
            </div>
            <p class="text-gray-600">
              Securely store and access training certificates, materials, and supporting documents.
            </p>
          </div>
          
          <!-- Feature 6 (New) -->
          <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition">
            <div class="flex items-center mb-3">
              <div class="bg-indigo-100 p-3 rounded-full mr-4">
                <i class="ri-notification-fill text-indigo-600 text-xl"></i>
              </div>
              <h4 class="font-bold text-lg text-gray-800">Reminders & Alerts</h4>
            </div>
            <p class="text-gray-600">
              Receive timely notifications for upcoming trainings and compliance deadlines.
            </p>
          </div>
        </div>
      </div>

      <!-- Benefits Section (New) -->
      <div class="bg-blue-50 p-6 rounded-xl mb-8 border border-blue-200">
        <h3 class="text-2xl font-bold text-blue-800 mb-4 text-center">System Benefits</h3>
        <ul class="space-y-3 list-disc list-inside pl-4">
          <li>Streamlines the training documentation process for all university personnel</li>
          <li>Provides real-time visibility into training compliance across departments</li>
          <li>Reduces administrative burden through automated reporting</li>
          <li>Ensures accurate record-keeping for accreditation purposes</li>
          <li>Facilitates professional growth through easy access to training history</li>
        </ul>
      </div>

      <!-- Getting Started -->
      <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
        <h3 class="text-2xl font-bold text-gray-800 mb-4 text-center">Getting Started</h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <h4 class="font-semibold text-gray-800 mb-2">For First-Time Users</h4>
            <ol class="space-y-2 list-decimal list-inside pl-4">
              <li>Register using your official LSPU email address</li>
              <li>Complete your profile information</li>
              <li>Wait for account verification (within 24 hours)</li>
              <li>Log in to access your personal dashboard</li>
            </ol>
          </div>
          <div>
            <h4 class="font-semibold text-gray-800 mb-2">After Login</h4>
            <ol class="space-y-2 list-decimal list-inside pl-4">
              <li>Document past training activities</li>
              <li>Register for upcoming training sessions</li>
              <li>Upload supporting documents</li>
              <li>Generate training reports as needed</li>
            </ol>
          </div>
        </div>
        <div class="mt-6 text-center">
          <p class="text-sm text-gray-500">
            Need assistance? Contact the system administrator at 
            <span class="text-blue-600">training.tracker@lspu.edu.ph</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</aside>

<!-- Right panel (About Us) -->
<aside class="side-panel right-panel" id="right-panel" aria-hidden="true" tabindex="-1">
  <div class="side-panel-content p-6">
    <!-- Header Section -->
    <div class="text-center mb-8">
      <h2 class="text-4xl font-extrabold text-blue-800 mb-2">About Us</h2>
      <div class="w-20 h-1 mx-auto bg-gradient-to-r from-blue-400 to-sky-400 rounded"></div>
      <p class="mt-4 text-gray-600 font-medium">The team behind LSPU-LBC Training Tracker System</p>
    </div>

    <!-- Introduction -->
    <div class="bg-white p-6 rounded-xl shadow-sm mb-8 border border-gray-100">
      <p class="text-gray-700 leading-relaxed">
        The <span class="font-semibold text-blue-700">LSPU-LBC Training Tracker System</span> was developed by a dedicated team of students from the
        <span class="italic text-gray-800">Bachelor of Science in Information Technology (BSIT)</span> program at the
        <span class="font-medium text-gray-700">College of Computer Studies, Laguna State Polytechnic University – Los Baños Campus</span>.
      </p>
    </div>

<!-- Development Team Section -->
<div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-xl mb-8 border border-blue-100">
  <h3 class="text-2xl font-bold text-blue-800 mb-6 text-center">Development Team</h3>
  
  <div class="bg-white p-6 rounded-lg border border-blue-200 shadow-sm max-w-lg mx-auto">
    <div class="flex flex-col items-center text-center">
      <div class="bg-blue-100 w-20 h-20 rounded-full flex items-center justify-center mb-4">
        <i class="ri-code-box-fill text-blue-600 text-3xl"></i>
      </div>
      <h4 class="font-bold text-xl text-gray-800 mb-1">Gian Maranan</h4>
      <p class="text-blue-600 font-medium mb-4">Lead Developer & System Architect</p>
      <p class="text-gray-600 mb-4">
        Sole developer responsible for designing and implementing the entire LSPU-LBC Training Tracker System,
        from initial concept to final deployment.
      </p>
    </div>
  </div>

  <div class="mt-6 bg-white p-5 rounded-lg border border-purple-200 shadow-sm max-w-lg mx-auto">
    <div class="flex items-center justify-center mb-4">
      <div class="bg-purple-100 p-3 rounded-full mr-4">
        <i class="ri-group-fill text-purple-600 text-xl"></i>
      </div>
      <h4 class="font-bold text-lg text-gray-800">Development Team</h4>
    </div>
    <p class="text-gray-600 text-center mb-4">
      Special thanks to the following for their valuable contributions to the project:
    </p>
    <div class="grid grid-cols-2 gap-3 text-gray-700">
      <div class="flex items-center justify-center">
        <i class="ri-user-3-line text-purple-500 mr-2"></i>
        Laraine Rodriguez
      </div>
      <div class="flex items-center justify-center">
        <i class="ri-user-3-line text-purple-500 mr-2"></i>
        Noemelyn Abasola
      </div>
      <div class="flex items-center justify-center">
        <i class="ri-user-3-line text-purple-500 mr-2"></i>
        Irene Caguya
      </div>
      <div class="flex items-center justify-center">
        <i class="ri-user-3-line text-purple-500 mr-2"></i>
        Ethan Daniel Monterola
      </div>
    </div>
  </div>
</div>

<!-- Project Advisors -->
<div class="bg-gray-50 p-6 rounded-xl mb-8 border border-gray-200">
  <h3 class="text-2xl font-bold text-gray-800 mb-4 text-center">Project Advisors</h3>
  
  <p class="text-gray-700 mb-6 text-center">
    The project was developed under the guidance of our esteemed advisors from the College of Computer Studies,
    whose expertise and mentorship were invaluable throughout the development process.
  </p>
  
  <div class="grid gap-6 sm:grid-cols-3">
    <!-- Advisor 1 -->
    <div class="bg-white p-5 rounded-lg border border-gray-200 text-center shadow-sm hover:shadow-md transition">
      <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
        <i class="ri-user-3-fill text-blue-600 text-2xl"></i>
      </div>
      <h4 class="font-bold text-gray-800 mb-1">Sir Alejandro V. Matute, Jr.</h4>
      <p class="text-blue-600 text-sm">Project Coordinator</p>
      <p class="text-gray-600 text-sm mt-2">
        Provided overall project supervision and academic direction.
      </p>
    </div>
    
    <!-- Advisor 2 -->
    <div class="bg-white p-5 rounded-lg border border-gray-200 text-center shadow-sm hover:shadow-md transition">
      <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
        <i class="ri-user-3-fill text-green-600 text-2xl"></i>
      </div>
      <h4 class="font-bold text-gray-800 mb-1">Sir Crisanto F. Gulay</h4>
      <p class="text-green-600 text-sm">Technical Advisor</p>
      <p class="text-gray-600 text-sm mt-2">
        Guided the technical implementation and system architecture.
      </p>
    </div>
    
    <!-- Advisor 3 -->
    <div class="bg-white p-5 rounded-lg border border-gray-200 text-center shadow-sm hover:shadow-md transition">
      <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
        <i class="ri-user-3-fill text-purple-600 text-2xl"></i>
      </div>
      <h4 class="font-bold text-gray-800 mb-1">Sir Merardo A. Camba, Jr.</h4>
      <p class="text-purple-600 text-sm">System Consultant</p>
      <p class="text-gray-600 text-sm mt-2">
        Offered valuable insights on system design and implementation.
      </p>
    </div>
  </div>

  <div class="mt-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
    <p class="text-blue-700 text-center">
      We extend our deepest gratitude to our advisors for their continuous support, valuable feedback,
      and for sharing their wealth of knowledge throughout this project's development.
    </p>
  </div>
</div>

    <!-- Project Development -->
    <div class="bg-white p-6 rounded-xl shadow-sm mb-8 border border-gray-200">
      <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">Project Development</h3>
      
      <div class="space-y-6">
        <div class="flex items-start">
          <div class="bg-blue-100 p-2 rounded-full mr-4 flex-shrink-0">
            <i class="ri-search-line text-blue-600"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-800 mb-1">Research and Planning</h4>
            <p class="text-gray-600">Conducted thorough research to identify system requirements and user needs.</p>
          </div>
        </div>
        
        <div class="flex items-start">
          <div class="bg-green-100 p-2 rounded-full mr-4 flex-shrink-0">
            <i class="ri-code-line text-green-600"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-800 mb-1">System Development</h4>
            <p class="text-gray-600">Implemented the core features and functionality of the training tracker system.</p>
          </div>
        </div>
        
        <div class="flex items-start">
          <div class="bg-purple-100 p-2 rounded-full mr-4 flex-shrink-0">
            <i class="ri-bug-line text-purple-600"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-800 mb-1">Testing and Refinement</h4>
            <p class="text-gray-600">Performed rigorous testing to ensure system reliability and user satisfaction.</p>
          </div>
        </div>
        
        <div class="flex items-start">
          <div class="bg-orange-100 p-2 rounded-full mr-4 flex-shrink-0">
            <i class="ri-checkbox-circle-line text-orange-600"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-800 mb-1">Final Implementation</h4>
            <p class="text-gray-600">Deployed the completed system for university-wide use.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Mission Statement -->
    <div class="bg-blue-800 text-white p-6 rounded-xl text-center">
      <i class="ri-double-quotes-l text-3xl text-blue-300 opacity-50"></i>
      <p class="text-lg font-medium mt-2 mb-4">
        This project reflects our commitment to serving the university community through meaningful, technology-driven solutions.
      </p>
      <div class="w-20 h-0.5 bg-blue-400 mx-auto"></div>
    </div>
  </div>
</aside>

<!-- Password Reset Modal -->
<div class="modal-overlay" id="passwordResetModal">
  <div class="modal-content relative">
    <span class="modal-close" id="closePasswordResetModal">&times;</span>
    <h2 class="text-2xl font-bold mb-4 text-center text-blue-800">Reset Password</h2>
    <form id="passwordResetForm" action="password_reset.php" method="POST" class="space-y-4">
      <div>
        <label for="resetEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
        <input type="email" id="resetEmail" name="email" required placeholder="Enter your registered email" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
      </div>
      
      <div id="resetTokenField" class="hidden">
        <label for="resetToken" class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
        <input type="text" id="resetToken" name="token" placeholder="Enter 6-digit code" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
      </div>
      
      <div id="newPasswordField" class="hidden">
        <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
        <input type="password" id="newPassword" name="new_password" placeholder="Create new password" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
        
        <label for="confirmNewPassword" class="block text-sm font-medium text-gray-700 mb-1 mt-2">Confirm New Password</label>
        <input type="password" id="confirmNewPassword" name="confirm_new_password" placeholder="Confirm new password" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
      </div>
      
      <div id="resetMessage" class="hidden text-sm p-3 rounded"></div>
      
      <button type="submit" id="resetSubmitBtn" class="btn btn-primary bg-primary hover:bg-primary-dark text-white w-full py-3 rounded-button font-medium">
        <span id="resetSubmitText">Send Reset Link</span>
        <span id="resetSpinner" class="hidden ml-2">
          <i class="ri-loader-4-line spinner"></i>
        </span>
      </button>
    </form>
  </div>
</div>

<!-- Main login container -->
<div class="min-h-screen flex flex-col justify-between px-4">
  <!-- Main Content -->
  <div class="flex-grow flex items-center justify-center">
    <div class="login-container rounded-xl p-8 w-full max-w-md bg-white bg-opacity-90 shadow-xl">

      <!-- Title section -->
      <div id="mainHeader" class="text-center mb-8">
        <h1 id="mainTitle" class="tracker-title text-3xl font-bold text-gray-800">
          LSPU-LBC Training Tracker
        </h1>
        <p id="mainSubtitle" class="text-gray-600 mt-2">
          Your Gateway to Continuous Learning and Professional Growth
        </p>
      </div>

      <!-- Buttons and forms container -->
      <div class="space-y-6">

        <!-- Initial Buttons -->
        <div id="mainButtons" class="space-y-4 animate__animated animate__fadeIn">
          <button id="loginBtn" type="button" class="btn btn-primary bg-primary hover:bg-primary-dark text-white w-full py-3 rounded-button font-medium whitespace-nowrap flex items-center justify-center transition-colors duration-300" onclick="showOnly(document.getElementById('loginForm'))">
            <span class="w-6 h-6 flex items-center justify-center mr-2">
              <i class="ri-login-circle-line ri-lg"></i>
            </span>
            Login to Your Account
          </button>

          <button id="registerBtn" type="button" class="btn btn-secondary bg-secondary hover:bg-secondary-dark text-white w-full py-3 rounded-button font-medium whitespace-nowrap flex items-center justify-center transition-colors duration-300" onclick="showOnly(document.getElementById('registerForm'))">
            <span class="w-6 h-6 flex items-center justify-center mr-2">
              <i class="ri-user-add-line ri-lg"></i>
            </span>
            Create New Account
          </button>
        </div>

        <!-- Login Form -->
        <div id="loginForm" class="relative hidden form-transition">
          <button type="button" class="absolute top-0 right-0 text-xl text-gray-600 hover:text-red-500 transition-colors" onclick="closeForm()">&times;</button>
          <h2 class="text-center text-3xl font-bold mb-6 mt-2 text-gray-800">Welcome Back</h2>
          
          <?php if (!empty($errors['account_status']) && strpos($errors['account_status'], 'approved') !== false): ?>
            <div class="success-message mb-4">
              <i class="ri-checkbox-circle-line"></i>
              <span><?php echo htmlspecialchars($errors['account_status']); ?></span>
            </div>
          <?php endif; ?>
          
          <form action="login_register.php" method="POST" class="space-y-4 mt-2">
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" id="email" name="email" required placeholder="Enter your email" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300" />
            </div>
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input type="password" id="password" name="password" required placeholder="Enter your password" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300" />
              <div class="flex justify-end mt-1">
                <button type="button" onclick="openPasswordResetModal()" class="text-sm text-primary hover:underline focus:outline-none">Forgot password?</button>
              </div>
            </div>
            
            <?php if (!empty($errors['login'])): ?>
              <div class="error-message">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($errors['login']); ?></span>
              </div>
            <?php endif; ?>
            
            <button type="submit" name="login" class="btn btn-primary bg-primary hover:bg-primary-dark text-white w-full py-3 rounded-button font-medium transition-colors duration-300">
              Sign In
            </button>
          </form>
          <p class="mt-4 text-center text-sm text-gray-600">
            Don't have an account? 
            <button type="button" class="underline text-primary hover:text-primary-dark font-semibold transition-colors" onclick="showOnly(document.getElementById('registerForm'))">Register here</button>
          </p>
        </div>

        <!-- Register Form -->
        <div id="registerForm" class="relative hidden form-transition">
          <button type="button" class="absolute top-0 right-0 text-xl text-gray-600 hover:text-red-500 transition-colors" onclick="closeForm()">&times;</button>
          <h2 class="text-center text-3xl font-bold mb-6 mt-2 text-gray-800">Create Account</h2>
          
          <?php if (!empty($errors['register_success'])): ?>
            <div class="success-message mb-4">
              <i class="ri-checkbox-circle-line"></i>
              <span><?php echo htmlspecialchars($errors['register_success']); ?></span>
            </div>
          <?php endif; ?>
          
          <form action="login_register.php" method="POST" class="space-y-4 mt-2">
            <input type="hidden" name="source" value="homepage" />

            <div>
              <label for="regName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
              <input type="text" id="regName" name="name" required placeholder="Enter your full name" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition-all duration-300" />
            </div>
            <div>
              <label for="regEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" id="regEmail" name="email" required placeholder="Enter your email" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition-all duration-300" />
              <p class="text-xs text-gray-500 mt-1">Use your LSPU email address</p>
            </div>
            <div>
              <label for="regPassword" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input type="password" id="regPassword" name="password" required placeholder="Create a password" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition-all duration-300" />
              <div class="text-xs text-gray-500 mt-1">
                <p>Password must contain:</p>
                <ul class="list-disc list-inside ml-3">
                  <li>At least 8 characters</li>
                  <li>One uppercase letter</li>
                  <li>One number</li>
                </ul>
              </div>
            </div>
            <div>
              <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
              <input type="password" id="confirmPassword" name="confirm_password" required placeholder="Confirm your password" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition-all duration-300" />
            </div>

            <?php if (!empty($errors['register'])): ?>
              <div class="error-message">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($errors['register']); ?></span>
              </div>
            <?php endif; ?>

            <button type="submit" name="register" class="btn btn-secondary bg-secondary hover:bg-secondary-dark text-white w-full py-3 rounded-button font-medium transition-colors duration-300">
              Create Account
            </button>
          </form>

          <p class="mt-4 text-center text-sm text-gray-600">
            Already have an account?
            <button type="button" class="underline text-secondary hover:text-secondary-dark font-semibold transition-colors" onclick="showOnly(document.getElementById('loginForm'))">Login here</button>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="mt-12 text-center text-sm text-gray-500">
  <div class="border-t border-gray-200 pt-4 px-4">
    <p>
      © 2025 Laguna State Polytechnic University Los Baños Campus. All rights reserved. <br>
      Created by <strong>Gian Maranan</strong>. Learn more
      <span onclick="openPanel('left')" role="button" tabindex="0" class="cursor-pointer text-indigo-600 hover:underline focus:outline-none transition-colors">
        about the system
      </span>
      or
      <span onclick="openPanel('right')" role="button" tabindex="0" class="cursor-pointer text-indigo-600 hover:underline focus:outline-none transition-colors">
        about us
      </span>.
    </p>
  </div>
</footer>
</div>

<!-- Load Particles.js library -->
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>

<!-- Your custom JavaScript file -->
<script src="main.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const mainButtons = document.getElementById('mainButtons');
    const mainTitle = document.getElementById('mainTitle');
    const mainSubtitle = document.getElementById('mainSubtitle');
    const notificationArea = document.getElementById('notificationArea');
    const passwordResetModal = document.getElementById('passwordResetModal');
    const closePasswordResetModal = document.getElementById('closePasswordResetModal');
    const passwordResetForm = document.getElementById('passwordResetForm');
    const resetEmail = document.getElementById('resetEmail');
    const resetTokenField = document.getElementById('resetTokenField');
    const newPasswordField = document.getElementById('newPasswordField');
    const resetMessage = document.getElementById('resetMessage');
    const resetSubmitBtn = document.getElementById('resetSubmitBtn');
    const resetSubmitText = document.getElementById('resetSubmitText');
    const resetSpinner = document.getElementById('resetSpinner');

    // Password reset modal functionality
    window.openPasswordResetModal = function() {
      passwordResetModal.classList.add('active');
      document.body.style.overflow = 'hidden';
    };

    closePasswordResetModal.addEventListener('click', () => {
      passwordResetModal.classList.remove('active');
      document.body.style.overflow = '';
    });

    // Close modal when clicking outside
    passwordResetModal.addEventListener('click', (e) => {
      if (e.target === passwordResetModal) {
        passwordResetModal.classList.remove('active');
        document.body.style.overflow = '';
      }
    });

    // Password reset form handling
    passwordResetForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      resetSubmitBtn.disabled = true;
      resetSubmitText.textContent = 'Processing...';
      resetSpinner.classList.remove('hidden');
      
      const formData = new FormData(passwordResetForm);
      const email = formData.get('email');
      const token = formData.get('token');
      const newPassword = formData.get('new_password');
      const confirmNewPassword = formData.get('confirm_new_password');
      
      try {
        let response;
        
        if (!resetTokenField.classList.contains('hidden') && !newPasswordField.classList.contains('hidden')) {
          // Final step - reset password
          if (newPassword !== confirmNewPassword) {
            throw new Error('Passwords do not match');
          }
          
          response = await fetch('password_reset.php', {
            method: 'POST',
            body: JSON.stringify({
              action: 'reset_password',
              email,
              token,
              new_password: newPassword
            }),
            headers: {
              'Content-Type': 'application/json'
            }
          });
        } else if (!resetTokenField.classList.contains('hidden')) {
          // Verify token
          response = await fetch('password_reset.php', {
            method: 'POST',
            body: JSON.stringify({
              action: 'verify_token',
              email,
              token
            }),
            headers: {
              'Content-Type': 'application/json'
            }
          });
        } else {
          // Initial request - send email
          response = await fetch('password_reset.php', {
            method: 'POST',
            body: JSON.stringify({
              action: 'request_reset',
              email
            }),
            headers: {
              'Content-Type': 'application/json'
            }
          });
        }
        
        const result = await response.json();
        
        if (result.success) {
          if (result.step === 'verify_token') {
            // Show new password fields
            resetTokenField.classList.add('hidden');
            newPasswordField.classList.remove('hidden');
            resetSubmitText.textContent = 'Reset Password';
            showResetMessage('Token verified. Please enter your new password.', 'success');
          } else if (result.step === 'password_reset') {
            // Password reset successful
            showResetMessage('Password reset successfully! You can now login with your new password.', 'success');
            setTimeout(() => {
              passwordResetModal.classList.remove('active');
              document.body.style.overflow = '';
              resetForm();
            }, 2000);
          } else {
            // Email sent successfully
            resetTokenField.classList.remove('hidden');
            resetSubmitText.textContent = 'Verify Code';
            showResetMessage(`A verification code has been sent to ${email}. Please check your email.`, 'success');
          }
        } else {
          throw new Error(result.message || 'An error occurred');
        }
      } catch (error) {
        showResetMessage(error.message, 'error');
      } finally {
        resetSubmitBtn.disabled = false;
        resetSubmitText.textContent = resetTokenField.classList.contains('hidden') ? 
          (newPasswordField.classList.contains('hidden') ? 'Send Reset Link' : 'Reset Password') : 'Verify Code';
        resetSpinner.classList.add('hidden');
      }
    });

    function showResetMessage(message, type) {
      resetMessage.textContent = message;
      resetMessage.className = 'text-sm p-3 rounded';
      resetMessage.classList.add(type === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700');
      resetMessage.classList.remove('hidden');
      
      // Scroll to message
      resetMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function resetForm() {
      passwordResetForm.reset();
      resetTokenField.classList.add('hidden');
      newPasswordField.classList.add('hidden');
      resetMessage.classList.add('hidden');
      resetSubmitText.textContent = 'Send Reset Link';
    }

    // Auto-close notifications after 5 seconds
    const notifications = notificationArea.querySelectorAll('.notification-item');
    notifications.forEach(notification => {
      setTimeout(() => {
        notification.classList.add('animate__animated', 'animate__fadeOutRight');
        setTimeout(() => notification.remove(), 300);
      }, 5000);
    });

// Show active form based on PHP session
<?php if ($activeForm === 'login' && (!empty($errors['login']) || !empty($errors['account_status']))) { ?>
  document.addEventListener('DOMContentLoaded', function() {
    showOnlyForm('loginForm');
  });
<?php } elseif ($activeForm === 'register' && (!empty($errors['register']) || !empty($errors['register_success']))) { ?>
  document.addEventListener('DOMContentLoaded', function() {
    showOnlyForm('registerForm');
  });
<?php } ?>

    // When main Login button is clicked
    loginBtn?.addEventListener('click', () => {
      showOnlyForm('loginForm');
    });

    // When main Register button is clicked
    registerBtn?.addEventListener('click', () => {
      showOnlyForm('registerForm');
    });

    // When clicking internal "Register" inside login form
    document.querySelectorAll('[onclick*="registerForm"]').forEach(btn => {
      btn.addEventListener('click', () => {
        showOnlyForm('registerForm');
      });
    });

    // When clicking internal "Login" inside register form
    document.querySelectorAll('[onclick*="loginForm"]').forEach(btn => {
      btn.addEventListener('click', () => {
        showOnlyForm('loginForm');
      });
    });

    // Global function to switch to a specific form
    window.showOnly = function (formToShow) {
      loginForm?.classList.add('hidden');
      registerForm?.classList.add('hidden');
      formToShow?.classList.remove('hidden');
      formToShow?.classList.add('animate__animated', 'animate__fadeIn');

      mainButtons?.classList.add('hidden');
      mainTitle?.classList.add('hidden');
      mainSubtitle?.classList.add('hidden');
    };

    // Close function when X is clicked
    window.closeForm = function () {
      loginForm?.classList.add('hidden');
      registerForm?.classList.add('hidden');

      mainButtons?.classList.remove('hidden');
      mainTitle?.classList.remove('hidden');
      mainSubtitle?.classList.remove('hidden');
      mainButtons?.classList.add('animate__animated', 'animate__fadeIn');
    };

    // Enhanced show form function
    function showOnlyForm(formId) {
      const formToShow = document.getElementById(formId);
      const otherForm = formId === 'loginForm' ? registerForm : loginForm;
      
      otherForm?.classList.add('hidden');
      otherForm?.classList.remove('animate__animated', 'animate__fadeIn');
      
      formToShow?.classList.remove('hidden');
      formToShow?.classList.add('animate__animated', 'animate__fadeIn');
      
      mainButtons?.classList.add('hidden');
      mainTitle?.classList.add('hidden');
      mainSubtitle?.classList.add('hidden');
    }
  });
</script>

</body>
</html>