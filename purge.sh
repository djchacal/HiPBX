#!/bin/bash
. /etc/hipbx.conf

crm configure erase

/etc/init.d/pacemaker stop
/etc/init.d/corosync stop
for x in /dev/mapper/*drbd*
 do
  drbdmeta --force 2 v08 $x internal wipe-md
 done
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'master'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'slave'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'cluster'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'localhost'"
mysqladmin -f -p$MYSQLPASS drop hipbx
for lv in /dev/mapper/*-drbd*
  do
    lvremove -f $lv
  done
rm -f /etc/hipbx.d/ssh_key*



