<?php 
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get profile data from session
$profile_name = $_SESSION['profile_name'] ?? '';
$educationalAttainment = $_SESSION['profile_educationalAttainment'] ?? '';
$specialization = $_SESSION['profile_specialization'] ?? '';
$designation = $_SESSION['profile_designation'] ?? '';
$department = $_SESSION['profile_department'] ?? '';
$yearsInLSPU = $_SESSION['profile_yearsInLSPU'] ?? '';
$teaching_status = $_SESSION['profile_teaching_status'] ?? '';
?>

<main class="flex-1 overflow-y-auto p-6 bg-gray-50">
  <div class="max-w-5xl mx-auto my-10 bg-white rounded-2xl shadow-xl p-10">
    <h1 class="text-3xl font-bold text-center text-blue-700 mb-8">Training Needs Assessment</h1>

    <form id="assessmentForm" method="POST" action="save_assessment.php">
      <!-- Past Trainings -->
      <section>
        <h2 class="text-xl font-semibold text-gray-800 mb-2">Please list all the trainings attended for the last three years.</h2>
        <p class="text-sm text-gray-600 mb-6">
          Trainings listed: <span id="training-count" class="font-semibold text-gray-800">0</span>
        </p>

        <div class="overflow-x-auto rounded-xl border border-gray-300 shadow-sm bg-white">
          <table id="training-table" class="w-full text-sm text-gray-700">
            <tbody id="training-body" class="divide-y divide-gray-200">
              <!-- Training rows are inserted dynamically via JavaScript -->
            </tbody>
          </table>
        </div>

        <div class="mt-6">
          <button type="button" id="add-entry" class="px-6 py-2 bg-blue-600 text-white rounded-full text-sm hover:bg-blue-700 transition-all">
            Add Entry
          </button>
        </div>
      </section>

      <!-- Additional Training and Comments -->
      <div class="mt-10 space-y-6">
        <div>
          <h2 class="text-lg font-medium text-gray-900 mb-2">Other relevant training courses you want to attend</h2>
          <textarea id="desired_skills"name="selected_training" rows="4" class="w-full border border-gray-300 rounded-lg p-3 shadow-sm" placeholder="Enter training here..."></textarea>
        </div>

        <div>
          <h2 class="text-lg font-medium text-gray-900 mb-2">Comments/Suggestions</h2>
          <textarea id="comments" name="comments" rows="4" class="w-full border border-gray-300 rounded-lg p-3 shadow-sm" placeholder="Your comments or suggestions here..."></textarea>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div class="mt-8 text-right space-x-3">
        <button type="submit" name="action" value="print" formaction="generate_pdf.php" formtarget="_blank" class="px-5 py-2 bg-green-600 text-white rounded-full text-sm hover:bg-green-700 transition">
          Print PDF
        </button>
        <button type="button" id="save-btn" class="px-5 py-2 bg-blue-600 text-white rounded-full text-sm hover:bg-blue-700 transition">
          Submit
        </button>
      </div>
    </form>
  </div>
</main>

<!-- Submit Confirmation Modal -->
<div id="submit-confirmation-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
  <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
    <h2 class="text-lg font-semibold mb-4">Ready to submit?</h2>
    <p class="mb-4 text-sm text-gray-600">Make sure all information is correct.</p>
    <div class="flex justify-end gap-2">
      <button id="cancel-submit" type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Cancel</button>
      <button id="confirm-submit" type="button" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Submit</button>
    </div>
  </div>
</div>

