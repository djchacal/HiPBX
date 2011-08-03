<?php

/**
 * FreePBX DAHDi Config Module
 *
 * Copyright (c) 2009, Digium, Inc.
 *
 * Author: Ryan Brindley <ryan@digium.com>
 *
 * This program is free software, distributed under the terms of
 * the GNU General Public License Version 2. 
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

global $db;

/**
 * DAHDI CONF
 * 
 * This class contains all the functions to configure asterisk via freepbx
 */
 class dahdiconfig_conf {
	var $cards;

 	public function dahdiconfig_conf() {
		$this->cards = new dahdi_cards();

		$this->cards->dahdi_cfg();
		$this->cards->write_modprobe();
	}

	public function get_filename() {
		return array('chan_dahdi_general.conf', 'chan_dahdi_groups.conf');
	}

	public function generateConf($file) {
		switch($file) {
		case 'chan_dahdi_general.conf':
			$output = array();
		
			if ( ! $this->cards->get_advanced('mwi_checkbox')) {
				return '';	
			}

			if ($this->cards->get_advanced('mwi') == 'fsk') {
				$output[] = "mwimonitor=fsk";
				$output[] = "mwilevel=512";
				$output[] = "mwimonitornotify=__builtin__";
			} else if ($this->cards->get_advanced('mwi') == 'neon') {
				$output[] = "mwimonitor=neon";
				$output[] = "mwimonitornotify=__builtin__";
			}

			return implode("\n", $output);
		case 'chan_dahdi_groups.conf':
			$output = array();

			foreach ($this->cards->get_spans() as $key=>$span) {
				if ($span['signalling'] == '') {
					continue;
				}

				$output[] = "";
				$output[] = "; [span_{$key}]";	
				$output[] = "signalling={$span['signalling']}";
				$output[] = "switchtype={$span['switchtype']}";
				$output[] = "pridialplan={$span['pridialplan']}";
				$output[] = "prilocaldialplan={$span['prilocaldialplan']}";
				$output[] = "group={$span['group']}";
				$output[] = "context={$span['context']}";
				$output[] = "channel=".$this->cards->calc_bchan_fxx($key);
			}

			foreach ($this->cards->get_analog_ports() as $num=>$port) {
				if ($port['type'] == '') {
					continue;
				}

				$output[] = "";
				$output[] = "signalling=".(($port['type']=='fxo')?'fxs':'fxo')."_{$port['signalling']}";
				$output[] = "context={$port['context']}";
				if (isset($port['group']) && $port['group'] != 0) {
					$output[] = "group={$port['group']}";
				}
				$output[] = "channel=>{$num}";
			}

			return implode("\n", $output);
		default:
			return '';
		}
	}

 }

/**
 * DAHDI CARDS
 *
 * This class contains all the functions necessary to manage DAHDi hardware.
 */
class dahdi_cards {
	private $analog_ports = array();	// stores all analog port info
	private $advanced = array(		// advanced array of values
		'module_name'=>'wctdm24xxp',
		'tone_region'=>'us',
		'opermode_checkbox'=>0,
		'opermode'=>'USA',
		'alawoverride_checkbox'=>0,
		'alawoverride'=>0,
		'fxs_honor_mode_checkbox'=>0,
		'fxs_honor_mode'=>0,
		'boostringer_checkbox'=>0,
		'boostringer'=>0,
		'fastringer_checkbox'=>0,
		'fastringer'=>0,
		'lowpower_checkbox'=>0,
		'lowpower'=>0,
		'ringdetect_checkbox'=>0,
		'ringdetect'=>0,
		'mwi_checkbox'=>0,
		'mwi'=>'none',
		'neon_voltage'=>'',
		'neon_offlimit'=>'',
		'echocan_nlp_type'=>0,
		'echocan_nlp_threshold'=>'',
		'echocan_nlp_max_supp'=>'' );
	private $configured_hdwr = array(); 	// The hardware already configured
	private $channels = array();		
	private $chan_dahdi_conf;		// /etc/asterisk/chan_dahdi_additional.conf
	private $dahdi_scan;			// The output of a DAHDi scan
	private $detected_hdwr = array();	// Existing hardware on the sys
	private $drivers_list = array(		// List of available drivers
		'tor2', 
		'wcb4xxp', 
		'wcfxo', 
		'wct1xxp', 
		'wct4xxp', 
		'wctc4xxp', 
		'wctdm24xxp', 
		'wctdm', 
		'wcte11xp', 
		'wcte12xp', 
		'wcusb', 
		'xpp_usb' );
	private $echocans = array(		// List of possible echo cancellers
		'mg2'=>0, 
		'kb1'=>1, 
		'sec'=>2, 
		'sec2'=>3, 
		'hpec'=>4 );
	private $error_msg = '';		// The latest error message
	private $fxo_ports = array();
	private $fxs_ports = array();
	private $groups = array();		// DAHDi Groups
	private $hardware = array();		
	private $has_analog_hdwr = FALSE;	// If the sys has analog hardware
	private $has_vpm = FALSE;		// If the sys has echo can
	private $has_digital_hdwr = FALSE;	// If the sys has digital hardware
	private $hdwr_changes = FALSE;		// If hardware has changed since last configure
	private $module_name = 'wctdm24xxp';	// The module used
	private $ports_signalling = array(	// The ports signalling
		'ls' => array(),
		'ks' => array() );
	private $spancount = array();		// Per location
	private $spans = array();		// The current spans
	private $system_conf;			// /etc/dahdi/system.conf

	/**
	 * Constructor
	 */
	public function dahdi_cards () {
		if (!is_file('/etc/dahdi/system.conf')) {
			$this->dahdi_genconf();
		}

		$this->load();
	}

	/**
	 * Calc Bchan Fxx
	 *
	 * Calculates the bchan and fxx strings for a given span
	 */
	 public function calc_bchan_fxx($num) {
	 	$span = $this->spans[$num];
		$y = $span['min_ch'];
		
		if ($span['totchans'] == 3) {
			return "$y-".($y+1);
		}

		$z = $span['definedchans'];
		if ($z === 1) {
			return $y;
		}

		if (isset($span['signalling']) && $span['signalling'] != "" && (substr($span['signalling'],0,3) !== 'pri')) {
			return "$y-".($y+$z);
		}

		if ($span['totchans'] <= 24) {
			return "$y-".($y+$z-1);
		}

		if ($z == 16) {
			return "$y-".($y+14).",".($y+16);
		} else if ($z < 16) {
			return "$y-".($y+$z-1);
		} else {
			return "$y-".($y+14).",".($y+16)."-".($y+$z-1);
		}
	 }

