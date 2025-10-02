<?php
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get URL parameters
$facultyName = isset($_GET['name']) ? urldecode($_GET['name']) : '';
$evaluationId = isset($_GET['id']) ? $_GET['id'] : '';
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';

// Get current user info (evaluator)
$evaluator_id = $_SESSION['user_id'];
$evaluator_sql = "SELECT name, department FROM users WHERE id = ?";
$evaluator_stmt = $con->prepare($evaluator_sql);
$evaluator_stmt->bind_param('i', $evaluator_id);
$evaluator_stmt->execute();
$evaluator_result = $evaluator_stmt->get_result();
$evaluator = $evaluator_result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Process form data
        $name = $_POST['name'];
        $department = $_POST['department'];
        $training_title = $_POST['training_title'];
        $date_conducted = $_POST['date_conducted'];
        $objectives = $_POST['objectives'];
        $comments = $_POST['comments'];
        $future_training = $_POST['future_training'];
        $rated_by = $_POST['rated_by'];
        $signature_data = $_POST['signature_data'];
        $assessment_date = $_POST['assessment_date'];
        
        // Process ratings
        $ratings = [];
        for ($i = 0; $i < 8; $i++) {
            $ratings[] = isset($_POST['rating'][$i]) ? (int)$_POST['rating'][$i] : 0;
        }
        
        // Process remarks
        $remarks = [];
        for ($i = 0; $i < 8; $i++) {
            $remarks[] = isset($_POST['remark'][$i]) ? $_POST['remark'][$i] : '';
        }
        
        // Check if evaluation already exists for this user
        $check_sql = "SELECT id FROM evaluations WHERE user_id = ? AND assessment_id = ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param('ii', $userId, $evaluationId);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        // Begin transaction
        $con->begin_transaction();
        
        if ($check_result->num_rows > 0) {
            // Update existing evaluation
            $existing_eval = $check_result->fetch_assoc();
            $eval_id = $existing_eval['id'];
            
            $sql = "UPDATE evaluations SET
                evaluator_id = ?, training_title = ?, date_conducted = ?, 
                objectives = ?, comments = ?, future_training_needs = ?, rated_by = ?, signature_data = ?, 
                assessment_date = ?, rating_1 = ?, rating_2 = ?, rating_3 = ?, rating_4 = ?, 
                rating_5 = ?, rating_6 = ?, rating_7 = ?, rating_8 = ?, remark_1 = ?, remark_2 = ?, 
                remark_3 = ?, remark_4 = ?, remark_5 = ?, remark_6 = ?, remark_7 = ?, remark_8 = ?, 
                status = 'submitted', updated_at = NOW()
                WHERE id = ?";
            
            $stmt = $con->prepare($sql);
            $stmt->bind_param(
                'issssssssiiiiiiiissssssssi',
                $evaluator_id,
                $training_title,
                $date_conducted,
                $objectives,
                $comments,
                $future_training,
                $rated_by,
                $signature_data,
                $assessment_date,
                $ratings[0], $ratings[1], $ratings[2], $ratings[3],
                $ratings[4], $ratings[5], $ratings[6], $ratings[7],
                $remarks[0], $remarks[1], $remarks[2], $remarks[3],
                $remarks[4], $remarks[5], $remarks[6], $remarks[7],
                $eval_id
            );
            
            if ($stmt->execute()) {
                // Add to workflow history
                $workflow_sql = "INSERT INTO evaluation_workflow (evaluation_id, from_status, to_status, changed_by) 
                                 VALUES (?, 'draft', 'submitted', ?)";
                $workflow_stmt = $con->prepare($workflow_sql);
                $workflow_stmt->bind_param('ii', $eval_id, $evaluator_id);
                $workflow_stmt->execute();
            }
        } else {
            // Insert new evaluation
            $sql = "INSERT INTO evaluations (
                user_id, assessment_id, evaluator_id, training_title, date_conducted, 
                objectives, comments, future_training_needs, rated_by, signature_data, 
                assessment_date, rating_1, rating_2, rating_3, rating_4, 
                rating_5, rating_6, rating_7, rating_8, remark_1, remark_2, 
                remark_3, remark_4, remark_5, remark_6, remark_7, remark_8, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')";
            
            $stmt = $con->prepare($sql);
            $stmt->bind_param(
                'iiissssssssiiiiiiiissssssss',
                $userId,
                $evaluationId,
                $evaluator_id,
                $training_title,
                $date_conducted,
                $objectives,
                $comments,
                $future_training,
                $rated_by,
                $signature_data,
                $assessment_date,
                $ratings[0], $ratings[1], $ratings[2], $ratings[3],
                $ratings[4], $ratings[5], $ratings[6], $ratings[7],
                $remarks[0], $remarks[1], $remarks[2], $remarks[3],
                $remarks[4], $remarks[5], $remarks[6], $remarks[7]
            );
            
            if ($stmt->execute()) {
                $new_evaluation_id = $con->insert_id;
                
                // Update user's evaluation_id
                $update_sql = "UPDATE users SET evaluation_id = ? WHERE id = ?";
                $update_stmt = $con->prepare($update_sql);
                $update_stmt->bind_param('ii', $new_evaluation_id, $userId);
                $update_stmt->execute();
                
                // Add to workflow history
                $workflow_sql = "INSERT INTO evaluation_workflow (evaluation_id, to_status, changed_by) 
                                 VALUES (?, 'submitted', ?)";
                $workflow_stmt = $con->prepare($workflow_sql);
                $workflow_stmt->bind_param('ii', $new_evaluation_id, $evaluator_id);
                $workflow_stmt->execute();
            }
        }
        
        // Update the evaluation status in the assessments table if it exists
        if (tableExists($con, 'assessments')) {
            $update_status_sql = "UPDATE assessments SET eval_status = 'completed' WHERE id = ?";
            $update_status_stmt = $con->prepare($update_status_sql);
            $update_status_stmt->bind_param('i', $evaluationId);
            $update_status_stmt->execute();
        }
        
        // Create notification for HR
        $notification_msg = "New evaluation submitted for " . $name;
        $notif_sql = "INSERT INTO notifications (user_id, message, related_id, related_type, type) 
                      SELECT id, ?, ?, 'evaluation', 'new_evaluation' 
                      FROM users WHERE role LIKE 'admin_%' OR role = 'admin'";
        $notif_stmt = $con->prepare($notif_sql);
        $notif_stmt->bind_param('si', $notification_msg, $new_evaluation_id);
        $notif_stmt->execute();
        
        // Commit transaction
        $con->commit();
        
        // Return success response
        if (isset($_POST['action']) && $_POST['action'] === 'submit') {
            echo json_encode(['success' => true, 'message' => 'Evaluation submitted successfully!']);
            exit();
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $con->rollback();
        error_log("Evaluation submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to submit evaluation.']);
        exit();
    }
}

