<h2>Analog <?php echo (($_GET['ports'] == 'fxo')?'FXO':'FXS')?> Ports</h2>
<hr />
<form name="dahdi_editanalog" action="/admin/config.php?type=setup&display=dahdi&dahdi_form=analog_signalling&ports=<?php echo $_GET['ports']?>" method="post">
<input type="hidden" name="type" value="<?php echo $_GET['ports']?>" />
<div id="editanalog_options_container">
<?php 
	$spans = ($_GET['ports'] == 'fxo') ? $dahdi_cards->get_fxo_ports() : $dahdi_cards->get_fxs_ports();
	foreach ($spans as $p): ?>
	<?php $port = $dahdi_cards->get_port($p); ?>
	<div>
		Port <?php echo $p?>: 
		<select name="port_<?php echo $p?>" id="port_<?=$p?>">
			<option value="ks">Kewl Start</option>
			<option value="ls">Loop Start</option>
		</select>
		Group:
		<input type="text" name="port_<?php echo $p?>_group" id="port_<?=$p?>_group" size="2" value="<?=$port['group']?>" />
		<?php if ($_GET['ports'] == 'fxo'): ?>
		Context:
		<input type="text" name="port_<?php echo $p?>_context" id="port_<?=$p?>_context" value="<?=$port['context']?>" />
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
</div>
<div id="editanalog_buttons">
	<input type="submit" name="editanalog_cancel" value="Cancel" />
	<input type="submit" name="editanalog_submit" value="Save" />
</div>
</form>
<script type="text/javascript">
	<?php
	$lsports = $dahdi_cards->get_ls_ports();
	
	foreach ($spans as $p): ?>
	ChangeSelectByValue('port_<?php echo $p?>', "<?=((in_array($p, $lsports)) ? 'ls' : 'ks')?>", true);
	<?php endforeach; ?>
</script>
