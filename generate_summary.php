<?php
require('fpdf.php');

// Database connection
$mysqli = new mysqli("localhost", "root", "", "user_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Assessment Summary', 0, 1, 'C');
$pdf->Ln(10);

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(20, 10, 'ID', 1);
$pdf->Cell(40, 10, 'User ID', 1);
$pdf->Cell(50, 10, 'Training History', 1);
$pdf->Cell(50, 10, 'Desired Skills', 1);
$pdf->Cell(30, 10, 'Comments', 1);
$pdf->Ln();

// Table data
$pdf->SetFont('Arial', '', 10);
$query = "SELECT id, user_id, training_history, desired_skills, comments FROM assessments";
$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $pdf->Cell(20, 10, $row['id'], 1);
    $pdf->Cell(40, 10, $row['user_id'], 1);
    $pdf->Cell(50, 10, substr($row['training_history'], 0, 30), 1); // trimmed for width
    $pdf->Cell(50, 10, substr($row['desired_skills'], 0, 30), 1);   // trimmed for width
    $pdf->Cell(30, 10, substr($row['comments'], 0, 20), 1);
    $pdf->Ln();
}

$pdf->Output();
?>
