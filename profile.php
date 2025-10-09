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
    
    // Handle image upload (for both regular and modal submissions)
    $image_data = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['profile_image']['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $update_error = "Only JPG, PNG, or GIF images are allowed.";
        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $update_error = "Image size must be less than 2MB.";
        } else {
            $image_data = file_get_contents($_FILES['profile_image']['tmp_name']);
        }
    } else {
        // If no new image uploaded, keep the existing one
        if (isset($_SESSION['profile_image_data'])) {
            $image_data = $_SESSION['profile_image_data'];
        }
    }
    
    $full_name = trim($first_name . ' ' . $last_name);

    // Basic validation for modal submission (minimum required fields)
    if ($is_modal_submission) {
        if (empty($first_name) || empty($last_name) || empty($educ) || empty($spec)) {
            $update_error = "Name, education, and specialization are required fields.";
        }
    }

    if (empty($update_error)) {
        // Save set year for future increment tracking
        $_SESSION['profile_yearInLSPU_set'] = date('Y');

        if ($image_data !== null) {
            // Update with image
            $stmt = $con->prepare("UPDATE users SET name=?, educationalAttainment=?, specialization=?, designation=?, department=?, yearsInLSPU=?, teaching_status=?, image_data=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $full_name, $educ, $spec, $desig, $dept, $years, $teach, $image_data, $user_id);
        } else {
            // Update without changing image
            $stmt = $con->prepare("UPDATE users SET name=?, educationalAttainment=?, specialization=?, designation=?, department=?, yearsInLSPU=?, teaching_status=? WHERE id=?");
            $stmt->bind_param("sssssssi", $full_name, $educ, $spec, $desig, $dept, $years, $teach, $user_id);
        }

        if ($stmt->execute()) {
            $update_success = true;
            // Update session data
            $_SESSION['profile_name'] = $full_name;
            $_SESSION['profile_first_name'] = $first_name;
            $_SESSION['profile_last_name'] = $last_name;
            $_SESSION['profile_educationalAttainment'] = $educ;
            $_SESSION['profile_specialization'] = $spec;
            $_SESSION['profile_designation'] = $desig;
            $_SESSION['profile_department'] = $dept;
            $_SESSION['profile_yearsInLSPU'] = $years;
            $_SESSION['profile_teaching_status'] = $teach;
            if ($image_data !== null) {
                $_SESSION['profile_image_data'] = $image_data;
            }

            // Different response for modal submission
            if ($is_modal_submission) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile updated successfully'
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

// Load user profile data from DB if not already stored in session
if (!isset($_SESSION['profile_name'])) {
    $stmt = $con->prepare("SELECT name, educationalAttainment, specialization, designation, department, yearsInLSPU, teaching_status, image_data FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($name, $educ, $spec, $desig, $dept, $years, $teach, $image_data);
    if ($stmt->fetch()) {
        $_SESSION['profile_name'] = $name;
        $_SESSION['profile_educationalAttainment'] = $educ;
        $_SESSION['profile_specialization'] = $spec;
        $_SESSION['profile_designation'] = $desig;
        $_SESSION['profile_department'] = $dept;
        $_SESSION['profile_yearsInLSPU'] = $years;
        $_SESSION['profile_teaching_status'] = $teach;
        $_SESSION['profile_image_data'] = $image_data;

        $name_parts = explode(' ', $name, 2);
        $_SESSION['profile_first_name'] = $name_parts[0];
        $_SESSION['profile_last_name'] = $name_parts[1] ?? '';

        // Set initial year tracker
        if (!isset($_SESSION['profile_yearInLSPU_set']) && is_numeric($years)) {
            $_SESSION['profile_yearInLSPU_set'] = date('Y');
        }
    }
    $stmt->close();
}

// Assign session variables for display
$first_name = $_SESSION['profile_first_name'] ?? '';
$last_name = $_SESSION['profile_last_name'] ?? '';
$educ = $_SESSION['profile_educationalAttainment'] ?? '';
$spec = $_SESSION['profile_specialization'] ?? '';
$desig = $_SESSION['profile_designation'] ?? '';
$dept = $_SESSION['profile_department'] ?? '';
$years = $_SESSION['profile_yearsInLSPU'] ?? '';
$teach = $_SESSION['profile_teaching_status'] ?? '';
$image_data = $_SESSION['profile_image_data'] ?? '';

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
  <title>Profile</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>

  <!-- Tailwind Config -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#f0f9ff',
              100: '#e0f2fe',
              200: '#bae6fd',
              300: '#7dd3fc',
              400: '#38bdf8',
              500: '#0ea5e9',
              600: '#0284c7',
              700: '#0369a1',
              800: '#075985',
              900: '#0c4a6e',
            },
            secondary: {
              50: '#f8fafc',
              100: '#f1f5f9',
              200: '#e2e8f0',
              300: '#cbd5e1',
              400: '#94a3b8',
              500: '#64748b',
              600: '#475569',
              700: '#334155',
              800: '#1e293b',
              900: '#0f172a',
            },
          },
          fontFamily: {
            sans: ['Poppins', 'sans-serif'],
          },
          borderRadius: {
            DEFAULT: '0.5rem',
            'button': '0.375rem'
          },
          boxShadow: {
            'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
            'focus': '0 0 0 3px rgba(14, 165, 233, 0.5)',
          }
        }
      }
    };
  </script>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />

  <!-- Custom Styles -->
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f8fafc;
    }
    
    /* Smooth transitions */
    input, select, button, textarea {
      transition: all 0.2s ease;
    }
    
    /* Focus styles */
    input:focus, select:focus, textarea:focus {
      outline: none;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.5);
      border-color: #0ea5e9;
    }
    
    /* Profile image hover effect */
    #profileImageContainer:hover #uploadButton {
      opacity: 1;
    }
    
    /* Show teaching status text only on print */
    #teachingStatusText {
      display: none;
    }
    
    @media print {
      #teachingStatusGroup, #editButton, #actionButtons {
        display: none;
      }
      #teachingStatusText {
        display: block;
        font-weight: 600;
        margin-top: 10px;
      }
      #printableProfile {
        display: block !important;
      }
    }
  </style>
  <style>
    .group.open .ri-arrow-down-s-line {
      transform: rotate(180deg);
    }
  </style>
