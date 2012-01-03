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

if [ "$1" = "--i-like-pain" ]; then
        echo "Enabling Masochist mode - You can now allocate a DRBD collection less than 50G"
        IHATEROB=true
else
        IHATEROB=false
fi


# Load functions from installer 
. scripts/functions.sh

# If /etc/hipbx.d/hipbx.conf  exists, grab it and read the config
if [ -f /etc/hipbx.d/hipbx.conf ]; then
	cp /etc/hipbx.d/hipbx.conf /etc/hipbx.d/hipbx.conf.bak
	. /etc/hipbx.d/hipbx.conf
fi

# Ensure ICMP redirects are turned off
fix_sysctl

# Check if SElinux is enabled
selinux

# Install packages if required
# I guess do a rpm -qa | grep ... for all (!) the RPMs. For the moment,
# just uncomment when required.
#installpackages

# Install packages.
installpackages
# Sanity check for required packages. This will encourage people to
# fix internet/yum errors. 
packages_validate

# Ensure that mysqld, httpd, iptables and drbd are off, and won't 
# automatically start up
# It doesn't matter if we shut down DRBD, as if you're running the
# installer, the machine is obviously not in production.
disableall

# Make the /etc/hipbx.d directory if it doesn't already exist.
hipbx_init

# Organise the MySQL Password, we'll need this laster
mysql_password

NEWCLUSTER=NO

# Is this the main or backup server?
if [ "$ISMAIN" = "" ]; then
	echo "This appears to be a new install - /etc/hipbx.d/hipbx.conf doesn't exist."
	echo "You need to select if this is the 'main' or 'backup' server."
	echo "THESE NAMES ARE ARBRITARY AND DO NOT REFLECT A SPECIFIC SERVICE"
	echo "The only reason for calling them 'main' and 'backup' is that that is"
	echo "is what the USB ports on the Xorcom Astribanks refer to. If you're not"
	echo "using Astribanks, the choice is up to you. If you ARE using Astribanks,"
	echo "the Dahdi failover scripts require that the 'main' server is"
	echo "plugged into the 'main' port(s), and the 'backup' server is plugged"
	echo "into the 'backup' ports."
	echo "If your two servers have different amounts of disk space, you will"
	echo "save yourself a lot of effort if you create the cluster on one with"
	echo "the SMALLER amount of disk space."
	echo -n "Is this the Main or Backup server? [m/b]: "
	read resp
	if [ "$resp" = "" ]; then
		echo "No default. You must select 'M'ain or 'B'ackup."
		exit
	fi
	if [ "$resp" = "M" -o "$resp" = "m" ]; then
		cfg ISMAIN YES
		MYNAME=MAIN
	elif [ "$resp" = "B" -o "$resp" = "b" ]; then
		cfg ISMAIN NO
		MYNAME=BACKUP
	else 
		echo "Sorry. You must select 'M'ain or 'B'ackup."
		exit
	fi
fi

echo -n "Are you creating a new HiPBX cluster? [N/y]: "
read resp
if [ "$resp" = "Y" -o "$resp" = "y" ]; then
	NEWCLUSTER=YES
fi

# Set the hostname of the machine to be 'main' or 'backup'
fix_hostname

if [ "$NEWCLUSTER" = "YES" ]; then
	. scripts/setup-newcluster.sh
else
	. scripts/setup-joincluster.sh
fi
