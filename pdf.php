<?php
require('fpdf.php');

class PDF extends FPDF
{
    function __construct()
    {
        parent::__construct();
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 15);
    }

    function Header()
    {
        // Check if image exists before trying to include it
        if (file_exists('images/lspubg2.png')) {
            $this->Image('images/lspubg2.png', 30, 10, 25);
        }
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'Republic of the Philippines', 0, 1, 'C');

        // Check if font exists before trying to use it
        if (file_exists('OLDENGL.php')) {
            $this->AddFont('oldengl', '', 'OLDENGL.php');
            $this->SetFont('oldengl', '', 16);
        } else {
            $this->SetFont('Arial', 'B', 16);
        }
        $this->Cell(0, 6, 'Laguna State Polytechnic University', 0, 1, 'C');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'Province of Laguna', 0, 1, 'C');
        $this->Ln(8);

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'TRAINING PROGRAM IMPACT ASSESSMENT FORM', 0, 1, 'C');
        $this->Ln(2);
    }

    function FormBody($data = [])
    {
        $this->SetFont('Arial', '', 9);

        $labelWidth = 50;
        $fieldWidth = 70;
        $labelWidth2 = 40;
        $fieldWidth2 = 30;

        $safeMultiCellText = function($text) {
            return utf8_decode(str_replace("\r\n", "\n", $text));
        };

        // Safely get POST data with default values
        $name = isset($data['name']) ? trim($data['name']) : '';
        $department = isset($data['department']) ? trim($data['department']) : '';
        $training_title = isset($data['training_title']) ? trim($data['training_title']) : '';
        $date_conducted = isset($data['date_conducted']) ? trim($data['date_conducted']) : '';
        $objectives = isset($data['objectives']) ? trim($data['objectives']) : '';
        $comments = isset($data['comments']) ? trim($data['comments']) : '';
        $future_training = isset($data['future_training']) ? trim($data['future_training']) : '';
        $rated_by = isset($data['rated_by']) ? trim($data['rated_by']) : '';
        $assessment_date = isset($data['assessment_date']) ? trim($data['assessment_date']) : '';
        $signature_data = isset($data['signature_data']) ? trim($data['signature_data']) : '';

        // Format dates if they exist
        if (!empty($date_conducted)) {
            $date_conducted = date('m/d/Y', strtotime($date_conducted));
        }
        if (!empty($assessment_date)) {
            $assessment_date = date('m/d/Y', strtotime($assessment_date));
        }

        // ROW 1
        $this->Cell($labelWidth, 7, 'Name of Employee:', 1, 0);
        $this->Cell($fieldWidth, 7, $safeMultiCellText($name), 1, 0);
        $this->Cell($labelWidth2, 7, 'Department/Unit:', 1, 0);
        $this->Cell($fieldWidth2, 7, $safeMultiCellText($department), 1, 1);

        // ROW 2
        $this->Cell($labelWidth, 7, 'Title of Training/Seminar Attended:', 1, 0);
        $this->Cell($fieldWidth, 7, $safeMultiCellText($training_title), 1, 0);
        $this->Cell($labelWidth2, 7, 'Date Conducted:', 1, 0);
        $this->Cell($fieldWidth2, 7, $safeMultiCellText($date_conducted), 1, 1);

        // Objectives
        $this->Cell(0, 7, 'Objective/s:', 1, 1);
        $this->MultiCell(0, 16, $safeMultiCellText($objectives), 1);
        $this->Ln(1);

        // Instruction
        $this->SetFont('Arial', '', 8);
        $instruction = "INSTRUCTION: Please check (✓) in the appropriate column the impact/benefits gained by the employee in attending the training program in a scale of 1-5 (where 5 – Strongly Agree; 4 – Agree; 3 – Neither agree nor disagree; 2 – Disagree; 1 – Strongly Disagree)";
        $this->MultiCell(0, 5, $safeMultiCellText($instruction), 0);
        $this->Ln(1);

        // Ratings table
        $ratings = isset($data['rating']) && is_array($data['rating']) ? $data['rating'] : [];
        $remarks = isset($data['remark']) && is_array($data['remark']) ? $data['remark'] : [];

        $this->SetFont('Arial', 'B', 8);
        $this->Cell(100, 8, 'IMPACT/BENEFITS GAINED', 1, 0, 'C');
        for ($i = 1; $i <= 5; $i++) {
            $this->Cell(10, 8, $i, 1, 0, 'C');
        }
        $this->Cell(40, 8, 'REMARKS', 1, 1, 'C');

        $this->SetFont('Arial', '', 7.5);
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

        foreach ($questions as $index => $q) {
            $x = $this->GetX();
            $y = $this->GetY();

            $this->MultiCell(100, 5, $safeMultiCellText($q), 1);
            $rowHeight = $this->GetY() - $y;

            $this->SetXY($x + 100, $y);

            for ($i = 1; $i <= 5; $i++) {
                if (isset($ratings[$index]) && intval($ratings[$index]) === $i) {
                    $this->SetFont('Arial', 'B', 10);
                    $this->Cell(10, $rowHeight, '✓', 1, 0, 'C');
                    $this->SetFont('Arial', '', 7.5);
                } else {
                    $this->Cell(10, $rowHeight, '', 1, 0);
                }
            }

            $remarkText = isset($remarks[$index]) ? $safeMultiCellText(trim($remarks[$index])) : '';
            $this->Cell(40, $rowHeight, $remarkText, 1, 1);
        }

        // Comments
        $this->Ln(1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, 'Comments:', 0, 1);
        $this->MultiCell(0, 12, $safeMultiCellText($comments), 1);

        // Future Training
        $this->Ln(1);
        $this->Cell(0, 6, 'Please list down other training programs he/she might need in the future:', 0, 1);
        $this->MultiCell(0, 12, $safeMultiCellText($future_training), 1);

        // Signature Section
        $this->Ln(4);
        $this->Cell(25, 6, 'Rated by:', 0, 0);
        $this->Cell(70, 6, $safeMultiCellText($rated_by ?: '__________________________'), 0, 0);
        $this->Cell(20, 6, 'Signature:', 0, 0);

        // Show signature image if available
        if (!empty($signature_data)) {
            try {
                $signature_image = preg_replace('#^data:image/\w+;base64,#i', '', $signature_data);
                $signature_binary = base64_decode($signature_image);
                
                if ($signature_binary !== false) {
                    $signature_path = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
                    if (file_put_contents($signature_path, $signature_binary) !== false) {
                        $this->Image($signature_path, $this->GetX(), $this->GetY() - 5, 40, 12);
                        unlink($signature_path);
                    }
                }
            } catch (Exception $e) {
                // Fallback if signature processing fails
                $this->Cell(40, 6, '__________________', 0, 0);
            }
        } else {
            $this->Cell(40, 6, '__________________', 0, 0);
        }

        $this->Cell(0, 6, 'Date: ' . $safeMultiCellText($assessment_date ?: '___________'), 0, 1);
        $this->Cell(70, 6, "(Immediate Supervisor's Name)", 0, 1);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 5, 'LSPU-HRO-SF-021', 0, 0, 'L');
        $this->SetX(0);
        $this->Cell($this->GetPageWidth(), 5, 'Rev. 1', 0, 0, 'C');
        $this->SetX(-50);
        $this->Cell(40, 5, '01 August 2019', 0, 0, 'R');
    }
}

