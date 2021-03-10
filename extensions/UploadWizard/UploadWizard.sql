-- MySQL version of the database schema for the Upload Wizard extension.
-- Licence: GNU GPL v2+
-- Author: Jeroen De Dauw < jeroendedauw@gmail.com >

-- Upload wizard campaigns
-- This is *not* the primary storage for campaigns.
-- Just stores a copy of information that is already present in the
-- appropriate wikipages, for easier indexing / querying
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/uw_campaigns (
  campaign_id              INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
  campaign_name            VARCHAR(255)        NOT NULL,
  campaign_enabled         TINYINT             NOT NULL default '0'
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/uw_campaigns_name ON /*_*/uw_campaigns (campaign_name);
-- Since we sort on campaign_id for pagination
CREATE INDEX /*i*/uw_campaigns_enabled ON /*_*/uw_campaigns (campaign_enabled, campaign_id);
