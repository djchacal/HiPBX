<?php 

function show_cluster() {
	$cluster=cluster_info();
	if ($cluster === null) {
		return;
	}
	// OK, so the cluster is up, and running. We're in HiPBX - probably.
        $out = '<div id="cluster" class="infobox">'."\n";
        $out .= "<h3>"._("Cluster Information")."</h3>";
	// Grab the cluster info
	// Give an overview of cluster status.
        $out .= '<table border=1px summary="'._('Cluster Information Table').'">';
        $out .= '<tr><th>'._('Cluster DC').':</th><td>'.$cluster['dc'].'</td></tr>';
        $out .= '<tr><th>'._('Node(s)').':</th><td>'.$cluster['nodes'][0].', '.$cluster['nodes'][1].'</td></tr>';
	$out .= '<tr><th>'._('Disk Sets').':</th><td></td></tr>';
	foreach ($cluster['ms'] as $key => $value) {
		$disp = substr($key, 8);
		$out .= "<tr><td colspan=2>I have $disp and $value</td></tr>\n";
	}
		
        $out .= '</table>';
	$out .= '</div>';
        return $out;
}


function cluster_info() {
	exec("crm status inactive", $output, $retvar);
	if ($retvar == 127 || $output[1]=="Connection to cluster failed: connection failed") {
		// CRM isn't installed, or isn't running. 
		return null;
	}
	// Stuff.
	$result = array( 
		'dc' => 'master',
		'nodes' => array('master', 'UNKNOWN'),
		'ms' => array('ms_drbd_asterisk' =>
			   array('started' => array('master' => 'UpToDate'),
				 'stopped' => null,),
			      'ms_drbd_http' =>
			   array('started' => array('master' => 'UpToDate'),
				 'stopped' => null,),
			      'ms_drbd_mysql' =>
			   array('started' => array('master' => 'UpToDate'),
				 'stopped' => null,),
			      'ms_drbd_dhcp' =>
			   array('started' => null,
				 'stopped' => null,),
			      'ms_drbd_ldap' =>
			   array('started' => null,
				 'stopped' => null,)),
		'res' => array(
			'asterisk' => array('fs_asterisk' => 'master', 'ip_asterisk' => 'master', 'dahdi' => 'master', 'asteriskd' => 'master'),
			'http' => array('fs_http' => 'master', 'ip_http' => 'master', 'httpd' => 'master'),
			'mysql' => array('fs_mysql' => 'master', 'ip_mysql' => 'master', 'mysqld' => 'master'),
			'dhcp' => array('fs_dhcp' => 'STOPPED', 'ip_dhcp' => 'STOPPED'),
			'ldap' => array('fs_ldap' => 'STOPPED', 'ip_ldap' => 'STOPPED'))
		);
	return $result;
}
			
		
