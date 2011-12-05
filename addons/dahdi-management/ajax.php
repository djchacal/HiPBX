<?php 
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 23 Jul 1971 00:00:00 GMT");
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
$routes = $_REQUEST['routes']; /* Is an array, escaped in update_route_perms */
$cidnum = mysql_real_escape_string($_REQUEST['cid']);
$didnum = mysql_real_escape_string($_REQUEST['did']);

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
		addext($ext, $sno, $xpd, $port, $tone, $cidname, $routes, $cidnum, $didnum);
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
	case "modify":
		modify($ext, $sno, $xpd, $port, $tone, $cidname, $routes, $cidnum, $didnum);
		break;
	case "resync":
		resync();
		break;
	case "doresync":
		doresync();
		break;
}

function ajax_ext($sno, $xpd, $port, $msg='') {
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
	
	$ext = $db->getRow("select ext,tone from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$port'", DB_FETCHROW_ASSOC);
	if ($ext[0] == "") {
		showextpage('', 'Plant Phone', 'au', $msg);
		print "<center><button id='addextbutton' onClick='addext()'>Add Ext</button></center><p></p>";
	} else {
		$res=core_users_get($ext[0]);
		if (!isset($res['name'])) { ?>
			<h3>FreePBX Error</h3>
			<span>This extension <strong>does not exist</strong> in FreePBX. It's probably been deleted.
			You may either remove this from provisioning, or re-create it.</span>
			<br /><br /><center><button id='remove_button' onClick='doremoveext(<?php echo $ext[0]?>)'>Remove</button>&nbsp;&nbsp;
			<button id='create' onClick='addext()'>Create</button></center>
		<?php
		exit;
		}
	# We're here because we've clicked on an Exten that exists, and is valid in 
	# FreePBX. Lets do some stuff.
	showextpage($ext[0], $res['name'], $ext[1], $msg);
	print "<center><button id='modext_button' onClick='modext()'>Modify</button>&nbsp;&nbsp;";
	print "<button id='remove_button' onClick='removeext()'>Remove</button></center>";
	print '<script>$("#cidname").focus();</script><p></p>';
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

	print "<div style='width:722px; text-align: center;'><center><table cellspacing=0>\n<tr>\n";
	# Do the top row first, 1,3,5,7(,9,11,13)
	for ($x=1; $x <= $ports; $x=$x+2) {
		$sql = "select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$x'";
		$res = $db->getOne($sql);
		if ($res == "") {
			$str = "Empty";
		} else {
			$str = $res;
		}
		print "<td class='extports' id='port_$x'><div class='bg'>Port $x</div><div class='ext' id='port$x' data-sno='$sno' data-xpd='$xpd' data-portno='$x'>$str</div></td>\n";
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
		print "<td class='extports' id='port_$x'><div class='bg'>Port $x</div><div class='ext' id='port$x' data-sno='$sno' data-xpd='$xpd' data-portno='$x'>$str</div></td>\n";
	}
	print "</tr></table></center></div>\n";
}

function addext($ext, $sno, $xpd, $port, $tone, $cidname, $routes, $cidnum, $didnum) {
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
	if (!is_numeric($cidnum)) {
		print "CID Number '$cidnum' is not numeric\n";
		print '<script>$("#cidnum").focus();$("#cidnum").select();</script>';
		exit;
	}
	if (!is_numeric($didnum)) {
		print "DID Number '$didnum' is not numeric\n";
		print '<script>$("#didnum").focus();$("#didnum").select();</script>';
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
		'cid_masquerade' => '', 'sipname' => '', 'ringtimer' => 0, 'cfringtimer' => 0, 'concurrency_limit' => 0,
		'callwaiting' => 'enabled', 'answermode' => 'disabled', 'call_screen' => 0, 'pinless' => 'disabled', 'emergency_cid' => '', 
		'tech' => 'dahdi', 'hardware' => 'generic', 'qnostate' => 'usestate', 'newdidcid' => '',
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
	$vars['outboundcid']=$cidnum;
	$vars['newdid']=$didnum;
	$vars['newdid_name']="$ext DID";
	# And FreePBX Also wants them to be in $_REQUEST, too.
	$_REQUEST=$vars;
	core_users_add($vars);
	core_devices_add($ext, 'dahdi', $vars['devinfo_dial'], 'fixed', $ext, $cidname);
	print "Sucessfully Assigned $ext to DAHDI/$dahdi<br />\n";
	if (function_exists('rp_get_routes') && is_array($routes)) {
		update_routeperms($ext, $routes);
	}
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



function showextpage($ext='', $name='', $tone='au', $msg) {
	print "<span class='left'>CallerID Name</span>\n";
	print "<span class='right'><input id='cidname' type=text size=15 value='$name'></span><br />\n";
	print "<span class='left'>Extension</span>\n";
	print "<span class='right'><input id='extno' type=text size=4 value='$ext'></span><br />\n";
	showdid($ext);
	showcid($ext);
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
	print "</span>";
	if (function_exists('rp_get_routes') && file_exists('/etc/hipbx.d/provis.conf')) {
		# We have routepermissions and hipbx!
		print_routeperms($ext);
	}
	print "</span><p id='addstat'>$msg</p>\n";
}

function delext($ext) {
	global $db;
        global $active_modules;

	core_users_del($ext);
	core_devices_del($ext);
	$sql = "delete from provis_dahdi_ports where `ext`='$ext'";
	$res = $db->query($sql);
	if (PEAR::isError($res)) {
		print "Unable to update database:<br />SQL: $sql<br /><pre>".$res->getMessage()."</pre>";
		exit;
	}
	rp_purge_ext($ext);

	# Remove DID if there is one
	$arr=framework_check_destination_usage(true, $active_modules);

	foreach (array_keys($arr['core']) as $did) {
		if ($arr['core'][$did]['dest'] == "from-did-direct,$ext,1") {
			preg_match('/Inbound.+\((\d+)\/(.*)\)/', $arr['core'][$did]['description'], $matches);
			core_did_del($matches[1],$matches[2]);
			fax_delete_incoming($matches[1]);
			break;
		}
	}


	print "<script>$('#content').overlay().close();</script>";
}

function blinkoff($ser) {
	blink($ser, 'off');
}

function blinkon($ser) {
	blink($ser, 'on');
}

function blink($ser, $mode) {

	$xpp = "/usr/sbin/xpp_blink";

	if ($mode == 'on') {
		$cmd = 'Enabling';
		$action = 'on';
	} else {
		$cmd = 'Disabling';
		$action = 'off';
	}
	print "<H2>$cmd 'blink' on $ser</h2>";
	if (!file_exists($xpp)) {
		print "<p class='warning'>Error: file $xpp does not exist, or is not accessable</p>";
		print "<p></p><p><center><button onClick='$(\"#content\").overlay().close()'>Close</button></center></p>";
		exit;
	}
	$cmd = "sudo $xpp $action label usb:$ser 2>&1";
	$r = exec($cmd, $output, $retvar);
	# Is sudo set up correctly?
	if (!strstr($r, 'sudo:') === false) {
		$processUser = posix_getpwuid(posix_geteuid());
		print "<p class='warning'>Error: SUDO is not set up correctly. Ensure that '$xpp' is a command available to the user ".$processUser['name']." in /etc/sudoers</p><p>$r</p>";
		print "<p></p><p><center><button onClick='$(\"#content\").overlay().close()'>Close</button></center></p>";
		exit;
	}
	if ($retvar === 127) {
		print "<p class='warning'>Error: Unable to run '$cmd'. Is it installed?</p>";
		print "<p></p><p><center><button onClick='$(\"#content\").overlay().close()'>Close</button></center></p>";
		exit;
	}
	print "<p>Done. xpp_blink returned $retvar</p><p><pre>$r</pre></p>";
	print "<p></p><p><center><button onClick='$(\"#content\").overlay().close()'>Close</button></center></p>";
}

function modify($ext, $sno, $xpd, $port, $tone, $cidname, $routes, $cidnum, $didnum) {
	global $db;

	# OK, what's being changed? Is someone trying to be clever and change the Ext?
	$myext = $db->getRow("select ext,tone from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$port'", DB_FETCHROW_ASSOC);
	if ($myext[0] !== $ext) {
		ajax_ext($sno, $xpd, $port, 'You can not change Extension numbers.');
		exit;
	} 

	print "<input type='hidden' id='modifydata' data-ext='$ext' data-cidname='$cidname' data-tone='$tone'></input>\n";
	print "<H2>Modifying $sno/$xpd/$port</h2>";
	# How about the name, is that being changed?
	$res=core_users_get($ext);
	if (!isset($res['name'])) {
		print "<span class='left'>User missing from FreePBX</span>\n";
	} else {
		if ($res['name'] != $cidname) {
			print "<span class='left'>Name Changed:</span><span class='right'>".$res['name']." -> $cidname</span><br />\n";
		} else {
			print "<span class='left'>CID Name unchanged.</span>\n";
		}
	}
	
	# Dialtone?
	if ($myext[1] !== $tone) {
		print "<span class='left'>Dialtone Changed:</span><span class='right'>$myext[1] -> $tone</span>\n";
	} else {
		print "<span class='left'>Dialtone unchanged.</span><br />\n";
	}

	# Route Permissions..
	if (function_exists('rp_get_routes') && is_array($routes)) {
		update_routeperms($ext, $routes);
		print "<span class='left'>Routes Updated</span><br />";
	}
		
	print "<center><button id='modext' onClick=\"$('#content').overlay().close()\">Done</button>&nbsp;&nbsp;";
}

function print_routeperms($ext) {
	$pconf= @parse_ini_file('/etc/hipbx.d/provis.conf', false, INI_SCANNER_RAW);
	print "<span class='both'>Route Permissions (<a href='#' onClick='rpshowhide()'>Show/Hide</a>)</span>\n";
	$routes = rp_get_routes();
	if ($ext === '') {
		# Do we have default route permissions in from provis?
		if (isset($pconf['ROUTEPERMISSIONS']) && is_array($pconf['ROUTEPERMISSIONS'])) {
			# OK, So our permissions are default!
			foreach ($pconf['ROUTEPERMISSIONS'] as $trunk=>$val) {
				$arr=preg_split('/=/', str_replace(array('"', "'"), null, $val));
				$p[$arr[0]]=$arr[1];
			}
			foreach ($routes as $r) {
				if ($p[$r] == 'NO') {
					$perms[$r]='';
				} else {
					$perms[$r]='checked';
				}
			}
		} else {
			# No Defaults. Everything OK!
			foreach ($routes as $r) {
				$perms[$r]='checked';
			}
		}
	} else {
		# We have an extension. Lets see what this baby's got.
		foreach ($routes as $r) {
			if (rp_get_perm($ext, $r) == 'NO') {
				$perms[$r]='';
			} else {
				$perms[$r]='checked';
			}
		}
	}
	print "<div id='routediv'>";
	$loc = 'left';
	foreach ($perms as $key => $val) {
		# OMG a lot of array iterations in this function..
		print "<span class='$loc'><input type='checkbox' name='routperms' value='$key' $val>$key</input></span>";
		if ($loc == 'left') { 
			$loc = 'right';
		} else {
			$loc = 'left';
		}
	}
	print "</div>";
	
}

function update_routeperms($ext, $route) {
	global $db;
	
	# Everything that's present in $route is ENABLED, everything that's NOT present is DISABLED.
	foreach ($route as $id => $val) {
		$allowed[$val]=true;
	}

	# Grab all the routes on the system
	$routes = rp_get_routes();

	# Purge the current permissions..
	rp_purge_ext($ext);

	# And insert them back in.
	foreach ($routes as $r) {
		
		$r = mysql_real_escape_string($r);

		if ($allowed[$r]) {
			$db->query("INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$ext', '$r', 'YES')");
		} else {
			$db->query("INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$ext', '$r', 'NO')");
		}
	}
}

function doresync() {
	$cmd = "/var/www/html/dahdi/parse.pl 2>&1";
	$r = exec($cmd, $output, $retvar);
	if ($r === "sudo: no tty present and no askpass program specified") {
		print "<h2>System Error</h2>";
		print "<p class='warning'>Error: SUDO is not set up correctly. Ensure that /usr/sbin/dahdi_hardware is a command available ";
		print "to the user ".$processUser['name']." in /etc/sudoers</p><p>$r</p>";
		print "<p></p><p><center><button onClick='$(\"#content\").overlay().close()'>Close</button></center></p>";
		exit;
	}

	print "<h2>Astribanks Imported</h2>";
	print "<p class='warning'>Astribanks have been imported. The output of the import program is below</p>";
	print "<pre>$r</pre>\n";
	print "<p></p><p><center><button onClick='location.reload(true)'>Close</button></center></p>";
}

function resync() {
	print "<h2>Resync Astribanks</h2>\n";
	print "<p class='warning'>This command will update the database with the current status of the \n";
	print "hardware connected to this machine. You would use this after a hardware change, or in \n";
	print " case of a failover to the other node that is not automatically detected. This takes \n";
	print "approx 2-3 seconds per Astribank that's connected. Please be patient.\n";
	print "</p>";
	print "<p></p><p><center>";
	print "<button onClick='doresync()'>Resync</button>";
	print "<button onClick='$(\"#content\").overlay().close()'>Close</button>";
	print "</center></p>";
}

function showdid($ext) {
	global $db;

	# Is this a HiPBX machine?
	if (file_exists('/etc/hipbx.d/provis.conf')) { 
		# Cool. Lets grab the provisioner...
		$pconf= @parse_ini_file('/etc/hipbx.d/provis.conf', false, INI_SCANNER_RAW);
		# Is there a DID prefix?
		if (isset($pconf['DIDPREFIX'])) { 
			# Awesome. Hooks into extno
			print "<script>$('#extno').keyup(function(){updatedid()});</script>";
			$prefix=$pconf['DIDPREFIX'];
		} else {
			$prefix="";
		}
	} else {
		$prefix="";
	}

	$did = getdid($ext);

	# If this is a new ext, set the default now.
	if ($ext == '') { $did = $prefix; }

	# Print our DID stuff
	print "<span class='left'>DID Number</span>\n";
	print "<span class='right'><input id='didnum' type=text size=10 data-did='$prefix' value='$did' ></span><br />\n";
}

function showcid($ext) {
	global $db;

	# Is this a HiPBX machine?
	if (file_exists('/etc/hipbx.d/provis.conf')) { 
		# Cool. Lets grab the provisioner...
		$pconf= @parse_ini_file('/etc/hipbx.d/provis.conf', false, INI_SCANNER_RAW);
		# Is there a CID prefix or CIDSET??
		if (isset($pconf['CIDPREFIX']) || isset($pconf['CIDSET'])) { 
			# If we're a SET, then we don't need to hook. Just display.
			if (isset($pconf['CIDSET'])) {
				$defaultcid=$pconf['CIDSET'];
			} else {
				# We're a prefix. Need to hook.
				print "<script>$('#extno').keyup(function(){updatecid()});</script>";
				$defaultcid=$pconf['CIDPREFIX'];
			}
		}
	} else {
		$defaultcid="";
	}
	

	# Grab our current CID.
	$cid = getcid($ext);

	# If this is a new ext, set the default now.
	if ($ext == '') { $cid = $defaultcid; }

	print "<span class='left'>CID Number</span>\n";
	print "<span class='right'><input id='cidnum' type=text size=10 data-cid='$defaultcid' value='$cid'></span><br />\n";


}

function getcid($ext) {
	global $db;

	# What's our current CID?
	$cid = $db->getOne("select outboundcid from users where extension='$ext'");
	return $cid;
}

function getdid($ext) {
	global $db;

	# What's our existing DID?
	$did = $db->getOne("select extension from incoming where destination='from-did-direct,$ext,1'");
	return $did;
}

