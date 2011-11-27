<?php 
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}
?>
<?php

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
<STYLE type="text/css">
   H1.myclass {border-width: 1; border: solid; text-align: center}
   .click {text-decoration: underline; cursor: pointer}
   .exts {color: blue}
   #port_9,#port_10,#port_11,#port_12,#port_13,#port_14 {background-color: LightGray}
</STYLE>
</head>
<body>

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
		print "<td>";
		if ($span['ports'] > 14) {
			print "PRI\n";
			continue;
		}
		# Now, how many extens do we have assigned to these ports?
		$sql = "select * from provis_dahdi_ports where serial='".$row['serial']."' and xpd='".$span['xpd']."' order by ext";
		$exts = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
		print "<div class='click' sno='".$row['serial']."' xpd='".$span['xpd']."'>";
		if (count($exts) === 0) {
			print "No Ports Assigned";
		} else {
			print count($exts)." Ports Assigned";
		}
		print "</div></td>\n";
	}
	print "<td><input type='submit' name='".$row['serial']."' value='Move Up'>";
	print "<input type='submit' name='".$row['serial']."' value='Move Down'>";
	print "</td>";
	print "</tr></table><div class='ports' id='".$row['serial']."'></div>";
}

?>
<script>
	$('.click').bind('click', function() {
		var s=$(this).attr('sno');
		var x=$(this).attr('xpd');
		$.ajax({
			type: 'POST',
			url: 'ports.php',
			data: 'sno='+s+"&xpd="+x,
		}).done(function( msg ) {  
			$(".ports").hide('fast');
			$(".ports").html("");
			$("#"+s).html(msg);
			$("#"+s).show('fast');
		});
	});
	function modport(ser, xpd, no) {
		alert("I want to mod ser "+ser+", xpd "+xpd+", nbr "+no);
	}
</script>

<?php

function move_astribank($ser, $dir) {
	global $db;
	# First, check to see if we're the bottom-most already.
	if ($dir == "down")  {
		$cmd = " desc "; 
	} else {
		$cmd = "";
	}
	$sql = "select serial from provis_dahdi_astribanks_layout order by disporder $cmd limit 1";
	$res = $db->getOne($sql);
	# It is? User is clicking buttons randomly. Yay.
	if ($res == $ser) {
		return;
	}
	# print "($dir) I have $res\n";

	# Now we need to grab the serial number of this one..
	$sql = "select disporder from provis_dahdi_astribanks_layout where serial='$ser'";
	$myorder = $db->getOne($sql);
	
	# Are we moving it down or up?
	if ($dir == "down") {
		$moveto = $myorder+1;
	} else {
		$moveto = $myorder-1;
	}

	# Who has that ordering, if anyone?
	$sql = "select serial from provis_dahdi_astribanks_layout where disporder='$moveto'";
	$other_astribank = $db->getOne($sql);
	if ($other_astribank == null) {
		# Nothing had that number. Possibly missing an astribank? Anyway, let's just update
		# the display order
		$sql = "update provis_dahdi_astribanks_layout set disporder='$moveto' where serial='$ser'";
		$db->query($sql);
	} else {
		# OK, we have something. Let's swap it with this one.
		$sql = "update provis_dahdi_astribanks_layout set disporder='$myorder' where serial='$other_astribank'";
		$db->query($sql);
		$sql = "update provis_dahdi_astribanks_layout set disporder='$moveto' where serial='$ser'";
		$db->query($sql);
	}
}

