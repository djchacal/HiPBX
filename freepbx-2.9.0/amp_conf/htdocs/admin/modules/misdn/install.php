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

require_once(dirname(__FILE__).'/functions.inc.php');

outn(_("Checking for an xslt processor.."));
if (!function_exists('xslt_create')) {
  out(_("nothing found."));
  out(_("FATAL: can't continue installing, see module requirements."));
  return false;
}

/* If we have an empty mISDN group list, create an example trunk and extensions group. */
if (!misdn_groups_list()) {
  sql("DELETE FROM `misdn_ports`;");

  $groups = array('nt' => array(), 'te' => array());

  $p = 1;
  foreach(misdn_dump() as $ar) {
    $name = array_shift($ar);
    array_shift($ar);

    foreach($ar as $mode)
      $groups[trim($mode)][] = $p++;
    }

    $ar = array();
    $ptypes = array(
	    'te' => array(
			'name' => 'TrunkPorts',
			'type' => 1,
		    ),
	    'nt' => array(
			'name' => 'ExtensionPorts',
			'type' => 0,
		    ),
    );

    foreach($misdn_confkeys as $confkey) {
      $keys[] = $confkey['name'];
      $vals[] = misdn_format_sql($confkey['name'], $confkey['default']);
    }

    foreach(array_keys($groups) as $g) {
      if (count($g)) {
	    sql('INSERT INTO `misdn_groups` (`name`,`type`,'.implode(',', $keys).") VALUES ('"._misdn_escape_string($ptypes[$g]['name'])."',".$ptypes[$g]['type'].",".implode(',', $vals).')');
      foreach($groups[$g] as $p) {
        sql("INSERT INTO `misdn_ports` (`port`, `group`) VALUES ($p, '"._misdn_escape_string($ptypes[$g]['name'])."')");
      }
    }
  }
}
