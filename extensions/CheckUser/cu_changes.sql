-- Tables for the CheckUser extension
-- vim: autoindent syn=mysql sts=2 sw=2

CREATE TABLE /*_*/cu_changes (
  -- Primary key
  cuc_id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,

  -- When pages are renamed, their RC entries do _not_ change.
  cuc_namespace int NOT NULL default '0',
  cuc_title varchar(255) binary NOT NULL default '',

  -- user.user_id
  cuc_user INTEGER NOT NULL DEFAULT 0,
  cuc_user_text VARCHAR(255) NOT NULL DEFAULT '',

  -- Edit summary
  cuc_actiontext varchar(255) binary NOT NULL default '',
  cuc_comment varchar(255) binary NOT NULL default '',
  cuc_minor bool NOT NULL default '0',

  -- Key to page_id (was cur_id prior to 1.5).
  -- This will keep links working after moves while
  -- retaining the at-the-time name in the changes list.
  cuc_page_id int(10) unsigned NOT NULL default '0',

  -- rev_id of the given revision
  cuc_this_oldid int(10) unsigned NOT NULL default '0',

  -- rev_id of the prior revision, for generating diff links.
  cuc_last_oldid int(10) unsigned NOT NULL default '0',

  -- Edit/new/log
  cuc_type tinyint(3) unsigned NOT NULL default '0',

  -- Event timestamp
  cuc_timestamp CHAR(14) NOT NULL default '',

  -- IP address, visible
  cuc_ip VARCHAR(255) NULL default '',

  -- IP address as hexidecimal
  cuc_ip_hex VARCHAR(255) default NULL,

  -- XFF header, visible, all data
  cuc_xff VARCHAR(255) BINARY NULL default '',

  -- XFF header, last IP, as hexidecimal
  cuc_xff_hex VARCHAR(255) default NULL,

  -- User agent
  cuc_agent VARCHAR(255) BINARY default NULL,

  -- Private Data
  cuc_private MEDIUMBLOB default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/cuc_ip_hex_time ON /*_*/cu_changes (cuc_ip_hex,cuc_timestamp);
CREATE INDEX /*i*/cuc_user_ip_time ON /*_*/cu_changes (cuc_user,cuc_ip,cuc_timestamp);
CREATE INDEX /*i*/cuc_xff_hex_time ON /*_*/cu_changes (cuc_xff_hex,cuc_timestamp);
CREATE INDEX /*i*/cuc_timestamp ON /*_*/cu_changes (cuc_timestamp);
