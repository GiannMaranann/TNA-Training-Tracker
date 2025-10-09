<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$form_data = null;
$is_edit = false;

// Get user information from database
$user_info = [];
$stmt = $con->prepare("SELECT name, yearsInLSPU FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_info = $result->fetch_assoc();
}
$stmt->close();

// Check if we're editing an existing form
if ($form_id > 0) {
    // Get form data from idp_forms table
    $stmt = $con->prepare("SELECT * FROM idp_forms WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $form_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $form_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($form_data) {
        $is_edit = true;
        $form_data['form_data'] = json_decode($form_data['form_data'], true);
        
        // Get additional data from idp_personal_info table
        $stmt = $con->prepare("SELECT * FROM idp_personal_info WHERE form_id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $personal_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($personal_info) {
            $form_data['form_data']['personal_info'] = array_merge(
                $form_data['form_data']['personal_info'] ?? [],
                $personal_info
            );
        }
        
        // Get purpose data
        $stmt = $con->prepare("SELECT * FROM idp_purpose WHERE form_id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $purpose = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($purpose) {
            $form_data['form_data']['purpose'] = $purpose;
        }
        
        // Get certification data
        $stmt = $con->prepare("SELECT * FROM idp_certification WHERE form_id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $certification = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($certification) {
            $form_data['form_data']['certification'] = $certification;
        }
        
        // Get long term goals
        $stmt = $con->prepare("SELECT * FROM idp_long_term_goals WHERE form_id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $long_term_goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $form_data['form_data']['long_term_goals'] = $long_term_goals;
        
        // Get short term goals
        $stmt = $con->prepare("SELECT * FROM idp_short_term_goals WHERE form_id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $short_term_goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $form_data['form_data']['short_term_goals'] = $short_term_goals;
    } else {
        $form_id = 0;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Begin transaction
    $con->begin_transaction();
    
    try {
        // Prepare form data for storage
        $form_input = [
            'personal_info' => [
                'name' => $_POST['name'] ?? $user_info['name'],
                'position' => $_POST['position'] ?? '',
                'salary_grade' => $_POST['salary_grade'] ?? '',
                'years_position' => $_POST['years_position'] ?? '',
                'years_lspu' => $_POST['years_lspu'] ?? $user_info['yearsInLSPU'],
                'years_other' => $_POST['years_other'] ?? '', // Changed to match database column
                'division' => $_POST['division'] ?? '',
                'office' => $_POST['office'] ?? '',
                'address' => $_POST['address'] ?? '',
                'supervisor' => $_POST['supervisor'] ?? ''
            ],
            'purpose' => [
                'purpose1' => isset($_POST['purpose1']) ? 1 : 0,
                'purpose2' => isset($_POST['purpose2']) ? 1 : 0,
                'purpose3' => isset($_POST['purpose3']) ? 1 : 0,
                'purpose4' => isset($_POST['purpose4']) ? 1 : 0,
                'purpose5' => isset($_POST['purpose5']) ? 1 : 0,
                'purpose_ofiber' => $_POST['purpose_ofiber'] ?? '' // This matches idp_purpose table
            ],
            'long_term_goals' => [],
            'short_term_goals' => [],
            'certification' => [
                'employee_name' => $_POST['employee_name'] ?? '',
                'employee_date' => $_POST['employee_date'] ?? '',
                'supervisor_name' => $_POST['supervisor_name'] ?? '',
                'supervisor_date' => $_POST['supervisor_date'] ?? '',
                'director_name' => $_POST['director_name'] ?? '',
                'director_date' => $_POST['director_date'] ?? ''
            ]
        ];

        // Process long term goals (unchanged)
        if (isset($_POST['long_term_area']) && is_array($_POST['long_term_area'])) {
            foreach ($_POST['long_term_area'] as $index => $area) {
                if (!empty($area) || !empty($_POST['long_term_activity'][$index])) {
                    $form_input['long_term_goals'][] = [
                        'area' => $area,
                        'activity' => $_POST['long_term_activity'][$index] ?? '',
                        'target_date' => $_POST['long_term_date'][$index] ?? '',
                        'stage' => $_POST['long_term_stage'][$index] ?? ''
                    ];
                }
            }
        }

        // Process short term goals (unchanged)
        if (isset($_POST['short_term_area']) && is_array($_POST['short_term_area'])) {
            foreach ($_POST['short_term_area'] as $index => $area) {
                if (!empty($area) || !empty($_POST['short_term_priority'][$index])) {
                    $form_input['short_term_goals'][] = [
                        'area' => $area,
                        'priority' => $_POST['short_term_priority'][$index] ?? '',
                        'activity' => $_POST['short_term_activity'][$index] ?? '',
                        'target_date' => $_POST['short_term_date'][$index] ?? '',
                        'responsible' => $_POST['short_term_responsible'][$index] ?? '',
                        'stage' => $_POST['short_term_stage'][$index] ?? ''
                    ];
                }
            }
        }

        $json_data = json_encode($form_input);
        $status = isset($_POST['submit_form']) ? 'submitted' : 'draft';
        $submitted_at = $status === 'submitted' ? date('Y-m-d H:i:s') : null;

        // Save to idp_forms table (unchanged)
        if ($is_edit) {
            $stmt = $con->prepare("UPDATE idp_forms SET form_data = ?, status = ?, submitted_at = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssi", $json_data, $status, $submitted_at, $form_id);
        } else {
            $stmt = $con->prepare("INSERT INTO idp_forms (user_id, form_data, status, submitted_at, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $user_id, $json_data, $status, $submitted_at);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save form data: " . $stmt->error);
        }
        
        if (!$is_edit) {
            $form_id = $con->insert_id;
        }
        $stmt->close();

        // Save to idp_personal_info table - UPDATED TO MATCH YOUR SCHEMA
        $stmt = $con->prepare("REPLACE INTO idp_personal_info (form_id, name, position, salary_grade, years_position, years_lspu, years_other, division, office, address, supervisor) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssssss", 
            $form_id,
            $form_input['personal_info']['name'],
            $form_input['personal_info']['position'],
            $form_input['personal_info']['salary_grade'],
            $form_input['personal_info']['years_position'],
            $form_input['personal_info']['years_lspu'],
            $form_input['personal_info']['years_other'], // Changed to match database column
            $form_input['personal_info']['division'],
            $form_input['personal_info']['office'],
            $form_input['personal_info']['address'],
            $form_input['personal_info']['supervisor']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save personal info: " . $stmt->error);
        }
        $stmt->close();

        // Save to idp_purpose table - unchanged (matches your schema)
        $stmt = $con->prepare("REPLACE INTO idp_purpose (form_id, purpose1, purpose2, purpose3, purpose4, purpose5, purpose_other) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiiis", 
            $form_id,
            $form_input['purpose']['purpose1'],
            $form_input['purpose']['purpose2'],
            $form_input['purpose']['purpose3'],
            $form_input['purpose']['purpose4'],
            $form_input['purpose']['purpose5'],
            $form_input['purpose']['purpose_other']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save purpose data: " . $stmt->error);
        }
        $stmt->close();

        // Save to idp_certification table - unchanged
        $stmt = $con->prepare("REPLACE INTO idp_certification (form_id, employee_name, employee_date, supervisor_name, supervisor_date, director_name, director_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", 
            $form_id,
            $form_input['certification']['employee_name'],
            $form_input['certification']['employee_date'],
            $form_input['certification']['supervisor_name'],
            $form_input['certification']['supervisor_date'],
            $form_input['certification']['director_name'],
            $form_input['certification']['director_date']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save certification data: " . $stmt->error);
        }
        $stmt->close();

        // Rest of your code remains unchanged...
        // [Keep all the remaining code for long_term_goals, short_term_goals, etc.]
        
        // Commit transaction
        $con->commit();

        $_SESSION['message'] = [
            'type' => 'success',
            'text' => $status === 'submitted' ? 'IDP form submitted successfully!' : 'IDP form saved as draft.'
        ];
        
        if ($status === 'submitted') {
            // Create notification for HR/admin
            $message = "New IDP form submitted by " . ($form_input['personal_info']['name'] ?? 'an employee');
            $stmt = $con->prepare("INSERT INTO notifications (user_id, message, related_id, type) 
                                  SELECT id, ?, ?, 'idp_form' FROM users WHERE role = 'admin'");
            $stmt->bind_param("si", $message, $form_id);
            $stmt->execute();
            $stmt->close();
            
            header("Location: save_idp_forms.php");
            exit();
        } else {
            header("Location: Individual_Development_Plan.php?id=" . $form_id);
            exit();
        }
    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'Error saving form: ' . $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Individual Development Plan</title>
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="css/idp_style.css">

<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#2563eb',
        secondary: '#3b82f6',
        accent: '#1d4ed8',
        light: '#f8fafc',
        dark: '#1e293b',
        sidebar: '#1e40af',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444'
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
      },
      fontFamily: {
        'sans': ['Poppins', 'sans-serif'],
        'display': ['Poppins', 'sans-serif']
      },
      boxShadow: {
        'soft': '0 4px 20px rgba(0, 0, 0, 0.08)',
        'focus': '0 0 0 3px rgba(37, 99, 235, 0.2)',
        'card': '0 2px 8px rgba(0, 0, 0, 0.1)'
      }
    }
  }
}
</script>
<style>
  .group.open .ri-arrow-down-s-line {
    transform: rotate(180deg);
  }
</style>
</head>
<body class="min-h-screen font-sans flex">
<!-- Fixed Sidebar -->
<aside class="sidebar bg-blue-900 text-white shadow-sm flex flex-col justify-between no-print">
  <div class="h-full flex flex-col">
    <!-- Logo & Title -->
    <div class="p-6 flex items-center">
      <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-3" />
      <a href="user_page.php" class="text-lg font-semibold text-white">Training Needs Assessment</a>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-8">
      <div class="space-y-2">
        <a href="user_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all" id="tna-link">
          <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-dashboard-line"></i></div>
          TNA
        </a>
        
        <!-- IDP Forms Dropdown -->
        <div class="group" id="idp-dropdown">
          <button id="idp-dropdown-btn" class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <div class="flex items-center">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-text-line"></i></div>
              IDP Forms
            </div>
            <i class="ri-arrow-down-s-line transition-transform duration-300 group-[.open]:rotate-180"></i>
          </button>
          
          <div id="idp-dropdown-menu" class="hidden pl-8 mt-1 space-y-1 group-[.open]:block">
            <a href="Individual_Development_Plan.php" class="flex items-center px-4 py-2 text-sm rounded-md hover:bg-blue-700 transition-all" id="create-new-link">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-add-line"></i></div>
              Create New
            </a>
            <a href="save_idp_forms.php" class="flex items-center px-4 py-2 text-sm rounded-md hover:bg-blue-700 transition-all" id="submitted-forms-link">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-list-line"></i></div>
              My Submitted Forms
            </a>
          </div>
        </div>
        
        <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all" id="profile-link">  
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
<div class="flex-1 main-content">
  <div class="container mx-auto px-4 py-8">
    <?php if (isset($_SESSION['message'])): ?>
      <div class="mb-4 p-4 rounded-lg <?php echo $_SESSION['message']['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> no-print">
        <?php echo $_SESSION['message']['text']; ?>
      </div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <!-- Print Header (only visible when printing) -->
    <div class="print-only">
      <div class="form-title">Republic of the Philippines</div>
      <div class="university-name">Laguna State Polytechnic University</div>
      <div class="province">Province of Laguna</div>
      <div class="form-title">INDIVIDUAL DEVELOPMENT PLAN</div>
    </div>
    
    <!-- Current Form -->
    <form method="POST" action="" class="bg-white shadow-soft rounded-xl overflow-hidden">
      <input type="hidden" name="form_id" value="<?php echo $form_id; ?>">
      
      <div class="p-8 md:p-12 form-container">
        <!-- Header Section -->
        <div class="text-center mb-10 no-print">
          <div class="flex items-center justify-center mb-4">
            <div class="mr-4">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
              </svg>
            </div>
            <div>
              <h1 class="text-3xl font-bold text-primary">INDIVIDUAL DEVELOPMENT PLAN</h1>
              <p class="text-sm text-gray-500 mt-1">Employee Growth and Competency Roadmap</p>
            </div>
          </div>
          <div class="w-24 h-1.5 bg-gradient-to-r from-primary to-secondary mx-auto rounded-full"></div>
        </div>
        
        <!-- Form Actions -->
        <div class="flex justify-between items-center mb-6 no-print">
          <div>
            <span class="text-sm font-medium text-gray-600">Form Status:</span>
            <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium <?php echo ($is_edit && $form_data['status'] === 'submitted') ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
              <?php echo ($is_edit && $form_data['status'] === 'submitted') ? 'Submitted' : ($is_edit ? 'Draft' : 'New'); ?>
            </span>
          </div>
          <div class="flex space-x-3">
            <button type="button" id="print-btn" class="bg-primary hover:bg-accent text-white px-4 py-2 rounded-lg flex items-center">
              <i class="fas fa-print mr-2"></i> Print Form
            </button>
          </div>
        </div>
        
        <!-- Personal Information Section -->
        <div class="mb-10">
          <h3 class="text-xl font-bold mb-6 text-dark flex items-center no-print">
            <i class="fas fa-user-circle mr-2 text-primary"></i> Personal Information
          </h3>
          
          <div class="print-only">
            <table class="personal-info-table">
              <tr>
                <td>1. Name</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['name']) : ''; ?></td>
                <td>6. Years in other office/agency if any</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_other']) : ''; ?></td>
              </tr>
              <tr>
                <td>2. Current Position</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['position']) : ''; ?></td>
                <td>7. Division</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['division']) : ''; ?></td>
              </tr>
              <tr>
                <td>3. Salary Grade</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['salary_grade']) : ''; ?></td>
                <td>8. Office</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['office']) : ''; ?></td>
              </tr>
              <tr>
                <td>4. Years in the Position</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_position']) : ''; ?></td>
                <td>9. No more development is desired or required for</td>
                <td></td>
              </tr>
              <tr>
                <td>5. Years in LSPU</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_lspu']) : ''; ?></td>
                <td>10. Supervisor's Name</td>
                <td><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['supervisor']) : ''; ?></td>
              </tr>
            </table>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 no-print">
            <!-- Column 1 -->
      <div class="space-y-6">
        <div class="floating-label">
          <input type="text" id="name" name="name" placeholder=" " class="border border-gray-200" 
                 value="<?php 
                 echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['name']) : 
                                (isset($user_info['full_name']) ? htmlspecialchars($user_info['full_name']) : ''); 
                 ?>" <?php echo isset($user_info['full_name']) ? 'readonly' : ''; ?>>
          <label for="name">Name</label>
        </div>
              
              <div class="floating-label">
                <input type="text" id="position" name="position" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['position']) : ''; ?>">
                <label for="position">Current Position</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="salary-grade" name="salary_grade" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['salary_grade']) : ''; ?>">
                <label for="salary-grade">Salary Grade</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="years-position" name="years_position" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_position']) : ''; ?>">
                <label for="years-position">Years in this Position</label>
              </div>
              
               <div class="floating-label">
          <input type="text" id="years-lspu" name="years_lspu" placeholder=" " class="border border-gray-200"
                 value="<?php 
                 echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_lspu']) : 
                                (isset($user_info['years_in_lspu']) ? htmlspecialchars($user_info['years_in_lspu']) : ''); 
                 ?>" <?php echo isset($user_info['years_in_lspu']) ? 'readonly' : ''; ?>>
          <label for="years-lspu">Years in LSPU</label>
        </div>
      </div>
            
            <!-- Column 2 -->
            <div class="space-y-6">
              <div class="floating-label">
                <input type="text" id="years-other" name="years_other" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_other']) : ''; ?>">
                <label for="years-other">Years in Other Office/Agency if any</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="division" name="division" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['division']) : ''; ?>">
                <label for="division">Division</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="office" name="office" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['office']) : ''; ?>">
                <label for="office">Office</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="address" name="address" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['address']) : ''; ?>">
                <label for="address">Office Address</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="supervisor" name="supervisor" placeholder=" " class="border border-gray-200"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['supervisor']) : ''; ?>">
                <label for="supervisor">Supervisor's Name</label>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Purpose Section -->
        <div class="mb-10">
          <h3 class="text-xl font-bold mb-6 text-dark flex items-center no-print">
            <i class="fas fa-bullseye mr-2 text-primary"></i> Purpose
          </h3>
          
          <div class="print-only section-title">PURPOSE:</div>
          
          <div class="bg-blue-50 p-6 rounded-lg no-print">
            <div class="space-y-3">
              <div class="flex items-start">
                <input type="checkbox" id="purpose1" name="purpose1" class="checkbox-custom mt-1 mr-3" 
                       <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose1']) ? 'checked' : ''; ?>>
                <label for="purpose1" class="text-sm text-gray-700 flex-1">To meet the competencies in the current positions</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose2" name="purpose2" class="checkbox-custom mt-1 mr-3"
                       <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose2']) ? 'checked' : ''; ?>>
                <label for="purpose2" class="text-sm text-gray-700 flex-1">To increase the level of competencies of current positions</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose3" name="purpose3" class="checkbox-custom mt-1 mr-3"
                       <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose3']) ? 'checked' : ''; ?>>
                <label for="purpose3" class="text-sm text-gray-700 flex-1">To meet the competencies in the next higher position</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose4" name="purpose4" class="checkbox-custom mt-1 mr-3"
                       <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose4']) ? 'checked' : ''; ?>>
                <label for="purpose4" class="text-sm text-gray-700 flex-1">To acquire new competencies across different functions/position</label>
              </div>
              
              <div class="flex items-start">
                <input type="checkbox" id="purpose5" name="purpose5" class="checkbox-custom mt-1 mr-3"
                       <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose5']) ? 'checked' : ''; ?>>
                <div class="flex-1">
                  <label for="purpose5" class="text-sm text-gray-700">Others, please specify:</label>
                  <input type="text" id="purpose-other" name="purpose_other" class="mt-1 w-full border-b border-gray-300 bg-transparent focus:border-primary focus:outline-none" 
                         placeholder="Specify here" value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['purpose']['purpose_other']) : ''; ?>">
                </div>
              </div>
            </div>
          </div>
          
          <div class="print-only purpose-section">
            <div class="purpose-item">
              (<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose1']) ? '✓' : ' '; ?>) To meet the competencies in the current positions
            </div>
            <div class="purpose-item">
              (<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose2']) ? '✓' : ' '; ?>) To increase the level of competencies of current positions
            </div>
            <div class="purpose-item">
              (<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose3']) ? '✓' : ' '; ?>) To meet the competencies in the next higher position
            </div>
            <div class="purpose-item">
              (<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose4']) ? '✓' : ' '; ?>) To acquire new competencies across different functions/position
            </div>
            <div class="purpose-item">
              (<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose5']) ? '✓' : ' '; ?>) Others, please specify: <?php echo $is_edit ? htmlspecialchars($form_data['form_data']['purpose']['purpose_other']) : ''; ?>
            </div>
          </div>
        </div>
        
<!-- Career Development Section -->
<div class="mb-10">
  <h3 class="text-xl font-bold mb-6 text-dark flex items-center no-print">
    <i class="fas fa-chart-line mr-2 text-primary"></i> Career Development
  </h3>
  
  <div class="print-only section-title">CAREER DEVELOPMENT:</div>
  
  <!-- Long Term Goals -->
  <div class="mb-10">
    <h4 class="font-bold mb-4 text-primary flex items-center no-print">
      <i class="fas fa-calendar-alt mr-2"></i> Training/Development Interventions for Long Term Goals (Next Five Years)
    </h4>
    
    <div class="print-only development-title">Training/Development Interventions for Long Terms Goals (Next Five Years)</div>
    
    <div class="overflow-visible">
      <table class="w-full rounded-lg development-table" id="long-term-goals">
        <thead>
          <tr>
            <th class="w-1/4 p-2">Area of Development</th>
            <th class="w-1/4 p-2">Development Activity</th>
            <th class="w-1/4 p-2">Target Completion Date</th>
            <th class="w-1/4 p-2">Completion Stage</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($is_edit && !empty($form_data['form_data']['long_term_goals'])): ?>
            <?php foreach ($form_data['form_data']['long_term_goals'] as $goal): ?>
              <tr>
                <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top"><?php echo htmlspecialchars($goal['area']); ?></td>
                <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top default-activity" contenteditable="true" data-name="long_term_activity[]"><?php echo htmlspecialchars($goal['activity']); ?></td>
                <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top">
                  <input type="date" name="long_term_date[]" class="w-full border-none bg-transparent focus:bg-blue-50 no-print" value="<?php echo htmlspecialchars($goal['target_date']); ?>">
                  <span class="print-only"><?php echo htmlspecialchars($goal['target_date']); ?></span>
                </td>
                <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top" contenteditable="true" data-name="long_term_stage[]"><?php echo htmlspecialchars($goal['stage']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top default-area" 
                  contenteditable="true" 
                  data-name="long_term_area[]"
                  data-default="Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development">
                Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development
              </td>
              <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top default-activity" 
                  contenteditable="true" 
                  data-name="long_term_activity[]"
                  data-default="Pursuance of Academic Degrees for advancement, conduct of trainings/seminars">
                Pursuance of Academic Degrees for advancement, conduct of trainings/seminars
              </td>
              <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top">
                <input type="date" name="long_term_date[]" class="w-full border-none bg-transparent focus:bg-blue-50 no-print">
                <span class="print-only"></span>
              </td>
              <td class="p-2 border border-gray-200 bg-white min-h-[80px] align-top" contenteditable="true" data-name="long_term_stage[]"></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
<!-- Short Term Goals -->
<div class="mb-10">
    <h4 class="font-bold mb-4 text-primary flex items-center no-print">
        <i class="fas fa-calendar-day mr-2"></i> Short Term Development Goals Next Year
    </h4>
    
    <div class="print-only development-title">Short Term Development Goals Next Year</div>
    
    <div class="overflow-visible">
        <table class="w-full rounded-lg development-table" id="short-term-goals">
            <thead>
                <tr>
                    <th class="w-1/6 p-2">Area of Development</th>
                    <th class="w-1/6 p-2">Priority for Learning and Development Program (LDP)</th>
                    <th class="w-1/6 p-2">Development Activity</th>
                    <th class="w-1/6 p-2">Target Completion Date</th>
                    <th class="w-1/6 p-2">Who is Responsible</th>
                    <th class="w-1/6 p-2">Completion Stage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $default_short_term_areas = [
                    "1. Behavioral Training such as: Value Re-orientation, Team Building, Oral Communication, Written Communication, Customer Relations, People Development, Improving Planning & Delivery, Solving Problems and making decisions, Basic Communication Training Program, etc",
                    "2. Technical Skills Training such as: Basic Occupational Safety & Health, University Safety procedures, Preventive Maintenance Activities, etc.",
                    "3. Quality Management Training such as: Customer Requirements, Time Management, Continuous Improvement for Quality & Productivity, etc",
                    "4. Others: Formal Classroom Training, on-the-job training, Self-development, developmental activities/interventions, etc."
                ];
                
                $default_activities = [
                    "Conduct of training/seminar",
                    "",
                    "",
                    "Coaching on the Job-knowledge sharing and learning session"
                ];
                
                // Display existing goals if editing
                if ($is_edit && !empty($form_data['form_data']['short_term_goals'])) {
                    foreach ($form_data['form_data']['short_term_goals'] as $index => $goal) {
                        echo '<tr class="h-auto">
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <div class="w-full h-full whitespace-normal">' . htmlspecialchars($goal['area']) . '</div>
                                <input type="hidden" name="short_term_area[]" value="' . htmlspecialchars($goal['area']) . '">
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_priority[]" class="w-full h-full min-h-[80px] border border-gray-100 rounded p-1 focus:bg-blue-50 no-print">' . htmlspecialchars($goal['priority']) . '</textarea>
                                <span class="print-only">' . htmlspecialchars($goal['priority']) . '</span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_activity[]" class="w-full h-full min-h-[80px] border border-gray-100 rounded p-1 focus:bg-blue-50 no-print">' . htmlspecialchars($goal['activity']) . '</textarea>
                                <span class="print-only">' . htmlspecialchars($goal['activity']) . '</span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <input type="date" name="short_term_date[]" class="w-full border border-gray-100 rounded p-1 focus:bg-blue-50 no-print h-[80px]" value="' . htmlspecialchars($goal['target_date']) . '">
                                <span class="print-only">' . htmlspecialchars($goal['target_date']) . '</span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_responsible[]" class="w-full h-full min-h-[80px] border border-gray-100 rounded p-1 focus:bg-blue-50 no-print resize-none" placeholder="Enter responsible person...">' . htmlspecialchars($goal['responsible']) . '</textarea>
                                <span class="print-only">' . htmlspecialchars($goal['responsible']) . '</span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_stage[]" class="w-full h-full min-h-[80px] border border-gray-100 rounded p-1 focus:bg-blue-50 no-print resize-none" placeholder="Enter completion stage...">' . htmlspecialchars($goal['stage']) . '</textarea>
                                <span class="print-only">' . htmlspecialchars($goal['stage']) . '</span>
                            </td>
                        </tr>';
                    }
                } 
                // Display default template if new form
                else {
                    foreach ($default_short_term_areas as $index => $area) {
                        $activity = $default_activities[$index] ?? '';
                        echo '<tr class="h-auto">
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <div class="w-full h-full whitespace-normal">' . htmlspecialchars($area) . '</div>
                                <input type="hidden" name="short_term_area[]" value="' . htmlspecialchars($area) . '">
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_priority[]" class="w-full h-full min-h-[80px] border border-gray-300 rounded p-1 focus:bg-blue-50 no-print resize-none" placeholder="Enter priority..."></textarea>
                                <span class="print-only"></span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_activity[]" class="w-full h-full min-h-[80px] border border-gray-300 rounded p-1 focus:bg-blue-50 no-print resize-none" placeholder="Enter activity...">' . htmlspecialchars($activity) . '</textarea>
                                <span class="print-only">' . htmlspecialchars($activity) . '</span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <input type="date" name="short_term_date[]" class="w-full border border-gray-300 rounded p-1 focus:bg-blue-50 no-print h-[80px]">
                                <span class="print-only"></span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_responsible[]" class="w-full h-full min-h-[80px] border border-gray-300 rounded p-1 focus:bg-blue-50 no-print resize-none" placeholder="Enter responsible person..."></textarea>
                                <span class="print-only"></span>
                            </td>
                            <td class="p-2 border border-gray-200 bg-white align-top">
                                <textarea name="short_term_stage[]" class="w-full h-full min-h-[80px] border border-gray-300 rounded p-1 focus:bg-blue-50 no-print resize-none" placeholder="Enter completion stage..."></textarea>
                                <span class="print-only"></span>
                            </td>
                        </tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
        <!-- Certification Section -->
        <div class="mb-10">
          <h3 class="text-xl font-bold mb-6 text-dark flex items-center no-print">
            <i class="fas fa-file-signature mr-2 text-primary"></i> CERTIFICATION AND COMMITMENT
          </h3>
          
          <div class="print-only section-title">CERTIFICATION AND COMMITMENT</div>
          
          <div class="bg-gray-50 p-6 rounded-lg mb-8 no-print">
            <p class="text-sm mb-4 text-gray-700">
              This is to certify that this Individual Development Plan has been discussed with me by my immediate superior. I further commit that I will exert time and effort to ensure that this will be achieved according to agreed time frames.
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-8">
              <!-- Employee Signature -->
              <div class="text-center">
                <div class="signature-line mb-2"></div>
                <p class="text-sm font-medium">Signature of Employee</p>
                <div class="mt-4">
                  <input type="text" name="employee_name" class="text-center border-none border-b border-gray-300 bg-transparent w-full" 
                         placeholder="Printed Name" value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_name']) : ''; ?>">
                </div>
                <div class="mt-2">
                  <input type="date" name="employee_date" class="text-center border-none border-b border-gray-300 bg-transparent w-full"
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_date']) : ''; ?>">
                </div>
              </div>
              
              <!-- Immediate Supervisor -->
              <div class="text-center">
                <div class="signature-line mb-2"></div>
                <p class="text-sm font-medium">Immediate Supervisor</p>
                <div class="mt-4">
                  <input type="text" name="supervisor_name" class="text-center border-none border-b border-gray-300 bg-transparent w-full" 
                         placeholder="Printed Name" value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_name']) : ''; ?>">
                </div>
                <div class="mt-2">
                  <input type="date" name="supervisor_date" class="text-center border-none border-b border-gray-300 bg-transparent w-full"
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_date']) : ''; ?>">
                </div>
              </div>
              
              <!-- Campus Director -->
              <div class="text-center">
                <div class="signature-line mb-2"></div>
                <p class="text-sm font-medium">Campus Director</p>
                <div class="mt-4">
                  <input type="text" name="director_name" class="text-center border-none border-b border-gray-300 bg-transparent w-full" 
                         placeholder="Printed Name" value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_name']) : ''; ?>">
                </div>
                <div class="mt-2">
                  <input type="date" name="director_date" class="text-center border-none border-b border-gray-300 bg-transparent w-full"
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_date']) : ''; ?>">
                </div>
              </div>
            </div>
            
            <div class="mt-8 text-center">
              <p class="text-sm text-gray-700 italic">
                I commit to support and ensure that this agreed Individual Development Plan is achieved to the agreed time frames
              </p>
            </div>
          </div>
          
          <!-- Print version of certification -->
          <div class="print-only">
            <div class="certification-text">
              This is to certify that this Individual Development Plan has been discussed with me by my immediate superior. I further commit that I will exert time and effort to ensure that this will be achieved according to agreed time frames.
            </div>
            
            <div class="signature-section">
              <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Signature of Employee</div>
                <div class="signature-name"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_name']) : ''; ?></div>
                <div class="signature-date"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_date']) : ''; ?></div>
              </div>
              
              <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Immediate Supervisor</div>
                <div class="signature-name"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_name']) : ''; ?></div>
                <div class="signature-date"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_date']) : ''; ?></div>
              </div>
              
              <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Campus Director</div>
                <div class="signature-name"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_name']) : ''; ?></div>
                <div class="signature-date"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_date']) : ''; ?></div>
              </div>
            </div>
            
            <div class="commitment-text">
              I commit to support and ensure that this agreed Individual Development Plan is achieved to the agreed time frames
            </div>
            
            <div class="form-code">
              LSPU-HRO-SF-027 Rev. 1 2 April 2018
            </div>
          </div>
          
          <!-- Form Footer -->
          <div class="flex flex-col md:flex-row justify-between items-center pt-6 border-t border-gray-200 mt-8 no-print">
            <div class="flex space-x-3 mb-4 md:mb-0">
              <button type="submit" name="save_draft" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg flex items-center">
                <i class="fas fa-save mr-2"></i> Save Draft
              </button>
              <button type="submit" name="submit_form" class="bg-success hover:bg-green-600 text-white px-6 py-2 rounded-lg flex items-center">
                <i class="fas fa-paper-plane mr-2"></i> Submit to HR
              </button>
            </div>
            <div class="text-sm text-gray-600 flex flex-col md:flex-row md:space-x-6">
              <span>LSPU-HRD-SF-027</span>
              <span>Rev. 1</span>
              <span>2 April 2018</span>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
// Dropdown functionality
const dropdownBtn = document.getElementById('idp-dropdown-btn');
const dropdownMenu = document.getElementById('idp-dropdown-menu');
const dropdownContainer = document.getElementById('idp-dropdown');

if (dropdownBtn && dropdownMenu && dropdownContainer) {
    // Check current page to determine active state
    const currentPage = window.location.pathname.split('/').pop();
    const isCreateNewPage = currentPage === 'Individual_Development_Plan.php';
    const isSubmittedFormsPage = currentPage === 'save_idp_forms.php';
    const isIDPFormsPage = isCreateNewPage || isSubmittedFormsPage;
    
    // Auto-open dropdown if on IDP forms pages and remember state
    const shouldOpenDropdown = isIDPFormsPage || localStorage.getItem('idpDropdownOpen') === 'true';
    
    if (shouldOpenDropdown) {
        dropdownContainer.classList.add('open');
        dropdownMenu.classList.remove('hidden');
    }

    // Highlight IDP Forms button if on any IDP forms page
    if (isIDPFormsPage) {
        dropdownBtn.classList.add('bg-blue-700');
    }

    // Highlight active link inside dropdown
    if (isCreateNewPage) {
        document.getElementById('create-new-link').classList.add('bg-blue-700');
    } else if (isSubmittedFormsPage) {
        document.getElementById('submitted-forms-link').classList.add('bg-blue-700');
    }

    dropdownBtn.addEventListener('click', function() {
        // Toggle dropdown state
        dropdownContainer.classList.toggle('open');
        dropdownMenu.classList.toggle('hidden');
        
        // Save the current state to localStorage
        const isNowOpen = dropdownMenu.classList.contains('hidden') === false;
        localStorage.setItem('idpDropdownOpen', isNowOpen.toString());
    });
}

// Highlight main navigation links
const currentPage = window.location.pathname.split('/').pop();
if (currentPage === 'user_page.php') {
    document.getElementById('tna-link').classList.add('bg-blue-700');
} else if (currentPage === 'profile.php') {
    document.getElementById('profile-link').classlist.add('bg-blue-700');
}
  
  // Print functionality - UPDATED FOR PDF GENERATION
  document.getElementById('print-btn')?.addEventListener('click', function() {
    // Collect all form data for PDF generation
    const formData = {
      // Personal Information
      name: document.getElementById('name')?.value || '',
      position: document.getElementById('position')?.value || '',
      salary_grade: document.getElementById('salary-grade')?.value || '',
      years_position: document.getElementById('years-position')?.value || '',
      years_lspu: document.getElementById('years-lspu')?.value || '',
      years_other: document.getElementById('years-other')?.value || '',
      division: document.getElementById('division')?.value || '',
      office: document.getElementById('office')?.value || '',
      address: document.getElementById('address')?.value || '',
      supervisor: document.getElementById('supervisor')?.value || '',
      
      // Purpose
      purpose1: document.getElementById('purpose1')?.checked || false,
      purpose2: document.getElementById('purpose2')?.checked || false,
      purpose3: document.getElementById('purpose3')?.checked || false,
      purpose4: document.getElementById('purpose4')?.checked || false,
      purpose5: document.getElementById('purpose5')?.checked || false,
      purpose_other: document.getElementById('purpose-other')?.value || '',
      
      // Long Term Goals
      long_term_area: Array.from(document.querySelectorAll('[data-name="long_term_area[]"]')).map(el => el.textContent.trim()),
      long_term_activity: Array.from(document.querySelectorAll('[data-name="long_term_activity[]"]')).map(el => el.textContent.trim()),
      long_term_date: Array.from(document.querySelectorAll('input[name="long_term_date[]"]')).map(el => el.value),
      long_term_stage: Array.from(document.querySelectorAll('[data-name="long_term_stage[]"]')).map(el => el.textContent.trim()),
      
      // Short Term Goals
      short_term_area: Array.from(document.querySelectorAll('input[name="short_term_area[]"]')).map(el => el.value),
      short_term_priority: Array.from(document.querySelectorAll('textarea[name="short_term_priority[]"]')).map(el => el.value),
      short_term_activity: Array.from(document.querySelectorAll('textarea[name="short_term_activity[]"]')).map(el => el.value),
      short_term_date: Array.from(document.querySelectorAll('input[name="short_term_date[]"]')).map(el => el.value),
      short_term_responsible: Array.from(document.querySelectorAll('textarea[name="short_term_responsible[]"]')).map(el => el.value),
      short_term_stage: Array.from(document.querySelectorAll('textarea[name="short_term_stage[]"]')).map(el => el.value),
      
      // Certification
      employee_name: document.querySelector('input[name="employee_name"]')?.value || '',
      employee_date: document.querySelector('input[name="employee_date"]')?.value || '',
      supervisor_name: document.querySelector('input[name="supervisor_name"]')?.value || '',
      supervisor_date: document.querySelector('input[name="supervisor_date"]')?.value || '',
      director_name: document.querySelector('input[name="director_name"]')?.value || '',
      director_date: document.querySelector('input[name="director_date"]')?.value || ''
    };
    
    // Create a form to submit data to PDF generator
    const pdfForm = document.createElement('form');
    pdfForm.method = 'POST';
    pdfForm.action = 'Individual_Development_Plan_pdf.php';
    pdfForm.target = '_blank';
    
    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'form_data';
    dataInput.value = JSON.stringify(formData);
    
    pdfForm.appendChild(dataInput);
    document.body.appendChild(pdfForm);
    pdfForm.submit();
    document.body.removeChild(pdfForm);
  });

  // Handle default text clearing for development areas
  document.querySelectorAll('.default-area').forEach(cell => {
    const defaultValue = cell.getAttribute('data-default');
    
    cell.addEventListener('focus', function() {
      if (this.textContent.trim() === defaultValue) {
        this.textContent = '';
      }
    });
    
    cell.addEventListener('blur', function() {
      if (this.textContent.trim() === '') {
        this.textContent = defaultValue;
      }
    });
  });

  // Handle default text for development activities
  document.querySelectorAll('.default-activity').forEach(cell => {
    const defaultValue = cell.hasAttribute('data-default') ? 
                         cell.getAttribute('data-default') : 
                         cell.textContent.trim();
    
    if (defaultValue) {
      cell.addEventListener('focus', function() {
        if (this.textContent.trim() === defaultValue) {
          this.textContent = '';
        }
      });
      
      cell.addEventListener('blur', function() {
        if (this.textContent.trim() === '') {
          this.textContent = defaultValue;
        }
      });
    }
  });

  // Collect contenteditable data before form submission
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function() {
      document.querySelectorAll('[contenteditable="true"]').forEach(el => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = el.getAttribute('data-name');
        input.value = el.textContent;
        form.appendChild(input);
      });
    });
  }

  // Prevent form resubmission on page refresh
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }

  // Add confirmation before leaving page with unsaved changes
  let hasUnsavedChanges = false;
  const formInputs = document.querySelectorAll('form input, [contenteditable="true"], form select, form textarea');
  
  formInputs.forEach(input => {
    if (input.hasAttribute('contenteditable')) {
      input.addEventListener('input', () => {
        hasUnsavedChanges = true;
      });
    } else {
      input.addEventListener('input', () => {
        hasUnsavedChanges = true;
      });
    }
  });

  window.addEventListener('beforeunload', (e) => {
    if (hasUnsavedChanges) {
      e.preventDefault();
      e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
      return e.returnValue;
    }
  });

  // Handle form submission to clear unsaved changes flag
  form?.addEventListener('submit', () => {
    hasUnsavedChanges = false;
  });

  // Show success message after form submission
  <?php if (isset($_SESSION['message'])): ?>
    Swal.fire({
      icon: '<?php echo $_SESSION['message']['type'] === 'success' ? 'success' : 'error'; ?>',
      title: '<?php echo $_SESSION['message']['type'] === 'success' ? 'Success!' : 'Error!'; ?>',
      text: '<?php echo addslashes($_SESSION['message']['text']); ?>',
      confirmButtonColor: '#2563eb',
    });
    <?php unset($_SESSION['message']); ?>
  <?php endif; ?>
});
</script>
</body>
</html>