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
<script>
  $.fn.spin = function(opts, color) {
		var presets = {
			"tiny": { lines: 8, length: 2, width: 2, radius: 3 },
			"small": { lines: 8, length: 4, width: 3, radius: 5 },
			"large": { lines: 10, length: 8, width: 4, radius: 8 }
		};
		if (Spinner) {
			return this.each(function() {
				var $this = $(this),
					data = $this.data();

				if (data.spinner) {
					data.spinner.stop();
					delete data.spinner;
				}
				if (opts !== false) {
					if (typeof opts === "string") {
						if (opts in presets) {
							opts = presets[opts];
						} else {
							opts = {};
						}
						if (color) {
							opts.color = color;
						}
					}
					data.spinner = new Spinner($.extend({color: $this.css('color')}, opts)).spin(this);
				}
			});
		} else {
			throw "Spinner class not available.";
		}
	};
</script>
<STYLE type="text/css">
   H1.myclass {border-width: 1; border: solid; text-align: center}
   .click {text-decoration: underline; cursor: pointer}
   .ext {color: blue; cursor: pointer}
   TD {text-align: center}
   #port_9,#port_10,#port_11,#port_12,#port_13,#port_14 {background-color: LightGray}
   #overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #000;
    		filter:alpha(opacity=50); -moz-opacity:0.5; -khtml-opacity: 0.5; opacity: 0.5; z-index: 1000; }
   #content { display:none; width:400px; border:10px solid #666; background-color: #fff; 
		border:10px solid rgba(182, 182, 182, 0.698); -moz-border-radius:8px; -webkit-border-radius:8px; }
   #content .close { background-image:url(close.png); position:absolute; right:-15px; top:-15px; cursor:pointer;
		height:35px; width:35px; }

</STYLE>
</head>
<body>

<div id="overlay" style="display: none"></div>

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
		print "<div class='click' sno='".$row['serial']."' xpd='".$span['xpd']."'>";
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
		var s=$(this).attr('sno');
		var x=$(this).attr('xpd');
		$.ajax({
			type: 'POST',
			url: 'ports.php',
			data: 'sno='+s+"&xpd="+x,
			beforeSend: function() {
				$(".ports").hide("fast");
		//		$("#overlay").show();
		//		$("#overlay").spin("large", "white");
			},
			success: function( msg ) {  
			//	$("#overlay").spin(false);
		//		$("#overlay").hide();
				$(".ports").hide('fast');
				$("#"+s).html(msg);
				$("#"+s).show('fast');
				$(".ext").bind('click', function() { 
					modport($(this).attr('sno'), $(this).attr('xpd'), $(this).attr('portno'));
				});
			},

		});
	});
	function modport(ser, xpd, no) {
		$("#portmod").html("I want to mod ser "+ser+", xpd "+xpd+", nbr "+no);
		$("#content").overlay({ top: 260, load: true, mask: { color: '#fff', loadSpeed: 200, opacity: 0.5 },});
		$("#content").overlay().load();
	}
</script>

<?php

function move_astribank($ser, $dir) {
	global $db;
	# First, check to see if we're the bottom-most already.
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

