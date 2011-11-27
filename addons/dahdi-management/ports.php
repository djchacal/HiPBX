<?php 
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}
?>
<?php

global $db;

# Grab the xpd and serial number that we're looking at
$sno = $_REQUEST['sno'];
$xpd = $_REQUEST['xpd'];


$ports = $db->getOne("select ports from provis_dahdi_spans where `serial`='$sno' and `xpd`='$xpd'");
if ($ports > 14) {
	print "Invalid. Not suitble for PRI";
	exit;
}

print "<table border=1>\n";
print "<tr>\n";
# Do the top row first, 1,3,5,7(,9,11,13)
for ($x=1; $x <= $ports; $x=$x+2) {
	$sql = "select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$x'";
	$res = $db->getOne($sql);
	if ($res == "") {
		$str = "Empty";
	} else {
		$str = $res;
	}
	print "<td id='port_$x'><div class='ext' sno='$sno' xpd='$xpd' portno='$x'>$str</div></td>\n";
}
print "</tr>\n";
print "<tr>\n";
# Now the second row
for ($x=2; $x <= $ports; $x=$x+2) {
	$sql = "select ext from provis_dahdi_ports where `serial`='$sno' and `xpd`='$xpd' and `portno`='$x'";
	$res = $db->getOne($sql);
	if ($res == "") {
		$str = "Empty";
	} else {
		$str = $res;
	}
	print "<td id='port_$x'><div class='ext' sno='$sno' xpd='$xpd' portno='$x'>$str</div></td>\n";
}
print "</tr>\n";
$res = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
	

