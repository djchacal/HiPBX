#!/bin/bash

if [ "$1" = "--i-like-pain" ]; then
	echo "Enabling Masochist mode - You can now allocate a DRBD collection less than 50G"
	IHATEROB=true
else
	IHATEROB=false
fi

#yum -y groupinstall "Development tools"
#yum -y install atrpms-repo    # For fxload, iksemel and spandsp
#yum -y install epel-release # for php-pear-DB, soon to be removed as a prereq.
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
echo ISMASTER=$ISMASTER > /etc/hipbx.conf

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



echo "drbd:"
echo -e "\tLooking for vg's with spare space..."
VGS=`vgdisplay -C --noheadings --nosuffix --units G | awk ' { print $1"="$7 }'`
VGS='vg_fake1=0
vg_fake2=49
vg_voipa=188.87
vg_fake3=0'


# Default storage percentages.  Minimum sizes (with 50GB lvm space used) in brackets
# MySQL = 30 (15G)
# Asterisk = 30 (15G)
# httpd = 20 (10G)
# dhcpd = 10 (5G)
# spare = 10 (5G)
SERVICES="mysql=30 asterisk=30 httpd=20 dhcpd=10 spare=10"

SELECTEDVG=not-found  # '-' is an invalid character in a volume group, so not-found will never be a valid answer
for vg in $VGS; do
	VGNAME=`echo $vg | awk -F= ' { print $1 } '`
	VGSPACE=`echo $vg | awk -F= ' { print $2 } '`
	if [ $VGSPACE = 0 ]; then
		echo -e "\tVG $VGNAME has no free space, skipping."
		continue
	fi

	if [ $IHATEROB = false -a $(echo "$VGSPACE < 50" | bc) -eq 1 ]; then
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

echo -ne "\tYou picked $VGNAME with ${VGSPACE}G free. Would you like to use all available space? [Yn]: "
read usespace
if [ "$usespace" = "" -o "$usespace" = "Y" -o "$usespace" = "y" ]; then
	echo -e "\tUsing all ${VGSPACE}G available."
else
	echo -ne "\tHow much space would you like to use (in Gigabytes, minimum 50) [50]: "
	read wantedspace
	if [ $IHATEROB = false -a $(echo "$wantedspace < 50" | bc) -eq 1 ]; then
		echo -e "\tLook. You can't expand a DRBD volume. You REALLY want to give it as much"
		echo -e "\tspace as you can at the start. If you ABSOLUTELY MUST use less than 50G,"
		echo -e "\trestart install.sh with the parameter --i-like-pain. That will bypass this"
		echo -e "\tmessage"
		exit
	fi
fi

exit








echo "MySQL..."

# Generate a MySQL password, if one hasn't already been generated.
[ "$MYSQLPASS" = "" ] && MYSQLPASS=`tr -dc A-Za-z0-9 < /dev/urandom | head -c16`
echo MYSQLPASS=$MYSQLPASS >> /etc/hipbx.conf

# First, can we connect to MySQL without a password?
if (mysql -equit >/dev/null 2>&1); then
	# We can. Set the password.
	echo -e "\tSetting Password to $MYSQLPASS"
	`mysqladmin password $MYSQLPASS > /dev/null`
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
	`mysqladmin -p$MYSQLPASS create hipbx`
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
		`mysql -p$MYSQLPASS -e"$CREATE"`
		GRANT='GRANT ALL PRIVILEGES ON hipbx.* TO "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
		`mysql -p$MYSQLPASS -e"$GRANT"`
	done
	echo ""
fi


exit

function slavesetup {
	echo Slave Setup not implemented yet.
	exit
}
