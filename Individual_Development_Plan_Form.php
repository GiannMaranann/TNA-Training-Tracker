<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login_register.php");
    exit();
}

// Get employees who have submitted IDP forms with their details
$query = "SELECT 
            u.id as user_id,
            u.name, 
            ip.position, 
            u.department,
            COUNT(f.id) as total_idps,
            MAX(f.submitted_at) as last_submission
          FROM idp_forms f
          JOIN users u ON f.user_id = u.id
          JOIN idp_personal_info ip ON f.id = ip.form_id
          WHERE f.status = 'submitted'
          GROUP BY u.id, u.name, ip.position, u.department
          ORDER BY u.name ASC";

$result = $con->query($query);
$employees = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Get IDP forms for a specific user
function getUserIDPForms($user_id) {
    global $con;
    $stmt = $con->prepare("SELECT id, form_data, submitted_at FROM idp_forms WHERE user_id = ? AND status = 'submitted' ORDER BY submitted_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $forms = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $forms;
}

// Complete departments list for LSPU
$departments = [
    'CA' => 'College of Agriculture',
    'CBAA' => 'College of Business, Administration and Accountancy',
    'CAS' => 'College of Arts and Sciences',
    'CCJE' => 'College of Criminal Justice Education',
    'CCS' => 'College of Computer Studies',
    'CFND' => 'College of Food Nutrition and Dietetics',
    'CHMT' => 'College of Hospitality and Tourism Management',
    'CIT' => 'College of Industrial Technology',
    'COE' => 'College of Engineering',
    'COF' => 'College of Fisheries',
    'COL' => 'College of Law',
    'CONAH' => 'College of Nursing and Allied Health',
    'CTE' => 'College of Teacher Education'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - IDP Submissions - LSPU</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#1e3a8a',
            secondary: '#1e40af',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            dark: '#1e293b',
            light: '#f8fafc',
            agriculture: '#059669', // Green for agriculture
            fisheries: '#0ea5e9',   // Blue for fisheries
            technology: '#92400e'   // Brown for technology
          },
          borderRadius: {
            DEFAULT: '12px',
            'button': '10px'
          },
          fontFamily: {
            'poppins': ['Poppins', 'sans-serif']
          },
          boxShadow: {
            'card': '0 8px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 10px -2px rgba(0, 0, 0, 0.05)',
            'button': '0 4px 12px 0 rgba(30, 58, 138, 0.2)'
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      min-height: 100vh;
    }
    
    .glass-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }
    
    .card {
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      border-radius: 20px;
      overflow: hidden;
      background: linear-gradient(145deg, #ffffff, #f8fafc);
      box-shadow: 0 10px 30px rgba(30, 58, 138, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.8);
    }
    
    .card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 40px rgba(30, 58, 138, 0.25);
    }
    
    .sidebar {
      background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
    }
    
    .sidebar-link {
      transition: all 0.3s ease;
      border-radius: 12px;
      margin: 4px 0;
      border: 1px solid transparent;
    }
    
    .sidebar-link:hover {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      transform: translateX(8px);
      border-color: rgba(255, 255, 255, 0.3);
      box-shadow: 0 8px 25px rgba(30, 58, 138, 0.3);
    }
    
    .sidebar-link.active {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      box-shadow: 0 8px 25px rgba(30, 58, 138, 0.4);
      border-color: rgba(255, 255, 255, 0.4);
    }
    
    .header {
      background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
    }
    
    .stats-card {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      color: #1e293b;
      border: 1px solid #e2e8f0;
    }
    
    .stats-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #1e3a8a, #1e40af);
    }
    
    .stats-card:nth-child(2)::before {
      background: linear-gradient(90deg, #7c3aed, #8b5cf6);
    }
    
    .stats-card:nth-child(3)::before {
      background: linear-gradient(90deg, #059669, #10b981);
    }
    
    .table-container {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(30, 58, 138, 0.15);
    }
    
    .data-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .data-table thead {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
    }
    
    .data-table thead th {
      padding: 1rem 1.25rem;
      text-align: left;
      font-weight: 600;
      color: white !important; /* Force white text */
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: none;
    }
    
    .data-table tbody tr {
      transition: all 0.3s ease;
      border-bottom: 1px solid #f1f5f9;
    }
    
    .data-table tbody tr:last-child {
      border-bottom: none;
    }
    
    .data-table tbody tr:hover {
      background-color: #f8fafc;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .data-table tbody td {
      padding: 1.25rem;
      vertical-align: top;
      color: #374151;
    }
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .badge-submitted {
      background: linear-gradient(135deg, #10b981, #34d399);
      color: white;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }
    
    .view-btn {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 10px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
    }
    
    .view-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(30, 58, 138, 0.4);
    }
    
    .export-btn {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
    }
    
    .export-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(30, 58, 138, 0.4);
    }
    
    .filter-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border: 1px solid #e2e8f0;
    }
    
    .filter-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    .search-input {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M18.031 16.617l4.283 4.282-1.415 1.415-4.282-4.283A8.96 8.96 0 0 1 11 20c-4.968 0-9-4.032-9-9s4.032-9 9-9 9 4.032 9 9a8.96 8.96 0 0 1-1.969 5.617zm-2.006-.742A6.977 6.977 0 0 0 18 11c0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132-7 7 7a6.977 6.977 0 0 0 4.875-1.975l.15-.15z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: left 1rem center;
      background-size: 16px;
      padding-left: 2.75rem;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      transition: all 0.2s;
    }
    
    .search-input:focus {
      border-color: #1e40af;
      box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    }
    
    .filter-select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M12 15l-4.243-4.243 1.415-1.414L12 12.172l2.828-2.829 1.415 1.414z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 16px;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      transition: all 0.2s;
    }
    
    .filter-select:focus {
      border-color: #1e40af;
      box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    }
    
    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(8px);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .modal-overlay.active {
      display: flex;
    }
    
    .modal-content {
      background-color: white;
      border-radius: 20px;
      width: 100%;
      max-width: 1000px;
      max-height: 90vh;
      overflow: hidden;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    
    .modal-header {
      padding: 1.5rem;
      border-bottom: 1px solid #e5e7eb;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      background: white;
      z-index: 10;
    }
    
    .modal-body {
      padding: 1.5rem;
      overflow-y: auto;
      max-height: calc(90vh - 130px);
    }
    
    .modal-footer {
      padding: 1.5rem;
      border-top: 1px solid #e5e7eb;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      position: sticky;
      bottom: 0;
      background: white;
      z-index: 10;
    }
    
    .profile-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border: 1px solid #e2e8f0;
    }
    
    .profile-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    .training-item {
      background: #f8fafc;
      border-radius: 12px;
      padding: 1rem;
      margin-bottom: 1rem;
      border: 1px solid #e2e8f0;
    }
    
    .print-btn {
      background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
      color: white;
      padding: 0.5rem 1.25rem;
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }
    
    .print-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    
    .idp-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    
    .form-card {
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      overflow: hidden;
      background: white;
    }
    
    .accordion-toggle {
      background: none;
      border: none;
      width: 100%;
      text-align: left;
      padding: 20px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background-color 0.2s;
    }
    
    .accordion-toggle:hover {
      background-color: #f9fafb;
    }
    
    .accordion-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
    }
    
    .accordion-content.expanded {
      max-height: 5000px;
    }
    
    .status-submitted {
      background: linear-gradient(135deg, #10b981, #34d399);
      color: white;
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }
    
    .form-section {
      margin-bottom: 1.5rem;
      padding: 1.25rem;
      background-color: #f9fafb;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
    }
    
    .form-section-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .grid-form {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1rem;
    }
    
    .form-field {
      margin-bottom: 0.75rem;
    }
    
    .form-field label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      color: #4b5563;
      margin-bottom: 0.25rem;
    }
    
    .form-field .value {
      padding: 0.5rem;
      background-color: white;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      min-height: 2.5rem;
      display: flex;
      align-items: center;
    }
    
    .table-responsive {
      overflow-x: auto;
      margin: 1rem 0;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }
    
    /* FIXED: Modal table styles with proper text visibility */
    .modal-table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
    }
    
    .modal-table th {
      background-color: #f3f4f6 !important; /* Light gray background */
      padding: 0.75rem;
      text-align: left;
      font-weight: 500;
      font-size: 0.875rem;
      color: #374151 !important; /* Dark text for headers */
      border: 1px solid #d1d5db;
    }
    
    .modal-table td {
      padding: 0.75rem;
      border: 1px solid #e5e7eb;
      background-color: white !important; /* White background for cells */
      color: #374151 !important; /* Dark text for content */
    }
    
    .modal-table tr:nth-child(even) td {
      background-color: #f9fafb !important; /* Light gray for even rows */
    }
    
    .modal-table tr:hover td {
      background-color: #f3f4f6 !important; /* Slightly darker on hover */
    }
    
    .signature-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-top: 1rem;
    }
    
    .signature-box {
      padding: 1rem;
      background-color: white;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      text-align: center;
    }
    
    .signature-name {
      font-weight: 600;
      margin-top: 0.5rem;
      padding-top: 0.5rem;
      border-top: 1px dashed #d1d5db;
    }
    
    .signature-date {
      font-size: 0.875rem;
      color: #6b7280;
      margin-top: 0.25rem;
    }
    
    .empty-row {
      text-align: center;
      color: #9ca3af;
      font-style: italic;
      padding: 1rem;
    }
    
    .checkbox-custom {
      width: 18px;
      height: 18px;
      accent-color: #1e40af;
    }
    
    .department-badge {
      background: linear-gradient(135deg, #1e3a8a, #1e40af);
      color: white;
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
    }

    /* New LSPU Theme Styles */
    .lspu-gradient {
      background: linear-gradient(135deg, #059669 0%, #0ea5e9 50%, #92400e 100%);
    }

    .lspu-gradient-text {
      background: linear-gradient(135deg, #059669 0%, #0ea5e9 50%, #92400e 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .submission-count {
      background: linear-gradient(135deg, #059669 0%, #0ea5e9 50%, #92400e 100%);
      color: white;
      padding: 0.25rem 0.5rem;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-left: 0.5rem;
    }

    /* Dropdown styles for filters */
    .dropdown {
      position: relative;
      display: inline-block;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      background-color: white;
      min-width: 200px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
      z-index: 1;
      border-radius: 10px;
      padding: 8px 0;
      max-height: 300px;
      overflow-y: auto;
    }

    .dropdown-content a {
      color: #374151;
      padding: 10px 16px;
      text-decoration: none;
      display: block;
      transition: background-color 0.2s;
    }

    .dropdown-content a:hover {
      background-color: #f1f5f9;
    }

    .dropdown:hover .dropdown-content {
      display: block;
    }

    .filter-dropdown-btn {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 10px 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      transition: all 0.2s;
    }

    .filter-dropdown-btn:hover {
      border-color: #1e40af;
    }

    .filter-dropdown-btn i {
      transition: transform 0.2s;
    }

    .dropdown:hover .filter-dropdown-btn i {
      transform: rotate(180deg);
    }

    .filter-selected {
      color: #1e40af;
      font-weight: 500;
    }
  </style>
</head>

<body class="min-h-screen">
<div class="flex h-screen overflow-hidden">
  <!-- Sidebar -->
  <aside class="w-80 sidebar border-r border-white/20 flex-shrink-0 relative z-10">
    <div class="h-full flex flex-col">
      <!-- LSPU Header -->
      <div class="p-6 border-b border-white/20">
        <div class="flex items-center space-x-4">
          <div class="logo-container">
            <img src="images/lspu-logo.png" alt="LSPU Logo" class="w-12 h-12 rounded-xl bg-white p-1" 
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
            <div class="w-12 h-12 lspu-gradient rounded-xl flex items-center justify-center backdrop-blur-sm" style="display: none;">
              <i class="ri-government-line text-white text-xl"></i>
            </div>
          </div>
          <div>
            <h1 class="text-lg font-bold text-white">LSPU Admin</h1>
            <p class="text-white/60 text-sm">IDP Forms</p>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-4 py-6">
        <div class="space-y-2">
          <a href="admin_page.php" 
             class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
            <i class="ri-dashboard-line mr-3 text-lg"></i>
            <span class="text-base">Dashboard</span>
          </a>

          <a href="Assessment Form.php" 
             class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
            <i class="ri-survey-line mr-3 text-lg"></i>
            <span class="text-base">Assessment Forms</span>
          </a>

          <a href="Individual_Development_Plan_Form.php" 
             class="flex items-center px-4 py-3 text-white font-semibold rounded-xl sidebar-link active">
            <i class="ri-contacts-book-2-line mr-3 text-lg"></i>
            <span class="text-base">IDP Forms</span>
            <i class="ri-arrow-right-s-line ml-auto text-lg"></i>
          </a>

          <a href="Evaluation_Form.php" 
             class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
            <i class="ri-file-search-line mr-3 text-lg"></i>
            <span class="text-base">Evaluation Forms</span>
          </a>
        </div>
      </nav>

      <!-- Sign Out -->
      <div class="p-4 border-t border-white/20">
        <a href="homepage.php" 
           class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link hover:bg-red-500/30 border border-red-500/30">
          <i class="ri-logout-box-line mr-3 text-lg"></i>
          <span class="text-base">Sign Out</span>
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 overflow-auto">
    <!-- Header -->
    <header class="header border-b border-white/20">
      <div class="flex justify-between items-center px-8 py-6">
        <div>
          <h1 class="text-3xl font-bold text-white">IDP Submissions - LSPU</h1>
          <p class="text-white/70 text-lg mt-2">View and manage all submitted Individual Development Plans</p>
        </div>
        <div class="flex items-center space-x-4">
          <div class="text-right">
            <p class="text-white/80 text-sm font-semibold">Today is</p>
            <p class="text-white font-bold text-lg"><?php echo date('F j, Y'); ?></p>
          </div>
          <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm border border-white/30">
            <i class="ri-calendar-2-line text-white text-xl"></i>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <div class="p-8">
      <div class="max-w-7xl mx-auto">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <!-- Total Employees -->
          <div class="card stats-card">
            <div class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-gray-600 text-sm font-medium">Total Employees</p>
                  <h3 class="text-4xl font-bold text-gray-800 mt-2"><?php echo count($employees); ?></h3>
                  <p class="text-gray-500 text-xs mt-1">Employees with submitted IDPs</p>
                </div>
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center shadow-lg">
                  <i class="fas fa-users text-blue-600 text-2xl"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Submissions -->
          <div class="card stats-card">
            <div class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-gray-600 text-sm font-medium">Total Submissions</p>
                  <h3 class="text-4xl font-bold text-gray-800 mt-2">
                    <?php 
                      $total_submissions = 0;
                      foreach ($employees as $employee) {
                        $total_submissions += $employee['total_idps'];
                      }
                      echo $total_submissions;
                    ?>
                  </h3>
                  <p class="text-gray-500 text-xs mt-1">All IDP forms submitted</p>
                </div>
                <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center shadow-lg">
                  <i class="fas fa-file-alt text-purple-600 text-2xl"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Departments -->
          <div class="card stats-card">
            <div class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-gray-600 text-sm font-medium">Departments</p>
                  <h3 class="text-4xl font-bold text-gray-800 mt-2">
                    <?php
                      $depts = array();
                      foreach ($employees as $employee) {
                        if (!in_array($employee['department'], $depts)) {
                          $depts[] = $employee['department'];
                        }
                      }
                      echo count($depts);
                    ?>
                  </h3>
                  <p class="text-gray-500 text-xs mt-1">Departments represented</p>
                </div>
                <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center shadow-lg">
                  <i class="fas fa-building text-green-600 text-2xl"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <!-- Department Filter -->
          <div class="filter-card">
            <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Department</label>
            <div class="dropdown">
              <button class="filter-dropdown-btn">
                <span class="filter-selected" id="department-selected">All Departments</span>
                <i class="ri-arrow-down-s-line"></i>
              </button>
              <div class="dropdown-content">
                <a href="#" data-department="">All Departments</a>
                <?php foreach ($departments as $abbr => $name): ?>
                  <a href="#" data-department="<?= $abbr ?>"><?= htmlspecialchars($name) ?></a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Status Filter -->
          <div class="filter-card">
            <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Status</label>
            <select class="filter-select appearance-none w-full px-4 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-primary text-gray-700">
              <option>All Status</option>
              <option selected>Submitted</option>
            </select>
          </div>

          <!-- Search Input -->
          <div class="filter-card">
            <label for="search-input" class="block text-sm font-medium text-gray-700 mb-3">Search Employees</label>
            <div class="relative">
              <input type="text" id="search-input" 
                     class="w-full search-input py-2.5 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-primary transition"
                     placeholder="Search by employee name..."
                     value="" />
              <button type="button" onclick="performSearch()" 
                      class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-primary text-white p-1.5 rounded-lg hover:bg-secondary transition">
                <i class="ri-search-line text-sm"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Export Button -->
        <div class="flex justify-end mb-6">
          <button class="export-btn">
            <i class="ri-download-2-line mr-2"></i> Export PDF
          </button>
        </div>

        <!-- IDP Forms Table -->
        <div class="table-container mb-8">
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th class="pl-6">Employee</th>
                  <th>Position</th>
                  <th>Department</th>
                  <th>IDPs Submitted</th>
                  <th>Last Submission</th>
                  <th class="pr-6">Actions</th>
                </tr>
              </thead>
              <tbody id="forms-table-body">
                <?php if (empty($employees)): ?>
                  <tr>
                    <td colspan="6" class="px-6 py-8 text-center">
                      <div class="flex flex-col items-center justify-center text-gray-400">
                        <i class="ri-file-list-3-line text-4xl mb-3"></i>
                        <p class="text-lg font-medium">No IDP submissions found</p>
                        <p class="text-sm mt-1">Employees will appear here once they submit their IDP forms</p>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($employees as $employee): 
                    $department_name = $departments[$employee['department']] ?? $employee['department'];
                  ?>
                    <tr class="employee-item" data-department="<?= htmlspecialchars($employee['department']) ?>">
                      <td class="pl-6 py-4">
                        <div class="flex items-center">
                          <div class="bg-blue-100 text-blue-600 rounded-full w-10 h-10 flex items-center justify-center mr-3">
                            <i class="ri-user-line"></i>
                          </div>
                          <div>
                            <div class="font-medium text-gray-800"><?= htmlspecialchars($employee['name']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="py-4">
                        <span class="text-sm text-gray-700"><?= htmlspecialchars($employee['position']) ?></span>
                      </td>
                      <td class="py-4">
                        <span class="department-badge"><?= htmlspecialchars($department_name) ?></span>
                      </td>
                      <td class="py-4">
                        <span class="font-semibold text-primary text-lg"><?= $employee['total_idps'] ?></span>
                      </td>
                      <td class="py-4 text-sm text-gray-500">
                        <?php
                        if ($employee['last_submission']) {
                            echo date('M j, Y', strtotime($employee['last_submission']));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                      </td>
                      <td class="pr-6 py-4">
                        <button class="view-btn view-idp-btn" data-user-id="<?= $employee['user_id'] ?>" data-user-name="<?= htmlspecialchars($employee['name']) ?>">
                          <i class="ri-eye-line mr-1.5"></i> View
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-6">
          <div class="text-sm text-gray-500">
            Showing <span class="font-medium">1</span> to <span class="font-medium"><?= count($employees) ?></span> of <span class="font-medium"><?= count($employees) ?></span> results
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- View IDP Modal -->
<div class="modal-overlay" id="view-idp-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="text-xl font-bold text-gray-800" id="modal-employee-name">Employee IDP Forms</h3>
      <button type="button" class="text-gray-400 hover:text-gray-500 text-2xl close-modal-btn">
        <i class="ri-close-line"></i>
      </button>
    </div>
    <div class="modal-body">
      <div class="idp-list" id="idp-forms-list">
        <!-- IDP forms will be loaded here -->
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors close-modal-btn">
        Close
      </button>
      <button type="button" class="print-btn" id="print-idp-btn">
        <i class="ri-printer-line mr-2"></i> Print
      </button>
    </div>
  </div>
</div>

<script>
  // Filter and search functionality
  document.addEventListener('DOMContentLoaded', function() {
    const departmentLinks = document.querySelectorAll('.dropdown-content a[data-department]');
    const searchInput = document.getElementById('search-input');
    const formsTableBody = document.getElementById('forms-table-body');
    const departmentSelected = document.getElementById('department-selected');
    
    let currentDepartment = '';
    
    // Department filter functionality
    departmentLinks.forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const department = this.getAttribute('data-department');
        currentDepartment = department;
        
        // Update selected department text
        if (department === '') {
          departmentSelected.textContent = 'All Departments';
        } else {
          departmentSelected.textContent = this.textContent;
        }
        
        filterEmployees();
      });
    });
    
    function filterEmployees() {
      const searchTerm = searchInput.value.toLowerCase();
      
      const rows = formsTableBody.querySelectorAll('.employee-item');
      
      rows.forEach(row => {
        const department = row.getAttribute('data-department');
        const text = row.textContent.toLowerCase();
        
        const departmentMatch = !currentDepartment || department === currentDepartment;
        const searchMatch = !searchTerm || text.includes(searchTerm);
        
        if (departmentMatch && searchMatch) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }
    
    // Manual search functionality
    function performSearch() {
      filterEmployees();
    }
    
    // Enter key handler for search
    searchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        performSearch();
      }
    });
    
    // View IDP button functionality
    const viewButtons = document.querySelectorAll('.view-idp-btn');
    const modal = document.getElementById('view-idp-modal');
    const modalContent = document.getElementById('idp-forms-list');
    const closeButtons = document.querySelectorAll('.close-modal-btn');
    const modalPrintBtn = document.getElementById('print-idp-btn');
    
    viewButtons.forEach(button => {
      button.addEventListener('click', function() {
        const userId = this.getAttribute('data-user-id');
        const userName = this.getAttribute('data-user-name');
        showIDPModal(userId, userName);
      });
    });
    
    // Close modal functionality
    closeButtons.forEach(button => {
      button.addEventListener('click', function() {
        modal.classList.remove('active');
      });
    });
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        modal.classList.remove('active');
      }
    });
    
    // Print button functionality
    modalPrintBtn.addEventListener('click', function() {
      const userId = this.getAttribute('data-user-id');
      const formId = this.getAttribute('data-form-id');
      
      if (formId) {
        // Open the printable PDF in a new window
        window.open(`Individual_Development_Plan_pdf.php?form_id=${formId}`, '_blank');
      } else {
        alert('Please select a specific IDP form to print');
      }
    });
  });
  
  // Modal functions
  function showIDPModal(userId, userName) {
    const modal = document.getElementById('view-idp-modal');
    const modalTitle = document.getElementById('modal-employee-name');
    const modalContent = document.getElementById('idp-forms-list');
    
    // Show loading state
    modalTitle.textContent = `Loading IDP Forms for ${userName}...`;
    modalContent.innerHTML = `
      <div class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    `;
    
    // Show modal
    modal.classList.add('active');
    
    // Fetch IDP forms via AJAX
    fetch(`get_user_idp_forms.php?user_id=${userId}`)
      .then(response => response.json())
      .then(data => {
        modalTitle.textContent = `${userName}'s IDP Forms`;
        
        if (data.length > 0) {
          let html = '';
          data.forEach((form, index) => {
            const formData = JSON.parse(form.form_data);
            const submittedDate = new Date(form.submitted_at).toLocaleDateString('en-US', {
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            });
            
            html += `
              <div class="form-card">
                <button class="accordion-toggle w-full text-left p-6 focus:outline-none" data-id="${form.id}">
                  <div class="flex justify-between items-center">
                    <div>
                      <h3 class="font-bold text-lg text-gray-800">IDP Form #${index + 1}</h3>
                      <p class="text-gray-600">Submitted: ${submittedDate}</p>
                    </div>
                    <div class="flex items-center space-x-4">
                      <span class="status-submitted">
                        Submitted
                      </span>
                      <i class="ri-arrow-down-s-line transition-transform duration-300"></i>
                    </div>
                  </div>
                </button>
                
                <div class="accordion-content px-6" id="content-${form.id}">
                  <!-- Personal Information Section -->
                  <div class="form-section">
                    <h4 class="form-section-title">Personal Information</h4>
                    <div class="grid-form">
                      <div class="form-field">
                        <label>Name</label>
                        <div class="value">${formData.personal_info.name || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Position</label>
                        <div class="value">${formData.personal_info.position || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Salary Grade</label>
                        <div class="value">${formData.personal_info.salary_grade || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Years in Position</label>
                        <div class="value">${formData.personal_info.years_position || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Years in LSPU</label>
                        <div class="value">${formData.personal_info.years_lspu || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Years in Other Office/Agency</label>
                        <div class="value">${formData.personal_info.years_other || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Division</label>
                        <div class="value">${formData.personal_info.division || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Office</label>
                        <div class="value">${formData.personal_info.office || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Office Address</label>
                        <div class="value">${formData.personal_info.address || 'N/A'}</div>
                      </div>
                      <div class="form-field">
                        <label>Supervisor's Name</label>
                        <div class="value">${formData.personal_info.supervisor || 'N/A'}</div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Purpose Section -->
                  <div class="form-section">
                    <h4 class="form-section-title">Purpose</h4>
                    <div class="space-y-2">
                      <div class="flex items-center">
                        <input type="checkbox" id="purpose1-${form.id}" class="checkbox-custom mr-2" ${formData.purpose.purpose1 ? 'checked' : ''} disabled>
                        <label for="purpose1-${form.id}" class="text-gray-700">To meet the competencies in the current positions</label>
                      </div>
                      <div class="flex items-center">
                        <input type="checkbox" id="purpose2-${form.id}" class="checkbox-custom mr-2" ${formData.purpose.purpose2 ? 'checked' : ''} disabled>
                        <label for="purpose2-${form.id}" class="text-gray-700">To increase the level of competencies of current positions</label>
                      </div>
                      <div class="flex items-center">
                        <input type="checkbox" id="purpose3-${form.id}" class="checkbox-custom mr-2" ${formData.purpose.purpose3 ? 'checked' : ''} disabled>
                        <label for="purpose3-${form.id}" class="text-gray-700">To meet the competencies in the next higher position</label>
                      </div>
                      <div class="flex items-center">
                        <input type="checkbox" id="purpose4-${form.id}" class="checkbox-custom mr-2" ${formData.purpose.purpose4 ? 'checked' : ''} disabled>
                        <label for="purpose4-${form.id}" class="text-gray-700">To acquire new competencies across different functions/position</label>
                      </div>
                      <div class="flex items-center">
                        <input type="checkbox" id="purpose5-${form.id}" class="checkbox-custom mr-2" ${formData.purpose.purpose5 ? 'checked' : ''} disabled>
                        <label for="purpose5-${form.id}" class="text-gray-700">Others, please specify:</label>
                        <span class="ml-2 text-gray-800">${formData.purpose.purpose_other || 'N/A'}</span>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Long Term Goals Section -->
                  <div class="form-section">
                    <h4 class="form-section-title">Training/Development Interventions for Long Term Goals (Next Five Years)</h4>
                    <div class="table-responsive">
                      <table class="modal-table">
                        <thead>
                          <tr>
                            <th>Area of Development</th>
                            <th>Development Activity</th>
                            <th>Target Completion Date</th>
                            <th>Completion Stage</th>
                          </tr>
                        </thead>
                        <tbody>
                          ${formData.long_term_goals && formData.long_term_goals.length > 0 ? 
                            formData.long_term_goals.map(goal => `
                              <tr>
                                <td>${goal.area || 'N/A'}</td>
                                <td>${goal.activity || 'N/A'}</td>
                                <td>${goal.target_date || 'N/A'}</td>
                                <td>${goal.stage || 'N/A'}</td>
                              </tr>
                            `).join('') : 
                            `<tr><td colspan="4" class="empty-row">No long-term goals specified</td></tr>`
                          }
                        </tbody>
                      </table>
                    </div>
                  </div>
                  
                  <!-- Short Term Goals Section -->
                  <div class="form-section">
                    <h4 class="form-section-title">Short Term Development Goals Next Year</h4>
                    <div class="table-responsive">
                      <table class="modal-table">
                        <thead>
                          <tr>
                            <th>Area of Development</th>
                            <th>Priority for Learning and Development Program (LDP)</th>
                            <th>Development Activity</th>
                            <th>Target Completion Date</th>
                            <th>Who is Responsible</th>
                            <th>Completion Stage</th>
                          </tr>
                        </thead>
                        <tbody>
                          ${formData.short_term_goals && formData.short_term_goals.length > 0 ? 
                            formData.short_term_goals.map(goal => `
                              <tr>
                                <td>${goal.area || 'N/A'}</td>
                                <td>${goal.priority || 'N/A'}</td>
                                <td>${goal.activity || 'N/A'}</td>
                                <td>${goal.target_date || 'N/A'}</td>
                                <td>${goal.responsible || 'N/A'}</td>
                                <td>${goal.stage || 'N/A'}</td>
                              </tr>
                            `).join('') : 
                            `<tr><td colspan="6" class="empty-row">No short-term goals specified</td></tr>`
                          }
                        </tbody>
                      </table>
                    </div>
                  </div>
                  
                  <!-- Certification Section -->
                  <div class="form-section">
                    <h4 class="form-section-title">Certification and Commitment</h4>
                    <div class="signature-grid">
                      <div class="signature-box">
                        <div>Employee Name</div>
                        <div class="signature-name">${formData.certification.employee_name || 'N/A'}</div>
                        <div class="signature-date">Date: ${formData.certification.employee_date || 'N/A'}</div>
                      </div>
                      <div class="signature-box">
                        <div>Supervisor Name</div>
                        <div class="signature-name">${formData.certification.supervisor_name || 'N/A'}</div>
                        <div class="signature-date">Date: ${formData.certification.supervisor_date || 'N/A'}</div>
                      </div>
                      <div class="signature-box">
                        <div>Director Name</div>
                        <div class="signature-name">${formData.certification.director_name || 'N/A'}</div>
                        <div class="signature-date">Date: ${formData.certification.director_date || 'N/A'}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            `;
          });
          modalContent.innerHTML = html;
          
          // Update print button with user ID
          document.getElementById('print-idp-btn').setAttribute('data-user-id', userId);
          
          // Add event listeners to accordion toggles
          document.querySelectorAll('.accordion-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
              const contentId = this.getAttribute('data-id');
              const content = document.getElementById(`content-${contentId}`);
              const icon = this.querySelector('i');
              
              content.classList.toggle('expanded');
              icon.classList.toggle('ri-arrow-down-s-line');
              icon.classList.toggle('ri-arrow-up-s-line');
              
              // Update print button with form ID when a form is expanded
              if (content.classList.contains('expanded')) {
                document.getElementById('print-idp-btn').setAttribute('data-form-id', contentId);
              }
            });
          });
        } else {
          modalContent.innerHTML = `
            <div class="text-center py-8 text-gray-500">
              <i class="ri-file-list-3-line text-4xl text-gray-300 mb-3"></i>
              <p class="text-lg">No IDP forms found</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = `
          <div class="text-center py-8 text-gray-500">
            <i class="ri-error-warning-line text-4xl text-red-300 mb-3"></i>
            <p class="text-lg">Error loading IDP forms</p>
            <p class="text-sm mt-1">Please try again later</p>
          </div>
        `;
      });
  }
</script>
</body>
</html>