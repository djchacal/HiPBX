<?php
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

global $db;

# Grab ALL THE THINGS!
$sno = $_REQUEST['sno'];
$xpd = $_REQUEST['xpd'];
$port = $_REQUEST['port'];
$ext = $_REQUEST['ext'];
$cidname = $_REQUEST['cidname'];
$tone = $_REQUEST['tone'];
$action = $_REQUEST['action'];

if ($action == 'addext') {
	# Adding an Extension. Yay. 
	# First. Does this ext already exist?
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
} elseif ($action == 'remove') {
	print "I would remove an extension\n";
}

	
	




# core_devices_add($deviceid,$tech,$devinfo_dial,$devicetype,$deviceuser,$description,$emergency_cid,true);



