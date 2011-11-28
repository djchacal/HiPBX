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
$port = $_REQUEST['port'];
#$sno = 'X1056351';
#$xpd = '00';
#$port = '1';

# Firstly, figure out if we're on ports 9-14 inclusive. If so,
# need to make the user aware this is a relay port, not a phone
# port.

if ($xpd == 0 && ($port > 8 && $port < 15)) {
	print "<h2>WARNING: THIS IS A RELAY PORT</h2>";
	print "<p>This port is NOT ACCESSABLE through the TCO, only through the front of the ";
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
	print "<span class='left'>CallerID Name</span>\n";
	print "<span class='right'><input id='cidname' type=text size=15></span><br />\n";
	print "<span class='left'>Extension</span>\n";
	print "<span class='right'><input id='extno' type=text size=4></span><br />\n";
	print "<span class='left'>Dial Tone</span>\n";
	print "<span class='right'>\n";
	print "  <input type='radio' name='tonezone' value='AU' checked >Au</input>\n";
	print "  <input type='radio' name='tonezone' value='XX'>Loud</input>\n";
	print "  <input type='radio' name='tonezone' value='YY'>Fax</input>\n";
	print "</span><p style='text-align: center' id='addstat'>&nbsp;</p>\n";
	print "<center><button id='addext' name'addext'>Add Ext</button></center>\n";
	exit;
} else {
	print "Port configured to be $ext";
}
exit;
