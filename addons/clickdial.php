<?php
# clickdial.php - Rob Thomas <xrobau@gmail.com> (C) 2011.
#
# Very basic, simple, and TOTALLY INSECURE SCRIPT.
#
# This just lets you have a web interface that you can call with:
# clickdial.php?from=200&to=*43
# (as an example)
# That will ring Exten 200, and when it's answered, will call *43 (echo test)
# THERE IS NO SECURITY ON THIS. Anyone can make anyone call anything.
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU Affero General Public License as
#    published by the Free Software Foundation, either version 3 of the
#    License, or (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.

if (!isset($_REQUEST['from']) || !(isset($_REQUEST['to']))) {
	print ("Sorry. This doesn't work that way.");
	exit;
}
include("/etc/freepbx.conf");
$host = $amp_conf['ASTMANAGERHOST'];
$port = $amp_conf['ASTMANAGERPORT'];
$user = $amp_conf['AMPMGRUSER'];
$pass = $amp_conf['AMPMGRPASS'];
$from = $_REQUEST['from'];
$to = $_REQUEST['to'];

$socket = fsockopen($host,$port, $errno, $errstr) 
  or die ("Cannot connect to asterisk - $errstr");
fputs($socket, "Action: Login\r\n"); 
fputs($socket, "UserName: $user\r\n"); 
fputs($socket, "Secret: $pass\r\n\r\n"); 
while ($ret = fgets($socket, 512)) {
	if (preg_match("/^Resp/", $ret)) break;
}
fputs($socket, "Action: Originate\r\n"); 
fputs($socket, "Channel: SIP/$from\r\n");
fputs($socket, "Context: from-internal\r\n");
fputs($socket, "Exten: $to\r\n");
fputs($socket, "CallerID: Click-to-Dial <$to>\r\n");
fputs($socket, "Priority: 1\r\n");
fputs($socket, "ActionID: ".rand(1000)."\r\n");
fputs($socket, "Timeout: 10000\r\n\r\n");
while ($ret = fgets($socket, 512)) {
	if (preg_match("/^Resp/", $ret)) break;
}
fputs($socket, "Action: Logoff\r\n"); 
while ($ret = fgets($socket, 512)) {
	if (preg_match("/^Resp/", $ret)) break;
}

?>

