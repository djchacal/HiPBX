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

# Load functions from installer 
. scripts/functions.sh

# Check if SElinux is enabled
selinux

# Install packages if required
# I guess do a rpm -qa | grep ... for all (!) the RPMs. For the moment,
# just uncomment when required.
#installpackages

# Ensure that mysqld, httpd, iptables and drbd are off, and won't 
# automatically start up
# It doesn't matter if we shut down DRBD, as if you're running the
# installer, the machine is obviously not in production.
disableall

# If /etc/hipbx.conf already exists, grab it and read the config
if [ -f /etc/hipbx.conf ]; then
	. /etc/hipbx.conf
fi
cp /etc/hipbx.conf /etc/hipbx.conf.bak


# Make the /etc/hipbx.d directory if it doesn't already exist.
hipbx-init

# Is this the master or slave server?
if [ "$ISMASTER" = "" ]; then
	echo "This appears to be a new install - /etc/hipbx.conf doesn't exist."
	echo "You need to select if this is the 'master' or 'slave' server."
	echo "Please note that these names are pretty much arbritary, and don't"
	echo "mean that one server is preferred over the other. The only reason"
	echo "for calling them 'master' and 'slave' is that that is what the USB "
	echo "connections on the Xorcom Astribanks refer to. If you're not using"
	echo "Astribanks, the choice is up to you. If you are using Astribanks,"
	echo "the Dahdi failover scripts require that the 'master' server is"
	echo "plugged into the 'master' port(s), and the 'slave' server is plugged"
	echo "into the 'slave' ports."
	echo "If your two servers have different amounts of disk space, you will"
	echo "save yourself a lot of effort if you set the one with the SMALLER"
	echo "amount of disk as the MASTER, and install that machine first."
	echo -n "Is this the Master or Slave server? [m/s]: "
	read -e resp
	if [ "$resp" = "" ]; then
		echo "No default. You must select 'M'aster or 'S'lave."
		exit
	fi
	if [ "$resp" = "M" -o "$resp" = "m" ]; then
		ISMASTER=YES
	else
		ISMASTER=NO
	fi
	echo -n "Are you creating a new HiPBX cluster? [Y/n]: "
	read -e resp
	if [ "$resp" = "" -o "$resp" = "Y" -o "$resp" = "y" ]; then
		NEWCLUSTER=YES
	else
		NEWCLUSTER=NO
	fi
fi

# Set the hostname of the machine to be 'master' or 'slave'
fix_hostname

echo ISMASTER=$ISMASTER >> /etc/hipbx.conf

if [ $NEWCLUSTER = YES ]; then
	. scripts/setup-newcluster.sh
else
	. scripts/setup-joincluster.sh
fi
