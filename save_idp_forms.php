<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_id'])) {
        // Redirect to edit form
        $form_id = intval($_POST['edit_id']);
        header("Location: Individual Development Plan.php?id=" . $form_id);
        exit();
    } 
    elseif (isset($_POST['save_id'])) {
        // Save form updates - only if form is still a draft
        $form_id = intval($_POST['save_id']);
        
        // Check if form is still a draft
        $stmt = $con->prepare("SELECT status FROM idp_forms WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $form_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $form = $result->fetch_assoc();
        $stmt->close();
        
        if ($form['status'] !== 'draft') {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'Cannot edit a submitted form.'
            ];
            header("Location: save_idp_forms.php");
            exit();
        }
        
        // Collect all form data
        $form_input = [
            'personal_info' => [
                'name' => $_POST['name'] ?? '',
                'position' => $_POST['position'] ?? '',
                'salary_grade' => $_POST['salary_grade'] ?? '',
                'years_position' => $_POST['years_position'] ?? '',
                'years_lspu' => $_POST['years_lspu'] ?? '',
                'years_other' => $_POST['years_other'] ?? '',
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
                'purpose_other' => $_POST['purpose_other'] ?? ''
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

        // Process long term goals
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

        // Process short term goals
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
        
        $stmt = $con->prepare("UPDATE idp_forms SET form_data = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $json_data, $form_id, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'IDP form saved successfully!'
            ];
        } else {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'Error saving form: ' . $con->error
            ];
        }
        header("Location: save_idp_forms.php");
        exit();
    }
    elseif (isset($_POST['submit_id'])) {
        // Submit to admin
        $form_id = intval($_POST['submit_id']);
        
        // First get the form data to include in notification
        $stmt = $con->prepare("SELECT form_data FROM idp_forms WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $form_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $form = $result->fetch_assoc();
        $stmt->close();
        
        $form_data = json_decode($form['form_data'], true);
        $employee_name = $form_data['personal_info']['name'] ?? 'an employee';
        
        // Update status to submitted
        $stmt = $con->prepare("UPDATE idp_forms SET status = 'submitted', submitted_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $form_id, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'IDP form submitted to HR successfully!'
            ];
            
            // Create notification for admin
            $message = "New IDP form submitted by " . $employee_name;
            $stmt = $con->prepare("INSERT INTO notifications (user_id, message, related_id, type) 
                                  SELECT id, ?, ?, 'idp_form' FROM users WHERE role = 'admin'");
            $stmt->bind_param("si", $message, $form_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'Error submitting form: ' . $con->error
            ];
        }
        header("Location: save_idp_forms.php");
        exit();
    }
}

