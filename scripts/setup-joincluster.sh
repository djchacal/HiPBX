# No hash-bang, this is not a standalone script. Do not put one here, thinking it's an error.

#  HiPBX Installer Script - Join an existing cluster.
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

# This is run when we need to join an existing cluster.  Ask for IP 
# address of other node

echo "Joining an existing cluster."
echo "This script will now connect to the existing cluster node, and retrieve"
echo "the current cluster configuration. No changes will me made to the running"
echo "node."
echo -n "Please enter any IP address of the other node in the cluster: "
read otherip
echo -n "Checking if machine is reachable..."
if ! ping -w1 -q $otherip > /dev/null; then
	echo -e "Fail.\nUnable to ping other node. Check IP address and connectivity"
	exit
else
	echo "Up."
fi
echo "I will now retrieve the contents of the /etc/hipbx.d directory from"
echo "the other node, using SSH. You may be prompted to verify the host key,"
echo "and then for the root password."
scp $otherip:/etc/hipbx.d/* /etc/hipbx.d
if [ ! -f /etc/hipbx.d/hipbx.conf ]; then
	echo "Bad, sad, not good things have happened."
	echo "I can't see the file /etc/hipbx.d/hipbx.conf - maybe you mis-typed the"
	echo "password or something? But I can't continue. Sorry. Try again."
	exit
fi

cfg ISMAIN $ISMAIN

# Load our configuration. Huzzah! 
. /etc/hipbx.d/hipbx.conf


fixhosts
configure_lvm
check_ssh
add_ssh
get_ssh_keys
gen_corosync
this_node_standby
setup_drbd
# Create Asterisk links
create_links /etc/asterisk /drbd/asterisk/etc no
fix_dahdi_perms
# MySQL Configuration file
rm -f /etc/my.cnf
ln -s /var/lib/mysql/my.cnf /etc/my.cnf
# Create www links on slave..
create_links /var/www /drbd/http/www yes
create_links /var/log/httpd /drbd/http/logs yes
create_links /etc/php.d /drbd/http/php yes
ln -sf /drbd/http/php.ini /etc/php.ini
ln -sf /drbd/asterisk/freepbx.conf /etc/freepbx.conf
ln -sf /drbd/asterisk/amportal.conf /etc/amportal.conf
rm -rf /usr/share/asterisk
ln -sf /drbd/asterisk/share /usr/share/asterisk 
rm -rf /var/lib/asterisk
ln -sf /drbd/asterisk/lib /var/lib/asterisk
rm -rf /var/spool/asterisk
ln -sf /drbd/asterisk/spool /var/spool/asterisk
rm -rf /etc/dahdi
ln -sf /drbd/asterisk/dahdi /etc/dahdi

usermod -G haclient apache

# Don't forget to enable me!
this_node_online
