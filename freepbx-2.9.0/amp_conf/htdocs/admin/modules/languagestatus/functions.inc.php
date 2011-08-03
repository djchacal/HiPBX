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

// This function calls msgattrib twice, once for getting the total strings in the .pot file
// and once for getting the translated strings in the .po fro the module
function check_translate_status($language_path, $language, $module)
{
$totalmsgcmd        = "msgattrib --stringtable-output ".$language_path.$module.".pot | grep \"^\\\"\" | wc -l";
$translatedmsgcmd   = "msgattrib --stringtable-output --translated ".$language_path.$language."/LC_MESSAGES/".$module.".po | grep \"^\\\"\" | wc -l";
exec($totalmsgcmd, $total);
exec($translatedmsgcmd, $total);
return($total);
}
?>
