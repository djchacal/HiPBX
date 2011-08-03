<?php
/*
 * freepbx-misdn -- mISDN module for FreePBX
 *
 * Copyright (C) 2006, Thomas Liske.
 *
 * Thomas Liske <thomas.liske@beronet.com>
 *
 * This program is free software, distributed under the terms of
 * the GNU General Public License Version 2.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

define('MISDN_PWD', dirname(__FILE__));
require_once(MISDN_PWD.'/xslt.inc.php');

// ************** components.class.php additions **************

class gui_text extends guiinput {
	function gui_text($elemname, $currentvalue = '', $prompttext = '', $helptext = '') {
		// call parent class contructor
		$parent_class = get_parent_class($this);
		parent::$parent_class($elemname, $currentvalue, $prompttext, $helptext);
		
		$this->html_input = htmlentities($this->currentvalue);
	}
}


// ************** Config File Generation **************

/* Configfile Generation Class */
class misdn_conf {
    function get_filename() {
	return array('misdn.conf');
    }
    
    function generateConf($file) {
	global $misdn_fields;
    
	if ($file == 'misdn.conf') {
	    if (misdn_general_get('bridging'))
		$bridging = "yes";
	    else
		$bridging = "no";
	
	    $ret = <<<EOF
[general]
bridging = $bridging
#include misdn_general_custom.conf

[default]
#include misdn_default_custom.conf


EOF;

	    foreach(misdn_groups_list() as $group) {
		$name = $group['name'];

		switch ($group['type']) {
		    case 0:
			$context = 'from-internal';
			break;
		    case 1:
			$context = 'from-pstn';
			break;
		    default:
			$context = 's';
			break;
		}
	
		$ports = join(',', misdn_ports_get_sel($name));
		
		$ret .= <<<EOG
[$name]
context=$context
ports=$ports

EOG;
		unset($group['name']);
		unset($group['type']);
		foreach(array_keys($group) as $key) {
		    $ret .= "$key=$group[$key]\n";
		}
		$ret .= '#include misdn_'.$name."_custom.conf\n\n";
	    }

	    return $ret;
	}
    }
}


// ************** Helper Functions **************

function misdn_general_get($key) {
    $val = sql("SELECT `data` FROM `misdn` WHERE `keyword`='"._misdn_escape_string($key)."' AND `id`='XXXXXX'", "getOne");
    return $val;
}

function misdn_general_set($key, $val) {
    sql("UPDATE `misdn` SET `data`='"._misdn_escape_string($val)."' WHERE `keyword`='"._misdn_escape_string($key)."' AND `id`='XXXXXX'");
}

/* get all configured groups */
function misdn_groups_list($type = null) {
    global $misdn_fields;

    $tf = '';
    if ($type != null)
	$tf = "WHERE type=$type";

    return sql("SELECT name, type, $misdn_fields FROM `misdn_groups` $tf ORDER BY `name` ASC", "getAll", DB_FETCHMODE_ASSOC);
}

/* get a specific group by name */
function misdn_groups_get($name) {
    global $misdn_fields;

    $ar = sql("SELECT name, type, $misdn_fields FROM `misdn_groups` WHERE `name` = '"._misdn_escape_string($name)."'", "getAll", DB_FETCHMODE_ASSOC);

    return array_shift($ar);
}

/* get all ports which are used by other groups, so they are not available for the actual group */
function misdn_ports_get_na($name) {
    global $misdn_fields;

    $ar = sql("SELECT `port` FROM `misdn_ports` WHERE `group` <> '"._misdn_escape_string($name)."' ORDER BY `port` ASC", "getAll", DB_FETCHMODE_ASSOC);

    $ret = array();
    foreach($ar as $port)
	$ret[$port['port']] = 1;

    return $ret;
}

/* get ports for a specific group as values */
function misdn_ports_get_sel($name) {
    global $misdn_fields;

    $ar = sql("SELECT `port` FROM `misdn_ports` WHERE `group` = '"._misdn_escape_string($name)."' ORDER BY `port` ASC", "getAll", DB_FETCHMODE_ASSOC);

    $ret = array();
    foreach($ar as $port)
	$ret[] = $port['port'];

    return $ret;
}