	/**
	 * DAHDi Gen Conf
	 *
	 * Run dahdi_genconf to generate system.conf
	 */
	 public function dahdi_genconf() {
		return `/usr/sbin/dahdi_genconf`;
	 }

	 /**
	  * DAHDi Config
	  *
	  * Run dahdi_cfg
	  */
	  public function dahdi_cfg() {
		return `/usr/sbin/dahdi_cfg`;
	  }

	/**
	 * Detect Hardware Changes
	 *
	 * Compare the known hardware with the current sys hardware and report
	 * if there are any changes
	 *
	 * @access public
	 * @return bool
	 */
	public function detect_hdwr_changes() {
		if ( count($this->detected_hdwr) != count($this->configured_hdwr)) {
			return TRUE;
		}

		foreach ($this->detected_hdwr as $location=>$detected) {
			if ( ! isset($this->configured_hdwr[$location])) {
				return TRUE;
			}

			$configured = $this->configured_hdwr[$location];

			$fields = array('device', 'basechan', 'type');
			foreach ($fields as $fld) {
				if ($configured[$fld] == $detected[$fld]) {
					continue;
				}

				if ($fld == 'analog' && $detected[$fld] == 'analog') {
					if (gettype($configured[$fld]) != gettype($detected[$fld])) {
						return TRUE;
					} else if (count($configured[$fld]) != count($detected[$fld])) {
						return TRUE;
					}

					
					for($i=0; $i<count($detected['port']); $i++) {
						if ($configured['port'][$i] == $detected['port'][$i]) {
							continue;
						}

						return TRUE;
					}

					continue;
				}

				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Get Advanced
	 *
	 * Get an advanced parameter
	 */
	 public function get_advanced($param) {
		return $this->advanced[$param];
	 }

	/**
	 * Get All Advanced
	 *
	 * Get all advanced parameters
	 */
	 public function get_all_advanced() {
		return $this->advanced;
	 }

	/**
	 * Get Analog Ports
	 *
	 * Get all analog port info
	 */
	 public function get_analog_ports() {
		return $this->analog_ports;
	 }

	/**
	 * Get Channels
	 *
	 * Get the channels array
	 *
	 * @access public
	 * @return array
	 */
	public function get_channels() {
		return $this->channels;
	}

	/**
	 * Get Hardware
	 *
	 * Get the hardware array
	 *
	 * @access public
	 * @return array
	 */
	public function get_hardware() {
		return $this->hardware;
	}

	/**
	 * Get FXO Ports
	 *
	 * Get the FXO ports
	 *
	 * @access public
	 * @return array
	 */
	public function get_fxo_ports() {
		return $this->fxo_ports;
	}

	/**
	 * Get FXS Ports
	 *
	 * Get the FXS ports
	 *
	 * @access public
	 * @return array
	 */
	public function get_fxs_ports() {
		return $this->fxs_ports;
	}

	/**
	 * Get FX(O|S)LS ports
	 *
	 * Get all the FXO and FXS ports with loop start signalling
	 *
	 * @access public
	 * @return array
	 */
	public function get_ls_ports() {
		return $this->ports_signalling['ls'];
	}

	/**
	 * Get Port
	 *
	 * Get the Analog Port assoc array that is associated
	 * with the given port number
	 */
	public function get_port($num) {
		return $this->analog_ports[$num];
	}

	/**
	 * Get Span Count
	 *
	 * Get the span count per location
	 *
	 * @access public
	 * @return array
	 */
	public function get_span_count($loc) {
		return $this->spancount[$loc];
	}

	/**
	 * Get Spans
	 *
	 * Get the digital spans
	 *
	 * @access public
	 * @return array
	 */
	public function get_spans() {
		return $this->spans;
	}

	/**
	 * Get Span
	 * 
	 * Get a digital span and all its info
	 */
	public function get_span($num) {
		return $this->spans[$num];
	}

	/**
	 * Has VPM
	 * 
	 * Return if the cards have a vpm module
	 */
	public function has_vpm() {
		return $this->has_vpm;
	}

	/**
	 * Hardware Changes
	 * 
	 * Return if hdwr has changed or not
	 * 
	 * @access public
	 * @return bool
	 */
	public function hdwr_changes() {
		return $this->hdwr_changes;
	}

	/**
	 * Load
	 *
	 * Load all the information the various locations (database, system.conf, chan_dahdi.conf)
	 */
	public function load() {
		global $db;

		$this->read_configured_hdwr();
		$this->read_dahdi_scan();

		$this->hdwr_changes = $this->detect_hdwr_changes();
		if ($this->hdwr_changes) {
			$this->dahdi_genconf();
			$this->dahdi_cfg();
			$this->read_dahdi_scan();
			$this->write_detected();
			$this->write_spans();
			$this->write_analog_signalling();
		}

		$this->read_system_conf();
		$this->read_chan_dahdi_conf();
		$this->read_dahdi_advanced();
		$this->read_dahdi_analog();
		$this->read_dahdi_spans();
	}

	/**
	 * Read Configured Hardware
	 *
	 * Go check out the database and read in the configured hardware
	 */
	 public function read_configured_hdwr() {
		global $db;

		$sql = "SELECT * FROM dahdi_configured_locations ORDER BY basechan";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			if ( ! isset($this->configured_hdwr[$row['location']])) {
				$this->configured_hdwr[$row['location']] = array();
			}

			$this->configured_hdwr[$row['location']]['device'] = $row['device'];
			$this->configured_hdwr[$row['location']]['basechan'] = $row['basechan'];
			$this->configured_hdwr[$row['location']]['type'] = $row['type'];
		}
	 }

	/**
	 * Read DAHDi Advanced
	 *
	 * Load all the configuration information in /etc/dahdi/system.conf
	 */
	public function read_dahdi_advanced() {
		global $db;

		$sql = 'SELECT * FROM dahdi_advanced';

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach($results as $result) {
			$this->advanced[$result['keyword']] = ($result['val']) ? $result['val'] : $result['default_val'];
		}
	}

	/**
	 *
	 *
	 */
	public function read_dahdi_analog() {
		global $db;

		$sql = 'SELECT * FROM dahdi_analog';

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach($results as $res) {
			$this->analog_ports[$res['port']] = $res;
			if ($res['signalling'] == 'ls') {
				$this->ports_signalling['ls'][] = $res['port'];
			} else {
				$this->ports_signalling['ks'][] = $res['port'];
			}
		}
	}

	/**
	 * Read DAHDi Spans
	 *
	 * Read in all the dahdi_spans info from the database
	 */
	public function read_dahdi_spans() {
		global $db;

		$sql = 'SELECT * FROM dahdi_spans';

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $span) {
			foreach ($span as $key=>$val) {
				if ($val == '') {
					continue;
				}

				$this->spans[$span['span']][$key] = $val;
			}
		}
	}

	/**
	 * Read System Conf
	 *
	 * Load all the configuration information in /etc/dahdi/system.conf
	 */
	public function read_system_conf() {
		
		$nomore = false;
		$ctr = 0;
		do {
			$this->system_conf = file_get_contents('/etc/dahdi/system.conf');
			if (! $this->system_conf) {
				return FALSE;
			}

			$lines = explode("\n", $this->system_conf);

			$hasaline = false;
      foreach ($lines as $line) {
				// its a comment, like this line
				if (substr($line,0,1) == '#' || trim($line) == '') {
					continue;
				}
				$hasaline = true;
        break;
			}

			if ( ! $hasaline) {
				$this->dahdi_genconf();
			}

			$ctr++;
			if ($ctr > 2) {
				$nomore = true;
			}
		} while ( !$hasaline || $nomore);

		if ( ! $hasaline) {
			return false;
		}

		for($i=0;$i<count($lines);$i++) {

			$line = $lines[$i];
			if ($line == '' || (strpos($line, '#') === 0)) {
				continue;
			}

			$begin = substr($line, 0, 5);
			switch($begin) {
			case 'fxoks':
			case 'fxsks':
				$ks_ports = explode('=',$line);
				$ks_ports = $ks_ports[1];
				$this->ports_signalling['ks'] = array_merge($this->ports_signalling['ks'], dahdi_chans2array($ks_ports));
				break;
			case 'fxols':
			case 'fxsls':
				$ls_ports = explode('=',$line);
				$ls_ports = $ls_ports[1];
				$this->ports_signalling['ls'] = array_merge($this->ports_signalling['ls'], dahdi_chans2array($ls_ports));
				break;
			case 'echoc': //echocanceller
				$this->has_echo_can = true;
				break;
			case 'span=':
				$info = explode('=', $line);
				list($num, $timing, $lbo, $framing, $coding, $yellow) = explode(',', $info[1]);
				$this->spans[$num]['timing'] = $timing;
				$this->spans[$num]['lbo'] = $lbo;
				$this->spans[$num]['framing'] = $framing;
				$this->spans[$num]['coding'] = $coding.(($yellow)?"/$yellow":"");
				break;
			default:
			}
		}

		// write echo can if it doesn't exist, for ABE only ???
	}

	/**
	 * Read Chan Dahdi Conf
	 *
	 * Read from chan_dahdi_additional.conf and get any useful information
	 */
	 public function read_chan_dahdi_conf() {
	 	global $db;

		$sql = 'SELECT * FROM dahdi WHERE id = -1 AND keyword != "account" AND flags != 1';
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			return($results->getMessage());
		}

		$additional = array();
		foreach ($results as $result) {
			$additional[$result['keyword']] = $result['data'];
		}

		unset($results);
		unset($result);

		$sql = 'SELECT * FROM dahdi WHERE keyword != "account" and flags != 1';
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			return($results->getMessage());
		}

