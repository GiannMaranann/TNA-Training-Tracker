<?php 
require('fpdf.php');
require('config.php');
session_start();

class PDF extends FPDF
{
    function Header()
    {
        $this->Image('images/lspubg2.png', 30, 10, 25);
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'Republic of the Philippines', 0, 1, 'C');

        $this->AddFont('oldengl', '', 'OLDENGL.php');
        $this->SetFont('oldengl', '', 16);
        $this->Cell(0, 6, 'Laguna State Polytechnic University', 0, 1, 'C');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'Province of Laguna', 0, 1, 'C');
        $this->Ln(8);

        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Training Needs Assessment Form', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-20);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'LSPU-HRO-SF-025   Rev.1    15 October 2018', 0, 0, 'C');
    }

    function PersonalProfile($data)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Personal Profile', 0, 1);

        $this->SetFont('Arial', '', 12);
        $this->Cell(80, 8, 'Name:', 0, 0);
        $this->Cell(0, 8, $data['name'], 0, 1);
        $this->Cell(80, 8, 'Highest Educational Attainment:', 0, 0);
        $this->Cell(0, 8, $data['educationalAttainment'], 0, 1);
        $this->Cell(80, 8, 'Specialization:', 0, 0);
        $this->Cell(0, 8, $data['specialization'], 0, 1);
        $this->Cell(80, 8, 'Present Designation:', 0, 0);
        $this->Cell(0, 8, $data['designation'], 0, 1);
        $this->Cell(80, 8, 'Department:', 0, 0);
        $this->Cell(0, 8, $data['department'], 0, 1);
        $this->Cell(80, 8, 'Years in LSPU:', 0, 0);
        $this->Cell(0, 8, $data['yearsInLSPU'], 0, 1);
        $this->Cell(80, 8, 'Type of Employment:', 0, 0);
        $this->Cell(0, 8, $data['teaching_status'], 0, 1);
    }

    function TrainingHistory($dates, $trainings, $venues, $start_times, $end_times, $durations)
    {
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Please list all trainings attended for the last three years.', 0, 1);

        $this->SetFont('Arial', '', 11);
        if ($dates && $trainings && $venues) {
            for ($i = 0; $i < count($dates); $i++) {
                $date = $dates[$i] ?? '';
                $start = $start_times[$i] ?? '';
                $end = $end_times[$i] ?? '';
                $duration = $durations[$i] ?? '';
                $training = $trainings[$i] ?? '';
                $venue = $venues[$i] ?? '';

                if ($date || $training || $venue) {
                    // Replace problematic characters (just to be safe)
                    $date = str_replace(['â€“', 'â€”', '?'], '-', $date);
                    $start = str_replace(['â€“', 'â€”', '?'], '-', $start);
                    $end = str_replace(['â€“', 'â€”', '?'], '-', $end);
                    $duration = str_replace(['â€“', 'â€”', '?'], '-', $duration);
                    $training = str_replace(['â€“', 'â€”', '?'], '-', $training);
                    $venue = str_replace(['â€“', 'â€”', '?'], '-', $venue);

                    $this->SetFont('Arial', 'B', 11);
                    $this->MultiCell(0, 7, "- $date ($start - $end)", 0, 1);
                    $this->SetFont('Arial', '', 11);
                    $this->MultiCell(0, 7, "Duration: $duration", 0, 1);
                    $this->MultiCell(0, 7, "$training at $venue", 0, 1);
                    $this->Ln(2);
                }
            }
        } else {
            $this->MultiCell(0, 7, 'No training data provided.', 0, 1);
        }
    }

    function DesiredSkills($skills, $additionalTraining, $comments)
    {
        $this->Ln(8);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Other training courses relevant and important to your present job that you may want to attend.', 0, 1);

        $this->SetFont('Arial', '', 11);
        if (!empty($skills)) {
            $this->MultiCell(0, 7, 'Desired Skills: ' . implode(', ', $skills), 0, 1);
        }

        if (!empty($additionalTraining)) {
            $this->Ln(2);
            $this->MultiCell(0, 7, $additionalTraining, 0, 1);
        } else {
            $this->MultiCell(0, 7, 'Other Training: No additional training specified.', 0, 1);
        }

        $this->Ln(5);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Comments / Suggestions.', 0, 1);
        $this->SetFont('Arial', '', 11);
        $this->MultiCell(0, 7, $comments ?: 'No comments provided.', 0, 1);
    }
}

// Collect profile data from session
$data = [
    'name' => $_SESSION['profile_name'] ?? '',
    'educationalAttainment' => $_SESSION['profile_educationalAttainment'] ?? '',
    'specialization' => $_SESSION['profile_specialization'] ?? '',
    'designation' => $_SESSION['profile_designation'] ?? '',
    'department' => $_SESSION['profile_department'] ?? '',
    'yearsInLSPU' => $_SESSION['profile_yearsInLSPU'] ?? '',
    'teaching_status' => $_SESSION['profile_teaching_status'] ?? ''
];

// Collect assessment form data from POST
$dates = $_POST['date'] ?? [];
$trainings = $_POST['training'] ?? [];
$venues = $_POST['venue'] ?? [];
$start_times = $_POST['start_time'] ?? [];
$end_times = $_POST['end_time'] ?? [];
$durations = $_POST['duration'] ?? [];

$skills = $_POST['selected_skills'] ?? [];
$additionalTraining = $_POST['selected_training'] ?? '';
$comments = $_POST['comments'] ?? '';

// Create PDF
$pdf = new PDF();
$pdf->AddPage();

$pdf->PersonalProfile($data);
$pdf->TrainingHistory($dates, $trainings, $venues, $start_times, $end_times, $durations);
$pdf->DesiredSkills($skills, $additionalTraining, $comments);
$pdf->Output('I', 'Training_Needs_Assessment.pdf');
?>
