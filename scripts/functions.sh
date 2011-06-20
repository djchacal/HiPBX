#!/bin/bash

#  HiPBX Installer Script. 
#  Copyright 2011, Rob Thomas <xrobau@gmail.com>
#  Shared functions used in both the Master and Slave setup scripts

#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU Affero General Public License as
#    published by the Free Software Foundation, either version 3 of the
#    License, or (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

function cfg {
	VARNAME=$1
	VARVAL=$2
	[ ! -f /etc/hipbx.d/hipbx.conf ] && touch /etc/hipbx.d/hipbx.conf
	if [[ $VARVAL != \(* ]]; then
		VARVAL=\"$VARVAL\"
	fi
	if egrep "^${VARNAME}=" /etc/hipbx.d/hipbx.conf > /dev/null; then
		sed -i "s!^${VARNAME}=.*!${VARNAME}=$VARVAL!" /etc/hipbx.d/hipbx.conf
	else
		echo ${VARNAME}=$VARVAL >> /etc/hipbx.d/hipbx.conf
	fi
}

function selinux {
	# Check if SELinux is enabled. If it is, disable it and warn that it's been
	# done.
	if selinuxenabled; then
		echo "SELinux is enabled. I've turned it off for you. Be aware."
		sed -i s/SELINUX=enforcing/SELINUX=disabled/ /etc/selinux/config
		setenforce 0
	fi
}

function installpackages {
	yum -y groupinstall "Development tools"
	yum -y install atrpms-repo    # For fxload, iksemel and spandsp
	yum -y install epel-release # for php-pear-DB, soon to be removed as a prereq.
	yum -y install bc vim
	yum -y install libusb-devel 
	yum -y install fxload
	yum -y install iksemel iksemel-devel
	yum -y install httpd php php-fpdf
	yum -y install mysql-server
	yum -y install curl
	yum -y install mysql mysql-devel
	yum -y install php-pear-DB php-process
	yum -y install libxml2-devel ncurses-devel libtiff-devel libogg-devel
	yum -y install libvorbis vorbis-tools
	yum -y install pacemaker
}

function disableall {
	chkconfig mysqld off
	service mysqld status > /dev/null && service mysqld stop
	chkconfig httpd off
	service httpd status > /dev/null &&  service httpd stop
	chkconfig iptables off
	service iptables status > /dev/null && service iptables stop
	chkconfig drbd off
	service drbd status > /dev/null && service drbd stop
	modprobe drbd
}


function hipbx_init {
	# Make the /etc/hipbx.d directory if it doesn't already exist.
	[ ! -d /etc/hipbx.d ] && mkdir /etc/hipbx.d
	[ ! -d /var/run/heartbeat/crm ] && mkdir  -p /var/run/heartbeat/crm
}

function mysql_password {
	# Generate a MySQL password, if one hasn't already been generated.
	[ "$MYSQLPASS" = "" ] && MYSQLPASS=`tr -dc A-Za-z0-9 < /dev/urandom | head -c16`
	cfg MYSQLPASS $MYSQLPASS
}

function fix_hostname {
	if [ $ISMASTER = YES ]; then
		if [ `hostname` != master ]; then
			echo "Fixing hostname - setting to 'master'"
			hostname master
			sed -i "s/^HOSTNAME=.*/HOSTNAME=master/" /etc/sysconfig/network
		fi
	else
		if [ `hostname` != slave ]; then
			echo "Fixing hostname - setting to 'slave'"
			hostname slave
			sed -i "s/^HOSTNAME=.*/HOSTNAME=slave/" /etc/sysconfig/network
		fi
	fi
}
		

function configure_lvm {

	#### LVM Setup Begin
	# Default storage percentages.  Minimum sizes (with 50GB lvm space used) in brackets
	# MySQL = 30 (15G)
	# Asterisk = 30 (15G)
	# httpd = 20 (10G)
	# dhcpd = 10 (5G)
	# spare = 10 (5G)
	# Don't try to use decimals here. Integers only.
	# Note that changing these AFTER the cluster has been built won't work. Create
	# a new cluster. Remember how I said set aside LOTS OF SPACE? I wasn't kidding.
	[ "$SERVICES" = "" ] && SERVICES=( mysql=30 asterisk=30 httpd=20 dhcpd=10 spare=10 )

	# Parse the SERVICES variable into arrays
	NBRSVCS=$((${#SERVICES[@]} - 1))
	SANITYSIZE=0
	SERVSTRING="( "
	for element in $(seq 0 $NBRSVCS); do
		SERVSTRING="$SERVSTRING ${SERVICES[$element]} "
		SERVICENAME[$element]=`echo ${SERVICES[$element]} | awk -F= ' { print $1 } '`
		SERVICEPCNT[$element]=`echo ${SERVICES[$element]} | awk -F= ' { print $2 } '`
		SANITYSIZE=$(( $SANITYSIZE + ${SERVICEPCNT[$element]} ))
	done

	if [ $SANITYSIZE -gt 100 ]; then
		echo -e "Severe programmer fail.\n The total percentages of SERVICES is greater than 100."
		echo -e "Please fix the SERVICES variable, and then poke yourself in the eye."
		exit
	fi

	cfg SERVICES "$SERVSTRING)"

	echo "LVM Setup:"
	echo -e "\tChecking for existing LVM volumes for drbd..."
	ALLOCATED=0
	USEDSPACE=0
	REQUIRED=0
	for x in $(seq 0 $NBRSVCS); do
		echo -ne "\t\t${SERVICENAME[$x]} - "
		USED=`lvdisplay -C --noheadings --nosuffix --units g | grep drbd_${SERVICENAME[$x]} | awk ' { print $4 }'`
		varname=drbd_${SERVICENAME[$x]}
		presize=${!varname}
		[ "$presize" = "" ] && presize=0
		if [ "$USED" != "" ]; then 
			# Integerify USED.
			USED=`printf %0.f $USED`
			echo  "Found (${USED}G)"
			if [ "$presize" != "0" -a "${USED}" -lt "$presize" ]; then
				echo -e "\nSevere Error. Amount of disk space allocated to this volume is"
				echo "LESS than the amount required by DRBD. This will corrupt your"
				echo "filesystem if you force it to work. Delete the existing volumes and"
				echo "let the installer script recreate them"
				exit
			fi
			if [ "$presize" != "0" ]; then 
				realspace=$presize
			else
				realspace=$USED
			fi
			cfg drbd_${SERVICENAME[$x]} "$realspace"
			ALLOCATED=$(( $ALLOCATED + ${SERVICEPCNT[$x]} ))
			USEDSPACE=$(( $USEDSPACE + `printf %0.f $realspace` ))
			SELECTEDVG=`lvdisplay -C --noheadings --nosuffix --units g | grep drbd_${SERVICENAME[$x]} | awk ' { print $2 }'`
		else
			echo "Not Found"
			LVMAKE=( ${LVMAKE[@]-} $x )
			if [ "$presize" != "0" ]; then
				REQUIRED=$(( $REQURED + $presize ))
			fi
		fi
	done

	if [ "$LVMAKE" != "" ]; then
		echo -e "\tLooking for vg's with spare space..."
		VGS=`vgdisplay -C --noheadings --nosuffix --units g | awk ' { print $1"="$7 }'`
		SELECTEDVG=not-found  # '-' is an invalid character in a volume group, so not-found will never be a valid answer
			for vg in $VGS; do
				VGNAME=`echo $vg | awk -F= ' { print $1 } '`
				VGSPACE=`echo $vg | awk -F= ' { print $2 } '`
				if [ $VGSPACE = 0 ]; then
					echo -e "\tVG $VGNAME has no free space, skipping."
					continue
				fi

				if [ $ALLOCATED -eq 0 -a $IHATEROB = false -a $(echo "$VGSPACE < 50" | bc) -eq 1 ]; then
					echo -e "\tVG $VGNAME has less than 50G free space, skipping."
					continue
				fi

			echo -en "\tVG $VGNAME has ${VGSPACE}G free space. Use this VG? [Yn]: " 
			read usevg
			if [ "$usevg" = "" -o "$usevg" = "Y" -o "$usevg" = "y" ]; then
				SELECTEDVG=$VGNAME
				break 2
			fi
		done
	
	if [ $SELECTEDVG = not-found ]; then
		echo "Unfortunately, we couldn't agree on a VG to use. That's something you'll need to take up with your LVM."
		exit
	fi
	
	echo -e "\tYou picked $VGNAME with ${VGSPACE}G free."
	echo -en "\n\tWould you like to use all available space? [Yn]: "
	read usespace

	if [ "$usespace" = "" -o "$usespace" = "Y" -o "$usespace" = "y" ]; then
		echo -e "\tUsing all ${VGSPACE}G available."
	else
		echo -ne "\tHow much space would you like to use (in Gigabytes, minimum 50) [50]: "
		read wantedspace
		[ "$wantedspace" == "" ] && wantedspace=50
		if [ $IHATEROB = false -a $(echo "$wantedspace < 50" | bc) -eq 1 ]; then
			echo -e "\tLook. You can't expand a DRBD volume. You REALLY want to give it as much"
			echo -e "\tspace as you can at the start. If you ABSOLUTELY MUST use less than 50G,"
			echo -e "\trestart install.sh with the parameter --i-like-pain. That will bypass this"
			echo -e "\tmessage"
			exit
		fi
		VGSPACE=$wantedspace
	fi

		echo -e "\tCreating LVs..."
		# Calculate how much is allocated already, and base our calculations off that.
		if [ $ALLOCATED = 0 ]; then
			BASESIZE=$VGSPACE
		else
			BASESIZE=`echo scale=8\; $USEDSPACE / \( $ALLOCATED / 100 \) | bc`
		fi
		# Round DOWN basesize
		BASESIZE=`echo $BASESIZE - .5 | bc`
		BASESIZE=`printf %0.f $BASESIZE`
		for x in $(seq 0 $(( ${#LVMAKE[@]} - 1 )) ) ; do
			echo -ne "\t\t${SERVICENAME[${LVMAKE[$x]}]} "
			drbdvar=drbd_${SERVICENAME[${LVMAKE[$x]}]}
			if [ "${!drbdvar}" != "" ]; then
				lvsize=${!drbdvar}
			else
				lvsize=`echo ${BASESIZE}*.${SERVICEPCNT[${LVMAKE[$x]}]} - .5 | bc`
				lvsize=`printf %0.f $lvsize`
			fi
			echo "(${lvsize}G) "
			if $(lvcreate -L${lvsize}g $VGNAME -n drbd_${SERVICENAME[${LVMAKE[$x]}]} > /dev/null); then
				cfg "drbd_${SERVICENAME[${LVMAKE[$x]}]}" "${lvsize}"
			else
				echo "Something really bad has happened. I can't create the logical volume."
				echo "This is the command I ran:"
				echo -e "\tlvcreate -L${lvsize}g $VGNAME -n drbd_${SERVICENAME[${LVMAKE[$x]}]}"
				echo "Try running it yourself and see if you can fix the problem."
				exit
			fi
		done
	fi
	if [ $ISMASTER = YES ]; then
		cfg MASTER_VGNAME "$SELECTEDVG"
	else
		cfg SLAVE_VGNAME "$SELECTEDVG"
	fi
	MY_VGNAME=$SELECTEDVG
}

function check_ssh {
	echo "SSH:"
	if [ "$SSH_KEY" = "" ]; then
		echo -e "\t\$SSH_KEY not found."
		if [ -f /etc/hipbx.d/ssh_key_master -a -f /etc/hipbx.d/ssh_key_master.pub ]; then
			echo -e "\tHowever, /etc/hipbx.d/ssh_key_master exists"
		else
			rm -f /etc/hipbx.d/ssh_key_master /etc/hipbx.d/ssh_key_master.pub
			echo -en "\tGenerating Master ssh Public key..."
			ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_master -N ""
			echo "Done"
		fi
		SSH_KEY=`cat /etc/hipbx.d/ssh_key_master.pub`
	else
		echo -en "\tMaster ssh key exists"
		test=`cat /etc/hipbx.d/ssh_key_master.pub 2>/dev/null`
		if [ "$SSH_KEY" != "$test" ]; then
			echo -e " - but doesn't match hipbx.conf! Regenerating."
			rm -f /etc/hipbx.d/ssh_key_master /etc/hipbx.d/ssh_key_master.pub
			echo -en "\tGenerating Master ssh Public key..."
			ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_master -N ""
			SSH_KEY=`cat /etc/hipbx.d/ssh_key_master.pub`
			echo "Done"
		else
			echo " and seems valid"
		fi
	fi
	cfg SSH_KEY "$SSH_KEY"
}

function add_ssh {
	if [ ! -f $HOME/.ssh/authorized_keys ]; then
		echo -n "Creating SSH authorized_keys file on local machine..."
		# Authorized Keys file doesn't exist. Create it.
		cp /etc/hipbx.d/ssh_key_master.pub $HOME/.ssh/authorized_keys
		echo "Done"
	else
		# It exists. Check to see if our key is in there
		if grep "$SSH_KEY" $HOME/.ssh/authorized_keys > /dev/null; then
			echo "HiPBX SSH Key already exists in authorized_keys"
		else
			echo -n "Adding HiPBX SSH Key to authorized_keys..."
			cat /etc/hipbx.d/ssh_key_master.pub >> $HOME/.ssh/authorized_keys
			echo "Done"
		fi
	fi
}
	

function config_networking {
	echo "Networking:"
	INTS=( `ip -o addr | grep -v "1: lo" |grep -v secondary | grep inet\ | awk '{print $9"="$4}'| sed 's^/[0-9]*^^'` )
	echo -e "\tThere needs to be at least two Ethernet Inferfaces for the cluster"
	echo -e "\tto work. The first interface is the 'internal' link. This should be"
	echo -e "\ta crossover cable, or even better, a pair of crossover cables"
	echo -e "\tbonded together, that links the two machines. There should NOT be a"
	echo -e "\tnetwork swtich on the internal link. "
	echo -e "\tThe second interface is your external network. This again should be"
	echo -e "\ta bonded interface, preferrably going to two seperate switches."
	echo -e "\tBoth of these network interfaces should already be configured, tested"
	echo -e "\tand working. If not, abort now (Ctrl-C) and do that.\n"
	echo -e "\tI can detect ${#INTS[@]} network interfaces with an IP address:"
	for x in `seq 0 $(( ${#INTS[@]} - 1 ))`; do
		iname=`echo ${INTS[$x]} | awk -F= '{print $1}'`
		iaddr=`echo ${INTS[$x]} | awk -F= '{print $2}'`
		echo -e "\t\t$iname\t$iaddr"
	done

	if [ $ISMASTER = YES ]; then
		MY_EXTERNAL_INT=$MASTER_EXTERNAL_INT
		MY_INTERNAL_INT=$MASTER_INTERNAL_INT
	else
		MY_EXTERNAL_INT=$SLAVE_EXTERNAL_INT
		MY_INTERNAL_INT=$SLAVE_INTERNAL_INT
	fi

	INTSVALID=false
	while [ $INTSVALID = false ]; do
		echo -ne "\tPlease select the EXTERNAL, PUBLIC interface "
		[ "$MY_EXTERNAL_INT" = "" ] && MY_EXTERNAL_INT="eth0"
		echo -n "[$MY_EXTERNAL_INT]: "
		read externalint
		if [ "$externalint" = "" ]; then
			externalint=$MY_EXTERNAL_INT
		fi

		if $(ip addr show $externalint > /dev/null 2>&1 ); then
			MY_EXTERNAL_IP=`ip -o addr show $externalint | grep -v secondary | grep ${externalint}$|awk '{print $4}'|sed 's^/[0-9]*^^'`
			if [ "$MY_EXTERNAL_IP" = "" ]; then
				echo "I'm guessing that was a typo. I can't get an IP address from that interface."
				echo "Try again."
			fi
			echo -e "\tSetting EXTERNAL interface to $externalint ($MY_EXTERNAL_IP)"
			INTSVALID=true
		else 
			echo "I'm guessing that was a typo. I can't find that interface. Sorry. Try again"
		fi
	done

	INTSVALID=false
	while [ $INTSVALID = false ]; do
		echo -en "\tPlesae enter the INTERNAL, PRIVATE interface "
		[ "$MY_INTERNAL_INT" = "" ] && MY_INTERNAL_INT="eth1"
		echo -n "[$MY_INTERNAL_INT]: "
		read internalint
		if [ "$internalint" = "" ]; then
			internalint=$MY_INTERNAL_INT
		fi

		if $(ip addr show $internalint > /dev/null 2>&1 ); then
			MY_INTERNAL_IP=`ip -o addr show $internalint | grep -v secondary | grep ${internalint}$|awk '{print $4}'|sed 's^/[0-9]*^^'`
			if [ "$MY_INTERNAL_IP" = "" ]; then
				echo "I'm guessing that was a typo. I can't get an IP address from that interface."
				echo "Try again."
			fi
			echo -e "\tSetting INTERNAL interface to $internalint ($MY_INTERNAL_IP)"
			INTSVALID=true
		else 
			echo "I'm guessing that was a typo. I can't find that interface. Sorry. Try again"
		fi
	done

	if [ $ISMASTER = YES ]; then
		cfg MASTER_INTERNAL_IP "$MY_INTERNAL_IP"
		cfg MASTER_INTERNAL_INT "$internalint"
		cfg MASTER_EXTERNAL_IP "$MY_EXTERNAL_IP"
		cfg MASTER_EXTERNAL_INT "$externalint"
	else
		cfg SLAVE_INTERNAL_IP "$MY_INTERNAL_IP"
		cfg SLAVE_INTERNAL_INT "$internalint"
		cfg SLAVE_EXTERNAL_IP "$MY_EXTERNAL_IP"
		cfg SLAVE_EXTERNAL_INT "$externalint"
	fi


	if [ "$MULTICAST_ADDR" = "" ]; then
		echo -en "\tGenerating Multicast Address..."
		M1=`tr -dc 0-9 < /dev/urandom | head -c3`
		M1=`echo ${M1}%256 | bc`
		M2=`tr -dc 0-9 < /dev/urandom | head -c3`
		M2=`echo ${M2}%256 | bc`
		M3=`tr -dc 0-9 < /dev/urandom | head -c3`
		M3=`echo ${M3}%256 | bc`
		echo "(239.${M1}.${M2}.${M3})"
		MULTICAST_ADDR=239.${M1}.${M2}.${M3}
	fi
	cfg MULTICAST_ADDR "$MULTICAST_ADDR"

	echo "Configure Services"
	echo -e "\tPlease enter the IP Addresses for the HiPBX Services. These addresses"
	echo -e "\tshould NOT already exist, and they will be assigned to the interface"
	echo -e "\tyou previously tselected ($MASTER_EXTERNAL_INT). These will be the"
	echo -e "\t'floating' addresses that are linked to a service, rather than a"
	echo -e "\tmachine. Please don't duplicate IP addresses when assigning them."
	for x in $(seq 0 $NBRSVCS); do
		VARNAME=${SERVICENAME[$x]}_IP
		IPADDR="unknown"
		[ "${!VARNAME}" != "" ] && IPADDR=${!VARNAME}
		echo -ne "\t\t${SERVICENAME[$x]} [${IPADDR}]: "
		read newip
		[ "$newip" = "" ] && newip=$IPADDR
		if [ $newip = unknown ]; then
			echo "No, 'unknown' means I DON'T KNOW. You need to tell me. Make it up."
			exit;
		fi
		# Check to make sure the address isn't being used..
	#	if $(arping -w1 -fqI $MASTER_EXTERNAL_INT $newip) ; then
	#		echo "Whoops. Something seems to be using that address. Here's the response"
	#		echo "from arping -w 1 -fI $MASTER_EXTERNAL_INT so you can see the MAC address"
	#		echo "for yourself."
	#		arping -w 1 -fI $MASTER_EXTERNAL_INT $newip
	#		exit;
	#	fi	
		cfg ${SERVICENAME[$x]}_IP "$newip"
	done
}

function fixhosts {
	echo -en "\tUpdating hosts file..."
	grep ${SLAVE_INTERNAL_IP}.slave /etc/hosts > /dev/null || echo -e "$SLAVE_INTERNAL_IP\tslave" >> /etc/hosts
	grep ${MASTER_INTERNAL_IP}.master /etc/hosts > /dev/null || echo -e "$MASTER_INTERNAL_IP\tmaster" >> /etc/hosts
}

function calc_netmasks {
	# Figure out netmasks. This isn't line noise, honest.
	EXTERNAL_CLASS=`ip -o addr | grep -v secondary | grep ${MASTER_EXTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_'`
	INTERNAL_CLASS=`ip -o addr | grep -v secondary | grep ${MASTER_INTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_'`
	cfg INTERNAL_CLASS $INTERNAL_CLASS
	cfg EXTERNAL_CLASS $EXTERNAL_CLASS
}


function gen_corosync {
	if [ $ISMASTER = YES ]; then 
		INTERNAL_IP=$MASTER_INTERNAL_IP
	else
		INTERNAL_IP=$SLAVE_INTERNAL_IP
	fi
	echo -n "Generating corosync configuration file: "
	echo " totem {
	version: 2
	secauth: off
	threads: 0
	interface {
		ringnumber: 0
		bindnetaddr: $INTERNAL_IP
		mcastaddr: $MULTICAST_ADDR
		mcastport: 8647
		ttl: 1
	}
}

logging {
	fileline: off
	to_stderr: no
	to_logfile: no
	to_syslog: yes
	logfile: /dev/null
	debug: off
	timestamp: on
	logger_subsys {
		subsys: AMF
		debug: off
	}
}

amf {
	mode: disabled
}

aisexec {
	user: root
	group: root
}" > /etc/corosync/corosync.conf
	echo "service {
	name: pacemaker
	ver: 1
}" > /etc/corosync/service.d/pcmk

	echo "Done"
	chkconfig corosync on
	/etc/init.d/corosync start
	chkconfig pacemaker on
	/etc/init.d/pacemaker start
}

function config_corosync {
	# Now corosync and pacemaker are up, lets make them work!
	echo -en "Configuring corosync\n\t(This may take up to 60 seconds, if the cluster isn't fully up yet)..."
	while :; do
		crm configure property stonith-enabled=false 2>&1 | grep ERROR > /dev/null || break
		echo -n "."
	done
	crm configure property no-quorum-policy=ignore
	crm configure property default-action-timeout=240
	crm configure rsc_defaults resource-stickiness=100

	for x in $(seq 0 $NBRSVCS); do
		CLUSTER=${SERVICENAME[$x]}_IP
		echo -en "\tCreating Cluster IP ${CLUSTER}.."
		crm configure primitive ip_${SERVICENAME[$x]} ocf:heartbeat:IPaddr2 params ip=${!CLUSTER} cidr_netmask=$INTERNAL_CLASS op monitor interval=59s
		echo "Done"
	done
	echo "Done"
}

function setup_drbd {
	echo "DRBD:"
	if [ ! -f /etc/drbd.conf ]; then
		echo "Looks like drbd isn't installed. Install all the RPMs in the 'rpms' directory"
		echo "by typing rpm -i rpms/*rpm"
		exit
	fi

	for x in $(seq 0 $NBRSVCS); do
		echo -ne "\t${SERVICENAME[$x]} on drbd_${SERVICENAME[$x]}"
		echo "resource ${SERVICENAME[$x]} {
	device /dev/drbd${x};
	meta-disk internal;
	on master {
		disk /dev/mapper/${MASTER_VGNAME}-drbd_${SERVICENAME[$x]};
		address ${MASTER_INTERNAL_IP}:400${x};
	}
	on slave {
		disk /dev/mapper/${SLAVE_VGNAME}-drbd_${SERVICENAME[$x]};
		address ${SLAVE_INTERNAL_IP}:400${x};
	}
}" > /etc/drbd.d/${SERVICENAME[$x]}.res
		cfg "${SERVICENAME[$x]}_DISK" "/dev/drbd$x"
		if $(drbdadm dump-md ${SERVICENAME[$x]} > /dev/null 2>&1); then
			echo -e " (already initialized)"
		else
			echo -ne " (initializing..."
			
			echo yes|drbdadm create-md  ${SERVICENAME[$x]} > /dev/null  2>&1
			echo "Done)"
		fi
		if [ ! -d /drbd/${SERVICENAME[$x]} ]; then
			rm -rf /drbd/${SERVICENAME[$x]}
			mkdir -p /drbd/${SERVICENAME[$x]}
		fi

		if [ $NEWCLUSTER = YES ]; then
			drbdadm adjust ${SERVICENAME[$x]}
			drbdadm -- --force primary ${SERVICENAME[$x]}
			drbdadm primary ${SERVICENAME[$x]}
			# Is there a filesystem on this disk?
			e2fsck -y /dev/drbd$x > /dev/null 2>&1
			FSCK_RETURN=$?
			if [ $FSCK_RETURN = 0 -o $FSCK_RETURN = 1 ]; then
				echo -e "\t\tFilesystem OK"
			else
				echo -ne "\t\tCreating filesystem..."
				mkfs.ext4 -L drbd_${SERVICENAME[$x]} -M /drbd/${SERVICENAME[$x]} /dev/drbd$x >/dev/null 2>&1
				echo "Done"
			fi
			crm configure primitive drbd_${SERVICENAME[$x]} ocf:linbit:drbd \
				params drbd_resource="${SERVICENAME[$x]}" \
				op monitor interval="59s" > /dev/null 2>&1
			crm configure ms ms_drbd_${SERVICENAME[$x]} drbd_${SERVICENAME[$x]} \
				meta master-max="1" \
				master-node-max="1" \
				clone-max="2" \
				clone-node-max="1" \
				notify="true" > /dev/null 2>&1
			crm configure primitive fs_${SERVICENAME[$x]} ocf:heartbeat:Filesystem \
				params device="/dev/drbd$x" \
				directory="/drbd/${SERVICENAME[$x]}" \
				fstype="ext4" > /dev/null 2>&1
			crm configure location loc_${SERVICENAME[$x]} ms_drbd_${SERVICENAME[$x]} rule role=master 100: \#uname eq master
			crm configure group ${SERVICENAME[$x]} fs_${SERVICENAME[$x]} ip_${SERVICENAME[$x]} > /dev/null 2>&1
			crm configure order order-${SERVICENAME[$x]} inf: ms_drbd_${SERVICENAME[$x]}:promote ${SERVICENAME[$x]}:start
			crm_resource --resource fs_${SERVICENAME[$x]} -C > /dev/null 2>&1
		else
			drbdadm up  ${SERVICENAME[$x]}
		fi
	done
}


function setup_mysql {
	echo "MySQL..."
	# First, can we connect to MySQL without a password?
	if (mysql -equit >/dev/null 2>&1); then
		# We can. Set the password.
		echo -e "\tSetting Password to $MYSQLPASS"
		mysqladmin password $MYSQLPASS > /dev/null
	else
		echo -e "\tPassword previously set"
		if mysql -p$MYSQLPASS -equit; then
			echo -e "\tPassword correct"
		else
			echo -e "Error: Unable to log into MySQL. Sorry.\nIf you know what the MySQL root password is, update the /etc/hipbx.org file with the password.\nOtherwise, you'll have to do a password reset."
			exit
		fi
	fi

	# MySQL is now secured, which is important, so now we check for the hipbx database
	if (mysql -p$MYSQLPASS hipbx -equit 2>&1 | grep Unknown\ database > /dev/null); then
		# Database does not exist. Create.
		echo -e "\tCreating HiPBX database"
		mysqladmin -p$MYSQLPASS create hipbx
	fi
	echo -en "\tChecking for correct GRANTs..."
	if (mysql -uhipbx -p$MYSQLPASS -hlocalhost hipbx -equit > /dev/null 2>&1); then
		echo "OK"
	else
		echo "Failed."
		echo -en "\tCreating HiPBX mysql users .. "
		for host in localhost master slave cluster; do
			echo -n "$host "
			CREATE='CREATE USER "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
			mysql -p$MYSQLPASS -e"$CREATE"
			GRANT='GRANT ALL PRIVILEGES ON hipbx.* TO "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
			mysql -p$MYSQLPASS -e"$GRANT"
		done
		echo ""
	fi
}


function get_peer_addr {
	if [ $ISMASTER = YES ]; then
		PEER=SLAVE
		PEER_IP=$SLAVE_INTERNAL_IP
	else
		PEER=MASTER
		PEER_IP=$MASTER_INTERNAL_IP
	fi
	
	while [ "$PEER_IP" = "" ]; do
		echo -en "\tPlease enter $PEER internal IP address: "
		read pip
		if $(ping -c1 $pip > /dev/null 2>&1); then
			echo -e "\tMachine is up."
			PEER_IP=$pip
		else
			echo -e "\tMachine is down. I can continue if you're sure that's the right address,"
			echo -e "\tbut for sanity checking, it's a good idea to have the slave machine up"
			echo -e "\twhile you're installing."
			echo -en "Are you sure you want to continue with \"$pip\" as the address? [yN]: "
			read sure
			[ "$sure" = "Y" -o "$sure" = "y" ] && PEER_IP=$pip
		fi
	done
	cfg ${PEER}_INTERNAL_IP $PEER_IP
	[ $PEER = SLAVE ] && SLAVE_INTERNAL_IP=$PEER_IP
	[ $PEER = MASTER ] && MASTER_INTERNAL_IP=$PEER_IP
}