// Get all IDP forms for this user
$stmt = $con->prepare("SELECT id, form_data, status, created_at, submitted_at FROM idp_forms WHERE user_id = ? ORDER BY status ASC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$forms = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count drafts and submitted forms
$draft_count = 0;
$submitted_count = 0;
$submitted_forms = [];
foreach ($forms as $form) {
    if ($form['status'] === 'draft') {
        $draft_count++;
    } else {
        $submitted_count++;
        $submitted_forms[] = $form;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My IDP Forms</title>
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="css/save_idp_style.css">
<style>
    .sidebar {
        width: 280px;
        min-height: 100vh;
    }
    .main-content {
        margin-left: 280px;
        background-color: #f7fafc;
    }
    .form-card {
        transition: all 0.3s ease;
    }
    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .accordion-content.active {
        max-height: 5000px;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    .tab-button {
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        background-color: #e2e8f0;
        color: #4a5568;
        font-weight: 500;
    }
    .tab-button.active {
        background-color: #3b82f6;
        color: white;
    }
    .status-draft {
        background-color: #fef3c7;
        color: #92400e;
    }
    .status-submitted {
        background-color: #d1fae5;
        color: #065f46;
    }
    .modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .modal-backdrop.active {
        display: flex;
    }
    .modal-content {
        background-color: white;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }
    .readonly-field {
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        padding: 8px 12px;
        border-radius: 6px;
        color: #374151;
    }
    .checkbox-custom:disabled {
        background-color: #e5e7eb;
        border-color: #d1d5db;
    }
</style>
</head>
<body class="min-h-screen flex">
<!-- Sidebar -->
<aside class="w-64 bg-blue-900 text-white shadow-sm flex flex-col justify-between no-print">
  <div class="h-full flex flex-col">
    <!-- Logo & Title -->
    <div class="p-6 flex items-center">
      <img src="images/lspu-logo.png" alt="Logo" class="w-10 h-10 mr-3" />
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
          <button id="idp-dropdown-btn" class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-medium rounded-xl bg-blue-700 hover:bg-blue-700 transition-all">
            <div class="flex items-center">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-text-line"></i></div>
              IDP Forms
            </div>
            <i id="dropdown-arrow" class="ri-arrow-down-s-line transition-transform duration-300 group-[.open]:rotate-180"></i>
          </button>
          
          <div id="idp-dropdown-menu" class="hidden pl-8 mt-1 space-y-1 group-[.open]:block">
            <a href="Individual_Development_Plan.php" class="flex items-center px-4 py-2 text-sm rounded-md hover:bg-blue-700 transition-all" id="create-new-link">
              <div class="w-5 h-5 flex items-center justify-center mr-3"><i class="ri-file-add-line"></i></div>
              Create New
            </a>
            <a href="save_idp_forms.php" class="flex items-center px-4 py-2 text-sm rounded-xl hover:bg-blue-700 transition-all" id="submitted-forms-link">
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
<div class="flex-1 overflow-y-auto">
    <div class="container mx-auto px-4 py-8">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo $_SESSION['message']['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $_SESSION['message']['text']; ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">My IDP Forms</h1>
            <a href="Individual_Development_Plan.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="ri-file-add-line mr-2"></i> Create New IDP
            </a>
        </div>
        
        <!-- Tabs -->
        <div class="mb-6 flex space-x-2">
            <button class="tab-button active" data-tab="drafts">
                Drafts (<?php echo $draft_count; ?>)
            </button>
            <button class="tab-button" data-tab="submitted">
                Submitted (<?php echo $submitted_count; ?>)
            </button>
        </div>
        
        <!-- Drafts Tab -->
        <div class="tab-content active" id="drafts-tab">
            <?php if ($draft_count === 0): ?>
                <div class="bg-white p-6 rounded-lg shadow text-center">
                    <p class="text-gray-600">You don't have any draft IDP forms.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php 
                    $draft_index = 0;
                    foreach ($forms as $form): 
                        if ($form['status'] !== 'draft') continue;
                        $draft_index++;
                        $form_data = json_decode($form['form_data'], true);
                        $created_date = date('M d, Y', strtotime($form['created_at']));
                    ?>
                        <div class="form-card bg-white rounded-lg shadow overflow-hidden">
                            <button class="accordion-toggle w-full text-left p-6 focus:outline-none" data-id="<?php echo $form['id']; ?>">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="font-bold text-lg">IDP Form Draft #<?php echo $draft_index; ?></h3>
                                        <p class="text-gray-600">Created: <?php echo $created_date; ?></p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium status-draft">
                                            Draft
                                        </span>
                                        <i class="ri-arrow-down-s-line transition-transform duration-300"></i>
                                    </div>
                                </div>
                            </button>
                            
                            <div class="accordion-content px-6" id="content-<?php echo $form['id']; ?>">
                                <div class="border-t border-gray-200 pt-4 pb-6">
                                    <form method="POST" action="save_idp_forms.php" id="form-<?php echo $form['id']; ?>">
                                        <input type="hidden" name="save_id" value="<?php echo $form['id']; ?>">
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                            <div>
                                                <h4 class="font-semibold text-gray-800 mb-2">Personal Information</h4>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Name</label>
                                                    <input type="text" name="name" value="<?php echo htmlspecialchars($form_data['personal_info']['name']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Position</label>
                                                    <input type="text" name="position" value="<?php echo htmlspecialchars($form_data['personal_info']['position']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Salary Grade</label>
                                                    <input type="text" name="salary_grade" value="<?php echo htmlspecialchars($form_data['personal_info']['salary_grade']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Years in Position</label>
                                                    <input type="text" name="years_position" value="<?php echo htmlspecialchars($form_data['personal_info']['years_position']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Years in LSPU</label>
                                                    <input type="text" name="years_lspu" value="<?php echo htmlspecialchars($form_data['personal_info']['years_lspu']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Years in Other Office/Agency</label>
                                                    <input type="text" name="years_other" value="<?php echo htmlspecialchars($form_data['personal_info']['years_other']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Division</label>
                                                    <input type="text" name="division" value="<?php echo htmlspecialchars($form_data['personal_info']['division']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Office</label>
                                                    <input type="text" name="office" value="<?php echo htmlspecialchars($form_data['personal_info']['office']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Office Address</label>
                                                    <input type="text" name="address" value="<?php echo htmlspecialchars($form_data['personal_info']['address']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="block text-gray-600 mb-1">Supervisor's Name</label>
                                                    <input type="text" name="supervisor" value="<?php echo htmlspecialchars($form_data['personal_info']['supervisor']); ?>" 
                                                           class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-6">
                                            <h4 class="font-semibold text-gray-800 mb-2">Purpose</h4>
                                            <div class="space-y-2">
                                                <div class="flex items-center">
                                                    <input type="checkbox" id="purpose1-<?php echo $form['id']; ?>" name="purpose1" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose1'] ? 'checked' : ''; ?>>
                                                    <label for="purpose1-<?php echo $form['id']; ?>" class="text-gray-700">To meet the competencies in the current positions</label>
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="checkbox" id="purpose2-<?php echo $form['id']; ?>" name="purpose2" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose2'] ? 'checked' : ''; ?>>
                                                    <label for="purpose2-<?php echo $form['id']; ?>" class="text-gray-700">To increase the level of competencies of current positions</label>
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="checkbox" id="purpose3-<?php echo $form['id']; ?>" name="purpose3" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose3'] ? 'checked' : ''; ?>>
                                                    <label for="purpose3-<?php echo $form['id']; ?>" class="text-gray-700">To meet the competencies in the next higher position</label>
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="checkbox" id="purpose4-<?php echo $form['id']; ?>" name="purpose4" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose4'] ? 'checked' : ''; ?>>
                                                    <label for="purpose4-<?php echo $form['id']; ?>" class="text-gray-700">To acquire new competencies across different functions/position</label>
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="checkbox" id="purpose5-<?php echo $form['id']; ?>" name="purpose5" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose5'] ? 'checked' : ''; ?>>
                                                    <label for="purpose5-<?php echo $form['id']; ?>" class="text-gray-700">Others, please specify:</label>
                                                    <input type="text" name="purpose_other" value="<?php echo htmlspecialchars($form_data['purpose']['purpose_other']); ?>" class="ml-2 border-b border-gray-300 focus:border-blue-600 focus:outline-none flex-1">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Long Term Goals -->
                                        <div class="mb-6">
                                            <h4 class="font-semibold text-gray-800 mb-2">Training/Development Interventions for Long Term Goals (Next Five Years)</h4>
                                            <table class="w-full border border-gray-300">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="p-2 border border-gray-300">Area of Development</th>
                                                        <th class="p-2 border border-gray-300">Development Activity</th>
                                                        <th class="p-2 border border-gray-300">Target Completion Date</th>
                                                        <th class="p-2 border border-gray-300">Completion Stage</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="long-term-goals-<?php echo $form['id']; ?>">
                                                    <?php foreach ($form_data['long_term_goals'] as $index => $goal): ?>
                                                    <tr>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="long_term_area[]" value="<?php echo htmlspecialchars($goal['area']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="long_term_activity[]" value="<?php echo htmlspecialchars($goal['activity']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="date" name="long_term_date[]" value="<?php echo htmlspecialchars($goal['target_date']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="long_term_stage[]" value="<?php echo htmlspecialchars($goal['stage']); ?>" class="w-full border-none">
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <button type="button" class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center" onclick="addLongTermGoal(<?php echo $form['id']; ?>)">
                                                <i class="fas fa-plus-circle mr-1"></i> Add another row
                                            </button>
                                        </div>
                                        
                                        <!-- Short Term Goals -->
                                        <div class="mb-6">
                                            <h4 class="font-semibold text-gray-800 mb-2">Short Term Development Goals Next Year</h4>
                                            <table class="w-full border border-gray-300">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="p-2 border border-gray-300">Area of Development</th>
                                                        <th class="p-2 border border-gray-300">Priority for Learning and Development Program (LDP)</th>
                                                        <th class="p-2 border border-gray-300">Development Activity</th>
                                                        <th class="p-2 border border-gray-300">Target Completion Date</th>
                                                        <th class="p-2 border border-gray-300">Who is Responsible</th>
                                                        <th class="p-2 border border-gray-300">Completion Stage</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="short-term-goals-<?php echo $form['id']; ?>">
                                                    <?php foreach ($form_data['short_term_goals'] as $index => $goal): ?>
                                                    <tr>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="short_term_area[]" value="<?php echo htmlspecialchars($goal['area']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="short_term_priority[]" value="<?php echo htmlspecialchars($goal['priority']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="short_term_activity[]" value="<?php echo htmlspecialchars($goal['activity']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="date" name="short_term_date[]" value="<?php echo htmlspecialchars($goal['target_date']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="short_term_responsible[]" value="<?php echo htmlspecialchars($goal['responsible']); ?>" class="w-full border-none">
                                                        </td>
                                                        <td class="p-2 border border-gray-300">
                                                            <input type="text" name="short_term_stage[]" value="<?php echo htmlspecialchars($goal['stage']); ?>" class="w-full border-none">
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Certification -->
                                        <div class="mb-6">
                                            <h4 class="font-semibold text-gray-800 mb-2">Certification and Commitment</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="block text-gray-600 mb-1">Employee Name</label>
                                                    <input type="text" name="employee_name" value="<?php echo htmlspecialchars($form_data['certification']['employee_name']); ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-600 mb-1">Employee Date</label>
                                                    <input type="date" name="employee_date" value="<?php echo htmlspecialchars($form_data['certification']['employee_date']); ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-600 mb-1">Supervisor Name</label>
                                                    <input type="text" name="supervisor_name" value="<?php echo htmlspecialchars($form_data['certification']['supervisor_name']); ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-600 mb-1">Supervisor Date</label>
                                                    <input type="date" name="supervisor_date" value="<?php echo htmlspecialchars($form_data['certification']['supervisor_date']); ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-600 mb-1">Director Name</label>
                                                    <input type="text" name="director_name" value="<?php echo htmlspecialchars($form_data['certification']['director_name']); ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-600 mb-1">Director Date</label>
                                                    <input type="date" name="director_date" value="<?php echo htmlspecialchars($form_data['certification']['director_date']); ?>" class="w-full border border-gray-300 rounded px-3 py-2">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-end space-x-3 mt-6">
                                            <button type="submit" name="save" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                                                <i class="ri-save-line mr-2"></i> Save Changes
                                            </button>
                                            <button type="button" onclick="showSubmitModal(<?php echo $form['id']; ?>)" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                                                <i class="ri-send-plane-line mr-2"></i> Submit to HR
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Submitted Tab -->
        <div class="tab-content" id="submitted-tab">
            <?php if ($submitted_count === 0): ?>
                <div class="bg-white p-6 rounded-lg shadow text-center">
                    <p class="text-gray-600">You haven't submitted any IDP forms yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php 
                    $submitted_index = 0;
                    foreach ($submitted_forms as $form): 
                        $submitted_index++;
                        $form_data = json_decode($form['form_data'], true);
                        $created_date = date('M d, Y', strtotime($form['created_at']));
                        $submitted_date = date('M d, Y', strtotime($form['submitted_at']));
                    ?>
                        <div class="form-card bg-white rounded-lg shadow overflow-hidden">
                            <button class="accordion-toggle w-full text-left p-6 focus:outline-none" data-id="<?php echo $form['id']; ?>">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="font-bold text-lg">IDP Form #<?php echo $submitted_index; ?></h3>
                                        <p class="text-gray-600">Submitted: <?php echo $submitted_date; ?></p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium status-submitted">
                                            Submitted
                                        </span>
                                        <i class="ri-arrow-down-s-line transition-transform duration-300"></i>
                                    </div>
                                </div>
                            </button>
                            
                            <div class="accordion-content px-6" id="content-<?php echo $form['id']; ?>">
                                <div class="border-t border-gray-200 pt-4 pb-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                        <div>
                                            <h4 class="font-semibold text-gray-800 mb-2">Personal Information</h4>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Name</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['name']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Position</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['position']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Salary Grade</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['salary_grade']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Years in Position</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['years_position']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Years in LSPU</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['years_lspu']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Years in Other Office/Agency</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['years_other']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Division</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['division']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Office</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['office']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Office Address</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['address']); ?></p>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-gray-600 mb-1">Supervisor's Name</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['personal_info']['supervisor']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-6">
                                        <h4 class="font-semibold text-gray-800 mb-2">Purpose</h4>
                                        <div class="space-y-2">
                                            <div class="flex items-center">
                                                <input type="checkbox" id="purpose1-<?php echo $form['id']; ?>" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose1'] ? 'checked' : ''; ?> disabled>
                                                <label for="purpose1-<?php echo $form['id']; ?>" class="text-gray-700">To meet the competencies in the current positions</label>
                                            </div>
                                            <div class="flex items-center">
                                                <input type="checkbox" id="purpose2-<?php echo $form['id']; ?>" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose2'] ? 'checked' : ''; ?> disabled>
                                                <label for="purpose2-<?php echo $form['id']; ?>" class="text-gray-700">To increase the level of competencies of current positions</label>
                                            </div>
                                            <div class="flex items-center">
                                                <input type="checkbox" id="purpose3-<?php echo $form['id']; ?>" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose3'] ? 'checked' : ''; ?> disabled>
                                                <label for="purpose3-<?php echo $form['id']; ?>" class="text-gray-700">To meet the competencies in the next higher position</label>
                                            </div>
                                            <div class="flex items-center">
                                                <input type="checkbox" id="purpose4-<?php echo $form['id']; ?>" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose4'] ? 'checked' : ''; ?> disabled>
                                                <label for="purpose4-<?php echo $form['id']; ?>" class="text-gray-700">To acquire new competencies across different functions/position</label>
                                            </div>
                                            <div class="flex items-center">
                                                <input type="checkbox" id="purpose5-<?php echo $form['id']; ?>" class="checkbox-custom mr-2" <?php echo $form_data['purpose']['purpose5'] ? 'checked' : ''; ?> disabled>
                                                <label for="purpose5-<?php echo $form['id']; ?>" class="text-gray-700">Others, please specify:</label>
                                                <span class="ml-2 text-gray-800"><?php echo htmlspecialchars($form_data['purpose']['purpose_other']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Long Term Goals -->
                                    <div class="mb-6">
                                        <h4 class="font-semibold text-gray-800 mb-2">Training/Development Interventions for Long Term Goals (Next Five Years)</h4>
                                        <table class="w-full border border-gray-300">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="p-2 border border-gray-300">Area of Development</th>
                                                    <th class="p-2 border border-gray-300">Development Activity</th>
                                                    <th class="p-2 border border-gray-300">Target Completion Date</th>
                                                    <th class="p-2 border border-gray-300">Completion Stage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($form_data['long_term_goals'] as $goal): ?>
                                                <tr>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['area']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['activity']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['target_date']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['stage']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Short Term Goals -->
                                    <div class="mb-6">
                                        <h4 class="font-semibold text-gray-800 mb-2">Short Term Development Goals Next Year</h4>
                                        <table class="w-full border border-gray-300">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="p-2 border border-gray-300">Area of Development</th>
                                                    <th class="p-2 border border-gray-300">Priority for Learning and Development Program (LDP)</th>
                                                    <th class="p-2 border border-gray-300">Development Activity</th>
                                                    <th class="p-2 border border-gray-300">Target Completion Date</th>
                                                    <th class="p-2 border border-gray-300">Who is Responsible</th>
                                                    <th class="p-2 border border-gray-300">Completion Stage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($form_data['short_term_goals'] as $goal): ?>
                                                <tr>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['area']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['priority']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['activity']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['target_date']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['responsible']); ?></td>
                                                    <td class="p-2 border border-gray-300"><?php echo htmlspecialchars($goal['stage']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Certification -->
                                    <div class="mb-6">
                                        <h4 class="font-semibold text-gray-800 mb-2">Certification and Commitment</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-gray-600 mb-1">Employee Name</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['certification']['employee_name']); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-gray-600 mb-1">Employee Date</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['certification']['employee_date']); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-gray-600 mb-1">Supervisor Name</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['certification']['supervisor_name']); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-gray-600 mb-1">Supervisor Date</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['certification']['supervisor_date']); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-gray-600 mb-1">Director Name</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['certification']['director_name']); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-gray-600 mb-1">Director Date</label>
                                                <p class="readonly-field"><?php echo htmlspecialchars($form_data['certification']['director_date']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Submit Confirmation Modal -->
<div class="modal-backdrop" id="submit-modal">
    <div class="modal-content">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Confirm Submission</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to submit this IDP form to HR? Once submitted, you won't be able to make further changes.</p>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideSubmitModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                    Cancel
                </button>
                <form method="POST" action="save_idp_forms.php" id="submit-form">
                    <input type="hidden" name="submit_id" id="submit-id-input">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Confirm Submit
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Accordion functionality
        const accordionToggles = document.querySelectorAll('.accordion-toggle');
        
        accordionToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const formId = this.getAttribute('data-id');
                const content = document.getElementById(`content-${formId}`);
                const arrow = this.querySelector('i');
                
                if (content.classList.contains('active')) {
                    content.classList.remove('active');
                    arrow.classList.remove('transform', 'rotate-180');
                } else {
                    content.classList.add('active');
                    arrow.classList.add('transform', 'rotate-180');
                }
            });
        });
        
        // Tab functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab button
                tabButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });

        // ========== UPDATED DROPDOWN FUNCTIONALITY ==========
        const dropdownBtn = document.getElementById('idp-dropdown-btn');
        const dropdownMenu = document.getElementById('idp-dropdown-menu');
        const dropdownContainer = document.getElementById('idp-dropdown');
        const dropdownArrow = document.getElementById('dropdown-arrow');

        if (dropdownBtn && dropdownMenu && dropdownContainer) {
            // Check current page to determine active state
            const currentPage = window.location.pathname.split('/').pop();
            const isCreateNewPage = currentPage === 'Individual_Development_Plan.php';
            const isSubmittedFormsPage = currentPage === 'save_idp_forms.php';
            
            // Auto-open dropdown if on IDP forms pages and remember state
            const shouldOpenDropdown = isCreateNewPage || isSubmittedFormsPage || localStorage.getItem('idpDropdownOpen') === 'true';
            
            if (shouldOpenDropdown) {
                dropdownContainer.classList.add('open');
                dropdownMenu.classList.remove('hidden');
                if (dropdownArrow) {
                    dropdownArrow.classList.add('rotate-180');
                }
            }

            // Highlight active links
            if (isCreateNewPage) {
                const createNewLink = document.getElementById('create-new-link');
                if (createNewLink) createNewLink.classList.add('bg-blue-700');
            } else if (isSubmittedFormsPage) {
                const submittedFormsLink = document.getElementById('submitted-forms-link');
                if (submittedFormsLink) submittedFormsLink.classList.add('bg-blue-700');
            }

            dropdownBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle dropdown state
                dropdownContainer.classList.toggle('open');
                dropdownMenu.classList.toggle('hidden');
                
                // Rotate arrow
                if (dropdownArrow) {
                    dropdownArrow.classList.toggle('rotate-180');
                }
                
                // Save the current state to localStorage
                const isNowOpen = dropdownMenu.classList.contains('hidden') === false;
                localStorage.setItem('idpDropdownOpen', isNowOpen.toString());
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdownContainer.contains(e.target)) {
                    dropdownContainer.classList.remove('open');
                    dropdownMenu.classList.add('hidden');
                    if (dropdownArrow) {
                        dropdownArrow.classList.remove('rotate-180');
                    }
                    localStorage.setItem('idpDropdownOpen', 'false');
                }
            });

            // Close dropdown when clicking on dropdown links
            const dropdownLinks = dropdownMenu.querySelectorAll('a');
            dropdownLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Don't close dropdown immediately, let the page navigation handle it
                    // The state will be handled on page load
                });
            });
        }

        // Highlight main navigation links
        const currentPage = window.location.pathname.split('/').pop();
        if (currentPage === 'user_page.php') {
            const tnaLink = document.getElementById('tna-link');
            if (tnaLink) tnaLink.classList.add('bg-blue-700');
        } else if (currentPage === 'profile.php') {
            const profileLink = document.getElementById('profile-link');
            if (profileLink) profileLink.classList.add('bg-blue-700');
        }
        // ========== END OF UPDATED DROPDOWN FUNCTIONALITY ==========
    });
    
    // Modal functions
    function showSubmitModal(formId) {
        const submitIdInput = document.getElementById('submit-id-input');
        const modal = document.getElementById('submit-modal');
        
        if (submitIdInput && modal) {
            submitIdInput.value = formId;
            modal.classList.add('active');
        }
    }
    
    function hideSubmitModal() {
        const modal = document.getElementById('submit-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }
    
    function addLongTermGoal(formId) {
        const tbody = document.getElementById(`long-term-goals-${formId}`);
        if (tbody) {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td class="p-2 border border-gray-300">
                    <input type="text" name="long_term_area[]" class="w-full border-none">
                </td>
                <td class="p-2 border border-gray-300">
                    <input type="text" name="long_term_activity[]" class="w-full border-none">
                </td>
                <td class="p-2 border border-gray-300">
                    <input type="date" name="long_term_date[]" class="w-full border-none">
                </td>
                <td class="p-2 border border-gray-300">
                    <input type="text" name="long_term_stage[]" class="w-full border-none">
                </td>
            `;
            tbody.appendChild(newRow);
        }
    }
</script>
</body>
</html>