</head>

<body class="flex h-screen bg-gray-50">

<!-- Sidebar -->
<aside class="w-64 bg-blue-900 text-white shadow-sm flex flex-col justify-between">
  <div class="h-full flex flex-col">

    <!-- Logo & Title - Removed border-b -->
    <div class="p-6 flex items-center">
      <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-3" />
      <a href="user_page.php" class="text-lg font-semibold text-white">Training Needs Assessment</a>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-8">
      <div class="space-y-2">
        <a href="user_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-dashboard-line"></i></div>
          TNA
        </a>
        <!-- IDP Forms Dropdown -->
        <div class="group">
          <button id="idp-dropdown-btn" class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-medium rounded-lg hover:bg-blue-700 transition-all">
            <div class="flex items-center">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-text-line"></i></div>
              IDP Forms
            </div>
            <i class="ri-arrow-down-s-line transition-transform duration-300 group-[.open]:rotate-180"></i>
          </button>
          
          <div id="idp-dropdown-menu" class="hidden pl-8 mt-1 space-y-1 group-[.open]:block">
            <a href="Individual_Development_Plan.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-all">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-add-line"></i></div>
              Create New
            </a>
            <a href="save_idp_forms.php" class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-all">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-list-line"></i></div>
              My Submitted Forms
            </a>
          </div>
        </div>
        <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg bg-blue-700 hover:bg-blue-700 transition-all">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-user-line"></i></div>
          Profile
        </a>
      </div>
    </nav>

    <!-- Sign Out - Removed border-t -->
    <div class="p-4">
      <a href="homepage.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md hover:bg-red-600 text-white">
        <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-logout-box-line"></i></div>
        Sign Out
      </a>
    </div>
  </div>
