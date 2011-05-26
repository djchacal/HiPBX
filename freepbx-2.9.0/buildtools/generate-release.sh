#!/bin/bash 
# use this script like:
# md5-amp_conf.sh 1.10.00X

# WARNING: THIS IS HARDCODED TO THE 2.3 BRANCH
#
# TODO: SHOULD CHECK THE VERSION OF THE MODULES BEFORE PROCEEDING
#
module_url="http://www.freepbx.org/v2/svn/modules/branches/2.8"
core_url=${module_url}/core
framework_url=${module_url}/framework
dashboard_url=${module_url}/dashboard
customappsreg_url=${module_url}/customappsreg
voicemail_url=${module_url}/voicemail
recordings_url=${module_url}/recordings
music_url=${module_url}/music
featurecodeadmin_url=${module_url}/featurecodeadmin
infoservices_url=${module_url}/infoservices

case "$1" in
	?*)

ver=$1

cd ..

# Make sure everything is up-to-date
svn update

# Now make sure javascript library reflects all the changes
cd buildtools
./pack_javascripts.php
cd ..
svn ci --message "Auto checkin packed libfreepbx.javascripts.js as part of build process" amp_conf/htdocs/admin/common/libfreepbx.javascripts.js

# This adds the MD5 Sum for all the relevant files that gets checked in on the next steps below
#
cd amp_conf
find agi-bin  astetc  bin htdocs  htdocs_panel  moh sbin sounds -type f | xargs md5sum | grep -v .svn > ../upgrades/$ver.md5

	;;
	*)

echo "usage: generate-release.sh <version>";
exit

	;;
esac


# Prepare and checkin the MD5 Sum
#
cd ../upgrades
svn add $ver.md5
svn ps svn:mime-type text/plain $ver.md5
svn ps svn:eol-style native $ver.md5
svn ci -m "Creating release $ver"

# Back up to the top, do an svn info to get the URL so we can use it to create a tag
#
cd ..
cur=`svn info | grep URL | awk ' { print $2 }'`
svn cp -m "Automatic tag of $ver" $cur http://www.freepbx.org/v2/svn/freepbx/tags/$ver

# Now that the tag is made, we want to add core and framework to the tag so that
# the tag reflects the tarball. Then we will use the tag to generate the releases
#
svn cp -m "Automatic packaging of core with $ver"             $core_url             http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of framework with $ver"        $framework_url        http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of dashboard with $ver"        $dashboard_url        http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of customappsreg with $ver"    $customappsreg_url    http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of voicemail with $ver"        $voicemail_url        http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of recordings with $ver"       $recordings_url       http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of music with $ver"            $music_url            http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of featurecodeadmin with $ver" $featurecodeadmin_url http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/
svn cp -m "Automatic packaging of infoservices with $ver"     $infoservices_url     http://www.freepbx.org/v2/svn/freepbx/tags/${ver}/amp_conf/htdocs/admin/modules/

# Now clear out the release diretory where we will build the tarballs and grab it from the tag to get core and framework
#
mkdir -p /usr/src/freepbx-release
rm -rf /usr/src/freepbx-release/freepbx-$ver

# Use the tag to build the tarball
#
svn export http://www.freepbx.org/v2/svn/freepbx/tags/$ver /usr/src/freepbx-release/freepbx-$ver

cd /usr/src/freepbx-release
tar zcvf freepbx-$ver.tar.gz freepbx-$ver

######################################################################################### 
# We don't make a full version with modules anymore so comment this out
# cd freepbx-$ver/amp_conf/htdocs/admin/modules/
# . ./import.sh
# 
# # import should not bring in core and framework, those were removed from its list
# #
# find . -name .svn -exec rm -rf {} \;
# cd /usr/src/freepbx-release
# tar zcvf freepbx-$ver-withmodules.tar.gz freepbx-$ver
#
######################################################################################### 
