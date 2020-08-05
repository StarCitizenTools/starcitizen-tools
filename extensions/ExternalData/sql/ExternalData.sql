CREATE TABLE /*_*/ `ed_url_cache` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `url` varchar(255) NOT NULL,
  `post_vars` text,
  `req_time` int(11) NOT NULL,
  `result` longtext,
  UNIQUE KEY `id` (`id`),
  KEY `url` (`url`)
) /*$wgDBTableOptions*/;