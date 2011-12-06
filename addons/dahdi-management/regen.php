<?php 
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

global $db;

include("include.php");

# Firstly, we need to run the parse program.
#doresync();

# Firstly, lets find all the Astribanks that we know about.
$sql='select distinct(serial) from provis_dahdi_ports';
$res = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
foreach($res as $row) {
	print "Astribank ".$row['serial']."\n<ul>\n";
	# Now, lets grab all the XPDs we know about on that astribank.
	$sql='select distinct(xpd) from provis_dahdi_ports where serial="'.$row['serial'].'"';
	$xpds=$db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
	foreach($xpds as $xpd) {
		print "    <li>XPD ".$xpd['xpd']." ";
		$sql='select * from provis_dahdi_ports where serial="'.$row['serial'].'" and xpd="'.$xpd['xpd'].'"';
		$ports=$db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
		$count=0;
		foreach ($ports as $port) {
			if (update_port($port['serial'], $port['xpd'], $port['portno'], $port['ext'])) {
				$count++;
			}
		}
		print "($count updated)</li>\n";
	}
	print "</ul>\n";
}


function update_port($sno, $xpd, $portno, $ext) {
	global $db;
	global $astman;

	# Sometimes "xpd='00'" becomes "xpd=0".
	if ($xpd==0) {
		$xpd='00';
	}
	# Right, lets start by finding out what the DAHDI portnum is of this device.
	# What SPAN are we on?
        $spanno = $db->getone("select span from provis_dahdi_spans where `serial`='$sno' and `xpd`='$xpd'");
	# And what's the real port number?
	$baseno = $db->getOne("select SUM(ports) from provis_dahdi_spans where `span` < $spanno");
	$dahdi = $baseno + $portno;

	$dial="DAHDI/$dahdi";

	# Now we do some nasty hacking on FreePBX to manually update the ID.
	# Firstly, update astdb
	$astman->database_put("DEVICE",$ext."/dial",$dial);
	# Now, update the dahdi table..
	$db->query("update dahdi set data='$dial' where id='$ext' and keyworld='dial'");
	$db->query("update dahdi set data='$dahdi' where id='$ext' and keyworld='channel'");
	# And the devices table.
	$db->query("update devices set dial='$dial' where id='$ext' and tech='dahdi'");
	return true;
}