// Collect and sanitize POST data
$data = [
    'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING) ?? '',
    'department' => filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING) ?? '',
    'training_title' => filter_input(INPUT_POST, 'training_title', FILTER_SANITIZE_STRING) ?? '',
    'date_conducted' => filter_input(INPUT_POST, 'date_conducted', FILTER_SANITIZE_STRING) ?? '',
    'objectives' => filter_input(INPUT_POST, 'objectives', FILTER_SANITIZE_STRING) ?? '',
    'comments' => filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING) ?? '',
    'future_training' => filter_input(INPUT_POST, 'future_training', FILTER_SANITIZE_STRING) ?? '',
    'rated_by' => filter_input(INPUT_POST, 'rated_by', FILTER_SANITIZE_STRING) ?? '',
    'assessment_date' => filter_input(INPUT_POST, 'assessment_date', FILTER_SANITIZE_STRING) ?? '',
    'rating' => filter_input(INPUT_POST, 'rating', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [],
    'remark' => filter_input(INPUT_POST, 'remark', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [],
    'signature_data' => filter_input(INPUT_POST, 'signature_data', FILTER_SANITIZE_STRING) ?? ''
];

// Create and output PDF
try {
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->FormBody($data);
    
    // Determine output method based on action
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    if ($action === 'print') {
        $pdf->Output('I', 'Training_Program_Impact_Assessment.pdf'); // Show in browser
    } else {
        $pdf->Output('D', 'Training_Program_Impact_Assessment.pdf'); // Force download
    }
} catch (Exception $e) {
    // Handle errors gracefully
    header('Content-Type: text/plain');
    echo "Error generating PDF: " . $e->getMessage();
    exit;
}
?>