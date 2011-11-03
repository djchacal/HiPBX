#!/usr/bin/perl
#
# Load configuration file
#
my $c = '/etc/hipbx.d/hipbx.conf';
my $mysqlpass = undef;
my $mysqlhost = undef;
my $mysqldb = 'hipbx';
my $basedir = '/drbd/dhcp';
my $header = 'dhcpd.header';
my $conf = '/etc/dhcpd/dhcpd.conf';
my $pid = $$;

use DBI;
open (my $conf, "/etc/hipbx.d/hipbx.conf");

while (<$conf>) {
	chomp;
	s/\"//g;
	my ($var, $val) = split(/=/);
	if ( $var eq "MYSQLPASS" ) { $mysqlpass = $val; }
	if ( $var eq "mysql_IP" ) { $mysqlhost = $val; }
}

if (! $mysqlpass || !$mysqlhost) {
	die "Couldn't get database info";
}
close $conf;

my $dbh = DBI->connect("dbi:mysql:$mysqldb", 'root', $mysqlpass) or die "Can't connect to MySQL";

my $q = $dbh->prepare('select mac,ext from simple_endpointman_mac_list m, simple_endpointman_line_list l where m.id=l.mac_id');
$q->execute();

# Uncomment the next line after you've got more than 10 phones in the system.
# It's there to ensure that a bad query doesn't nuke your dhcpd.conf.
# Sanity Check: More than 10 rows? 
#if ($q->rows lt 10) {
#	die "Got less than 10 rows? WTF?";
#}

my $contents = '';

# OK, we're good to go. Lets grab the header..
open (my $h, "$basedir/$header") or die "Can't open file $basedir/$header: $!";
while (<$h>) { $contents .= $_; }
close $h;

# Generate the new dhcpd.conf file
while (($mac, $ext) = $q->fetchrow_array()) {
	$contents .= "host $mac { ";
	# Split the mac into words, and then splice them back together with colons
	$mac = join(':', split(/([0-9a-f]{2})/i, $mac));
	$mac =~ s/::/:/g;
	$mac =~ s/^://;
	# CONFIGURE YOUR IP ADDRESS HERE. We just use the range 10.4.10x.zy, where
	# x y and z are the three digit extension. So exten 100 would be on the IP
	# address 10.4.101.0, whilst exten 333 would be on the IP address 10.4.103.33
	# The Exten is 10.4.10x.yz
	($x, $y, $z) = split(undef, $ext);
	$ipaddr = "10.4.10$x.0$y$z";
	$contents .= " hardware ethernet $mac; fixed-address $ipaddr; }\n";
}

open (OUT, ">$basedir/dhcpd.conf.$pid") or die "Can't create file $basedir/dhcpd.conf.$pid: $!";
print OUT $contents;
close OUT;

# Now, check if this is the same as the previous one.
$newmd = `md5sum $basedir/dhcpd.conf | cut -d' '  -f1`;
$oldmd = `md5sum $basedir/dhcpd.conf.$pid | cut -d' '  -f1`;

if ($newmd eq $oldmd) {
	# No changes. Exit happily.
	unlink ("$basedir/dhcpd.conf.$pid");
	exit;
}

# Ooh. Changes. Lets rotate them back 20 times.
my $cb = "$basedir/dhcpd.conf.bak";

unlink ("$cb.20");
for(my $r = 19; $r; $r--) {
	my $n = $r + 1;
	link("$cb.$r", "$cb.$n");
	unlink("$cb.$r");
}
link("$basedir/dhcpd.conf", "$basedir/dhcpd.conf.bak.1");
unlink("$basedir/dhcpd.conf");
link ("$basedir/dhcpd.conf.$pid", "$basedir/dhcpd.conf");
unlink ("$basedir/dhcpd.conf.$pid");

`/etc/init.d/dhcpd restart 2>&1 > /dev/null`;
