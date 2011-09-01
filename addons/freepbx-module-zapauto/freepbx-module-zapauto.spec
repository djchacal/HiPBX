Summary: FreePBX module - Zaptel AutoConfiguration
Name: freepbx-module-zapauto
Version: 0.7.5
Release: 1
License: GPL
Group: Applications/System
URL: http://www.freepbx.org/
Source: freepbx-module-zapauto-%{version}.tar.gz
Packager: Tzafrir Cohen <tzafrir.cohen@xorcom.com>
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root
BuildArch: noarch
Requires: asterisk, sudo

# TODO:
# when freepbx will be packaged into RPMS, this will be used
#Requires: asterisk, sudo, freepbx-common 

%description 
This is a freepbx-module for autoconfiguration of Zaptel hardware.

%prep
%setup

%install
[ "%{buildroot}" != '/' ] && rm -rf %{buildroot}
#pwd - TODO, really? is this pwd needed?
make DESTDIR=%{buildroot} installfiles
make DESTDIR=%{buildroot} patchfiles

%post
SUDOERS=/etc/sudoers
LINE="asterisk ALL=NOPASSWD:/var/lib/asterisk/bin/detect_zap"
if ! fgrep -q "$LINE" /etc/sudoers; then echo "$LINE" >> $SUDOERS; fi

%preun
# this should be run only on removals, not on upgrades
# see http://www.rpm.org/hintskinks/buildtree/mdk-rpm/#UPGRADE
if [ $1 = 0 ]; then
	SUDOERS=/etc/sudoers
	LINE="asterisk ALL=NOPASSWD:/var/lib/asterisk/bin/detect_zap"
	fgrep -v "$LINE"  $SUDOERS > ${SUDOERS}.tmp && cp ${SUDOERS}.tmp $SUDOERS && rm ${SUDOERS}.tmp
fi

%files 
%defattr(-,root,root)
%doc LICENSE
%doc README
/var/www/html/admin/modules/zapauto/
/var/lib/asterisk/bin/detect_zap
/var/lib/asterisk/bin/fix_ast_db
/var/lib/asterisk/bin/zap2amp
/var/lib/asterisk/bin/zap.template

%changelog
* Mon Apr 26 2010 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.7.5-1
- Created from SVN file $Id: freepbx-module-zapauto.spec.in 7810 2010-04-26 10:34:48Z tzafrir $
- Change texts from AMPortal to freePBX (about time)
- Change texts from zaptel to DAHDI (about time)
- Added support for freePBX 2.7 .
- Also include the field 'dial', and busydetect-related fields.
- Also set 'mohclass' for FreePBX 2.7 .
- freepbx_version is a number, not a string.
- Updated documentation a little bit. Still a mess.
- Call and pickup groups are set to 1 by default, call pickup is enabled from this configuration
  (this is different then freePBX's default - but exposes a non working feature to the end user)

* Sun Mar 28 2010 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.7.4-1
- Change the default base exetnsion number from 401 to 201.

* Mon Apr 20 2009 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.7.3-1
- DAHDI support

* Wed Mar 18 2009 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.7.2-1
- Run xpp_sync after ztcfg in detect_zap.
- Fix version in module.xml.

* Sun Mar 08 2009 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.7.1-1
- Use new zapconf (if available) instead of legacy genzaptelconf.
- Update Packager field.
- Trixbox compatibility.
- Don't generate extensions for NT spans.
- Fix voicemail generation in zap.template.

* Wed Jan 21 2009 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.7.0-1
- Support for FreePBX 2.5.0 .
- In certain circumstances, after clicking the "Run ZAPTEL detection" 
  button the final screen didn't appear until Asterisk was not stopped  
  manually. Fixed. 
- Support for the ASTRIBANK_IOPORTS_ENABLED parameter was broken. Fixed. 
  This parameter may be defined in the /etc/amportal.conf. When it is 
  defined with value 'no' or 'false' then extensions for input/output 
  ports of Astribank FXS device will not be created. 

* Sun May  4 2008 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.6.9-1
- Support 'bchan=1-2,3-4' (used e.g. for E1).

* Thu Jan 30 2008 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.6.8-1
- Support FreePBX 2.4 .
- Update web interface text.

* Thu Jan 30 2008 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.6.7.1-1
- Add a silly sleep.

* Tue Jan 15 2008 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.6.7-1
- Remove some FXO-only values and echotraining from the template.

* Tue Aug 14 2007 Diego Iastrubni <diego.iastrubni@xorcom.com> - 0.6.6-1
- Initial support for FreePBX 2.3

* Thu Jul  9 2007 Tzafrir Cohen <tzafrir.cohen@xorcom.com> - 0.6.5-1
- New release.
- The input/output ports of the Astribank have an explicit name now.
- You can disable input-output ports by setting
  ASTRIBANK_IOPORTS_ENABLED=0 in /etc/amportal.conf .

* Wed Apr 18 2007  Diego Iastrubni <diego.iastrubni@xorcom.com> - 0.6.4-1
- Restart of asterisk + zaptel is done only once
- Script will change colors:
  - blue means running
  - green means ended ok
  - red means ended with a problem
- Updated freePBX configuration to reflect new version (forgotten in last 
  update)

* Sun Apr 15 2007 Diego Iastrubni <diego.iastrubni@xorcom.com> - 0.6.3-1
- Code supports freePBX 2.1 and 2.2

* Sun Apr 1 2007 Diego Iastrubni <diego.iastrubni@xorcom.com> - 0.6.2-1
- New release
- Better, smarter configuration bootstrap
- Astribank BRI detection has been added

* Tue Feb 20 2007 Diego Iastrubni <diego.iastrubni@xorcom.com> - 0.6.1-1
- New release
- Less vebose configuration
- Default of the echo canceller is now "yes" instead of "8"

* Thu Nov 22 2006 Diego Iastrubni <diego.iastrubni@xorcom.com> - 0.6.0-1
- New release
- Targetting version 2.2 of freePBX

* Thu Nov 22 2006 Diego Iastrubni <diego.iastrubni@xorcom.com> - 0.5.5-1
- New release
- Code + spec cleanups

* Thu Oct 8 2006 Diego Iastrubni <diego.iastrubni@xorcom.com>
- New release
- zaptel.conf and zapata-*.conf are set a+r on detection
- When new hardware is discovered, you will hear 2 beeps (if the application "beep" is installed)

* Thu Aug 13 2006 Diego Iastrubni <diego.iastrubni@xorcom.com>
- First release

