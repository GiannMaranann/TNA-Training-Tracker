<?php
include 'config.php'; // database connection as $con

try {
    $sql = "SELECT 
                u.id AS user_id,
                u.name,
                u.department,
                u.teaching_status,
                MAX(a.evaluated) AS evaluated,   -- kumuha ng latest evaluated
                MAX(a.status) AS status          -- kumuha ng latest status
            FROM users u
            LEFT JOIN assessments a ON u.id = a.user_id
            WHERE u.department = 'CCS'
              AND u.teaching_status IS NOT NULL
              AND u.teaching_status != ''
            GROUP BY u.id, u.name, u.department, u.teaching_status
            ORDER BY MAX(a.created_at) DESC";   // pinaka-latest record ng bawat user
    
    // Prepare and execute
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $con->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();


} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
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
            info: '#3b82f6',
            dark: '#1e293b',
            light: '#f8fafc'
          },
          borderRadius: {
            DEFAULT: '8px',
            'button': '8px'
          },
          fontFamily: {
            'poppins': ['Poppins', 'sans-serif']
          },
          boxShadow: {
            'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
            'button': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)'
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f3f4f6;
    }
    .table-container {
      overflow-x: auto;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    table {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      border-collapse: separate;
      border-spacing: 0;
    }
    th {
      position: sticky;
      top: 0;
      background-color: #1e293b;
      color: white;
      font-weight: 500;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
    }
    tr:last-child td {
      border-bottom: none;
    }
    tbody tr:nth-child(even) {
      background-color: #f8fafc;
    }
    tbody tr:hover {
      background-color: #f1f5f9;
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: capitalize;
    }
    .status-completed {
      background-color: #d1fae5;
      color: #065f46;
    }
    .status-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    .status-evaluated {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-top: 1.5rem;
      gap: 0.5rem;
    }
    .pagination button {
      padding: 0.5rem 1rem;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      background-color: white;
      transition: all 0.2s;
    }
    .pagination button:hover:not(.active) {
      background-color: #f1f5f9;
    }
    .pagination button.active {
      background-color: #6366f1;
      color: white;
      border-color: #6366f1;
    }
    .sidebar-link {
      transition: all 0.2s;
      border-radius: 8px;
    }
    .sidebar-link:hover {
      background-color: #4338ca;
    }
    .sidebar-link.active {
      background-color: #4338ca;
    }
    .filter-btn {
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      border: 1px solid #e2e8f0;
      background-color: white;
    }
    .filter-btn:hover {
      background-color: #f1f5f9;
    }
    .filter-btn.active {
      background-color: #6366f1;
      color: white;
      border-color: #6366f1;
    }
    .search-input {
      transition: all 0.2s;
      border-radius: 8px;
      padding-left: 2.5rem;
    }
    .search-input:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }
    .action-btn {
      transition: all 0.2s;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }
    .action-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .action-btn:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
    }
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background-color: white;
      border-radius: 12px;
      width: 90%;
      max-width: 1000px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }
    .modal-header {
      padding: 1.5rem;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-body {
      padding: 1.5rem;
    }
    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #6b7280;
    }
    .modal-iframe {
      width: 100%;
      height: 70vh;
      border: none;
      border-radius: 8px;
    }
  </style>
