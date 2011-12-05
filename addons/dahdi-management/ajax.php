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
