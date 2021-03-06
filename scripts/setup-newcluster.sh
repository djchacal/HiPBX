# No bang-hash, this is not a standalone script. Do not put one here, thinking it's an error.

#  HiPBX Installer Script - Create a new cluster
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


# Check to make sure no-one's trying to run this script directly.
if  ! (type -t configure_lvm | grep function > /dev/null) ; then
	echo "This script cannot be run by itself. Run install.sh"
	exit
fi

if [ "$ISMAIN" = "YES" ]; then
	MYNAME=main
else
	MYNAME=backup
fi 
echo "Starting new cluster setup on $MYNAME."

# Set up LVM
configure_lvm

# Check SSH keys, and regenerate if needed
check_ssh
add_ssh

# Configure Networking
config_networking

# Ask for the peer's address
get_peer_addr

# Fix hosts file
fixhosts

# Calculate Netmasks
calc_netmasks

# Create basic corosync configuration
gen_corosync

# Configure Corosync
config_corosync

# Configure DRBD
setup_drbd

# Start this node
this_node_online

# Do MySQL
mysql_install
setup_mysql

# Do Asterisk
fix_dahdi_perms
asterisk_install
add_cluster_addresses

# Apache
apache_install
freepbx_install
freepbx_create_symlinks

