<?php
# Die noisily if fpdf doesn't exist
require('fpdf/fpdf.php');

# Load our FreePBX stuff up.
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

$ext = $_REQUEST['ext'];
if (!is_numeric($ext)) {
	print "Need a numeric extension. Got $ext instead\n";
	exit;
}

# Grab the details of the handset
$sql="select m.global_settings_override as conf, m.global_custom_cfg_data as data from simple_endpointman_mac_list as m, simple_endpointman_line_list as e where m.id=e.mac_id and e.ext='$ext'";
$results = $db->getRow($sql, DB_FETCHROW_ASSOC);
$conf=json_decode($results[0], true);
$data=json_decode($results[1], true);

if (!$conf['enable_sidecar1'] && !$conf['enable_sidecar2']) { 
	print "No Sidecar\n";
	exit;
}

if (!isset($data['data']['unit1'])) {
	print "No data for sidecar\n";
	exit;
}

# Load up the stuff into an array
foreach ($data['data']['unit1'] as $arr) {
	$buttons[]=getuser($arr['data']);
}

# Start making a PDF!
$pdf=new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial','',16);
$pdf->Cell(40,10, 'SPA500 Keypad Layout for '.getuser($ext));
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
# And display it.
$pdf->Output();

function getuser($ext) {
	$res=core_users_get($ext);
	if (!isset($res['name'])) {
		return "$ext";
	} else {
		return $res['name']." <$ext>";
	}
}

