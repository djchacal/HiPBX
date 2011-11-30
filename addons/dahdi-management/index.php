<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<?php 
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

include("include.php");

global $db;

# Handle commands. Lets see what's been clicked, if anything.

foreach($_REQUEST as $key => $val) {
	switch($val) {
		case "Move Down":
			move_astribank($key, "down");
			break;
		case "Move Up":
			move_astribank($key, "up");
			break;
	}
}
	

$sql = "select serial from provis_dahdi_astribanks_layout order by disporder";
$res = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
# Each Astribank's serial number
?>

<html>
<head>
<title>DAHDI Overview</title>
<script src='jquery.tools.min.js'></script>
<script src='spin.js'></script>
<script src='dahdi.js'></script>
<link rel="stylesheet" type="text/css" href="dahdi.css" />
</head>
<body>

<div id="olay" style="display: none"></div>
<div id="content" style="display: none"><span id="ctext"></span></div>


<form method='post'>

<?php
foreach ($res as $row) {
	print "<table cellspacing=0>\n";
	print "<caption> ".$row['serial']."</caption>\n";
	print "<tr>\n";
	# Each Astribank has a number of spans
	$sql = "select * from provis_dahdi_spans where serial='".$row['serial']."' and span > 0 order by xpd";
	$spans = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
	foreach ($spans as $span) {
		if ($span['ports'] > 14) {
			print "<td><h3 class='pri'>PRI Not Available</h3>&nbsp;Use FreePBX to manage this PRI&nbsp;</td>";
			continue;
		}
		print "<td class='spans' id='".$row['serial']."_".$span['xpd']."'>";
		print display_ports($row['serial'], $span['xpd'], $span['span']);
		print "</td>";
	}
	print "<td style='padding: 0px; border: 0px'>";
	print "<table class='buttons'><tr><td><input class='mbuttons' type='submit' name='".$row['serial']."' value='Move Up'></td>\n";
	print "<td><button class='mbuttons' onClick='return blink(\"".$row['serial']."\", \"blinkon\");'>Blink On</button></td></tr>";
	print "<tr><td><input class='mbuttons' type='submit' name='".$row['serial']."' value='Move Down'></td>\n";
	print "<td><button class='mbuttons' onClick='return blink(\"".$row['serial']."\", \"blinkoff\");'>Blink Off</button></td></tr></table>";
	print "</td>";
	print "</tr></table><div class='ports' id='".$row['serial']."'></div>";
}

?>
<script> bindall(); </script>

