<?php
include 'config.php';

// Get selected year or default to current year
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : 0; // 0 means all months
$search = isset($_GET['search']) ? $con->real_escape_string($_GET['search']) : '';
$teaching_status = isset($_GET['teaching_status']) ? $con->real_escape_string($_GET['teaching_status']) : '';
$view_id = isset($_GET['view_id']) ? intval($_GET['view_id']) : 0;

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delete_id = intval($_POST['delete_id']);
    if ($delete_id > 0) {
        $con->query("DELETE FROM assessments WHERE id = $delete_id");
        $redirect_url = $_SERVER['PHP_SELF'] . "?year=" . $selected_year;
        if ($selected_month > 0) {
            $redirect_url .= "&month=" . $selected_month;
        }
        if (!empty($search)) {
            $redirect_url .= "&search=" . urlencode($search);
        }
        if (!empty($teaching_status)) {
            $redirect_url .= "&teaching_status=" . urlencode($teaching_status);
        }
        header("Location: $redirect_url");
        exit();
    }
}

// Get all distinct years from submission_date
$years_result = $con->query("SELECT DISTINCT YEAR(submission_date) AS year FROM assessments WHERE submission_date IS NOT NULL ORDER BY year ASC");

// Get distinct months for the selected year
$months_result = $con->query("SELECT DISTINCT MONTH(submission_date) AS month FROM assessments WHERE YEAR(submission_date) = $selected_year AND submission_date IS NOT NULL ORDER BY month ASC");

// Get distinct teaching statuses for filter
$statuses_result = $con->query("SELECT DISTINCT teaching_status FROM users WHERE teaching_status IS NOT NULL AND teaching_status != ''");

// Get detailed data for view if view_id is set
$view_data = null;
if ($view_id > 0) {
    $view_sql = "SELECT 
                    u.*, 
                    a.training_history, 
                    a.desired_skills, 
                    a.comments, 
                    a.submission_date,
                    a.id as assessment_id
                FROM users u
                JOIN assessments a ON u.id = a.user_id
                WHERE a.id = $view_id";
    $view_result = $con->query($view_sql);
    if ($view_result && $view_result->num_rows > 0) {
        $view_data = $view_result->fetch_assoc();
    }
}

// Pagination setup
$perPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Count total rows for pagination
$count_sql = "SELECT COUNT(*) AS total
              FROM users u
              LEFT JOIN assessments a ON u.id = a.user_id
              WHERE YEAR(a.submission_date) = $selected_year";

if ($selected_month > 0) {
    $count_sql .= " AND MONTH(a.submission_date) = $selected_month";
}

if (!empty($search)) {
    $search_escaped = $con->real_escape_string($search);
    $count_sql .= " AND (u.name LIKE '%$search_escaped%' OR u.department LIKE '%$search_escaped%')";
}

if (!empty($teaching_status)) {
    $count_sql .= " AND u.teaching_status = '$teaching_status'";
}

$count_result = $con->query($count_sql);
$total_rows = ($count_result && $count_result->num_rows > 0) ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $perPage);

// Function to extract surname for sorting
function getSurname($name) {
    $parts = explode(' ', $name);
    return end($parts); // Get the last part as surname
}

// Main paginated query - modified to sort by surname
$sql = "SELECT 
            u.name,
            u.department,
            u.teaching_status,
            a.training_history,
            a.desired_skills,
            a.comments,
            a.submission_date,
            a.id
        FROM users u
        LEFT JOIN assessments a ON u.id = a.user_id
        WHERE YEAR(a.submission_date) = $selected_year";

if ($selected_month > 0) {
    $sql .= " AND MONTH(a.submission_date) = $selected_month";
}

if (!empty($search)) {
    $sql .= " AND (u.name LIKE '%$search%' OR u.department LIKE '%$search%')";
}

if (!empty($teaching_status)) {
    $sql .= " AND u.teaching_status = '$teaching_status'";
}

// Modified ORDER BY to sort by surname
$sql .= " ORDER BY SUBSTRING_INDEX(u.name, ' ', -1) ASC, u.name ASC LIMIT $perPage OFFSET $offset";
$result = $con->query($sql);

if ($result === false) {
    die("Database query failed: " . $con->error);
}