</head>
<body class="flex h-screen bg-gray-50">
  <!-- Sidebar -->
  <aside class="w-64 bg-blue-900 text-white shadow-sm flex-shrink-0">
    <div class="h-full flex flex-col">
      <div class="p-6 flex items-center">
        <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2" />
        <span class="text-lg font-semibold text-white">CCS Admin</span>
      </div>
      <nav class="flex-1 px-4">
        <div class="space-y-1">
          <a href="CCS.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 sidebar-link">
            <i class="ri-dashboard-line mr-3 w-5 h-5 flex-shrink-0"></i>
            <span class="whitespace-nowrap">Dashboard</span>
          </a>
          <a href="ccs_eval.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-blue-800 text-white sidebar-link active">
            <i class="ri-file-list-3-line w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="whitespace-nowrap">Evaluation</span>
          </a>
        </div>
      </nav>
      <div class="p-4">
        <a href="homepage.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-red-500 text-white sidebar-link">
          <i class="ri-logout-box-line mr-3 w-5 h-5 flex-shrink-0"></i>
          <span class="whitespace-nowrap">Sign Out</span>
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 overflow-auto p-8">
    <div class="bg-white rounded-xl shadow-sm p-6 max-w-7xl mx-auto">
      <!-- Title and Search -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-800">CCS Faculty Evaluations</h2>
          <p class="text-sm text-gray-500 mt-1">View and manage faculty training evaluations</p>
        </div>
        <div class="relative w-full md:w-96">
          <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
            <i class="ri-search-line"></i>
          </div>
          <input type="search" id="search-input" class="search-input w-full pl-10 pr-4 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Search by name...">
        </div>
      </div>

      <!-- Filter Buttons -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Faculty Type:</label>
        <div class="flex gap-2">
          <button type="button" class="filter-btn active" data-filter="all">All Faculty</button>
          <button type="button" class="filter-btn" data-filter="teaching">Teaching</button>
          <button type="button" class="filter-btn" data-filter="non-teaching">Non-Teaching</button>
        </div>
      </div>

      <!-- Table -->
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th class="px-6 py-3">Name</th>
              <th class="px-6 py-3">Department</th>
              <th class="px-6 py-3">Type Employment </th>
              <th class="px-6 py-3">Evaluation Status</th>
              <th class="px-6 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="evaluation-table-body">
            <?php if ($result && $result->num_rows > 0): ?>
              <?php 
              $counter = 0;
              while ($row = $result->fetch_assoc()): 
                if ($counter >= 7) break;
                $status = !empty($row['submission_date']) ? 'completed' : 'pending';
                $type = $row['teaching_status'] === 'Teaching' ? 'teaching' : 'non-teaching';
                $evaluated = $row['evaluated'] ? 'evaluated' : 'pending';
                $counter++;
              ?>
                <tr data-name="<?= htmlspecialchars($row['name']) ?>" 
                    data-department="<?= htmlspecialchars($row['department']) ?>"
                    data-status="<?= $status ?>"
                    data-type="<?= $type ?>"
                    data-evaluated="<?= $evaluated ?>"
                    data-id="<?= $row['evaluation_id'] ?>"
                    class="hover:bg-gray-50 transition-colors">
                  <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($row['name']) ?></td>
                  <td class="px-6 py-4 text-gray-600">CCS</td>
                  <td class="px-6 py-4">
                    <span class="status-badge <?= $type === 'teaching' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                      <?= htmlspecialchars($row['teaching_status']) ?>
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="status-badge <?= $evaluated === 'evaluated' ? 'status-evaluated' : 'status-pending' ?>">
                      <?= $evaluated === 'evaluated' ? 'Evaluated' : 'Pending Evaluation' ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <button 
                      type="button"
                      class="evaluate-btn action-btn bg-indigo-600 text-white hover:bg-indigo-700"
                      data-name="<?= htmlspecialchars($row['name']) ?>"
                      data-id="<?= htmlspecialchars($row['evaluation_id']) ?>"
                      data-user-id="<?= htmlspecialchars($row['user_id']) ?>"
                      <?= $status === 'Pending Evaluation' ? 'disabled' : '' ?>
                    >
                      <i class="ri-star-line"></i>
                      Evaluate
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="px-6 py-8 text-center">
                  <div class="flex flex-col items-center justify-center py-8">
                    <i class="ri-file-search-line text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900">No evaluations found</h3>
                    <p class="mt-1 text-sm text-gray-500">No CCS faculty evaluations with teaching status found.</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="pagination" id="pagination">
        <button id="prev-page" class="px-4 py-2 rounded-lg hover:bg-gray-100">
          <i class="ri-arrow-left-line"></i>
        </button>
        <div id="page-numbers" class="flex gap-1"></div>
        <button id="next-page" class="px-4 py-2 rounded-lg hover:bg-gray-100">
          <i class="ri-arrow-right-line"></i>
        </button>
      </div>
    </div>
  </main>

  <!-- Modal for Evaluation Form -->
  <div id="evaluation-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="text-lg font-semibold text-gray-900">Training Program Impact Assessment</h3>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <iframe id="evaluation-iframe" class="modal-iframe" src="about:blank"></iframe>
      </div>
    </div>
  </div>

  <script>
    // Search Functionality
    document.getElementById('search-input').addEventListener('input', function() {
      const query = this.value.trim().toLowerCase();
      const rows = document.querySelectorAll('#evaluation-table-body tr');
      
      rows.forEach(row => {
        const name = row.getAttribute('data-name').toLowerCase();
        const department = row.getAttribute('data-department').toLowerCase();
        const type = row.getAttribute('data-type').toLowerCase();
        
        if (name.includes(query) || department.includes(query) || type.includes(query)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
      
      // Update pagination after search
      initPagination();
    });

    // Filter by Type Buttons
    document.querySelectorAll('.filter-btn').forEach(button => {
      button.addEventListener('click', function() {
        // Update active state
        document.querySelectorAll('.filter-btn').forEach(btn => {
          btn.classList.remove('active', 'bg-indigo-600', 'text-white');
        });
        this.classList.add('active', 'bg-indigo-600', 'text-white');
        
        const filter = this.getAttribute('data-filter');
        const rows = document.querySelectorAll('#evaluation-table-body tr');
        
        rows.forEach(row => {
          const rowType = row.getAttribute('data-type');
          
          if (filter === 'all' || rowType === filter) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
        
        // Update pagination after filter
        initPagination();
      });
    });

    // Modal functionality
    const modal = document.getElementById('evaluation-modal');
    const modalIframe = document.getElementById('evaluation-iframe');
    const modalClose = document.querySelector('.modal-close');
    
    // Open modal when evaluate button is clicked
    document.querySelectorAll('.evaluate-btn').forEach(button => {
      button.addEventListener('click', function() {
        if (this.disabled) return;
        
        const facultyName = this.getAttribute('data-name');
        const evaluationId = this.getAttribute('data-id');
        const userId = this.getAttribute('data-user-id');
        
        // Construct the URL with parameters
        const url = `training_program_impact_assessment_form.html?name=${encodeURIComponent(facultyName)}&id=${encodeURIComponent(evaluationId)}&user_id=${encodeURIComponent(userId)}`;
        
        // Set the iframe source
        modalIframe.src = url;
        
        // Show the modal
        modal.style.display = 'flex';
      });
    });
    
    // Close modal when X is clicked
    modalClose.addEventListener('click', function() {
      modal.style.display = 'none';
      modalIframe.src = 'about:blank';
    });
    
    // Close modal when clicking outside the modal content
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        modal.style.display = 'none';
        modalIframe.src = 'about:blank';
      }
    });

    // Function to handle messages from the iframe (if needed)
    window.addEventListener('message', function(e) {
      if (e.data === 'closeModal') {
        modal.style.display = 'none';
        modalIframe.src = 'about:blank';
        // You might want to refresh the table here if the evaluation was submitted
        window.location.reload();
      }
    });

    // Pagination functionality
    function initPagination() {
      const visibleRows = Array.from(document.querySelectorAll('#evaluation-table-body tr')).filter(
        row => row.style.display !== 'none'
      );
      const rowsPerPage = 7;
      const pageCount = Math.ceil(visibleRows.length / rowsPerPage);
      const pageNumbers = document.getElementById('page-numbers');
      
      // Clear existing page numbers
      pageNumbers.innerHTML = '';
      
      // Create page number buttons
      for (let i = 1; i <= pageCount; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.textContent = i;
        pageBtn.className = 'px-4 py-2 rounded-lg';
        if (i === 1) pageBtn.classList.add('active', 'bg-indigo-600', 'text-white');
        pageBtn.addEventListener('click', () => goToPage(i, visibleRows));
        pageNumbers.appendChild(pageBtn);
      }
      
      // Set up previous/next buttons
      document.getElementById('prev-page').addEventListener('click', () => {
        const currentPage = document.querySelector('#page-numbers button.active');
        if (currentPage) {
          const currentPageNum = parseInt(currentPage.textContent);
          if (currentPageNum > 1) {
            goToPage(currentPageNum - 1, visibleRows);
          }
        }
      });
      
      document.getElementById('next-page').addEventListener('click', () => {
        const currentPage = document.querySelector('#page-numbers button.active');
        if (currentPage) {
          const currentPageNum = parseInt(currentPage.textContent);
          if (currentPageNum < pageCount) {
            goToPage(currentPageNum + 1, visibleRows);
          }
        }
      });
      
      // Show first page by default
      if (pageCount > 0) {
        goToPage(1, visibleRows);
      }
    }
    
    function goToPage(pageNum, visibleRows) {
      const rowsPerPage = 7;
      const startIndex = (pageNum - 1) * rowsPerPage;
      const endIndex = startIndex + rowsPerPage;
      
      // Hide all rows first
      document.querySelectorAll('#evaluation-table-body tr').forEach(row => {
        row.style.display = 'none';
      });
      
      // Show only the visible rows for the current page
      for (let i = startIndex; i < endIndex && i < visibleRows.length; i++) {
        visibleRows[i].style.display = '';
      }
      
      // Update active page button
      const pageButtons = document.querySelectorAll('#page-numbers button');
      pageButtons.forEach(btn => {
        btn.classList.remove('active', 'bg-indigo-600', 'text-white');
        if (parseInt(btn.textContent) === pageNum) {
          btn.classList.add('active', 'bg-indigo-600', 'text-white');
        }
      });
    }

    // Initialize pagination on load
    document.addEventListener('DOMContentLoaded', initPagination);
  </script>
</body>
</html>