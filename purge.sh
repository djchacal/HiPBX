#!/bin/bash
. /etc/hipbx.conf
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'master'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'slave'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'cluster'"
mysql -p$MYSQLPASS -e"drop user 'hipbx'@'localhost'"
mysqladmin -f -p$MYSQLPASS drop hipbx
for lv in /dev/mapper/*-drbd*
  do
    lvremove -f $lv
  done



