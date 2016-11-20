<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die;
}

define( 'PAGE_IMAGES_INSTALLED', true );

$wgExtensionCredits['api'][] = array(
	'path'           => __FILE__,
	'name'           => 'PageImages',
	'descriptionmsg' => 'pageimages-desc',
	'author'         => 'Max Semenik',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:PageImages',
	'license-name'   => 'WTFPL',
);

$wgAutoloadClasses['ApiQueryPageImages'] = __DIR__ . '/includes/ApiQueryPageImages.php';
$wgAutoloadClasses['PageImages'] = __DIR__ . '/includes/PageImages.php';
$wgAutoloadClasses['PageImages\Hooks\LinksUpdateHookHandler']
	= __DIR__ . '/includes/LinksUpdateHookHandler.php';
$wgAutoloadClasses['PageImages\Hooks\ParserFileProcessingHookHandlers']
	= __DIR__ . '/includes/ParserFileProcessingHookHandlers.php';

$wgMessagesDirs['PageImages'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['PageImages'] = __DIR__ . "/PageImages.i18n.php";

$wgHooks['ParserMakeImageParams'][]
	= 'PageImages\Hooks\ParserFileProcessingHookHandlers::onParserMakeImageParams';
$wgHooks['LinksUpdate'][] = 'PageImages\Hooks\LinksUpdateHookHandler::onLinksUpdate';
$wgHooks['OpenSearchXml'][] = 'PageImages::onApiOpenSearchSuggest';
$wgHooks['ApiOpenSearchSuggest'][] = 'PageImages::onApiOpenSearchSuggest';
$wgHooks['InfoAction'][] = 'PageImages::onInfoAction';
$wgHooks['AfterParserFetchFileAndTitle'][]
	= 'PageImages\Hooks\ParserFileProcessingHookHandlers::onAfterParserFetchFileAndTitle';
$wgHooks['SpecialMobileEditWatchlist::images'][] = 'PageImages::onSpecialMobileEditWatchlist_images';

$wgHooks['UnitTestsList'][] = function( array &$paths ) {
	$paths[] = __DIR__ . '/tests/phpunit';
};

$wgAPIPropModules['pageimages'] = 'ApiQueryPageImages';

/**
 * Configures how various aspects of image affect its score
 */
$wgPageImagesScores = array(
	/** position of image in article */
	'position' => array( 8, 6, 4, 3 ),
	/** image width as shown on page */
	'width' => array(
		119 => -100, // Very small images are usually from maintenace or stub templates
		400 => 10,
		600 => 5, // Larger images are panoramas, less suitable
		601 => 0,
	),
	/** real width of a gallery image */
	'galleryImageWidth' => array(
		99 => -100,
		100 => 0,
	),
	/** width/height ratio, in tenths */
	'ratio' => array(
		3 => -100,
		5 => 0,
		20 => 5,
		30 => 0,
		31 => -100,
	),
	'rights' => array(
		'nonfree' => -100, // don't show nonfree images
	),
);

$wgPageImagesBlacklist = array(
	array(
		'type' => 'db',
		'page' => 'MediaWiki:Pageimages-blacklist',
		'db' => false, // current wiki
	),
	/*
	array(
		'type' => 'db',
		'page' => 'MediaWiki:Pageimages-blacklist',
		'db' => 'commonswiki',
	),
	array(
		'type' => 'url',
		'url' => 'http://example.com/w/index.php?title=somepage&action=raw',
	),
	 */
);

/**
 * How long blacklist cache lives
 */
$wgPageImagesBlacklistExpiry = 60 * 15;

/**
 * Whether this extension's image information should be used by OpenSearch
 */
$wgPageImagesExpandOpenSearchXml = false;

/**
 * Collect data only for these namespaces
 */
$wgPageImagesNamespaces = array( NS_MAIN );

/**
 * If set to true, allows selecting images from galleries as page images
 */
$wgPageImagesUseGalleries = false;
