#!/bin/bash

#  HiPBX Installer Script. 
#  Copyright 2011, Rob Thomas <xrobau@gmail.com>
#  Shared functions used in both the Main and Backup setup scripts

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
	# dualbus on freeode's #bash clued me up on this handy trick.
	# Set the variable in the running bash.
	printf -v "$VARNAME" $VARVAL
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
		echo "SELinux is enabled. I've turned it off for you."
		sed -i s/SELINUX=enforcing/SELINUX=disabled/ /etc/selinux/config
		echo "You MUST now reboot. Sorry, there's no way to get around this."
		exit;
	fi
}

function installpackages {
	add_repos
	echo "Required Packages:"
	echo -en "\tGenerating list of all RPMs installed on this machine..."
	rpm -qa --queryformat '%{NAME}\n' > /tmp/rpms.$$
	echo "Done"
	INSTALL=""
	# RPMs from yum.
	YUMPACKS="bc vim-enhanced sox libusb-devel httpd php php-gd php-pear php-mysql mysql-server curl mysql mysql-devel php-process libxml2-devel ncurses-devel libtiff-devel libogg-devel libvorbis vorbis-tools pacemaker unixODBC bluez-libs postgresql-libs festival ImageMagick"
	# Update. Now using epel and elrepo repositories
	YUMPACKS="$YUMPACKS asterisk asterisk-dahdi dahdi-tools asterisk-sounds-core-en_AU asterisk-sounds-core-en-wav asterisk-sqlite asterisk-voicemail-plain asterisk-mysql asterisk-mobile asterisk-ldap asterisk-jabber asterisk-festival asterisk-fax asterisk-curl asterisk-calendar asterisk-jack spandsp iksemel-utils php-fpdf libsrtp php-pear-DB libresample kmod-drbd84 drbd84-utils"
	for x in $YUMPACKS ; do 
		if ! (grep "^${x}$" /tmp/rpms.$$ > /dev/null) ; then 
			INSTALL="$INSTALL $x"
		fi
	done
	if [ "$INSTALL" != "" ] ; then
		echo -e "\tInstalling missing yum packages."
		yum --enablerepo=elrepo-testing -y install $INSTALL
	else
		echo -e "\tNo yum packages required"
	fi
	if ! rpm -q dahdi-linux > /dev/null ; then
		echo -e "\tInstalling DAHDI Kernel module"
		yum -y --enablerepo=atrpms install dahdi-linux
	fi
	if ! rpm -q asterisk-extra-sounds-en-sln16 > /dev/null ; then
		echo -e "\tInstalling Asterisk Extra-sounds module"
		yum -y --enablerepo=atrpms install asterisk-extra-sounds-en-sln16
	fi

}

function disableall {
	chkconfig mysqld off
	chkconfig httpd off
	chkconfig iptables off
	service iptables status > /dev/null && service iptables stop
	chkconfig drbd off
	if ! modprobe drbd > /dev/null 2>&1; then
		echo "Unable to load DRBD module. Please install DRBD rpms"
		exit
	fi
}

function hipbx_init {
	# Make the /etc/hipbx.d directory if it doesn't already exist.
	[ ! -d /etc/hipbx.d ] && mkdir /etc/hipbx.d
	[ ! -d /var/run/heartbeat/crm ] && mkdir  -p /var/run/heartbeat/crm
}

function mysql_password {
	# Generate a MySQL password, if one hasn't already been generated.
	[ "$MYSQLPASS" = "" ] && MYSQLPASS=$(tr -dc A-Za-z0-9 < /dev/urandom | head -c16)
	cfg MYSQLPASS $MYSQLPASS
}

function fix_hostname {
	if [ $ISMAIN = YES ]; then
		if [ $(hostname) != main ]; then
			echo "Fixing hostname - setting to 'main'"
			hostname main
			sed -i "s/^HOSTNAME=.*/HOSTNAME=main/" /etc/sysconfig/network
		fi
	else
		if [ $(hostname) != backup ]; then
			echo "Fixing hostname - setting to 'backup'"
			hostname backup
			sed -i "s/^HOSTNAME=.*/HOSTNAME=backup/" /etc/sysconfig/network
		fi
	fi
}
		