/* get ports for a specific group as keys */
function misdn_ports_get($name) {
    global $misdn_fields;

    $ar = sql("SELECT `port` FROM `misdn_ports` WHERE `group` = '"._misdn_escape_string($name)."'", "getAll", DB_FETCHMODE_ASSOC);

    $ret = array();
    foreach($ar as $port)
	$ret[$port['port']] = 1;

    return $ret;
}

function _misdn_escape_string($var) {
  global $db;
  return $db->escapeSimple($var);
}

/* get all configured ports */
function misdn_ports_list_exts() {
    global $misdn_fields;

    return sql("SELECT misdn_ports.port FROM `misdn_ports`, `misdn_groups` WHERE misdn_ports.group = misdn_groups.name AND misdn_groups.type = 0 ORDER BY `port` ASC", "getAll", DB_FETCHMODE_ASSOC);
}
function misdn_ports_list_trunks() {
    global $misdn_fields;

    return sql("SELECT misdn_ports.port FROM `misdn_ports`, `misdn_groups` WHERE misdn_ports.group = misdn_groups.name AND misdn_groups.type = 1 ORDER BY `port` ASC", "getAll", DB_FETCHMODE_ASSOC);
}

/* retrieve all groups and ports, usefull for dialstring generation */
function misdn_groups_ports() {
    $ret = array();

    foreach(misdn_groups_list(1) as $group) {
      $ret[] = 'g:'.$group['name'];
    }
    
    foreach(misdn_ports_list_trunks() as $port) {
      $ret[] = $port['port'];
    }
    
    return $ret;
}

/* retrieve ISDN card configuration from mISDN */
function misdn_dump() {
    $xsl = xslt_create();

    $res = trim(xslt_process($xsl, '/etc/mISDN.conf', MISDN_PWD.'/misdn_dump.xsl'));

    if (strlen($res) && !strstr($res, "\n"))
	return array(explode(',', $res));

    $ret = array();
    foreach(explode("\n", $res) as $line)
	$ret[] = explode(',', $line);
    return $ret;
}

// ************** Page: GENERAL **************

/* apply hooks */
function misdn_mgeneral_configpageinit($dispnum) {
    global $currentcomponent;

    $currentcomponent->addprocessfunc('misdn_mgeneral_configprocess');
}

function misdn_mgeneral_configprocess() {
    if (isset($_POST['bridging'])) {
	$b = $_POST['bridging'];
	settype($b, 'int');
	misdn_general_set('bridging', $b);

	needreload();
    }
}

// ************** Page: GROUPS **************

$misdn_echo = array(
		array('value' => 0, 'text' => '0 [off]'),
		array('value' => 32, 'text' => '32'),
		array('value' => 64, 'text' => '64'),
		array('value' => 128, 'text' => '128 [default]'),
		array('value' => 256, 'text' => '256')
	      );

$misdn_yesno = array(
	       array('value' => 1, 'text' => _('yes')),
	       array('value' => 0, 'text' => _('no')),
	       );

$misdn_methods = array(
		 array('value' => 'standard'   , 'text' => _('Standard')),
		 array('value' => 'round_robin', 'text' => _('Round Robin')),
		 );

$misdn_txgains = array();
for($i=-8; $i<=8; $i++)
  $misdn_txgains[] = array('value' => $i, 'text' => $i);

$misdn_dialplans = array(
		   array('value' => 0, 'text' => _('Unknown')),
		   array('value' => 1, 'text' => _('International')),
		   array('value' => 2, 'text' => _('National')),
		   array('value' => 4, 'text' => _('Subscriber')),
		   );