</aside>


  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto">
    <div class="p-8 max-w-5xl mx-auto">
      <section class="bg-white rounded-xl shadow-sm overflow-hidden">
        <!-- Header -->
        <header class="bg-primary-50 border-b border-gray-200 px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-semibold text-gray-900">Personal Information</h1>
              <p class="text-sm text-gray-500 mt-1">Update your profile details and preferences</p>
            </div>
            <button
              id="editButton"
              type="button"
              class="text-primary-600 hover:text-primary-800 font-medium flex items-center gap-2"
              onclick="enableEditing()"
            >
              <i class="ri-edit-line"></i> Edit Profile
            </button>
          </div>
        </header>

        <div class="p-8">
          <div class="flex flex-col lg:flex-row gap-8">
            <!-- Profile Image Column -->
            <aside class="w-full lg:w-1/3">
              <div id="profileImageContainer" class="relative group">
                <div class="relative overflow-hidden rounded-xl border-2 border-gray-200 w-full aspect-square">
                  <img 
                    id="profileImage" 
                    src="<?= !empty($image_data) ? 'data:image/jpeg;base64,' . base64_encode($image_data) : 'images/noprofile.jpg' ?>" 
                    alt="Profile Picture" 
                    class="w-full h-full object-cover"
                  />
                  <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center">
                    <span class="text-white font-medium">Change Photo</span>
                  </div>
                </div>
                <input id="imageInput" type="file" name="profile_image" accept="image/*" class="hidden" onchange="handleImageChange(event)" />
                <button 
                  id="uploadButton" 
                  type="button" 
                  class="mt-4 w-full px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors duration-200 flex items-center justify-center gap-2 hidden"
                  onclick="document.getElementById('imageInput').click()"
                >
                  <i class="ri-upload-line"></i> Upload New Photo
                </button>
                <p class="text-xs text-gray-500 mt-2 text-center">JPG, GIF or PNG. Max size 2MB</p>
              </div>
            </aside>

            <!-- Profile Form -->
            <form id="profileForm" class="flex-1" method="POST" action="" enctype="multipart/form-data" onsubmit="return validateForm()">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                  <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Basic Information</h2>
                </div>
                
                <div>
                  <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                  <input 
                    id="first_name" 
                    name="first_name" 
                    type="text" 
                    placeholder="Enter first name" 
                    value="<?= htmlspecialchars($first_name ?? '') ?>" 
                    readonly 
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500" 
                    required
                  />
                </div>
                
                <div>
                  <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                  <input 
                    id="last_name" 
                    name="last_name" 
                    type="text" 
                    placeholder="Enter last name" 
                    value="<?= htmlspecialchars($last_name ?? '') ?>" 
                    readonly 
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500" 
                    required
                  />
                </div>
                
                <div>
                  <label for="educationalAttainment" class="block text-sm font-medium text-gray-700 mb-1">Educational Attainment</label>
                  <select 
                    id="educationalAttainment" 
                    name="educationalAttainment" 
                    disabled
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500"
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
                  <label for="specialization" class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                  <input 
                    id="specialization" 
                    name="specialization" 
                    type="text" 
                    placeholder="Enter specialization" 
                    value="<?= htmlspecialchars($spec ?? '') ?>" 
                    readonly 
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500" 
                    required
                  />
                </div>
                
                <div class="md:col-span-2">
                  <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Employment Details</h2>
                </div>
                
                <div>
                  <label for="designation" class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                  <input 
                    id="designation" 
                    name="designation" 
                    type="text" 
                    placeholder="Enter designation" 
                    value="<?= htmlspecialchars($desig ?? '') ?>" 
                    readonly 
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500" 
                    required
                  />
                </div>
                
                <div>
                  <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                  <select 
                    id="department" 
                    name="department" 
                    disabled
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500"
                    required
                  >
                    <option value="">Select Department</option>
                    <?php
                      $deptOptions = [
                        "CCS" => "College of Computer Studies (CCS)",
                        "CAS" => "College of Arts and Sciences (CAS)",
                        "CBAA" => "College of Business, Administration and Accountancy (CBAA)",
                        "CCJE" => "College of Criminal Justice Education (CCJE)",
                        "CFND" => "College of Food Nutrition and Dietetics (CFND)",
                        "CHMT" => "College of Hospitality Management and Tourism (CHMT)",
                        "COF" => "College of Fisheries (COF)",
                        "CTE" => "College of Teacher Education (CTE)",
                      ];
                      foreach ($deptOptions as $val => $label) {
                        $selected = ($dept ?? '') === $val ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($val) . "\" $selected>" . htmlspecialchars($label) . "</option>";
                      }
                    ?>
                  </select>
                </div>
                
                <div>
                  <label for="yearsInLSPU" class="block text-sm font-medium text-gray-700 mb-1">Years in LSPU</label>
                  <input 
                    id="yearsInLSPU" 
                    name="yearsInLSPU" 
                    type="number" 
                    min="0" 
                    max="50"
                    value="<?= htmlspecialchars($years) ?>"
                    readonly
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500" 
                    required
                  />

                  <?php
                    $start_year = is_numeric($computed_years) ? ($current_year - $computed_years) : '';
                  ?>

                  <?php if ($computed_years !== ''): ?>
                    <p class="text-xs text-gray-500 mt-1">
                      Started in <strong><?= $start_year ?></strong>,
                      now it's <strong><?= $computed_years ?></strong> year<?= $computed_years > 1 ? 's' : '' ?> as of <?= $current_year ?>.
                    </p>
                  <?php endif; ?>
                </div>
                
                <div>
                  <label for="teaching_status" class="block text-sm font-medium text-gray-700 mb-1">Type of Employment</label>
                  <select 
                    id="teaching_status" 
                    name="teaching_status" 
                    disabled
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 focus:ring-primary-500 focus:border-primary-500"
                    required
                  >
                    <option value="" <?= ($teach ?? '') === "" ? "selected" : "" ?>>Select</option>
                    <option value="Teaching" <?= ($teach ?? '') === "Teaching" ? "selected" : "" ?>>Teaching</option>
                    <option value="Non Teaching" <?= ($teach ?? '') === "Non Teaching" ? "selected" : "" ?>>Non Teaching</option>
                  </select>
                </div>
                
                <div id="actionButtons" class="md:col-span-2 pt-4 mt-4 border-t border-gray-200 hidden">
                  <div class="flex justify-end gap-3">
                    <button 
                      type="button" 
                      onclick="disableEditing()" 
                      class="px-6 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium text-gray-700 transition-colors duration-200"
                    >
                      Cancel
                    </button>
                    <button 
                      type="submit" 
                      name="update_profile" 
                      class="px-6 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium transition-colors duration-200 flex items-center gap-2"
                    >
                      <i class="ri-save-line"></i> Save Changes
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- Printable Profile (Hidden in UI, visible only when printing) -->
      <section id="printableProfile" class="hidden mt-8 print:block bg-white p-6 rounded-lg">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 border-b pb-2">Profile Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="mb-2"><strong class="text-gray-700">Name:</strong> <span id="printName" class="text-gray-900"><?= htmlspecialchars(trim(($first_name ?? '') . ' ' . ($last_name ?? ''))) ?></span></p>
            <p class="mb-2"><strong class="text-gray-700">Educational Attainment:</strong> <span id="printEduc" class="text-gray-900"><?= htmlspecialchars($educ ?? '') ?></span></p>
            <p class="mb-2"><strong class="text-gray-700">Specialization:</strong> <span id="printSpec" class="text-gray-900"><?= htmlspecialchars($spec ?? '') ?></span></p>
          </div>
          <div>
            <p class="mb-2"><strong class="text-gray-700">Designation:</strong> <span id="printDesig" class="text-gray-900"><?= htmlspecialchars($desig ?? '') ?></span></p>
            <p class="mb-2"><strong class="text-gray-700">Department:</strong> <span id="printDept" class="text-gray-900"><?= htmlspecialchars($deptOptions[$dept] ?? $dept ?? '') ?></span></p>
            <p class="mb-2"><strong class="text-gray-700">Years in LSPU:</strong> <span id="printYears" class="text-gray-900"><?= htmlspecialchars($years ?? '') ?></span></p>
            <p class="mb-2"><strong class="text-gray-700">Type of Employment:</strong> <span id="printTeach" class="text-gray-900"><?= htmlspecialchars($teach ?? '') ?></span></p>
          </div>
        </div>
        <div class="mt-6 flex justify-center">
          <?php if (!empty($image_data)): ?>
            <img src="data:image/jpeg;base64,<?= base64_encode($image_data) ?>" alt="Profile Picture" class="w-32 h-32 object-cover rounded-lg border border-gray-200" />
          <?php endif; ?>
        </div>
        <div class="mt-6 text-xs text-gray-500 text-right">
          Printed on <?= date('F j, Y') ?>
        </div>
      </section>
    </div>
  </main>

  <!-- Notification -->
  <?php if (!empty($update_success)): ?>
    <div id="notification" class="fixed top-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg font-medium flex items-center gap-3 animate-fade-in">
      <div class="w-6 h-6 flex items-center justify-center bg-green-500 text-white rounded-full">
        <i class="ri-check-line"></i>
      </div>
      <span>Profile updated successfully</span>
    </div>
  <?php elseif (!empty($update_error)): ?>
    <div id="notification" class="fixed top-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg font-medium flex items-center gap-3 animate-fade-in">
      <div class="w-6 h-6 flex items-center justify-center bg-red-500 text-white rounded-full">
        <i class="ri-close-line"></i>
      </div>
      <span><?= htmlspecialchars($update_error) ?></span>
    </div>
  <?php endif; ?>

  <script>
    // Enable editing fields and buttons
    function enableEditing() {
      const formElements = document.getElementById('profileForm').querySelectorAll('input, select');
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
      const formElements = document.getElementById('profileForm').querySelectorAll('input, select');
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
        alert('File size should not exceed 2MB');
        return;
      }

      // Validate file type
      const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
      if (!validTypes.includes(file.type)) {
        alert('Only JPG, PNG, or GIF images are allowed');
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
      
      requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (!field.value.trim()) {
          field.classList.add('border-red-500');
          isValid = false;
        } else {
          field.classList.remove('border-red-500');
        }
      });
      
      if (!isValid) {
        alert('Please fill in all required fields');
        return false;
      }
      
      return true;
    }

    // Auto-hide notification after 3 seconds
    setTimeout(() => {
      const notif = document.getElementById('notification');
      if (notif) {
        notif.classList.add('animate-fade-out');
        setTimeout(() => notif.remove(), 300);
      }
    }, 3000);

    // On page load: disable editing mode by default
    window.addEventListener('load', () => {
      disableEditing();
    });
  </script>


  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const dropdownBtn = document.getElementById('idp-dropdown-btn');
      const dropdownMenu = document.getElementById('idp-dropdown-menu');
      
      dropdownBtn.addEventListener('click', function() {
        dropdownBtn.parentElement.classList.toggle('open');
        dropdownMenu.classList.toggle('hidden');
      });
    });
  </script>

  <style>
    /* Animation for notification */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeOut {
      from { opacity: 1; transform: translateY(0); }
      to { opacity: 0; transform: translateY(-20px); }
    }
    
    .animate-fade-in {
      animation: fadeIn 0.3s ease-out forwards;
    }
    
    .animate-fade-out {
      animation: fadeOut 0.3s ease-in forwards;
    }
    
    /* Error state for form fields */
    .border-red-500 {
      border-color: #ef4444;
    }
    
    @media print {
      /* Hide form and buttons during print */
      #profileForm, #editButton, #actionButtons {
        display: none !important;
      }
      /* Show printable profile info */
      #printableProfile {
        display: block !important;
        background: white;
        padding: 1rem;
        font-size: 16px;
      }
      /* Hide sidebar when printing */
      aside {
        display: none !important;
      }
      /* Adjust main content for printing */
      main {
        margin-left: 0 !important;
        width: 100% !important;
      }
    }
  </style>

</body>
</html>