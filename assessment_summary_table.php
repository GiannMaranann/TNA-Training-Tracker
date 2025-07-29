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
} else {
    $deadline = null;
}

// Fetch all submissions from the user (latest first)
$stmt = $mysqli->prepare("SELECT * FROM assessments WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$assessments = $result->fetch_all(MYSQLI_ASSOC);

// Determine overall status (based on most recent submission)
if (!empty($assessments) && $deadline) {
    $latest_submission = strtotime($assessments[0]['created_at']);
    $status = $latest_submission <= $deadline ? "✅ On Time" : "⚠️ Late";
} else {
    $status = "❌ No Submission";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>TNA Records</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="p-6 bg-gray-100">
  <div class="max-w-6xl mx-auto bg-white shadow p-6 rounded-lg">
    <h2 class="text-xl font-bold mb-4">Assessment Records</h2>
    <p class="mb-4 text-sm text-gray-700">Submission Status: 
      <span class="font-semibold"><?= htmlspecialchars($status) ?></span>
    </p>

    <?php if (!empty($assessments)): ?>
      <table class="min-w-full border border-gray-300 text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="border px-4 py-2">Training History</th>
            <th class="border px-4 py-2">Relevant Training Courses</th>
            <th class="border px-4 py-2">Comments</th>
            <th class="border px-4 py-2">Submitted On</th>
            <th class="border px-4 py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assessments as $entry): ?>
            <tr class="border-t">
              <!-- Training History -->
              <td class="border px-4 py-2">
                <?php
                $training = json_decode($entry['training_history'], true);
                if (!empty($training)) {
                    echo "<ul class='list-disc pl-5'>";
                    foreach ($training as $t) {
                        $date = htmlspecialchars($t['date'] ?? '');
                        $start = htmlspecialchars($t['start_time'] ?? '');
                        $end = htmlspecialchars($t['end_time'] ?? '');
                        $duration = htmlspecialchars($t['duration'] ?? '');
                        $title = htmlspecialchars($t['training'] ?? 'N/A');
                        $venue = htmlspecialchars($t['venue'] ?? 'N/A');

                        $formattedDate = $date ? date("F j, Y", strtotime($date)) : 'N/A';
                        $formattedStart = $start ? date("g:i A", strtotime($start)) : 'N/A';
                        $formattedEnd = $end ? date("g:i A", strtotime($end)) : 'N/A';

                        echo "<li class='mb-3'>
                                <div><strong>{$formattedDate}</strong> ({$formattedStart} - {$formattedEnd})</div>
                                <div class='text-sm text-gray-600'>Duration: {$duration}</div>
                                <div class='mt-1'>{$title} at {$venue}</div>
                              </li>";
                    }
                    echo "</ul>";
                } else {
                    echo "N/A";
                }
                ?>
              </td>

              <!-- Desired Skills -->
              <td class="border px-4 py-2">
                <?php
                $skills = json_decode($entry['desired_skills'], true);
                if (!empty($skills)) {
                    echo "<ul class='list-disc pl-5'>";
                    foreach ($skills as $skill) {
                        echo "<li>" . htmlspecialchars($skill) . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo htmlspecialchars($entry['desired_skills'] ?? 'N/A');
                }
                ?>
              </td>

              <!-- Comments -->
              <td class="border px-4 py-2">
                <?= nl2br(htmlspecialchars($entry['comments'] ?? 'N/A')) ?>
              </td>

              <!-- Submission Date -->
              <td class="border px-4 py-2">
                <?php
                $created_at = strtotime($entry['created_at']);
                echo date("F j, Y g:i A", $created_at);
                ?>
              </td>

              <!-- Submission Status -->
              <td class="border px-4 py-2 text-center">
                <?php
                if ($deadline) {
                    echo $created_at <= $deadline ? "✅ On Time" : "⚠️ Late";
                } else {
                    echo "❌ No Deadline";
                }
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-gray-600">No assessment submission found for this user.</p>
    <?php endif; ?>
  </div>
</body>
</html>

<?php
if (isset($stmt)) $stmt->close();
if (isset($mysqli)) $mysqli->close();
?>
