<?php 
/* $Id:$ */

// Original Release by Rob Thomas (xrobau@gmail.com)
// Copyright Rob Thomas (2009)
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$tabindex = 0;
// What are we doing..
isset($_REQUEST['action'])?$action = $_REQUEST['action']:$action='';

// Where we are
$dispnum = "routepermissions"; //used for switch on config.php


?>

</div> <!-- end content div -->

<div class="content">
<?php
	global $dispnum;
?>
	<h2 id='title'><?php echo _("Route Permissions") ?></h2></td>
<?php 
	// Has something been submitted?
	if(isset($_POST['action'])) {
		// Figure out which button has been pushed.
		foreach ($_REQUEST as $r=>$val) {
			if (!strncmp($r, "on_", 3)) {
				$route=substr($r,3);
				print "<h4>Route $route set to ALLOW for supplied range</h4>\n";
				rp_allow($route, $_REQUEST["range_$route"]);
			}
			if (!strncmp($r, "off_", 3)) {
				$route=substr($r,4);
				print "<h4>Route $route set to DENY for supplied range</h4>\n";
				rp_deny($route, $_REQUEST["range_$route"]);
			}
			if (!strncmp($r, "redirect_", 8)) {
				$route=substr($r,9);
				$redir=trim($_REQUEST["rp-redir_$route"]);
				// Make sure redirect field is not empty or whitespace only - could have better sanity checking
				if (strlen($redir)) {
					print "<h4>Route $route set to DENY for supplied range using redirect prefix $redir</h4>\n";
					rp_redir($route, $_REQUEST["range_$route"], $redir);
				} else {
					print "<h3><font color=#FF0000>Redirect selected but redirect prefix missing for route $route - no action taken</font></h3>\n";
				}
			}
			if ($r == 'update_dest') {
				$dest = $_REQUEST[$_REQUEST['gotofaildest'].'faildest'];
				$sdest = mysql_real_escape_string($dest);
				sql("DELETE FROM routepermissions WHERE EXTEN='-1'");
				sql("INSERT INTO routepermissions (exten, routename, faildest) VALUES ('-1', 'default', '".$dest."')");
				print "<h4>Default destination changed</h4>\n";
			}
		}
	}
?>
  
	<tr><td colspan=2><span id="instructions">
	<p><h3>Instructions</h3></p>
	<p>This module allows you to block access to certain routes from specified extensions. You can do 
	bulk changes on this page, and you can individually change access to routes on the extension's page.</p>
	<p>Note that Asterisk is incapable of having two identical routes and trying to force calls to use
	the other route if one of them is banned by this module. <b>It will not work.</b> You must have 
	unique outbound routes for the proper selection to work.</p> 
	<p>If you wish to emulate this functionality, you can use the 'Redirect' function. Any number you type 
	in the 'Redirect' range will be PREPENDED to the number dialed, and the call will then be sent through
	the dialplan again. For example:</p>
	<p><ul>
		<li>Route 1: Zap/1 matches 0|.</li>
		<li>Route 2: Sip/Foo matches 1|.</li>
	</ul></p>
	<p>If you wanted to stop extension 100 from using Zap/1 at all, and send all his calls through Sip/Foo, 
	you would need to DENY 100 access to Route1, and create a NEW route, Route3:</p>
	<p><ul><li>Route 3: Sip/Foo matches 9990|.</li></ul></p>
	<p>In the 'Redirect' field, type '999'. When extension 100 dials 0123456, they match Route 1. Route 1 FAILS,
	and then system invisibly changes the number dialed to be 9990123456 (note the '0' he dialled 
	originally is preserved, and you then strip 9990 from the front in Route 3), which matches Route 3 
	and the call is then sent via Sip/Foo.</p>
	<p>Redirect rules are only checked if the route is DENIED.</p>
	<p>You can set a Default Destination if calls are denied. If you wish to use something other than the
	default in a specific instance, you can use a Redirect prefix and a Misc. Application.  Example: set
	the redirect prefix to 000123, then create a Misc. Application and set the Feature Code to <b>_000123.</b>
	(note the underscore at the start and the period at the end of the Feature Code - both are necessary),
	then make the destination of the Misc. Application whatever you wish.</p>
