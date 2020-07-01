<?php

/**
 * DismissableSiteNotice extension - allows users to dismiss (hide)
 * the sitenotice.
 *
 * @file
 * @ingroup Extensions
 * @version 1.1
 * @author Brion Vibber
 * @author Kevin Israel
 * @author Dror S.
 * @license GPL-2.0-or-later
 * @link http://www.mediawiki.org/wiki/Extension:DismissableSiteNotice Documentation
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'DismissableSiteNotice' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['DismissableSiteNotice'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for DismissableSiteNotice extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the DismissableSiteNotice extension requires MediaWiki 1.25+' );
}
