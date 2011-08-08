<?php
/* $Id$ */

// Original Release 2009 by Rob Thomas (xrobau@gmail.com)
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



global $db;
global $amp_conf;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

// create the tables
$sql = "CREATE TABLE IF NOT EXISTS routepermissions (
  exten int(11) NOT NULL,
  routename varchar(25) NOT NULL,
  allowed varchar(3) default 'YES',
  faildest varchar(255),
  KEY idx_exten (exten)
);";

$check = $db->query($sql);
if (DB::IsError($check)) {
        die_freepbx( "Can not create `routepermissions` table: " . $check->getMessage() .  "\n");
}

// 0.3 - add 'faildest' 
outn(_("Checking for faildest..."));
$sql = "SELECT faildest FROM routepermissions";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($check)) {
	// add new field
	$sql = "ALTER TABLE routepermissions ADD faildest varchar(255);";
                $result = $db->query($sql);
                if(DB::IsError($result)) {
                        die_freepbx($result->getDebugInfo());
                }
                out(_("OK"));
        } else {
                out(_("already exists"));
}

// Check to see if there's data in the table allready - if so, don't touch.
$sql = "SELECT COUNT(exten) FROM routepermissions";
$results = $db->getRow($sql);
if ($results[0] > 0) { 
	out("Data already exists in routepermissions. Not regenerating");
} else {
// If there's not, propogate all extensions and all trunks with YES permissions
	$sql = "SELECT extension FROM users ORDER BY extension";
	$extns = $db->getAll($sql);
	$sql = "SELECT DISTINCT context FROM extensions WHERE context LIKE 'outrt%';";
	$routes = $db->getAll($sql);

	foreach($extns as $ext) {
		foreach ($routes as $r) {
			$rn = substr($r[0], 10);
			$db->query("INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$ext[0]', '$rn', 'YES');");
		}
	}
}				

?>
