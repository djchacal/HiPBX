<?php 
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

global $db;
include("include.php");

# Grab the xpd and serial number that we're looking at
$sno = $_REQUEST['sno'];
$xpd = $_REQUEST['xpd'];
$port = $_REQUEST['port'];
$ext = $_REQUEST['ext'];
$cidname = $_REQUEST['cidname'];
$tone = $_REQUEST['tone'];
$action = $_REQUEST['action'];

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
		sleep(2);
		delext($ext);
		break;
}

function ajax_ext($sno, $xpd, $port) {
	global $db;

	# Dump everything we need to care about.
	print "<input type='hidden' id='astribank' data-sno='$sno' data-xpd='$xpd' data-port='$port'></input>\n";

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
		showextpage(null, 'Plant Phone', null);
		print "<center><button id='addext' onClick='addext()'>Add Ext</button></center>";
	} else {
		$res=core_users_get($ext);
		if (!isset($res['name'])) { ?>
			<h3>FreePBX Error</h3>
			<span>This extension <strong>does not exist</strong> in FreePBX. It's probably been deleted.
			You may either remove this from provisioning, or re-create it.</span>
			<br /><br /><center><button id='xxremove' onClick='removeext()'>Remove</button>&nbsp;&nbsp;
			<button id='create' onClick='addext()'>Create</button></center>
		<?php
		exit;
		}
	# We're here because we've clicked on an Exten that exists, and is valid in 
	# FreePBX. Lets do some stuff.
	showextpage($ext, $res['name'], 'AU');
	print "<center><button id='modext' onClick='modext()'>Modify</button>&nbsp;&nbsp;";
	print "<button id='xxremove' onClick='removeext()'>Remove</button></center>";
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

	print "<table border=1>\n<tr>\n";
	# Do the top row first, 1,3,5,7(,9,11,13)
	for ($x=1; $x <= $ports; $x=$x+2) {
		$sql = "select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$x'";
		$res = $db->getOne($sql);
		if ($res == "") {
			$str = "Empty";
		} else {
			$str = $res;
		}
		print "<td id='port_$x'><div class='ext' id='port$x' data-sno='$sno' data-xpd='$xpd' data-portno='$x'>$str</div></td>\n";
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
		print "<td id='port_$x'><div class='ext' id='port$x' data-sno='$sno' data-xpd='$xpd' data-portno='$x'>$str</div></td>\n";
	}
	print "</tr></table>\n";
}

function addext($ext, $sno, $xpd, $port, $tone, $cidname) {
	global $db;

	# Adding an Extension. Yay. 
	# Rule 1: Sanity check ALL THE THINGS.
	if ($ext === '') {
		print "Extension can't be blank\n";
		exit;
	}
	if ($cidname === '') {
		$cidname = "Plant Phone\n";
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
	print "Port $port This would be port DAHDI/$dahdi\n";
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
	core_devices_add($ext, 'dahdi', '', 'fixed', $ext, $name);
} 

function removeext($sno, $xpd, $port) {
	global $db;

	$ext = $db->getOne("select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$port'");
	print "<h2>Removing Extension</h2>\n";
	print "<input type='hidden' id='astribank' data-sno='$sno' data-xpd='$xpd' data-port='$port'></input>\n";
	print "<p><center>Are you sure you wish to remove extension $ext?</center></p>\n";
	print "<center><button id='modext'>No</button>&nbsp;&nbsp;";
	print "<button onClick='doremoveext()'>Yes</button></center>";
}

# core_devices_add($deviceid,$tech,$devinfo_dial,$devicetype,$deviceuser,$description,$emergency_cid,true);



function showextpage($ext, $name, $tone) {
	print "<span class='left'>CallerID Name</span>\n";
	print "<span class='right'><input id='cidname' type=text size=15 value='$name'></span><br />\n";
	print "<span class='left'>Extension</span>\n";
	print "<span class='right'><input id='extno' type=text size=4 value='$ext'></span><br />\n";
	print "<span class='left'>Dial Tone</span>\n";
	print "<span class='right'>\n";
	foreach (array('au' => 'Au', 'xx' => 'Loud', 'yy' => 'Fax') as $t => $v) {
		if ($tone === $t) {
			$selected = 'selected';
		} else {
			$selected = '';
		}
		print "<input type='radio' name='tonezone' $selected value='$t'>$v</input>\n";
	}
	print "</span><p id='addstat'>&nbsp;</p>\n";
}

function delext($ext) {
	print "Really deleting ext $ext\n";
	print "<span class='left'>CallerID Name</span>\n";
	print "<span class='right'><input id='cidname' type=text size=15 value='$name'></span><br />\n";
	print "<span class='left'>Extension</span>\n";
	print "<span class='right'><input id='extno' type=text size=4 value='$ext'></span><br />\n";
	print "<span class='left'>Dial Tone</span>\n";
	print "<span class='right'>\n";
}
