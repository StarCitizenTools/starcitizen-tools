<?php
/**
 * RSS-Feed MediaWiki extension
 *
 * @file
 * @ingroup Extensions
 * @version 2.25.0
 * @author mutante, Daniel Kinzler, Rdb, Mafs, Thomas Gries, Alxndr, Chris Reigrut, K001
 * @author Kellan Elliott-McCrea <kellan@protest.net> -- author of MagpieRSS
 * @author Jeroen De Dauw
 * @author Jack Phoenix <jack@countervandalism.net>
 * @copyright © Kellan Elliott-McCrea <kellan@protest.net>
 * @copyright © mutante, Daniel Kinzler, Rdb, Mafs, Thomas Gries, Alxndr, Chris Reigrut, K001
 * @link https://www.mediawiki.org/wiki/Extension:RSS Documentation
 */

define( "EXTENSION_RSS_VERSION", "2.25.0" );

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'RSS feed',
	'author' => array( 'Kellan Elliott-McCrea', 'mutante', 'Daniel Kinzler',
		'Rdb', 'Mafs', 'Alxndr', 'Thomas Gries', 'Chris Reigrut',
		'K001', 'Jack Phoenix', 'Jeroen De Dauw', 'Mark A. Hershberger',
		'...'
	),
	'version' => EXTENSION_RSS_VERSION,
	'url' => 'https://www.mediawiki.org/wiki/Extension:RSS',
	'descriptionmsg' => 'rss-desc',
	'license-name' => 'GPL-2.0+'
);

// Internationalization file and autoloadable classes
$wgMessagesDirs['RSS'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['RSS'] = __DIR__ . '/RSS.i18n.php';
$wgAutoloadClasses['RSSHooks'] = __DIR__ . '/RSSHooks.php';
$wgAutoloadClasses['RSSParser'] = __DIR__ . '/RSSParser.php';
$wgAutoloadClasses['RSSUtils'] = __DIR__ . '/RSSParser.php';
$wgAutoloadClasses['RSSData'] = __DIR__ . '/RSSData.php';

// List tracking category on Special:TrackingCategories
$wgTrackingCategories[] = 'rss-tracking-category';

$wgHooks['ParserFirstCallInit'][] = 'RSSHooks::parserInit';

// one hour
$wgRSSCacheAge = 3600;

// Check cached content, if available, against remote.
// $wgRSSCacheCompare should be set to false or a timeout
// (less than $wgRSSCacheAge) after which a comparison will be made.
// for debugging set $wgRSSCacheCompare = 1;
$wgRSSCacheCompare = false;

// 15 second timeout
$wgRSSFetchTimeout = 15;

// Ignore the RSS tag in all but the namespaces listed here.
// null (the default) means the <rss> tag can be used anywhere.
$wgRSSNamespaces = null;

// Whitelist of allowed RSS Urls
//
// If there are items in the array, and the user supplied URL is not in the array,
// the url will not be allowed
//
// Urls are case-sensitively tested against values in the array.
// They must exactly match including any trailing "/" character.
//
// Warning: Allowing all urls (not setting a whitelist)
// may be a security concern.
//
// an empty or non-existent array means: no whitelist defined
// this is the default: an empty whitelist. No servers are allowed by default.
$wgRSSUrlWhitelist = array();

// include "*" if you expressly want to allow all urls (you should not do this)
// $wgRSSUrlWhitelist = array( "*" );

// Maximum number of redirects to follow (defaults to 0)
// Note: this should only be used when the target URLs are trusted,
// to avoid attacks on intranet services accessible by HTTP.
$wgRSSUrlNumberOfAllowedRedirects = 0;

// Agent to use for fetching feeds
$wgRSSUserAgent = "MediaWikiRSS/" . strtok( EXTENSION_RSS_VERSION, " " ) .
	" (+http://www.mediawiki.org/wiki/Extension:RSS) / MediaWiki RSS extension";

// Proxy server to use for fetching feeds
$wgRSSProxy = false;

// default date format of item publication dates see http://www.php.net/date
$wgRSSDateDefaultFormat = "Y-m-d H:i:s";

// limit the number of characters in the item description
// or set to false for unlimited length.
// THIS IS CURRENTLY NOT WORKING (bug 30377)
$wgRSSItemMaxLength = false;

// You can choose to allow active links in feed items; default: false
$wgRSSAllowLinkTag = false;

// If you want to allow images (HTML <img> tag) in RSS feeds; default: false
$wgRSSAllowImageTag = false;
