<?php 
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

global $db;
include("include.php");

# RFC3986 all the things.
$sno = mysql_real_escape_string($_REQUEST['sno']);
$xpd = mysql_real_escape_string($_REQUEST['xpd']);
$port = mysql_real_escape_string($_REQUEST['port']);
$ext = mysql_real_escape_string($_REQUEST['ext']);
$cidname = mysql_real_escape_string($_REQUEST['cidname']);
$tone = mysql_real_escape_string($_REQUEST['tone']);
$action = mysql_real_escape_string($_REQUEST['action']);

# Dump everything we need to care about.
print "<input type='hidden' id='astribank' data-sno='$sno' data-xpd='$xpd' data-port='$port'></input>\n";
switch ($action) {
	case "ext":
		ajax_ext($sno, $xpd, $port);
		break;
	case "span":
		update_span($sno, $xpd);
		break;
	case "ports":
		show_ports($sno, $xpd);
		break;
	case "addext":
		addext($ext, $sno, $xpd, $port, $tone, $cidname);
		break;
	case "remove":
		removeext($sno, $xpd, $port);
		break;
	case "doremove":
		delext($ext);
		break;
	case "blinkoff":
		blinkoff($sno);
		break;
	case "blinkon":
		blinkon($sno);
		break;
}

function ajax_ext($sno, $xpd, $port) {
	global $db;


	# Firstly, figure out if we're on ports 9-14 inclusive. If so,
	# need to make the user aware this is a relay port, not a phone
	# port.


	if ($xpd == 0 && ($port > 8 && $port < 15)) {
		print "<h2>WARNING: THIS IS A RELAY PORT</h2>";
		print "<p class='warning'>This port is NOT ACCESSIBLE through the TCO, only through the front of the ";
		print "Astribank. You probably do NOT want to use this</p>";
		$relay = true;
	} else {
		$relay = false;
	}

	# Figure out which ACTUAL port number we're on
	$portno = ($xpd/10)*8+$port;
	if ($portno > 16) {
		$pairno = $portno - 16;
		if ($relay) {
			print "<h2>Modify Port $portno (No TCO)</h2>\n";
		} else {
			print "<h2>Modify Port $portno (Cable B, Pair $pairno)</h2>\n";
		}
	} else {
		if ($relay) {
			print "<h2>Modify Port $portno (No TCO)</h2>\n";
		} else {
			print "<h2>Modify Port $portno (Cable A, Pair $portno)</h2>\n";
		}
	}
	
	$ext = $db->getOne("select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$port'");
	if ($ext == "") {
		showextpage('', 'Plant Phone');
		print "<center><button id='addextbutton' onClick='addext()'>Add Ext</button></center>";
	} else {
		$res=core_users_get($ext);
		if (!isset($res['name'])) { ?>
			<h3>FreePBX Error</h3>
			<span>This extension <strong>does not exist</strong> in FreePBX. It's probably been deleted.
			You may either remove this from provisioning, or re-create it.</span>
			<br /><br /><center><button id='xxremove' onClick='doremoveext(<?php echo $ext?>)'>Remove</button>&nbsp;&nbsp;
			<button id='create' onClick='addext()'>Create</button></center>
		<?php
		exit;
		}
	# We're here because we've clicked on an Exten that exists, and is valid in 
	# FreePBX. Lets do some stuff.
	showextpage($ext, $res['name'], 'AU');
	print "<center><button id='modext' onClick='modext()'>Modify</button>&nbsp;&nbsp;";
	print "<button id='xxremove' onClick='removeext()'>Remove</button></center>";
	print '<script>$("#cidname").focus();</script>';
	}
}

function update_span($sno, $xpd) {
	print display_ports($sno, $xpd, "99");
}
	