		$accounts = array();
		foreach ($results as $result) {
			if ( ! isset($accounts[$result['id']])) {
				$accounts[$result['id']] = array();
			}

			switch($result['keyword']) {
			case 'record_in':
			case 'record_out':
			case 'dial':
				break;
			default:
				$accounts[$result['id']][$result['keyword']] = $result['data'];
			}
		}

		unset($results);
		unset($result);
		unset($sql);

		foreach ($accounts as $account) {
			$account = array_merge($account, $additional);
			$this->channels[$account['channel']] = $account;
		}

		$sql = 'SELECT * FROM dahdi_spans';
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
		}

		$flds = array('framing', 'signalling', 'coding', 'definedchans', 'switchtype', 'syncsrc', 'lbo', 'pridialplan', 'prilocaldialplan');
		foreach ($results as $span) {
			foreach ($flds as $fld) {
				if ( ! $span[$fld]) {
					continue;
				}

				$this->spans[$span['span']][$fld] = $span[$fld];
			}
		}

		return true;
	 }

	/**
	 * Read DAHDi Scan
	 *
	 * Read all the information given in the DAHDi Scan script
	 */
	public function read_dahdi_scan() {
		$this->dahdi_scan = `/usr/sbin/dahdi_scan`;
		unset($this->fxo_ports);
		unset($this->fxs_ports);
		$this->fxo_ports = array();
		$this->fxs_ports = array();

		$lines = explode("\n", $this->dahdi_scan);
		foreach ($lines as $line) {
			if ($line == '') {
				continue;
			} else if (preg_match('/^\[([-a-zA-Z0-9_][-a-zA-Z0-9_]*)\]/', $line, $matches)) {
						$cxt = $matches[1];
						$cxts[$cxt] = array();
						continue;
			}

			list($var, $val) = explode('=', $line);

			if ($var == 'port' && strpos($val, 'FXO')) {
				$num = explode(',',$val);
				$num = $num[0];
				$this->fxo_ports[] = $num;
			} else if ($var == 'port' && strpos($val, 'FXS')) {
				$num = explode(',',$val);
				$num = $num[0];
				$this->fxs_ports[] = $num;
			}

			if ($var == 'type' && strpos($val,'analog')) {
				$this->has_analog_hdwr = TRUE;
			} else if ($var == 'type' && strpos($val, 'digital')) {
				$this->has_digital_hdwr = TRUE;
			}
		}

		/* If there is a DAHDI_Dummy then there is no hardware to parse */
		if (isset($cxts)) foreach ($cxts as $cxt) {
			if (strpos($cxt['description'],'DAHDI_DUMMY') === false) {
				continue;
			}

			return;
		}

		$spans = dahdi_config2array($this->dahdi_scan);

		if (count($spans) == 0) {
			$this->has_digital_hdwr = FALSE;
			$this->has_analog_hdwr = FALSE;
			return;
		}

		foreach ($spans as $key=>$span) {
			if (strpos($span['devicetype'], 'VPMADT032') !== FALSE) {
				$this->has_vpm = true;
			}

			if ($span['type'] == 'analog') {
				$this->hardware[$span['location']] = array();
				$this->hardware[$span['location']]['device'] = $span['devicetype'];
				$this->hardware[$span['location']]['basechan'] = $span['basechan'];
				$this->hardware[$span['location']]['type'] = $span['type'];

				$this->detected_hdwr[$span['location']] = $this->hardware[$span['location']];
				continue;
			}

			if (strpos($span['description'], 'ztdummy') !== false) {
				continue;
			}

			$this->spans[$key] = array();
			foreach ($span as $attr=>$val) {
				$this->spans[$key][$attr] = $span[$attr];

				switch($attr) {
				case 'location':
					if ( ! isset($this->spancount[$val]) ) {
						$this->spancount[$val] = 0;
					}

					$this->spancount[$val]++;
				
					if (!isset($this->hardware[$val]) ) {
						$this->hardware[$val] = array();
						$this->hardware[$val]['device'] = $span['devicetype'];
						$this->hardware[$val]['basechan'] = $span['basechan'];
						$this->hardware[$val]['type'] = $span['type'];
						$this->detected_hdwr[$span['location']] = $this->hardware[$span['location']];
					}
					break;
				case 'totchans':
					list($dummy, $this->spans[$key]['spantype']) = explode('-',$span['type']);
					$this->spans[$key]['min_ch'] = $span['basechan'];
					$this->spans[$key]['max_ch'] = $span['basechan'] + $span['totchans'] - 1;

					switch ($span['totchans']) {
					case 3:
						$this->spans[$key]['definedchans'] = 2;
						$this->spans[$key]['reserved_ch'] = $span['basechan'] + 2;
						break;
					case 24:
						$this->spans[$key]['definedchans'] = 23;
						$this->spans[$key]['reserved_ch'] = $span['basechan'] + 23;
						break;
					case 31:
						$this->spans[$key]['definedchans'] = 31;
						$this->spans[$key]['reserved_ch'] = $span['basechan'] + 15;
						break;
					default:
						$this->spans[$key]['definedchans'] = 0;
						break;
					}
					break;
				case 'lbo':
					switch($val){
					case '0 db (CSU)/0-133 feet (DSX-1)':
						$this->spans[$key]['lbo'] = 0; 
						break; 
					case '133-266 feet (DSX-1)':
						$this->spans[$key]['lbo'] = 1; 
						break; 
					case '266-399 feet (DSX-1)':
						$this->spans[$key]['lbo'] = 2; 
						break;
					case '399-533 feet (DSX-1)':
						$this->spans[$key]['lbo'] = 3; 
						break;
					case '533-655 feet (DSX-1)':
						$this->spans[$key]['lbo'] = 4; 
						break;
					case '-7.5db (CSU)':
						$this->spans[$key]['lbo'] = 5; 
						break;
					case '-15db (CSU)':
						$this->spans[$key]['lbo'] = 6; 
						break;
					case '-22.5db (CSU)':
						$this->spans[$key]['lbo'] = 7; 
						break;
					default:
						$this->spans[$key]['lbo'] = 0; 
						break; 
					}
					break;
				default:
					break;
				}
			}
		}
	}
	
	/**
	 * Set Analog Signalling
	 *
	 * Take the port number and signalling (ls/ks) and update the
	 * ports_signalling array
	 */
	public function set_analog_signalling($num, $port) {
		$opp = ($port['signalling'] == 'ks') ? 'ls' : 'ks';
		$key = array_search($num, $this->ports_signalling[$opp]);
		if ($key) {
			unset($this->ports_signalling[$opp][$key]);
		}

		if ( ! in_array($num, $this->ports_signalling[$port['signalling']])) {
			$this->ports_signalling[$port['signalling']][] = $num;
		}

		$this->analog_ports[$num]['group'] = $port['group'];
		$this->analog_ports[$num]['context'] = $port['context'];

		needreload();
	}

	/**
	 * Update DAHDi Advanced
	 *
	 * Update the database for dahdi advanced and then update the proper files
	 *
	 * @access public
	 * @param array $params An array of parameters
	 * @return bool
	 */
	public function update_dahdi_advanced($params) {
	 	global $db;

		foreach ($params as $keyword=>$val) {
			if ($val === null) {
				$sql = "UPDATE dahdi_advanced SET val=null WHERE keyword=\"{$keyword}\"";
				$this->advanced[$keyword] = null;
			} else {
				$sql = "UPDATE dahdi_advanced SET val=\"{$val}\" WHERE keyword=\"{$keyword}\"";
				$this->advanced[$keyword] = $val;
			}
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	/**
	 * Update Span
	 *
	 * Update the span info and write it to the appropriate files
	 */
	public function update_span($editspan) {
		$num = $editspan['span'];

		if ($editspan['fac'] == 'CCS/HDB3/CRC4') {
			$this->spans[$num]['framing'] = 'CCS/HDB3';
			$this->spans[$num]['coding'] = 'CRC4';
		} else {
			list($framing, $coding) = explode('/',$editspan['fac']);
			$this->spans[$num]['framing'] = $framing;
			$this->spans[$num]['coding'] = $coding;
		}

		$this->spans[$num]['fac'] = $editspan['fac'];
		$this->spans[$num]['signalling'] = $editspan['signalling'];
		$this->spans[$num]['syncsrc'] = $editspan['syncsrc'];
		$this->spans[$num]['channels'] = $editspan['channels'];
		$this->spans[$num]['switchtype'] = $editspan['switchtype'];
		$this->spans[$num]['lbo'] = $editspan['lbo'];
		$this->spans[$num]['pridialplan'] = $editspan['pridialplan'];
		$this->spans[$num]['prilocaldialplan'] = $editspan['prilocaldialplan'];
		$this->spans[$num]['group'] = $editspan['group'];
		$this->spans[$num]['context'] = $editspan['context'];
		$this->spans[$num]['definedchans'] = $editspan['definedchans'];

		$this->write_spans(); 
		$this->write_system_conf();

		needreload();
	}

	/**
	 * Write Analog Signalling
	 *
	 * Take the analog ports and write them to the dahdi_analog table
	 */
	public function write_analog_signalling() {
		global $db;

		$sql = 'TRUNCATE dahdi_analog';

		$result = $db->query($sql);
		if (DB::IsError($result)) {
			die_freepbx($result->getDebugInfo());
		}
		unset($result);

		$ports = array();
		foreach ($this->fxo_ports as $fxo) {
			if (in_array($fxo, $this->ports_signalling['ls'])) {
				$sig = 'ls';
			} else {
				$sig = 'ks';
			}

			$group = (isset($this->analog_ports[$fxo]['group']))?$this->analog_ports[$fxo]['group']:0;
			$context = (isset($this->analog_ports[$fxo]['context']))?$this->analog_ports[$fxo]['context']:'from-analog';

			$ports[] = "($fxo, 'fxo', '$sig', $group, '$context')";
		}

		foreach ($this->fxs_ports as $fxs) {
			if (in_array($fxs, $this->ports_signalling['ls'])) {
				$sig = 'ls';
			} else {
				$sig = 'ks';
			}

			$group = (isset($this->analog_ports[$fxs]['group']))?$this->analog_ports[$fxs]['group']:0;
			$context = (isset($this->analog_ports[$fxs]['context']))?$this->analog_ports[$fxs]['context']:'from-analog';

			$ports[] = "($fxs, 'fxs', '$sig', $group, '$context')";
		}

		if (sizeof($ports) <= 0) {
			return true;
		}

		$sql = 'INSERT INTO dahdi_analog (port, type, signalling, `group`, context) VALUES '.implode(', ',$ports);

		$result = $db->query($sql);
		if (DB::IsError($result)) {
			die_freepbx($result->getDebugInfo());
		}

		$this->write_system_conf();

		return true;
	}

	/**
	 * Write Detected
	 *
	 * Write the detected hardware to the dahdi_configured_locations table
	 */
	public function write_detected() {
		global $db;

		$sql = "TRUNCATE TABLE dahdi_configured_locations";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			die_freepbx($result->getDebugInfo());
		}
		unset($result);

		$flds = array('location', 'device', 'basechan', 'type');
		$inserts = array();
		foreach ($this->detected_hdwr as $loc=>$hdwr) {
			$insert = array();
			foreach ($flds as $fld) {
				if ($fld == 'location') {
					$insert[] = "'$loc'";
				} else {
					$insert[] = "'{$hdwr[$fld]}'";
				}
			}

			$inserts[] = '('.implode(', ',$insert).')';
		}
		$sql = 'INSERT INTO dahdi_configured_locations ('.implode(', ',$flds).') VALUES '.implode(', ',$inserts);

		$result = $db->query($sql);
		if (DB::IsError($result)) {
			die_freepbx($result->getDebugInfo());
		}

		return true;
	}

	/**
	 * Write Spans
	 *
	 * Write the current spans to the database
	 */
	public function write_spans() {
		global $db;

		$sql = "TRUNCATE dahdi_spans";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			die_freepbx($result);
		}
		unset($result);

		$flds = array('span', 'framing', 'definedchans', 'coding', 'signalling', 'switchtype', 'syncsrc', 'lbo', 'pridialplan', 'prilocaldialplan', 'group', 'context');

		$sql = 'INSERT INTO dahdi_spans (`'.implode('`, `',$flds).'`) VALUES ';
		$inserts = array();
		foreach ($this->spans as $key=>$span) {
			$values = array();

			foreach ($flds as $fld) {
				if ($fld == 'span') {
					$values[] = "'$key'";
				} else {
					$values[] = "'{$span[$fld]}'";
				}
			}

			$inserts[] = '('.implode(', ',$values).')';
			unset($values);
		}
		$sql .= implode(', ', $inserts);

		$result = $db->query($sql);
		if (DB::IsError($result)) {
			die_freepbx($result->getDebugInfo());
		}
		unset($result);
	}

	/**
	 * Write System Conf
	 *
	 * Take all the information received and write a new /etc/dahdi/system.conf
	 */
	public function write_system_conf() {
	  	$fxx = array();
	   	$output = array();
		$bchan = '';
		$dchan = '';
		$hardhdlc = '';

		foreach ($this->spans as $num=>$span) {
			if ( ! $span['signalling']) {
				continue;
			}

			$span['fac'] = str_replace('/',',',$span['framing']).','.$span['coding'];
			$span['lbo'] = ($span['lbo']) ? $span['lbo'] : 0;
			$span['syncsrc'] = (isset($span['syncsrc'])) ? $span['syncsrc'] : 1;

			$spanline = "{$num},{$span['syncsrc']},{$span['lbo']},{$span['fac']}";

			$output[] = "span={$spanline}";

			$chan = $this->calc_bchan_fxx($num);

			if ( substr($span['signalling'],0,3) != 'pri' && substr($span['signalling'],0,3) != 'bri') {
				if (substr($span['signalling'],0,2) == 'fx') {
					$fx = str_replace('_','',$span['signalling']);
				} else {
					$fx = 'e&m';
				}

				if ($fxx[$fx]) {
					$fxx[$fx] .= ",{$chan}";
				} else {
					$fxx[$fx] = $chan;
				}
			} else if (substr($span['signalling'],0,3) == 'pri') {
				$bchan .= ($bchan) ? ",$chan" : "$chan";
				$dchan .= ($dchan) ? ",{$span['reserved_ch']}" : "{$span['reserved_ch']}";
			} else {
				$bchan .= ($bchan) ? ",$chan" : "$chan";
				$hardhdlc .= ($hardhdlc) ? ",{$span['reserved_ch']}" : "{$span['reserved_ch']}";
			}

			$this->spans[$num]['dahdichanstring'] = $chan;
		}

		foreach ($fxx as $e=>$val) {
			$output[]  = "$e={$val}";
		}

		if ($bchan) {
			$output[] = "bchan={$bchan}";
		}

		if ($dchan) {
			$output[] = "dchan={$dchan}";
		}

		if ($hardhdlc) {
			$output[]  = "hardhdlc={$hardhdlc}";
		}

		$fxols = array();
		$fxoks = array();
		$fxsls = array();
		$fxsks = array();

		foreach ($this->fxo_ports as $fxo) {
			if (in_array($fxo, $this->ports_signalling['ls'])) {
				$fxols[] = $fxo;
			} else {
				$fxoks[] = $fxo;
			}
		}

		foreach ($this->fxs_ports as $fxs) {
			if (in_array($fxs, $this->ports_signalling['ls'])) {
				$fxsls[] = $fxs;
			} else {
				$fxsks[] = $fxs;
			}
		}

		if ($fxols) {
			$output[] = "fxsls=".dahdi_array2chans($fxols);
		}
		if ($fxoks) {
			$output[] = "fxsks=".dahdi_array2chans($fxoks);
		}
		if ($fxsls) {
			$output[] = "fxols=".dahdi_array2chans($fxsls);
		}
		if ($fxsks) {
			$output[] = "fxoks=".dahdi_array2chans($fxsks);
		}

		$output[] = "loadzone={$this->advanced['tone_region']}";
		$output[] = "defaultzone={$this->advanced['tone_region']}";

		$fh = fopen('/etc/dahdi/system.conf', 'w');
		$output = implode("\n", $output);
		fwrite($fh, $output); 
		fclose();
	}

	/**
	 * Write Modprobe
	 * 
	 * Write all the modprob options to modprobe.conf
	 */
	public function write_modprobe() {
		$file = "/etc/modprobe.d/dahdi.conf";

		if ( ! is_writable($file)) {
			echo "not writable";
		}

		$fh = fopen($file, 'w');

		fwrite($fh, "#******************************************#\n");
		fwrite($fh, "#* Auto-generated by FreePBX, do not edit *#\n");
		fwrite($fh, "#******************************************#\n");

		$options = "options {$this->advanced['module_name']}";

		$opts = array('opermode'=>'opermode', 'alawoverride'=>'alawoverride', 'boostringer'=>'boostringer', 'lowpower'=>'lowpower', 'fastringer'=>'fastringer', 'ringdetect'=>'fwringdetect', 'fxs_honor_mode'=>'fxshonormode');
		foreach ($opts as $opt=>$name) {
			if ( ! $this->advanced["{$opt}_checkbox"]) {
				continue;	
			}

			$options .= " {$name}={$this->advanced[$opt]}";
		}

		if ($this->advanced["mwi_checkbox"]) {
			if ($this->advanced['mwi'] == 'neon') {
				$options .= " neonmwi_monitor=1";
				if ($this->advanced['neon_voltage']) {
					$options .= " neonmwi_level={$this->advanced['neon_voltage']}";
				}
				if ($this->advanced['neon_offlimit']) {
					$options .= " neonmwi_offlimit={$this->advanced['neon_offlimit']}";
				}
			} else {
				$options .= " neonmwi_monitor=0";
			}
		}

		$opts = array('echocan_nlp_type'=>'vpmnlptype', 'echocan_nlp_threshold'=>'vpmnlpthresh', 'echocan_nlp_max_supp'=>'vpmnlpmaxsupp');
		foreach ($opts as $adv=>$opt) {
			if ( ! $this->advanced[$adv]) {
				continue;
			}

			$options .= " {$opt}={$this->advanced[$adv]}";
		}

		fwrite($fh,$options);
		fclose($fh);
	}
}

