# No bang-hash, this shuld be . included from the main setup script.
# Check to make sure it is.

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
configure-lvm

# Check SSH keys, and regenerate if needed
check-ssh

# Configure Networking
config-networking

# Fix hosts file
fixhosts

# Calculate Netmasks
calc-netmasks

# Create basic corosync configuration
gen-corosync

# Configure Corosync
config-corosync

# Configure DRBD
setup-drbd
exit


