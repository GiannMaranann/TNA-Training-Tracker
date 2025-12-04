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

// Set upload directory path
$upload_dir = 'uploads/profile_images/';

// Create uploads directory if not exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get user information from database - INCLUDING PROFILE IMAGE
$user_info = [];
$stmt = $con->prepare("SELECT name, yearsInLSPU, profile_image, designation FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_info = $result->fetch_assoc();
}
$stmt->close();

// Assign user info for sidebar
$profile_name = $user_info['name'] ?? '';
$profile_image = $user_info['profile_image'] ?? '';
$designation = $user_info['designation'] ?? 'Staff';

// Split name for display
$name_parts = explode(' ', $profile_name, 2);
$first_name = $name_parts[0] ?? '';
$last_name = $name_parts[1] ?? '';

// Get profile image path
$defaultImage = 'images/noprofile.jpg';
$imageSrc = $defaultImage;

if (!empty($profile_image)) {
    $full_path = $upload_dir . $profile_image;
    if (file_exists($full_path)) {
        $imageSrc = $full_path;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

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
    // Handle signature image uploads
    function saveSignatureImage($base64Data, $type, $form_id) {
        if (empty($base64Data) || strpos($base64Data, 'data:image') === false) {
            return '';
        }
        
        $upload_dir = 'uploads/signatures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate filename
        $filename = $type . '_' . $form_id . '_' . time() . '.png';
        $filepath = $upload_dir . $filename;
        
        // Remove data URL prefix
        $base64Data = str_replace('data:image/png;base64,', '', $base64Data);
        $base64Data = str_replace(' ', '+', $base64Data);
        
        // Decode base64 and save file
        $imageData = base64_decode($base64Data);
        if ($imageData !== false) {
            file_put_contents($filepath, $imageData);
            return $filepath;
        }
        
        return '';
    }
    
    // Begin transaction
    $con->begin_transaction();
    
    try {
        // Process uploaded signatures
        $employee_signature_path = '';
        $supervisor_signature_path = '';
        $director_signature_path = '';
        
        if ($form_id > 0) {
            if (!empty($_POST['employee_signature_data'])) {
                $employee_signature_path = saveSignatureImage($_POST['employee_signature_data'], 'employee', $form_id);
            }
            if (!empty($_POST['supervisor_signature_data'])) {
                $supervisor_signature_path = saveSignatureImage($_POST['supervisor_signature_data'], 'supervisor', $form_id);
            }
            if (!empty($_POST['director_signature_data'])) {
                $director_signature_path = saveSignatureImage($_POST['director_signature_data'], 'director', $form_id);
            }
        }
        
        // Prepare form data for storage
        $form_input = [
            'personal_info' => [
                'name' => $_POST['name'] ?? $user_info['name'],
                'position' => $_POST['position'] ?? '',
                'salary_grade' => $_POST['salary_grade'] ?? '',
                'years_position' => $_POST['years_position'] ?? '',
                'years_lspu' => $_POST['years_lspu'] ?? $user_info['yearsInLSPU'],
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
                'employee_signature' => $employee_signature_path ?: ($_POST['employee_signature'] ?? ''),
                'supervisor_name' => $_POST['supervisor_name'] ?? '',
                'supervisor_date' => $_POST['supervisor_date'] ?? '',
                'supervisor_signature' => $supervisor_signature_path ?: ($_POST['supervisor_signature'] ?? ''),
                'director_name' => $_POST['director_name'] ?? '',
                'director_date' => $_POST['director_date'] ?? '',
                'director_signature' => $director_signature_path ?: ($_POST['director_signature'] ?? '')
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

        $json_data = json_encode($form_input);
        $status = isset($_POST['submit_form']) ? 'submitted' : 'draft';
        $submitted_at = $status === 'submitted' ? date('Y-m-d H:i:s') : null;

        // Save to idp_forms table
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

        // Save to idp_personal_info table
        $stmt = $con->prepare("REPLACE INTO idp_personal_info (form_id, name, position, salary_grade, years_position, years_lspu, years_other, division, office, address, supervisor) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssssss", 
            $form_id,
            $form_input['personal_info']['name'],
            $form_input['personal_info']['position'],
            $form_input['personal_info']['salary_grade'],
            $form_input['personal_info']['years_position'],
            $form_input['personal_info']['years_lspu'],
            $form_input['personal_info']['years_other'],
            $form_input['personal_info']['division'],
            $form_input['personal_info']['office'],
            $form_input['personal_info']['address'],
            $form_input['personal_info']['supervisor']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save personal info: " . $stmt->error);
        }
        $stmt->close();

        // Save to idp_purpose table
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

        // Save to idp_certification table
        $stmt = $con->prepare("REPLACE INTO idp_certification (form_id, employee_name, employee_date, employee_signature, supervisor_name, supervisor_date, supervisor_signature, director_name, director_date, director_signature) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssss", 
            $form_id,
            $form_input['certification']['employee_name'],
            $form_input['certification']['employee_date'],
            $form_input['certification']['employee_signature'],
            $form_input['certification']['supervisor_name'],
            $form_input['certification']['supervisor_date'],
            $form_input['certification']['supervisor_signature'],
            $form_input['certification']['director_name'],
            $form_input['certification']['director_date'],
            $form_input['certification']['director_signature']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save certification data: " . $stmt->error);
        }
        $stmt->close();

        // Save long term goals
        $stmt = $con->prepare("DELETE FROM idp_long_term_goals WHERE form_id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $stmt->close();
        
        foreach ($form_input['long_term_goals'] as $goal) {
            $stmt = $con->prepare("INSERT INTO idp_long_term_goals (form_id, area, activity, target_date, stage) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $form_id, $goal['area'], $goal['activity'], $goal['target_date'], $goal['stage']);
            $stmt->execute();
            $stmt->close();
        }

        // Save short term goals
        $stmt = $con->prepare("DELETE FROM idp_short_term_goals WHERE form_id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $stmt->close();
        
        foreach ($form_input['short_term_goals'] as $goal) {
            $stmt = $con->prepare("INSERT INTO idp_short_term_goals (form_id, area, priority, activity, target_date, responsible, stage) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $form_id, $goal['area'], $goal['priority'], $goal['activity'], $goal['target_date'], $goal['responsible'], $goal['stage']);
            $stmt->execute();
            $stmt->close();
        }

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
<link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
        sidebar: '#1e40af',
        light: '#f8fafc',
        dark: '#1e293b'
      },
      borderRadius: {
        'xl': '1rem',
        '2xl': '1.5rem',
      },
      fontFamily: {
        'poppins': ['Poppins', 'sans-serif'],
      },
      boxShadow: {
        'custom': '0 4px 20px rgba(0, 0, 0, 0.08)',
        'custom-hover': '0 8px 30px rgba(0, 0, 0, 0.12)',
        'soft': '0 4px 20px rgba(0, 0, 0, 0.08)',
        'focus': '0 0 0 3px rgba(30, 64, 175, 0.2)',
        'card': '0 2px 8px rgba(0, 0, 0, 0.1)'
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
      }
    }
  }
}
</script>
<style>
* {
  font-family: 'Poppins', sans-serif;
}

body {
  background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
  margin: 0;
  padding: 0;
}

/* Fixed Sidebar Styles */
.fixed-sidebar {
  position: fixed;
  left: 0;
  top: 0;
  height: 100vh;
  width: 16rem;
  background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
  overflow-y: auto;
  z-index: 50;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.main-content-wrapper {
  margin-left: 16rem;
  min-height: 100vh;
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

.form-gradient {
  background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
}

.hover-lift {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.hover-lift:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.floating-label {
  position: relative;
  margin-bottom: 1.5rem;
}

.floating-label input,
.floating-label textarea {
  width: 100%;
  padding: 1rem 1rem 0.5rem 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.75rem;
  font-size: 1rem;
  transition: all 0.3s ease;
  background: white;
}

.floating-label input:focus,
.floating-label textarea:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  outline: none;
}

.floating-label label {
  position: absolute;
  top: 50%;
  left: 1rem;
  transform: translateY(-50%);
  background: white;
  padding: 0 0.5rem;
  color: #6b7280;
  font-size: 0.875rem;
  transition: all 0.3s ease;
  pointer-events: none;
}

.floating-label input:focus + label,
.floating-label input:not(:placeholder-shown) + label,
.floating-label textarea:focus + label,
.floating-label textarea:not(:placeholder-shown) + label {
  top: 0.25rem;
  transform: translateY(0);
  font-size: 0.75rem;
  color: #3b82f6;
}

.floating-label textarea {
  min-height: 100px;
  resize: vertical;
}

.checkbox-custom {
  width: 1.25rem;
  height: 1.25rem;
  border: 2px solid #d1d5db;
  border-radius: 0.375rem;
  appearance: none;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.checkbox-custom:checked {
  background-color: #3b82f6;
  border-color: #3b82f6;
}

.checkbox-custom:checked::after {
  content: '✓';
  position: absolute;
  color: white;
  font-size: 0.875rem;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.development-table {
  border-collapse: separate;
  border-spacing: 0;
  border-radius: 0.75rem;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  width: 100%;
  table-layout: fixed;
}

.development-table th {
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  color: white;
  padding: 1rem;
  font-weight: 600;
  text-align: left;
  vertical-align: top;
  position: sticky;
  top: 0;
}

.development-table td {
  padding: 0.5rem;
  border: 1px solid #e5e7eb;
  background: white;
  vertical-align: top;
  word-wrap: break-word;
}

.development-table tr:hover td {
  background-color: #f8fafc;
}

.development-table .editable-cell {
  min-height: 80px;
  padding: 0.5rem;
  cursor: text;
  outline: none;
  transition: all 0.3s ease;
  width: 100%;
  display: block;
}

.development-table .editable-cell:focus {
  background-color: #f0f9ff;
  border-radius: 0.5rem;
}

.development-table .editable-cell.default-text {
  color: #6b7280;
  font-style: italic;
}

.development-table .editable-cell.placeholder {
  color: #9ca3af;
}

.development-table textarea {
  width: 100%;
  min-height: 80px;
  resize: none;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0.5rem;
  font-family: 'Poppins', sans-serif;
  font-size: 0.875rem;
  transition: all 0.3s ease;
}

.development-table textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.development-table textarea.auto-expand {
  overflow: hidden;
  min-height: 80px;
  max-height: 300px;
}

.signature-line {
  height: 2px;
  background: #6b7280;
  margin: 0.5rem auto;
  width: 80%;
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

.group.open .ri-arrow-down-s-line {
  transform: rotate(180deg);
}

/* Print styles */
@media print {
  .no-print {
    display: none !important;
  }
  
  .print-only {
    display: block !important;
  }
  
  .fixed-sidebar {
    display: none !important;
  }
  
  .main-content-wrapper {
    margin-left: 0 !important;
  }
  
  body {
    background: white !important;
  }
  
  .form-container {
    padding: 0 !important;
    box-shadow: none !important;
  }
  
  table {
    page-break-inside: avoid;
  }
  
  .signature-section {
    page-break-inside: avoid;
  }
}

.print-only {
  display: none;
}

.section-highlight {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(30, 64, 175, 0.05) 100%);
  border-left: 4px solid #3b82f6;
  padding: 1.5rem;
  border-radius: 0 0.75rem 0.75rem 0;
}

.btn-gradient-primary {
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}

.btn-gradient-primary:hover {
  background: linear-gradient(135deg, #2563eb 0%, #1e3a8a 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
}

.btn-gradient-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.btn-gradient-success:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
}

.required-field::after {
  content: '*';
  color: #ef4444;
  margin-left: 4px;
}

.help-text {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.form-section {
  animation: fadeIn 0.5s ease-out;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-draft {
  background-color: #dbeafe;
  color: #1e40af;
}

.badge-submitted {
  background-color: #d1fae5;
  color: #065f46;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 6px;
}

.dot-draft {
  background-color: #3b82f6;
}

.dot-submitted {
  background-color: #10b981;
}

/* Signature Upload Styles */
.signature-upload-container {
  border: 2px dashed #e5e7eb;
  border-radius: 0.75rem;
  background: white;
  padding: 1rem;
  margin-bottom: 1rem;
  text-align: center;
  transition: all 0.3s ease;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.signature-upload-container:hover {
  border-color: #3b82f6;
  background-color: #f8fafc;
}

.signature-upload-container.dragover {
  border-color: #3b82f6;
  background-color: #f0f9ff;
}

.signature-upload-btn {
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  border: none;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.signature-upload-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.signature-preview {
  margin-top: 1rem;
  max-width: 100%;
  max-height: 150px;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0.5rem;
  background: white;
}

.signature-preview img {
  max-width: 100%;
  max-height: 100px;
  display: block;
  margin: 0 auto;
}

/* Column Widths for Development Tables */
.area-col {
  width: 25%;
}

.priority-col {
  width: 15%;
}

.activity-col {
  width: 25%;
}

.date-col {
  width: 12%;
}

.responsible-col {
  width: 12%;
}

.stage-col {
  width: 11%;
}

.long-term-area-col {
  width: 25%;
}

.long-term-activity-col {
  width: 35%;
}

.long-term-date-col {
  width: 15%;
}

.long-term-stage-col {
  width: 25%;
}

/* Editable table cell styles */
.table-cell-content {
  min-height: 60px;
  padding: 0.5rem;
  cursor: text;
  outline: none;
  transition: all 0.3s ease;
  position: relative;
}

.table-cell-content:focus {
  background-color: #f0f9ff;
  border-radius: 0.25rem;
}

.table-cell-content.placeholder {
  color: #9ca3af;
  font-style: italic;
}

.table-cell-content.default-text {
  color: #6b7280;
  font-style: italic;
}

/* Signature Canvas Styles */
.signature-canvas {
  width: 100%;
  height: 150px;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: white;
  cursor: crosshair;
}

.signature-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.signature-display {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px dashed #e5e7eb;
  border-radius: 0.5rem;
  background: #f9fafb;
  min-height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.signature-display img {
  max-width: 100%;
  max-height: 100px;
}

.hidden-input {
  display: none;
}
</style>
</head>
<body class="min-h-screen font-poppins">
<!-- Fixed Sidebar (won't close when clicking inside) -->
<div class="fixed-sidebar text-white">
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
        <i class="ri-arrow-right-s-line ml-auto"></i>
      </a>
      
      <!-- IDP Forms Dropdown (Always Open) -->
      <div class="group open" id="idp-dropdown">
        <button id="idp-dropdown-btn" class="nav-item active flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg bg-blue-700/50 transition-all">
          <div class="flex items-center">
            <div class="w-6 h-6 flex items-center justify-center mr-3">
              <i class="ri-file-text-line text-lg"></i>
            </div>
            IDP Forms
          </div>
          <i class="ri-arrow-down-s-line transition-transform duration-300 rotate-180"></i>
        </button>
        
        <div id="idp-dropdown-menu" class="pl-10 mt-1 space-y-1 block">
          <a href="Individual_Development_Plan.php" class="nav-item active flex items-center px-4 py-2.5 text-sm rounded-lg bg-blue-700/30 transition-all">
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
      </a>
    </nav>

    <!-- User Info & Logout -->
    <div class="p-4 border-t border-blue-800/30 mt-auto">
      <div class="flex items-center justify-between">
        <!-- User Info -->
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
            <p class="text-sm font-medium text-white truncate max-w-[120px]">
              <?= htmlspecialchars($first_name . ' ' . $last_name) ?>
            </p>
            <p class="text-xs text-blue-300 truncate max-w-[120px]">
              <?= htmlspecialchars($desig ?: 'Staff') ?>
            </p>
          </div>
        </div>
        
        <!-- Logout Button -->
        <a href="homepage.php" class="p-2 rounded-lg hover:bg-red-600/20 text-red-100 border border-red-500/20 transition-all flex items-center justify-center">
          <i class="ri-logout-box-line"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="main-content-wrapper">
  <div class="container mx-auto px-8 py-8">
    <?php if (isset($_SESSION['message'])): ?>
      <div class="mb-6 p-4 rounded-xl animate-fade-in <?php echo $_SESSION['message']['type'] === 'success' ? 'bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-800' : 'bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 text-red-800'; ?> no-print">
        <div class="flex items-center">
          <i class="ri-<?php echo $_SESSION['message']['type'] === 'success' ? 'checkbox-circle-fill' : 'error-warning-fill'; ?> mr-3 text-xl"></i>
          <div>
            <p class="font-medium"><?php echo $_SESSION['message']['text']; ?></p>
          </div>
        </div>
      </div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <!-- Print Header (only visible when printing) -->
    <div class="print-only">
      <div class="text-center mb-8">
        <div class="text-sm">Republic of the Philippines</div>
        <div class="text-xl font-bold">Laguna State Polytechnic University</div>
        <div class="text-sm">Province of Laguna</div>
        <div class="text-2xl font-bold mt-4">INDIVIDUAL DEVELOPMENT PLAN</div>
      </div>
    </div>
    
    <!-- Current Form -->
    <form method="POST" action="" class="form-gradient rounded-2xl shadow-custom overflow-hidden hover:shadow-custom-hover transition-all duration-300 hover-lift" id="idp-form" enctype="multipart/form-data">
      <input type="hidden" name="form_id" value="<?php echo $form_id; ?>">
      <input type="hidden" id="employee_signature_data" name="employee_signature_data" value="">
      <input type="hidden" id="supervisor_signature_data" name="supervisor_signature_data" value="">
      <input type="hidden" id="director_signature_data" name="director_signature_data" value="">
      
      <div class="p-8 md:p-12 form-container">
        <!-- Header Section -->
        <div class="text-center mb-12 no-print">
          <div class="flex flex-col items-center mb-6">
            <div class="w-20 h-20 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center mb-4 shadow-lg">
              <i class="ri-file-text-line text-white text-3xl"></i>
            </div>
            <div>
              <h1 class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">INDIVIDUAL DEVELOPMENT PLAN</h1>
              <p class="text-gray-600 mt-2">Employee Growth and Competency Roadmap</p>
            </div>
          </div>
          <div class="w-32 h-1.5 bg-gradient-to-r from-blue-500 via-blue-600 to-blue-700 mx-auto rounded-full"></div>
        </div>
        
        <!-- Form Actions -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4 no-print">
          <div class="flex items-center">
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-3 mr-4">
              <i class="ri-file-list-3-line text-blue-600 text-2xl"></i>
            </div>
            <div>
              <span class="text-sm font-medium text-gray-600">Form Status:</span>
              <span class="ml-2 badge <?php echo ($is_edit && $form_data['status'] === 'submitted') ? 'badge-submitted' : 'badge-draft'; ?>">
                <span class="status-dot <?php echo ($is_edit && $form_data['status'] === 'submitted') ? 'dot-submitted' : 'dot-draft'; ?>"></span>
                <?php echo ($is_edit && $form_data['status'] === 'submitted') ? 'Submitted' : ($is_edit ? 'Draft' : 'New Form'); ?>
              </span>
              <?php if ($is_edit): ?>
                <p class="text-xs text-gray-500 mt-1">Last updated: <?php echo date('M j, Y g:i A', strtotime($form_data['updated_at'])); ?></p>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="flex space-x-4">
            <button type="button" id="print-btn" class="btn-gradient-primary text-white px-6 py-3 rounded-xl font-medium flex items-center shadow-lg hover:shadow-xl">
              <i class="ri-printer-line mr-3"></i> Generate PDF
            </button>
            <button type="button" id="help-btn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-medium flex items-center">
              <i class="ri-question-line mr-3"></i> Help
            </button>
          </div>
        </div>
        
        <!-- Personal Information Section -->
        <div class="mb-12 form-section">
          <div class="section-highlight mb-6 no-print">
            <h3 class="text-2xl font-bold text-gray-800 flex items-center">
              <i class="ri-user-settings-line mr-3 text-blue-600"></i> Personal Information
            </h3>
            <p class="text-gray-600 mt-2">Complete your personal and employment details</p>
          </div>
          
          <div class="print-only">
            <h3 class="text-xl font-bold mb-4">PERSONAL INFORMATION</h3>
          </div>
          
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 no-print">
            <!-- Column 1 -->
            <div class="space-y-6">
              <div class="floating-label">
                <input type="text" id="name" name="name" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl" 
                       value="<?php 
                       echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['name']) : 
                                      (isset($user_info['name']) ? htmlspecialchars($user_info['name']) : ''); 
                       ?>" <?php echo isset($user_info['name']) ? 'readonly' : ''; ?>>
                <label for="name" class="required-field">Name</label>
                <span class="help-text">Your full name as registered in the system</span>
              </div>
              
              <div class="floating-label">
                <input type="text" id="position" name="position" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['position']) : ''; ?>">
                <label for="position" class="required-field">Current Position</label>
                <span class="help-text">Your current job title</span>
              </div>
              
              <div class="floating-label">
                <input type="text" id="salary-grade" name="salary_grade" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['salary_grade']) : ''; ?>">
                <label for="salary-grade" class="required-field">Salary Grade</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="years-position" name="years_position" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_position']) : ''; ?>">
                <label for="years-position" class="required-field">Years in this Position</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="years-lspu" name="years_lspu" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php 
                       echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_lspu']) : 
                                      (isset($user_info['yearsInLSPU']) ? htmlspecialchars($user_info['yearsInLSPU']) : ''); 
                       ?>" <?php echo isset($user_info['yearsInLSPU']) ? 'readonly' : ''; ?>>
                <label for="years-lspu" class="required-field">Years in LSPU</label>
                <span class="help-text">Automatically filled from your profile</span>
              </div>
            </div>
            
            <!-- Column 2 -->
            <div class="space-y-6">
              <div class="floating-label">
                <input type="text" id="years-other" name="years_other" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_other']) : ''; ?>">
                <label for="years-other">Years in Other Office/Agency</label>
                <span class="help-text">If applicable</span>
              </div>
              
              <div class="floating-label">
                <input type="text" id="division" name="division" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['division']) : ''; ?>">
                <label for="division" class="required-field">Division</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="office" name="office" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['office']) : ''; ?>">
                <label for="office" class="required-field">Office/Unit</label>
              </div>
              
              <div class="floating-label">
                <textarea id="address" name="address" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                          rows="2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['address']) : ''; ?></textarea>
                <label for="address" class="required-field">Office Address</label>
              </div>
              
              <div class="floating-label">
                <input type="text" id="supervisor" name="supervisor" placeholder=" " class="border-2 border-gray-200 focus:border-blue-500 rounded-xl"
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['supervisor']) : ''; ?>">
                <label for="supervisor" class="required-field">Supervisor's Name</label>
              </div>
            </div>
          </div>
          
          <!-- Print version of personal info -->
          <div class="print-only">
            <table class="w-full border-collapse border border-gray-300 mb-6">
              <tbody>
                <tr>
                  <td class="border border-gray-300 p-2 font-medium">1. Name</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['name']) : ''; ?></td>
                  <td class="border border-gray-300 p-2 font-medium">6. Years in other office/agency if any</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_other']) : ''; ?></td>
                </tr>
                <tr>
                  <td class="border border-gray-300 p-2 font-medium">2. Current Position</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['position']) : ''; ?></td>
                  <td class="border border-gray-300 p-2 font-medium">7. Division</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['division']) : ''; ?></td>
                </tr>
                <tr>
                  <td class="border border-gray-300 p-2 font-medium">3. Salary Grade</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['salary_grade']) : ''; ?></td>
                  <td class="border border-gray-300 p-2 font-medium">8. Office</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['office']) : ''; ?></td>
                </tr>
                <tr>
                  <td class="border border-gray-300 p-2 font-medium">4. Years in the Position</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_position']) : ''; ?></td>
                  <td class="border border-gray-300 p-2 font-medium">9. No more development is desired or required for</td>
                  <td class="border border-gray-300 p-2"></td>
                </tr>
                <tr>
                  <td class="border border-gray-300 p-2 font-medium">5. Years in LSPU</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['years_lspu']) : ''; ?></td>
                  <td class="border border-gray-300 p-2 font-medium">10. Supervisor's Name</td>
                  <td class="border border-gray-300 p-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['personal_info']['supervisor']) : ''; ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Purpose Section -->
        <div class="mb-12 form-section">
          <div class="section-highlight mb-6 no-print">
            <h3 class="text-2xl font-bold text-gray-800 flex items-center">
              <i class="ri-target-line mr-3 text-blue-600"></i> Purpose of Development Plan
            </h3>
            <p class="text-gray-600 mt-2">Select the purpose(s) for creating this development plan</p>
          </div>
          
          <div class="print-only">
            <h3 class="text-xl font-bold mb-4">PURPOSE:</h3>
          </div>
          
          <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-8 rounded-2xl border border-blue-100 no-print">
            <div class="space-y-4">
              <div class="flex items-start p-4 rounded-lg hover:bg-white/50 transition-colors">
                <div class="flex items-center h-6 mr-4">
                  <input type="checkbox" id="purpose1" name="purpose1" class="checkbox-custom w-5 h-5" 
                         <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose1']) ? 'checked' : ''; ?>>
                </div>
                <label for="purpose1" class="flex-1 cursor-pointer">
                  <span class="text-gray-800 font-medium">To meet the competencies in the current positions</span>
                  <p class="text-sm text-gray-600 mt-1">Develop skills required for your current role</p>
                </label>
              </div>
              
              <div class="flex items-start p-4 rounded-lg hover:bg-white/50 transition-colors">
                <div class="flex items-center h-6 mr-4">
                  <input type="checkbox" id="purpose2" name="purpose2" class="checkbox-custom w-5 h-5"
                         <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose2']) ? 'checked' : ''; ?>>
                </div>
                <label for="purpose2" class="flex-1 cursor-pointer">
                  <span class="text-gray-800 font-medium">To increase the level of competencies of current positions</span>
                  <p class="text-sm text-gray-600 mt-1">Enhance existing skills for better performance</p>
                </label>
              </div>
              
              <div class="flex items-start p-4 rounded-lg hover:bg-white/50 transition-colors">
                <div class="flex items-center h-6 mr-4">
                  <input type="checkbox" id="purpose3" name="purpose3" class="checkbox-custom w-5 h-5"
                         <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose3']) ? 'checked' : ''; ?>>
                </div>
                <label for="purpose3" class="flex-1 cursor-pointer">
                  <span class="text-gray-800 font-medium">To meet the competencies in the next higher position</span>
                  <p class="text-sm text-gray-600 mt-1">Prepare for career advancement</p>
                </label>
              </div>
              
              <div class="flex items-start p-4 rounded-lg hover:bg-white/50 transition-colors">
                <div class="flex items-center h-6 mr-4">
                  <input type="checkbox" id="purpose4" name="purpose4" class="checkbox-custom w-5 h-5"
                         <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose4']) ? 'checked' : ''; ?>>
                </div>
                <label for="purpose4" class="flex-1 cursor-pointer">
                  <span class="text-gray-800 font-medium">To acquire new competencies across different functions/position</span>
                  <p class="text-sm text-gray-600 mt-1">Develop cross-functional skills</p>
                </label>
              </div>
              
              <div class="flex items-start p-4 rounded-lg hover:bg-white/50 transition-colors">
                <div class="flex items-center h-6 mr-4">
                  <input type="checkbox" id="purpose5" name="purpose5" class="checkbox-custom w-5 h-5"
                         <?php echo ($is_edit && $form_data['form_data']['purpose']['purpose5']) ? 'checked' : ''; ?>>
                </div>
                <div class="flex-1">
                  <label for="purpose5" class="text-gray-800 font-medium cursor-pointer">Others, please specify:</label>
                  <div class="mt-3">
                    <input type="text" id="purpose-other" name="purpose_other" 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition-colors" 
                           placeholder="Specify other purposes here" 
                           value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['purpose']['purpose_other']) : ''; ?>">
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Print version -->
          <div class="print-only">
            <div class="mb-4">
              <div>(<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose1']) ? '✓' : ' '; ?>) To meet the competencies in the current positions</div>
              <div>(<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose2']) ? '✓' : ' '; ?>) To increase the level of competencies of current positions</div>
              <div>(<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose3']) ? '✓' : ' '; ?>) To meet the competencies in the next higher position</div>
              <div>(<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose4']) ? '✓' : ' '; ?>) To acquire new competencies across different functions/position</div>
              <div>(<?php echo ($is_edit && $form_data['form_data']['purpose']['purpose5']) ? '✓' : ' '; ?>) Others, please specify: <?php echo $is_edit ? htmlspecialchars($form_data['form_data']['purpose']['purpose_other']) : ''; ?></div>
            </div>
          </div>
        </div>
        
        <!-- Career Development Section -->
        <div class="mb-12 form-section">
          <div class="section-highlight mb-8 no-print">
            <h3 class="text-2xl font-bold text-gray-800 flex items-center">
              <i class="ri-line-chart-line mr-3 text-blue-600"></i> Career Development Plan
            </h3>
            <p class="text-gray-600 mt-2">Outline your short-term and long-term development goals</p>
          </div>
          
          <!-- Long Term Goals -->
          <div class="mb-10">
            <div class="mb-6 no-print">
              <h4 class="text-xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="ri-calendar-2-line mr-3 text-blue-500"></i>
                Training/Development Interventions for Long Term Goals (Next Five Years)
              </h4>
              <p class="text-gray-600">Plan your development activities for the next five years</p>
            </div>
            
            <div class="print-only">
              <h4 class="font-bold mb-2">Training/Development Interventions for Long Term Goals (Next Five Years)</h4>
            </div>
            
            <div class="overflow-x-auto rounded-2xl border border-gray-200">
              <table class="w-full development-table">
                <thead>
                  <tr>
                    <th class="p-4 font-semibold text-left long-term-area-col">Area of Development</th>
                    <th class="p-4 font-semibold text-left long-term-activity-col">Development Activity</th>
                    <th class="p-4 font-semibold text-left long-term-date-col">Target Completion Date</th>
                    <th class="p-4 font-semibold text-left long-term-stage-col">Completion Stage</th>
                  </tr>
                </thead>
                <tbody id="long-term-goals-body">
                  <?php if ($is_edit && !empty($form_data['form_data']['long_term_goals'])): ?>
                    <?php foreach ($form_data['form_data']['long_term_goals'] as $goal): ?>
                      <tr>
                        <td class="p-2">
                          <div contenteditable="true" data-name="long_term_area[]" 
                               class="table-cell-content min-h-[60px] outline-none <?php echo (empty($goal['area']) || $goal['area'] === 'Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development') ? 'default-text' : ''; ?>"
                               data-default="Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development">
                            <?php echo !empty($goal['area']) ? htmlspecialchars($goal['area']) : 'Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development'; ?>
                          </div>
                          <input type="hidden" name="long_term_area[]" value="<?php echo htmlspecialchars($goal['area']); ?>">
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="long_term_activity[]" 
                               class="table-cell-content min-h-[60px] outline-none <?php echo (empty($goal['activity']) || $goal['activity'] === 'Pursuance of Academic Degrees for advancement, conduct of trainings/seminars') ? 'default-text' : ''; ?>"
                               data-default="Pursuance of Academic Degrees for advancement, conduct of trainings/seminars">
                            <?php echo !empty($goal['activity']) ? htmlspecialchars($goal['activity']) : 'Pursuance of Academic Degrees for advancement, conduct of trainings/seminars'; ?>
                          </div>
                          <input type="hidden" name="long_term_activity[]" value="<?php echo htmlspecialchars($goal['activity']); ?>">
                        </td>
                        <td class="p-2">
                          <input type="date" name="long_term_date[]" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none no-print"
                                 value="<?php echo htmlspecialchars($goal['target_date']); ?>">
                          <span class="print-only"><?php echo htmlspecialchars($goal['target_date']); ?></span>
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="long_term_stage[]" 
                               class="table-cell-content min-h-[60px] outline-none <?php echo empty($goal['stage']) ? 'placeholder' : ''; ?>"
                               data-placeholder="Enter completion stage...">
                            <?php echo !empty($goal['stage']) ? htmlspecialchars($goal['stage']) : ''; ?>
                          </div>
                          <input type="hidden" name="long_term_stage[]" value="<?php echo htmlspecialchars($goal['stage']); ?>">
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td class="p-2">
                        <div contenteditable="true" data-name="long_term_area[]" 
                             class="table-cell-content min-h-[60px] outline-none default-text"
                             data-default="Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development">
                          Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development
                        </div>
                        <input type="hidden" name="long_term_area[]" value="Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development">
                      </td>
                      <td class="p-2">
                        <div contenteditable="true" data-name="long_term_activity[]" 
                             class="table-cell-content min-h-[60px] outline-none default-text"
                             data-default="Pursuance of Academic Degrees for advancement, conduct of trainings/seminars">
                          Pursuance of Academic Degrees for advancement, conduct of trainings/seminars
                        </div>
                        <input type="hidden" name="long_term_activity[]" value="Pursuance of Academic Degrees for advancement, conduct of trainings/seminars">
                      </td>
                      <td class="p-2">
                        <input type="date" name="long_term_date[]" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none no-print">
                        <span class="print-only"></span>
                      </td>
                      <td class="p-2">
                        <div contenteditable="true" data-name="long_term_stage[]" 
                             class="table-cell-content min-h-[60px] outline-none placeholder"
                             data-placeholder="Enter completion stage..."></div>
                        <input type="hidden" name="long_term_stage[]" value="">
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            
            <div class="mt-4 text-right no-print">
              <button type="button" onclick="addLongTermGoal()" class="text-blue-600 hover:text-blue-800 flex items-center text-sm">
                <i class="ri-add-circle-line mr-2"></i> Add Another Long Term Goal
              </button>
            </div>
          </div>
          
          <!-- Short Term Goals -->
          <div class="mb-10">
            <div class="mb-6 no-print">
              <h4 class="text-xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="ri-calendar-line mr-3 text-green-500"></i>
                Short Term Development Goals (Next Year)
              </h4>
              <p class="text-gray-600">Plan your immediate development activities for the coming year</p>
            </div>
            
            <div class="print-only">
              <h4 class="font-bold mb-2">Short Term Development Goals Next Year</h4>
            </div>
            
            <div class="overflow-x-auto rounded-2xl border border-gray-200">
              <table class="w-full development-table">
                <thead>
                  <tr>
                    <th class="p-4 font-semibold text-left area-col">Area of Development</th>
                    <th class="p-4 font-semibold text-left priority-col">Priority for Learning and Development Program (LDP)</th>
                    <th class="p-4 font-semibold text-left activity-col">Development Activity</th>
                    <th class="p-4 font-semibold text-left date-col">Target Completion Date</th>
                    <th class="p-4 font-semibold text-left responsible-col">Who is Responsible</th>
                    <th class="p-4 font-semibold text-left stage-col">Completion Stage</th>
                  </tr>
                </thead>
                <tbody id="short-term-goals-body">
                  <?php 
                  $default_short_term_areas = [
                    "1. Behavioral Training such as: Value Re-orientation, Team Building, Oral Communication, Written Communication, Customer Relations, People Development, Improving Planning & Delivery, Solving Problems and making decisions, Basic Communication Training Program, etc",
                    "2. Technical Skills Training such as: Basic Occupational Safety & Health, University Safety procedures, Preventive Maintenance Activities, etc.",
                    "3. Quality Management Training such as: Customer Requirements, Time Management, Continuous Improvement for Quality & Productivity, etc",
                    "4. Others: Formal Classroom Training, on-the-job training, Self-development, developmental activities/interventions, etc."
                  ];
                  
                  if ($is_edit && !empty($form_data['form_data']['short_term_goals'])) {
                    foreach ($form_data['form_data']['short_term_goals'] as $index => $goal) {
                      $area = !empty($goal['area']) ? $goal['area'] : ($default_short_term_areas[$index] ?? '');
                      $defaultActivity = ($index === 0) ? 'Conduct of training/seminar' : (($index === 3) ? 'Coaching on the Job-knowledge sharing and learning session' : '');
                      $activity = !empty($goal['activity']) ? $goal['activity'] : $defaultActivity;
                      ?>
                      <tr>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_area[]" 
                               class="table-cell-content min-h-[60px] outline-none">
                            <?php echo htmlspecialchars($area); ?>
                          </div>
                          <input type="hidden" name="short_term_area[]" value="<?php echo htmlspecialchars($area); ?>">
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_priority[]" 
                               class="table-cell-content min-h-[60px] outline-none placeholder"
                               data-placeholder="Enter priority...">
                            <?php echo htmlspecialchars($goal['priority']); ?>
                          </div>
                          <input type="hidden" name="short_term_priority[]" value="<?php echo htmlspecialchars($goal['priority']); ?>">
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_activity[]" 
                               class="table-cell-content min-h-[60px] outline-none <?php echo (empty($goal['activity']) && !empty($defaultActivity)) ? 'default-text' : ''; ?>"
                               data-default="<?php echo $defaultActivity; ?>">
                            <?php echo htmlspecialchars($activity); ?>
                          </div>
                          <input type="hidden" name="short_term_activity[]" value="<?php echo htmlspecialchars($activity); ?>">
                        </td>
                        <td class="p-2">
                          <input type="date" name="short_term_date[]" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none no-print"
                                 value="<?php echo htmlspecialchars($goal['target_date']); ?>">
                          <div class="print-only"><?php echo htmlspecialchars($goal['target_date']); ?></div>
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_responsible[]" 
                               class="table-cell-content min-h-[60px] outline-none placeholder"
                               data-placeholder="Enter responsible person...">
                            <?php echo htmlspecialchars($goal['responsible']); ?>
                          </div>
                          <input type="hidden" name="short_term_responsible[]" value="<?php echo htmlspecialchars($goal['responsible']); ?>">
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_stage[]" 
                               class="table-cell-content min-h-[60px] outline-none placeholder"
                               data-placeholder="Enter completion stage...">
                            <?php echo htmlspecialchars($goal['stage']); ?>
                          </div>
                          <input type="hidden" name="short_term_stage[]" value="<?php echo htmlspecialchars($goal['stage']); ?>">
                        </td>
                      </tr>
                      <?php
                    }
                  } else {
                    foreach ($default_short_term_areas as $index => $area) {
                      $defaultActivity = ($index === 0) ? 'Conduct of training/seminar' : (($index === 3) ? 'Coaching on the Job-knowledge sharing and learning session' : '');
                      ?>
                      <tr>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_area[]" 
                               class="table-cell-content min-h-[60px] outline-none">
                            <?php echo htmlspecialchars($area); ?>
                          </div>
                          <input type="hidden" name="short_term_area[]" value="<?php echo htmlspecialchars($area); ?>">
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_priority[]" 
                               class="table-cell-content min-h-[60px] outline-none placeholder"
                               data-placeholder="Enter priority..."></div>
                          <input type="hidden" name="short_term_priority[]" value="">
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_activity[]" 
                               class="table-cell-content min-h-[60px] outline-none <?php echo !empty($defaultActivity) ? 'default-text' : ''; ?>"
                               data-default="<?php echo $defaultActivity; ?>">
                            <?php echo $defaultActivity; ?>
                          </div>
                          <input type="hidden" name="short_term_activity[]" value="<?php echo $defaultActivity; ?>">
                        </td>
                        <td class="p-2">
                          <input type="date" name="short_term_date[]" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none no-print">
                          <div class="print-only"></div>
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_responsible[]" 
                               class="table-cell-content min-h-[60px] outline-none placeholder"
                               data-placeholder="Enter responsible person..."></div>
                          <input type="hidden" name="short_term_responsible[]" value="">
                        </td>
                        <td class="p-2">
                          <div contenteditable="true" data-name="short_term_stage[]" 
                               class="table-cell-content min-h-[60px] outline-none placeholder"
                               data-placeholder="Enter completion stage..."></div>
                          <input type="hidden" name="short_term_stage[]" value="">
                        </td>
                      </tr>
                      <?php
                    }
                  }
                  ?>
                </tbody>
              </table>
            </div>
            
            <div class="mt-4 text-right no-print">
              <button type="button" onclick="addShortTermGoal()" class="text-blue-600 hover:text-blue-800 flex items-center text-sm">
                <i class="ri-add-circle-line mr-2"></i> Add Another Short Term Goal
              </button>
            </div>
          </div>
        </div>
        
        <!-- Certification Section with Signature Upload -->
        <div class="form-section">
          <div class="section-highlight mb-6 no-print">
            <h3 class="text-2xl font-bold text-gray-800 flex items-center">
              <i class="ri-file-certificate-line mr-3 text-blue-600"></i> Certification and Commitment
            </h3>
            <p class="text-gray-600 mt-2">Signatures and commitments from all parties</p>
          </div>
          
          <div class="print-only">
            <h3 class="text-xl font-bold mb-4">CERTIFICATION AND COMMITMENT</h3>
          </div>
          
          <div class="bg-gradient-to-r from-gray-50 to-blue-50 p-8 rounded-2xl border border-gray-200 mb-8 no-print">
            <div class="text-center mb-8">
              <div class="inline-flex items-center justify-center p-3 rounded-full bg-blue-100 mb-4">
                <i class="ri-shield-check-line text-blue-600 text-2xl"></i>
              </div>
              <p class="text-gray-700 italic">
                This is to certify that this Individual Development Plan has been discussed with me by my immediate superior. I further commit that I will exert time and effort to ensure that this will be achieved according to agreed time frames.
              </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
              <!-- Employee Signature -->
              <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 text-center">
                <div class="signature-line mb-4"></div>
                <p class="text-sm font-medium text-gray-700 mb-4">Signature of Employee</p>
                
                <!-- Signature Upload for Employee -->
                <div class="signature-upload-container mb-4" id="employeeSignatureUpload">
                  <input type="file" id="employeeSignatureFile" accept="image/*" class="hidden-input" onchange="handleSignatureUpload(this, 'employee')">
                  <i class="ri-upload-cloud-2-line text-4xl text-blue-500 mb-3"></i>
                  <p class="text-gray-600 mb-4">Click to upload signature image</p>
                  <button type="button" onclick="document.getElementById('employeeSignatureFile').click()" class="signature-upload-btn">
                    <i class="ri-upload-line"></i> Upload Signature
                  </button>
                  <p class="text-xs text-gray-500 mt-2">Supported: PNG, JPG, JPEG</p>
                  <div class="signature-preview mt-3" id="employeeSignaturePreview">
                    <?php if ($is_edit && !empty($form_data['form_data']['certification']['employee_signature'])): ?>
                      <img src="<?php echo htmlspecialchars($form_data['form_data']['certification']['employee_signature']); ?>" alt="Employee Signature">
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="space-y-4">
                  <input type="text" name="employee_name" 
                         class="w-full px-3 py-2 border-b-2 border-gray-300 bg-transparent text-center focus:border-blue-500 focus:outline-none" 
                         placeholder="Printed Name" 
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_name']) : ''; ?>">
                  <input type="date" name="employee_date" 
                         class="w-full px-3 py-2 border-b-2 border-gray-300 bg-transparent text-center focus:border-blue-500 focus:outline-none"
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_date']) : ''; ?>">
                </div>
                <input type="hidden" id="employeeSignature" name="employee_signature" 
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_signature'] ?? '') : ''; ?>">
              </div>
              
              <!-- Immediate Supervisor -->
              <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 text-center">
                <div class="signature-line mb-4"></div>
                <p class="text-sm font-medium text-gray-700 mb-4">Immediate Supervisor</p>
                
                <!-- Signature Upload for Supervisor -->
                <div class="signature-upload-container mb-4" id="supervisorSignatureUpload">
                  <input type="file" id="supervisorSignatureFile" accept="image/*" class="hidden-input" onchange="handleSignatureUpload(this, 'supervisor')">
                  <i class="ri-upload-cloud-2-line text-4xl text-blue-500 mb-3"></i>
                  <p class="text-gray-600 mb-4">Click to upload signature image</p>
                  <button type="button" onclick="document.getElementById('supervisorSignatureFile').click()" class="signature-upload-btn">
                    <i class="ri-upload-line"></i> Upload Signature
                  </button>
                  <p class="text-xs text-gray-500 mt-2">Supported: PNG, JPG, JPEG</p>
                  <div class="signature-preview mt-3" id="supervisorSignaturePreview">
                    <?php if ($is_edit && !empty($form_data['form_data']['certification']['supervisor_signature'])): ?>
                      <img src="<?php echo htmlspecialchars($form_data['form_data']['certification']['supervisor_signature']); ?>" alt="Supervisor Signature">
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="space-y-4">
                  <input type="text" name="supervisor_name" 
                         class="w-full px-3 py-2 border-b-2 border-gray-300 bg-transparent text-center focus:border-blue-500 focus:outline-none" 
                         placeholder="Printed Name" 
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_name']) : ''; ?>">
                  <input type="date" name="supervisor_date" 
                         class="w-full px-3 py-2 border-b-2 border-gray-300 bg-transparent text-center focus:border-blue-500 focus:outline-none"
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_date']) : ''; ?>">
                </div>
                <input type="hidden" id="supervisorSignature" name="supervisor_signature" 
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_signature'] ?? '') : ''; ?>">
              </div>
              
              <!-- Campus Director -->
              <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 text-center">
                <div class="signature-line mb-4"></div>
                <p class="text-sm font-medium text-gray-700 mb-4">Campus Director</p>
                
                <!-- Signature Upload for Director -->
                <div class="signature-upload-container mb-4" id="directorSignatureUpload">
                  <input type="file" id="directorSignatureFile" accept="image/*" class="hidden-input" onchange="handleSignatureUpload(this, 'director')">
                  <i class="ri-upload-cloud-2-line text-4xl text-blue-500 mb-3"></i>
                  <p class="text-gray-600 mb-4">Click to upload signature image</p>
                  <button type="button" onclick="document.getElementById('directorSignatureFile').click()" class="signature-upload-btn">
                    <i class="ri-upload-line"></i> Upload Signature
                  </button>
                  <p class="text-xs text-gray-500 mt-2">Supported: PNG, JPG, JPEG</p>
                  <div class="signature-preview mt-3" id="directorSignaturePreview">
                    <?php if ($is_edit && !empty($form_data['form_data']['certification']['director_signature'])): ?>
                      <img src="<?php echo htmlspecialchars($form_data['form_data']['certification']['director_signature']); ?>" alt="Director Signature">
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="space-y-4">
                  <input type="text" name="director_name" 
                         class="w-full px-3 py-2 border-b-2 border-gray-300 bg-transparent text-center focus:border-blue-500 focus:outline-none" 
                         placeholder="Printed Name" 
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_name']) : ''; ?>">
                  <input type="date" name="director_date" 
                         class="w-full px-3 py-2 border-b-2 border-gray-300 bg-transparent text-center focus:border-blue-500 focus:outline-none"
                         value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_date']) : ''; ?>">
                </div>
                <input type="hidden" id="directorSignature" name="director_signature" 
                       value="<?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_signature'] ?? '') : ''; ?>">
              </div>
            </div>
            
            <div class="mt-8 text-center">
              <div class="inline-flex items-center justify-center p-3 rounded-full bg-green-100 mb-4">
                <i class="ri-hand-heart-line text-green-600 text-2xl"></i>
              </div>
              <p class="text-gray-700 italic">
                I commit to support and ensure that this agreed Individual Development Plan is achieved to the agreed time frames
              </p>
            </div>
          </div>
          
          <!-- Print version -->
          <div class="print-only">
            <div class="mb-6">
              <p class="mb-4">This is to certify that this Individual Development Plan has been discussed with me by my immediate superior. I further commit that I will exert time and effort to ensure that this will be achieved according to agreed time frames.</p>
              
              <div class="grid grid-cols-3 gap-4 mt-8">
                <div class="text-center">
                  <div class="signature-line mb-2"></div>
                  <div class="text-sm font-medium">Signature of Employee</div>
                  <div class="mt-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_name']) : ''; ?></div>
                  <div class="text-sm"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['employee_date']) : ''; ?></div>
                  <?php if ($is_edit && !empty($form_data['form_data']['certification']['employee_signature'])): ?>
                    <div class="mt-2">
                      <img src="<?php echo htmlspecialchars($form_data['form_data']['certification']['employee_signature']); ?>" alt="Employee Signature" style="max-height: 50px;">
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="text-center">
                  <div class="signature-line mb-2"></div>
                  <div class="text-sm font-medium">Immediate Supervisor</div>
                  <div class="mt-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_name']) : ''; ?></div>
                  <div class="text-sm"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['supervisor_date']) : ''; ?></div>
                  <?php if ($is_edit && !empty($form_data['form_data']['certification']['supervisor_signature'])): ?>
                    <div class="mt-2">
                      <img src="<?php echo htmlspecialchars($form_data['form_data']['certification']['supervisor_signature']); ?>" alt="Supervisor Signature" style="max-height: 50px;">
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="text-center">
                  <div class="signature-line mb-2"></div>
                  <div class="text-sm font-medium">Campus Director</div>
                  <div class="mt-2"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_name']) : ''; ?></div>
                  <div class="text-sm"><?php echo $is_edit ? htmlspecialchars($form_data['form_data']['certification']['director_date']) : ''; ?></div>
                  <?php if ($is_edit && !empty($form_data['form_data']['certification']['director_signature'])): ?>
                    <div class="mt-2">
                      <img src="<?php echo htmlspecialchars($form_data['form_data']['certification']['director_signature']); ?>" alt="Director Signature" style="max-height: 50px;">
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="mt-8 text-center italic">
                I commit to support and ensure that this agreed Individual Development Plan is achieved to the agreed time frames
              </div>
              
              <div class="mt-8 text-center text-sm">
                LSPU-HRO-SF-027 Rev. 1 2 April 2018
              </div>
            </div>
          </div>
          
          <!-- Form Footer -->
          <div class="flex flex-col lg:flex-row justify-between items-center pt-8 border-t border-gray-200 mt-8 no-print">
            <div class="flex space-x-4 mb-6 lg:mb-0">
              <button type="submit" name="save_draft" class="bg-gray-600 hover:bg-gray-700 text-white px-8 py-3 rounded-xl font-medium flex items-center shadow-lg hover:shadow-xl transition-all">
                <i class="ri-save-line mr-3"></i> Save as Draft
              </button>
              <button type="submit" name="submit_form" class="btn-gradient-success text-white px-8 py-3 rounded-xl font-medium flex items-center shadow-lg hover:shadow-xl transition-all">
                <i class="ri-send-plane-fill mr-3"></i> Submit to HR
              </button>
            </div>
            <div class="text-sm text-gray-600 flex flex-col lg:flex-row lg:space-x-8 text-center lg:text-left">
              <span class="font-medium">LSPU-HRD-SF-027</span>
              <span>Revision 1</span>
              <span>Effective: 2 April 2018</span>
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
  // Initialize dropdown (always open)
  const dropdownContainer = document.getElementById('idp-dropdown');
  if (dropdownContainer) {
    dropdownContainer.classList.add('open');
  }
  
  // Handle editable cells in tables
  initializeEditableCells();
  
  // Auto-expand textareas
  document.querySelectorAll('.auto-expand').forEach(textarea => {
    textarea.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Trigger initial resize
    textarea.dispatchEvent(new Event('input'));
  });
  
  // Print functionality
  document.getElementById('print-btn')?.addEventListener('click', function() {
    // Collect all form data for PDF generation
    const formData = collectFormData();
    
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
  
  // Form validation
  const form = document.getElementById('idp-form');
  if (form) {
    form.addEventListener('submit', function(e) {
      updateHiddenInputs(); // Update all hidden inputs before submission
      
      const requiredFields = form.querySelectorAll('[class*="required-field"]');
      let isValid = true;
      let firstInvalidField = null;

      requiredFields.forEach(label => {
        const fieldId = label.getAttribute('for');
        const field = document.getElementById(fieldId);
        if (field && !field.value.trim()) {
          isValid = false;
          if (!firstInvalidField) firstInvalidField = field;
          field.classList.add('border-red-500');
        }
      });

      if (!isValid) {
        e.preventDefault();
        firstInvalidField?.focus();
        Swal.fire({
          title: 'Missing Information',
          text: 'Please fill in all required fields marked with *',
          icon: 'warning',
          confirmButtonColor: '#ef4444'
        });
      }
    });
  }
  
  // Clear red border when user starts typing
  document.querySelectorAll('input, textarea').forEach(field => {
    field.addEventListener('input', function() {
      this.classList.remove('border-red-500');
    });
  });
  
  // Help button functionality
  document.getElementById('help-btn')?.addEventListener('click', function() {
    Swal.fire({
      title: 'IDP Form Help',
      html: `
        <div class="text-left space-y-4">
          <div>
            <h4 class="font-bold text-blue-600">Personal Information</h4>
            <p class="text-sm text-gray-600">Fill in your current employment details. Some fields are automatically populated from your profile.</p>
          </div>
          <div>
            <h4 class="font-bold text-blue-600">Purpose</h4>
            <p class="text-sm text-gray-600">Select the main reason(s) for creating this development plan. You can select multiple options.</p>
          </div>
          <div>
            <h4 class="font-bold text-blue-600">Career Development Goals</h4>
            <p class="text-sm text-gray-600">Plan both long-term (5 years) and short-term (1 year) development activities. You can add more rows as needed.</p>
          </div>
          <div>
            <h4 class="font-bold text-blue-600">Certification</h4>
            <p class="text-sm text-gray-600">Upload signature images for employee, supervisor, and campus director.</p>
          </div>
        </div>
      `,
      icon: 'info',
      confirmButtonColor: '#3b82f6',
      confirmButtonText: 'Got it!',
      width: '600px'
    });
  });
  
  // Show success message after form submission
  <?php if (isset($_SESSION['message'])): ?>
    Swal.fire({
      icon: '<?php echo $_SESSION['message']['type'] === 'success' ? 'success' : 'error'; ?>',
      title: '<?php echo $_SESSION['message']['type'] === 'success' ? 'Success!' : 'Error!'; ?>',
      text: '<?php echo addslashes($_SESSION['message']['text']); ?>',
      confirmButtonColor: '#2563eb',
      customClass: {
        popup: 'rounded-2xl'
      },
      timer: 3000,
      timerProgressBar: true
    }).then(() => {
      <?php unset($_SESSION['message']); ?>
    });
  <?php endif; ?>
});

function initializeEditableCells() {
  // Handle editable cells with default text
  document.querySelectorAll('.table-cell-content.default-text').forEach(cell => {
    const defaultValue = cell.getAttribute('data-default');
    const hiddenInput = cell.parentElement.querySelector('input[type="hidden"]');
    
    cell.addEventListener('focus', function() {
      if (this.textContent.trim() === defaultValue) {
        this.textContent = '';
        this.classList.remove('default-text');
      }
    });
    
    cell.addEventListener('blur', function() {
      if (this.textContent.trim() === '') {
        this.textContent = defaultValue;
        this.classList.add('default-text');
      }
      // Update hidden input value
      if (hiddenInput) {
        hiddenInput.value = this.textContent.trim();
      }
    });
    
    // Handle input to update hidden field
    cell.addEventListener('input', function() {
      if (hiddenInput) {
        hiddenInput.value = this.textContent.trim();
      }
    });
  });
  
  // Handle editable cells with placeholder
  document.querySelectorAll('.table-cell-content.placeholder').forEach(cell => {
    const placeholder = cell.getAttribute('data-placeholder');
    const hiddenInput = cell.parentElement.querySelector('input[type="hidden"]');
    
    if (cell.textContent.trim() === '') {
      cell.textContent = placeholder;
      cell.classList.add('placeholder');
    }
    
    cell.addEventListener('focus', function() {
      if (this.classList.contains('placeholder')) {
        this.textContent = '';
        this.classList.remove('placeholder');
      }
    });
    
    cell.addEventListener('blur', function() {
      if (this.textContent.trim() === '') {
        this.textContent = placeholder;
        this.classList.add('placeholder');
      }
      // Update hidden input value
      if (hiddenInput) {
        hiddenInput.value = this.textContent.trim();
      }
    });
    
    // Handle input to update hidden field
    cell.addEventListener('input', function() {
      if (hiddenInput) {
        hiddenInput.value = this.textContent.trim();
      }
    });
  });
  
  // Handle regular editable cells
  document.querySelectorAll('.table-cell-content:not(.default-text):not(.placeholder)').forEach(cell => {
    const hiddenInput = cell.parentElement.querySelector('input[type="hidden"]');
    
    cell.addEventListener('input', function() {
      if (hiddenInput) {
        hiddenInput.value = this.textContent.trim();
      }
    });
    
    cell.addEventListener('blur', function() {
      if (hiddenInput) {
        hiddenInput.value = this.textContent.trim();
      }
    });
  });
}

// Signature Upload Functions
function handleSignatureUpload(input, type) {
  const file = input.files[0];
  if (!file) return;
  
  // Validate file type
  if (!file.type.match('image.*')) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid File',
      text: 'Please upload an image file (PNG, JPG, JPEG)',
      confirmButtonColor: '#ef4444'
    });
    return;
  }
  
  // Validate file size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    Swal.fire({
      icon: 'error',
      title: 'File Too Large',
      text: 'Please upload an image smaller than 2MB',
      confirmButtonColor: '#ef4444'
    });
    return;
  }
  
  const reader = new FileReader();
  reader.onload = function(e) {
    const preview = document.getElementById(type + 'SignaturePreview');
    const signatureInput = document.getElementById(type + 'Signature');
    const signatureDataInput = document.getElementById(type + '_signature_data');
    
    if (preview) {
      preview.innerHTML = `<img src="${e.target.result}" alt="${type} Signature">`;
    }
    
    if (signatureInput) {
      // Convert to base64 for storage
      const base64Data = e.target.result;
      signatureInput.value = base64Data;
      
      if (signatureDataInput) {
        signatureDataInput.value = base64Data;
      }
    }
    
    Swal.fire({
      icon: 'success',
      title: 'Signature Uploaded!',
      text: `${type.charAt(0).toUpperCase() + type.slice(1)} signature has been uploaded.`,
      confirmButtonColor: '#10b981',
      timer: 2000,
      timerProgressBar: true
    });
  };
  
  reader.readAsDataURL(file);
}

