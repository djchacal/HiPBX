#!/bin/bash

yum groupinstall "Development tools"
yum install atrpms-repo    # For fxload, iksemel and spandsp
yum install epel-release # for php-pear-DB, soon to be removed as a prereq.
yum install libusb-devel 
yum install fxload
yum install iksemel iksemel-devel
yum install httpd php php-fpdf
yum install mysql-server
yum install curl
yum install mysql mysql-devel
yum install php-pear-DB php-process
yum install libxml2-devel ncurses-devel libtiff-devel libogg-devel
yum install libvorbis vorbis-tools

