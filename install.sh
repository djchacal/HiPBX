#!/bin/bash

#  HiPBX Installer Script. 
#  Copyright 2011, Rob Thomas <xrobau@gmail.com>

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

# Check if SELinux is enabled. If so, disable it and warn that it's been
# done.

if selinuxenabled; then
	echo "SELinux is enabled. I've turned it off for you. Be aware."
	sed -i s/SELINUX=enforcing/SELINUX=disabled/ /etc/selinux/config
	setenforce 0
fi

if [ "$1" = "--i-like-pain" ]; then
	echo "Enabling Masochist mode - You can now allocate a DRBD collection less than 50G"
	IHATEROB=true
else
	IHATEROB=false
fi

#yum -y groupinstall "Development tools"
#yum -y install atrpms-repo    # For fxload, iksemel and spandsp
#yum -y install epel-release # for php-pear-DB, soon to be removed as a prereq.
#yum -y install bc vim
#yum -y install libusb-devel 
#yum -y install fxload
#yum -y install iksemel iksemel-devel
#yum -y install httpd php php-fpdf
#yum -y install mysql-server
#yum -y install curl
#yum -y install mysql mysql-devel
#yum -y install php-pear-DB php-process
#yum -y install libxml2-devel ncurses-devel libtiff-devel libogg-devel
#yum -y install libvorbis vorbis-tools
#yum -y install pacemaker

chkconfig mysqld off
/etc/init.d/mysqld stop
chkconfig httpd off
/etc/init.d/httpd stop
chkconfig iptables off
/etc/init.d/iptables stop
chkconfig drbd off
/etc/init.d/drbd stop
modprobe drbd

# If /etc/hipbx.conf already exists, grab it and read the config
SETUPOK=yes
if [ -f /etc/hipbx.conf ]; then
	. /etc/hipbx.conf
	for checkvar in MYSQLPASS ISMASTER; do
		if [ "${!checkvar}" = "" ]; then
			echo \$$checkvar undefined. Restarting setup
			SETUPOK=no
		fi
	done
else
	SETUPOK=no
fi

cp /etc/hipbx.conf /etc/hipbx.conf.bak

# Make the /etc/hipbx.d directory if it doesn't already exist.
[ ! -d /etc/hipbx.d ] && mkdir /etc/hipbx.d
[ ! -d /var/run/heartbeat/crm ] && mkdir  -p /var/run/heartbeat/crm

# Generate a MySQL password, if one hasn't already been generated.
[ "$MYSQLPASS" = "" ] && MYSQLPASS=`tr -dc A-Za-z0-9 < /dev/urandom | head -c16`
echo MYSQLPASS=$MYSQLPASS > /etc/hipbx.conf

# Now we've blown away the conf file, lets put back all the SLAVE configs we
# won't know about later, and if they're not there, take a guess.
[ "$SLAVE_VGNAME" = "" ] && SLAVE_VGNAME=unknown_vg
echo SLAVE_VGNAME=$SLAVE_VGNAME >> /etc/hipbx.conf


# Is this the master or slave server?
if [ "$ISMASTER" = "" ]; then
	echo -n "Is this the Master or Slave server? [M/s]: "
	read -e resp
	if [ "$resp" = "" -o "$resp" = "M" -o "$resp" = "m" ]; then
		ISMASTER=YES
	else
		ISMASTER=NO
	fi
fi
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
		
echo ISMASTER=$ISMASTER >> /etc/hipbx.conf

if [ $ISMASTER = NO ]; then
	echo -n "Please enter Heartbeat IP Address of Master [10.80.17.1]: "
	read -e masterip
	[ "$masterip" = "" ] && masterip="10.80.17.1"
	if ping -c1 $masterip > /dev/null; then
		echo "$slaveip able to ping"
	else
		echo  -e "Error: Unable to ping $masterip. Sorry.\nYou need to fix this. If this is a Master server, delete the /etc/hipbx.conf file and re-run install"
		exit
	fi
fi

[ $ISMASTER = NO ] && slavesetup

echo "Starting Master setup."


#### LVM Setup Begin
# Default storage percentages.  Minimum sizes (with 50GB lvm space used) in brackets
# MySQL = 30 (15G)
# Asterisk = 30 (15G)
# httpd = 20 (10G)
# dhcpd = 10 (5G)
# spare = 10 (5G)
# Don't try to use decimals here. Integers only.
[ "$SERVICES" = "" ] && SERVICES=( mysql=30 asterisk=30 httpd=20 dhcpd=10 spare=10 )