// Add drag and drop for signature upload
['employeeSignatureUpload', 'supervisorSignatureUpload', 'directorSignatureUpload'].forEach(id => {
  const container = document.getElementById(id);
  if (container) {
    container.addEventListener('dragover', function(e) {
      e.preventDefault();
      this.classList.add('dragover');
    });
    
    container.addEventListener('dragleave', function(e) {
      e.preventDefault();
      this.classList.remove('dragover');
    });
    
    container.addEventListener('drop', function(e) {
      e.preventDefault();
      this.classList.remove('dragover');
      
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        const type = id.replace('SignatureUpload', '');
        const fileInput = document.getElementById(type + 'SignatureFile');
        if (fileInput) {
          fileInput.files = files;
          handleSignatureUpload(fileInput, type);
        }
      }
    });
  }
});

function collectFormData() {
  updateHiddenInputs();
  
  return {
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
    long_term_area: Array.from(document.querySelectorAll('input[name="long_term_area[]"]')).map(el => el.value),
    long_term_activity: Array.from(document.querySelectorAll('input[name="long_term_activity[]"]')).map(el => el.value),
    long_term_date: Array.from(document.querySelectorAll('input[name="long_term_date[]"]')).map(el => el.value),
    long_term_stage: Array.from(document.querySelectorAll('input[name="long_term_stage[]"]')).map(el => el.value),
    
    // Short Term Goals
    short_term_area: Array.from(document.querySelectorAll('input[name="short_term_area[]"]')).map(el => el.value),
    short_term_priority: Array.from(document.querySelectorAll('input[name="short_term_priority[]"]')).map(el => el.value),
    short_term_activity: Array.from(document.querySelectorAll('input[name="short_term_activity[]"]')).map(el => el.value),
    short_term_date: Array.from(document.querySelectorAll('input[name="short_term_date[]"]')).map(el => el.value),
    short_term_responsible: Array.from(document.querySelectorAll('input[name="short_term_responsible[]"]')).map(el => el.value),
    short_term_stage: Array.from(document.querySelectorAll('input[name="short_term_stage[]"]')).map(el => el.value),
    
    // Certification
    employee_name: document.querySelector('input[name="employee_name"]')?.value || '',
    employee_date: document.querySelector('input[name="employee_date"]')?.value || '',
    employee_signature: document.getElementById('employeeSignature')?.value || '',
    supervisor_name: document.querySelector('input[name="supervisor_name"]')?.value || '',
    supervisor_date: document.querySelector('input[name="supervisor_date"]')?.value || '',
    supervisor_signature: document.getElementById('supervisorSignature')?.value || '',
    director_name: document.querySelector('input[name="director_name"]')?.value || '',
    director_date: document.querySelector('input[name="director_date"]')?.value || '',
    director_signature: document.getElementById('directorSignature')?.value || ''
  };
}