function dahdi_config2array ($config) {
	if (! is_array($config)) {
		$config = explode("\n", $config);	
	}

	$cxts = array();
	$cxt = '';

	unset($config[count($config)-1]);
	
	for($i=0;$i<count($config);$i++) {
		unset($matches);
		if ($config[$i] == '') {
			continue;
		} else if (preg_match('/^\[([-a-zA-Z0-9_][-a-zA-Z0-9_]*)\]/', $config[$i], $matches)) {
			$cxt = $matches[1];
			$cxts[$cxt] = array();
			continue;
		}

		if ($cxt == '') {
			continue;
		}

		list($var, $val) = explode('=',$config[$i]);
		
		if (isset($cxts[$cxt][$var])) {
			if (gettype($cxts[$cxt][$var]) !== 'array') {
				$cxts[$cxt][$var] = array($cxts[$cxt][$var]);
			}

			$cxts[$cxt][$var][] = $val;
		} else {
			$cxts[$cxt][$var] = $val;
		}
	}

	return $cxts;
}

function dahdi_chans2array($chans=null) {
	if (!$chans || $chans = '') {
		return array();
	}

	$chanarray = array();
	
	if (strpos($chans,',') && strpos($chans,'-')) {
		$segs = explode(',',$chans);
		foreach ($segs as $seg) {
			if (strpos($chans,'-')) {
				list($start, $end) = explode('-',$chans);
				for($i=$start;$i<=$end;$i++) {
					$chanarray[] = $i;
				}
				continue;
			}

			$chanarray[] = $seg;
		}
	} else if (strpos($chans,',')) {
		$chanarray = explode(',',$chans);	
	} else if (strpos($chans,'-')) {
		list($start,$end) = explode('-',$chans);
		for($i=$start; $i<=$end; $i++) {
			$chanarray[] = $i;
		}
	} else {
		$chanarray = array($chans);
	}

	return $chanarray;
}