// All rows for export (no LIMIT/OFFSET) - also sorted by surname
$export_sql = "SELECT 
                  u.name,
                  u.department,
                  u.teaching_status,
                  a.training_history,
                  a.desired_skills,
                  a.comments,
                  a.submission_date,
                  a.id
              FROM users u
              LEFT JOIN assessments a ON u.id = a.user_id
              WHERE YEAR(a.submission_date) = $selected_year";

if ($selected_month > 0) {
    $export_sql .= " AND MONTH(a.submission_date) = $selected_month";
}

if (!empty($search)) {
    $export_sql .= " AND (u.name LIKE '%$search%' OR u.department LIKE '%$search%')";
}

if (!empty($teaching_status)) {
    $export_sql .= " AND u.teaching_status = '$teaching_status'";
}

$export_sql .= " ORDER BY SUBSTRING_INDEX(u.name, ' ', -1) ASC, u.name ASC";
$export_result = $con->query($export_sql);
$all_rows_for_export = $export_result ? $export_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Assessment Form Dashboard</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#4f46e5',
            secondary: '#6366f1',
            accent: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            dark: '#1e293b',
            light: '#f8fafc',
            'gradient-start': '#1e3a8a',
            'gradient-end': '#1e40af'
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
            'table': '0 2px 10px rgba(0, 0, 0, 0.05)',
            'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
            'hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)'
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      min-height: 100vh;
    }
    
    .glass-card {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .sidebar {
      background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .stats-card {
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
      border-radius: 16px;
      transition: all 0.3s ease;
    }
    
    .stats-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(79, 70, 229, 0.2);
    }
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.75rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .status-teaching {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .status-nonteaching {
      background-color: #fee2e2;
      color: #991b1b;
    }
    
    .table-container {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .data-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .data-table thead {
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
    
    .data-table thead th {
      padding: 1rem 1.25rem;
      text-align: left;
      font-weight: 600;
      color: #374151;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid #e5e7eb;
    }
    
    .data-table tbody tr {
      transition: all 0.3s ease;
      border-bottom: 1px solid #f3f4f6;
    }
    
    .data-table tbody tr:last-child {
      border-bottom: none;
    }
    
    .data-table tbody tr:hover {
      background-color: #f8fafc;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .data-table tbody td {
      padding: 1.25rem;
      vertical-align: top;
    }
    
    .filter-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    
    .filter-card:hover {
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
      color: white;
    }
    
    .view-btn {
      background-color: #e0e7ff;
      color: #4f46e5;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
    }
    
    .view-btn:hover {
      background-color: #c7d2fe;
      transform: translateY(-1px);
    }
    
    /* Enhanced Modal Styles */
    .modal-overlay {
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
    
    .modal-overlay.active {
      display: flex;
    }
    
    .modal-content {
      background-color: white;
      border-radius: 16px;
      width: 100%;
      max-width: 900px;
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
    
    .profile-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }
    
    .profile-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    .training-item {
      background: #f8fafc;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1rem;
    }
    
    .print-btn {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
    }
    
    .print-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .export-btn {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
    }
    
    .export-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .filter-tag {
      display: inline-flex;
      align-items: center;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      cursor: pointer;
    }
    
    .filter-tag.active {
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
      color: white;
    }
    
    .filter-tag:not(.active) {
      background-color: #e5e7eb;
      color: #4b5563;
    }
    
    .filter-tag:not(.active):hover {
      background-color: #d1d5db;
    }
    
    .search-input {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M18.031 16.617l4.283 4.282-1.415 1.415-4.282-4.283A8.96 8.96 0 0 1 11 20c-4.968 0-9-4.032-9-9s4.032-9 9-9 9 4.032 9 9a8.96 8.96 0 0 1-1.969 5.617zm-2.006-.742A6.977 6.977 0 0 0 18 11c0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132 7 7 7a6.977 6.977 0 0 0 4.875-1.975l.15-.15z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: left 1rem center;
      background-size: 16px;
      padding-left: 2.75rem;
    }
  </style>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="sidebar w-64 text-white shadow-sm fixed top-0 left-0 h-screen overflow-y-auto z-10">
    <div class="h-full flex flex-col">
      <div class="p-6 flex items-center">
        <img src="images/lspubg2.png" alt="Logo" class="w-12 h-12 mr-4">
        <a href="admin_page.php" class="text-lg font-semibold text-white">Admin Dashboard</a>
      </div>
      <nav class="flex-1 px-4 py-4">
        <div class="space-y-3">
        <!-- Dashboard -->
        <a href="admin_page.php" 
           class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
          <i class="ri-dashboard-2-line w-5 h-5 mr-3"></i>
          <span class="whitespace-nowrap">Dashboard</span>
        </a>
        <!-- Assessment Forms -->
        <a href="Assessment Form.php" 
           class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 text-white hover:bg-blue-700 transition-all">
          <i class="ri-survey-line w-5 h-5 mr-3"></i>
          <span class="whitespace-nowrap">Assessment Forms</span>
        </a>
        <!-- IDP Forms -->
        <a href="Individual_Development_Plan_Form.php" 
           class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
          <i class="ri-contacts-book-2-line w-5 h-5 mr-3"></i>
          <span class="whitespace-nowrap">IDP Forms</span>
        </a>
        <!-- Evaluation Forms -->
        <a href="Evaluation_Form.php" 
           class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
          <i class="ri-file-search-line w-5 h-5 mr-3"></i>
          <span class="whitespace-nowrap">Evaluation Forms</span>
        </a>
        </div>
      </nav>
      <div class="p-4 mt-auto">
        <a href="homepage.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg hover:bg-red-500 text-white transition-all">
          <i class="ri-logout-box-line mr-3"></i> Sign Out
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="ml-64 flex-1 p-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header Section -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div>
          <h1 class="text-3xl font-bold text-gray-800">Assessment Form Submissions</h1>
          <p class="text-gray-600 mt-2">View and manage all submitted assessment forms</p>
        </div>
        <div class="mt-4 md:mt-0">
          <div class="bg-gradient-to-r from-primary to-secondary text-white px-6 py-3 rounded-xl shadow-lg">
            <p class="text-sm font-medium">
              <span class="font-bold text-lg"><?= $total_rows ?></span> records found 
              <?= $selected_month > 0 ? 'for ' . date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)) : 'for ' . htmlspecialchars($selected_year) ?>
            </p>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Year Filter -->
        <div class="filter-card">
          <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Year</label>
          <div class="flex flex-wrap gap-2">
            <?php 
            $years_result->data_seek(0); // Reset pointer
            while ($yearRow = $years_result->fetch_assoc()): ?>
              <a href="?year=<?= urlencode($yearRow['year']) ?>&month=<?= $selected_month ?>&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>"
                 class="filter-tag <?= $selected_year == $yearRow['year'] ? 'active' : '' ?>">
                <?= htmlspecialchars($yearRow['year']) ?>
              </a>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- Month Filter -->
        <div class="filter-card">
          <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Month</label>
          <div class="flex flex-wrap gap-2">
            <a href="?year=<?= $selected_year ?>&month=0&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>"
               class="filter-tag <?= $selected_month == 0 ? 'active' : '' ?>">
              All Months
            </a>
            <?php 
            $months = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
            ];
            
            // Reset pointer if needed
            $months_result->data_seek(0);
            
            while ($monthRow = $months_result->fetch_assoc()): 
                $monthNum = $monthRow['month'];
                $monthName = $months[$monthNum] ?? '';
            ?>
              <a href="?year=<?= $selected_year ?>&month=<?= $monthNum ?>&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>"
                 class="filter-tag <?= $selected_month == $monthNum ? 'active' : '' ?>">
                <?= $monthName ?>
              </a>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- Teaching Status Filter -->
        <div class="filter-card">
          <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Status</label>
          <div class="flex flex-wrap gap-2">
            <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&search=<?= urlencode($search) ?>"
               class="filter-tag <?= empty($teaching_status) ? 'active' : '' ?>">
              All
            </a>
            <?php 
            $statuses_result->data_seek(0);
            while ($statusRow = $statuses_result->fetch_assoc()): ?>
              <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&teaching_status=<?= urlencode($statusRow['teaching_status']) ?>&search=<?= urlencode($search) ?>"
                 class="filter-tag <?= $teaching_status == $statusRow['teaching_status'] ? 'active' : '' ?>">
                <?= htmlspecialchars($statusRow['teaching_status']) ?>
              </a>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- Search Input -->
        <div class="filter-card">
          <label for="search-input" class="block text-sm font-medium text-gray-700 mb-3">Search</label>
          <form method="GET" class="relative">
            <input type="hidden" name="year" value="<?= htmlspecialchars($selected_year) ?>">
            <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
            <input type="hidden" name="teaching_status" value="<?= htmlspecialchars($teaching_status) ?>">
            <input type="search" name="search" id="search-input"
                   class="w-full search-input py-2.5 text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-primary focus:border-primary transition"
                   placeholder="Search by name or department..."
                   value="<?= htmlspecialchars($search) ?>" />
          </form>
        </div>
      </div>

      <!-- Export Button -->
      <div class="flex justify-between items-center mb-6">
        <div class="text-sm text-gray-500">
          Showing page <?= $page ?> of <?= $total_pages ?> (<?= $total_rows ?> total records)
        </div>
        <button onclick="generatePDF()"
                class="export-btn">
          <i class="ri-download-2-line mr-2"></i> Export PDF
        </button>
      </div>

      <!-- Table Section -->
      <div class="table-container mb-8">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="pl-6">Name</th>
                <th>Department</th>
                <th>Status</th>
                <th>Seminars Attended</th>
                <th>Desired Training</th>
                <th class="pr-6">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="pl-6 py-4">
                      <div class="font-medium text-gray-900"><?= htmlspecialchars($row['name']) ?></div>
                      <div class="text-xs text-gray-500 mt-1"><?= date('M d, Y', strtotime($row['submission_date'])) ?></div>
                    </td>
                    <td class="py-4">
                      <span class="text-sm text-gray-700"><?= htmlspecialchars($row['department']) ?></span>
                    </td>
                    <td class="py-4">
                      <?php if (strtolower($row['teaching_status']) == 'teaching'): ?>
                        <span class="status-badge status-teaching">
                          <i class="ri-user-star-fill mr-1"></i> Teaching
                        </span>
                      <?php else: ?>
                        <span class="status-badge status-nonteaching">
                          <i class="ri-user-fill mr-1"></i> Non-Teaching
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="py-4 max-w-xs">
                      <?php
                      if (!empty($row['training_history'])) {
                          $seminars = json_decode($row['training_history'], true);
                          if (is_array($seminars)) {
                              echo '<div class="space-y-2">';
                              foreach ($seminars as $seminar) {
                                  echo '<div class="training-item">';
                                  echo '<p class="font-medium text-gray-800 text-sm">' . htmlspecialchars($seminar['training'] ?? '') . '</p>';
                                  echo '<p class="text-xs text-gray-500 mt-1">' . 
                                       htmlspecialchars($seminar['date'] ?? '') . ' • ' . 
                                       (isset($seminar['start_time']) ? htmlspecialchars($seminar['start_time']) : '') . 
                                       (isset($seminar['end_time']) ? ' - ' . htmlspecialchars($seminar['end_time']) : '') . ' • ' . 
                                       (isset($seminar['duration']) ? htmlspecialchars($seminar['duration']) : '') . ' • ' . 
                                       htmlspecialchars($seminar['venue'] ?? '') . '</p>';
                                  echo '</div>';
                              }
                              echo '</div>';
                          } else {
                              echo '<span class="text-gray-400 text-sm">—</span>';
                          }
                      } else {
                          echo '<span class="text-gray-400 text-sm">—</span>';
                      }
                      ?>
                    </td>
                    <td class="py-4">
                      <div class="text-sm text-gray-700 whitespace-pre-line max-w-xs"><?= nl2br(htmlspecialchars($row['desired_skills'])) ?></div>
                    </td>
                    <td class="pr-6 py-4">
                      <a href="#" class="view-btn view-details-btn" data-id="<?= $row['id'] ?>">
                        <i class="ri-eye-line mr-1.5"></i> View
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="px-6 py-8 text-center">
                    <div class="flex flex-col items-center justify-center text-gray-400">
                      <i class="ri-file-list-3-line text-4xl mb-3"></i>
                      <p class="text-lg font-medium">No submissions found</p>
                      <p class="text-sm mt-1">
                        <?= $selected_month > 0 ? 'for ' . date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)) : 'for ' . htmlspecialchars($selected_year) ?>
                        <?php if (!empty($search) || !empty($teaching_status)): ?>
                          with current filters
                        <?php endif; ?>
                      </p>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="flex justify-between items-center mt-6">
        <div class="text-sm text-gray-500">
          Showing <?= $perPage * ($page - 1) + 1 ?> to <?= min($perPage * $page, $total_rows) ?> of <?= $total_rows ?> entries
        </div>
        <nav class="inline-flex items-center space-x-1" aria-label="Pagination">
          <?php if ($page > 1): ?>
            <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
               class="pagination-btn bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
              <i class="ri-arrow-left-s-line"></i>
            </a>
          <?php else: ?>
            <span class="pagination-btn bg-gray-100 text-gray-400 cursor-not-allowed">
              <i class="ri-arrow-left-s-line"></i>
            </span>
          <?php endif; ?>

          <?php 
          // Show page numbers with ellipsis
          $start_page = max(1, $page - 2);
          $end_page = min($total_pages, $page + 2);
          
          if ($start_page > 1) {
              echo '<a href="?year='.urlencode($selected_year).'&month='.$selected_month.'&page=1&search='.urlencode($search).'&teaching_status='.urlencode($teaching_status).'" class="pagination-btn bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">1</a>';
              if ($start_page > 2) {
                  echo '<span class="pagination-btn bg-transparent border-0 text-gray-500">...</span>';
              }
          }
          
          for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
               class="pagination-btn <?= $page == $i ? 'active' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
              <?= $i ?>
            </a>
          <?php endfor;
          
          if ($end_page < $total_pages) {
              if ($end_page < $total_pages - 1) {
                  echo '<span class="pagination-btn bg-transparent border-0 text-gray-500">...</span>';
              }
              echo '<a href="?year='.urlencode($selected_year).'&month='.$selected_month.'&page='.$total_pages.'&search='.urlencode($search).'&teaching_status='.urlencode($teaching_status).'" class="pagination-btn bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>';
          }
          ?>

          <?php if ($page < $total_pages): ?>
            <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
               class="pagination-btn bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
              <i class="ri-arrow-right-s-line"></i>
            </a>
          <?php else: ?>
            <span class="pagination-btn bg-gray-100 text-gray-400 cursor-not-allowed">
              <i class="ri-arrow-right-s-line"></i>
            </span>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="view-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="text-xl font-bold text-gray-800">Employee Profile</h3>
      <button type="button" class="text-gray-400 hover:text-gray-500 text-2xl close-modal-btn">
        <i class="ri-close-line"></i>
      </button>
    </div>
    <div class="modal-body">
      <div id="modal-content">
        <!-- Content will be loaded here via JavaScript -->
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors close-modal-btn">
        Close
      </button>
      <button type="button" class="print-btn" id="modal-print-btn">
        <i class="ri-printer-line mr-2"></i> Print
      </button>
    </div>
  </div>