function show_ports($sno, $xpd) {
	global $db;

	$ports = $db->getOne("select ports from provis_dahdi_spans where `serial`='$sno' and `xpd`='$xpd'");
	if ($ports > 14) {
		print "Invalid. Not suitble for PRI";
		exit;
	}

	print "<p></p><table cellspacing=0>\n<tr>\n";
	# Do the top row first, 1,3,5,7(,9,11,13)
	for ($x=1; $x <= $ports; $x=$x+2) {
		$sql = "select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$x'";
		$res = $db->getOne($sql);
		if ($res == "") {
			$str = "Empty";
		} else {
			$str = $res;
		}
		print "<td class='extports' id='port_$x'><span class='bg'>Port $x</span><span class='ext' id='port$x' data-sno='$sno' data-xpd='$xpd' data-portno='$x'>$str</span></td>\n";
	}
	print "</tr><tr>\n";
	# Now the second row
	for ($x=2; $x <= $ports; $x=$x+2) {
		$sql = "select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$x'";
		$res = $db->getOne($sql);
		if ($res == "") {
			$str = "Empty";
		} else {
			$str = $res;
		}
		print "<td class='extports' id='port_$x'><span class='bg'>Port $x</span><span class='ext' id='port$x' data-sno='$sno' data-xpd='$xpd' data-portno='$x'>$str</span></td>\n";
	}
	print "</tr></table>\n";
}

function addext($ext, $sno, $xpd, $port, $tone, $cidname) {
	global $db;

	# Adding an Extension. Yay. 
	# Rule 1: Sanity check ALL THE THINGS.
	if ($ext === '') {
		print "Extension can't be blank\n";
		print '<script>$("#extno").focus();</script>';
		exit;
	}
	if (!is_numeric($ext)) {
		print "Extension '$ext' is not numeric\n";
		print '<script>$("#extno").focus();$("#extno").select();</script>';
		exit;
	}
	if ($ext > 9999) {
		print "Extension '$ext' too long\n";
		exit;
	}
	if (strlen($cidname) > 20) {
		print "CallerID Name too long";
		exit;
	}
	if ($cidname === '') {
		$cidname = "Plant Phone\n";
	}
	if ($sno == '' || $xpd == '' || $port == '') {
		print "<h2>Missing stuff in the POST</h2><p>This is an ajax error</p><p>Tell Rob</p>";
		print "<p>I have serial='$sno', xpd='$xpd', port='$port'</p>";
		exit;
	}
	# Now. Does this ext already exist?
	$results = $db->getRow("SELECT extension,name FROM users where extension='$ext'", DB_FETCHROW_ASSOC);
	if (isset($results[0])) {
		print "Extension $ext already assigned to ".$results[1];
		exit;
	} 
	# Nifty. Add it to our database.
	$sql = "INSERT INTO `provis_dahdi_ports` (serial, xpd, portno, ext, tone) values ('$sno', '$xpd', '$port', '$ext', '$tone')";
	$res = $db->query($sql);
	if (PEAR::isError($res)) {
		print "Unable to update database:<br />SQL: $sql<br /><pre>".$res->getMessage()."</pre>";
		exit;
	}
	# OK. Now, lets figure out what ACTUAL dahdi port this thing is.
	# What span number  is this?
	$spanno = $db->getone("select span from provis_dahdi_spans where `serial`='$sno' and `xpd`='$xpd'");
	# And how many ports are before this span? 
	$baseno = $db->getOne("select SUM(ports) from provis_dahdi_spans where `span` < $spanno");
	$dahdi = $baseno + $port;
	# Big array of vars that FreePBX cares about. Half of this stuff probably isn't needed.
	$vars = array (
		'display' => 'extensions', 'type' => 'setup', 'action' => 'add', 'extdisplay' => '', 'extension' => $ext, 'name' => $cidname,
		'cid_masquerade' => '', 'sipname' => '', 'outboundcid' => '', 'ringtimer' => 0, 'cfringtimer' => 0, 'concurrency_limit' => 0,
		'callwaiting' => 'enabled', 'answermode' => 'disabled', 'call_screen' => 0, 'pinless' => 'disabled', 'emergency_cid' => '', 
		'tech' => 'dahdi', 'hardware' => 'generic', 'qnostate' => 'usestate', 'newdid_name' => '', 'newdid' => '', 'newdidcid' => '',
		'devinfo_secret_origional' => '', 'devinfo_dtmfmode' => 'rfc2833', 'devinfo_canreinvite' => 'no',
		'devinfo_context' => 'from-internal', 'devinfo_host' => 'dynamic', 'devinfo_trustrpid' => 'yes', 'devinfo_sendrpid' => 'no', 
		'devinfo_type' => 'peer', 'devinfo_nat' => 'no', 'devinfo_port' => '5060', 'devinfo_qualify' => 'yes', 'devinfo_qualifyfreq' => '60',
		'devinfo_transport' => 'udp', 'devinfo_encryption' => 'no', 'devinfo_callgroup' => '', 'devinfo_pickupgroup' => '',
		'devinfo_disallow' => '', 'devinfo_allow' => '', 'devinfo_dial' => '', 'devinfo_accountcode' => '', 'devinfo_mailbox' => $ext.'@default',
		'devinfo_vmexten' => '', 'devinfo_deny' => '0.0.0.0/0.0.0.0', 'devinfo_permit' => '0.0.0.0/0.0.0.0', 'noanswer_dest' => 'goto0', 
		'busy_dest' => 'goto1', 'chanunavail_dest' => 'goto2', 'dictenabled' => 'disabled', 'dictformat' => 'ogg', 'dictemail' => '',
		'langcode' => '', 'record_in' => 'Adhoc', 'record_out' => 'Adhoc', 'email' => $email, 'vm' => 'disabled', 
	);
	$vars['devinfo_dial']="DAHDI/$dahdi";
	# And FreePBX Also wants them to be in $_REQUEST, too.
	$_REQUEST=$vars;
	core_users_add($vars);
	core_devices_add($ext, 'dahdi', $vars['devinfo_dial'], 'fixed', $ext, $cidname);
	print "Sucessfully Assigned $ext to DAHDI/$dahdi<br />\n";
	#print '<script>${"#addext").html("Close");${"#addext").unbind();$("#addext").bind("click", function() { ${"#overlay").close() });</script>';
	print '<script>$("#addextbutton").html("Close"); $("#addextbutton").attr({onClick: ""});$("#addextbutton").bind("click", function() {$("#content").overlay().close();});</script>';
} 