// Helper function to check if table exists
function tableExists($con, $table) {
    $result = $con->query("SHOW TABLES LIKE '$table'");
    return $result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Program Impact Assessment Form Evaluation</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#64748b'
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
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="css/tpi.css">

</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="form-container max-w-5xl mx-auto rounded-lg p-6 md:p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">TRAINING PROGRAM IMPACT ASSESSMENT FORM</h1>
            <button id="close-form" class="text-gray-500 hover:text-gray-700">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>

        <form action="training_program_impact_assessment_form.php" method="POST" id="assessment-form">
            <!-- Hidden fields to store the passed parameters -->
            <input type="hidden" id="evaluation-id" name="evaluation_id" value="<?= htmlspecialchars($evaluationId) ?>">
            <input type="hidden" id="user-id" name="user_id" value="<?= htmlspecialchars($userId) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name of Employee:</label>
                    <input id="name" type="text" name="name" class="form-field w-full px-4 py-2 rounded" 
                           value="<?= htmlspecialchars($facultyName) ?>" placeholder="Enter employee name" required readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department/Unit:</label>
                    <input type="text" name="department" class="form-field w-full px-4 py-2 rounded" value="CCS" readonly>
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
                                  <input type="radio" id="q<?= $i ?>-<?= $j ?>" name="rating[<?= $i-1 ?>]" value="<?= $j ?>" required>
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
                        <input type="text" name="rated_by" class="form-field w-full px-4 py-2 rounded" 
                               value="<?= htmlspecialchars($evaluator['name'] ?? '') ?>" placeholder="Immediate Supervisor's Name" required readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Signature:</label>
                        <div id="signature-pad" class="signature-pad w-full h-20 rounded flex items-center justify-center cursor-pointer">
                            <span class="text-gray-400">Click to sign or upload image</span>
                        </div>
                        <input type="file" id="signature-upload" accept="image/*" class="hidden" />
                        <input type="hidden" name="signature_data" id="signature-data" required />
                        <div class="signature-actions">
                            <button type="button" id="upload-signature" class="signature-btn upload-btn">Upload Image</button>
                            <button type="button" id="clear-signature" class="signature-btn clear-btn">Clear</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date:</label>
                        <input type="date" name="assessment_date" class="form-field w-full px-4 py-2 rounded" required>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end space-x-4">
                <button type="button" id="clear-form" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-button hover:bg-gray-300 transition-colors whitespace-nowrap">Clear Form</button>
                <button type="submit" name="action" value="print" class="px-6 py-2 bg-green-500 text-white rounded-button hover:bg-green-600 transition-colors whitespace-nowrap">Print</button>
                <button type="submit" id="submit-form" name="action" value="submit" class="px-6 py-2 bg-primary text-white rounded-button hover:bg-blue-600 transition-colors whitespace-nowrap">Submit Assessment</button>
            </div>
        </form>
    </div> 

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set current date as default for assessment date
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="assessment_date"]').value = today;

        // Close button functionality
        document.getElementById('close-form').addEventListener('click', function() {
            // Send message to parent window to close the modal
            if (window.opener) {
                window.opener.postMessage('closeModal', '*');
            } else {
                // For iframe implementation
                window.parent.postMessage('closeModal', '*');
            }
        });

        // Signature pad functionality
        const signaturePad = document.getElementById('signature-pad');
        const signatureDataInput = document.getElementById('signature-data');
        const signatureUpload = document.getElementById('signature-upload');
        const uploadSignatureBtn = document.getElementById('upload-signature');
        const clearSignatureBtn = document.getElementById('clear-signature');
        let isDrawing = false;
        let ctx;
        let canvas;

        function initializeCanvas() {
            canvas = document.createElement('canvas');
            canvas.width = signaturePad.clientWidth;
            canvas.height = signaturePad.clientHeight;
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.cursor = 'crosshair';
            
            ctx = canvas.getContext('2d');
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#3b82f6';
            
            signaturePad.innerHTML = '';
            signaturePad.appendChild(canvas);
            
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);
            
            canvas.addEventListener('touchstart', startDrawingTouch);
            canvas.addEventListener('touchmove', drawTouch);
            canvas.addEventListener('touchend', stopDrawing);
        }

        function startDrawing(e) {
            isDrawing = true;
            ctx.beginPath();
            ctx.moveTo(e.offsetX, e.offsetY);
        }

        function startDrawingTouch(e) {
            e.preventDefault();
            if (e.touches.length === 1) {
                const touch = e.touches[0];
                const rect = canvas.getBoundingClientRect();
                const offsetX = touch.clientX - rect.left;
                const offsetY = touch.clientY - rect.top;

                isDrawing = true;
                ctx.beginPath();
                ctx.moveTo(offsetX, offsetY);
            }
        }

        function draw(e) {
            if (!isDrawing) return;
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.stroke();
        }

        function drawTouch(e) {
            e.preventDefault();
            if (!isDrawing || e.touches.length !== 1) return;

            const touch = e.touches[0];
            const rect = canvas.getBoundingClientRect();
            const offsetX = touch.clientX - rect.left;
            const offsetY = touch.clientY - rect.top;

            ctx.lineTo(offsetX, offsetY);
            ctx.stroke();
        }

        function stopDrawing() {
            isDrawing = false;
        }

        function clearSignature() {
            if (canvas) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }

        // Handle image upload
        uploadSignatureBtn.addEventListener('click', function() {
            signatureUpload.click();
        });

        signatureUpload.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        // Initialize canvas if not already done
                        if (!canvas) {
                            initializeCanvas();
                        }
                        
                        // Clear any existing signature
                        clearSignature();
                        
                        // Calculate dimensions to maintain aspect ratio
                        const canvasAspect = canvas.width / canvas.height;
                        const imgAspect = img.width / img.height;
                        
                        let drawWidth, drawHeight, offsetX = 0, offsetY = 0;
                        
                        if (imgAspect > canvasAspect) {
                            // Image is wider than canvas (relative to height)
                            drawHeight = canvas.height;
                            drawWidth = img.width * (canvas.height / img.height);
                            offsetX = (canvas.width - drawWidth) / 2;
                        } else {
                            // Image is taller than canvas (relative to width)
                            drawWidth = canvas.width;
                            drawHeight = img.height * (canvas.width / img.width);
                            offsetY = (canvas.height - drawHeight) / 2;
                        }
                        
                        // Draw the image centered on the canvas
                        ctx.drawImage(img, offsetX, offsetY, drawWidth, drawHeight);
                        
                        // Set signature data
                        signatureDataInput.value = canvas.toDataURL();
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Clear signature button
        clearSignatureBtn.addEventListener('click', function() {
            clearSignature();
            signatureDataInput.value = '';
        });

        // Initialize canvas on load
        initializeCanvas();
        window.addEventListener('resize', initializeCanvas);

        // Form submission handling
        const form = document.getElementById('assessment-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Always capture signature data
                if (canvas && !signatureDataInput.value) {
                    const signatureDataURL = canvas.toDataURL();
                    signatureDataInput.value = signatureDataURL;
                }

                // For submit action, handle with AJAX
                if (e.submitter && e.submitter.value === 'submit') {
                    e.preventDefault();
                    
                    // Validate required fields
                    let isValid = true;
                    const requiredFields = document.querySelectorAll('[required]');
                    const ratingInputs = document.querySelectorAll('input[type="radio"]:checked');
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('border-red-500');
                            isValid = false;
                        } else {
                            field.classList.remove('border-red-500');
                        }
                    });
                    
                    // Check if all ratings are provided
                    if (ratingInputs.length < 8) {
                        alert('Please provide ratings for all 8 questions.');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        alert('Please fill in all required fields.');
                        return;
                    }

                    // Show loading state
                    const submitBtn = document.getElementById('submit-form');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = 'Submitting...';
                    submitBtn.disabled = true;

                    // Submit form via AJAX
                    const formData = new FormData(form);
                    
                    fetch('training_program_impact_assessment_form.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Evaluation submitted successfully!');
                            // Notify parent to close modal
                            if (window.opener) {
                                window.opener.postMessage('closeModal', '*');
                            } else {
                                window.parent.postMessage('closeModal', '*');
                            }
                            // Reload parent page to update evaluation status
                            if (window.opener) {
                                window.opener.location.reload();
                            }
                        } else {
                            alert(data.message || 'Failed to submit evaluation.');
                        }
                    })
                    .catch(error => {
                        alert('An error occurred while submitting the evaluation.');
                        console.error('Error:', error);
                    })
                    .finally(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
                }
            });
        }

        // Clear form button
        document.getElementById('clear-form').addEventListener('click', function() {
            // Clear all input fields except name and department
            document.querySelectorAll('input[type="text"]:not(#name):not([name="department"]):not([name="rated_by"]), input[type="date"], textarea').forEach(input => {
                input.value = '';
            });
            
            // Uncheck all radio buttons
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.checked = false;
            });
            
            // Clear signature
            clearSignature();
            signatureDataInput.value = '';
        });
    });
    </script>
</body>
</html>