function updateHiddenInputs() {
  // Update all hidden inputs from editable cells
  document.querySelectorAll('.table-cell-content').forEach(cell => {
    const hiddenInput = cell.parentElement.querySelector('input[type="hidden"]');
    if (hiddenInput) {
      hiddenInput.value = cell.textContent.trim();
    }
  });
}

// Add new long term goal row
function addLongTermGoal() {
  const tableBody = document.getElementById('long-term-goals-body');
  const newRow = document.createElement('tr');
  newRow.innerHTML = `
    <td class="p-2">
      <div contenteditable="true" data-name="long_term_area[]" 
           class="table-cell-content min-h-[60px] outline-none default-text"
           data-default="Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development"></div>
      <input type="hidden" name="long_term_area[]" value="Academic (if applicable), Attendance to seminar on Supervisory Development Program & Management/Executive & Leadership Development">
    </td>
    <td class="p-2">
      <div contenteditable="true" data-name="long_term_activity[]" 
           class="table-cell-content min-h-[60px] outline-none default-text"
           data-default="Pursuance of Academic Degrees for advancement, conduct of trainings/seminars"></div>
      <input type="hidden" name="long_term_activity[]" value="Pursuance of Academic Degrees for advancement, conduct of trainings/seminars">
    </td>
    <td class="p-2">
      <input type="date" name="long_term_date[]" 
             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none no-print">
      <span class="print-only"></span>
    </td>
    <td class="p-2">
      <div contenteditable="true" data-name="long_term_stage[]" 
           class="table-cell-content min-h-[60px] outline-none placeholder"
           data-placeholder="Enter completion stage..."></div>
      <input type="hidden" name="long_term_stage[]" value="">
    </td>
  `;
  tableBody.appendChild(newRow);
  
  // Initialize the new editable cells
  initializeEditableCells();
}

