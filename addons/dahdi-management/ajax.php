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
$action = $_REQUEST['action'];
#$sno = 'X1056351';
#$xpd = '00';
#$port = '1';

switch ($action) {
	case "ext":
		ajax_ext($sno, $xpd, $port);
		break;
	case "span":
		update_span($sno, $xpd);
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
		print "<span class='left'>CallerID Name</span>\n";
		print "<span class='right'><input id='cidname' type=text size=15></span><br />\n";
		print "<span class='left'>Extension</span>\n";
		print "<span class='right'><input id='extno' type=text size=4></span><br />\n";
		print "<span class='left'>Dial Tone</span>\n";
		print "<span class='right'>\n";
		print "  <input type='radio' name='tonezone' value='AU' checked >Au</input>\n";
		print "  <input type='radio' name='tonezone' value='XX'>Loud</input>\n";
		print "  <input type='radio' name='tonezone' value='YY'>Fax</input>\n";
		print "</span><p id='addstat'>&nbsp;</p>\n";
		print "<center><button id='addext' name='addext'>Add Ext</button></center>";
		#print "<button id='delext name='delext'>Del Ext</button>'</center>\n";
		#print "<center><button id='addext' name='addext'>Add Ext</button>&nbsp;&nbsp";
		#print "<button id='delext name='delext'>Del Ext</button>'</center>\n";
		exit;
	} else {
		$res=core_users_get($ext);
		if (!isset($res['name'])) { ?>
			<h3>FreePBX Error</h3>
			<span>This extension <strong>does not exist</strong> in FreePBX. It's probably been deleted.
			You may either remove this from provisioning, or re-create it.</span>
			<br /><br /><center><button id='remove'>Remove</button>&nbsp;&nbsp;<button id='create'>Create</button></center>
			<script>
			$('#remove').bind('click', function() {
				alert($('#astribank').data('sno'));
				var query= {
					sno:  $('#astribank').data('sno'),
					xpd:  $('#astribank').attr('data-xpd'),
					port: $('#astribank').data('port'),
					action: 'remove',
				};
				$.post("doext.php", query, function(data) { $("#ctext").html(data); });

			});
			</script>
		<?php
		exit;
		}
	print "Port configured to be $ext, owned by ".$res['name'];
	}
}

function update_span($sno, $xpd) {
	print display_ports($sno, $xpd, "99");
}
	
