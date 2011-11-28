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
<STYLE type="text/css">
   body { font-family: 'Trebuchet MS', Arial; }
   H1.myclass {border-width: 1; border: solid; text-align: center}
   .click {text-decoration: underline; cursor: pointer}
   .ext {color: blue; cursor: pointer}
   TD {text-align: center}
   P.warning {padding-left: 1em;}
   P#addstat {margin: 0px; text-align: center}
   #port_9,#port_10,#port_11,#port_12,#port_13,#port_14 {background-color: LightGray}
   #olay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #000;
    		filter:alpha(opacity=50); -moz-opacity:0.5; -khtml-opacity: 0.5; opacity: 0.5; z-index: 1000; }
   #content { display:none; width:400px; border:10px solid #666; background-color: #fff; 
		border:10px solid rgba(182, 182, 182, 0.698); -moz-border-radius:8px; -webkit-border-radius:8px;
		z-index: 2000 }
   #content .close { background-image:url(close.png); position:absolute; right:-15px; top:-15px; cursor:pointer;
		height:35px; width:35px; }
   #content h2 { text-align: center; }
   .right { width: 100px; }
   .left { padding-left: 1em; display: inline-block; width: 150px; }
	#triggers {
		text-align:center;
	}
	
	#triggers img {
		cursor:pointer;
		margin:0 5px;
		background-color:#fff;
		border:1px solid #ccc;
		padding:2px;
	
		-moz-border-radius:4px;
		-webkit-border-radius:4px;
		
	}
	


</STYLE>
</head>
<body>

<div id="olay" style="display: none"></div>
<div id="content" style="display: none"><span id="ctext"></span></div>


<form method='post'>

<?php
foreach ($res as $row) {
	print "<table border=1>\n";
	print "<caption> ".$row['serial']."</caption>\n";
	print "<tr>\n";
	# Each Astribank has a number of spans
	$sql = "select * from provis_dahdi_spans where serial='".$row['serial']."' and span > 0 order by xpd";
	$spans = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
	foreach ($spans as $span) {
		if ($span['ports'] > 14) {
			print "<td>PRI</td>";
			continue;
		}
		print "<td id='".$row['serial']."_".$span['xpd']."'>";
		print display_ports($row['serial'], $span['xpd'], $span['span']);
		print "</td>";
	}
	print "<td><input type='submit' name='".$row['serial']."' value='Move Up'>";
	print "<input type='submit' name='".$row['serial']."' value='Move Down'>";
	print "</td>";
	print "</tr></table><div class='ports' id='".$row['serial']."'></div>";
}

?>
<script> bindall(); </script>

