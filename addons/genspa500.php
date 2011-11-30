<?php


$buttons = array("Rob Thomas <500>", "Do Not Disturb", "Day/Night switch", "Xorcom <+972 4 9951999>", "Fred's Mobile <0402 077 155>", "dbond <756>", null, null, null, "Other", "Ford Prefect <+44 1837 223 224>", "Divert to Mobile <*450402077155>", "Blah Blah <123>", "I'm getting bored <of typing these>", "But I need more", "I don't need to", "put numbers <123>", "In all of the m <though>");
require('fpdf/fpdf.php');
$pdf=new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial','',16);
$pdf->Cell(40,10, 'SPA500 Keypad Layout for 999 (Username)');
$pdf->SetFont('Arial','',8);

$xpos = 10;

# Just draw the boxes
for ($i = 0; $i <=31; $i++) {
  $bg = $i+1;
  if ($i >= 16) {
        $xpos=33.5;
        $bg--;
  }
  $pdf->SetFillColor("192");
  $pdf->SetXY($xpos, ($i%16)*9.6875+30);
        $pdf->Cell(23.5, 9.6875,"",1,0,"C",($bg%2));
}


$xpos=10;
# Now, fill the details in.
for ($i = 0; $i <=31; $i++) {
  if (!isset($buttons[$i])) {
        continue;
  }
  if ($i >= 16) {
        $xpos=33.5;
  }
  $pdf->SetXY($xpos, ($i%16)*9.6875+30);
  # Does it have a < in it? If so, that should go on the second line.
  if (preg_match("/(.+)<(.+)>/", $buttons[$i], $matches)) {
        $pdf->Cell(23.5, 9.6875/2, $matches[1], 0, 0, "C");
        $pdf->SetXY($xpos, ($i%16)*9.6875+30+9.8675/2);
        $pdf->Cell(23.5, 9.6875/2, $matches[2], 0, 0, "R");
  } else {
                $pdf->Cell(23.5, 9.6875,$buttons[$i], 0, 0, "C");
  }
}
#
$pdf->Output();
?>