function configure_lvm {

	#### LVM Setup Begin
	# Default storage percentages.  Minimum sizes (with 50GB lvm space used) in brackets
	# MySQL = 30 (15G)
	# Asterisk = 30 (15G)
	# http = 20 (10G)
	# dhcp = 10 (5G)
	# spare = 10 (5G)
	# Don't try to use decimals here. Integers only.
	# Note that changing these AFTER the cluster has been built won't work. Create
	# a new cluster. Remember how I said set aside LOTS OF SPACE? I wasn't kidding.
	[ "$SERVICES" = "" ] && SERVICES=( asterisk=30 http=20 mysql=30 dhcp=10 ldap=10 )

	# Parse the SERVICES variable into arrays
	NBRSVCS=$((${#SERVICES[@]} - 1))
	SANITYSIZE=0
	SERVSTRING="( "
	for element in $(seq 0 $NBRSVCS); do
		SERVSTRING="$SERVSTRING ${SERVICES[$element]} "
		SERVICENAME[$element]=$(echo ${SERVICES[$element]} | awk -F= ' { print $1 } ')
		SERVICEPCNT[$element]=$(echo ${SERVICES[$element]} | awk -F= ' { print $2 } ')
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
		USED=$(lvdisplay -C --noheadings --nosuffix --units g | grep drbd_${SERVICENAME[$x]} | awk ' { print $4 }')
		varname=drbd_${SERVICENAME[$x]}
		presize=${!varname}
		[ "$presize" = "" ] && presize=0
		if [ "$USED" != "" ]; then 
			# Integerify USED.
			USED=$(printf %0.f $USED)
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
			USEDSPACE=$(( $USEDSPACE + $(printf %0.f $realspace) ))
			SELECTEDVG=$(lvdisplay -C --noheadings --nosuffix --units g | grep drbd_${SERVICENAME[$x]} | awk ' { print $2 }')
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
		VGS=$(vgdisplay -C --noheadings --nosuffix --units g | awk ' { print $1"="$7 }')
		SELECTEDVG=not-found  # '-' is an invalid character in a volume group, so not-found will never be a valid answer
			for vg in $VGS; do
				VGNAME=$(echo $vg | awk -F= ' { print $1 } ')
				VGSPACE=$(echo $vg | awk -F= ' { print $2 } ')
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

		# Check if the amount of space wanted is less than the amount needed.
		NEEDEDSPACE=0
		for x in $(seq 0 $NBRSVCS); do
			varname=drbd_${SERVICENAME[$x]}
			if [ "${!varname}" != "" ]; then
				NEEDEDSPACE=$(( $NEEDEDSPACE +  ${!varname} ))
			fi
		done
		# Integerify VGSPACE
		VGSPACE=$(printf %0.f $VGSPACE)
		if [ $NEEDEDSPACE -gt $VGSPACE ]; then
			echo "A problem has occured."
			echo "The amount of space needed by DRBD (${NEEDEDSPACE}G) - according to /etc/hipbx.d/hipbx.conf"
			echo "exceeds the amount of space you have requested (${VGSPACE}G)"
			exit
		fi

		echo -e "\tCreating LVs..."
		# Calculate how much is allocated already, and base our calculations off that.
		if [ $ALLOCATED = 0 ]; then
			BASESIZE=$VGSPACE
		else
			BASESIZE=$(echo scale=8\; $USEDSPACE / \( $ALLOCATED / 100 \) | bc)
		fi
		# Round DOWN basesize
		BASESIZE=$(echo $BASESIZE - .5 | bc)
		BASESIZE=$(printf %0.f $BASESIZE)
		for x in $(seq 0 $(( ${#LVMAKE[@]} - 1 )) ) ; do
			echo -ne "\t\t${SERVICENAME[${LVMAKE[$x]}]} "
			drbdvar=drbd_${SERVICENAME[${LVMAKE[$x]}]}
			if [ "${!drbdvar}" != "" ]; then
				lvsize=${!drbdvar}
			else
				lvsize=$(echo ${BASESIZE}*.${SERVICEPCNT[${LVMAKE[$x]}]} - .5 | bc)
				lvsize=$(printf %0.f $lvsize)
			fi
			[ "$lvsize" -lt 1 ] && lvsize=1
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

	if [ $ISMAIN = YES ]; then
		cfg MAIN_VGNAME "$SELECTEDVG"
	else
		cfg BACKUP_VGNAME "$SELECTEDVG"
	fi
	MY_VGNAME=$SELECTEDVG
}

function check_ssh {
	echo "SSH:"
	if [ "$SSH_KEY" = "" ]; then
		echo -e "\t\$SSH_KEY not found."
		if [ -f /etc/hipbx.d/ssh_key_main -a -f /etc/hipbx.d/ssh_key_main.pub ]; then
			echo -e "\tHowever, /etc/hipbx.d/ssh_key_main exists"
		else
			rm -f /etc/hipbx.d/ssh_key_main /etc/hipbx.d/ssh_key_main.pub
			echo -en "\tGenerating Main ssh Public key..."
			ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_main -N ""
			echo "Done"
		fi
		SSH_KEY=$(cat /etc/hipbx.d/ssh_key_main.pub)
	else
		echo -en "\tMain ssh key exists"
		test=$(cat /etc/hipbx.d/ssh_key_main.pub 2>/dev/null)
		if [ "$SSH_KEY" != "$test" ]; then
			echo -e " - but doesn't match hipbx.conf! Regenerating."
			rm -f /etc/hipbx.d/ssh_key_main /etc/hipbx.d/ssh_key_main.pub
			echo -en "\tGenerating Main ssh Public key..."
			ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_main -N ""
			SSH_KEY=$(cat /etc/hipbx.d/ssh_key_main.pub)
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
		mkdir -p $HOME/.ssh
		cp /etc/hipbx.d/ssh_key_main.pub $HOME/.ssh/authorized_keys
		echo "Done"
	else
		# It exists. Check to see if our key is in there
		if grep "\"$SSH_KEY\"" $HOME/.ssh/authorized_keys > /dev/null; then
			echo -e "\tHiPBX SSH Key already exists in authorized_keys"
		else
			echo -en "\tAdding HiPBX SSH Key to authorized_keys..."
			cat /etc/hipbx.d/ssh_key_main.pub >> $HOME/.ssh/authorized_keys
			echo "Done"
		fi
	fi
}
	
function get_ssh_keys {
	# This should be run on the second machine to be brought online, so it's called from 'joincluster'
	if [ "$ISMAIN" = "YES" ]; then
		# We need to get backups keys, and load backup with ours.
		ssh -i /etc/hipbx.d/ssh_key_main -o StrictHostKeyChecking=no backup "ssh -i /etc/hipbx.d/ssh_key_main -o StrictHostKeyChecking=no main exit"
	else
		# We're backup, and we need to get mains, and load mains with ours.
		ssh -i /etc/hipbx.d/ssh_key_main -o StrictHostKeyChecking=no main "ssh -i /etc/hipbx.d/ssh_key_main -o StrictHostKeyChecking=no backup exit"
	fi
}

function config_networking {
	echo "Networking:"
	INTS=( $(ip -o addr | grep -v "1: lo" |grep -v secondary | grep inet\ | awk '{print $9"="$4}'| sed 's^/[0-9]*^^') )
	echo -e "\tThere needs to be at least two Ethernet Inferfaces for the cluster"
	echo -e "\tto work. The first interface is the 'internal' link. This should be"
	echo -e "\ta crossover cable, or even better, a pair of crossover cables"
	echo -e "\tbonded together, that links the two machines. There should NOT be a"
	echo -e "\tnetwork switch on the internal link. "
	echo -e "\tThe second interface is your external network. This again should be"
	echo -e "\ta bonded interface, preferrably going to two seperate switches."
	echo -e "\tBoth of these network interfaces should already be configured, tested"
	echo -e "\tand working. If not, abort now (Ctrl-C) and do that.\n"
	echo -e "\tI can detect ${#INTS[@]} network interfaces with an IP address:"
	for x in $(seq 0 $(( ${#INTS[@]} - 1 ))); do
		iname=$(echo ${INTS[$x]} | awk -F= '{print $1}')
		iaddr=$(echo ${INTS[$x]} | awk -F= '{print $2}')
		echo -e "\t\t$iname\t$iaddr"
	done

	if [ $ISMAIN = YES ]; then
		MY_EXTERNAL_INT=$MAIN_EXTERNAL_INT
		MY_INTERNAL_INT=$MAIN_INTERNAL_INT
	else
		MY_EXTERNAL_INT=$BACKUP_EXTERNAL_INT
		MY_INTERNAL_INT=$BACKUP_INTERNAL_INT
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
			MY_EXTERNAL_IP=$(ip -o addr show $externalint | grep -v inet6 | grep -v secondary | grep -v /32 | grep ${externalint}$|awk '{print $4}'|sed 's^/[0-9]*^^')
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
		echo -en "\tPlease enter the INTERNAL, PRIVATE interface "
		[ "$MY_INTERNAL_INT" = "" ] && MY_INTERNAL_INT="eth1"
		echo -n "[$MY_INTERNAL_INT]: "
		read internalint
		if [ "$internalint" = "" ]; then
			internalint=$MY_INTERNAL_INT
		fi

		if $(ip addr show $internalint > /dev/null 2>&1 ); then
			MY_INTERNAL_IP=$(ip -o addr show $internalint | grep -v inet6 | grep -v secondary | grep -v /32 | grep ${internalint}$|awk '{print $4}'|sed 's^/[0-9]*^^')
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

	if [ $ISMAIN = YES ]; then
		cfg MAIN_INTERNAL_IP "$MY_INTERNAL_IP"
		cfg MAIN_INTERNAL_INT "$internalint"
		cfg MAIN_EXTERNAL_IP "$MY_EXTERNAL_IP"
		cfg MAIN_EXTERNAL_INT "$externalint"
	else
		cfg BACKUP_INTERNAL_IP "$MY_INTERNAL_IP"
		cfg BACKUP_INTERNAL_INT "$internalint"
		cfg BACKUP_EXTERNAL_IP "$MY_EXTERNAL_IP"
		cfg BACKUP_EXTERNAL_INT "$externalint"
	fi


	if [ "$MULTICAST_ADDR" = "" ]; then
		echo -en "\tGenerating Multicast Address..."
		M1=$(tr -dc 0-9 < /dev/urandom | head -c3)
		M1=$(echo ${M1}%256 | bc)
		M2=$(tr -dc 0-9 < /dev/urandom | head -c3)
		M2=$(echo ${M2}%256 | bc)
		M3=$(tr -dc 0-9 < /dev/urandom | head -c3)
		M3=$(echo ${M3}%256 | bc)
		echo "(239.${M1}.${M2}.${M3})"
		MULTICAST_ADDR=239.${M1}.${M2}.${M3}
	fi
	cfg MULTICAST_ADDR "$MULTICAST_ADDR"

	echo "Configure Services"
	echo -e "\tPlease enter the IP Addresses for the HiPBX Services. These addresses"
	echo -e "\tshould NOT already exist, and they will be assigned to the interface"
	if [ "$ISMAIN" = "YES" ] ; then
		echo -e "\tyou previously selected ($MAIN_EXTERNAL_INT). These will be the"
	else
		echo -e "\tyou previously selected ($BACKUP_EXTERNAL_INT). These will be the"
	fi

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
	#	if $(arping -w1 -fqI $MAIN_EXTERNAL_INT $newip) ; then
	#		echo "Whoops. Something seems to be using that address. Here's the response"
	#		echo "from arping -w 1 -fI $MAIN_EXTERNAL_INT so you can see the MAC address"
	#		echo "for yourself."
	#		arping -w 1 -fI $MAIN_EXTERNAL_INT $newip
	#		exit;
	#	fi	
		cfg ${SERVICENAME[$x]}_IP "$newip"
	done
}

function fixhosts {
	echo -en "\tUpdating hosts file..."
	addreplace_host backup $BACKUP_INTERNAL_IP
	addreplace_host main $MAIN_INTERNAL_IP
	addreplace_host mysql $mysql_IP
	addreplace_host asterisk $asterisk_IP
	echo "Done"
}

function addreplace_host {
	h=/etc/hosts
	hostnm=$1
	hostip=$2
	# If either of the things are blank, don't do anything.
	[[ "$hostip" = ""  || "$hostnm" = "" ]] && return 0
	hoststxt=$(egrep -v "[[:space:]]${hostnm}($|[[:space:]])" $h) 2> /dev/null
	# The host may have existed in /etc/hosts, but it doesn't in  hoststxt.
	hoststxt="$hoststxt
$hostip		$hostnm"
	echo "$hoststxt" > $h
}
		
		
function calc_netmasks {
	# Figure out netmasks. This isn't line noise, honest.
	if [ $ISMAIN = YES ] ; then
		EXTERNAL_CLASS=$(ip -o addr | grep -v inet6 | grep -v secondary | grep ${MAIN_EXTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_')
		INTERNAL_CLASS=$(ip -o addr | grep -v inet6 | grep -v secondary | grep ${MAIN_INTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_')
	else
		EXTERNAL_CLASS=$(ip -o addr | grep -v inet6 | grep -v secondary | grep ${BACKUP_EXTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_')
		INTERNAL_CLASS=$(ip -o addr | grep -v inet6 | grep -v secondary | grep ${BACKUP_INTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_')
	fi
	cfg INTERNAL_CLASS $INTERNAL_CLASS
	cfg EXTERNAL_CLASS $EXTERNAL_CLASS
}


function gen_corosync {
	if [ $ISMAIN = YES ]; then 
		INTERNAL_IP=$MAIN_INTERNAL_IP
	else
		INTERNAL_IP=$BACKUP_INTERNAL_IP
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
	echo -en "Configuring corosync\n\t(This may take up to 60 seconds, if the cluster isn't fully up yet)... "
	while ( crm status | grep Current\ DC:\ NONE > /dev/null); do
		spinner
		sleep 1;
	done
	printf "\bUp!\n"
	this_node_standby
	crm configure property stonith-enabled=false
	crm configure property no-quorum-policy=ignore
	crm configure property default-action-timeout=240
	crm configure rsc_defaults resource-stickiness=100
	for x in $(seq 0 $NBRSVCS); do
		CLUSTER=${SERVICENAME[$x]}_IP
		echo -en "\tCreating Cluster IP ${CLUSTER}.."
		crm configure primitive ip_${SERVICENAME[$x]} ocf:heartbeat:IPaddr2 params ip=${!CLUSTER} cidr_netmask=$EXTERNAL_CLASS op monitor interval=59s notify="true"
		echo "Done"
	done
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
	net {
		protocol C;
		after-sb-0pri	discard-least-changes;
		after-sb-1pri	discard-secondary;
	}
	disk {
		resync-rate 50M;
		disk-drain no;
		disk-flushes no;
		md-flushes no;
	}
	on main {
		disk /dev/mapper/${MAIN_VGNAME}-drbd_${SERVICENAME[$x]};
		address ${MAIN_INTERNAL_IP}:400${x};
	}
	on backup {
		disk /dev/mapper/${BACKUP_VGNAME}-drbd_${SERVICENAME[$x]};
		address ${BACKUP_INTERNAL_IP}:400${x};
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
				mkfs.ext4 -j -L drbd_${SERVICENAME[$x]} -M /drbd/${SERVICENAME[$x]} /dev/drbd$x >/dev/null 2>&1
				echo "Done"
			fi
			crm configure primitive drbd_${SERVICENAME[$x]} ocf:linbit:drbd \
				params drbd_resource="${SERVICENAME[$x]}" 
			crm configure ms ms_drbd_${SERVICENAME[$x]} drbd_${SERVICENAME[$x]} \
				meta main-max="1" main-node-max="1" clone-max="2" target-role="Stopped"\
				clone-node-max="1" notify="true"
			crm configure primitive fs_${SERVICENAME[$x]} ocf:heartbeat:Filesystem \
				params device="/dev/drbd$x" directory="/drbd/${SERVICENAME[$x]}" \
				fstype="ext4" \
				op monitor interval="59s" notify="true"\
				meta target-role="Stopped"
			crm configure group ${SERVICENAME[$x]} fs_${SERVICENAME[$x]} ip_${SERVICENAME[$x]} meta target-role="Stopped"
			crm configure colocation colo-${SERVICENAME[$x]} inf: ${SERVICENAME[$x]} ms_drbd_${SERVICENAME[$x]}:Master
			crm configure order order-${SERVICENAME[$x]} inf: ms_drbd_${SERVICENAME[$x]}:promote ${SERVICENAME[$x]}:start
			# For some reason, an error always occurs when you create a DRBD RA. Clean it up.
			crm_resource --resource drbd_${SERVICENAME[$x]} -C > /dev/null 2>&1
		else
			# Invalidate the local drbd volume. We don't know what's there. Blow it away!
			echo yes|drbdadm create-md  ${SERVICENAME[$x]} > /dev/null  2>&1
			drbdadm up  ${SERVICENAME[$x]}
		fi
	done
}


function wait_for_mysql_start {
	# Wait here until MySQL is contactable.
	# We'll only do this locally. 
	echo -n "Waiting for MySQL startup..."

        nowt=$(date +%s)
        fint=$(( $nowt + 30 ))

	res=$(mysql -equit 2>&1)
        while [[ "$res" = "ERROR 2002"* ]]; do
                [ $(date +%s) -gt $fint ] && break
                sleep 0.2 # Sleep 200msec
        	nowt=$(date +%s)
		if [ "$nowt" -gt "$fint" ]; then
			echo "I'm unable to connet to MySQL. Maybe it hasn't started, or maybe something"
			echo "bad has happened. I don't know. I'm giving up. Please fix me."
			exit
		fi
		res=$(mysql -equit 2>&1)
        done
	echo "Up!"
}

	
function setup_mysql {
	echo "MySQL..."
	wait_for_mysql_start
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
			echo "I can't continue. Please fix this and re-run install.sh"
			exit
		fi
	fi

	# MySQL is now secured, which is important, so now we check for the hipbx database
	if (mysql -p$MYSQLPASS hipbx -equit 2>&1 | grep Unknown\ database > /dev/null); then
		# Database does not exist. Create.
		echo -e "\tCreating HiPBX databases"
		mysqladmin -p$MYSQLPASS create hipbx
		mysqladmin -p$MYSQLPASS create asteriskcdrdb
	fi
	echo -en "\tChecking for correct GRANTs on hipbx database..."
	if (mysql -uhipbx -p$MYSQLPASS -hlocalhost hipbx -equit > /dev/null 2>&1); then
		echo "OK"
	else
		echo "Failed."
		echo -en "\tCreating HiPBX mysql users .. "
		CREATE='CREATE USER "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
		mysql -p$MYSQLPASS -e"$CREATE"
		for host in localhost main backup cluster mysql $PEER_IP %; do
			echo -n "$host "
			GRANT='GRANT ALL PRIVILEGES ON hipbx.* TO "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
			mysql -p$MYSQLPASS -e"$GRANT"
			GRANT='GRANT ALL PRIVILEGES ON asteriskcdrdb.* TO "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
			mysql -p$MYSQLPASS -e"$GRANT"
		done
		echo ""
	fi
	echo -en "\tChecking for correct GRANTs on asteriskcdrdb database..."
	if (mysql -uhipbx -p$MYSQLPASS -hlocalhost asteriskcdrdb -equit > /dev/null 2>&1); then
		echo "OK"
	else
		echo "Failed."
		echo -en "\tCreating HiPBX mysql users .. "
		for host in localhost main backup cluster mysql $PEER_IP %; do
			echo -n "$host "
			GRANT='GRANT ALL PRIVILEGES ON asteriskcdrdb.* TO "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
			mysql -p$MYSQLPASS -e"$GRANT"
		done
		echo ""
	fi
}

function get_peer_addr {
	if [ $ISMAIN = YES ]; then
		PEER=BACKUP
		PEER_IP=$BACKUP_INTERNAL_IP
	else
		PEER=MAIN
		PEER_IP=$MAIN_INTERNAL_IP
	fi
	
	while [ "$PEER_IP" = "" ]; do
		echo -en "\tPlease enter $PEER internal IP address: "
		read pip
		if $(ping -c1 $pip > /dev/null 2>&1); then
			echo -e "\tMachine is up."
			PEER_IP=$pip
		else
			echo -e "\tMachine is down. I can continue if you're sure that's the right address,"
			echo -e "\tbut for sanity checking, it's a good idea to have the other machine up"
			echo -e "\twhile you're installing."
			echo -en "Are you sure you want to continue with \"$pip\" as the address? [yN]: "
			read sure
			[ "$sure" = "Y" -o "$sure" = "y" ] && PEER_IP=$pip
		fi
	done
	cfg ${PEER}_INTERNAL_IP $PEER_IP
	[ $PEER = BACKUP ] && BACKUP_INTERNAL_IP=$PEER_IP
	[ $PEER = MAIN ] && MAIN_INTERNAL_IP=$PEER_IP
}

function spinner {
	# Spin a bit, while we're waiting for things to happen. 
	[ "$chars" = "" ] && chars='\|/-'
	chars="${chars#?}${chars%???}"
	printf '\b%.1s' "$chars"
}

function this_node_standby {

	if [ "$ISMAIN" = "YES" ]; then
		crm node standby main
	else
		crm node standby backup
	fi
}

function this_node_online {

	if [ "$ISMAIN" = "YES" ]; then
		crm node online main
	else
		crm node online backup
	fi
}

function packages_validate {
	# Check to make sure the required RPM's are installed.
	Packages=( mysql-server asterisk asterisk-voicemail )
	for p in ${Packages[@]}; do
		if ! rpm -q $p >/dev/null 2>&1; then
			echo REQUIRED Package $p not installed.
			exit
		fi
	done
	mkdir -p /usr/lib/ocf/resource.d/hipbx
	cp -f resource-agents/asterisk /usr/lib/ocf/resource.d/hipbx/asterisk
}

function find_mount {
	name=$1
	drbdvar="${name}_DISK"
	drbddev=${!drbdvar}
	if [ "$drbddev" = "" ] ; then
		echo "Programmer error. Someone called 'find_mount' with the parameter of $name," 1>&2
		echo "but I can't find that resource. Please fix." 1>&2
		exit
	fi
	mountstat=$(grep $drbddev /proc/mounts | cut -d\  -f2)
	mount_count=0
	while [ "$mountstat" = "" ]; do
		if [ $mount_count -gt 5 ]; then
			echo -e "Error.\nI've waited 30 seconds for the drbd disk to be ready. Please fix the disk" 1>&2
			echo "and run this script again." 1>&2
			echo "Timeout waiting for Pacemaker to mount $drbddev somewhere." 1>&2
			exit
		fi
		sleep 1
		mountstat=$(grep $drbddev /proc/mounts | cut -d\  -f2)
		mount_count=$(( $mount_count + 1 ))
	done
	echo $mountstat
}

function dir_contains_files {
	# Found this handy bit of code on Stack Overflow - http://stackoverflow.com/questions/91368
	# Written by Pumbaa80 - http://stackoverflow.com/users/27862
	dirname=$1
	shopt -q nullglob || resetnullglob=1;
	shopt -s nullglob;
	shopt -q dotglob || resetdotglob=1;
	shopt -s dotglob;
	files=($dirname/*); 
	[ "$resetdotglob" ] && shopt -u dotglob;
	[ "$resetnullglob" ] && shopt -u nullglob;
	[ "$files" ] && return 0
	return 1
}

function create_links {
	# Params:
	# $1 = source
	# $2 = dest
	# $3 = preserve dest data

	src=$1
	dst=$2
	keep=$3

	# Make sure dst exists.
	mkdir -p $dst

	# Is it already set up and pointing to the right place? If so, good, we're done.
	if [ -L "$src" ]; then
		if [ "$(readlink $src)" = "$dst" ]; then
			return 0
		else
			# It's pointing somewhere wrong.
			rm -f $src
			ln -s $dst $src
		fi
	fi

	# It's not a symlink, so, is there anything in there?
	if dir_contains_files $dst; then
		# OK, dst has files. What about src?
		if dir_contains_files $src; then
			# We have files in both src and dst. 
			if [ "$keep" = "no" ]; then
				# Blow away src, move src into dst
				rm -rf $src
				ln -s $dst $src
				return 0
			else
				# There are files in src _and_ dst. We wern't explicitly told to blow
				# away dst, so abort and cry.
				echo "I have a conflict. There are files in $src AND there are files"
				echo "in $dst."
				echo "I don't know which one wins, so I'm just going to give up and let you"
				echo "sort it out. Either delete the entire $src, or"
				echo "delete the entire $dst directory. There can be only one."
				echo "Re-run setup when you've done that and I'll continue on."
				echo "This can happen in a disaster-recovery situation. You most probably want"
				echo "to delete $src if this is the case"
				exit 
			fi
		else 
			# Files in dst, but none in src. 
			rm -rf $src
			ln -s $dst $src
			return 0
		fi
			
	fi
	# Dst doesn't contain files. 
	if dir_contains_files $src; then
		mv $src/* $dst
	fi
	rm -rf $src
	ln -s $dst $src
	return 0
}

function mysql_install {
	echo "Starting MySQL Filesystem..."
	crm resource start ms_drbd_mysql
	crm resource start fs_mysql
	# Make sure that I am the machine managing the resource
	echo -e "\tMigrating mysqld resource to this server..."
	crm resource migrate fs_mysql $(hostname) >/dev/null 2>&1
	# Check to see where the DRBD mysql resource is mounted, when it turns up.
	if [ "$(find_mount mysql)" = "/drbd/mysql" ]; then
		# This is a new install
		# Check to see if MySQL has stuff in /var/lib/mysql, and migrate it if it does.
		if [ -d /var/lib/mysql/mysql ]; then
			echo -en "\tRelocating MySQL data to Cluster Filesystem..."
			mv /var/lib/mysql/* /drbd/mysql
			echo "Done"
		else
			echo -e "\tData move not needed."
		fi
		# Now we have everything in /drbd/mysql, Tell pacemaker the new location.
		echo -e "\tRemounting under /var/lib/mysql"
		crm resource stop fs_mysql
		crm resource param fs_mysql set directory "/var/lib/mysql"
		crm resource start fs_mysql
	fi
	# MySQL Config file.
	if [ ! -h /etc/my.cnf ] ; then
		if [ ! -e /var/lib/mysql/my.cnf ]; then 
			cp /etc/my.cnf /var/lib/mysql/my.cnf
			rm -f /etc/my.cnf
			ln -s /var/lib/mysql/my.cnf /etc/my.cnf
		fi
	fi
	# Add MySQL RA
	crm configure primitive mysqld lsb:mysqld meta target-role="Stopped"
	echo group mysql fs_mysql ip_mysql mysqld | crm configure load update - 
	echo -e "\tStarting Clustered MySQL service"
	crm resource start mysqld
	crm resource unmigrate fs_mysql >/dev/null 2>&1
}

function asterisk_install {
	echo "Starting Asterisk Filesystem.."
	crm resource start ms_drbd_asterisk
	crm resource start fs_asterisk
	# Make sure that I am the machine managing the resource
	echo "Migrating resource to this server..."
	crm resource migrate fs_asterisk $(hostname) >/dev/null 2>&1
	# Wait for the partition to be mounted...
	find_mount asterisk > /dev/null
	# Create the symbolic links and move any files if they exist
	create_links /etc/asterisk /drbd/asterisk/etc yes
	create_links /etc/dahdi /drbd/asterisk/dahdi yes
	create_links /var/log/asterisk /drbd/asterisk/log no
	create_links /var/spool/asterisk /drbd/asterisk/spool no
	create_links /var/lib/asterisk /drbd/asterisk/lib no
	create_links /usr/lib64/asterisk/modules /drbd/asterisk/modules no
	chown -R apache /drbd/asterisk/*
	chmod -R 755 /drbd/asterisk/*
	# Remove all the conflicting files for FreePBX
	rm -f /etc/asterisk/chan_dahdi.conf /etc/asterisk/ccss.conf /etc/asterisk/sip_notify.conf
	rm -f /etc/asterisk/extensions.conf /etc/asterisk/iax.conf /etc/asterisk/features.conf
	rm -f /etc/asterisk/sip.conf /etc/asterisk/logger.conf

	# Add HiPBX Asterisk RA
	crm configure primitive asteriskd ocf:hipbx:asterisk meta target-role="Stopped"
	crm configure primitive dahdi lsb:dahdi meta target-role="Stopped"
	echo group asterisk fs_asterisk ip_asterisk dahdi asteriskd | crm configure load update - 
	echo -e "\tStarting Clustered Asterisk service"
	crm resource start asterisk
	crm resource unmigrate fs_asterisk >/dev/null 2>&1
}

function apache_install {
	echo "Starting Apache configuration.."
	crm resource start ms_drbd_http
	crm resource start fs_http
	# Make sure that I am the machine managing the resource
	echo "Migrating resource to this server..."
	crm resource migrate fs_http $(hostname) >/dev/null 2>&1
	# Wait for the partition to be mounted...
	find_mount http > /dev/null
	create_links /var/www /drbd/http/www yes
	create_links /var/log/httpd /drbd/http/logs yes
	create_links /etc/php.d /drbd/http/php yes
	chown -R apache /drbd/http/*
	safe_create_symlink /etc/php.ini /drbd/http/php.ini
	# Fix timezone in php.ini..
	. /etc/sysconfig/clock
	# Let apache view cluster status
	usermod -G haclient apache 2>/dev/null
	sed -i "s_^;*date.timezone.*\$_date.timezone = '$ZONE'_" /drbd/http/php.ini
	crm configure primitive httpd lsb:httpd meta target-role="Stopped"
	echo group http fs_http ip_http httpd | crm configure load update - 
	echo -e "\tStarting Clustered Apache service"
	crm resource start http
	crm resource unmigrate fs_http >/dev/null 2>&1
}
	
function fix_dahdi_perms {
	sed -i s/asterisk/apache/g /etc/udev/rules.d/*dahdi.rules
	udevadm control --reload-rules

}

function freepbx_install {
	cd freepbx-2.9.0
	mysql -hmysql -uhipbx -p$MYSQLPASS hipbx < SQL/newinstall.sql
	mysql -hmysql -uhipbx -p$MYSQLPASS asteriskcdrdb < SQL/cdr_mysql_table.sql
	./install_amp --dbhost=mysql --dbname=hipbx --username=hipbx --password=$MYSQLPASS --uid=apache --gid=apache --freepbxip=$http_IP --scripted
}

function safe_create_symlink {
	src=$1
	dst=$2
	if [ -h $src ] ; then
		# Does it point to a file that exists?
		if [ -f $(readlink $src) ] ; then
			echo -e "\t$src correct";
		else
			# Destination doesn't exist. Can we recover it?
			if [ -f ${src}.bak ] ; then
				echo "**** WARNING **** $src went missing. Restoring from ${src}.bak"
				cp ${src}.bak $dst
				rm -f $src
				ln -s $dst $src
			else
				echo "**** ERROR ****. I can't find a $src file.Cluster will be sad. Please fix."
				exit
			fi
		fi
	elif [ -f $src ] ; then 
		# Original file is still there.
		if [ -f $dst ] ; then
			# And there's already a destination. Keep it.
			rm -f $src
			ln -s $dst $src
		else
			# There's nothing in drbd. Back it up, create it, and link it.
			[ ! -f ${src}.bak ] && cp $src ${src}.bak
			mv $src $dst
			ln -s $dst $src
		fi
	fi
}

function freepbx_create_symlinks {
	safe_create_symlink /etc/freepbx.conf /drbd/asterisk/freepbx.conf
	safe_create_symlink /etc/amportal.conf /drbd/asterisk/amportal.conf
	create_links /usr/share/asterisk /drbd/asterisk/share no
}


function add_repos {
	echo -n 'Ensuring all repos are added...'
	add_elrepo_repo
	add_epel_repo
	add_atrpms_repo
	echo 'Done'
}

function add_elrepo_repo {
	# Installed?
	if [ -e /etc/yum.repos.d/elrepo.repo ] ; then
		return;
	fi

	yum -y install elrepo-release > /dev/null 2>&1

	# Dammit. Someone's running this on CentOS. Lets just throw it in
	cat > /etc/yum.repos.d/elrepo.repo << EOF
### Name: ELRepo.org Community Enterprise Linux Repository for el6
### URL: http://elrepo.org/

[elrepo]
name=ELRepo.org Community Enterprise Linux Repository - el6
baseurl=http://elrepo.org/linux/elrepo/el6/\$basearch/
mirrorlist=http://elrepo.org/mirrors-elrepo.el6
enabled=1
gpgcheck=0
protect=0

[elrepo-testing]
name=ELRepo.org Community Enterprise Linux Testing Repository - el6
baseurl=http://elrepo.org/linux/testing/el6/\$basearch/
mirrorlist=http://elrepo.org/mirrors-elrepo-testing.el6
enabled=0
gpgcheck=0
protect=0

[elrepo-kernel]
name=ELRepo.org Community Enterprise Linux Kernel Repository - el6
baseurl=http://elrepo.org/linux/kernel/el6/\$basearch/
mirrorlist=http://elrepo.org/mirrors-elrepo-kernel.el6
enabled=0
gpgcheck=0
protect=0

[elrepo-extras]
name=ELRepo.org Community Enterprise Linux Repository - el6
baseurl=http://elrepo.org/linux/extras/el6/\$basearch/
mirrorlist=http://elrepo.org/mirrors-elrepo-extras.el6
enabled=0
gpgcheck=0
protect=0
EOF

}

function add_epel_repo {
	# Is it installed?
	if [ -e /etc/yum.repos.d/epel.repo ] ; then
		return;
	fi

	yum -y install epel-release > /dev/null 2>&1

	# Dammit. Someone's running this on CentOS. Lets just throw it in
	cat > /etc/yum.repos.d/epel.repo << EOF
[epel]
name=Extra Packages for Enterprise Linux 6 - \$basearch
#baseurl=http://download.fedoraproject.org/pub/epel/6/\$basearch
mirrorlist=https://mirrors.fedoraproject.org/metalink?repo=epel-6&arch=\$basearch
failovermethod=priority
enabled=1
gpgcheck=0

[epel-debuginfo]
name=Extra Packages for Enterprise Linux 6 - \$basearch - Debug
#baseurl=http://download.fedoraproject.org/pub/epel/6/\$basearch/debug
mirrorlist=https://mirrors.fedoraproject.org/metalink?repo=epel-debug-6&arch=\$basearch
failovermethod=priority
enabled=0
gpgcheck=0

[epel-source]
name=Extra Packages for Enterprise Linux 6 - \$basearch - Source
#baseurl=http://download.fedoraproject.org/pub/epel/6/SRPMS
mirrorlist=https://mirrors.fedoraproject.org/metalink?repo=epel-source-6&arch=\$basearch
failovermethod=priority
enabled=0
gpgcheck=0
EOF

}

function add_atrpms_repo {

	# Is it installed?
	if [ -e /etc/yum.repos.d/atrpms.repo ] ; then
		# Ensure it's turned off by default.
		sed -i 's/enabled=./enabled=0/' /etc/yum.repos.d/atrpms*
		return;
	fi

	yum -y install atrpms-repo > /dev/null 2>&1

	# Dammit. Someone's running this on CentOS. Lets just throw it in
	cat > /etc/yum.repos.d/atrpms.repo << EOF
[atrpms]
name=Red Hat Enterprise Linux 6 - \$basearch - ATrpms
failovermethod=priority
baseurl=http://dl.atrpms.net/el6-\$basearch/atrpms/stable
enabled=0
gpgcheck=0

[atrpms-debuginfo]
name=Red Hat Enterprise Linux 6 - \$basearch - ATrpms - Debug
failovermethod=priority
baseurl=http://dl.atrpms.net/debug/el6-\$basearch/atrpms/stable
enabled=0
gpgcheck=0

[atrpms-source]
name=Red Hat Enterprise Linux 6 - \$basearch - ATrpms - Source
failovermethod=priority
baseurl=http://dl.atrpms.net/src/el6-\$basearch/atrpms/stable
enabled=0
gpgcheck=0
EOF

}

function fix_sysctl {
	# ICMP redirects are bad in a hiav. Don't do them. Don't send them. Don't honour them.
	for var in net.ipv4.conf.all.accept_redirects net.ipv4.conf.all.send_redirects net.ipv6.conf.all.accept_redirects net.ipv6.conf.all.send_redirects ; do
		grep $var /etc/sysctl.conf > /dev/null || echo "$var = 1" >> /etc/sysctl.conf
	done
}

function add_cluster_addresses {
	# Ensure that Asterisk only binds to the ASTERISK IP address.
	
	# SIP
	if grep udpbindaddr /etc/asterisk/sip_general_custom.conf > /dev/null ; then
		# It exists. Ensure it's right.
		sed -i "s/^udpbindaddr.*\$/udpbindaddr=$asterisk_IP/" /etc/asterisk/sip_general_custom.conf 
	else
		echo "udpbindaddr=$asterisk_IP" >> /etc/asterisk/sip_general_custom.conf
	fi
	if grep tcpbindaddr /etc/asterisk/sip_general_custom.conf > /dev/null ; then
		# It exists. Ensure it's right.
		sed -i "s/^tcpbindaddr.*\$/tcpbindaddr=$asterisk_IP/" /etc/asterisk/sip_general_custom.conf 
	else
		echo "tcpbindaddr=$asterisk_IP" >> /etc/asterisk/sip_general_custom.conf
	fi

	# IAX
	if grep bindaddr /etc/asterisk/iax_general_custom.conf > /dev/null ; then
		# It exists. Ensure it's right.
		sed -i "s/^bindaddr.*\$/bindaddr=$asterisk_IP/" /etc/asterisk/iax_general_custom.conf 
	else
		echo "bindaddr=$asterisk_IP" >> /etc/asterisk/iax_general_custom.conf
	fi

	# Dundi
	# Note - commented out by default. Uncomment and fix it anyway.
	if grep bindaddr /etc/asterisk/dundi.conf > /dev/null ; then
		# It exists. Ensure it's right.
		sed -i "s/^;bindaddr.*\$/bindaddr=$asterisk_IP/" /etc/asterisk/dundi.conf 
	else
		echo "bindaddr=$asterisk_IP" >> /etc/asterisk/dundi.conf
	fi

}
	
