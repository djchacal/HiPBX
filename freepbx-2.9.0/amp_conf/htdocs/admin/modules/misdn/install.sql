--
-- freepbx-misdn -- mISDN module for FreePBX
--
-- Copyright (C) 2006, Thomas Liske.
--
-- Thomas Liske <thomas.liske@beronet.com>
--
-- This program is free software, distributed under the terms of
-- the GNU General Public License Version 2.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--

-- the rows have to be in sync with $confkeys in page.mgroups.php
CREATE TABLE IF NOT EXISTS `misdn_groups` (
  `name` varchar(30) NOT NULL,
  `type` smallint,
  `msns` varchar(60),
  `echocancel` smallint,
  `immediate` smallint,
  `method` varchar(20),
  `pmp_l1_check` smallint,
  `txgain` smallint,
  `dialplan` smallint,
  `nationalprefix` varchar(10),
  `internationalprefix` varchar(10),
  `language` varchar(10),
  `callgroup` int,
  `pickupgroup` int,
  `senddtmf` smallint,
  `reject_cause` int,
  PRIMARY KEY  (`name`)
);

CREATE TABLE IF NOT EXISTS `misdn_ports` (
  `port` int,
  `group` varchar(30) NOT NULL,
  PRIMARY KEY  (`port`)
);

CREATE TABLE IF NOT EXISTS `misdn` (
  `id` varchar(20) NOT NULL default '-1',
  `keyword` varchar(30) NOT NULL default '',
  `data` varchar(150) NOT NULL default '',
  `flags` int(1) NOT NULL default '0',
  PRIMARY KEY  (`id`,`keyword`)
);

INSERT INTO `misdn` (`id`, `keyword`, `data`) VALUES ('XXXXXX', 'bridging', '0');
