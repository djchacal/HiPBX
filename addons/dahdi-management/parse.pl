#!/usr/bin/perl

use DBI;

# Grab SQL details from /etc/hipbx.d/hipbx.conf
open (FH, "/etc/hipbx.d/hipbx.conf");

my $muser = "hipbx";
my $mpass = undef;
my $mdb = "hipbx";
while (<FH>) {
   if (/MYSQLPASS="(.+)"/) { $mpass = $1 }
}
close FH;

my $dbh = DBI->connect("DBI:mysql:$mdb", $muser, $mpass) 
	|| die "Could not connect to DB: $DBI::errstr";

open (FH, "sudo /usr/sbin/dahdi_hardware -v|") or die "Cannot run sudo /usr/sbin/dahdi_hardware -v $!";
#open (FH, "/tmp/a");

# Grab all of the file and stick it in an array.
@astribanks = <FH>;

# Create MySQL Tables if they don't exist.
&create_tables;

# Nuke all the existing spans. 
$dbh->do('delete from provis_dahdi_spans');
my $count=0;

while (my $line = shift @astribanks) {
	chomp($line);
	# usb:003/005          xpp_usb+     e4e4:1162 Astribank-modular FPGA-firmware
	if ($line =~ /^usb:(\d\d\d\/\d\d\d)/) {
		$count++;
		# Start of an Astribank. 
		$usb = $1;
		# Grab the next 4 lines:
		#  MPP: TWINSTAR_PORT=0
		shift @astribanks;
		#  MPP: TWINSTAR_WATCHDOG=0
		shift @astribanks;
		#  MPP: TWINSTAR_POWER[0]=1
		$power1 = shift @astribanks;
		if ($power1 !~ /MPP: TWINSTAR_POWER\[0\]=(\d)/) {
			die "Parse error on power1 of usb $usb - have $power1";
		} else {
			$p1 = $1;
		}
			
		#  MPP: TWINSTAR_POWER[1]=1
		$power2 = shift @astribanks;
		if ($power2 !~ /MPP: TWINSTAR_POWER\[1\]=(\d)/) {
			die "Parse error on power2 of usb $usb";
		} else {
			$p2 = $1;
		}
		# Now, lets grab the serial number
		#  LABEL=[usb:X1046341]       CONNECTOR=@usb-0000:04:01.2-2.2
		$serline = shift @astribanks;
		if ($serline !~ /LABEL=\[usb:(.+)\]/) {
			die "Unable to parse LABEL line from $serline on usb $usb";
		} else {
			$serial = $1;
		}

		# Load it into MySQL if we don't already know about it
		&load_astribank($serial, $usb, $p1, $p2);
		
		# Finally, lets grab all the XPDs
		while (1) {
			my $xpd = shift @astribanks;
			if ($xpd !~ /XBUS-\d\d\/XPD-(\d\d): (.+) .+\((\d+)\)/) {
				unshift @astribanks, $xpd;
				last;	
			} else {
				# This is an XBUS Line, we want to care about the span
				# ports, and type
				$xpd_number=$1;
				$type=$2;
				$ports=$3;
				if ($xpd =~ /.+Span (\d+)/) {
					&load_span($1, $xpd_number, $ports, $serial, $type);
				} else {
					&load_span(-1, $xpd_number, $ports, $serial, $type);
				}
			}
		}
	}
}

print "Imported $count Astribanks";

sub load_astribank($$$$) {
	my ($sno, $usb, $p1, $p2) = @_;
	# Does this Astibank already exist?
	my $sth = $dbh->prepare('SELECT * from provis_dahdi_astribanks where serial=?');
	$sth->execute($sno);
	if ($sth->rows == 0) {
		$sth = $dbh->prepare('insert into provis_dahdi_astribanks(serial, usbport, power1, power2) values (?, ?, ?, ?)');
		$sth->execute($sno, $usb, $p1, $p2);
	} else {
		$sth = $dbh->prepare('update provis_dahdi_astribanks set usbport=?, power1=?, power2=? where serial=?');
		$sth->execute($usb, $p1, $p2, $sno);
	}
		
	# Need to give it an ordering, too
	$sth = $dbh->prepare('SELECT * from provis_dahdi_astribanks_layout where serial=?');
	$sth->execute($sno);
	if ($sth->rows == 0) {
		$dbh->do('insert into provis_dahdi_astribanks_layout (serial, disporder) values ("'.$sno.'", 0)');
		$dbh->do("update provis_dahdi_astribanks_layout set disporder = ( select (max(x.disporder)+1) from ( select * from provis_dahdi_astribanks_layout ) as x )  where serial = '$sno'");
	}
	
}

sub load_span($$$$$) {
	my ($span, $xpd, $ports, $serial, $type) = @_;
	my $sth = $dbh->prepare('insert into provis_dahdi_spans(xpd, span, serial, ports, type) values (?, ?, ?, ?, ?)');
	$sth->execute($xpd, $span, $serial, $ports, $type);
}


sub create_tables() {
	$dbh->do('CREATE TABLE IF NOT EXISTS `provis_dahdi_spans` ( `serial` char(15) DEFAULT NULL, `span` int(11) DEFAULT NULL, `ports` int(11) DEFAULT NULL, `xpd` char(5) DEFAULT NULL, `type` char(10) default NULL)'); 
	$dbh->do('CREATE TABLE IF NOT EXISTS `provis_dahdi_astribanks` ( `serial` char(15) DEFAULT NULL, `usbport` char(15) DEFAULT NULL, `power1` int(11) DEFAULT NULL, `power2` int(11) DEFAULT NULL)');
	$dbh->do('CREATE TABLE IF NOT EXISTS `provis_dahdi_astribanks_layout` ( `serial` char(15) DEFAULT NULL, disporder int(11) DEFAULT NULL )');
	$dbh->do('CREATE TABLE IF NOT EXISTS `provis_dahdi_ports` ( `serial` char(15) DEFAULT NULL, `xpd` int(11) DEFAULT NULL, `portno` int(11) DEFAULT NULL, `ext` char(10) DEFAULT NULL, `tone` char(5) DEFAULT "AU")');
}
