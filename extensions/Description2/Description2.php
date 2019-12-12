<?php
/**
 * Description2 – Adds meaningful description <meta> tag to MW pages and into the parser output
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @copyright Copyright 2010 – Daniel Friesen
 * @license GPL-2.0-or-later
 * @link https://www.mediawiki.org/wiki/Extension:Description2 Documentation
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Description2' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Description2'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for Description2 extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the Description2 extension requires MediaWiki 1.25+' );
}
