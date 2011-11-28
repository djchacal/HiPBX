<?php 
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}
?>
<?php

global $db;

# Handle commands. Lets see what's been clicked, if anything.

foreach($_REQUEST as $key => $val) {
	switch($val) {
		case "Move Down":
			move_astribank($key, "down");
			break;
		case "Move Up":
			move_astribank($key, "up");
			break;
	}
}
	

$sql = "select serial from provis_dahdi_astribanks_layout order by disporder";
$res = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
# Each Astribank's serial number
?>

<html>
<head>
<title>DAHDI Overview</title>
<script src='jquery.tools.min.js'></script>
<script src='spin.js'></script>
<STYLE type="text/css">
   body { font-family: 'Trebuchet MS', Arial; }
   H1.myclass {border-width: 1; border: solid; text-align: center}
   .click {text-decoration: underline; cursor: pointer}
   .ext {color: blue; cursor: pointer}
   TD {text-align: center}
   P.warning {padding-left: 1em;}
   P#addstat {margin: 0px; text-align: center}
   #port_9,#port_10,#port_11,#port_12,#port_13,#port_14 {background-color: LightGray}
   #olay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #000;
    		filter:alpha(opacity=50); -moz-opacity:0.5; -khtml-opacity: 0.5; opacity: 0.5; z-index: 1000; }
   #content { display:none; width:400px; border:10px solid #666; background-color: #fff; 
		border:10px solid rgba(182, 182, 182, 0.698); -moz-border-radius:8px; -webkit-border-radius:8px;
		z-index: 2000 }
   #content .close { background-image:url(close.png); position:absolute; right:-15px; top:-15px; cursor:pointer;
		height:35px; width:35px; }
   #content h2 { text-align: center; }
   .right { width: 100px; }
   .left { padding-left: 1em; display: inline-block; width: 150px; }

</STYLE>
</head>
<body>

<div id="olay" style="display: none"></div>
<div id="content" style="display: none"><div id="ctext"></div></div>


<form method='post'>

<?php
foreach ($res as $row) {
	print "<table border=1>\n";
	print "<caption> ".$row['serial']."</caption>\n";
	print "<tr>\n";
	# Each Astribank has a number of spans
	$sql = "select * from provis_dahdi_spans where serial='".$row['serial']."' and span > 0 order by xpd";
	$spans = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
	foreach ($spans as $span) {
		print "<td>";
		if ($span['ports'] > 14) {
			print "PRI\n";
			continue;
		}
		# Now, how many extens do we have assigned to these ports?
		$sql = "select * from provis_dahdi_ports where serial='".$row['serial']."' and xpd='".$span['xpd']."' order by ext";
		$exts = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
		print "<div class='header'>XPD: ".$span['xpd']."/Span: ".$span['span']."</div>\n";
		print "<div class='click' data-sno='".$row['serial']."' data-xpd='".$span['xpd']."'>";
		if (count($exts) === 0) {
			print "No Ports Assigned";
		} else {
			print count($exts)." Ports Assigned";
		}
		print "</div></td>\n";
	}
	print "<td><input type='submit' name='".$row['serial']."' value='Move Up'>";
	print "<input type='submit' name='".$row['serial']."' value='Move Down'>";
	print "</td>";
	print "</tr></table><div class='ports' id='".$row['serial']."'></div>";
}

?>
<script>
	$('.click').bind('click', function() {
		var s=$(this).data('sno');
		var x=$(this).attr('data-xpd'); // 00 = 0 if you use '.data'
		$.ajax({
			type: 'POST',
			url: 'ports.php',
			data: 'sno='+s+'&xpd='+x,
			beforeSend: function() {
				$(".ports").hide("fast");
				$("#olay").show();
				$("#olay").spin("large", "white");
			},
			success: function( msg ) {  
				$("#olay").spin(false);
				$("#olay").hide();
				$(".ports").hide('fast');
				$("#"+s).html(msg);
				$("#"+s).show('fast');
				$(".ext").bind('click', function() { 
					modport($(this).data('sno'), $(this).attr('data-xpd'), $(this).data('portno'));
				});
			},

		});
	});
	function modport(ser, xpd, no) {
		$("#olay").show();
		$("#ctext").html("<i>Loading...</i>");
		$("#content").overlay({ 
			top: 160, 
			load: true, 
			mask: { color: '#fff', loadSpeed: 200, opacity: 0.5 },
			onBeforeClose: function() { $("#olay").hide(); },
		});
		$("#content").overlay().load();
		$.get("ext.php", 'sno='+ser+'&xpd='+xpd+'&port='+no, function(data) {
			$("#ctext").html(data);
			$("#addext").bind('click', function() {
				$('input:radio').each(function(){
					if ($(this).attr('checked')) {
						addext(
							$("#cidname").val(), $("#extno").val(), $(this).attr('value') );
					}
				});
			});	
		});
	}

	function addext(cidname, ext, tone) {
		$("#addstat").html("<i>Processing...</i>");
		var query= {
			sno:  $('#astribank').data('sno'),
			xpd:  $('#astribank').attr('data-xpd'),
		  	port: $('#astribank').data('port'),
			ext:  ext,
			cidname: cidname,
			tone: tone,
			action: 'addext',
		};
		$.post("doext.php", query, function(data) {
			$("#addstat").html(data);
		});
	}
</script>

<?php

function move_astribank($ser, $dir) {
	global $db;
	# First, check to see if we're the bottom-most already.
	# Order our query
	if ($dir == "down")  {
		$cmd = " desc "; 
	} else {
		$cmd = "";
	}
	$sql = "select serial from provis_dahdi_astribanks_layout order by disporder $cmd limit 1";
	$res = $db->getOne($sql);
	# It is? User is clicking buttons randomly. Yay.
	if ($res == $ser) {
		return;
	}
	# print "($dir) I have $res\n";

	# Now we need to grab the serial number of this one..
	$sql = "select disporder from provis_dahdi_astribanks_layout where serial='$ser'";
	$myorder = $db->getOne($sql);
	
	# Are we moving it down or up?
	if ($dir == "down") {
		$moveto = $myorder+1;
	} else {
		$moveto = $myorder-1;
	}

	# Who has that ordering, if anyone?
	$sql = "select serial from provis_dahdi_astribanks_layout where disporder='$moveto'";
	$other_astribank = $db->getOne($sql);
	if ($other_astribank == null) {
		# Nothing had that number. Possibly missing an astribank? Anyway, let's just update
		# the display order
		$sql = "update provis_dahdi_astribanks_layout set disporder='$moveto' where serial='$ser'";
		$db->query($sql);
	} else {
		# OK, we have something. Let's swap it with this one.
		$sql = "update provis_dahdi_astribanks_layout set disporder='$myorder' where serial='$other_astribank'";
		$db->query($sql);
		$sql = "update provis_dahdi_astribanks_layout set disporder='$moveto' where serial='$ser'";
		$db->query($sql);
	}
}

