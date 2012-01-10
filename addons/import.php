<?php

$bootstrap_settings['freepbx_auth'] = false;
include('/etc/freepbx.conf');

$file = file_get_contents('accts.csv');
$arr = explode("\n", $file);
foreach ($arr as $line) {
	$t = explode(',', $line);
	
	$ext = $t[0];
	$cidname = $t[1];
	$secret = $t[2];
	$vmpin = $t[3];

	print "I have $ext\n";
	$vars = array (
	'display' => 'extensions', 'type' => 'setup', 'action' => 'add', 'extdisplay' => '', 'extension' => $ext, 'name' => $cidname,
	'cid_masquerade' => '', 'sipname' => '', 'ringtimer' => 0, 'cfringtimer' => 0, 'concurrency_limit' => 0,
	'callwaiting' => 'enabled', 'answermode' => 'disabled', 'call_screen' => 0, 'pinless' => 'disabled', 'emergency_cid' => '',
	'tech' => 'sip', 'hardware' => 'generic', 'qnostate' => 'usestate', 'newdidcid' => '',
	'devinfo_secret_origional' => '', 'devinfo_dtmfmode' => 'rfc2833', 'devinfo_canreinvite' => 'no',
	'devinfo_context' => 'from-internal', 'devinfo_host' => 'dynamic', 'devinfo_trustrpid' => 'yes', 'devinfo_sendrpid' => 'no',
	'devinfo_type' => 'peer', 'devinfo_nat' => 'no', 'devinfo_port' => '5060', 'devinfo_qualify' => 'yes', 'devinfo_qualifyfreq' => '60',
	'devinfo_transport' => 'udp', 'devinfo_encryption' => 'no', 'devinfo_callgroup' => '', 'devinfo_pickupgroup' => '',
	'devinfo_disallow' => '', 'devinfo_allow' => '', 'devinfo_dial' => '', 'devinfo_accountcode' => '', 'devinfo_mailbox' => $ext.'@default',
	'devinfo_vmexten' => '', 'devinfo_deny' => '0.0.0.0/0.0.0.0', 'devinfo_permit' => '0.0.0.0/0.0.0.0', 'noanswer_dest' => 'goto0',
	'busy_dest' => 'goto1', 'chanunavail_dest' => 'goto2', 'dictenabled' => 'disabled', 'dictformat' => 'ogg', 'dictemail' => '',
	'langcode' => '', 'record_in' => 'Adhoc', 'record_out' => 'Adhoc', 'email' => '', 'vm' => 'enabled',
	);
	$vars['devinfo_dial']="SIP/$ext";

        if ($vm == 'yes') {
                $vm = array (
                        'vm' => 'enabled',
                        'mailbox' => $ext,
                        'devinfo_voicemail' => 'default',
                        'devinfo_mailbox' => $ext.'@default',
                        'vmpwd' => $vmpin,
                        'attach' => 'attach=no',
                        'saycid' => 'saycid=yes',
                        'envelope' => 'envelope=no',
                        'delete' => 'delete=no',
                        'pager' => '',
                        'vmcontext' => 'default',
                );
                $vars = array_merge($vars, $vm);
        }

	# And FreePBX Also wants them to be in $_REQUEST, too.
	$_REQUEST=$vars;
	core_users_add($vars);
	core_devices_add($ext, 'sip', $vars['devinfo_dial'], 'fixed', $ext, $cidname);
	# Create voicemail
	if ($vars['vm'] === 'enabled') {
		voicemail_mailbox_add($ext, $vars);
		# Seriously. FreePBX is setting 'vm' to be 'novm' every time. You can't add an exten with
		# voicemail enabled. This is broken. Workaround below.
		$sql="update users set voicemail='default' where extension='$ext'";
		$this->db->query($sql);
		global $astman;
		$astman->database_put("AMPUSER",$ext."/voicemail", 'default');
	}
}

