<?php
include 'config.php'; // database connection as $con

// Set default year to current year
$selected_year = date('Y');

// Validate year parameter if provided
if (isset($_GET['year']) && is_numeric($_GET['year'])) {
    $selected_year = intval($_GET['year']);
    // Ensure year is within a reasonable range
    $current_year = date('Y');
    if ($selected_year < 2000 || $selected_year > $current_year + 5) {
        $selected_year = $current_year;
    }
}

try {
    // First check if the 'deleted' column exists in assessments table
    $column_check = $con->query("SHOW COLUMNS FROM assessments LIKE 'deleted'");
    $has_deleted_column = ($column_check && $column_check->num_rows > 0);

    // Build the query
    $sql = "SELECT 
                u.id as user_id,
                u.name,
                u.department,
                u.teaching_status,
                a.training_history,
                a.desired_skills,
                a.comments,
                a.submission_date,
                a.id AS assessment_id
            FROM users u
            LEFT JOIN assessments a ON u.id = a.user_id
            WHERE u.department = 'CCS' 
              AND (a.submission_date IS NULL OR YEAR(a.submission_date) = ?)";
    
    // Add deleted condition if column exists
    if ($has_deleted_column) {
        $sql .= " AND a.deleted = 0";
    }
    
    $sql .= " ORDER BY a.submission_date DESC";
    
    // Prepare and execute the query with parameter binding
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $con->error);
    }
    
    $stmt->bind_param("i", $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check for errors
    if (!$result) {
        throw new Exception("Query failed: " . $con->error);
    }

} catch (Exception $e) {
    // Handle errors gracefully
    error_log("Database error: " . $e->getMessage());
    
    // You might want to display a user-friendly message or redirect
    die("An error occurred while fetching data. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CCS Assessment Portal</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />

  <style>
    :where([class^="ri-"])::before {
      content: "\f3c2";
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
    }

    .search-input:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
    }

    .table-container {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }

    th {
      position: relative;
      cursor: pointer;
      user-select: none;
    }

    th:hover {
      background-color: #f1f5f9;
    }

    tbody tr:nth-child(even) {
      background-color: #f8f9fa;
    }

    tbody tr:hover {
      background-color: #f1f5f9;
    }

    .rating-modal {
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

    .star {
      cursor: pointer;
      transition: color 0.2s;
    }

    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 16px;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      transform: translateY(-100px);
      opacity: 0;
      transition: all 0.3s ease;
    }

    .notification.show {
      transform: translateY(0);
      opacity: 1;
    }

    input[type="search"]::-webkit-search-decoration,
    input[type="search"]::-webkit-search-cancel-button,
    input[type="search"]::-webkit-search-results-button,
    input[type="search"]::-webkit-search-results-decoration {
      display: none;
    }

    .custom-select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.5rem center;
      background-size: 1.5em 1.5em;
    }

    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    .animate-bounce {
        animation: bounce 1.2s infinite;
    }

    .seminar-details {
      max-height: 150px;
      overflow-y: auto;
      padding-right: 8px;
    }
    .seminar-details::-webkit-scrollbar {
      width: 6px;
    }
    .seminar-details::-webkit-scrollbar-thumb {
      background-color: #c1c1c1;
      border-radius: 3px;
    }

    .action-buttons {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    @media (min-width: 768px) {
      .action-buttons {
        flex-direction: row;
      }
    }

    /* Evaluation Form Styles */
    .form-container {
      background-color: #ffffff;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .form-field {
      border: 1px solid #e5e7eb;
      transition: all 0.2s;
    }
    .form-field:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
      outline: none;
    }
    .radio-container input[type="radio"] {
      display: none;
    }
    .radio-container label {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
      cursor: pointer;
      border: 1px solid #e5e7eb;
      transition: all 0.2s;
    }
    .radio-container input[type="radio"]:checked + label {
      background-color: #3b82f6;
      color: white;
      border-color: #3b82f6;
    }
    .signature-container {
      border: 1px dashed #e5e7eb;
      background-color: #f9fafb;
      position: relative;
      width: 100%;
      height: 120px;
    }
    .signature-actions {
      display: flex;
      gap: 8px;
      margin-top: 8px;
    }
    .signature-btn {
      padding: 4px 8px;
      font-size: 12px;
      border-radius: 4px;
      cursor: pointer;
    }
    .upload-btn {
      background-color: #3b82f6;
      color: white;
      border: none;
    }
    .clear-btn {
      background-color: #e5e7eb;
      color: #333;
      border: none;
    }
    
    /* New UI Enhancements */
    .status-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 500;
    }
    .status-completed {
      background-color: #dcfce7;
      color: #166534;
    }
    .status-pending {
      background-color: #fef9c3;
      color: #854d0e;
    }
    
    /* Filter chips */
    .filter-chip {
      display: inline-flex;
      align-items: center;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .filter-chip.active {
      background-color: #3b82f6;
      color: white;
    }
    .filter-chip:hover:not(.active) {
      background-color: #e5e7eb;
    }
    
    /* Card design for better visual hierarchy */
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    /* Signature canvas */
    #signature-canvas {
      width: 100%;
      height: 100%;
      touch-action: none;
    }
    
    /* Modern button styles */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s;
      gap: 6px;
    }
    .btn-primary {
      background-color: #3b82f6;
      color: white;
      border: none;
    }
    .btn-primary:hover {
      background-color: #2563eb;
    }
    .btn-danger {
      background-color: #ef4444;
      color: white;
      border: none;
    }
    .btn-danger:hover {
      background-color: #dc2626;
    }
    .btn-secondary {
      background-color: #e5e7eb;
      color: #4b5563;
      border: none;
    }
    .btn-secondary:hover {
      background-color: #d1d5db;
    }
    
    /* Loading spinner */
    .spinner {
      width: 24px;
      height: 24px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 1s ease-in-out infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    /* Empty state */
    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      text-align: center;
    }
    .empty-state-icon {
      width: 80px;
      height: 80px;
      margin-bottom: 16px;
      color: #9ca3af;
    }

    /* Signature image preview */
    .signature-preview {
      max-width: 100%;
      max-height: 100px;
      display: none;
    }

    /* Image cropping container */
    .image-crop-container {
      position: relative;
      width: 100%;
      height: 200px;
      overflow: hidden;
      border: 1px dashed #ccc;
      margin-bottom: 10px;
    }
    .image-crop-preview {
      position: absolute;
      top: 0;
      left: 0;
    }
    .crop-controls {
      position: absolute;
      bottom: 10px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0,0,0,0.5);
      padding: 5px;
      border-radius: 4px;
      display: flex;
      gap: 5px;
    }
    .crop-btn {
      background: #fff;
      border: none;
      border-radius: 3px;
      padding: 3px 8px;
      cursor: pointer;
    }
  </style>
