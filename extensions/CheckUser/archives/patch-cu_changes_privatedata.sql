-- Add column for storing encrypted private data
-- vim: autoindent syn=mysql sts=2 sw=2
-- Replace /*$wgDBprefix*/ with the proper prefix

ALTER TABLE /*$wgDBprefix*/cu_changes
  ADD COLUMN (`cuc_private` MEDIUMBLOB default NULL);
