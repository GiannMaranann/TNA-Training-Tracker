<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 1;

date_default_timezone_set('Asia/Manila');

$mysqli = new mysqli("localhost", "root", "", "user_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get submission deadline
$deadline_result = $mysqli->query("SELECT submission_deadline FROM settings LIMIT 1");
if ($deadline_row = $deadline_result->fetch_assoc()) {
    $deadline = strtotime($deadline_row['submission_deadline']);
    $formatted_deadline = date("F j, Y g:i A", $deadline);
} else {
    $deadline = null;
    $formatted_deadline = "Not set";
}

// Pagination setup
$per_page = 3; // Number of items per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total number of submissions for pagination
$count_stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM assessments WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_assessments = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_assessments / $per_page);

// Fetch paginated submissions from the user (latest first)
$stmt = $mysqli->prepare("SELECT * FROM assessments WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$assessments = $result->fetch_all(MYSQLI_ASSOC);

// Determine overall status (based on most recent submission)
if (!empty($assessments) && $deadline) {
    $latest_submission = strtotime($assessments[0]['created_at']);
    $status = $latest_submission <= $deadline ? "On Time" : "Late";
    $status_color = $latest_submission <= $deadline ? "text-green-600" : "text-yellow-600";
} else {
    $status = "No Submission";
    $status_color = "text-red-600";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>TNA Records</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    .card {
      transition: all 0.3s ease;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
    }
    .status-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .on-time {
      background-color: #dcfce7;
      color: #166534;
    }
    .late {
      background-color: #fef3c7;
      color: #92400e;
    }
    .no-submission {
      background-color: #fee2e2;
      color: #991b1b;
    }
    .pagination {
      display: flex;
      justify-content: center;
      margin-top: 2rem;
    }
    .pagination a, .pagination span {
      padding: 0.5rem 1rem;
      margin: 0 0.25rem;
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      color: #4b5563;
      text-decoration: none;
    }
    .pagination a:hover {
      background-color: #f3f4f6;
    }
    .pagination .active {
      background-color: #3b82f6;
      color: white;
      border-color: #3b82f6;
    }
    .pagination .disabled {
      color: #9ca3af;
      pointer-events: none;
    }
  </style>
</head>
<body class="bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-800">Training Needs Assessment</h1>
      <div class="flex items-center gap-4 mt-2">
        <div class="text-sm text-gray-600">
          <span class="font-medium">Status:</span>
          <span class="<?= $status_color ?> font-semibold ml-1"><?= $status ?></span>
        </div>
        <div class="text-sm text-gray-600">
          <span class="font-medium">Deadline:</span>
          <span class="font-semibold ml-1"><?= $formatted_deadline ?></span>
        </div>
      </div>
    </div>

    <?php if (!empty($assessments)): ?>
      <div class="grid gap-6">
        <?php foreach ($assessments as $index => $entry): ?>
          <?php
          $training = json_decode($entry['training_history'], true);
          $skills = json_decode($entry['desired_skills'], true);
          $created_at = strtotime($entry['created_at']);
          $formatted_date = date("F j, Y g:i A", $created_at);
          
          $submission_status = "No Deadline";
          $status_class = "bg-gray-200 text-gray-800";
          
          if ($deadline) {
              $submission_status = $created_at <= $deadline ? "On Time" : "Late";
              $status_class = $created_at <= $deadline ? "on-time" : "late";
          }
          
          // Calculate the assessment number based on total count and current position
          $assessment_number = $total_assessments - (($page - 1) * $per_page + $index);
          ?>
          
          <div class="card bg-white rounded-xl p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
              <h3 class="text-lg font-semibold text-gray-800">
                Assessment #<?= $assessment_number ?>
                <span class="status-badge <?= $status_class ?> ml-2">
                  <?= $submission_status ?>
                </span>
              </h3>
              <div class="text-sm text-gray-500">
                Submitted: <?= $formatted_date ?>
              </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6">
              <!-- Training History -->
              <div>
                <h4 class="font-bold text-gray-700 mb-3 flex items-center">
                  Training History
                </h4>
                <?php if (!empty($training)): ?>
                  <ul class="space-y-4">
                    <?php foreach ($training as $t): ?>
                      <?php
                      $date = htmlspecialchars($t['date'] ?? '');
                      $start = htmlspecialchars($t['start_time'] ?? '');
                      $end = htmlspecialchars($t['end_time'] ?? '');
                      $duration = htmlspecialchars($t['duration'] ?? '');
                      $title = htmlspecialchars($t['training'] ?? 'N/A');
                      $venue = htmlspecialchars($t['venue'] ?? 'N/A');

                      $formattedDate = $date ? date("F j, Y", strtotime($date)) : 'N/A';
                      $formattedStart = $start ? date("g:i A", strtotime($start)) : 'N/A';
                      $formattedEnd = $end ? date("g:i A", strtotime($end)) : 'N/A';
                      ?>
                      <li class="border-l-4 border-blue-400 pl-4 py-1">
                        <div class="font-medium text-gray-800"><?= $title ?></div>
                        <div class="text-sm text-gray-600 mt-1">
                          <i class=""></i> <?= $venue ?>
                          <span class="mx-2">•</span>
                          <i class=""></i> <?= $formattedDate ?>
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                          <i class=""></i> <?= $formattedStart ?> - <?= $formattedEnd ?>
                          <span class="mx-2">•</span>
                          <i class=""></i> <?= $duration ?>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <p class="text-gray-500 italic">No training history recorded</p>
                <?php endif; ?>
              </div>
              
              <!-- Desired Skills & Comments -->
              <div class="space-y-6">
                <div>
                  <h4 class="font-bold text-gray-700 mb-3 flex items-center">
                    Desired Training Courses
                  </h4>
                  <?php if (is_array($skills) && !empty($skills)): ?>
                    <ul class="list-disc pl-5 space-y-1">
                      <?php foreach ($skills as $skill): ?>
                        <li class="text-gray-700"><?= htmlspecialchars($skill) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <p class="text-gray-700"><?= nl2br(htmlspecialchars($entry['desired_skills'] ?? 'No desired courses specified')) ?></p>
                  <?php endif; ?>
                </div>
                
                <div>
                  <h4 class="font-bold text-gray-700 mb-3 flex items-center">
                    Comments/Suggestions
                  </h4>
                    <p class="text-gray-700"><?= nl2br(htmlspecialchars($entry['comments'] ?? 'No comments provided')) ?></p>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
             Previous
          </a>
        <?php else: ?>
          <span class="px-4 py-2 bg-gray-100 rounded text-gray-400"> Previous</span>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
          <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
            Next
          </a>
        <?php else: ?>
          <span class="px-4 py-2 bg-gray-100 rounded text-gray-400">Next</i></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      
    <?php else: ?>
      <div class="bg-white rounded-xl p-8 text-center">
        <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-700">No assessment submissions found</h3>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>

<?php
if (isset($stmt)) $stmt->close();
if (isset($count_stmt)) $count_stmt->close();
if (isset($mysqli)) $mysqli->close();