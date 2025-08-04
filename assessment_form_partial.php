<?php 
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<main class="flex-1 overflow-y-auto p-6 bg-gray-50">
  <div class="max-w-5xl mx-auto my-10 bg-white rounded-2xl shadow-xl p-10">
    <h1 class="text-3xl font-bold text-center text-blue-700 mb-8">Training Needs Assessment</h1>

    <form id="assessmentForm" method="POST" action="save_assessment.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      
      <!-- Hidden fields for print data -->
      <input type="hidden" id="print-training-data" name="print_training_data" value="">
      <input type="hidden" id="print-desired-skills" name="print_desired_skills" value="">
      <input type="hidden" id="print-comments" name="print_comments" value="">
      
      <!-- Past Trainings -->
      <section class="print-section">
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

        <div class="mt-6 no-print">
          <button type="button" id="add-entry" class="px-6 py-2 bg-blue-600 text-white rounded-full text-sm hover:bg-blue-700 transition-all">
            Add Entry
          </button>
        </div>
      </section>

      <!-- Additional Training and Comments -->
      <div class="mt-10 space-y-6 print-section">
        <div>
          <h2 class="text-lg font-medium text-gray-900 mb-2">Other relevant training courses you want to attend</h2>
          <textarea id="desired_skills" name="desired_skills" rows="4" class="w-full border border-gray-300 rounded-lg p-3 shadow-sm" placeholder="Enter training here..."></textarea>
        </div>

        <div>
          <h2 class="text-lg font-medium text-gray-900 mb-2">Comments/Suggestions</h2>
          <textarea id="comments" name="comments" rows="4" class="w-full border border-gray-300 rounded-lg p-3 shadow-sm" placeholder="Your comments or suggestions here..."></textarea>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div class="mt-8 text-right space-x-3 no-print">
        <button type="submit" name="action" value="print" formaction="Training Needs Assessment Form_pdf.php" formtarget="_blank" class="px-5 py-2 bg-green-600 text-white rounded-full text-sm hover:bg-green-700 transition">
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
<div id="submit-confirmation-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden no-print">
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
<div id="success-notification" class="fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded shadow-lg hidden z-50 no-print">
  ✅ Assessment submitted successfully!
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tableBody = document.getElementById('training-body');
  const addEntryBtn = document.getElementById('add-entry');
  const trainingCount = document.getElementById('training-count');
  const saveBtn = document.getElementById('save-btn');
  const printBtn = document.querySelector('button[formaction="Training Needs Assessment Form_pdf.php"]');
  const form = document.getElementById('assessmentForm');
  const modal = document.getElementById('submit-confirmation-modal');
  const confirmBtn = document.getElementById('confirm-submit');
  const cancelBtn = document.getElementById('cancel-submit');
  const desiredSkillsInput = document.getElementById('desired_skills');
  const commentsInput = document.getElementById('comments');
  const printTrainingData = document.getElementById('print-training-data');
  const printDesiredSkills = document.getElementById('print-desired-skills');
  const printComments = document.getElementById('print-comments');

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

  function updatePrintData() {
    // Prepare training data for print
    const trainingRows = document.querySelectorAll('.training-row');
    const trainingData = [];
    
    for (let i = 0; i < trainingRows.length; i += 2) {
      const date = trainingRows[i].querySelector('input[name*="date"]')?.value;
      const start = trainingRows[i].querySelector('input[name*="start_time"]')?.value;
      const end = trainingRows[i].querySelector('input[name*="end_time"]')?.value;
      const duration = trainingRows[i].querySelector('input[name*="duration"]')?.value;
      const training = trainingRows[i + 1].querySelector('textarea[name*="training"]')?.value;
      const venue = trainingRows[i + 1].querySelector('textarea[name*="venue"]')?.value;
      
      if (date || training) {
        trainingData.push({
          date: date || '',
          start_time: start || '',
          end_time: end || '',
          duration: duration || '',
          training: training || '',
          venue: venue || ''
        });
      }
    }
    
    // Update hidden fields
    printTrainingData.value = JSON.stringify(trainingData);
    printDesiredSkills.value = desiredSkillsInput.value;
    printComments.value = commentsInput.value;
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
            <input type="date" name="training_history[${rowId}][date]" required class="w-full border border-gray-300 rounded-md px-3 py-2 shadow-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Start Time</label>
            <input type="time" name="training_history[${rowId}][start_time]" required class="start-time w-full border border-gray-300 rounded-md px-3 py-2 shadow-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">End Time</label>
            <input type="time" name="training_history[${rowId}][end_time]" required class="end-time w-full border border-gray-300 rounded-md px-3 py-2 shadow-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Duration</label>
            <input type="text" name="training_history[${rowId}][duration]" readonly placeholder="Auto" class="duration w-full border border-gray-300 bg-gray-100 rounded-md px-3 py-2 shadow-inner" />
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
            <textarea name="training_history[${rowId}][training]" required placeholder="Training title" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 resize-none shadow-sm"></textarea>
          </div>
          <div class="col-span-5">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Venue</label>
            <textarea name="training_history[${rowId}][venue]" required placeholder="Venue" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 resize-none shadow-sm"></textarea>
          </div>
          <div class="col-span-1 flex items-end justify-center no-print">
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
    
    // Add event listeners to update print data when fields change
    firstRow.querySelectorAll('input').forEach(input => {
      input.addEventListener('input', updatePrintData);
    });
    secondRow.querySelectorAll('textarea').forEach(textarea => {
      textarea.addEventListener('input', updatePrintData);
    });
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
        updatePrintData();
      }
    }
  });

  // Update print data when desired skills or comments change
  desiredSkillsInput?.addEventListener('input', updatePrintData);
  commentsInput?.addEventListener('input', updatePrintData);

  // Update print data before form submission for PDF
  printBtn?.addEventListener('click', (e) => {
    updatePrintData();
  });

  function validateForm() {
    const trainingRows = document.querySelectorAll('.training-row');
    const desiredSkills = desiredSkillsInput?.value.trim();
    const comments = commentsInput?.value.trim();
    
    // Check if at least one training is complete
    let hasValidTraining = false;
    
    for (let i = 0; i < trainingRows.length; i += 2) {
      const date = trainingRows[i].querySelector('input[type="date"]')?.value;
      const training = trainingRows[i + 1].querySelector('textarea[name*="training"]')?.value.trim();
      
      if (date || training) {
        if (!date || !training) {
          alert('Please complete both date and training title for all entries');
          return false;
        }
        hasValidTraining = true;
      }
    }
    
    if (!hasValidTraining && !desiredSkills && !comments) {
      alert('Please enter at least one training, desired skill, or comment');
      return false;
    }
    
    return true;
  }

  function submitAssessment() {
    if (!validateForm()) return;

    updatePrintData(); // Ensure print data is up to date
    
    const formData = new FormData(form);
    
    fetch('save_assessment.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        modal.classList.add('hidden');
        const successNotif = document.getElementById('success-notification');
        successNotif.classList.remove('hidden');
        setTimeout(() => successNotif.classList.add('hidden'), 5000);
        
        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 1000);
        }
      } else {
        throw new Error(data.error || 'Unknown error occurred');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error submitting assessment: ' + error.message);
    });
  }

  // Show modal on Save button click
  saveBtn?.addEventListener('click', () => {
    if (validateForm()) {
      modal.classList.remove('hidden');
    }
  });

  // Confirm modal submit
  confirmBtn?.addEventListener('click', submitAssessment);

  // Cancel modal
  cancelBtn?.addEventListener('click', () => {
    modal.classList.add('hidden');
  });

  // Handle success notification from URL parameter
  if (new URLSearchParams(window.location.search).has('success')) {
    const successNotif = document.getElementById('success-notification');
    if (successNotif) {
      successNotif.classList.remove('hidden');
      setTimeout(() => successNotif.classList.add('hidden'), 5000);
    }
  }
  
  // Initial update of print data
  updatePrintData();
});
</script>