<!-- Success Notification -->
<div id="success-notification" class="fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded shadow-lg hidden z-50">
  ✅ Assessment submitted successfully!
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tableBody = document.getElementById('training-body');
  const addEntryBtn = document.getElementById('add-entry');
  const trainingCount = document.getElementById('training-count');
  const saveBtn = document.getElementById('save-btn');
  const form = document.getElementById('assessmentForm');
  const modal = document.getElementById('submit-confirmation-modal');
  const confirmBtn = document.getElementById('confirm-submit');
  const cancelBtn = document.getElementById('cancel-submit');

  function formatDuration(minutes) {
    const hrs = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hrs > 0 && mins > 0) return `${hrs} hour${hrs > 1 ? 's' : ''} ${mins} minute${mins > 1 ? 's' : ''}`;
    if (hrs > 0) return `${hrs} hour${hrs > 1 ? 's' : ''}`;
    if (mins > 0) return `${mins} minute${mins > 1 ? 's' : ''}`;
    return `0 minutes`;
  }

  function calculateDuration(row) {
    const start = row.querySelector('.start-time')?.value;
    const end = row.querySelector('.end-time')?.value;
    const durationField = row.querySelector('.duration');

    if (start && end) {
      const startTime = new Date(`1970-01-01T${start}:00`);
      const endTime = new Date(`1970-01-01T${end}:00`);
      const diffMs = endTime - startTime;
      if (diffMs > 0) {
        const minutes = diffMs / 60000;
        durationField.value = formatDuration(minutes);
      } else {
        durationField.value = 'Invalid time';
      }
    } else {
      durationField.value = '';
    }
  }

  function attachDurationEvents(row) {
    const startInput = row.querySelector('.start-time');
    const endInput = row.querySelector('.end-time');
    if (startInput && endInput) {
      startInput.addEventListener('input', () => calculateDuration(row));
      endInput.addEventListener('input', () => calculateDuration(row));
    }
  }

  function updateCount() {
    const total = tableBody.querySelectorAll('.training-row').length / 2;
    trainingCount.textContent = total;
  }

  function addTrainingEntry() {
    const rowId = `row-${Date.now()}`;

    const firstRow = document.createElement('tr');
    firstRow.classList.add('training-row', 'border-t');
    firstRow.setAttribute('data-id', rowId);
    firstRow.innerHTML = `
      <td colspan="7" class="px-6 pt-6 pb-2">
        <div class="grid grid-cols-4 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Date</label>
            <input type="date" name="date[]" required class="w-full border border-gray-300 rounded-md px-3 py-2 shadow-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Start Time</label>
            <input type="time" name="start_time[]" required class="start-time w-full border border-gray-300 rounded-md px-3 py-2 shadow-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">End Time</label>
            <input type="time" name="end_time[]" required class="end-time w-full border border-gray-300 rounded-md px-3 py-2 shadow-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Duration</label>
            <input type="text" name="duration[]" readonly placeholder="Auto" class="duration w-full border border-gray-300 bg-gray-100 rounded-md px-3 py-2 shadow-inner" />
          </div>
        </div>
      </td>
    `;

    const secondRow = document.createElement('tr');
    secondRow.classList.add('training-row');
    secondRow.setAttribute('data-id', rowId);
    secondRow.innerHTML = `
      <td colspan="7" class="px-6 pb-6 pt-2">
        <div class="grid grid-cols-12 gap-4">
          <div class="col-span-6">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Training</label>
            <textarea name="training[]" required placeholder="Training title" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 resize-none shadow-sm"></textarea>
          </div>
          <div class="col-span-5">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Venue</label>
            <textarea name="venue[]" required placeholder="Venue" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 resize-none shadow-sm"></textarea>
          </div>
          <div class="col-span-1 flex items-end justify-center">
            <button type="button" class="text-gray-400 hover:text-red-500 delete-btn mt-6">
              <i class="ri-delete-bin-line text-2xl"></i>
            </button>
          </div>
        </div>
      </td>
    `;

    tableBody.appendChild(firstRow);
    tableBody.appendChild(secondRow);
    attachDurationEvents(firstRow);
    updateCount();
  }

  // Initial row
  addTrainingEntry();

  addEntryBtn?.addEventListener('click', addTrainingEntry);

  tableBody.addEventListener('click', (e) => {
    if (e.target.closest('.delete-btn')) {
      const row = e.target.closest('tr');
      const rowId = row?.getAttribute('data-id');
      const rowsToDelete = tableBody.querySelectorAll(`[data-id="${rowId}"]`);
      if (rowsToDelete.length === 2) {
        rowsToDelete.forEach(r => r.remove());
        updateCount();
      }
    }
  });

  function submitAssessment() {
    const trainingRows = document.querySelectorAll('.training-row');
    const trainingData = [];

    for (let i = 0; i < trainingRows.length; i += 2) {
      const date = trainingRows[i].querySelector('input[name="date[]"]')?.value;
      const start = trainingRows[i].querySelector('input[name="start_time[]"]')?.value;
      const end = trainingRows[i].querySelector('input[name="end_time[]"]')?.value;
      const duration = trainingRows[i].querySelector('input[name="duration[]"]')?.value;
      const training = trainingRows[i + 1].querySelector('textarea[name="training[]"]')?.value;
      const venue = trainingRows[i + 1].querySelector('textarea[name="venue[]"]')?.value;

      if (date || start || end || duration || training || venue) {
        trainingData.push({ date, start_time: start, end_time: end, duration, training, venue });
      }
    }

    const desiredSkillsTextarea = document.querySelector('#desired_skills');
    const desiredSkills = desiredSkillsTextarea?.value || '';
    const comments = document.querySelector('#comments')?.value || '';

    if (trainingData.length === 0 && desiredSkills.trim() === '' && comments.trim() === '') {
      alert('Please enter at least one training, skill, or comment before saving.');
      return;
    }

    const formData = new FormData();
    formData.append('training_history', JSON.stringify(trainingData));
    formData.append('desired_skills', desiredSkills);
    formData.append('comments', comments);

    fetch('save_assessment.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          modal.classList.add('hidden');
          alert('Assessment saved successfully!');
          location.reload();
        } else {
          alert('Error saving assessment: ' + result.error);
        }
      })
      .catch(err => {
        console.error('Save error:', err);
        alert('Something went wrong. Please try again.');
      });
  }

  // Show modal on Save button click
  saveBtn?.addEventListener('click', () => {
    modal.classList.remove('hidden');
  });

  // Confirm modal submit
  confirmBtn?.addEventListener('click', submitAssessment);

  // Cancel modal
  cancelBtn?.addEventListener('click', () => {
    modal.classList.add('hidden');
  });

  if (window.location.href.includes('submitted=true')) {
    const successNotif = document.getElementById('success-notification');
    if (successNotif) {
      successNotif.classList.remove('hidden');
      setTimeout(() => successNotif.classList.add('hidden'), 5000);
    }
    document.querySelector('table')?.scrollIntoView({ behavior: 'smooth' });
  }
});
</script>


</body>
</html>