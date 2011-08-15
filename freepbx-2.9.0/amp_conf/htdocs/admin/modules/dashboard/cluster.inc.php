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
        $out .= '<table summary="'._('Cluster Information Table').'">';
        $out .= '<tr><th>'._('Cluster DC').':</th><td colspan=2>'.$cluster['dc'].'</td></tr>';
        $out .= '<tr><th>'._('Node(s)').':</th><td>'.$cluster['nodes'][0].'</td><td>'.$cluster['nodes'][1].'</td></tr>';
	$node0 = $cluster['nodes'][0];
	$node1 = $cluster['nodes'][1];
	$out .= '<tr><th>'._('Disk Sets').':</th><td colspan=2></td></tr>';
	foreach ($cluster['ms'] as $key => $value) {
		$disp = substr($key, 8);
		if (isset($cluster['ms'][$key][$node0])) {
			$r0 = "<td>".$cluster['ms'][$key][$node0][0]." (".$cluster['ms'][$key][$node0][1].")</td>";
		} else {
			$r0 = "<td>down</td>";
		}
		if (isset($cluster['ms'][$key][$node1])) {
			$r1 = "<td>".$cluster['ms'][$key][$node1][0]." (".$cluster['ms'][$key][$node1][1].")</td>";
		} else {
			$r1 = "<td>down</td>";
		}
		$out .= "<tr><td align='right'>$disp:</td>";
		$out .= "$r0\n";
		$out .= "$r1\n";
	}
	$out .= '<tr><th>'._('Resources').':</th><td colspan=2></td></tr>';
	foreach ($cluster['res'] as $key => $value) {
		$out .= "<tr><td align='right'>$key:</td><td colspan=2>";
		foreach ($cluster['res'][$key] as $rname => $rval) {
			$out .= "$rname (".$cluster['res'][$key][$rname].") ";
		}
		$out .= "</tr>\n";
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
		'nodes' => array('master', 'slave'),
		'ms' => array('ms_drbd_asterisk' =>
			   array('master' => array('master', 'UpToDate'),
				 'slave' => array('slave', 'Inconsistent')),
			      'ms_drbd_http' =>
			   array('master' => array('master', 'UpToDate'),
				 'slave' => array('slave', 'Inconsistent')),
			      'ms_drbd_mysql' =>
			   array('master' => array('master', 'UpToDate'),
				 'slave' => array('slave', 'Inconsistent')),
			      'ms_drbd_dhcp' =>
			   array('master' => null,
				 'slave' => null,),
			      'ms_drbd_ldap' =>
			   array('master' => null,
				 'slave' => null,)),
		'res' => array(
			'asterisk' => array('fs_asterisk' => 'master', 'ip_asterisk' => 'master', 'dahdi' => 'master', 'asteriskd' => 'master'),
			'http' => array('fs_http' => 'master', 'ip_http' => 'master', 'httpd' => 'master'),
			'mysql' => array('fs_mysql' => 'master', 'ip_mysql' => 'master', 'mysqld' => 'master'),
			'dhcp' => array('fs_dhcp' => 'Stopped', 'ip_dhcp' => 'Stopped'),
			'ldap' => array('fs_ldap' => 'Stopped', 'ip_ldap' => 'Stopped'))
		);
	return $result;
}
			
		
