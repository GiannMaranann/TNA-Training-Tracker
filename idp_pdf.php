<?php
require('fpdf.php');
class PDF extends FPDF
{
    function Header(){
        $this->Image('images/lspubg2.png',10,5,18);
        $this->SetFont('Arial','B',9);
        $this->Cell(0,4,'Republic of the Philippines',0,1,'C');
        $this->SetFont('Arial','B',10);
        $this->Cell(0,4,'Laguna State Polytechnic University',0,1,'C');
        $this->SetFont('Arial','',9);
        $this->Cell(0,4,'Province of Laguna',0,1,'C');
        $this->Ln(2);
        $this->SetFont('Arial','B',10);
        $this->Cell(0,5,'INDIVIDUAL DEVELOPMENT PLAN',0,1,'C');
        $this->Ln(2);
    }
    function Footer(){ $this->SetY(-10); $this->SetFont('Arial','I',7); $this->Cell(0,4,'LSPU-HRO-SF-027          Rev. 1                                                   2 April 2018',0,0,'C'); }
    function NbLines($w,$txt){ $cw=&$this->CurrentFont['cw']; if($w==0)$w=$this->w-$this->rMargin-$this->x; $wmax=($w-2*$this->cMargin)*1000/$this->FontSize; $s=str_replace("\r",'', $txt); $nb=strlen($s); if($nb>0 && $s[$nb-1]=="\n")$nb--; $sep=-1;$i=0;$j=0;$l=0;$nl=1; while($i<$nb){$c=$s[$i]; if($c=="\n"){ $i++; $sep=-1;$j=$i;$l=0;$nl++; continue;} if($c==' '){$sep=$i;} $l+=$cw[$c]; if($l>$wmax){ if($sep==-1){ if($i==$j)$i++;} else $i=$sep+1; $sep=-1;$j=$i;$l=0;$nl++;} else $i++;} return $nl;} }

$pdf=new PDF('P','mm','Letter');
$pdf->AddPage();
$pdf->SetMargins(8,8,8);
$pdf->SetAutoPageBreak(false);
$pdf->SetFont('Arial','',6.5);

$col=47.5;$h=5;
$info=[['1. Name','6. Years in other office/agency if any'],['2. Current Position','7. Division'],['3. Salary Grade','8. Office'],['4. Years in the Position','9. No further development is desired or required for'],['5. Years in LSPU','10. Supervisor\'s Name']];
foreach($info as $row){
    $pdf->Cell($col,$h,$row[0],1);
    $pdf->Cell($col,$h,'',1);
    $pdf->Cell($col,$h,$row[1],1);
    $pdf->Cell($col,$h,'',1);
    $pdf->Ln();
}
$pdf->Ln(1);

$pdf->SetFont('Arial','B',6.5);
$pdf->Cell(0,4,'PURPOSE:',0,1);
$pdf->SetFont('Arial','',6.3);
$pur=[
    'To meet the competencies in the current positions',
    'To increase the level of competencies of current positions',
    'To meet the competencies in the next higher position',
    'To acquire new competencies across different functions/position',
    'Others, please specify ______'
];
foreach($pur as $p){ $pdf->Cell(4,3,'( )'); $pdf->Cell(0,3,$p); $pdf->Ln(); }
$pdf->Ln(1);

$pdf->SetFont('Arial','B',6.5);
$pdf->Cell(0,4,'CAREER DEVELOPMENT:',0,1);
$pdf->SetFont('Arial','',6);
$w=[60,60,35,35];
$pdf->Cell($w[0],4,'Area of Development',1,0,'C');
$pdf->Cell($w[1],4,'Development Activity',1,0,'C');
$pdf->Cell($w[2],4,'Target Completion Date',1,0,'C');
$pdf->Cell($w[3],4,'Completion Stage',1,1,'C');

$ad='Academic (if applicable), attendance to seminar on Supervisory Development Program & Management/ Executive & Leadership Development Program';
$h1=$pdf->NbLines($w[0],$ad)*3;
$pdf->MultiCell($w[0],3,$ad,1);
$y=$pdf->GetY();
$pdf->SetXY(8+$w[0],$y-$h1);
$pdf->MultiCell($w[1],3,'Pursuance of Academic Degrees for advancement, conduct of trainings/seminars',1);
$y2=$pdf->GetY();
$h=max($y,$y2)-($y-$h1);
$pdf->SetXY(8+$w[0]+$w[1],$y-$h1); $pdf->Cell($w[2],$h,'',1);
$pdf->SetXY(8+$w[0]+$w[1]+$w[2],$y-$h1); $pdf->Cell($w[3],$h,'',1);
$pdf->Ln(2);

$pdf->SetFont('Arial','B',6.5);
$pdf->Cell(0,4,'Short Term Development Goals Next Year',0,1);
$wh=[50,40,40,20,20,20];
$head=['Area of Development','Priority for Learning and Development Program (LDP)','Development Activity','Target Completion Date','Who is Responsible','Completion Stage'];
$pdf->SetFont('Arial','',6);
foreach($wh as $i=>$wi){ $pdf->Cell($wi,4,$head[$i],1,0,'C'); } $pdf->Ln();

$data=[
    ['1. Behavioral Training such as: Value Re-orientation, Team Building, Oral Communication, Written Communication, Customer Relations, People Development, Improving Planning & Delivery, Solving Problems and making decisions, Basic Communication Training Program, etc','','Conduct of training/seminar','','',''],
    ['2. Technical Skills Training such as: Basic Occupational Safety & health, University Safety procedures, Preventive Maintenance Activities, etc.','','','','',''],
    ['3. Quality Management Training such as: Customer Requirements, Time Management, Continous Improvement for Quality & Productivity, etc','','','','',''],
    ['4. Others: Formal Classroom Training, on-the job training, Self-development, developmental activities/interventions, etc.','','Coaching on the job-knowledge sharing and learning session','','','']
];
foreach($data as $r){
    $max=1; foreach($r as $i=>$t){ $ln=$pdf->NbLines($wh[$i],$t); if($ln>$max)$max=$ln; }
    $rh=$max*2.8;
    foreach($r as $i=>$t){
        $x=$pdf->GetX(); $y=$pdf->GetY();
        $pdf->Rect($x,$y,$wh[$i],$rh);
        $pdf->MultiCell($wh[$i],2.8,$t,0,'L');
        $pdf->SetXY($x+$wh[$i],$y);
    }
    $pdf->Ln($rh);
}

$pdf->Ln(1);
$pdf->SetFont('Arial','B',6.5);
$pdf->Cell(0,4,'CERTIFICATION AND COMMITMENT',0,1,'C');
$pdf->SetFont('Arial','',6);
$pdf->MultiCell(0,3,'This is to certify that this Individual Development Plan has been discussed with me by immediate superior. I further commit that I will exert time and effort to ensure that this will be achieved according to agreed time frames.',0,'C');
$pdf->Ln(2);
$pdf->Cell(0,4,'Signature of Employee: ____________________________');$pdf->Ln(3);
$pdf->Cell(0,4,'Immediate Supervisor');$pdf->Ln(3);
$pdf->Cell(0,4,'I commit to support and ensure that this agreed Individual Development Plan is achieved to the agreed time frames');$pdf->Ln(3);
$pdf->Cell(0,4,'Campus Director');

$pdf->Output('I','Individual_Development_Plan.pdf');