</div>

<!-- Hidden Table for Full Export -->
<table id="full-data-table" class="hidden">
  <tbody>
    <?php foreach ($all_rows_for_export as $row): ?>
    <tr>
      <td><?= htmlspecialchars($row['name']) ?></td>
      <td><?= htmlspecialchars($row['department']) ?></td>
      <td><?= htmlspecialchars($row['teaching_status']) ?></td>
      <td>
        <?php
          if (!empty($row['training_history'])) {
            $seminars = json_decode($row['training_history'], true);
            if (is_array($seminars)) {
              foreach ($seminars as $seminar) {
                echo "Training: " . htmlspecialchars($seminar['training'] ?? '') . "<br>";
                echo "Date: " . htmlspecialchars($seminar['date'] ?? '') . "<br>";
                echo "Time: " . 
                     (isset($seminar['start_time']) ? htmlspecialchars($seminar['start_time']) : '') . 
                     (isset($seminar['end_time']) ? ' - ' . htmlspecialchars($seminar['end_time']) : '') . "<br>";
                echo "Duration: " . (isset($seminar['duration']) ? htmlspecialchars($seminar['duration']) : '') . "<br>";
                echo "Venue: " . htmlspecialchars($seminar['venue'] ?? '') . "<br><br>";
              }
            }
          }
        ?>
      </td>
      <td><?= nl2br(htmlspecialchars($row['desired_skills'])) ?></td>
      <td><?= nl2br(htmlspecialchars($row['comments'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- PDF Generator Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
// View details functionality
document.addEventListener('DOMContentLoaded', function() {
  const viewButtons = document.querySelectorAll('.view-details-btn');
  const modal = document.getElementById('view-modal');
  const modalContent = document.getElementById('modal-content');
  const closeButtons = document.querySelectorAll('.close-modal-btn');
  const modalPrintBtn = document.getElementById('modal-print-btn');
  
  viewButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      const assessmentId = this.getAttribute('data-id');
      
      // Show loading state
      modalContent.innerHTML = `
        <div class="flex justify-center items-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>
      `;
      
      // Show modal
      modal.classList.add('active');
      
      // Fetch assessment details
      fetch(`get_assessment_details.php?id=${assessmentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const assessment = data.assessment;
            modalContent.innerHTML = `
              <!-- Profile Card -->
              <div class="profile-card mb-6">
                <div class="flex flex-col md:flex-row gap-6">
                  <!-- Left Column - Basic Info -->
                  <div class="md:w-1/3">
                    <div class="flex items-center space-x-4 mb-6">
                      <div class="bg-primary/10 w-16 h-16 rounded-full flex items-center justify-center">
                        <i class="ri-user-3-line text-3xl text-primary"></i>
                      </div>
                      <div>
                        <h4 class="text-xl font-bold text-gray-800">${assessment.name || 'N/A'}</h4>
                        <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold ${assessment.teaching_status && assessment.teaching_status.toLowerCase() == 'teaching' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                          ${assessment.teaching_status || 'N/A'}
                        </span>
                      </div>
                    </div>
                    
                    <div class="space-y-4">
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Educational Attainment</h5>
                        <p class="text-gray-800">${assessment.educationalAttainment || 'Not specified'}</p>
                      </div>
                      
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Specialization</h5>
                        <p class="text-gray-800">${assessment.specialization || 'Not specified'}</p>
                      </div>
                      
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Designation</h5>
                        <p class="text-gray-800">${assessment.designation || 'Not specified'}</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Middle Column - Department Info -->
                  <div class="md:w-1/3">
                    <div class="space-y-4">
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Department</h5>
                        <p class="text-gray-800">${assessment.department || 'N/A'}</p>
                      </div>
                      
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Years in LSPU</h5>
                        <p class="text-gray-800">${assessment.yearsInLSPU || 'Not specified'}</p>
                      </div>
                      
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Type of Employment</h5>
                        <p class="text-gray-800">${assessment.teaching_status || 'Not specified'}</p>
                      </div>
                      
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Submission Date</h5>
                        <p class="text-gray-800">${assessment.submission_date ? new Date(assessment.submission_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not specified'}</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Right Column - Contact Info -->
                  <div class="md:w-1/3">
                    <div class="space-y-4">
                      <div>
                        <h5 class="text-sm font-medium text-gray-500">Email</h5>
                        <p class="text-gray-800">${assessment.email || 'Not specified'}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Training History Section -->
              <div class="profile-card mb-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4">Training History</h4>
                
                ${assessment.training_history && assessment.training_history.length > 0 ? `
                  <div class="space-y-4">
                    ${assessment.training_history.map(training => `
                      <div class="training-item">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                          <div>
                            <h6 class="text-sm font-medium text-gray-500">Training/Seminar</h6>
                            <p class="text-gray-800 font-medium">${training.training || 'N/A'}</p>
                          </div>
                          <div>
                            <h6 class="text-sm font-medium text-gray-500">Date</h6>
                            <p class="text-gray-800">${training.date || 'N/A'}</p>
                          </div>
                          <div>
                            <h6 class="text-sm font-medium text-gray-500">Time</h6>
                            <p class="text-gray-800">
                              ${training.start_time || ''}
                              ${training.end_time ? ' - ' + training.end_time : ''}
                            </p>
                          </div>
                          <div>
                            <h6 class="text-sm font-medium text-gray-500">Venue</h6>
                            <p class="text-gray-800">${training.venue || 'N/A'}</p>
                          </div>
                        </div>
                      </div>
                    `).join('')}
                  </div>
                ` : `
                  <p class="text-gray-500 italic">No training history recorded.</p>
                `}
              </div>
              
              <!-- Desired Training Section -->
              <div class="profile-card mb-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4">Desired Training/Seminar</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-gray-800 whitespace-pre-line">${assessment.desired_skills || 'Not specified'}</p>
                </div>
              </div>
              
              <!-- Comments Section -->
              <div class="profile-card">
                <h4 class="text-lg font-bold text-gray-800 mb-4">Comments/Suggestions</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-gray-800 whitespace-pre-line">${assessment.comments || 'Not specified'}</p>
                </div>
              </div>
            `;
            
            // Update print button
            modalPrintBtn.onclick = function() {
              const printForm = document.createElement('form');
              printForm.action = 'Training Needs Assessment Form_pdf.php';
              printForm.method = 'post';
              printForm.target = '_blank';
              
              const fields = [
                {name: 'name', value: assessment.name || ''},
                {name: 'educationalAttainment', value: assessment.educationalAttainment || ''},
                {name: 'specialization', value: assessment.specialization || ''},
                {name: 'designation', value: assessment.designation || ''},
                {name: 'department', value: assessment.department || ''},
                {name: 'yearsInLSPU', value: assessment.yearsInLSPU || ''},
                {name: 'teaching_status', value: assessment.teaching_status || ''},
                {name: 'training_history', value: JSON.stringify(assessment.training_history || [])},
                {name: 'desired_skills', value: assessment.desired_skills || ''},
                {name: 'comments', value: assessment.comments || ''}
              ];
              
              fields.forEach(field => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = field.name;
                input.value = field.value;
                printForm.appendChild(input);
              });
              
              document.body.appendChild(printForm);
              printForm.submit();
              document.body.removeChild(printForm);
            };
          } else {
            modalContent.innerHTML = `
              <div class="text-center py-12 text-gray-500">
                <i class="ri-error-warning-line text-4xl text-red-300 mb-3"></i>
                <p class="text-lg">Error loading assessment details</p>
                <p class="text-sm mt-1">Please try again later</p>
              </div>
            `;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          modalContent.innerHTML = `
            <div class="text-center py-12 text-gray-500">
              <i class="ri-error-warning-line text-4xl text-red-300 mb-3"></i>
              <p class="text-lg">Error loading assessment details</p>
              <p class="text-sm mt-1">Please try again later</p>
            </div>
          `;
        });
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
});

function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: "landscape" });

    const pageWidth = doc.internal.pageSize.getWidth();
    const title = "SUMMARY OF TRAINING NEEDS ASSESSMENT FORMS";
    const titleWidth = doc.getStringUnitWidth(title) * doc.getFontSize() / doc.internal.scaleFactor;
    doc.setFontSize(14);
    doc.text(title, (pageWidth - titleWidth) / 2, 15);

    // Use full-data-table for complete export
    const table = document.querySelector('#full-data-table');
    if (!table) return alert("Full data table not found!");

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const data = [];

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 6) return;

        const name = cells[0]?.innerText.trim() || '';
        const department = cells[1]?.innerText.trim() || '';
        const employmentType = cells[2]?.innerText.trim() || '';

        const seminarHTML = cells[3]?.innerHTML || '';
        const seminarText = seminarHTML
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<[^>]*>/g, '')
            .trim();

        const desiredTraining = cells[4]?.innerText.trim() || '';
        const comments = cells[5]?.innerText.trim() || '';

        data.push([
            name,
            department,
            employmentType,
            seminarText,
            desiredTraining,
            comments
        ]);
    });

    const headers = [[
        'Name',
        'Department',
        'Type of Employment',
        'Seminar Attended (Date, Time, Duration, Venue)',
        'Desired Training / Seminar',
        'Comments / Suggestions'
    ]];

    doc.autoTable({
        head: headers,
        body: data,
        startY: 25,
        styles: {
            fontSize: 9,
            textColor: [0, 0, 0],
            halign: 'left',
            valign: 'top',
            lineWidth: 0.2,
            lineColor: [0, 0, 0]
        },
        headStyles: {
            fillColor: [230, 230, 230],
            textColor: [0, 0, 0],
            fontStyle: 'bold',
            lineWidth: 0.2,
            lineColor: [0, 0, 0]
        },
        theme: 'grid'
    });

    doc.save("Summary_of_Training_Needs_Assessment_Forms.pdf");
}
</script>

</body>
</html>