NBRSVCS=$((${#SERVICES[@]} - 1))
SANITYSIZE=0
SERVSTRING="SERVICES=("
for element in $(seq 0 $NBRSVCS); do
	SERVSTRING="$SERVSTRING ${SERVICES[$element]} "
	SERVICENAME[$element]=`echo ${SERVICES[$element]} | awk -F= ' { print $1 } '`
	SERVICEPCNT[$element]=`echo ${SERVICES[$element]} | awk -F= ' { print $2 } '`
	SANITYSIZE=$(( $SANITYSIZE + ${SERVICEPCNT[$element]} ))
done

echo "$SERVSTRING)" >> /etc/hipbx.conf

if [ $SANITYSIZE -gt 100 ]; then
	echo -e "Severe programmer fail.\n The total percentages of SERVICES is greater than 100."
	echo -e " Please fix the SERVICES variable, and then poke yourself in the eye."
	exit
fi

echo "lvm:"
echo -e "\tChecking for existing LVM volumes for drbd..."
ALLOCATED=0
USEDSPACE=0
for x in $(seq 0 $NBRSVCS); do
	echo -ne "\t\t${SERVICENAME[$x]} - "
	USED=`lvdisplay -C --noheadings --nosuffix --units g | grep drbd_${SERVICENAME[$x]} | awk ' { print $4 }'`
	if [ "$USED" != "" ]; then 
		echo "Found (${USED}G)"
		echo "drbd_${SERVICENAME[$x]}=${USED}" >> /etc/hipbx.conf
		ALLOCATED=$(( $ALLOCATED + ${SERVICEPCNT[$x]} ))
		USEDSPACE=$(( $USEDSPACE + `printf %0.f $USED` ))
		SELECTEDVG=`lvdisplay -C --noheadings --nosuffix --units g | grep drbd_${SERVICENAME[$x]} | awk ' { print $2 }'`
	else
		echo "Not Found"
		LVMAKE=( ${LVMAKE[@]-} $x )
	fi
done

if [ "$LVMAKE" != "" ]; then
	echo -e "\tLooking for vg's with spare space..."
	VGS=`vgdisplay -C --noheadings --nosuffix --units g | awk ' { print $1"="$7 }'`
	xVGS='vg_fake1=0
vg_fake2=49
vg_voipa=175.9
vg_fake3=0'

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
	
	MASTER_VGNAME=$SELECTEDVG
	echo -ne "\tYou picked $VGNAME with ${VGSPACE}G free.\n\tWould you like to use all available space? [Yn]: "
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
		lvsize=`echo ${BASESIZE}*.${SERVICEPCNT[${LVMAKE[$x]}]} - .5 | bc`
		lvsize=`printf %0.f $lvsize`
		echo "(${lvsize}G) "
		if $(lvcreate -L${lvsize}g $VGNAME -n drbd_${SERVICENAME[${LVMAKE[$x]}]} > /dev/null); then
			echo "drbd_${SERVICENAME[${LVMAKE[$x]}]}=${lvsize}" >> /etc/hipbx.conf
		else
			echo "Something really bad has happened. I can't create the logical volume."
			echo "This is the command I ran:"
			echo -e "\tlvcreate -L${lvsize}g $VGNAME -n drbd_${SERVICENAME[${LVMAKE[$x]}]}"
			echo "Try running it yourself and see if you can fix the problem."
			exit
		fi
	done
fi
echo MASTER_VGNAME=$SELECTEDVG >> /etc/hipbx.conf
MASTER_VGNAME=$SELECTEDVG

echo "SSH:"
if [ "$SSH_MASTER" = "" ]; then
	echo -e "\t\$SSH_MASTER not found."
	if [ -f /etc/hipbx.d/ssh_key_master -a -f /etc/hipbx.d/ssh_key_master.pub ]; then
		echo -e "\tHowever, /etc/hipbx.d/ssh_key_master exists"
	else
		rm -f /etc/hipbx.d/ssh_key_master /etc/hipbx.d/ssh_key_master.pub
		echo -en "\tGenerating MASTER ssh Public key..."
		ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_master -N ""
		echo "Done"
	fi
	SSH_MASTER=`cat /etc/hipbx.d/ssh_key_master.pub`
else
	echo -en "\tMaster ssh key exists"
	test=`cat /etc/hipbx.d/ssh_key_master.pub 2>/dev/null`
	if [ "$SSH_MASTER" != "$test" ]; then
		echo -e " - but doesn't match hipbx.conf! Regenerating."
		rm -f /etc/hipbx.d/ssh_key_master /etc/hipbx.d/ssh_key_master.pub
		echo -en "\tGenerating MASTER ssh Public key..."
		ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_master -N ""
		SSH_MASTER=`cat /etc/hipbx.d/ssh_key_master.pub`
		echo "Done"
	else
		echo " and seems valid"
	fi
fi
echo "SSH_MASTER=\"$SSH_MASTER\"" >> /etc/hipbx.conf

if [ "$SSH_SLAVE" = "" ]; then
	echo -e "\t\$SSH_SLAVE not found."
	if [ -f /etc/hipbx.d/ssh_key_slave -a -f /etc/hipbx.d/ssh_key_slave.pub ]; then
		echo -e "\tHowever, /etc/hipbx.d/ssh_key_slave exists"
	else
		rm -f /etc/hipbx.d/ssh_key_slave /etc/hipbx.d/ssh_key_slave.pub
		echo -en "\tGenerating SLAVE ssh Public key..."
		ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_slave -N ""
		echo "Done"
	fi
	SSH_SLAVE=`cat /etc/hipbx.d/ssh_key_slave.pub`
else
	echo -en "\tSlave ssh key exists"
	test=`cat /etc/hipbx.d/ssh_key_slave.pub 2>/dev/null`
	if [ "$SSH_SLAVE" != "$test" ]; then
		echo "- but doesn't match hipbx.conf! Regenerating."
		rm -f /etc/hipbx.d/ssh_key_slave /etc/hipbx.d/ssh_key_slave.pub
		echo -en "\tGenerating SLAVE ssh Public key..."
		ssh-keygen -q -t dsa -f /etc/hipbx.d/ssh_key_slave -N ""
		SSH_SLAVE=`cat /etc/hipbx.d/ssh_key_slave.pub`
		echo "Done"
	else
		echo " and seems valid"
	fi
fi
echo "SSH_SLAVE=\"$SSH_SLAVE\"" >> /etc/hipbx.conf


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
echo -en "\tPlesae enter the INTERNAL, PRIVATE interface "
[ "$MASTER_INTERNAL_INT" = "" ] && MASTER_INTERNAL_INT="unknown"
echo -n "[$MASTER_INTERNAL_INT]: "
read internalint
if [ "$internalint" = ""  -a "$MASTER_INTERNAL_INT" = "unknown" ]; then
	echo "Wait.. What? I don't KNOW what the interface is. That's why it says 'unknown' there."
	echo "Next time, really pick a network interface."
	exit
fi
if [ "$internalint" = "" ]; then
	internalint=$MASTER_INTERNAL_INT
fi

if $(ip addr show $internalint > /dev/null 2>&1 ); then
	MASTER_INTERNAL_IP=`ip -o addr show $internalint | grep -v secondary | grep ${internalint}$|awk '{print $4}'|sed 's^/[0-9]*^^'`
	if [ "$MASTER_INTERNAL_IP" = "" ]; then
		echo "I'm guessing that was a typo. I can't get an IP address from that interface."
		echo "Try again."
		exit
	fi
	echo -e "\tSetting INTERNAL interface to $internalint ($MASTER_INTERNAL_IP)"
else 
	echo "I'm guessing that was a typo. I can't find that interface. Sorry. Try again"
	exit
fi
echo "MASTER_INTERNAL_IP=$MASTER_INTERNAL_IP" >> /etc/hipbx.conf
echo "MASTER_INTERNAL_INT=$internalint" >> /etc/hipbx.conf


echo -ne "\tPlease select the EXTERNAL, PUBLIC interface "
[ "$MASTER_EXTERNAL_INT" = "" ] && MASTER_EXTERNAL_INT="unknown"
echo -n "[$MASTER_EXTERNAL_INT]: "
read externalint
if [ "$externalint" = ""  -a "$MASTER_EXTERNAL_INT" = "unknown" ]; then
	echo "Wait.. What? I don't KNOW what the interface is. That's why it says 'unknown' there."
	echo "Next time, really pick a network interface."
	exit
fi
if [ "$externalint" = "" ]; then
	externalint=$MASTER_EXTERNAL_INT
fi

if $(ip addr show $externalint > /dev/null 2>&1 ); then
	MASTER_EXTERNAL_IP=`ip -o addr show $externalint | grep -v secondary | grep ${externalint}$|awk '{print $4}'|sed 's^/[0-9]*^^'`
	if [ "$MASTER_EXTERNAL_IP" = "" ]; then
		echo "I'm guessing that was a typo. I can't get an IP address from that interface."
		echo "Try again."
		exit
	fi
	echo -e "\tSetting EXTERNAL interface to $externalint ($MASTER_EXTERNAL_IP)"
else 
	echo "I'm guessing that was a typo. I can't find that interface. Sorry. Try again"
	exit
fi
echo "MASTER_EXTERNAL_IP=$MASTER_EXTERNAL_IP" >> /etc/hipbx.conf
echo "MASTER_EXTERNAL_INT=$externalint" >> /etc/hipbx.conf

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
echo "MULTICAST_ADDR=$MULTICAST_ADDR" >> /etc/hipbx.conf
while [ "$SLAVE_INTERNAL_IP" = "" ]; do
	echo -en "\tPlease enter SLAVE internal IP address: "
	read slaveip
	if $(ping -c1 $slaveip > /dev/null 2>&1); then
		echo -e "\tMachine is up."
		SLAVE_INTERNAL_IP=$slaveip
	else
		echo -e "\tMachine is down. I can continue if you're sure that's the right address,"
		echo -e "\tbut for sanity checking, it's a good idea to have the slave machine up"
		echo -e "\twhile you're installing."
		echo -en "Are you sure you want to continue with \"$slaveip\" as the address? [yN]: "
		read sure
		[ "$sure" = "Y" -o "$sure" = "y" ] && SLAVE_INTERNAL_IP=$slaveip
	fi
done
echo "SLAVE_INTERNAL_IP=$SLAVE_INTERNAL_IP" >> /etc/hipbx.conf
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
	echo ${SERVICENAME[$x]}_IP=$newip >> /etc/hipbx.conf
done


echo -en "\tUpdating hosts file..."
grep ${SLAVE_INTERNAL_IP}.slave /etc/hosts > /dev/null || echo -e "$SLAVE_INTERNAL_IP\tslave" >> /etc/hosts
grep ${MASTER_INTERNAL_IP}.master /etc/hosts > /dev/null || echo -e "$MASTER_INTERNAL_IP\tmaster" >> /etc/hosts

# Figure out netmasks. This isn't line noise, honest.
EXTERNAL_CLASS=`ip -o addr | grep -v secondary | grep ${MASTER_EXTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_'`
INTERNAL_CLASS=`ip -o addr | grep -v secondary | grep ${MASTER_INTERNAL_INT}$ | sed 's_.*/\([0-9]*\) .*_\1_'`
echo INTERNAL_CLASS=$INTERNAL_CLASS >> /etc/hipbx.conf
echo EXTERNAL_CLASS=$EXTERNAL_CLASS >> /etc/hipbx.conf


echo -n "Generating corosync configuration file: "
echo " totem {
	version: 2
	secauth: off
	threads: 0
	interface {
		ringnumber: 0
		bindnetaddr: $MASTER_INTERNAL_IP
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
	grou: root
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

# Now corosync and pacemaker are up, lets make them work!
echo -en "Configuring corosync\n\t(This may take up to 60 seconds, if the cluster isn't fully up yet)..."
while :; do
	crm configure property stonith-enabled=false 2>&1 | grep ERROR > /dev/null || break
	echo -n "."
done
echo
crm configure property no-quorum-policy=ignore
crm configure property default-action-timeout=240
for x in $(seq 0 $NBRSVCS); do
	CLUSTER=${SERVICENAME[$x]}_IP
	echo -en "\tCreating Cluster IP ${CLUSTER}.."
	crm configure primitive ip_${SERVICENAME[$x]} ocf:heartbeat:IPaddr2 params ip=${!CLUSTER} cidr_netmask=$INTERNAL_CLASS op monitor interval=10s
	echo "Done"
done
echo "Done"
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
	echo ${SERVICENAME[$x]}_DISK=/dev/drbd$x >> /etc/hipbx.conf
	if $(drbdadm dump-md ${SERVICENAME[$x]} > /dev/null 2>&1); then
		echo -e " (already initialized)"
	else
		echo -ne " (initializing..."
		
		echo yes|drbdadm create-md  ${SERVICENAME[$x]} > /dev/null  2>&1
		echo "Done)"
	fi
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
		op monitor interval="10s" > /dev/null 2>&1
	crm configure ms ms_drbd_${SERVICENAME[$x]} drbd_${SERVICENAME[$x]} \
		meta master-max="1" \
		master-node-max="1" \
		clone-max="2" \
		clone-node-max="1" \
		notify="true" > /dev/null 2>&1
	crm configure primitive fs_${SERVICENAME[$x]} ocf:heartbeat:Filesystem \
		params device="/dev/drbd$x" \
		directory="/drdb/${SERVICENAME[$x]}" \
		fstype="ext3" > /dev/null 2>&1
	crm configure group ${SERVICENAME[$x]} fs_${SERVICENAME[$x]} ip_${SERVICENAME[$x]} > /dev/null 2>&1

done

echo "Setting up cluster:"

	


exit


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


exit

function slavesetup {
	echo Slave Setup not implemented yet.
	exit
}
