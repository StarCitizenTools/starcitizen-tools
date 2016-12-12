-- PostgreSQL version of the database schema for the Upload Wizard extension.
-- Licence: GNU GPL v2+
-- Author: Jeroen De Dauw < jeroendedauw@gmail.com >, Jeff Janes < jeff.janes@gmail.com >

-- Upload wizard campaigns
-- This is *not* the primary storage for campaigns.
-- Just stores a copy of information that is already present in the
-- appropriate wikipages, for easier indexing / querying
CREATE SEQUENCE uw_campaigns_campaign_id_seq;
CREATE TABLE uw_campaigns (
  campaign_id              INTEGER PRIMARY KEY NOT NULL DEFAULT nextval('uw_campaigns_campaign_id_seq'),
  campaign_name            VARCHAR(255)        NOT NULL,
  campaign_enabled         INTEGER             NOT NULL default '0'
);

CREATE UNIQUE INDEX uw_campaigns_name ON uw_campaigns (campaign_name);
-- Since we sort on campaign_id for pagination
CREATE INDEX uw_campaigns_enabled ON uw_campaigns (campaign_enabled, campaign_id);
