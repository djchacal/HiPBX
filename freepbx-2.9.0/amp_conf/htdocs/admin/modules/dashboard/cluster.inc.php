<?php 

function show_cluster() {
	$cluster=cluster_info();
	if ($cluster === null) {
		return;
	}
	print_r($cluster);
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
		if (isset($cluster['ms'][$key]['status'])) {
			$out .= "<tr><td align='right'>$disp (".$cluster['ms'][$key]['status']."):</td>";
		} else {
			$out .= "<tr><td align='right'>$disp (Unknown):</td>";
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
	// Loop through $output and care about stuff.
	foreach ($output as $rowno => $val) {
		// Cluster DC
		if (preg_match('/^Current DC: (.+) -/', $val, $matches)) {
			$result['dc']=$matches[1];
		}
		// Nodes (Online)
		if (preg_match('/^Online: \[ (.+) \]/', $val, $matches)) {
			$nodes = preg_split('/ /', $matches[1]);
			foreach ($nodes as $nodename) {
				$result['nodes'][]=$nodename;
				$result['online'][]=$nodename;
			}
		}
		// Nodes (Offline)
		if (preg_match('/^OFFLINE: \[ (.+) \]/', $val, $matches)) {
			$nodes = preg_split('/ /', $matches[1]);
			foreach ($nodes as $nodename) {
				$result['nodes'][]=$nodename;
				$result['offline'][]=$nodename;
			}
		}
		// Nodes (Standby)
		if (preg_match('/^Node (.+): standby/', $val, $matches)) {
			$result['nodes'][]=$nodename;
			$result['standby'][]=$nodename;
		}
		// Master/Slave sets
		if (preg_match('/^ Master\/Slave Set: (.+)/', $val, $matches)) {
			print "Found a set at $rowno\n";
			$mymaster="unknown";
			$myslave="unknown";
			// This is always two lines. 
			$startat = $rowno;
			for ( $startat ; $rowno < $startat + 3; $rowno++) {
				if (preg_match('/Masters: \[ (.+) \]/', $output[$rowno], $myline)) {
					$result['ms'][$matches[1]]['master'][]=$myline[1];
					$mymaster=$myline[1];
				} 
				if (preg_match('/Slaves: \[ (.+) \]/', $output[$rowno], $myline)) {
					$result['ms'][$matches[1]]['slave'][]=$myline[1];
					$myslave=$myline[1];
				} 
			}
			// Now, ['master'] is going to be the one that is 'Primary' in /proc/drbd
			$msname=preg_match('/\//', $val[1]);
			exec("drbdadmin role $msname[1]", $adminout, $retvar);
			// Check for weirdisms.
			if ($retvar == 3) { // Name not defined
				$result['ms'][$matches[1]]['master'][]="Error: Unconf";
				$result['ms'][$matches[1]]['slave'][]="Error: Unconf";
			} elseif ($retvar == 10 ) { // drbd not started for that volume
				$result['ms'][$matches[1]]['master'][]="Error: Not Started";
				$result['ms'][$matches[1]]['slave'][]="Error: Not Started";
			} elseif ($retvar != 0 )  { // Something else odd happened.
				$result['ms'][$matches[1]]['master'][]="Error: Unknown $retvar";
				$result['ms'][$matches[1]]['slave'][]="Error: Unknown $retvar";
			} else {
				// We have results!
				exec("drbdadmin cstate $msname[1]", $state);
				$result['ms'][$matches[1]]['status'][]=$state[0];
				if (preg_match('/(.+)\/Primary$/', $adminout[1], $whoami)) { // Master is second col
				}
			}
				
		}
			
	}
	$xresult = array( 
		'nodes' => array('master', 'slave'),
		'online' => array('master'),
		'standby' => null,
		'offline' => array('slave'),
		'ms' => array('ms_drbd_asterisk' =>
			   array('master' => array('master', 'UpToDate'),
				 'slave' => array('slave', 'Inconsistent'),
				 'status' => 'SyncSource'),
			      'ms_drbd_http' =>
			   array('master' => array('master', 'UpToDate'),
				 'slave' => array('slave', 'Inconsistent'),
				 'status' => 'SyncSource'),
			      'ms_drbd_mysql' =>
			   array('master' => array('master', 'UpToDate'),
				 'slave' => array('slave', 'Inconsistent'),
				 'status' => 'SyncSource'),
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
			
		

function drbd_get($resname) {
	// Firstly, do we know about this resource? 
	if (!file_exists("/etc/drbd.d/$resname.res")) {
		// One of those things we should never hit, but...
		$ret['status'] = "MISSING";
		$ret['this'] = "MISSING";
		$ret['other'] = "MISSING";
		return $ret;
	}
	// Oh god, this is horrible.
	$drbdno = `grep device /etc/drbd.d/$resname.res | sed 's/\(.*drbd\)\(.\)./\2/'`;
	$ret['status'] = `grep "^ $drbdno:" /proc/drbd | sed 's/\(.*cs:\)\([[:alpha:]]*\)\(.*\)/\2/'`;
	$ret['this'] =  `grep "^ $drbdno:" /proc/drbd | sed 's/\(.*ro:\)\([[:alpha:]]*\)\/\([[:alpha:]]*\)\(.*\)/\2/'`;
	$ret['other'] =  `grep "^ $drbdno:" /proc/drbd |sed 's/\(.*ro:\)\([[:alpha:]]*\)\/\([[:alpha:]]*\)\(.*\)/\3/'`;
}