function dahdi_array2chans($arr) {
	$chans = array();;
	$seq = 0;
	for($i=0;$i<count($arr);$i++) {
		$last_write = false;
		if ( $i != 0 && $arr[$i] - $arr[$i-1] == 1) {
			$seq++;
			if ($i+1 == count($arr)) {
				$chans[] = "{$arr[$i-$seq]}-{$arr[$i]}";
				$last_write = true;
			}
		} else if ($seq > 0) {
			$chans[] = "{$arr[$i-1-$seq]}-{$arr[$i-1]}";
			$seq = 0;
		} else if ($i != 0) {
			$chans[] = "$arr[$i]";
		}
		
		if ( !$last_write && $i+1 == count($arr)) {
			$chans[] = "$arr[$i]";
		}
	}

	return implode(',',$chans);
}

// list unused DAHDI fxs channels that can be configured for extensions
//
function dahdiconfig_get_unused_fxs_channels($current_device='') {
  $all_channels = sql('SELECT * FROM dahdi_analog WHERE type = "fxs"','getAll',DB_FETCHMODE_ASSOC); 
  $used_channels = sql('SELECT id device, data port FROM dahdi WHERE keyword = "channel"','getAll',DB_FETCHMODE_ASSOC); 
  $used_channels_hash = array();
  foreach ($used_channels as $chan) {
    $used_channels_hash[$chan['port']] = $chan['device'];
  }

  $avail_channels = array();
  foreach ($all_channels as $chan) {
    if (isset($used_channels_hash[$chan['port']])) {
      if ($current_device == $used_channels_hash[$chan['port']]) {
        $selected = true;
      } else {
        continue;
      }
    } else {
      $selected = false;
    }
    $avail_channels[] = array('channel' => $chan['port'], 'signalling' => 'fxo_'.$chan['signalling'], 'selected' => $selected);
  }
  return $avail_channels;
}

