<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Google Analytics Integration',
	'version' => '3.0.1',
	'author' => array(
		'Tim Laqua',
		'[https://www.mediawiki.org/wiki/User:DavisNT Davis Mosenkovs]'
	),
	'descriptionmsg' => 'googleanalytics-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Google_Analytics_Integration',
	'license-name' => 'GPL-2.0+'
);

$wgMessagesDirs['googleAnalytics'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['googleAnalytics'] = __DIR__ . '/googleAnalytics.i18n.php';

/*** Default configuration ***/

// Google Universal Analytics account id (e.g. "UA-12345678-1")
$wgGoogleAnalyticsAccount = '';

// Don't store last octet (or last 80 bits of IPv6 address) in Google Universal Analytics
// For more info see https://support.google.com/analytics/answer/2763052?hl=en
$wgGoogleAnalyticsAnonymizeIP = true;

// HTML code for other web analytics (can be used along with Google Universal Analytics)
$wgGoogleAnalyticsOtherCode = '';

// Array with NUMERIC namespace IDs where web analytics code should NOT be included.
$wgGoogleAnalyticsIgnoreNsIDs = array();

// Array with page names (see magic word {{FULLPAGENAME}}) where web analytics code should NOT be included.
$wgGoogleAnalyticsIgnorePages = array();

// Array with special pages where web analytics code should NOT be included.
$wgGoogleAnalyticsIgnoreSpecials = array( 'Userlogin', 'Userlogout', 'Preferences', 'ChangePassword' );

/* WARNING! The following options were removed in version 3.0:
 *   $wgGoogleAnalyticsAddASAC
 *   $wgGoogleAnalyticsIgnoreSysops
 *   $wgGoogleAnalyticsIgnoreBots
 * It is possible (and advised) to use 'noanalytics' permission to exclude specific groups from web analytics. */

/*****************************/

$wgAutoloadClasses['GoogleAnalyticsHooks'] = __DIR__ . '/googleAnalytics.hooks.php';
$wgHooks['SkinAfterBottomScripts'][] = 'GoogleAnalyticsHooks::onSkinAfterBottomScripts';
$wgHooks['UnitTestsList'][] = 'GoogleAnalyticsHooks::onUnitTestsList';
