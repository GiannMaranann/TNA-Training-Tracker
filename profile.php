<?php
session_start();

// Database connection
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'user_db';

$con = new mysqli($host, $db_user, $db_pass, $db_name);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$from_modal = isset($_GET['source']) && $_GET['source'] === 'modal';

// Set upload directory path
$upload_dir = 'uploads/profile_images/';

// Create uploads directory if not exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle profile update form submission
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle both regular and modal form submissions
    $is_modal_submission = isset($_POST['action']) && $_POST['action'] === 'save_profile_modal';
    
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $educ = trim($_POST['educationalAttainment'] ?? '');
    $spec = trim($_POST['specialization'] ?? '');
    $desig = trim($_POST['designation'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $years = trim($_POST['yearsInLSPU'] ?? '');
    $teach = trim($_POST['teaching_status'] ?? '');
    
    // Get current profile image from database
    $current_image = '';
    $stmt = $con->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_image);
    $stmt->fetch();
    $stmt->close();
    
    // Handle image upload
    $profile_image = $current_image; // Keep current image by default
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['profile_image']['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $update_error = "Only JPG, PNG, or GIF images are allowed.";
        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $update_error = "Image size must be less than 2MB.";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Delete old image if exists and is not default
                if (!empty($current_image) && $current_image != 'noprofile.jpg' && file_exists($upload_dir . $current_image)) {
                    unlink($upload_dir . $current_image);
                }
                $profile_image = $new_filename;
            } else {
                $update_error = "Failed to upload image.";
            }
        }
    }
    
    $full_name = trim($first_name . ' ' . $last_name);

    // Basic validation for modal submission
    if ($is_modal_submission) {
        if (empty($first_name) || empty($last_name) || empty($educ) || empty($spec)) {
            $update_error = "Name, education, and specialization are required fields.";
        }
    }

    if (empty($update_error)) {
        // Save set year for future increment tracking
        $_SESSION['profile_yearInLSPU_set'] = date('Y');

        // Update user profile with image filename
        $stmt = $con->prepare("UPDATE users SET name=?, educationalAttainment=?, specialization=?, designation=?, department=?, yearsInLSPU=?, teaching_status=?, profile_image=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $full_name, $educ, $spec, $desig, $dept, $years, $teach, $profile_image, $user_id);

        if ($stmt->execute()) {
            $update_success = true;
            
            // Update ALL session data including image
            $_SESSION['profile_name'] = $full_name;
            $_SESSION['profile_first_name'] = $first_name;
            $_SESSION['profile_last_name'] = $last_name;
            $_SESSION['profile_educationalAttainment'] = $educ;
            $_SESSION['profile_specialization'] = $spec;
            $_SESSION['profile_designation'] = $desig;
            $_SESSION['profile_department'] = $dept;
            $_SESSION['profile_yearsInLSPU'] = $years;
            $_SESSION['profile_teaching_status'] = $teach;
            $_SESSION['profile_image'] = $profile_image; // Update image in session

            // Different response for modal submission
            if ($is_modal_submission) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'image_url' => !empty($profile_image) ? $upload_dir . $profile_image : 'images/noprofile.jpg'
                ]);
                exit;
            }
        } else {
            $update_error = "Error updating profile: " . $stmt->error;
            if ($is_modal_submission) {
                echo json_encode([
                    'success' => false,
                    'message' => $update_error
                ]);
                exit;
            }
        }
        $stmt->close();
    } elseif ($is_modal_submission) {
        echo json_encode([
            'success' => false,
            'message' => $update_error
        ]);
        exit;
    }
}

