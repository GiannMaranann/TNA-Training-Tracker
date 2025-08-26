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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - IDP Submissions</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#4f46e5',
            secondary: '#6366f1',
            accent: '#818cf8',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            light: '#f8fafc',
            dark: '#1e293b'
          },
          borderRadius: {
            'custom': '12px',
            'button': '8px'
          },
          fontFamily: {
            'poppins': ['Poppins', 'sans-serif']
          },
          boxShadow: {
            'custom': '0 4px 20px rgba(0, 0, 0, 0.08)',
            'table': '0 2px 10px rgba(0, 0, 0, 0.05)'
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
      background-color: #f8fafc;
    }
    
    .glass-card {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .idp-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0 8px;
    }
    
    .idp-table thead tr th {
      background-color: #f1f5f9;
      font-weight: 600;
      color: #475569;
      padding: 1rem 1.25rem;
      text-align: left;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .idp-table thead tr th:first-child {
      border-top-left-radius: 12px;
      border-bottom-left-radius: 12px;
    }
    
    .idp-table thead tr th:last-child {
      border-top-right-radius: 12px;
      border-bottom-right-radius: 12px;
    }
    
    .idp-table tbody tr {
      transition: all 0.3s ease;
      border-radius: 12px;
    }
    
    .idp-table tbody tr td {
      padding: 1.25rem;
      background-color: white;
      vertical-align: middle;
    }
    
    .idp-table tbody tr td:first-child {
      border-top-left-radius: 12px;
      border-bottom-left-radius: 12px;
    }
    
    .idp-table tbody tr td:last-child {
      border-top-right-radius: 12px;
      border-bottom-right-radius: 12px;
    }
    
    .idp-table tbody tr:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .idp-table tbody tr:hover td {
      background-color: #f8fafc;
    }
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.75rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .badge-submitted {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .badge-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .action-btn {
      display: inline-flex;
      align-items: center;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      cursor: pointer;
    }
    
    .view-btn {
      background-color: #e0e7ff;
      color: #4f46e5;
    }
    
    .view-btn:hover {
      background-color: #c7d2fe;
      transform: translateY(-1px);
    }
    
    .pagination-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s;
    }
    
    .pagination-btn.active {
      background-color: #4f46e5;
      color: white;
    }
    
    .filter-select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M12 15l-4.243-4.243 1.415-1.414L12 12.172l2.828-2.829 1.415 1.414z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 16px;
    }
    
    .stats-card {
      border-radius: 16px;
      transition: all 0.3s ease;
    }
    
    .stats-card:hover {
      transform: translateY(-5px);
    }
    
    /* Modal Styles */
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
      padding: 20px;
    }
    
    .modal-backdrop.active {
      display: flex;
    }
    
    .modal-content {
      background-color: white;
      border-radius: 16px;
      width: 100%;
      max-width: 1000px;
      max-height: 90vh;
      overflow: hidden;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
    
    .idp-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    
    .form-card {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      overflow: hidden;
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
      background-color: #10b981;
      color: white;
      padding: 0.35rem 0.75rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .readonly-field {
      padding: 8px 12px;
      background-color: #f9fafb;
      border-radius: 6px;
      border: 1px solid #e5e7eb;
      margin-top: 4px;
    }
    
    .checkbox-custom {
      width: 18px;
      height: 18px;
      accent-color: #4f46e5;
    }
    
    .no-forms {
      text-align: center;
      padding: 40px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .no-forms p {
      color: #6b7280;
      font-size: 1.1rem;
    }
    
    /* Print Button Styles */
    .print-btn {
      background-color: #10b981;
      color: white;
    }
    
    .print-btn:hover {
      background-color: #059669;
    }
    
    /* Enhanced Modal Styles */
    .form-section {
      margin-bottom: 1.5rem;
      padding: 1.25rem;
      background-color: #f9fafb;
      border-radius: 8px;
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
      border-radius: 6px;
      border: 1px solid #d1d5db;
      min-height: 2.5rem;
      display: flex;
      align-items: center;
    }
    
    .table-responsive {
      overflow-x: auto;
      margin: 1rem 0;
    }
    
    .data-table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
    }
    
    .data-table th {
      background-color: #f3f4f6;
      padding: 0.75rem;
      text-align: left;
      font-weight: 500;
      font-size: 0.875rem;
      color: #374151;
      border: 1px solid #d1d5db;
    }
    
    .data-table td {
      padding: 0.75rem;
      border: 1px solid #e5e7eb;
    }
    
    .data-table tr:nth-child(even) {
      background-color: #f9fafb;
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
  </style>
</head>

<body class="bg-gray-50">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="w-64 bg-gradient-to-b from-blue-900 to-blue-900 text-white shadow-lg">
    <div class="h-full flex flex-col">
      <div class="p-6 flex items-center">
        <img src="images/lspubg2.png" alt="Logo" class="w-12 h-12 mr-4" />
        <a href="admin_page.php" class="text-lg font-semibold text-white">Admin Dashboard</a>
      </div>
      <nav class="flex-1 px-4 py-4">
        <div class="space-y-3">
          <a href="admin_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg hover:bg-blue-700 transition-all">
            <i class="ri-dashboard-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">Dashboard</span>
          </a>
          <a href="Assessment Form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">Assessment Forms</span>
          </a>
          <a href="Individual Development Plan Form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg bg-blue-700 text-white transition-all">
            <i class="ri-file-text-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">IDP Forms</span>
          </a>
        </div>
      </nav>
      <div class="p-4 mt-auto">
        <a href="homepage.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg hover:bg-red-500 text-white transition-all">
          <i class="ri-logout-box-line mr-3"></i>
          Sign Out
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6">
    <div class="max-w-7xl mx-auto">
      <!-- Header Section -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div>
          <h1 class="text-3xl font-bold text-gray-800">IDP Submissions</h1>
          <p class="text-gray-600 mt-2">View and manage all submitted Individual Development Plans</p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-3">
          <button class="bg-white border border-gray-200 hover:border-gray-300 text-gray-700 px-4 py-2.5 rounded-lg flex items-center shadow-sm hover:shadow-md transition-all">
            <i class="fas fa-filter mr-2"></i> Filter
          </button>
          <button class="bg-primary hover:bg-secondary text-white px-4 py-2.5 rounded-lg flex items-center shadow-md hover:shadow-lg transition-all">
            <i class="fas fa-download mr-2"></i> Export
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="stats-card bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-2xl shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-blue-100 text-sm font-medium">Total Employees</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo count($employees); ?></h3>
            </div>
            <div class="bg-blue-400 p-3 rounded-full">
              <i class="ri-user-line text-xl"></i>
            </div>
          </div>
          <p class="text-blue-100 text-xs mt-3"><i class="ri-arrow-up-line text-success"></i> All employees with submitted IDPs</p>
        </div>
        
        <div class="stats-card bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-2xl shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-purple-100 text-sm font-medium">Total Submissions</p>
              <h3 class="text-2xl font-bold mt-1">
                <?php 
                  $total_submissions = 0;
                  foreach ($employees as $employee) {
                    $total_submissions += $employee['total_idps'];
                  }
                  echo $total_submissions;
                ?>
              </h3>
            </div>
            <div class="bg-purple-400 p-3 rounded-full">
              <i class="ri-file-text-line text-xl"></i>
            </div>
          </div>
          <p class="text-purple-100 text-xs mt-3"><i class="ri-arrow-up-line text-success"></i> All IDP forms submitted</p>
        </div>
        
        <div class="stats-card bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-2xl shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-green-100 text-sm font-medium">Departments</p>
              <h3 class="text-2xl font-bold mt-1">
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
            </div>
            <div class="bg-green-400 p-3 rounded-full">
              <i class="ri-building-line text-xl"></i>
            </div>
          </div>
          <p class="text-green-100 text-xs mt-3"><i class="ri-arrow-up-line text-success"></i> Departments represented</p>
        </div>
      </div>

      <!-- IDP Forms Table -->
      <div class="bg-white rounded-2xl shadow-custom overflow-hidden">
        <div class="p-6">
          <!-- Filters -->
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex flex-wrap items-center gap-3">
              <div class="relative">
                <select id="department-filter" class="filter-select appearance-none bg-light border-0 rounded-lg px-4 py-2.5 pr-10 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-20 text-gray-700 w-full md:w-48">
                  <option value="">All Departments</option>
                  <?php
                  // Get departments from users who submitted IDP forms
                  $dept_query = "SELECT DISTINCT u.department 
                               FROM idp_forms f
                               JOIN users u ON f.user_id = u.id
                               WHERE u.department IS NOT NULL AND u.department != '' AND f.status = 'submitted'";
                  $dept_result = $con->query($dept_query);
                  if ($dept_result->num_rows > 0) {
                      while ($dept = $dept_result->fetch_assoc()) {
                          echo '<option value="' . htmlspecialchars($dept['department']) . '">' . htmlspecialchars($dept['department']) . '</option>';
                      }
                  }
                  ?>
                </select>
              </div>
              
              <div class="relative">
                <select class="filter-select appearance-none bg-light border-0 rounded-lg px-4 py-2.5 pr-10 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-20 text-gray-700 w-full md:w-40">
                  <option>All Status</option>
                  <option selected>Submitted</option>
                </select>
              </div>
            </div>
            
            <div class="relative w-full md:w-64">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="ri-search-line text-gray-400"></i>
              </div>
              <input type="text" id="search-input" placeholder="Search employees..." class="w-full bg-light border-0 rounded-lg px-4 py-2.5 pl-10 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-20 text-gray-700 placeholder-gray-500">
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto rounded-2xl shadow-table">
            <table class="idp-table w-full">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Position</th>
                  <th>Department</th>
                  <th>IDPs Submitted</th>
                  <th>Last Submission</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="forms-table-body">
                <?php if (empty($employees)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-12 text-gray-500">
                      <i class="ri-file-list-3-line text-4xl text-gray-300 mb-3"></i>
                      <p class="text-lg">No IDP submissions found</p>
                      <p class="text-sm mt-1">Employees will appear here once they submit their IDP forms</p>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($employees as $employee): ?>
                    <tr class="employee-item" data-department="<?= htmlspecialchars($employee['department']) ?>">
                      <td>
                        <div class="flex items-center">
                          <div class="bg-blue-100 text-blue-600 rounded-full w-10 h-10 flex items-center justify-center mr-3">
                            <i class="ri-user-line"></i>
                          </div>
                          <div>
                            <div class="font-medium text-gray-800"><?= htmlspecialchars($employee['name']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="text-gray-700"><?= htmlspecialchars($employee['position']) ?></td>
                      <td>
                        <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                          <?= htmlspecialchars($employee['department']) ?>
                        </span>
                      </td>
                      <td>
                        <span class="font-semibold text-primary"><?= $employee['total_idps'] ?></span>
                      </td>
                      <td class="text-sm text-gray-500">
                        <?php
                        if ($employee['last_submission']) {
                            echo date('M j, Y', strtotime($employee['last_submission']));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                      </td>
                      <td>
                        <button class="action-btn view-btn view-idp-btn" data-user-id="<?= $employee['user_id'] ?>" data-user-name="<?= htmlspecialchars($employee['name']) ?>">
                          <i class="ri-eye-line mr-1.5"></i> View
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex flex-col md:flex-row justify-between items-center mt-8 space-y-4 md:space-y-0">
            <div class="text-sm text-gray-500">
              Showing <span class="font-medium">1</span> to <span class="font-medium"><?= count($employees) ?></span> of <span class="font-medium"><?= count($employees) ?></span> results
            </div>
            
            <div class="flex space-x-1">
              <a href="#" class="pagination-btn bg-white border border-gray-200 text-gray-500 hover:bg-gray-50">
                <i class="ri-arrow-left-s-line"></i>
              </a>
              <a href="#" class="pagination-btn active">1</a>
              <a href="#" class="pagination-btn bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">2</a>
              <a href="#" class="pagination-btn bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">3</a>
              <a href="#" class="pagination-btn bg-white border border-gray-200 text-gray-500 hover:bg-gray-50">
                <i class="ri-arrow-right-s-line"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- View IDP Modal -->
<div class="modal-backdrop" id="view-idp-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="text-xl font-bold text-gray-800" id="modal-employee-name">Employee IDP Forms</h3>
      <button type="button" class="text-gray-400 hover:text-gray-500 text-2xl" onclick="hideModal()">
        <i class="ri-close-line"></i>
      </button>
    </div>
    <div class="modal-body">
      <div class="idp-list" id="idp-forms-list">
        <!-- IDP forms will be loaded here -->
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors" onclick="hideModal()">
        Close
      </button>
      <button type="button" class="action-btn print-btn" id="print-idp-btn">
        <i class="ri-printer-line mr-1.5"></i> Print
      </button>
    </div>
  </div>
</div>

<script>
  // Filter and search functionality
  document.addEventListener('DOMContentLoaded', function() {
    const departmentFilter = document.getElementById('department-filter');
    const searchInput = document.getElementById('search-input');
    const formsTableBody = document.getElementById('forms-table-body');
    
    function filterEmployees() {
      const departmentValue = departmentFilter.value.toLowerCase();
      const searchTerm = searchInput.value.toLowerCase();
      
      const rows = formsTableBody.querySelectorAll('.employee-item');
      
      rows.forEach(row => {
        const department = row.getAttribute('data-department').toLowerCase();
        const text = row.textContent.toLowerCase();
        
        const departmentMatch = !departmentValue || department.includes(departmentValue);
        const searchMatch = !searchTerm || text.includes(searchTerm);
        
        if (departmentMatch && searchMatch) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }
    
    departmentFilter.addEventListener('change', filterEmployees);
    searchInput.addEventListener('input', filterEmployees);
    
    // View IDP button functionality
    const viewButtons = document.querySelectorAll('.view-idp-btn');
    viewButtons.forEach(button => {
      button.addEventListener('click', function() {
        const userId = this.getAttribute('data-user-id');
        const userName = this.getAttribute('data-user-name');
        showIDPModal(userId, userName);
      });
    });
    
    // Print button functionality
    document.getElementById('print-idp-btn').addEventListener('click', function() {
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
    // Show loading state
    document.getElementById('modal-employee-name').textContent = `Loading IDP Forms for ${userName}...`;
    document.getElementById('idp-forms-list').innerHTML = `
      <div class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    `;
    
    // Show modal
    document.getElementById('view-idp-modal').classList.add('active');
    
    // Fetch IDP forms via AJAX
    fetch(`get_user_idp_forms.php?user_id=${userId}`)
      .then(response => response.json())
      .then(data => {
        document.getElementById('modal-employee-name').textContent = `${userName}'s IDP Forms`;
        
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
              <div class="form-card bg-white rounded-lg shadow overflow-hidden">
                <button class="accordion-toggle w-full text-left p-6 focus:outline-none" data-id="${form.id}">
                  <div class="flex justify-between items-center">
                    <div>
                      <h3 class="font-bold text-lg">IDP Form #${index + 1}</h3>
                      <p class="text-gray-600">Submitted: ${submittedDate}</p>
                    </div>
                    <div class="flex items-center space-x-4">
                      <span class="px-3 py-1 rounded-full text-sm font-medium status-submitted">
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
                      <table class="data-table">
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
                      <table class="data-table">
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
          document.getElementById('idp-forms-list').innerHTML = html;
          
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
          document.getElementById('idp-forms-list').innerHTML = `
            <div class="text-center py-8 text-gray-500">
              <i class="ri-file-list-3-line text-4xl text-gray-300 mb-3"></i>
              <p class="text-lg">No IDP forms found</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('idp-forms-list').innerHTML = `
          <div class="text-center py-8 text-gray-500">
            <i class="ri-error-warning-line text-4xl text-red-300 mb-3"></i>
            <p class="text-lg">Error loading IDP forms</p>
            <p class="text-sm mt-1">Please try again later</p>
          </div>
        `;
      });
  }
  
  function hideModal() {
    document.getElementById('view-idp-modal').classList.remove('active');
  }
  
  // Close modal when clicking outside
  document.getElementById('view-idp-modal').addEventListener('click', function(e) {
    if (e.target === this) {
      hideModal();
    }
  });
</script>
</body>
</html>