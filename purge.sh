#!/bin/bash
. /etc/hipbx.conf

for x in /drbd/*; do
	umount $x
done
for x in `crm configure show | grep ^primitive | awk '{print $2}'`; do
	crm resource stop $x
done

crm configure erase

/etc/init.d/pacemaker stop
/etc/init.d/corosync stop
rm -f /var/lib/heartbeat/crm/*
for x in /etc/drbd.d/*.res
 do
  echo yes|drbdadm wipe-md `echo $x|sed 's_/etc/drbd.d/\(.*\).res_\1_'`
 done
rm -f /etc/drbd.d/*.res
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'master'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'slave'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'cluster'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'localhost'"
mysqladmin -f -p$MYSQLPASS drop hipbx
for lv in /dev/mapper/*-drbd*
  do
    lvremove -f $lv
  done
rm -f /etc/hipbx.d