function _dahdiconfig_gsort($a, $b) {
  $gn_a = substr($a,1);
  $gn_b = substr($b,1);
  if ($gn_a == $gn_b) {
    return ($b > $a);
  } else {
    return ($gn_a > $gn_b);
  }
}

function dahdiconfig_get_unused_trunk_options($current_identifier='') {
  global $amp_conf;
  $avail_group = array();
  $analog_chan = array();
  $digital_chan = array();

  $dahdi_cards = new dahdi_cards();
  $analog_ports = $dahdi_cards->get_fxo_ports();

  // Get Analog Groups and Channels for FXO
  //
  foreach ($analog_ports as $port) {
    $port_details = $dahdi_cards->get_port($port);
    $grp = $port_details['group'];
    $chan = (string) $port_details['port'];
    $avail_group["g$grp"] = array('identifier' => "g$grp",'name' => "Group $grp Ascending",'alarms' => '','selected'  => ($current_identifier == "g$grp"));
    $avail_group["G$grp"] = array('identifier' => "G$grp",'name' => "Group $grp Descending",'alarms' => '','selected' => ($current_identifier == "G$grp"));
    $analog_chan[$chan] = array('identifier' => $chan, 'name' => "Analog Channel $chan",'alarms' => '','selected' => ($current_identifier == $chan));
  }
  // Get Digital Groups and Channels. Channels are not that useful
  // but can be helpful when testing bad channels
  //
  $digital_spans = $dahdi_cards->get_spans();
  foreach ($digital_spans as $span) {
    if (!$span['active']) {
      continue;
    }
    $grp = $span['group'];
    $alarms = $span['alarms'];
    if (!isset($avail_group["g$grp"])) {
      $avail_group["g$grp"] = array('identifier' => "g$grp",'name' => "Group $grp Ascending",'alarms' => $alarms,'selected'  => ($current_identifier == "g$grp"));
      $avail_group["G$grp"] = array('identifier' => "G$grp",'name' => "Group $grp Descending",'alarms' => $alarms,'selected' => ($current_identifier == "G$grp"));
    } else {
      //TODO: figure out the possible alarms and the create proper hiearchy of what to report
      //
      if ($alarms == 'RED' || $avail_group["g$grp"]['alarms'] == '') {
        $avail_group["g$grp"]['alarms'] = $alarms;
        $avail_group["G$grp"]['alarms'] = $alarms;
      }
    }
    if ($amp_conf['DAHDISHOWDIGITALCHANS']) {
      $basechan = $span['basechan'];
      $definedchans = $span['definedchans'];
      $topchan = $basechan + $definedchans;
      for ($port = $basechan; $port < $topchan; $port++) {
        $digital_chan["$port"] = array('identifier' => "$port", 'name' => "Digital Channel $port",'alarms' => $alarms,'selected' => ($current_identifier == "$port"));
      }
    }
  }
  uksort($avail_group,'_dahdiconfig_gsort');
  ksort($analog_chan);
  if ($amp_conf['DAHDISHOWDIGITALCHANS']) {
    ksort($digital_chan);
    $avail_identifiers = $avail_group + $analog_chan + $digital_chan;
  } else {
    $avail_identifiers = $avail_group + $analog_chan;
  }
  $trunk_list = core_trunks_listbyid();
  foreach ($trunk_list as $trunk) {
    if ($trunk['tech'] != 'dahdi' || $trunk['channelid'] == $current_identifier) {
      continue;
    }
    unset($avail_identifiers[$trunk['channelid']]);
  }
  return ($avail_identifiers);
}

