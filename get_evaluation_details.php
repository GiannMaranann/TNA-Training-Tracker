<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

if (!isset($_GET['evaluation_id'])) {
    http_response_code(400);
    exit('Evaluation ID is required');
}

$evaluation_id = $_GET['evaluation_id'];

try {
    // Get evaluation details
    $eval_sql = "
        SELECT 
            e.*,
            u.name as employee_name,
            u.department,
            u.teaching_status,
            ev.name as evaluator_name
        FROM evaluations e
        JOIN users u ON e.user_id = u.id
        JOIN users ev ON e.evaluator_id = ev.id
        WHERE e.id = ?
    ";
    
    $eval_stmt = $con->prepare($eval_sql);
    $eval_stmt->bind_param("i", $evaluation_id);
    $eval_stmt->execute();
    $evaluation = $eval_stmt->get_result()->fetch_assoc();
    
    if (!$evaluation) {
        http_response_code(404);
        exit('Evaluation not found');
    }
    
    // Get ratings
    $ratings_sql = "SELECT * FROM evaluation_ratings WHERE evaluation_id = ? ORDER BY question_number";
    $ratings_stmt = $con->prepare($ratings_sql);
    $ratings_stmt->bind_param("i", $evaluation_id);
    $ratings_stmt->execute();
    $ratings = $ratings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
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
    
    ?>
    <div class="space-y-6">
        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee Name:</label>
                <p class="text-gray-900"><?= htmlspecialchars($evaluation['employee_name']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department:</label>
                <p class="text-gray-900"><?= htmlspecialchars($evaluation['department']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teaching Status:</label>
                <p class="text-gray-900"><?= htmlspecialchars($evaluation['teaching_status']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Training Title:</label>
                <p class="text-gray-900"><?= htmlspecialchars($evaluation['training_title']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Conducted:</label>
                <p class="text-gray-900"><?= date('M d, Y', strtotime($evaluation['date_conducted'])) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Evaluation Status:</label>
                <span class="status-badge status-<?= $evaluation['status'] ?>">
                    <?= ucfirst($evaluation['status']) ?>
                </span>
            </div>
        </div>

        <!-- Objectives -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Objectives:</label>
            <p class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($evaluation['objectives']) ?></p>
        </div>

        <!-- Ratings Table -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">Impact/Benefits Assessment:</label>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="text-left py-2 px-3 font-medium text-gray-700 border border-gray-300">Question</th>
                            <th class="text-center py-2 px-3 font-medium text-gray-700 border border-gray-300">Rating</th>
                            <th class="text-left py-2 px-3 font-medium text-gray-700 border border-gray-300">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratings as $rating): ?>
                        <tr>
                            <td class="py-2 px-3 border border-gray-300 text-sm text-gray-700">
                                <?= $questions[$rating['question_number'] - 1] ?? 'Question ' . $rating['question_number'] ?>
                            </td>
                            <td class="py-2 px-3 border border-gray-300 text-center text-gray-900 font-medium">
                                <?= $rating['rating'] ?>/5
                            </td>
                            <td class="py-2 px-3 border border-gray-300 text-sm text-gray-700">
                                <?= htmlspecialchars($rating['remark']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Comments -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Comments:</label>
            <p class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($evaluation['comments']) ?></p>
        </div>

        <!-- Future Training Needs -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Future Training Needs:</label>
            <p class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($evaluation['future_training_needs']) ?></p>
        </div>

        <!-- Signature Section -->
        <div class="border-t border-gray-200 pt-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rated by:</label>
                    <p class="text-gray-900"><?= htmlspecialchars($evaluation['rated_by']) ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Signature:</label>
                    <?php if (!empty($evaluation['signature_data'])): ?>
                        <img src="<?= htmlspecialchars($evaluation['signature_data']) ?>" alt="Signature" class="h-16 border border-gray-300 rounded">
                    <?php else: ?>
                        <p class="text-gray-500 italic">No signature provided</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assessment Date:</label>
                    <p class="text-gray-900"><?= date('M d, Y', strtotime($evaluation['assessment_date'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Evaluator Info -->
        <div class="border-t border-gray-200 pt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Evaluated by:</label>
            <p class="text-gray-900"><?= htmlspecialchars($evaluation['evaluator_name']) ?></p>
        </div>
    </div>
    <?php
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="text-center py-8 text-red-600">Error loading evaluation details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>