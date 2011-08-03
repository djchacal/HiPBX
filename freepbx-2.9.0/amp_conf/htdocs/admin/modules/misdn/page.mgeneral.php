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
?>
</div>

<div class="content">

<h2><?php echo _("mISDN Settings")?></h2>

<?php

global $currentcomponent;

$currentcomponent->addguielem('_top', new gui_selectbox('bridging', $misdn_yesno, misdn_general_get('bridging'), 'Enable Bridging:', "Enables mISDN_dsp to bridge the calls in HW.<br><br>Default is <b>no</b>.", false));
