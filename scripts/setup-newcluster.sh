# No bang-hash, this is not a standalone script. This shuld only be 
# . included from the main setup script.  Check to make sure it is.
if  ! (type -t configure_lvm | grep function > /dev/null) ; then
	echo "This script cannot be run natively. Run setup.sh"
	exit
fi

if [ "$ISMASTER" = "YES" ]; then
	MYNAME=master
else
	MYNAME=slave
fi 
echo "Starting new cluster setup on $MYNAME."
exit

# Set up LVM
configure_lvm

# Check SSH keys, and regenerate if needed
check_ssh

# Configure Networking
config_networking

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
exit