function removeext($sno, $xpd, $port) {
	global $db;

	$ext = $db->getOne("select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$port'");
	print "<h2>Removing Extension</h2>\n";
	print "<p><center>Are you sure you wish to remove extension $ext?</center></p>\n";
	print "<center><button id='modext' onClick=\"$('#content').overlay().close()\">No</button>&nbsp;&nbsp;";
	print "<button id='yesbutton' onClick='doremoveext($ext)'>Yes</button></center>";
	print '<script>$("#modext").focus();</script>';
}

# core_devices_add($deviceid,$tech,$devinfo_dial,$devicetype,$deviceuser,$description,$emergency_cid,true);



function showextpage($ext='', $name='', $tone='au') {
	print "<span class='left'>CallerID Name</span>\n";
	print "<span class='right'><input id='cidname' type=text size=15 value='$name'></span><br />\n";
	print "<span class='left'>Extension</span>\n";
	print "<span class='right'><input id='extno' type=text size=4 value='$ext'></span><br />\n";
	print "<span class='left'>Dial Tone</span>\n";
	print "<span class='right'>\n";
	foreach (array('au' => 'Au', 'xx' => 'Loud', 'yy' => 'Fax') as $t => $v) {
		if ($tone == $t) {
			$selected = 'checked';
		} else {
			$selected = '';
		}
		print "<input type='radio' name='tonezone' $selected value='$t'>$v</input>\n";
	}
	print "</span><p id='addstat'>&nbsp;</p>\n";
}

function delext($ext) {
	global $db;

	core_users_del($ext);
	core_devices_del($ext);
	$sql = "delete from provis_dahdi_ports where `ext`='$ext'";
	$res = $db->query($sql);
	if (PEAR::isError($res)) {
		print "Unable to update database:<br />SQL: $sql<br /><pre>".$res->getMessage()."</pre>";
		exit;
	}
	print "<script>$('#content').overlay().close();</script>";
}


function r($str) {
	return rawurlencode($str); 
}

function blinkoff($ser) {
	blink($ser, 'off');
}

function blinkon($ser) {
	blink($ser, 'on');
}

function blink($ser, $mode) {
	if ($mode == 'on') {
		$cmd = 'Enabling';
		$action = 'on';
	} else {
		$cmd = 'Disabling';
		$action = 'off';
	}
	print "<H2>$cmd 'blink' on $ser</h2>";
	$r = exec("xpp_blink $action label $ser 2>&1", $output, $retvar);
	if ($retvar === 127) {
		print "<p class='warning'>Error: Unable to run 'xpp_blink'. Is it installed?</p>";
		exit;
	}
	print "<p>Done. xpp_blink returned $retvar</p><p><pre>$r</pre></p>";
}