/* This is derived from the msisdn_groups table structure - so keep it in sync! */
$misdn_confkeys = array(
			array('name' => 'msns'		         , 'title' => 'MSNs'                , 'default' =>        '*', 'type' => 's', 'db' => 's',                            'descr' => _("Give a comma separated list (or '*' to match any) of MSNs for TE ports.")),
			array('name' => 'echocancel'	         , 'title' => 'Echocancellation'    , 'default' =>          0, 'type' => 'a', 'db' => 'i', 'alt' => $misdn_echo     , 'descr' => _("This enables echocancellation, with the given number of taps. Be aware, move this setting only to outgoing portgroups! A value of zero turns echocancellation off.")),
			array('name' => 'immediate' 	         , 'title' => 'Immediate'           , 'default' =>          0, 'type' => 'a', 'db' => 'b', 'alt' => $misdn_yesno    , 'descr' => ''),
			array('name' => 'method'		 , 'title' => 'Method'              , 'default' => 'standard', 'type' => 'a', 'db' => 's', 'alt' => $misdn_methods  , 'descr' => _("Set the method to use for channel selection.<br /><i>Standard</i> always chooses the first free channel with the lowest number.<br /><i>Round Robin</i> uses the round robin algorithm to select a channel. Use this if you want to balance your load.")),
			array('name' => 'pmp_l1_check'	 	 , 'title' => 'PMP L1 Check'        , 'default' =>          0, 'type' => 'a', 'db' => 'b', 'alt' => $misdn_yesno    , 'descr' => _("This option defines, if chan_misdn should check the L1 on a PMP before making a group call on it. The L1 may go down for PMP Ports so we might need this.")),
			array('name' => 'txgain'		 , 'title' => 'TX Gain'             , 'default' =>          0, 'type' => 'a', 'db' => 'i', 'alt' => $misdn_txgains  , 'descr' => _("Changes the TX Gain.")),
			array('name' => 'dialplan'		 , 'title' => 'Dialplan'            , 'default' =>          0, 'type' => 'a', 'db' => 'i', 'alt' => $misdn_dialplans, 'descr' => _("Type Of Number in ISDN Terms (for outgoing calls).")),
			array('name' => 'nationalprefix'	 , 'title' => 'National Prefix'     , 'default' =>        '0', 'type' => 's', 'db' => 's',                            'descr' => _("National prefix is put before the oad if an according dialplan is set by the other end.")),
			array('name' => 'internationalprefix'    , 'title' => 'International Prefix', 'default' =>       '00', 'type' => 's', 'db' => 's',                            'descr' => _("International prefix is put before the oad if an according dialplan is set by the other end.")),
			array('name' => 'language'		 , 'title' => 'Language'            , 'default' =>       'en', 'type' => 's', 'db' => 's',                            'descr' => ''),
			array('name' => 'callgroup'		 , 'title' => 'Callgroup'           , 'default' =>          0, 'type' => 'i', 'db' => 'i',                            'descr' => ''),
			array('name' => 'pickupgroup'	         , 'title' => 'Pickupgroup'         , 'default' =>          0, 'type' => 'i', 'db' => 'i',                            'descr' => ''),
			array('name' => 'senddtmf'	         , 'title' => 'Send DTMF'           , 'default' =>          0, 'type' => 'a', 'db' => 'b', 'alt' => $misdn_yesno    , 'descr' => _("Either if we should produce DTMF Tones ourselves.")),
			array('name' => 'reject_cause'	         , 'title' => 'Reject Cause'        , 'default' =>         21, 'type' => 'i', 'db' => 'i',                            'descr' => _("Reject code sent to the caller in PMP.<br /><i>16</i> normal call clearing.<br /><i>17</i> user busy.<br /><i>21</i> disconnected.")),
			);

$misdn_fields_ar = array('`name`');
foreach($misdn_confkeys as $confkey) {
    $misdn_fields_ar[] = '`'.$confkey['name'].'`';
}
global $misdn_fields;
$misdn_fields = implode(',', $misdn_fields_ar);