// Add new short term goal row
function addShortTermGoal() {
  const tableBody = document.getElementById('short-term-goals-body');
  const newRow = document.createElement('tr');
  newRow.innerHTML = `
    <td class="p-2">
      <div contenteditable="true" data-name="short_term_area[]" 
           class="table-cell-content min-h-[60px] outline-none"></div>
      <input type="hidden" name="short_term_area[]" value="">
    </td>
    <td class="p-2">
      <div contenteditable="true" data-name="short_term_priority[]" 
           class="table-cell-content min-h-[60px] outline-none placeholder"
           data-placeholder="Enter priority..."></div>
      <input type="hidden" name="short_term_priority[]" value="">
    </td>
    <td class="p-2">
      <div contenteditable="true" data-name="short_term_activity[]" 
           class="table-cell-content min-h-[60px] outline-none"></div>
      <input type="hidden" name="short_term_activity[]" value="">
    </td>
    <td class="p-2">
      <input type="date" name="short_term_date[]" 
             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none no-print">
      <div class="print-only"></div>
    </td>
    <td class="p-2">
      <div contenteditable="true" data-name="short_term_responsible[]" 
           class="table-cell-content min-h-[60px] outline-none placeholder"
           data-placeholder="Enter responsible person..."></div>
      <input type="hidden" name="short_term_responsible[]" value="">
    </td>
    <td class="p-2">
      <div contenteditable="true" data-name="short_term_stage[]" 
           class="table-cell-content min-h-[60px] outline-none placeholder"
           data-placeholder="Enter completion stage..."></div>
      <input type="hidden" name="short_term_stage[]" value="">
    </td>
  `;
  tableBody.appendChild(newRow);
  
  // Initialize the new editable cells
  initializeEditableCells();
}
</script>
</body>
</html>