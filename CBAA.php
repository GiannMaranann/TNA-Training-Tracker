<?php
include 'config.php'; // database connection as $con

$selected_year = date('Y'); // Default year is current year
if (isset($_GET['year'])) {
    $selected_year = intval($_GET['year']);
}

// Fetch CBAA users with their assessments ONLY for department CBAA
$sql = "SELECT 
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
        WHERE u.department = 'CBAA' 
          AND (a.submission_date IS NULL OR YEAR(a.submission_date) = $selected_year)
        ORDER BY a.submission_date DESC";

$result = $con->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CBAA</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#0D6EFD',
            secondary: '#6C757D'
          },
          borderRadius: {
            'none': '0px',
            'sm': '4px',
            DEFAULT: '8px',
            'md': '12px',
            'lg': '16px',
            'xl': '20px',
            '2xl': '24px',
            '3xl': '32px',
            'full': '9999px',
            'button': '8px'
          },
          fontFamily: {
            sans: ['Poppins', 'sans-serif']
          }
        }
      }
    };
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

  <!-- Use Poppins instead of Inter -->
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
  </style>
    <style>
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    .animate-bounce {
        animation: bounce 1.2s infinite;
    }
    </style>
</head>
<body class="min-h-screen font-sans" style="font-family: 'Poppins', sans-serif;">
  <div class="min-w-[1024px]">
    <!-- Header -->
    <header class="bg-white shadow-sm fixed top-0 left-0 w-full z-50">
    <div class="max-w-7xl mx-auto px-6 py-5 flex justify-between items-center">
        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-800">College of Business, Administration and Accountancy</h1>

        <!-- Right Section -->
        <div class="flex items-center space-x-6">
        <!-- Notification Bell -->
        <div class="relative">
            <button class="flex items-center text-gray-600 hover:text-gray-900 focus:outline-none">
            <div class="relative group cursor-pointer" id="notifWrapper">
                <!-- Bell Icon enlarged -->
                <i class="ri-notification-3-line text-4xl text-gray-700 group-hover:text-blue-600 animate-bounce ringing-bell" id="notifBell"></i>

                <!-- Red Dot -->
                <span id="notifBadge" class="absolute top-0 right-0 transform translate-x-1 -translate-y-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded-full hidden">!</span>

                <!-- Notification Popup (Icon on Top Right) -->
                <div id="notifPopup" class="absolute right-0 mt-3 w-96 bg-white border border-gray-200 rounded-2xl shadow-xl hidden z-50">
                <div class="relative p-5">
                    <!-- Icon in Top Right -->
                    <div class="absolute top-4 right-4 text-primary text-xl">
                    <i class="ri-information-line"></i>
                    </div>

                    <!-- Message Content -->
                    <div class="text-sm text-gray-700 space-y-2">
                    <h3 class="text-lg font-semibold text-primary">Reminder</h3>
                    <p>
                        It has been over <span class="font-medium">3 months</span> since your last employee evaluation. As the Head/Dean, you are required to conduct a timely performance review of all faculty and staff under your supervision.
                    </p>
                    <p class="font-semibold text-gray-900">
                        Please complete the evaluation process as soon as possible to ensure compliance with institutional policy and support the continuous professional development of your team.
                    </p>
                    </div>
                </div>
                </div>
            </div>
            </button>
        </div>

        <!-- Profile Section -->
        <div class="flex items-center space-x-3">
            <!-- Logos with Slash (images bigger) -->
            <div class="flex items-center space-x-1">
            <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-200">
                <img src="images/lspubg2.png" alt="Logo 1" class="w-full h-full object-cover" />
            </div>
            <span class="text-gray-500 font-bold"></span>
            <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-200">
                <img src="images/cbaa.png" alt="Logo 2" class="w-full h-full object-cover" />
            </div>
            </div>
            <!-- Welcome Text bold and darker -->
        <div class="hidden md:block">
          <p class="text-sm font-bold text-gray-800">Welcome Head/Dean</p>
        </div>
      </div>

      <!-- Sign Out Button - pinaka right dulo -->
      <div>
        <a href="homepage.php" 
           class="text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300 font-semibold rounded-lg text-sm px-4 py-2 transition duration-300"
           >Sign Out</a>
      </div>
    </div>
  </div>
    </header>
        <!-- Main Content (with padding-top) -->
        <main class="max-w-7xl mx-auto px-6 pt-32 pb-8">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <!-- Title and Search -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h2 class="text-xl font-semibold text-gray-800">Assessment Forms Submissions</h2>
            <div class="relative w-full md:w-96">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <div class="w-5 h-5 flex items-center justify-center text-gray-500">
                    <i class="ri-search-line"></i>
                </div>
                </div>
                <input type="search" id="search-input" class="search-input w-full pl-10 pr-4 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-lg focus:border-primary transition-colors" placeholder="Search assessment forms...">
                <div id="search-suggestions" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg hidden"></div>
            </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-4 mb-6 justify-end">
            <div class="w-full md:w-auto">
                <label class="block mb-1 text-sm font-medium text-transparent">Actions</label>
                <button onclick="generatePDF()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium">
                <i class="ri-download-2-line mr-1"></i> Export Summary PDF
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
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Type of Employment</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Seminar Attended</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Desired Training</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Comments</th>
                                <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-800"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['department']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['teaching_status']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php
                                              if (!empty($row['training_history'])) {
                                                  $seminars = json_decode($row['training_history'], true);
                                                  if (is_array($seminars)) {
                                                      foreach ($seminars as $seminar) {
                                                          echo "<div class='mb-2'>";
                                                          echo "<strong>Date:</strong> " . htmlspecialchars($seminar['date'] ?? '') . "<br>";
                                                          echo "<strong>Start Time:</strong> " . htmlspecialchars($seminar['start_time'] ?? '') . "<br>";
                                                          echo "<strong>End Time:</strong> " . htmlspecialchars($seminar['end_time'] ?? '') . "<br>";
                                                          echo "<strong>Duration:</strong> " . htmlspecialchars($seminar['duration'] ?? '') . "<br>";
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
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['desired_skills'] ?? '—') ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['comments'] ?? '—') ?></td>
                                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                                            <div class="flex space-x-2">
                                                <!-- Evaluate Button -->
                                                <button 
                                                    type="button"
                                                    class="evaluate-btn bg-primary text-white rounded-button px-3 py-1.5 text-xs font-medium hover:bg-primary/90 transition whitespace-nowrap flex items-center"
                                                    data-name="<?= htmlspecialchars($row['name']) ?>"
                                                >
                                                    <span class="w-4 h-4 flex items-center justify-center mr-1">
                                                        <i class="ri-star-line"></i>
                                                    </span>
                                                    Evaluate
                                                </button>
                                                <button 
                                                    class="remove-btn bg-red-500 text-white rounded-button px-3 py-1.5 text-xs font-medium hover:bg-red-600 transition whitespace-nowrap" 
                                                    data-id="<?= htmlspecialchars($row['name']) ?>"
                                                >
                                                    <i class="ri-delete-bin-line mr-1"></i> Remove
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No CCS data found for <?= $selected_year ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <!-- Modal Background and Container -->
    <div id="modalBackdrop" class="fixed inset-0 bg-black/30 backdrop-blur-sm hidden z-50 flex justify-center items-center">
        <div class="bg-white w-11/12 max-w-4xl h-[90vh] overflow-auto rounded-lg shadow-lg p-4 relative">
            <!-- Close Button -->
            <button id="closeModal" class="absolute top-2 right-2 text-gray-500 hover:text-black text-lg">
                &times;
            </button>

            <!-- Iframe to load the form -->
            <iframe src="training program impact assessment form.html" class="w-full h-full border-0"></iframe>
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
                <button id="cancel-remove" class="px-4 py-2 border border-gray-300 rounded-button text-gray-700 hover:bg-gray-50 text-sm font-medium whitespace-nowrap">Cancel</button>
                <button id="confirm-remove" class="px-4 py-2 bg-red-500 text-white rounded-button hover:bg-red-600 text-sm font-medium whitespace-nowrap">Yes, Remove</button>
            </div>
        </div>
    </div>

    <script>
        const openModalButtons = document.querySelectorAll(".evaluate-btn");
        const closeModalBtn = document.getElementById("closeModal");
        const modalBackdrop = document.getElementById("modalBackdrop");

        openModalButtons.forEach(button => {
            button.addEventListener("click", () => {
                const name = button.getAttribute("data-name");
                const id = button.getAttribute("data-id");
                // Optional: inject these into modal fields
                // document.getElementById("evaluateName").textContent = name;

                modalBackdrop.classList.remove("hidden");
            });
        });

        closeModalBtn.addEventListener("click", () => {
            modalBackdrop.classList.add("hidden");
        });
    </script>

    <script>
    function shouldRemindDean() {
        const lastShown = localStorage.getItem('lastDeanReminder');
        const now = new Date();

        if (!lastShown) return true;

        const lastDate = new Date(lastShown);
        const monthsPassed = (now.getFullYear() - lastDate.getFullYear()) * 12 + (now.getMonth() - lastDate.getMonth());

        return monthsPassed >= 3;
    }

    window.addEventListener('DOMContentLoaded', () => {
        const bell = document.getElementById('notifBell');
        const badge = document.getElementById('notifBadge');
        const popup = document.getElementById('notifPopup');
        const wrapper = document.getElementById('notifWrapper');

        if (shouldRemindDean()) {
        badge.classList.remove('hidden');
        bell.classList.add('animate-bounce');
        }

        wrapper.addEventListener('click', () => {
        popup.classList.toggle('hidden');
        badge.classList.add('hidden');
        bell.classList.remove('animate-bounce');
        localStorage.setItem('lastDeanReminder', new Date().toISOString());
        });
    });
    </script>

    <script id="search-functionality">
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-input');
        const tableRows = document.querySelectorAll('tbody tr');

        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();

            tableRows.forEach(row => {
                const name = row.children[0]?.textContent.toLowerCase() || '';
                const employmentType = row.children[2]?.textContent.toLowerCase() || '';

                if (name.includes(query) || employmentType.includes(query)) {
                    row.style.display = ''; // show row
                } else {
                    row.style.display = 'none'; // hide row
                }
            });
        });
    });
    </script>

    <script id="removal-functionality"> 
    document.addEventListener('DOMContentLoaded', () => {
        const confirmDialog = document.getElementById('confirm-dialog');
        const removeButtons = document.querySelectorAll('.remove-btn');
        const cancelRemove = document.getElementById('cancel-remove');
        const confirmRemove = document.getElementById('confirm-remove');

        let currentRow = null;

        // Function para i-save sa localStorage yung removed names
        function saveRemovedName(name) {
            let removed = JSON.parse(localStorage.getItem('removedNames') || '[]');
            if (!removed.includes(name)) {
                removed.push(name);
                localStorage.setItem('removedNames', JSON.stringify(removed));
            }
        }

        // Function para i-check sa localStorage at i-remove/hide yung rows na removed na
        function filterRemovedRows() {
            let removed = JSON.parse(localStorage.getItem('removedNames') || '[]');
            removed.forEach(name => {
                document.querySelectorAll('tr').forEach(row => {
                    const cell = row.querySelector('td');
                    if (cell && cell.textContent.trim() === name) {
                        row.remove();
                    }
                });
            });
        }

        // Run sa pag-load ng page para i-filter yung na-remove na rows
        filterRemovedRows();

        removeButtons.forEach(button => {
            button.addEventListener('click', () => {
                currentRow = button.closest('tr');
                if (currentRow) {
                    confirmDialog.style.display = 'flex';
                }
            });
        });

        cancelRemove.addEventListener('click', () => {
            confirmDialog.style.display = 'none';
            currentRow = null;
        });

        confirmRemove.addEventListener('click', () => {
            if (!currentRow) return;

            const nameCell = currentRow.querySelector('td');
            const name = nameCell ? nameCell.textContent.trim() : '';

            confirmDialog.style.display = 'none';

            fetch('delete_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `name=${encodeURIComponent(name)}`
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === 'success') {
                    // Remove row from table
                    currentRow.remove();

                    // Save in localStorage para persistent kahit i-refresh
                    saveRemovedName(name);

                    showNotification(`${name}'s assessment form has been removed successfully.`);
                } else {
                    showNotification(`Failed to remove ${name}'s data.`);
                }
                currentRow = null;
            })
            .catch(err => {
                showNotification('Error removing data: ' + err.message);
                currentRow = null;
            });
        });

        function showNotification(message) {
            let container = document.getElementById('notification-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notification-container';
                container.style.position = 'fixed';
                container.style.top = '20px';
                container.style.right = '20px';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }

            const notif = document.createElement('div');
            notif.textContent = message;
            notif.style.background = '#4BB543';
            notif.style.color = '#fff';
            notif.style.padding = '10px 20px';
            notif.style.marginTop = '10px';
            notif.style.borderRadius = '5px';
            notif.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
            notif.style.cursor = 'pointer';
            notif.style.minWidth = '250px';

            notif.addEventListener('click', () => {
                notif.remove();
            });

            container.appendChild(notif);

            setTimeout(() => {
                notif.remove();
            }, 4000);
        }
    });
    </script>

    <script>
    function generatePDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: "landscape" });

        // Title
        doc.setFontSize(14);
        const pageWidth = doc.internal.pageSize.getWidth();
        const title = "SUMMARY OF TRAINING NEEDS ASSESSMENT FORMS CBAA";
        const titleWidth = doc.getStringUnitWidth(title) * doc.getFontSize() / doc.internal.scaleFactor;
        doc.text(title, (pageWidth - titleWidth) / 2, 15);

        const table = document.querySelector('table');
        if (!table) return alert("Table not found!");

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
            'Seminar Attended',
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

        doc.save("Summary_of_Training_Needs_Assessment_Forms_CBAA.pdf");
    }
    </script>

</body>
</html>