</head>
<body class="min-h-screen font-sans" style="font-family: 'Poppins', sans-serif;">
  <div class="min-w-[1024px]">
    <!-- Header -->
    <header class="bg-white shadow-sm fixed top-0 left-0 w-full z-50">
      <div class="max-w-7xl mx-auto px-6 py-5 flex justify-between items-center">
        <!-- Title -->
        <div class="flex items-center space-x-4">
          <div class="flex items-center space-x-2">
            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-200">
              <img src="images/ccs.png" alt="CCS Logo" class="w-full h-full object-cover" />
            </div>
            <h1 class="text-2xl font-bold text-gray-800">CCS Assessment Portal</h1>
          </div>
        </div>

        <!-- Right Section -->
        <div class="flex items-center space-x-6">
          <!-- Notification Bell -->
          <div class="relative">
            <button class="flex items-center text-gray-600 hover:text-gray-900 focus:outline-none">
              <div class="relative group cursor-pointer" id="notifWrapper">
                <i class="ri-notification-3-line text-4xl text-gray-700 group-hover:text-blue-600 animate-bounce" id="notifBell"></i>
                <span id="notifBadge" class="absolute top-0 right-0 transform translate-x-1 -translate-y-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded-full hidden">!</span>
                <div id="notifPopup" class="absolute right-0 mt-3 w-96 bg-white border border-gray-200 rounded-2xl shadow-xl hidden z-50">
                  <div class="relative p-5">
                    <div class="absolute top-4 right-4 text-primary text-xl">
                      <i class="ri-information-line"></i>
                    </div>
                    <div class="text-sm text-gray-700 space-y-2">
                      <h3 class="text-lg font-semibold text-primary">Semester Reminder</h3>
                      <p>It's time for the <span class="font-medium">semester evaluation</span> of faculty members...</p>
                      <p class="font-semibold text-gray-900">
                        Please complete the evaluation process before the deadline to ensure compliance.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </button>
          </div>

          <!-- Profile Section -->
          <div class="flex items-center space-x-4">
            <div class="hidden md:block text-right">
              <p class="text-sm font-bold text-gray-800">Welcome, Administrator</p>
              <p class="text-xs text-gray-500">College of Computer Studies</p>
            </div>
            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-200 border-2 border-primary">
              <img src="images/lspubg2.png" alt="Profile" class="w-full h-full object-cover" />
            </div>
          </div>

          <!-- Sign Out Button -->
          <div>
            <a href="homepage.php" 
               class="btn btn-danger">
              <i class="ri-logout-box-r-line"></i> Sign Out
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content (with padding-top) -->
    <main class="max-w-7xl mx-auto px-6 pt-32 pb-8">
      <div class="bg-white rounded-xl shadow-sm p-6 card">
        <!-- Title and Search -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
          <div>
            <h2 class="text-2xl font-semibold text-gray-800">Assessment Forms Submissions</h2>
            <p class="text-sm text-gray-500 mt-1">View and manage faculty training assessments</p>
          </div>
          <div class="relative w-full md:w-96">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <div class="w-5 h-5 flex items-center justify-center text-gray-500">
                <i class="ri-search-line"></i>
              </div>
            </div>
            <input type="search" id="search-input" class="search-input w-full pl-10 pr-4 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-lg focus:border-primary transition-colors" placeholder="Search by name, department, or status...">
          </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-4 mb-6 justify-between items-center">
          <div class="flex flex-wrap gap-3">
            <div>
              <label for="year-filter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Year:</label>
              <select id="year-filter" class="custom-select bg-gray-50 border border-gray-300 text-gray-700 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                <?php
                $currentYear = date('Y');
                for ($year = $currentYear; $year >= 2020; $year--) {
                    $selected = $year == $selected_year ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
              </select>
            </div>
            <div>
              <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Status:</label>
              <select id="status-filter" class="custom-select bg-gray-50 border border-gray-300 text-gray-700 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                <option value="all">All Status</option>
                <option value="completed">Completed</option>
                <option value="pending">Pending</option>
              </select>
            </div>
            <div>
              <label for="type-filter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Type:</label>
              <select id="type-filter" class="custom-select bg-gray-50 border border-gray-300 text-gray-700 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                <option value="all">All Types</option>
                <option value="teaching">Teaching</option>
                <option value="non-teaching">Non-Teaching</option>
              </select>
            </div>
          </div>
          <div class="flex gap-3">
            <button onclick="generatePDF()" class="btn btn-primary">
              <i class="ri-download-2-line"></i> Export PDF
            </button>
            <button id="refresh-btn" class="btn btn-secondary">
              <i class="ri-refresh-line"></i> Refresh
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="table-container rounded-lg border border-gray-200 mb-6">
          <table class="min-w-full">
            <thead class="bg-gray-50 text-left">
              <tr>
                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Name</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Department</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Type</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Status</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Seminar Attended</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Desired Training</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                  $status = !empty($row['submission_date']) ? 'completed' : 'pending';
                  $type = $row['teaching_status'] === 'Teaching' ? 'teaching' : 'non-teaching';
                ?>
                  <tr data-name="<?= htmlspecialchars($row['name']) ?>" 
                      data-department="<?= htmlspecialchars($row['department']) ?>"
                      data-status="<?= $status ?>"
                      data-type="<?= $type ?>"
                      data-id="<?= $row['assessment_id'] ?>"
                      class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm font-medium text-gray-800"><?= htmlspecialchars($row['name']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['department']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                      <span class="status-badge <?= $type === 'teaching' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                        <?= htmlspecialchars($row['teaching_status']) ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                      <span class="status-badge <?= $status === 'completed' ? 'status-completed' : 'status-pending' ?>">
                        <?= $status === 'completed' ? 'Completed' : 'Pending' ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                      <div class="seminar-details">
                        <?php
                          if (!empty($row['training_history'])) {
                              $seminars = json_decode($row['training_history'], true);
                              if (is_array($seminars)) {
                                  foreach ($seminars as $seminar) {
                                      echo "<div class='mb-2'>";
                                      echo "<strong>Date:</strong> " . htmlspecialchars($seminar['date'] ?? '') . "<br>";
                                      echo "<strong>Training:</strong> " . htmlspecialchars($seminar['training'] ?? '') . "<br>";
                                      echo "<strong>Venue:</strong> " . htmlspecialchars($seminar['venue'] ?? '') . "<br>";
                                      echo "</div><hr class='my-2'>";
                                  }
                              } else {
                                  echo "Invalid format";
                              }
                          } else {
                              echo "—";
                          }
                          ?>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['desired_skills'] ?? '—') ?></td>
                    <td class="px-6 py-4 text-sm whitespace-nowrap">
                      <div class="action-buttons">
                        <!-- Evaluate Button -->
                        <button 
                          type="button"
                          class="evaluate-btn btn btn-primary px-3 py-1.5 text-xs"
                          data-name="<?= htmlspecialchars($row['name']) ?>"
                          data-id="<?= htmlspecialchars($row['assessment_id']) ?>"
                          data-user-id="<?= htmlspecialchars($row['user_id']) ?>"
                        >
                          <i class="ri-star-line mr-1"></i>
                          Evaluate
                        </button>
                        <button 
                          class="remove-btn btn btn-danger px-3 py-1.5 text-xs" 
                          data-id="<?= htmlspecialchars($row['assessment_id']) ?>"
                          data-name="<?= htmlspecialchars($row['name']) ?>"
                        >
                          <i class="ri-delete-bin-line mr-1"></i> Remove
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="px-6 py-8 text-center">
                    <div class="empty-state">
                      <i class="ri-file-search-line empty-state-icon text-4xl"></i>
                      <h3 class="text-lg font-medium text-gray-900">No assessments found</h3>
                      <p class="mt-1 text-sm text-gray-500">No CCS data found for <?= $selected_year ?>.</p>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Evaluation Modal -->
  <div id="evaluationModal" class="rating-modal">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-5xl mx-4 overflow-y-auto" style="max-height: 90vh;">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-2xl font-bold text-gray-800">TRAINING PROGRAM IMPACT ASSESSMENT FORM</h3>
        <button id="closeEvalModal" class="text-gray-500 hover:text-gray-700">
          <i class="ri-close-line text-2xl"></i>
        </button>
      </div>
      
      <div class="mb-4">
        <p class="text-sm text-gray-600">Faculty: <span id="evalFacultyName" class="font-medium text-gray-800"></span></p>
      </div>
      
      <form action="pdf.php" method="POST" id="assessment-form" target="_blank">
        <input type="hidden" name="assessment_id" id="assessment-id">
        <input type="hidden" name="user_id" id="user-id">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name of Employee:</label>
            <input id="evalName" type="text" name="name" class="form-field w-full px-4 py-2 rounded" placeholder="Enter employee name" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department/Unit:</label>
            <input id="evalDepartment" type="text" name="department" class="form-field w-full px-4 py-2 rounded" placeholder="Enter department or unit" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title of Training/Seminar Attended:</label>
            <input type="text" name="training_title" class="form-field w-full px-4 py-2 rounded" placeholder="Enter training title" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date Conducted:</label>
            <input type="date" name="date_conducted" class="form-field w-full px-4 py-2 rounded" required>
          </div>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Objective/s:</label>
          <textarea name="objectives" class="form-field w-full px-4 py-2 rounded min-h-[80px]" placeholder="Enter training objectives" required></textarea>
        </div>

        <div class="mb-6">
          <div class="bg-gray-100 p-3 rounded mb-4">
            <p class="text-sm text-gray-700"><span class="font-medium">INSTRUCTION:</span> Please check (✓) in the appropriate column the impact/benefits gained by the employee in attending the training program in a scale of 1-5 (where 5 – Strongly Agree; 4 – Agree; 3 – Neither agree nor disagree; 2 – Disagree; 1 – Strongly Disagree)</p>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full border-collapse">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left py-3 px-4 font-medium text-gray-700 border border-gray-200 w-1/2">IMPACT/BENEFITS GAINED</th>
                  <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-200 w-8">1</th>
                  <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-200 w-8">2</th>
                  <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-200 w-8">3</th>
                  <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-200 w-8">4</th>
                  <th class="text-center py-3 px-2 font-medium text-gray-700 border border-gray-200 w-8">5</th>
                  <th class="text-left py-3 px-4 font-medium text-gray-700 border border-gray-200">REMARKS</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 1; $i <= 8; $i++): ?>
                <tr class="<?= $i % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?>">
                  <td class="py-3 px-4 border border-gray-200 text-gray-700">
                    <?php 
                      $questions = [
                        "1. The employee's performance became more efficient as shown with no/less commitment of mistakes on work.",
                        "2. The employee enhanced his/her ability to generate ideas and recommendations.",
                        "3. He/She has developed new system or improved the present system through contributing new ideas.",
                        "4. The employee's morale has been upgraded.",
                        "5. The employee has applied new skills in the performance of his/her work.",
                        "6. The employee became more proud and confident in his/her tasks.",
                        "7. The employee can now be entrusted higher/greater responsibility.",
                        "8. He/She transferred the knowledge and skills gained through conduct of workshop or demonstration to co-employee."
                      ];
                      echo $questions[$i-1];
                    ?>
                  </td>
                  <?php for ($j = 1; $j <= 5; $j++): ?>
                  <td class="py-2 px-0 border border-gray-200">
                    <div class="radio-container h-8 flex items-center justify-center">
                      <input type="radio" id="q<?= $i ?>-<?= $j ?>" name="rating[<?= $i-1 ?>]" value="<?= $j ?>">
                      <label for="q<?= $i ?>-<?= $j ?>" class="rounded-button w-8 h-8"><?= $j ?></label>
                    </div>
                  </td>
                  <?php endfor; ?>
                  <td class="py-2 px-2 border border-gray-200">
                    <input type="text" name="remark[<?= $i-1 ?>]" class="w-full px-2 py-1 border-none bg-transparent" placeholder="Add remarks">
                  </td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Comments:</label>
          <textarea name="comments" class="form-field w-full px-4 py-2 rounded min-h-[80px]" placeholder="Enter additional comments"></textarea>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Please list down other training programs he/she might need in the future:</label>
          <textarea name="future_training" class="form-field w-full px-4 py-2 rounded min-h-[100px]" placeholder="Enter future training needs"></textarea>
        </div>

        <div class="border-t border-gray-200 pt-6">
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Rated by:</label>
              <input type="text" name="rated_by" class="form-field w-full px-4 py-2 rounded" placeholder="Immediate Supervisor's Name" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Signature:</label>
              <div class="signature-container">
                <canvas id="signature-canvas"></canvas>
                <img id="signature-preview" class="signature-preview" alt="Signature Preview">
              </div>
              <input type="file" id="signature-upload" accept="image/*" class="hidden" />
              <input type="hidden" name="signature_data" id="signature-data" />
              <div class="signature-actions">
                <button type="button" id="upload-signature" class="signature-btn upload-btn">
                  <i class="ri-upload-line mr-1"></i> Upload Image
                </button>
                <button type="button" id="clear-signature" class="signature-btn clear-btn">
                  <i class="ri-eraser-line mr-1"></i> Clear
                </button>
              </div>
              <!-- Image crop modal -->
              <div id="image-crop-modal" class="rating-modal hidden">
                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4">
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Crop Signature</h3>
                    <button id="close-crop-modal" class="text-gray-500 hover:text-gray-700">
                      <i class="ri-close-line text-2xl"></i>
                    </button>
                  </div>
                  <div class="image-crop-container">
                    <img id="image-to-crop" class="image-crop-preview" alt="Image to crop">
                    <div class="crop-controls">
                      <button type="button" id="zoom-in" class="crop-btn">+</button>
                      <button type="button" id="zoom-out" class="crop-btn">-</button>
                      <button type="button" id="move-left" class="crop-btn">←</button>
                      <button type="button" id="move-right" class="crop-btn">→</button>
                      <button type="button" id="move-up" class="crop-btn">↑</button>
                      <button type="button" id="move-down" class="crop-btn">↓</button>
                    </div>
                  </div>
                  <div class="flex justify-end space-x-4 mt-4">
                    <button type="button" id="cancel-crop" class="btn btn-secondary">
                      Cancel
                    </button>
                    <button type="button" id="apply-crop" class="btn btn-primary">
                      Apply Crop
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date:</label>
              <input type="date" name="assessment_date" class="form-field w-full px-4 py-2 rounded" required />
            </div>
          </div>
        </div>

        <div class="mt-8 flex justify-end space-x-4">
          <button type="button" id="cancelEvaluation" class="btn btn-secondary">
            <i class="ri-close-line mr-1"></i> Cancel
          </button>
          <button type="submit" name="action" value="print" class="btn btn-primary">
            <i class="ri-printer-line mr-1"></i> Print
          </button>
          <button type="submit" id="submitEvaluation" name="action" value="submit" class="btn btn-primary">
            <span id="submit-text">Submit Assessment</span>
            <span id="submit-spinner" class="spinner hidden"></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Confirmation Dialog -->
  <div id="confirm-dialog" class="rating-modal">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
      <div class="mb-4">
        <div class="w-12 h-12 mx-auto flex items-center justify-center bg-red-100 rounded-full text-red-500">
          <i class="ri-error-warning-line ri-2x"></i>
        </div>
        <h3 class="mt-4 text-lg font-semibold text-gray-800 text-center">Confirm Removal</h3>
        <p class="mt-2 text-sm text-gray-600 text-center">Are you sure you want to remove this assessment form? This action cannot be undone.</p>
      </div>
      <div class="flex justify-center space-x-3">
        <button id="cancel-remove" class="btn btn-secondary">
          Cancel
        </button>
        <button id="confirm-remove" class="btn btn-danger">
          <i class="ri-delete-bin-line mr-1"></i> Yes, Remove
        </button>
      </div>
    </div>
  </div>

  <!-- Notification -->
  <div id="notification" class="notification bg-green-50 border-l-4 border-green-500">
    <div class="flex items-center">
      <div class="w-6 h-6 flex items-center justify-center text-green-500 mr-3">
        <i class="ri-check-line ri-lg"></i>
      </div>
      <div>
        <p class="font-medium text-green-800">Success!</p>
        <p class="text-sm text-green-700" id="notification-message"></p>
      </div>
    </div>
  </div>

  <script>
    // Notification System
    function showNotification(message, type = 'success') {
      const notification = document.getElementById('notification');
      const messageEl = document.getElementById('notification-message');
      
      // Set notification style based on type
      notification.className = 'notification';
      if (type === 'success') {
        notification.classList.add('bg-green-50', 'border-green-500');
        notification.querySelector('i').className = 'ri-check-line ri-lg';
      } else if (type === 'error') {
        notification.classList.add('bg-red-50', 'border-red-500');
        notification.querySelector('i').className = 'ri-close-line ri-lg';
      } else if (type === 'warning') {
        notification.classList.add('bg-yellow-50', 'border-yellow-500');
        notification.querySelector('i').className = 'ri-alert-line ri-lg';
      }
      
      messageEl.textContent = message;
      notification.classList.add('show');
      
      // Hide after 5 seconds
      setTimeout(() => {
        notification.classList.remove('show');
      }, 5000);
    }

    // Semester Notification Check
    function checkSemesterNotification() {
      const lastNotification = localStorage.getItem('lastSemesterNotification');
      const now = new Date();
      const currentMonth = now.getMonth();
      
      // Check if it's the start of a semester (June or January)
      const isSemesterStart = currentMonth === 0 || currentMonth === 5;
      
      if (isSemesterStart && (!lastNotification || new Date(lastNotification).getMonth() !== currentMonth)) {
        document.getElementById('notifBadge').classList.remove('hidden');
        document.getElementById('notifBell').classList.add('animate-bounce');
        localStorage.setItem('lastSemesterNotification', now.toISOString());
      }
    }

    // Notification Bell Interaction
    document.getElementById('notifWrapper').addEventListener('click', function() {
      const popup = document.getElementById('notifPopup');
      const badge = document.getElementById('notifBadge');
      const bell = document.getElementById('notifBell');
      
      popup.classList.toggle('hidden');
      badge.classList.add('hidden');
      bell.classList.remove('animate-bounce');
    });

    // Search Functionality
    document.getElementById('search-input').addEventListener('input', function() {
      const query = this.value.trim().toLowerCase();
      const rows = document.querySelectorAll('tbody tr');
      
      rows.forEach(row => {
        const name = row.getAttribute('data-name').toLowerCase();
        const department = row.getAttribute('data-department').toLowerCase();
        const status = row.getAttribute('data-status').toLowerCase();
        const type = row.getAttribute('data-type').toLowerCase();
        
        if (name.includes(query) || department.includes(query) || status.includes(query) || type.includes(query)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });

    // Filter Functionality
    document.getElementById('status-filter').addEventListener('change', function() {
      const status = this.value;
      const rows = document.querySelectorAll('tbody tr');
      
      rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        
        if (status === 'all' || rowStatus === status) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });

    document.getElementById('type-filter').addEventListener('change', function() {
      const type = this.value;
      const rows = document.querySelectorAll('tbody tr');
      
      rows.forEach(row => {
        const rowType = row.getAttribute('data-type');
        
        if (type === 'all' || rowType === type) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });

    // Year Filter
    document.getElementById('year-filter').addEventListener('change', function() {
      window.location.href = `?year=${this.value}`;
    });

    // Refresh Button
    document.getElementById('refresh-btn').addEventListener('click', function() {
      window.location.reload();
    });

    // Evaluation Modal
    const evaluationModal = document.getElementById('evaluationModal');
    const evalFacultyName = document.getElementById('evalFacultyName');
    const evalName = document.getElementById('evalName');
    const evalDepartment = document.getElementById('evalDepartment');
    const assessmentIdInput = document.getElementById('assessment-id');
    const userIdInput = document.getElementById('user-id');
    let currentAssessmentId = null;

    // Open Evaluation Modal
    document.querySelectorAll('.evaluate-btn').forEach(button => {
      button.addEventListener('click', function() {
        const facultyName = this.getAttribute('data-name');
        currentAssessmentId = this.getAttribute('data-id');
        const userId = this.getAttribute('data-user-id');
        
        // Set the faculty name and department in the form
        evalFacultyName.textContent = facultyName;
        evalName.value = facultyName;
        evalDepartment.value = 'CCS'; // Default department
        assessmentIdInput.value = currentAssessmentId;
        userIdInput.value = userId;
        
        evaluationModal.style.display = 'flex';
      });
    });

    // Close Evaluation Modal
    document.getElementById('closeEvalModal').addEventListener('click', function() {
      evaluationModal.style.display = 'none';
    });
    document.getElementById('cancelEvaluation').addEventListener('click', function() {
      evaluationModal.style.display = 'none';
    });

    // Remove Functionality
    const confirmDialog = document.getElementById('confirm-dialog');
    let currentRowToRemove = null;
    let currentNameToRemove = null;
    let currentIdToRemove = null;

    document.querySelectorAll('.remove-btn').forEach(button => {
      button.addEventListener('click', function() {
        currentRowToRemove = this.closest('tr');
        currentNameToRemove = this.getAttribute('data-name');
        currentIdToRemove = this.getAttribute('data-id');
        confirmDialog.style.display = 'flex';
      });
    });

    document.getElementById('cancel-remove').addEventListener('click', function() {
      confirmDialog.style.display = 'none';
      currentRowToRemove = null;
      currentNameToRemove = null;
      currentIdToRemove = null;
    });

    document.getElementById('confirm-remove').addEventListener('click', function() {
      if (!currentRowToRemove || !currentIdToRemove) return;
      
      // Show loading state
      const confirmBtn = document.getElementById('confirm-remove');
      const originalText = confirmBtn.innerHTML;
      confirmBtn.innerHTML = '<span class="spinner"></span> Removing...';
      confirmBtn.disabled = true;
      
      // Send AJAX request to remove the assessment
      const formData = new FormData();
      formData.append('assessment_id', currentIdToRemove);
      formData.append('action', 'remove_assessment');
      
      fetch('process_assessment.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove the row from the DOM
          currentRowToRemove.remove();
          showNotification(`Assessment for ${currentNameToRemove} has been removed.`);
          
          // Check if table is empty now
          const tbody = document.querySelector('tbody');
          if (tbody && tbody.querySelectorAll('tr').length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
              <td colspan="7" class="px-6 py-8 text-center">
                <div class="empty-state">
                  <i class="ri-file-search-line empty-state-icon text-4xl"></i>
                  <h3 class="text-lg font-medium text-gray-900">No assessments found</h3>
                  <p class="mt-1 text-sm text-gray-500">No CCS data found for ${document.getElementById('year-filter').value}.</p>
                </div>
              </td>
            `;
            tbody.appendChild(emptyRow);
          }
        } else {
          showNotification(data.message || 'Failed to remove assessment.', 'error');
        }
      })
      .catch(error => {
        showNotification('An error occurred while removing the assessment.', 'error');
        console.error('Error:', error);
      })
      .finally(() => {
        confirmDialog.style.display = 'none';
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        currentRowToRemove = null;
        currentNameToRemove = null;
        currentIdToRemove = null;
      });
    });

    // PDF Generation
    function generatePDF() {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ orientation: "landscape" });

      // Title
      doc.setFontSize(16);
      doc.setTextColor(13, 110, 253);
      const title = "SUMMARY OF TRAINING NEEDS ASSESSMENT FORMS - CCS";
      const titleWidth = doc.getStringUnitWidth(title) * doc.getFontSize() / doc.internal.scaleFactor;
      doc.text(title, (doc.internal.pageSize.getWidth() - titleWidth) / 2, 15);

      // Date
      doc.setFontSize(10);
      doc.setTextColor(0, 0, 0);
      const currentDate = moment().format('MMMM D, YYYY');
      doc.text(`Generated on: ${currentDate}`, 15, 25);

      const table = document.querySelector('table');
      if (!table) return showNotification("No data available to export.", 'error');

      const rows = Array.from(table.querySelectorAll('tbody tr'));
      const data = [];

      rows.forEach(row => {
        if (row.style.display === 'none') return;
        
        const cells = row.querySelectorAll('td');
        if (cells.length < 6) return;

        const name = cells[0]?.textContent.trim() || '';
        const department = cells[1]?.textContent.trim() || '';
        const type = cells[2]?.textContent.trim() || '';
        const status = cells[3]?.textContent.trim() || '';

        const seminarHTML = cells[4]?.innerHTML || '';
        const seminarText = seminarHTML
          .replace(/<br\s*\/?>/gi, '\n')
          .replace(/<[^>]*>/g, '')
          .trim();

        const desiredTraining = cells[5]?.textContent.trim() || '';

        data.push([
          name,
          department,
          type,
          status,
          seminarText,
          desiredTraining
        ]);
      });

      const headers = [[
        'Name',
        'Department',
        'Type',
        'Status',
        'Seminar Attended',
        'Desired Training'
      ]];

      doc.autoTable({
        head: headers,
        body: data,
        startY: 30,
        styles: {
          fontSize: 8,
          textColor: [0, 0, 0],
          halign: 'left',
          valign: 'top',
          lineWidth: 0.1,
          lineColor: [200, 200, 200]
        },
        headStyles: {
          fillColor: [230, 230, 230],
          textColor: [0, 0, 0],
          fontStyle: 'bold',
          lineWidth: 0.1,
          lineColor: [200, 200, 200]
        },
        theme: 'grid',
        margin: { top: 35 }
      });

      doc.save(`CCS_Training_Assessment_${currentDate}.pdf`);
      showNotification('PDF report generated successfully!');
    }

    // Signature Pad Functionality
    let signaturePad;
    const canvas = document.getElementById('signature-canvas');
    const signaturePreview = document.getElementById('signature-preview');
    
    function resizeCanvas() {
      const container = canvas.parentElement;
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      
      canvas.width = container.offsetWidth * ratio;
      canvas.height = container.offsetHeight * ratio;
      canvas.style.width = container.offsetWidth + 'px';
      canvas.style.height = container.offsetHeight + 'px';
      canvas.getContext('2d').scale(ratio, ratio);
      
      if (signaturePad) {
        signaturePad.clear(); // Clear on resize
      }
    }
    
    function initSignaturePad() {
      resizeCanvas();
      signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)'
      });
    }
    
    function clearSignature() {
      if (signaturePad) {
        signaturePad.clear();
        signaturePreview.style.display = 'none';
        canvas.style.display = 'block';
      }
    }
    
    // Handle image upload for signature
    document.getElementById('upload-signature').addEventListener('click', function() {
      document.getElementById('signature-upload').click();
    });
    
    // Image crop variables
    let cropImage = new Image();
    let cropScale = 1;
    let cropX = 0;
    let cropY = 0;
    let cropImageWidth = 0;
    let cropImageHeight = 0;
    
    document.getElementById('signature-upload').addEventListener('change', function(e) {
      if (e.target.files.length > 0) {
        const file = e.target.files[0];
        const reader = new FileReader();
        
        reader.onload = function(event) {
          // Show crop modal
          const cropModal = document.getElementById('image-crop-modal');
          const imageToCrop = document.getElementById('image-to-crop');
          
          cropImage.src = event.target.result;
          imageToCrop.src = event.target.result;
          
          // Initialize crop values
          cropScale = 1;
          cropX = 0;
          cropY = 0;
          
          // Show modal
          cropModal.classList.remove('hidden');
          cropModal.style.display = 'flex';
          
          // Set initial crop position
          updateCropImage();
        };
        reader.readAsDataURL(file);
      }
    });
    
    // Crop controls
    function updateCropImage() {
      const imageToCrop = document.getElementById('image-to-crop');
      
      // Calculate display dimensions based on scale
      const displayWidth = cropImage.naturalWidth * cropScale;
      const displayHeight = cropImage.naturalHeight * cropScale;
      
      // Update image display
      imageToCrop.style.width = `${displayWidth}px`;
      imageToCrop.style.height = `${displayHeight}px`;
      imageToCrop.style.transform = `translate(${cropX}px, ${cropY}px)`;
      
      // Store the actual dimensions for later use
      cropImageWidth = cropImage.naturalWidth;
      cropImageHeight = cropImage.naturalHeight;
    }
    
    // Zoom in
    document.getElementById('zoom-in').addEventListener('click', function() {
      cropScale = Math.min(cropScale + 0.1, 3);
      updateCropImage();
    });
    
    // Zoom out
    document.getElementById('zoom-out').addEventListener('click', function() {
      cropScale = Math.max(cropScale - 0.1, 0.5);
      updateCropImage();
    });
    
    // Move left
    document.getElementById('move-left').addEventListener('click', function() {
      cropX += 10;
      updateCropImage();
    });
    
    // Move right
    document.getElementById('move-right').addEventListener('click', function() {
      cropX -= 10;
      updateCropImage();
    });
    
    // Move up
    document.getElementById('move-up').addEventListener('click', function() {
      cropY += 10;
      updateCropImage();
    });
    
    // Move down
    document.getElementById('move-down').addEventListener('click', function() {
      cropY -= 10;
      updateCropImage();
    });
    
    // Close crop modal
    document.getElementById('close-crop-modal').addEventListener('click', function() {
      document.getElementById('image-crop-modal').style.display = 'none';
    });
    
    document.getElementById('cancel-crop').addEventListener('click', function() {
      document.getElementById('image-crop-modal').style.display = 'none';
    });
    
    // Apply crop
    document.getElementById('apply-crop').addEventListener('click', function() {
      const cropModal = document.getElementById('image-crop-modal');
      const container = document.querySelector('.image-crop-container');
      
      // Create a temporary canvas to crop the image
      const tempCanvas = document.createElement('canvas');
      const ctx = tempCanvas.getContext('2d');
      
      // Set canvas dimensions to match the container
      tempCanvas.width = container.offsetWidth;
      tempCanvas.height = container.offsetHeight;
      
      // Calculate the area to crop (in original image coordinates)
      const scaleX = cropImage.naturalWidth / (cropImage.naturalWidth * cropScale);
      const scaleY = cropImage.naturalHeight / (cropImage.naturalHeight * cropScale);
      
      const sx = -cropX * scaleX;
      const sy = -cropY * scaleY;
      const sWidth = container.offsetWidth * scaleX;
      const sHeight = container.offsetHeight * scaleY;
      
      // Draw the cropped portion of the image
      ctx.drawImage(cropImage, sx, sy, sWidth, sHeight, 0, 0, tempCanvas.width, tempCanvas.height);
      
      // Convert to white background with black signature
      const imageData = ctx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
      const data = imageData.data;
      
      for (let i = 0; i < data.length; i += 4) {
        // Convert to grayscale
        const avg = (data[i] + data[i + 1] + data[i + 2]) / 3;
        // Threshold to make it black and white
        if (avg > 200) { // Light pixels become white
          data[i] = 255;     // R
          data[i + 1] = 255; // G
          data[i + 2] = 255; // B
          data[i + 3] = 255; // A
        } else { // Dark pixels become black
          data[i] = 0;     // R
          data[i + 1] = 0; // G
          data[i + 2] = 0; // B
          data[i + 3] = 255; // A
        }
      }
      
      ctx.putImageData(imageData, 0, 0);
      
      // Update the signature preview
      signaturePreview.src = tempCanvas.toDataURL();
      signaturePreview.style.display = 'block';
      canvas.style.display = 'none';
      
      // Set the signature data for form submission
      document.getElementById('signature-data').value = tempCanvas.toDataURL();
      
      // Close the modal
      cropModal.style.display = 'none';
    });
    
    // Clear signature button
    document.getElementById('clear-signature').addEventListener('click', clearSignature);

    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
      checkSemesterNotification();
      initSignaturePad();
      window.addEventListener('resize', resizeCanvas);
    });

    // Form submission handling
    const form = document.getElementById('assessment-form');
    if (form) {
      form.addEventListener('submit', function(e) {
        // Always capture signature data
        if (signaturePreview.style.display !== 'none') {
          // Use the cropped signature
          document.getElementById('signature-data').value = signaturePreview.src;
        } else if (signaturePad && !signaturePad.isEmpty()) {
          const signatureDataURL = signaturePad.toDataURL(); // Get base64 image
          document.getElementById('signature-data').value = signatureDataURL;
        }
        
        // For print action, prevent default and handle in new window
        if (e.submitter && e.submitter.value === 'print') {
          e.preventDefault();
          form.target = '_blank';
          form.submit();
          setTimeout(() => { form.target = '_blank'; }, 100);
          return;
        }
        
        // For submit action, show loading state
        if (e.submitter && e.submitter.value === 'submit') {
          e.preventDefault();
          
          const submitBtn = document.getElementById('submitEvaluation');
          const submitText = document.getElementById('submit-text');
          const submitSpinner = document.getElementById('submit-spinner');
          
          submitText.classList.add('hidden');
          submitSpinner.classList.remove('hidden');
          submitBtn.disabled = true;
          
          // Submit form via AJAX
          const formData = new FormData(form);
          
          fetch('process_assessment.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showNotification('Assessment submitted successfully!');
              setTimeout(() => {
                window.location.reload();
              }, 1500);
            } else {
              showNotification(data.message || 'Failed to submit assessment.', 'error');
            }
          })
          .catch(error => {
            showNotification('An error occurred while submitting the assessment.', 'error');
            console.error('Error:', error);
          })
          .finally(() => {
            submitText.classList.remove('hidden');
            submitSpinner.classList.add('hidden');
            submitBtn.disabled = false;
          });
        }
      });
    }
    
    // Form validation
    document.getElementById('submitEvaluation')?.addEventListener('click', function(e) {
      // Validate required fields
      let isValid = true;
      const requiredFields = document.querySelectorAll('#assessment-form input[required], #assessment-form textarea[required]');
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          field.classList.add('border-red-500');
          isValid = false;
        } else {
          field.classList.remove('border-red-500');
        }
      });
      
      // Validate signature
      const signatureData = document.getElementById('signature-data').value;
      if (!signatureData) {
        showNotification('Please provide a signature (draw or upload).', 'error');
        isValid = false;
      }
      
      if (!isValid) {
        e.preventDefault();
        showNotification('Please fill in all required fields.', 'error');
      }
    });
  </script>
</body>
</html>