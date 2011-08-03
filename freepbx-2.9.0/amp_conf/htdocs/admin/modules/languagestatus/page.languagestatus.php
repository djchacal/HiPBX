<?php
// Copyright (C) 2011 Mikael Carlsson (mickecarlsson at gmail dot com)
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation, version 2
// of the License.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

$language = $_COOKIE['lang'];
if($language != "en_US") {

 $supported_modules = array(
  "announcement",
  "asterisk-cli",
  "asteriskinfo",
  "backup",
  "blacklist",
  "callback",
  "callforward",
  "callwaiting",
  "cidlookup",
  "conferences",
  "customappsreg",
  "dashboard",
  "daynight",
  "dictate",
  "disa",
  "donotdisturb",
  "dundicheck",
  "fax",
  "featurecodeadmin",
  "findmefollow",
  "iaxsettings",
  "infoservices",
  "irc",
  "ivr",
  "javassh",
  "languages",
  "logfiles",
  "manager",
  "miscapps",
  "miscdests",
  "music",
  "outroutemsg",
  "paging",
  "parking",
  "pbdirectory",
  "phonebook",
  "phpagiconf",
  "phpinfo",
  "pinsets",
  "printextensions",
  "queueprio",
  "queues",
  "recordings",
  "restart",
  "ringgroups",
  "sipsettings",
  "speeddial",
  "timeconditions",
  "vmblast",
  "voicemail",
  );

 if (file_exists($amp_conf['AMPWEBROOT']."/admin/i18n/${language}/LC_MESSAGES/amp.po" ))
 {
 $module = "amp";
 $result = check_translate_status($amp_conf['AMPWEBROOT']."/admin/i18n/",$language,$module);
 $total = $result[0] -1;
 $total_translated = $result[1] -1;
 $total_untranslated = $total - $total_translated;
 $percent_completed = number_format((($total_translated/$total)*100),0);
 if ($percent_completed == 100) {  // Green color, all is OK! Nice work
	$bgcolor = "#00ff00";
 } elseif ($percent_completed >= 75) {  // Almost there, just a few more translations to do
	$bgcolor = "#ffff00";
 } elseif ($percent_completed < 75) {  // Wow, someone got tired of translation or we just released a new version with a lot of new text strings.
	$bgcolor = "#ff0000";
 };

 echo "<h1>Translation status for ".$_COOKIE['lang']."</h1>";
 echo "<table ><tr><td>";
 echo "Application/Module</td><td>Strings</td><td>Translated</td><td>Not translated</td><td>Percent completed</td></tr>";
 echo "<td>core</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total_translated."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total_untranslated."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$percent_completed."%</td></tr>";

 $module = "ari";
 $result = check_translate_status($amp_conf['AMPWEBROOT']."/recordings/locale/",$language,$module);
  if($result[0] != "0") { 
   $total = $result[0] -1;
   } else { 
    $total = "0"; 
   };
  if($result[1] != "0") {
   $total_translated = $result[1] -1;
   } else {
    $total_translated = "0";
   }
   $total_untranslated = $total - $total_translated;
   $percent_completed = number_format((($total_translated/$total)*100),0);
	if ($percent_completed == 100) {  // Green color, all is OK! Nice work
	 $bgcolor = "#00ff00";
	} elseif ($percent_completed >= 75) {  // Almost there, just a few more translations to do
	 $bgcolor = "#ffff00";
	} elseif ($percent_completed < 75) {  // Wow, someone got tired of translation or we just released a new version with a lot of new text strings.
	 $bgcolor = "#ff0000";
	};
 echo "<td>ari</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total."</td><td  style=\"background-color: ".$bgcolor."\" align=\"center\">".$total_translated."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total_untranslated."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$percent_completed."%</td></tr>";

 foreach($supported_modules as $langmodule) {
  $result = check_translate_status($amp_conf['AMPWEBROOT']."/admin/modules/$langmodule/i18n/",$language,$langmodule);
  if($result[0] != "0") { 
   $total = $result[0] -1;
   } else { 
    $total = "0"; 
   };
  if($result[1] != "0") {
   $total_translated = $result[1] -1;
   } else {
    $total_translated = "0";
   }
   $total_untranslated = $total - $total_translated;
   if($total != "0") { // if there is no .pot file we can't divide by zero
    $percent_completed = number_format((($total_translated/$total)*100),0);
		 if ($percent_completed == 100) {  // Green color, all is OK! Nice work
 			$bgcolor = "#00ff00";
		 } elseif ($percent_completed >= 75) {  // Almost there, just a few more translations to do
	 		$bgcolor = "#ffff00";
		 } elseif ($percent_completed < 75) {  // Wow, someone got tired of translation or we just released a new version with a lot of new text strings.
	 		$bgcolor = "#ff0000";
		 }
	 } else {
	 $percent_completed = 0;
	 $bgcolor = "#ff0000";
	 };
   echo "<td>".$langmodule."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total_translated."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$total_untranslated."</td><td style=\"background-color: ".$bgcolor."\" align =\"center\">".$percent_completed."%</td></tr>";
  }
  echo "</table>";
  } 
 }
 else {
  echo "<h1>Language Status</h1><br>Select language in the language selection<br>";
}
?>