<?php
	echo "<p><h3>"._("Bulk Changes"); echo "</h3></p> ";
	echo "<p>"._("Select a route and press Allow or Deny to set all extensions. If you enter a redirect prefix and click Redirect, the route will automatically be set to DENIED.");
	echo  _("You can enter any normal range - comma or hyphen seperated. For example '123,125,200-300' will select extensions 123, 125 and any extensions between 200 and 300.");
	echo "</p>\n ";
	echo "<p>"._("Note that there is NO UNDO and changes take effect IMMEDIATELY. Don't click Redirect unless you have correct data in both text fields! Be cautious.")."</p>";
	

	echo '<form autocomplete="off" name="edit" action="'.$_SERVER['PHP_SELF'].'" method="post">';
	echo "<input type=\"hidden\" name=\"display\" value=\"{$dispnum}\">\n";
	echo "<input type=\"hidden\" name=\"action\" value=\"edit\">\n";
               
	$routes = rp_get_routes();

	echo "<table>\n";
	foreach ($routes as $r) {
		print "<tr>\n<td>$r</td><td><input type='text' size=15 name='range_$r' value='All' ";
		print "tabindex='".++$tabindex."'></td>\n";
		print "<td><input type='submit' name=on_$r value=Allow></td><td><input type='submit' ";
		print "name=off_$r value=Deny></td>\n";
		print "<td><input type='text' size=15 name='rp-redir_$r' value='' tabindex='".++$tabindex."'>";
		print "</td>\n<td><input type='submit' name='redirect_$r' value='Redirect'></td></tr>\n";
	}
	echo "</table><table>";
	echo '<tr><td colspan="6"><br><h5>'._("Default Destination if denied").':<hr></h5></td></tr>';
	$res=sql("SELECT faildest FROM routepermissions where exten='-1'", "getRow");
	if (isset($res[0])) { 
		echo drawselects($res[0], 'faildest'); 
	} else {
		echo drawselects(0, 'faildest');
	}
	echo '<tr><td><input type="submit" name="update_dest" value="Change Destination"></td></tr>';
	echo "</table>\n";

function rp_allow($route, $range) {
	$extens = rp_get_extens();
	if ($range == "All") {
		foreach ($extens as $r=>$foo) {
			rp_do($route, $r, "YES");
		}
	} else {
		$rangearray = rp_range($range);
		foreach ($rangearray as $r) {
			if ($extens[$r] == "ok") {
				rp_do($route, $r, "YES");
			}
		}
	}
}

function rp_deny($route, $range) {
	$extens = rp_get_extens();
	if ($range == "All") {
		foreach ($extens as $r=>$foo) {
			rp_do($route, $r, "NO");
		}
	} else {
		$rangearray = rp_range($range);
		foreach ($rangearray as $r) {
			if ($extens[$r] == "ok") {
				rp_do($route, $r, "NO");
			}
		}
	}
}

function rp_redir($route, $range, $redir) {
	$extens = rp_get_extens();
	if ($range == "All") {
		foreach ($extens as $r=>$foo) {
			rp_doredir($route, $r, "NO", $redir);
		}
	} else {
		$rangearray = rp_range($range);
		foreach ($rangearray as $r) {
			if ($extens[$r] == "ok") {
				rp_doredir($route, $r, "NO", $redir);
			}
		}
	}
}

function rp_do($route, $ext, $perm) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$Sroute = mysql_real_escape_string($route);
	sql("DELETE FROM routepermissions WHERE exten='$Sext' AND routename='$Sroute'");
	sql("INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$Sext', '$Sroute', '$perm')");
}

function rp_doredir($route, $ext, $perm, $redir) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$Sroute = mysql_real_escape_string($route);
	$Sredir = mysql_real_escape_string($redir);
	sql("DELETE FROM routepermissions WHERE exten='$Sext' AND routename='$Sroute'");
	sql("INSERT INTO routepermissions (exten, routename, allowed, faildest) VALUES ('$Sext', '$Sroute', '$perm', '$Sredir')");
}

function rp_range($range_str) {
	$range_out = array();
	// Strip spaces
	$ranges = explode(",", str_replace(" ", "", $range_str));

	foreach($ranges as $range) {
		if(is_numeric($range)) {
			// Just a number; add it to the list.
			$range_out[] = $range;
			$last_num = $range;
		} else if(is_string($range)) {
			if (preg_match("/(\d+)-(\d+)/", $range, $selection)) {
				$start = $selection[1];
				$end = $selection[2];
	
				if($start > $end) {
					for($i = $start; $i >= $end; $i--) {
						$range_out[] = $i;
					}
				} else {
					for($i = $start; $i <= $end; $i++) {
						$range_out[] = $i;
					}
				}
			}
		}
	}
	return $range_out;
}

function rp_get_extens() {
	global $db;
     	$extens = core_users_list();
	foreach ($extens as $e) {
		$ret[$e[0]]="ok";
	}
	return $ret;
}
?>
