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
            dark: '#1e293b',
            light: '#f8fafc'
          },
          borderRadius: {
            DEFAULT: '0.5rem',
            button: '0.375rem'
          },
          boxShadow: {
            card: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
            hover: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)'
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" />
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f8fafc;
    }
    .sidebar {
      background: linear-gradient(180deg, #1e3a8a 0%, #1e3a8a 100%);
    }
    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .status-teaching {
      background-color: #d1fae5;
      color: #065f46;
    }
    .status-nonteaching {
      background-color: #fee2e2;
      color: #991b1b;
    }
    .table-row-hover:hover {
      background-color: #f1f5f9;
      transition: background-color 0.2s ease;
    }
    .pagination-btn {
      transition: all 0.2s ease;
    }
    .pagination-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .profile-card {
      transition: all 0.3s ease;
    }
    .profile-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .modal-overlay {
      background-color: rgba(0, 0, 0, 0.5);
    }
    .modal-content {
      max-height: 90vh;
      overflow-y: auto;
    }
    .view-button {
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .modal-container {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 2rem;
    }
    .training-item {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (min-width: 768px) {
      .training-item {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }
  </style>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="sidebar w-64 text-white shadow-sm fixed top-0 left-0 h-screen overflow-y-auto">
    <div class="h-full flex flex-col">
      <div class="p-6 flex items-center">
        <img src="images/lspubg2.png" alt="Logo" class="w-10 h-10 mr-2">
        <a href="admin_page.php" class="text-lg font-semibold text-white">Admin Dashboard</a>
      </div>
      <nav class="flex-1 px-4">
        <div class="space-y-1">
          <a href="admin_page.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-white/10 transition">
            <i class="ri-dashboard-line w-5 h-5 mr-3"></i> Dashboard
          </a>
          <a href="user_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md bg-white/20 text-white hover:bg-white/30 transition">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i> Assessment Forms
          </a>
          <a href="user_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-blue-700 transition-all">
            <i class="ri-file-list-3-line w-5 h-5 mr-3"></i>
            IDP Forms
          </a>
        </div>
      </nav>
      <div class="p-4">
        <a href="homepage.php" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-md hover:bg-red-500/90 text-white transition">
          <i class="ri-logout-box-line w-5 h-5 mr-3"></i> Sign Out
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="ml-64 flex-1 p-8">
    <div class="max-w-7xl mx-auto">
      <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-100">
        <!-- Title and Stats -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-800">Assessment Form Submissions</h1>
            <p class="text-gray-600">View and manage all submitted assessment forms</p>
          </div>
          <div class="bg-primary/10 px-4 py-2 rounded-lg">
            <p class="text-sm text-primary font-medium">
              <span class="font-bold"><?= $total_rows ?></span> records found 
              <?= $selected_month > 0 ? 'for ' . date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)) : 'for ' . htmlspecialchars($selected_year) ?>
            </p>
          </div>
        </div>

        <!-- Filters Section -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <!-- Year Filter -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Year</label>
            <div class="flex flex-wrap gap-2">
              <?php while ($yearRow = $years_result->fetch_assoc()): ?>
                <a href="?year=<?= urlencode($yearRow['year']) ?>&month=<?= $selected_month ?>&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>"
                   class="px-3 py-1 rounded-lg font-medium transition <?= $selected_year == $yearRow['year'] ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                  <?= htmlspecialchars($yearRow['year']) ?>
                </a>
              <?php endwhile; ?>
            </div>
          </div>

          <!-- Month Filter -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Month</label>
            <div class="flex flex-wrap gap-2">
              <a href="?year=<?= $selected_year ?>&month=0&teaching_status=<?= urlencode($teaching_status) ?>&search=<?= urlencode($search) ?>"
                 class="px-3 py-1 rounded-lg font-medium transition <?= $selected_month == 0 ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
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
                   class="px-3 py-1 rounded-lg font-medium transition <?= $selected_month == $monthNum ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                  <?= $monthName ?>
                </a>
              <?php endwhile; ?>
            </div>
          </div>

          <!-- Teaching Status Filter -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
            <div class="flex flex-wrap gap-2">
              <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&search=<?= urlencode($search) ?>"
                 class="px-3 py-1 rounded-lg font-medium transition <?= empty($teaching_status) ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                All
              </a>
              <?php while ($statusRow = $statuses_result->fetch_assoc()): ?>
                <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&teaching_status=<?= urlencode($statusRow['teaching_status']) ?>&search=<?= urlencode($search) ?>"
                   class="px-3 py-1 rounded-lg font-medium transition <?= $teaching_status == $statusRow['teaching_status'] ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                  <?= htmlspecialchars($statusRow['teaching_status']) ?>
                </a>
              <?php endwhile; ?>
            </div>
          </div>

          <!-- Search Input -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <label for="search-input" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <form method="GET" class="relative">
              <input type="hidden" name="year" value="<?= htmlspecialchars($selected_year) ?>">
              <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
              <input type="hidden" name="teaching_status" value="<?= htmlspecialchars($teaching_status) ?>">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="ri-search-line text-lg text-gray-500"></i>
              </div>
              <input type="search" name="search" id="search-input"
                     class="w-full pl-10 pr-4 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-primary focus:border-primary transition"
                     placeholder="Search by name or department..."
                     value="<?= htmlspecialchars($search) ?>" />
            </form>
          </div>
        </div>

        <!-- Export Button -->
        <div class="flex justify-between items-center mb-6">
          <div class="text-sm text-gray-500">
            Showing page <?= $page ?> of <?= $total_pages ?>
          </div>
          <button onclick="generatePDF()"
                  class="flex items-center bg-accent hover:bg-accent/90 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm hover:shadow-md">
            <i class="ri-download-2-line mr-2"></i> Export PDF
          </button>
        </div>

        <!-- Table Section -->
        <section id="printable" class="bg-white rounded-lg shadow overflow-hidden border border-gray-200 mb-6">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seminars Attended</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desired Training</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="table-row-hover">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="text-xs text-gray-500"><?= date('M d, Y', strtotime($row['submission_date'])) ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($row['department']) ?></td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (strtolower($row['teaching_status']) == 'teaching'): ?>
                          <span class="status-badge status-teaching">Teaching</span>
                        <?php else: ?>
                          <span class="status-badge status-nonteaching">Non-Teaching</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                        <?php
                        if (!empty($row['training_history'])) {
                            $seminars = json_decode($row['training_history'], true);
                            if (is_array($seminars)) {
                                echo '<div class="space-y-2">';
                                foreach ($seminars as $seminar) {
                                    echo '<div class="bg-gray-50 p-2 rounded">';
                                    echo '<p class="font-medium text-gray-800">' . htmlspecialchars($seminar['training'] ?? '') . '</p>';
                                    echo '<p class="text-xs text-gray-500">' . 
                                         htmlspecialchars($seminar['date'] ?? '') . ' • ' . 
                                         (isset($seminar['start_time']) ? htmlspecialchars($seminar['start_time']) : '') . 
                                         (isset($seminar['end_time']) ? ' - ' . htmlspecialchars($seminar['end_time']) : '') . ' • ' . 
                                         (isset($seminar['duration']) ? htmlspecialchars($seminar['duration']) : '') . ' • ' . 
                                         htmlspecialchars($seminar['venue'] ?? '') . '</p>';
                                    echo '</div>';
                                }
                                echo '</div>';
                            } else {
                                echo '<span class="text-gray-400">—</span>';
                            }
                        } else {
                            echo '<span class="text-gray-400">—</span>';
                        }
                        ?>
                      </td>
                      <td class="px-6 py-4 text-sm text-gray-600">
                        <div class="whitespace-pre-line"><?= nl2br(htmlspecialchars($row['desired_skills'])) ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div class="flex items-center">
                          <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>&view_id=<?= $row['id'] ?>#view-modal"
                             class="view-button bg-primary hover:bg-primary/90 text-white rounded-lg transition shadow-sm hover:shadow-md">
                            <i class="ri-eye-line mr-1"></i> View
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                      No submissions found <?= $selected_month > 0 ? 'for ' . date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)) : 'for ' . htmlspecialchars($selected_year) ?>.
                      <?php if (!empty($search) || !empty($teaching_status)): ?>
                        <br>Try adjusting your search or filter criteria.
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-between items-center mt-6">
          <div class="text-sm text-gray-500">
            Showing <?= $perPage * ($page - 1) + 1 ?> to <?= min($perPage * $page, $total_rows) ?> of <?= $total_rows ?> entries
          </div>
          <nav class="inline-flex items-center space-x-1" aria-label="Pagination">
            <?php if ($page > 1): ?>
              <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
                 class="pagination-btn px-3 py-1 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition text-sm font-medium">
                <i class="ri-arrow-left-line"></i> Previous
              </a>
            <?php else: ?>
              <span class="px-3 py-1 rounded-lg bg-gray-100 text-gray-400 text-sm font-medium cursor-not-allowed">
                <i class="ri-arrow-left-line"></i> Previous
              </span>
            <?php endif; ?>

            <?php 
            // Show page numbers with ellipsis
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo '<a href="?year='.urlencode($selected_year).'&month='.$selected_month.'&page=1&search='.urlencode($search).'&teaching_status='.urlencode($teaching_status).'" class="px-3 py-1 rounded-lg font-medium bg-gray-200 text-gray-700 hover:bg-gray-300">1</a>';
                if ($start_page > 2) {
                    echo '<span class="px-3 py-1">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
              <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
                 class="px-3 py-1 rounded-lg font-medium <?= $page == $i ? 'bg-primary text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' ?>">
                <?= $i ?>
              </a>
            <?php endfor;
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="px-3 py-1">...</span>';
                }
                echo '<a href="?year='.urlencode($selected_year).'&month='.$selected_month.'&page='.$total_pages.'&search='.urlencode($search).'&teaching_status='.urlencode($teaching_status).'" class="px-3 py-1 rounded-lg font-medium bg-gray-200 text-gray-700 hover:bg-gray-300">'.$total_pages.'</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
              <a href="?year=<?= urlencode($selected_year) ?>&month=<?= $selected_month ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>"
                 class="pagination-btn px-3 py-1 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition text-sm font-medium">
                Next <i class="ri-arrow-right-line"></i>
              </a>
            <?php else: ?>
              <span class="px-3 py-1 rounded-lg bg-gray-100 text-gray-400 text-sm font-medium cursor-not-allowed">
                Next <i class="ri-arrow-right-line"></i>
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
<?php if ($view_data): ?>
<div id="view-modal" class="fixed inset-0 z-50 modal-overlay" style="display: block;">
  <div class="modal-container">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl modal-content relative">
      
      <!-- Close Button -->
      <a href="?year=<?= $selected_year ?>&month=<?= $selected_month ?>&search=<?= urlencode($search) ?>&teaching_status=<?= urlencode($teaching_status) ?>&page=<?= $page ?>"
         class="absolute top-4 right-4 text-gray-600 hover:text-red-600 text-2xl font-bold transition">
         ✕
      </a>

      <div class="p-6 pt-12">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-2xl font-bold text-gray-800">Employee Profile</h3>
          
          <!-- Print Button -->
          <form id="printForm" action="Training Needs Assessment Form_pdf.php" method="post" target="_blank">
            <!-- Personal Information -->
            <input type="hidden" name="name" value="<?= htmlspecialchars($view_data['name'] ?? '') ?>">
            <input type="hidden" name="educationalAttainment" value="<?= htmlspecialchars($view_data['educationalAttainment'] ?? '') ?>">
            <input type="hidden" name="specialization" value="<?= htmlspecialchars($view_data['specialization'] ?? '') ?>">
            <input type="hidden" name="designation" value="<?= htmlspecialchars($view_data['designation'] ?? '') ?>">
            <input type="hidden" name="department" value="<?= htmlspecialchars($view_data['department'] ?? '') ?>">
            <input type="hidden" name="yearsInLSPU" value="<?= htmlspecialchars($view_data['yearsInLSPU'] ?? '') ?>">
            <input type="hidden" name="teaching_status" value="<?= htmlspecialchars($view_data['teaching_status'] ?? '') ?>">
            
            <!-- Training History (JSON encoded) -->
            <input type="hidden" name="training_history" value="<?= htmlspecialchars($view_data['training_history'] ?? '[]') ?>">
            
            <!-- Other Fields -->
            <input type="hidden" name="desired_skills" value="<?= htmlspecialchars($view_data['desired_skills'] ?? '') ?>">
            <input type="hidden" name="comments" value="<?= htmlspecialchars($view_data['comments'] ?? '') ?>">

            <button type="submit" class="flex items-center bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm hover:shadow-md">
              <i class="ri-printer-line mr-2"></i> Print
            </button>
          </form>
        </div>
        
        <!-- Profile Card -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden profile-card border border-gray-200 mb-6">
          <div class="p-6">
            <div class="flex flex-col md:flex-row gap-6">
              <!-- Left Column - Basic Info -->
              <div class="md:w-1/3">
                <div class="flex items-center space-x-4 mb-6">
                  <div class="bg-primary/10 w-16 h-16 rounded-full flex items-center justify-center">
                    <i class="ri-user-3-line text-3xl text-primary"></i>
                  </div>
                  <div>
                    <h4 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($view_data['name'] ?? '') ?></h4>
                    <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold <?= strtolower($view_data['teaching_status'] ?? '') == 'teaching' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                      <?= htmlspecialchars($view_data['teaching_status'] ?? '') ?>
                    </span>
                  </div>
                </div>
                
                <div class="space-y-4">
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Educational Attainment</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['educationalAttainment'] ?? 'Not specified') ?></p>
                  </div>
                  
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Specialization</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['specialization'] ?? 'Not specified') ?></p>
                  </div>
                  
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Designation</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['designation'] ?? 'Not specified') ?></p>
                  </div>
                </div>
              </div>
              
              <!-- Middle Column - Department Info -->
              <div class="md:w-1/3">
                <div class="space-y-4">
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Department</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['department'] ?? '') ?></p>
                  </div>
                  
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Years in LSPU</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['yearsInLSPU'] ?? 'Not specified') ?></p>
                  </div>
                  
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Type of Employment</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['teaching_status'] ?? 'Not specified') ?></p>
                  </div>
                  
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Submission Date</h5>
                    <p class="text-gray-800"><?= !empty($view_data['submission_date']) ? date('F j, Y', strtotime($view_data['submission_date'])) : 'Not specified' ?></p>
                  </div>
                </div>
              </div>
              
              <!-- Right Column - Contact Info -->
              <div class="md:w-1/3">
                <div class="space-y-4">
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Email</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['email'] ?? 'Not specified') ?></p>
                  </div>
                  
                  <div>
                    <h5 class="text-sm font-medium text-gray-500">Contact Number</h5>
                    <p class="text-gray-800"><?= htmlspecialchars($view_data['contact_number'] ?? 'Not specified') ?></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Training History Section -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden profile-card border border-gray-200 mb-6">
          <div class="p-6">
            <h4 class="text-lg font-bold text-gray-800 mb-4">Training History</h4>
            
            <?php if (!empty($view_data['training_history'])): 
              $trainings = json_decode($view_data['training_history'], true);
              if (is_array($trainings) && count($trainings) > 0): ?>
                <div class="space-y-4">
                  <?php foreach ($trainings as $training): ?>
                    <div class="bg-gray-50 p-4 rounded-lg">
                      <div class="grid training-item gap-4">
                        <div>
                          <h6 class="text-sm font-medium text-gray-500">Training/Seminar</h6>
                          <p class="text-gray-800 font-medium"><?= htmlspecialchars($training['training'] ?? '') ?></p>
                        </div>
                        <div>
                          <h6 class="text-sm font-medium text-gray-500">Date</h6>
                          <p class="text-gray-800"><?= htmlspecialchars($training['date'] ?? '') ?></p>
                        </div>
                        <div>
                          <h6 class="text-sm font-medium text-gray-500">Time</h6>
                          <p class="text-gray-800">
                            <?= isset($training['start_time']) ? htmlspecialchars($training['start_time']) : '' ?>
                            <?= isset($training['end_time']) ? ' - ' . htmlspecialchars($training['end_time']) : '' ?>
                          </p>
                        </div>
                        <div>
                          <h6 class="text-sm font-medium text-gray-500">Duration</h6>
                          <p class="text-gray-800"><?= isset($training['duration']) ? htmlspecialchars($training['duration']) : '' ?></p>
                        </div>
                        <div>
                          <h6 class="text-sm font-medium text-gray-500">Venue</h6>
                          <p class="text-gray-800"><?= htmlspecialchars($training['venue'] ?? '') ?></p>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-gray-500 italic">No training history recorded.</p>
              <?php endif; ?>
            <?php else: ?>
              <p class="text-gray-500 italic">No training history recorded.</p>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Desired Training Section -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden profile-card border border-gray-200 mb-6">
          <div class="p-6">
            <h4 class="text-lg font-bold text-gray-800 mb-4">Desired Training/Seminar</h4>
            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="text-gray-800 whitespace-pre-line"><?= !empty($view_data['desired_skills']) ? nl2br(htmlspecialchars($view_data['desired_skills'])) : 'Not specified' ?></p>
            </div>
          </div>
        </div>
        
        <!-- Comments Section -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden profile-card border border-gray-200">
          <div class="p-6">
            <h4 class="text-lg font-bold text-gray-800 mb-4">Comments/Suggestions</h4>
            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="text-gray-800 whitespace-pre-line"><?= !empty($view_data['comments']) ? nl2br(htmlspecialchars($view_data['comments'])) : 'Not specified' ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

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