/* apply hooks */
function misdn_mgroups_configpageinit($dispnum) {
    global $currentcomponent;

    extract($_REQUEST);
    $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
    $tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;
    $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
    $display = isset($_REQUEST['display'])?$_REQUEST['display']:null;
    $gdisplay = isset($_REQUEST['gdisplay'])?$_REQUEST['gdisplay']:null;

    if ($display == 'extensions') {
	// add entry to Basic->Extensions device list
	if ($ports = misdn_ports_list_exts())
	    $currentcomponent->addoptlistitem('devicelist', 'misdn_generic', _('Generic mISDN Device'));

	if ( $action == 'del' ) {
	    // nothing
	} else {
	    $tmparr = array();
	    $tmparr['context'] = array('value' => 'from-internal', 'level' => 2);
	    if ($extdisplay)
		$tmparr['dial'] = array('value' => '', 'level' => 0);
	    else {
		$select = array();
		foreach($ports as $port) {
		    $select[] = array('value' => 'mISDN/'.$port['port'], 'text' => 'mISDN/'.$port['port']);
	        }
		$tmparr['port'] = array('value' => '', 'select' => $select, 'level' => 0);
	    };
	    $currentcomponent->addgeneralarrayitem('devtechs', 'misdn', $tmparr);
	    
	    unset($tmparr);
	}
    }
    else if ($dispnum == 'mgroups') {
	if ($gdisplay) {
	    $currentcomponent->addgeneralarrayitem('misdn', 'group', misdn_groups_get($gdisplay));
	    $currentcomponent->addgeneralarrayitem('misdn', 'ports', misdn_ports_get($gdisplay));
	}

	$currentcomponent->addprocessfunc('misdn_mgroups_configprocess_mgroups');
    }
    global $devinfo_dial;
    $devinfo_dial = "Port:";
}

function misdn_format_sql($ar, $val) {
  if ($ar['type'] == 'a') {
    $v = $ar['default'];

    foreach($ar['alt'] as $alt) {
      if ($alt['value'] == $val) {
	$v = $val;
	break;
      }
    }
  }
  else
    $v = $val;

  switch ($ar['db']) {
  case 'b':
    if ($v)
      $v = 1;
    else
      $v = 0;
  case 'i':
    settype($v, 'int');
    return $v;
  default:
    return "'"._misdn_escape_string($v)."'";
  }
}

function misdn_mgroups_configprocess_mgroups() {
  global $misdn_confkeys;

  $gdisplay = isset($_REQUEST['gdisplay'])?$_REQUEST['gdisplay']:null;

  if ($_GET['del'] && $gdisplay) {
    $name = _misdn_escape_string($_GET['gdisplay']);
    sql("DELETE FROM `misdn_ports` WHERE `group`='$name'");
    sql("DELETE FROM `misdn_groups` WHERE `name`='$name'");
    unset($_REQUEST['gdisplay']);
    return;
  }
  
  if (!$_POST['name'])
    return;
    
  $_POST['name'] = trim($_POST['name']);

  if ($_POST['editgroup']) {
    $keyvals = array("`name`='"._misdn_escape_string($_POST['name'])."'");
    foreach($misdn_confkeys as $confkey) {
      $keyvals[] = '`'.$confkey['name'].'`='.misdn_format_sql($confkey, $_POST[$confkey['name']]);
    }

    $sql = "UPDATE `misdn_groups` SET ".implode(',', $keyvals)." WHERE `name`='"._misdn_escape_string($_POST['editgroup'])."'";
    sql("DELETE FROM `misdn_ports` WHERE `group`='"._misdn_escape_string($_POST['editgroup'])."'");
  }
  else {
    $keys = array();
    $vals = array();
    foreach($misdn_confkeys as $confkey) {
      $keys[] = '`'.$confkey['name'].'`';
      $vals[] = misdn_format_sql($confkey, $_POST[$confkey['name']]);
    }

    $type = $_POST['grouptype'];
    settype($type, 'int');

    $sql = 'INSERT INTO `misdn_groups` (`name`,`type`,'.implode(',', $keys).") VALUES ('"._misdn_escape_string($_POST['name'])."',$type,".implode(',', $vals).')';
  }

  sql($sql);

  $i = 0;
  foreach(misdn_dump() as $card) {
    for($j=0; $j<$card[1]; $j++) {
      $i++;

      if ($_POST["port$i"])
	sql("INSERT INTO `misdn_ports` (`port`, `group`) VALUES ($i, '"._misdn_escape_string($_POST['name'])."')");
    }
  }

  needreload();
}
