<h2>Analog Hardware</h2>
<hr />
<table class="taglist" id="digital_cards_table" cellpadding="5" cellspacing="1" border="0">
        <thead>
        <tr>
                <th>Type</th>
                <th>Ports</th>
                <th>Action</th>
        </tr>
        </thead>
        <tbody>
	<?php
		$fxo = $dahdi_cards->get_fxo_ports();
		$fxs = $dahdi_cards->get_fxs_ports();
	?>
	<tr class="odd">
		<td>FXO Ports</td>
		<td><?php echo ((count($fxo))?implode(',', $fxo):'--')?></td>
		<td><?php echo ((count($fxo))?'<a href="/admin/config.php?type=setup&display=dahdi&dahdi_form=analog_signalling&ports=fxo">Edit</a>':'')?></td>
	</tr>
	<tr>
		<td>FXS Ports</td>
		<td><?php echo ((count($fxs))?implode(',', $fxs):'--')?></td>
		<td><?php echo ((count($fxs))?'<a href="'.$_SERVER['REQUEST_URI'].'&dahdi_form=analog_signalling&ports=fxs">Edit</a>':'')?></td>
	</tr>
        </tbody>
</table>
