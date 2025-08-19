<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Get employees who have submitted IDP forms with their details
$query = "SELECT 
            u.name, 
            ip.position, 
            u.department,
            COUNT(f.id) as total_idps,
            MAX(f.submitted_at) as last_submission
          FROM idp_forms f
          JOIN users u ON f.user_id = u.id
          JOIN idp_personal_info ip ON f.id = ip.form_id
          GROUP BY u.id, u.name, ip.position, u.department
          ORDER BY u.name ASC";

$result = $con->query($query);
$employees = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
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
            primary: '#6366f1',
            secondary: '#818cf8',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6'
          },
          borderRadius: {
            DEFAULT: '8px',
            'button': '8px'
          },
          fontFamily: {
            'poppins': ['Poppins', 'sans-serif']
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
      background-color: #f3f4f6;
    }
    .shadow-custom {
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .idp-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    .idp-table th {
      background-color: #f8fafc;
      font-weight: 600;
      color: #1e293b;
      position: sticky;
      top: 0;
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
    }
    .idp-table td {
      padding: 1rem;
      text-align: left;
      vertical-align: top;
      border-bottom: 1px solid #e2e8f0;
      background-color: white;
    }
    .idp-table tr:hover td {
      background-color: #f8fafc;
    }
    .action-btn {
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
    }
    .view-btn {
      background-color: #e0e7ff;
      color: #4f46e5;
    }
    .view-btn:hover {
      background-color: #c7d2fe;
    }
    .last-submission {
      font-size: 0.875rem;
      color: #6b7280;
    }
  </style>
</head>

<body>
<div class="flex h-screen">
  <!-- Sidebar -->
  <aside class="w-64 bg-blue-900 text-white shadow-sm">
    <div class="h-full flex flex-col">
      <div class="p-6 flex items-center">
        <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2" />
        <a href="admin_page.php" class="text-lg font-semibold text-white">Admin Dashboard</a>
      </div>
      <nav class="flex-1 px-4">
        <div class="space-y-2">
          <a href="admin_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-dashboard-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">Dashboard</span>
          </a>
          <a href="Assessment Form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">Assessment Forms</span>
          </a>
          <a href="Individual Development Plan Form.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 text-white hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            <span class="whitespace-nowrap">IDP Forms</span>
          </a>
        </div>
      </nav>
      <div class="p-4">
        <a href="logout.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-red-500 text-white transition-all">
          <i class="ri-logout-box-line mr-3"></i>
          Sign Out
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto">
    <div class="container mx-auto px-6 py-8">
      <!-- Header Section -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">IDP Submissions</h1>
          <p class="text-gray-600 mt-1">Employees with submitted IDP forms</p>
        </div>
        <div class="mt-4 md:mt-0">
          <button class="bg-primary hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-download mr-2"></i> Export Data
          </button>
        </div>
      </div>

      <!-- IDP Forms Table -->
      <div class="bg-white rounded-xl shadow-custom overflow-hidden">
        <div class="p-6">
          <!-- Filters -->
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0">
            <div class="flex items-center space-x-4">
              <div class="relative">
                <select id="department-filter" class="appearance-none bg-gray-100 border border-gray-200 rounded-lg px-4 py-2 pr-8 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                  <option value="">All Departments</option>
                  <?php
                  // Get departments from users who submitted IDP forms
                  $dept_query = "SELECT DISTINCT u.department 
                               FROM idp_forms f
                               JOIN users u ON f.user_id = u.id
                               WHERE u.department IS NOT NULL AND u.department != ''";
                  $dept_result = $con->query($dept_query);
                  if ($dept_result->num_rows > 0) {
                      while ($dept = $dept_result->fetch_assoc()) {
                          echo '<option value="' . htmlspecialchars($dept['department']) . '">' . htmlspecialchars($dept['department']) . '</option>';
                      }
                  }
                  ?>
                </select>
                <i class="ri-arrow-down-s-line absolute right-3 top-2.5 text-gray-500"></i>
              </div>
            </div>
            <div class="relative w-full md:w-64">
              <input type="text" id="search-input" placeholder="Search..." class="w-full bg-gray-100 border border-gray-200 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
              <i class="ri-search-line absolute left-3 top-2.5 text-gray-500"></i>
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto">
            <table class="idp-table w-full">
              <thead>
                <tr>
                  <th>Employee Name</th>
                  <th>Position</th>
                  <th>Department</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="forms-table-body">
                <?php if (empty($employees)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">No IDP submissions found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($employees as $employee): ?>
                    <tr class="employee-item" data-department="<?= htmlspecialchars($employee['department']) ?>">
                      <td class="font-medium"><?= htmlspecialchars($employee['name']) ?></td>
                      <td><?= htmlspecialchars($employee['position']) ?></td>
                      <td><?= htmlspecialchars($employee['department']) ?></td>
                      <td>
                        <a href="employee_idp_details.php?name=<?= urlencode($employee['name']) ?>" class="action-btn view-btn flex items-center">
                          <i class="ri-eye-line mr-1"></i> View IDPs
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex justify-center mt-6">
            <nav class="inline-flex rounded-md shadow">
              <a href="#" class="px-3 py-2 rounded-l-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Previous</span>
                <i class="ri-arrow-left-line"></i>
              </a>
              <a href="#" class="px-3 py-2 border-t border-b border-gray-300 bg-white text-primary font-medium">1</a>
              <a href="#" class="px-3 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">2</a>
              <a href="#" class="px-3 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">3</a>
              <a href="#" class="px-3 py-2 rounded-r-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="ri-arrow-right-line"></i>
              </a>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </main>
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
  });
</script>
</body>
</html>