function dahdiconfig_configpageinit($dispnum) {
global $currentcomponent;
switch ($dispnum) {
  case 'devices':
  case 'extensions':
    // if tech_hardware set, this is an initial extension/device creation
    // otherwise, determine if the target extension/device is DAHDI
    //
    if (isset($_REQUEST['tech_hardware']) && $_REQUEST['tech_hardware'] == 'dahdi_generic') {
      $extdisplay = '';
    } else {
      if (!isset($_REQUEST['extdisplay']) || $_REQUEST['extdisplay'] == '') {
        return true;
      }
      $extdisplay = $_REQUEST['extdisplay'];
      $device_info = core_devices_get($extdisplay);
      if (empty($device_info) || $device_info['tech'] != 'dahdi') {
        return true;
      }
    }
  
	  $channel_select  = dahdiconfig_get_unused_fxs_channels($extdisplay);
    $currentcomponent->addoptlistitem('dahdi_channel_select', '', _('==Choose=='));
	  foreach ($channel_select as $val) {
		  $currentcomponent->addoptlistitem('dahdi_channel_select', $val['channel'].':'.$val['signalling'], $val['channel']);
	  }
	  $currentcomponent->setoptlistopts('dahdi_channel_select', 'sort', false);
  break;
  case 'trunks':
    if (isset($_REQUEST['tech']) && strtolower($_REQUEST['tech']) == 'dahdi') {
      $extdisplay = '';
      $_REQUEST['dahdi_current_channel'] = '';
    } else {
      if (!isset($_REQUEST['extdisplay']) || $_REQUEST['extdisplay'] == '') {
        return true;
      }
      $extdisplay = $_REQUEST['extdisplay'];
      $trunknum = ltrim($extdisplay,'OUT_');
      $trunk_details = core_trunks_getDetails($trunknum);
      $tech = $trunk_details['tech'];
      if ($tech != 'dahdi') {
        return true;
      }
      $_REQUEST['dahdi_current_channel'] = $trunk_details['channelid'];
    }
    // dahdiconfig_hook_core will see this and create the needed selelect structure
    //
    $_REQUEST['display_dahdi_select'] = 'true';
  break;
  default;
    return true;
  break;
  }
  $currentcomponent->addguifunc("dahdiconfig_{$dispnum}_configpageload");
}

