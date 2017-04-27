<?php
/**
 * Lazyload extension
 *
 * @file
 * @ingroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) { die(); }

// credits
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Lazyload',
	'version' => '0.2.5',
	'author' => array( 'Mudkip' ),
	'url' => 'https://github.com/mudkipme/mediawiki-lazyload',
	'descriptionmsg'  => 'lazyload-desc',
);

$wgExtensionMessagesFiles['Lazyload'] = dirname( __FILE__ ) . '/Lazyload.i18n.php';
$wgAutoloadClasses['Lazyload'] = dirname( __FILE__ ) . '/Lazyload.class.php';

$wgResourceModules['ext.lazyload'] = array(
	'scripts' => array('lazyload.js' ),
	'dependencies' => array( 'mediawiki.hidpi' ),
	'localBasePath' => dirname( __FILE__ ) . '/modules',
	'remoteExtPath' => 'Lazyload/modules',
	'targets' => array( 'desktop', 'mobile' ),
);

$wgHooks['LinkerMakeExternalImage'][] = 'Lazyload::LinkerMakeExternalImage';
$wgHooks['ThumbnailBeforeProduceHTML'][] = 'Lazyload::ThumbnailBeforeProduceHTML';
$wgHooks['BeforePageDisplay'][] = 'Lazyload::BeforePageDisplay';
