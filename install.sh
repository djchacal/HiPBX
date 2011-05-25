#!/bin/bash

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
	read resp
	if [ "$resp" = "" -o "$resp" = "M" -o "$resp" = "m" ]; then
		ISMASTER=YES
	else
		ISMASTER=NO
	fi
fi
echo ISMASTER=$ISMASTER > /etc/hipbx.conf

if [ $ISMASTER = NO ]; then
	echo -n "Please enter Heartbeat IP Address of Master [10.80.17.1]: "
	read masterip
	[ "$masterip" = "" ] && masterip="10.80.17.1"
	if ping -c1 $masterip > /dev/null; then
		echo "$slaveip able to ping"
	else
		echo  -e "Error: Unable to ping $masterip. Sorry.\nYou need to fix this. If this is a Master server, delete the /etc/hipbx.conf file and re-run install"
		exit
	fi
fi

[ $ISMASTER = NO ] && slavesetup

echo -e "Starting Master setup.\nMySQL..."

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
	#`echo mysqladmin -p$MYSQLPASS create hipbx > /dev/null`
	`mysqladmin -p$MYSQLPASS create hipbx`
	echo -en "\tCreating HiPBX mysql user.."
	for host in localhost master slave cluster; do
		echo -n "$host "
		CREATE='CREATE USER "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
		`mysql -p$MYSQLPASS -e"$CREATE"`
		GRANT='GRANT ALL PRIVILEGES ON hipbx.* TO "hipbx"@"'$host'" IDENTIFIED BY "'$MYSQLPASS'"'
		#`mysql -p$MYSQLPASS -e"$GRANT"`
		`mysql -p$MYSQLPASS -e"$GRANT"`
	done
	:
fi


exit

function slavesetup {
	echo Slave Setup not implemented yet.
	exit
}
