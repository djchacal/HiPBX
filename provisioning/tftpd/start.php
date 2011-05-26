#!/usr/bin/php
<?PHP
if(!defined('STDIN') )  {
  die("You Must run this from the CLI"); 
}

require_once("includes/tftpserver.php");
require_once("includes/epm_tftpd.class");
if(count($_SERVER["argv"]) < 3)
  die("Usage: {$_SERVER["argv"][0]} bind_ip [user] [debug]\n");

$user = null;
if(isset($_SERVER["argv"][2]))
  $user = posix_getpwnam($_SERVER["argv"][2]);

$debug = false;
if(isset($_SERVER["argv"][3]))
  $debug = (bool)$_SERVER["argv"][3];

$server = new epm_tftpd("udp://".$_SERVER["argv"][1].":69", $_SERVER["argv"][2], $debug);
if(!$server->loop($error, $user))
  die("$error\n");
?>