// Always load fresh data from database to ensure image is current
$stmt = $con->prepare("SELECT name, educationalAttainment, specialization, designation, department, yearsInLSPU, teaching_status, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $educ, $spec, $desig, $dept, $years, $teach, $profile_image);
if ($stmt->fetch()) {
    // Store in session
    $_SESSION['profile_name'] = $name;
    $_SESSION['profile_educationalAttainment'] = $educ;
    $_SESSION['profile_specialization'] = $spec;
    $_SESSION['profile_designation'] = $desig;
    $_SESSION['profile_department'] = $dept;
    $_SESSION['profile_yearsInLSPU'] = $years;
    $_SESSION['profile_teaching_status'] = $teach;
    $_SESSION['profile_image'] = $profile_image;

    // Split name
    $name_parts = explode(' ', $name, 2);
    $_SESSION['profile_first_name'] = $name_parts[0];
    $_SESSION['profile_last_name'] = $name_parts[1] ?? '';

    // Set initial year tracker
    if (!isset($_SESSION['profile_yearInLSPU_set']) && is_numeric($years)) {
        $_SESSION['profile_yearInLSPU_set'] = date('Y');
    }
} else {
    // Default values if no record found
    $_SESSION['profile_name'] = '';
    $_SESSION['profile_first_name'] = '';
    $_SESSION['profile_last_name'] = '';
    $_SESSION['profile_educationalAttainment'] = '';
    $_SESSION['profile_specialization'] = '';
    $_SESSION['profile_designation'] = '';
    $_SESSION['profile_department'] = '';
    $_SESSION['profile_yearsInLSPU'] = '';
    $_SESSION['profile_teaching_status'] = '';
    $_SESSION['profile_image'] = '';
}
$stmt->close();

// Assign session variables for display
$first_name = $_SESSION['profile_first_name'] ?? '';
$last_name = $_SESSION['profile_last_name'] ?? '';
$educ = $_SESSION['profile_educationalAttainment'] ?? '';
$spec = $_SESSION['profile_specialization'] ?? '';
$desig = $_SESSION['profile_designation'] ?? '';
$dept = $_SESSION['profile_department'] ?? '';
$years = $_SESSION['profile_yearsInLSPU'] ?? '';
$teach = $_SESSION['profile_teaching_status'] ?? '';
$profile_image = $_SESSION['profile_image'] ?? '';

// Compute auto-increased Years in LSPU
$current_year = date('Y');
$year_set = $_SESSION['profile_yearInLSPU_set'] ?? $current_year;
$computed_years = is_numeric($years) ? ((int)$years + ($current_year - (int)$year_set)) : '';

// Redirect back to user page if coming from modal with success
if ($from_modal && $update_success) {
    header("Location: user_page.php?profile_updated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profile | Training Needs Assessment</title>
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
<!-- Sidebar with Navigation -->
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
      <a href="user_page.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg hover:bg-blue-700/50 transition-all">
        <div class="w-6 h-6 flex items-center justify-center mr-3">
          <i class="ri-dashboard-line text-lg"></i>
        </div>
        Dashboard
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
      
      <a href="profile.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg bg-blue-700/50 hover:bg-blue-700/70 transition-all active">
        <div class="w-6 h-6 flex items-center justify-center mr-3">
          <i class="ri-user-line text-lg"></i>
        </div>
        Profile
        <i class="ri-arrow-right-s-line ml-auto"></i>
      </a>
    </nav>

    <!-- User Info & Logout -->
    <div class="p-4 border-t border-blue-800/30">
      <!-- User Info -->
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <?php
            // Get profile image path
            $defaultImage = 'images/noprofile.jpg';
            $imageSrc = $defaultImage;
            
            if (!empty($profile_image)) {
                $full_path = $upload_dir . $profile_image;
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
              <?= htmlspecialchars($first_name . ' ' . $last_name) ?>
            </p>
            <p class="text-xs text-blue-300">
              <?= htmlspecialchars($desig ?: 'Staff') ?>
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
  <div class="p-8">
    <!-- Welcome Section -->
    <div class="mb-8">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
          <h1 class="text-4xl font-bold text-gray-800 mb-2">
            My Profile
          </h1>
          <p class="text-gray-600 flex items-center">
            <i class="ri-user-line mr-2"></i>
            Manage your personal information and preferences
          </p>
        </div>
        
        <div class="flex items-center gap-6">
          <?php if ($update_success): ?>
            <div class="px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-medium flex items-center">
              <i class="ri-checkbox-circle-line mr-2"></i>
              Profile Updated
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Profile Form Card -->
    <div class="card-gradient rounded-2xl shadow-custom p-8 hover:shadow-custom-hover transition-all duration-300">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
            <i class="ri-user-settings-line text-blue-500 mr-3 text-2xl"></i>
            Personal Information
          </h2>
          <p class="text-gray-600">Update your profile details</p>
        </div>
        
        <button
          id="editButton"
          type="button"
          class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-medium hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-xl flex items-center gap-2"
          onclick="enableEditing()"
        >
          <i class="ri-edit-line"></i> Edit Profile
        </button>
      </div>

      <form id="profileForm" class="space-y-6" method="POST" action="" enctype="multipart/form-data" onsubmit="return validateForm()">
        <!-- Profile Image and Form Fields in Two Columns -->
        <div class="flex flex-col lg:flex-row gap-8">
          <!-- Left Column - Profile Picture -->
          <div class="w-full lg:w-1/3">
            <div id="profileImageContainer" class="relative group">
              <div class="relative overflow-hidden rounded-2xl border-2 border-gray-200 w-full aspect-square">
                <?php
                  $mainImageSrc = 'images/noprofile.jpg';
                  if (!empty($profile_image)) {
                      $full_path = $upload_dir . $profile_image;
                      if (file_exists($full_path)) {
                          $mainImageSrc = $full_path;
                      }
                  }
                ?>
                <img 
                  id="profileImage" 
                  src="<?= htmlspecialchars($mainImageSrc) ?>" 
                  alt="Profile Picture" 
                  class="w-full h-full object-cover"
                  onerror="this.onerror=null;this.src='images/noprofile.jpg';"
                />
                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center">
                  <span class="text-white font-medium">Change Photo</span>
                </div>
              </div>
              <input id="imageInput" type="file" name="profile_image" accept="image/*" class="hidden" onchange="handleImageChange(event)" />
              <button 
                id="uploadButton" 
                type="button" 
                class="mt-4 w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 flex items-center justify-center gap-2 hidden"
                onclick="document.getElementById('imageInput').click()"
              >
                <i class="ri-upload-line"></i> Upload New Photo
              </button>
              <p class="text-xs text-gray-500 mt-2 text-center">JPG, GIF or PNG. Max size 2MB</p>
            </div>
          </div>

          <!-- Right Column - Form Fields -->
          <div class="flex-1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                <input 
                  id="first_name" 
                  name="first_name" 
                  type="text" 
                  placeholder="Enter first name" 
                  value="<?= htmlspecialchars($first_name) ?>" 
                  readonly 
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                  required
                />
              </div>
              
              <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                <input 
                  id="last_name" 
                  name="last_name" 
                  type="text" 
                  placeholder="Enter last name" 
                  value="<?= htmlspecialchars($last_name) ?>" 
                  readonly 
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                  required
                />
              </div>
              
              <div>
                <label for="educationalAttainment" class="block text-sm font-medium text-gray-700 mb-2">Educational Attainment</label>
                <select 
                  id="educationalAttainment" 
                  name="educationalAttainment" 
                  disabled
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all"
                  required
                >
                  <option value="">Select Educational Attainment</option>
                  <?php
                    $educOptions = [
                      "Doctorate Degree (Completed)",
                      "Doctorate Degree – With Complete Academic Requirements (CAR)",
                      "Doctorate Degree – With Units Earned",
                      "Master's Degree (Completed)",
                      "Master's Degree – With Complete Academic Requirements (CAR)",
                      "Master's Degree – With Units Earned",
                      "Bachelor's Degree",
                      "Bachelor's Degree – With Units Earned",
                      "Associate Degree",
                      "Senior High School Graduate",
                      "High School Graduate",
                      "Vocational/Technical Graduate",
                      "Currently Enrolled in Graduate Studies"
                    ];
                    foreach ($educOptions as $option) {
                      $selected = ($educ ?? '') === $option ? 'selected' : '';
                      echo "<option value=\"" . htmlspecialchars($option) . "\" $selected>" . htmlspecialchars($option) . "</option>";
                    }
                  ?>
                </select>
              </div>
              
              <div>
                <label for="specialization" class="block text-sm font-medium text-gray-700 mb-2">Specialization</label>
                <input 
                  id="specialization" 
                  name="specialization" 
                  type="text" 
                  placeholder="Enter specialization" 
                  value="<?= htmlspecialchars($spec) ?>" 
                  readonly 
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                  required
                />
              </div>
              
              <div>
                <label for="designation" class="block text-sm font-medium text-gray-700 mb-2">Designation</label>
                <input 
                  id="designation" 
                  name="designation" 
                  type="text" 
                  placeholder="Enter designation" 
                  value="<?= htmlspecialchars($desig) ?>" 
                  readonly 
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                  required
                />
              </div>
              
              <div>
                <label for="department" class="block text-sm font-medium text-gray700 mb-2">Department</label>
                <select 
                  id="department" 
                  name="department" 
                  disabled
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all"
                  required
                >
                  <option value="">Select Department</option>
                  <?php
                    $deptOptions = [
                      "CA" => "College of Agriculture (CA)",
                      "CAS" => "College of Arts and Sciences (CAS)",
                      "CBAA" => "College of Business, Administration and Accountancy (CBAA)",
                      "CCS" => "College of Computer Studies (CCS)",
                      "CCJE" => "College of Criminal Justice Education (CCJE)",
                      "COE" => "College of Engineering (COE)",
                      "CIT" => "College of Industrial Technology (CIT)",
                      "CFND" => "College of Food, Nutrition and Dietetics (CFND)",
                      "COF" => "College of Fisheries (COF)",
                      "CIHTM" => "College of International Hospitality and Tourism Management (CIHTM)",
                      "CHMT" => "College of Hospitality Management and Tourism (CHMT)",
                      "CTE" => "College of Teacher Education (CTE)",
                      "CONAH" => "College of Nursing and Allied Health (CONAH)",
                      "COL" => "College of Law (COL)"
                    ];
                    foreach ($deptOptions as $val => $label) {
                      $selected = ($dept ?? '') === $val ? 'selected' : '';
                      echo "<option value=\"" . htmlspecialchars($val) . "\" $selected>" . htmlspecialchars($label) . "</option>";
                    }
                  ?>
                </select>
              </div>
              
              <div>
                <label for="yearsInLSPU" class="block text-sm font-medium text-gray-700 mb-2">Years in LSPU</label>
                <input 
                  id="yearsInLSPU" 
                  name="yearsInLSPU" 
                  type="number" 
                  min="0" 
                  max="50"
                  value="<?= htmlspecialchars($years) ?>"
                  readonly
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                  required
                />

                <?php
                  $start_year = is_numeric($computed_years) ? ($current_year - $computed_years) : '';
                ?>

                <?php if ($computed_years !== ''): ?>
                  <p class="text-xs text-gray-500 mt-2">
                    Started in <strong><?= $start_year ?></strong>,
                    now it's <strong><?= $computed_years ?></strong> year<?= $computed_years > 1 ? 's' : '' ?> as of <?= $current_year ?>.
                  </p>
                <?php endif; ?>
              </div>
              
              <div>
                <label for="teaching_status" class="block text-sm font-medium text-gray-700 mb-2">Type of Employment</label>
                <select 
                  id="teaching_status" 
                  name="teaching_status" 
                  disabled
                  class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition-all"
                  required
                >
                  <option value="" <?= ($teach ?? '') === "" ? "selected" : "" ?>>Select</option>
                  <option value="Teaching" <?= ($teach ?? '') === "Teaching" ? "selected" : "" ?>>Teaching</option>
                  <option value="Non Teaching" <?= ($teach ?? '') === "Non Teaching" ? "selected" : "" ?>>Non Teaching</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div id="actionButtons" class="pt-6 mt-6 border-t border-gray-200 hidden">
          <div class="flex justify-end gap-3">
            <button 
              type="button" 
              onclick="disableEditing()" 
              class="px-6 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 font-medium text-gray-700 transition-all duration-200 flex items-center gap-2"
            >
              <i class="ri-close-line"></i> Cancel
            </button>
            <button 
              type="submit" 
              name="update_profile" 
              class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-700 font-medium transition-all duration-200 flex items-center gap-2 shadow-lg hover:shadow-xl"
            >
              <i class="ri-save-line"></i> Save Changes
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</main>

<!-- Notification -->
<?php if (!empty($update_success)): ?>
  <div id="notification" class="fixed top-4 right-4 bg-gradient-to-r from-green-500 to-green-600 text-white p-4 rounded-xl shadow-lg font-medium flex items-center gap-3 z-50">
    <div class="w-6 h-6 flex items-center justify-center bg-white/20 text-white rounded-full">
      <i class="ri-check-line"></i>
    </div>
    <span>Profile updated successfully</span>
  </div>
<?php elseif (!empty($update_error)): ?>
  <div id="notification" class="fixed top-4 right-4 bg-gradient-to-r from-red-500 to-red-600 text-white p-4 rounded-xl shadow-lg font-medium flex items-center gap-3 z-50">
    <div class="w-6 h-6 flex items-center justify-center bg-white/20 text-white rounded-full">
      <i class="ri-close-line"></i>
    </div>
    <span><?= htmlspecialchars($update_error) ?></span>
  </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Enable editing fields and buttons
  function enableEditing() {
    const formElements = document.getElementById('profileForm').querySelectorAll('input, select, textarea');
    formElements.forEach(el => {
      el.removeAttribute('readonly');
      el.removeAttribute('disabled');
      el.classList.remove('bg-gray-50');
      el.classList.add('bg-white');
    });
    document.getElementById('uploadButton').classList.remove('hidden');
    document.getElementById('editButton').classList.add('hidden');
    document.getElementById('actionButtons').classList.remove('hidden');
  }

  // Disable editing fields and buttons
  function disableEditing() {
    const formElements = document.getElementById('profileForm').querySelectorAll('input, select, textarea');
    formElements.forEach(el => {
      el.setAttribute('readonly', true);
      el.setAttribute('disabled', true);
      el.classList.remove('bg-white');
      el.classList.add('bg-gray-50');
    });
    document.getElementById('uploadButton').classList.add('hidden');
    document.getElementById('editButton').classList.remove('hidden');
    document.getElementById('actionButtons').classList.add('hidden');
  }

  // Handle image file input change: preview image
  function handleImageChange(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file size (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire({
        title: 'File Too Large',
        text: 'File size should not exceed 2MB',
        icon: 'warning',
        confirmButtonColor: '#3b82f6',
        customClass: {
          popup: 'rounded-2xl'
        }
      });
      event.target.value = ''; // Clear the file input
      return;
    }

    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!validTypes.includes(file.type.toLowerCase())) {
      Swal.fire({
        title: 'Invalid File Type',
        text: 'Only JPG, PNG, or GIF images are allowed',
        icon: 'warning',
        confirmButtonColor: '#3b82f6',
        customClass: {
          popup: 'rounded-2xl'
        }
      });
      event.target.value = ''; // Clear the file input
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('profileImage').src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  // Form validation before submission
  function validateForm() {
    const requiredFields = [
      'first_name', 
      'last_name', 
      'educationalAttainment', 
      'specialization', 
      'designation', 
      'department', 
      'yearsInLSPU', 
      'teaching_status'
    ];
    
    let isValid = true;
    let firstEmptyField = null;
    
    requiredFields.forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field && !field.value.trim()) {
        field.classList.add('border-red-500');
        isValid = false;
        if (!firstEmptyField) {
          firstEmptyField = field;
        }
      } else if (field) {
        field.classList.remove('border-red-500');
      }
    });
    
    if (!isValid) {
      // Scroll to first empty field
      if (firstEmptyField) {
        firstEmptyField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstEmptyField.focus();
      }
      
      Swal.fire({
        title: 'Validation Error',
        text: 'Please fill in all required fields',
        icon: 'error',
        confirmButtonColor: '#ef4444',
        customClass: {
          popup: 'rounded-2xl'
        }
      });
      return false;
    }
    
    return true;
  }

  // Auto-hide notification after 3 seconds
  setTimeout(() => {
    const notif = document.getElementById('notification');
    if (notif) {
      notif.style.transition = 'opacity 0.3s ease';
      notif.style.opacity = '0';
      setTimeout(() => notif.remove(), 300);
    }
  }, 3000);

  // On page load: disable editing mode by default
  window.addEventListener('load', () => {
    disableEditing();
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
  });
</script>

</body>
</html>