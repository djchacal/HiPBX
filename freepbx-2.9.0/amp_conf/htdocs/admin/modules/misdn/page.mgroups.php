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
    $gdisplay = isset($_REQUEST['gdisplay'])?$_REQUEST['gdisplay']:null;
    $misdn_cards = misdn_dump();
?>
<script type="text/javascript">
<!--

function ismISDNGroupName(s) {
    if (!isAlphanumeric(s))
	return false;

    if (s == 'default')
	return false;

    if (s == 'general')
	return false;

    return true;
}

//-->
</script>

<div class="rnav">
    <ul>
 	<li><a<?php echo ($gdisplay == '' ? ' class="current"' : ''); ?> href="config.php?display=mgroups"><?php echo _("Add Port Group"); ?></a></li>
<?php
    foreach(misdn_groups_list() as $group) {
	$p = array_keys(misdn_ports_get($group['name']));
	natsort($p);
?>
	<li><a<?php echo ($gdisplay == $group['name'] ? ' class="current"' : ''); ?> href="config.php?display=mgroups&amp;gdisplay=<?php echo urlencode($group['name']); ?>"><?php echo urlencode($group['name']); if ($p) echo ' [', join(',',$p),']'; ?></a></li>
<?php
    }
?>
    </ul>
</div>

<div class="warning">
    <p>
	<?php echo _("Please remember that you have to restart asterisk after changing port to group assignment!"); ?>
    </p>
</div>

<div class="content">

<h2><?php echo _("mISDN Settings")?></h2>

<?php
    global $currentcomponent;

    if (count($misdn_cards) == 0) {
	$currentcomponent->addguielem('_top', new gui_subheading('head', _("mISDN Error")));
	$currentcomponent->addguielem('_top', new gui_subheading('errtxt', _("No cards found on this system!")));
    }
    else {
	$ar = array();
	$ar2 = array();

	if ($gdisplay && $currentcomponent->_lists['misdn']['group']) {
	    /* we are editing an entry */
	    $currentcomponent->addguielem('_top', new gui_subheading('head', 'Edit Group'));

	    $ar = $currentcomponent->getgeneralarrayitem('misdn', 'group');
	    $ar2 = $currentcomponent->getgeneralarrayitem('misdn', 'ports');
	    $delURL = $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&del=1';
	    $currentcomponent->addguielem('_top', new gui_hidden("editgroup", $ar['name']));
	    $currentcomponent->addguielem('_top', new gui_link('add', _("Delete mISDN Port Group"), $delURL));
	}
	else {
	    /* we create a new entry - so use default values */
	    $currentcomponent->addguielem('_top', new gui_subheading('head', _("Add Group")));
	    
	    foreach($misdn_confkeys as $confkey) {
		$ar[$confkey['name']] = $confkey['default'];
	    }
	}

	$currentcomponent->addguielem('_top', new gui_textbox('name', $ar['name'], 'Name:', _("Unique name of the mISDN portgroup. Must not be <i>default</i> nor <i>general</i>."), '!ismISDNGroupName()', "The group name must contain only alphanumeric characters, be non empty and not equal 'default' or 'general'!", false));
	$ptypes = array(
		    array('value' => 0, 'text' => _("Extensions")),
		    array('value' => 1, 'text' => _("Trunks")),
		 );
	if (!($gdisplay && $currentcomponent->_lists['misdn']['group']))
	    $currentcomponent->addguielem('_top', new gui_selectbox('grouptype', $ptypes, 0, 'Ports Type:', _("Select type of the ports in this group."), false));
	else
	    $currentcomponent->addguielem('_top', new gui_text('grouptype', $ptypes[$ar['type']]['text'], 'Ports Type:', _("The type of the ports in this group.")));
	
	/* create port selection checkboxes */
	$i = 1;
	$pna = misdn_ports_get_na($ar['name']);
        foreach($misdn_cards as $card) {
	    $j = $i;
	    $currentcomponent->addguielem('_top', new gui_subheading("card$i", "$card[0]: $card[1] Port".($card[1]>1 ? 's' : '')));

	    $hasone=0;	
	    for(; $i < $card[1]+$j; $i++) {
		if (!$pna[$i]) {
		    if (!$hasone) {
			$currentcomponent->addguielem('_top', new guitext("chint$i", _("Selectable ports on this card:")));
			$hasone = 1;
		    }
	    	    $currentcomponent->addguielem('_top', new gui_checkbox("port$i", $ar2[$i], '', '', '1', strtoupper($card[$i-$j+2]).' '._("Port").' '.($i-$j+1)." [$i]"));
		}
	    }
	    if (!$hasone)
		$currentcomponent->addguielem('_top', new guitext("chhasnone$i", _("All ports of this device are already assigned to groups!")));
	}

	/* generate settings from $misdn_confkeys */
	$currentcomponent->addguielem('_top', new gui_subheading('settings', _("Settings")));
	foreach($misdn_confkeys as $confkey) {
	    $elem = null;
	    $def = (strlen($confkey['descr']) ? '<br /><br />' : '')._('Default is').' <b>';
	    
    	    switch($confkey['type']) {
		case 's':
		case 'i':
		    $def .= $confkey['default'].'</b>.';
		    $elem = new gui_textbox($confkey['name'], $ar[$confkey['name']], $confkey['title'].':', $confkey['descr'].$def, '', '', true);
		    break;

		case 'a':
		    foreach($confkey['alt'] as $alt) {
			if ($alt['value'] == $confkey['default']) {
			    $def .= $alt['text'].'</b>.';
			    break;
			}
		    }
		    $elem = new gui_selectbox($confkey['name'], $confkey['alt'], $ar[$confkey['name']], $confkey['title'].':', $confkey['descr'].$def, false);
		    break;

		default:
		    die("Unhandled type '".$confkey['type']."'!");
		    break;
	    }
	
	    $currentcomponent->addguielem('_top', $elem);
	}
    }