function dahdiconfig_hook_core($viewing_itemid, $target_menuid) {
  global $tabindex;
  $html = '';
  if ($target_menuid == 'trunks' && isset($_REQUEST['display_dahdi_select']) && $_REQUEST['display_dahdi_select'] == 'true') {

    // TODO: If list is exhausted, write message that no options left
    //
    $avail_trunks = dahdiconfig_get_unused_trunk_options($_REQUEST['dahdi_current_channel']);
    if (!empty($avail_trunks)) {
      $html = '
				<tr>
					<td>
						<a href=# class="info">' . _("DAHDI Trunks") . '<span>' . _("Available DAHDI Groups and Channels configued in the DAHDI Configuration Module") . '</span></a>: 
          </td>
          <td>
            <select name="dahdi_trunks" id="dahdi_trunks" tabindex="' . ++$tabindex . '">
      ';
      foreach ($avail_trunks as $ident) {
        $selected = $ident['selected'] ? ' SELECTED' : '';
        $html .= "
            <option value='{$ident['identifier']}'$selected>{$ident['name']}
        ";
      }
      $html .= "
            </select>
					</td>
        </tr>
      ";
    } else {
      $html .= '
				<tr>
					<td colspan="2">
						<a href=# class="info">' . _("No Available Groups or Channels") . '<span>' . _("There are no DAHDI Groups or Channels available to be configured. Check the DAHDI module (linked below) to configure any un-used cards") . '</span></a> 
          </td>
				</tr>
      ';
    }

	  $URL = $_SERVER['PHP_SELF'].'?'.'display=dahdi';
    $html .= '
				<tr><td colspan="2"><input type="hidden" id="dahdi_trunks" value=""></td></tr>
				<tr>
					<td colspan="2">
						<a href="'.$URL.'" class="info">' . _("Configure/Edit DAHDI Cards") . '<span>' . _("Configure/Edit DAHDI Card settings in DAHDi Module") . '</span></a> 
          </td>
				</tr>
    ';
  }
  return $html;
}

//hook gui function
//
function dahdiconfig_devices_configpageload() {
  dahdiconfig_configpageload('device');
}
function dahdiconfig_extensions_configpageload() {
  dahdiconfig_configpageload('extension');
}
function dahdiconfig_configpageload($mode) {
  global $currentcomponent;

	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

  $dahdi_channel_select = $currentcomponent->getoptlist('dahdi_channel_select');
  if (!empty($dahdi_channel_select)) {
    // Generate Channel Select, on submit populuate device channel, dial and signalling fields
    $currentcomponent->addguielem('Device Options', new gui_selectbox(
      'dahdi_channel', 
      $dahdi_channel_select,
      '',
      _('Channel'), 
      sprintf(_('Choose the FXS channel for this %s'),$mode),
    false, 
      "javascript:if (document.frm_{$mode}s.dahdi_channel.value) {parts = document.frm_{$mode}s.dahdi_channel.value.split(':');document.frm_{$mode}s.devinfo_channel.value = parts[0];document.frm_{$mode}s.devinfo_dial.value = 'DAHDI/'+parts[0];document.frm_{$mode}s.devinfo_signalling.value = parts[1]; } else { document.frm_{$mode}s.devinfo_channel.value = ''}"
    ));

    // On pageload hide channel, signalling and dial fields and select dahdi_channel based on channel field's contents
    $js = '<script type="text/javascript">
      $(document).ready(function(){
        $("#dahdi_channel").val($("#devinfo_channel").val()+":"+$("#devinfo_signalling").val());
        if ($("#dahdi_channel").val() == null) {
          $("#dahdi_channel").val("");
        }
        $("#devinfo_channel").parent().parent().hide();
        $("#devinfo_signalling").parent().parent().hide();
        $("#devinfo_dial").parent().parent().hide();
      });
    </script>';
    $currentcomponent->addguielem('Device Options', new guielement('dahdi-chan-html', $js, ''));
  } else {
    // No available channels so display that and hide channel, signalling and dial fields
	  $currentcomponent->addguielem('Device Options', new gui_label('no_dahdi_channel', _('No Unused DAHDi Channels Available')));
    $js = '<script type="text/javascript">
      $(document).ready(function(){
        $("#devinfo_channel").parent().parent().hide();
        $("#devinfo_signalling").parent().parent().hide();
        $("#devinfo_dial").parent().parent().hide();
      });
    </script>';
    $currentcomponent->addguielem('Device Options', new guielement('dahdi-chan-html', $js, ''));
  }
}

function dahdiconfig_trunks_configpageload() {
  global $currentcomponent;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

  $js = '
  <script type="text/javascript">
    $(document).ready(function(){
      $("input[name=\'channelid\']").attr("id","channelid").val($("#dahdi_trunks").val()).parent().parent().hide();
      $("#dahdi_trunks").change(function(){
        $("#channelid").val(this.value);
      });
    });
  </script>';
  $currentcomponent->addguielem('_top', new guielement('dahdi-chan-html', $js, ''));
}
// End of File
