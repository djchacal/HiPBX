MODULE_NAME=zapauto
NAME=freepbx-module-$(MODULE_NAME)
MODULES_DIR=/var/www/html/admin/modules/
BIN_DIR=/var/lib/asterisk/bin
SHARE_FILES=detect_zap fix_ast_db zap2amp zap.template
SPEC_FILE=$(NAME).spec
SPEC_TEMPLATE=$(SPEC_FILE).in
MODULE_CONF=module.xml
MODULE_TEMPLATE=$(MODULE_CONF).in
TEMPLATED_FILES=$(SPEC_FILE) $(MODULE_CONF)
# VERSION and AUTHOR are take from the top debian/changelog entry.
# RELEASE is taken from the field in the .spec.in (rpm) file.
# DATE is generated for now.
VERSION:=$(shell head -n 1 debian/changelog | sed -e 's/.*:\([^-]*\)-.*/\1/')
AUTHOR:=$(shell  grep '^ --' debian/changelog | head -n 1 | sed -e 's/^ -- //' -e 's/>.*/>/')
DATE:=$(shell date +'%a %b %e %Y')
#VERSION:=$(shell awk '/^Version: /{print $$2}' $(SPEC_FILE))
RELEASE:=$(shell awk '/^Release: /{print $$2}' $(SPEC_TEMPLATE))
export VERSION RELEASE AUTHOR DATE
DESTDIR=

SUDOERS=/etc/sudoers
LINE=asterisk ALL=NOPASSWD:$(BIN_DIR)/detect_zap
LINE2=asterisk ALL=NOPASSWD:/etc/init.d/asterisk

TARBALL		= $(NAME)-$(VERSION).tar.gz
RPM_BASE	= $(NAME)-$(VERSION)-$(RELEASE).noarch.rpm
SRPM_BASE	= $(NAME)-$(VERSION)-$(RELEASE).src.rpm
RPM		= RPMS/noarch/$(RPM_BASE)
SRPM		= SRPMS/$(SRPM_BASE)

############

.PHONY: install uninstall forceuninstall remove_share_files

all:
	@echo "freepbx-module-zapauto installer"
	@echo " "
	@echo "usage: make install - install the module"
	@echo "       make uninstall - remove the module"
	@echo " "
	@echo "The uninstall target will not remove the PHP files used"
	@echo "by the web interface. You should remove the module from"
	@echo "freepbx gui manually."
	@echo " "
	@echo " "
	@echo "For more information, please contact support@xorcom.com"
	@echo " "
	@echo " "

installfiles:
	mkdir -p $(DESTDIR)/$(BIN_DIR)
	mkdir -p $(DESTDIR)/$(MODULES_DIR)$(MODULE_NAME)
		
#	install support files
	for i in $(SHARE_FILES); do cp -a share/$$i $(DESTDIR)/$(BIN_DIR); done
	
#	install the main module
	for j in php sql sqlite html xml; do \
		for i in *.$$j; do if [ -f $$i ]; then cp -a $$i $(DESTDIR)/$(MODULES_DIR)$(MODULE_NAME); fi done; \
	done

patchfiles:
#	patch the installed files
	sed -i -e 's|/usr/share/freepbx-common|/var/lib/asterisk/bin|g' $(DESTDIR)/$(BIN_DIR)/detect_zap
	sed -i -e 's|/usr/share/asterisk/bin|/var/lib/asterisk/bin|g'   $(DESTDIR)/$(BIN_DIR)/detect_zap
	sed -i -e 's|/usr/share/freepbx-common|/var/lib/asterisk/bin|g' $(DESTDIR)/$(BIN_DIR)/zap2amp
	sed -i -e 's|/usr/share/asterisk/bin|/var/lib/asterisk/bin|g'   $(DESTDIR)/$(BIN_DIR)/zap2amp
	sed -i -e 's|/usr/share/freepbx|/var/www/html|g'                $(DESTDIR)/$(BIN_DIR)/fix_ast_db
	sed -i -e 's|/usr/share/freepbx-common|/var/lib/asterisk/bin|g' $(DESTDIR)/$(MODULES_DIR)/$(MODULE_NAME)/*.php

install: installfiles patchfiles
#	setup sudo
	yum install sudo
	if ! fgrep -q "$(LINE)"  $(SUDOERS); then echo "$(LINE)"  >> $(SUDOERS); fi
	if ! fgrep -q "$(LINE2)" $(SUDOERS); then echo "$(LINE2)" >> $(SUDOERS); fi
	


uninstall: remove_share_files
	echo "Please remove the module from freePBX gui"

forceuninstall: remove_share_files
	rm -fr $(DESTDIR)/$(MODULES_DIR)/$(MODULE_NAME)

REVISION=$(shell svnversion -c . | cut -d: -f2)
FILENAME=freepbx-module-zapauto-$(VERSION).tar.gz
#TMPDIR=$(shell mktemp -d -p /tmp/ freepbx-module-zapauto-XXXXXX)
TMPDIR=/tmp/freepbx-module/

dist: $(FILENAME)
$(FILENAME): $(TEMPLATED_FILES)
	rm -fr $(TMPDIR)
	mkdir -p $(TMPDIR)
	svn export . $(TMPDIR)/freepbx-module-zapauto-$(VERSION)
	cp -a $(TEMPLATED_FILES) $(TMPDIR)/freepbx-module-zapauto-$(VERSION)/
	tar -czf  $(FILENAME) -C $(TMPDIR)/ .
	rm -fr $(TMPDIR)

packs:
	@ls $(SPEC_FILE) $(TARBALL) $(SRPM) $(RPM) 

$(TEMPLATED_FILES): %: %.in
	perl -p -e 's/@([A-Z]+)@/$$ENV{$$1}/g' $^ >$@

rpm: $(TEMPLATED_FILES) $(FILENAME)
	./rpmer -ba $(SPEC_FILE)

####################################################3
$(DESTDIR)/$(MODULES_DIR)/$(MODULE_NAME):
	mkdir -p $(DESTDIR)/$(MODULES_DIR)/$(MODULE_NAME)

remove_share_files:
	# clean up sudo
	fgrep -v "$(LINE)"  /etc/sudoers > /etc/sudoers.tmp && cp /etc/sudoers.tmp /etc/sudoers && rm /etc/sudoers.tmp
	fgrep -v "$(LINE2)" /etc/sudoers > /etc/sudoers.tmp && cp /etc/sudoers.tmp /etc/sudoers && rm /etc/sudoers.tmp
	
	# remove support scripts
	#mv /usr/local/sbin/genzaptelconf.trixbox /usr/local/sbin/genzaptelconf
	for i in $(SHARE_FILES); do rm -f  $(DESTDIR)/$(BIN_DIR)/$$i; done

.PHONY: dist rpm all packs install uninstall forceuninstall remove_share_files
