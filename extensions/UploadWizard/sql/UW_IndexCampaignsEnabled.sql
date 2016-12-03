-- Since we sort on campaign_id for pagination
CREATE INDEX /*i*/uw_campaigns_enabled ON /*_*/uw_campaigns (campaign_enabled, campaign_id);
