<?php 

function move_astribank($ser, $dir) {
	global $db;
	# First, check to see if we're the bottom-most already.
	# Order our query
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


function display_ports($ser, $xpd, $span) {
	global $db;
	# Now, how many extens do we have assigned to these ports?
	$sql = "select * from provis_dahdi_ports where serial='".$ser."' and xpd='".$xpd."' order by ext";
	$exts = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
	print "<div class='header'>XPD: ".$xpd."/Span: ".$span."</div>\n";
	print "<div id ='".$row['serial']."_".$xpd."' class='click' data-sno='".$ser."' data-xpd='".$xpd."'>";
	if (count($exts) === 0) {
		print "No Ports Assigned";
	} else {
		print count($exts)." Ports Assigned";
	}
	print "</div>\n";
}

