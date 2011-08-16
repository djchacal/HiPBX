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
		$out .= "<tr><td align='right'> $disp (".$cluster['ms'][$key]['status'].")</td>";
		$out .= "<td>".$cluster['ms'][$key]['master']['status']."/";
		$out .= "<td>".$cluster['ms'][$key]['slave']['status']."</td></tr>";
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
			$mymaster="Unknown";
			$myslave="Unknown";
			// This is always two lines.  Figure out which is the master
			// and the slave.
			$startat = $rowno;
			for ( $rowno++ ; $rowno < $startat + 3; $rowno++) {
				if (preg_match('/Masters: \[ (.+) \]/', $output[$rowno], $myline)) {
					$mymaster=$myline[1];
				} elseif (preg_match('/Slaves: \[ (.+) \]/', $output[$rowno], $myline)) {
					$myslave=$myline[1];
				} 
			}
			$drbd_status=drbd_get($matches[1]);
			$result['ms'][$matches[1]]['status']=$drbd_status['status'];
			$result['ms'][$matches[1]]['master']['host']=$mymaster;
			$result['ms'][$matches[1]]['slave']['host']=$myslave;
			$result['ms'][$matches[1]]['master']['status']=$drbd_status['this']['status'];
			$result['ms'][$matches[1]]['slave']['status']=$drbd_status['other']['status'];
		} 
		// Resource Groups
		if (preg_match('/^ Resource Group: (.+)/', $val, $rgname)) {
			// Resource groups start with 5 spaces. Pull all the items
			// in from the rg.
			$startat = $rowno;
			for ( $rowno++ ; strpos($output[$rowno], "     ") !== false ; $rowno++) {
				if (preg_match('/^\s+([\w_]+)\s+\(.+\):\s+(.+)$/', $output[$rowno], $matches)) {
					$result['res'][$rgname[1]][$matches[1]]=$matches[2];
				}
			}
		}
	}
	return $result;
}
			
		

function drbd_get($resname) {
	// Get the real name - strip of ms_drbd_ from the front of it.
	if (!preg_match('/ms_drbd_(\w+)/', $resname, $name)) {
		return null;
	}
	$resname=$name[1];
	// Firstly, do we know about this resource? 
	if (!file_exists("/etc/drbd.d/$resname.res")) {
		// One of those things we should never hit, but...
		$ret['status'] = "MISSING";
		$ret['this'] = "MISSING";
		$ret['other'] = "MISSING";
		return $ret;
	}
	// Oh god, this is horrible.
	$drbdno = trim(`grep device /etc/drbd.d/$resname.res | sed 's/\(.*drbd\)\(.\)./\\2/'`);
	$drbdline = trim(`grep "^ $drbdno:" /proc/drbd`);
	if (strstr($drbdline, 'cs:Unconfigured') || $drbdline == "") {
		$ret['status'] = "Down";
		$ret['this']['role'] = "Unknown";
		$ret['this']['status'] = "Unknown";
		$ret['other']['role'] = "Unknown";
		$ret['other']['status'] = "Unknown";
		return $ret;
	}
	preg_match('_ cs:(\w+) ro:(\w+)/(\w+) ds:(\w+)/(\w+) _', $drbdline, $tmparray);
	$ret['status'] = $tmparray[1];
	$ret['this']['role'] = $tmparray[2];
	$ret['other']['role'] = $tmparray[3];
	$ret['this']['status'] = $tmparray[4];
	$ret['other']['status'] = $tmparray[5];
	return $ret;
}

