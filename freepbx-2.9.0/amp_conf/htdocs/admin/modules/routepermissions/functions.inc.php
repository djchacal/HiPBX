<?php
/* $Id$ */

// Original Release by Rob Thomas (xrobau@gmail.com)
// Copyright Rob Thomas (2009)
//
/*  This program is free software: you can redistribute it and/or modify
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



// This MUST be "TheNameOfTheModule_configpageinit" as it's loaded automatically
function routepermissions_configpageinit($pagename) {
	global $currentcomponent;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;
// We only want to hook 'users' or 'extensions' pages.
	if ($pagename != 'users' && $pagename != 'extensions')
		return true;
	// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page.
	if ($tech_hardware != null || $pagename == 'users') {
		rp_applyhooks();
		$currentcomponent->addprocessfunc('rp_configprocess', 5);
	} elseif ($action=="add") {
		// We don't need to display anything on an 'add', but we do need to handle returned data.
		$currentcomponent->addprocessfunc('rp_configprocess', 5);
	} elseif ($extdisplay != '') {
		// We're now viewing an extension, so we need to display _and_ process.
		rp_applyhooks();
		$currentcomponent->addprocessfunc('rp_configprocess', 5);
	}
}

// This MUST be "TheNameOfTheModule_hookGet_config" to be called when a reload (yellow bar) is clicked.
function routepermissions_hookGet_config($engine) {
	global $ext;
	global $version;
	switch($engine) {
		case "asterisk":
			$context="macro-dialout-trunk";
			$ext->splice($context, 's', 1 ,new ext_agi('checkperms.agi'));
			$ext->add($context, 'barred', '', new ext_noop('Route administratively banned for this user.'));
			$ext->add($context, 'reroute', '', new ext_goto('1','${ARG2}','from-internal'));

			$context="macro-dialout-dundi";
			$ext->splice($context, 's', 1 ,new ext_agi('checkperms.agi'));
			$ext->add($context, 'barred', '', new ext_noop('Route administratively banned for this user.'));
			$ext->add($context, 'reroute', '', new ext_goto('1','${ARG2}','from-internal'));

			$context="macro-dialout-enum";
			$ext->splice($context, 's', 0 ,new ext_agi('checkperms.agi'));
			$ext->add($context, 'barred', '', new ext_noop('Route administratively banned for this user.'));
			$ext->add($context, 'reroute', '', new ext_goto('1','${ARG2}','from-internal'));

			// Insert the ROUTENAME into each route
			//
			$names = core_routing_list();
			foreach($names as $name) {
				$context = 'outrt-'.$name['route_id'];
				$routename = $name['name'];
				$routes = core_routing_getroutepatternsbyid($name['route_id']);
				foreach ($routes as $rt) {
					$extension = $rt['match_pattern_prefix'].$rt['match_pattern_pass'];
					// If there are any wildcards in there, add a _ to the start
					//
					if (preg_match("/\.|z|x|\[|\]/i", $extension)) { 
						$extension = "_".$extension; 
					}
					$ext->splice($context, $extension, 1, new ext_setvar('__ROUTENAME',$routename));
				}
			}
      break;
	}
}

function rp_applyhooks() {
	global $currentcomponent;

	// Add Allow/Deny options
	$currentcomponent->addoptlistitem('rpyn', 'YES', _('yes'));
	$currentcomponent->addoptlistitem('rpyn', 'NO', _('no'));
	$currentcomponent->setoptlistopts('rpyn', 'sort', false);

	// Add the 'process' function
	$currentcomponent->addguifunc('rp_configpageload');
}

function rp_configpageload() {
	global $currentcomponent;

	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

	// Don't display if this is a 'This xtn has been deleted' page.
	if ($action != 'del') {
		$section = _('Outbound Route Permssions');
		$routes = rp_get_routes();
		foreach ($routes as $route) {
			$currentcomponent->addguielem($section, new gui_radio("rp_$route", $currentcomponent->getoptlist('rpyn'), rp_get_perm($extdisplay,$route), $route, "" , null));
			$currentcomponent->addguielem($section, new gui_textbox("rp-redir_$route", rp_get_redir($extdisplay, $route), "'".$route."' "._('Redirect Prefix'), _("Add this prefex and try again if denied. READ THE INSTRUCTIONS on the Outbound Permissions page"), "", "", true, 0, null));
		}
	}
}

function rp_configprocess() {
	// Extract any variables from $REQUEST that start with rp_
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	if(isset($_REQUEST['extension']) ){
		$extdisplay=$_REQUEST['extension'];
	}
	$rps = array();
	$redir = array();

	foreach ($_REQUEST as $r=>$val) {
		if (!strncmp($_REQUEST[$r], "rp_", 3)) {
			$rps[substr($r, 3)]=$val;
		}
		if (!strncmp($r, "rp-redir_", 9)) {
			$redir[substr($r, 9)]=$val;
		}
	}
	//if submitting form, update database
	switch ($action) {
		case "add":
		case "edit":
		rp_purge_ext($extdisplay);
		rp_set_perm($extdisplay, $rps);
		rp_set_redir($extdisplay, $redir);
		break;
		case "del":
		rp_purge_ext($extdisplay);
		break;
	}
}



function rp_get_perm($ext, $route) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$Sroute = mysql_real_escape_string($route);
	$sql = "SELECT allowed FROM routepermissions WHERE routename='$Sroute' AND exten='$Sext'";
	$res = $db->getRow($sql);
	if (PEAR::isError($res)) { die($res->getMessage()); }
	if (isset($res[0])) {
		return $res[0];
	} else {
		return "YES";
	}
}

function rp_get_redir($ext, $route) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$Sroute = mysql_real_escape_string($route);
	$sql = "SELECT faildest FROM routepermissions WHERE routename='$Sroute' AND exten='$Sext'";
	$res = $db->getRow($sql);
	if (PEAR::isError($res)) { die($res->getMessage()); }
	if (isset($res[0])) {
		return $res[0];
	} else {
		return "";
	}
}

function rp_set_perm($ext, $rps) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	foreach($rps as $r=>$p) {
		$val = explode("=", $p);
		$Sr =mysql_real_escape_string($r);
		$Sval =mysql_real_escape_string($val[1]);
		$sql = "INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$Sext', '$Sr', '$Sval')";
		sql($sql);
	}
}

function rp_set_redir($ext, $rps) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	foreach($rps as $r=>$p) {
		$Sr =mysql_real_escape_string($r);
		$Sval =mysql_real_escape_string($p);
		$sql = "UPDATE routepermissions SET faildest='$Sval' where exten='$Sext' and routename='$Sr'";
		sql($sql);
	}
}


function rp_purge_ext($ext) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$sql = "DELETE FROM routepermissions WHERE exten='$Sext'";
	sql($sql);
}

function rp_get_routes() {
	global $db;
	$sql = "SELECT DISTINCT a.name FROM `outbound_routes` a JOIN `outbound_route_sequence` b ON a.route_id = b.route_id ORDER BY `seq`;";
	$res = $db->getAll($sql);
	foreach ($res as $r) {
		$arr[] = $r[0];
	} 
	return $arr;
}

?>

