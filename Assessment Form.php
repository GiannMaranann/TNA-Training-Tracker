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
            primary: '#1e3a8a',
            secondary: '#1e40af',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            dark: '#1e293b',
            light: '#f8fafc'
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
    
    .badge-on-time {
      background: linear-gradient(135deg, #10b981, #34d399);
      color: white;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }
    
    .badge-late {
      background: linear-gradient(135deg, #f59e0b, #fbbf24);
      color: white;
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }
    
    .badge-no-submission {
      background: linear-gradient(135deg, #ef4444, #f87171);
      color: white;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    
    .status-teaching {
      background: linear-gradient(135deg, #10b981, #34d399);
      color: white;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }
    
    .status-nonteaching {
      background: linear-gradient(135deg, #3b82f6, #60a5fa);
      color: white;
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
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
      color: white;
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
    
    .pagination-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.2s;
      border: 1px solid #e5e7eb;
    }
    
    .pagination-btn.active {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      color: white;
      border-color: #1e3a8a;
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
    
    /* Enhanced Modal Styles */
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
      max-width: 900px;
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
    
    .filter-tag {
      display: inline-flex;
      align-items: center;
      padding: 0.5rem 1rem;
      border-radius: 10px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      cursor: pointer;
      text-decoration: none;
    }
    
    .filter-tag.active {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      color: white;
      box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
    }
    
    .filter-tag:not(.active) {
      background-color: #f1f5f9;
      color: #64748b;
      border: 1px solid #e2e8f0;
    }
    
    .filter-tag:not(.active):hover {
      background-color: #e2e8f0;
      transform: translateY(-1px);
    }
    
    .search-input {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M18.031 16.617l4.283 4.282-1.415 1.415-4.282-4.283A8.96 8.96 0 0 1 11 20c-4.968 0-9-4.032-9-9s4.032-9 9-9 9 4.032 9 9a8.96 8.96 0 0 1-1.969 5.617zm-2.006-.742A6.977 6.977 0 0 0 18 11c0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132 7 7 7a6.977 6.977 0 0 0 4.875-1.975l.15-.15z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
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
    
    .stats-card {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      border-radius: 16px;
      color: white;
      padding: 1.5rem;
      box-shadow: 0 10px 30px rgba(30, 58, 138, 0.3);
    }

    /* New styles for compressed filters */
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
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm" style="display: none;">
              <i class="ri-government-line text-white text-xl"></i>
            </div>
          </div>
          <div>
            <h1 class="text-lg font-bold text-white">LSPU Admin</h1>
            <p class="text-white/60 text-sm">Dashboard</p>
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
             class="flex items-center px-4 py-3 text-white font-semibold rounded-xl sidebar-link active">
            <i class="ri-survey-line mr-3 text-lg"></i>
            <span class="text-base">Assessment Forms</span>
            <i class="ri-arrow-right-s-line ml-auto text-lg"></i>
          </a>

          <a href="Individual_Development_Plan_Form.php" 
             class="flex items-center px-4 py-3 text-white/90 font-semibold rounded-xl sidebar-link">
            <i class="ri-contacts-book-2-line mr-3 text-lg"></i>
            <span class="text-base">IDP Forms</span>
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
          <h1 class="text-3xl font-bold text-white">Assessment Form Submissions</h1>
          <p class="text-white/70 text-lg mt-2">View and manage all submitted assessment forms</p>
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
        <!-- Stats Card -->
        <div class="stats-card mb-8">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-white/80 text-sm font-medium">Total Records Found</p>
              <h3 class="text-4xl font-bold text-white mt-2"><?= $total_rows ?></h3>
              <p class="text-white/90 text-xs mt-1 font-semibold">
                <?= $selected_month > 0 ? 'for ' . date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)) : 'for ' . htmlspecialchars($selected_year) ?>
              </p>
            </div>
            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm shadow-lg">
              <i class="fas fa-file-alt text-white text-2xl"></i>
            </div>
          </div>
        </div>

        <!-- Filters Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Year Filter Dropdown -->
          <div class="filter-card">
            <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Year</label>
            <div class="dropdown">
              <button class="filter-dropdown-btn">
                <span class="filter-selected">
                  <?= $selected_year == 0 ? 'All Years' : htmlspecialchars($selected_year) ?>
                </span>
                <i class="ri-arrow-down-s-line"></i>
              </button>
              <div class="dropdown-content">
                <a href="?year=0&month=<?= $selected_month ?>&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>">
                  All Years
                </a>
                <?php 
                $years_result->data_seek(0); // Reset pointer
                while ($yearRow = $years_result->fetch_assoc()): ?>
                  <a href="?year=<?= urlencode($yearRow['year']) ?>&month=<?= $selected_month ?>&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>"
                     class="<?= $selected_year == $yearRow['year'] ? 'bg-primary text-white' : '' ?>">
                    <?= htmlspecialchars($yearRow['year']) ?>
                  </a>
                <?php endwhile; ?>
              </div>
            </div>
          </div>

          <!-- Month Filter Dropdown -->
          <div class="filter-card">
            <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Month</label>
            <div class="dropdown">
              <button class="filter-dropdown-btn">
                <span class="filter-selected">
                  <?= $selected_month == 0 ? 'All Months' : date('F', mktime(0, 0, 0, $selected_month, 1)) ?>
                </span>
                <i class="ri-arrow-down-s-line"></i>
              </button>
              <div class="dropdown-content">
                <a href="?year=<?= $selected_year ?>&month=0&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>">
                  All Months
                </a>
                <?php 
                $months = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                ];
                
                // Reset pointer if needed
                $months_result->data_seek(0);
                
                while ($monthRow = $months_result->fetch_assoc()): 
                    $monthNum = $monthRow['month'];
                    $monthName = $months[$monthNum] ?? '';
                ?>
                  <a href="?year=<?= $selected_year ?>&month=<?= $monthNum ?>&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>"
                     class="<?= $selected_month == $monthNum ? 'bg-primary text-white' : '' ?>">
                    <?= $monthName ?>
                  </a>
                <?php endwhile; ?>
              </div>
            </div>
          </div>

          <!-- Teaching Status Filter Dropdown -->
          <div class="filter-card">
            <label class="block text-sm font-medium text-gray-700 mb-3">Filter by Status</label>
            <div class="dropdown">
              <button class="filter-dropdown-btn">
                <span class="filter-selected">
                  <?= empty($teaching_status) ? 'All Status' : htmlspecialchars($teaching_status) ?>
                </span>
                <i class="ri-arrow-down-s-line"></i>
              </button>
              <div class="dropdown-content">
                <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&search=<?= urlencode($search) ?>">
                  All Status
                </a>
                <?php 
                $statuses_result->data_seek(0);
                while ($statusRow = $statuses_result->fetch_assoc()): ?>
                  <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&teaching_status=<?= urlencode($statusRow['teaching_status']) ?>&search=<?= urlencode($search) ?>"
                     class="<?= $teaching_status == $statusRow['teaching_status'] ? 'bg-primary text-white' : '' ?>">
                    <?= htmlspecialchars($statusRow['teaching_status']) ?>
                  </a>
                <?php endwhile; ?>
              </div>
            </div>
          </div>

          <!-- Search Input -->
          <div class="filter-card">
            <label for="search-input" class="block text-sm font-medium text-gray-700 mb-3">Search</label>
            <div class="relative">
              <input type="search" name="search" id="search-input"
                     class="w-full search-input py-2.5 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-primary transition"
                     placeholder="Search by name or department..."
                     value="<?= htmlspecialchars($search) ?>" 
                     oninput="handleSearchInput(this.value)" />
            </div>
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
                 class="pagination-btn bg-white text-gray-700 hover:bg-gray-50 transition pagination-link">
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
                echo '<a href="?year='.urlencode($selected_year).'&month='.$selected_month.'&page=1&search='.urlencode($search).'&teaching_status='.urlencode($teaching_status).'" class="pagination-btn bg-white text-gray-700 hover:bg-gray-50 pagination-link">1</a>';
                if ($start_page > 2) {
                    echo '<span class="pagination-btn bg-transparent border-0 text-gray-500">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
              <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
                 class="pagination-btn <?= $page == $i ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50' ?> pagination-link">
                <?= $i ?>
              </a>
            <?php endfor;
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="pagination-btn bg-transparent border-0 text-gray-500">...</span>';
                }
                echo '<a href="?year='.urlencode($selected_year).'&month='.$selected_month.'&page='.$total_pages.'&search='.urlencode($search).'&teaching_status='.urlencode($teaching_status).'" class="pagination-btn bg-white text-gray-700 hover:bg-gray-50 pagination-link">'.$total_pages.'</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
              <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
                 class="pagination-btn bg-white text-gray-700 hover:bg-gray-50 transition pagination-link">
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
// Store scroll position before navigation
function storeScrollPosition() {
    sessionStorage.setItem('scrollPosition', window.pageYOffset);
}

// Restore scroll position after page load
function restoreScrollPosition() {
    const scrollPosition = sessionStorage.getItem('scrollPosition');
    if (scrollPosition) {
        window.scrollTo(0, parseInt(scrollPosition));
        sessionStorage.removeItem('scrollPosition');
    }
}

// Real-time search functionality
let searchTimeout;
function handleSearchInput(value) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        url.searchParams.set('search', value);
        url.searchParams.set('page', '1'); // Reset to first page when searching
        
        storeScrollPosition();
        window.location.href = url.toString();
    }, 500); // 500ms delay
}

// Enhanced pagination with scroll preservation
document.addEventListener('DOMContentLoaded', function() {
    // Restore scroll position on page load
    restoreScrollPosition();
    
    // Add click handlers to pagination links
    document.querySelectorAll('.pagination-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            storeScrollPosition();
            window.location.href = this.href;
        });
    });

    // View details functionality
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
                                <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold ${assessment.teaching_status && assessment.teaching_status.toLowerCase() == 'teaching' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">
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